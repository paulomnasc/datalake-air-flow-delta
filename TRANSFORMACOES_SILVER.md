# Transformações Inteligentes da Camada Silver

## Visão Geral

A camada **Silver** aplica **inteligência automática de dados** usando bibliotecas Python (pandas) para detectar padrões e aplicar transformações sem necessidade de configuração manual. Além disso, adiciona **validação de qualidade de dados** e colunas de auditoria.

## Princípio: Zero Configuração

**Nenhum código customizado é necessário** - o sistema analisa os dados automaticamente e aplica as melhores práticas de engenharia de dados.

---

## 📊 Dicionário de Dados - Camadas Silver

### Colunas Originais
Todas as colunas da camada Bronze são preservadas, mas com transformações automáticas:
- Nomes normalizados (lowercase, sem espaços)
- Tipos inferidos automaticamente (datas, números, categorias)
- Valores nulos tratados inteligentemente

### Colunas de Qualidade de Dados (Adicionadas Automaticamente)

| Coluna | Tipo | Descrição | Valores | Uso |
|--------|------|-----------|---------|-----|
| **DataQualityRulesPass** | `int64` | Quantidade de regras de qualidade que a linha passou | 0 a 5+ | Identificar linhas com boa qualidade. Valores altos (4-5) = dados confiáveis |
| **DataQualityRulesFail** | `int64` | Quantidade de regras de qualidade que a linha falhou | 0 a 5+ | Identificar linhas problemáticas. Valores > 0 = dados precisam revisão |
| **DataQualityRulesSkip** | `int64` | Quantidade de regras que não se aplicaram à linha | 0 a 5+ | Regras não executadas (ex: validação de email em linha sem email) |
| **DataQualityEvaluationResult** | `string` | Resultado final da avaliação de qualidade | "Passed" ou "Failed" | Filtrar rapidamente linhas confiáveis. Filtro: `== "Passed"` |

### Regras de Qualidade Aplicadas

#### Regra 1: Verificação de Valores Nulos
- **O que verifica**: Campos críticos (id, key, code, number) não podem ser nulos
- **Pass**: Campo obrigatório está preenchido
- **Fail**: Campo obrigatório está vazio (NULL/NaN)
- **Skip**: Coluna não é crítica

**Exemplo**:
```python
# customerNumber não pode ser nulo
customerNumber  DataQualityRulesPass  DataQualityRulesFail
103            ✅ +1                  0
NULL           0                      ❌ +1
```

#### Regra 2: Validação de Tipos de Dados
- **O que verifica**: Valores numéricos devem ser finitos (não infinito, não NaN)
- **Pass**: Número é finito e válido
- **Fail**: Número é infinito (inf/-inf) ou inválido
- **Skip**: Coluna não é numérica

**Exemplo**:
```python
# creditLimit deve ser número válido
creditLimit    DataQualityRulesPass  DataQualityRulesFail
50000.00      ✅ +1                  0
inf           0                      ❌ +1
```

#### Regra 3: Detecção de Duplicatas
- **O que verifica**: Linhas duplicadas exatas (todos os campos iguais)
- **Pass**: Linha é única
- **Fail**: Linha é duplicata de outra
- **Skip**: Nunca pula

**Exemplo**:
```python
# Duas linhas idênticas
customerNumber  name        DataQualityRulesFail
103            "John Doe"   0 (primeira ocorrência)
103            "John Doe"   ❌ +1 (duplicata)
```

#### Regra 4: Validação de Ranges Numéricos
- **O que verifica**: Valores numéricos dentro de 3 desvios padrão da média (outliers)
- **Pass**: Valor está dentro do range esperado
- **Fail**: Valor é outlier extremo (> 3 desvios padrão)
- **Skip**: Coluna não é numérica

**Exemplo**:
```python
# creditLimit outliers
creditLimit    Média    Desvio   Z-Score  DataQualityRulesFail
50000         100000   30000    -1.67    0 (dentro do range)
300000        100000   30000    6.67     ❌ +1 (outlier extremo)
```

#### Regra 5: Validação de Padrões de String
- **O que verifica**: Emails e telefones têm formato válido (regex)
- **Pass**: String segue padrão esperado
- **Fail**: String não segue padrão (email inválido, telefone malformado)
- **Skip**: Coluna não contém emails/telefones

**Padrões validados**:
- **Email**: `usuario@dominio.com`
- **Telefone**: Formatos com 10-15 dígitos

**Exemplo**:
```python
# Validação de email
email                  DataQualityRulesPass  DataQualityRulesFail
"john@example.com"    ✅ +1                  0
"invalid-email"       0                      ❌ +1
```

### Interpretação dos Resultados

| DataQualityRulesPass | DataQualityRulesFail | DataQualityEvaluationResult | Interpretação |
|---------------------|---------------------|---------------------------|---------------|
| 5 | 0 | Passed | ✅ Excelente - Todos os testes passaram |
| 4 | 1 | Failed | ⚠️  Atenção - 1 problema encontrado |
| 3 | 2 | Failed | ⚠️  Qualidade duvidosa - 2 problemas |
| 0-2 | 3+ | Failed | ❌ Qualidade crítica - Requer revisão |

### Exemplo Prático Completo

**Entrada (Bronze)**:
```csv
customerNumber,contactFirstName,creditLimit,email
103,John,50000,john@example.com
,Jane,999999999,invalid-email
105,Bob,75000,bob@company.com
105,Bob,75000,bob@company.com
```

**Saída (Silver)**:
```csv
customernumber,contactfirstname,creditlimit,email,DataQualityRulesPass,DataQualityRulesFail,DataQualityRulesSkip,DataQualityEvaluationResult
103,John,50000,john@example.com,5,0,0,Passed
,Jane,999999999,invalid-email,2,3,0,Failed
105,Bob,75000,bob@company.com,5,0,0,Passed
105,Bob,75000,bob@company.com,4,1,0,Failed
```

