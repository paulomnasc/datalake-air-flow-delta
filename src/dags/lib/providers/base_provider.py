import abc
from typing import Dict, Any, List

class BaseCloudProvider(abc.ABC):
    """
    Interface base para os provedores de Data Lake Externo.
    Implementa o Padrão Strategy para enviar dados gerados pelo Airflow local
    para outros provedores (AWS, Azure, GCP, etc).
    """

    @abc.abstractmethod
    def sync_files(self, local_hook: Any, bucket: str, files: List[str], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Garante que todos os subprovedores implementarão o método principal de sincronização.
        
        Args:
            local_hook: Um hook do Airflow instanciado apontando para o S3 (geralmente local/MinIO).
            bucket: Nome do bucket local de onde extrair as fontes.
            files: Lista de chaves (keys) dos arquivos (geralmente parquets) selecionados para subida.
            config: O dicionário kwargs `transform_args` consolidado provido pela UI do App.
            
        Returns:
            Dict[str, Any]: Dicionário com informações de status e sucesso do processamento.
        """
        pass
