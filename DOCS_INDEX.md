# 📚 Documentação do Datalake - Índice de Navegação

## Visão Geral da Arquitetura Medallion

```
┌──────────────┐   ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
│     RAW      │ → │    BRONZE    │ → │    SILVER    │ → │  GOLD DELTA  │
│   (CSV)      │   │   (CSV)      │   │  (Parquet)   │   │  (Delta Lake)│
│              │   │              │   │  + Quality   │   │  + Features  │
└──────────────┘   └──────────────┘   └──────────────┘   └──────────────┘
 Dados brutos      Limpeza básica    Validação         Analytics
                                      Transformações     Inteligência
```

---

## 🎯 Guias de Uso e Configuração

### 📋 Guia da Interface Web de Configuração

**Arquivo**: [`GUIDE_WEBAPP_CONFIG.md`](./GUIDE_WEBAPP_CONFIG.md)

**Conteúdo**:
- ✅ Como preencher formulário de configuração de DAGs
- ✅ Modo Single-Tabela vs Multi-Tabela
- ✅ Configuração de conexões SQL (Direta e SSH Tunnel)
- ✅ Seleção correta de funções de pipeline
- ✅ Validações automáticas e troubleshooting
- ✅ Exemplos práticos para cada tipo de fonte

**Quando ler**: Antes de criar sua primeira DAG na interface web, ou quando precisar entender as opções de configuração.

---

## 📖 Documentação por Camada

### 1️⃣ Camada Silver (Qualidade e Transformações)

**Arquivo**: [`TRANSFORMACOES_SILVER.md`](./TRANSFORMACOES_SILVER.md)

**Conteúdo**:
- ✅ Transformações inteligentes automáticas
- ✅ Sistema de validação de qualidade (5 regras)
- ✅ **Dicionário de Dados**: 4 colunas de qualidade
- ✅ Exemplos práticos e casos de uso
- ✅ Troubleshooting e otimizações

**Colunas Adicionadas**:
- `DataQualityRulesPass` - Regras aprovadas
- `DataQualityRulesFail` - Regras reprovadas
- `DataQualityRulesSkip` - Regras não aplicáveis
- `DataQualityEvaluationResult` - "Passed" ou "Failed"

**Quando ler**: Para entender como funciona a validação de qualidade e quais transformações são aplicadas automaticamente.

---

### 2️⃣ Camada Gold (Delta Lake e Feature Engineering)

**Arquivo**: [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md)

**Conteúdo**:
- ✅ Implementação completa Delta Lake (deltalake-python 0.15.0)
- ✅ ACID transactions, versionamento, time travel
- ✅ **Dicionário de Dados**: 19+ features analíticas criadas automaticamente
- ✅ Feature Engineering (numéricas, categóricas, temporais, agregações)
- ✅ Integração com MinIO e Airflow
- ✅ Troubleshooting completo

**Features Criadas Automaticamente**:
- **Numéricas**: zscore, percentile, min_max_scaled (3 por coluna)
- **Categóricas**: frequency, pct (2 por coluna)
- **Temporais**: year, month, day, dayofweek, quarter (5 por coluna)
- **Agregações**: {col}_by_{group}_mean
- **Rankings**: {col}_rank

**Quando ler**: Para entender como funciona o Delta Lake, quais features analíticas são criadas e como usar para ML/BI.

---

## 🎯 Navegação Rápida por Caso de Uso

### Para Análise de Qualidade de Dados

➡️ [`TRANSFORMACOES_SILVER.md`](./TRANSFORMACOES_SILVER.md)

**Seções relevantes**:
- Dicionário de Dados - Colunas de Qualidade
- Regras de Qualidade Aplicadas (5 regras detalhadas)
- Queries Úteis para Análise
- Troubleshooting de Qualidade

**Exemplo**:
```python
# Filtrar dados confiáveis
df = df[df['DataQualityEvaluationResult'] == 'Passed']
```

---

### Para Machine Learning / Feature Engineering

➡️ [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md)

**Seções relevantes**:
- Features Numéricas (zscore, percentile, scaling)
- Features Categóricas (frequency encoding)
- Features Temporais (decomposição de datas)
- Casos de Uso por Feature

**Exemplo**:
```python
# Features prontas para ML
X = df[[col for col in df.columns if '_min_max_scaled' in col]]
```

---

### Para Análise Temporal (Séries Temporais)

