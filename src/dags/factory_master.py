import os
import importlib
import json
import logging
import pendulum
from datetime import datetime, date
from typing import Dict, Any, List

# Airflow Core
from airflow.models.dag import DAG
from airflow.utils.dates import days_ago
from airflow.operators.python import PythonOperator
from airflow.decorators import task, dag as dag_decorator
from airflow.operators.bash import BashOperator 
from airflow.operators.python import BranchPythonOperator
from airflow.exceptions import AirflowException

# ----------------------------------------------------------------------
# 0. CONFIGURAÇÃO DE CAMINHO E LOGGER
# ----------------------------------------------------------------------

# Define o caminho absoluto do arquivo factory_master.py
DAG_FILE_PATH = os.path.abspath(__file__)

# Inicializa o logger
log = logging.getLogger(__name__)

# Airflow Provider para MySQL
try:
    from airflow.providers.mysql.hooks.mysql import MySqlHook
except ImportError as e:
    log.error(f"FATAL PARSING ERROR (MYSQL HOOK): Falha crítica ao importar MySqlHook: {e}")
    raise AirflowException("O pacote 'apache-airflow-providers-mysql' não está instalado. Por favor, instale-o.")


# ----------------------------------------------------------------------
# 1. CONFIGURAÇÃO E CONSTANTES
# ----------------------------------------------------------------------

MYSQL_CONN_ID = 'mysql_dag_metadata'

DEFAULT_ARGS = {
    'owner': 'airflow',
    'start_date': days_ago(1),
    'retries': 1,
}

# ----------------------------------------------------------------------
# 2. FUNÇÕES DE UTILIDADE E HOOKS
# ----------------------------------------------------------------------

def import_callable_from_path(module_path: str):
    """Importa e retorna uma função Python a partir de um caminho de módulo."""
    if '.' not in module_path:
        raise ValueError(f"O caminho do módulo é inválido: {module_path}")

    module_name, func_name = module_path.rsplit('.', 1)
    module = importlib.import_module(module_name)
    return getattr(module, func_name)

def fetch_dag_configurations(mysql_conn_id: str) -> List[tuple]:
    """
    Conecta ao MySQL e busca as configurações ativas da DAG.
    Retorna colunas incluindo is_multi_table, max_parallel_tasks e campos SQL dedicados.
    """
    sql_query = f"""
    SELECT
        id,
        dag_id, 
        schedule_interval,
        owner,
        description,
        source_filename,
        target_table_name,
        python_module_path,
        transform_args,
        start_date,
        is_multi_table,
        max_parallel_tasks,
        sql_connection_id,
        sql_host,
        sql_port,
        sql_database_name,
        sql_user,
        sql_password
    FROM dag_configurations
    WHERE is_active = 1 
    ORDER BY id;
    """
    
    log.info("DEBUG: Executando query no MySQL para buscar configurações ativas...")
    log.debug(f"DEBUG: Query SQL:\n{sql_query}")
    
    hook = MySqlHook(mysql_conn_id=mysql_conn_id)
    records = hook.get_records(sql=sql_query)
    
    log.info(f"DEBUG: Retornadas {len(records)} configurações do MySQL")
    for idx, rec in enumerate(records):
        log.debug(f"DEBUG: Registro {idx}: {rec}")
    
    return records

def fetch_selected_tables(mysql_conn_id: str, dag_config_id: int) -> List[str]:
    """
    Busca tabelas selecionadas para uma DAG multi-table.
    """
    sql_query = f"""
    SELECT table_name
    FROM dag_table_selections
    WHERE id_dag_config = {dag_config_id}
    AND is_selected = 1
    ORDER BY table_name;
    """
    
    hook = MySqlHook(mysql_conn_id=mysql_conn_id)
    records = hook.get_records(sql=sql_query)
    
    return [rec[0] for rec in records] if records else []


