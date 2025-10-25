from airflow import DAG
from airflow.operators.bash import BashOperator
from datetime import datetime, timedelta

default_args = {
    'owner': 'airflow',
    'start_date': datetime(2025, 10, 18),
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

for tabela in ['customers', 'products', 'orders']:
    with DAG(
        dag_id=f'ingestao_delta_{tabela}',
        default_args=default_args,
        schedule_interval=None,
        catchup=False,
        tags=['delta', tabela],
    ) as dag:

        ingestao_delta = BashOperator(
            task_id=f'ingestao_delta_{tabela}',
            bash_command=f'docker exec spark spark-submit /opt/spark-apps/ingest_delta.py {tabela}'
        )
