#!/usr/bin/env python3
"""
Script de Reconciliação de Créditos em Conta Corrente (Apostas do dia 26/08/2026)
Executado para garantir que todas as apostas ganhas/resolvidas em 26/08/2026
tenham o correspondente registro CREDITO_RETORNO_APOSTA na tabela 'conta_corrente' e saldo atualizado.
"""

import sys
import os
import pymysql
from datetime import datetime, date

def get_db_connection():
    hosts_ports = [
        ("mysql", 3306),
        ("127.0.0.1", 23306),
        ("localhost", 3306)
    ]
    for host, port in hosts_ports:
        try:
            conn = pymysql.connect(
                host=host,
                port=port,
                user="root",
                password="YM11rMrT32xH0E6N",
                database="footballweb",
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
            print(f"✅ [Reconciliação Créditos 26/08] Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ [ERRO CRÍTICO] Falha ao conectar no MySQL.")
    sys.exit(1)

def creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor, status, descricao=None):
    """
    Credita o retorno de uma aposta resolvida/ganha/anulada na conta_corrente e atualiza usuario.saldo_conta_corrente.
    Possui checagem anti-duplicidade (idempotência) para evitar creditar a mesma aposta duas vezes.
    """
    try:
        valor = float(valor or 0.0)
        if valor <= 0 or not usuario_id:
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

        saldo_posterior = round(saldo_anterior + valor, 2)

        cursor.execute("""
            INSERT INTO conta_corrente (usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em)
            VALUES (%s, %s, 'CREDITO_RETORNO_APOSTA', %s, %s, %s, %s, NOW())
        """, (usuario_id, aposta_id, descricao, valor, saldo_anterior, saldo_posterior))

        cursor.execute("""
            UPDATE usuario
            SET saldo_conta_corrente = %s
            WHERE id = %s
        """, (saldo_posterior, usuario_id))

        print(f"💰 [Crédito Conta Corrente] Aposta #{aposta_id} -> Creditado R$ {valor:.2f} para Usuário #{usuario_id} (Novo Saldo: R$ {saldo_posterior:.2f})")
        return True
    except Exception as e:
        print(f"⚠️ Erro ao creditar retorno da aposta #{aposta_id} na conta corrente: {e}")
        return False

def reconciliar_apostas_dia_26():
    conn = get_db_connection()
    cursor = conn.cursor()

    target_date_str = "2026-08-26"
    print(f"🔍 [Reconciliação] Buscando apostas resolvidas do dia {target_date_str}...")

    # Buscar apostas de 26/08/2026 que estejam resolvidas (Ganha, Meio Ganha, ANULADA, Meio Perdida, Cashout)
    cursor.execute("""
        SELECT a.* 
        FROM apostas a 
        WHERE a.status IN ('Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Cashout')
          AND (
              DATE(a.criado_em) = %s 
              OR DATE(a.data_hora_jogo) = %s 
              OR DATE(a.processado_em) = %s
          )
        ORDER BY a.id ASC
    """, (target_date_str, target_date_str, target_date_str))

    apostas_26 = cursor.fetchall()
    print(f"📋 Encontradas {len(apostas_26)} apostas resolvidas associadas a {target_date_str}.")

    creditadas = 0
    ja_creditadas = 0

    for aposta in apostas_26:
        aposta_id = aposta['id']
        usuario_id = aposta['usuario_id']
        status = aposta['status']
        cash_out = aposta.get('cash_out')
        ganhos = float(aposta.get('ganhos_potenciais') or 0.0)
        valor_retorno = float(cash_out) if (status == 'Cashout' and cash_out is not None) else ganhos
        time_casa = aposta.get('time_casa', '')
        time_fora = aposta.get('time_fora', '')

        if valor_retorno <= 0:
            continue

        descricao = f"Retorno Aposta #{aposta_id} ({time_casa} x {time_fora} - {status})"
        sucesso = creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor_retorno, status, descricao)

        if sucesso:
            creditadas += 1
        else:
            ja_creditadas += 1

    print("\n=======================================================")
    print(f"✅ RECONCILIAÇÃO DE CRÉDITOS DO DIA 26/08/2026 CONCLUÍDA!")
    print(f"📊 Total Apostas Verificadas: {len(apostas_26)}")
    print(f"💰 Novos Créditos Inseridos: {creditadas}")
    print(f"ℹ️ Já Creditadas Anteriores: {ja_creditadas}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    reconciliar_apostas_dia_26()
