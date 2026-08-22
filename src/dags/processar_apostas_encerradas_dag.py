from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import sys
import os

def run_processar_apostas_script(**kwargs):
    """
    Executa o script de verificação e liquidação de apostas dos jogos encerrados do dia.
    Pode ser chamado via worker container ou localmente.
    """
    # Caminho do script de processamento de apostas
    candidate_paths = [
        '/usr/local/bin/scripts/processar_apostas_encerradas.py',
        '/root/datalake-air-flow-delta/scripts/processar_apostas_encerradas.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/processar_apostas_encerradas.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        # Tenta executar diretamente como módulo ou fallback
        script_path = candidate_paths[1]

    cmd = ['python3', script_path]
    print(f"🚀 [Airflow DAG] Executando script de processamento de apostas: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de processamento de apostas falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'processar_apostas_encerradas_dag',
    default_args=default_args,
    schedule_interval='0 * * * *',  # Executa a cada 1 hora para liquidação de jogos encerrados (economia de cota de API)
    catchup=False,
    description="DAG do Airflow que verifica jogos encerrados a cada 30 min e processa apostas/palpites (GREEN, RED, VOID, NO_BET)",
    tags=['football', 'apostas', 'settlement', 'intraday_30m']
)

processar_task = PythonOperator(
    task_id='processar_apostas_encerradas_23h',
    python_callable=run_processar_apostas_script,
    dag=dag
)
