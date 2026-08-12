# 🛡️ Guia de Validações Customizadas - Medallion Architecture

## 📋 Visão Geral

O **Editor de Validações Customizadas** permite que você crie suas próprias regras de validação de dados para pipelines Medallion (Bronze → Silver → Gold) **sem precisar editar código Python ou entender a implementação interna das DAGs**.

### ✨ Principais Vantagens

✅ **Interface Web Intuitiva** - Editor visual com syntax highlighting Python  
✅ **Templates Prontos** - 4 validações comuns pré-configuradas  
✅ **Zero Código nas DAGs** - Totalmente isolado da dag_factory  
✅ **Multi-Camada** - Validações específicas para Bronze, Silver ou Gold  
✅ **Teste em Tempo Real** - Valida sintaxe Python antes de salvar  
✅ **Isolamento por Usuário** - Cada bucket tem suas validações  
✅ **Logs Detalhados** - Rastreamento completo no Airflow  

---

## 🚀 Guia Rápido (5 Minutos)

### Passo 1: Acessar o Editor

Acesse o **Editor de Validações** através do menu principal:

1. No menu superior, clique em **"Ferramentas"** (ou dropdown de navegação)
2. Selecione **"🛡️ Validações Customizadas"**

**OU** acesse diretamente via URL:

```
http://localhost:8088/validation-rules-editor
```

### Passo 2: Criar Nova Regra

1. Clique no card **"+ Nova Regra"**
2. Preencha o formulário:
   - **Nome**: `validar_emails` (identificador único)
   - **Camada**: `Silver` (Bronze/Silver/Gold)
   - **Tabela**: `clientes` (opcional, deixe vazio para aplicar em todas)
   - **Descrição**: "Valida formato de emails"

### Passo 3: Escolher Template ou Código Próprio

**Opção A - Usar Template:**

Clique em um dos templates disponíveis:
- 📋 **Verificar Nulos** - Valida colunas obrigatórias
- 🔍 **Verificar Duplicatas** - Detecta registros duplicados
- 📊 **Quality Score** - Calcula score de qualidade
- 💼 **Regra de Negócio** - Validações específicas do domínio

O código será carregado automaticamente no editor.

**Opção B - Código Personalizado:**

Escreva sua validação Python:

```python
def validate(df, **context):
    """
    Valida formato de emails.
    
    Args:
        df: pandas DataFrame com os dados
        context: contexto do Airflow (task_instance, etc)
    
    Returns:
        dict com status 'ok' ou lança exceção
    """
    import pandas as pd
    
    if 'email' in df.columns:
        # Verificar se email contém @
        invalid_emails = df[~df['email'].str.contains('@', na=False)]
        
        if len(invalid_emails) > 0:
            raise ValueError(f"{len(invalid_emails)} emails sem @")
    
    return {'status': 'ok', 'validated_rows': len(df)}
```

### Passo 4: Testar e Salvar

1. Clique em **🧪 Testar** para validar sintaxe Python
2. Se OK, clique em **💾 Salvar Regra**
3. Regra fica disponível imediatamente!

### Passo 5: Ativar na DAG

No banco de dados MySQL:

```sql
UPDATE dags_config 
SET builder_type = 'custom_validation'
WHERE config_name = 'minha_dag_clientes';
```

**Pronto!** A DAG automaticamente carrega e executa suas validações.

---

## 📚 Templates Disponíveis

### 1. Verificar Nulos

**Quando usar**: Garantir que colunas obrigatórias não tenham valores vazios

**Código**:
```python
def validate(df, **context):
    """Valida que colunas críticas não tenham valores nulos."""
    import pandas as pd
    
    # Colunas que não podem ser nulas
    required_columns = ['id', 'nome', 'email']
    
    for col in required_columns:
        if col in df.columns:
            null_count = df[col].isnull().sum()
            if null_count > 0:
                raise ValueError(f"Coluna '{col}' tem {null_count} valores nulos")
    
    return {'status': 'ok', 'validated_rows': len(df)}
```

**Exemplo de uso**:
- Validar que todos os clientes têm ID e email
- Garantir que pedidos têm data e valor
- Verificar campos obrigatórios antes do BI

