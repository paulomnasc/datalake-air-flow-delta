from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def get_script_path():
    candidate_paths = [
        '/usr/local/bin/scripts/alerta_inicio_jogo_5m.py',
        '/root/datalake-air-flow-delta/scripts/alerta_inicio_jogo_5m.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/alerta_inicio_jogo_5m.py'))
    ]
    for p in candidate_paths:
        if os.path.exists(p):
            return p
    return candidate_paths[1]

def run_alerta_5min(**kwargs):
    """
    Executa a Sentinela de Início Iminente (T-5 minutos pré-jogo).
    Varre o MySQL em busca de jogos com início previsto entre 1 e 6 minutos
    e dispara push mobile de alta prioridade e notificação no Sininho.
    """
    script_path = get_script_path()
    cmd = ['python3', script_path]

    print(f"⏰ [Airflow DAG] Executando Sentinela Início Iminente (T-5m): {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    print("--- STDOUT ---")
    print(result.stdout)
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
    if result.returncode != 0:
        raise Exception(f"❌ A Sentinela de 5 minutos falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=1),
}

dag = DAG(
    'football_alerta_5min_dag',
    default_args=default_args,
    schedule_interval='*/3 * * * *',  # Executa a cada 3 minutos cobrindo a janela T-5m com precisão cirúrgica
    catchup=False,
    description="Sentinela de Início Iminente (Alerta T-5m): monitora partidas a 5 minutos do início e dispara push prioritário no smartphone e alerta no Sininho.",
    tags=['football', 'sentinela', 'prejogo', 'alerta5m', 'push', 'ntfy']
)

task_alerta_5min = PythonOperator(
    task_id='alerta_inicio_iminente_5m',
    python_callable=run_alerta_5min,
    dag=dag
)

