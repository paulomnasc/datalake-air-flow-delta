import logging
import os
import tempfile
import json

log = logging.getLogger(__name__)

def raw_to_bronze(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Bronze: Cópia dos dados de Raw para Bronze com conversão para Parquet.
    
    Transforma arquivos para formato Parquet com compressão Snappy (boa prática moderna).
    Sem limpeza ou validação - apenas cópia com otimização de formato.
    
    Suporta:
    - Arquivo único: 'raw/pasta/arquivo.csv' ou 'raw/pasta/arquivo.json'
    - Pasta com múltiplos arquivos: 'raw/pasta/' (com barra no final)
    """
    log.info(f"[BRONZE] Iniciando ingestão para: {target_table_name}")
    log.info(f"[BRONZE] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        import pandas as pd
    except Exception as e:
        log.error("S3Hook/Pandas não disponível: %s", e)
        raise

    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')
    dag_id = kwargs.get('dag_id', 'default')

    def _read_json_to_df(path: str):
        """Carrega JSON de forma robusta (lista, objeto, NDJSON)."""
        # 1) NDJSON (um objeto por linha)
        try:
            df = pd.read_json(path, lines=True)
            if not df.empty:
                return df
        except Exception:
            pass

        # 2) JSON padrão (lista ou objeto)
        try:
            df = pd.read_json(path)
            if not df.empty:
                # Caso venha tudo em uma coluna objeto, tenta expandir
                if len(df.columns) == 1 and df.dtypes.iloc[0] == 'object':
                    col = df.columns[0]
                    try:
                        normalized = pd.json_normalize(df[col].apply(lambda x: json.loads(x) if isinstance(x, str) else x))
                        if not normalized.empty:
                            return normalized
                    except Exception:
                        pass
                return df
        except Exception:
            pass

        # 3) Leitura manual e normalização
        with open(path, 'r') as f:
            payload = json.load(f)
        if isinstance(payload, list):
            return pd.json_normalize(payload)
        if isinstance(payload, dict):
            return pd.json_normalize(payload)
        raise ValueError("Formato JSON não suportado para Bronze")
    
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
                basename_no_ext = os.path.splitext(basename)[0]
                
                # Bronze: manter apenas o basename do arquivo Raw em Parquet
                # Estrutura: bronze/{dag_id}/{target_table_name}/{basename_no_ext}.parquet
                bronze_key = f"bronze/{dag_id}/{target_table_name}/{basename_no_ext}.parquet"
                
                # Download
                local_file = hook.download_file(key=file_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
                log.info("[BRONZE] Arquivo baixado: %s", local_file)
                
                # Ler e converter para Parquet (suporta CSV, JSON)
                try:
                    file_ext = os.path.splitext(local_file)[1].lower()
                    if file_ext == '.csv':
                        df = pd.read_csv(local_file)
                    elif file_ext == '.json':
                        df = _read_json_to_df(local_file)
                    else:
                        log.warning("[BRONZE] ⚠️ Formato não suportado: %s, copiando como-é", file_ext)
                        hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
                        log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
                        results.append(bronze_key)
                        continue
                    
                    # Salvar como Parquet com compressão Snappy
                    local_parquet = os.path.join(tmpdir, f"{basename_no_ext}.parquet")
                    df.to_parquet(local_parquet, index=False, compression='snappy', engine='pyarrow')
                    log.info("[BRONZE] Arquivo convertido para Parquet: %d linhas", len(df))
                    
                    # Upload para Bronze
                    hook.load_file(filename=local_parquet, key=bronze_key, bucket_name=bucket, replace=True)
                    log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
                    results.append(bronze_key)
                except Exception as e:
                    log.error("[BRONZE] ❌ Erro ao processar %s: %s", file_key, e)
                    raise
        else:
            # É um arquivo específico
            basename = os.path.basename(src_key)
            basename_no_ext = os.path.splitext(basename)[0]
            
            # Bronze: converter para Parquet
            # Estrutura: bronze/{dag_id}/{target_table_name}/{basename_no_ext}.parquet
            bronze_key = f"bronze/{dag_id}/{target_table_name}/{basename_no_ext}.parquet"
            
            log.info("[BRONZE] Copiando e convertendo: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)
            
            # Download do arquivo Raw
            local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
            log.info("[BRONZE] Arquivo baixado: %s", local_file)

            # Ler e converter para Parquet (suporta CSV, JSON)
            try:
                file_ext = os.path.splitext(local_file)[1].lower()
                if file_ext == '.csv':
                    df = pd.read_csv(local_file)
                elif file_ext == '.json':
                    df = _read_json_to_df(local_file)
                else:
                    log.warning("[BRONZE] ⚠️ Formato não suportado: %s, copiando como-é", file_ext)
                    hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
                    log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
                    results.append(bronze_key)
                    return {"layer": "bronze", "files_processed": 1, "keys": results}
                
                # Salvar como Parquet com compressão Snappy
                local_parquet = os.path.join(tmpdir, f"{basename_no_ext}.parquet")
                df.to_parquet(local_parquet, index=False, compression='snappy', engine='pyarrow')
                log.info("[BRONZE] Arquivo convertido para Parquet: %d linhas", len(df))
                
                # Upload para camada Bronze
                hook.load_file(filename=local_parquet, key=bronze_key, bucket_name=bucket, replace=True)
                log.info("[BRONZE] ✅ Arquivo salvo em: s3://%s/%s", bucket, bronze_key)
                results.append(bronze_key)
            except Exception as e:
                log.error("[BRONZE] ❌ Erro ao processar arquivo: %s", e)
                raise
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[BRONZE] Processo concluído com sucesso! %d arquivo(s) processado(s)", len(results))
    return {"layer": "bronze", "files_processed": len(results), "keys": results}
