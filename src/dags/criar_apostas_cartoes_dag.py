from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_criar_apostas_cartoes_script(**kwargs):
    """
    Executa o script de verificação de jogos em aberto do dia e criação de apostas no mercado de Cartões Under.
    Pode ser chamado via worker container ou localmente.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/criar_apostas_cartoes_diario.py',
        '/root/datalake-air-flow-delta/scripts/criar_apostas_cartoes_diario.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/criar_apostas_cartoes_diario.py'))
    ]
    
    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break
            
    if not script_path:
        script_path = candidate_paths[1]

    # Check se target_date foi passado nas configurações da DAG Run (dag_run.conf)
    dag_run = kwargs.get('dag_run')
    target_date = None
    if dag_run and dag_run.conf:
        target_date = dag_run.conf.get('target_date')

    cmd = ['python3', script_path]
    if target_date:
        cmd.append(str(target_date))

    print(f"🚀 [Airflow DAG] Executando script de criação de apostas Cartões Under: {' '.join(cmd)}")
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    
    print("--- STDOUT ---")
    print(result.stdout)
    
    if result.stderr:
        print("--- STDERR ---")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"❌ O script de criação de apostas Cartões Under falhou com código de saída {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'criar_apostas_cartoes_dag',
    default_args=default_args,
    schedule_interval='*/30 * * * *',  # Executa a cada 30 minutos capturando jogos na janela de pré-jogo (30 a 45 min antes do início)
    catchup=False,
    description="DAG do Airflow que verifica jogos em aberto na janela pré-jogo (30 a 45 min antes do início), cria apostas em Total de Cartões (Estratégia Under) para ligas do Brasil, CONMEBOL e Ligas de Elite (Premier League, La Liga, Serie A, Ligue 1, Primeira Liga, Eredivisie, Allsvenskan, MLS, Liga MX e Liga Profesional Arg) e envia e-mail com apostas criadas.",
    tags=['football', 'cartoes', 'cards_under', 'apostas', 'prematch_creation', 'email_notification']
)

criar_task = PythonOperator(
    task_id='criar_apostas_cartoes_diario',
    python_callable=run_criar_apostas_cartoes_script,
    dag=dag
)
