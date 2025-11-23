import logging
import os
import tempfile

log = logging.getLogger(__name__)

def bronze_to_silver(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Silver: Limpeza e transformação de dados Bronze → Silver.
    - Remove duplicatas e linhas vazias
    - Converte para formato Parquet
    - Aplica validações básicas
    """
    log.info(f"[SILVER] Iniciando transformação para: {target_table_name}")
    log.info(f"[SILVER] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import pandas as pd
    
    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')

    # Determina chave Bronze e Silver
    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    
    # Se source_filename aponta para Raw, ajusta para Bronze
    if src_key.startswith('raw/'):
        bronze_key = src_key.replace('raw/', 'bronze/', 1)
    else:
        bronze_key = f"bronze/{target_table_name}/{basename}"
    
    basename_no_ext = os.path.splitext(basename)[0]
    silver_key = f"silver/{target_table_name}/{basename_no_ext}.parquet"

    log.info("[SILVER] Processando: s3://%s/%s → s3://%s/%s", bucket, bronze_key, bucket, silver_key)

    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Download do arquivo Bronze
        local_file = hook.download_file(key=bronze_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[SILVER] Arquivo Bronze baixado: %s", local_file)

        # Leitura e transformação com Pandas
        log.info("[SILVER] Lendo CSV...")
        df = pd.read_csv(local_file)
        log.info("[SILVER] Dados originais: %d linhas, %d colunas", len(df), len(df.columns))
        
        # Limpeza de dados
        original_count = len(df)
        df = df.dropna(how='all')  # Remove linhas totalmente vazias
        df = df.drop_duplicates()  # Remove duplicatas
        cleaned_count = len(df)
        
        log.info("[SILVER] Limpeza: %d linhas removidas (%d → %d)", 
                 original_count - cleaned_count, original_count, cleaned_count)
        
        # Salvar como Parquet
        silver_local = os.path.join(tmpdir, f"{basename_no_ext}.parquet")
        df.to_parquet(silver_local, index=False, compression='snappy')
        log.info("[SILVER] Parquet criado: %s", silver_local)

        # Upload para camada Silver
        hook.load_file(filename=silver_local, key=silver_key, bucket_name=bucket, replace=True)
        log.info("[SILVER] ✅ Arquivo salvo em: s3://%s/%s", bucket, silver_key)
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[SILVER] Processo concluído com sucesso!")
    return {"layer": "silver", "key": silver_key, "rows": cleaned_count}
