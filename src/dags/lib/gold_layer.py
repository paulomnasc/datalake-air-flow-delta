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
        
        # Aplicar inteligência analítica automática
        df = _apply_analytical_intelligence(df)
        
        log.info("[GOLD] Aplicando otimizações finais...")
        
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


def _apply_analytical_intelligence(df):
    """
    Aplica inteligência analítica automática para criar métricas agregadas.
    
    Cria automaticamente:
    1. Agregações numéricas (somas, médias, contagens)
    2. Estatísticas descritivas por grupo
    3. Rankings e percentis
    4. Flags e indicadores analíticos
    5. Time-series features (se houver datas)
    """
    import pandas as pd
    import numpy as np
    
    log.info("[GOLD] Aplicando inteligência analítica...")
    original_shape = df.shape
    
    # ==================== 1. MÉTRICAS NUMÉRICAS GLOBAIS ====================
    numeric_cols = df.select_dtypes(include=[np.number]).columns.tolist()
    # Remover colunas de auditoria
    numeric_cols = [col for col in numeric_cols if not col.startswith('_silver_')]
    
    if numeric_cols:
        log.info(f"[GOLD] Criando métricas para {len(numeric_cols)} colunas numéricas")
        
        # Criar sufixos para métricas agregadas
        for col in numeric_cols:
            # Percentil da linha em relação ao dataset
            df[f'{col}_percentile'] = df[col].rank(pct=True) * 100
            
            # Z-score (quantos desvios padrão da média)
            mean_val = df[col].mean()
            std_val = df[col].std()
            if std_val > 0:
                df[f'{col}_zscore'] = (df[col] - mean_val) / std_val
            
            # Flag de outlier (z-score > 3 ou < -3)
            if f'{col}_zscore' in df.columns:
                df[f'{col}_is_outlier'] = (df[f'{col}_zscore'].abs() > 3).astype(int)
            
            log.debug(f"[GOLD] ✓ Métricas criadas para: {col}")
    
    # ==================== 2. ANÁLISE CATEGÓRICA ====================
    categorical_cols = df.select_dtypes(include=['object', 'category']).columns.tolist()
    categorical_cols = [col for col in categorical_cols if not col.startswith('_silver_')]
    
    if categorical_cols:
        log.info(f"[GOLD] Criando métricas para {len(categorical_cols)} colunas categóricas")
        
        for col in categorical_cols:
            # Frequência da categoria (quantas vezes aparece)
            value_counts = df[col].value_counts()
            df[f'{col}_frequency'] = df[col].map(value_counts).astype('int64')
            
            # Percentual do total (converter para numérico antes de dividir)
            df[f'{col}_pct'] = (df[f'{col}_frequency'].astype('float64') / len(df) * 100).round(2)
            
            # Flag: é categoria mais comum?
            most_common = df[col].mode()[0] if not df[col].mode().empty else None
            if most_common:
                df[f'{col}_is_top'] = (df[col] == most_common).astype(int)
            
            log.debug(f"[GOLD] ✓ Métricas criadas para: {col}")
    
    # ==================== 3. ANÁLISE TEMPORAL ====================
    datetime_cols = df.select_dtypes(include=['datetime64']).columns.tolist()
    
    if datetime_cols:
        log.info(f"[GOLD] Criando features temporais para {len(datetime_cols)} colunas de data")
        
        for col in datetime_cols:
            # Extrair componentes de tempo
            df[f'{col}_year'] = df[col].dt.year
            df[f'{col}_month'] = df[col].dt.month
            df[f'{col}_quarter'] = df[col].dt.quarter
            df[f'{col}_day_of_week'] = df[col].dt.dayofweek
            df[f'{col}_day_of_month'] = df[col].dt.day
            df[f'{col}_week_of_year'] = df[col].dt.isocalendar().week
            
            # Flags úteis
            df[f'{col}_is_weekend'] = df[col].dt.dayofweek.isin([5, 6]).astype(int)
            df[f'{col}_is_month_start'] = df[col].dt.is_month_start.astype(int)
            df[f'{col}_is_month_end'] = df[col].dt.is_month_end.astype(int)
            df[f'{col}_is_quarter_start'] = df[col].dt.is_quarter_start.astype(int)
            df[f'{col}_is_quarter_end'] = df[col].dt.is_quarter_end.astype(int)
            
            # Dias desde epoch (útil para cálculos)
            df[f'{col}_days_since_epoch'] = (df[col] - pd.Timestamp('1970-01-01')).dt.days
            
            log.debug(f"[GOLD] ✓ Features temporais criadas para: {col}")
    
    # ==================== 4. AGREGAÇÕES POR GRUPO ====================
    # Se houver colunas categóricas E numéricas, criar agregações
    if categorical_cols and numeric_cols:
        log.info("[GOLD] Criando agregações por grupo...")
        
        # Pegar primeira coluna categórica como dimensão principal
        group_col = categorical_cols[0]
        
        # Calcular médias por grupo para cada coluna numérica
        for num_col in numeric_cols[:3]:  # Limitar a 3 para não explodir dimensionalidade
            group_means = df.groupby(group_col)[num_col].transform('mean')
            df[f'{num_col}_avg_by_{group_col}'] = group_means
            
            # Comparação com a média do grupo
            df[f'{num_col}_vs_group_avg'] = df[num_col] - group_means
            df[f'{num_col}_vs_group_pct'] = ((df[num_col] / group_means - 1) * 100).round(2)
            
        log.info(f"[GOLD] ✓ Agregações criadas usando dimensão: {group_col}")
    
    # ==================== 5. MÉTRICAS DE QUALIDADE ====================
    # Percentual de completude por linha (já existe do Silver, mas recalcular)
    df['_gold_completeness'] = df.notna().sum(axis=1) / len(df.columns) * 100
    
    # Contagem de campos numéricos válidos
    if numeric_cols:
        df['_gold_numeric_fields_count'] = df[numeric_cols].notna().sum(axis=1)
    
    # ==================== 6. RANKINGS GLOBAIS ====================
    # Se tiver coluna que parece ser ID/chave primária, criar ranking
    potential_id_cols = [col for col in df.columns if 'id' in col.lower() or 'number' in col.lower()]
    if potential_id_cols and numeric_cols:
        # Usar primeira coluna numérica para ranking
        rank_col = numeric_cols[0]
        df['_gold_global_rank'] = df[rank_col].rank(ascending=False, method='dense').astype(int)
        log.info(f"[GOLD] ✓ Ranking global criado baseado em: {rank_col}")
    
    # ==================== 7. METADATA ====================
    df['_gold_processed_at'] = pd.Timestamp.now()
    df['_gold_feature_count'] = len(df.columns)
    
    new_cols = df.shape[1] - original_shape[1]
    log.info(f"[GOLD] Inteligência analítica concluída: {new_cols} novas colunas criadas")
    log.info(f"[GOLD] Shape: {original_shape} → {df.shape}")
    
    return df
