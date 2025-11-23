import logging

# O logger do Airflow é a melhor forma de registrar informações
log = logging.getLogger(__name__)

def transform_data_with_pandas(
    source_filename: str, 
    target_table_name: str, 
    **kwargs
):
    """
    Esta é a função que o Airflow Scheduler está tentando importar.
    Ela deve conter a lógica para extrair, transformar e carregar os dados.
    """
    log.info(f"Iniciando transformação para arquivo: {source_filename}")
    log.info(f"O resultado será carregado em: {target_table_name}")
    log.info("Os argumentos extras (transform_args) são: %s", kwargs)

    # Implementação mínima prática: copia o objeto de entrada para
    # `processed/raw/{target_table_name}/{basename}` no bucket MinIO.
    # Isso é um 'pass-through' que garante que algo será gravado no bucket
    # para as etapas seguintes (validação etc.).
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import os
    import tempfile

    bucket = os.environ.get("MINIO_BUCKET", "lab01")

    hook = S3Hook(aws_conn_id='minio_conn')

    # Normalize source key and destination key
    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    dest_key = f"processed/raw/{target_table_name}/{basename}"

    log.info("Copying from s3://%s/%s to s3://%s/%s", bucket, src_key, bucket, dest_key)

    # Use temp file to download + re-upload (avoids keeping objects in memory)
    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Download source object to temp directory
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)

        # TRANSFORMAÇÃO PARA CAMADA SILVER: Processamento com Pandas
        import pandas as pd
        
        log.info("Lendo arquivo CSV: %s", local_file)
        df = pd.read_csv(local_file)
        
        log.info("Dados originais: %d linhas, %d colunas", len(df), len(df.columns))
        
        # Limpeza básica de dados
        df = df.dropna(how='all')  # Remove linhas totalmente vazias
        df = df.drop_duplicates()  # Remove duplicatas
        
        log.info("Após limpeza: %d linhas", len(df))
        
        # Salvar como Parquet na camada Silver
        basename_no_ext = os.path.splitext(basename)[0]
        silver_key = f"silver/{target_table_name}/{basename_no_ext}.parquet"
        silver_local = os.path.join(tmpdir, f"{basename_no_ext}.parquet")
        
        df.to_parquet(silver_local, index=False, compression='snappy')
        log.info("Arquivo Parquet criado localmente: %s", silver_local)

        # Upload result to Silver layer (replace if exists)
        hook.load_file(filename=silver_local, key=silver_key, bucket_name=bucket, replace=True)

        log.info("Uploaded result to s3://%s/%s", bucket, silver_key)
        
        # Também mantém cópia em processed/raw (Bronze) para auditoria
        hook.load_file(filename=local_file, key=dest_key, bucket_name=bucket, replace=True)
        log.info("Bronze copy saved to s3://%s/%s", bucket, dest_key)
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("Processo de transformação concluído com sucesso!")
    return True