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
    def test_run_pipeline_for_dag(self, mock_s3_hook):
        dag_id = os.environ.get('TEST_DAG_ID', 'default_test_dag')
        owner = os.environ.get('TEST_OWNER', 'airflow')
        
        log.info(f"🧪 Starting pipeline test for DAG: {dag_id} (Owner: {owner})")
        
        # Instantiate pipeline
        pipeline = RawToMedallionPipeline()
        
        # Mock internal processing steps to avoid complex dependencies
        pipeline._process_bronze = MagicMock()
        pipeline._process_silver = MagicMock()
        pipeline._process_gold = MagicMock()
        pipeline._process_delta = MagicMock()
        pipeline._cleanup = MagicMock()
        
        # Run pipeline
        result = pipeline(
            source_filename=f'raw/{dag_id}/table1.csv',
            target_table_name='table1',
            owner=owner,
            dag_id=dag_id
        )
        
        # Assertions on orchestration
        log.info("Checking pipeline orchestration...")
        pipeline._process_bronze.assert_called_once()
        pipeline._process_silver.assert_called_once()
        pipeline._process_gold.assert_called_once()
        pipeline._process_delta.assert_called_once()
        
        log.info(f"✅ Pipeline orchestration for {dag_id} verified!")

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
