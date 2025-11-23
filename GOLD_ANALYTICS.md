# Camada Gold - Inteligência Analítica Automática

## Visão Geral

A camada **Gold** transforma dados limpos (Silver) em **dados analíticos prontos para consumo**, criando automaticamente dezenas de métricas, agregações e features sem configuração manual.

## Princípio: Analytics-Ready Data

Dados Gold são **otimizados para análise**, não para armazenamento. Cada coluna adicional criada serve para:
- Acelerar análises em BI (Power BI, Tableau)
- Facilitar Machine Learning
- Responder perguntas de negócio diretamente

---

## Métricas Criadas Automaticamente

### 1. **Métricas Numéricas Globais**

Para cada coluna numérica (ex: `creditlimit`, `price`, `quantity`):

| Métrica | Descrição | Exemplo |
|---------|-----------|---------|
| `{col}_percentile` | Posição percentual no dataset (0-100) | `creditlimit=75000` → `percentile=85` (top 15%) |
| `{col}_zscore` | Desvios padrão da média | `price=100` (média=50, std=25) → `zscore=2.0` |
| `{col}_is_outlier` | Flag se é outlier (zscore > 3) | `zscore=4.5` → `is_outlier=1` |

**Exemplo prático**:
```python
# Entrada Silver:
creditlimit: [50000, 75000, 100000, 25000]

# Saída Gold adiciona:
creditlimit_percentile: [50.0, 75.0, 100.0, 25.0]
creditlimit_zscore: [-0.5, 0.5, 1.5, -1.5]
creditlimit_is_outlier: [0, 0, 0, 0]
```

**Uso em BI**:
```sql
-- Power BI: Encontrar top 10% de clientes por crédito
SELECT * FROM gold.customers 
WHERE creditlimit_percentile > 90
```

---

### 2. **Análise Categórica**

Para cada coluna categórica (ex: `country`, `status`, `category`):

| Métrica | Descrição | Exemplo |
|---------|-----------|---------|
| `{col}_frequency` | Quantas vezes aparece no dataset | `country=USA` → `frequency=48` |
| `{col}_pct` | Percentual do total | `country=USA` (48/122) → `pct=39.34` |
| `{col}_is_top` | Flag se é categoria mais comum | `country=USA` (mais comum) → `is_top=1` |

**Exemplo prático**:
```python
# Entrada Silver:
country: ['USA', 'USA', 'France', 'USA', 'Germany']

# Saída Gold adiciona:
country_frequency: [3, 3, 1, 3, 1]
country_pct: [60.0, 60.0, 20.0, 60.0, 20.0]
country_is_top: [1, 1, 0, 1, 0]
```

**Uso em BI**:
```sql
-- Power BI: Análise de concentração de mercado
SELECT country, AVG(country_pct) as market_share
FROM gold.customers
GROUP BY country
ORDER BY market_share DESC
```

---

### 3. **Features Temporais (Time-Series)**

Para cada coluna de data (ex: `orderdate`, `hiredate`):

| Feature | Descrição | Uso Analítico |
|---------|-----------|---------------|
| `{col}_year` | Ano (2024) | Análise anual |
| `{col}_month` | Mês (1-12) | Sazonalidade |
| `{col}_quarter` | Trimestre (1-4) | Análise trimestral |
| `{col}_day_of_week` | Dia da semana (0=Monday, 6=Sunday) | Padrões semanais |
| `{col}_week_of_year` | Semana do ano (1-52) | Análise semanal |
| `{col}_is_weekend` | Flag se é fim de semana | Padrões de comportamento |
| `{col}_is_month_start` | Flag se é início do mês | Eventos periódicos |
| `{col}_is_month_end` | Flag se é fim do mês | Fechamento |
| `{col}_is_quarter_start` | Flag se é início de trimestre | Planejamento |
| `{col}_is_quarter_end` | Flag se é fim de trimestre | Relatórios |
| `{col}_days_since_epoch` | Dias desde 1970-01-01 | Cálculos de intervalo |

**Exemplo prático**:
```python
# Entrada Silver:
orderdate: ['2024-01-15', '2024-03-31', '2024-12-25']

# Saída Gold adiciona:
orderdate_year: [2024, 2024, 2024]
orderdate_month: [1, 3, 12]
orderdate_quarter: [1, 1, 4]
orderdate_day_of_week: [0, 6, 2]  # Monday, Sunday, Wednesday
orderdate_is_weekend: [0, 1, 0]
orderdate_is_quarter_end: [0, 1, 0]
```

**Uso em BI**:
```sql
-- Power BI: Vendas por dia da semana
SELECT orderdate_day_of_week, SUM(total) as total_sales
FROM gold.orders
GROUP BY orderdate_day_of_week

-- Machine Learning: Prever vendas considerando fim de trimestre
SELECT orderdate_is_quarter_end, AVG(quantity) as avg_quantity
FROM gold.orders
GROUP BY orderdate_is_quarter_end
```

---

### 4. **Agregações por Grupo**

Quando há colunas categóricas + numéricas, cria automaticamente:

| Métrica | Descrição | Exemplo |
|---------|-----------|---------|
| `{num_col}_avg_by_{cat_col}` | Média do grupo | `creditlimit_avg_by_country` |
| `{num_col}_vs_group_avg` | Diferença da média do grupo | `creditlimit - média_USA` |
| `{num_col}_vs_group_pct` | Percentual acima/abaixo da média | `+15%` ou `-8%` |

**Exemplo prático**:
```python
# Entrada Silver:
country: ['USA', 'USA', 'France']
creditlimit: [50000, 100000, 60000]

# Saída Gold adiciona:
creditlimit_avg_by_country: [75000, 75000, 60000]  # Média USA=75k, France=60k
creditlimit_vs_group_avg: [-25000, 25000, 0]
creditlimit_vs_group_pct: [-33.33, 33.33, 0.0]
```

**Uso em BI**:
```sql
-- Power BI: Clientes acima da média do país
SELECT * FROM gold.customers
WHERE creditlimit_vs_group_pct > 0
ORDER BY creditlimit_vs_group_pct DESC
```

---

### 5. **Rankings Globais**

Se houver coluna ID + coluna numérica:

| Métrica | Descrição | Exemplo |
|---------|-----------|---------|
| `_gold_global_rank` | Posição no ranking (1=melhor) | Cliente com maior crédito = rank 1 |

**Exemplo prático**:
```python
# Entrada Silver:
customernumber: [103, 112, 114]
creditlimit: [100000, 50000, 75000]

# Saída Gold adiciona:
_gold_global_rank: [1, 3, 2]  # Ranking por creditlimit
```

**Uso em BI**:
```sql
-- Power BI: Top 10 clientes
SELECT * FROM gold.customers
WHERE _gold_global_rank <= 10
```

---

### 6. **Métricas de Qualidade**

Colunas de auditoria e qualidade:

| Métrica | Descrição | Uso |
|---------|-----------|-----|
| `_gold_completeness` | % de campos preenchidos na linha | Identificar registros incompletos |
| `_gold_numeric_fields_count` | Quantidade de campos numéricos válidos | Qualidade de dados |
| `_gold_processed_at` | Timestamp do processamento | Auditoria |
| `_gold_feature_count` | Total de colunas criadas | Metadata |

**Uso em BI**:
```sql
-- Power BI: Registros com baixa qualidade
SELECT * FROM gold.customers
WHERE _gold_completeness < 80
```

---

## Exemplo Completo: Antes e Depois

### Entrada (Silver - 5 colunas)
```csv
customernumber,country,creditlimit,orderdate,status
103,USA,75000,2024-01-15,Active
112,France,50000,2024-03-31,Active
114,USA,100000,2024-12-25,Inactive
```

### Saída (Gold - ~40 colunas)
```csv
# Colunas originais (5)
customernumber,country,creditlimit,orderdate,status,

# Métricas de creditlimit (3)
creditlimit_percentile,creditlimit_zscore,creditlimit_is_outlier,

# Análise de country (3)
country_frequency,country_pct,country_is_top,

# Análise de status (3)
status_frequency,status_pct,status_is_top,

# Features temporais de orderdate (12)
orderdate_year,orderdate_month,orderdate_quarter,orderdate_day_of_week,
orderdate_week_of_year,orderdate_is_weekend,orderdate_is_month_start,
orderdate_is_month_end,orderdate_is_quarter_start,orderdate_is_quarter_end,
orderdate_days_since_epoch,

# Agregações por grupo (3)
creditlimit_avg_by_country,creditlimit_vs_group_avg,creditlimit_vs_group_pct,

# Ranking e qualidade (5)
_gold_global_rank,_gold_completeness,_gold_numeric_fields_count,
_gold_processed_at,_gold_feature_count
```

**Total**: 5 colunas → ~40 colunas (8x mais features analíticas!)

---

## Casos de Uso em Power BI

### 1. Dashboard de Performance por País
```dax
// Usando agregações automáticas
Market Share = [country_pct]
Performance vs Country Avg = [creditlimit_vs_group_pct]

// Filtro visual: Mostrar apenas países acima da média global
creditlimit_percentile > 50
```

### 2. Análise de Sazonalidade
```dax
// Usando features temporais
Sales by Quarter = CALCULATE(SUM([amount]), [orderdate_quarter] = 4)
Weekend Sales = CALCULATE(SUM([amount]), [orderdate_is_weekend] = 1)
End of Month Pattern = CALCULATE(COUNT(*), [orderdate_is_month_end] = 1)
```

### 3. Detecção de Outliers
```dax
// Usando z-scores
Outlier Customers = CALCULATE(COUNT([customernumber]), [creditlimit_is_outlier] = 1)
High Value Customers = FILTER([creditlimit_zscore] > 2)
```

### 4. Análise de Qualidade de Dados
```dax
// Usando métricas de qualidade
Data Quality Score = AVERAGE([_gold_completeness])
Incomplete Records = COUNTROWS(FILTER([_gold_completeness] < 80))
```

---

