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
    
    Parâmetros:
        source_filename: Caminho do arquivo na camada raw (ex: 'raw/pipe-albuns/Track.json')
        target_table_name: Nome da tabela de destino
        bucket_name: (opcional) Nome do bucket MinIO, default usa MINIO_BUCKET env ou 'lab01'
    """
    log.info(f"[MEDALLION] Iniciando pipeline completo para: {target_table_name}")
    log.info(f"[MEDALLION] Arquivo origem: {source_filename}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except Exception as e:
        log.error("S3Hook não disponível: %s", e)
        raise

    import pandas as pd
    
    # Permite override do bucket via kwargs, senão usa env ou default
    bucket = kwargs.get('bucket_name') or os.environ.get("MINIO_BUCKET", "lab01")
    log.info(f"[MEDALLION] Usando bucket: {bucket}")
    
    hook = S3Hook(aws_conn_id='minio_conn')

    src_key = source_filename.lstrip('/')
    basename = os.path.basename(src_key)
    basename_no_ext = os.path.splitext(basename)[0]
    
    # Definir chaves de todas as camadas
    dag_id = kwargs.get('dag_id', 'default')
    bronze_key = f"bronze/{dag_id}/{target_table_name}/{basename}"
    silver_key = f"silver/{dag_id}/{target_table_name}/{basename_no_ext}.parquet"
    gold_key = f"gold/{dag_id}/{target_table_name}/{basename_no_ext}.parquet"
    
    tmpdir = None
    results = {}
    
    # Verificar se Atlas está habilitado
    atlas_enabled = os.getenv("ENABLE_ATLAS", "false").lower() == "true"
    
    try:
        tmpdir = tempfile.mkdtemp()
        
        # Atlas client para registrar metadados e processos
        atlas = None
        if atlas_enabled:
            from .atlas_client import AtlasClient
            from .atlas_lineage import register_table, register_process
            atlas = AtlasClient()
            db_name = os.getenv("ATLAS_HIVE_DB", "medallion")
            
            # Flag para controlar registro de processos (pode ser lento)
            register_processes = os.getenv("ATLAS_REGISTER_PROCESSES", "false").lower() == "true"
            
            # Pegar owner dos kwargs (vem da webapp via factory_master)
            owner = kwargs.get('owner', 'airflow')
            
            # Verificar se há origem MySQL para criar processo MySQL→Raw
            mysql_qualified_name = kwargs.get('mysql_qualified_name')
            
            # Aguarda o Atlas ficar pronto para evitar 5xx durante inicialização
            try:
                atlas.wait_until_ready(timeout_seconds=90)
            except Exception as e:
                log.warning("[ATLAS] Atlas não respondeu no tempo esperado (%s), prosseguindo assim mesmo", e)
            atlas.ensure_hive_db(db_name)
            # Registrar entidade RAW (referência) para permitir lineage completo
            raw_table = f"{target_table_name}_raw"
            log.info("[ATLAS] Registrando tabela RAW: %s", f"{db_name}.{raw_table}@cluster")
            atlas.create_hive_table(
                qualified_name=f"{db_name}.{raw_table}@cluster",
                name=raw_table,
                db=db_name,
                columns=None,
                description=f"Raw source for {target_table_name}",
                owner=owner
            )
            
            # Se temos origem MySQL, criar processo MySQL → Raw
            if mysql_qualified_name and register_processes:
                try:
                    log.info("[ATLAS] Criando processo MySQL→Raw...")
                    register_process(
                        atlas,
                        step_name=f"mysql_to_raw_{target_table_name}",
                        layer_from="mysql",
                        layer_to="raw",
                        inputs_qn=[mysql_qualified_name],
                        outputs_qn=[f"{db_name}.{raw_table}@cluster"]
                    )
                    log.info("[ATLAS] ✅ Processo MySQL→Raw criado")
                except Exception as e:
                    log.warning("[ATLAS] Falha ao criar processo MySQL→Raw: %s", e)
        else:
            log.info("[MEDALLION] Atlas desabilitado (ENABLE_ATLAS=false), pulando registro de metadados")
        
        # ==================== CAMADA BRONZE ====================
        log.info("[BRONZE] Copiando: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)
        
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[BRONZE] Arquivo baixado: %s", local_file)
        
        hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
        log.info("[BRONZE] ✅ Salvo em: s3://%s/%s", bucket, bronze_key)
        results['bronze'] = bronze_key

        # Registrar tabela Bronze
        if atlas_enabled and atlas:
            bronze_table = f"{target_table_name}_bronze"
            try:
                # Construir schema de colunas (bronze: do CSV)
                import pandas as pd
                df_bronze = pd.read_csv(local_file)
                bronze_columns = []
                for col in df_bronze.columns.tolist():
                    bronze_columns.append({
                        "qualifiedName": f"{db_name}.{bronze_table}.{col}@cluster",
                        "name": col,
                        "type": "string"  # tipos simples para Atlas
                    })
                log.info("[ATLAS] Registrando tabela Bronze: %s", f"{db_name}.{bronze_table}@cluster")
                res_bronze = register_table(
                    atlas,
                    layer="bronze",
                    table=bronze_table,
                    db=db_name,
                    columns=None,  # Criar tabela sem colunas primeiro
                    owner=owner
                )
                log.info("[ATLAS] ✓ Tabela Bronze registrada: %s", f"{db_name}.{bronze_table}@cluster")
                
                # Adicionar colunas à tabela Bronze
                try:
                    atlas.add_columns_to_table(
                        table_qualified_name=f"{db_name}.{bronze_table}@cluster",
                        columns=bronze_columns
                    )
                    log.info("[ATLAS] ✓ %d colunas adicionadas à Bronze", len(bronze_columns))
                except Exception as e:
                    log.warning("[ATLAS] Falha ao adicionar colunas (Bronze): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
                
                # Registrar processo Raw → Bronze (opcional)
                if register_processes:
                    try:
                        register_process(
                            atlas,
                            step_name=f"raw_to_bronze_{target_table_name}",
                            layer_from="raw",
                            layer_to="bronze",
                            inputs_qn=[f"{db_name}.{raw_table}@cluster"],
                            outputs_qn=[f"{db_name}.{bronze_table}@cluster"]
                        )
                        log.info("[ATLAS] ✅ Processo Raw→Bronze registrado")
                    except Exception as e:
                        err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                        log.warning("[ATLAS] Falha ao registrar processo raw→bronze: %s", err_fmt)
                else:
                    log.info("[ATLAS] ⏭️  Registro de processos desabilitado (ATLAS_REGISTER_PROCESSES=false)")
            except Exception as e:
                err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                log.warning("[ATLAS] Falha ao registrar bronze: %s", err_fmt)
        
        # ==================== CAMADA SILVER ====================
        # Usa a implementação oficial da camada Silver para garantir colunas de qualidade
        log.info("[SILVER] Chamando bronze_to_silver para aplicar transformações e validação de qualidade...")
        from lib.silver_layer import bronze_to_silver
        silver_result = bronze_to_silver(bronze_key, target_table_name, **kwargs)
        results['silver'] = silver_result.get('key')
        results['rows'] = silver_result.get('rows')

        # Atualiza a variável silver_key para a chave efetiva retornada
        silver_key = silver_result.get('key')
        if not silver_key:
            silver_keys = silver_result.get('keys') or []
            if silver_keys:
                silver_key = silver_keys[0]
                results['silver'] = silver_key

        if not silver_key:
            raise ValueError("Silver stage retornou nenhum arquivo gerado; não é possível prosseguir para Gold.")
        log.info("[SILVER] ✅ Silver gerado com qualidade de dados: s3://%s/%s", bucket, silver_key)

        # Registrar tabela Silver
        if atlas_enabled and atlas:
            silver_table = f"{target_table_name}_silver"
            try:
                # Construir schema de colunas a partir do Parquet Silver
                import pandas as pd
                silver_local_schema = hook.download_file(key=silver_key, bucket_name=bucket)
                df_silver_schema = pd.read_parquet(silver_local_schema)
                def _map_dtype(dtype):
                    import pandas as pd
                    if pd.api.types.is_integer_dtype(dtype):
                        return "int"
                    if pd.api.types.is_float_dtype(dtype):
                        return "double"
                    if pd.api.types.is_datetime64_any_dtype(dtype):
                        return "timestamp"
                    return "string"
                silver_columns = [{
                    "qualifiedName": f"{db_name}.{silver_table}.{c}@cluster",
                    "name": c,
                    "type": _map_dtype(df_silver_schema.dtypes[c])
                } for c in df_silver_schema.columns.tolist()]
                log.info("[ATLAS] Registrando tabela Silver: %s", f"{db_name}.{silver_table}@cluster")
                res_silver = register_table(
                    atlas,
                    layer="silver",
                    table=silver_table,
                    db=db_name,
                    columns=None,  # Criar tabela sem colunas primeiro
                    owner=owner
                )
                log.info("[ATLAS] ✓ Tabela Silver registrada: %s", f"{db_name}.{silver_table}@cluster")
                
                # Adicionar colunas à tabela Silver
                try:
                    atlas.add_columns_to_table(
                        table_qualified_name=f"{db_name}.{silver_table}@cluster",
                        columns=silver_columns
                    )
                    log.info("[ATLAS] ✓ %d colunas adicionadas à Silver", len(silver_columns))
                except Exception as e:
                    log.warning("[ATLAS] Falha ao adicionar colunas (Silver): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
                
                # Registrar processo Bronze → Silver (opcional)
                if register_processes:
                    try:
                        register_process(
                            atlas,
                            step_name=f"bronze_to_silver_{target_table_name}",
                            layer_from="bronze",
                            layer_to="silver",
                            inputs_qn=[f"{db_name}.{bronze_table}@cluster"],
                            outputs_qn=[f"{db_name}.{silver_table}@cluster"]
                        )
                        log.info("[ATLAS] ✅ Processo Bronze→Silver registrado")
                    except Exception as e:
                        err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                        log.warning("[ATLAS] Falha ao registrar processo bronze→silver: %s", err_fmt)
            except Exception as e:
                err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                log.warning("[ATLAS] Falha ao registrar silver: %s", err_fmt)
        
        # ==================== CAMADA GOLD (DELTA LAKE) ====================
        log.info("[GOLD] Processando: s3://%s/%s → Delta Lake", bucket, silver_key)
        
        # Usar Delta Lake ao invés de Parquet simples
        try:
            from lib.gold_delta_layer import silver_to_gold_delta
            log.info("[GOLD] Importação gold_delta_layer OK, chamando silver_to_gold_delta...")
            
            gold_result = silver_to_gold_delta(
                source_filename=silver_key,
                target_table_name=target_table_name,
                dag_id=dag_id
            )
            
            results['gold_delta'] = gold_result.get('gold_delta')
            results['gold_format'] = 'delta'
            results['gold_version'] = gold_result.get('version', 0)
            log.info("[GOLD] ✅ Delta Lake salvo em: %s (versão %s)", 
                    gold_result.get('gold_delta'), gold_result.get('version', 0))

            # Registrar tabela Gold (Delta)
            if atlas_enabled and atlas:
                gold_table = f"{target_table_name}_gold"
                try:
                    # Construir schema de colunas a partir do resultado Delta
                    gold_columns_schema = gold_result.get('columns_schema') or []
                    gold_columns = [{
                        "qualifiedName": f"{db_name}.{gold_table}.{c['name']}@cluster",
                        "name": c['name'],
                        "type": c.get('type', 'string')
                    } for c in gold_columns_schema]
                    log.info("[ATLAS] Registrando tabela Gold: %s", f"{db_name}.{gold_table}@cluster")
                    res_gold = register_table(
                        atlas,
                        layer="gold",
                        table=gold_table,
                        db=db_name,
                        columns=None,  # Criar tabela sem colunas primeiro
                        owner=owner
                    )
                    log.info("[ATLAS] ✓ Tabela Gold registrada: %s", f"{db_name}.{gold_table}@cluster")
                    
                    # Adicionar colunas à tabela Gold
                    if gold_columns_schema:
                        gold_columns = [{
                            "qualifiedName": f"{db_name}.{gold_table}.{c['name']}@cluster",
                            "name": c['name'],
                            "type": c.get('type', 'string')
                        } for c in gold_columns_schema]
                        try:
                            atlas.add_columns_to_table(
                                table_qualified_name=f"{db_name}.{gold_table}@cluster",
                                columns=gold_columns
                            )
                            log.info("[ATLAS] ✓ %d colunas adicionadas à Gold", len(gold_columns))
                        except Exception as e:
                            log.warning("[ATLAS] Falha ao adicionar colunas (Gold): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
                    
                    # Registrar processo Silver → Gold (opcional)
                    if register_processes:
                        try:
                            register_process(
                                atlas,
                                step_name=f"silver_to_gold_{target_table_name}",
                                layer_from="silver",
                                layer_to="gold",
                                inputs_qn=[f"{db_name}.{silver_table}@cluster"],
                                outputs_qn=[f"{db_name}.{gold_table}@cluster"]
                            )
                            log.info("[ATLAS] ✅ Processo Silver→Gold (Delta) registrado")
                        except Exception as e:
                            err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                            log.warning("[ATLAS] Falha ao registrar processo silver→gold: %s", err_fmt)
                except Exception as e:
                    err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                    log.warning("[ATLAS] Falha ao registrar gold: %s", err_fmt)
            
        except Exception as e:
            # Fallback para Parquet se Delta Lake falhou
            log.warning("[GOLD] ⚠️  Delta Lake falhou (%s: %s), usando fallback Parquet", 
                       type(e).__name__, str(e))
            
            # Recarregar DataFrame do Silver para fallback
            log.info("[GOLD] Recarregando Silver para fallback Parquet...")
            silver_local_fallback = hook.download_file(key=silver_key, bucket_name=bucket)
            df_fallback = pd.read_parquet(silver_local_fallback)
            
            from lib.gold_layer import _apply_analytical_intelligence
            df_fallback = _apply_analytical_intelligence(df_fallback)
            
            log.info("[GOLD] Aplicando otimizações finais...")
            
            gold_local = os.path.join(tmpdir, f"{basename_no_ext}_gold.parquet")
            df_fallback.to_parquet(gold_local, index=False, compression='snappy', engine='pyarrow')
            
            hook.load_file(filename=gold_local, key=gold_key, bucket_name=bucket, replace=True)
            log.info("[GOLD] ✅ Fallback Parquet salvo em: s3://%s/%s", bucket, gold_key)
            results['gold'] = gold_key
            results['gold_format'] = 'parquet_fallback'

            # Registrar gold parquet fallback
            if atlas_enabled and atlas:
                gold_table = f"{target_table_name}_gold"
                try:
                    register_table(
                        atlas,
                        layer="gold",
                        table=gold_table,
                        db=db_name,
                        columns=[{"qualifiedName": f"{db_name}.{gold_table}.data@cluster", "name": "data", "type": "string"}],
                        owner=owner
                    )
                    
                    # Registrar processo Silver → Gold (fallback, opcional)
                    if register_processes:
                        try:
                            register_process(
                                atlas,
                                step_name=f"silver_to_gold_{target_table_name}",
                                layer_from="silver",
                                layer_to="gold",
                                inputs_qn=[f"{db_name}.{silver_table}@cluster"],
                                outputs_qn=[f"{db_name}.{gold_table}@cluster"]
                            )
                            log.info("[ATLAS] ✅ Processo Silver→Gold (Parquet fallback) registrado")
                        except Exception as e:
                            err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                            log.warning("[ATLAS] Falha ao registrar processo silver→gold (fallback): %s", err_fmt)
                except Exception as e:
                    err_fmt = getattr(atlas, "format_http_error", lambda x: str(x))(e)
                    log.warning("[ATLAS] Falha ao registrar gold (fallback): %s", err_fmt)
        
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


def batch_raw_to_medallion(batch_id: str, files: list, max_parallel: int = 4, **context):
    """
    Processar múltiplos arquivos em batch utilizando ThreadPoolExecutor.
    
    Cada arquivo é processado através do pipeline completo (Bronze → Silver → Gold)
    de forma paralela ou limitada pelo parâmetro max_parallel.
    
    Args:
        batch_id: Identificador único do batch
        files: Lista de dicts com 'source_path', 'file_name', 'size_bytes'
        max_parallel: Número máximo de arquivos processados simultaneamente
        **context: Contexto do Airflow
        
    Returns:
        Dict com resumo do processamento (successful, failed, results, errors)
    """
    from concurrent.futures import ThreadPoolExecutor, as_completed
    import os
    
    log.info(f"[BATCH] Iniciando processamento em batch: {batch_id}")
    log.info(f"[BATCH] Total de arquivos: {len(files)}")
    log.info(f"[BATCH] Paralelismo máximo: {max_parallel}")
    
    results = []
    errors = []
    
    def process_single_file(file_info):
        """Processar um único arquivo através do pipeline Medallion"""
        file_name = file_info['file_name']
        source_path = file_info['source_path']
        
        try:
            log.info(f"[BATCH] Processando arquivo: {file_name}")
            
            # Extrair nome da tabela (sem extensão)
            target_table = os.path.splitext(file_name)[0]
            
            # Chamar pipeline existente para arquivo único
            result = raw_to_medallion(
                source_filename=source_path,
                target_table_name=target_table,
                **context
            )
            
            log.info(f"[BATCH] ✅ Arquivo processado com sucesso: {file_name}")
            
            return {
                'status': 'success',
                'file': file_name,
                'source_path': source_path,
                'target_table': target_table,
                'result': result
            }
            
        except Exception as e:
            log.error(f"[BATCH] ❌ Erro ao processar {file_name}: {str(e)}")
            return {
                'status': 'error',
                'file': file_name,
                'source_path': source_path,
                'error': str(e)
            }
    
    # Processar arquivos em paralelo usando ThreadPoolExecutor
    with ThreadPoolExecutor(max_workers=max_parallel) as executor:
        # Submeter todas as tasks
        futures = {
            executor.submit(process_single_file, file_info): file_info 
            for file_info in files
        }
        
        # Coletar resultados conforme são completados
        for future in as_completed(futures):
            result = future.result()
            
            if result['status'] == 'success':
                results.append(result)
            else:
                errors.append(result)
    
    # Resumo final
    summary = {
        'batch_id': batch_id,
        'total_files': len(files),
        'successful': len(results),
        'failed': len(errors),
        'results': results,
        'errors': errors
    }
    
    log.info(f"[BATCH] ✅ Processamento batch concluído!")
    log.info(f"[BATCH] Arquivos processados com sucesso: {len(results)}/{len(files)}")
    
    if errors:
        log.warning(f"[BATCH] Arquivos com erro: {len(errors)}")
        for error in errors:
            log.warning(f"[BATCH]   - {error['file']}: {error['error']}")
    
    return summary
