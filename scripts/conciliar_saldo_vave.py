#!/usr/bin/env python3
"""
scripts/conciliar_saldo_vave.py

Script para ajuste de conciliação bancária do saldo de conta corrente com a casa Vave.
Divergência: 0.06506916 LTC proveniente das apostas:
- #4763 (Progreso vs Defensor) -> Estorno indevido (+0.02251528)
- #4760 (Elche vs Valencia) -> Estorno indevido (+0.02251528)
- #4749 (Cambuur vs GO Ahead Eagles) -> Lucro não ocorrido na Vave (+0.02003860)

Saldo Anterior: 0.28557981 LTC
Ajuste (RESGATE_CREDITO): -0.06506916 LTC
Saldo Posterior: 0.22051065 LTC (100% alinhado com a carteira Vave)
"""

import sys
import os
from datetime import datetime

sys.path.append(os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection

def conciliar():
    conn = get_db_connection()
    cursor = conn.cursor()

    usuario_id = 558
    ajuste = 0.06506916
    saldo_esperado_final = 0.22051065

    try:
        cursor.execute("SELECT id, saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
        user = cursor.fetchone()
        if not user:
            print(f"❌ Usuário #{usuario_id} não encontrado.")
            return

        saldo_atual = float(user['saldo_conta_corrente'])
        print(f"🔍 Usuário #{usuario_id}: Saldo atual em conta = {saldo_atual:.8f} LTC")

        saldo_posterior = round(saldo_atual - ajuste, 8)
        print(f"📉 Ajuste a aplicar: -{ajuste:.8f} LTC")
        print(f"🎯 Novo saldo projetado: {saldo_posterior:.8f} LTC (Esperado: {saldo_esperado_final:.8f} LTC)")

        if abs(saldo_posterior - saldo_esperado_final) > 1e-7:
            print(f"⚠️ Divergência no saldo projetado: {saldo_posterior:.8f} vs {saldo_esperado_final:.8f}. Abortando por segurança.")
            return

        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        descricao = "Ajuste de Conciliação Vave: Correção de liquidações (#4763 Progreso, #4760 Elche e #4749 Cambuur)"

        # 1. Inserir registro no extrato conta_corrente
        cursor.execute("""
            INSERT INTO conta_corrente (
                usuario_id, aposta_id, tipo, moeda_codigo, descricao,
                valor, saldo_anterior, saldo_posterior, criado_em
            ) VALUES (%s, NULL, 'RESGATE_CREDITO', 'LTC', %s, %s, %s, %s, %s)
        """, (usuario_id, descricao, -ajuste, saldo_atual, saldo_posterior, now))

        # 2. Atualizar saldo_conta_corrente do usuário
        cursor.execute("""
            UPDATE usuario
            SET saldo_conta_corrente = %s
            WHERE id = %s
        """, (saldo_posterior, usuario_id))

        conn.commit()
        print(f"✅ Transação de conciliação registrada com sucesso no extrato!")
        print(f"✅ Saldo do usuário #{usuario_id} atualizado para {saldo_posterior:.8f} LTC.")
        print(f"🎉 Conta Corrente 100% sincronizada com a Vave!")

    except Exception as e:
        conn.rollback()
        print(f"❌ Erro durante a conciliação: {e}")
        raise e
    finally:
        conn.close()

if __name__ == '__main__':
    conciliar()

