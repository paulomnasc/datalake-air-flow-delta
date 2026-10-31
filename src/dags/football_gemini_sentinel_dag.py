from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def get_sentinel_script_path():
    candidate_paths = [
        '/usr/local/bin/scripts/gemini_sentinel.py',
        '/root/datalake-air-flow-delta/scripts/gemini_sentinel.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/gemini_sentinel.py'))
    ]
    for p in candidate_paths:
        if os.path.exists(p):
            return p
    return candidate_paths[1]

def run_phase1_sentinel(**kwargs):
    """
    Fase 1: Antecipação Matinal (Janela de 24 horas).
    Qualifica novas partidas do dia utilizando notícias web e desfalques reportados,
    liberando apostas com Sinal Verde para cravar a odd máxima na abertura.
    """
    script_path = get_sentinel_script_path()
    cmd = ['python3', script_path, '--phase', '1', '--window', '1440']

    print(f"🌅 [Airflow DAG] Executando Sentinela Fase 1 (Antecipação Matinal - 24h): {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    print("--- STDOUT (Fase 1) ---")
    print(result.stdout)
    if result.stderr:
        print("--- STDERR (Fase 1) ---")
        print(result.stderr)
    if result.returncode != 0:
        raise Exception(f"❌ O Sentinela Fase 1 falhou com código de saída {result.returncode}")

def run_phase2_sentinel(**kwargs):
    """
    Fase 2: Repescagem Pré-Jogo (Janela T-50m a T-15m) e Vigilância de Mercado.
    - Repescagem: confere a súmula oficial (11 titulares) para partidas em observação.
    - Vigilância de Mercado: checa se houve drift adverso nas apostas já confirmadas.
    """
    script_path = get_sentinel_script_path()
    cmd = ['python3', script_path, '--phase', '2', '--window', '50']

    print(f"⏱️ [Airflow DAG] Executando Sentinela Fase 2 (Repescagem Pré-Jogo e Drift - 50m): {' '.join(cmd)}")
    result = subprocess.run(cmd, capture_output=True, text=True)
    print("--- STDOUT (Fase 2) ---")
    print(result.stdout)
    if result.stderr:
        print("--- STDERR (Fase 2) ---")
        print(result.stderr)
    if result.returncode != 0:
        raise Exception(f"❌ O Sentinela Fase 2 falhou com código de saída {result.returncode}")

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
    schedule_interval='*/15 * * * *',  # Executa a cada 15 minutos cobrindo Fase 1 e Fase 2
    catchup=False,
    description="Esteira da Sentinela em Dois Estágios: Fase 1 (Antecipação Matinal 24h para Sinal Verde no topo da odd) e Fase 2 (Repescagem Pré-Jogo T-50m e Vigilância de Mercado).",
    tags=['football', 'sentinela', 'gemini', 'ia', 'fase1', 'fase2', 'repescagem', 'prejogo', 'drift']
)

task_phase1 = PythonOperator(
    task_id='sentinel_phase1_morning_advance',
    python_callable=run_phase1_sentinel,
    dag=dag
)

task_phase2 = PythonOperator(
    task_id='sentinel_phase2_pregame_repescagem',
    python_callable=run_phase2_sentinel,
    dag=dag
)

task_phase1 >> task_phase2
