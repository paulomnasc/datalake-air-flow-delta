# 🔧 GUIA: Corrigindo Validador Customizado com Sincronização

## 📋 Problema Identificado

Seu `MeuValidador.py` estava causando **corrupção no arquivo Parquet** da Silver. A razão:

```
raw_to_medallion() → Bronze → Silver → Gold → Delta (SIMULTÂNEO)
                                ↓
                    MeuValidador tenta re-salvar Silver
                    ENQUANTO Gold Layer está lendo!
                    
RESULTADO: Arquivo truncado/corrompido
```

---

## ✅ Solução 1: Validações na GOLD (Recomendado - Mais Simples)

**Quando usar:** Se você quer validar/transformar dados APÓS o pipeline completo.

### Passo 1: Substituir o validador

```bash
# Backup do antigo
cp meu_validador.py meu_validador_BACKUP.py

# Usar o novo (sincronizado)
cp meu_validador_CORRIGIDO.py meu_validador.py
```

### Passo 2: Configurar no MySQL

```sql
-- Parar de usar o antigo
UPDATE dag_configurations 
SET python_module_path = NULL 
WHERE python_module_path LIKE '%meu_validador%';

-- Usar o novo (sincronizado)
UPDATE dag_configurations 
SET python_module_path = 'meu_validador_CORRIGIDO.MeuValidadorCORRIGIDO'
WHERE dag_id = 'sua_dag_id';
```

### Passo 3: Executar

```bash
airflow dags trigger sua_dag_id -e 2026-01-17
```

### Logs esperados:

```
[MeuValidadorCORRIGIDO] 🚀 Iniciando pipeline para Customer
[MeuValidadorCORRIGIDO] 📦 ETAPA 1: Executando pipeline padrão...
[MEDALLION] ✅ Pipeline completo concluído com sucesso!
[CustomValidationsGold] 📥 Lendo Gold: silver/...
[CustomValidationsGold] ✅ Gold carregado: 10 registros
[CustomValidationsGold] ✅ Gold atualizada com validações!
```

---

## ✅ Solução 2: Validações DENTRO do Pipeline (Mais Robusto)

**Quando usar:** Se você quer validações aplicadas DURANTE a criação da Silver.

### Vantagens:
- ✅ Sincronização automática (sem race conditions)
- ✅ Validação aplicada logo após limpeza
- ✅ Sem precisa re-salvar nada
- ✅ Mais rápido

### Passo 1: Criar validador customizado

```bash
mkdir -p /root/datalake-air-flow-delta/src/dags/lib/validadores
```

Criar arquivo: `src/dags/lib/validadores/meu_validador.py`

```python
"""
Validador customizado que roda DENTRO do pipeline Medallion.
Sincronização automática - SEGURO!
"""
import pandas as pd
import logging

log = logging.getLogger(__name__)


def validate(df, target_table_name: str = None):
    """
    Função chamada automaticamente durante bronze_to_silver().
    
    Args:
        df: DataFrame da Bronze pronto para Silver
        target_table_name: Nome da tabela (ex: 'Customer')
    
    Returns:
        df: DataFrame transformado/validado
    """
    
    log.info(f"[MeuValidador] Aplicando validações em {target_table_name}")
    log.info(f"[MeuValidador] Entrada: {len(df)} registros, {len(df.columns)} colunas")
    
    # ───────────────────────────────────────────────────
    # VALIDAÇÃO 1: CEP (Billing Postal Code)
    # ───────────────────────────────────────────────────
    if 'billingpostalcode' in df.columns:
        log.info("[MeuValidador] 🔍 Tratando 'billingpostalcode'...")
        
        # Contar inválidos
        invalid_mask = (
            (df['billingpostalcode'].isnull()) |
            (df['billingpostalcode'].astype(str).str.strip().str.lower()
             .isin(['nan', 'none', 'null', '']))
        )
        invalid_count = invalid_mask.sum()
        
        if invalid_count > 0:
            log.info(f"[MeuValidador]   Encontrados {invalid_count} valores inválidos")
            df.loc[invalid_mask, 'billingpostalcode'] = None
        
        log.info(f"[MeuValidador] ✅ CEP tratado")
    
    # ───────────────────────────────────────────────────
    # VALIDAÇÃO 2: Remover colunas desnecessárias
    # ───────────────────────────────────────────────────
    cols_to_drop = []
    for col in df.columns:
        # Remover colunas que sejam 100% nulas
        if df[col].isnull().all():
            cols_to_drop.append(col)
    
    if cols_to_drop:
        log.info(f"[MeuValidador] Removendo {len(cols_to_drop)} colunas 100% nulas: {cols_to_drop}")
        df = df.drop(columns=cols_to_drop)
    
    # ───────────────────────────────────────────────────
    # VALIDAÇÃO 3: Data Quality Score
    # ───────────────────────────────────────────────────
    quality_score = (df.notna().sum().sum() / df.size) * 100
    log.info(f"[MeuValidador] 📊 Quality Score: {quality_score:.2f}%")
    
    if quality_score < 80:
        log.warning(f"[MeuValidador] ⚠️ Quality baixa! ({quality_score:.2f}%)")
    
    log.info(f"[MeuValidador] Saída: {len(df)} registros, {len(df.columns)} colunas")
    
    return df
```

