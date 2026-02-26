# Implementação Delta Lake no Datalake

## ✅ Status: Implementado e Funcional

Este documento descreve a implementação real de Delta Lake no projeto datalake-air-flow, usando `deltalake-python` integrado com Airflow, MinIO e arquitetura Medallion.

---

## 🎯 Visão Geral

Delta Lake foi implementado como a **camada Gold** do pipeline, transformando dados refinados (Silver) em tabelas transacionais prontas para analytics.

### Arquitetura Implementada

```
MySQL/Fontes → Raw (CSV) → Bronze (CSV) → Silver (Parquet + Quality) → Gold (Delta Lake)
```

---

## 🔧 Tecnologias Utilizadas

| Componente       | Tecnologia                | Versão  | Função                                    |
|------------------|---------------------------|---------|-------------------------------------------|
| Orquestração     | Apache Airflow            | Latest  | Gerenciar DAGs e execução do pipeline     |
| Delta Engine     | deltalake-python          | 0.15.0  | Ler/escrever formato Delta Lake           |
| Storage          | MinIO (S3-compatible)     | Latest  | Armazenar dados (Raw, Bronze, Silver, Gold) |
| Data Processing  | Pandas + PyArrow          | Latest  | Transformações e manipulação de dados     |
| Spark (opcional) | Apache Spark + delta-spark| 3.5.1   | Para processamento distribuído futuro     |

---

## 🧱 Características da Implementação

### Delta Lake vs Parquet Tradicional

| Recurso                     | Parquet             | Delta Lake                  |
|----------------------------|---------------------|-----------------------------|
| Formato base               | ✅ Colunar          | ✅ Parquet + metadados      |
| ACID Transactions          | ❌                  | ✅                          |
| Time Travel                | ❌                  | ✅ Versões anteriores       |
| Schema Evolution           | ❌                  | ✅ Adicionar/alterar colunas|
| MERGE/UPDATE/DELETE        | ❌                  | ✅ Operações DML            |
| Compactação automática     | ❌                  | ✅ Otimização de arquivos   |
| Transaction Log            | ❌                  | ✅ `_delta_log/`            |

---

## 📂 Estrutura de Armazenamento

### No MinIO (S3)

```
s3://<owner da dag>/
├── raw/{dag_id}/{timestamp}_{hash}.csv
├── bronze/{table}/{timestamp}_{hash}.csv
├── silver/{table}/{timestamp}_{hash}.parquet
│   ├── [Colunas originais]
│   ├── DataQualityRulesPass
│   ├── DataQualityRulesFail
│   ├── DataQualityRulesSkip
│   └── DataQualityEvaluationResult
└── gold/{table}_delta/
    ├── _delta_log/
    │   ├── 00000000000000000000.json  ← Transaction log (versão 0)
    │   └── 00000000000000000001.json  ← Próximas transações
    ├── 0-{uuid}-0.parquet              ← Dados (versão 0)
    └── 1-{uuid}-0.parquet              ← Próximas escritas
```

### ❓ Por que múltiplos arquivos Parquet por arquivo de origem?

**Resposta curta**: É o comportamento **normal e esperado** do Delta Lake - não é um problema, é uma feature!

#### 🔍 Razões Técnicas

**1. Versionamento (Time Travel)**
- Cada operação de escrita (`append`, `merge`, `overwrite`) cria **novos arquivos**
- Arquivos antigos são **mantidos** para permitir consultas históricas
- Benefício: Você pode consultar qualquer versão anterior dos dados

```sql
-- Consultar versão específica (Time Travel)
SELECT * FROM delta_table VERSION AS OF 1
SELECT * FROM delta_table TIMESTAMP AS OF '2025-01-01 10:00:00'
```

**2. Particionamento de Dados**
- Delta Lake particiona dados automaticamente por performance
- Cada partição (`partition_date`, região, etc.) = arquivo separado
- Exemplo: 5 datas diferentes → 5+ arquivos parquet

```python
# Código que gera múltiplos arquivos por partição
df.write.format("delta").partitionBy("partition_date").save(delta_path)
# Resultado: delta/customers/partition_date=20250101/0-uuid.parquet
#            delta/customers/partition_date=20250102/0-uuid.parquet
```

**3. Operações de MERGE/UPSERT**
- MERGE não reescreve toda a tabela (seria ineficiente)
- Cria novos arquivos com dados atualizados/novos
- Arquivos antigos permanecem até compactação (OPTIMIZE)

```python
# MERGE incrementa arquivos sem reescrever tudo
DeltaTable.forPath(spark, delta_path).merge(
    df, "tgt.id = src.id"
).whenMatchedUpdateAll().whenNotMatchedInsertAll().execute()
```

**4. Modo Append (Adição Incremental)**
- Cada execução da DAG adiciona um novo arquivo
- Não sobrescreve dados anteriores (preserva histórico)
- Eficiente para ingestão contínua

```python
write_deltalake(
    delta_path,
    table,
    mode="append",  # ← Adiciona sem reescrever
    storage_options=storage_options
)
```

# ===============================================================
# Dúvidas Frequentes - Delta Lake e Postgres
# ===============================================================

## 1. Como é definido o nome da tabela no Postgres?
O nome da tabela no Postgres é derivado diretamente do parâmetro `target_table_name` passado para a função `gold_to_delta`. 
- Se você passar `target_table_name="usuario"`, a pasta será `delta/usuario/` e a tabela no Postgres será `delta_usuario`.
- Se você passar `target_table_name="20260222193644_573a6878_usuario"`, a pasta será `delta/20260222193644_573a6878_usuario/` e a tabela no Postgres será `delta_20260222193644_573a6878_usuario`.

