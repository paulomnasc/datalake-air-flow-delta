# Migração: DuckDB → PostgreSQL para Power BI

## Data da Migração
**25/12/2024**

## Problema Original

### DuckDB com Locks de Arquivo
- Power BI abria arquivo `.duckdb` e mantinha conexão ativa
- Ao tentar adicionar segunda tabela, ocorria erro: "O arquivo já está sendo usado por outro processo"
- DuckDB não foi projetado para múltiplas conexões simultâneas em arquivo único

### Tentativas Anteriores (NÃO FUNCIONARAM)
1. ❌ Múltiplos arquivos DuckDB (datalake_bi_1, datalake_bi_2, datalake_bi_3)
2. ❌ Try/finally para fechar conexões
3. ❌ PRAGMA enable_object_cache
4. ❌ Arquivo de cópia separado (datalake_bi.duckdb)

**Conclusão:** DuckDB em arquivo local não suporta múltiplos acessos simultâneos do Power BI.

---

## Solução Implementada: PostgreSQL

### Arquitetura

```
┌─────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   MinIO     │────▶│   Airflow    │────▶│  PostgreSQL  │◀────│   Power BI   │
│  (Delta)    │     │   DAG        │     │   (BI DB)    │     │  (Windows)   │
└─────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
  s3://lab01/         DuckDB             postgres-bi:5432       localhost:5433
  delta/              (em-memória)       datalake_bi            Múltiplas
                                         pbi_user               Conexões
                                                                Simultâneas ✅
```

### Componentes

#### 1. PostgreSQL para BI (`postgres-bi`)
```yaml
# docker-compose.yml
postgres-bi:
  image: postgres:13
  container_name: postgres-bi
  environment:
    POSTGRES_USER: pbi_user
    POSTGRES_PASSWORD: pbi_password
    POSTGRES_DB: datalake_bi
  ports:
    - "5433:5432"  # Porta externa para Power BI
  volumes:
    - pg_bi_data:/var/lib/postgresql/data
  networks:
    - airflow_net
```

#### 2. DAG de Sincronização (`sync_delta_to_postgres.py`)

**Fluxo:**
1. **setup_postgres**: Valida conexão com PostgreSQL
2. **sync_delta_to_postgres**: 
   - Conecta DuckDB em-memória (`:memory:`)
   - Descobre tabelas Delta no MinIO
   - Lê Parquet via DuckDB
   - Insere dados em PostgreSQL
3. **verify_postgres_tables**: Lista tabelas criadas

**Dependências:**
```python
# requirements.txt
psycopg2-binary  # Driver PostgreSQL
duckdb==0.10.2   # Leitura de Parquet/S3 (em-memória apenas)
```

### Configuração Power BI

**Connection String:**
```
Server: localhost
Port: 5433
Database: datalake_bi
Authentication: Database
Username: pbi_user
Password: pbi_password
```

**Tabelas Disponíveis:**
- `delta_customers_202512230532` (29 registros)
- `delta_employee_privileges_202512230532` (1 registro)
- `delta_orders_202512241315` (48 registros)
- `delta_products_202512241315` (45 registros)

---

## Comparação: Antes vs Depois

| Aspecto | DuckDB (Antigo) ❌ | PostgreSQL (Novo) ✅ |
|---------|-------------------|---------------------|
| **Múltiplos acessos** | ❌ Lock de arquivo | ✅ Nativo |
| **Power BI multi-table** | ❌ Erro na 2ª tabela | ✅ Funciona |
| **Persistência** | Arquivo `.duckdb` | Volume Docker |
| **Gerenciamento conexões** | Manual (try/finally) | Automático |
| **Escalabilidade** | Limitada | Alta |
| **Backup** | Cópia de arquivo | pg_dump |
| **Porta de acesso** | Arquivo UNC WSL | TCP localhost:5433 |

---

## Mudanças em Arquivos

### Criados
- ✅ `src/dags/sync_delta_to_postgres.py` (DAG nova)
- ✅ `MIGRACAO_DUCKDB_POSTGRESQL.md` (este arquivo)

### Modificados
- ✅ `docker-compose.yml` → Adicionado `postgres-bi` service
- ✅ `requirements.txt` → Adicionado `psycopg2-binary`

### Desativados
- 🔴 `src/dags/sync_duckdb_views.py` → Renomeado para `.bak`
- 🔴 `/opt/duckdb/datalake*.duckdb` → Obsoletos (não apagar ainda, histórico)

