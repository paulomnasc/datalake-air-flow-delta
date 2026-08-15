# 🚀 Guia de Ativação - Query Builder DuckDB

## ✅ O que foi adicionado?

1. **Link no menu SERVIÇOS** → `🦆 Query Builder Parquet`
2. **Rotas configuradas** → Todas as rotas do QueryBuilderController
3. **Helper DuckDB** → Facilita chamadas à API
4. **Interface web completa** → Editor SQL + visualização de resultados

---

## 📋 Passo a passo para ativar

### 1️⃣ Build do container DuckDB

```bash
cd /home/cblna123456/datalake-air-flow

# Build da imagem
docker-compose build duckdb-api

# Subir o serviço
docker-compose up duckdb-api -d

# Verificar logs
docker-compose logs -f duckdb-api
```

**Esperado:**
```
✅ DuckDB connection established with S3 config
✅ DuckDB startup check passed
INFO:     Uvicorn running on http://0.0.0.0:5000
```

---

### 2️⃣ Testar API diretamente

```bash
# Health check
curl http://localhost:5000/health

# Resposta esperada:
# {
#   "status": "healthy",
#   "service": "DuckDB Query API",
#   "minio_bucket": "lab01",
#   "duckdb_path": "/opt/duckdb/datalake.duckdb"
# }
```

---

### 3️⃣ Acessar interface web

1. **Abra o navegador**
2. **Acesse:** `http://localhost:8088/query-builder`
3. **Você verá:**
   - Editor SQL no centro
   - Lista de arquivos Parquet à esquerda
   - Status da API no topo (🟢 DuckDB Online)

---

### 4️⃣ Executar primeira query

No editor SQL, digite:

```sql
SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 10
```

Clique em **▶️ Executar**

**Resultado esperado:**
- Tabela com até 10 linhas de dados
- Status: "✅ X linhas retornadas"

---

## 🧪 Verificações

### Checklist de funcionamento:

- [ ] Container `duckdb-api` está rodando
- [ ] Health check retorna `{"status": "healthy"}`
- [ ] Interface web carrega em `/query-builder`
- [ ] Menu **SERVIÇOS** exibe link "🦆 Query Builder Parquet"
- [ ] Query SQL retorna dados dos arquivos Parquet

---

## ❌ Problemas comuns

### "DuckDB Offline" no status badge

**Causa:** Container não está rodando  
**Solução:**
```bash
docker-compose up duckdb-api -d
docker-compose logs duckdb-api
```

---

### "Erro ao carregar arquivos" na sidebar

**Causa:** Nenhum arquivo Parquet no MinIO  
**Solução:**
1. Execute uma DAG do Airflow para criar arquivos Bronze
2. Ou ajuste o path de busca no código

---

### "Call to undefined function helper()"

**Causa:** Helper não foi carregado  
**Solução:**
```php
// No QueryBuilderController.php, adicione no construtor:
public function __construct()
{
    helper('DuckDB');
}
```

---

### "404 Not Found" ao acessar /query-builder

**Causa:** Rotas não foram carregadas  
**Solução:**
```bash
# Limpe cache de rotas do CodeIgniter
rm -rf src/codeigniter-app/writable/cache/*
```

---

## 📊 Monitoramento

### Ver logs em tempo real:

```bash
# DuckDB API
docker-compose logs -f duckdb-api

# Webapp CodeIgniter
docker-compose logs -f codeigniter-app
```

---

## 🎯 Próximos passos recomendados

1. **Testar com dados reais:** Execute DAGs do Airflow para criar Parquets
2. **Ajustar permissões:** Definir quais usuários podem acessar Query Builder
3. **Criar queries salvas:** Adicionar funcionalidade de favoritos
4. **Integrar com dashboards:** Exibir resultados em gráficos

---

## 📚 Documentação completa

Consulte:
- `duckdb-api/README.md` - Documentação completa da API
- `src/codeigniter-app/app/Controllers/QueryBuilderController.php` - Código-fonte
- `src/codeigniter-app/app/Helpers/DuckDBHelper.php` - Helper PHP

---

**Precisa de ajuda?** Verifique os logs primeiro!
