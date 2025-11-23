from datetime import datetime
from airflow import DAG
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
from airflow.operators.python import PythonOperator
import os

def upload_to_minio():
    # Caminho absoluto baseado no local do arquivo DAG
    base_path = os.path.dirname(__file__)
    local_path = os.path.join(base_path, 'datasources', 'Persons.json')

    # Configuração do S3Hook com a conexão MinIO
    s3_hook = S3Hook(aws_conn_id='minio_conn')
    bucket_name = 'lab01'
    object_name = 'processed/raw/Persons.json'

    # Upload do arquivo
    s3_hook.load_file(
        filename=local_path,
        key=object_name,
        bucket_name=bucket_name,
        replace=True
    )
    print(f"Arquivo {local_path} enviado para s3://{bucket_name}/{object_name}")

# Definição da DAG
with DAG(
    dag_id='upload_json_to_minio',
    start_date=datetime(2025, 10, 1),
    schedule_interval=None,
    catchup=False,
    tags=['minio', 'json']
) as dag:

    upload_task = PythonOperator(
        task_id='upload_to_minio',
        python_callable=upload_to_minio
    )