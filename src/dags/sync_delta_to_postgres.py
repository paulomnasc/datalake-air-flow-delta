"""
DAG para sincronizar Delta Lake → PostgreSQL para Power BI.
Lê tabelas Delta do MinIO e insere em PostgreSQL.
Power BI conecta direto no PostgreSQL (sem locks, múltiplos acessos nativos).
"""
from datetime import datetime, timedelta
from airflow import DAG
from airflow.operators.python import PythonOperator
import duckdb
import psycopg2
from psycopg2 import sql
import re
import os

# Configurações
POSTGRES_HOST = 'postgres-bi'
POSTGRES_PORT = 5432
POSTGRES_DB = 'datalake_bi'
POSTGRES_USER = 'pbi_user'
POSTGRES_PASSWORD = 'pbi_password'

MINIO_ENDPOINT = 'minio:9000'
MINIO_ACCESS_KEY = 'admin'
MINIO_SECRET_KEY = 'admin123'
# MINIO_BUCKET será obtido dinamicamente: kwargs -> env -> fallback 'lab01'

default_args = {
    'owner': 'airflow',
    'depends_on_past': False,
    'email_on_failure': False,
    'email_on_retry': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}


def setup_postgres(**context):
    """Conecta PostgreSQL e cria esquema."""
    try:
        print(f"\n🔗 Conectando ao PostgreSQL...")
        conn = psycopg2.connect(
            host=POSTGRES_HOST,
            port=POSTGRES_PORT,
            database=POSTGRES_DB,
            user=POSTGRES_USER,
            password=POSTGRES_PASSWORD
        )
        cursor = conn.cursor()
        print(f"✅ Conectado a PostgreSQL: {POSTGRES_HOST}:{POSTGRES_PORT}/{POSTGRES_DB}")
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"❌ ERRO ao conectar: {str(e)}")
        raise


def sync_delta_to_postgres(**context):
    """
    Descobre tabelas Delta, lê via DuckDB, insere em PostgreSQL.
    """
    # Bucket isolado por usuário - prioridades:
    # 1. Parâmetro passado na trigger (dag_run.conf)
    # 2. Params da DAG
    # 3. Variável de ambiente
    # 4. Fallback lab01
    
    dag_run = context.get('dag_run')
    bucket = None
    
    # 1. Tenta obter de dag_run.conf (quando disparada pela webapp ou trigger manual)
    if dag_run and dag_run.conf:
        bucket = dag_run.conf.get('bucket_name') or dag_run.conf.get('bucket')
    
    # 2. Tenta params da DAG
    if not bucket:
        bucket = context.get('params', {}).get('bucket_name')
    
    # 3. Variável de ambiente
    if not bucket:
        bucket = os.environ.get("MINIO_BUCKET")
    
    # 4. Fallback
    if not bucket:
        bucket = "lab01"
    
    print(f"\n🗂️  Usando bucket: {bucket}")
    print(f"   Fonte: {'dag_run.conf' if dag_run and dag_run.conf and dag_run.conf.get('bucket_name') else 'params/env/fallback'}")
    
    # Construir paths dinamicamente
    search_globs = [
        f's3://{bucket}/delta/*/*.parquet',
        f's3://{bucket}/delta/*/*/*.parquet',
    ]
    
    duckdb_con = None
    pg_conn = None
    try:
        print(f"\n🔌 Criando conexão DuckDB em-memória...")
        duckdb_con = duckdb.connect(':memory:')
        
        # Configura DuckDB
        print("📡 Instalando e carregando httpfs...")
        duckdb_con.execute("INSTALL httpfs;")
        duckdb_con.execute("LOAD httpfs;")
        duckdb_con.execute(f"SET s3_endpoint='{MINIO_ENDPOINT}';")
        duckdb_con.execute(f"SET s3_access_key_id='{MINIO_ACCESS_KEY}';")
        duckdb_con.execute(f"SET s3_secret_access_key='{MINIO_SECRET_KEY}';")
        duckdb_con.execute("SET s3_use_ssl=false;")
        duckdb_con.execute("SET s3_url_style='path';")
        print("✅ DuckDB configurado")
        
        # Descobre pastas
        print("\n📊 Descobrindo estrutura no MinIO...")
        folders = set()
        for search_path in search_globs:
            try:
                print(f"  🔍 Procurando em: {search_path}")
                rows = duckdb_con.execute(f"""
                    SELECT DISTINCT regexp_extract(filename, '.*/delta/([^/]+)/.*', 1) AS folder
                    FROM read_parquet('{search_path}', filename=true)
                    WHERE folder IS NOT NULL AND folder <> ''
                """).fetchall()
                for (folder,) in rows:
                    folders.add(folder)
            except Exception as e:
                print(f"    ⚠️  Falha: {str(e)[:80]}")
        
        if not folders:
            print("\n⚠️  Nenhuma pasta encontrada")
            return
        
        print(f"\n📂 Pastas: {', '.join(sorted(folders))}")
        
        # Conecta PostgreSQL
        print("\n🔗 Conectando a PostgreSQL...")
        pg_conn = psycopg2.connect(
            host=POSTGRES_HOST,
            port=POSTGRES_PORT,
            database=POSTGRES_DB,
            user=POSTGRES_USER,
            password=POSTGRES_PASSWORD
        )
        pg_cursor = pg_conn.cursor()
        print("✅ PostgreSQL conectado")
        
        # Para cada pasta, insere em PostgreSQL
        print(f"\n💾 Inserindo tabelas em PostgreSQL...")
        results = []
        
        for folder in sorted(folders):
            safe_name = re.sub(r"[^a-zA-Z0-9_]+", "_", folder)
            table_name = f"delta_{safe_name}"
            
            # Tenta os dois padrões
            for search_path in [
                f"s3://{bucket}/delta/{folder}/*.parquet",
                f"s3://{bucket}/delta/{folder}/*/*.parquet",
            ]:
                try:
                    print(f"  📥 {table_name} ← {folder}")
                    
                    # Lê Parquet com DuckDB
                    duckdb_con.execute(f"SELECT * FROM read_parquet('{search_path}') LIMIT 0")
                    columns = [desc[0] for desc in duckdb_con.description]
                    
                    result_df = duckdb_con.execute(f"""
                        SELECT * FROM read_parquet('{search_path}')
                    """).fetchall()
                    
                    df = result_df
                    count = len(df)
                    
                    # Cria tabela em PostgreSQL (trunca se existir)
                    col_defs = ", ".join([f'"{col}" TEXT' for col in columns])
                    pg_cursor.execute(f"DROP TABLE IF EXISTS {table_name} CASCADE;")
                    pg_cursor.execute(f"""
                        CREATE TABLE {table_name} (
                            {col_defs}
                        )
                    """)
                    
                    # Insere dados
                    if df:
                        placeholders = ", ".join(["%s"] * len(columns))
                        insert_sql = f"""
                            INSERT INTO {table_name} ({", ".join([f'"{col}"' for col in columns])})
                            VALUES ({placeholders})
                        """
                        for row in df:
                            pg_cursor.execute(insert_sql, row)
                    
                    pg_conn.commit()
                    
                    print(f"  ✅ {table_name}: {count} registros")
                    results.append({'table': table_name, 'status': 'OK', 'count': count})
                    break
                    
                except Exception as e:
                    print(f"    ⚠️  Erro: {str(e)[:100]}")
                    continue
            
            if not any(r['table'] == table_name for r in results):
                print(f"  ⚠️  {table_name}: FALHOU")
                results.append({'table': table_name, 'status': 'FAILED'})
        
        success = sum(1 for r in results if r['status'] == 'OK')
        print(f"\n✅ Sincronização: {success}/{len(results)} OK")
        context['task_instance'].xcom_push(key='sync_results', value=results)
        
    except Exception as e:
        print(f"\n❌ ERRO: {str(e)}")
        import traceback
        traceback.print_exc()
        raise
    finally:
        if duckdb_con:
            try:
                duckdb_con.close()
            except:
                pass
        if pg_conn:
            try:
                pg_conn.close()
            except:
                pass


