#!/usr/bin/env python3
"""
Script de Processamento e Liquidação de Apostas em Gols Asiáticos (Airflow DAG Worker / Web Service)
Executado periodicamente para verificar jogos encerrados (FT), comparar placares finais com as linhas de Gols Asiáticos
e atualizar o status ('Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Perdida') e creditar os retornos devidos em 'conta_corrente'.
"""

import sys
import os
import re
import pymysql
from datetime import datetime, timedelta

# Importar configurações e motor de liquidação
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection
from asian_total_engine import settle_asian_total_bet

def creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor, status, descricao=None):
    """
    Credita o retorno de uma aposta resolvida/ganha/anulada na conta_corrente e atualiza usuario.saldo_conta_corrente.
    Possui checagem anti-duplicidade (idempotência) para evitar creditar a mesma aposta duas vezes.
    Apenas apostas confirmadas (confirmada = 1) possuem direito a crédito na conta corrente.
    """
    try:
        valor = float(valor or 0.0)
        if valor <= 0 or not usuario_id:
            return False

        # Checagem de segurança: Apenas apostas confirmadas (confirmada = 1) podem creditar retorno na conta corrente
        if aposta_id:
            cursor.execute("SELECT confirmada FROM apostas WHERE id = %s", (aposta_id,))
            row_aposta = cursor.fetchone()
            if row_aposta and row_aposta.get('confirmada') is not None and int(row_aposta['confirmada']) == 0:
                print(f"ℹ️ [Crédito Ignorado] Aposta #{aposta_id} não é confirmada (confirmada = 0). Saldo em conta corrente mantido.")
                return False

        cursor.execute("""
            SELECT id FROM conta_corrente 
            WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'CREDITO_RETORNO_APOSTA'
            LIMIT 1
        """, (usuario_id, aposta_id))
        if cursor.fetchone():
            return False

        if not descricao:
            descricao = f"Retorno Aposta #{aposta_id} ({status})"

        cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
        row_u = cursor.fetchone()
        if row_u and row_u.get('saldo_conta_corrente') is not None:
            saldo_anterior = float(row_u['saldo_conta_corrente'])
        else:
            cursor.execute("""
                SELECT saldo_posterior FROM conta_corrente 
                WHERE usuario_id = %s 
                ORDER BY id DESC LIMIT 1
            """, (usuario_id,))
            row_cc = cursor.fetchone()
            saldo_anterior = float(row_cc['saldo_posterior']) if row_cc and row_cc.get('saldo_posterior') is not None else 0.0

        moeda_cod = 'BRL'
        if aposta_id:
            cursor.execute("SELECT moeda_codigo FROM apostas WHERE id = %s", (aposta_id,))
            row_ap = cursor.fetchone()
            if row_ap and row_ap.get('moeda_codigo'):
                moeda_cod = row_ap['moeda_codigo']

        saldo_posterior = round(saldo_anterior + valor, 8)

        cursor.execute("""
            INSERT INTO conta_corrente (usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, moeda_codigo, criado_em)
            VALUES (%s, %s, 'CREDITO_RETORNO_APOSTA', %s, %s, %s, %s, %s, NOW())
        """, (usuario_id, aposta_id, descricao, valor, saldo_anterior, saldo_posterior, moeda_cod))

        cursor.execute("""
            UPDATE usuario
            SET saldo_conta_corrente = %s
            WHERE id = %s
        """, (saldo_posterior, usuario_id))

        print(f"💰 [Crédito Conta Corrente] Aposta #{aposta_id} -> Creditado {moeda_cod} {valor} para Usuário #{usuario_id} (Novo Saldo: {moeda_cod} {saldo_posterior})")
        return True
    except Exception as e:
        print(f"⚠️ Erro ao creditar retorno da aposta #{aposta_id} na conta corrente: {e}", file=sys.stderr)
        return False


def processar_apostas_gols_encerradas():
    """
    Busca todas as apostas pendentes de Gols Asiáticos associadas a jogos finalizados (FT)
    e executa a liquidação contábil e estatística.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        print("🔍 [Gols Asiáticos] Iniciando liquidação de apostas de jogos finalizados...")

        cursor.execute("""
            SELECT a.id, a.usuario_id, a.fixture_id, a.time_casa, a.time_fora,
                   a.mercado, a.palpite, a.odd, a.valor_aposta, a.confirmada,
                   a.moeda_codigo, ft.goals_home, ft.goals_away, ft.status as fixture_status,
                   ft.score_processed_at
            FROM apostas a
            JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
            WHERE a.status = 'Pendente'
              AND a.mercado = 'Gols Asiáticos'
              AND ft.status IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED')
              AND ft.goals_home IS NOT NULL
              AND ft.goals_away IS NOT NULL
        """)
        apostas_pendentes = cursor.fetchall()

        if not apostas_pendentes:
            print("ℹ️ [Gols Asiáticos] Nenhuma aposta pendente de jogo finalizado encontrada.")
            return

        print(f"📋 Encontradas {len(apostas_pendentes)} apostas de Gols Asiáticos para liquidar.")

        total_processadas = 0
        total_ganhas = 0
        total_perdidas = 0
        total_anuladas = 0

        for ap in apostas_pendentes:
            aid = ap['id']
            uid = ap['usuario_id']
            fid = ap['fixture_id']
            palpite = ap['palpite']
            odd = float(ap['odd'] or 1.0)
            valor = float(ap['valor_aposta'] or 10.0)
            moeda = ap.get('moeda_codigo') or 'BRL'
            gh = int(ap['goals_home'])
            ga = int(ap['goals_away'])
            total_goals = gh + ga

            # Liquidação através do motor
            novo_status, retorno, lucro, detalhe = settle_asian_total_bet(
                total_goals=total_goals,
                palpite=palpite,
                odd=odd,
                valor_aposta=valor,
                moeda_codigo=moeda
            )

            if novo_status == 'Pendente':
                continue

            cursor.execute("""
                UPDATE apostas
                SET status = %s,
                    ganhos_potenciais = %s,
                    resultado_detalhado = %s
                WHERE id = %s
            """, (novo_status, retorno, detalhe, aid))

            # Se houver retorno financeiro positivo ou reembolso, credita em conta_corrente
            if retorno > 0:
                creditar_retorno_aposta(
                    cursor=cursor,
                    usuario_id=uid,
                    aposta_id=aid,
                    valor=retorno,
                    status=novo_status,
                    descricao=f"Retorno Gols Asiáticos - Aposta #{aid} ({novo_status})"
                )

            total_processadas += 1
            if novo_status in ('Ganha', 'Meio Ganha'):
                total_ganhas += 1
            elif novo_status in ('Perdida', 'Meio Perdida'):
                total_perdidas += 1
            elif novo_status == 'ANULADA':
                total_anuladas += 1

            print(f"🏁 [Aposta #{aid} Liquidada] Partida #{fid}: {detalhe}")

        conn.commit()
        print(f"✅ [Liquidação Concluída] Total processadas: {total_processadas} (Ganhas/Meio: {total_ganhas}, Perdidas/Meio: {total_perdidas}, Anuladas: {total_anuladas})")

    except Exception as e:
        conn.rollback()
        print(f"❌ [Liquidação Gols Asiáticos] Erro: {e}", file=sys.stderr)
        raise
    finally:
        cursor.close()
        conn.close()


if __name__ == '__main__':
    processar_apostas_gols_encerradas()
