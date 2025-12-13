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
