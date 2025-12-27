# GUIA: Isolamento de Buckets por Usuário - DAGs e Processamento Medallion

## ⚠️ Importante

O sistema de DAGs atual está configurado para processar dados do bucket **lab01** compartilhado.
Para implementar isolamento completo por usuário, são necessárias adaptações arquiteturais significativas.

## 🏗️ Arquitetura Atual vs. Proposta

### Arquitetura Atual (lab01 compartilhado)
```
Webapp Upload → lab01/raw/dag_id/arquivo.csv
           ↓
DAG Airflow processa: lab01/raw → lab01/bronze → lab01/silver → lab01/gold
           ↓
Usuários veem: Todos os dados em lab01
```

### Arquitetura Proposta (user-{id} isolado)
```
Webapp Upload → user-{id}/raw/dag_id/arquivo.csv
           ↓
DAG Airflow processa: user-{id}/raw → user-{id}/bronze → user-{id}/silver → user-{id}/gold
           ↓
Usuários veem: Apenas dados do seu bucket (user-{id})
```

## 🔧 Mudanças Necessárias

### 1. ConfigController (Upload de Arquivos)

**Arquivo**: `src/codeigniter-app/app/Controllers/ConfigController.php`

**Mudança**: Linha 408
```php
// ANTES:
$bucket = $this->bucketName ?: 'lab01';

// DEPOIS:
use App\Helpers\SessionHelper;
$bucket = SessionHelper::getUserBucket() ?: $this->bucketName ?: 'lab01';
```

### 2. DAGs do Airflow

**Problema**: DAGs são definidos estaticamente no código Python, processam bucket fixo.

**Solução**: Passar bucket como parâmetro de configuração da DAG.

#### factory_master.py

```python
# ADICIONAR função auxiliar no início do arquivo
def get_user_bucket_from_dag_config(dag_config: Dict[str, Any]) -> str:
    """
    Extrai o bucket do usuário da configuração da DAG.
    Se não especificado, usa 'lab01' como fallback.
    """
    return dag_config.get('user_bucket') or dag_config.get('bucket_name') or os.environ.get('MINIO_BUCKET', 'lab01')

# MODIFICAR list_files_from_minio_folder (linha 121)
def list_files_from_minio_folder(folder_path: str, bucket_name: str = None) -> List[str]:
    """Lista arquivos de uma pasta no MinIO"""
    if bucket_name is None:
        bucket_name = os.environ.get('MINIO_BUCKET', 'lab01')
    # ... resto do código

# MODIFICAR run_bronze_layer_transformation (linha ~225)
def run_bronze_layer_transformation(**kwargs):
    transform_args = kwargs.get('params', {}) or {}
    bucket_name = get_user_bucket_from_dag_config(transform_args)  # NOVO
    # ... usar bucket_name em vez de hardcoded 'lab01'

# MODIFICAR run_silver_layer_transformation (linha ~313)
def run_silver_layer_transformation(**kwargs):
    bucket = get_user_bucket_from_dag_config(kwargs.get('params', {}))  # NOVO
    # ... usar bucket em vez de os.environ.get('MINIO_BUCKET', 'lab01')
```

#### Tabela dag_config (MySQL)

**Adicionar coluna**:
```sql
ALTER TABLE dag_config 
ADD COLUMN user_bucket VARCHAR(100) DEFAULT NULL COMMENT 'Bucket do usuário (user-{id})';

-- Popular buckets existentes para migração
UPDATE dag_config dc
JOIN usuarios u ON u.id = dc.created_by_user_id
SET dc.user_bucket = CONCAT('user-', u.id)
WHERE dc.user_bucket IS NULL;
```

#### Webapp - Criação de DAG

**Arquivo**: Controller que cria DAGs (onde insere em `dag_config`)

```php
use App\Helpers\SessionHelper;

// Ao inserir nova DAG:
$dagData = [
    'dag_id' => $dagId,
    'config_json' => json_encode($config),
    'user_bucket' => SessionHelper::getUserBucket(),  // NOVO
    'created_by_user_id' => SessionHelper::getUserId(),
    // ... outros campos
];
```

### 3. Bibliotecas Medallion

**Arquivos a modificar**:
- `src/dags/lib/bronze_layer.py`
- `src/dags/lib/silver_layer.py`
- `src/dags/lib/gold_layer.py`
- `src/dags/lib/gold_delta_layer.py`
- `src/dags/lib/minio_tasks.py`

