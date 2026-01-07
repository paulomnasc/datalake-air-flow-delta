"""
EXEMPLO: Implementações Customizadas de DAGBuilder

Este arquivo demonstra como outras pessoas podem criar suas próprias
implementações de DAGBuilder para customizar comportamentos específicos
SEM modificar a factory_master.py.

Padrões usados:
- Template Method (define a estrutura, subclasses customizam passos)
- Strategy Pattern (diferentes implementações de Bronze/Silver/Gold)
- Decorator Pattern (wrapping de funções para logging/métricas)
"""

import logging
from typing import Any, Callable, Dict, Optional
from functools import wraps

from dag_builder_base import (
    DAGBuilder, 
    DefaultDAGBuilder,
    BronzeStrategy,
    SilverStrategy,
    GoldStrategy,
)

log = logging.getLogger(__name__)


# ==============================================================================
# EXEMPLO 1: DAG com Logging e Monitoramento Customizado
# ==============================================================================

class MonitoredDAGBuilder(DAGBuilder):
    """
    DAG que adiciona logging detalhado e coleta de métricas.
    
    Implementa hooks para:
    - Wrapping de funções com logging
    - Coleta de métricas de execução
    - Validação customizada
    """
    
    def customize_pipeline_function(self, python_module_path: str) -> Optional[Callable]:
        """
        Envolve a função principal com logging e coleta de tempo de execução.
        """
        
        def monitoring_wrapper(original_func: Callable) -> Callable:
            """Wrapper que adiciona monitoramento à função original."""
            
            @wraps(original_func)
            def wrapper(*args, **kwargs) -> Any:
                import time
                
                start_time = time.time()
                task_id = kwargs.get('task_id', 'unknown')
                owner = kwargs.get('owner', 'unknown')
                
                log.info(f"📊 [MONITORING] Iniciando execução - Task: {task_id}, Owner: {owner}")
                
                try:
                    result = original_func(*args, **kwargs)
                    elapsed_time = time.time() - start_time
                    
                    log.info(f"✅ [MONITORING] Execução concluída - Tempo: {elapsed_time:.2f}s")
                    
                    # Retornar resultado + metadata
                    return {
                        'status': 'success',
                        'result': result,
                        'execution_time': elapsed_time,
                        'owner': owner,
                    }
                
                except Exception as e:
                    elapsed_time = time.time() - start_time
                    log.error(f"❌ [MONITORING] Execução falhou após {elapsed_time:.2f}s - {str(e)}")
                    raise
            
            return wrapper
        
        # Retornar a função wrapper (será usada como decorator)
        return monitoring_wrapper
    
    def customize_validation_task(self) -> Callable:
        """
        Validação customizada com registros detalhados.
        """
        
        def monitored_validation(**context) -> bool:
            """Validação com logging."""
            log.info("🔍 [VALIDATION] Iniciando validação customizada com monitoramento")
            
            ti = context['ti']
            
            # Verificar cada etapa
            layers = ['bronze_task', 'silver_task', 'gold_task']
            results = {}
            
            for layer in layers:
                try:
                    result = ti.xcom_pull(task_ids=layer)
                    results[layer] = result is not None
                    status = "✅" if results[layer] else "⚠️"
                    log.info(f"{status} [VALIDATION] {layer}: {'OK' if results[layer] else 'FAILED'}")
                except Exception as e:
                    results[layer] = False
                    log.error(f"❌ [VALIDATION] {layer}: ERROR - {e}")
            
            # Resumo
            all_ok = all(results.values())
            log.info(f"{'✅' if all_ok else '❌'} [VALIDATION] Resultado Final: {results}")
            
            return all_ok
        
        return monitored_validation


# ==============================================================================
# EXEMPLO 2: DAG com Enriquecimento de Dados (Silver customizada)
# ==============================================================================

