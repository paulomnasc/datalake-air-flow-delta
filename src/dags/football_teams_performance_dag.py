from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os

def run_football_teams_performance_script(**kwargs):
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/football_ingest_teams_performance.py'
    
    dag_run = kwargs.get('dag_run')
    env = os.environ.copy()
    if dag_run and dag_run.conf and dag_run.conf.get('force_refresh'):
        env['FORCE_REFRESH'] = '1'

    import sys
    cmd = ['python3', '-u', script_path]
    print(f"Executing script: {' '.join(cmd)}", flush=True)
    
    process = subprocess.Popen(cmd, env=env, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    if process.stdout:
        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
    process.wait()
    
    if process.returncode != 0:
        raise Exception(f"The football teams performance ingest script failed with exit code {process.returncode}")

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
    schedule_interval='0 7 * * *',  # Executa diariamente às 07:00 AM (America/São Paulo)
    catchup=False,
    description="Ingests football team statistics and moving averages daily at 07:00 AM (America/São Paulo)",
    tags=['football', 'api', 'ingestion', 'performance', 'teams']
)

ingest_task = PythonOperator(
    task_id='ingest_football_teams_performance',
    python_callable=run_football_teams_performance_script,
    dag=dag
)