---

### 2. Verificar Duplicatas

**Quando usar**: Detectar registros duplicados por chave primária

**Código**:
```python
def validate(df, **context):
    """Valida que não existam duplicatas na chave primária."""
    import pandas as pd
    
    # Definir chave primária
    primary_key = ['id']
    
    duplicates = df[df.duplicated(subset=primary_key, keep=False)]
    
    if len(duplicates) > 0:
        raise ValueError(f"Encontradas {len(duplicates)} linhas duplicadas")
    
    return {'status': 'ok', 'unique_records': len(df)}
```

**Exemplo de uso**:
- Garantir unicidade de IDs de clientes
- Validar que não há pedidos duplicados
- Detectar registros repetidos antes da Gold

---

### 3. Quality Score

**Quando usar**: Calcular score de qualidade dos dados com threshold

**Código**:
```python
def validate(df, **context):
    """Calcula score de qualidade de dados."""
    import pandas as pd
    
    total_cells = df.shape[0] * df.shape[1]
    null_cells = df.isnull().sum().sum()
    
    quality_score = ((total_cells - null_cells) / total_cells) * 100
    
    if quality_score < 95:
        context['task_instance'].xcom_push(
            key='quality_warning',
            value=f'Quality score baixo: {quality_score:.2f}%'
        )
    
    return {
        'status': 'ok',
        'quality_score': quality_score,
        'total_cells': total_cells,
        'null_cells': null_cells
    }
```

**Exemplo de uso**:
- Monitorar qualidade ao longo do tempo
- Alertar quando qualidade cai abaixo de threshold
- Gerar métricas para dashboards de governança

---

### 4. Regra de Negócio

**Quando usar**: Validar regras específicas do domínio

**Código**:
```python
def validate(df, **context):
    """Valida regras de negócio customizadas."""
    import pandas as pd
    
    # Exemplo: Salários devem estar entre R$ 1.320 e R$ 50.000
    if 'salario' in df.columns:
        invalid_salaries = df[
            (df['salario'] < 1320) | (df['salario'] > 50000)
        ]
        
        if len(invalid_salaries) > 0:
            raise ValueError(
                f"{len(invalid_salaries)} salários fora do range permitido"
            )
    
    # Exemplo: Email deve ter @ e domínio
    if 'email' in df.columns:
        invalid_emails = df[~df['email'].str.contains('@', na=False)]
        if len(invalid_emails) > 0:
            raise ValueError(
                f"{len(invalid_emails)} emails inválidos"
            )
    
    return {'status': 'ok', 'validated_records': len(df)}
```

**Exemplo de uso**:
- Validar faixas de valores (preços, salários, idades)
- Verificar formatos (CPF, email, telefone)
- Aplicar regras de negócio específicas do domínio

---

## 🎯 Casos de Uso Práticos

### Caso 1: Validação de LGPD (Dados Mascarados)

**Cenário**: Garantir que CPFs, RGs e emails foram mascarados antes da Gold

**Solução**:
```python
def validate(df, **context):
    """Valida que dados sensíveis foram mascarados."""
    import pandas as pd
    
    sensitive_cols = ['cpf', 'rg', 'email']
    
    for col in sensitive_cols:
        if col in df.columns:
            # Verificar se valores parecem mascarados (contém ***)
            unmasked = df[~df[col].str.contains(r'\*', na=False)]
            if len(unmasked) > 0:
                raise ValueError(
                    f"{col} não mascarado: {len(unmasked)} registros expostos"
                )
    
    return {'status': 'ok', 'lgpd_compliant': True}
```

**Camada**: Silver (após transformações de mascaramento)  
**Tabela**: funcionarios, clientes (dados pessoais)

---

### Caso 2: SLA de Completude (99%)

**Cenário**: Garantir que dados têm pelo menos 99% de completude

