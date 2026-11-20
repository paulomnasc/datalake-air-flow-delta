from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import subprocess
import os
import sys

def run_limpar_auditoria_expirada_script(**kwargs):
    """
    Executa o script de expurgo de auditoria (limpar_auditoria_expirada.py)
    para remover registros de ciclo de vida com mais de 30 dias de ocorrido
    em lotes controlados com consumo zero de cota externa.
    """
    candidate_paths = [
        '/usr/local/bin/scripts/limpar_auditoria_expirada.py',
        '/root/datalake-air-flow-delta/scripts/limpar_auditoria_expirada.py',
        os.path.abspath(os.path.join(os.path.dirname(__file__), '../../scripts/limpar_auditoria_expirada.py'))
    ]

    script_path = None
    for p in candidate_paths:
        if os.path.exists(p):
            script_path = p
            break

    if not script_path:
        script_path = candidate_paths[1]

    # Parâmetros opcionais via dag_run.conf
    dag_run = kwargs.get('dag_run')
    days = 30
    batch_size = 1000
    dry_run = False

    if dag_run and dag_run.conf:
        days = int(dag_run.conf.get('days', days))
        batch_size = int(dag_run.conf.get('batch_size', batch_size))
        dry_run = bool(dag_run.conf.get('dry_run', dry_run))

    cmd = ['python3', '-u', script_path, '--days', str(days), '--batch-size', str(batch_size)]
    if dry_run:
        cmd.append('--dry-run')

    print(f"🚀 [Airflow DAG Cleanup] Executando expurgo de auditoria expirada: {' '.join(cmd)}", flush=True)

    process = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True, bufsize=1)
    if process.stdout:
        for line in process.stdout:
            sys.stdout.write(line)
            sys.stdout.flush()
    process.wait()

    if process.returncode != 0:
        raise Exception(f"❌ O script de expurgo de auditoria falhou com código de saída {process.returncode}")


default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'limpar_auditoria_expirada_dag',
    default_args=default_args,
    schedule_interval='0 3 * * *',  # Executa diariamente às 03:00 da madrugada
    catchup=False,
    description="Expurga registros de eventos de auditoria e ciclo de vida (pipeline_lifecycle_logs) com mais de 30 dias de ocorrido em lotes seguros.",
    tags=['football', 'manutencao', 'auditoria', 'cleanup', 'lifecycle']
)

cleanup_task = PythonOperator(
    task_id='purgar_auditoria_30_dias',
    python_callable=run_limpar_auditoria_expirada_script,
    dag=dag
)
