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
        if cloud_dest_type == 'aws':
            return _sync_to_aws(local_hook, bucket_name, files_to_sync, transform_args)
        elif cloud_dest_type == 'azure':
            return _sync_to_azure(local_hook, bucket_name, files_to_sync, transform_args)
        else:
            log.warning(f"[CLOUD_SYNC] Tipo de nuvem desconhecido: {cloud_dest_type}")
            return {'status': 'error', 'reason': f'unkown cloud_dest_type: {cloud_dest_type}'}
            
    except Exception as e:
        log.error(f"[CLOUD_SYNC] Erro durante a sincronização: {e}", exc_info=True)
        raise

def _sync_to_aws(local_hook, local_bucket, files_to_sync, config):
    import boto3
    import tempfile
    
    aws_access_key = config.get('aws_access_key')
    aws_secret_key = config.get('aws_secret_key')
    aws_bucket = config.get('aws_bucket')
    aws_region = config.get('aws_region', 'us-east-1')
    
    if not all([aws_access_key, aws_secret_key, aws_bucket]):
        raise ValueError("[CLOUD_SYNC][AWS] Faltam credenciais (access_key, secret_key, bucket).")
        
    # Inicializar cliente AWS
    aws_client = boto3.client(
        's3',
        aws_access_key_id=aws_access_key,
        aws_secret_access_key=aws_secret_key,
        region_name=aws_region
    )
    
    synced = 0
    with tempfile.TemporaryDirectory() as tmpdir:
        for file_key in files_to_sync:
            log.info(f"[CLOUD_SYNC][AWS] Sincronizando: {file_key}")
            # Pular subpastas vazias
            if file_key.endswith('/'):
                continue
                
            local_path = local_hook.download_file(
                key=file_key,
                bucket_name=local_bucket,
                local_path=tmpdir,
                preserve_file_name=True
            )
            
            # Subir para AWS usando o mesmo nome de chave do MinIO (mantém hierarquia do data lake)
            aws_client.upload_file(local_path, aws_bucket, file_key)
            synced += 1
            os.remove(local_path)
            
    log.info(f"[CLOUD_SYNC][AWS] ✅ Sucesso! {synced} arquivos encaminhados para bucket {aws_bucket}.")
    return {'status': 'success', 'synced': synced, 'target': 'aws', 'bucket': aws_bucket}

def _sync_to_azure(local_hook, local_bucket, files_to_sync, config):
    from azure.storage.blob import BlobServiceClient
    import tempfile
    
    account_name = config.get('azure_account_name')
    account_key = config.get('azure_account_key')
    container_name = config.get('azure_container')
    
    if not all([account_name, account_key, container_name]):
        raise ValueError("[CLOUD_SYNC][AZURE] Faltam credenciais da Azure.")
        
    connection_string = f"DefaultEndpointsProtocol=https;AccountName={account_name};AccountKey={account_key};EndpointSuffix=core.windows.net"
    blob_service_client = BlobServiceClient.from_connection_string(connection_string)
    
    container_client = blob_service_client.get_container_client(container_name)
    
    synced = 0
    with tempfile.TemporaryDirectory() as tmpdir:
        for file_key in files_to_sync:
            log.info(f"[CLOUD_SYNC][AZURE] Sincronizando: {file_key}")
            if file_key.endswith('/'):
                continue
                
            local_path = local_hook.download_file(
                key=file_key,
                bucket_name=local_bucket,
                local_path=tmpdir,
                preserve_file_name=True
            )
            
            blob_client = container_client.get_blob_client(file_key)
            with open(local_path, "rb") as data:
                blob_client.upload_blob(data, overwrite=True)
                
            synced += 1
            os.remove(local_path)
            
    log.info(f"[CLOUD_SYNC][AZURE] ✅ Sucesso! {synced} arquivos encaminhados para o container {container_name}.")
    return {'status': 'success', 'synced': synced, 'target': 'azure', 'container': container_name}
