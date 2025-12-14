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
        # Atlas client para registrar metadados e processos
        from .atlas_client import AtlasClient
        from .atlas_lineage import register_table, register_process
        atlas = AtlasClient()
        db_name = os.getenv("ATLAS_HIVE_DB", "medallion")
        
        # Flag para controlar registro de processos (pode ser lento)
        register_processes = os.getenv("ATLAS_REGISTER_PROCESSES", "false").lower() == "true"
        
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
            description=f"Raw source for {target_table_name}"
        )
        
        # ==================== CAMADA BRONZE ====================
        log.info("[BRONZE] Copiando: s3://%s/%s → s3://%s/%s", bucket, src_key, bucket, bronze_key)
        
        local_file = hook.download_file(key=src_key, bucket_name=bucket, local_path=tmpdir, preserve_file_name=True)
        log.info("[BRONZE] Arquivo baixado: %s", local_file)
        
        hook.load_file(filename=local_file, key=bronze_key, bucket_name=bucket, replace=True)
        log.info("[BRONZE] ✅ Salvo em: s3://%s/%s", bucket, bronze_key)
        results['bronze'] = bronze_key

        # Registrar tabela Bronze
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
                columns=bronze_columns
            )
            try:
                atlas.link_table_columns(
                    table_qualified_name=f"{db_name}.{bronze_table}@cluster",
                    column_qualified_names=[c["qualifiedName"] for c in bronze_columns]
                )
                log.info("[ATLAS] ✓ Relacionamento tabela→colunas (Bronze) aplicado")
            except Exception as e:
                log.warning("[ATLAS] Falha ao vincular colunas (Bronze): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
            
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
        log.info("[SILVER] ✅ Silver gerado com qualidade de dados: s3://%s/%s", bucket, silver_key)

        # Registrar tabela Silver
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
                columns=silver_columns
            )
            try:
                atlas.link_table_columns(
                    table_qualified_name=f"{db_name}.{silver_table}@cluster",
                    column_qualified_names=[c["qualifiedName"] for c in silver_columns]
                )
                log.info("[ATLAS] ✓ Relacionamento tabela→colunas (Silver) aplicado")
            except Exception as e:
                log.warning("[ATLAS] Falha ao vincular colunas (Silver): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
            try:
                atlas.get_entity_by_qualified_name("hive_table", f"{db_name}.{silver_table}@cluster")
                log.info("[ATLAS] Silver entity indexed: %s", f"{db_name}.{silver_table}@cluster")
            except Exception as e:
                log.warning("[ATLAS] Silver entity fetch failed (may be eventual consistency): %s", e)
            
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
                target_table_name=target_table_name
            )
            
            results['gold_delta'] = gold_result.get('gold_delta')
            results['gold_format'] = 'delta'
            results['gold_version'] = gold_result.get('version', 0)
            log.info("[GOLD] ✅ Delta Lake salvo em: %s (versão %s)", 
                    gold_result.get('gold_delta'), gold_result.get('version', 0))

            # Registrar tabela Gold (Delta)
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
                    columns=gold_columns or [{"qualifiedName": f"{db_name}.{gold_table}.data@cluster", "name": "data", "type": "string"}]
                )
                try:
                    atlas.link_table_columns(
                        table_qualified_name=f"{db_name}.{gold_table}@cluster",
                        column_qualified_names=[c["qualifiedName"] for c in (gold_columns or [])]
                    )
                    log.info("[ATLAS] ✓ Relacionamento tabela→colunas (Gold) aplicado")
                except Exception as e:
                    log.warning("[ATLAS] Falha ao vincular colunas (Gold): %s", getattr(atlas, "format_http_error", lambda x: str(x))(e))
                try:
                    atlas.get_entity_by_qualified_name("hive_table", f"{db_name}.{gold_table}@cluster")
                    log.info("[ATLAS] Gold entity indexed: %s", f"{db_name}.{gold_table}@cluster")
                except Exception as e:
                    log.warning("[ATLAS] Gold entity fetch failed (may be eventual consistency): %s", e)
                
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
            df_fallback = _apply_analytical_intelligence(df_fallback, target_table_name)
            
            log.info("[GOLD] Aplicando otimizações finais...")
            
            gold_local = os.path.join(tmpdir, f"{basename_no_ext}_gold.parquet")
            df_fallback.to_parquet(gold_local, index=False, compression='snappy', engine='pyarrow')
            
            hook.load_file(filename=gold_local, key=gold_key, bucket_name=bucket, replace=True)
            log.info("[GOLD] ✅ Fallback Parquet salvo em: s3://%s/%s", bucket, gold_key)
            results['gold'] = gold_key
            results['gold_format'] = 'parquet_fallback'

            # Registrar gold parquet fallback
            gold_table = f"{target_table_name}_gold"
            try:
                register_table(
                    atlas,
                    layer="gold",
                    table=gold_table,
                    db=db_name,
                    columns=[{"qualifiedName": f"{db_name}.{gold_table}.data@cluster", "name": "data", "type": "string"}]
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
