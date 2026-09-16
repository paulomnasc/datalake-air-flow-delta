from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_alerta_cartoes_prejogo_script(**kwargs):
    """
    Executa o script de monitoramento rápido pré-jogo (janela de 15 a 45 minutos)
    para captura de árbitro oficial recém-publicado e odds em tempo real da Betano,
    gerando alertas no sininho do site quando houver aposta aprovada pelo Gatekeeper.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/alerta_cartoes_prejogo_rapido.py',
        '/root/datalake-air-flow-delta/scripts/alerta_cartoes_prejogo_rapido.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/alerta_cartoes_prejogo_rapido.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    cmd = ['python3', script_path]

    print(f"🚀 [Airflow DAG] Executando script de monitoramento rápido de cartões pré-jogo: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de alerta de cartões pré-jogo falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=3),
}

dag = DAG(
    'alerta_cartoes_prejogo_dag',
    default_args=default_args,
    schedule_interval='*/30 * * * *',  # Executa a cada 30 minutos monitorando jogos na janela de 15 a 45 min
    catchup=False,
    description="DAG do Airflow que monitora a cada 30 min partidas a iniciar entre 15 e 45 min, captura árbitros recém-publicados pelas federações e cotações reais da Betano, aprovando apostas no Gatekeeper e gerando alertas no sininho do site.",
    tags=['football', 'cartoes', 'alerta_prejogo', 'sininho', 'betano', 'gatekeeper']
)

alerta_task = PythonOperator(
    task_id='alerta_cartoes_prejogo_rapido',
    python_callable=run_alerta_cartoes_prejogo_script,
    dag=dag
)
