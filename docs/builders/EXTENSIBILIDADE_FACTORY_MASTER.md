# Sistema de Extensibilidade da Factory Master - Guia Completo

## 📋 Visão Geral

A `factory_master.py` agora suporta **extensibilidade por herança**, permitindo que outras pessoas criem suas próprias implementações de DAGs customizadas **sem modificar o código principal**.

### Benefícios:
- ✅ **Isolamento de Código**: Customizações não afetam a factory
- ✅ **Reutilização**: Padrões comuns em classes base
- ✅ **Versionamento**: Cada customização é independente
- ✅ **Colaboração**: Múltiplas pessoas trabalhando simultaneamente
- ✅ **Estrutura**: Template Method Pattern garante consistência

---

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────┐
│      factory_master.py                  │
│  (Cria DAGs dinamicamente)              │
└──────────────────┬──────────────────────┘
                   │ usa
                   ↓
┌─────────────────────────────────────────┐
│   dag_builder_base.py                   │
│  • DAGBuilder (classe base abstrata)     │
│  • Template Method Pattern               │
│  • Hooks para customização               │
│  • DefaultDAGBuilder (impl. padrão)      │
└──────────────────┬──────────────────────┘
                   │ herda
        ┌──────────┼──────────┐
        ↓          ↓          ↓
   Customização  Customização  Customização
   by Pessoa A   by Pessoa B   by Pessoa C
   
   MonitoredDAGBuilder
   EnrichedSilverDAGBuilder
   ResilientDAGBuilder
   ... suas próprias classes ...
```

---

## 🔑 Conceitos Principais

### 1. Template Method Pattern
Define a estrutura da criação de DAG em `create_dag()`:
```python
def create_dag(self) -> DAG:
    self.dag = self._create_dag_object()        # Cria DAG base
    self.dag = self.customize_dag_definition()  # 🪝 Hook 1
    
    tasks = {
        'bronze_task': self._create_bronze_task(),
        'silver_task': self._create_silver_task(),
        'gold_task': self._create_gold_task(),
        'validation_task': self._create_validation_task(),
    }
    
    self.customize_task_dependencies(tasks)     # 🪝 Hook 2
    return self.dag
```

### 2. Hooks (Pontos de Customização)

São métodos que subclasses podem sobrescrever:

| Hook | Propósito | Retorna |
|------|-----------|---------|
| `customize_dag_definition()` | Alterar propriedades da DAG | `DAG` |
| `customize_bronze_task()` | Função customizada de ingestão | `Callable` ou `None` |
| `customize_silver_transformation()` | Função customizada de transformação | `Callable` ou `None` |
| `customize_gold_aggregation()` | Função customizada de agregação | `Callable` ou `None` |
| `customize_validation_task()` | Função customizada de validação | `Callable` ou `None` |
| `customize_task_dependencies()` | Reordenar/modificar fluxo | `None` |
| `customize_pipeline_function()` | Envolver função principal (decorator) | `Callable` ou `None` |

---

## 🚀 Como Criar Sua Própria Customização

### Passo 1: Criar uma classe herdando de `DAGBuilder`

```python
from dag_builder_base import DAGBuilder
import logging

log = logging.getLogger(__name__)

class MeuDAGCustomizado(DAGBuilder):
    """Minha implementação customizada de DAG para meu domínio."""
    
    def customize_dag_definition(self, dag):
        """Personalizar propriedades da DAG."""
        dag.tags.append('meu-dominio')
        dag.tags.append('v1.0')
        return dag
```

### Passo 2: Sobrescrever hooks necessários

```python
class MeuDAGCustomizado(DAGBuilder):
    
    def customize_silver_transformation(self):
        """Implementar transformação específica do meu domínio."""
        
        def minha_transformacao_silver(**context):
            log.info("Executando transformação customizada")
            
            ti = context['ti']
            bronze_data = ti.xcom_pull(task_ids='bronze_task')
            
            # Minha lógica aqui
            transformed = aplicar_regras_negocio(bronze_data)
            
            return transformed
        
        return minha_transformacao_silver
```

### Passo 3: Registrar no DAGBuilderRegistry

```python
from dag_builder_examples import DAGBuilderRegistry

registry = DAGBuilderRegistry()
registry.register_builder('meu-dominio', MeuDAGCustomizado)
```

### Passo 4: Usar em `factory_master.py`

Modificar `factory_master.py` para usar o registry:

```python
# No final do loop de criação de DAGs
builder_type = task_config.get('builder_type', 'default')  # Campo no MySQL