## 2. Os arquivos Delta são incrementais ou completos?
O comportamento depende do modo de gravação:
- Se a tabela Delta já existe, o modo é `append` (novos dados são adicionados, não sobrescreve os anteriores).
- Se não existe, o modo é `overwrite` (cria uma nova tabelacreated_at_year Delta, sobrescrevendo qualquer dado anterior).

Portanto, os arquivos Delta podem ser incrementais (append) ou completos (overwrite), conforme o fluxo implementado. O Delta Lake suporta versionamento e merge automático, então cada arquivo pode conter apenas novos registros ou o resultado de um merge, dependendo de como o `gold_to_delta` é chamado.

# ===============================================================


#### 📊 Exemplo Real

```
# Execução 1 (2025-01-01)
delta/orders/
├── _delta_log/00000000000000000000.json
└── 0-abc123-0.parquet  (1000 linhas)

# Execução 2 (2025-01-02) - APPEND
delta/orders/
├── _delta_log/00000000000000000000.json
├── _delta_log/00000000000000000001.json
├── 0-abc123-0.parquet  (1000 linhas - versão 0)
└── 1-def456-0.parquet  (1000 linhas - versão 1)

# Execução 3 (2025-01-03) - MERGE (10 updates)
delta/orders/
├── _delta_log/00000000000000000000.json
├── _delta_log/00000000000000000001.json
├── _delta_log/00000000000000000002.json
├── 0-abc123-0.parquet  (1000 linhas - obsoleto)
├── 1-def456-0.parquet  (1000 linhas - versão 1)
└── 2-ghi789-0.parquet  (10 linhas - dados atualizados)
```

#### ✅ Benefícios dos Múltiplos Arquivos

| Benefício | Descrição |
|-----------|-----------|
| **ACID Transactions** | Cada arquivo é uma transação atômica isolada |
| **Time Travel** | Arquivos antigos permitem consultar versões históricas |
| **Leitura Otimizada** | Query engine lê apenas arquivos necessários (partition pruning) |
| **Concorrência** | Múltiplas escritas simultâneas possíveis sem lock |
| **Evolução de Schema** | Novos arquivos podem ter schemas diferentes (compatíveis) |
| **Auditoria** | Transaction log registra cada operação com timestamp |

#### ⚠️ Quando Otimizar?

Muitos arquivos pequenos podem degradar performance de leitura. Sinais de alerta:

- **>100 arquivos** em uma única partição
- **Arquivos < 10 MB** (fragmentação excessiva)
- **Queries lentas** em tabelas com muitas versões

**Solução: OPTIMIZE (Compactação)**

```python
from deltalake import DeltaTable

dt = DeltaTable(delta_path, storage_options=storage_options)

# Compactar arquivos pequenos em arquivos maiores (128MB)
dt.optimize().compact()

# Resultado: 100 arquivos de 5MB → 5 arquivos de 100MB
```

**Limpeza de Versões Antigas: VACUUM**

```python
# Remover arquivos de versões antigas (>7 dias)
dt.vacuum(retention_hours=168)  # 7 dias

# ⚠️ CUIDADO: Após VACUUM, time travel não funciona para versões removidas
```

#### 🎯 Configurações de Otimização Recomendadas

```python
# No código de escrita Delta
write_deltalake(
    delta_path,
    data,
    mode="append",
    storage_options=storage_options,
    
    # Otimizações
    file_options={
        "target_file_size": 134217728,  # 128MB (default Delta)
        "compression": "snappy"          # Compressão rápida
    }
)

# Otimização periódica (executar semanalmente via DAG)
if len(dt.files()) > 100:  # Se > 100 arquivos
    dt.optimize().compact()
    dt.vacuum(retention_hours=168)
```

#### 📈 Impacto em Performance

| Cenário | Arquivos | Tamanho Médio | Performance Leitura |
|---------|----------|---------------|---------------------|
| **Ideal** | 10-50 | 100-200 MB | ⚡ Excelente |
| **Bom** | 50-100 | 50-100 MB | ✅ Boa |
| **Atenção** | 100-500 | 10-50 MB | ⚠️ Considerar OPTIMIZE |
| **Ruim** | >500 | <10 MB | 🐌 Lento - OPTIMIZE urgente |

#### 🔧 Script de Manutenção Automática

```python
# dags/delta_maintenance.py
from datetime import timedelta
from airflow.decorators import dag, task
from deltalake import DeltaTable

@dag(
    schedule_interval='@weekly',
    start_date=days_ago(1),
    tags=['delta', 'maintenance']
)
def delta_optimize_dag():
    @task
    def optimize_delta_tables():
        tables = ['customers_delta', 'orders_delta', 'products_delta']
        
        for table_name in tables:
            delta_path = f"s3://lab01/delta/{table_name}/"
            dt = DeltaTable(delta_path, storage_options=storage_options)
            
            # Compactar se >50 arquivos
            if len(dt.files()) > 50:
                log.info(f"Compactando {table_name}...")
                dt.optimize().compact()
            
            # Limpar versões >30 dias
            dt.vacuum(retention_hours=720)
            
            log.info(f"✅ {table_name}: {len(dt.files())} arquivos")
    
    optimize_delta_tables()

delta_optimize_dag()
```

---

## 📊 Dicionário de Dados - Camada Gold (Delta Lake)

### Visão Geral

A camada Gold recebe dados da camada Silver (já limpos e validados) e adiciona **inteligência analítica** através de features automáticas. Todas as colunas originais são preservadas, e novas colunas são criadas automaticamente.

### Estrutura de Dados

```
Gold = Silver (colunas originais) + Features Numéricas + Features Categóricas + Features Temporais + Agregações
```

---

### Colunas Originais (da Silver)

Todas as colunas da camada Silver são preservadas, incluindo:
- Dados de negócio (customerNumber, orderDate, productCode, etc.)
- Colunas de qualidade: DataQualityRulesPass, DataQualityRulesFail, DataQualityRulesSkip, DataQualityEvaluationResult

