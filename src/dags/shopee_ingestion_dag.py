from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.operators.trigger_dagrun import TriggerDagRunOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_shopee_ingestion_script():
    """
    Executa o script de extração da API da Shopee (shopee_ingest_offers.py).
    Testa primeiro o caminho mapeado no contêiner do worker e fallback para o repositório local.
    """
    script_paths = [
        '/usr/local/bin/scripts/shopee_ingest_offers.py',
        '/root/datalake-air-flow-delta/scripts/shopee_ingest_offers.py',
        os.path.join(os.path.dirname(__file__), '../../scripts/shopee_ingest_offers.py')
    ]

    selected_script = None
    for p in script_paths:
        if os.path.exists(p):
            selected_script = p
            break

    if not selected_script:
        selected_script = script_paths[0]

    cmd = [sys.executable, selected_script, "moda fitness", "50"]
    print(f"🚀 Executando script extrator da Shopee: {' '.join(cmd)}")

    result = subprocess.run(cmd, capture_output=True, text=True)

    print("STDOUT:")
    print(result.stdout)

    if result.stderr:
        print("STDERR:")
        print(result.stderr)

    if result.returncode != 0:
        raise Exception(f"❌ O script da Shopee falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=2),
}

dag = DAG(
    'shopee_ingestion_dag',
    default_args=default_args,
    schedule=None,  # Disparo manual ou agendado
    catchup=False,
    description="Consome a API GraphQL da Shopee via HMAC-SHA256 e grava em s3://paulomnasc-558/raw/promocao-shopee/",
    tags=['shopee', 'affiliate', 'raw', 'ingestion']
)

# 1. Tarefa de Extração da API da Shopee
ingest_shopee_task = PythonOperator(
    task_id='fetch_shopee_api_to_raw',
    python_callable=run_shopee_ingestion_script,
    dag=dag
)

# 2. Tarefa para disparar a DAG do Medalhão automaticamente após o upload dos dados brutos
trigger_medallion_task = TriggerDagRunOperator(
    task_id='trigger_promocao_shopee_medallion',
    trigger_dag_id='promocao-shopee98',
    reset_dag_run=True,
    wait_for_completion=False,
    dag=dag
)

# Encadeamento das tarefas
ingest_shopee_task >> trigger_medallion_task
