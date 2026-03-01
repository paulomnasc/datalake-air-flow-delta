def clean_postgres_table_names():
    """Padroniza e limpa os nomes das tabelas no PostgreSQL, removendo prefixos, UID/hash e caracteres especiais."""
    import psycopg2
    import re
    print("\n🔄 Limpando nomes das tabelas no PostgreSQL...")
    try:
        conn = psycopg2.connect(
            host=POSTGRES_HOST,
            port=POSTGRES_PORT,
            database=POSTGRES_DB,
            user=POSTGRES_USER,
            password=POSTGRES_PASSWORD
        )
        cursor = conn.cursor()
        cursor.execute("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
        all_tables = [row[0] for row in cursor.fetchall()]
        for tbl in all_tables:
            nome_logico = re.sub(r'^delta_', '', tbl)
            nome_logico = re.sub(r'_[0-9a-fA-F]{8,}$', '', nome_logico)  # Remove UID/hash final
            nome_logico = re.sub(r'[^a-zA-Z0-9]+', '_', nome_logico)  # Limpa caracteres especiais
            nome_logico = nome_logico.strip('_').lower()
            if nome_logico and nome_logico != tbl:
                try:
                    cursor.execute(f"DROP TABLE IF EXISTS {nome_logico} CASCADE;")
                    cursor.execute(f"ALTER TABLE {tbl} RENAME TO {nome_logico};")
                    print(f"  ✅ {tbl} → {nome_logico}")
                except Exception as e:
                    print(f"  ⚠️  Falha ao renomear {tbl} → {nome_logico}: {e}")
        conn.commit()
        cursor.close()
        conn.close()
        print("[AUDIT] Limpeza de nomes de tabelas concluída.")
    except Exception as e:
        print(f"❌ ERRO ao limpar nomes das tabelas: {e}")

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
    owner = context['dag'].owner
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
    f's3://{bucket}/delta/*/*.parquet',
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
                    SELECT DISTINCT regexp_extract(filename, '.*/delta/([^/]+)/[^/]+\\.parquet', 1) AS folder
                    FROM read_parquet('{search_path}', filename=true)
                    WHERE folder IS NOT NULL AND folder <> ''
                """).fetchall()
                for (folder,) in rows:
                    folders.add(folder)
            except Exception as e:
                print(f"    ⚠️  Falha: {str(e)[:80]}")
                # Continua mesmo se falhar em um glob
        if not folders:
            print("\n⚠️  Nenhuma pasta encontrada")
            print("[AUDIT] Nenhuma pasta Delta encontrada para processamento. Verifique se os dados foram gravados corretamente.")
            log.info("[AUDIT] Nenhuma pasta Delta encontrada para processamento. Verifique se os dados foram gravados corretamente.")
            return  # Não interrompe a DAG, apenas retorna
        
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
            # Extrai UID/hash e nome lógico para garantir unicidade
            if '_' in folder:
                uid = folder.split('_')[0]
                nome_logico = folder.split('_', 2)[-1]
                safe_name = re.sub(r"[^a-zA-Z0-9_]+", "_", nome_logico)
                table_name = f"delta_{safe_name}_{uid}"
            else:
                safe_name = re.sub(r"[^a-zA-Z0-9_]+", "_", folder)
                table_name = f"delta_{safe_name}"
            delta_path = f"s3://{bucket}/delta/{folder}/"
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
                    # Corrige NaN, 'NaT' (string), None, pd.NaT para None em todas as colunas
                    df_delta[col] = df_delta[col].apply(
                        lambda x: None if (
                            x is None or x is pd.NaT or (isinstance(x, float) and pd.isna(x)) or str(x).strip() in ("NaT", "None", "nan", "<NA>")
                        ) else x
                    )
                columns = list(df_delta.columns)
                count_delta = len(df_delta)
                # Detecta tipos reais das colunas via DuckDB (DESCRIBE)
                duck_types = {}
                describe_rows = duckdb_con.execute(f"DESCRIBE SELECT * FROM read_parquet('{latest_file}')").fetchall()
                for row in describe_rows:
                    colname, coltype = row[0], row[1]
                    duck_types[colname] = coltype
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
                # Diagnóstico: imprime tipos detectados
                print("\n[AUDIT] Tipos detectados por DuckDB:")
                for col in columns:
                    print(f"  - {col}: {duck_types[col]}")

                # Cria tabela no Postgres
                # Usa tipos reais detectados para criar tabela
                def map_duck_to_pg(dtype):
                    # Normaliza para minúsculas
                    dtype = str(dtype).lower()
                    if 'timestamp' in dtype or 'datetime' in dtype:
                        return 'TIMESTAMP'
                    if 'date' == dtype:
                        return 'DATE'
                    if 'time' == dtype:
                        return 'TIME'
                    if 'varchar' in dtype or 'string' in dtype or 'text' in dtype:
                        return 'TEXT'
                    if 'integer' in dtype or 'int' == dtype or 'int4' in dtype:
                        return 'INTEGER'
                    if 'bigint' in dtype or 'int8' in dtype:
                        return 'BIGINT'
                    if 'smallint' in dtype or 'int2' in dtype:
                        return 'SMALLINT'
                    if 'double' in dtype or 'float' in dtype or 'float8' in dtype or 'real' in dtype:
                        return 'DOUBLE PRECISION'
                    if 'float4' in dtype:
                        return 'REAL'
                    if 'numeric' in dtype or 'decimal' in dtype:
                        return 'NUMERIC'
                    if 'boolean' in dtype or 'bool' == dtype:
                        return 'BOOLEAN'
                    if 'uuid' in dtype:
                        return 'UUID'
                    if 'json' in dtype or 'jsonb' in dtype:
                        return 'JSONB'
                    # Default fallback
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
                    import csv
                    failed_inserts = []
                    for i in range(0, len(data), batch_size):
                        batch = data[i:i+batch_size]
                        for row in batch:
                            valid_row = []
                            skip_row = False
                            for col, val in zip(columns, row):
                                dtype = duck_types[col]
                                dtype_norm = str(dtype).lower()
                                # Trata 'NaT' (string), None, pd.NaT, np.nan como None
                                if val is None or (isinstance(val, float) and pd.isna(val)) or str(val).strip() in ("NaT", "None", "nan", "<NA>"):
                                    valid_row.append(None)
                                else:
                                    try:
                                        if 'timestamp' in dtype_norm or 'datetime' in dtype_norm:
                                            valid_row.append(str(val) if val not in [None, ''] else None)
                                        elif 'date' == dtype_norm:
                                            valid_row.append(str(val) if val not in [None, ''] else None)
                                        elif 'time' == dtype_norm:
                                            valid_row.append(str(val) if val not in [None, ''] else None)
                                        elif 'varchar' in dtype_norm or 'string' in dtype_norm or 'text' in dtype_norm:
                                            valid_row.append(str(val) if val is not None else None)
                                        elif 'integer' in dtype_norm or 'int' == dtype_norm or 'int4' in dtype_norm:
                                            valid_row.append(int(val) if val not in [None, ''] else None)
                                        elif 'bigint' in dtype_norm or 'int8' in dtype_norm:
                                            valid_row.append(int(val) if val not in [None, ''] else None)
                                        elif 'smallint' in dtype_norm or 'int2' in dtype_norm:
                                            valid_row.append(int(val) if val not in [None, ''] else None)
                                        elif 'double' in dtype_norm or 'float' in dtype_norm or 'float8' in dtype_norm or 'real' in dtype_norm:
                                            valid_row.append(float(val) if val not in [None, ''] else None)
                                        elif 'float4' in dtype_norm:
                                            valid_row.append(float(val) if val not in [None, ''] else None)
                                        elif 'numeric' in dtype_norm or 'decimal' in dtype_norm:
                                            valid_row.append(float(val) if val not in [None, ''] else None)
                                        elif 'boolean' in dtype_norm or 'bool' == dtype_norm:
                                            valid_row.append(bool(val) if val not in [None, ''] else None)
                                        elif 'uuid' in dtype_norm:
                                            valid_row.append(str(val) if val is not None else None)
                                        elif 'json' in dtype_norm or 'jsonb' in dtype_norm:
                                            valid_row.append(str(val) if val is not None else None)
                                        else:
                                            valid_row.append(str(val) if val is not None else None)
                                    except Exception as e:
                                        skip_row = True
                                        print(f"[AUDIT] ERRO ao converter valor: coluna={col}, valor={val}, tipo={dtype}, erro={e}")
                                        log.info(f"[AUDIT] ERRO ao converter valor: coluna={col}, valor={val}, tipo={dtype}, erro={e}")
                                        failed_inserts.append({'row': row, 'error': f'Conversão: coluna={col}, valor={val}, tipo={dtype}, erro={e}'})
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
                                failed_inserts.append({'row': valid_row, 'error': error_msg})
                                continue
                    # Salva registros que falharam em um arquivo CSV e envia para MinIO
                    if failed_inserts:
                        failed_path = f"/tmp/failed_inserts_{table_name}.csv"
                        with open(failed_path, "w", newline="") as f:
                            writer = csv.writer(f)
                            writer.writerow(["row", "error"])
                            for item in failed_inserts:
                                writer.writerow([item['row'], item['error']])
                        print(f"[AUDIT] Registros com falha salvos em: {failed_path}")
                        # Envia para MinIO na pasta logs do bucket do usuário
                        try:
                            import boto3
                            from botocore.client import Config
                            minio_client = boto3.client(
                                's3',
                                endpoint_url=f'http://{MINIO_ENDPOINT}',
                                aws_access_key_id=MINIO_ACCESS_KEY,
                                aws_secret_access_key=MINIO_SECRET_KEY,
                                config=Config(signature_version='s3v4'),
                                region_name='us-east-1'
                            )
                            minio_key = f"logs/failed_inserts_{table_name}.csv"
                            minio_client.upload_file(failed_path, bucket, minio_key)
                            print(f"[AUDIT] Arquivo de falhas enviado para MinIO: s3://{bucket}/{minio_key}")
                        except Exception as e:
                            print(f"[AUDIT] Falha ao enviar arquivo para MinIO: {e}")
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
        failed = len(results) - success
        print(f"\n✅ Sincronização: {success}/{len(results)} OK")
        print(f"[AUDIT] Tabelas Delta (origem): {len(folders)}")
        print(f"[AUDIT] Tabelas PostgreSQL (destino): {success}")
        print(f"[AUDIT] Tabelas com falha: {failed}")
        print(f"[AUDIT] Tabelas sincronizadas com sucesso: {success}/{len(results)}")
        print(f"[AUDIT] Fim da sincronização Delta → PostgreSQL | Data: {datetime.now().isoformat()}")
        log.info(f"[AUDIT] Tabelas Delta (origem): {len(folders)}")
        log.info(f"[AUDIT] Tabelas PostgreSQL (destino): {success}")
        log.info(f"[AUDIT] Tabelas com falha: {failed}")
        log.info(f"[AUDIT] Tabelas sincronizadas com sucesso: {success}/{len(results)}")
        log.info(f"[AUDIT] Fim da sincronização Delta → PostgreSQL | Data: {datetime.now().isoformat()}")
        print("============================================================\n")
        context['task_instance'].xcom_push(key='sync_results', value=results)

        # Chama limpeza dos nomes das tabelas após sincronização
        clean_postgres_table_names()
        
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
