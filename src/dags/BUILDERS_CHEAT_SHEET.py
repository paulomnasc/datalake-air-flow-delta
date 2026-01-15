#!/usr/bin/env python3
"""
QUICK REFERENCE: Cheat Sheet para Sistema de Builders

Referência rápida para implementações comuns.
"""

# ==============================================================================
# 1. CRIAR BUILDER MINIMAL (Cópia & Cole)
# ==============================================================================

"""
from dag_builder_base import DAGBuilder

class MeuBuilder(DAGBuilder):
    '''Meu builder customizado.'''
    
    def customize_silver_transformation(self):
        def minha_silver(**context):
            # Sua lógica aqui
            return resultado
        return minha_silver

# Registrar (em qualquer lugar):
from dag_builder_examples import DAGBuilderRegistry
registry = DAGBuilderRegistry()
registry.register_builder('meu-tipo', MeuBuilder)
"""

# ==============================================================================
# 2. HOOKS DISPONÍVEIS (Todos os 7)
# ==============================================================================

HOOKS = {
    '1. customize_dag_definition(dag)': {
        'modifica': 'Propriedades da DAG (tags, schedule, doc)',
        'retorna': 'DAG (modificada)',
        'exemplo': 'dag.tags.append("meu-tag"); return dag',
    },
    
    '2. customize_bronze_task()': {
        'modifica': 'Função de ingestão/Bronze',
        'retorna': 'Callable (função) ou None (usa padrão)',
        'exemplo': 'def minha_bronze(**ctx): return data; return minha_bronze',
    },
    
    '3. customize_silver_transformation()': {
        'modifica': 'Função de transformação/Silver',
        'retorna': 'Callable (função) ou None (usa padrão)',
        'exemplo': 'def minha_silver(**ctx): return transformed; return minha_silver',
    },
    
    '4. customize_gold_aggregation()': {
        'modifica': 'Função de agregação/Gold',
        'retorna': 'Callable (função) ou None (usa padrão)',
        'exemplo': 'def minha_gold(**ctx): return aggregated; return minha_gold',
    },
    
    '5. customize_validation_task()': {
        'modifica': 'Função de validação',
        'retorna': 'Callable (função) ou None (usa padrão)',
        'exemplo': 'def minha_validacao(**ctx): return True/False; return minha_validacao',
    },
    
    '6. customize_task_dependencies(tasks)': {
        'modifica': 'Fluxo/dependências entre tasks',
        'retorna': 'None (modifica tasks in-place)',
        'exemplo': 'tasks["bronze"] >> tasks["silver"] >> tasks["gold"]',
    },
    
    '7. customize_pipeline_function(path)': {
        'modifica': 'Decorator para função principal',
        'retorna': 'Callable (wrapper) ou None (usa original)',
        'exemplo': 'return decorator_wrapper(original_func)',
    },
}

# ==============================================================================
# 3. PADRÕES COMUNS
# ==============================================================================

# PADRÃO 1: Adicionar Logging Detalhado
PATTERN_LOGGING = '''
def customize_pipeline_function(self, path):
    from functools import wraps
    import time
    
    def logging_wrapper(func):
        @wraps(func)
        def wrapper(*args, **kwargs):
            import logging
            log = logging.getLogger(__name__)
            
            start = time.time()
            log.info(f"🚀 Iniciando: {path}")
            
            try:
                result = func(*args, **kwargs)
                elapsed = time.time() - start
                log.info(f"✅ Sucesso em {elapsed:.2f}s")
                return result
            except Exception as e:
                log.error(f"❌ Erro: {e}")
                raise
        
        return wrapper
    
    return logging_wrapper
'''

# PADRÃO 2: Mascarar Dados Sensíveis
PATTERN_MASKING = '''
def customize_silver_transformation(self):
    def masked_transform(**context):
        ti = context['ti']
        bronze_data = ti.xcom_pull(task_ids='bronze_task')
        
        # Mascarar CPF
        def mask_cpf(cpf):
            return f"XXX.XXX.XXX-{cpf[-2:]}"
        
        # Mascarar email
        def mask_email(email):
            local, domain = email.split("@")
            return f"{local[0]}***@{domain}"
        
        # Aplicar mascaramento
        masked = bronze_data.copy()
        masked['cpf'] = mask_cpf(masked.get('cpf', ''))
        masked['email'] = mask_email(masked.get('email', ''))
        
        return masked
    
    return masked_transform
'''

# PADRÃO 3: Validação com Erro Customizado
PATTERN_VALIDATION = '''
def customize_validation_task(self):
    def custom_validation(**context):
        ti = context['ti']
        
        # Verificar cada etapa
        checks = {
            'bronze': ti.xcom_pull(task_ids='bronze_task') is not None,
            'silver': ti.xcom_pull(task_ids='silver_task') is not None,
            'gold': ti.xcom_pull(task_ids='gold_task') is not None,
        }
        
        # Detalhar falhas
        failures = [k for k, v in checks.items() if not v]
        
        if failures:
            raise ValueError(f"Validação falhou: {failures}")
        
        return True
    
    return custom_validation
'''