---

## Migração de Dados

### Executar DAG Manualmente
```bash
# Trigger DAG
docker compose exec -T airflow-scheduler \
  airflow dags trigger sync_delta_to_postgres

# Verificar tabelas criadas
docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi \
  -c "SELECT tablename FROM pg_tables WHERE schemaname='public';"
```

### Agendamento
- **Schedule:** Diário às 02:00 AM
- **Retry:** 1 tentativa com 5 min de delay
- **Catchup:** Desabilitado

---

## Manutenção

### Verificar Status PostgreSQL
```bash
docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi \
  -c "SELECT tablename, pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size 
      FROM pg_tables WHERE schemaname='public';"
```

### Limpar Tabelas Antigas
```bash
docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi \
  -c "DROP TABLE IF EXISTS delta_<table_name> CASCADE;"
```

### Backup PostgreSQL
```bash
docker compose exec -T postgres-bi \
  pg_dump -U pbi_user datalake_bi > backup_datalake_bi_$(date +%Y%m%d).sql
```

### Restaurar Backup
```bash
cat backup_datalake_bi_YYYYMMDD.sql | \
  docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi
```

---

## Troubleshooting

### Power BI não conecta
```bash
# Verificar se PostgreSQL está rodando
docker compose ps postgres-bi

# Verificar porta 5433 está aberta
netstat -an | grep 5433

# Testar conexão do host
psql -h localhost -p 5433 -U pbi_user -d datalake_bi
```

### DAG falha ao inserir dados
```bash
# Verificar logs
docker compose logs airflow-scheduler | grep sync_delta_to_postgres

# Testar conexão manual
docker compose exec -T airflow-scheduler python3 -c "
import psycopg2
conn = psycopg2.connect(
    host='postgres-bi', port=5432,
    database='datalake_bi', user='pbi_user', password='pbi_password'
)
print('✅ Conectado!')
conn.close()
"
```

### MinIO inacessível
```bash
# Verificar rede Docker
docker network inspect datalake-air-flow_airflow_net

# Testar S3 do Airflow
docker compose exec -T airflow-scheduler python3 -c "
import duckdb
con = duckdb.connect(':memory:')
con.execute('LOAD httpfs;')
con.execute(\"SET s3_endpoint='minio:9000';\")
con.execute(\"SET s3_access_key_id='admin';\")
con.execute(\"SET s3_secret_access_key='admin123';\")
con.execute(\"SET s3_use_ssl=false;\")
result = con.execute(\"SELECT COUNT(*) FROM read_parquet('s3://lab01/delta/customers_202512230532/*.parquet')\").fetchone()
print(f'✅ {result[0]} registros')
"
```

---

## Próximos Passos (Opcional)

### 1. Indexação para Performance
```sql
-- Criar índices em colunas frequentes
CREATE INDEX idx_customers_id ON delta_customers_202512230532(customer_id);
CREATE INDEX idx_orders_date ON delta_orders_202512241315(order_date);
```

### 2. Views Materializadas
```sql
-- Para agregações complexas
CREATE MATERIALIZED VIEW mv_sales_summary AS
SELECT DATE(order_date) as date, SUM(total) as total_sales
FROM delta_orders_202512241315
GROUP BY DATE(order_date);
```

### 3. Particionamento
```sql
-- Para tabelas grandes (milhões de registros)
CREATE TABLE delta_orders_partitioned (
    -- colunas
) PARTITION BY RANGE (order_date);
```

---

## Observações Finais

### Por que PostgreSQL?
1. ✅ **Padrão de mercado** para BI/Analytics
2. ✅ **Gerenciamento nativo** de múltiplas conexões
3. ✅ **Compatibilidade total** com Power BI ODBC/PostgreSQL driver
4. ✅ **Escalável** (suporta milhões de registros)
5. ✅ **Ferramentas maduras** (backup, replicação, monitoramento)

### DuckDB ainda é útil?
**Sim!** Mas apenas para:
- ✅ Análises ad-hoc em notebooks
- ✅ Leitura rápida de Parquet/CSV
- ✅ Processamento em-memória (sem persistência)

**Não usar para:**
- ❌ Servir múltiplos clientes (Power BI, Tableau, etc.)
- ❌ Banco de dados compartilhado
- ❌ Aplicações com concorrência

---

**Migração concluída com sucesso em 25/12/2024** ✅
