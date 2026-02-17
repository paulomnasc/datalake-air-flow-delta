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
    
    Suporta:
    - Arquivo único: 'raw/pasta/arquivo.csv' ou 'bronze/dag_id/tabela/arquivo.csv'
    - Pasta com múltiplos arquivos: 'raw/dag_id/' ou 'bronze/dag_id/tabela/' (com barra no final)
    """
    log.info(f"[SILVER] Iniciando transformação para: {target_table_name}")
    log.info(f"[SILVER] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import pandas as pd
    
    # Bucket deve ser sempre igual ao owner da DAG (campo 'owner' do contexto/kwargs)
    # Nunca usar bucket_name de kwargs nem variável de ambiente!
    bucket = kwargs.get('owner', 'lab01')
    hook = S3Hook(aws_conn_id='minio_conn')

    # Determina chave Bronze e Silver
    src_key = source_filename.lstrip('/')
    dag_id = kwargs.get('dag_id') or 'default'
    
    # Detectar se é pasta (termina com /)
    is_folder = src_key.endswith('/')
    
    # Se source_filename aponta para Raw, converte para Bronze mantendo apenas o basename
    if src_key.startswith('raw/'):
        # Extrair apenas o filename: raw/dag_id/file.ext -> bronze/{target_table_name}/file.parquet
        basename = os.path.basename(src_key)
        src_key = f"bronze/{target_table_name}/{os.path.splitext(basename)[0]}.parquet"
        is_folder = False
    
    log.info("[SILVER] Pasta detectada: %s", "Sim" if is_folder else "Não")

    tmpdir = None
    processed_count = 0
    results = []
    
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Se é pasta, listar arquivos; senão processar como arquivo único
        bronze_keys_to_process = []
        if is_folder:
            log.info("[SILVER] Listando arquivos em: %s", src_key)
            keys = hook.list_keys(bucket_name=bucket, prefix=src_key)
            if not keys:
                log.warning("[SILVER] ⚠️ Nenhum arquivo encontrado em %s", src_key)
                return {"layer": "silver", "files_processed": 0}
            for key in keys:
                if key != src_key:  # Pula a própria pasta
                    bronze_keys_to_process.append(key)
        else:
            bronze_keys_to_process = [src_key]
        
        log.info("[SILVER] Total de arquivos a processar: %d", len(bronze_keys_to_process))
        
        # Processar cada arquivo
        import json
        for bronze_key in bronze_keys_to_process:
            log.info("[SILVER] Processando: %s", bronze_key)
            
            # Download do arquivo Bronze
            local_file = hook.download_file(key=bronze_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
            log.info("[SILVER] Arquivo Bronze baixado: %s", local_file)
        
            basename = os.path.basename(bronze_key)
            basename_no_ext = os.path.splitext(basename)[0]

            # Leitura e transformação com Pandas - detecta automaticamente CSV ou JSON
            file_extension = os.path.splitext(local_file)[1].lower()
            
            df = None
            if file_extension == '.json':
                log.info("[SILVER] Lendo arquivo JSON...")
                # Leitura robusta de JSON: suporta NDJSON, lista de objetos e
                # objetos com chave-raiz igual ao nome da tabela
                def _normalize_json_payload(payload):
                    """Converte payload JSON arbitrário em DataFrame colunares."""
                    # Lista de objetos diretamente
                    if isinstance(payload, list):
                        if payload and isinstance(payload[0], dict):
                            return pd.json_normalize(payload)
                        else:
                            return pd.DataFrame(payload)
                    # Objeto dict
                    if isinstance(payload, dict):
                        # Se possui somente uma chave e ela corresponde ao nome da tabela,
                        # expandir o conteúdo dessa chave
                        if len(payload) == 1:
                            only_key = next(iter(payload))
                            val = payload[only_key]
                            if isinstance(val, list):
                                return pd.json_normalize(val)
                            if isinstance(val, dict):
                                return pd.json_normalize([val])
                        # Caso geral: normalizar o dict em uma única linha
                        return pd.json_normalize([payload])
                    # Caso não seja JSON estruturado, tentar DataFrame direto
                    return pd.DataFrame(payload)

                # 1) Tenta NDJSON (um objeto por linha)
                try:
                    ndjson_df = pd.read_json(local_file, lines=True)
                    # Se NDJSON devolveu algo com colunas significativas, usa
                    if not ndjson_df.empty and len(ndjson_df.columns) > 0:
                        df = ndjson_df
                        log.info("[SILVER] JSON no formato NDJSON detectado (%d colunas)", len(df.columns))
                except Exception:
                    pass

                # 2) Tenta leitura padrão
                if df is None:
                    try:
                        std_df = pd.read_json(local_file)
                        # Se a leitura padrão gerar uma coluna única com dict/list, normaliza
                        if len(std_df.columns) == 1:
                            col = std_df.columns[0]
                            series = std_df[col]
                            first_val = series.dropna().iloc[0] if not series.dropna().empty else None
                            if isinstance(first_val, (dict, list)):
                                df = _normalize_json_payload(series.tolist())
                                log.info("[SILVER] JSON com chave-raiz detectado; normalizado de coluna única '%s'", col)
                        if df is None:
                            df = std_df
                    except Exception:
                        # 3) Fallback: carregar texto e parsear com json.load/json.loads
                        with open(local_file, 'r', encoding='utf-8') as f:
                            content = f.read()
                        try:
                            payload = json.loads(content)
                            df = _normalize_json_payload(payload)
                            log.info("[SILVER] JSON carregado via json.loads e normalizado (%d colunas)", len(df.columns))
                        except Exception as e:
                            log.error("[SILVER] Falha ao ler JSON: %s", e)
                            raise
            elif file_extension == '.csv':
                log.info("[SILVER] Lendo arquivo CSV...")
                df = pd.read_csv(local_file)
            elif file_extension == '.parquet':
                log.info("[SILVER] Lendo arquivo Parquet...")
                df = pd.read_parquet(local_file)
            else:
                log.warning("[SILVER] Extensão desconhecida '%s', tentando CSV como fallback...", file_extension)
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
            
            # ========== VALIDAÇÃO DE QUALIDADE DE DADOS ==========
            log.info("[SILVER] Aplicando validação de qualidade de dados...")
            from lib.data_quality import validate_dataframe
            
            df, quality_metrics = validate_dataframe(df, target_table_name)
            
            log.info("[SILVER] ✓ Validação de qualidade concluída:")
            log.info("[SILVER]   - Taxa de aprovação: %.1f%%", quality_metrics['pass_rate'])
            log.info("[SILVER]   - Linhas aprovadas: %d", quality_metrics['rows_passed'])
            log.info("[SILVER]   - Linhas reprovadas: %d", quality_metrics['rows_failed'])
            
            # Silver: estrutura silver/{target_table_name}/{timestamp_hash}.parquet
            basename_no_ext = os.path.splitext(os.path.basename(bronze_key))[0]
            silver_key = f"silver/{target_table_name}/{basename_no_ext}.parquet"
            silver_local = os.path.join(tmpdir, f"{basename_no_ext}.parquet")
            df.to_parquet(silver_local, index=False, compression='snappy')
            log.info("[SILVER] Parquet criado: %s", silver_local)

            # Upload para camada Silver
            hook.load_file(filename=silver_local, key=silver_key, bucket_name=bucket, replace=True)
            log.info("[SILVER] ✅ Arquivo salvo em: s3://%s/%s", bucket, silver_key)
            results.append(silver_key)
            processed_count += 1
        
    finally:
        if tmpdir is not None and os.path.exists(tmpdir):
            import shutil
            try:
                shutil.rmtree(tmpdir)
            except Exception:
                pass

    log.info("[SILVER] Processo concluído! %d arquivo(s) processado(s)", processed_count)
    return {"layer": "silver", "files_processed": processed_count, "keys": results}


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
