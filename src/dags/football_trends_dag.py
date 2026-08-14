from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os

def run_football_ingest_script(**kwargs):
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/football_ingest_trends.py'
    
    # Check if target_date was passed in dag run configuration (conf)
    dag_run = kwargs.get('dag_run')
    target_date = None
    if dag_run and dag_run.conf:
        target_date = dag_run.conf.get('target_date')
        
    cmd = ['python3', script_path]
    if target_date:
        cmd.append(str(target_date))
        
    print(f"Executing script: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"The football trends ingest script failed with exit code {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'football_trends_ingestion_dag',
    default_args=default_args,
    schedule_interval='0 */3 * * *',  # Runs every 3 hours
    catchup=False,
    description="Ingests football fixtures, referee statistics and odds for trends dashboard every 3 hours",
    tags=['football', 'api', 'ingestion', 'trends']
)

ingest_task = PythonOperator(
    task_id='ingest_football_trends',
    python_callable=run_football_ingest_script,
    dag=dag
)