**Solução**:
```python
def validate(df, **context):
    """Garante SLA de 99% de completude."""
    import pandas as pd
    
    completeness = (1 - df.isnull().sum().sum() / (df.shape[0] * df.shape[1])) * 100
    
    if completeness < 99.0:
        # Alerta mas não quebra pipeline
        context['task_instance'].xcom_push(
            key='sla_warning', 
            value={'completeness': completeness, 'threshold': 99.0}
        )
        print(f"⚠️ SLA violado: {completeness:.2f}%")
    
    return {'status': 'ok', 'completeness': completeness}
```

**Camada**: Gold (validação final antes do BI)  
**Tabela**: Todas as tabelas críticas

---

### Caso 3: Detecção de Anomalias

**Cenário**: Detectar valores extremos em vendas

**Solução**:
```python
def validate(df, **context):
    """Detecta valores fora do padrão histórico."""
    import pandas as pd
    
    if 'valor_venda' in df.columns:
        mean = df['valor_venda'].mean()
        std = df['valor_venda'].std()
        
        # Valores > 3 desvios-padrão são anomalias
        anomalies = df[abs(df['valor_venda'] - mean) > 3 * std]
        
        if len(anomalies) > 0:
            # Registrar anomalias no XCom para análise
            context['task_instance'].xcom_push(
                key='anomalies_detected',
                value={
                    'count': len(anomalies),
                    'samples': anomalies.head(5).to_dict('records')
                }
            )
            print(f"⚠️ {len(anomalies)} anomalias detectadas")
    
    return {'status': 'ok', 'anomalies_count': len(anomalies)}
```

**Camada**: Bronze (detecção precoce)  
**Tabela**: vendas, transacoes

---

### Caso 4: Validação de Integridade Referencial

**Cenário**: Garantir que todos os pedidos têm cliente válido

**Solução**:
```python
def validate(df, **context):
    """Valida integridade referencial com tabela de clientes."""
    import pandas as pd
    
    if 'cliente_id' not in df.columns:
        return {'status': 'ok', 'message': 'Coluna cliente_id não encontrada'}
    
    # Buscar lista de clientes válidos do XCom (gerada por outra task)
    ti = context['task_instance']
    valid_clients = ti.xcom_pull(task_ids='load_clients', key='valid_ids')
    
    if valid_clients:
        # Verificar se todos os cliente_id existem
        invalid = df[~df['cliente_id'].isin(valid_clients)]
        
        if len(invalid) > 0:
            raise ValueError(
                f"{len(invalid)} pedidos com cliente_id inválido"
            )
    
    return {'status': 'ok', 'validated_references': len(df)}
```

**Camada**: Silver (após joins e transformações)  
**Tabela**: pedidos, vendas

---

## 🔧 API da Função `validate()`

### Assinatura

```python
def validate(df: pd.DataFrame, **context) -> dict:
    """
    Função de validação customizada.
    
    Args:
        df: pandas DataFrame com dados da camada
        context: dict com contexto Airflow
    
    Returns:
        dict: {'status': 'ok', ...campos opcionais}
    
    Raises:
        Exception: Em caso de validação falhar
    """
```

### Parâmetros

#### `df` (pandas.DataFrame)
DataFrame com os dados da camada sendo validada:

```python
# Acessar colunas
if 'email' in df.columns:
    emails = df['email']

# Estatísticas
mean = df['valor'].mean()
null_count = df['coluna'].isnull().sum()

# Filtros
invalidos = df[df['idade'] < 0]
```

#### `context` (dict)
Contexto do Airflow com informações úteis:

```python
# Task Instance (para XCom)
ti = context['task_instance']

# Enviar dados para outra task
ti.xcom_push(key='metricas', value={'total': 1000})

# Receber dados de task anterior
dados = ti.xcom_pull(task_ids='bronze_task')

# Informações da execução
execution_date = context['execution_date']
dag_run = context['dag_run']
```

### Retorno

#### Validação OK
```python
return {
    'status': 'ok',
    'validated_rows': len(df),
    'quality_score': 98.5,
    # ...outros campos opcionais
}
```

#### Validação Falhou
```python
raise ValueError("Descrição clara do erro")
raise Exception("Erro crítico encontrado")
```

---

## 🎨 Boas Práticas

### ✅ Fazer

