#!/usr/bin/env python3
"""
Cliente Python para Apache Atlas
Demonstra conexão e operações básicas com a API REST
"""

import requests
import json
from requests.auth import HTTPBasicAuth

class AtlasClient:
    def __init__(self, url="http://localhost:21000", username="admin", password="admin"):
        self.url = url
        self.auth = HTTPBasicAuth(username, password)
        self.session = requests.Session()
        self.session.auth = self.auth
        self.session.headers.update({'Content-Type': 'application/json'})
    
    def get_version(self):
        """Obter versão do Atlas"""
        response = self.session.get(f"{self.url}/api/atlas/admin/version")
        return response.json()
    
    def get_types(self):
        """Listar todos os tipos disponíveis"""
        response = self.session.get(f"{self.url}/api/atlas/v2/types/typedefs")
        return response.json()
    
    def search_entities(self, query="*", limit=10):
        """Buscar entidades por termo"""
        params = {"query": query, "limit": limit}
        response = self.session.get(f"{self.url}/api/atlas/v2/search/basic", params=params)
        return response.json()
    
    def create_entity(self, entity_data):
        """Criar nova entidade"""
        response = self.session.post(
            f"{self.url}/api/atlas/v2/entity",
            json={"entity": entity_data}
        )
        return response.json()
    
    def get_entity(self, guid):
        """Obter entidade por GUID"""
        response = self.session.get(f"{self.url}/api/atlas/v2/entity/guid/{guid}")
        return response.json()

def main():
    """Exemplo de uso do cliente Atlas"""
    print("🚀 Conectando ao Apache Atlas...")
    
    client = AtlasClient()
    
    try:
        # Testar conexão
        version = client.get_version()
        print(f"✅ Atlas versão: {version}")
        
        # Listar tipos
        types = client.get_types()
        entity_types = types.get('entityDefs', [])
        print(f"📋 Tipos disponíveis: {len(entity_types)}")
        
        # Buscar entidades
        results = client.search_entities("*")
        entities = results.get('entities', [])
        print(f"🔍 Entidades encontradas: {len(entities)}")
        
        # Mostrar primeiras entidades
        for i, entity in enumerate(entities[:3]):
            print(f"  {i+1}. {entity.get('displayText', 'N/A')} ({entity.get('typeName', 'N/A')})")
            
    except Exception as e:
        print(f"❌ Erro: {e}")

if __name__ == "__main__":
    main()