def verify_postgres_tables(**context):
    """Verifica tabelas criadas em PostgreSQL."""
    try:
        print(f"\n📋 Tabelas em PostgreSQL:")
        pg_conn = psycopg2.connect(
            host=POSTGRES_HOST,
            port=POSTGRES_PORT,
            database=POSTGRES_DB,
            user=POSTGRES_USER,
            password=POSTGRES_PASSWORD
        )
        pg_cursor = pg_conn.cursor()
        
        pg_cursor.execute("""
            SELECT tablename FROM pg_tables 
            WHERE schemaname = 'public'
            ORDER BY tablename
        """)
        
        tables = pg_cursor.fetchall()
        
        if not tables:
            print(f"  ⚠️  Nenhuma tabela encontrada")
            pg_conn.close()
            return
        
        for (table,) in tables:
            pg_cursor.execute(f"SELECT COUNT(*) FROM {table}")
            count = pg_cursor.fetchone()[0]
            print(f"  📦 {table}: {count} registros")
        
        print(f"\n✅ Power BI - Conectar em:")
        print(f"   Server: {POSTGRES_HOST} (ou localhost:5433 do Windows)")
        print(f"   Port: {POSTGRES_PORT} (interno)")
        print(f"   Database: {POSTGRES_DB}")
        print(f"   User: {POSTGRES_USER}")
        print(f"   Password: {POSTGRES_PASSWORD}")
        print(f"   Total: {len(tables)} tabelas")
        
        pg_conn.close()
        
    except Exception as e:
        print(f"❌ ERRO: {str(e)}")
        raise


# NOTA: A DAG não é mais criada aqui. 
# As DAGs de sync são criadas dinamicamente pelo factory_master.py
# com base nos usuários ativos (uma DAG por usuário).
#
# Para criar manualmente uma DAG de sync, use:
# from factory_master import create_sync_delta_to_postgres_dag
# my_dag = create_sync_delta_to_postgres_dag(owner='username', bucket='bucket_name')
#
# Formato do DAG ID: sync_delta_dw_{username}
