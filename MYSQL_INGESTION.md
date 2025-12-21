# Ingestão MySQL → Data Lake

## Visão Geral

Módulo para ingestão automática de dados do MySQL para o Data Lake, com suporte a pipeline completo Medallion.

## Funcionalidades

### 1. `ingest_mysql_to_raw()`
Extrai dados do MySQL e salva na camada Raw como CSV.

**Parâmetros**:
```python
ingest_mysql_to_raw(
    mysql_conn_id='mysql_dag_metadata',  # ID da conexão Airflow
    table_name='customers',              # Tabela MySQL
    query=None,                          # Query customizada (opcional)
    target_table_name='customers',       # Nome destino (opcional)
    dag_id='ingestao-mysql-customers'    # ID da DAG
)
```

**Fluxo**:
```
MySQL (tabela customers)
    ↓
Extração via MySqlHook
    ↓
pandas.read_sql()
    ↓
Salvar CSV temporário
    ↓
Upload para MinIO: raw/{dag_id}/{timestamp}_{hash}.csv
```

**Retorno**:
```python
{
    "source": "mysql",
    "table": "customers",
    "raw_key": "raw/ingestao-mysql-customers/1732368000_abc123.csv",
    "rows": 122,
    "columns": 13,
    "filename": "1732368000_abc123.csv"
}
```

---

### 2. `mysql_to_medallion()`
Pipeline completo: MySQL → Raw → Bronze → Silver → Gold

**Parâmetros**:
```python
mysql_to_medallion(
    mysql_conn_id='mysql_dag_metadata',
    table_name='customers',
    query=None,                    # Query customizada (opcional)
    target_table_name='customers',
    dag_id='ingestao-mysql-customers'
)
```

**Fluxo Completo**:
```
MySQL (tabela customers)
    ↓
1. Ingestão → Raw CSV
    ↓
2. Bronze → Cópia imutável
    ↓
3. Silver → Limpeza + tipos automáticos
    ↓
4. Gold → 66+ métricas analíticas
```

**Retorno**:
```python
{
    "source": "mysql",
    "table": "customers",
    "raw_key": "raw/ingestao-mysql-customers/1732368000_abc123.csv",
    "rows": 122,
    "columns": 13,
    "bronze": "bronze/customers/1732368000_abc123.csv",
    "silver": "silver/customers/1732368000_abc123.parquet",
    "gold": "gold/customers/1732368000_abc123.parquet",
    "pipeline": "mysql_to_medallion"
}
```

---

## Como Usar

### Opção 1: Pela Webapp (Recomendado)

1. **Crie a conexão MySQL no Airflow primeiro**:
   ```bash
   docker exec airflow-webserver airflow connections add \
       'mysql_northwind' \
       --conn-type 'mysql' \
       --conn-host 'mysql' \
       --conn-schema 'northwind' \
       --conn-login 'root' \
       --conn-password 'root' \
       --conn-port 3306
   ```

2. **Acesse**: http://localhost:8088

3. **Adicionar Nova Configuração**

4. **Preencha**:
   - **DAG ID**: `ingestao-mysql-customers`
   - **Schedule Interval**: `@daily` (ou `None` para manual)
   - **Owner**: `airflow`
   - **Description**: `Ingestão da tabela customers do MySQL Northwind`
   - **Source Type**: Selecione "Database SQL"
   - **Source DB URI**: `mysql+mysqlconnector://root:root@mysql:3306/northwind`
   - **Target Table**: `customers`
   - **Python Module Path**: Selecione **"MySQL → Medallion (Ingestão + Bronze + Silver + Gold)"**
   - **Transform Args** (JSON):
   ```json
   {
       "mysql_conn_id": "mysql_northwind",
       "table_name": "customers"
   }
   ```

5. **Salvar** - A DAG será criada automaticamente!

### Opção 2: Inserção Direta no Banco

```sql
INSERT INTO dag_configurations (
    dag_id,
    schedule_interval,
    owner,
    description,
    source_filename,
    target_table_name,
    python_module_path,
    transform_args,
    start_date,
    is_active
) VALUES (
    'ingestao-mysql-customers',
    '@daily',
    'airflow',
    'Ingestão diária da tabela customers do MySQL',
    '',  -- Não precisa (dados vêm do MySQL)
    'customers',
    'lib.mysql_ingestion.mysql_to_medallion',
    '{"mysql_conn_id": "mysql_classicmodels", "table_name": "customers"}',
    '2024-01-01',
    1
);
```

