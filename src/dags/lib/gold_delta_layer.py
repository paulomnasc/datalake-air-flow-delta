import logging
import os
import tempfile
from datetime import datetime

log = logging.getLogger(__name__)

def silver_to_gold_delta(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Gold com Delta Lake: Dados agregados com versionamento e ACID.
    
    Diferenças vs Parquet:
    - ACID transactions
    - Time travel (versões anteriores)
    - Schema evolution
    - MERGE/UPDATE/DELETE support
    - Compactação automática
    
    Args:
        source_filename: Caminho do arquivo Silver (Parquet)
        target_table_name: Nome da tabela Gold
    """
    log.info(f"[GOLD-DELTA] Iniciando agregação Delta Lake para: {target_table_name}")
    log.info(f"[GOLD-DELTA] Arquivo origem: {source_filename}")
    
    log.info("[GOLD-DELTA] Tentando importar deltalake...")
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        log.info("[GOLD-DELTA] ✓ S3Hook importado")
        from deltalake import write_deltalake, DeltaTable
        log.info("[GOLD-DELTA] ✓ deltalake importado com sucesso!")
    except ImportError as e:
        log.warning(f"[GOLD-DELTA] Delta Lake não disponível, usando Parquet: {e}")
        # Fallback para versão Parquet
        from lib.gold_layer import silver_to_gold
        return silver_to_gold(source_filename, target_table_name, **kwargs)
    except Exception as e:
        log.error("Erro ao importar dependências: %s", e)
        raise

    import pandas as pd
    import pyarrow as pa
    import pyarrow.parquet as pq
    
    bucket = os.environ.get("MINIO_BUCKET", "lab01")
    hook = S3Hook(aws_conn_id='minio_conn')

    # Determina chave Silver e Gold (Delta)
    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    basename_no_ext = os.path.splitext(basename)[0]
    
    # Delta Lake usa diretório ao invés de arquivo único
    gold_delta_path = f"s3://{bucket}/gold/{target_table_name}_delta/"

    log.info("[GOLD-DELTA] Processando: s3://%s/%s → %s", bucket, src_key, gold_delta_path)

    tmpdir = None
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Download do arquivo Silver
        log.info("[GOLD-DELTA] Baixando Silver de: s3://%s/%s", bucket, src_key)
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[GOLD-DELTA] Arquivo Silver baixado: %s", local_file)

        # Leitura do Parquet Silver
        log.info("[GOLD-DELTA] Lendo Parquet Silver...")
        df = pd.read_parquet(local_file)
        log.info("[GOLD-DELTA] Dados Silver: %d linhas, %d colunas", len(df), len(df.columns))
        
        if df.empty:
            log.warning("[GOLD-DELTA] ⚠️ DataFrame vazio. Nenhuma agregação será feita.")
            return {
                "silver": silver_key,
                "gold_delta": gold_delta_path,
                "rows": 0,
                "status": "empty"
            }

        # Aplicar inteligência analítica (mesma lógica do gold_layer.py)
        log.info("[GOLD-DELTA] Aplicando inteligência analítica...")
        df_enriched = _apply_analytical_intelligence(df, target_table_name)
        
        original_shape = (len(df), len(df.columns))
        final_shape = (len(df_enriched), len(df_enriched.columns))
        new_columns = final_shape[1] - original_shape[1]
        
        log.info(f"[GOLD-DELTA] Shape: {original_shape} → {final_shape}")
        log.info(f"[GOLD-DELTA] Inteligência analítica concluída: {new_columns} novas colunas criadas")

        # Adicionar metadados Delta Lake
        df_enriched['_delta_version'] = datetime.now().strftime('%Y%m%d_%H%M%S')
        df_enriched['_ingestion_timestamp'] = datetime.now()
        
        # Converter categorical para string (Delta Lake não suporta Dictionary type)
        for col in df_enriched.select_dtypes(include=['category']).columns:
            df_enriched[col] = df_enriched[col].astype(str)
        
        # Converter para PyArrow Table
        table = pa.Table.from_pandas(df_enriched)
        
        # Pegar credenciais do S3Hook do Airflow
        credentials = hook.get_credentials()
        endpoint_url = hook.conn_config.endpoint_url or "http://minio:9000"
        
        # Configurar storage options para MinIO/S3
        storage_options = {
            "AWS_ACCESS_KEY_ID": credentials.access_key,
            "AWS_SECRET_ACCESS_KEY": credentials.secret_key,
            "AWS_ENDPOINT_URL": endpoint_url,
            "AWS_REGION": "us-east-1",
            "AWS_ALLOW_HTTP": "true",
            "AWS_S3_ALLOW_UNSAFE_RENAME": "true"
        }
        
        log.info("[GOLD-DELTA] Gravando Delta Lake...")
        log.info(f"[GOLD-DELTA] Endpoint: {endpoint_url}")
        
        # Verificar se tabela Delta já existe
        try:
            dt = DeltaTable(gold_delta_path, storage_options=storage_options)
            log.info(f"[GOLD-DELTA] Tabela Delta existente encontrada. Versão: {dt.version()}")
            
            # Append mode (adiciona novos dados)
            write_deltalake(
                gold_delta_path,
                table,
                mode="append",
                storage_options=storage_options,
                schema_mode="merge"  # Permite evolução de schema
            )
            log.info(f"[GOLD-DELTA] ✅ Dados adicionados à tabela Delta (append)")
            
        except Exception as e:
            log.info(f"[GOLD-DELTA] Tabela Delta não existe. Criando nova: {e}")
            
            # Overwrite mode (cria nova tabela)
            write_deltalake(
                gold_delta_path,
                table,
                mode="overwrite",
                storage_options=storage_options
            )
            log.info(f"[GOLD-DELTA] ✅ Nova tabela Delta criada")

        log.info(f"[GOLD-DELTA] ✅ Tabela Delta salva em: {gold_delta_path}")
        
        return {
            "silver": src_key,
            "gold_delta": gold_delta_path,
            "rows": len(df_enriched),
            "columns": len(df_enriched.columns),
            "new_features": new_columns,
            "format": "delta",
            "version": dt.version() if 'dt' in locals() else 0,
            "status": "success"
        }
        
    except Exception as e:
        log.error(f"[GOLD-DELTA] ❌ Erro ao processar Gold Delta: {e}")
        raise
        
    finally:
        if tmpdir and os.path.exists(tmpdir):
            import shutil
            shutil.rmtree(tmpdir)
            log.debug("[GOLD-DELTA] Diretório temporário removido.")


def _apply_analytical_intelligence(df, table_name):
    """
    Aplica transformações analíticas inteligentes ao DataFrame.
    Mesma lógica do gold_layer.py para manter consistência.
    """
    import pandas as pd
    import numpy as np
    
    df_result = df.copy()
    
    # 1. FEATURES NUMÉRICAS
    numeric_cols = df_result.select_dtypes(include=[np.number]).columns.tolist()
    if numeric_cols:
        log.info(f"[GOLD-DELTA] Criando métricas para {len(numeric_cols)} colunas numéricas")
        for col in numeric_cols:
            if df_result[col].nunique() > 1:
                df_result[f'{col}_zscore'] = (df_result[col] - df_result[col].mean()) / df_result[col].std()
                df_result[f'{col}_percentile'] = df_result[col].rank(pct=True) * 100
                df_result[f'{col}_min_max_scaled'] = (df_result[col] - df_result[col].min()) / (df_result[col].max() - df_result[col].min())
    
    # 2. FEATURES CATEGÓRICAS
    categorical_cols = df_result.select_dtypes(include=['object', 'category']).columns.tolist()
    if categorical_cols:
        log.info(f"[GOLD-DELTA] Criando métricas para {len(categorical_cols)} colunas categóricas")
        for col in categorical_cols:
            if df_result[col].nunique() > 1 and df_result[col].nunique() < len(df_result) * 0.5:
                value_counts = df_result[col].value_counts()
                df_result[f'{col}_frequency'] = df_result[col].map(value_counts).astype('int64')
                df_result[f'{col}_pct'] = (df_result[f'{col}_frequency'].astype('float64') / len(df_result) * 100).round(2)
    
    # 3. FEATURES TEMPORAIS
    date_cols = df_result.select_dtypes(include=['datetime64']).columns.tolist()
    if date_cols:
        log.info(f"[GOLD-DELTA] Criando features temporais para {len(date_cols)} colunas de data")
        for col in date_cols:
            df_result[f'{col}_year'] = df_result[col].dt.year
            df_result[f'{col}_month'] = df_result[col].dt.month
            df_result[f'{col}_day'] = df_result[col].dt.day
            df_result[f'{col}_dayofweek'] = df_result[col].dt.dayofweek
            df_result[f'{col}_quarter'] = df_result[col].dt.quarter
    
    # 4. AGREGAÇÕES POR GRUPO
    if len(categorical_cols) > 0 and len(numeric_cols) > 0:
        group_col = categorical_cols[0]
        if df_result[group_col].nunique() > 1 and df_result[group_col].nunique() < len(df_result) * 0.8:
            log.info(f"[GOLD-DELTA] ✓ Agregações criadas usando dimensão: {group_col}")
            for num_col in numeric_cols[:3]:
                group_stats = df_result.groupby(group_col)[num_col].transform('mean')
                df_result[f'{num_col}_by_{group_col}_mean'] = group_stats
    
    # 5. RANKING GLOBAL
    if len(numeric_cols) > 0:
        rank_col = numeric_cols[0]
        df_result[f'{rank_col}_rank'] = df_result[rank_col].rank(method='dense', ascending=False).astype(int)
        log.info(f"[GOLD-DELTA] ✓ Ranking global criado baseado em: {rank_col}")
    
    return df_result