**Padrão de mudança** (exemplo bronze_layer.py linha 29):
```python
# ANTES:
bucket = os.environ.get("MINIO_BUCKET", "lab01")

# DEPOIS:
bucket = kwargs.get('bucket_name') or os.environ.get("MINIO_BUCKET", "lab01")
```

### 4. sync_delta_to_postgres.py

**Linha 24-28**:
```python
# ANTES:
MINIO_BUCKET = 'lab01'
DELTA_PATHS = [
    's3://lab01/delta/*/*.parquet',
    's3://lab01/delta/*/*/*.parquet',
]

# DEPOIS:
def get_delta_paths_for_bucket(bucket: str) -> List[str]:
    return [
        f's3://{bucket}/delta/*/*.parquet',
        f's3://{bucket}/delta/*/*/*.parquet',
    ]

# Usar em: pattern_list = get_delta_paths_for_bucket(bucket_name)
```

## 🎯 Estratégia de Implementação Recomendada

### Fase 1: Webapp Isolado (✅ CONCLUÍDO)
- [x] SessionHelper criado
- [x] Query Builder filtra por user bucket
- [x] Validação de acesso cross-bucket

### Fase 2: Upload Direcionado (📍 VOCÊ ESTÁ AQUI)
- [ ] ConfigController usar SessionHelper para bucket
- [ ] Adicionar coluna `user_bucket` em `dag_config`
- [ ] Migration SQL para popular buckets

### Fase 3: DAGs Multi-Tenant
- [ ] Modificar factory_master.py para suportar bucket dinâmico
- [ ] Adaptar bibliotecas medallion (bronze, silver, gold)
- [ ] Testar processamento isolado

### Fase 4: Migração de Dados
- [ ] Script para copiar dados de lab01 para user-{id} buckets
- [ ] Validação de integridade

### Fase 5: Limpeza
- [ ] Remover fallbacks para 'lab01'
- [ ] Documentação completa
- [ ] Testes end-to-end

## 🚨 Considerações Importantes

### Performance
- Cada bucket user-{id} terá seu próprio medallion (bronze/silver/gold)
- Processamento paralelo de múltiplos usuários aumenta carga no cluster Spark
- Considerar quotas e limites por usuário

### Custos de Armazenamento
- Duplicação de dados se múltiplos usuários processam mesmos datasets
- MinIO pode ter limites de número de buckets
- Considerar estratégia de limpeza de buckets inativos

### Compatibilidade
- DAGs antigas continuarão funcionando com lab01 (fallback)
- Migração gradual possível
- Rollback sem perda de dados

## 📊 Exemplo de Configuração de DAG

```json
{
  "dag_id": "mysql_northwind_to_parquet",
  "user_bucket": "user-42",
  "schedule_interval": "@daily",
  "mysql_connection": "northwind_db",
  "target_tables": ["customers", "orders"],
  "layers": {
    "bronze": {"enabled": true, "format": "parquet"},
    "silver": {"enabled": true, "transformations": ["deduplicate"]},
    "gold": {"enabled": true, "format": "delta"}
  }
}
```

## 🧪 Script de Teste

```bash
#!/bin/bash
# Testar isolamento de buckets

USER_ID=42
USER_BUCKET="user-${USER_ID}"

# 1. Criar bucket de teste
docker exec minio mc mb "minio/${USER_BUCKET}"

# 2. Upload de arquivo de teste
echo "id,name\n1,Test User" > /tmp/test.csv
docker exec minio mc cp /tmp/test.csv "minio/${USER_BUCKET}/raw/test_dag/test.csv"

# 3. Verificar isolamento
docker exec minio mc ls "minio/${USER_BUCKET}/" | grep test.csv

# 4. Tentar acessar bucket de outro usuário (deve falhar na webapp)
curl -X POST http://localhost:8088/query-builder/execute \
  -H "Content-Type: application/json" \
  -d '{"sql":"SELECT * FROM read_parquet(\"s3://user-99/bronze/data.parquet\")"}'
# Esperado: {"success":false,"error":"Acesso negado: você não pode consultar dados de outros usuários"}
```

## 📝 Próximos Passos Imediatos

1. **Migration SQL** (criar arquivo `migrations/add_user_bucket_to_dag_config.sql`)
2. **Atualizar ConfigController** (linha 408)
3. **Modificar factory_master.py** (adicionar `get_user_bucket_from_dag_config`)
4. **Testar upload** com usuário logado
5. **Validar isolamento** no Query Builder

---

**Autor**: Sistema de Isolamento Multi-Tenant  
**Data**: 2025-12-27  
**Status**: Em Implementação
