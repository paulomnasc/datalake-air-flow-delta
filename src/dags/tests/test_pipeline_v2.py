import unittest
from unittest.mock import MagicMock, patch
import os
import sys
import argparse
import logging

# Configuration of logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
log = logging.getLogger(__name__)

# Pre-mocking Airflow to avoid import issues in non-airflow environments
sys.modules['airflow'] = MagicMock()
sys.modules['airflow.providers'] = MagicMock()
sys.modules['airflow.providers.amazon'] = MagicMock()
sys.modules['airflow.providers.amazon.aws'] = MagicMock()
sys.modules['airflow.providers.amazon.aws.hooks'] = MagicMock()
sys.modules['airflow.providers.amazon.aws.hooks.s3'] = MagicMock()
sys.modules['airflow.providers.mysql'] = MagicMock()
sys.modules['airflow.providers.mysql.hooks'] = MagicMock()
sys.modules['airflow.providers.mysql.hooks.mysql'] = MagicMock()

# Import the pipeline class
from lib.medallion_pipeline_v2 import RawToMedallionPipeline

class TestPipelineCLI(unittest.TestCase):
    """
    Test class designed to be executed via CLI with a specific dag_id.
    """
    
    @patch('airflow.providers.amazon.aws.hooks.s3.S3Hook')
    @patch('lib.medallion_pipeline_v2.RawToMedallionPipeline._read_file')
    @patch('pandas.DataFrame.to_parquet')
    def test_run_pipeline_for_dag(self, mock_to_parquet, mock_read_file, mock_s3_hook):
        dag_id = os.environ.get('TEST_DAG_ID', 'default_test_dag')
        owner = os.environ.get('TEST_OWNER', 'airflow')
        
        log.info(f"🧪 Starting pipeline test for DAG: {dag_id} (Owner: {owner})")
        
        # Setup mocks
        mock_s3_instance = mock_s3_hook.return_value
        mock_s3_instance.download_file.return_value = '/tmp/fake_source.csv'
        mock_s3_instance.list_keys.return_value = [f'raw/{dag_id}/table1.csv']
        
        import pandas as pd
        mock_df = MagicMock(spec=pd.DataFrame)
        mock_df.empty = False
        mock_df.__len__.return_value = 100
        mock_read_file.return_value = mock_df
        
        # Instantiate and run pipeline
        pipeline = RawToMedallionPipeline()
        
        # In a real scenario, we might want to fetch real config from DB 
        # but for a safe unit test we simulate the call with params
        result = pipeline(
            source_filename=f'raw/{dag_id}/table1.csv',
            target_table_name='table1',
            owner=owner,
            dag_id=dag_id
        )
        
        # Assertions
        log.info("Checking pipeline results...")
        self.assertIn('bronze', result)
        self.assertIn('silver', result)
        self.assertIn('gold', result)
        
        # Check if S3 load_file was called for each layer
        # Bronze, Silver, Gold (3 calls minimum)
        self.assertGreaterEqual(mock_s3_instance.load_file.call_count, 3)
        
        log.info(f"✅ Pipeline test for {dag_id} completed successfully!")

def run_cli():
    parser = argparse.ArgumentParser(description='Run Medallion Pipeline Test for a specific DAG')
    parser.add_argument('--dag_id', type=str, required=True, help='The ID of the DAG to test')
    parser.add_argument('--owner', type=str, default='airflow', help='The owner/bucket to use')
    
    args = parser.parse_args()
    
    # Pass arguments to the test class via environment variables
    os.environ['TEST_DAG_ID'] = args.dag_id
    os.environ['TEST_OWNER'] = args.owner
    
    # Run the test
    suite = unittest.TestLoader().loadTestsFromTestCase(TestPipelineCLI)
    runner = unittest.TextTestRunner(verbosity=2)
    result = runner.run(suite)
    
    sys.exit(not result.wasSuccessful())

if __name__ == '__main__':
    # If run directly from bash with arguments
    if len(sys.argv) > 1 and not sys.argv[1].startswith('Test'):
        run_cli()
    else:
        # Fallback to standard unittest behavior
        unittest.main()
