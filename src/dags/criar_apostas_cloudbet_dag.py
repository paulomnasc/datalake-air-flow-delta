from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_criar_apostas_cloudbet_script(**kwargs):
    """
    Executa o script autônomo de criação de apostas da Cloudbet (100% Robot).
    Reconcilia partidas do MySQL com a Cloudbet, avalia +EV pelo Gatekeeper
    e emite as ordens em modo Dry-Run ou Real com micro-stakes de 0.10 USDT.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/criar_apostas_cloudbet_diario.py',
        '/root/datalake-air-flow-delta/scripts/criar_apostas_cloudbet_diario.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/criar_apostas_cloudbet_diario.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    # Parâmetros opcionais via dag_run.conf (ex: target_date, fixture_id, real)
    dag_run = kwargs.get('dag_run')
    cmd = ['python3', script_path]

    if dag_run and dag_run.conf:
        target_date = dag_run.conf.get('target_date')
        fixture_id = dag_run.conf.get('fixture_id')
        force_real = dag_run.conf.get('real')

        if target_date:
            cmd.extend(['--date', str(target_date)])
        if fixture_id:
            cmd.extend(['--fixture_id', str(fixture_id)])
        if force_real:
            cmd.append('--real')

    print(f"🚀 [Airflow DAG Cloudbet] Executando criador de apostas autônomo: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de criação de apostas Cloudbet falhou com código {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=2),
}

dag = DAG(
    'criar_apostas_cloudbet_dag',
    default_args=default_args,
    schedule_interval='*/30 * * * *',  # Executa a cada 30 minutos capturando abertura de linhas no pré-jogo
    catchup=False,
    description="DAG do Airflow 100% Robotizada: avalia partidas em aberto, consulta odds reais da Cloudbet via proxy suíço e cria apostas chanceladas pelo Gatekeeper com stake mínima de 0.10 USDT.",
    tags=['football', 'cloudbet', 'trading', 'robot', '100_percent_automated', 'usdt']
)

criar_cloudbet_task = PythonOperator(
    task_id='criar_apostas_cloudbet_diario',
    python_callable=run_criar_apostas_cloudbet_script,
    dag=dag
)
