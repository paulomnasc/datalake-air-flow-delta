from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
from datetime import datetime
import pandas as pd
from sqlalchemy import create_engine
import io

default_args = {
    "owner": "airflow",
    "start_date": datetime(2025, 10, 14),
    "retries": 1,
}

with DAG(
    dag_id="ingest_raw_mysql_to_minio",
    default_args=default_args,
    schedule_interval="@daily",
    catchup=False,
    tags=["raw_zone", "mysql", "minio"],
) as dag:

    def extract_and_upload_s3():
        # ✅ Conexão com MySQL ajustada
        engine = create_engine("mysql+mysqlconnector://root:root@mysql:3306/lista_revisao")

        # ✅ Conexão com MinIO via S3Hook
        s3_hook = S3Hook(aws_conn_id="minio_conn")
        bucket_name = "lab01"
        tabelas = ["customers", "orders", "products"]

        for tabela in tabelas:
            df = pd.read_sql(f"SELECT * FROM {tabela}", con=engine)

            # Exporta para CSV em memória
            csv_buffer = io.StringIO()
            df.to_csv(csv_buffer, index=False)

            # Envia para MinIO usando S3Hook
            s3_hook.load_string(
                string_data=csv_buffer.getvalue(),
                key=f"processed/raw/{tabela}.csv",
                bucket_name=bucket_name,
                replace=True
            )

    ingest_task = PythonOperator(
        task_id="extract_mysql_and_upload_to_minio",
        python_callable=extract_and_upload_s3
    )