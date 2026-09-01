#!/usr/bin/env python3
"""
Script de Restauração de Apostas Confirmadas Canceladas por DAGs
Busca apostas com confirmada = 1 que foram indevidamente alteradas para status = 'Cancelada'
por abstenção automática de IA ou oscilação de odds/linhas e restaura seu status para 'Pendente'.
"""

import sys
import pymysql

def get_db_connection():
    hosts_ports = [
        ("127.0.0.1", 23306),
        ("mysql", 3306),
        ("localhost", 3306)
    ]
    for host, port in hosts_ports:
        try:
            conn = pymysql.connect(
                host=host, port=port, user="root", password="YM11rMrT32xH0E6N",
                database="footballweb", charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue
    print("❌ Falha ao conectar no MySQL.")
    sys.exit(1)

def main():
    conn = get_db_connection()
    cursor = conn.cursor()

    print("🔍 Auditando apostas confirmadas (confirmada = 1) com status 'Cancelada'...")

    cursor.execute("""
        SELECT id, usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
               valor_aposta, confirmada, status, resultado_detalhado, updated_at
        FROM apostas
        WHERE confirmada = 1 AND status = 'Cancelada'
    """)
    mislabeled_bets = cursor.fetchall()

    print(f"📋 Encontradas {len(mislabeled_bets)} apostas confirmadas indevidamente canceladas.")

    if not mislabeled_bets:
        print("✨ Nenhuma aposta confirmada cancelada indevidamente foi encontrada.")
        conn.close()
        return

    restored_count = 0
    for bet in mislabeled_bets:
        bet_id = bet['id']
        home = bet['time_casa']
        away = bet['time_fora']
        palpite = bet['palpite']
        motivo_anterior = bet['resultado_detalhado']
        
        print(f"  -> Restaurando Aposta #{bet_id} [{home} x {away}] ({palpite}) de 'Cancelada' -> 'Pendente'...")
        
        cursor.execute("""
            UPDATE apostas
            SET status = 'Pendente',
                resultado_detalhado = NULL,
                updated_at = NOW()
            WHERE id = %s
        """, (bet_id,))
        restored_count += 1

    print(f"✅ Sucesso! {restored_count} apostas confirmadas foram restauradas para status 'Pendente'.")
    conn.close()

if __name__ == '__main__':
    main()
