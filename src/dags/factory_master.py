import os
import yaml
from airflow.models.dag import DAG
from datetime import datetime
from airflow.operators.bash import BashOperator
from airflow.utils.dates import days_ago

# ----------------------------------------------------------------------
# 1. CONFIGURAÇÃO DE CAMINHOS
# ----------------------------------------------------------------------

# O Airflow vê o volume mapeado como /opt/airflow/dags
# A pasta de configs está dentro de dags: /opt/airflow/dags/configs
CONFIGS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "configs")
DEFAULT_ARGS = {
    'owner': 'airflow',
    'start_date': days_ago(1),
    'retries': 1,
}

# ----------------------------------------------------------------------
# 2. FUNÇÃO PRINCIPAL DE CRIAÇÃO DA DAG
# ----------------------------------------------------------------------

def create_dynamic_dag(config_name: str, config_data: dict):
    """
    Cria e retorna um objeto Airflow DAG com base nos dados de configuração.
    """
    # Define o ID da DAG usando o nome do arquivo (sem extensão)
    dag_id = f"dynamic_{config_name}"
    
    # Extrai metadados da DAG (assumindo que o YAML tem uma seção 'dag_metadata')
    dag_metadata = config_data.get('dag_metadata', {})
    schedule_interval = dag_metadata.get('schedule_interval', None)
    
    # Cria o objeto DAG
    dag = DAG(
        dag_id=dag_id,
        default_args=DEFAULT_ARGS,
        schedule_interval=schedule_interval,
        catchup=False,
        tags=[config_data.get('type', 'dynamic'), dag_metadata.get('area', 'default')]
    )
    
    with dag:
        # A lógica de tarefas irá aqui. Por exemplo, uma tarefa Spark para processamento.
        
        # Exemplo de Tarefa 1: Processamento Spark (aqui você usaria o SparkSubmitOperator)
        spark_task = BashOperator(
            task_id='run_spark_processing',
            # Simula um comando de execução que utiliza o parâmetro do YAML
            bash_command=f"spark-submit --master spark://spark:7077 \
                /opt/spark-apps/process_data.py \
                --pipeline-name {config_data.get('pipeline_name', 'default_pipe')} \
                --source {config_data.get('source_system', 'S3')} \
                --table {config_data.get('target_table', 'unknown_table')}"
        )

        # Você pode adicionar mais tarefas aqui (sensor MinIO, etc.)
        
        # Define a ordem das tarefas se necessário (ex: T1 >> T2)
        # Por simplicidade, temos apenas uma task
        pass 
        
    return dag

# ----------------------------------------------------------------------
# 3. GERAÇÃO DINÂMICA DE DAGS
# ----------------------------------------------------------------------

# Itera sobre todos os arquivos YAML na pasta de configurações
for filename in os.listdir(CONFIGS_DIR):
    if filename.endswith((".yml", ".yaml")):
        config_name = os.path.splitext(filename)[0] # Nome do arquivo sem a extensão
        file_path = os.path.join(CONFIGS_DIR, filename)
        
        try:
            with open(file_path, 'r') as f:
                config_data = yaml.safe_load(f)

            # Verifica se o YAML foi carregado corretamente
            if config_data and isinstance(config_data, dict):
                
                # 📢 ATENÇÃO: É assim que o Airflow reconhece e registra a DAG!
                # A variável global precisa ter o nome 'dag' e ser definida no escopo global.
                # Aqui, estamos definindo uma variável global com um nome único (o ID da DAG)
                globals()[f"dag_{config_name}"] = create_dynamic_dag(config_name, config_data)
                print(f"✅ DAG '{config_name}' carregada com sucesso.")
                
            else:
                print(f"⚠️ Aviso: O arquivo YAML '{filename}' está vazio ou mal formatado.")

        except Exception as e:
            print(f"❌ Erro ao processar o arquivo '{filename}': {e}")


# Este arquivo não define DAGs explicitamente, apenas as gera dinamicamente!