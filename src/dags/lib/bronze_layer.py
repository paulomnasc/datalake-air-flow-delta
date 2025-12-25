import logging
import os
import tempfile

log = logging.getLogger(__name__)

def raw_to_bronze(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Bronze: Cópia bruta dos dados de Raw para Bronze.
    Mantém o formato original (CSV) sem transformações.
    
    Suporta:
    - Arquivo único: 'raw/pasta/arquivo.csv'
    - Pasta com múltiplos arquivos: 'raw/pasta/' (com barra no final)
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
    dag_id = kwargs.get('dag_id', 'default')
    
    # Normalize source key
    src_key = source_filename.lstrip('/')
    
    # Verificar se é uma pasta (termina com /)
    is_folder = src_key.endswith('/')
    
    results = []
    tmpdir = None
    
    try:
        tmpdir = tempfile.mkdtemp()
        
        if is_folder:
            # É uma pasta: listar todos os arquivos
            log.info("[BRONZE] Detectada pasta: %s (listando arquivos...)", src_key)
            keys = hook.list_keys(bucket_name=bucket, prefix=src_key)
            
            if not keys:
                log.warning("[BRONZE] ⚠️ Nenhum arquivo encontrado em %s", src_key)
                return {"layer": "bronze", "files_processed": 0}
            
            for file_key in keys:
                if file_key == src_key:  # Pula a própria pasta
                    continue
                    
                log.info("[BRONZE] Processando: %s", file_key)
                basename = os.path.basename(file_key)
                bronze_key = f"bronze/{dag_id}/{target_table_name}/{basename}"
                
                # Download
                local_file = hook.download_file(key=file_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
                
                # Upload para Bronze
                hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
                log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
                results.append(bronze_key)
        else:
            # É um arquivo específico
            basename = os.path.basename(src_key)
            bronze_key = f"bronze/{dag_id}/{target_table_name}/{basename}"
            
            log.info("[BRONZE] Copiando: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)
            
            # Download do arquivo Raw
            local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
            log.info("[BRONZE] Arquivo baixado: %s", local_file)

            # Upload para camada Bronze (sem transformação)
            hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
            log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
            results.append(bronze_key)
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[BRONZE] Processo concluído com sucesso! %d arquivo(s) processado(s)", len(results))
    return {"layer": "bronze", "files_processed": len(results), "keys": results}
