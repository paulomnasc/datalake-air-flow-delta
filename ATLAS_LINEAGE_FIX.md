# 🔧 Correção do Data Lineage no Apache Atlas

## 📋 Problema Identificado

O data lineage não estava sendo registrado corretamente no Apache Atlas, impossibilitando rastrear o caminho completo dos dados desde a origem (MySQL) até a camada Gold (Delta Lake).

### O que estava faltando:

1. **`ATLAS_REGISTER_PROCESSES` estava desabilitado** - As entidades (tabelas) eram registradas, mas os processos (transformações) não
2. **Falta de registro da origem MySQL** - O MySQL não era registrado como entidade de origem
3. **Processo MySQL→Raw não existia** - Não havia conexão entre MySQL e a camada Raw

## ✅ Correções Implementadas

### 1. Habilitação do Registro de Processos

**Arquivo:** [docker compose.yml](docker compose.yml)

Adicionadas variáveis de ambiente nos containers `airflow-scheduler` e `airflow-webserver`:

```yaml
environment:
  # Configurações do Apache Atlas para Data Lineage
  - ATLAS_HOST=http://apache-atlas:21000
  - ATLAS_USER=admin
  - ATLAS_PASS=admin
  - ATLAS_HIVE_DB=medallion
  - ATLAS_REGISTER_PROCESSES=true  # ← CRÍTICO: Habilita lineage
  - ATLAS_HTTP_TIMEOUT=90.0
  - ATLAS_MAX_RETRIES=5
  - ATLAS_BACKOFF_SECONDS=2.0
```

### 2. Registro da Origem MySQL

**Arquivo:** [src/dags/lib/atlas_client.py](src/dags/lib/atlas_client.py)

Nova função `create_mysql_table()` para registrar tabelas MySQL como entidades no Atlas:

```python
def create_mysql_table(self, qualified_name: str, name: str, db: str, 
                      columns: Optional[List[Dict]] = None, 
                      description: str = "", row_count: int = 0) -> Dict:
    """Cria uma referência de tabela MySQL no Atlas"""
    # Registra tabela MySQL como EXTERNAL_TABLE
```

### 3. Registro do Processo MySQL→Raw

**Arquivo:** [src/dags/lib/mysql_ingestion.py](src/dags/lib/mysql_ingestion.py)

Atualizada função `mysql_to_medallion()` para:
- Registrar tabela MySQL de origem no Atlas
- Passar `mysql_qualified_name` para o pipeline medallion
- Permitir criação do processo MySQL→Raw

**Arquivo:** [src/dags/lib/medallion_pipeline.py](src/dags/lib/medallion_pipeline.py)

Atualizada função `raw_to_medallion()` para:
- Aceitar `mysql_qualified_name` como parâmetro opcional
- Criar processo MySQL→Raw quando origem MySQL é detectada

## 🔄 Fluxo Completo do Lineage

Após as correções, o lineage ficará assim:

```
┌─────────────────────────────────────────────────────────────────┐
│                       APACHE ATLAS LINEAGE                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  MySQL.lista_revisao.customers                                  │
│         │                                                        │
│         │ [Processo: mysql_to_raw_customers]                    │
│         ↓                                                        │
│  medallion.customers_raw@cluster                                │
│         │                                                        │
│         │ [Processo: raw_to_bronze_customers]                   │
│         ↓                                                        │
│  medallion.customers_bronze@cluster                             │
│         │                                                        │
│         │ [Processo: bronze_to_silver_customers]                │
│         ↓                                                        │
│  medallion.customers_silver@cluster                             │
│         │                                                        │
│         │ [Processo: silver_to_gold_customers]                  │
│         ↓                                                        │
│  medallion.customers_gold@cluster (Delta Lake)                  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## 🚀 Como Testar

### 1. Reiniciar os Containers

```bash
docker compose down
docker compose up -d
```

### 2. Aguardar Atlas Inicializar

O Atlas pode levar 2-5 minutos para inicializar completamente:

```bash
# Verificar logs
docker logs apache-atlas -f

