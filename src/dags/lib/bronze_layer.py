import logging
import os
import tempfile

log = logging.getLogger(__name__)

def raw_to_bronze(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Bronze: Cópia bruta dos dados de Raw para Bronze.
    Mantém o formato original (CSV) sem transformações.
    """
    log.info(f"[BRONZE] Iniciando ingestão para: {target_table_name}")
    log.info(f"[BRONZE] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')

    # Normalize source key and destination key
    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    bronze_key = f"bronze/{target_table_name}/{basename}"

    log.info("[BRONZE] Copiando: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)

    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Download do arquivo Raw
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[BRONZE] Arquivo baixado: %s", local_file)

        # Upload para camada Bronze (sem transformação)
        hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
        log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[BRONZE] Processo concluído com sucesso!")
    return {"layer": "bronze", "key": bronze_key}
