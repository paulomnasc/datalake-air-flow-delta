from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os

def run_lomadee_script():
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/load_lomadee.py'
    
    # We execute the script as a subprocess
    cmd = ['python', script_path]
    print(f"Executing script: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"The lomadee script failed with exit code {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'lomadee_ingestion_dag',
    default_args=default_args,
    schedule=None,  # Manual trigger, or can be changed by the user
    catchup=False,
    description="Loads and normalizes Lomadee products from S3 parquet to PostgreSQL datalake_bi",
    tags=['lomadee', 'bronze', 'normalized']
)

ingest_task = PythonOperator(
    task_id='ingest_and_normalize_lomadee',
    python_callable=run_lomadee_script,
    dag=dag
)
