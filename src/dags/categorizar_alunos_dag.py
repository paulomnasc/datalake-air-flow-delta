from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.providers.mysql.hooks.mysql import MySqlHook
from datetime import datetime
import subprocess
import os

def run_categorizar_script():
    # Obtém as credenciais do MySQL via Airflow Connection
    hook = MySqlHook(mysql_conn_id='mydataflow-conn')
    conn_obj = hook.get_connection('mydataflow-conn')
    
    # Prepara as variáveis de ambiente a partir da conexão para repassar ao script
    env = os.environ.copy()
    env['MYSQL_HOSTNAME'] = conn_obj.host or ''
    env['MYSQL_USERNAME'] = conn_obj.login or ''
    env['MYSQL_PASSWORD'] = conn_obj.password or ''
    env['MYSQL_DATABASE'] = conn_obj.schema or ''
    env['MYSQL_PORT'] = str(conn_obj.port) if conn_obj.port else '3306'
    env['RUNNING_IN_DOCKER'] = '1'

    # Caminho do script mapeado no volume do worker/webserver do Airflow
    script_path = '/usr/local/bin/scripts/categorizar_alunos.py'
    
    # Chama o script por subprocesso passando a flag para atualizar o BD
    cmd = ['python', script_path, '--update-db']
    
    print(f"Executando script: {' '.join(cmd)}")
    result = subprocess.run(cmd, env=env, capture_output=True, text=True)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"O script falhou com código {result.returncode}")

default_args = {
    'owner': 'airflow',
    'start_date': datetime(2024, 1, 1),
    'retries': 1
}

dag = DAG(
    'categorizar_alunos_dag',
    default_args=default_args,
    schedule_interval='0 0 * * *',
    catchup=False,
    description="Executa o script de categorização: /scripts/categorizar_alunos.py"
)

categorizar_task = PythonOperator(
    task_id='categorizar_alunos',
    python_callable=run_categorizar_script,
    dag=dag
)
