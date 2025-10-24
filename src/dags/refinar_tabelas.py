from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime
from utils.io_s3 import ler_parquet_s3, salvar_parquet_s3
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
from utils.transformations.refined import customers, orders, products  # + orders, products...

BUCKET = 'lab01'
TABELAS = {
    "customers": {
        "prefix_trusted": "processed/trusted/customers_",
        "prefix_refined": "processed/refined/customers_",
        "transformador": customers.refinar
    },
    "orders": {
        "prefix_trusted": "processed/trusted/orders_",
        "prefix_refined": "processed/refined/orders_",
        "transformador": orders.refinar
    },
    "products": {
        "prefix_trusted": "processed/trusted/products_",
        "prefix_refined": "processed/refined/products_",
        "transformador": products.refinar
    }
    # Adicione orders e products aqui
}

def refinar_tabela(tabela_nome):
    config = TABELAS[tabela_nome]
    s3_hook = S3Hook(aws_conn_id='minio_conn')
    s3_client = s3_hook.get_conn()

    trusted_objs = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=config["prefix_trusted"]).get('Contents', [])
    refined_objs = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=config["prefix_refined"]).get('Contents', [])

    trusted_keys = [obj['Key'] for obj in trusted_objs if obj['Key'].endswith('.parquet')]
    refined_keys = [obj['Key'] for obj in refined_objs if obj['Key'].endswith('.parquet')]
    pendentes = [key for key in trusted_keys if key.replace('trusted', 'refined') not in refined_keys]

    for trusted_key in pendentes:
        df = ler_parquet_s3(BUCKET, trusted_key)
        df_refinado = config["transformador"](df)
        refined_key = trusted_key.replace('trusted', 'refined')
        salvar_parquet_s3(df_refinado, BUCKET, refined_key)
        print(f"✅ Refinado: {refined_key}")

with DAG(
    dag_id='refinar_tabelas',
    schedule_interval='@daily',
    start_date=datetime(2023, 1, 1),
    catchup=False
) as dag:
    for tabela in TABELAS:
        PythonOperator(
            task_id=f"refinar_{tabela}",
            python_callable=refinar_tabela,
            op_args=[tabela]
        )