def list_files_from_minio_folder(folder_path: str, bucket_name: str = 'lab01') -> List[str]:
    """
    Lista todos os arquivos dentro de uma pasta no MinIO.
    
    Args:
        folder_path: Caminho da pasta (ex: 'raw/pipe-albuns/')
        bucket_name: Nome do bucket MinIO (default: 'lab01')
        
    Returns:
        Lista de caminhos completos dos arquivos (ex: ['raw/pipe-albuns/Track.json', ...])
    """
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        
        hook = S3Hook(aws_conn_id='minio_conn')
        
        # Remove a barra final se existir para o prefixo
        prefix = folder_path.rstrip('/') + '/'
        
        log.info(f"[MINIO] Listando arquivos em s3://{bucket_name}/{prefix}")
        
        # Lista todos os objetos com o prefixo
        keys = hook.list_keys(bucket_name=bucket_name, prefix=prefix)
        
        if not keys:
            log.warning(f"[MINIO] Nenhum arquivo encontrado em {prefix}")
            return []
        
        # Filtra apenas arquivos (não subpastas vazias)
        files = [key for key in keys if not key.endswith('/')]
        
        log.info(f"[MINIO] {len(files)} arquivo(s) encontrado(s): {files}")
        return files
        
    except Exception as e:
        log.error(f"[MINIO] Erro ao listar arquivos em {folder_path}: {e}")
        return []


# ----------------------------------------------------------------------
# 3. FUNÇÃO PRINCIPAL DE CRIAÇÃO DA DAG (COM CORREÇÃO DE MAX_ACTIVE_RUNS)
# ----------------------------------------------------------------------

