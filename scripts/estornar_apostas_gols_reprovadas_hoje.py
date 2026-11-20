#!/usr/bin/env python3
"""
Script de Estorno Financeiro e Cancelamento Seguro das Apostas de Gols Asiáticos de Hoje (20/11)
que foram reprovadas pelos novos critérios de maturidade do Gatekeeper.
Apostas afetadas:
- #4963: Athletic Club x Espanyol (xG 2.72 > 2.30, EV +7.6% < 8.0%, Prob 59.9% < 62.0%)
- #4937: Puebla x Atletico San Luis (xG 2.48 > 2.30, veto tático por risco ofensivo)
Aposta mantida e promovida a PREMIUM:
- #4962: Palestino x Union La Calera (xG 2.29 <= 2.30, EV +16.1%, Prob 70.0%)
"""

import sys
import os
import pymysql

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection

def estornar_apostas_hoje():
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    try:
        # 1. Obter as duas apostas reprovadas
        ids_para_cancelar = [4937, 4963]
        cursor.execute("""
            SELECT id, usuario_id, fixture_id, time_casa, time_fora, valor_aposta, moeda_codigo, status, resultado_detalhado
            FROM apostas
            WHERE id IN (%s, %s) AND status = 'Pendente'
            ORDER BY id ASC
        """, (ids_para_cancelar[0], ids_para_cancelar[1]))
        apostas_para_cancelar = cursor.fetchall()

        print(f"🔍 Encontradas {len(apostas_para_cancelar)} apostas de hoje para cancelamento e estorno.")

        estornadas_count = 0
        total_estornado = 0.0

        for ap in apostas_para_cancelar:
            ap_id = ap['id']
            uid = ap['usuario_id']
            val = float(ap['valor_aposta'])
            moeda = ap['moeda_codigo'] or 'USDT'
            desc_aposta = f"{ap['time_casa']} x {ap['time_fora']}"

            # Checagem de idempotência
            cursor.execute("""
                SELECT id FROM conta_corrente
                WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'
                LIMIT 1
            """, (uid, ap_id))
            ja_estornado = cursor.fetchone()

            if not ja_estornado:
                # Saldo anterior do usuário
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
                    f"Estorno de Aposta #{ap_id} ({desc_aposta}) - Reprovada pelo Gatekeeper de Gols (Maturidade/Tático)",
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
            novo_detalhe = f"Cancelada pelo Gatekeeper: Reprovada nos critérios de maturidade/perfil tático do novo motor de Gols Asiáticos || {ap.get('resultado_detalhado') or ''}"
            cursor.execute("""
                UPDATE apostas
                SET status = 'Cancelada',
                    status_gatekeeper = 'CANCELADA',
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (novo_detalhe, ap_id))

            estornadas_count += 1

        # 2. Atualizar a aposta #4962 (Palestino x Union La Calera) com sua chancela PREMIUM
        cursor.execute("""
            UPDATE apostas
            SET gatekeeper_category = 'Oportunidade PREMIUM (+EV Alavancado)',
                destaque = 1,
                updated_at = NOW()
            WHERE id = 4962 AND status = 'Pendente'
        """)
        print("👑 Aposta #4962 (Palestino x Union La Calera) atualizada para 'Oportunidade PREMIUM' com destaque.")

        conn.commit()
        print(f"✅ Operação concluída com sucesso! {estornadas_count} apostas canceladas/estornadas. Total devolvido: {total_estornado:.2f} USDT.")

    except Exception as e:
        conn.rollback()
        print(f"❌ Erro durante o estorno: {e}", file=sys.stderr)
        raise
    finally:
        cursor.close()
        conn.close()

if __name__ == '__main__':
    estornar_apostas_hoje()
