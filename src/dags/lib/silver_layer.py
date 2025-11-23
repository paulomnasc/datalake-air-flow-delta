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
        
        # Limpeza básica de dados
        original_count = len(df)
        df = df.dropna(how='all')  # Remove linhas totalmente vazias
        df = df.drop_duplicates()  # Remove duplicatas
        cleaned_count = len(df)
        
        log.info("[SILVER] Limpeza básica: %d linhas removidas (%d → %d)", 
                 original_count - cleaned_count, original_count, cleaned_count)
        
        # Aplicar inteligência automática de dados
        df = _apply_smart_transformations(df)
        
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


def _apply_smart_transformations(df):
    """
    Aplica inteligência automática de dados usando bibliotecas Python.
    
    Transformações aplicadas automaticamente:
    1. Inferência e conversão de tipos de dados
    2. Detecção e conversão de datas
    3. Normalização de strings (trim, case)
    4. Detecção de colunas categóricas
    5. Preenchimento inteligente de nulos
    """
    import pandas as pd
    import numpy as np
    
    log.info("[SILVER] Aplicando transformações inteligentes automáticas...")
    original_cols = len(df.columns)
    
    # 1. Normalizar nomes de colunas
    df.columns = df.columns.str.strip().str.lower().str.replace(' ', '_')
    
    # 2. Detectar e converter datas automaticamente
    for col in df.columns:
        if df[col].dtype == 'object':
            try:
                # Tenta converter para datetime
                converted = pd.to_datetime(df[col], errors='coerce')
                # Se mais de 50% das linhas foram convertidas com sucesso, aceita
                if converted.notna().sum() / len(df) > 0.5:
                    df[col] = converted
                    log.info(f"[SILVER] ✓ Coluna '{col}' convertida para datetime")
            except:
                pass
    
    # 3. Normalizar colunas de texto (remover espaços extras)
    text_cols = df.select_dtypes(include=['object']).columns
    for col in text_cols:
        df[col] = df[col].astype(str).str.strip()
    
    # 4. Detectar e categorizar colunas com poucos valores únicos
    for col in df.select_dtypes(include=['object']).columns:
        unique_ratio = df[col].nunique() / len(df)
        # Se menos de 5% de valores únicos, pode ser categórica
        if unique_ratio < 0.05 and df[col].nunique() < 50:
            df[col] = df[col].astype('category')
            log.info(f"[SILVER] ✓ Coluna '{col}' convertida para category ({df[col].nunique()} valores únicos)")
    
    # 5. Inferir tipos numéricos automaticamente
    for col in df.select_dtypes(include=['object']).columns:
        try:
            # Tenta converter para numérico
            converted = pd.to_numeric(df[col], errors='coerce')
            # Se mais de 80% foram convertidos, aceita
            if converted.notna().sum() / len(df) > 0.8:
                df[col] = converted
                log.info(f"[SILVER] ✓ Coluna '{col}' convertida para numérico")
        except:
            pass
    
    # 6. Preenchimento inteligente de nulos por tipo
    for col in df.columns:
        null_count = df[col].isna().sum()
        if null_count > 0:
            if df[col].dtype in ['int64', 'float64']:
                # Numéricos: preencher com mediana (mais robusto que média)
                df[col] = df[col].fillna(df[col].median())
                log.debug(f"[SILVER] ✓ Coluna '{col}': {null_count} nulos preenchidos com mediana")
            elif df[col].dtype == 'object':
                # Texto: preencher com 'N/A'
                df[col] = df[col].fillna('N/A')
                log.debug(f"[SILVER] ✓ Coluna '{col}': {null_count} nulos preenchidos com 'N/A'")
            elif pd.api.types.is_categorical_dtype(df[col]):
                # Categóricos: preencher com moda
                if not df[col].mode().empty:
                    df[col] = df[col].fillna(df[col].mode()[0])
                    log.debug(f"[SILVER] ✓ Coluna '{col}': {null_count} nulos preenchidos com moda")
    
    # 7. Criar metadados úteis (colunas de auditoria)
    df['_silver_processed_at'] = pd.Timestamp.now()
    df['_silver_row_quality'] = df.notna().sum(axis=1) / len(df.columns) * 100
    
    new_cols = len(df.columns) - original_cols
    log.info(f"[SILVER] Transformações inteligentes concluídas: {new_cols} colunas adicionadas")
    log.info(f"[SILVER] Tipos finais: {df.dtypes.value_counts().to_dict()}")
    
    return df
