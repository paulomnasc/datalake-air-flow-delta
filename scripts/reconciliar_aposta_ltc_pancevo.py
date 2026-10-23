#!/usr/bin/env python3
"""
scripts/reconciliar_aposta_ltc_pancevo.py

Script para reconciliação do retorno da aposta #4656 (IMT Novi Beograd vs Železničar Pančevo)
e sincronização com o saldo real da casa Vave (0.28467921 LTC).
"""

import sys
import os

sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection

def reconciliar():
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        print("🔍 Verificando estado atual das transações #819 e #820 e aposta #4656...")
        cursor.execute("SELECT id, usuario_id, aposta_id, tipo, valor, saldo_anterior, saldo_posterior FROM conta_corrente WHERE id IN (819, 820) ORDER BY id ASC")
        transacoes = cursor.fetchall()
        for t in transacoes:
            print(f"  • Transação #{t['id']}: tipo={t['tipo']}, valor={t['valor']}, saldo_ant={t['saldo_anterior']}, saldo_post={t['saldo_posterior']}")

        cursor.execute("SELECT id, usuario_id, time_casa, time_fora, odd, valor_aposta, ganhos_potenciais, moeda_codigo FROM apostas WHERE id = 4656")
        aposta = cursor.fetchone()
        if aposta:
            print(f"  • Aposta #4656: {aposta['time_casa']} x {aposta['time_fora']} | Stake: {aposta['valor_aposta']} {aposta['moeda_codigo']} @ {aposta['odd']} | Ganhos: {aposta['ganhos_potenciais']}")

        # 1. Atualizar a aposta #4656 para 0.04458025
        cursor.execute("""
            UPDATE apostas
            SET ganhos_potenciais = 0.04458025,
                updated_at = NOW()
            WHERE id = 4656
        """)
        print("✅ Aposta #4656: ganhos_potenciais atualizado para 0.04458025 LTC.")

        # 2. Atualizar transação #819 (Retorno da aposta)
        cursor.execute("""
            UPDATE conta_corrente
            SET valor = 0.04458025,
                saldo_posterior = 0.30719449
            WHERE id = 819
        """)
        print("✅ Transação #819: valor atualizado para +0.04458025 LTC e saldo_posterior para 0.30719449 LTC.")

        # 3. Atualizar transação #820 (Débito da aposta seguinte Torino x Monza)
        cursor.execute("""
            UPDATE conta_corrente
            SET saldo_anterior = 0.30719449,
                saldo_posterior = 0.28467921
            WHERE id = 820
        """)
        print("✅ Transação #820: saldo_anterior atualizado para 0.30719449 LTC e saldo_posterior para 0.28467921 LTC.")

        # 4. Atualizar saldo_conta_corrente do usuário
        if aposta and aposta.get('usuario_id'):
            uid = aposta['usuario_id']
            cursor.execute("""
                UPDATE usuario
                SET saldo_conta_corrente = 0.28467921
                WHERE id = %s
            """, (uid,))
            print(f"✅ Usuário #{uid}: saldo_conta_corrente atualizado para 0.28467921 LTC (100% alinhado com a Vave).")

        conn.commit()
        print("\n🎉 Reconciliação concluída com sucesso!")
    except Exception as e:
        conn.rollback()
        print(f"❌ Erro durante reconciliação: {e}")
        raise e
    finally:
        conn.close()

if __name__ == '__main__':
    reconciliar()
