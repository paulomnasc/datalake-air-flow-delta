from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_processar_apostas_handicap_script(**kwargs):
    """
    Executa o script de verificação e liquidação de apostas em Handicap Asiático para jogos encerrados.
    Pode ser chamado via worker container ou localmente.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/processar_apostas_handicap_encerradas.py',
        '/root/datalake-air-flow-delta/scripts/processar_apostas_handicap_encerradas.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/processar_apostas_handicap_encerradas.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    cmd = ['python3', script_path]
    print(f"🚀 [Airflow DAG] Executando script de liquidação de apostas AH: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de liquidação de apostas AH falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'processar_apostas_handicap_dag',
    default_args=default_args,
    schedule_interval=None,  # DAG desativada (fora do escopo de atuação do usuário)
    catchup=False,
    description="DAG do Airflow que verifica jogos encerrados e liquida apostas no mercado de Handicap Asiático (Ganha, Meio Ganha, ANULADA, Meio Perdida, Perdida)",
    tags=['football', 'handicap', 'apostas', 'settlement_daily']
)

processar_task = PythonOperator(
    task_id='processar_apostas_handicap_encerradas',
    python_callable=run_processar_apostas_handicap_script,
    dag=dag
)