➡️ [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md) → Seção "Features Temporais"

**Features criadas**:
- `{date}_year` - Ano
- `{date}_month` - Mês (1-12)
- `{date}_day` - Dia do mês
- `{date}_dayofweek` - Dia da semana (0=segunda)
- `{date}_quarter` - Trimestre (1-4)

**Exemplo**:
```sql
-- Vendas por trimestre
SELECT orderDate_year, orderDate_quarter, SUM(amount)
FROM orders_delta
GROUP BY orderDate_year, orderDate_quarter
```

---

### Para Segmentação e Rankings

➡️ [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md) → Seções "Features Numéricas" e "Rankings"

**Features criadas**:
- `{col}_percentile` - Posição percentual (0-100)
- `{col}_rank` - Ranking global
- `{col}_by_{group}_mean` - Média por grupo

**Exemplo**:
```python
# Top 10% (premium)
premium = df[df['creditLimit_percentile'] >= 90]

# Top 20 clientes
top20 = df[df['creditLimit_rank'] <= 20]
```

---

### Para Detecção de Outliers

➡️ [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md) → Seção "Features Numéricas (zscore)"

**Feature criada**:
- `{col}_zscore` - Desvios padrão da média

**Exemplo**:
```python
# Outliers extremos (> 2 desvios padrão)
outliers = df[abs(df['creditLimit_zscore']) > 2]
```

---

### Para Comparação com Média do Grupo

➡️ [`DELTA_LAKE_IMPLEMENTATION.md`](./DELTA_LAKE_IMPLEMENTATION.md) → Seção "Features de Agregação"

**Feature criada**:
- `{col}_by_{group}_mean` - Média do grupo

**Exemplo**:
```python
# Clientes acima da média do país
above_avg = df[df['creditLimit'] > df['creditLimit_by_country_mean']]
```

---

## 📊 Resumo de Colunas por Camada

### Bronze
- **Origem**: Raw CSV
- **Colunas**: N originais (sem modificação)
- **Formato**: CSV
- **Operações**: Cópia simples

### Silver
- **Origem**: Bronze CSV
- **Colunas**: N originais + 4 qualidade = **N+4 total**
- **Formato**: Parquet (comprimido)
- **Operações**: Limpeza, transformações automáticas, validação de qualidade

**Colunas Adicionadas** (4):
1. DataQualityRulesPass
2. DataQualityRulesFail
3. DataQualityRulesSkip
4. DataQualityEvaluationResult

### Gold (Delta Lake)
- **Origem**: Silver Parquet
- **Colunas**: N+4 originais + ~19 features = **N+23 total** (em média)
- **Formato**: Delta Lake (Parquet + transaction log)
- **Operações**: Feature engineering, agregações, versionamento

**Features Adicionadas** (~19 para tabela típica):
- 6 numéricas (3 por coluna × 2 colunas)
- 4 categóricas (2 por coluna × 2 colunas)
- 5 temporais (5 por coluna × 1 coluna)
- 3 agregações
- 1 ranking

---

## 🔗 Arquivos Relacionados

### Código Fonte

| Arquivo | Descrição | Camada |
|---------|-----------|--------|
| `src/dags/lib/silver_layer.py` | Lógica da camada Silver | Silver |
| `src/dags/lib/data_quality.py` | Validação de qualidade (5 regras) | Silver |
| `src/dags/lib/gold_delta_layer.py` | Delta Lake + Feature Engineering | Gold |
| `src/dags/lib/medallion_pipeline.py` | Pipeline completo Raw→Gold | Todas |

### Configuração

| Arquivo | Descrição |
|---------|-----------|
| `docker-compose.yml` | Containers: Airflow, MinIO, MySQL, Spark |
| `requirements.txt` | Dependências Python (deltalake==0.15.0) |
| `src/dags/factory_master.py` | Factory de DAGs (cria DAGs automaticamente) |

### Documentação Geral

| Arquivo | Descrição |
|---------|-----------|
| `README.md` | Visão geral do projeto |
| `arquiteturaDatalake.md` | Arquitetura Medallion completa |
| `MYSQL_INGESTION.md` | Ingestão de dados MySQL |
| `GOLD_ANALYTICS.md` | Análises avançadas camada Gold |

---

## 🚀 Início Rápido

### 1. Entender a Arquitetura
```
Leia: README.md → arquiteturaDatalake.md
```

