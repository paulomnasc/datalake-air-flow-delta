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
    p.add_argument("--key", help="Optional: MinIO/S3 key (object path) to check instead of MySQL table")
    p.add_argument("--bucket", help="Optional: MinIO bucket name (defaults to env MINIO_BUCKET or 'lab01')")
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


def check_minio_object(key: str, bucket: str | None = None) -> int:
    """Verifica se o objeto/key existe no MinIO/S3.
    Tenta primeiro usar S3Hook (Airflow), senão usa o cliente `minio`.
    Retorna 0 se encontrado, 2 se não encontrado, 4 se falta dependência, 3 se erro de conexão.
    """
    bucket = bucket or os.environ.get("MINIO_BUCKET", "lab01")

    # 1) Try Airflow S3Hook
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        try:
            hook = S3Hook(aws_conn_id='minio_conn')
            exists = hook.check_for_key(key, bucket_name=bucket)
            if exists:
                print(f"Found object '{key}' in bucket '{bucket}' via S3Hook")
                return 0
            else:
                print(f"Object '{key}' not found in bucket '{bucket}' via S3Hook", file=sys.stderr)
                return 2
        except Exception as e:
            print(f"Error when checking S3 via S3Hook: {e}", file=sys.stderr)
            # fall through to minio client
    except Exception:
        # S3Hook not available; continue to minio client
        pass

    # 2) Try python 'minio' client
    try:
        from minio import Minio
        from minio.error import S3Error
    except Exception:
        print("Missing dependency 'minio' or S3Hook. Install 'apache-airflow-providers-amazon' or 'minio' package.", file=sys.stderr)
        return 4

    endpoint = os.environ.get("MINIO_ENDPOINT", os.environ.get("MINIO_HOST", "http://minio:9000"))
    # Normalize endpoint to host:port without schema for Minio client
    if endpoint.startswith("http://"):
        endpoint_host = endpoint.replace("http://", "")
    elif endpoint.startswith("https://"):
        endpoint_host = endpoint.replace("https://", "")
    else:
        endpoint_host = endpoint

    access_key = os.environ.get("MINIO_ACCESS_KEY", os.environ.get("MINIO_ROOT_USER", "admin"))
    secret_key = os.environ.get("MINIO_SECRET_KEY", os.environ.get("MINIO_ROOT_PASSWORD", "admin123"))
    secure = os.environ.get("MINIO_SECURE", "false").lower() in ("1", "true", "yes")

    try:
        client = Minio(endpoint_host, access_key=access_key, secret_key=secret_key, secure=secure)
        try:
            client.stat_object(bucket, key)
            print(f"Found object '{key}' in bucket '{bucket}' via Minio client")
            return 0
        except S3Error as e:
            if getattr(e, 'code', None) in ('NoSuchKey', 'NoSuchBucket') or 'not found' in str(e).lower():
                print(f"Object '{key}' not found in bucket '{bucket}' via Minio client", file=sys.stderr)
                return 2
            else:
                print(f"Error when checking object via Minio client: {e}", file=sys.stderr)
                return 3
    except Exception as e:
        print(f"Error connecting to MinIO endpoint '{endpoint_host}': {e}", file=sys.stderr)
        return 3


def main():
    args = get_args()
    table = args.table
    conn_id = args.conn_id
    database_override = args.database
    key = args.key
    bucket = args.bucket

    # If a MinIO key is provided, check MinIO first and exit accordingly
    if key:
        rc_key = check_minio_object(key, bucket)
        sys.exit(rc_key)

    # 1) Try to use Airflow's MySqlHook (preferred; reuses Airflow connections)
    rc = validate_with_mysql_hook(table, conn_id, database_override)
    if rc != 5:
        # rc != 5 means MySqlHook was available and returned a meaningful result
        if rc == 0:
            sys.exit(0)
        # If not found in MySQL (rc == 2) then try MinIO checks (the target may be a file)
        if rc == 2:
            # try to find object in MinIO with the given key
            # common keys to try: exact name, trusted/{name}, trusted/{name}.parquet
            candidates = [table, f"trusted/{table}", f"trusted/{table}.parquet", f"{table}.parquet"]
            for key in candidates:
                rc_minio = check_minio_object(key)
                if rc_minio == 0:
                    sys.exit(0)
            # none found, exit with original rc (2)
            sys.exit(2)
        # other error codes: propagate
        sys.exit(rc)

    # 2) Fallback: try PyMySQL using environment variables
    print("Falling back to PyMySQL using environment variables...")
    rc2 = validate_with_pymysql(table, database_override)
    sys.exit(rc2)


if __name__ == "__main__":
    main()
