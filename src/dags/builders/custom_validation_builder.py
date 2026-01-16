"""
Exemplo de DAGBuilder com validações customizadas do usuário.

Demonstra como integrar validações criadas via interface web
sem expor a implementação da dag_factory.
"""

import logging
from typing import Callable, Dict, Any
from dag_builder_base import DAGBuilder

log = logging.getLogger(__name__)


class CustomValidationDAGBuilder(DAGBuilder):
    """
    DAGBuilder que carrega e executa validações customizadas do usuário.
    
    As validações são definidas via interface web (/validation-rules-editor)
    e carregadas automaticamente do MinIO.
    
    Exemplo de uso em factory_master.py:
        if builder_type == 'custom_validation':
            builder = CustomValidationDAGBuilder(dag_config)
            dag = builder.create_dag()
    """
    
    def customize_dag_definition(self, dag):
        """Adiciona tags indicando uso de validações customizadas."""
        dag.tags.append('custom-validations')
        dag.doc_md = f"""
        # DAG com Validações Customizadas
        
        Esta DAG utiliza regras de validação definidas pelo usuário via interface web.
        
        **Bucket:** {self.config.get('bucket_name', 'lab01')}
        **Tabela:** {self.config.get('table_name', 'N/A')}
        **Validações:** Carregadas dinamicamente do MinIO
        
        Para editar validações, acesse: `/validation-rules-editor`
        """
        return dag
    
    def customize_validation_task(self) -> Callable:
        """
        Retorna função de validação que carrega regras customizadas do usuário.
        
        Esta abordagem esconde completamente a implementação da dag_factory,
        expondo apenas uma interface simples de validação.
        """
        from lib.custom_validators import create_validation_task_func
        
        bucket = self.config.get('bucket_name', 'lab01')
        table_name = self.config.get('table_name')
        
        # A camada a validar depende do pipeline
        # Por padrão, validar a camada Silver (após transformações)
        layer = self.config.get('validation_layer', 'silver')
        
        log.info(f"[{self.__class__.__name__}] Configurando validações customizadas para {layer}/{table_name}")
        
        return create_validation_task_func(
            bucket=bucket,
            layer=layer,
            table=table_name
        )


class MultiLayerValidationDAGBuilder(DAGBuilder):
    """
    DAGBuilder que executa validações em múltiplas camadas (Bronze, Silver, Gold).
    
    Cria uma task de validação separada para cada camada.
    """
    
    def customize_task_dependencies(self, tasks: Dict[str, Any]) -> None:
        """
        Customiza dependências para adicionar validações em cada camada.
        
        Fluxo:
            Bronze → Validação Bronze → Silver → Validação Silver → Gold → Validação Gold
        """
        from airflow.operators.python import PythonOperator
        from lib.custom_validators import create_validation_task_func
        
        bucket = self.config.get('bucket_name', 'lab01')
        table_name = self.config.get('table_name')
        
        # Validação Bronze
        if 'bronze_task' in tasks:
            validate_bronze = PythonOperator(
                task_id='validate_bronze',
                python_callable=create_validation_task_func(bucket, 'bronze', table_name),
                provide_context=True,
                dag=self.dag
            )
            tasks['bronze_task'] >> validate_bronze
            tasks['validate_bronze'] = validate_bronze
            
            # Silver depende de validação Bronze
            if 'silver_task' in tasks:
                validate_bronze >> tasks['silver_task']
        
        # Validação Silver
        if 'silver_task' in tasks:
            validate_silver = PythonOperator(
                task_id='validate_silver',
                python_callable=create_validation_task_func(bucket, 'silver', table_name),
                provide_context=True,
                dag=self.dag
            )
            tasks['silver_task'] >> validate_silver
            tasks['validate_silver'] = validate_silver
            
            # Gold depende de validação Silver
            if 'gold_task' in tasks:
                validate_silver >> tasks['gold_task']
        
        # Validação Gold
        if 'gold_task' in tasks:
            validate_gold = PythonOperator(
                task_id='validate_gold',
                python_callable=create_validation_task_func(bucket, 'gold', table_name),
                provide_context=True,
                dag=self.dag
            )
            tasks['gold_task'] >> validate_gold
            tasks['validate_gold'] = validate_gold
            
            # Task de validação final depende de Gold
            if 'validation_task' in tasks:
                validate_gold >> tasks['validation_task']
        
        log.info(f"[{self.__class__.__name__}] Validações multi-camada configuradas")


# ─────────────────────────────────────────────────────────────────────────
# Registro no DAGBuilderRegistry
# ─────────────────────────────────────────────────────────────────────────

def register_custom_validation_builders():
    """
    Registra builders de validação customizada no registry.
    
    Adicione esta linha em factory_master.py:
        from builders.custom_validation_builder import register_custom_validation_builders
        register_custom_validation_builders()
    """
    try:
        from dag_builder_examples import DAGBuilderRegistry
        
        registry = DAGBuilderRegistry()
        registry.register('custom_validation', CustomValidationDAGBuilder)
        registry.register('multi_layer_validation', MultiLayerValidationDAGBuilder)
        
        log.info("[REGISTRY] Builders de validação customizada registrados")
        
    except Exception as e:
        log.warning(f"[REGISTRY] Não foi possível registrar builders: {e}")


# Auto-registro ao importar
register_custom_validation_builders()