class EnrichedSilverDAGBuilder(DAGBuilder):
    """
    DAG que implementa transformações avançadas na Silver:
    - Validação de schema
    - Deduplicação
    - Enriquecimento com dados externos
    - Data quality checks
    """
    
    def customize_silver_transformation(self) -> Callable:
        """
        Implementa transformação Silver com enriquecimento.
        """
        
        def enriched_silver_transform(**context) -> Dict[str, Any]:
            """Transformação Silver com múltiplos passos."""
            log.info("🔄 [SILVER] Iniciando transformação enriquecida")
            
            ti = context['ti']
            bronze_data = ti.xcom_pull(task_ids='bronze_task')
            
            if not bronze_data:
                raise ValueError("Bronze não retornou dados")
            
            # Passo 1: Validação de schema
            log.info("🔍 [SILVER] Passo 1: Validando schema")
            schema_valid = True  # Sua lógica aqui
            if not schema_valid:
                raise ValueError("Schema inválido")
            
            # Passo 2: Deduplicação
            log.info("🧹 [SILVER] Passo 2: Removendo duplicatas")
            deduplicated = bronze_data  # Sua lógica aqui
            
            # Passo 3: Enriquecimento com dados externos
            log.info("➕ [SILVER] Passo 3: Enriquecendo com dados externos")
            enriched = deduplicated  # Chamar APIs, consultar banco externo, etc.
            
            # Passo 4: Data quality checks
            log.info("✅ [SILVER] Passo 4: Executando data quality checks")
            quality_checks = {
                'null_values': 0,  # Sua lógica aqui
                'invalid_dates': 0,
                'duplicates': 0,
            }
            
            log.info(f"📊 [SILVER] Data Quality: {quality_checks}")
            
            return {
                'status': 'enriched',
                'data': enriched,
                'quality_checks': quality_checks,
            }
        
        return enriched_silver_transform
    
    def customize_validation_task(self) -> Callable:
        """
        Validação específica para dados enriquecidos.
        """
        
        def enrichment_validation(**context) -> bool:
            """Validar dados enriquecidos."""
            log.info("📋 [VALIDATION] Validando dados enriquecidos da Silver")
            
            ti = context['ti']
            silver_result = ti.xcom_pull(task_ids='silver_task')
            
            if not silver_result or silver_result.get('status') != 'enriched':
                raise ValueError("Silver não retornou dados enriquecidos")
            
            quality = silver_result.get('quality_checks', {})
            log.info(f"✅ [VALIDATION] Qualidade dos dados: {quality}")
            
            return True
        
        return enrichment_validation


# ==============================================================================
# EXEMPLO 3: DAG com Processamento Paralelo (customização de task flow)
# ==============================================================================

class ParallelProcessingDAGBuilder(DAGBuilder):
    """
    DAG que implementa múltiplos processadores Silver em paralelo.
    
    Bronze → [Silver1, Silver2, Silver3] → Gold
    """
    
    def customize_task_dependencies(self, tasks: Dict[str, Any]) -> None:
        """
        Modifica o fluxo padrão para permitir processamento paralelo de Silver.
        """
        log.info("⚙️ [PARALLEL] Customizando fluxo de tarefas para processamento paralelo")
        
        # Bronze primeiro
        bronze = tasks.get('bronze_task')
        
        # Múltiplas transformações Silver em paralelo
        silver = tasks.get('silver_task')
        gold = tasks.get('gold_task')
        validation = tasks.get('validation_task')
        
        if bronze and silver and gold:
            # Bronze → Silver → Gold → Validation
            bronze >> silver >> gold >> validation
            log.info("✅ [PARALLEL] Fluxo paralelo configurado: Bronze → Silver → Gold → Validation")


# ==============================================================================
# EXEMPLO 4: DAG com Fallback e Retry Customizado
# ==============================================================================

class ResilientDAGBuilder(DAGBuilder):
    """
    DAG que implementa estratégias de resiliência:
    - Retry automático com backoff exponencial
    - Fallback a dados em cache
    - Alertas customizados
    """
    
    def customize_dag_definition(self, dag) -> Any:
        """
        Adiciona configurações de resiliência à DAG.
        """
        log.info("🛡️ [RESILIENCE] Aplicando configurações de resiliência")
        
        # Aumentar retries
        dag.default_args['retries'] = 3
        dag.default_args['retry_delay'] = 300  # 5 minutos
        
        # Tags de resiliência
        dag.tags.append('resilient')
        dag.tags.append('critical')
        
        return dag
    
    def customize_bronze_task(self) -> Callable:
        """
        Bronze com fallback a dados em cache.
        """
        
        def resilient_bronze(**context) -> Dict[str, Any]:
            """Ingestão com fallback a cache."""
            log.info("🔄 [RESILIENCE] Ingestão com suporte a fallback")
            
            try:
                # Tentar ingestão normal
                log.info("📥 [RESILIENCE] Tentando ingestão primária")
                # Sua lógica de ingestão aqui
                data = {'source': 'primary'}
                
            except Exception as e:
                # Fallback a cache
                log.warning(f"⚠️ [RESILIENCE] Ingestão falhou, usando cache: {e}")
                data = {'source': 'cache', 'warning': str(e)}
            
            return data
        
        return resilient_bronze


