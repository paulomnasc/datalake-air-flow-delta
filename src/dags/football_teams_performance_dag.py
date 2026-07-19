from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os

def run_football_teams_performance_script(**kwargs):
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/football_ingest_teams_performance.py'
    
    cmd = ['python3', script_path]
    print(f"Executing script: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"The football teams performance ingest script failed with exit code {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'football_teams_performance_ingestion_dag',
    default_args=default_args,
    schedule_interval='0 4 * * 1',  # Runs weekly on Mondays at 04:00 AM
    catchup=False,
    description="Ingests football team statistics and moving averages weekly",
    tags=['football', 'api', 'ingestion', 'performance', 'teams']
)

ingest_task = PythonOperator(
    task_id='ingest_football_teams_performance',
    python_callable=run_football_teams_performance_script,
    dag=dag
)
