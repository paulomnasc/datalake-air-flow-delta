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

# Configurações
POSTGRES_HOST = 'postgres-bi'
POSTGRES_PORT = 5432
POSTGRES_DB = 'datalake_bi'
POSTGRES_USER = 'pbi_user'
POSTGRES_PASSWORD = 'pbi_password'

MINIO_ENDPOINT = 'minio:9000'
MINIO_ACCESS_KEY = 'admin'
MINIO_SECRET_KEY = 'admin123'
MINIO_BUCKET = 'lab01'

SEARCH_GLOBS = [
    's3://lab01/delta/*/*.parquet',
    's3://lab01/delta/*/*/*.parquet',
]

EXACT_PATHS = [
    's3://lab01/delta/customers_202512230532/*.parquet',
]

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
    duckdb_con = None
    pg_conn = None
    try:
        print(f"\n🔌 Criando conexão DuckDB em-memória...")
        duckdb_con = duckdb.connect(':memory:')
        
        # Configura DuckDB
        print("📡 Carregando httpfs...")
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
        for search_path in SEARCH_GLOBS + EXACT_PATHS:
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
                f"s3://{MINIO_BUCKET}/delta/{folder}/*.parquet",
                f"s3://{MINIO_BUCKET}/delta/{folder}/*/*.parquet",
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


with DAG(
    'sync_delta_to_postgres',
    default_args=default_args,
    description='Delta → PostgreSQL para Power BI (sem locks)',
    schedule_interval='0 2 * * *',  # 02:00 AM diário
    start_date=datetime(2024, 1, 1),
    catchup=False,
    tags=['postgres', 'pbi', 'delta', 'no-locks'],
) as dag:
    
    dag.doc_md = """
    ## DAG: Delta → PostgreSQL para Power BI
    
    Lê tabelas Delta do MinIO via DuckDB e insere em PostgreSQL.
    Power BI conecta direto (suporta múltiplos acessos nativamente).
    
    ### Fluxo:
    1. Setup: Valida conexão PostgreSQL
    2. Sync: Lê Delta, insere em PostgreSQL
    3. Verify: Lista tabelas criadas
    
    ### Power BI - Conectar em:
    - Server: localhost:5433 (porta externa do container)
    - Database: datalake_bi
    - User: pbi_user
    - Password: pbi_password
    
    ### Vantagem:
    ✅ PostgreSQL suporta múltiplos acessos
    ✅ Sem problemas de lock
    ✅ Power BI gerencia conexões normalmente
    """
    
    setup_task = PythonOperator(
        task_id='setup_postgres',
        python_callable=setup_postgres,
    )
    
    sync_task = PythonOperator(
        task_id='sync_delta_to_postgres',
        python_callable=sync_delta_to_postgres,
    )
    
    verify_task = PythonOperator(
        task_id='verify_postgres_tables',
        python_callable=verify_postgres_tables,
    )
    
    setup_task >> sync_task >> verify_task
