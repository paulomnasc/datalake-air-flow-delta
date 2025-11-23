import logging
import os
import tempfile

log = logging.getLogger(__name__)

def silver_to_gold(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Gold: Dados agregados e otimizados para consumo analítico.
    - Aplica agregações e métricas de negócio
    - Otimiza para queries analíticas
    - Formato final para consumo (BI, ML, APIs)
    """
    log.info(f"[GOLD] Iniciando agregação para: {target_table_name}")
    log.info(f"[GOLD] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import pandas as pd
    
    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')

    # Determina chave Silver e Gold
    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    basename_no_ext = os.path.splitext(basename)[0]
    
    silver_key = f"silver/{target_table_name}/{basename_no_ext}.parquet"
    gold_key = f"gold/{target_table_name}/{basename_no_ext}.parquet"

    log.info("[GOLD] Processando: s3://%s/%s → s3://%s/%s", bucket, silver_key, bucket, gold_key)

    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Download do arquivo Silver
        local_file = hook.download_file(key=silver_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[GOLD] Arquivo Silver baixado: %s", local_file)

        # Leitura do Parquet
        log.info("[GOLD] Lendo Parquet...")
        df = pd.read_parquet(local_file)
        log.info("[GOLD] Dados Silver: %d linhas, %d colunas", len(df), len(df.columns))
        
        # Aqui você pode adicionar lógica de agregação específica
        # Exemplo: calcular métricas, criar dimensões, etc.
        # Por enquanto, apenas otimiza o Parquet
        
        log.info("[GOLD] Aplicando otimizações...")
        
        # Salvar como Parquet otimizado
        gold_local = os.path.join(tmpdir, f"{basename_no_ext}_gold.parquet")
        df.to_parquet(gold_local, index=False, compression='snappy', engine='pyarrow')
        log.info("[GOLD] Parquet otimizado criado: %s", gold_local)

        # Upload para camada Gold
        hook.load_file(filename=gold_local, key=gold_key, bucket_name=bucket, replace=True)
        log.info("[GOLD] ✅ Arquivo salvo em: s3://%s/%s", bucket, gold_key)
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[GOLD] Processo concluído com sucesso!")
    return {"layer": "gold", "key": gold_key}
