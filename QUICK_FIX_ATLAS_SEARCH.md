# 🔧 Quick Fix: Apache Atlas Search/Filter Not Working

## 🐛 Problema

Ao acessar o Apache Atlas (http://localhost:21000) e tentar usar a busca ou filtros, você vê:

```
Results for: Type: _ALL_ENTITY_TYPES
If you do not find the entity in search result below then you can create new entity
```

E nada aparece, mesmo com entidades cadastradas.

### Sintomas

- ✅ Atlas UI carrega normalmente
- ✅ Login funciona (admin/admin)
- ❌ Search/filtros não retornam resultados
- ❌ API retorna erro 500 ou NullPointerException
- ❌ Logs mostram: `Could not find a healthy node to handle the request`

### Causa Raiz

O **Solr embutido no Atlas não inicializou corretamente**, impossibilitando indexação e busca de entidades.

Isso geralmente acontece quando:
1. Container foi reiniciado abruptamente
2. Volumes do Atlas ficaram corrompidos
3. Falta de memória durante inicialização
4. Dependências foram inicializadas fora de ordem

---

## ✅ Solução Rápida (Recomendada)

### Opção 1: Usar o script de restart completo

```bash
cd ~/datalake-air-flow
chmod +x restart.sh
./restart.sh
```

Este script:
- ✅ Para toda a stack
- ✅ Reconstrói imagens necessárias
- ✅ Inicia serviços na ordem correta
- ✅ Aguarda Atlas inicializar completamente

**Tempo estimado**: 5-7 minutos

---

## 🔧 Solução Manual (Se restart.sh não funcionar)

### Passo 1: Limpar volumes do Atlas

```bash
cd ~/datalake-air-flow

# Parar e remover container
docker-compose down
docker stop apache-atlas
docker rm apache-atlas

# Remover volumes corrompidos
docker volume rm datalake-air-flow_atlas_data datalake-air-flow_atlas_logs
```

### Passo 2: Reiniciar Atlas com volumes limpos

```bash
# Subir Atlas
docker-compose up -d atlas

# Monitorar inicialização (aguarde ver "isHealthy=true")
docker logs apache-atlas -f
```

**Aguarde ver no log**:
```
Local Solr started!
indexBackEnd=solr; isHealthy=true
```

Isso pode levar **3-5 minutos** na primeira inicialização.

### Passo 3: Reiniciar Airflow

```bash
# Remover containers antigos
docker rm airflow-webserver airflow-scheduler

# Subir novamente
docker-compose up -d airflow-webserver airflow-scheduler
```

### Passo 4: Verificar funcionamento

```bash
# Testar API do Atlas
curl -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic?limit=5"

# Deve retornar JSON com campo "entities"
```

---

## 🔄 Popular o Atlas Novamente

⚠️ **Importante**: Limpar volumes do Atlas **apaga todos os metadados**!

Você precisa re-executar suas DAGs para recriar as entidades:

1. **Acesse o Airflow**: http://localhost:8085
2. **Execute uma DAG** que usa `mysql_to_medallion()` ou pipeline medallion
3. **Aguarde conclusão** da DAG
4. **Verifique no Atlas**: http://localhost:21000

### DAGs que populam o Atlas:

- Qualquer DAG com `python_module_path = 'lib.mysql_ingestion.mysql_to_medallion'`
- Qualquer DAG com `python_module_path = 'lib.medallion_pipeline.raw_to_medallion'`

---

## 🔍 Verificação de Saúde do Atlas

### Via Logs

```bash
# Verificar se Solr está saudável
docker logs apache-atlas 2>&1 | grep "isHealthy"

# Deve mostrar:
# indexBackEnd=solr; isHealthy=true
```

### Via API

```bash
# Buscar entidades
curl -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic"

# Listar tipos de entidades
curl -u admin:admin "http://localhost:21000/api/atlas/v2/types/typedefs"
```

### Via Interface Web

1. Acesse: http://localhost:21000
2. Login: `admin` / `admin`
3. Vá em **Search** → **Basic Search**
4. Deve listar entidades (se houver)

---

## 🛡️ Prevenção

### 1. Sempre parar a stack corretamente

```bash
# ✅ CORRETO
docker-compose down

# ❌ ERRADO
docker kill $(docker ps -q)
```

### 2. Aumentar memória do Atlas (se necessário)

Edite [docker-compose.yml](docker-compose.yml):

```yaml
atlas:
  environment:
    - JAVA_OPTS=-Xmx2048m -Xms1024m  # Aumentar de 1024m para 2048m
  mem_limit: 4g  # Aumentar de 2g para 4g
```

### 3. Usar restart.sh para reinicializações

O script garante ordem correta e tempos de espera:

```bash
./restart.sh
```

---

## 📊 Troubleshooting Avançado

### Problema: Atlas não inicia mesmo após limpar volumes

```bash
# Verificar logs completos
docker logs apache-atlas --tail 100

# Procurar por erros
docker logs apache-atlas 2>&1 | grep -i "error\|exception\|failed"
```

### Problema: Porta 21000 já em uso

```bash
# Verificar processo usando a porta
lsof -i :21000

# Ou verificar containers duplicados
docker ps -a | grep atlas
docker rm $(docker ps -a | grep atlas | awk '{print $1}')
```

### Problema: "Network overlap" ao iniciar

```bash
# Remover redes antigas
docker network prune

# Reiniciar stack
./restart.sh
```

---

## 📚 Arquivos Relacionados

- [docker-compose.yml](docker-compose.yml) - Configuração da stack
- [restart.sh](restart.sh) - Script de reinicialização
- [ATLAS_LINEAGE_FIX.md](ATLAS_LINEAGE_FIX.md) - Configuração de lineage
- [src/dags/lib/atlas_client.py](src/dags/lib/atlas_client.py) - Cliente Python do Atlas

---

## ⏱️ Tempo Estimado de Resolução

| Método | Tempo | Perda de Dados |
|--------|-------|----------------|
| **restart.sh** | 5-7 min | ❌ Não |
| **Reiniciar Atlas apenas** | 5 min | ❌ Não |
| **Limpar volumes** | 3-5 min | ⚠️ **Sim** (metadados do Atlas) |

---

## ✅ Checklist de Verificação

Após aplicar o fix, verifique:

- [ ] Atlas UI carrega em http://localhost:21000
- [ ] Login funciona (admin/admin)
- [ ] Logs mostram `isHealthy=true`
- [ ] API retorna JSON válido
- [ ] Search/filtros funcionam (se houver entidades)
- [ ] Airflow consegue conectar no Atlas
- [ ] DAGs executam sem erros de Atlas

---

## 🆘 Se nada funcionar

1. **Backup dos dados importantes** (se houver)
2. **Remover TODA a stack**:
   ```bash
   docker-compose down -v  # ⚠️ Remove TODOS os volumes
   ```
3. **Subir tudo novamente**:
   ```bash
   ./restart.sh
   ```

---

**Última atualização**: 2025-12-18  
**Versão do Atlas**: 2.3.0 (sburn/apache-atlas:latest)
