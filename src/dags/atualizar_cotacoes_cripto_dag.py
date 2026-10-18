from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_atualizar_cotacoes_cripto_script(**kwargs):
    """
    Executa o script de sincronização de cotações de criptomoedas com a API da Binance.
    Pode ser executado tanto no worker container do Airflow quanto localmente no host.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/atualizar_cotacoes_cripto.py',
        '/root/datalake-air-flow-delta/scripts/atualizar_cotacoes_cripto.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/atualizar_cotacoes_cripto.py'))
    ]

    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break

    if not script_path:
        script_path = candidate_paths[0]

    cmd = ['python3', '-u', script_path]
    print(f"🚀 [Airflow DAG] Executando atualização de cotações cripto: {' '.join(cmd)}", flush=True)

    process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    if process.stdout:
        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
    process.wait()

    if process.returncode != 0:
        raise Exception(f"❌ O script de atualização de cotações falhou com código de saída {process.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 2,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'atualizar_cotacoes_cripto_dag',
    default_args=default_args,
    schedule_interval='0 6,12 * * *',  # Executa duas vezes ao dia: às 06:00 e às 12:00
    catchup=False,
    description="Atualiza automaticamente a cotação em Reais (BRL) das criptomoedas na tabela moedas_config via API pública da Binance Brasil duas vezes ao dia antes dos palpites.",
    tags=['football', 'crypto', 'moedas', 'binance', 'cotacoes']
)

task_atualizar_cotacoes = PythonOperator(
    task_id='atualizar_cotacoes_cripto_binance',
    python_callable=run_atualizar_cotacoes_cripto_script,
    dag=dag
)
