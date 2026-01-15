# Sistema de Validações Customizadas - Medallion Architecture

## 📋 Visão Geral

Este sistema permite que usuários **criem suas próprias regras de validação** para pipelines Medallion (Bronze, Silver, Gold) **sem precisar editar código Python ou entender a implementação interna da `dag_factory`**.

### Vantagens

✅ **Interface Web Amigável** - Editor visual com Monaco (mesmo do VS Code)  
✅ **Zero Acoplamento** - Validações são plugins isolados, não quebram DAGs existentes  
✅ **Templates Prontos** - Validações comuns pré-configuradas (nulls, duplicatas, quality score)  
✅ **Multi-Camada** - Validações específicas para Bronze, Silver ou Gold  
✅ **Teste em Tempo Real** - Valida sintaxe Python antes de salvar  
✅ **Segurança** - Validações ficam isoladas por bucket de usuário  

---

## 🚀 Como Usar

### 1. Acessar o Editor

Navegue para `/validation-rules-editor` no Code Editor.

### 2. Criar Nova Regra

1. Clique em **"+ Nova Regra"**
2. Preencha:
   - **Nome**: identificador único (ex: `validar_cpf`)
   - **Camada**: Bronze, Silver ou Gold
   - **Tabela**: (opcional) aplicar só em tabela específica
   - **Descrição**: o que a regra valida
   - **Código Python**: função `validate(df, **context)`

### 3. Usar Template ou Código Próprio

**Opção A - Usar Template:**
- Clique em um template (ex: "Verificar Nulos")
- Template é carregado no editor
- Customize conforme necessário

**Opção B - Escrever do Zero:**
```python
def validate(df, **context):
    """
    Valida dados do DataFrame.
    
    Args:
        df: pandas DataFrame com dados da camada
        context: dicionário com task_instance, dag_run, etc
    
    Returns:
        dict com 'status': 'ok' ou lançar exceção em falha
    """
    import pandas as pd
    
    # Exemplo: Validar que coluna 'email' não tenha nulos
    if 'email' in df.columns:
        null_count = df['email'].isnull().sum()
        if null_count > 0:
            raise ValueError(f"Coluna 'email' tem {null_count} valores nulos")
    
    return {'status': 'ok', 'validated_rows': len(df)}
```

### 4. Testar e Salvar

1. Clique em **🧪 Testar** - valida sintaxe Python
2. Se OK, clique em **💾 Salvar Regra**
3. Regra fica disponível imediatamente para DAGs

---

## 🏗️ Arquitetura

### Fluxo de Dados

```
┌─────────────────────────────────────────┐
│  Interface Web (validation-rules-editor)│
│  - Monaco Editor (Python)                │
│  - Templates prontos                     │
│  - Teste de sintaxe                      │
└──────────────┬──────────────────────────┘
               │ POST /api/validation-rule-save
               ▼
┌─────────────────────────────────────────┐
│  ValidationRulesController (PHP)         │
│  - Valida código Python                  │
│  - Salva em MinIO                        │
└──────────────┬──────────────────────────┘
               │ s3://bucket/validation-rules/
               ▼
┌─────────────────────────────────────────┐
│  MinIO Storage                           │
│  └─ validation-rules/                    │
│     ├─ bronze/                           │
│     │  └─ check_nulls.py                 │
│     ├─ silver/                           │
│     │  ├─ check_duplicates.py            │
│     │  └─ data_quality.py                │
│     └─ gold/                             │
│        └─ business_rules.py              │
└──────────────┬──────────────────────────┘
               │ Lido em runtime pela DAG
               ▼
┌─────────────────────────────────────────┐
│  Airflow DAG (Python)                    │
│  - custom_validators.py                  │
│    - load_user_validators()              │
│    - execute_all_validators()            │
│  - CustomValidationDAGBuilder            │
└─────────────────────────────────────────┘
```

### Estrutura de Arquivos

```
src/
├── codeigniter-app/
│   ├── app/
│   │   ├── Controllers/
│   │   │   └── ValidationRulesController.php  # API REST
│   │   └── Views/
│   │       └── code_editor/
│   │           └── validation-rules-editor.php # Interface Web
│   └── public/
│
└── dags/
    ├── lib/
    │   └── custom_validators.py               # Loader de validações
    │
    ├── builders/
    │   └── custom_validation_builder.py       # DAGBuilder com validações
    │
    └── factory_master.py                       # Factory de DAGs
```

---

## 📝 API de Validação

### Anatomia de uma Função `validate()`

```python
def validate(df: pd.DataFrame, **context) -> dict:
    """
    Função de validação customizada.
    
    Args:
        df: pandas DataFrame com dados da camada
        context: dict com contexto Airflow
            - task_instance: TaskInstance do Airflow
            - dag_run: DagRun atual
            - ds: data de execução (str)
            - execution_date: data de execução (datetime)
            ... outros padrões do Airflow
    
    Returns:
        dict: {'status': 'ok', ...outros campos opcionais}
    
    Raises:
        Exception: Em caso de validação falhar
    """
    # Sua lógica aqui
    pass
```

