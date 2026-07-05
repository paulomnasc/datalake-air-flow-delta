# Copyright (C) 2026 Paulo Nascimento - Este programa é um software livre licenciado sob a GNU Affero General Public License v3.
"""
DAG Factory para processamento batch de múltiplos arquivos
Gerada automaticamente pela WebApp
"""

from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.utils.task_group import TaskGroup
from datetime import datetime, timedelta
import yaml
import os

def create_batch_dag_from_config(config_file):
    """
    Criar DAG dinâmica para processamento em batch baseado em arquivo YAML
    
    Args:
        config_file: Caminho para o arquivo de configuração YAML
        
    Returns:
        DAG configurada para processamento batch
    """
    # Carregar configuração
    with open(config_file, 'r') as f:
        config = yaml.safe_load(f)
    
    dag_id = config['dag_id']
    batch_id = config['batch_id']
    batch_mode = config.get('batch_mode', 'parallel')
    max_parallel = config.get('max_parallel_tasks', 4)
    bucket_name = config.get('bucket_name') or os.environ.get('MINIO_BUCKET', 'lab01')
    files = config['files']
    
    default_args = {
        'owner': config.get('owner', 'airflow'),
        'depends_on_past': False,
        'start_date': datetime(2024, 1, 1),
        'email_on_failure': config.get('email_on_failure', False),
        'email_on_retry': False,
        'retries': config.get('retries', 1),
        'retry_delay': timedelta(minutes=config.get('retry_delay_minutes', 5)),
    }
    
    dag = DAG(
        dag_id=dag_id,
        default_args=default_args,
        description=f'Batch processing: {batch_id} ({len(files)} arquivos)',
        schedule_interval=None,  # Manual trigger only
        catchup=False,
        tags=['batch', 'multi-file', batch_mode, f'files_{len(files)}']
    )
    
    with dag:
        if batch_mode == 'parallel':
            # Modo Paralelo: Uma única task processa todos os arquivos em paralelo
            from lib.medallion_pipeline import batch_raw_to_medallion
            
            process_batch = PythonOperator(
                task_id='process_all_files',
                python_callable=batch_raw_to_medallion,
                op_kwargs={
                    'batch_id': batch_id,
                    'files': files,
                    'max_parallel': max_parallel,
                    'dag_id': dag_id,
                    'bucket_name': bucket_name
                },
                doc_md=f"""
                ### Processamento Batch Paralelo
                
                - **Batch ID**: {batch_id}
                - **Total de arquivos**: {len(files)}
                - **Paralelismo**: {max_parallel} arquivos simultâneos
                - **Modo**: Paralelo (ThreadPoolExecutor)
                
                #### Arquivos:
                {chr(10).join(f"- {f['file_name']} ({f['size_bytes']} bytes)" for f in files)}
                """
            )
            
        else:
            # Modo Sequencial: Uma task por arquivo, executadas em sequência
            from lib.medallion_pipeline import raw_to_medallion
            
            with TaskGroup(group_id='process_files_sequential') as file_group:
                previous_task = None
                
                for idx, file_info in enumerate(files):
                    file_name = file_info['file_name']
                    source_path = file_info['source_path']
                    target_table = os.path.splitext(file_name)[0]
                    
                    task = PythonOperator(
                        task_id=f'process_{idx:02d}_{target_table}',
                        python_callable=raw_to_medallion,
                        op_kwargs={
                            'source_filename': source_path,
                            'target_table_name': target_table,
                            'dag_id': dag_id,
                            'bucket_name': bucket_name
                        },
                        doc_md=f"""
                        ### Processar: {file_name}
                        
                        - **Arquivo**: {file_name}
                        - **Origem**: {source_path}
                        - **Tabela**: {target_table}
                        - **Tamanho**: {file_info['size_bytes']} bytes
                        - **Posição**: {idx + 1} de {len(files)}
                        """
                    )
                    
                    # Encadear tasks sequencialmente
                    if previous_task is not None:
                        previous_task >> task
                    
                    previous_task = task
    
    return dag


# Auto-carregar DAGs de arquivos YAML em dags/configs/
def load_batch_dags():
    """
    Escanear diretório de configs e criar DAGs automaticamente
    """
    configs_dir = os.path.join(os.path.dirname(__file__), 'configs')
    
    if not os.path.exists(configs_dir):
        return {}
    
    dags = {}
    
    for filename in os.listdir(configs_dir):
        if filename.endswith('.yml') or filename.endswith('.yaml'):
            if filename.startswith('example_'):
                continue  # Ignorar exemplos
            
            config_path = os.path.join(configs_dir, filename)
            
            try:
                with open(config_path, 'r') as f:
                    config = yaml.safe_load(f)
                
                # Verificar se é uma config batch válida
                if 'batch_id' in config and 'files' in config:
                    dag = create_batch_dag_from_config(config_path)
                    dags[config['dag_id']] = dag
                    
            except Exception as e:
                print(f"Erro ao carregar DAG de {filename}: {e}")
    
    return dags


# Carregar todas as DAGs batch
batch_dags = load_batch_dags()

# Exportar DAGs para o Airflow
for dag_id, dag in batch_dags.items():
    globals()[dag_id] = dag
