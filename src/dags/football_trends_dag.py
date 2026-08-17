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
        
    import sys
    cmd = ['python3', '-u', script_path]
    if target_date:
        cmd.append(str(target_date))
        
    print(f"Executing script: {' '.join(cmd)}", flush=True)
    
    process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    if process.stdout:
        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
    process.wait()
    
    if process.returncode != 0:
        raise Exception(f"The football trends ingest script failed with exit code {process.returncode}")

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
    schedule_interval='0 */2 * * *',  # Runs every 2 hours
    catchup=False,
    description="Ingests football fixtures, referee statistics and odds for trends dashboard every 2 hours",
    tags=['football', 'api', 'ingestion', 'trends']
)

ingest_task = PythonOperator(
    task_id='ingest_football_trends',
    python_callable=run_football_ingest_script,
    dag=dag
)
