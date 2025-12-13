"""
Extrator de metadados do PostgreSQL
"""

import psycopg2
import pandas as pd
from typing import Dict, List
import logging


class PostgreSQLExtractor:
    """Extrator de metadados do PostgreSQL"""
    
    def __init__(self, host: str, port: int, database: str, user: str, password: str):
        """
        Inicializa o extrator
        
        Args:
            host: Host do PostgreSQL
            port: Porta do PostgreSQL
            database: Nome do banco
            user: Usuário
            password: Senha
        """
        self.connection_params = {
            'host': host,
            'port': port,
            'database': database,
            'user': user,
            'password': password
        }
        
    def _get_connection(self):
        """Cria conexão com PostgreSQL"""
        try:
            return psycopg2.connect(**self.connection_params)
        except psycopg2.Error as e:
            raise Exception(f"Erro ao conectar com PostgreSQL: {e}")
    
    def extract_tables_metadata(self) -> Dict:
        """
        Extrai metadados de todas as tabelas
        
        Returns:
            Dicionário com metadados das tabelas
        """
        metadata = {
            'database': self.connection_params['database'],
            'tables': {}
        }
        
        with self._get_connection() as conn:
            cursor = conn.cursor()
            
            # Obter lista de tabelas
            cursor.execute("""
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_type = 'BASE TABLE'
                ORDER BY table_name
            """)
            
            tables = [row[0] for row in cursor.fetchall()]
            
            for table_name in tables:
                metadata['tables'][table_name] = self._extract_table_metadata(cursor, table_name)
        
        return metadata
    
    def _extract_table_metadata(self, cursor, table_name: str) -> Dict:
        """Extrai metadados de uma tabela específica"""
        table_metadata = {
            'name': table_name,
            'columns': [],
            'primary_keys': [],
            'foreign_keys': []
        }
        
        # Extrair colunas
        cursor.execute("""
            SELECT 
                column_name,
                data_type,
                is_nullable,
                column_default,
                ordinal_position
            FROM information_schema.columns 
            WHERE table_name = %s 
            AND table_schema = 'public'
            ORDER BY ordinal_position
        """, (table_name,))
        
        for row in cursor.fetchall():
            column = {
                'name': row[0],
                'type': row[1],
                'nullable': row[2] == 'YES',
                'default': row[3],
                'position': row[4]
            }
            table_metadata['columns'].append(column)
        
        # Extrair chaves primárias
        cursor.execute("""
            SELECT column_name
            FROM information_schema.key_column_usage kcu
            JOIN information_schema.table_constraints tc 
                ON kcu.constraint_name = tc.constraint_name
            WHERE tc.table_name = %s 
            AND tc.constraint_type = 'PRIMARY KEY'
            AND tc.table_schema = 'public'
        """, (table_name,))
        
        table_metadata['primary_keys'] = [row[0] for row in cursor.fetchall()]
        
        # Extrair chaves estrangeiras
        cursor.execute("""
            SELECT 
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name
            FROM information_schema.key_column_usage kcu
            JOIN information_schema.constraint_column_usage ccu 
                ON kcu.constraint_name = ccu.constraint_name
            JOIN information_schema.table_constraints tc 
                ON kcu.constraint_name = tc.constraint_name
            WHERE tc.table_name = %s 
            AND tc.constraint_type = 'FOREIGN KEY'
            AND tc.table_schema = 'public'
        """, (table_name,))
        
        for row in cursor.fetchall():
            fk = {
                'column': row[0],
                'referenced_table': row[1],
                'referenced_column': row[2]
            }
            table_metadata['foreign_keys'].append(fk)
        
        return table_metadata
    
    def get_table_count(self, table_name: str) -> int:
        """Obtém contagem de registros de uma tabela"""
        with self._get_connection() as conn:
            cursor = conn.cursor()
            cursor.execute(f"SELECT COUNT(*) FROM {table_name}")
            return cursor.fetchone()[0]