registry = DAGBuilderRegistry()
dag_builder = registry.get_builder(builder_type, dag_config)
dag_obj = dag_builder.create_dag()
globals()[config_name] = dag_obj
```

---

## 📝 Exemplos Práticos

### Exemplo 1: Adicionar Logging Detalhado

```python
from dag_builder_base import DAGBuilder
import time
from functools import wraps

class LoggedDAGBuilder(DAGBuilder):
    """DAG com logging detalhado de cada etapa."""
    
    def customize_pipeline_function(self, python_module_path):
        """Envolver a função original com logging."""
        
        def logging_wrapper(original_func):
            @wraps(original_func)
            def wrapper(*args, **kwargs):
                print(f"🚀 Iniciando: {python_module_path}")
                start = time.time()
                
                try:
                    result = original_func(*args, **kwargs)
                    elapsed = time.time() - start
                    print(f"✅ Sucesso em {elapsed:.2f}s")
                    return result
                except Exception as e:
                    print(f"❌ Erro: {e}")
                    raise
            
            return wrapper
        
        return logging_wrapper
```

**Uso:** Todas as funções Python executadas serão automaticamente envolvidas com logging.

---

### Exemplo 2: Validação Customizada por Domínio

```python
class ValidatedDAGBuilder(DAGBuilder):
    """DAG com validações específicas de domínio."""
    
    def customize_validation_task(self):
        """Validação customizada."""
        
        def custom_validation(**context):
            ti = context['ti']
            
            # Validar esquema
            silver = ti.xcom_pull(task_ids='silver_task')
            expected_columns = ['id', 'name', 'email']
            
            if not all(col in silver.keys() for col in expected_columns):
                raise ValueError("Schema inválido")
            
            # Validar limites de dados
            if len(silver) == 0:
                raise ValueError("Nenhum dado processado")
            
            print("✅ Validação passou")
            return True
        
        return custom_validation
```

---

### Exemplo 3: DAG com Tratamento de Erros Customizado

```python
class ResilientDAGBuilder(DAGBuilder):
    """DAG com retry automático e fallback."""
    
    def customize_dag_definition(self, dag):
        """Aumentar resiliência."""
        dag.default_args['retries'] = 5
        dag.default_args['retry_delay'] = 60  # 1 minuto
        return dag
    
    def customize_bronze_task(self):
        """Ingestão com fallback a cache."""
        
        def bronze_with_fallback(**context):
            try:
                # Ingestão primária
                return fetch_from_primary_source(**context)
            except Exception as e:
                log.warning(f"Fallback para cache: {e}")
                return fetch_from_cache(**context)
        
        return bronze_with_fallback
```

---

## 📊 Fluxo Completo: Do Banco de Dados à DAG Customizada

```
┌────────────────────────────────────────┐
│   MySQL: dag_configurations            │
│  + coluna "builder_type" (novo)        │
│  + valores: 'default', 'logged',       │
│             'resilient', 'ecommerce'   │
└────────────────┬───────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────┐
│   factory_master.py                    │
│   fetch_dag_configurations()           │
└────────────────┬───────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────┐
│   Para cada config:                    │
│   builder_type = record['builder_type']│
│   registry.get_builder(builder_type)   │
└────────────────┬───────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────┐
│   DAGBuilderRegistry.get_builder()     │
│   Retorna classe apropriada            │
└────────────────┬───────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────┐
│   builder.create_dag()                 │
│   Template Method + Hooks              │
│   Executa customizações                │
└────────────────┬───────────────────────┘
                 │
                 ↓
┌────────────────────────────────────────┐
│   DAG customizada registrada em        │
│   globals()[dag_id]                    │
│   ✅ Pronta para o Airflow             │
└────────────────────────────────────────┘
```

---

## 🔧 Alterações Necessárias em `factory_master.py`

Para integrar completamente o sistema de builders:

```python
# 1. No início, importar o registry
from dag_builder_examples import DAGBuilderRegistry

# 2. Criar instância global do registry
registry = DAGBuilderRegistry()

# 3. Na query MySQL, adicionar coluna builder_type
sql_query = """
SELECT
    ...existentes...,
    builder_type  -- NOVO
FROM dag_configurations
WHERE is_active = 1;
"""

