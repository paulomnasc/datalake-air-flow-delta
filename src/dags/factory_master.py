import os
import importlib
import json
import logging
from datetime import datetime, date
from typing import Dict, Any, List

# Airflow Core
from airflow.models.dag import DAG
from airflow.utils.dates import days_ago
from airflow.operators.python import PythonOperator
from airflow.exceptions import AirflowException

# ----------------------------------------------------------------------
# 0. CONFIGURAÇÃO DE CAMINHO E LOGGER
# ----------------------------------------------------------------------

# Define o caminho absoluto do arquivo factory_master.py (para registro do fileloc no DB de metadados)
DAG_FILE_PATH = os.path.abspath(__file__)

# Inicializa o logger para melhor rastreamento (além dos prints)
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

# ID da conexão MySQL configurada no Airflow UI/Secrets Backend
MYSQL_CONN_ID = 'mysql_dag_metadata' # 💡 ATENÇÃO: Configure esta conexão no Airflow!

# Argumentos padrão para todas as DAGs, a menos que sobrepostos pelos metadados
DEFAULT_ARGS = {
    'owner': 'airflow',
    'start_date': days_ago(1),
    'retries': 1,
}

# ----------------------------------------------------------------------
# 2. FUNÇÕES DE UTILIDADE E HOOKS
# ----------------------------------------------------------------------

def import_callable_from_path(module_path: str):
    """
    Importa e retorna uma função Python a partir de um caminho de módulo.
    Ex: 'lib.minio_tasks.transform_data_with_pandas'
    """
    if '.' not in module_path:
        raise ValueError(f"O caminho do módulo é inválido: {module_path}")

    # Divide o caminho em módulo e nome da função
    module_name, func_name = module_path.rsplit('.', 1)
    
    # Importa o módulo
    module = importlib.import_module(module_name)
    
    # Retorna a função
    return getattr(module, func_name)

def fetch_dag_configurations(mysql_conn_id: str) -> List[tuple]:
    """
    Conecta ao MySQL e busca as configurações ativas da DAG.
    Retorna 9 colunas na ordem da query.
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
        transform_args
    FROM dag_configurations
    WHERE is_active = 1
    ORDER BY id;
    """
    
    log.info("DEBUG: Executando query corrigida no MySQL...")
    
    hook = MySqlHook(mysql_conn_id=mysql_conn_id)
    records = hook.get_records(sql=sql_query)
    
    return records


# ----------------------------------------------------------------------
# 3. FUNÇÃO PRINCIPAL DE CRIAÇÃO DA DAG
# ----------------------------------------------------------------------

def create_dynamic_dag(dag_config: Dict[str, Any]) -> DAG:
    """
    Cria e retorna um objeto Airflow DAG com base nos dados de configuração.
    """
    
    dag_id = dag_config['dag_id']
    dag_metadata = dag_config['dag_metadata']
    task_config = dag_config['task_config']
    
    # 1. Preparar Argumentos da DAG
    dag_args = DEFAULT_ARGS.copy()
    
    start_date_db = dag_metadata.get('start_date')
    if isinstance(start_date_db, date):
        dag_args['start_date'] = datetime.combine(start_date_db, datetime.min.time())
    else:
        dag_args['start_date'] = DEFAULT_ARGS['start_date']
        
    dag_args['owner'] = dag_metadata.get('owner') or DEFAULT_ARGS['owner']
    
    # 2. Criar Objeto DAG
    dag = DAG(
        dag_id=dag_id,
        schedule_interval=dag_metadata.get('schedule_interval'),
        catchup=False,
        default_args=dag_args,
        tags=dag_metadata.get('tags'),
        max_active_runs=dag_metadata.get('max_active_runs'),
        doc_md=dag_metadata.get('description', f"DAG gerada dinamicamente a partir dos metadados da tabela dag_configurations (ID: {dag_id})")
    )

    # 3. Criar a Tarefa Principal (ETL/ELT)
    python_module_path = task_config.get('python_module_path')
    transform_args = task_config.get('transform_args', {})

    if not python_module_path:
        # Se não houver módulo Python, cria uma tarefa dummy/log
        from airflow.operators.bash import BashOperator
        task = BashOperator(
            task_id='no_module_configured',
            bash_command=f"echo '⚠️ Alerta: Nenhuma função Python configurada para a DAG {dag_id}'",
            dag=dag
        )
    else:
        try:
            # Chama a função utilitária para importar a função Python real
            callable_function = import_callable_from_path(python_module_path)
        except (ImportError, AttributeError, ValueError) as e:
            # Lança uma exceção Airflow para ser capturada pelo bloco try/except externo
            raise AirflowException(f"❌ Erro ao importar callable '{python_module_path}' para a DAG {dag_id}: {e}")

        # O PythonOperator executa a função Python real
        task = PythonOperator(
            task_id=f"etl_process_for_{task_config.get('target_table_name', 'data')}",
            python_callable=callable_function,
            op_kwargs={
                'source_type': task_config.get('source_type'),
                'source_filename': task_config.get('source_filename'),
                'target_table_name': task_config.get('target_table_name'),
                **transform_args
            },
            dag=dag,
        )
    
    return dag