**Análise**:
- **Linha 1**: ✅ Perfeita (5 passes, 0 fails)
- **Linha 2**: ❌ Problemas (customernumber nulo, creditLimit outlier, email inválido)
- **Linha 3**: ✅ Perfeita (5 passes, 0 fails)
- **Linha 4**: ❌ Duplicata (4 passes, 1 fail - duplicata detectada)

### Queries Úteis para Análise

```python
import pandas as pd

df = pd.read_parquet('silver/customers/file.parquet')

# 1. Taxa de aprovação geral
pass_rate = (df['DataQualityEvaluationResult'] == 'Passed').sum() / len(df) * 100
print(f"Taxa de aprovação: {pass_rate:.1f}%")

# 2. Filtrar apenas linhas confiáveis
clean_data = df[df['DataQualityEvaluationResult'] == 'Passed']

# 3. Encontrar linhas com múltiplos problemas
critical_issues = df[df['DataQualityRulesFail'] >= 3]
print(f"Linhas críticas: {len(critical_issues)}")

# 4. Distribuição de qualidade
quality_dist = df['DataQualityRulesPass'].value_counts().sort_index()
print(quality_dist)

# 5. Identificar problemas específicos
null_issues = df[df['customernumber'].isna()]
outliers = df[df['creditlimit'] > df['creditlimit'].mean() + 3*df['creditlimit'].std()]
```

---

---

## Transformações Automáticas Aplicadas

### 1. **Normalização de Colunas**
```python
# Antes: 'Customer Name', ' ProductID ', 'order_date'
# Depois: 'customer_name', 'productid', 'order_date'

df.columns = df.columns.str.strip().str.lower().str.replace(' ', '_')
```

### 2. **Detecção e Conversão Automática de Datas**
```python
# Detecta automaticamente colunas com formato de data
# Se >50% das linhas são datas válidas, converte para datetime

Exemplos detectados:
- '2024-01-15' → datetime64[ns]
- '15/01/2024' → datetime64[ns]
- '2024-01-15 10:30:00' → datetime64[ns]
```

**Benefício**: Análises temporais funcionam automaticamente no Power BI/SQL.

### 3. **Inferência Automática de Tipos Numéricos**
```python
# Colunas que parecem números mas estão como texto
# Se >80% das linhas são números válidos, converte

Antes:  'price': ['100.50', '200.00', '150.75']  (object)
Depois: 'price': [100.50, 200.00, 150.75]        (float64)
```

### 4. **Detecção Automática de Categorias**
```python
# Colunas com poucos valores únicos (<5% da base)
# Automaticamente convertidas para tipo 'category'

Exemplo:
- 'status': ['Ativo', 'Inativo', 'Ativo'] → category
- 'country': ['USA', 'France', 'USA'] → category
```

**Benefício**: Economia de memória (~50-70%) e queries mais rápidas.

### 5. **Normalização de Texto**
```python
# Remove espaços extras de todas as colunas de texto

Antes:  '  John Doe  '
Depois: 'John Doe'
```

### 6. **Preenchimento Inteligente de Nulos**

O sistema escolhe a estratégia por tipo de dado:

| Tipo | Estratégia | Exemplo |
|------|------------|---------|
| **Numérico** | Mediana | `creditLimit: [100, 200, NaN] → [100, 200, 150]` |
| **Texto** | 'N/A' | `state: ['CA', NaN, 'NY'] → ['CA', 'N/A', 'NY']` |
| **Categórico** | Moda (valor mais frequente) | `status: ['Ativo', NaN, 'Ativo'] → ['Ativo', 'Ativo', 'Ativo']` |

**Por que mediana em vez de média?**
- Mediana é robusta a outliers
- Exemplo: salários [1000, 2000, 100000] → mediana=2000 (média=34333)

### 7. **Colunas de Auditoria Automáticas**

Duas colunas são adicionadas automaticamente:

```python
'_silver_processed_at': Timestamp de quando foi processado
'_silver_row_quality': Percentual de completude da linha (0-100%)
```

**Exemplo**:
```csv
customerNumber,name,creditLimit,_silver_processed_at,_silver_row_quality
103,Doe,50000,2024-11-23 10:30:00,100.0
104,Smith,,2024-11-23 10:30:00,66.67
```

**Uso**: Identificar linhas com baixa qualidade de dados.

---

## Exemplo Prático: Antes e Depois

### Entrada (Bronze CSV)
```csv
Customer Name, Order Date ,  Price , Status
  John Doe  ,2024-01-15,100.50,  Active  
Jane Smith,2024-01-16,200,Active
Bob Wilson,,150.75,
```

### Saída (Silver Parquet)
```csv
customer_name,order_date,price,status,_silver_processed_at,_silver_row_quality
John Doe,2024-01-15,100.50,Active,2024-11-23 10:30:00,100.0
Jane Smith,2024-01-16,200.00,Active,2024-11-23 10:30:00,100.0
Bob Wilson,N/A,150.75,N/A,2024-11-23 10:30:00,50.0
```

**Transformações aplicadas**:
1. ✅ Colunas normalizadas: `Customer Name` → `customer_name`
2. ✅ Data detectada e convertida: `Order Date` → `order_date` (datetime)
3. ✅ Preços convertidos para numérico: `"100.50"` → `100.50` (float)
4. ✅ Status convertido para categoria (apenas 2 valores únicos)
5. ✅ Espaços removidos: `"  John Doe  "` → `"John Doe"`
6. ✅ Nulos preenchidos inteligentemente
7. ✅ Colunas de auditoria adicionadas

---

## Logs de Execução