def create_dynamic_dag(dag_config: Dict[str, Any]) -> DAG:
    """
    Cria e retorna um objeto Airflow DAG com base nos dados de configuração e define a sequência de tarefas.
    """
    
    dag_id = dag_config['dag_id']
    dag_metadata = dag_config['dag_metadata']
    task_config = dag_config['task_config']
    
    log.info(f"DEBUG: Criando DAG '{dag_id}'")
    log.debug(f"DEBUG: dag_metadata = {dag_metadata}")
    log.debug(f"DEBUG: task_config = {task_config}")
    
    # 1. Preparar Argumentos da DAG e Start Date
    dag_args = DEFAULT_ARGS.copy()
    
    start_date_db = dag_metadata.get('start_date')
    
    # Define a data de início (effective_start_date)
    if isinstance(start_date_db, date):
        # Converte datetime.date para datetime.datetime (hora 00:00:00)
        # Nota: Airflow prefere datetime com timezone (tz), mas datetime.combine funciona para o propósito.
        effective_start_date = datetime.combine(start_date_db, datetime.min.time())
        log.debug(f"DEBUG: start_date do DB convertido: {effective_start_date}")
    else:
        # Usa o start_date padrão (days_ago(1)) se o DB retornar None
        effective_start_date = DEFAULT_ARGS['start_date']
        log.debug(f"DEBUG: start_date do DB é None, usando padrão: {effective_start_date}")
        
    dag_args['owner'] = dag_metadata.get('owner') or DEFAULT_ARGS['owner']
    
    # 2. Criar Objeto DAG
    
    # 📢 CORREÇÃO CRÍTICA: Força max_active_runs a ser um inteiro >= 1
    max_runs_from_metadata = dag_metadata.get('max_active_runs')
    # Se for None, não for um inteiro, ou for < 1, assume 1.
    effective_max_runs = int(max_runs_from_metadata) if isinstance(max_runs_from_metadata, int) and max_runs_from_metadata >= 1 else 1 
    
    dag = DAG(
        dag_id=dag_id,
        schedule_interval=dag_metadata.get('schedule_interval'),
        start_date=effective_start_date, # Passa a data calculada
        catchup=False,
        default_args=dag_args,
        tags=dag_metadata.get('tags'),
        max_active_runs=effective_max_runs, # Aplica a correção
        doc_md=dag_metadata.get('description', f"DAG gerada dinamicamente a partir dos metadados da tabela dag_configurations (ID: {dag_id})")
    )

    # 3. Criar a Tarefa Principal (ETL/ELT - PythonOperator)
    python_module_path = task_config.get('python_module_path')
    transform_args = task_config.get('transform_args', {})
    source_filename = task_config.get('source_filename')

    # 🆕 DETECÇÃO DE MULTI-ARQUIVO: Se source_filename termina com '/', é uma pasta
    is_folder = source_filename and source_filename.endswith('/')
    
    if is_folder:
        log.info(f"[MULTI-FILE] Detectada pasta: {source_filename}")
        
        # Listar todos os arquivos da pasta no MinIO
        bucket_name = transform_args.get('bucket_name') or os.environ.get('MINIO_BUCKET', 'lab01')
        file_list = list_files_from_minio_folder(source_filename, bucket_name)
        
        if not file_list:
            log.warning(f"[MULTI-FILE] Nenhum arquivo encontrado em {source_filename}, criando DAG vazia")
            task_etl = BashOperator(
                task_id='no_files_found',
                bash_command=f"echo '⚠️ Nenhum arquivo encontrado em {source_filename}'",
                dag=dag
            )
            return dag
        
        # Criar uma task para cada arquivo
        tasks = []
        for file_path in file_list:
            # Extrair nome do arquivo sem extensão para o task_id
            file_basename = os.path.basename(file_path)
            file_name_no_ext = os.path.splitext(file_basename)[0]
            task_id = f"process_{file_name_no_ext}"
            
            try:
                callable_function = import_callable_from_path(python_module_path)
            except (ImportError, AttributeError, ValueError) as e:
                raise AirflowException(f"❌ Erro ao importar callable '{python_module_path}': {e}")
            
            op_kwargs_dict = {
                'source_filename': file_path,  # Caminho completo do arquivo individual
                'target_table_name': file_name_no_ext,  # Nome da tabela baseado no arquivo
                'dag_id': dag_id,  # ID da DAG para organizar camadas
                'owner': dag_metadata.get('owner', 'airflow'),
                'bucket_name': bucket_name,  # Passa o bucket correto
                **transform_args
            }
            
            task = PythonOperator(
                task_id=task_id,
                python_callable=callable_function,
                op_kwargs=op_kwargs_dict,
                dag=dag,
            )
            tasks.append(task)
        
        log.info(f"[MULTI-FILE] Criadas {len(tasks)} tasks para processar arquivos individuais")
        return dag
    
    # Comportamento ORIGINAL para arquivo único

    if not python_module_path:
        # Se não houver módulo Python, cria uma tarefa dummy/log
        task_etl = BashOperator(
            task_id='no_module_configured',
            bash_command=f"echo '⚠️ Alerta: Nenhuma função Python configurada para a DAG {dag_id}'",
            dag=dag
        )
    else:
        try:
            callable_function = import_callable_from_path(python_module_path)
        except (ImportError, AttributeError, ValueError) as e:
            raise AirflowException(f"❌ Erro ao importar callable '{python_module_path}' para a DAG {dag_id}: {e}")

        op_kwargs_dict = {
            'source_filename': task_config.get('source_filename'),
            'target_table_name': task_config.get('target_table_name'),
            'dag_id': dag_id,  # ID da DAG para organizar camadas
            'owner': dag_metadata.get('owner', 'airflow'),
            **transform_args
        }
        
        task_id_name = f"etl_process_for_{task_config.get('target_table_name', 'data')}"
        log.debug(f"DEBUG: Criando PythonOperator '{task_id_name}' com op_kwargs: {op_kwargs_dict}")
        
        task_etl = PythonOperator(
            task_id=task_id_name,
            python_callable=callable_function,
            op_kwargs=op_kwargs_dict,
            dag=dag,
        )
    
    # 4. Criar Tarefa de Validação
    
    target_name = task_config.get('target_table_name', 'data')
    source_filename = task_config.get('source_filename')

    def validate_processed_file(**context):
        """Valida se o arquivo Silver (Parquet) existe no MinIO usando o resultado da pipeline."""
        import os
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        
        bucket = os.environ.get('MINIO_BUCKET', 'lab01')
        hook = S3Hook(aws_conn_id='minio_conn')
        
        # Buscar o resultado retornado pela task anterior via XCom
        ti = context['ti']
        task_id_name = f"etl_process_for_{target_name}"
        pipeline_result = ti.xcom_pull(task_ids=task_id_name)
        
        # Se a pipeline retornou o caminho do silver, usar isso
        if pipeline_result and isinstance(pipeline_result, dict):
            silver_key = pipeline_result.get('silver')
            log.info(f"📦 Usando caminho Silver retornado pela pipeline: {silver_key}")
        else:
            # Fallback: tentar adivinhar o caminho (comportamento antigo)
            basename = os.path.basename(source_filename) if source_filename else f"{target_name}.csv"
            basename_no_ext = os.path.splitext(basename)[0]
            silver_key = f"silver/{target_name}/{basename_no_ext}.parquet"
            log.warning(f"⚠️ Pipeline não retornou resultado, usando fallback: {silver_key}")
        
        log.info(f"Validando existência de: s3://{bucket}/{silver_key}")
        
        try:
            if hook.check_for_key(silver_key, bucket_name=bucket):
                log.info(f"✅ Arquivo Silver encontrado: {silver_key}")
                return True
            else:
                log.warning(f"❌ Arquivo Silver não encontrado: {silver_key}")
                return False
        except Exception as e:
            log.error(f"❌ Erro ao validar arquivo: {e}")
            raise

    task_validation = PythonOperator(
        task_id='validate_data_integrity',
        python_callable=validate_processed_file,
        provide_context=True,
        dag=dag,
    )

    # TAREFAS DE FINALIZAÇÃO: caminhos separados para sucesso e falha (permite tratamento distinto)
    # task_cleanup_notify = BashOperator(
    #     task_id='cleanup_and_notify',
    #     bash_command=f"""
    #         echo 'Processo ETL para DAG {dag_id} concluído com sucesso. Limpeza e Notificação.'
    #         /usr/local/bin/scripts/send_notification.sh --status success --dag {dag_id}
    #     """,
    #     dag=dag,
    # )

    # task_handle_validation_failed = BashOperator(
    #    task_id='handle_validation_failed',
    #     bash_command=f"""
    #         echo 'Validação falhou para DAG {dag_id}. Executando rotina de falha.'
    #         /usr/local/bin/scripts/send_notification.sh --status failed --dag {dag_id}
    #     """,
    #     dag=dag,
    # )

    # 5. Definir a Sequência de Tarefas (ETL >> Validação >> (success|failure))
    task_etl >> task_validation
    # task_validation >> task_cleanup_notify
    # task_validation >> task_handle_validation_failed
    
    return dag


