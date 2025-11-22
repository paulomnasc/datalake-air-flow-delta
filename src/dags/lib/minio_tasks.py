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
    tmp = None
    try:
        tmp = tempfile.NamedTemporaryFile(delete=False)
        tmp.close()

        # Download source object to temp file
        # Use positional args to match S3Hook signature across provider versions
        hook.download_file(src_key, bucket, tmp.name)

        # (Aqui poderia entrar processamento com pandas)

        # Upload result to destination key (replace if exists)
        hook.load_file(tmp.name, dest_key, bucket, replace=True)

        log.info("Uploaded result to s3://%s/%s", bucket, dest_key)
    finally:
        if tmp is not None and os.path.exists(tmp.name):
            try:
                os.unlink(tmp.name)
            except Exception:
                pass

    log.info("Processo de transformação concluído com sucesso!")
    return True