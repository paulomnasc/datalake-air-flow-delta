from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime
import json
import os
from airflow.providers.amazon.aws.hooks.s3 import S3Hook # Import necessário

# ====================================================================
# CORREÇÃO 1: Definir as variáveis antes de qualquer código que as use.
# ====================================================================
BUCKET = 'lab01'
RAW_PREFIX = 'processed/raw/'
TRUSTED_PREFIX = 'processed/trusted/'

# ====================================================================
# HOOK E CLIENT S3 (Definidos no escopo global para acesso pela função)
# ====================================================================
s3_hook = S3Hook(aws_conn_id='minio_conn')
s3_client = s3_hook.get_conn() # s3_client é o cliente correto

# ====================================================================
# CORREÇÃO 2: Removida a chamada 'response = s3_client.list_objects_v2(...)' 
# do escopo global para evitar o erro Broken DAG.
# ====================================================================

def validar_e_mover():
    # ====================================================================
    # CORREÇÃO 3: Usar o cliente CORRETO (s3_client) em toda a função.
    # ====================================================================
    
    # 1. Listar objetos
    response = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=RAW_PREFIX)
    arquivos = response.get('Contents', [])

    for obj in arquivos:
        key = obj['Key']
        if not key.endswith('.json'):
            continue

        # Baixar o arquivo temporariamente
        local_file = '/tmp/temp.json'
        s3_client.download_file(BUCKET, key, local_file) # Usando s3_client

        # Validar JSON
        try:
            with open(local_file, 'r') as f:
                json.load(f)
        except Exception:
            print(f"Arquivo inválido: {key}")
            continue

        # Gerar novo nome com timestamp
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        base_name = os.path.basename(key).replace('.json', '')
        new_key = f"{TRUSTED_PREFIX}{base_name}_{timestamp}.json"

        # Copiar para zona trusted
        s3_client.copy_object(Bucket=BUCKET, CopySource=f"{BUCKET}/{key}", Key=new_key) # Usando s3_client
        # s3_client.delete_object(Bucket=BUCKET, Key=key) # Usando s3_client
        print(f"Copiado para trusted: {new_key}")

default_args = {
    'start_date': datetime(2023, 1, 1),
    'catchup': False
}

with DAG(
    dag_id='validate_and_move_persons_to_trusted',
    schedule_interval='@daily',
    default_args=default_args,
    description='Valida arquivos da zona raw e move para trusted com versionamento',
) as dag:

    validar_e_mover_task = PythonOperator(
        task_id='validar_e_mover',
        python_callable=validar_e_mover
    )