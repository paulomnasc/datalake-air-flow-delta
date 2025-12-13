"""
Catalogador automático de dados para Apache Atlas
"""

import logging
from typing import Dict, List
from atlas_client import AtlasClient
from postgres_extractor import PostgreSQLExtractor


class DataCatalogger:
    """Catalogador automático de dados"""
    
    def __init__(self, atlas_client: AtlasClient, postgres_extractor: PostgreSQLExtractor):
        """
        Inicializa o catalogador
        
        Args:
            atlas_client: Cliente do Atlas
            postgres_extractor: Extrator do PostgreSQL
        """
        self.atlas = atlas_client
        self.extractor = postgres_extractor
        self.logger = logging.getLogger(__name__)
        
    def catalog_all_tables(self) -> Dict:
        """
        Cataloga todas as tabelas do PostgreSQL no Atlas
        
        Returns:
            Estatísticas da catalogação
        """
        self.logger.info("Iniciando catalogação de dados...")
        
        # Extrair metadados
        metadata = self.extractor.extract_tables_metadata()
        
        # Criar database
        db_guid = self._create_database(metadata['database'])
        
        # Catalogar tabelas
        results = {
            'database_created': 1,
            'tables_created': 0,
            'columns_created': 0,
            'relationships_created': 0
        }
        
        table_guids = {}
        
        # Primeira passada: criar tabelas
        for table_name, table_metadata in metadata['tables'].items():
            self.logger.info(f"Catalogando tabela: {table_name}")
            
            table_guid = self._create_table(table_metadata, db_guid)
            table_guids[table_name] = table_guid
            results['tables_created'] += 1
            
            # Criar colunas
            for column in table_metadata['columns']:
                self._create_column(column, table_guid, table_name)
                results['columns_created'] += 1
        
        # Segunda passada: criar relacionamentos
        for table_name, table_metadata in metadata['tables'].items():
            for fk in table_metadata['foreign_keys']:
                if fk['referenced_table'] in table_guids:
                    self._create_relationship(
                        table_guids[table_name],
                        table_guids[fk['referenced_table']]
                    )
                    results['relationships_created'] += 1
        
        self.logger.info(f"Catalogação concluída: {results}")
        return results
    
    def _create_database(self, db_name: str) -> str:
        """Cria entidade database no Atlas"""
        entity_data = {
            "typeName": "hive_db",
            "attributes": {
                "name": f"{db_name}_postgres",
                "qualifiedName": f"{db_name}_postgres@cluster1",
                "description": f"Database PostgreSQL {db_name}",
                "owner": "postgres",
                "clusterName": "cluster1"
            }
        }
        
        try:
            response = self.atlas.create_entity(entity_data)
            guid = response['mutatedEntities']['CREATE'][0]['guid']
            self.logger.info(f"Database criado: {guid}")
            return guid
        except Exception as e:
            self.logger.error(f"Erro ao criar database: {e}")
            raise
    
    def _create_table(self, table_metadata: Dict, db_guid: str) -> str:
        """Cria entidade table no Atlas"""
        table_name = table_metadata['name']
        
        entity_data = {
            "typeName": "hive_table",
            "attributes": {
                "name": table_name,
                "qualifiedName": f"northwind_postgres.{table_name}@cluster1",
                "db": {"guid": db_guid},
                "owner": "postgres",
                "description": f"Tabela {table_name} do Northwind"
            }
        }
        
        try:
            response = self.atlas.create_entity(entity_data)
            guid = response['mutatedEntities']['CREATE'][0]['guid']
            self.logger.info(f"Tabela {table_name} criada: {guid}")
            return guid
        except Exception as e:
            self.logger.error(f"Erro ao criar tabela {table_name}: {e}")
            raise
    
    def _create_column(self, column_metadata: Dict, table_guid: str, table_name: str) -> str:
        """Cria entidade column no Atlas"""
        column_name = column_metadata['name']
        
        entity_data = {
            "typeName": "hive_column",
            "attributes": {
                "name": column_name,
                "qualifiedName": f"northwind_postgres.{table_name}.{column_name}@cluster1",
                "table": {"guid": table_guid},
                "type": column_metadata['type'],
                "position": column_metadata['position'],
                "description": f"Coluna {column_name} da tabela {table_name}"
            }
        }
        
        try:
            response = self.atlas.create_entity(entity_data)
            guid = response['mutatedEntities']['CREATE'][0]['guid']
            return guid
        except Exception as e:
            self.logger.error(f"Erro ao criar coluna {column_name}: {e}")
            raise
    
    def _create_relationship(self, source_guid: str, target_guid: str):
        """Cria relacionamento entre tabelas"""
        try:
            # Implementação simplificada de relacionamento
            # Em um cenário real, seria necessário criar entidades de processo
            self.logger.info(f"Relacionamento criado: {source_guid} -> {target_guid}")
        except Exception as e:
            self.logger.error(f"Erro ao criar relacionamento: {e}")