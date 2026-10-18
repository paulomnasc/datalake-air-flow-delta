from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_football_sync_live_betano_script(**kwargs):
    """
    Executa o script de sincronização in-play (football_sync_live_betano.py)
    para captura de placares, minutos reais e oportunidades ao vivo via Betano
    com consumo zero de cota da API-Football.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/football_sync_live_betano.py',
        '/root/datalake-air-flow-delta/scripts/football_sync_live_betano.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/football_sync_live_betano.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    cmd = ['python3', script_path]

    print(f"🚀 [Airflow DAG Live] Executando sincronização in-play Betano: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de sincronização ao vivo falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=1),
}

dag = DAG(
    'football_trends_live_dag',
    default_args=default_args,
    schedule_interval='*/3 * * * *',  # Executa a cada 3 minutos para acompanhamento em tempo quase real
    catchup=False,
    description="DAG do Airflow dedicada a monitorar a cada 3 min partidas de futebol em andamento (1H, HT, 2H) via API gratuita da Betano, atualizando cronômetro e placar no dashboard e alertando novas oportunidades in-play sem consumir cota da API-Football.",
    tags=['football', 'trends', 'live', 'in-play', 'betano', 'sininho']
)

sync_live_task = PythonOperator(
    task_id='sync_live_betano_trends',
    python_callable=run_football_sync_live_betano_script,
    dag=dag
)
