# 🏗️ GUIA DE MIGRAÇÃO: Herança no Pipeline Medallion

## 📌 Resumo

Nova arquitetura com **Template Method Pattern** via herança garante:

- ✅ **Sincronização 100% garantida** (zero race conditions)
- ✅ **Simples de estender** (override apenas hooks necessários)
- ✅ **Backward compatible** (código antigo continua funcionando)
- ✅ **Sem corrupção de parquet** (impossível arquiteturalmente)

---

## 🔄 Como Migrar

### Opção 1: Manter Compatibilidade (Sem Mudanças)

Seu código antigo continua funcionando:

```python
# ANTES (continua funcionando)
from lib.medallion_pipeline import raw_to_medallion

result = raw_to_medallion(
    source_filename='raw/dados/Customer.csv',
    target_table_name='Customer'
)

# Internamente usa RawToMedallionPipeline (nova classe)
```

**Nada mudou para você!** O wrapper mantém compatibilidade.

---

### Opção 2: Novo Padrão com Herança (RECOMENDADO)

Herdar de `RawToMedallionPipeline` para customizações seguras:

#### Passo 1: Criar classe que herda

```python
# arquivo: src/dags/lib/validadores/meu_validador.py

from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging

log = logging.getLogger(__name__)


class MeuValidador(RawToMedallionPipeline):
    """
    Validador customizado com sincronização garantida.
    
    Herança oferece:
    - self.hook: S3 hook
    - self.bucket: Nome do bucket
    - self.tmpdir: Diretório temporário
    - self.results: Dict com resulados (bronze, silver, gold, delta)
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """
        Override este método para customizar a Silver.
        
        Roda APÓS Silver padrão estar salva.
        SEGURO sobrescrever arquivo.
        """
        
        log.info("[MeuValidador] Validando Silver...")
        
        # Baixar Silver
        local_file = self.hook.download_file(
            key=silver_key,
            bucket_name=self.bucket,
            local_path=self.tmpdir,
            preserve_file_name=True
        )
        
        # Transformar
        df = pd.read_parquet(local_file)
        df['coluna'] = self._sua_logica(df['coluna'])
        
        # Re-salvar
        df.to_parquet(local_file, index=False, compression='snappy')
        self.hook.load_file(
            filename=local_file,
            key=silver_key,
            bucket_name=self.bucket,
            replace=True
        )
        
        log.info("[MeuValidador] ✅ Silver validada")
        return silver_key
    
    def _sua_logica(self, series):
        """Sua lógica customizada aqui"""
        # Implementar aqui
        return series
```

#### Passo 2: Configurar no banco de dados

```sql
-- Usar novo padrão com classe
UPDATE dag_configurations 
SET python_module_path = 'lib.validadores.meu_validador.MeuValidador'
WHERE dag_id = 'sua_dag_id';
```

#### Passo 3: Executar

```bash
airflow dags trigger sua_dag_id
```

---

## 📚 Arquitetura Internamente

### Sequência Sincronizada

```
__call__(source_filename, target_table_name, **kwargs)
    │
    ├─ _setup()                        # 1. Inicializa
    │   └─ tmpdir, hooks, atlas
    │
    ├─ _process_bronze()               # 2. Bronze
    │   └─ Converte para Parquet
    │
    ├─ _process_silver()               # 3. Silver
    │   ├─ bronze_to_silver()          # Silver padrão
    │   └─ silver_layer_transform()    # ← HOOK CUSTOMIZADO
    │       (Roda APÓS Silver estar salva)
    │
    ├─ _process_gold()                 # 4. Gold
    │   ├─ silver_to_gold()            # Gold padrão
    │   └─ gold_layer_transform()      # ← HOOK CUSTOMIZADO
    │       (Roda APÓS Gold estar salva)
    │
    ├─ _process_delta()                # 5. Delta
    │   └─ gold_to_delta()
    │
    ├─ _cleanup()                      # 6. Cleanup
    │   └─ Remove tmpdir
    │
    └─ return results
```

**Cada etapa espera a anterior terminar → SINCRONIZAÇÃO GARANTIDA**

---

## 🔧 Exemplos Prontos

Veja: `src/dags/lib/validadores/exemplos_heranca.py`

### Exemplo 1: Validar CEP

```python
class CustomerValidador(RawToMedallionPipeline):
    def silver_layer_transform(self, silver_key: str) -> str:
        local_file = self.hook.download_file(
            key=silver_key, bucket_name=self.bucket, local_path=self.tmpdir, 
            preserve_file_name=True
        )
        df = pd.read_parquet(local_file)
        
        # Normalizar CEP
        df['billingpostalcode'] = df['billingpostalcode'].apply(
            lambda x: None if pd.isna(x) or str(x).lower() in ['nan', 'none', '']
            else str(x).strip()
        )
        
        df.to_parquet(local_file, index=False, compression='snappy')
        self.hook.load_file(
            filename=local_file, key=silver_key, bucket_name=self.bucket, replace=True
        )
        return silver_key
```