### Acessando Contexto do Airflow

```python
def validate(df, **context):
    # Task Instance (para XCom, etc)
    ti = context['task_instance']
    
    # Enviar dados para próxima task
    ti.xcom_push(key='quality_score', value=95.5)
    
    # Receber dados de task anterior
    previous_data = ti.xcom_pull(task_ids='bronze_task')
    
    # Informações da execução
    execution_date = context['execution_date']
    dag_run = context['dag_run']
    
    return {'status': 'ok'}
```

### Exemplos de Validações

#### 1. Verificar Valores Nulos

```python
def validate(df, **context):
    required_cols = ['id', 'nome', 'email']
    
    for col in required_cols:
        if col in df.columns:
            null_count = df[col].isnull().sum()
            if null_count > 0:
                raise ValueError(f"{col}: {null_count} nulos encontrados")
    
    return {'status': 'ok', 'validated_columns': required_cols}
```

#### 2. Detectar Duplicatas

```python
def validate(df, **context):
    primary_key = ['id']
    duplicates = df[df.duplicated(subset=primary_key, keep=False)]
    
    if len(duplicates) > 0:
        # Logar duplicatas no XCom
        context['task_instance'].xcom_push(
            key='duplicates_found',
            value=duplicates.to_dict('records')
        )
        raise ValueError(f"{len(duplicates)} duplicatas na chave {primary_key}")
    
    return {'status': 'ok', 'unique_records': len(df)}
```

#### 3. Quality Score com Threshold

```python
def validate(df, **context):
    total_cells = df.shape[0] * df.shape[1]
    null_cells = df.isnull().sum().sum()
    quality_score = ((total_cells - null_cells) / total_cells) * 100
    
    threshold = context.get('quality_threshold', 95.0)
    
    if quality_score < threshold:
        raise ValueError(
            f"Quality score {quality_score:.2f}% abaixo do threshold {threshold}%"
        )
    
    return {
        'status': 'ok',
        'quality_score': quality_score,
        'null_cells': null_cells,
        'total_cells': total_cells
    }
```

#### 4. Regras de Negócio

```python
def validate(df, **context):
    errors = []
    
    # Regra 1: Salário entre 1.320 e 50.000
    if 'salario' in df.columns:
        invalid = df[(df['salario'] < 1320) | (df['salario'] > 50000)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} salários fora do range")
    
    # Regra 2: CPF válido (11 dígitos)
    if 'cpf' in df.columns:
        invalid = df[~df['cpf'].str.match(r'^\d{11}$', na=False)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} CPFs inválidos")
    
    # Regra 3: Email com @
    if 'email' in df.columns:
        invalid = df[~df['email'].str.contains('@', na=False)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} emails sem @")
    
    if errors:
        raise ValueError("; ".join(errors))
    
    return {'status': 'ok', 'business_rules_validated': 3}
```

---

## 🔧 Integração com DAGs

### Opção 1: Usar DAGBuilder Pronto

No banco de dados, configure `builder_type`:

```sql
UPDATE dags_config 
SET builder_type = 'custom_validation'
WHERE config_name = 'minha_dag';
```

Pronto! A DAG agora carrega validações automaticamente do MinIO.

### Opção 2: Criar DAGBuilder Customizado

```python
from dag_builder_base import DAGBuilder
from lib.custom_validators import create_validation_task_func

class MeuDAGBuilder(DAGBuilder):
    def customize_validation_task(self):
        """Injeta validações customizadas do usuário."""
        return create_validation_task_func(
            bucket=self.config.get('bucket_name', 'lab01'),
            layer='silver',
            table=self.config.get('table_name')
        )
```

### Opção 3: Validações em Múltiplas Camadas

```python
from builders.custom_validation_builder import MultiLayerValidationDAGBuilder

# No factory_master.py ou registro:
if builder_type == 'multi_layer_validation':
    builder = MultiLayerValidationDAGBuilder(dag_config)
    dag = builder.create_dag()
```

Isso cria validações separadas para Bronze, Silver e Gold:

```
Bronze → [Validação Bronze] → Silver → [Validação Silver] → Gold → [Validação Gold]
```

---

## 🔒 Segurança e Isolamento

### Isolamento por Bucket

Cada usuário tem suas validações no próprio bucket:

```
s3://user1/validation-rules/...  ← Isolado
s3://user2/validation-rules/...  ← Isolado
```

### Sandboxing de Código

Validações rodam em contexto controlado:
- ✅ Acesso a pandas, numpy, datetime
- ✅ Acesso a XCom do Airflow
- ❌ Sem acesso a system calls perigosos
- ❌ Sem acesso a arquivos fora do bucket

### Teste de Sintaxe

Antes de salvar, código é validado:

```bash
python3 -m py_compile validation.py
```

Garante que código não terá erro de sintaxe em runtime.

---

## 📊 Monitoramento

### Logs no Airflow

Validações customizadas geram logs detalhados:

