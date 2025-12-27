# 🦆 DuckDB Query API - Guia de Implementação

## O que foi adicionado?

Uma solução completa para executar **SQL diretamente em arquivos Parquet** armazenados no MinIO S3, sem usar Spark.

### Componentes:

1. **API FastAPI DuckDB** (`duckdb-api/`) - Serviço que executa queries SQL
2. **DuckDBHelper** - Class PHP para integração com CodeIgniter
3. **QueryBuilderController** - Controller com endpoints REST
4. **Query Builder UI** - Interface web interativa

---

## Arquitetura

```
┌─────────────────────────┐
│   CodeIgniter Webapp    │
│  (Query Builder UI)     │
└────────────┬────────────┘
             │ HTTP POST
             │
┌─────────────▼────────────┐
│   DuckDB API (FastAPI)   │  ← Serviço novo
│   http://duckdb-api:5000 │
└────────────┬────────────┘
             │ SQL Driver
             │
    ┌────────┴────────┐
    ▼                 ▼
┌──────────┐     ┌─────────────┐
│ DuckDB   │────▶│ MinIO (S3)   │
│ Database │     │ Parquet      │
└──────────┘     └─────────────┘
```

---

## Como usar na webapp?

### 1. Via Helper (forma recomendada)

```php
<?php
// Em qualquer Controller
use App\Helpers\DuckDBHelper;

// Carregar o helper
helper('DuckDB');

// Executar query
$result = DuckDBHelper::query(
    "SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 10"
);

if ($result['success']) {
    $rows = $result['data'];      // Array de resultados
    $columns = $result['columns']; // Nomes das colunas
    $count = $result['rows_affected'];
} else {
    echo "Erro: " . $result['error'];
}
?>
```

### 2. Via Controller REST

```php
<?php
public function searchParquet()
{
    $queryController = new \App\Controllers\QueryBuilderController();
    
    // GET /query-builder          → Abre interface web
    // POST /query-builder/execute → Executa query
    // POST /query-builder/tables  → Lista tabelas
    // POST /query-builder/schema  → Obtém schema
    // GET /query-builder/status   → Health check
}
?>
```

### 3. Chamada direta HTTP (JavaScript/AJAX)

```javascript
// No frontend (HTML da webapp)
async function queryParquet() {
    const response = await fetch('/query-builder/execute', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            sql: "SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 100",
            limit: 1000
        })
    });
    
    const result = await response.json();
    console.log(result.data);
}
```

---

## Sintaxe SQL para Parquet

### Padrões úteis:

```sql
-- Ler arquivo único
SELECT * FROM read_parquet('s3://lab01/bronze/customers.parquet') LIMIT 10;

-- Ler múltiplos arquivos (wildcard)
SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 10;

-- Com filtros
SELECT id, name, email 
FROM read_parquet('s3://lab01/bronze/customers.parquet')
WHERE status = 'active'
LIMIT 100;

-- Agregações
SELECT category, COUNT(*) as total, AVG(price) as avg_price
FROM read_parquet('s3://lab01/bronze/products.parquet')
GROUP BY category;

-- JOIN entre Parquets
SELECT c.*, o.order_id
FROM read_parquet('s3://lab01/bronze/customers.parquet') c
JOIN read_parquet('s3://lab01/bronze/orders.parquet') o 
  ON c.id = o.customer_id
LIMIT 50;

-- Converter para JSON
SELECT json_object('id', id, 'name', name) as json_data
FROM read_parquet('s3://lab01/bronze/customers.parquet')
LIMIT 5;
```

---

## Endpoints da API

### POST `/query`
Executa uma query SQL

**Body:**
```json
{
    "sql": "SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 10",
    "limit": 1000
}
```

**Response:**
```json
{
    "success": true,
    "data": [
        {"id": 1, "name": "John", "email": "john@example.com"},
        {"id": 2, "name": "Jane", "email": "jane@example.com"}
    ],
    "columns": ["id", "name", "email"],
    "rows_affected": 2,
    "error": null
}
```

---

### POST `/query/tables`
Lista tabelas/views no DuckDB

**Response:**
```json
{
    "success": true,
    "tables": ["customers", "orders", "products"]
}
```

---

### POST `/query/parquet-files`
Lista arquivos Parquet no S3/MinIO

**Body:**
```json
{
    "path": "s3://lab01/bronze"
}
```