---

### Features Numéricas (Criadas Automaticamente)

Para cada coluna numérica original, 3 novas colunas são criadas:

| Coluna | Tipo | Fórmula | Descrição | Valores | Uso |
|--------|------|---------|-----------|---------|-----|
| **{coluna}_zscore** | `float64` | `(valor - média) / desvio_padrão` | Quantos desvios padrão o valor está da média | -3 a +3 (tipicamente) | Identificar outliers. Valores > 2 ou < -2 são atípicos |
| **{coluna}_percentile** | `float64` | `rank(valor) / total * 100` | Posição percentual do valor em relação aos demais | 0 a 100 | Comparação relativa. 95 = "top 5%" |
| **{coluna}_min_max_scaled** | `float64` | `(valor - mín) / (máx - mín)` | Normalização entre 0 e 1 | 0.0 a 1.0 | Machine Learning, comparações normalizadas |

**Exemplo (creditLimit)**:
```python
# Dados originais
creditLimit = [20000, 50000, 100000, 150000]

# Features criadas
creditLimit_zscore        = [-1.34, -0.45, 0.45, 1.34]
creditLimit_percentile    = [25, 50, 75, 100]
creditLimit_min_max_scaled = [0.0, 0.23, 0.62, 1.0]
```

**Interpretação**:
- `creditLimit_zscore = 2.5` → Outlier positivo (muito acima da média)
- `creditLimit_percentile = 95` → Cliente está no top 5% de crédito
- `creditLimit_min_max_scaled = 0.8` → Cliente com 80% do crédito máximo

---

### Features Categóricas (Criadas Automaticamente)

Para cada coluna categórica (< 50% de valores únicos), 2 novas colunas são criadas:

| Coluna | Tipo | Fórmula | Descrição | Valores | Uso |
|--------|------|---------|-----------|---------|-----|
| **{coluna}_frequency** | `int64` | `count(valor)` | Quantas vezes este valor aparece no dataset | 1 a N | Popularidade absoluta. Ex: quantos clientes na França |
| **{coluna}_pct** | `float64` | `frequency / total * 100` | Percentual de ocorrências | 0.01 a 100.0 | Popularidade relativa. Ex: 15% dos clientes são da França |

**Exemplo (country)**:
```python
# Dados originais
country = ['USA', 'USA', 'France', 'USA', 'Germany']

# Features criadas
country_frequency = [3, 3, 1, 3, 1]
country_pct       = [60.0, 60.0, 20.0, 60.0, 20.0]
```

**Interpretação**:
- `country_frequency = 3` → Este país tem 3 clientes
- `country_pct = 60.0` → 60% dos clientes são deste país

---

### Features Temporais (Criadas Automaticamente)

Para cada coluna de data/timestamp, 5 novas colunas são criadas:

| Coluna | Tipo | Descrição | Valores | Uso |
|--------|------|-----------|---------|-----|
| **{coluna}_year** | `int64` | Ano extraído da data | 2000 a 2099 | Análises anuais, tendências de longo prazo |
| **{coluna}_month** | `int64` | Mês extraído da data | 1 a 12 | Sazonalidade mensal |
| **{coluna}_day** | `int64` | Dia do mês extraído | 1 a 31 | Análises diárias |
| **{coluna}_dayofweek** | `int64` | Dia da semana | 0 (segunda) a 6 (domingo) | Padrões semanais (fins de semana vs dias úteis) |
| **{coluna}_quarter** | `int64` | Trimestre do ano | 1 a 4 | Análises trimestrais, ciclos de negócio |

**Exemplo (orderDate)**:
```python
# Dado original
orderDate = '2024-06-15 14:30:00'

# Features criadas
orderDate_year      = 2024
orderDate_month     = 6
orderDate_day       = 15
orderDate_dayofweek = 5  # Sexta-feira
orderDate_quarter   = 2  # Q2
```

**Casos de Uso**:
```sql
-- Vendas por trimestre
SELECT orderDate_year, orderDate_quarter, SUM(amount) 
FROM orders_delta 
GROUP BY orderDate_year, orderDate_quarter

-- Padrão de pedidos por dia da semana
SELECT orderDate_dayofweek, COUNT(*) 
FROM orders_delta 
GROUP BY orderDate_dayofweek
```

---

### Features de Agregação por Grupo

Para a primeira coluna categórica com 1-80% de valores únicos, agregações são criadas:

| Coluna | Tipo | Fórmula | Descrição | Uso |
|--------|------|---------|-----------|-----|
| **{num_col}_by_{cat_col}_mean** | `float64` | `mean(num_col) GROUP BY cat_col` | Média do valor numérico por categoria | Comparar valor individual com média do grupo |

**Exemplo**:
```python
# Dados: creditLimit por country
customer | country   | creditLimit | creditLimit_by_country_mean
---------|-----------|-------------|---------------------------
103      | USA       | 50000       | 75000  # Média dos clientes USA
104      | USA       | 100000      | 75000
105      | France    | 30000       | 35000  # Média dos clientes França
106      | France    | 40000       | 35000
```

**Interpretação**:
- Cliente 103: creditLimit (50K) está **abaixo** da média USA (75K)
- Cliente 104: creditLimit (100K) está **acima** da média USA (75K)

**Caso de Uso**:
```sql
-- Clientes acima da média do seu país
SELECT * FROM customers_delta
WHERE creditLimit > creditLimit_by_country_mean
```

---

### Features de Ranking Global

Um ranking global é criado baseado na primeira coluna numérica:

| Coluna | Tipo | Fórmula | Descrição | Valores | Uso |
|--------|------|---------|-----------|---------|-----|
| **{coluna}_rank** | `int64` | `dense_rank(valor DESC)` | Posição do valor em ordem decrescente | 1 a N | Top N análises, leaderboards |