# ----------------------------------------------------------------------
# 3B. FUNÇÃO PARA CRIAR DAG MULTI-TABLE COM DYNAMIC TASK MAPPING
# ----------------------------------------------------------------------

def create_multi_table_dag(dag_config: Dict[str, Any]) -> DAG:
    """
    Cria uma DAG que processa múltiplas tabelas em paralelo usando Dynamic Task Mapping.
    Disponível a partir do Airflow 2.3+.
    """
    dag_id = dag_config['dag_id']
    dag_metadata = dag_config['dag_metadata']
    task_config = dag_config['task_config']
    config_id = dag_config.get('id')
    
    log.info(f"[MULTI-TABLE] Criando DAG multi-table: {dag_id}")
    
    # Preparar argumentos
    dag_args = DEFAULT_ARGS.copy()
    start_date_db = dag_metadata.get('start_date')
    
    if isinstance(start_date_db, date):
        effective_start_date = datetime.combine(start_date_db, datetime.min.time())
    else:
        effective_start_date = DEFAULT_ARGS['start_date']
    
    dag_args['owner'] = dag_metadata.get('owner') or DEFAULT_ARGS['owner']
    
    # Criar DAG
    max_parallel = task_config.get('max_parallel_tasks', 16)
    
    dag = DAG(
        dag_id=dag_id,
        schedule_interval=dag_metadata.get('schedule_interval'),
        start_date=effective_start_date,
        catchup=False,
        default_args=dag_args,
        tags=dag_metadata.get('tags', []) + ['multi-table'],
        max_active_runs=1,  # Evita overlap de execuções
        max_active_tasks=max_parallel,  # Controla paralelismo
        doc_md=f"DAG Multi-Table: {dag_metadata.get('description', '')}. Processa múltiplas tabelas em paralelo."
    )
    
    # Task para buscar lista de tabelas selecionadas
    @task(task_id='get_selected_tables', dag=dag)
    def get_tables_to_process(**context):
        """Busca tabelas selecionadas do banco de dados."""
        tables = fetch_selected_tables(MYSQL_CONN_ID, config_id)
        log.info(f"[MULTI-TABLE] Encontradas {len(tables)} tabelas para processar: {tables}")
        return tables
    
    # Task para processar uma tabela individual
    @task(task_id='process_table', dag=dag)
    def process_single_table(table_name: str, **context):
        """Processa uma única tabela através do pipeline Medallion."""
        log.info(f"[MULTI-TABLE] Processando tabela: {table_name}")
        
        python_module_path = task_config.get('python_module_path')
        transform_args = task_config.get('transform_args', {})
        
        if not python_module_path:
            log.warning(f"Nenhuma função Python configurada para {table_name}")
            return {'status': 'skipped', 'table': table_name}
        
        try:
            callable_function = import_callable_from_path(python_module_path)
            
            # Preparar kwargs com table_name específica
            op_kwargs = {
                'source_filename': task_config.get('source_filename'),
                'target_table_name': table_name,
                'table_name': table_name,  # Importante para mysql_to_medallion
                'owner': dag_metadata.get('owner', 'airflow'),
                **transform_args
            }
            
            # Executar pipeline para esta tabela
            result = callable_function(**op_kwargs, **context)
            
            log.info(f"[MULTI-TABLE] ✅ Tabela {table_name} processada com sucesso")
            return {'status': 'success', 'table': table_name, 'result': result}
            
        except Exception as e:
            log.error(f"[MULTI-TABLE] ❌ Erro ao processar {table_name}: {e}")
            raise
    
    # Dynamic Task Mapping: cria 1 task por tabela
    tables_list = get_tables_to_process()
    process_single_table.expand(table_name=tables_list)
    
    return dag


