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
