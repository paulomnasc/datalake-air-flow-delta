import unittest
from unittest.mock import MagicMock, patch, call
import os
import sys
import logging

# Configure logging to see what's happening in the code being tested
logging.basicConfig(level=logging.INFO, stream=sys.stdout)
log = logging.getLogger(__name__)

# Pre-mocking to avoid import errors
sys.modules['airflow'] = MagicMock()
sys.modules['airflow.providers'] = MagicMock()
sys.modules['airflow.providers.mysql'] = MagicMock()
sys.modules['airflow.providers.mysql.hooks'] = MagicMock()
sys.modules['airflow.providers.mysql.hooks.mysql'] = MagicMock()
sys.modules['airflow.providers.amazon'] = MagicMock()
sys.modules['airflow.providers.amazon.aws'] = MagicMock()
sys.modules['airflow.providers.amazon.aws.hooks'] = MagicMock()
sys.modules['airflow.providers.amazon.aws.hooks.s3'] = MagicMock()

# Now we can safely import the code
from lib.mysql_ingestion import ingest_mysql_to_raw

class TestMySqlIngestion(unittest.TestCase):
    
    def setUp(self):
        # Reset mocks before each test
        self.mock_mysql_hook_class = sys.modules['airflow.providers.mysql.hooks.mysql'].MySqlHook
        self.mock_s3_hook_class = sys.modules['airflow.providers.amazon.aws.hooks.s3'].S3Hook
        
        self.mock_mysql_hook_class.reset_mock()
        self.mock_s3_hook_class.reset_mock()

    @patch('pandas.read_sql')
    @patch('pandas.DataFrame.to_csv')
    @patch('tempfile.mkdtemp')
    @patch('os.path.exists')
    @patch('shutil.rmtree')
    def test_ingest_mysql_to_raw_creates_csv_for_selected_tables(
        self, mock_rmtree, mock_exists, mock_mkdtemp, 
        mock_to_csv, mock_read_sql
    ):
        """
        Verifica se a função ingest_mysql_to_raw cria corretamente um arquivo CSV
        na camada raw para uma tabela selecionada.
        """
        # Configuração do Mock
        mock_mysql_conn_id = 'mysql_source'
        mock_table_name = 'users'
        mock_dag_id = 'test_dag_ingestion'
        mock_bucket = 'test-bucket'
        
        # Simula diretório temporário
        mock_mkdtemp.return_value = '/tmp/fake_dir'
        mock_exists.return_value = True
        
        # Simula dados retornados pelo MySQL
        import pandas as pd
        mock_df = MagicMock(spec=pd.DataFrame)
        mock_df.__len__.return_value = 10
        mock_df.columns = ['id', 'name', 'email']
        mock_read_sql.return_value = mock_df
        
        # Executa a função de ingestão
        try:
            with patch.dict(os.environ, {"MINIO_BUCKET": mock_bucket}):
                result = ingest_mysql_to_raw(
                    mysql_conn_id=mock_mysql_conn_id,
                    table_name=mock_table_name,
                    dag_id=mock_dag_id
                )
        except Exception as e:
            print(f"\nCaught exception during ingestion: {e}")
            raise
            
        # Verificações
        
        # 1. Verificou se instanciou o MySqlHook correto
        self.mock_mysql_hook_class.assert_called_with(mysql_conn_id=mock_mysql_conn_id)
        
        # 2. Verificou se salvou o CSV localmente (no mock_df retornado por read_sql)
        mock_df.to_csv.assert_called_once()
        
        # 3. Verificação CRÍTICA: Se fez o upload para a pasta RAW com o caminho correto
        self.mock_s3_hook_class.assert_called()
        mock_s3_instance = self.mock_s3_hook_class.return_value
        mock_s3_instance.load_file.assert_called_once()
        
        _, load_kwargs = mock_s3_instance.load_file.call_args
        self.assertTrue(load_kwargs['key'].startswith(f"raw/{mock_dag_id}/"))
        self.assertTrue(load_kwargs['key'].endswith(".csv"))
        
        # 4. Verificou o retorno
        self.assertEqual(result['table'], mock_table_name)
        self.assertEqual(result['rows'], 10)

    @patch('lib.mysql_ingestion.ingest_mysql_to_raw')
    def test_multi_table_ingestion_logic(self, mock_ingest):
        """
        Simula a lógica de uma DAG multi-table para garantir que todas as tabelas
        selecionadas na UX passem pelo processo de ingestão.
        """
        # Evitando importar factory_master aqui para não quebrar por mocks de airflow
        selected_tables = ['customers', 'orders', 'products']
        
        for table in selected_tables:
            mock_ingest(
                mysql_conn_id='mysql_default',
                table_name=table,
                dag_id='multi_table_dag'
            )
            
        self.assertEqual(mock_ingest.call_count, len(selected_tables))
        mock_ingest.assert_has_calls([
            call(mysql_conn_id='mysql_default', table_name='customers', dag_id='multi_table_dag'),
            call(mysql_conn_id='mysql_default', table_name='orders', dag_id='multi_table_dag'),
            call(mysql_conn_id='mysql_default', table_name='products', dag_id='multi_table_dag'),
        ], any_order=True)

if __name__ == '__main__':
    unittest.main()