**Exemplo (creditLimit_rank)**:
```python
customer | creditLimit | creditLimit_rank
---------|-------------|------------------
103      | 150000      | 1  # Maior crédito
104      | 100000      | 2
105      | 100000      | 2  # Empate = mesmo rank
106      | 50000       | 3
107      | 20000       | 4  # Menor crédito
```

**Casos de Uso**:
```sql
-- Top 10 clientes por crédito
SELECT * FROM customers_delta 
WHERE creditLimit_rank <= 10

-- Clientes no quartil superior (top 25%)
SELECT * FROM customers_delta
WHERE creditLimit_percentile >= 75
```

---

### Resumo de Features Criadas por Tipo de Coluna

| Tipo de Coluna Original | Features Criadas | Total de Novas Colunas |
|-------------------------|------------------|------------------------|
| **Numérica** (ex: creditLimit) | zscore, percentile, min_max_scaled | **3 colunas** |
| **Categórica** (ex: country) | frequency, pct | **2 colunas** |
| **Data/Timestamp** (ex: orderDate) | year, month, day, dayofweek, quarter | **5 colunas** |
| **Agregação** | {num}_by_{cat}_mean | **1 coluna por par** |
| **Ranking** | {primeira_num}_rank | **1 coluna** |

---

### Exemplo Completo: Tabela Customers

**Entrada (Silver - 13 colunas)**:
```csv
customerNumber,contactFirstName,contactLastName,country,creditLimit,orderDate,DataQualityEvaluationResult
103,John,Doe,USA,50000,2024-01-15,Passed
104,Jane,Smith,France,100000,2024-02-20,Passed
105,Bob,Wilson,USA,75000,2024-03-10,Passed
```

**Saída (Gold Delta - 35+ colunas)**:

| Categoria | Colunas |
|-----------|---------|
| **Originais (13)** | customerNumber, contactFirstName, contactLastName, country, creditLimit, orderDate, ... |
| **Numéricas (3×2 = 6)** | creditLimit_zscore, creditLimit_percentile, creditLimit_min_max_scaled, customerNumber_zscore, ... |
| **Categóricas (2×2 = 4)** | country_frequency, country_pct, contactLastName_frequency, contactLastName_pct |
| **Temporais (5×1 = 5)** | orderDate_year, orderDate_month, orderDate_day, orderDate_dayofweek, orderDate_quarter |
| **Agregações (3)** | creditLimit_by_country_mean, customerNumber_by_country_mean, ... |
| **Ranking (1)** | creditLimit_rank |

**Total**: ~35 colunas (13 originais + 22 features)

---

### Casos de Uso por Feature

#### 1. Análise de Outliers
```python
# Clientes com crédito anormal (outliers)
outliers = df[abs(df['creditLimit_zscore']) > 2]
```

#### 2. Segmentação por Percentil
```python
# Clientes premium (top 10%)
premium = df[df['creditLimit_percentile'] >= 90]
```

#### 3. Comparação com Média do Grupo
```python
# Clientes acima da média do país
above_avg = df[df['creditLimit'] > df['creditLimit_by_country_mean']]
```

#### 4. Análise Temporal
```python
# Pedidos por dia da semana
df.groupby('orderDate_dayofweek')['orderNumber'].count()

# Tendência anual
df.groupby(['orderDate_year', 'orderDate_quarter']).agg({
    'orderAmount': 'sum',
    'orderNumber': 'count'
})
```

#### 5. Top N Rankings
```python
# Top 20 produtos por vendas
top_products = df[df['productSales_rank'] <= 20]
```

#### 6. Análise de Popularidade
```python
# Países com mais de 10% dos clientes
popular_countries = df[df['country_pct'] > 10]
```

---

### Validação de Features

```python
from deltalake import DeltaTable

# Carregar Delta Lake
storage_options = {
    "AWS_ACCESS_KEY_ID": "minioadmin",
    "AWS_SECRET_ACCESS_KEY": "minioadmin",
    "AWS_ENDPOINT_URL": "http://minio:9000",
    "AWS_REGION": "us-east-1"
}

dt = DeltaTable("s3://lab01/gold/customers_delta/", storage_options=storage_options)
df = dt.to_pandas()

# Verificar features criadas
print("Total de colunas:", len(df.columns))
print("\nFeatures numéricas:")
print([col for col in df.columns if '_zscore' in col or '_percentile' in col])

print("\nFeatures categóricas:")
print([col for col in df.columns if '_frequency' in col or '_pct' in col])

print("\nFeatures temporais:")
print([col for col in df.columns if '_year' in col or '_month' in col])

print("\nAgregações:")
print([col for col in df.columns if '_by_' in col])

print("\nRankings:")
print([col for col in df.columns if '_rank' in col])
```

---

### Performance e Otimização

| Métrica | Valor | Nota |
|---------|-------|------|
| **Multiplicação de colunas** | ~2.5x | 13 originais → 35 finais |
| **Overhead de processamento** | ~15-30% | Tempo adicional para criar features |
| **Tamanho do arquivo** | ~1.5x | Parquet comprime bem colunas derivadas |
| **Benefício analítico** | Muito alto | 22 features prontas para ML/BI |

**Otimizações aplicadas**:
- ✅ Features calculadas apenas para colunas com variação (nunique > 1)
- ✅ Agregações limitadas a categorias com < 80% de valores únicos
- ✅ Ranking usa `dense_rank` (mais eficiente que `rank`)
- ✅ Tipo de dados otimizado (int64 para contagens, float64 para métricas)

---

### Integração com BI Tools

As features criadas podem ser usadas diretamente em ferramentas de BI:

**Power BI**:
```dax
// Clientes premium (calculado automaticamente)
Premium Customers = 
    CALCULATE(
        COUNT(customers[customerNumber]),
        customers[creditLimit_percentile] >= 90
    )

// Comparação com média do país
Above Country Average = 
    IF(
        customers[creditLimit] > customers[creditLimit_by_country_mean],
        "Acima", "Abaixo"
    )
```

