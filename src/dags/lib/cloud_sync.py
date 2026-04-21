import os
import logging
from typing import List, Dict, Any

log = logging.getLogger(__name__)

def push_to_external_cloud(**kwargs) -> Dict[str, Any]:
    """
    Operator Entrypoint: Sincroniza o resultado local do modelo Medallion para a nuvem externa.
    
    A configuração da nuvem (dest_type, aws_*, azure_*) vem implicitamente mesclada de `transform_args`.
    O bucket do usuário vem de `bucket_name` (inserido pelo factory_master).
    A tabela alvo de `target_table_name`.
    """
    
    # Extrair configurações
    transform_args = kwargs
    cloud_dest_type = transform_args.get('cloud_dest_type', transform_args.get('cloudDestType', 'minio')).lower()
    
    if cloud_dest_type in ['none', 'minio', 'local']:
        log.info("[CLOUD_SYNC] Destino na nuvem definido como Local/MinIO. Os dados permanecerão no Data Lake local (sem exportação para AWS/Azure).")
        return {'status': 'local_only', 'reason': 'kept_in_minio'}
        
    bucket_name = transform_args.get('bucket_name', transform_args.get('bucketName'))
    target_table_name = transform_args.get('target_table_name', transform_args.get('targetTableName'))
    
    if not bucket_name or not target_table_name:
        raise ValueError("[CLOUD_SYNC] 'bucket_name' e 'target_table_name' são obrigatórios no kwargs.")
        
    log.info(f"[CLOUD_SYNC] Iniciando sincronização da tabela '{target_table_name}' do bucket '{bucket_name}' para {cloud_dest_type.upper()}")
    
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        local_hook = S3Hook(aws_conn_id='minio_conn')
        
        # Determinar qual pasta enviar. Vamos enviar a Delta (se existir), senão Gold, senão Silver.
        # Por padrão, enviaremos a hierarquia completa.
        prefixes = [f"delta/{target_table_name}/", f"gold/{target_table_name}/", f"silver/{target_table_name}/"]
        files_to_sync = []
        for prefix in prefixes:
            files_to_sync = local_hook.list_keys(bucket_name=bucket_name, prefix=prefix)
            if files_to_sync:
                log.info(f"[CLOUD_SYNC] Dados encontrados no prefixo: {prefix} ({len(files_to_sync)} arquivos)")
                break
                
        if not files_to_sync:
            log.warning(f"[CLOUD_SYNC] Nenhum arquivo final encontrado para a tabela '{target_table_name}'.")
            return {'status': 'skipped', 'reason': 'no files found'}

        # Processar com base no destino
        from lib.providers import ProviderFactory
        
        try:
            cloud_provider = ProviderFactory.get_provider(cloud_dest_type)
        except ValueError as exception_provider:
            log.warning(f"[CLOUD_SYNC] Provedor declinado: {exception_provider}")
            return {'status': 'error', 'reason': str(exception_provider)}
            
        # Desacoplamento Polimórfico - Executa Strategy
        return cloud_provider.sync_files(
            local_hook=local_hook,
            bucket=bucket_name,
            files=files_to_sync,
            config=transform_args
        )
            
    except Exception as e:
        log.error(f"[CLOUD_SYNC] Erro durante a sincronização: {e}", exc_info=True)
        raise
