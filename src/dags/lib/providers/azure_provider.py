import logging
import os
import tempfile
from typing import Dict, Any, List
from .base_provider import BaseCloudProvider

log = logging.getLogger(__name__)

class AzureCloudProvider(BaseCloudProvider):
    """
    Estratégia para enviar dados locais (MinIO) para o Microsoft Azure Blob Storage.
    """
    
    def sync_files(self, local_hook: Any, bucket: str, files: List[str], config: Dict[str, Any]) -> Dict[str, Any]:
        from azure.storage.blob import BlobServiceClient
        
        account_name = config.get('azure_account_name')
        account_key = config.get('azure_account_key')
        container_name = config.get('azure_container')
        
        if not all([account_name, account_key, container_name]):
            raise ValueError("[CLOUD_SYNC][AZURE] Faltam credenciais da Azure (account_name, account_key ou container).")
            
        connection_string = f"DefaultEndpointsProtocol=https;AccountName={account_name};AccountKey={account_key};EndpointSuffix=core.windows.net"
        blob_service_client = BlobServiceClient.from_connection_string(connection_string)
        
        container_client = blob_service_client.get_container_client(container_name)
        
        synced = 0
        with tempfile.TemporaryDirectory() as tmpdir:
            for file_key in files:
                log.info(f"[CLOUD_SYNC][AZURE] Sincronizando: {file_key}")
                if file_key.endswith('/'):
                    continue
                    
                local_path = local_hook.download_file(
                    key=file_key,
                    bucket_name=bucket,
                    local_path=tmpdir,
                    preserve_file_name=True
                )
                
                # Envia ao Azure com overwrite
                blob_client = container_client.get_blob_client(file_key)
                with open(local_path, "rb") as data:
                    blob_client.upload_blob(data, overwrite=True)
                    
                synced += 1
                os.remove(local_path)
                
        log.info(f"[CLOUD_SYNC][AZURE] ✅ Sucesso! {synced} arquivos encaminhados para o container {container_name}.")
        return {'status': 'success', 'synced': synced, 'target': 'azure', 'container': container_name}
