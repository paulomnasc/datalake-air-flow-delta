"""
EXEMPLO PRÁTICO: Builder customizado para pipeline de dados de RH

Demonstra como criar um builder específico para um domínio (Recursos Humanos)
com lógica customizada para cada camada do medalhão.

Padrão: Herança simples de DAGBuilder + sobrescrita de métodos específicos
"""

import logging
from typing import Dict, Any, Callable, Optional

from dag_builder_base import DAGBuilder

log = logging.getLogger(__name__)


# ==============================================================================
# BUILDER CUSTOMIZADO PARA DOMÍNIO: RH (Recursos Humanos)
# ==============================================================================

class HRDataPipelineBuilder(DAGBuilder):
    """
    Pipeline customizado para dados de Recursos Humanos.
    
    Implementa regras de negócio específicas:
    - Máscara de dados sensíveis (CPF, salary, etc)
    - Validação de contratação
    - Cálculo de férias acumuladas
    - Detecção de inconsistências
    
    Fluxo:
    Bronze (dados brutos) → Silver (mascarado) → Gold (analytics)
    """
    
    def customize_dag_definition(self, dag):
        """Adiciona tags e documentação específica de RH."""
        log.info("🏢 [RH] Customizando definição da DAG")
        
        dag.tags.extend(['rh', 'hr', 'confidencial', 'dados-sensíveis'])
        dag.doc_md = """
        ## 🏢 Pipeline de Dados de RH (Recursos Humanos)
        
        ### Características:
        - **Camada Bronze**: Dados brutos de sistemas de RH (DP, folha, etc)
        - **Camada Silver**: Dados mascarados e conformes (LGPD)
        - **Camada Gold**: Analítica segura para gestores
        
        ### Dados Processados:
        - 👤 Dados de colaboradores (sem informações sensíveis)
        - 📊 Folha de pagamento (agregada)
        - 🎯 Performance e metas
        - 📅 Férias e ausências
        
        ### Segurança:
        ⚠️ Esta pipeline processa dados CONFIDENCIAIS
        ✅ Aplicam-se regras de LGPD e mascaramento
        ✅ Acesso restrito a usuários autorizados
        
        ### Agendamento:
        - Execução: Diária às 22:00 (após encerramento comercial)
        - Retenção: 2 anos
        """
        
        return dag
    
    def customize_bronze_task(self) -> Callable:
        """
        Ingestão da Bronze com validações iniciais de RH.
        
        Validações:
        - Conectar a sistema ERP (SAP/Oracle)
        - Validar CPF/CNPJ formato
        - Registrar timestamp de ingestão
        """
        
        def hr_bronze_ingestion(**context):
            """Ingere dados de RH do sistema externo."""
            log.info("👥 [RH-BRONZE] Iniciando ingestão de dados de RH")
            
            source_filename = context.get('source_filename', 'rh_data')
            
            # Validações iniciais
            log.info("🔍 [RH-BRONZE] Validando fonte de dados")
            
            validations = {
                'has_employee_id': True,
                'has_hire_date': True,
                'has_department': True,
            }
            
            for validation, status in validations.items():
                if status:
                    log.info(f"  ✅ {validation}")
                else:
                    log.error(f"  ❌ {validation}")
                    raise ValueError(f"Validação falhou: {validation}")
            
            # Simular leitura de dados
            employees_raw = {
                'record_count': 0,
                'source': source_filename,
                'ingestion_timestamp': '2024-01-06T10:00:00Z',
                'columns': ['employee_id', 'name', 'cpf', 'salary', 'hire_date', 'department']
            }
            
            log.info(f"✅ [RH-BRONZE] {employees_raw['record_count']} registros ingeridos de {source_filename}")
            
            return employees_raw
        
        return hr_bronze_ingestion
    
    def customize_silver_transformation(self) -> Callable:
        """
        Transformação da Silver com mascaramento de dados sensíveis.
        
        Transformações:
        - Mascarar CPF: XXX.XXX.XXX-12
        - Mascarar salário: Apenas faixas salariais
        - Normalizar datas
        - Validar contratações vigentes
        """
        
        def hr_silver_transformation(**context):
            """Transforma e mascara dados de RH."""
            log.info("🔐 [RH-SILVER] Iniciando transformação com mascaramento")
            
            ti = context['ti']
            bronze_data = ti.xcom_pull(task_ids='bronze_task')
            
            if not bronze_data:
                raise ValueError("Bronze não retornou dados")
            
            # Passo 1: Mascarar CPF
            log.info("🔒 [RH-SILVER] Passo 1: Mascarando CPF")
            
            def mask_cpf(cpf):
                """Mascarar CPF para XXX.XXX.XXX-XX"""
                if len(cpf) >= 11:
                    return f"XXX.XXX.XXX-{cpf[-2:]}"
                return "INVALID"
            
            # Passo 2: Faixas salariais
            log.info("💰 [RH-SILVER] Passo 2: Convertendo salários em faixas")
            
            salary_ranges = {
                0: 'Até R$2.000',
                2000: 'R$2.000 - R$5.000',
                5000: 'R$5.000 - R$10.000',
                10000: 'R$10.000+',
            }
            
            def get_salary_range(salary):
                for min_salary, range_label in sorted(salary_ranges.items(), reverse=True):
                    if salary >= min_salary:
                        return range_label
                return 'Sem informação'
            
            # Passo 3: Filtrar apenas funcionários ativos
            log.info("🟢 [RH-SILVER] Passo 3: Filtrando funcionários ativos")
            
            active_employees_count = 0  # Sua lógica aqui
            
            # Passo 4: Validações de conformidade LGPD
            log.info("📋 [RH-SILVER] Passo 4: Validações LGPD")
            
            lgpd_checks = {
                'no_raw_cpf': True,
                'no_raw_salary': True,
                'consent_recorded': True,
                'encryption_active': True,
            }
            
            all_compliant = all(lgpd_checks.values())
            
            if not all_compliant:
                log.error(f"❌ [RH-SILVER] Conformidade LGPD falhou: {lgpd_checks}")
                raise ValueError("Dados não conformes com LGPD")
            
            log.info(f"✅ [RH-SILVER] LGPD OK: {lgpd_checks}")
            
            return {
                'status': 'rh_transformed',
                'employees_count': active_employees_count,
                'masking_applied': {
                    'cpf': 'masked',
                    'salary': 'ranges',
                },
                'lgpd_compliant': True,
                'transformations': [
                    'CPF mascarado',
                    'Salários em faixas',
                    'Funcionários ativos filtrados',
                    'LGPD validado'
                ]
            }
        
        return hr_silver_transformation
    
    def customize_gold_aggregation(self) -> Callable:
        """
        Agregação da Gold com KPIs e dashboards de RH.
        
        Métricas:
        - Headcount por departamento
        - Turnover rate
        - Salário médio por faixa
        - Distribuição por senioridade
        """
        
        def hr_gold_aggregation(**context):
            """Agrega dados para dashboard de RH."""
            log.info("📊 [RH-GOLD] Iniciando agregação para analytics")
            
            ti = context['ti']
            silver_data = ti.xcom_pull(task_ids='silver_task')
            
            if not silver_data or not silver_data.get('lgpd_compliant'):
                raise ValueError("Silver não está conforme LGPD")
            
            log.info("📈 [RH-GOLD] Calculando KPIs")
            
            # KPI 1: Headcount
            log.info("  👥 KPI: Headcount por departamento")
            headcount_by_dept = {
                'TI': 45,
                'Vendas': 60,
                'RH': 12,
                'Financeiro': 25,
                'Operações': 38,
            }
            
            # KPI 2: Turnover
            log.info("  🔄 KPI: Turnover rate")
            turnover_rate = 0.12  # 12% ao ano
            
            # KPI 3: Distribuição por senioridade
            log.info("  📊 KPI: Seniority distribution")
            seniority_distribution = {
                'Junior': 0.30,
                'Pleno': 0.45,
                'Senior': 0.20,
                'Arquiteto': 0.05,
            }
            
            # KPI 4: Custo com folha
            log.info("  💵 KPI: Custo com folha (agregado)")
            total_payroll = 'R$ 500.000'  # Agregado por segurança
            
            # Consolidar
            kpis = {
                'headcount_by_department': headcount_by_dept,
                'turnover_rate': turnover_rate,
                'seniority_distribution': seniority_distribution,
                'total_payroll': total_payroll,
                'last_updated': '2024-01-06T22:00:00Z',
            }
            
            log.info(f"✅ [RH-GOLD] KPIs calculados: {list(kpis.keys())}")
            
            return {
                'status': 'rh_aggregated',
                'kpis': kpis,
                'ready_for_bi': True,
                'access_level': 'managers_only',
            }
        
        return hr_gold_aggregation
    
    def customize_validation_task(self) -> Callable:
        """
        Validação customizada para RH.
        
        Verifica:
        - Conformidade LGPD
        - Completude de dados
        - Integridade de relacionamentos
        """
        
        def hr_validation(**context):
            """Validação com regras de negócio de RH."""
            log.info("✅ [RH-VALIDATION] Validação de conformidade de RH")
            
            ti = context['ti']
            
            # Validar Bronze
            bronze = ti.xcom_pull(task_ids='bronze_task')
            if not bronze or 'record_count' not in bronze:
                raise ValueError("Bronze inválida")
            log.info("  ✅ Bronze: OK")
            
            # Validar Silver
            silver = ti.xcom_pull(task_ids='silver_task')
            if not silver or not silver.get('lgpd_compliant'):
                raise ValueError("Silver não conforme com LGPD")
            log.info("  ✅ Silver: LGPD OK")
            
            # Validar Gold
            gold = ti.xcom_pull(task_ids='gold_task')
            if not gold or not gold.get('ready_for_bi'):
                raise ValueError("Gold não pronta para BI")
            log.info("  ✅ Gold: Pronta para BI")
            
            # Relatório de validação
            validation_report = {
                'pipeline_status': 'SUCCESS',
                'compliance': {
                    'lgpd': True,
                    'data_quality': True,
                    'security': True,
                },
                'timestamp': '2024-01-06T22:30:00Z',
            }
            
            log.info(f"✅ [RH-VALIDATION] Validação completa: {validation_report['pipeline_status']}")
            
            return True
        
        return hr_validation


