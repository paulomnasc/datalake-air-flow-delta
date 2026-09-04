#!/usr/bin/env python3
"""
Script de Sanitização de Dados para o Mercado de Handicap Asiático
Reclassifica registros legados de 'Vitória [Time]' na tabela 'apostas' que foram
erroneamente atribuídos ao mercado 'Handicap Asiático' para 'Resultado Final (1X2)',
higienizando o histórico e restaurando a fidelidade estatística do dashboard de desempenho.
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

    print("🔍 Auditando palpites na tabela 'apostas' no mercado 'Handicap Asiático'...")

    # Buscar apostas com mercado 'Handicap Asiático' cujos palpites começam com 'Vitória' ou não possuem linha de handicap
    cursor.execute("""
        SELECT id, fixture_id, time_casa, time_fora, palpite, mercado, status
        FROM apostas
        WHERE mercado = 'Handicap Asiático'
          AND (palpite LIKE 'Vitória %' OR palpite LIKE 'Vitoria %')
    """)
    mislabeled_bets = cursor.fetchall()

    print(f"📋 Encontradas {len(mislabeled_bets)} apostas marcadas com 'Vitória [Time]' no mercado de Handicap Asiático.")

    if not mislabeled_bets:
        print("✨ Nenhuma aposta desalinhada encontrada. A base já está limpa!")
        conn.close()
        return

    updated_count = 0
    for bet in mislabeled_bets:
        bet_id = bet['id']
        palpite = bet['palpite']
        print(f"  -> Reclassificando aposta #{bet_id} ('{palpite}') de 'Handicap Asiático' -> 'Resultado Final (1X2)'...")
        
        cursor.execute("""
            UPDATE apostas
            SET mercado = 'Resultado Final (1X2)',
                updated_at = NOW()
            WHERE id = %s
        """, (bet_id,))
        updated_count += 1

    print(f"✅ Sucesso! {updated_count} apostas foram reclassificadas para 'Resultado Final (1X2)'.")
    conn.close()

if __name__ == '__main__':
    main()
