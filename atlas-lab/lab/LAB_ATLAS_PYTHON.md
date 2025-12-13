# Lab Python - Apache Atlas Demo

## Objetivo
Demonstrar o uso do Apache Atlas como catálogo de dados através de exemplos práticos em Python, incluindo:
- Conexão com Atlas via API REST
- Criação de entidades (tabelas, colunas)
- Busca e descoberta de dados
- Linhagem de dados (data lineage)
- Integração com banco PostgreSQL

## Pré-requisitos

### 1. Ambiente Rodando
```bash
# Iniciar Atlas + PostgreSQL
docker-compose up -d

# Aguardar inicialização
./wait-for-atlas.sh
```

### 2. Dependências Python
```bash
pip install requests pandas psycopg2-binary
```

## Setup do Lab

### 1. Estrutura do Projeto
```
lab/
├── atlas_client.py      # Cliente para Atlas API
├── data_discovery.py    # Descoberta de dados
├── lineage_demo.py      # Demonstração de linhagem
└── postgres_integration.py # Integração com PostgreSQL
```

### 2. Configuração Base
```python
# config.py
ATLAS_URL = "http://localhost:21000"
ATLAS_USER = "admin"
ATLAS_PASSWORD = "admin"

POSTGRES_CONFIG = {
    "host": "localhost",
    "port": 2001,
    "database": "northwind",
    "user": "postgres",
    "password": "postgres"
}
```

## Exercícios Práticos

### Exercício 1: Cliente Atlas Básico

```python
# lab/atlas_client.py
import requests
import json
from requests.auth import HTTPBasicAuth

class AtlasClient:
    def __init__(self, url, username, password):
        self.url = url
        self.auth = HTTPBasicAuth(username, password)
        self.session = requests.Session()
        self.session.auth = self.auth
    
    def get_types(self):
        """Listar todos os tipos disponíveis"""
        response = self.session.get(f"{self.url}/api/atlas/v2/types/typedefs")
        return response.json()
    
    def search_entities(self, query):
        """Buscar entidades por termo"""
        params = {"query": query, "limit": 10}
        response = self.session.get(f"{self.url}/api/atlas/v2/search/basic", params=params)
        return response.json()
    
    def create_entity(self, entity_data):
        """Criar nova entidade"""
        response = self.session.post(
            f"{self.url}/api/atlas/v2/entity",
            json={"entity": entity_data}
        )
        return response.json()

# Exemplo de uso
if __name__ == "__main__":
    client = AtlasClient("http://localhost:21000", "admin", "admin")
    
    # Listar tipos
    types = client.get_types()
    print(f"Tipos disponíveis: {len(types.get('entityDefs', []))}")
    
    # Buscar entidades
    results = client.search_entities("table")
    print(f"Entidades encontradas: {len(results.get('entities', []))}")
```

### Exercício 2: Descoberta de Dados

```python
# lab/data_discovery.py
from atlas_client import AtlasClient
import pandas as pd

class DataDiscovery:
    def __init__(self, atlas_client):
        self.client = atlas_client
    
    def discover_tables(self):
        """Descobrir todas as tabelas no catálogo"""
        results = self.client.search_entities("*")
        tables = []
        
        for entity in results.get('entities', []):
            if entity.get('typeName') == 'hive_table':
                tables.append({
                    'name': entity.get('displayText'),
                    'guid': entity.get('guid'),
                    'status': entity.get('status')
                })
        
        return pd.DataFrame(tables)
    
    def get_table_schema(self, table_guid):
        """Obter schema de uma tabela específica"""
        response = self.client.session.get(
            f"{self.client.url}/api/atlas/v2/entity/guid/{table_guid}"
        )
        entity = response.json().get('entity', {})
        
        columns = []
        for col_ref in entity.get('relationshipAttributes', {}).get('columns', []):
            col_response = self.client.session.get(
                f"{self.client.url}/api/atlas/v2/entity/guid/{col_ref['guid']}"
            )
            col_entity = col_response.json().get('entity', {})
            columns.append({
                'name': col_entity.get('attributes', {}).get('name'),
                'type': col_entity.get('attributes', {}).get('dataType'),
                'comment': col_entity.get('attributes', {}).get('comment', '')
            })
        
        return pd.DataFrame(columns)

# Exemplo de uso
if __name__ == "__main__":
    client = AtlasClient("http://localhost:21000", "admin", "admin")
    discovery = DataDiscovery(client)
    
    # Descobrir tabelas
    tables_df = discovery.discover_tables()
    print("Tabelas descobertas:")
    print(tables_df)
    
    # Ver schema de uma tabela
    if not tables_df.empty:
        table_guid = tables_df.iloc[0]['guid']
        schema_df = discovery.get_table_schema(table_guid)
        print(f"\nSchema da tabela:")
        print(schema_df)
```

### Exercício 3: Integração com PostgreSQL

