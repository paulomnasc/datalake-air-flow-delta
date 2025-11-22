#!/usr/bin/env python3
"""Validator que reutiliza o esquema de conexão usado por `factory_master.py`.

Por padrão usa a Connection do Airflow com `mysql_conn_id` (padrão: "mysql_dag_metadata").
Se o hook do Airflow não estiver disponível, faz fallback para PyMySQL lendo vars de ambiente.

Uso:
  ./run_validator.py --table ingestao_customers_raw [--conn-id mysql_dag_metadata]

Exit codes:
  0 -> tabela encontrada
  2 -> tabela não encontrada
  3 -> erro de conexão
  4 -> dependência ausente
"""

import argparse
import os
import sys

def get_args():
    p = argparse.ArgumentParser()
    p.add_argument("--table", required=True, help="Nome da tabela a validar")
    p.add_argument("--conn-id", default="mysql_dag_metadata", help="Airflow connection id to use (default: mysql_dag_metadata)")
    p.add_argument("--database", help="Optional: override database/schema name (if not provided, uses connection's default)")
    return p.parse_args()


def validate_with_mysql_hook(table: str, conn_id: str, database_override: str | None) -> int:
    """Tenta usar Airflow MySqlHook; retorna exit code como definido acima."""
    try:
        from airflow.providers.mysql.hooks.mysql import MySqlHook
    except Exception as e:
        print(f"MySqlHook not available: {e}", file=sys.stderr)
        return 5

    try:
        hook = MySqlHook(mysql_conn_id=conn_id)
        # Use DATABASE() to refer to the connection's default DB unless overridden
        if database_override:
            sql = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=%s AND table_name=%s LIMIT 1"
            params = (database_override, table)
        else:
            sql = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s LIMIT 1"
            params = (table,)

        records = hook.get_records(sql=sql, parameters=params)
        if records and len(records) > 0:
            print(f"Table '{table}' exists (via conn_id={conn_id})")
            return 0
        else:
            print(f"Table '{table}' NOT found (via conn_id={conn_id})", file=sys.stderr)
            return 2

    except Exception as e:
        print(f"Error when querying via MySqlHook (conn_id={conn_id}): {e}", file=sys.stderr)
        return 3


def validate_with_pymysql(table: str, database: str | None) -> int:
    try:
        import pymysql
    except Exception:
        print("Missing dependency 'pymysql'. Install it in the worker image (pip install PyMySQL).", file=sys.stderr)
        return 4

    host = os.environ.get("MYSQL_HOST", "mysql")
    port = int(os.environ.get("MYSQL_PORT", "3306"))
    user = os.environ.get("MYSQL_USER", "root")
    password = os.environ.get("MYSQL_PASSWORD", "")
    database = database or os.environ.get("MYSQL_DATABASE", "lista_revisao")

    print(f"Connecting to MySQL {user}@{host}:{port}/{database}")
    try:
        conn = pymysql.connect(host=host, port=port, user=user, password=password, database=database, connect_timeout=5)
    except Exception as e:
        print(f"Error connecting to MySQL: {e}", file=sys.stderr)
        return 3

    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema=%s AND table_name=%s LIMIT 1",
                (database, table),
            )
            row = cur.fetchone()
            if row:
                print(f"Table '{table}' exists in database '{database}'")
                return 0
            else:
                print(f"Table '{table}' NOT found in database '{database}'", file=sys.stderr)
                return 2
    finally:
        conn.close()


def main():
    args = get_args()
    table = args.table
    conn_id = args.conn_id
    database_override = args.database

    # 1) Try to use Airflow's MySqlHook (preferred; reuses Airflow connections)
    rc = validate_with_mysql_hook(table, conn_id, database_override)
    if rc != 5:
        # rc != 5 means MySqlHook was available and returned a meaningful result
        sys.exit(rc)

    # 2) Fallback: try PyMySQL using environment variables
    print("Falling back to PyMySQL using environment variables...")
    rc2 = validate_with_pymysql(table, database_override)
    sys.exit(rc2)


if __name__ == "__main__":
    main()