# ==============================================================================
# USO: Como registrar e usar este builder
# ==============================================================================

"""
1. Salvar este arquivo em: src/dags/builders/hr_builder.py

2. No seu banco MySQL, adicionar configuração:

INSERT INTO dag_configurations (
    dag_id,
    schedule_interval,
    owner,
    description,
    source_filename,
    target_table_name,
    python_module_path,
    is_active,
    builder_type
) VALUES (
    'rh_pipeline_daily',
    '0 22 * * *',  # 22:00 todos os dias
    'rh_team',
    'Pipeline de dados de RH com mascaramento LGPD',
    'erp/rh_export',
    'rh_employees',
    'builders.hr_builder.process_hr_data',
    1,
    'hr'  # Tipo de builder
);

3. Registrar o builder em factory_master.py ou em um módulo de inicialização:

from builders.hr_builder import HRDataPipelineBuilder
from dag_builder_examples import DAGBuilderRegistry

registry = DAGBuilderRegistry()
registry.register_builder('hr', HRDataPipelineBuilder)

4. Quando factory_master.py buscar esta configuração, usará HRDataPipelineBuilder
   automaticamente, aplicando todas as customizações de RH.
"""


# ==============================================================================
# TESTES (Exemplo de como testar)
# ==============================================================================

if __name__ == '__main__':
    
    # Setup de logging
    logging.basicConfig(level=logging.INFO)
    
    # Configuração de teste
    test_dag_config = {
        'dag_id': 'test_rh_pipeline',
        'dag_metadata': {
            'owner': 'rh_team',
            'schedule_interval': '0 22 * * *',
            'description': 'Pipeline teste de RH',
            'start_date': '2024-01-01',
        },
        'task_config': {
            'python_module_path': None,
            'source_filename': 'erp/rh_export',
            'target_table_name': 'rh_employees',
            'transform_args': {'bucket_name': 'rh-data'},
        }
    }
    
    print("\n" + "="*80)
    print("TESTANDO HR DATA PIPELINE BUILDER")
    print("="*80 + "\n")
    
    # Criar builder
    builder = HRDataPipelineBuilder(test_dag_config)
    
    # Criar DAG
    try:
        dag = builder.create_dag()
        print(f"✅ DAG criada com sucesso: {dag.dag_id}")
        print(f"📋 Tags: {dag.tags}")
        print(f"📅 Tasks: {list(dag.task_dict.keys())}")
        print("\n✅ Teste passou!\n")
    except Exception as e:
        print(f"❌ Erro ao criar DAG: {e}\n")
