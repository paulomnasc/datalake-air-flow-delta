from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_revalidar_cards_cache_local_script(**kwargs):
    """
    Executa o script de revalidação e gravação universal local de cards via cache do MySQL.
    Consome ZERO chamadas de API externa e mantém os cards alinhados com os motores analíticos
    (asian_handicap_engine.py e cards_engine.py).
    """
    candidate_paths = [
        '/usr/local/bin/scripts/revalidar_cards_cache_local.py',
        '/root/datalake-air-flow-delta/scripts/revalidar_cards_cache_local.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/revalidar_cards_cache_local.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[0]

    # Parâmetros opcionais via dag_run.conf
    dag_run = kwargs.get('dag_run')
    market = "all"
    days = 1
    target_date = None
    sync_apostas = False

    if dag_run and dag_run.conf:
        market = dag_run.conf.get('market', market)
        days = dag_run.conf.get('days', days)
        target_date = dag_run.conf.get('target_date')
        sync_apostas = dag_run.conf.get('sync_apostas', False)

    cmd = ['python3', '-u', script_path, '--market', str(market), '--days', str(days)]
    if target_date:
        cmd.extend(['--date', str(target_date)])
    if sync_apostas:
        cmd.append('--sync_apostas')

    print(f"🚀 [Airflow DAG] Executando revalidação local de cards: {' '.join(cmd)}", flush=True)

    process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    if process.stdout:
        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
    process.wait()

    if process.returncode != 0:
        raise Exception(f"❌ O script de revalidação local falhou com código de saída {process.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'revalidar_cards_cache_local_dag',
    default_args=default_args,
    schedule_interval='*/30 * * * *',  # Executa a cada 30 minutos
    catchup=False,
    description="Revalida e sincroniza periodicamente os cards de Handicap e Cartões em fixtures_trends usando 100% de cache local MySQL (Zero API)",
    tags=['football', 'cache', 'gatekeeper', 'revalidation', 'zero_api']
)

revalidar_task = PythonOperator(
    task_id='revalidar_cards_cache_local',
    python_callable=run_revalidar_cards_cache_local_script,
    dag=dag
)
