from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def get_script_path():
    candidate_paths = [
        '/usr/local/bin/scripts/radar_reversao_nobet_sweetspot.py',
        '/root/datalake-air-flow-delta/scripts/radar_reversao_nobet_sweetspot.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/radar_reversao_nobet_sweetspot.py'))
    ]
    for p in candidate_paths:
        if os.path.exists(p):
            return p
    return candidate_paths[1]

def run_radar_reversao_sweetspot(**kwargs):
    """
    Executa o Radar de Reversão de NO_BET por Flutuação de Odds no Sweet Spot.
    Varre partidas abertas marcadas com abstenção (NO_BET), reavalia contra
    as cotações atualizadas no MySQL e gera novas entradas caso entrem
    no Sweet Spot (1.60 a 1.95) com probabilidade >= 60% e +EV >= 5%.
    """
    script_path = get_script_path()
    cmd = ['python3', script_path]

    # Suporte a parâmetros manuais via dag_run.conf no Airflow UI
    dag_run = kwargs.get('dag_run')
    if dag_run and dag_run.conf:
        target_date = dag_run.conf.get('target_date')
        if target_date:
            cmd.extend(['--date', str(target_date)])
        if dag_run.conf.get('allow_api'):
            cmd.append('--allow-api')

    print(f"🎯 [Airflow DAG] Executando Radar de Reversão Sweet Spot: {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    print("--- STDOUT ---")
    print(result.stdout)
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
    if result.returncode != 0:
        raise Exception(f"❌ O Radar de Reversão Sweet Spot falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=2),
}

dag = DAG(
    'radar_reversao_sweetspot_dag',
    default_args=default_args,
    schedule_interval='*/30 * * * *',  # Executa a cada 30 minutos monitorando flutuações de odds intradiárias
    catchup=False,
    description="Radar de Reversão de NO_BET no Sweet Spot: monitora partidas com abstenção que entraram no Sweet Spot seguro (1.60 a 1.95) com +EV >= 5% e alta probabilidade, revertendo para sinal verde.",
    tags=['football', 'sentinela', 'reversao', 'sweetspot', 'handicap', 'gols', 'valor_esperado']
)

task_radar = PythonOperator(
    task_id='radar_reversao_sweetspot_job',
    python_callable=run_radar_reversao_sweetspot,
    dag=dag
)
