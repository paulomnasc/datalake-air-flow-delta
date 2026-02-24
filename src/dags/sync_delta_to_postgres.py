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


# Configurações dinâmicas: obtém do ambiente (.env ou docker-compose)
POSTGRES_HOST = os.environ.get('POSTGRES_HOST', 'postgres-bi')
POSTGRES_PORT = int(os.environ.get('POSTGRES_PORT', 5432))
POSTGRES_DB = os.environ.get('POSTGRES_DB', 'datalake_bi')
POSTGRES_USER = os.environ.get('POSTGRES_USER', 'pbi_user')
POSTGRES_PASSWORD = os.environ.get('POSTGRES_PASSWORD', 'pbi_password')

MINIO_ENDPOINT = os.environ.get('MINIO_ENDPOINT', 'minio:9000')
MINIO_ACCESS_KEY = os.environ.get('MINIO_ACCESS_KEY', 'admin')
MINIO_SECRET_KEY = os.environ.get('MINIO_SECRET_KEY', 'admin123')
# MINIO_BUCKET será obtido dinamicamente: kwargs -> env -> fallback 'lab01'

default_args = {
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
    
    # ===== OBTENÇÃO ROBUSTA DO BUCKET (padrão medallion_pipeline_v2.py) =====
    bucket = None
    bucket_source = None
    # 1. Prioridade: parâmetro 'owner' explícito (como no Medallion)
    # ===== OBTENÇÃO ROBUSTA DO OWNER/BUCKET (padrão medallion_pipeline_v2.py) =====
    owner = None
    # 1. owner explícito no contexto (como no Medallion)
    if 'owner' in context and context['owner']:
        owner = context['owner']
    elif 'params' in context and context['params'] and 'owner' in context['params'] and context['params']['owner']:
        owner = context['params']['owner']
    # 2. dag_run.conf (usado em triggers manuais/webapp)
    elif 'dag_run' in context and context['dag_run'] and getattr(context['dag_run'], 'conf', None):
        conf = context['dag_run'].conf
        owner = conf.get('owner') or conf.get('bucket_name') or conf.get('bucket')
    
    if not owner:
        raise ValueError("O parâmetro 'owner' (bucket do usuário) deve ser o owner da dag.")
    
    
    bucket = owner
    bucket_source = 'owner (context/params/conf/env/fallback)'
    import logging
    log = logging.getLogger("airflow.task")
    print(f"\n🗂️  Usando bucket: {bucket}")
    print(f"   Fonte: {bucket_source}")
    print("\n================ AUDITORIA DELTA → POSTGRESQL ================" )
    print(f"[AUDIT] Início da sincronização Delta → PostgreSQL | Data: {datetime.now().isoformat()}")
    log.info(f"[AUDIT] Início da sincronização Delta → PostgreSQL | Bucket: {bucket} | Fonte: {bucket_source} | Data: {datetime.now().isoformat()}")
    
    # Construir paths dinamicamente
    search_globs = [
    f's3://{bucket}/gold/*_delta/*.parquet',
    ]
    
    duckdb_con = None
    pg_conn = None
    try:
        print(f"\n🔌 Criando conexão DuckDB em-memória...")
        duckdb_con = duckdb.connect(':memory:')
        
        # Configura DuckDB
        print("📡 Instalando e carregando extensões...")
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
                    SELECT DISTINCT regexp_extract(filename, '.*/gold/([^/]+)_delta/.*', 1) AS folder
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
        print(f"[AUDIT] Total de tabelas Delta encontradas: {len(folders)}")
        log.info(f"[AUDIT] Total de tabelas Delta encontradas: {len(folders)}")
        
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
        print(f"[AUDIT] Início da escrita PostgreSQL para {len(folders)} tabelas Delta")
        log.info(f"[AUDIT] Início da escrita PostgreSQL para {len(folders)} tabelas Delta")
        for folder in sorted(folders):
            safe_name = re.sub(r"[^a-zA-Z0-9_]+", "_", folder)
            table_name = f"delta_{safe_name}"
            delta_path = f"s3://{bucket}/gold/{folder}_delta/"
            print(f"  📥 {table_name} ← {folder}")
            try:
                print(f"     Descobrindo versão mais recente...")
                files = duckdb_con.execute(f"""
                    SELECT DISTINCT filename FROM read_parquet('{delta_path}*.parquet', filename=true)
                    ORDER BY filename DESC
                    LIMIT 1
                """).fetchall()
                if not files:
                    print(f"    ⚠️  Nenhum arquivo encontrado")
                    print(f"[AUDIT] {table_name}: FALHA - Nenhum arquivo Delta encontrado")
                    log.info(f"[AUDIT] {table_name}: FALHA - Nenhum arquivo Delta encontrado")
                    results.append({'table': table_name, 'status': 'FAILED', 'count': 0})
                    continue
                latest_file = files[0][0]
                print(f"     Usando: {latest_file.split('/')[-1]}")
                # Lê todos os registros do Delta (originais)
                df_delta = duckdb_con.execute(f"SELECT * FROM read_parquet('{latest_file}')").fetchdf()
                # Substitui 'NaT', 'None' (string), np.nan, pd.NaT por None em todas as colunas
                import pandas as pd
                import numpy as np
                def clean_value(x):
                    if x is None or x is pd.NaT or x is np.nan:
                        return None
                    if isinstance(x, float) and pd.isna(x):
                        return None
                    if str(x) in ("NaT", "None", "nan", "<NA>"):
                        return None
                    return x
                for col in df_delta.columns:
                    df_delta[col] = df_delta[col].apply(clean_value)
                columns = list(df_delta.columns)
                count_delta = len(df_delta)
                # Detecta tipos reais das colunas via DuckDB
                duck_types = {}
                duck_type_exprs = []
                for col in columns:
                    duck_type_exprs.append("typeof('" + col + "') as " + col)
                duck_type_sql = ", ".join(duck_type_exprs)
                duck_type_query = "SELECT " + duck_type_sql + " FROM read_parquet('" + latest_file + "') LIMIT 1"
                duck_type_rows = duckdb_con.execute(duck_type_query).fetchone()
                for col, dtype in zip(columns, duck_type_rows):
                    duck_types[col] = dtype
                if not columns:
                    print(f"    ⚠️  Nenhuma coluna encontrada")
                    print(f"[AUDIT] {table_name}: FALHA - Nenhuma coluna encontrada")
                    log.info(f"[AUDIT] {table_name}: FALHA - Nenhuma coluna encontrada")
                    results.append({'table': table_name, 'status': 'FAILED', 'count': 0})
                    continue
                if count_delta == 0:
                    print(f"    ⚠️  Nenhum registro encontrado")
                    print(f"[AUDIT] {table_name}: FALHA - Nenhum registro encontrado no Delta")
                    log.info(f"[AUDIT] {table_name}: FALHA - Nenhum registro encontrado no Delta")
                    results.append({'table': table_name, 'status': 'FAILED', 'count': 0})
                    continue
                print(f"     ✓ Encontrado {count_delta} registros originais no Delta")
                print(f"[AUDIT] {table_name}: {count_delta} registros originais lidos do Delta")
                log.info(f"[AUDIT] {table_name}: {count_delta} registros originais lidos do Delta")
                # Cria tabela no Postgres
                # Usa tipos reais detectados para criar tabela
                def map_duck_to_pg(dtype):
                    if dtype in ('timestamp', 'TIMESTAMP'): return 'TIMESTAMP'
                    if dtype in ('varchar', 'VARCHAR', 'string', 'STRING'): return 'TEXT'
                    if dtype in ('integer', 'INTEGER', 'int', 'INT'): return 'INTEGER'
                    if dtype in ('double', 'DOUBLE', 'float', 'FLOAT'): return 'DOUBLE PRECISION'
                    if dtype in ('boolean', 'BOOLEAN'): return 'BOOLEAN'
                    if dtype in ('date', 'DATE'): return 'DATE'
                    return 'TEXT'
                col_defs = ", ".join([f'"{col}" {map_duck_to_pg(duck_types[col])}' for col in columns])
                pg_cursor.execute(f"DROP TABLE IF EXISTS {table_name} CASCADE;")
                pg_cursor.execute(f"""
                    CREATE TABLE {table_name} (
                        {col_defs}
                    )
                """)
                print(f"     ✓ Tabela criada em PostgreSQL")
                # Insere dados
                count_inserted = 0
                if not df_delta.empty:
                    placeholders = ", ".join(["%s"] * len(columns))
                    insert_sql = f"""
                        INSERT INTO {table_name} ({', '.join([f'"{col}"' for col in columns])})
                        VALUES ({placeholders})
                    """
                    batch_size = 100
                    data = df_delta.values.tolist()
                    for i in range(0, len(data), batch_size):
                        batch = data[i:i+batch_size]
                        for row in batch:
                            valid_row = []
                            skip_row = False
                            for col, val in zip(columns, row):
                                dtype = duck_types[col]
                                try:
                                    if dtype in ('timestamp', 'TIMESTAMP'):
                                        if val is None or val == '':
                                            valid_row.append(None)
                                        else:
                                            valid_row.append(str(val))
                                    elif dtype in ('integer', 'INTEGER', 'int', 'INT'):
                                        if val is None or val == '':
                                            valid_row.append(None)
                                        else:
                                            valid_row.append(int(val))
                                    elif dtype in ('double', 'DOUBLE', 'float', 'FLOAT'):
                                        if val is None or val == '':
                                            valid_row.append(None)
                                        else:
                                            valid_row.append(float(val))
                                    elif dtype in ('boolean', 'BOOLEAN'):
                                        if val is None or val == '':
                                            valid_row.append(None)
                                        else:
                                            valid_row.append(bool(val))
                                    elif dtype in ('date', 'DATE'):
                                        if val is None or val == '':
                                            valid_row.append(None)
                                        else:
                                            valid_row.append(str(val))
                                    else:
                                        valid_row.append(str(val) if val is not None else None)
                                except Exception as e:
                                    skip_row = True
                                    print(f"[AUDIT] ERRO ao converter valor: coluna={col}, valor={val}, tipo={dtype}, erro={e}")
                                    log.info(f"[AUDIT] ERRO ao converter valor: coluna={col}, valor={val}, tipo={dtype}, erro={e}")
                                    break
                            if skip_row:
                                print(f"[AUDIT] Registro ignorado por erro de conversão: {row}")
                                log.info(f"[AUDIT] Registro ignorado por erro de conversão: {row}")
                                continue
                            try:
                                pg_cursor.execute(insert_sql, valid_row)
                                count_inserted += 1
                            except Exception as e:
                                error_msg = str(e)
                                print(f"[AUDIT] ERRO ao inserir registro: {valid_row}")
                                log.info(f"[AUDIT] ERRO ao inserir registro: {valid_row}")
                                log.info(f"[AUDIT] Exception: {error_msg}")
                                pg_conn.rollback()
                                print(f"[AUDIT] ROLLBACK realizado após erro de inserção.")
                                log.info(f"[AUDIT] ROLLBACK realizado após erro de inserção.")
                                continue
                    print(f"     ✓ {count_inserted} registros inseridos no PostgreSQL")
                    print(f"[AUDIT] {table_name}: {count_inserted} registros inseridos no PostgreSQL")
                    log.info(f"[AUDIT] {table_name}: {count_inserted} registros inseridos no PostgreSQL")
                pg_conn.commit()
                print(f"  ✅ {table_name}: {count_inserted} registros processados (Delta → PostgreSQL)")
                print(f"[AUDIT] {table_name}: {count_delta} registros originais no Delta | {count_inserted} inseridos no PostgreSQL")
                log.info(f"[AUDIT] {table_name}: {count_delta} registros originais no Delta | {count_inserted} inseridos no PostgreSQL")
                results.append({'table': table_name, 'status': 'OK', 'count_delta': count_delta, 'count_postgres': count_inserted})
            except Exception as e:
                error_msg = str(e)[:150]
                print(f"  ❌ {table_name}: {error_msg}")
                print(f"[AUDIT] {table_name}: FALHA - {error_msg}")
                log.info(f"[AUDIT] {table_name}: FALHA - {error_msg}")
                results.append({'table': table_name, 'status': 'FAILED', 'count_delta': 0, 'count_postgres': 0})
        success = sum(1 for r in results if r['status'] == 'OK')
        print(f"\n✅ Sincronização: {success}/{len(results)} OK")
        print(f"[AUDIT] Tabelas sincronizadas com sucesso: {success}/{len(results)}")
        print(f"[AUDIT] Fim da sincronização Delta → PostgreSQL | Data: {datetime.now().isoformat()}")
        log.info(f"[AUDIT] Tabelas sincronizadas com sucesso: {success}/{len(results)}")
        log.info(f"[AUDIT] Fim da sincronização Delta → PostgreSQL | Data: {datetime.now().isoformat()}")
        print("============================================================\n")
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
