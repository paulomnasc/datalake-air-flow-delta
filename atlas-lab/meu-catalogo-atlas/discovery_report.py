"""
Gerador de relatórios de descoberta de dados
"""

import json
import csv
from datetime import datetime
from typing import Dict, List
from atlas_client import AtlasClient
from postgres_extractor import PostgreSQLExtractor


class DiscoveryReport:
    """Gerador de relatórios de descoberta"""
    
    def __init__(self, atlas_client: AtlasClient):
        """
        Inicializa o gerador de relatórios
        
        Args:
            atlas_client: Cliente do Atlas
        """
        self.atlas = atlas_client
    
    def generate_report(self, output_prefix: str = "discovery_report"):
        """
        Gera relatório completo de descoberta
        
        Args:
            output_prefix: Prefixo dos arquivos de saída
        """
        # Coletar estatísticas
        stats = self._collect_statistics()
        
        # Gerar relatório em JSON
        self._export_json(stats, f"{output_prefix}.json")
        
        # Gerar relatório em CSV
        self._export_csv(stats, f"{output_prefix}.csv")
        
        print(f"Relatórios gerados: {output_prefix}.json e {output_prefix}.csv")
    
    def _collect_statistics(self) -> Dict:
        """Coleta estatísticas das entidades catalogadas"""
        stats = {
            'timestamp': datetime.now().isoformat(),
            'databases': 0,
            'tables': 0,
            'columns': 0,
            'relationships': 0,
            'tables_detail': [],
            'summary': {}
        }
        
        try:
            # Buscar databases
            db_results = self.atlas.search_entities("hive_db")
            stats['databases'] = len(db_results.get('entities', []))
            
            # Buscar tabelas
            table_results = self.atlas.search_entities("hive_table")
            tables = table_results.get('entities', [])
            stats['tables'] = len(tables)
            
            # Buscar colunas
            column_results = self.atlas.search_entities("hive_column")
            stats['columns'] = len(column_results.get('entities', []))
            
            # Analisar detalhes das tabelas
            for table in tables:
                table_detail = {
                    'name': table.get('displayText', 'N/A'),
                    'guid': table.get('guid', 'N/A'),
                    'type': table.get('typeName', 'N/A')
                }
                stats['tables_detail'].append(table_detail)
            
            # Criar resumo
            stats['summary'] = {
                'total_entities': stats['databases'] + stats['tables'] + stats['columns'],
                'avg_columns_per_table': round(stats['columns'] / max(stats['tables'], 1), 2),
                'catalog_coverage': '100%' if stats['tables'] > 0 else '0%'
            }
            
        except Exception as e:
            print(f"Erro ao coletar estatísticas: {e}")
            
        return stats
    
    def _export_json(self, stats: Dict, filename: str):
        """Exporta relatório em formato JSON"""
        try:
            with open(filename, 'w', encoding='utf-8') as f:
                json.dump(stats, f, indent=2, ensure_ascii=False)
        except Exception as e:
            print(f"Erro ao exportar JSON: {e}")
    
    def _export_csv(self, stats: Dict, filename: str):
        """Exporta relatório em formato CSV"""
        try:
            with open(filename, 'w', newline='', encoding='utf-8') as f:
                writer = csv.writer(f)
                
                # Cabeçalho
                writer.writerow(['Métrica', 'Valor'])
                
                # Estatísticas gerais
                writer.writerow(['Timestamp', stats['timestamp']])
                writer.writerow(['Total Databases', stats['databases']])
                writer.writerow(['Total Tabelas', stats['tables']])
                writer.writerow(['Total Colunas', stats['columns']])
                writer.writerow(['Média Colunas/Tabela', stats['summary']['avg_columns_per_table']])
                writer.writerow(['Cobertura do Catálogo', stats['summary']['catalog_coverage']])
                
                # Linha em branco
                writer.writerow([])
                
                # Detalhes das tabelas
                writer.writerow(['Nome da Tabela', 'GUID', 'Tipo'])
                for table in stats['tables_detail']:
                    writer.writerow([table['name'], table['guid'], table['type']])
                    
        except Exception as e:
            print(f"Erro ao exportar CSV: {e}")
    
    def print_summary(self):
        """Imprime resumo das estatísticas"""
        stats = self._collect_statistics()
        
        print("\n" + "="*50)
        print("RELATÓRIO DE DESCOBERTA DE DADOS")
        print("="*50)
        print(f"Timestamp: {stats['timestamp']}")
        print(f"Total de Databases: {stats['databases']}")
        print(f"Total de Tabelas: {stats['tables']}")
        print(f"Total de Colunas: {stats['columns']}")
        print(f"Média de Colunas por Tabela: {stats['summary']['avg_columns_per_table']}")
        print(f"Cobertura do Catálogo: {stats['summary']['catalog_coverage']}")
        print("="*50)