# ==============================================================================
# EXEMPLO 5: DAG Específica por Domínio (Ex: E-commerce)
# ==============================================================================

class EcommerceSalesDAGBuilder(DAGBuilder):
    """
    DAG customizada para processamento de dados de vendas em e-commerce.
    
    Implementa regras de negócio específicas:
    - Cálculo de comissões
    - Detecção de fraude
    - Atualização de inventário
    """
    
    def customize_silver_transformation(self) -> Callable:
        """
        Transformação específica para vendas de e-commerce.
        """
        
        def ecommerce_silver_transform(**context) -> Dict[str, Any]:
            """Transformação com regras de negócio de e-commerce."""
            log.info("🛍️ [ECOMMERCE] Transformação de dados de vendas")
            
            ti = context['ti']
            bronze_sales = ti.xcom_pull(task_ids='bronze_task')
            
            # Regra 1: Cálculo de comissões
            log.info("💰 [ECOMMERCE] Calculando comissões")
            commission_rules = {
                'basic': 0.05,      # 5%
                'premium': 0.08,    # 8%
                'enterprise': 0.10, # 10%
            }
            
            # Regra 2: Validação de estoque
            log.info("📦 [ECOMMERCE] Validando disponibilidade de estoque")
            
            # Regra 3: Detecção de fraude
            log.info("🚨 [ECOMMERCE] Verificando fraude")
            fraud_detection_rules = ['high_value', 'unusual_pattern', 'high_chargeback_rate']
            
            return {
                'status': 'ecommerce_transformed',
                'sales_data': bronze_sales,
                'commission_rules': commission_rules,
                'fraud_flags': [],
            }
        
        return ecommerce_silver_transform
    
    def customize_gold_aggregation(self) -> Callable:
        """
        Agregação específica para analytics de e-commerce.
        """
        
        def ecommerce_gold_aggregate(**context) -> Dict[str, Any]:
            """Agregação com métricas de e-commerce."""
            log.info("📊 [ECOMMERCE] Agregando para dashboard de vendas")
            
            ti = context['ti']
            silver_data = ti.xcom_pull(task_ids='silver_task')
            
            # Métricas de vendas
            metrics = {
                'total_sales': 0,      # Sua lógica
                'average_order_value': 0,
                'conversion_rate': 0,
                'customer_lifetime_value': 0,
                'fraud_rate': 0,
            }
            
            log.info(f"📈 [ECOMMERCE] Métricas calculadas: {metrics}")
            
            return {
                'status': 'ecommerce_aggregated',
                'metrics': metrics,
            }
        
        return ecommerce_gold_aggregate


# ==============================================================================
# EXEMPLO 6: Registry de Builders Customizados
# ==============================================================================

class DAGBuilderRegistry:
    """
    Registry para mapear tipos de DAG para suas implementações customizadas.
    
    Uso:
        registry = DAGBuilderRegistry()
        builder_class = registry.get_builder('ecommerce')  # Retorna EcommerceSalesDAGBuilder
    """
    
    def __init__(self):
        """Inicializa registry com builders padrão."""
        self.builders = {
            'default': DefaultDAGBuilder,
            'monitored': MonitoredDAGBuilder,
            'enriched': EnrichedSilverDAGBuilder,
            'parallel': ParallelProcessingDAGBuilder,
            'resilient': ResilientDAGBuilder,
            'ecommerce': EcommerceSalesDAGBuilder,
        }
        log.info(f"📋 [REGISTRY] Inicializado com {len(self.builders)} builders")
    
    def register_builder(self, name: str, builder_class: type) -> None:
        """
        Registra um novo builder customizado.
        
        Exemplo:
            class MyCustomBuilder(DAGBuilder):
                pass
            
            registry.register_builder('my_custom', MyCustomBuilder)
        """
        self.builders[name] = builder_class
        log.info(f"✅ [REGISTRY] Builder registrado: {name}")
    
    def get_builder(self, builder_type: str, dag_config: Dict[str, Any]):
        """
        Retorna instância do builder apropriado.
        
        Args:
            builder_type: Nome do tipo de builder (ex: 'ecommerce')
            dag_config: Configuração da DAG
            
        Returns:
            Instância de DAGBuilder customizado
        """
        builder_class = self.builders.get(builder_type, DefaultDAGBuilder)
        log.info(f"🔧 [REGISTRY] Usando builder: {builder_class.__name__}")
        return builder_class(dag_config)
    
    def list_available_builders(self) -> list:
        """Lista todos os builders disponíveis."""
        return list(self.builders.keys())