### Exemplo Real
```
[SILVER] Limpeza básica: 3 linhas removidas (125 → 122)
[SILVER] Aplicando transformações inteligentes automáticas...
[SILVER] ✓ Coluna 'orderdate' convertida para datetime
[SILVER] ✓ Coluna 'requireddate' convertida para datetime
[SILVER] ✓ Coluna 'shippeddate' convertida para datetime
[SILVER] ✓ Coluna 'status' convertida para category (6 valores únicos)
[SILVER] ✓ Coluna 'productline' convertida para category (7 valores únicos)
[SILVER] ✓ Coluna 'quantityordered' convertida para numérico
[SILVER] ✓ Coluna 'priceeach' convertida para numérico
[SILVER] Transformações inteligentes concluídas: 2 colunas adicionadas
[SILVER] Tipos finais: {'datetime64[ns]': 3, 'category': 2, 'float64': 5, 'int64': 2, 'object': 3}
[SILVER] ✅ Arquivo salvo em: s3://lab01/silver/orders/file.parquet
```

---

## Vantagens da Abordagem Automática

### ✅ Zero Configuração
- Funciona com **qualquer tabela** automaticamente
- Nenhum código customizado necessário
- Nenhum arquivo de configuração

### ✅ Inteligente por Padrão
- Detecta tipos automaticamente (datas, números, categorias)
- Aplica melhores práticas de engenharia de dados
- Economia de memória com tipos otimizados

### ✅ Manutenível
- Um único algoritmo para todas as tabelas
- Melhorias beneficiam todas as tabelas automaticamente
- Sem necessidade de manter transformadores por tabela

### ✅ Robusto
- Usa pandas (biblioteca battle-tested)
- Tratamento de erros embutido
- Fallback: se conversão falhar, mantém original

### ✅ Escalável
- Performance não degrada com número de tabelas
- Mesma lógica para 1 ou 1000 tabelas

---

## Comparação: Customizado vs Automático

### ❌ Abordagem Customizada (Manual)

```python
# Criar arquivo: customers.py
def transform(df):
    df['nome_completo'] = df['firstName'] + ' ' + df['lastName']
    df['faixa_credito'] = pd.cut(...)
    return df

# Criar arquivo: orders.py
def transform(df):
    df['orderdate'] = pd.to_datetime(df['orderdate'])
    return df

# Criar arquivo: products.py
# Criar arquivo: employees.py
# ... (repetir para cada tabela)
```

**Problemas**:
- Trabalho manual para cada tabela
- Inconsistência entre transformações
- Manutenção complexa (N arquivos)
- Requer conhecimento de cada tabela

### ✅ Abordagem Automática (Inteligente)

```python
# UM algoritmo para TODAS as tabelas
def _apply_smart_transformations(df):
    # Detecta datas automaticamente
    # Detecta números automaticamente
    # Detecta categorias automaticamente
    # Preenche nulos inteligentemente
    return df
```

**Vantagens**:
- Zero trabalho manual
- Consistência garantida
- Manutenção centralizada (1 função)
- Funciona com tabelas desconhecidas

---

## Configurações e Thresholds

Os thresholds podem ser ajustados em `_apply_smart_transformations()`:

| Parâmetro | Valor Padrão | Descrição |
|-----------|--------------|-----------|
| **Date detection** | 50% | Se >50% das linhas são datas válidas, converte |
| **Numeric detection** | 80% | Se >80% das linhas são números válidos, converte |
| **Category threshold** | 5% | Se <5% de valores únicos, trata como categórica |
| **Category max unique** | 50 | Máximo de valores únicos para considerar categórica |
| **Null fill strategy** | Por tipo | Numérico=mediana, Texto=N/A, Categórico=moda |

---

## Validação de Qualidade

### Como `DataQualityRulesPass` é calculada

- Implementação: `src/dags/lib/data_quality.py` (`DataQualityValidator.add_quality_columns`).
- Inicializa as 4 colunas de qualidade em zero e avalia cada linha.
- Incrementa `DataQualityRulesPass` quando a linha passa em cada regra:
    - **Nulos críticos**: campos-chave (`id`, `key`, `code`, `number` ou 1ª coluna) não podem ser nulos.
    - **Tipos numéricos válidos**: valores não podem ser `NaN`/`±inf`.
    - **Duplicatas**: linha não é marcada como duplicada (considerando todas as colunas de negócio).
    - **Ranges numéricos**: valor dentro de média ± 3 desvios padrão.
    - **Padrões de string**: e-mail e telefone aderem aos regex básicos definidos.


### Verificar Tipos Inferidos

```python
import pandas as pd

df = pd.read_parquet('s3://lab01/silver/customers/file.parquet')

# Ver tipos inferidos
print(df.dtypes)

# Ver resumo estatístico
print(df.describe(include='all'))

# Ver valores únicos de categorias
for col in df.select_dtypes(include='category').columns:
    print(f"{col}: {df[col].unique()}")
```

### Verificar Qualidade dos Dados

```python
# Verificar completude das linhas
print(df['_silver_row_quality'].describe())

# Encontrar linhas com baixa qualidade (<80%)
low_quality = df[df['_silver_row_quality'] < 80]
print(f"Linhas com baixa qualidade: {len(low_quality)}")
```

---

## Quando Adicionar Lógica Customizada?

A abordagem automática cobre **90% dos casos**. Adicione lógica customizada apenas se precisar:

1. **Regras de negócio muito específicas**
   - Exemplo: Calcular comissão com fórmula complexa do seu negócio
   
2. **Enriquecimento com dados externos**
   - Exemplo: Buscar taxa de câmbio de API externa

3. **Transformações domínio-específicas**
   - Exemplo: Cálculos financeiros específicos da indústria

**Como adicionar**:
Crie função em `gold_layer.py` (análise avançada) em vez de modificar Silver (dados limpos).

---

