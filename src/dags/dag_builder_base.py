"""
DAG Builder Base - Sistema de Extensibilidade para Factory Master

Este módulo fornece classes base que permitem customizar comportamentos
da pipeline Medallion sem modificar o código principal da factory_master.py.

Padrão: Herança + Template Method + Strategy Pattern
"""

import os
import json
import logging
from abc import ABC, abstractmethod
from datetime import datetime, date
from typing import Dict, Any, List, Optional, Callable

from airflow.models.dag import DAG
from airflow.operators.python import PythonOperator
from airflow.operators.bash import BashOperator
from airflow.utils.dates import days_ago

log = logging.getLogger(__name__)


# ==============================================================================
# 1. INTERFACE BASE PARA CUSTOMIZAÇÃO (Strategy Pattern)
# ==============================================================================

class BronzeStrategy(ABC):
    """
    Define a estratégia para ingestão na camada Bronze.
    Subclasses implementam diferentes fontes (MinIO, MySQL, APIs, etc).
    """
    
    @abstractmethod
    def read_source(self, **kwargs) -> Any:
        """Lê dados da fonte configurada."""
        pass
    
    @abstractmethod
    def validate_source(self, **kwargs) -> bool:
        """Valida se a fonte está disponível e acessível."""
        pass


class SilverStrategy(ABC):
    """
    Define a estratégia para transformação na camada Silver.
    Subclasses implementam lógicas de limpeza, enriquecimento, etc.
    """
    
    @abstractmethod
    def transform(self, data: Any, **kwargs) -> Any:
        """Transforma os dados da Bronze em Silver."""
        pass
    
    @abstractmethod
    def get_schema(self, **kwargs) -> Dict[str, str]:
        """Retorna o schema esperado da Silver."""
        pass


class GoldStrategy(ABC):
    """
    Define a estratégia para agregação/analítica na camada Gold.
    Subclasses implementam diferentes modelos analíticos.
    """
    
    @abstractmethod
    def aggregate(self, data: Any, **kwargs) -> Any:
        """Agrega dados da Silver para Gold."""
        pass
    
    @abstractmethod
    def get_metrics(self, data: Any, **kwargs) -> Dict[str, float]:
        """Calcula métricas específicas do domínio."""
        pass


# ==============================================================================
# 2. CLASSE BASE PARA CONSTRUÇÃO DE DAGS (Template Method Pattern)
# ==============================================================================