## Machine Learning Features

As features criadas são ideais para ML:

### Classificação
```python
# Prever se cliente é "High Value"
features = [
    'creditlimit_percentile',
    'creditlimit_zscore',
    'country_frequency',
    'orderdate_month',
    'orderdate_is_quarter_end',
    'creditlimit_vs_group_pct'
]

X = df[features]
y = df['creditlimit_percentile'] > 75  # High value = top 25%
```

### Regressão
```python
# Prever valor de venda
features = [
    'orderdate_month',
    'orderdate_day_of_week',
    'orderdate_is_weekend',
    'country_pct',
    'creditlimit_percentile'
]

X = df[features]
y = df['sales_amount']
```

---

## Logs de Execução

### Exemplo Real
```
[GOLD] Aplicando inteligência analítica...
[GOLD] Criando métricas para 8 colunas numéricas
[GOLD] ✓ Métricas criadas para: creditlimit
[GOLD] ✓ Métricas criadas para: quantityordered
[GOLD] ✓ Métricas criadas para: priceeach
[GOLD] Criando métricas para 5 colunas categóricas
[GOLD] ✓ Métricas criadas para: country
[GOLD] ✓ Métricas criadas para: status
[GOLD] Criando features temporais para 3 colunas de data
[GOLD] ✓ Features temporais criadas para: orderdate
[GOLD] ✓ Features temporais criadas para: requireddate
[GOLD] Criando agregações por grupo...
[GOLD] ✓ Agregações criadas usando dimensão: country
[GOLD] ✓ Ranking global criado baseado em: creditlimit
[GOLD] Inteligência analítica concluída: 47 novas colunas criadas
[GOLD] Shape: (122, 15) → (122, 62)
[GOLD] ✅ Salvo em: s3://lab01/gold/customers/file.parquet
```

---

## Vantagens da Abordagem

### ✅ Zero Configuração
- Funciona com qualquer tabela automaticamente
- Detecta tipos e aplica transformações apropriadas
- Não precisa definir métricas manualmente

### ✅ Analytics-Ready
- Dados prontos para consumo em BI
- Features prontas para Machine Learning
- Reduz trabalho de analistas/cientistas de dados

### ✅ Consistente
- Mesmas métricas aplicadas a todas as tabelas
- Naming conventions padronizados
- Facilita comparações entre datasets

### ✅ Escalável
- Performance não degrada com número de tabelas
- Processamento vetorizado (pandas)
- Parquet otimizado para queries analíticas

### ✅ Autodocumentado
- Nomes de colunas descritivos
- Sufixos indicam tipo de métrica (_percentile, _zscore, _avg_by_)
- Metadata embutida (_gold_processed_at, _gold_feature_count)

---

## Performance e Otimizações

### Compressão Inteligente
```python
# Gold usa Parquet com PyArrow
df.to_parquet(compression='snappy', engine='pyarrow')
```

**Benefícios**:
- Compressão ~70% (CSV → Parquet)
- Queries 10-100x mais rápidas
- Columnar storage (lê apenas colunas necessárias)

### Tipos Otimizados
```python
# Categorias usam menos memória
'country': category (4 bytes) vs object (50+ bytes)

# Datas são int64 internamente
'orderdate': datetime64 (8 bytes) vs string (20+ bytes)
```

---

## Quando NÃO Usar Gold

Gold é para **análise**, não para **armazenamento**:

❌ **Não use Gold para**:
- ETL downstream (use Silver)
- Backups (use Bronze)
- Integração com outros sistemas (use Silver)

✅ **Use Gold para**:
- Dashboards em Power BI/Tableau
- Machine Learning
- Análises ad-hoc em SQL
- APIs analíticas

---

## Comparação: Silver vs Gold

| Aspecto | Silver | Gold |
|---------|--------|------|
| **Objetivo** | Dados limpos e confiáveis | Dados prontos para análise |
| **Colunas** | Originais + limpeza | Originais + dezenas de features |
| **Tamanho** | Menor (só dados essenciais) | Maior (muitas features) |
| **Uso** | ETL, integração | BI, ML, analytics |
| **Agregação** | Não | Sim (métricas, rankings) |
| **Temporal** | Não | Sim (features de data) |
| **Performance** | Boa | Otimizada para queries |

---

## Referências

- **Código fonte**: `src/dags/lib/gold_layer.py` → função `_apply_analytical_intelligence()`
- **Pipeline completo**: `src/dags/lib/medallion_pipeline.py`
- **Pandas Aggregations**: [pandas.pydata.org/docs/user_guide/groupby.html](https://pandas.pydata.org/docs/user_guide/groupby.html)
- **Feature Engineering**: [scikit-learn.org/stable/modules/preprocessing.html](https://scikit-learn.org/stable/modules/preprocessing.html)

---

## Histórico

| Data | Mudança |
|------|---------|
| 2024-11-23 | Implementação de inteligência analítica automática |
| 2024-11-23 | Features temporais, rankings, agregações por grupo |
| 2024-11-23 | Métricas de qualidade e metadata de auditoria |
