from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess

def run_shortener_script(**kwargs):
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/shorten_lomadee_urls.py'
    
    # Check if force_refresh was passed in dag run configuration
    dag_run = kwargs.get('dag_run')
    force_refresh = False
    if dag_run and dag_run.conf:
        force_refresh = dag_run.conf.get('force_refresh', False)
        
    cmd = ['python', script_path]
    if force_refresh:
        cmd.append('--force-refresh')
        
    print(f"Executing script: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"The lomadee shortener script failed with exit code {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'lomadee_shortener_dag',
    default_args=default_args,
    schedule=None,  # Manual trigger
    catchup=False,
    description="Shortens Lomadee product URLs and stores them in PostgreSQL",
    tags=['lomadee', 'api', 'shortener']
)

shorten_task = PythonOperator(
    task_id='shorten_lomadee_products',
    python_callable=run_shortener_script,
    dag=dag
)
