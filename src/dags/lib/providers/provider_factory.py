import logging
from typing import Dict, Any
from .base_provider import BaseCloudProvider
from .aws_provider import AWSCloudProvider
from .azure_provider import AzureCloudProvider

log = logging.getLogger(__name__)

class ProviderFactory:
    """
    Roteador que fabrica a classe de estratégia na nuvem baseada na String de controle
    configurada pelo ambiente/UX. O usuário nunca instanciará as classes manualmente.
    """
    
    @staticmethod
    def get_provider(cloud_dest_type: str) -> BaseCloudProvider:
        tipo = cloud_dest_type.lower()
        
        # Mapeamento do Strategy Pattern
        if tipo == 'aws':
            return AWSCloudProvider()
        elif tipo == 'azure':
            return AzureCloudProvider()
        else:
            raise ValueError(f"[PROVIDER_FACTORY] Não existe Estratégia de Provedor cadastrada para a nuvem tipo: '{tipo}'")
