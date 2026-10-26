#!/usr/bin/env python3
"""
scripts/migrar_colunas_auditoria_ia.py

Adiciona as colunas de metadados para a Sentinela Gemini nas tabelas
'apostas' e 'fixtures_trends', de forma idempotente (IF NOT EXISTS).
Respeita a regra de NÃO alterar o campo 'status' principal ('Pendente').
"""

import sys
import os

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection

def column_exists(cursor, table_name, column_name):
    cursor.execute("""
        SELECT COUNT(*) as cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
    """, (table_name, column_name))
    row = cursor.fetchone()
    return (row.get('cnt', 0) if isinstance(row, dict) else row[0]) > 0

def run_migration():
    print("Iniciando migração de colunas de auditoria da IA...")
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # 1. Colunas em 'apostas'
            cols_apostas = [
                ("auditoria_ia_status", "VARCHAR(30) NULL DEFAULT NULL"),
                ("auditoria_ia_resumo", "VARCHAR(255) NULL DEFAULT NULL"),
                ("auditoria_ia_parecer", "TEXT NULL DEFAULT NULL"),
                ("auditoria_ia_checked_at", "DATETIME NULL DEFAULT NULL")
            ]
            for col, col_type in cols_apostas:
                if not column_exists(cursor, 'apostas', col):
                    sql = f"ALTER TABLE apostas ADD COLUMN {col} {col_type}"
                    print(f"Executando: {sql}")
                    cursor.execute(sql)
                else:
                    print(f"Coluna apostas.{col} já existe.")

            # 2. Colunas em 'fixtures_trends'
            cols_ft = [
                ("auditoria_ia_status", "VARCHAR(30) NULL DEFAULT NULL"),
                ("auditoria_ia_resumo", "VARCHAR(255) NULL DEFAULT NULL"),
                ("auditoria_ia_parecer", "TEXT NULL DEFAULT NULL"),
                ("auditoria_ia_checked_at", "DATETIME NULL DEFAULT NULL")
            ]
            for col, col_type in cols_ft:
                if not column_exists(cursor, 'fixtures_trends', col):
                    sql = f"ALTER TABLE fixtures_trends ADD COLUMN {col} {col_type}"
                    print(f"Executando: {sql}")
                    cursor.execute(sql)
                else:
                    print(f"Coluna fixtures_trends.{col} já existe.")

        conn.commit()
        print("✅ Migração concluída com sucesso!")
    finally:
        conn.close()

if __name__ == '__main__':
    run_migration()
