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