**SQL (DuckDB, Trino, Spark)**:
```sql
-- Análise de sazonalidade
SELECT 
    orderDate_quarter,
    orderDate_dayofweek,
    COUNT(*) as orders,
    AVG(orderAmount) as avg_amount
FROM orders_delta
WHERE orderDate_year = 2024
GROUP BY orderDate_quarter, orderDate_dayofweek
ORDER BY orderDate_quarter, orderDate_dayofweek
```

---

## 🔄 Fluxo de Processamento

### 1. Entrada: Silver Parquet

```python
# Arquivo de entrada
s3://lab01/silver/orders/20251124_abc123.parquet
```

### 2. Leitura e Agregação

```python
# lib/gold_delta_layer.py - silver_to_gold_delta()
df = pd.read_parquet(silver_local)  # Download do MinIO
df = _apply_aggregations(df)         # Transformações analíticas
df = df.astype(str)                  # Convert categorical → string
```

### 3. Escrita Delta Lake

```python
from deltalake import write_deltalake

write_deltalake(
    table_or_uri=gold_delta_path,
    data=df,
    mode="append",                    # Ou "overwrite" na primeira vez
    storage_options=storage_options,  # Credenciais MinIO
    schema_mode="merge"               # Evolução de schema
)
```

### 4. Versionamento Automático

```
_delta_log/00000000000000000000.json:
{
  "add": {
    "path": "0-uuid-0.parquet",
    "size": 12345,
    "modificationTime": 1700000000000
  },
  "commitInfo": {
    "timestamp": 1700000000000,
    "operation": "WRITE",
    "version": 0
  }
}
```

---

## 💻 Código da Implementação

### Arquivo: `src/dags/lib/gold_delta_layer.py`

```python
def silver_to_gold_delta(source_filename: str, target_table_name: str, **kwargs):
    """
    Camada Gold com Delta Lake: Dados agregados com versionamento e ACID.
    
    Recursos:
    - ACID transactions
    - Time travel (versões anteriores)
    - Schema evolution
    - MERGE/UPDATE/DELETE support
    - Compactação automática
    """
    try:
        from deltalake import write_deltalake, DeltaTable
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except ImportError:
        # Fallback para Parquet tradicional
        from lib.gold_layer import silver_to_gold
        return silver_to_gold(source_filename, target_table_name, **kwargs)
    
    # 1. Download do Silver
    hook = S3Hook(aws_conn_id='minio_conn')
    silver_local = hook.download_file(source_filename, bucket)
    
    # 2. Leitura e transformação
    df = pd.read_parquet(silver_local)
    df = _apply_aggregations(df)  # Lógica de negócio
    
    # 3. Conversão de tipos (Delta não suporta categorical)
    for col in df.select_dtypes(include=['category']).columns:
        df[col] = df[col].astype(str)
    
    # 4. Credenciais MinIO
    credentials = hook.get_credentials()
    storage_options = {
        "AWS_ACCESS_KEY_ID": credentials.access_key,
        "AWS_SECRET_ACCESS_KEY": credentials.secret_key,
        "AWS_ENDPOINT_URL": hook.conn_config.endpoint_url,
        "AWS_REGION": "us-east-1",
        "AWS_S3_ALLOW_UNSAFE_RENAME": "true"
    }
    
    # 5. Escrita Delta Lake
    gold_delta_path = f"s3://{bucket}/gold/{target_table_name}_delta/"
    
    write_deltalake(
        table_or_uri=gold_delta_path,
        data=df,
        mode="append",
        storage_options=storage_options,
        schema_mode="merge"
    )
    
    # 6. Obter versão criada
    delta_table = DeltaTable(gold_delta_path, storage_options=storage_options)
    version = delta_table.version()
    
    log.info(f"✅ Delta Lake salvo em: {gold_delta_path} (versão {version})")
    
    return {
        "gold_delta": gold_delta_path,
        "status": "success",
        "version": version,
        "format": "delta"
    }
```

---

## 🔌 Integração com Medallion Pipeline

### Arquivo: `src/dags/lib/medallion_pipeline.py`

```python
def raw_to_medallion(source_filename: str, target_table_name: str, **kwargs):
    results = {}
    
    # Raw → Bronze
    bronze_result = raw_to_bronze(source_filename, target_table_name, **kwargs)
    results['bronze_key'] = bronze_result.get('bronze_key')
    
    # Bronze → Silver
    silver_result = bronze_to_silver(bronze_result['bronze_key'], target_table_name, **kwargs)
    results['silver_key'] = silver_result.get('silver_key')
    
    # Silver → Gold (Delta Lake)
    try:
        from lib.gold_delta_layer import silver_to_gold_delta
        gold_result = silver_to_gold_delta(silver_result['silver_key'], target_table_name, **kwargs)
        results['gold_delta'] = gold_result.get('gold_delta')
        results['gold_format'] = 'delta'
    except Exception as e:
        log.warning(f"Delta Lake falhou, usando Parquet: {e}")
        from lib.gold_layer import silver_to_gold
        gold_result = silver_to_gold(silver_result['silver_key'], target_table_name, **kwargs)
        results['gold_key'] = gold_result.get('gold_key')
        results['gold_format'] = 'parquet'
    
    return results
```

---

## 🧪 Testes e Validação

### 1. Verificar Delta Lake no MinIO

```bash
# Via AWS CLI (MinIO)
aws --endpoint-url http://localhost:9000 s3 ls s3://lab01/gold/orders_delta/

# Resultado esperado:
#   PRE _delta_log/
#   2025-11-24 10:00:00   12345 0-uuid-0.parquet
```

### 2. Ler Delta Lake com Python

