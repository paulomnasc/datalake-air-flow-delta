from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime
import os
import pandas as pd
import io
from airflow.providers.amazon.aws.hooks.s3 import S3Hook

# ====================================================================
# VARIÁVEIS DE CONFIGURAÇÃO
# ====================================================================
BUCKET = 'lab01'
RAW_PREFIX = 'processed/raw/'
TRUSTED_PREFIX = 'processed/trusted/'

# ====================================================================
# HOOK E CLIENTE S3
# ====================================================================
s3_hook = S3Hook(aws_conn_id='minio_conn')
s3_client = s3_hook.get_conn()

# ====================================================================
# FUNÇÃO DE TRATAMENTO E MOVIMENTAÇÃO
# ====================================================================
def tratar_e_mover():
    response = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=RAW_PREFIX)
    arquivos = response.get('Contents', [])

    # ✅ Gerar o timestamp uma única vez por execução
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')

    for obj in arquivos:
        key = obj['Key']
        if not key.endswith('.csv'):
            continue

        # Baixar o CSV para memória
        csv_obj = s3_client.get_object(Bucket=BUCKET, Key=key)
        csv_data = csv_obj['Body'].read().decode('utf-8')
        df = pd.read_csv(io.StringIO(csv_data))
        df = pd.read_csv(io.StringIO(csv_data))
        print(f"📊 Registros lidos de {key}: {df.shape}")

        # Tratamento simples: padronização de colunas e remoção de duplicatas
        df.columns = [col.strip().lower() for col in df.columns]
        df.drop_duplicates(inplace=True)

        # Exportar para Parquet em memória
        parquet_buffer = io.BytesIO()
        df.to_parquet(parquet_buffer, index=False)

        # Gerar novo nome com timestamp fixo
        base_name = os.path.basename(key).replace('.csv', '')
        new_key = f"{TRUSTED_PREFIX}{base_name}_{timestamp}.parquet"

        # Enviar para zona trusted
        s3_client.put_object(
            Bucket=BUCKET,
            Key=new_key,
            Body=parquet_buffer.getvalue()
        )

        print(f"Arquivo tratado e movido para trusted: {new_key}")

# ====================================================================
# DEFINIÇÃO DA DAG
# ====================================================================
default_args = {
    'start_date': datetime(2023, 1, 1),
    'catchup': False
}

with DAG(
    dag_id='transform_raw_to_trusted_parquet',
    schedule_interval='@daily',
    default_args=default_args,
    description='Transforma arquivos CSV da zona raw e salva como Parquet na zona trusted',
) as dag:

    transformar_task = PythonOperator(
        task_id='tratar_e_mover',
        python_callable=tratar_e_mover
    )