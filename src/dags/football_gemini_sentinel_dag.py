from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_gemini_sentinel_script(**kwargs):
    """
    Executa o auditor Sentinela pré-jogo (Gemini Pro + Google Search Grounding)
    para checagem em tempo real de escalações oficiais, notícias de vestiário e
    jogadores poupados nas partidas a iniciar entre 15 e 50 minutos.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/gemini_sentinel.py',
        '/root/datalake-air-flow-delta/scripts/gemini_sentinel.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/gemini_sentinel.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    cmd = ['python3', script_path, '--window', '50']

    print(f"🚀 [Airflow DAG] Executando Auditor Sentinela Gemini Pro: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script do Sentinela Gemini falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=2),
}

dag = DAG(
    'football_gemini_sentinel_dag',
    default_args=default_args,
    schedule_interval='*/15 * * * *',  # Executa a cada 15 minutos monitorando a janela de 15 a 50 min
    catchup=False,
    description="DAG do Airflow dedicada a auditar partidas pré-jogo (janela 15-50 min) via Gemini Pro com Google Search Grounding, checando escalações, titulares poupados e gerando alertas no sininho sem cancelar apostas.",
    tags=['football', 'sentinela', 'gemini', 'ia', 'prejogo', 'escalacoes', 'sininho']
)

task = PythonOperator(
    task_id='audit_matches_with_gemini_sentinel',
    python_callable=run_gemini_sentinel_script,
    dag=dag
)
