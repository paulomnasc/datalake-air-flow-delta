import logging
import os
import tempfile

log = logging.getLogger(__name__)

def raw_to_medallion(source_filename: str, target_table_name: str, **kwargs):
    """
    Pipeline completo Medallion: Raw → Bronze → Silver → Gold em uma única execução.
    
    Processa todas as camadas sequencialmente:
    1. Bronze: Cópia do arquivo Raw
    2. Silver: Limpeza e conversão para Parquet
    3. Gold: Agregação e otimização
    """
    log.info(f"[MEDALLION] Iniciando pipeline completo para: {target_table_name}")
    log.info(f"[MEDALLION] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import pandas as pd
    
    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')

    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    basename_no_ext = os.path.splitext(basename)[0]
    
    # Definir chaves de todas as camadas
    bronze_key = f"bronze/{target_table_name}/{basename}"
    silver_key = f"silver/{target_table_name}/{basename_no_ext}.parquet"
    gold_key = f"gold/{target_table_name}/{basename_no_ext}.parquet"
    
    tmpdir = None
    results = {}
    
    try:
        tmpdir = tempfile.mkdtemp()
        
        # ==================== CAMADA BRONZE ====================
        log.info("[BRONZE] Copiando: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)
        
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[BRONZE] Arquivo baixado: %s", local_file)
        
        hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
        log.info("[BRONZE] ✅ Salvo em: s3://%s/%s", bucket, bronze_key)
        results['bronze'] = bronze_key
        
        # ==================== CAMADA SILVER ====================
        log.info("[SILVER] Processando: s3://%s/%s → s3://%s/%s", bucket, bronze_key, bucket, silver_key)
        
        df = pd.read_csv(local_file)
        original_count = len(df)
        log.info("[SILVER] Dados originais: %d linhas, %d colunas", original_count, len(df.columns))
        
        # Limpeza básica
        df = df.dropna(how='all')
        df = df.drop_duplicates()
        cleaned_count = len(df)
        log.info("[SILVER] Limpeza básica: %d linhas removidas (%d → %d)", 
                 original_count - cleaned_count, original_count, cleaned_count)
        
        # Aplicar inteligência automática de dados
        from lib.silver_layer import _apply_smart_transformations
        df = _apply_smart_transformations(df)
        
        # Salvar Parquet Silver
        silver_local = os.path.join(tmpdir, f"{basename_no_ext}_silver.parquet")
        df.to_parquet(silver_local, index=False, compression='snappy')
        
        hook.load_file(filename=silver_local, key=silver_key, bucket_name=bucket, replace=True)
        log.info("[SILVER] ✅ Salvo em: s3://%s/%s", bucket, silver_key)
        results['silver'] = silver_key
        results['rows'] = cleaned_count
        
        # ==================== CAMADA GOLD (DELTA LAKE) ====================
        log.info("[GOLD] Processando: s3://%s/%s → Delta Lake", bucket, silver_key)
        
        # Usar Delta Lake ao invés de Parquet simples
        try:
            from lib.gold_delta_layer import silver_to_gold_delta
            log.info("[GOLD] Importação gold_delta_layer OK, chamando silver_to_gold_delta...")
            
            gold_result = silver_to_gold_delta(
                source_filename=silver_key,
                target_table_name=target_table_name
            )
            
            results['gold_delta'] = gold_result.get('gold_delta')
            results['gold_format'] = 'delta'
            results['gold_version'] = gold_result.get('version', 0)
            log.info("[GOLD] ✅ Delta Lake salvo em: %s (versão %s)", 
                    gold_result.get('gold_delta'), gold_result.get('version', 0))
            
        except Exception as e:
            # Fallback para Parquet se Delta Lake falhou
            log.warning("[GOLD] ⚠️  Delta Lake falhou (%s: %s), usando fallback Parquet", 
                       type(e).__name__, str(e))
            
            # Recarregar DataFrame do Silver para fallback
            log.info("[GOLD] Recarregando Silver para fallback Parquet...")
            silver_local_fallback = hook.download_file(key=silver_key, bucket_name=bucket)
            df_fallback = pd.read_parquet(silver_local_fallback)
            
            from lib.gold_layer import _apply_analytical_intelligence
            df_fallback = _apply_analytical_intelligence(df_fallback, target_table_name)
            
            log.info("[GOLD] Aplicando otimizações finais...")
            
            gold_local = os.path.join(tmpdir, f"{basename_no_ext}_gold.parquet")
            df_fallback.to_parquet(gold_local, index=False, compression='snappy', engine='pyarrow')
            
            hook.load_file(filename=gold_local, key=gold_key, bucket_name=bucket, replace=True)
            log.info("[GOLD] ✅ Fallback Parquet salvo em: s3://%s/%s", bucket, gold_key)
            results['gold'] = gold_key
            results['gold_format'] = 'parquet_fallback'
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[MEDALLION] ✅ Pipeline completo concluído com sucesso!")
    log.info("[MEDALLION] Bronze: %s", results.get('bronze'))
    log.info("[MEDALLION] Silver: %s", results.get('silver'))
    if results.get('gold_format') == 'delta':
        log.info("[MEDALLION] Gold (Delta Lake): %s (versão %s)", 
                results.get('gold_delta'), results.get('gold_version', 0))
    else:
        log.info("[MEDALLION] Gold (Parquet): %s", results.get('gold'))
    
    return results