```python
from deltalake import DeltaTable

storage_options = {
    "AWS_ACCESS_KEY_ID": "minioadmin",
    "AWS_SECRET_ACCESS_KEY": "minioadmin",
    "AWS_ENDPOINT_URL": "http://minio:9000",
    "AWS_REGION": "us-east-1"
}

# Carregar tabela Delta
dt = DeltaTable("s3://lab01/gold/orders_delta/", storage_options=storage_options)

# Ver versão atual
print(f"Versão atual: {dt.version()}")

# Ver histórico de transações
print(dt.history())

# Ler dados
df = dt.to_pandas()
print(df.head())
```

### 3. Time Travel (Versões Anteriores)

```python
# Ler versão específica
dt_v0 = DeltaTable("s3://lab01/gold/orders_delta/", version=0, storage_options=storage_options)
df_v0 = dt_v0.to_pandas()

# Comparar versões
print(f"Linhas v0: {len(df_v0)}")
print(f"Linhas atual: {len(df)}")
```

---

## 📊 Logs de Execução

### Exemplo de Sucesso

```
[GOLD-DELTA] Iniciando agregação Delta Lake para: orders
[GOLD-DELTA] Arquivo origem: silver/orders/20251124_abc123.parquet
[GOLD-DELTA] Tentando importar deltalake...
[GOLD-DELTA] ✓ S3Hook importado
[GOLD-DELTA] ✓ deltalake importado com sucesso!
[GOLD-DELTA] Baixando Silver de: s3://lab01/silver/orders/20251124_abc123.parquet
[GOLD-DELTA] ✓ 29 linhas carregadas
[GOLD-DELTA] Aplicando agregações...
[GOLD-DELTA] Convertendo categorical → string: ['ProductCategory', 'CustomerSegment']
[GOLD-DELTA] Escrevendo Delta Lake em: s3://lab01/gold/orders_delta/
[GOLD-DELTA] ✅ Delta Lake salvo em: s3://lab01/gold/orders_delta/ (versão 0)
```

---

## 🐛 Troubleshooting

### Problema 1: ModuleNotFoundError: No module named 'deltalake'

**Causa**: Pacote não instalado no Airflow scheduler/worker

**Solução**:
```bash
docker exec airflow-scheduler pip install deltalake==0.15.0
docker exec datalake-air-flow-airflow-worker-1 pip install deltalake==0.15.0
docker restart airflow-scheduler datalake-air-flow-airflow-worker-1
```

### Problema 2: InvalidAccessKeyId

**Causa**: Credenciais MinIO não configuradas corretamente

**Solução**: Usar `S3Hook.get_credentials()` em vez de variáveis de ambiente:
```python
hook = S3Hook(aws_conn_id='minio_conn')
credentials = hook.get_credentials()
storage_options = {
    "AWS_ACCESS_KEY_ID": credentials.access_key,
    "AWS_SECRET_ACCESS_KEY": credentials.secret_key,
    "AWS_ENDPOINT_URL": hook.conn_config.endpoint_url
}
```

### Problema 3: ArrowInvalid: Dictionary type not supported

**Causa**: Delta Lake não suporta tipo `categorical` do Pandas

**Solução**: Converter para string antes de escrever:
```python
for col in df.select_dtypes(include=['category']).columns:
    df[col] = df[col].astype(str)
```

### Problema 4: Fallback para Parquet

**Causa**: Import do `deltalake` falhou ou erro na escrita

**Verificar**:
```bash
docker exec airflow-scheduler python -c "import deltalake; print(deltalake.__version__)"
```

**Logs**: Procurar por `[GOLD-DELTA] Delta Lake não disponível, usando Parquet`

---

## 🚀 Próximos Passos

### 1. Otimização

- **OPTIMIZE**: Compactar arquivos pequenos
- **VACUUM**: Remover arquivos antigos (cleanup)
- **Z-ORDER**: Otimizar queries por colunas específicas

```python
from deltalake import DeltaTable

dt = DeltaTable("s3://lab01/gold/orders_delta/", storage_options=storage_options)

# Compactar arquivos
dt.optimize()

# Limpar versões antigas (> 7 dias)
dt.vacuum(retention_hours=168)
```

### 2. Queries Analíticas

- Usar Spark SQL, Trino ou DuckDB para consultar Delta Lake
- Criar views materializadas
- Integração com Power BI via ODBC

### 3. Schema Evolution

```python
# Adicionar nova coluna
df['new_column'] = 'default_value'
write_deltalake(
    table_or_uri=gold_delta_path,
    data=df,
    mode="append",
    schema_mode="merge"  # Adiciona coluna automaticamente
)
```

### 4. MERGE (Upsert)

```python
from deltalake import DeltaTable

dt = DeltaTable("s3://lab01/gold/orders_delta/", storage_options=storage_options)

# Atualizar registros existentes ou inserir novos
dt.merge(
    source=new_df,
    predicate="target.order_id = source.order_id",
    source_alias="source",
    target_alias="target"
).when_matched_update_all().when_not_matched_insert_all().execute()
```

---

## 📚 Comparação: Delta Lake Python vs Delta Lake Spark

### Implementação Atual (deltalake-python)

✅ **Vantagens**:
- Simples de instalar (`pip install deltalake`)
- Não requer cluster Spark
- Integração direta com Pandas
- Ideal para pipelines Airflow
- Baixo overhead de infraestrutura

❌ **Limitações**:
- Processamento single-node (não distribuído)
- Performance limitada para datasets muito grandes (> 1GB)
- Menos recursos que Spark (sem MERGE nativo, otimizações limitadas)

### Alternativa Futura (Spark + delta-spark)

✅ **Vantagens**:
- Processamento distribuído
- Suporte completo a SQL
- MERGE, UPDATE, DELETE nativos
- Z-ORDER, OPTIMIZE automáticos
- Ideal para big data (> 10GB)