# PADRÃO 4: Retry com Fallback
PATTERN_FALLBACK = '''
def customize_bronze_task(self):
    def resilient_bronze(**context):
        import logging
        log = logging.getLogger(__name__)
        
        try:
            # Tentar primária
            log.info("Tentando fonte primária...")
            return fetch_primary_data(**context)
        
        except Exception as e:
            # Fallback a secundária
            log.warning(f"Falha primária: {e}, tentando fallback...")
            try:
                return fetch_secondary_data(**context)
            except Exception as e2:
                log.error(f"Falha em ambas: {e2}")
                raise
    
    return resilient_bronze
'''

# PADRÃO 5: Processamento Paralelo de Silver
PATTERN_PARALLEL = '''
def customize_task_dependencies(self, tasks):
    bronze = tasks.get('bronze_task')
    silver = tasks.get('silver_task')
    gold = tasks.get('gold_task')
    
    # Todas as silvers em paralelo, depois gold
    # Ao invés de: bronze >> silver >> gold
    # Fazer: bronze >> [silver1, silver2, silver3] >> gold
    
    bronze >> silver >> gold
'''

# PADRÃO 6: Adicionar Métricas e Alertas
PATTERN_METRICS = '''
def customize_validation_task(self):
    def metricsed_validation(**context):
        ti = context['ti']
        
        # Coletar métricas
        bronze = ti.xcom_pull(task_ids='bronze_task')
        silver = ti.xcom_pull(task_ids='silver_task')
        gold = ti.xcom_pull(task_ids='gold_task')
        
        metrics = {
            'bronze_records': len(bronze.get('data', [])),
            'silver_records': len(silver.get('data', [])),
            'gold_records': len(gold.get('data', [])),
        }
        
        # Enviar para Prometheus/Datadog
        from datadog import initialize, api
        initialize(statsd_host="localhost", statsd_port=8125)
        
        for metric_name, value in metrics.items():
            statsd_client.gauge(f"pipeline.{metric_name}", value)
        
        return True
    
    return metricsed_validation
'''

# PADRÃO 7: DAG por Domínio com Regras de Negócio
PATTERN_DOMAIN = '''
class MeuDominioBuilder(DAGBuilder):
    """Builder específico para meu domínio."""
    
    def customize_dag_definition(self, dag):
        # Tags específicas
        dag.tags.extend(['meu-dominio', 'producao'])
        
        # Documentação específica
        dag.doc_md = """
        ## Pipeline de Meu Domínio
        
        Processa dados de meu_dominio com:
        - ✅ Validações específicas
        - ✅ Transformações de negócio
        - ✅ KPIs customizados
        """
        
        return dag
    
    def customize_silver_transformation(self):
        def dominio_silver(**context):
            # Regras de negócio específicas do domínio
            return aplicar_regras_negocio(context)
        return dominio_silver
    
    def customize_gold_aggregation(self):
        def dominio_gold(**context):
            # KPIs específicos do domínio
            return calcular_kpis(context)
        return dominio_gold
'''

# ==============================================================================
# 4. CHECKLIST DE IMPLEMENTAÇÃO
# ==============================================================================

CHECKLIST = '''
✅ ANTES DE COMEÇAR:
- [ ] Ler EXTENSIBILIDADE_FACTORY_MASTER.md
- [ ] Revisar dag_builder_examples.py (exemplos)
- [ ] Revisar builders/hr_builder.py (caso real)

✅ CRIAR SEU BUILDER:
- [ ] Criar arquivo builders/seu_dominio_builder.py
- [ ] Herdar de DAGBuilder
- [ ] Sobrescrever apenas hooks necessários
- [ ] Adicionar docstrings
- [ ] Adicionar logging (log.info, log.warning, etc)

✅ REGISTRAR:
- [ ] Registrar no DAGBuilderRegistry
- [ ] Testar localmente
- [ ] Adicionar coluna builder_type ao MySQL (se não existir)
- [ ] Inserir configuração com seu builder_type

✅ INTEGRAR NA FACTORY:
- [ ] Adicionar import do registry (1 linha)
- [ ] Modificar query SQL (1 linha)
- [ ] Adicionar lógica if/else (5 linhas)
- [ ] Testar com DAG de teste

✅ VALIDAR:
- [ ] DAG aparece no Airflow
- [ ] Tasks correm sem erro
- [ ] Customizações são aplicadas
- [ ] Logs mostram classe customizada sendo usada
'''

# ==============================================================================
# 5. TROUBLESHOOTING RÁPIDO
# ==============================================================================

