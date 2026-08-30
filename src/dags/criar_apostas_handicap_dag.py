from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_criar_apostas_handicap_script(**kwargs):
    """
    Executa o script de verificação de jogos em aberto do dia e criação de apostas no mercado de Handicap Asiático.
    Pode ser chamado via worker container ou localmente.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/criar_apostas_handicap_diario.py',
        '/root/datalake-air-flow-delta/scripts/criar_apostas_handicap_diario.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/criar_apostas_handicap_diario.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    # Check se target_date e confirmada foram passados nas configurações da DAG Run (dag_run.conf)
    dag_run = kwargs.get('dag_run')
    target_date = None
    confirmada = None
    if dag_run and dag_run.conf:
        target_date = dag_run.conf.get('target_date')
        confirmada = dag_run.conf.get('confirmada')

    cmd = ['python3', script_path]
    if target_date:
        cmd.append(str(target_date))
        if confirmada is not None:
            cmd.append(str(confirmada))

    print(f"🚀 [Airflow DAG] Executando script de criação de apostas AH: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de criação de apostas AH falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'criar_apostas_handicap_dag',
    default_args=default_args,
    schedule_interval=None,  # DAG desativada (fora do escopo de atuação do usuário)
    catchup=False,
    description="DAG do Airflow que verifica jogos em aberto na janela pré-jogo (30 a 45 min antes do apito inicial) e cria apostas no mercado de Handicap Asiático (Odd Mínima: 1.60)",
    tags=['football', 'handicap', 'apostas', 'prematch_creation']
)

criar_task = PythonOperator(
    task_id='criar_apostas_handicap_diario',
    python_callable=run_criar_apostas_handicap_script,
    dag=dag
)
