"""
Script principal para catalogação de dados
"""

import logging
from config import ATLAS_CONFIG, POSTGRES_CONFIG
from atlas_client import AtlasClient
from postgres_extractor import PostgreSQLExtractor
from data_catalogger import DataCatalogger
from discovery_report import DiscoveryReport


def setup_logging():
    """Configura logging"""
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )


def main():
    """Função principal"""
    setup_logging()
    logger = logging.getLogger(__name__)
    
    try:
        # Inicializar componentes
        logger.info("Inicializando componentes...")
        atlas = AtlasClient(**ATLAS_CONFIG)
        extractor = PostgreSQLExtractor(**POSTGRES_CONFIG)
        catalogger = DataCatalogger(atlas, extractor)
        
        # Catalogar dados
        logger.info("Iniciando catalogação...")
        results = catalogger.catalog_all_tables()
        logger.info(f"Catalogação concluída: {results['tables_created']} tabelas catalogadas")
        
        # Gerar relatório
        logger.info("Gerando relatório...")
        report = DiscoveryReport(atlas)
        report.generate_report("discovery_report")
        report.print_summary()
        
        logger.info("Processo concluído com sucesso!")
        
    except Exception as e:
        logger.error(f"Erro durante execução: {e}")
        raise


if __name__ == "__main__":
    main()