**1. Validar Amostras em Datasets Grandes**
```python
def validate(df, **context):
    # Para > 1M linhas, validar apenas amostra
    if len(df) > 1000000:
        df_sample = df.sample(n=10000, random_state=42)
    else:
        df_sample = df
    
    # Sua validação aqui
    ...
```

**2. Usar Logging para Debugging**
```python
def validate(df, **context):
    import logging
    log = logging.getLogger(__name__)
    
    log.info(f"Validando {len(df)} registros")
    log.info(f"Colunas: {list(df.columns)}")
    
    # Sua validação
    ...
```

**3. Retornar Métricas Detalhadas**
```python
return {
    'status': 'ok',
    'total_rows': len(df),
    'null_cells': df.isnull().sum().sum(),
    'duplicates': len(df[df.duplicated()]),
    'quality_score': 95.5
}
```

**4. Documentar Regras de Negócio**
```python
def validate(df, **context):
    """
    Valida regras de RH:
    - Salário entre R$ 1.320 (mínimo) e R$ 50.000 (teto)
    - CPF deve ter 11 dígitos
    - Data de admissão não pode ser futura
    """
    # Código aqui
```

---

### ❌ Não Fazer

**1. Usar `time.sleep()` ou Operações Bloqueantes**
```python
# ❌ ERRADO - bloqueia o Airflow
import time
time.sleep(60)

# ✅ CORRETO - validações rápidas
null_count = df['col'].isnull().sum()
```

**2. Fazer Requests HTTP sem Timeout**
```python
# ❌ ERRADO - pode travar
import requests
response = requests.get('https://api.example.com/validate')

# ✅ CORRETO - com timeout
response = requests.get('https://api.example.com/validate', timeout=5)
```

**3. Alterar Dados (use Silver transformation para isso)**
```python
# ❌ ERRADO - validação não deve modificar dados
df['nova_coluna'] = df['valor'] * 2

# ✅ CORRETO - apenas validar
if (df['valor'] < 0).any():
    raise ValueError("Valores negativos encontrados")
```

**4. Processar DataFrames Gigantes sem Amostragem**
```python
# ❌ ERRADO - lento em 10M+ linhas
for index, row in df.iterrows():
    if row['valor'] < 0:
        ...

# ✅ CORRETO - operações vetorizadas
invalid = df[df['valor'] < 0]
if len(invalid) > 0:
    raise ValueError(f"{len(invalid)} valores negativos")
```

---

## 📊 Monitoramento e Logs

### Logs no Airflow

Validações customizadas geram logs detalhados:

```
[2026-01-14 10:30:00] [VALIDATORS] Carregando validações de s3://lab01/validation-rules/silver/
[2026-01-14 10:30:01] [VALIDATORS] ✓ Carregado: check_nulls
[2026-01-14 10:30:01] [VALIDATORS] ✓ Carregado: check_duplicates
[2026-01-14 10:30:01] [VALIDATORS] Total carregados: 2

[2026-01-14 10:30:05] [VALIDATOR] Executando: check_nulls
[2026-01-14 10:30:06] [VALIDATOR] ✓ check_nulls: ok

[2026-01-14 10:30:07] [VALIDATOR] Executando: check_duplicates
[2026-01-14 10:30:08] [VALIDATOR] ✓ check_duplicates: ok

[2026-01-14 10:30:08] [VALIDATORS] Resumo: 2/2 passaram
```

### Acessar Resultados via XCom

```python
# Em outra task ou análise posterior
from airflow.models import TaskInstance

ti = TaskInstance(...)
results = ti.xcom_pull(key='validation_results_silver')

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

## 🚨 Troubleshooting

### Problema: Validação não aparece na DAG

**Sintomas**: Regra foi salva mas DAG não executa

**Soluções**:

1. **Verificar bucket correto**:
```sql
SELECT bucket_name FROM dags_config WHERE config_name = 'minha_dag';
-- Deve corresponder ao bucket onde salvou a regra
```

2. **Confirmar camada (bronze/silver/gold)**:
```python
# Regra salva em: s3://lab01/validation-rules/silver/minha_regra.py
# DAG configurada para: layer='bronze'
# ❌ NÃO VAI CARREGAR!