## Bibliotecas Utilizadas

| Biblioteca | Uso | Documentação |
|------------|-----|--------------|
| **pandas** | Análise e transformação de dados | [pandas.pydata.org](https://pandas.pydata.org) |
| **numpy** | Operações numéricas | [numpy.org](https://numpy.org) |
| **pyarrow** | Leitura/escrita Parquet | [arrow.apache.org](https://arrow.apache.org) |

---

## Referências

- **Código fonte**: `src/dags/lib/silver_layer.py` → função `_apply_smart_transformations()`
- **Pipeline completo**: `src/dags/lib/medallion_pipeline.py`
- **Pandas Type Inference**: [pandas.pydata.org/docs/user_guide/basics.html](https://pandas.pydata.org/docs/user_guide/basics.html)

---

## Histórico

| Data | Mudança |
|------|---------|
| 2024-11-23 | Refatoração para inteligência automática usando pandas |
| 2024-11-23 | Removida necessidade de transformadores customizados |
| 2024-11-23 | Implementação de detecção automática de tipos e categorias |


### Como Funciona

```python
def _apply_custom_transformations(df, table_name):
    """
    1. Normaliza nome da tabela (remove prefixos: ingestao_, raw_, bronze_)
    2. Busca módulo: utils/transformations/silver/{nome_normalizado}.py
    3. Se encontrar, chama função transform(df)
    4. Se não encontrar, retorna DataFrame sem mudanças
    """
    import importlib
    
    # Normalizar: 'ingestao_customers_raw4' → 'customers'
    normalized_name = table_name.lower()
    for prefix in ['ingestao_', 'raw_', 'bronze_']:
        normalized_name = normalized_name.replace(prefix, '')
    
    try:
        # Import dinâmico
        module_path = f"utils.transformations.silver.{normalized_name}"
        transformer_module = importlib.import_module(module_path)
        
        # Executar transform()
        if hasattr(transformer_module, 'transform'):
            return transformer_module.transform(df)
    except ModuleNotFoundError:
        # Sem transformador = só limpeza básica
        pass
    
    return df
```

### Fluxo de Decisão

```
Arquivo Bronze CSV
    ↓
1. Limpeza Básica (sempre)
   - dropna(how='all')
   - drop_duplicates()
    ↓
2. Buscar transformador customizado
   ├─ Existe? → Aplicar transform(df)
   └─ Não existe? → Continuar sem transformação
    ↓
3. Conversão para Parquet
   - compression='snappy'
    ↓
Arquivo Silver Parquet
```

---

## Exemplo: Tabelas Sem Transformador

### Tabela Genérica (employees, offices, etc.)

```bash
# Upload: employees.csv
# Target table: ingestao_employees_raw2

# Processamento:
# 1. Bronze: employees.csv copiado
# 2. Silver: 
#    - Busca: utils/transformations/silver/employees.py
#    - Não encontra: Aplica só limpeza básica
#    - Salva: employees.parquet
# 3. Gold: Otimiza Parquet
```

**Log de execução**:
```
[SILVER] Limpeza básica: 2 linhas removidas (25 → 23)
[SILVER] Buscando transformador: utils.transformations.silver.employees
[SILVER] Nenhum transformador customizado para 'ingestao_employees_raw2' (genérico será usado)
[SILVER] Parquet criado: /tmp/tmpxyz/employees.parquet
[SILVER] ✅ Arquivo salvo em: s3://lab01/silver/ingestao_employees_raw2/employees.parquet
```

**Resultado**: Funciona perfeitamente sem nenhum código customizado! 🎉

---

## Como Adicionar Transformações Customizadas

### Passo 1: Criar Módulo de Transformação

Crie arquivo: `src/dags/utils/transformations/silver/{nome_tabela}.py`

**Estrutura obrigatória**:
```python
import pandas as pd
import logging

log = logging.getLogger(__name__)


def transform(df: pd.DataFrame) -> pd.DataFrame:
    """
    Aplica transformações de negócio.
    
    Args:
        df: DataFrame já limpo (sem duplicatas/linhas vazias)
        
    Returns:
        DataFrame transformado
    """
    log.info("[SILVER/TABELA] Iniciando transformações...")
    
    # Suas transformações aqui
    df.columns = df.columns.str.lower()
    # ... (adicionar colunas, categorizar, etc.)
    
    log.info("[SILVER/TABELA] Transformações concluídas")
    return df
```

### Passo 2: Implementar Lógica de Negócio

#### Exemplo: Employees

```python
# src/dags/utils/transformations/silver/employees.py

import pandas as pd
import logging

log = logging.getLogger(__name__)


def transform(df: pd.DataFrame) -> pd.DataFrame:
    log.info("[SILVER/EMPLOYEES] Iniciando transformações...")
    
    df.columns = df.columns.str.lower()
    
    # Nome completo
    if 'firstname' in df.columns and 'lastname' in df.columns:
        df['nome_completo'] = df['firstname'] + ' ' + df['lastname']
    
    # Tempo de empresa (em anos)
    if 'hiredate' in df.columns:
        df['hiredate'] = pd.to_datetime(df['hiredate'])
        hoje = pd.Timestamp.now()
        df['anos_empresa'] = ((hoje - df['hiredate']).dt.days / 365).round(1)
    
    # Categorizar por cargo
    if 'jobtitle' in df.columns:
        def categorizar_cargo(job):
            job_lower = str(job).lower()
            if 'president' in job_lower or 'vp' in job_lower:
                return 'Executivo'
            elif 'manager' in job_lower:
                return 'Gerência'
            elif 'sales rep' in job_lower:
                return 'Vendas'
            else:
                return 'Outros'
        
        df['categoria_cargo'] = df['jobtitle'].apply(categorizar_cargo)
    
    log.info("[SILVER/EMPLOYEES] Transformações concluídas")
    return df
```

### Passo 3: Upload e Execução

```bash
# Upload via webapp
# Arquivo: employees.csv
# Target table: ingestao_employees_raw3

# Execução automática:
# Silver layer detecta módulo e aplica transformações
```

**Log de execução (com transformador)**:
```
[SILVER] Limpeza básica: 0 linhas removidas (23 → 23)
[SILVER] Buscando transformador: utils.transformations.silver.employees
[SILVER] ✅ Aplicando transformador customizado: utils.transformations.silver.employees
[SILVER/EMPLOYEES] Iniciando transformações...
[SILVER/EMPLOYEES] Transformações concluídas
[SILVER] Parquet criado: /tmp/tmpxyz/employees.parquet
```

---

## Transformadores Já Criados

### 1. Customers (`utils/transformations/silver/customers.py`)

**Transformações**:
- `nome_completo` = firstName + lastName
- `faixa_credito` = Baixo/Médio/Alto/Premium
- `credito_brl` = valor_cliente × taxa_cambio

**Ativação**: Qualquer tabela com nome contendo "customers"
- ✅ `ingestao_customers_raw4`
- ✅ `customers`
- ✅ `customers_backup`

### 2. Orders (`utils/transformations/silver/orders.py`)

**Transformações**:
- Conversão de datas para datetime
- `dias_atraso` = shippedDate - requiredDate

**Ativação**: Tabelas com "orders" no nome

### 3. Products (`utils/transformations/silver/products.py`)

**Transformações**:
- `margem_lucro_pct` = ((MSRP - buyPrice) / buyPrice) × 100
- `faixa_preco` = Econômico/Médio/Premium/Luxo

**Ativação**: Tabelas com "products" no nome

---

## Vantagens da Arquitetura

### ✅ Zero Acoplamento
- Silver layer **não conhece** nenhuma tabela específica
- Funciona com qualquer tabela (employees, offices, payments, etc.)
- Adicionar nova tabela = criar 1 arquivo, zero mudanças no core

### ✅ Opt-in (não obrigatório)
- Tabelas sem transformador = funcionam perfeitamente (limpeza básica)
- Transformadores são opcionais, não obrigatórios

### ✅ Escalável
- Adicionar 100 tabelas = criar 100 arquivos
- Nenhuma alteração no `silver_layer.py`
- Cada transformador é independente

### ✅ Testável
- Testar transformador: import e chame `transform(df)`
- Sem dependências do Airflow/MinIO
- Unit tests simples:
```python
from utils.transformations.silver.customers import transform
import pandas as pd

df_test = pd.DataFrame({'contactFirstName': ['John'], 'contactLastName': ['Doe']})
df_result = transform(df_test)
assert 'nome_completo' in df_result.columns
```

### ✅ Manutenível
- Cada transformador é um arquivo isolado
- Alterar lógica de customers = editar 1 arquivo
- Não afeta outras tabelas

---

## Estrutura de Diretórios

```
src/dags/
├── lib/
│   ├── bronze_layer.py
│   ├── silver_layer.py          ← Core genérico (zero acoplamento)
│   ├── gold_layer.py
│   └── medallion_pipeline.py
│
└── utils/
    └── transformations/
        └── silver/
            ├── __init__.py       ← Documentação do padrão
            ├── customers.py      ← Transformador opcional
            ├── orders.py         ← Transformador opcional
            ├── products.py       ← Transformador opcional
            └── employees.py      ← (exemplo de novo transformador)
```

---

## Padrão de Nomenclatura

### Normalização Automática

O sistema remove automaticamente prefixos comuns:

| Nome da Tabela | Nome Normalizado | Módulo Buscado |
|----------------|------------------|----------------|
| `ingestao_customers_raw4` | `customers` | `silver/customers.py` |
| `raw_orders_v2` | `orders` | `silver/orders.py` |
| `bronze_products` | `products` | `silver/products.py` |
| `employees` | `employees` | `silver/employees.py` |

**Prefixos removidos automaticamente**:
- `ingestao_`
- `raw_`
- `bronze_`

---

## Validação e Debugging

### Verificar se Transformador Foi Aplicado

**Via logs do Airflow**:
```bash
docker logs $(docker ps -q -f name=airflow-scheduler) | grep SILVER

# Com transformador:
[SILVER] ✅ Aplicando transformador customizado: utils.transformations.silver.customers

# Sem transformador (genérico):
[SILVER] Nenhum transformador customizado para 'ingestao_xyz' (genérico será usado)
```

### Verificar Colunas Criadas

```python
import pandas as pd

# Ler arquivo Silver
df = pd.read_parquet('s3://lab01/silver/customers/file.parquet')

# Verificar colunas
print("Colunas:", df.columns.tolist())

# Verificar se transformações foram aplicadas
custom_columns = ['nome_completo', 'faixa_credito', 'credito_brl']
applied = [col for col in custom_columns if col in df.columns]
print(f"Transformações aplicadas: {applied}")
```

---

## Comparação: Antes vs Agora

### ❌ Antes (Acoplado)

```python
# silver_layer.py tinha dicionário hardcoded:
transformers = {
    'customers': _transform_customers,
    'orders': _transform_orders,
    'products': _transform_products,
}

# Problemas:
# - Adicionar nova tabela = editar silver_layer.py
# - Todas as transformações no mesmo arquivo (1000+ linhas)
# - Impossível testar transformações isoladamente
# - Não funciona com tabelas desconhecidas
```

### ✅ Agora (Desacoplado)

```python
# silver_layer.py é 100% genérico:
def _apply_custom_transformations(df, table_name):
    try:
        module = importlib.import_module(f"utils.transformations.silver.{table_name}")
        return module.transform(df)
    except ModuleNotFoundError:
        return df  # Funciona sem transformador!

# Vantagens:
# ✅ Adicionar nova tabela = criar 1 arquivo
# ✅ Cada transformador tem ~50 linhas
# ✅ Testar: import e chame transform()
# ✅ Funciona com QUALQUER tabela
```

---

## Referências

- **Core genérico**: `src/dags/lib/silver_layer.py`
- **Pipeline completo**: `src/dags/lib/medallion_pipeline.py`
- **Transformadores opcionais**: `src/dags/utils/transformations/silver/`
- **Documentação do padrão**: `src/dags/utils/transformations/silver/__init__.py`

## Histórico

| Data | Mudança |
|------|---------|
| 2024-11-23 | Refatoração para arquitetura 100% genérica (zero acoplamento) |
| 2024-11-23 | Transformadores movidos para módulos independentes |
| 2024-11-23 | Sistema de import dinâmico com fallback para genérico |


### Sistema Plugável

O arquivo `lib/silver_layer.py` implementa um **sistema plugável de transformações**:

```python
def _apply_table_transformations(df, table_name):
    """
    Aplica transformações específicas baseadas no nome da tabela.
    Se não houver transformação específica, retorna o DataFrame sem mudanças.
    """
    transformers = {
        'customers': _transform_customers,
        'orders': _transform_orders,
        'products': _transform_products,
    }
    
    # Busca case-insensitive
    table_lower = table_name.lower()
    for table_key, transformer_func in transformers.items():
        if table_key in table_lower:
            return transformer_func(df)
    
    return df  # Sem transformação específica
```

### Fluxo de Processamento

```
Bronze CSV
    ↓
1. Limpeza Básica
   - dropna(how='all')
   - drop_duplicates()
    ↓
2. Transformação Específica (se existir)
   - Preenchimento inteligente de nulos
   - Criação de colunas calculadas
   - Categorização
   - Enriquecimento
    ↓
3. Conversão para Parquet
   - compression='snappy'
    ↓
Silver Parquet
```

---

## Transformações por Tabela

### 1. Customers (Clientes)

#### Operações Aplicadas

| Operação | Descrição | Exemplo |
|----------|-----------|---------|
| **Normalização de Colunas** | Todas as colunas para lowercase | `CreditLimit` → `creditlimit` |
| **Preenchimento de Nulos** | Defaults inteligentes por coluna | `creditLimit = creditlimit.fillna(0)` |
| **Nome Completo** | Concatenação de primeiro e último nome | `nome_completo = firstName + ' ' + lastName` |
| **Valor do Cliente** | Cópia do limite de crédito para análise | `valor_cliente = creditLimit` |
| **Faixa de Crédito** | Categorização em 4 níveis | Baixo / Médio / Alto / Premium |
| **Conversão Cambial** | Enriquecimento com taxas de câmbio | `credito_brl = valor_cliente * taxa_cambio` |

#### Código de Exemplo

```python
def _transform_customers(df):
    import pandas as pd
    
    df.columns = df.columns.str.lower()
    
    # Preenchimento inteligente
    df['creditLimit'] = df['creditlimit'].fillna(0)
    df['state'] = df['state'].replace('', pd.NA).fillna('N/A')
    df['salesrepemployeenumber'] = df['salesrepemployeenumber'].fillna(0)
    
    # Coluna calculada: Nome completo
    df['nome_completo'] = df['contactfirstname'].str.strip() + ' ' + df['contactlastname'].str.strip()
    
    # Categorização: Faixa de crédito
    df['valor_cliente'] = df['creditLimit']
    df['faixa_credito'] = pd.cut(
        df['valor_cliente'],
        bins=[-1, 50000, 100000, 150000, float('inf')],
        labels=['Baixo', 'Médio', 'Alto', 'Premium']
    )
    
    # Enriquecimento: Conversão cambial
    taxas_cambio = {
        'USA': 5.0, 'France': 5.3, 'Germany': 5.4, 'UK': 6.2,
        'Japan': 0.035, 'Canada': 3.8, 'Australia': 3.2, 'Spain': 5.1,
        'Brazil': 1.0, 'Italy': 5.2, 'Netherlands': 5.3
    }
    df['taxa_cambio'] = df['country'].str.strip().map(taxas_cambio).fillna(5.0)
    df['credito_brl'] = df['valor_cliente'] * df['taxa_cambio']
    
    return df
```

#### Antes vs Depois

**Bronze (Entrada)**:
```csv
customerNumber,contactFirstName,contactLastName,country,creditLimit
103,Carine,Schmitt,France,21000.00
```

**Silver (Saída)**:
```csv
customernumber,contactfirstname,contactlastname,country,creditlimit,creditLimit,nome_completo,valor_cliente,faixa_credito,taxa_cambio,credito_brl
103,Carine,Schmitt,France,21000.00,21000.00,"Carine Schmitt",21000.00,Baixo,5.3,111300.00
```

**Novas Colunas Criadas**:
- `nome_completo`: "Carine Schmitt"
- `faixa_credito`: "Baixo" (< 50k)
- `taxa_cambio`: 5.3 (France)
- `credito_brl`: 111.300,00

---

### 2. Orders (Pedidos)

#### Operações Aplicadas

| Operação | Descrição | Exemplo |
|----------|-----------|---------|
| **Conversão de Datas** | String → datetime | `orderDate: '2024-01-15' → datetime64` |
| **Dias de Atraso** | Cálculo de diferença entre datas | `dias_atraso = shippedDate - requiredDate` |
| **Normalização de Status** | Remove espaços extras | `status.strip()` |

#### Código de Exemplo

```python
def _transform_orders(df):
    import pandas as pd
    
    df.columns = df.columns.str.lower()
    
    # Converter datas para datetime
    date_columns = ['orderdate', 'requireddate', 'shippeddate']
    for col in date_columns:
        if col in df.columns:
            df[col] = pd.to_datetime(df[col], errors='coerce')
    
    # Calcular dias de atraso
    if 'shippeddate' in df.columns and 'requireddate' in df.columns:
        df['dias_atraso'] = (df['shippeddate'] - df['requireddate']).dt.days
        df['dias_atraso'] = df['dias_atraso'].clip(lower=0)  # Só positivos
    
    # Normalizar status
    if 'status' in df.columns:
        df['status'] = df['status'].str.strip()
    
    return df
```

#### Antes vs Depois

**Bronze (Entrada)**:
```csv
orderNumber,orderDate,requiredDate,shippedDate,status
10100,2003-01-06,2003-01-13,2003-01-10,Shipped
```

**Silver (Saída)**:
```csv
ordernumber,orderdate,requireddate,shippeddate,status,dias_atraso
10100,2003-01-06,2003-01-13,2003-01-10,Shipped,0
```

**Nova Coluna Criada**:
- `dias_atraso`: 0 (entregue 3 dias antes)

---

### 3. Products (Produtos)

#### Operações Aplicadas

| Operação | Descrição | Exemplo |
|----------|-----------|---------|
| **Preenchimento de Preços** | Preenche nulos com 0 | `buyPrice.fillna(0)` |
| **Margem de Lucro** | Cálculo percentual | `((MSRP - buyPrice) / buyPrice) * 100` |
| **Faixa de Preço** | Categorização em 4 níveis | Econômico / Médio / Premium / Luxo |

#### Código de Exemplo

```python
def _transform_products(df):
    import pandas as pd
    
    df.columns = df.columns.str.lower()
    
    # Preencher preços nulos
    df['buyprice'] = df['buyprice'].fillna(0)
    df['msrp'] = df['msrp'].fillna(0)
    
    # Calcular margem de lucro
    if 'msrp' in df.columns and 'buyprice' in df.columns:
        df['margem_lucro_pct'] = ((df['msrp'] - df['buyprice']) / df['buyprice'] * 100).round(2)
        df['margem_lucro_pct'] = df['margem_lucro_pct'].fillna(0)
    
    # Categorizar por faixa de preço
    if 'msrp' in df.columns:
        df['faixa_preco'] = pd.cut(
            df['msrp'],
            bins=[0, 50, 100, 200, float('inf')],
            labels=['Econômico', 'Médio', 'Premium', 'Luxo']
        )
    
    return df
```

#### Antes vs Depois

**Bronze (Entrada)**:
```csv
productCode,productName,buyPrice,MSRP
S10_1678,1969 Harley Davidson Ultimate Chopper,48.81,95.70
```

**Silver (Saída)**:
```csv
productcode,productname,buyprice,msrp,margem_lucro_pct,faixa_preco
S10_1678,1969 Harley Davidson Ultimate Chopper,48.81,95.70,96.11,Médio
```

**Novas Colunas Criadas**:
- `margem_lucro_pct`: 96.11% (lucro quase 100%)
- `faixa_preco`: "Médio" (50-100)

---

## Como Adicionar Novas Transformações

### Passo 1: Criar Função de Transformação

Adicione uma nova função em `lib/silver_layer.py`:

```python
def _transform_employees(df):
    """
    Transformações para tabela de funcionários
    """
    import pandas as pd
    
    df.columns = df.columns.str.lower()
    
    # Criar nome completo
    if 'firstname' in df.columns and 'lastname' in df.columns:
        df['nome_completo'] = df['firstname'] + ' ' + df['lastname']
    
    # Calcular tempo de empresa
    if 'hiredate' in df.columns:
        df['hiredate'] = pd.to_datetime(df['hiredate'])
        hoje = pd.Timestamp.now()
        df['anos_empresa'] = ((hoje - df['hiredate']).dt.days / 365).round(1)
    
    return df
```

### Passo 2: Registrar no Mapeamento

Adicione ao dicionário `transformers` em `_apply_table_transformations()`:

```python
transformers = {
    'customers': _transform_customers,
    'orders': _transform_orders,
    'products': _transform_products,
    'employees': _transform_employees,  # <-- NOVO
}
```

### Passo 3: Testar

```bash
# Upload de arquivo employees.csv via webapp
# Executa DAG com pipeline completo
# Verifica Silver layer:
docker-compose exec minio mc ls minio/lab01/silver/employees/
```

---

## Logs de Transformação

### Exemplo de Log (Customers)

```
[SILVER] Iniciando transformação para: ingestao_customers_raw4
[SILVER] Arquivo origem: raw/ingestao_customers_raw4/20241123_abc123.csv
[SILVER] Processando: s3://lab01/bronze/ingestao_customers_raw4/20241123_abc123.csv → s3://lab01/silver/ingestao_customers_raw4/20241123_abc123.parquet
[SILVER] Arquivo Bronze baixado: /tmp/tmpxyz/20241123_abc123.csv
[SILVER] Lendo CSV...
[SILVER] Dados originais: 122 linhas, 13 colunas
[SILVER] Limpeza básica: 2 linhas removidas (122 → 120)
[SILVER] Aplicando transformador: _transform_customers
[SILVER] Iniciando transformações de customers...
[SILVER] Coluna 'nome_completo' criada
[SILVER] Coluna 'faixa_credito' criada
[SILVER] Colunas de conversão cambial criadas
[SILVER] Transformações de customers concluídas
[SILVER] Transformações específicas aplicadas para: ingestao_customers_raw4
[SILVER] Parquet criado: /tmp/tmpxyz/20241123_abc123.parquet
[SILVER] ✅ Arquivo salvo em: s3://lab01/silver/ingestao_customers_raw4/20241123_abc123.parquet
[SILVER] Processo concluído com sucesso!
```

---

## Validação de Transformações

### Verificar Colunas Criadas

```python
import pandas as pd
from minio import Minio

# Conectar ao MinIO
client = Minio(
    "localhost:9000",
    access_key="minio",
    secret_key="minio123",
    secure=False
)

# Baixar arquivo Silver
client.fget_object("lab01", "silver/customers/20241123_abc123.parquet", "/tmp/test.parquet")

# Ler e validar
df = pd.read_parquet("/tmp/test.parquet")
print("Colunas:", df.columns.tolist())
print("Novas colunas criadas:", [col for col in df.columns if col in ['nome_completo', 'faixa_credito', 'credito_brl']])
print("\nAmostra:")
print(df[['nome_completo', 'faixa_credito', 'credito_brl']].head())
```

**Saída Esperada**:
```
Colunas: ['customernumber', 'contactfirstname', 'contactlastname', 'country', 'creditlimit', 'creditLimit', 'nome_completo', 'valor_cliente', 'faixa_credito', 'taxa_cambio', 'credito_brl']
Novas colunas criadas: ['nome_completo', 'faixa_credito', 'credito_brl']

Amostra:
           nome_completo faixa_credito  credito_brl
0        Carine Schmitt         Baixo     111300.00
1           Jean King           Médio     285500.00
2    Peter Ferguson          Alto     467400.00
```

---

## Comparação: Silver Simples vs Silver Enriquecida

### Silver Simples (Antes)

- **Operações**: dropna, drop_duplicates, to_parquet
- **Valor de Negócio**: Baixo (só limpeza)
- **Colunas**: Mesmas do Bronze
- **Uso**: Necessita transformações posteriores no Power BI/SQL

### Silver Enriquecida (Agora)

- **Operações**: Limpeza + Transformações de Negócio
- **Valor de Negócio**: Alto (dados prontos para análise)
- **Colunas**: Originais + Calculadas + Categorizadas + Enriquecidas
- **Uso**: Prontos para consumo direto em dashboards

---

## Referências

- **Código Fonte**: `src/dags/lib/silver_layer.py`
- **Validação de Qualidade**: `src/dags/lib/data_quality.py`
- **Pipeline Completo**: `src/dags/lib/medallion_pipeline.py`
- **Código Legado (referência)**: `src/dags/utils/transformations/refined/customers.py`

---

## 📋 Resumo Visual - Colunas Silver

### Estrutura Final

```
Silver = Bronze (limpo) + Transformações Inteligentes + Qualidade de Dados
```

### Inventário de Colunas

| Origem | Quantidade | Exemplos |
|--------|-----------|----------|
| **Bronze (originais)** | N colunas | customerNumber, name, creditLimit, orderDate, ... |
| **Transformações automáticas** | 0 colunas | Dados transformados in-place (tipos, nulls, categorias) |
| **Qualidade de dados** | **4 colunas** | DataQualityRulesPass, DataQualityRulesFail, DataQualityRulesSkip, DataQualityEvaluationResult |

### Total

```
Total Silver = N (originais transformadas) + 4 (qualidade) colunas
```

**Exemplo**: Tabela com 13 colunas originais → Silver com **17 colunas** (13 transformadas + 4 qualidade)

---

## 🎯 Quick Reference - Validação de Qualidade

### Comandos Rápidos

```python
import pandas as pd

# Carregar Silver
df = pd.read_parquet('silver/customers/file.parquet')

# Taxa de aprovação
(df['DataQualityEvaluationResult'] == 'Passed').sum() / len(df) * 100

# Filtrar dados confiáveis
clean = df[df['DataQualityEvaluationResult'] == 'Passed']

# Linhas problemáticas
issues = df[df['DataQualityRulesFail'] > 0]

# Distribuição de qualidade
df['DataQualityRulesPass'].value_counts().sort_index()
```

### Troubleshooting

| Problema | Causa Provável | Solução |
|----------|---------------|---------|
| **Alta taxa de falha (>20%)** | Dados de origem com qualidade ruim | Revisar processo de coleta de dados |
| **Muitos nulls (Regra 1)** | Campos obrigatórios não preenchidos | Validar formulários de entrada |
| **Outliers extremos (Regra 4)** | Valores fora do esperado | Verificar se são erros ou casos reais |
| **Emails inválidos (Regra 5)** | Formato incorreto | Implementar validação no front-end |
| **Duplicatas (Regra 3)** | Múltiplas cargas do mesmo dado | Verificar processo de ingestão |

---

## 🔄 Fluxo Completo Bronze → Silver

```
┌─────────────────────────┐
│   Bronze CSV            │
│   (dados brutos)        │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ 1. Limpeza Básica       │
│   • dropna(how='all')   │
│   • drop_duplicates()   │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ 2. Transformações       │
│    Inteligentes         │
│   • Normalizar colunas  │
│   • Detectar datas      │
│   • Inferir tipos       │
│   • Criar categorias    │
│   • Preencher nulos     │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ 3. Validação Qualidade  │
│   • 5 regras aplicadas  │
│   • 4 colunas adicionadas│
│   • Métricas calculadas │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ 4. Salvar Parquet       │
│   • compression=snappy  │
│   • index=False         │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│   Silver Parquet        │
│   (dados confiáveis)    │
│   + 4 colunas qualidade │
└─────────────────────────┘
```

---

## Histórico de Mudanças

| Data | Mudança |
|------|---------|
| 2024-11-23 | Implementação inicial do sistema de transformações plugáveis |
| 2024-11-23 | Adicionadas transformações: customers, orders, products |
| 2024-11-23 | Integração com `medallion_pipeline.py` |
| 2024-11-24 | Adicionado sistema de validação de qualidade de dados (5 regras, 4 colunas) |
| 2024-11-24 | Documentação completa com dicionário de dados e exemplos práticos |
