# 🏗️ Sistema de Extensibilidade da Factory Master

## Visão Rápida

```
┌─────────────────────────────────────────────────────────────┐
│                    factory_master.py                        │
│                (Orquestrador Principal)                     │
└─────────────────┬───────────────────────────────────────────┘
                  │
                  │ (usa se builder_type definido)
                  ↓
┌─────────────────────────────────────────────────────────────┐
│                  DAGBuilderRegistry                         │
│            (Localiza builder apropriado)                    │
└─────────────────┬───────────────────────────────────────────┘
                  │
        ┌─────────┴──────────┬──────────────┐
        ↓                    ↓              ↓
    DefaultDAGBuilder   HRBuilder    EcommerceBuilder  ... suas ...
    (padrão)           (RH)         (E-commerce)      builders
```

---

## 📦 Arquivos Criados

### 1. **dag_builder_base.py** (700+ linhas)
Classe base abstracta `DAGBuilder` com:
- ✅ Template Method Pattern (`create_dag()`)
- ✅ 7 Hooks customizáveis
- ✅ Interfaces Strategy (BronzeStrategy, SilverStrategy, GoldStrategy)
- ✅ Implementação padrão de todas as camadas
- ✅ Suporte a contexto Airflow (XCom, context)

### 2. **dag_builder_examples.py** (600+ linhas)
Exemplos práticos de builders customizados:
- ✅ `MonitoredDAGBuilder` - Logging e métricas
- ✅ `EnrichedSilverDAGBuilder` - Transformações avançadas
- ✅ `ParallelProcessingDAGBuilder` - Processamento paralelo
- ✅ `ResilientDAGBuilder` - Retry e fallback
- ✅ `EcommerceSalesDAGBuilder` - Domínio de e-commerce
- ✅ `DAGBuilderRegistry` - Registro centralizado

### 3. **builders/hr_builder.py** (400+ linhas)
Exemplo real: Builder para Recursos Humanos com:
- ✅ Mascaramento LGPD de dados sensíveis
- ✅ Validações de conformidade
- ✅ KPIs específicos de RH
- ✅ Documentação completa

### 4. **EXTENSIBILIDADE_FACTORY_MASTER.md** (500+ linhas)
Documentação completa com:
- ✅ Arquitetura e padrões
- ✅ Guia passo a passo
- ✅ Exemplos de uso
- ✅ Troubleshooting

### 5. **INTEGRACAO_BUILDERS_FACTORY.py** (300+ linhas)
Instruções de integração com:
- ✅ Estratégia gradual (recomendado)
- ✅ Estratégia full migration
- ✅ Exemplos de código
- ✅ Script helper

---

## 🎯 Benefícios Conseguidos

### ✅ **Extensibilidade por Herança**
Outras pessoas criam suas próprias classes herdando de `DAGBuilder`:
```python
class MeuBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        # Sua lógica aqui
        pass
```

### ✅ **Factory Continua Gerando o Medalhão**
`factory_master.py` ainda cria Bronze → Silver → Gold para TODAS as DAGs.
Builders apenas customizam comportamentos específicos.

### ✅ **Isolamento Total**
Cada pessoa/domínio cria seu builder em arquivo separado.
Não afeta o código da factory principal.

### ✅ **Template Method Pattern**
Estrutura garantida: `create_dag()` sempre executa:
1. Criar DAG
2. Criar tasks (Bronze, Silver, Gold, Validação)
3. Aplicar customizações
4. Registrar dependências

### ✅ **7 Pontos de Customização (Hooks)**

| # | Hook | Customiza |
|---|------|-----------|
| 1 | `customize_dag_definition()` | Propriedades da DAG (tags, schedule, etc) |
| 2 | `customize_bronze_task()` | Função de ingestão |
| 3 | `customize_silver_transformation()` | Função de transformação |
| 4 | `customize_gold_aggregation()` | Função de agregação |
| 5 | `customize_validation_task()` | Função de validação |
| 6 | `customize_task_dependencies()` | Fluxo de tasks |
| 7 | `customize_pipeline_function()` | Decorator para função principal |

---

## 🚀 Fluxo de Uso

### Para Pessoa A (RH):
```python
# builders/rh_builder.py
class HRBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        # Mascarar CPF, validar LGPD, etc
        pass
```

### Para Pessoa B (E-commerce):
```python
# builders/ecommerce_builder.py
class EcommerceBuilder(DAGBuilder):
    def customize_gold_aggregation(self):
        # Calcular KPIs de vendas
        pass
```

### No MySQL:
```sql
INSERT INTO dag_configurations (..., builder_type)
VALUES ('rh_pipeline', 'hr');  -- Usa HRBuilder

INSERT INTO dag_configurations (..., builder_type)
VALUES ('vendas_pipeline', 'ecommerce');  -- Usa EcommerceBuilder
```

### factory_master.py automaticamente:
```python
# Busca builder_type do banco
builder = registry.get_builder(builder_type, dag_config)
dag = builder.create_dag()  # Executa template method com customizações
```