**Response:**
```json
{
    "success": true,
    "files": [
        ["s3://lab01/bronze/customers.parquet", 1024000, 500],
        ["s3://lab01/bronze/orders.parquet", 2048000, 1200]
    ],
    "path": "s3://lab01/bronze"
}
```

---

### POST `/query/schema`
Obtém schema de um arquivo Parquet

**Body:**
```json
{
    "path": "s3://lab01/bronze/customers"
}
```

**Response:**
```json
{
    "success": true,
    "columns": [
        {"name": "id", "type": "BIGINT"},
        {"name": "name", "type": "VARCHAR"},
        {"name": "email", "type": "VARCHAR"}
    ],
    "path": "s3://lab01/bronze/customers"
}
```

---

### GET `/health`
Verifica saúde da API

**Response:**
```json
{
    "status": "healthy",
    "service": "DuckDB Query API",
    "minio_bucket": "lab01",
    "duckdb_path": "/opt/duckdb/datalake.duckdb"
}
```

---

## Impacto de Hardware (atualizado)

Com DuckDB adicionado à stack:

| Serviço | RAM | CPU | Disco | Status |
|---------|-----|-----|-------|--------|
| DuckDB API | **256MB** | Baixo (idle) | Mínimo | ✅ **NOVO** |
| Spark | 4-8GB | Alto | Alto | Existente |
| PostgreSQL | 256MB | Médio | Médio | Existente |
| Atlas | 1-2GB | Médio | Médio | Existente |
| Airflow | 512MB-1GB | Médio | Médio | Existente |
| **TOTAL** | **7-13GB** | **Médio** | **Alto** | |

### Benefício:
- **DuckDB reduz dependência do Spark** para queries simples
- Pode desativar Spark quando não houver processamento pesado
- Usa I/O de disco (Parquet já está lá)

---

## Configuração no docker-compose

O serviço já foi adicionado:

```yaml
duckdb-api:
  build:
    context: ./duckdb-api
    dockerfile: Dockerfile
  container_name: duckdb-api
  environment:
    - MINIO_ENDPOINT=http://minio:9000
    - MINIO_ACCESS_KEY_ID=admin
    - MINIO_SECRET_ACCESS_KEY=admin123
    - MINIO_BUCKET_RAW=lab01
    - DUCKDB_PATH=/opt/duckdb/datalake.duckdb
  volumes:
    - ./ddb:/opt/duckdb
  ports:
    - "5000:5000"
  depends_on:
    - minio
  networks:
    - airflow_net
```

---

## Como iniciar?

```bash
# 1. Build da imagem DuckDB
docker-compose build duckdb-api

# 2. Subir o container
docker-compose up duckdb-api -d

# 3. Verificar logs
docker-compose logs -f duckdb-api

# 4. Acessar interface web
# http://localhost:8088/query-builder

# 5. Testar API diretamente
curl -X POST http://localhost:5000/health
```

---

## Problemas comuns

### ❌ "Connection refused to DuckDB API"
- Verifique se o container está rodando: `docker-compose ps duckdb-api`
- Confirme que MinIO está online: `docker-compose ps minio`

### ❌ "S3 access denied"
- Valide credenciais MinIO no `.env` da webapp
- Verifique se bucket `lab01` existe no MinIO

### ❌ "Parquet file not found"
- Confirme caminho do arquivo: `s3://lab01/bronze/...`
- Validar que arquivos foram criados pelo Airflow

### ❌ "Query timeout"
- Aumente `timeout` no DuckDBHelper.php (padrão: 30s)
- Reduza tamanho do dataset ou adicione filtros WHERE

---

## Próximos passos

1. **Integrar Query Builder na webapp**: Adicionar link no menu
2. **Criar templates**: Views para exibir resultados em tabelas/gráficos
3. **Salvar queries**: Armazenar queries favoritas no MySQL
4. **Permissões**: Controlar quem pode executar queries
5. **Auditoria**: Log de todas as queries executadas

---

## Documentação

- [DuckDB Docs](https://duckdb.org/docs/)
- [DuckDB S3/Parquet](https://duckdb.org/docs/extensions/httpfs.html)
- [FastAPI Docs](https://fastapi.tiangolo.com/)
- [CodeIgniter 4 Docs](https://codeigniter.com/user_guide/)
