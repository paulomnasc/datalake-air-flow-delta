from airflow import DAG
from airflow.operators.bash import BashOperator
from datetime import datetime, timedelta

default_args = {
    'owner': 'airflow',
    'start_date': datetime(2025, 10, 18),
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    dag_id='ingestao_delta_clientes',
    default_args=default_args,
    schedule_interval=None,
    catchup=False,
    tags=['delta', 'clientes', 'minio'],
)

ingestao_delta = BashOperator(
    task_id='ingestao_delta_clientes',
    bash_command='docker exec spark spark-submit /opt/spark-apps/ingest_delta_clientes.py',
    dag=dag
)