```python
# lab/postgres_integration.py
import psycopg2
import pandas as pd
from atlas_client import AtlasClient

class PostgreSQLAtlasIntegration:
    def __init__(self, postgres_config, atlas_client):
        self.pg_config = postgres_config
        self.atlas = atlas_client
    
    def get_postgres_tables(self):
        """Listar tabelas do PostgreSQL"""
        conn = psycopg2.connect(**self.pg_config)
        query = """
        SELECT table_name, table_schema 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        """
        df = pd.read_sql(query, conn)
        conn.close()
        return df
    
    def get_table_columns(self, table_name):
        """Obter colunas de uma tabela"""
        conn = psycopg2.connect(**self.pg_config)
        query = """
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns 
        WHERE table_name = %s AND table_schema = 'public'
        ORDER BY ordinal_position
        """
        df = pd.read_sql(query, conn, params=[table_name])
        conn.close()
        return df
    
    def register_table_in_atlas(self, table_name):
        """Registrar tabela do PostgreSQL no Atlas"""
        # Obter metadados da tabela
        columns_df = self.get_table_columns(table_name)
        
        # Criar entidade de tabela no Atlas
        table_entity = {
            "typeName": "hive_table",
            "attributes": {
                "name": table_name,
                "qualifiedName": f"postgres.northwind.{table_name}",
                "owner": "postgres",
                "description": f"Tabela {table_name} do banco Northwind",
                "parameters": {
                    "source": "PostgreSQL",
                    "database": "northwind"
                }
            }
        }
        
        # Criar colunas
        columns = []
        for _, col in columns_df.iterrows():
            column_entity = {
                "typeName": "hive_column",
                "attributes": {
                    "name": col['column_name'],
                    "qualifiedName": f"postgres.northwind.{table_name}.{col['column_name']}",
                    "dataType": col['data_type'],
                    "isNullable": col['is_nullable'] == 'YES'
                }
            }
            columns.append(column_entity)
        
        table_entity["attributes"]["columns"] = columns
        
        # Registrar no Atlas
        result = self.atlas.create_entity(table_entity)
        return result

# Exemplo de uso
if __name__ == "__main__":
    postgres_config = {
        "host": "localhost",
        "port": 2001,
        "database": "northwind",
        "user": "postgres",
        "password": "postgres"
    }
    
    atlas_client = AtlasClient("http://localhost:21000", "admin", "admin")
    integration = PostgreSQLAtlasIntegration(postgres_config, atlas_client)
    
    # Listar tabelas do PostgreSQL
    tables_df = integration.get_postgres_tables()
    print("Tabelas no PostgreSQL:")
    print(tables_df)
    
    # Registrar primeira tabela no Atlas
    if not tables_df.empty:
        table_name = tables_df.iloc[0]['table_name']
        print(f"\nRegistrando tabela '{table_name}' no Atlas...")
        result = integration.register_table_in_atlas(table_name)
        print("Resultado:", result)
```

### Exercício 4: Linhagem de Dados

```python
# lab/lineage_demo.py
from atlas_client import AtlasClient

class LineageDemo:
    def __init__(self, atlas_client):
        self.client = atlas_client
    
    def create_etl_process(self, source_table, target_table, process_name):
        """Criar processo ETL que conecta duas tabelas"""
        process_entity = {
            "typeName": "Process",
            "attributes": {
                "name": process_name,
                "qualifiedName": f"etl.{process_name}",
                "description": f"Processo ETL: {source_table} -> {target_table}",
                "inputs": [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": source_table}}],
                "outputs": [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": target_table}}]
            }
        }
        
        return self.client.create_entity(process_entity)
    
    def get_lineage(self, entity_guid):
        """Obter linhagem de uma entidade"""
        response = self.client.session.get(
            f"{self.client.url}/api/atlas/v2/lineage/{entity_guid}"
        )
        return response.json()

# Exemplo de uso
if __name__ == "__main__":
    client = AtlasClient("http://localhost:21000", "admin", "admin")
    lineage = LineageDemo(client)
    
    # Criar processo ETL
    result = lineage.create_etl_process(
        "postgres.northwind.customers",
        "postgres.northwind.customer_summary",
        "customer_aggregation"
    )
    print("Processo ETL criado:", result)
```

## Exercícios Propostos

### Exercício A: Exploração Básica
1. Execute o cliente básico e liste todos os tipos disponíveis
2. Busque por entidades usando diferentes termos
3. Analise a estrutura de resposta da API

### Exercício B: Catalogação
1. Use a integração PostgreSQL para listar todas as tabelas
2. Registre pelo menos 3 tabelas no Atlas
3. Verifique se as entidades foram criadas corretamente

### Exercício C: Descoberta Avançada
1. Implemente busca por tags
2. Crie filtros por tipo de entidade
3. Desenvolva relatório de cobertura do catálogo

### Exercício D: Linhagem Completa
1. Crie um pipeline completo: Raw → Processed → Analytics
2. Mapeie transformações entre tabelas
3. Visualize a linhagem através da API

## Resultados Esperados

Ao final do lab, você terá:
- Cliente Python funcional para Atlas
- Tabelas PostgreSQL catalogadas
- Linhagem de dados mapeada
- Descoberta automatizada de metadados
- Integração completa Atlas + PostgreSQL

## Recursos Adicionais

- [Atlas REST API Documentation](https://atlas.apache.org/api/v2/)
- [PostgreSQL Information Schema](https://www.postgresql.org/docs/current/information-schema.html)
- [Python Requests Library](https://docs.python-requests.org/)

## Troubleshooting

### Erro de Conexão Atlas
```bash
# Verificar se Atlas está rodando
curl -u admin:admin http://localhost:21000/api/atlas/admin/version
```

### Erro de Conexão PostgreSQL
```bash
# Testar conexão
docker exec -it postgres-erp psql -U postgres -d northwind -c "SELECT version();"
```

### Problemas de Autenticação
```python
# Verificar credenciais
response = requests.get(
    "http://localhost:21000/api/atlas/admin/version",
    auth=HTTPBasicAuth("admin", "admin")
)
print(response.status_code, response.text)
```