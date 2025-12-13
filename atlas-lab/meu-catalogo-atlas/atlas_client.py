"""
Cliente para integração com Apache Atlas via API REST
"""

import requests
import json
from requests.auth import HTTPBasicAuth
from typing import Dict, List, Optional


class AtlasClient:
    """Cliente para interação com Apache Atlas via API REST"""
    
    def __init__(self, url: str, username: str, password: str):
        """
        Inicializa o cliente Atlas
        
        Args:
            url: URL base do Apache Atlas
            username: Nome de usuário
            password: Senha
        """
        self.base_url = url.rstrip('/')
        self.auth = HTTPBasicAuth(username, password)
        self.session = requests.Session()
        self.session.auth = self.auth
        
    def search_entities(self, query: str) -> Dict:
        """
        Busca entidades no Atlas
        
        Args:
            query: Termo de busca
            
        Returns:
            Resultado da busca
        """
        try:
            url = f"{self.base_url}/api/atlas/v2/search/basic"
            params = {"query": query}
            response = self.session.get(url, params=params)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            raise Exception(f"Erro ao buscar entidades: {e}")
    
    def create_entity(self, entity_data: Dict) -> Dict:
        """
        Cria uma entidade no Atlas
        
        Args:
            entity_data: Dados da entidade
            
        Returns:
            Resposta da criação
        """
        try:
            url = f"{self.base_url}/api/atlas/v2/entity/bulk"
            headers = {"Content-Type": "application/json"}
            
            payload = {"entities": [entity_data]}
            response = self.session.post(url, json=payload, headers=headers)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            raise Exception(f"Erro ao criar entidade: {e}")
    
    def get_entity(self, guid: str) -> Dict:
        """
        Obtém uma entidade por GUID
        
        Args:
            guid: GUID da entidade
            
        Returns:
            Dados da entidade
        """
        try:
            url = f"{self.base_url}/api/atlas/v2/entity/guid/{guid}"
            response = self.session.get(url)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            raise Exception(f"Erro ao obter entidade {guid}: {e}")
    
    def get_lineage(self, guid: str) -> Dict:
        """
        Obtém a linhagem de uma entidade
        
        Args:
            guid: GUID da entidade
            
        Returns:
            Dados de linhagem
        """
        try:
            url = f"{self.base_url}/api/atlas/v2/lineage/{guid}"
            response = self.session.get(url)
            response.raise_for_status()
            return response.json()
        except requests.exceptions.RequestException as e:
            raise Exception(f"Erro ao obter linhagem de {guid}: {e}")