### Exemplo 2: Agregações de negócio

```python
class InvoiceAgregador(RawToMedallionPipeline):
    def gold_layer_transform(self, gold_key: str) -> str:
        local_file = self.hook.download_file(
            key=gold_key, bucket_name=self.bucket, local_path=self.tmpdir, 
            preserve_file_name=True
        )
        df = pd.read_parquet(local_file)
        
        # Categorizar por valor
        df['categoria'] = pd.cut(
            df['total'],
            bins=[0, 100, 500, 1000, float('inf')],
            labels=['pequeno', 'médio', 'grande', 'muito_grande']
        )
        
        df.to_parquet(local_file, index=False, compression='snappy')
        self.hook.load_file(
            filename=local_file, key=gold_key, bucket_name=self.bucket, replace=True
        )
        return gold_key
```

### Exemplo 3: Ambos (Silver + Gold)

```python
class TrackValidadorEAgregador(RawToMedallionPipeline):
    def silver_layer_transform(self, silver_key: str) -> str:
        # Validações...
        return silver_key
    
    def gold_layer_transform(self, gold_key: str) -> str:
        # Agregações...
        return gold_key
```

---

## 🎯 Casos de Uso

| Caso | Solução | Método |
|------|---------|--------|
| **Validar dados** | Herança Silver | `silver_layer_transform()` |
| **Agregar/enriquecer** | Herança Gold | `gold_layer_transform()` |
| **Ambos** | Herança ambos | Override ambos |
| **Função simples** | Compatibilidade | `raw_to_medallion()` |

---

## ⚠️ O QUE MUDOU

### Antes (Problema)

```python
# ❌ ERRADO: Race condition
class MeuValidador:
    def __call__(self, source_filename, target_table_name, **context):
        pipeline_result = raw_to_medallion(...)  # ← Roda paralelo
        self.custom_validations(...)             # ← Tenta modificar Silver
        # Silver e Gold podem estar sendo processadas SIMULTANEAMENTE
```

### Agora (Correto)

```python
# ✅ CORRETO: Sincronização garantida
class MeuValidador(RawToMedallionPipeline):
    def silver_layer_transform(self, silver_key: str) -> str:
        # Roda GARANTIDAMENTE APÓS Silver estar salva
        # Nenhuma race condition possível
        return silver_key
```

---

## 🔌 Integração com Factory Master

### Usar classe customizada (NOVO)

```python
# Em factory_master.py

# Importar validador
if python_module_path and '.' in python_module_path:
    # Novo: Suporta classes com herança
    module_name, class_name = python_module_path.rsplit('.', 1)
    module = __import__(module_name, fromlist=[class_name])
    processor_class = getattr(module, class_name)
    
    # Instantiate e chamar
    processor = processor_class()
    result = processor(
        source_filename=source_file,
        target_table_name=table_name,
        **dag_config
    )
```

---

## 📊 Comparação

| Aspecto | Antes | Depois |
|--------|--------|--------|
| **Sincronização** | Manual (risco) | Automática (segura) |
| **Race conditions** | ⚠️ Possível | ❌ Impossível |
| **Padrão** | Ad-hoc | Template Method |
| **Complexidade** | Alta | Baixa |
| **Corrupção** | ⚠️ Sim | ❌ Não |
| **Compatibilidade** | N/A | ✅ 100% |

---

## 🚀 Próximos Passos

1. **Revisar exemplos** em `exemplos_heranca.py`
2. **Criar seu validador** herdando de `RawToMedallionPipeline`
3. **Testar em DAG teste** antes de produção
4. **Gradualmente migrar** DAGs existentes (compatibilidade mantida)
5. **Documentar** suas regras customizadas

---

## 🐛 Troubleshooting

### Erro: "ModuleNotFoundError: No module named 'lib.medallion_pipeline_v2'"

**Solução:** Arquivo novo foi criado. Certifique-se que está em `src/dags/lib/`

```bash
ls -la src/dags/lib/medallion_pipeline_v2.py
```

### Erro: "AttributeError: 'module' object has no attribute 'MeuValidador'"

**Solução:** Verifique se a classe está exportada corretamente:

```python
# Correto
class MeuValidador(RawToMedallionPipeline):
    pass
```

### "Silver padrão não foi executada"

**Solução:** `silver_layer_transform()` é chamado APÓS `bronze_to_silver()`. Não substitui, apenas estende.

---

## 📖 Referências

- [RawToMedallionPipeline](medallion_pipeline_v2.py) - Classe base
- [Exemplos de uso](exemplos_heranca.py) - 3 exemplos prontos
- [Compatibilidade](medallion_pipeline.py) - Função wrapper antiga