# ----------------------------------------------------------------------
# 4. GERAÇÃO DINÂMICA DE DAGS (REFATORADO PARA ROBUSTEZ)
# ----------------------------------------------------------------------

try:
    log.info(f"Buscando configurações ativas na tabela 'dag_configurations' via conexão '{MYSQL_CONN_ID}'...")
    dag_records = fetch_dag_configurations(MYSQL_CONN_ID)
    log.info(f"✅ {len(dag_records)} configurações ativas encontradas.")
    
    for record in dag_records:
        
        config_name = None # Inicializa para o escopo do log de erro
        
        # 📢 TRY/EXCEPT ISOLADO para capturar falhas específicas de cada registro
        try:
            # Desempacotamento (9 variáveis - Crucial: deve corresponder à query)
            id, dag_id_value, schedule_interval, owner, description, source_filename, \
            target_table_name, python_module_path, transform_args = record
            
            # 1. Constrói o nome da DAG (Correção Lógica: Usa 'dag_id_value')
            config_name = f"{dag_id_value.strip()}{id}"
            
            # 2. Converte e prepara a configuração (Ponto de falha: JSON)
            parsed_transform_args = json.loads(transform_args) if transform_args else {}
            
            dag_config = {
                'dag_id': config_name,
                'dag_metadata': {
                    'schedule_interval': schedule_interval,
                    'owner': owner,
                    'description': description,
                },
                'task_config': {
                    'source_filename': source_filename,
                    'target_table_name': target_table_name,
                    'python_module_path': python_module_path,
                    'transform_args': parsed_transform_args,
                }
            }
            
            # 3. Cria e registra a DAG no escopo global
            dag_object = create_dynamic_dag(dag_config)
            
            # 4. Força o fileloc para garantir o registro correto no DB de metadados
            dag_object.fileloc = DAG_FILE_PATH 
            
            globals()[f"dag_{config_name}"] = dag_object
            
            # Log de sucesso SÓ é emitido após a criação bem-sucedida do objeto
            log.info(f"✅ DAG '{config_name}' carregada com sucesso.")

        except (ValueError, TypeError, json.JSONDecodeError) as e:
            # Captura erro de desempacotamento de tupla ou JSON inválido
            log.warning(f"❌ Erro de Parsing LÓGICO no registro do DB (ID: {record[0] if len(record) > 0 else 'N/A'}, DAG: {config_name}): {e}")
            continue
        
        except AirflowException as e:
            # Captura erro de importação de módulo (lançado em create_dynamic_dag)
            log.error(f"❌ Erro de Módulo (DAG: {config_name}): {e}")
            continue

except Exception as e:
    # Captura erro de conexão MySQL ou falha na query
    log.critical(f"❌ Erro fatal ao buscar configurações da DAG no MySQL: {e}")