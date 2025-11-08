import os
import importlib
import json
from datetime import datetime, date
from typing import Dict, Any, List

# Airflow Core
from airflow.models.dag import DAG
from airflow.utils.dates import days_ago
from airflow.operators.python import PythonOperator
from airflow.exceptions import AirflowException

# factory_master.py (No topo, logo após os imports)

# Define o caminho absoluto do arquivo factory_master.py.
# Isso garante que o Airflow registre a DAG com o fileloc correto:
DAG_FILE_PATH = os.path.abspath(__file__)

# Airflow Provider para MySQL
# Certifique-se de que o pacote 'apache-airflow-providers-mysql' esteja instalado.
try:
    from airflow.providers.mysql.hooks.mysql import MySqlHook
except ImportError as e:
    print(f"FATAL AIRFLOW PARSING ERROR (MYSQL HOOK): Falha crítica: {e}")
    # Em ambientes Airflow mais antigos, pode ser necessário 'airflow.hooks.mysql_hook.MySqlHook'
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
    Ex: 'utils.transformations.raw.my_etl_function'
    """
    if '.' not in module_path:
        raise ValueError(f"O caminho do módulo é inválido: {module_path}")

    # Divide o caminho em módulo e nome da função
    module_name, func_name = module_path.rsplit('.', 1)
    
    # Importa o módulo (ex: 'utils.transformations.raw')
    module = importlib.import_module(module_name)
    
    # Retorna a função (ex: 'my_etl_function')
    return getattr(module, func_name)

def fetch_dag_configurations(mysql_conn_id: str) -> List[tuple]:
    """
    Conecta ao MySQL e busca as configurações ativas da DAG.
    """
    sql_query = f"""
    SELECT
        id,
        dag_id,  /* CORRIGIDO: Usa o nome da coluna real da sua tabela */
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
    
    # Adicionando o print de debug
    print("DEBUG: Executando query corrigida no MySQL...")
    
    hook = MySqlHook(mysql_conn_id=mysql_conn_id)
    # get_records retorna uma lista de tuplas [(col1_val, col2_val, ...), ...]
    # Alternativamente, hook.get_pandas_df(sql_query) poderia ser usado para um DataFrame
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
    
    # Cria uma cópia dos args padrão e sobrepõe com os metadados do DB
    dag_args = DEFAULT_ARGS.copy()
    
    # Conversão da data de início: garante que seja um datetime.datetime
    start_date_db = dag_metadata.get('start_date')
    if isinstance(start_date_db, date):
        # Converte datetime.date para datetime.datetime (hora 00:00:00)
        dag_args['start_date'] = datetime.combine(start_date_db, datetime.min.time())
    else:
        # Usa start_date padrão (days_ago(1)) se não houver no DB
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
        # Se não houver módulo Python, cria uma tarefa dummy/log para a DAG
        from airflow.operators.bash import BashOperator
        task = BashOperator(
            task_id='no_module_configured',
            bash_command=f"echo '⚠️ Alerta: Nenhuma função Python configurada para a DAG {dag_id}'",
            dag=dag
        )
    else:
        try:
            # 💡 Chama a função utilitária para importar a função Python real
            callable_function = import_callable_from_path(python_module_path)
        except (ImportError, AttributeError, ValueError) as e:
            print(f"FATAL AIRFLOW PARSING ERROR (MYSQL HOOK): Falha crítica: {e}")
            raise AirflowException(f"❌ Erro ao importar callable '{python_module_path}' para a DAG {dag_id}: {e}")

        # O PythonOperator executa a função Python real (a lógica de ETL)
        task = PythonOperator(
            task_id=f"etl_process_for_{task_config.get('target_table_name', 'data')}",
            python_callable=callable_function,
            op_kwargs={
                # Passa todos os parâmetros da task como argumentos de palavra-chave para a função
                'source_type': task_config.get('source_type'),
                'source_filename': task_config.get('source_filename'),
                'target_table_name': task_config.get('target_table_name'),
                **transform_args # Desempacota os argumentos JSON extras
            },
            dag=dag,
        )

    # Nota: Em um projeto real, você poderia ter várias tasks e definir dependências aqui.
    # Ex: [Task_Extrai >> Task_Transforma >> Task_Carrega]
    
    return dag

# ----------------------------------------------------------------------
# 4. GERAÇÃO DINÂMICA DE DAGS
# ----------------------------------------------------------------------

try:
  print(f"Buscando configurações ativas na tabela 'dag_configurations' via conexão '{MYSQL_CONN_ID}'...")
  dag_records = fetch_dag_configurations(MYSQL_CONN_ID)
  print(f"✅ {len(dag_records)} configurações ativas encontradas.")
  
  for record in dag_records:
    
    # 📢 AQUI ESTÁ A CORREÇÃO CRÍTICA:
    # Deve desempacotar exatamente 9 valores e usar o nome correto (dag_id)
    id, dag_id, schedule_interval, owner, description, source_filename, \
    target_table_name, python_module_path, transform_args = record
    
    # 1. Constrói o nome da DAG
    # Agora, 'dag_id' contém o valor 'ingestao_customers_raw'
    config_name = f"{dag_id.strip()}{id}"
    
    # 2. Converte e prepara a configuração
    try:
      parsed_transform_args = json.loads(transform_args) if transform_args else {}
    except Exception as e:
      print(f"❌ Erro ao parsear JSON 'transform_args' para DAG ID {id}: {e}")
      continue # Pula para o próximo registro
      
    dag_config = {
      'dag_id': config_name,
      'dag_metadata': {
        'schedule_interval': schedule_interval,
        'owner': owner,
        'description': description,
        # Os campos start_date, tags, max_active_runs, etc. estão ausentes,
        # então a DAG usará os valores do DEFAULT_ARGS (days_ago(1) e 'airflow').
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
    globals()[f"dag_{config_name}"] = dag_object
    print(f"✅ DAG '{config_name}' carregada com sucesso.")


    

except Exception as e:
    # Este print agora deve capturar o erro, se ele não for o de desempacotamento
  print(f"❌ Erro fatal ao buscar configurações da DAG no MySQL: {e}")
    # ... (restante do bloco)