# 4. No loop de criação de DAGs, usar o builder
for record in dag_records:
    # ...desempacotamento...
    builder_type = record[20]  # Novo índice para builder_type
    
    dag_config = {
        'id': id,
        'dag_id': config_name,
        'dag_metadata': {...},
        'task_config': {...}
    }
    
    # 🔑 USAR O REGISTRY
    dag_builder = registry.get_builder(builder_type or 'default', dag_config)
    dag_obj = dag_builder.create_dag()
    globals()[config_name] = dag_obj
```

---

## 📚 Estrutura de Diretórios

```
src/dags/
├── factory_master.py              # Mantido (orquestrador principal)
├── dag_builder_base.py            # ✨ NOVO: Classes base + interfaces
├── dag_builder_examples.py        # ✨ NOVO: Exemplos de implementações
├── dag_builders/                  # ✨ NOVO: Customizações por usuário
│   ├── __init__.py
│   ├── ecommerce_builder.py       # Builder para e-commerce
│   ├── healthcare_builder.py      # Builder para saúde
│   └── finance_builder.py         # Builder para finanças
└── ...outros arquivos...
```

---

## 🎯 Melhores Práticas

### 1. **Mantenha Compatibilidade com a Interface Base**
```python
# ✅ BOM: Respeita contrato de DAGBuilder
class MyBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        def transform(**context):
            return result
        return transform

# ❌ RUIM: Muda assinatura do método
class MyBuilder(DAGBuilder):
    def customize_silver_transformation(self, extra_param):  # Quebra contrato
        pass
```

### 2. **Sempre Chame o Super ou Reutilize Comportamento Padrão**
```python
# ✅ BOM
class MyBuilder(DAGBuilder):
    def customize_dag_definition(self, dag):
        dag = super().customize_dag_definition(dag)  # Se souber que existe
        dag.tags.append('meu-tag')
        return dag

# Ou reutilize o comportamento padrão
class MyBuilder(DAGBuilder):
    def customize_bronze_task(self):
        # Se não sobrescrever, usa o padrão automaticamente
        return None
```

### 3. **Documente Seus Hooks**
```python
class MyBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        """
        Implementa lógica de negócio específica de meu domínio.
        
        Retorna:
            Dict com 'status' e 'data' processados
            
        Raises:
            ValueError: Se dados estiverem inválidos
        """
        pass
```

### 4. **Use Logging Abundante**
```python
def customize_silver_transformation(self):
    def transform(**context):
        log.info("🔄 [MEU-DOMINIO] Transformação iniciada")
        # ... código ...
        log.info(f"✅ [MEU-DOMINIO] {len(data)} registros processados")
        return data
    return transform
```

---

## 🧪 Testando Sua Customização

```python
# test_my_builder.py
from dag_builder_examples import DAGBuilderRegistry
from my_custom_builders import MyBuilder

def test_my_builder():
    dag_config = {
        'dag_id': 'test_dag',
        'dag_metadata': {'owner': 'test', 'schedule_interval': None},
        'task_config': {'python_module_path': None}
    }
    
    builder = MyBuilder(dag_config)
    dag = builder.create_dag()
    
    assert dag.dag_id == 'test_dag'
    assert 'bronze_task' in dag.task_dict
    assert 'silver_task' in dag.task_dict
    print("✅ Testes passaram")
```

---

## 🚨 Troubleshooting

| Problema | Solução |
|----------|---------|
| "AttributeError: 'DAGBuilder' has no attribute..." | Verifique nome do método - use exatos do contrato |
| "Hooks não sendo chamados" | Certifique-se de herdar de `DAGBuilder`, não `DefaultDAGBuilder` |
| "DAG não aparece no Airflow" | Verifique se `globals()[dag_id] = dag` foi executado |
| "Função não é callable" | Hooks devem retornar `Callable` ou `None`, não `str` |

---

## 📞 Suporte

Para dúvidas ou sugestões sobre o sistema de extensibilidade:

1. Revisite [dag_builder_base.py](./dag_builder_base.py) - Documentação inline
2. Consulte [dag_builder_examples.py](./dag_builder_examples.py) - Exemplos reais
3. Abra issue no repositório

---

## 🎓 Resumo

| Conceito | Descrição |
|----------|-----------|
| **DAGBuilder** | Classe base que define fluxo de criação |
| **Hooks** | Métodos que subclasses sobrescrevem para customizar |
| **Template Method** | Padrão que garante estrutura consistente |
| **Registry** | Registro centralizado de todas as implementações |
| **Strategy** | Padrão para diferentes implementações de Bronze/Silver/Gold |

**Resultado**: Factory Master continua gerando o código principal do medalhão, mas agora permite infinitas customizações sem modificar o core! 🎉