### Passo 2: Configurar no MySQL

```sql
-- Se está usando MeuValidador como classe, remover
UPDATE dag_configurations 
SET python_module_path = NULL 
WHERE python_module_path LIKE '%MeuValidador%';

-- Configurar validador customizado (NOVO)
UPDATE dag_configurations 
SET validation_rules_module = 'lib.validadores.meu_validador'
WHERE dag_id = 'sua_dag_id';

-- Habilitar validações
UPDATE dag_configurations 
SET validation_rules_enabled = 1
WHERE dag_id = 'sua_dag_id';
```

### Passo 3: Executar

```bash
airflow dags trigger sua_dag_id -e 2026-01-17
```

### Logs esperados:

```
[SILVER] Aplicando validação de qualidade de dados...
[MeuValidador] Aplicando validações em Customer
[MeuValidador] 🔍 Tratando 'billingpostalcode'...
[MeuValidador] Encontrados 3 valores inválidos
[MeuValidador] ✅ CEP tratado
[MeuValidador] 📊 Quality Score: 95.50%
[SILVER] ✅ Validação de qualidade concluída
```

---

## 🔍 Verificar Integridade do Parquet

Após usar uma das soluções acima, verificar que o parquet está OK:

```bash
# Dentro do container duckdb-api
printf 'import duckdb\ncon=duckdb.connect(database=":memory:")\ncon.execute("INSTALL httpfs")\ncon.execute("LOAD httpfs")\ncon.execute("SET s3_region='"'"'us-east-1'"'"'")\ncon.execute("SET s3_endpoint='"'"'minio:9000'"'"'")\ncon.execute("SET s3_url_style='"'"'path'"'"'")\ncon.execute("SET s3_use_ssl=false")\ncon.execute("SET s3_access_key_id='"'"'admin'"'"'")\ncon.execute("SET s3_secret_access_key='"'"'admin123'"'"'")\nres = con.execute("SELECT COUNT(*), COUNT(DISTINCT *) FROM read_parquet('"'"'s3://admin-146/silver/seu_dag_id/Customer/Customer.parquet'"'"')")\nprint(res.fetchdf())\n' | docker exec -i duckdb-api python -
```

Saída esperada:
```
   count(*) count(distinct *)
0       100                100
```

---

## 📊 Comparação das Soluções

| Aspecto | Solução 1 (GOLD) | Solução 2 (Validador) |
|---------|------------------|----------------------|
| **Sincronização** | Segura (pipeline 100% completo) | Automática (dentro do pipeline) |
| **Velocidade** | Mais lenta (reprocessa Gold) | Mais rápida (validação inline) |
| **Complexidade** | Simples | Média |
| **Flexibilidade** | Alta (fácil debugar) | Alta (fácil reutilizar) |
| **Casos de uso** | Validações pós-pipeline | Validações durante pipeline |
| **Risco de corrupção** | ❌ NENHUM | ❌ NENHUM |

---

## ⚠️ O QUE NÃO FAZER

```python
# ❌ ERRADO: Re-salvar Silver após raw_to_medallion()
silver_key = pipeline_result.get('silver')
df_silver = read_parquet(s3://bucket/silver_key)
df_silver['column'] = transform(df_silver['column'])
save_parquet(df_silver, s3://bucket/silver_key)  # ← CORRUPÇÃO!

# ✅ CORRETO: Re-salvar Gold (pipeline já completou)
gold_key = pipeline_result.get('gold')
df_gold = read_parquet(s3://bucket/gold_key)
df_gold['column'] = transform(df_gold['column'])
save_parquet(df_gold, s3://bucket/gold_key)  # ← SEGURO!

# ✅ CORRETO: Usar validador dentro do pipeline
def validate(df, target_table_name):
    df['column'] = transform(df['column'])
    return df
# Chamado automaticamente durante bronze_to_silver()
```

---

## 🚀 Próximos Passos

1. **Escolher uma solução** (recomendo Solução 2 para novo código)
2. **Testar com DAG teste** antes de usar em produção
3. **Monitorar logs** para confirmar validações sendo executadas
4. **Verificar parquet** com DuckDB para garantir integridade
5. **Documentar suas regras** de validação customizada

---

## 💡 Debugging

Se ainda tiver problemas:

```bash
# Ver logs completos
airflow tasks log sua_dag_id task_id 2026-01-17

# Ver resultado do pipeline (XCom)
airflow tasks xcom-list sua_dag_id task_id 2026-01-17

# Testar parquet local
duckdb -c "SELECT * FROM read_parquet('/tmp/Customer.parquet') LIMIT 5"

# Conferir arquivo no S3 via MinIO
docker exec datalake-air-flow-delta-minio-1 mc ls myminio/admin-146/silver/seu_dag_id/Customer/
```