### 2. Implementar Validação de Qualidade
```
Leia: TRANSFORMACOES_SILVER.md
Arquivo: src/dags/lib/data_quality.py
```

### 3. Implementar Delta Lake
```
Leia: DELTA_LAKE_IMPLEMENTATION.md
Arquivo: src/dags/lib/gold_delta_layer.py
Comando: pip install deltalake==0.15.0
```

### 4. Testar Pipeline Completo
```bash
# Upload CSV via webapp
# Ou executar DAG manualmente:
docker exec airflow-scheduler airflow dags trigger ingestao-mysql-orders7
```

### 5. Verificar Resultados
```bash
# MinIO Console
http://localhost:9001

# Verificar estrutura:
# silver/{table}/       → Parquet com 4 colunas qualidade
# gold/{table}_delta/   → Delta Lake com features
#   ├── _delta_log/     → Transaction logs (versionamento)
#   └── *.parquet       → Dados
```

---

## 🎓 Recursos de Aprendizado

### Para Iniciantes
1. Leia: `README.md` (visão geral)
2. Leia: `arquiteturaDatalake.md` (arquitetura Medallion)
3. Execute: Pipeline completo com tabela de teste

### Para Data Engineers
1. Leia: `TRANSFORMACOES_SILVER.md` (transformações automáticas)
2. Leia: `DELTA_LAKE_IMPLEMENTATION.md` (Delta Lake)
3. Explore: Código fonte em `src/dags/lib/`

### Para Data Scientists
1. Leia: `DELTA_LAKE_IMPLEMENTATION.md` → Seção "Dicionário de Dados"
2. Foco: Features Numéricas, Categóricas, Temporais
3. Use: Features prontas para ML sem feature engineering manual

### Para Analistas de BI
1. Leia: `TRANSFORMACOES_SILVER.md` → Seção "Colunas de Qualidade"
2. Leia: `DELTA_LAKE_IMPLEMENTATION.md` → Seção "Integração com BI Tools"
3. Use: Filtros de qualidade + features analíticas

---

## 📞 Suporte

### Problemas Comuns

| Problema | Documento | Seção |
|----------|-----------|-------|
| Dados com baixa qualidade | TRANSFORMACOES_SILVER.md | Troubleshooting |
| Delta Lake não criado | DELTA_LAKE_IMPLEMENTATION.md | Troubleshooting |
| Features faltando | DELTA_LAKE_IMPLEMENTATION.md | Manutenção e Troubleshooting |
| Performance lenta | DELTA_LAKE_IMPLEMENTATION.md | Performance e Otimização |

### Debug

```bash
# Verificar logs do Airflow
docker logs airflow-scheduler | grep -E "SILVER|GOLD|QUALITY"

# Verificar Delta Lake
docker exec datalake-air-flow-minio-1 mc ls local/lab01/gold/{table}_delta/

# Testar função Python diretamente
docker exec -it airflow-scheduler python
>>> from lib.gold_delta_layer import silver_to_gold_delta
>>> result = silver_to_gold_delta("silver/orders/file.parquet", "orders")
```

---

## 📋 Checklist de Implementação

### Silver Layer
- [x] Limpeza básica (dropna, drop_duplicates)
- [x] Transformações inteligentes automáticas
- [x] Validação de qualidade (5 regras)
- [x] 4 colunas de qualidade adicionadas
- [x] Formato Parquet comprimido
- [x] Documentação completa

### Gold Layer
- [x] Delta Lake implementado (deltalake-python 0.15.0)
- [x] Versionamento (_delta_log/)
- [x] ACID transactions
- [x] Feature Engineering automático (19+ features)
- [x] Features numéricas (zscore, percentile, scaling)
- [x] Features categóricas (frequency encoding)
- [x] Features temporais (decomposição de datas)
- [x] Agregações por grupo
- [x] Rankings globais
- [x] Documentação completa

### Próximos Passos (Opcional)
- [ ] OPTIMIZE + VACUUM (compactação Delta Lake)
- [ ] Dashboard de qualidade de dados
- [ ] Integração Power BI via ODBC
- [ ] Queries analíticas avançadas (Trino/Spark SQL)
- [ ] Machine Learning pipelines

---

**Última atualização**: 24/11/2025  
**Versão**: 1.0  
**Status**: ✅ Documentação Completa