```
[VALIDATORS] Carregando validações de s3://lab01/validation-rules/silver/
[VALIDATORS] ✓ Carregado: check_nulls
[VALIDATORS] ✓ Carregado: check_duplicates
[VALIDATORS] Total carregados: 2
[VALIDATOR] Executando: check_nulls
[VALIDATOR] ✓ check_nulls: ok
[VALIDATOR] Executando: check_duplicates
[VALIDATOR] ✓ check_duplicates: ok
[VALIDATORS] Resumo: 2/2 passaram
```

### XCom Results

Resultados ficam disponíveis em XCom:

```python
ti.xcom_pull(key='validation_results_silver')
# {
#   'status': 'ok',
#   'validators_run': 2,
#   'passed': 2,
#   'failed': 0,
#   'results': {
#     'check_nulls': {'status': 'ok', 'validated_rows': 1000},
#     'check_duplicates': {'status': 'ok', 'unique_records': 1000}
#   }
# }
```

---

## 🎯 Casos de Uso

### 1. Governança de Dados (LGPD)

```python
def validate(df, **context):
    """Valida que dados sensíveis foram mascarados."""
    sensitive_cols = ['cpf', 'rg', 'email']
    
    for col in sensitive_cols:
        if col in df.columns:
            # Verificar se valores parecem mascarados (****)
            unmasked = df[~df[col].str.contains(r'\*', na=False)]
            if len(unmasked) > 0:
                raise ValueError(f"{col} não mascarado: {len(unmasked)} registros")
    
    return {'status': 'ok', 'lgpd_compliant': True}
```

### 2. SLA de Qualidade

```python
def validate(df, **context):
    """Garante SLA de 99% de completude."""
    completeness = (1 - df.isnull().sum().sum() / (df.shape[0] * df.shape[1])) * 100
    
    if completeness < 99.0:
        # Alerta mas não quebra pipeline
        context['task_instance'].xcom_push(key='sla_warning', value=True)
        log.warning(f"⚠️ SLA violado: {completeness:.2f}%")
    
    return {'status': 'ok', 'completeness': completeness}
```

### 3. Detecção de Anomalias

```python
def validate(df, **context):
    """Detecta valores fora do padrão histórico."""
    if 'valor_venda' in df.columns:
        mean = df['valor_venda'].mean()
        std = df['valor_venda'].std()
        
        # Valores > 3 desvios-padrão são anomalias
        anomalies = df[abs(df['valor_venda'] - mean) > 3 * std]
        
        if len(anomalies) > 0:
            context['task_instance'].xcom_push(
                key='anomalies_detected',
                value=anomalies.to_dict('records')
            )
            log.warning(f"⚠️ {len(anomalies)} anomalias detectadas")
    
    return {'status': 'ok', 'anomalies_count': len(anomalies)}
```

---

## 🚫 Limitações e Boas Práticas

### ❌ Não Fazer

- **Não usar time.sleep()** - bloqueia o Airflow
- **Não fazer requests HTTP externos** sem timeout
- **Não processar volumes gigantes** - validação deve ser rápida
- **Não alterar dados** - use Silver transformation para isso

### ✅ Fazer

- **Validar amostras** se dataset for grande
- **Usar logging** para debugging
- **Retornar dicts informativos** com métricas
- **Documentar** regras de negócio no código
- **Testar** antes de salvar

### Performance

Para datasets grandes (> 1M linhas):

```python
def validate(df, **context):
    # Validar apenas amostra de 10k linhas
    sample_size = min(10000, len(df))
    df_sample = df.sample(n=sample_size, random_state=42)
    
    # Sua validação aqui
    ...
    
    return {'status': 'ok', 'sample_size': sample_size}
```

---

## 📚 Referências

- **DAGBuilder Base**: `src/dags/dag_builder_base.py`
- **Exemplos**: `src/dags/dag_builder_examples.py`
- **Builders Reais**: `src/dags/builders/hr_builder.py`
- **Custom Validators**: `src/dags/lib/custom_validators.py`
- **Documentação Builders**: `BUILDERS_CHEAT_SHEET.py`

---

## 🆘 Troubleshooting

### Validação não aparece na DAG

1. Verifique se salvou no bucket correto
2. Confirme que camada (bronze/silver/gold) está correta
3. Veja logs do Airflow: `[VALIDATORS]`

### Erro de sintaxe Python

1. Clique em **🧪 Testar** antes de salvar
2. Verifique indentação (Python é sensível)
3. Confirme que tem `def validate(df, **context):`

### Validação falha mas deveria passar

1. Adicione `print()` ou `log.info()` no código
2. Veja logs completos no Airflow
3. Teste localmente com DataFrame de amostra

### Performance lenta

1. Use `.sample()` para validar apenas amostra
2. Evite loops em linhas individuais (use pandas vetorizado)
3. Considere mover validações pesadas para Gold

---

**Criado em:** 2026-01-14  
**Versão:** 1.0  
**Mantido por:** Sistema de Builders Extensível
