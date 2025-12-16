# ⚡ Quick Fix - Data Lineage não aparecendo

## 🔍 Problema Detectado

Apenas a tabela `orders_raw` foi criada, mas as outras camadas (bronze, silver, gold) não aparecem.

**Causa:** As variáveis de ambiente do Atlas não estavam carregadas nos containers quando a DAG foi executada.

## ✅ Solução Aplicada

Os containers foram reiniciados e as variáveis agora estão ativas:

```bash
✓ ATLAS_HOST=http://apache-atlas:21000
✓ ATLAS_USER=admin
✓ ATLAS_PASS=admin
✓ ATLAS_HIVE_DB=medallion
✓ ATLAS_REGISTER_PROCESSES=true  # ← CRUCIAL!
✓ ATLAS_HTTP_TIMEOUT=90.0
```

## 🚀 Próximos Passos

### 1. Execute a DAG Novamente

Acesse o Airflow e execute a DAG do zero:

1. Vá para http://localhost:8085
2. Encontre sua DAG (ex: `mysql_to_medallion` ou similar)
3. **Limpe a execução anterior:**
   - Clique na DAG
   - Menu: **"Clear"** → Clear all tasks
4. **Trigger nova execução:**
   - Clique no botão ▶️ "Trigger DAG"

### 2. Acompanhe a Execução

Durante a execução, verifique os logs para confirmar que o Atlas está sendo chamado:

```bash
# Ver logs em tempo real
docker logs airflow-scheduler -f | grep ATLAS
```

Você deve ver mensagens como:
```
[ATLAS] Registrando tabela RAW: medallion.orders_raw@cluster
[ATLAS] ✅ Processo Raw→Bronze registrado
[ATLAS] Registrando tabela Bronze: medallion.orders_bronze@cluster
[ATLAS] ✅ Processo Bronze→Silver registrado
[ATLAS] Registrando tabela Silver: medallion.orders_silver@cluster
[ATLAS] ✅ Processo Silver→Gold registrado
[ATLAS] Registrando tabela Gold: medallion.orders_gold@cluster
```

### 3. Aguarde Processamento do Atlas

Após a DAG terminar, **aguarde 1-2 minutos** para o Atlas indexar todas as entidades.

### 4. Verifique no Atlas

1. Acesse http://localhost:21000
2. Login: `admin` / `admin`
3. **Search** → **Basic Search**
4. Type: `hive_table`
5. Você deve ver:
   - ✅ `orders_raw`
   - ✅ `orders_bronze`
   - ✅ `orders_silver`
   - ✅ `orders_gold`

6. Clique em `orders_gold`
7. Vá na aba **Lineage**
8. Você verá o diagrama completo! 🎉

### 5. Validação Automática

Execute o script de validação:

```bash
./test_lineage.sh
```

Ou manualmente:

```bash
python3 scripts/validate_atlas_lineage.py
```

## 🔧 Se ainda não funcionar

### Verificar se a DAG usa o pipeline correto

A DAG deve usar `mysql_to_medallion()` para o lineage completo:

```python
from lib.mysql_ingestion import mysql_to_medallion

result = mysql_to_medallion(
    mysql_conn_id='mysql_default',
    table_name='orders',
    target_table_name='orders'
)
```

### Verificar logs de erro

```bash
# Logs do scheduler
docker logs airflow-scheduler --tail 500 | grep -i "error\|warning"

# Logs do Atlas
docker logs apache-atlas --tail 200
```

### Limpar Atlas e recriar entidades

Se necessário, você pode limpar o Atlas e reprocessar:

```bash
# Parar Atlas
docker-compose stop atlas

# Remover dados antigos
docker volume rm datalake-air-flow_atlas_data

# Reiniciar
docker-compose up -d atlas

# Aguardar inicialização (2-5 minutos)
# Executar DAG novamente
```

## 📊 Resultado Esperado

Após seguir os passos, você terá:

**No Atlas UI:**
```
MySQL → orders_raw → orders_bronze → orders_silver → orders_gold
        ↓              ↓                ↓                ↓
    [Process]      [Process]        [Process]        [Process]
```

**Via API:**
```bash
# 4 tabelas
curl -s -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic?typeName=hive_table" | jq '.entities | length'
# Output: 4

# 3 processos (raw→bronze, bronze→silver, silver→gold)
curl -s -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic?typeName=hive_process" | jq '.entities | length'
# Output: 3
```

---

**Data:** 15/12/2025  
**Status:** ✅ Variáveis configuradas - Aguardando reexecução da DAG
