#!/usr/bin/env python3
"""
Script de Estorno Financeiro e Cancelamento Seguro das Apostas de Gols Asiáticos de Sábado (21/11).
Executa o estorno idempotente na tabela conta_corrente e atualiza o status na tabela apostas.
"""

import sys
import os
import pymysql

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection

def estornar_apostas_sabado():
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    try:
        # Selecionar as apostas criadas hoje para o mercado de Gols Asiáticos cujos jogos ocorrem no sábado (2026-11-21)
        cursor.execute("""
            SELECT id, usuario_id, fixture_id, time_casa, time_fora, valor_aposta, moeda_codigo, status, data_hora_jogo, resultado_detalhado
            FROM apostas
            WHERE mercado = 'Gols Asiáticos'
              AND status = 'Pendente'
              AND DATE(criado_em) = CURDATE()
              AND DATE(CONVERT_TZ(data_hora_jogo, '+00:00', '-03:00')) > CURDATE()
            ORDER BY id ASC
        """)
        apostas_para_cancelar = cursor.fetchall()

        print(f"🔍 Encontradas {len(apostas_para_cancelar)} apostas antecipadas de sábado para cancelamento e estorno.")

        estornadas_count = 0
        total_estornado = 0.0

        for ap in apostas_para_cancelar:
            ap_id = ap['id']
            uid = ap['usuario_id']
            val = float(ap['valor_aposta'])
            moeda = ap['moeda_codigo'] or 'USDT'
            desc_aposta = f"{ap['time_casa']} x {ap['time_fora']}"

            # Verificar se já não foi estornado
            cursor.execute("""
                SELECT id FROM conta_corrente
                WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'
                LIMIT 1
            """, (uid, ap_id))
            ja_estornado = cursor.fetchone()

            if not ja_estornado:
                # Obter saldo atual do usuário
                cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
                u_row = cursor.fetchone()
                saldo_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
                saldo_pos = round(saldo_ant + val, 8)

                # Registrar ESTORNO_APOSTA na conta corrente
                cursor.execute("""
                    INSERT INTO conta_corrente (
                        usuario_id, aposta_id, tipo, descricao, valor,
                        saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                    ) VALUES (
                        %s, %s, 'ESTORNO_APOSTA', %s, %s, %s, %s, %s, NOW()
                    )
                """, (
                    uid, ap_id,
                    f"Estorno de Aposta #{ap_id} ({desc_aposta}) - Recalibração de Maturidade Gatekeeper Gols",
                    val, saldo_ant, saldo_pos, moeda
                ))

                # Atualizar saldo do usuário
                cursor.execute("""
                    UPDATE usuario 
                    SET saldo_conta_corrente = %s 
                    WHERE id = %s
                """, (saldo_pos, uid))

                print(f"💰 [Estorno CC] Aposta #{ap_id} ({desc_aposta}): +{val:.2f} {moeda} estornados. Saldo: {saldo_ant:.2f} -> {saldo_pos:.2f} {moeda}")
                total_estornado += val

            # Atualizar status da aposta para Cancelada
            novo_detalhe = f"Cancelada pelo Gatekeeper para reprocessamento com novas travas de maturidade || {ap.get('resultado_detalhado') or ''}"
            cursor.execute("""
                UPDATE apostas
                SET status = 'Cancelada',
                    status_gatekeeper = 'CANCELADA',
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (novo_detalhe, ap_id))

            estornadas_count += 1

        conn.commit()
        print(f"✅ Concluído com sucesso! {estornadas_count} apostas canceladas. Total de fundos estornados: {total_estornado:.2f} USDT.")

    except Exception as e:
        conn.rollback()
        print(f"❌ Erro durante o estorno: {e}", file=sys.stderr)
        raise
    finally:
        cursor.close()
        conn.close()

if __name__ == '__main__':
    estornar_apostas_sabado()