# ----------------------------------------------------------------------
# 4. GERAÇÃO DINÂMICA DE DAGS
# ----------------------------------------------------------------------

try:
    log.info(f"Buscando configurações ativas na tabela 'dag_configurations' via conexão '{MYSQL_CONN_ID}'...")
    dag_records = fetch_dag_configurations(MYSQL_CONN_ID)
    log.info(f"✅ {len(dag_records)} configurações ativas encontradas.")
    
    for record in dag_records:
        
        config_name = None 
        
        try:
            # Desempacotamento incluindo is_multi_table, max_parallel_tasks e campos SQL
            id, dag_id_value, schedule_interval, owner, description, source_filename, \
            target_table_name, python_module_path, transform_args, start_date_db, \
            is_multi_table, max_parallel_tasks, sql_connection_id, sql_host, sql_port, \
            sql_database_name, sql_user, sql_password = record
            
            log.debug(f"DEBUG: Processando registro ID={id}, dag_id={dag_id_value}, multi_table={is_multi_table}")
            
            # 1. Constrói o nome da DAG
            config_name = f"{dag_id_value.strip()}{id}"
            
            # 2. Converte e prepara a configuração
            parsed_transform_args = json.loads(transform_args) if transform_args else {}
            
            # 3. Adiciona campos SQL dedicados ao transform_args (para retrocompatibilidade)
            if sql_connection_id:
                parsed_transform_args['mysql_conn_id'] = sql_connection_id
            if sql_host:
                parsed_transform_args['sql_host'] = sql_host
            if sql_port:
                parsed_transform_args['sql_port'] = sql_port
            if sql_database_name:
                parsed_transform_args['database_name'] = sql_database_name
            if sql_user:
                parsed_transform_args['sql_user'] = sql_user
            if sql_password:
                parsed_transform_args['sql_password'] = sql_password
            
            dag_config = {
                'id': id,
                'dag_id': config_name,
                'dag_metadata': {
                    'schedule_interval': schedule_interval,
                    'owner': owner,
                    'description': description,
                    'start_date': start_date_db, 
                    'max_active_runs': None
                },
                'task_config': {
                    'source_filename': source_filename,
                    'target_table_name': target_table_name,
                    'python_module_path': python_module_path,
                    'transform_args': parsed_transform_args,
                    'max_parallel_tasks': max_parallel_tasks or 16
                }
            }
            
            # 3. Escolhe a função de criação baseado em is_multi_table
            if is_multi_table:
                log.info(f"[MULTI-TABLE] Criando DAG multi-table: {config_name}")
                dag_object = create_multi_table_dag(dag_config)
            else:
                dag_object = create_dynamic_dag(dag_config)
            
            dag_object.fileloc = DAG_FILE_PATH 
            globals()[f"dag_{config_name}"] = dag_object
            
            log.info(f"✅ DAG '{config_name}' carregada com sucesso (multi_table={is_multi_table}).")

        except (ValueError, TypeError, json.JSONDecodeError) as e:
            log.warning(f"❌ Erro no registro do DB (ID: {record[0] if len(record) > 0 else 'N/A'}, DAG: {config_name}). Tipo de erro: {e.__class__.__name__}. Motivo: {e}")
            continue
        
        except AirflowException as e:
            log.error(f"❌ Erro de Módulo (DAG: {config_name}): {e}")
            continue

except Exception as e:
    log.critical(f"❌ Erro fatal ao buscar configurações da DAG no MySQL: {e}")