class DAGBuilder(ABC):
    """
    Classe base que define o fluxo de construção de uma DAG Medallion.
    
    Subclasses implementam hooks específicos para customizar comportamentos
    sem quebrar a estrutura principal.
    
    Template Method Pattern: método create_dag() define a sequência,
    mas chama métodos abstratos que subclasses podem sobrescrever.
    """
    
    def __init__(self, dag_config: Dict[str, Any]):
        """
        Inicializa o builder com configuração da DAG.
        
        Args:
            dag_config: Dicionário com dag_id, dag_metadata, task_config
        """
        self.dag_config = dag_config
        self.dag_id = dag_config.get('dag_id')
        self.dag_metadata = dag_config.get('dag_metadata', {})
        self.task_config = dag_config.get('task_config', {})
        self.dag = None
        
        log.info(f"[DAGBuilder] Inicializado para DAG: {self.dag_id}")
    
    # ──────────────────────────────────────────────────────────────────────
    # MÉTODOS DE CUSTOMIZAÇÃO (Hooks para Subclasses)
    # ──────────────────────────────────────────────────────────────────────
    
    def customize_dag_definition(self, dag: DAG) -> DAG:
        """
        Hook: Personalizar propriedades da DAG ANTES das tasks serem criadas.
        
        Exemplo: adicionar tags customizadas, alterar schedule_interval, etc.
        
        Args:
            dag: Objeto DAG recém-criado
            
        Returns:
            DAG potencialmente modificada
        """
        return dag
    
    def customize_bronze_task(self) -> Callable:
        """
        Hook: Retornar função Python customizada para ingestão na Bronze.
        
        Se retornar None, usa a função padrão.
        Se retornar uma função, essa será usada como PythonOperator.
        
        Returns:
            Função Python callable ou None (usa padrão)
        """
        return None
    
    def customize_silver_transformation(self) -> Callable:
        """
        Hook: Retornar função Python customizada para transformação na Silver.
        
        Se retornar None, usa a função padrão.
        
        Returns:
            Função Python callable ou None (usa padrão)
        """
        return None
    
    def customize_gold_aggregation(self) -> Callable:
        """
        Hook: Retornar função Python customizada para agregação na Gold.
        
        Se retornar None, usa a função padrão.
        
        Returns:
            Função Python callable ou None (usa padrão)
        """
        return None
    
    def customize_validation_task(self) -> Callable:
        """
        Hook: Retornar função Python customizada para validação final.
        
        Se retornar None, usa a função padrão.
        
        Returns:
            Função Python callable ou None (usa padrão)
        """
        return None
    
    def customize_task_dependencies(self, tasks: Dict[str, Any]) -> None:
        """
        Hook: Modificar as dependências entre tasks APÓS criação.
        
        Permite reordenar, pular tasks, adicionar branches condicionais, etc.
        
        Args:
            tasks: Dicionário {task_id: task_object} com todas as tasks criadas
        """
        # Implementação padrão: etl >> validation
        if 'bronze_task' in tasks and 'validation_task' in tasks:
            tasks['bronze_task'] >> tasks['validation_task']
    
    def customize_pipeline_function(self, python_module_path: str) -> Optional[Callable]:
        """
        Hook: Interceptar e potencialmente envolver a função Python principal.
        
        Exemplo: adicionar logging, métricas, tratamento de erro customizado.
        
        Args:
            python_module_path: Caminho para a função original
            
        Returns:
            Função wrapper ou None (usa original)
        """
        return None
    
    def get_bronze_strategy(self) -> Optional[BronzeStrategy]:
        """
        Hook: Retornar estratégia customizada para Bronze.
        
        Se retornar None, usa a estratégia padrão.
        """
        return None
    
    def get_silver_strategy(self) -> Optional[SilverStrategy]:
        """
        Hook: Retornar estratégia customizada para Silver.
        
        Se retornar None, usa a estratégia padrão.
        """
        return None
    
    def get_gold_strategy(self) -> Optional[GoldStrategy]:
        """
        Hook: Retornar estratégia customizada para Gold.
        
        Se retornar None, usa a estratégia padrão.
        """
        return None
    
    # ──────────────────────────────────────────────────────────────────────
    # MÉTODO PRINCIPAL: TEMPLATE METHOD
    # ──────────────────────────────────────────────────────────────────────
    
    def create_dag(self) -> DAG:
        """
        Template Method: Define a sequência de criação da DAG.
        
        Subclasses sobrescrevem hooks para customizar passos específicos.
        """
        
        log.info(f"[{self.__class__.__name__}] Iniciando criação da DAG: {self.dag_id}")
        
        # 1. Criar objeto DAG base
        self.dag = self._create_dag_object()
        
        # 2. Hook: Customizar definição da DAG
        self.dag = self.customize_dag_definition(self.dag)
        
        # 3. Criar tasks
        tasks = {}
        
        # Task: Bronze (Ingestão)
        tasks['bronze_task'] = self._create_bronze_task()
        log.info(f"[{self.__class__.__name__}] Task Bronze criada: bronze_task")
        
        # Task: Silver (Transformação)
        tasks['silver_task'] = self._create_silver_task()
        log.info(f"[{self.__class__.__name__}] Task Silver criada: silver_task")
        
        # Task: Gold (Agregação)
        tasks['gold_task'] = self._create_gold_task()
        log.info(f"[{self.__class__.__name__}] Task Gold criada: gold_task")
        
        # Task: Validação
        tasks['validation_task'] = self._create_validation_task()
        log.info(f"[{self.__class__.__name__}] Task Validação criada: validation_task")
        
        # 4. Hook: Customizar dependências entre tasks
        self.customize_task_dependencies(tasks)
        log.info(f"[{self.__class__.__name__}] Dependências customizadas")
        
        log.info(f"[{self.__class__.__name__}] ✅ DAG '{self.dag_id}' criada com sucesso")
        
        return self.dag
    
    # ──────────────────────────────────────────────────────────────────────
    # MÉTODOS INTERNOS (Implementação Padrão - podem ser sobrescritos)
    # ──────────────────────────────────────────────────────────────────────
    
    def _create_dag_object(self) -> DAG:
        """
        Cria o objeto DAG base com configurações padrão.
        """
        from datetime import datetime, date
        
        dag_args = {
            'owner': self.dag_metadata.get('owner', 'airflow'),
            'start_date': days_ago(1),
            'retries': 1,
        }
        
        start_date_db = self.dag_metadata.get('start_date')
        if isinstance(start_date_db, date):
            effective_start_date = datetime.combine(start_date_db, datetime.min.time())
        else:
            effective_start_date = dag_args['start_date']
        
        dag = DAG(
            dag_id=self.dag_id,
            schedule_interval=self.dag_metadata.get('schedule_interval'),
            start_date=effective_start_date,
            catchup=False,
            default_args=dag_args,
            tags=self.dag_metadata.get('tags', []),
            max_active_runs=1,
            doc_md=self.dag_metadata.get('description', f"DAG: {self.dag_id}"),
        )
        
        return dag
    
    def _create_bronze_task(self):
        """Cria a task de ingestão (Bronze). Pode ser sobrescrita."""
        
        custom_func = self.customize_bronze_task()
        
        if custom_func:
            # Usar função customizada
            task = PythonOperator(
                task_id='bronze_task',
                python_callable=custom_func,
                op_kwargs=self._prepare_op_kwargs(),
                dag=self.dag,
            )
        else:
            # Usar função padrão
            python_module_path = self.task_config.get('python_module_path')
            
            if python_module_path:
                from factory_master import import_callable_from_path
                callable_func = import_callable_from_path(python_module_path)
                
                # Hook: Envolver a função se customização retornar wrapper
                wrapper = self.customize_pipeline_function(python_module_path)
                if wrapper:
                    callable_func = wrapper(callable_func)
                
                task = PythonOperator(
                    task_id='bronze_task',
                    python_callable=callable_func,
                    op_kwargs=self._prepare_op_kwargs(),
                    dag=self.dag,
                )
            else:
                # Fallback: BashOperator dummy
                task = BashOperator(
                    task_id='bronze_task',
                    bash_command="echo 'ℹ️ Nenhuma função Python configurada para Bronze'",
                    dag=self.dag,
                )
        
        return task
    
    def _create_silver_task(self):
        """Cria a task de transformação (Silver). Pode ser sobrescrita."""
        
        custom_func = self.customize_silver_transformation()
        
        if custom_func:
            task = PythonOperator(
                task_id='silver_task',
                python_callable=custom_func,
                op_kwargs=self._prepare_op_kwargs(),
                dag=self.dag,
            )
        else:
            # Task padrão que depende de Bronze
            def default_silver_transform(**context):
                log.info("🔄 Transformação padrão da Silver iniciada")
                ti = context['ti']
                bronze_result = ti.xcom_pull(task_ids='bronze_task')
                log.info(f"Silver recebeu resultado de Bronze: {type(bronze_result)}")
                return {'status': 'transformed', 'data': bronze_result}
            
            task = PythonOperator(
                task_id='silver_task',
                python_callable=default_silver_transform,
                provide_context=True,
                dag=self.dag,
            )
        
        return task
    
    def _create_gold_task(self):
        """Cria a task de agregação (Gold). Pode ser sobrescrita."""
        
        custom_func = self.customize_gold_aggregation()
        
        if custom_func:
            task = PythonOperator(
                task_id='gold_task',
                python_callable=custom_func,
                op_kwargs=self._prepare_op_kwargs(),
                dag=self.dag,
            )
        else:
            # Task padrão que depende de Silver
            def default_gold_aggregate(**context):
                log.info("📊 Agregação padrão da Gold iniciada")
                ti = context['ti']
                silver_result = ti.xcom_pull(task_ids='silver_task')
                log.info(f"Gold recebeu resultado de Silver: {type(silver_result)}")
                return {'status': 'aggregated', 'data': silver_result}
            
            task = PythonOperator(
                task_id='gold_task',
                python_callable=default_gold_aggregate,
                provide_context=True,
                dag=self.dag,
            )
        
        return task
    
    def _create_validation_task(self):
        """Cria a task de validação. Pode ser sobrescrita."""
        
        custom_func = self.customize_validation_task()
        
        if custom_func:
            task = PythonOperator(
                task_id='validation_task',
                python_callable=custom_func,
                provide_context=True,
                dag=self.dag,
            )
        else:
            # Validação padrão
            def default_validation(**context):
                log.info("✅ Validação padrão iniciada")
                ti = context['ti']
                # Validar presença de resultados
                bronze_ok = ti.xcom_pull(task_ids='bronze_task') is not None
                silver_ok = ti.xcom_pull(task_ids='silver_task') is not None
                gold_ok = ti.xcom_pull(task_ids='gold_task') is not None
                
                if bronze_ok and silver_ok and gold_ok:
                    log.info("✅ Todas as camadas processadas com sucesso")
                    return True
                else:
                    raise Exception("❌ Validação falhou: algumas camadas retornaram None")
            
            task = PythonOperator(
                task_id='validation_task',
                python_callable=default_validation,
                provide_context=True,
                dag=self.dag,
            )
        
        return task
    
    def _prepare_op_kwargs(self) -> Dict[str, Any]:
        """Prepara argumentos para PythonOperator."""
        
        bucket_name = self.task_config.get('transform_args', {}).get('bucket_name') \
            or self.dag_metadata.get('user_bucket') \
            or self.dag_metadata.get('owner') \
            or os.environ.get('MINIO_BUCKET', 'lab01')
        
        return {
            'source_filename': self.task_config.get('source_filename'),
            'target_table_name': self.task_config.get('target_table_name'),
            'dag_id': self.dag_id,
            'owner': self.dag_metadata.get('owner', 'airflow'),
            'bucket_name': bucket_name,
            **self.task_config.get('transform_args', {})
        }


# ==============================================================================
# 3. BUILDER PADRÃO (Sem Customizações)
# ==============================================================================

class DefaultDAGBuilder(DAGBuilder):
    """
    Implementação padrão que não customiza nada.
    Use como base quando herdar.
    """
    pass