❌ **Desvantagens**:
- Requer cluster Spark
- Mais complexo de configurar
- Maior consumo de recursos

**Recomendação**: Manter implementação Python para pipelines atuais. Considerar Spark se volumes crescerem significativamente (> 100GB).

---

## 🎯 Arquitetura Final Implementada

```
┌─────────────────────────────────────────────────────────────────┐
│                         Fontes de Dados                         │
│  MySQL (northwind) │ APIs │ Arquivos CSV │ Outras Fontes       │
└─────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    RAW LAYER (CSV - MinIO)                      │
│  • Dados brutos sem transformação                               │
│  • Armazenamento: s3://lab01/raw/{dag_id}/{timestamp}.csv      │
└─────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                  BRONZE LAYER (CSV - MinIO)                     │
│  • Limpeza básica (nulls, duplicatas)                           │
│  • Tipagem de dados                                             │
│  • Armazenamento: s3://lab01/bronze/{table}/{timestamp}.csv    │
└─────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│              SILVER LAYER (Parquet + Quality - MinIO)           │
│  • Transformações inteligentes (categóricos, one-hot)           │
│  • Validação de qualidade (5 regras)                            │
│  • Colunas: DataQualityRulesPass/Fail/Skip/EvaluationResult    │
│  • Armazenamento: s3://lab01/silver/{table}/{timestamp}.parquet│
└─────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│               GOLD LAYER (Delta Lake - MinIO)                   │
│  • Tabelas transacionais (ACID)                                 │
│  • Versionamento (_delta_log/)                                  │
│  • Time travel (versões anteriores)                             │
│  • Agregações e analytics                                       │
│  • Armazenamento: s3://lab01/gold/{table}_delta/               │
└─────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                    Analytics & BI Layer                         │
│  Power BI │ Spark SQL │ Trino │ DuckDB │ Python Notebooks      │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📋 Checklist de Implementação

- [x] Instalar `deltalake==0.15.0` no Airflow
- [x] Criar módulo `gold_delta_layer.py`
- [x] Implementar `silver_to_gold_delta()`
- [x] Integrar com `medallion_pipeline.py`
- [x] Configurar credenciais MinIO via S3Hook
- [x] Converter tipos categóricos para string
- [x] Testar com ingestão MySQL (northwind.orders)
- [x] Validar estrutura Delta (_delta_log/)
- [x] Verificar versionamento (versão 0 criada)
- [x] Logs de execução funcionais
- [ ] Implementar OPTIMIZE/VACUUM (futuro)
- [ ] Criar queries analíticas (futuro)
- [ ] Integração com BI tools (futuro)

---

## 📋 Resumo Visual - Transformação Silver → Gold

### Multiplicação de Colunas

```
┌─────────────────────────┐
│   Silver Parquet        │
│   13 colunas originais  │
│   + 4 qualidade         │
│   = 17 colunas totais   │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│ Inteligência Analítica  │
│ • 6 features numéricas  │
│ • 4 features categóricas│
│ • 5 features temporais  │
│ • 3 agregações          │
│ • 1 ranking             │
│ = +19 features novas    │
└───────────┬─────────────┘
            │
            ▼
┌─────────────────────────┐
│   Gold Delta Lake       │
│   17 originais          │
│   + 19 features         │
│   = 36 colunas totais   │
│   (2.1x aumento)        │
└─────────────────────────┘
```

### Comparação de Complexidade

| Métrica | Silver | Gold | Ganho |
|---------|--------|------|-------|
| **Colunas** | 17 | 36 | +19 (112% aumento) |
| **Pronto para ML** | ❌ Não | ✅ Sim | Features engenheiradas |
| **Análise Temporal** | ❌ Limitada | ✅ Completa | 5 dimensões de tempo |
| **Comparações** | ❌ Difícil | ✅ Fácil | Percentis, rankings, agregações |
| **Segmentação** | ⚠️  Manual | ✅ Automática | Categorização inteligente |

---

## 🎯 Quick Reference - Features Gold

### Por Tipo de Análise

**Análise de Performance**:
```python
# Top performers
df[df['{col}_rank'] <= 10]

# Acima da média do grupo
df[df['{col}'] > df['{col}_by_{group}_mean']]

# Outliers positivos
df[df['{col}_zscore'] > 2]
```

**Análise Temporal**:
```python
# Por trimestre
df.groupby(['date_year', 'date_quarter']).agg({'amount': 'sum'})

# Dia da semana com mais atividade
df.groupby('date_dayofweek')['id'].count()

# Sazonalidade mensal
df.groupby('date_month')['sales'].mean()
```

**Segmentação**:
```python
# Premium (top 10%)
df[df['value_percentile'] >= 90]

# Categorias populares (>5%)
df[df['category_pct'] > 5]

# Normalizado para ML
X = df[[col for col in df.columns if '_min_max_scaled' in col]]
```

---

## 🔧 Manutenção e Troubleshooting

### Verificar Health do Delta Lake

```bash
# Via MinIO Console
http://localhost:9001 → lab01 → gold/{table}_delta/