# Aguardar até ver mensagens como:
# "Server started listening on port 21000"
```

### 3. Executar uma DAG de Teste

Execute qualquer DAG que use `mysql_to_medallion()`, por exemplo:

```python
# Na interface do Airflow (http://localhost:8085)
# Executar DAG que processa dados do MySQL
```

### 4. Verificar Lineage no Atlas

#### Via Interface Web:
1. Acesse http://localhost:21000
2. Login: `admin` / `admin`
3. Vá em **Search** → **Basic Search**
4. Type: `hive_table`
5. Busque por: `customers_gold`
6. Clique na entidade
7. Aba **Lineage** → Visualize o diagrama completo

#### Via API:

```bash
# Listar todas as tabelas
curl -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic?typeName=hive_table" | jq

# Listar todos os processos
curl -u admin:admin "http://localhost:21000/api/atlas/v2/search/basic?typeName=hive_process" | jq

# Obter lineage de uma entidade específica (substitua GUID)
curl -u admin:admin "http://localhost:21000/api/atlas/v2/lineage/{GUID}" | jq
```

## 📊 Exemplo de Resultado Esperado

### Entidades Criadas:

| Entidade | Tipo | QualifiedName |
|----------|------|---------------|
| MySQL Source | hive_table | `medallion.mysql_customers@cluster` |
| Raw | hive_table | `medallion.customers_raw@cluster` |
| Bronze | hive_table | `medallion.customers_bronze@cluster` |
| Silver | hive_table | `medallion.customers_silver@cluster` |
| Gold | hive_table | `medallion.customers_gold@cluster` |

### Processos Criados:

| Processo | Tipo | Input → Output |
|----------|------|----------------|
| MySQL→Raw | hive_process | `mysql_customers` → `customers_raw` |
| Raw→Bronze | hive_process | `customers_raw` → `customers_bronze` |
| Bronze→Silver | hive_process | `customers_bronze` → `customers_silver` |
| Silver→Gold | hive_process | `customers_silver` → `customers_gold` |

## ⚠️ Troubleshooting

### Problema: Atlas não responde (timeout)

**Solução:**
```bash
# Verificar se está rodando
docker ps | grep atlas

# Verificar logs
docker logs apache-atlas --tail 100

# Reiniciar se necessário
docker restart apache-atlas
```

### Problema: Processos não aparecem no lineage

**Verificação:**
```bash
# Confirmar que ATLAS_REGISTER_PROCESSES=true
docker exec airflow-scheduler env | grep ATLAS

# Deve mostrar:
# ATLAS_REGISTER_PROCESSES=true
```

Se estiver `false`, edite o [docker compose.yml](docker compose.yml) e reinicie.

### Problema: Entidades criadas mas sem conexões

**Causa:** Os processos são criados depois das entidades. Precisa aguardar a execução completa da DAG.

**Solução:** Executar a DAG novamente do início.

## 📈 Benefícios do Lineage Completo

1. **Rastreabilidade**: Identificar origem de erros rapidamente
2. **Impacto de Mudanças**: Entender quais dados serão afetados por alterações
3. **Auditoria**: Compliance e governança de dados
4. **Documentação Automática**: Diagrama vivo da arquitetura de dados
5. **Data Discovery**: Facilita encontrar dados relevantes

## 🔗 Referências

- [README do Atlas Lab](atlas-lab/README.md)
- [Migrations Atlas](atlas-lab/migrations/migrations.md)
- [Implementação Delta Lake](DELTA_LAKE_IMPLEMENTATION.md)
- [Camadas do Datalake](DATALAKE_LAYERS.md)

## 📝 Próximos Passos

- [ ] Adicionar métricas de qualidade ao lineage
- [ ] Criar tipos customizados (bronze_table, silver_table, gold_delta_table)
- [ ] Implementar captura automática via Spark Listener
- [ ] Adicionar tags de classificação de dados
- [ ] Configurar políticas de retenção

---

**Data da Correção:** 15 de Dezembro de 2025  
**Autor:** Sistema de Data Engineering  
**Status:** ✅ Implementado e Pronto para Teste
