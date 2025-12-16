import logging
import os
import tempfile
from datetime import datetime
import hashlib

log = logging.getLogger(__name__)

def ingest_mysql_to_raw(
    mysql_conn_id: str,
    table_name: str,
    query: str = None,
    target_table_name: str = None,
    dag_id: str = None,
    **kwargs
):
    """
    Ingesta dados do MySQL para camada Raw do Data Lake.
    
    Args:
        mysql_conn_id: ID da conexão MySQL no Airflow
        table_name: Nome da tabela MySQL para extrair
        query: Query SQL customizada (opcional). Se não fornecida, usa SELECT * FROM {table_name}
        target_table_name: Nome da tabela de destino (default: table_name)
        dag_id: ID da DAG (usado para organizar arquivos no Raw)
        
    Returns:
        dict: Informações do arquivo criado na camada Raw
    """
    log.info(f"[MYSQL→RAW] Iniciando ingestão do MySQL para: {table_name}")
    
    try:
        from airflow.providers.mysql.hooks.mysql import MySqlHook
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("Hooks não disponíveis: %s", e)
        raise
    
    import pandas as pd
    
    # Configurações
    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    target_table = target_table_name or table_name
    
    # Pega dag_id do contexto do Airflow se não foi passado explicitamente
    if not dag_id and 'dag' in kwargs:
        dag_id = kwargs['dag'].dag_id
    elif not dag_id and 'ti' in kwargs:
        dag_id = kwargs['ti'].dag_id
    
    # Se ainda não tiver dag_id, usa o nome da tabela de destino
    dag_name = dag_id or target_table
    
    # Gerar nome único para arquivo
    timestamp = int(datetime.now().timestamp())
    hash_suffix = hashlib.md5(f"{table_name}{timestamp}".encode()).hexdigest()[:20]
    filename = f"{timestamp}_{hash_suffix}.csv"
    raw_key = f"raw/{dag_name}/{filename}"
    
    log.info(f"[MYSQL→RAW] MySQL Connection: {mysql_conn_id}")
    log.info(f"[MYSQL→RAW] Tabela origem: {table_name}")
    log.info(f"[MYSQL→RAW] Destino: s3://{bucket}/{raw_key}")
    
    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Conectar ao MySQL e extrair dados
        mysql_hook = MySqlHook(mysql_conn_id=mysql_conn_id)
        
        # Query SQL
        if query:
            sql_query = query
            log.info(f"[MYSQL→RAW] Usando query customizada: {query[:100]}...")
        else:
            sql_query = f"SELECT * FROM {table_name}"
            log.info(f"[MYSQL→RAW] Query: {sql_query}")
        
        # Executar query e carregar em DataFrame
        log.info("[MYSQL→RAW] Executando query no MySQL...")
        connection = mysql_hook.get_conn()
        df = pd.read_sql(sql_query, connection)
        connection.close()
        
        row_count = len(df)
        col_count = len(df.columns)
        log.info(f"[MYSQL→RAW] Dados extraídos: {row_count} linhas, {col_count} colunas")
        
        if row_count == 0:
            log.warning(f"[MYSQL→RAW] ⚠️ Nenhum dado encontrado na tabela {table_name}")
        
        # Salvar como CSV temporário
        local_csv = os.path.join(tmpdir, filename)
        df.to_csv(local_csv, index=False)
        log.info(f"[MYSQL→RAW] CSV temporário criado: {local_csv}")
        
        # Upload para MinIO (camada Raw)
        s3_hook = S3Hook(aws_conn_id='minio_conn')
        s3_hook.load_file(
            filename=local_csv,
            key=raw_key,
            bucket_name=bucket,
            replace=True
        )
        
        log.info(f"[MYSQL→RAW] ✅ Dados salvos em: s3://{bucket}/{raw_key}")
        log.info(f"[MYSQL→RAW] Ingestão concluída com sucesso!")
        
        return {
            "source": "mysql",
            "table": table_name,
            "raw_key": raw_key,
            "rows": row_count,
            "columns": col_count,
            "filename": filename
        }
        
    except Exception as e:
        log.error(f"[MYSQL→RAW] ❌ Erro na ingestão: {e}")
        raise
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass


def mysql_to_medallion(
    mysql_conn_id: str,
    table_name: str,
    query: str = None,
    target_table_name: str = None,
    dag_id: str = None,
    **kwargs
):
    """
    Pipeline completo: MySQL → Raw → Bronze → Silver → Gold
    
    Combina ingestão do MySQL com pipeline Medallion.
    """
    import os
    log.info(f"[MYSQL→MEDALLION] Iniciando pipeline completo para: {table_name}")
    
    # 0. Registrar tabela MySQL no Atlas (origem da linhagem)
    mysql_qualified_name = None
    register_processes = os.getenv("ATLAS_REGISTER_PROCESSES", "false").lower() == "true"
    
    if register_processes:
        try:
            from lib.atlas_client import AtlasClient
            atlas = AtlasClient()
            db_name = os.getenv("ATLAS_HIVE_DB", "medallion")
            
            # Criar entidade MySQL
            mysql_table_name = f"mysql_{table_name}"
            mysql_qualified_name = f"mysql.lista_revisao2.{table_name}@{mysql_conn_id}"
            
            log.info(f"[MYSQL→ATLAS] Registrando tabela MySQL de origem: {mysql_qualified_name}")
            atlas.create_mysql_table(
                qualified_name=mysql_qualified_name,
                name=table_name,
                database="lista_revisao2",
                server=mysql_conn_id
            )
            log.info(f"[MYSQL→ATLAS] ✅ Tabela MySQL registrada")
        except Exception as e:
            log.warning(f"[MYSQL→ATLAS] Falha ao registrar MySQL: {e}")
    
    # 1. Ingestão MySQL → Raw
    ingest_result = ingest_mysql_to_raw(
        mysql_conn_id=mysql_conn_id,
        table_name=table_name,
        query=query,
        target_table_name=target_table_name,
        dag_id=dag_id,
        **kwargs
    )
    
    # 2. Pipeline Medallion (Raw → Bronze → Silver → Gold)
    from lib.medallion_pipeline import raw_to_medallion
    
    # Remove source_filename de kwargs para evitar duplicação
    medallion_kwargs = {k: v for k, v in kwargs.items() if k != 'source_filename'}
    
    # Passar mysql_qualified_name para criar processo MySQL→Raw
    if mysql_qualified_name:
        medallion_kwargs['mysql_qualified_name'] = mysql_qualified_name
    
    medallion_result = raw_to_medallion(
        source_filename=ingest_result['raw_key'],
        target_table_name=target_table_name or table_name,
        **medallion_kwargs
    )
    
    # Combinar resultados
    result = {
        **ingest_result,
        **medallion_result,
        "pipeline": "mysql_to_medallion"
    }
    
    log.info(f"[MYSQL→MEDALLION] ✅ Pipeline completo concluído!")
    log.info(f"[MYSQL→MEDALLION] MySQL → Raw: {result['raw_key']}")
    log.info(f"[MYSQL→MEDALLION] Bronze: {result.get('bronze')}")
    log.info(f"[MYSQL→MEDALLION] Silver: {result.get('silver')}")
    if result.get('gold_format') == 'delta':
        log.info(f"[MYSQL→MEDALLION] Gold (Delta Lake): {result.get('gold_delta')} (versão {result.get('gold_version', 0)})")
    else:
        log.info(f"[MYSQL→MEDALLION] Gold (Parquet): {result.get('gold')}")
    
    return result