---

## Configuração de Conexão MySQL

### 1. Criar Conexão no Airflow

**Via CLI** (Recomendado):
```bash
docker exec airflow-webserver airflow connections add \
    'mysql_northwind' \
    --conn-type 'mysql' \
    --conn-host 'mysql' \
    --conn-schema 'northwind' \
    --conn-login 'root' \
    --conn-password 'root' \
    --conn-port 3306
```

**Via UI** (http://localhost:8085/connection/add):
- **Connection Id**: `mysql_northwind`
- **Connection Type**: `MySQL`
- **Host**: `mysql` (nome do container no docker compose)
- **Schema**: `northwind`
- **Login**: `root`
- **Password**: `root`
- **Port**: `3306`

### 2. Instalar Provider MySQL (se necessário)

```bash
docker exec -it airflow-webserver pip install apache-airflow-providers-mysql
```

---

## Exemplos de Uso

### Exemplo 1: Ingestão Simples (Tabela Inteira)

```json
{
    "mysql_conn_id": "mysql_northwind",
    "table_name": "customers"
}
```

**Query executada automaticamente**:
```sql
SELECT * FROM customers
```

### Exemplo 2: Query Customizada

```json
{
    "mysql_conn_id": "mysql_northwind",
    "table_name": "customers",
    "query": "SELECT CustomerID, CompanyName, Country, City FROM customers WHERE Country = 'USA'"
}
```

**Query executada**:
```sql
SELECT CustomerID, CompanyName, Country, City 
FROM customers 
WHERE Country = 'USA'
```

### Exemplo 3: Join de Múltiplas Tabelas

```json
{
    "mysql_conn_id": "mysql_northwind",
    "table_name": "customers_with_orders",
    "query": "SELECT c.CustomerID, c.CompanyName, c.Country, COUNT(o.OrderID) as total_orders, SUM(od.Quantity * od.UnitPrice) as total_spent FROM customers c LEFT JOIN orders o ON c.CustomerID = o.CustomerID LEFT JOIN `order details` od ON o.OrderID = od.OrderID GROUP BY c.CustomerID, c.CompanyName, c.Country"
}
```

---

## Estrutura de Dados Resultante

### Raw
```
s3://lab01/raw/ingestao-mysql-customers/1732368000_abc123.csv
```
- Formato: CSV
- Conteúdo: Dados brutos do MySQL

### Bronze
```
s3://lab01/bronze/customers/1732368000_abc123.csv
```
- Formato: CSV
- Conteúdo: Cópia imutável do Raw

### Silver
```
s3://lab01/silver/customers/1732368000_abc123.parquet
```
- Formato: Parquet (Snappy)
- Transformações:
  - Tipos inferidos automaticamente
  - Datas convertidas para datetime
  - Categorias otimizadas
  - Nulos preenchidos inteligentemente
  - +2 colunas de auditoria

### Gold
```
s3://lab01/gold/customers/1732368000_abc123.parquet
```
- Formato: Parquet (Snappy otimizado)
- Features:
  - +66 colunas analíticas
  - Rankings, percentis, z-scores
  - Agregações por grupo
  - Features temporais

---

## Logs de Execução

### Exemplo Real

```
[MYSQL→RAW] Iniciando ingestão do MySQL para: customers
[MYSQL→RAW] MySQL Connection: mysql_classicmodels
[MYSQL→RAW] Tabela origem: customers
[MYSQL→RAW] Destino: s3://lab01/raw/ingestao-mysql-customers/1732368000_abc123.csv
[MYSQL→RAW] Query: SELECT * FROM customers
[MYSQL→RAW] Executando query no MySQL...
[MYSQL→RAW] Dados extraídos: 122 linhas, 13 colunas
[MYSQL→RAW] CSV temporário criado: /tmp/tmpxyz/1732368000_abc123.csv
[MYSQL→RAW] ✅ Dados salvos em: s3://lab01/raw/ingestao-mysql-customers/1732368000_abc123.csv
[MYSQL→RAW] Ingestão concluída com sucesso!

[MEDALLION] Iniciando pipeline completo para: customers
[BRONZE] ✅ Salvo em: s3://lab01/bronze/customers/1732368000_abc123.csv
[SILVER] Transformações inteligentes concluídas: 2 colunas adicionadas
[SILVER] ✅ Salvo em: s3://lab01/silver/customers/1732368000_abc123.parquet
[GOLD] Inteligência analítica concluída: 66 novas colunas criadas
[GOLD] ✅ Salvo em: s3://lab01/gold/customers/1732368000_abc123.parquet

[MYSQL→MEDALLION] ✅ Pipeline completo concluído!
[MYSQL→MEDALLION] MySQL → Raw: raw/ingestao-mysql-customers/1732368000_abc123.csv
[MYSQL→MEDALLION] Bronze: bronze/customers/1732368000_abc123.csv
[MYSQL→MEDALLION] Silver: silver/customers/1732368000_abc123.parquet
[MYSQL→MEDALLION] Gold: gold/customers/1732368000_abc123.parquet
```

---

## Vantagens

### ✅ Automatizado
- Extração automática do MySQL
- Conversão CSV automática
- Pipeline Medallion automático

### ✅ Flexível
- Suporte a queries customizadas
- Suporte a joins complexos
- Suporte a filtros (WHERE)

### ✅ Consistente
- Mesmo formato de saída (CSV → Parquet)
- Mesmas transformações automáticas
- Mesmas métricas analíticas

### ✅ Escalável
- Processa tabelas de qualquer tamanho
- Pandas otimizado para grandes volumes
- Parquet com compressão eficiente

---

## Casos de Uso

### 1. Ingestão Diária de Vendas
```sql
-- transform_args:
{
    "mysql_conn_id": "mysql_sales",
    "table_name": "sales",
    "query": "SELECT * FROM sales WHERE sale_date = CURDATE() - INTERVAL 1 DAY"
}

-- Schedule: @daily
-- Resultado: Vendas de ontem no Data Lake todo dia
```

### 2. Snapshot Mensal de Clientes
```sql
-- transform_args:
{
    "mysql_conn_id": "mysql_crm",
    "table_name": "customers_snapshot",
    "query": "SELECT *, NOW() as snapshot_date FROM customers"
}

-- Schedule: 0 0 1 * * (primeiro dia do mês)
-- Resultado: Snapshot completo de clientes mensalmente
```

### 3. Relatório de Performance
```sql
-- transform_args:
{
    "mysql_conn_id": "mysql_analytics",
    "query": "
        SELECT 
            DATE(order_date) as date,
            COUNT(*) as orders,
            SUM(total) as revenue,
            AVG(total) as avg_ticket
        FROM orders
        WHERE order_date >= CURDATE() - INTERVAL 7 DAY
        GROUP BY DATE(order_date)
    "
}

-- Schedule: @hourly
-- Resultado: Métricas de performance dos últimos 7 dias
```

---

## Troubleshooting

### Erro: "Connection not found"
```
Solução: Criar conexão MySQL no Airflow (ver seção "Configuração de Conexão")
```

### Erro: "No module named MySQLdb"
```bash
# Instalar provider MySQL
docker exec -it airflow-webserver pip install apache-airflow-providers-mysql
docker restart airflow-webserver airflow-scheduler airflow-worker
```

### Erro: "Access denied for user"
```
Solução: Verificar credenciais na conexão MySQL do Airflow
- Host correto (nome do container)
- Usuário/senha corretos
- Schema correto
```

### Aviso: "Table is empty"
```
[MYSQL→RAW] ⚠️ Nenhum dado encontrado na tabela customers
```
- Query pode estar retornando 0 linhas
- Verificar filtros WHERE
- Verificar se tabela tem dados

---

## Referências

- **Código fonte**: `src/dags/lib/mysql_ingestion.py`
- **MySqlHook**: https://airflow.apache.org/docs/apache-airflow-providers-mysql/
- **Pandas SQL**: https://pandas.pydata.org/docs/reference/api/pandas.read_sql.html

---

## Histórico

| Data | Mudança |
|------|---------|
| 2024-11-23 | Implementação inicial de ingestão MySQL |
| 2024-11-23 | Suporte a queries customizadas e joins |
| 2024-11-23 | Integração com pipeline Medallion |