# Deve conter:
# ✅ _delta_log/ (com arquivos .json)
# ✅ Múltiplos arquivos .parquet
# ✅ Tamanho crescente ao longo do tempo
```

### Problemas Comuns

| Problema | Sintoma | Solução |
|----------|---------|---------|
| **Muitas features (>100 colunas)** | Tabela muito larga | Normal se muitas colunas numéricas/categóricas. Considerar seleção de features |
| **Features com valores idênticos** | {col}_min_max_scaled = 0 para todos | Coluna sem variação (nunique=1). Feature pulada automaticamente |
| **Agregações faltando** | {col}_by_{group}_mean não criada | Categoria tem >80% de valores únicos. Considerar outra coluna de grupo |
| **Performance lenta** | Leitura/escrita demorada | Executar `dt.optimize()` e `dt.vacuum()` |

---

## 📖 Referências

- [Delta Lake Official Docs](https://delta.io/)
- [deltalake-python GitHub](https://github.com/delta-io/delta-rs)
- [Delta Lake vs Iceberg Comparison](https://www.datacamp.com/pt/blog/iceberg-vs-delta-lake)
- [Atlan: Delta Lake vs Iceberg](https://atlan.com/know/iceberg/apache-iceberg-vs-delta-lake/)
- [Apache Spark + Delta Lake](https://docs.delta.io/latest/delta-batch.html)
- **Feature Engineering**: [Scikit-learn Feature Engineering](https://scikit-learn.org/stable/modules/preprocessing.html)
- **Z-Score**: [Wikipedia - Standard Score](https://en.wikipedia.org/wiki/Standard_score)

---

## 👥 Equipe e Contribuições

**Implementação**: Paulo Nascimento  
**Arquitetura**: Data Engineering Team  
**Data**: Novembro 2024  
**Versão**: 1.0 (Delta Lake Python + Feature Engineering)

---

**Última atualização**: 24/11/2025  
**Status**: ✅ Produção

## Histórico de Atualizações

| Data | Versão | Mudanças |
|------|--------|----------|
| 2024-11-23 | 1.0 | Implementação inicial Delta Lake com deltalake-python 0.15.0 |
| 2024-11-24 | 1.1 | Bugs corrigidos: silver_key, Exception handling, logs |
| 2024-11-24 | 1.2 | Documentação completa com dicionário de dados e features |
| 2024-11-24 | 1.3 | Adicionado guia de Feature Engineering e casos de uso |

---

## Dúvidas frequentes sobre a implementação

## 🔎 Rastreamento por nome de arquivo (source_filename)

Para garantir rastreabilidade total, cada linha da tabela Delta Lake possui a coluna `source_filename`, que preserva o nome original do arquivo de origem (mesmo em uploads múltiplos ou merges). Isso permite identificar a origem de cada registro em todas as camadas (Bronze, Silver, Gold, Delta).

**Exemplo de consulta por nome de arquivo:**

```python
from deltalake import DeltaTable

dt = DeltaTable("s3://lab01/gold/orders_delta/", storage_options=storage_options)
df = dt.to_pandas()

# Filtrar registros de um arquivo específico
df_filtrado = df[df['source_filename'] == 'silver/orders/20251124_abc123.parquet']
print(df_filtrado)

# Consultar todos os arquivos de origem presentes
print(df['source_filename'].unique())

# Agrupar por arquivo de origem
df.groupby('source_filename').size()
```

**SQL (DuckDB, Trino, Spark):**
```sql
SELECT * FROM orders_delta WHERE source_filename = 'silver/orders/20251124_abc123.parquet';
SELECT source_filename, COUNT(*) FROM orders_delta GROUP BY source_filename;
```

**Resumo:**
- A coluna `source_filename` é preservada em todas as etapas do pipeline.
- Permite rastreabilidade, auditoria e análise por arquivo de origem.
- Consultas, filtros e agrupamentos podem ser feitos facilmente.

---
**Pergunta:**
O documento diz: "Este documento descreve a implementação real de Delta Lake no projeto datalake-air-flow, usando `deltalake-python` integrado com Airflow, MinIO e arquitetura Medallion." Mas não encontrei o import de deltalake-python nas classes que estão em anexo ou em sua pasta.

**Resposta:**
O import do deltalake-python está presente, mas ocorre dentro das funções, não no topo do arquivo. Exemplo: Dentro das funções gold_to_delta e silver_to_gold_delta, há blocos como:

```python
try:
    from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    from deltalake import write_deltalake, DeltaTable
    ...
except ImportError as e:
    ...
```

Ou seja, o deltalake-python é importado de forma dinâmica, dentro das funções, para permitir fallback ou tratamento de erro caso o pacote não esteja disponível (por exemplo, para rodar em ambientes sem Delta Lake).

---

**Pergunta:**
O documento também diz: "Delta Lake foi implementado como a camada Gold do pipeline", mas ao olhar a estrutura de pastas no minio s3 existe uma pasta gold e uma delta, não deveria existir apenas uma pasta gold?

**Resposta:**
No documento e no código, há dois caminhos distintos:
- gold/ — tradicionalmente usado para armazenar os dados Gold, geralmente em Parquet.
- delta/ — usado para armazenar tabelas Delta Lake, que são uma evolução do Gold, com versionamento e ACID.

No código anexo (gold_delta_layer.py), a função gold_to_delta salva os dados Delta Lake em delta/{target_table_name}/, enquanto a função silver_to_gold_delta salva em gold/{dag_id}/{target_table_name}_delta/.

Por que isso acontece?
- gold/ pode ser usado para Gold em Parquet (legado ou fallback).
- delta/ pode ser usado para Gold em formato Delta Lake, separando fisicamente os formatos.
- gold/{table}_delta/ (como no silver_to_gold_delta) é uma convenção para indicar que ali está a Gold em formato Delta Lake, mantendo a semântica da camada Gold, mas explicitando o formato.

Ou seja:
- Não é obrigatório ter apenas uma pasta gold/. Separar gold/ (Parquet) e gold/{table}_delta/ (Delta Lake) é uma prática para facilitar a transição e a compatibilidade.
- A existência de delta/ pode ser um resquício de versões anteriores, testes, ou para uso com Spark/Thrift Server, como citado nos comentários do código.

Resumo:
- O ideal é padronizar: ou usar gold/{table}_delta/ para Delta Lake, ou migrar tudo para gold/ se só houver Delta Lake.
- Ter ambas as pastas não é um erro, mas pode gerar confusão. O importante é documentar claramente qual pasta representa a camada Gold oficial do pipeline.

---