TROUBLESHOOTING = {
    'Erro: "cannot import name"': [
        '❓ Problema: Import do builder falhando',
        '✅ Solução:',
        '  1. Verificar nome do arquivo',
        '  2. Verificar path do import',
        '  3. Verificar __init__.py existe',
    ],
    
    'DAG não aparece no Airflow': [
        '❓ Problema: DAG não registrada',
        '✅ Solução:',
        '  1. Verificar if globals()[dag_id] = dag foi executado',
        '  2. Verificar log da factory_master.py',
        '  3. Verificar se dag_id está correto',
    ],
    
    'Hook não é chamado': [
        '❓ Problema: Método sobrescrito mas não executado',
        '✅ Solução:',
        '  1. Verificar nome exato do método',
        '  2. Verificar assinatura (parâmetros)',
        '  3. Verificar se herda de DAGBuilder (não DefaultDAGBuilder)',
    ],
    
    'Erro em customize_silver_transformation': [
        '❓ Problema: Erro ao executar transformação',
        '✅ Solução:',
        '  1. Adicionar try/except e logging',
        '  2. Verificar tipo de retorno (deve ser Callable)',
        '  3. Testar função isoladamente',
    ],
}

# ==============================================================================
# 6. SNIPPETS ÚTEIS
# ==============================================================================

# Snippet 1: Pegar dados de task anterior
SNIPPET_XCOM = '''
def customize_silver_transformation(self):
    def my_silver(**context):
        ti = context['ti']
        bronze_data = ti.xcom_pull(task_ids='bronze_task')
        
        if not bronze_data:
            raise ValueError("Bronze não retornou dados")
        
        transformed = processar(bronze_data)
        return transformed
    
    return my_silver
'''

# Snippet 2: Logging detalhado
SNIPPET_LOGGING = '''
import logging
log = logging.getLogger(__name__)

def customize_silver_transformation(self):
    def my_silver(**context):
        log.info("🔄 Iniciando transformação")
        
        try:
            log.debug("Debug info aqui")
            result = processar()
            log.info(f"✅ Processado {len(result)} registros")
            return result
        
        except Exception as e:
            log.error(f"❌ Erro: {e}", exc_info=True)
            raise
    
    return my_silver
'''

# Snippet 3: Validar dados
SNIPPET_VALIDATION = '''
def customize_validation_task(self):
    def my_validation(**context):
        ti = context['ti']
        
        # Checklist de validação
        validations = {
            'bronze_exists': ti.xcom_pull(task_ids='bronze_task') is not None,
            'silver_exists': ti.xcom_pull(task_ids='silver_task') is not None,
            'gold_exists': ti.xcom_pull(task_ids='gold_task') is not None,
        }
        
        # Detalhar resultado
        for name, status in validations.items():
            symbol = "✅" if status else "❌"
            print(f"{symbol} {name}")
        
        if not all(validations.values()):
            raise ValueError(f"Validação falhou: {validations}")
        
        return True
    
    return my_validation
'''

# ==============================================================================
# 7. ROADMAP (Próximas Adições)
# ==============================================================================

ROADMAP = '''
🔄 PLANEJADO:
- [ ] DAGBuilderRegistry persistente (BD)
- [ ] Interface Web para criar builders
- [ ] Templates pré-prontos (CSV, JSON, API)
- [ ] Testes automáticos de builders
- [ ] Versionamento de builders
- [ ] Reuso de componentes comuns
'''

# ==============================================================================
# MAIN: Print tudo
# ==============================================================================

if __name__ == '__main__':
    print("\n" + "="*80)
    print("QUICK REFERENCE: CHEAT SHEET DO SISTEMA DE BUILDERS")
    print("="*80 + "\n")
    
    # 1. Hooks
    print("📋 HOOKS DISPONÍVEIS:\n")
    for hook_name, details in HOOKS.items():
        print(f"  {hook_name}")
        for key, value in details.items():
            print(f"    • {key}: {value}")
        print()
    
    # 2. Padrões
    print("\n" + "="*80)
    print("💡 PADRÕES COMUNS:\n")
    print("  1. Logging detalhado")
    print("  2. Mascaramento de dados sensíveis")
    print("  3. Validação com erro customizado")
    print("  4. Retry com fallback")
    print("  5. Processamento paralelo")
    print("  6. Métricas e alertas")
    print("  7. DAG específica por domínio")
    
    # 3. Checklist
    print("\n" + "="*80)
    print("✅ CHECKLIST DE IMPLEMENTAÇÃO:\n")
    print(CHECKLIST)
    
    # 4. Troubleshooting
    print("\n" + "="*80)
    print("🔧 TROUBLESHOOTING:\n")
    for problem, solutions in TROUBLESHOOTING.items():
        print(f"  {problem}")
        for solution in solutions:
            print(f"    {solution}")
        print()
    
    print("="*80)
    print("📚 Para mais informações, consulte:")
    print("   - EXTENSIBILIDADE_FACTORY_MASTER.md")
    print("   - dag_builder_examples.py")
    print("   - builders/hr_builder.py")
    print("="*80 + "\n")