---

## 📊 Comparação: Antes vs Depois

### ANTES (Sem extensibilidade)
```
factory_master.py (2000+ linhas)
├── Função create_dynamic_dag()
├── Função create_multi_table_dag()
├── Validações hardcoded
├── Transformações padrão
└── ❌ Qualquer mudança = modificar factory
```

### DEPOIS (Com extensibilidade)
```
factory_master.py (1800 linhas, mais simples)
├── Busca builder_type do banco
└── Usa registry.get_builder()

dag_builder_base.py (classe base abstrata)
├── DAGBuilder (template method)
├── DefaultDAGBuilder
└── Interfaces Strategy

builders/ (customizações isoladas)
├── hr_builder.py (Person A)
├── ecommerce_builder.py (Person B)
├── finance_builder.py (Person C)
└── ... seus builders ...

✅ Cada um trabalha em seu arquivo
✅ Factory continua igual
✅ Reutilização de código
```

---

## 🔄 Integração Passo a Passo

### Fase 1: Setup (5 minutos)
```bash
# 1. Arquivo dag_builder_base.py ✅ PRONTO
# 2. Arquivo dag_builder_examples.py ✅ PRONTO
# 3. Diretório builders/ ✅ PRONTO
```

### Fase 2: Integração na Factory (10 minutos)
```python
# Adicionar 5 linhas em factory_master.py:
from dag_builder_examples import DAGBuilderRegistry
registry = DAGBuilderRegistry()

# Modificar query: adicionar builder_type
# Adicionar: if registry: usar builder, else: usar factory padrão
```

### Fase 3: Criar Primeiros Builders (20 minutos)
```python
# Criar builders/seu_dominio_builder.py
# Registrar no registry
# Atualizar MySQL com builder_type
```

### Fase 4: Uso em Produção
```
DAGs novas automaticamente usam seus builders customizados
Sem quebrar DAGs existentes (compatibilidade retroativa)
```

---

## 💡 Exemplos Reais de Uso

### 1. **Logging e Monitoramento**
```python
class MonitoredBuilder(DAGBuilder):
    def customize_pipeline_function(self, path):
        # Envolver função com logging de tempo de execução
        # Enviar métricas para Prometheus
        # Alertas automáticos
```

### 2. **Dados de RH (com LGPD)**
```python
class HRBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        # Mascarar CPF: XXX.XXX.XXX-XX
        # Converter salários em faixas
        # Validar conformidade LGPD
```

### 3. **E-commerce com Fraude**
```python
class EcommerceBuilder(DAGBuilder):
    def customize_gold_aggregation(self):
        # Detecção de fraude em tempo real
        # Cálculo de comissões
        # Alertas para pedidos suspeitos
```

### 4. **Processamento em Paralelo**
```python
class ParallelBuilder(DAGBuilder):
    def customize_task_dependencies(self, tasks):
        # Bronze → [Silver1, Silver2, Silver3] → Gold
        # Processamento paralelo automático
```

---

## 🎓 Padrões Utilizados

| Padrão | Aplicação |
|--------|-----------|
| **Template Method** | `create_dag()` define fluxo, subclasses customizam |
| **Strategy** | BronzeStrategy, SilverStrategy, GoldStrategy |
| **Registry** | DAGBuilderRegistry mapeia tipos → classes |
| **Decorator** | `customize_pipeline_function()` wraps função |
| **Factory** | Registry cria instâncias apropriadas |

---

## 🏁 Próximos Passos

### Para Você:
1. ✅ Revisar `dag_builder_base.py` (entender estrutura)
2. ✅ Revisar `dag_builder_examples.py` (ver exemplos)
3. ✅ Revisar `builders/hr_builder.py` (caso real)
4. ✅ Ler `EXTENSIBILIDADE_FACTORY_MASTER.md` (documentação)

### Para Integrar:
1. Modificar query do MySQL (adicionar `builder_type`)
2. Adicionar 10 linhas em `factory_master.py`
3. Criar primeira customização em `builders/seu_dominio.py`
4. Testar com DAG de teste

### Para Equipes:
1. Cada pessoa/domínio cria seu builder
2. Registra no MySQL com `builder_type`
3. Não toca em `factory_master.py`
4. Código totalmente isolado e versionado

---

## 📞 Dúvidas?

Consulte:
- **Como usar?** → `EXTENSIBILIDADE_FACTORY_MASTER.md`
- **Como integrar?** → `INTEGRACAO_BUILDERS_FACTORY.py`
- **Exemplos?** → `dag_builder_examples.py`
- **Exemplo real?** → `builders/hr_builder.py`
- **Base técnica?** → `dag_builder_base.py` (com docstrings)

---

## ✨ Resultado Final

A factory agora permite que múltiplas pessoas criem suas próprias codificações
customizadas **por herança**, mas a factory continua gerando o código principal
do medalhão (Bronze → Silver → Gold) **para todas as DAGs**. 🎉

```
factory_master.py + DAGBuilder = Extensibilidade + Estrutura
```