# Corrija para mesma camada
```

3. **Ver logs do Airflow**:
```bash
# Buscar por [VALIDATORS]
docker logs airflow-scheduler 2>&1 | grep VALIDATORS
```

---

### Problema: Erro de Sintaxe Python

**Sintomas**: "SyntaxError" ao executar validação

**Soluções**:

1. **Testar antes de salvar**:
   - Clique em 🧪 **Testar** no editor
   - Corrige erros antes de salvar

2. **Verificar indentação**:
```python
# ❌ ERRADO
def validate(df, **context):
return {'status': 'ok'}  # Sem indentação!

# ✅ CORRETO
def validate(df, **context):
    return {'status': 'ok'}  # 4 espaços
```

3. **Confirmar assinatura da função**:
```python
# ✅ CORRETO
def validate(df, **context):
    ...

# ❌ ERRADO - nome diferente
def my_validation(df, **context):
    ...
```

---

### Problema: Validação Passa mas Deveria Falhar

**Sintomas**: Dados inválidos não são detectados

**Soluções**:

1. **Adicionar logging**:
```python
def validate(df, **context):
    print(f"Colunas disponíveis: {list(df.columns)}")
    print(f"Total de linhas: {len(df)}")
    print(f"Amostra:\n{df.head()}")
    
    # Sua validação
    ...
```

2. **Verificar nomes de colunas**:
```python
# Verificar se coluna existe
if 'email' in df.columns:
    print(f"Coluna 'email' encontrada!")
else:
    print(f"Coluna 'email' NÃO encontrada. Disponíveis: {list(df.columns)}")
```

3. **Testar localmente com DataFrame de amostra**:
```python
import pandas as pd

# Criar amostra
df = pd.DataFrame({
    'email': ['test@example.com', 'invalido'],
    'nome': ['João', 'Maria']
})

# Testar validação
def validate(df, **context={}):
    # Seu código aqui
    ...

validate(df)
```

---

### Problema: Performance Lenta

**Sintomas**: Validação demora muito tempo

**Soluções**:

1. **Usar amostragem**:
```python
# Para datasets > 1M linhas
sample_size = min(10000, len(df))
df_sample = df.sample(n=sample_size, random_state=42)

# Validar apenas amostra
...

return {'status': 'ok', 'sample_size': sample_size}
```

2. **Evitar loops (usar pandas vetorizado)**:
```python
# ❌ LENTO - loop linha por linha
for index, row in df.iterrows():
    if row['valor'] < 0:
        errors += 1

# ✅ RÁPIDO - operação vetorizada
errors = (df['valor'] < 0).sum()
```

3. **Mover validações pesadas para Gold**:
- Bronze/Silver: validações rápidas (nulls, duplicatas)
- Gold: validações complexas (agregações, joins)

---

## 🎓 Próximos Passos

### Nível Iniciante

1. ✅ Use templates prontos (Verificar Nulos, Duplicatas)
2. ✅ Teste validações simples em tabela pequena
3. ✅ Ative em DAG de teste

### Nível Intermediário

1. ✅ Crie validações com regras de negócio específicas
2. ✅ Use XCom para comunicação entre tasks
3. ✅ Implemente validações em múltiplas camadas

### Nível Avançado

1. ✅ Crie validações com ML (detecção de anomalias)
2. ✅ Integre com APIs externas (validação de CEP, CNPJ)
3. ✅ Desenvolva framework de qualidade customizado

---

## 📚 Referências

- **Editor de Validações**: http://localhost:8088/validation-rules-editor (via menu Ferramentas)
- **Code Editor SQL**: http://localhost:8088/code-editor
- **Código Python**: `/src/dags/lib/custom_validators.py`
- **DAGBuilder**: `/src/dags/builders/custom_validation_builder.py`
- **Documentação Completa**: `CUSTOM_VALIDATIONS_README.md`
- **Builders**: `BUILDERS_CHEAT_SHEET.py`

---

**Última Atualização**: 2026-01-14  
**Versão**: 1.0  
**Mantido por**: Sistema de Builders Extensível
