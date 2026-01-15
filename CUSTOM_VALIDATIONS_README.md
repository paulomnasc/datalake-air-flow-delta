# Sistema de Validações Customizadas - Medallion Architecture

## 📋 Visão Geral

Este sistema permite que usuários **criem suas próprias regras de validação** para pipelines Medallion (Bronze, Silver, Gold) **sem precisar editar código Python ou entender a implementação interna da `dag_factory`**.

### Vantagens

✅ **Interface Web Amigável** - Editor visual com Monaco (mesmo do VS Code)  
✅ **Zero Acoplamento** - Validações são plugins isolados, não quebram DAGs existentes  
✅ **Templates Prontos** - Validações comuns pré-configuradas (nulls, duplicatas, quality score)  
✅ **Multi-Camada** - Validações específicas para Bronze, Silver ou Gold  
✅ **Teste em Tempo Real** - Valida sintaxe Python antes de salvar  
✅ **Segurança** - Validações ficam isoladas por bucket de usuário  

---

## 🚀 Como Usar

### 1. Acessar o Editor

Navegue para `/validation-rules-editor` no Code Editor.

### 2. Criar Nova Regra

1. Clique em **"+ Nova Regra"**
2. Preencha:
   - **Nome**: identificador único (ex: `validar_cpf`)
   - **Camada**: Bronze, Silver ou Gold
   - **Tabela**: (opcional) aplicar só em tabela específica
   - **Descrição**: o que a regra valida
   - **Código Python**: função `validate(df, **context)`

### 3. Usar Template ou Código Próprio

**Opção A - Usar Template:**
- Clique em um template (ex: "Verificar Nulos")
- Template é carregado no editor
- Customize conforme necessário

**Opção B - Escrever do Zero:**
```python
def validate(df, **context):
    """
    Valida dados do DataFrame.
    
    Args:
        df: pandas DataFrame com dados da camada
        context: dicionário com task_instance, dag_run, etc
    
    Returns:
        dict com 'status': 'ok' ou lançar exceção em falha
    """
    import pandas as pd
    
    # Exemplo: Validar que coluna 'email' não tenha nulos
    if 'email' in df.columns:
        null_count = df['email'].isnull().sum()
        if null_count > 0:
            raise ValueError(f"Coluna 'email' tem {null_count} valores nulos")
    
    return {'status': 'ok', 'validated_rows': len(df)}
```

### 4. Testar e Salvar

#### 🧪 Botão "Testar"
Ao clicar, o sistema:
1. **Verifica a sintaxe Python** do código (se está escrito corretamente)
2. **Valida se existe função `validate()`** - requisito obrigatório
3. **NÃO executa** a validação de verdade (apenas testa o código)
4. Exibe resultado no painel "📊 Resultado do Teste"

#### 💾 Botão "Salvar"
Ao clicar, o sistema:
1. **Salva o arquivo Python no seu repositório Git** (não no MinIO atualmente)
2. Você pode visualizar todos os arquivos salvos na **sidebar GitHub**
3. O arquivo fica em: **Raiz do repositório Git conectado**
4. Exemplo: `validador.py`, `check_nulls.py`, etc.

#### 📌 Como o validador é conectado à DAG?

**Atualmente, a integração com `dag_configurations` ainda não está implementada automaticamente.**

Para usar seu validador:
1. Salve o arquivo Python via GitHub
2. **Manualmente** edite a `dag_configurations` MySQL:
   ```sql
   UPDATE dag_configurations 
   SET python_module_path = 'seu_validador_module'
   WHERE dag_id = 'sua_dag_id'
   ```
3. Ou use o campo `transform_args` (JSON) para passar parâmetros extras à DAG

---

## 🏗️ Arquitetura Atual

### Fluxo de Dados

```
┌──────────────────────────────────────────┐
│  Interface Web (validation-rules-editor) │
│  - Monaco Editor (Python)                 │
│  - Sidebar GitHub (isomorphic-git)       │
│  - Teste de sintaxe                      │
└──────────────┬─────────────────────────┘
               │ 🧪 Teste: python3 -m py_compile
               ▼
         [Validação de Sintaxe]
               │ ✓ OK
               ▼
        [Arquivo pronto para salvar]
               │ 💾 Save
               ▼
┌──────────────────────────────────────────┐
│  GitHub Repository (isomorphic-git)     │
│  └─ validador.py                         │
│  └─ check_nulls.py                       │
│  └─ data_quality.py                      │
│  └─ outros_validadores.py                │
└──────────────┬─────────────────────────┘
               │ Repositório Git versionado
               │ (Você faz Commit & Push)
               ▼
┌──────────────────────────────────────────┐
│  MySQL - dag_configurations              │
│  ├─ python_module_path (MANUAL)         │
│  ├─ transform_args (JSON)               │
│  └─ [Próximo: associação automática]     │
└──────────────┬─────────────────────────┘
               │ Lido pela DAG em runtime
               ▼
┌──────────────────────────────────────────┐
│  Airflow DAG (Python Factory)            │
│  - Carrega validador do repo Git        │
│  - Executa validate() em dados reais    │
│  - Se falhar: sinaliza erro na DAG      │
└──────────────────────────────────────────┘
```

### Estrutura Atual de Arquivos

```
src/
├── codeigniter-app/
│   ├── app/
│   │   ├── Controllers/
│   │   │   └── ValidationRulesController.php  # API REST (MinIO - não utilizado ainda)
│   │   └── Views/
│   │       └── code_editor/
│   │           └── validation-rules-editor.php # Interface Web (Git ✓)
│   └── public/
│       └── assets/
│           └── js/
│               └── git-file-manager.js        # Gerenciador isomorphic-git
│
└── seu-repo-github/
    ├── validador.py                  # Seus validadores salvos aqui
    ├── check_nulls.py
    ├── data_quality.py
    └── business_rules.py

MySQL (lista_revisao2):
└── dag_configurations
    ├── id
    ├── dag_id
    ├── python_module_path      ← Configure manualmente para carregar o validador
    ├── transform_args (JSON)
    └── ...outros campos
```

### O que está implementado vs o que falta

| Funcionalidade | Status | Detalhes |
|---|---|---|
| **Interface Web** | ✅ Implementado | Monaco editor + GitHub sidebar |
| **Teste de Sintaxe** | ✅ Implementado | `python3 -m py_compile` |
| **Salvamento em Git** | ✅ Implementado | Via isomorphic-git (cliente) |
| **Versionar Código** | ✅ Implementado | Commit & Push para GitHub |
| **Herança de `raw_to_medallion`** | ⏳ Recomendado | Estruturar validadores como classes herdadas |
| **Validação em cada camada** | ⏳ Recomendado | Métodos: `validate_bronze()`, `validate_silver()`, `validate_gold()` |
| **Carregamento automático** | ⏳ Em desenvolvimento | Factory detecta `python_module_path` em `dag_configurations` |
| **Executar validação na DAG** | ⏳ Em desenvolvimento | Task chama método `validate_*()` em cada estágio |
| **Salvamento em MinIO** | ❌ Não será usado | Git é melhor para versionamento |

---

## 🔧 Como Usar AGORA (Solução Provisória)

Como a integração automática com `dag_configurations` ainda não está pronta, aqui está o fluxo provisório:

### Passo 1: Estude o exemplo prático

Abra [VALIDADOR_EXEMPLO.py](VALIDADOR_EXEMPLO.py) - contém 3 classes de exemplo completas:
- `ValidadorVendas` - Validações de negócio (datas, valores, etc)
- `ValidadorLGPD` - Mascaramento de dados sensíveis
- `ValidadorComQualityScore` - Cálculo de quality score por coluna

### Passo 2: Crie seu próprio validador

Herde de `raw_to_medallion` e implemente os métodos que precisa:

```python
from lib.medallion_pipeline import raw_to_medallion
import logging

log = logging.getLogger(__name__)

class MeuValidador(raw_to_medallion):
    def validate_bronze(self, df_bronze, **context):
        df_bronze = super().validate_bronze(df_bronze, **context)
        # sua lógica aqui
        return df_bronze
    
    def validate_silver(self, df_silver, **context):
        df_silver = super().validate_silver(df_silver, **context)
        # sua lógica aqui
        return df_silver
    
    def validate_gold(self, df_gold, **context):
        df_gold = super().validate_gold(df_gold, **context)
        # sua lógica aqui
        return df_gold
```

### Passo 3: Teste via GitHub

1. Abra `/validation-rules-editor`
2. Conecte seu GitHub (sidebar)
3. Crie arquivo `meu_validador.py`
4. Cole seu código
5. Clique **🧪 Testar** → valida sintaxe
6. Clique **💾 Salvar** → vai para GitHub
7. Faça **Commit & Push**

### Passo 4: Registre em `dag_configurations`

```sql
UPDATE dag_configurations 
SET python_module_path = 'meu_validador.MeuValidador'
WHERE dag_id = 'sua_dag_id';
```

O factory detectará e carregará automaticamente!

### Arquitetura com Herança

A melhor abordagem seria que validadores **herdem** de `lib.medallion_pipeline` e estendam o comportamento:

```python
# seu_validador_customizado.py
from lib.medallion_pipeline import raw_to_medallion
import logging

log = logging.getLogger(__name__)

class CustomRawToMedallion(raw_to_medallion):
    """
    Estende o pipeline Medallion padrão com validações customizadas.
    
    Herda todo comportamento de:
    - Conversão Bronze (JSON/CSV → Parquet)
    - Processamento Silver (validação de qualidade padrão)
    - Otimização Gold (agregações e índices)
    - Registro em Atlas (linhagem)
    
    Adiciona:
    - Validações customizadas em cada camada
    - Mascaramento de dados sensíveis
    - Regras de negócio específicas do usuário
    """
    
    def validate_bronze(self, df_bronze, **context):
        """Validar dados Bronze antes de passar para Silver."""
        log.info("[CUSTOM] Validando camada Bronze...")
        
        # Herança: execute a validação padrão
        super().validate_bronze(df_bronze)
        
        # Adicione sua lógica customizada aqui
        if 'email' in df_bronze.columns:
            invalid_emails = df_bronze[~df_bronze['email'].str.contains('@', na=False)]
            if len(invalid_emails) > 0:
                log.warning(f"[CUSTOM] {len(invalid_emails)} emails inválidos encontrados")
        
        return df_bronze
    
    def validate_silver(self, df_silver, **context):
        """Validar dados Silver antes de passar para Gold."""
        log.info("[CUSTOM] Validando camada Silver...")
        
        # Herança: execute a validação padrão (qualidade de dados)
        df_silver = super().validate_silver(df_silver)
        
        # Adicione sua lógica customizada aqui
        required_cols = ['id', 'name', 'created_at']
        missing_cols = [c for c in required_cols if c not in df_silver.columns]
        if missing_cols:
            raise ValueError(f"[CUSTOM] Colunas obrigatórias faltando: {missing_cols}")
        
        return df_silver
    
    def validate_gold(self, df_gold, **context):
        """Validar dados Gold (análise final antes de expor)."""
        log.info("[CUSTOM] Validando camada Gold...")
        
        # Herança: execute a validação padrão
        df_gold = super().validate_gold(df_gold)
        
        # Adicione sua lógica customizada aqui
        quality_threshold = 95.0
        quality_score = (df_gold.notna().sum().sum() / df_gold.size) * 100
        
        if quality_score < quality_threshold:
            raise Exception(f"[CUSTOM] Quality score {quality_score:.1f}% < {quality_threshold}%")
        
        log.info(f"[CUSTOM] ✅ Quality score: {quality_score:.1f}%")
        return df_gold
```

**Vantagens dessa abordagem:**
- ✅ Reutiliza toda lógica de produção
- ✅ Validações ocorrem **em cada camada** (não só no final)
- ✅ Suporta `super()` para chamar comportamento padrão
- ✅ Fácil de testar (cada método pode ser mockado)
- ✅ Integração natural com Atlas/lineage
- ✅ Sem duplicação de código

### Passo 5: Registrar customizador em `dag_configurations`

```sql
UPDATE dag_configurations 
SET python_module_path = 'seu_validador_customizado.CustomRawToMedallion'
WHERE dag_id = 'sua_dag_id';
```

### Passo 6: Factory carrega automaticamente

```python
# factory_master.py (pseudocódigo)
from importlib import import_module

def build_dag(config):
    # Se tem module customizado, usar; senão usar padrão
    module_path = config['python_module_path']
    
    if module_path:
        parts = module_path.rsplit('.', 1)
        module = import_module(parts[0])
        pipeline_class = getattr(module, parts[1])
    else:
        from lib.medallion_pipeline import raw_to_medallion as pipeline_class
    
    # Usar o pipeline (herdado ou customizado)
    t_medallion = PythonOperator(
        task_id='medallion_pipeline',
        python_callable=pipeline_class(),  # Executa __call__()
        op_kwargs={
            'source_filename': config['source_filename'],
            'target_table_name': config['target_table_name'],
        }
    )
    
    return dag
```

---

## 📝 API de Validação - Padrão com Herança

### Estrutura Base

Todo validador customizado deve herdar de `raw_to_medallion`:

```python
from lib.medallion_pipeline import raw_to_medallion
import pandas as pd
import logging

log = logging.getLogger(__name__)

class MeuValidador(raw_to_medallion):
    """Seu validador customizado extendendo o pipeline padrão."""
    
    def validate_bronze(self, df_bronze, **context):
        """Validar dados na camada Bronze (após leitura do arquivo)."""
        # Chamar validação padrão primeiro
        df_bronze = super().validate_bronze(df_bronze, **context)
        
        # Sua lógica aqui
        log.info(f"[{self.__class__.__name__}] Bronze: {len(df_bronze)} linhas")
        return df_bronze
    
    def validate_silver(self, df_silver, **context):
        """Validar dados na camada Silver (após limpeza)."""
        # Chamar validação padrão primeiro
        df_silver = super().validate_silver(df_silver, **context)
        
        # Sua lógica aqui
        log.info(f"[{self.__class__.__name__}] Silver: {len(df_silver)} linhas")
        return df_silver
    
    def validate_gold(self, df_gold, **context):
        """Validar dados na camada Gold (pronto para análise)."""
        # Chamar validação padrão primeiro
        df_gold = super().validate_gold(df_gold, **context)
        
        # Sua lógica aqui
        log.info(f"[{self.__class__.__name__}] Gold: {len(df_gold)} linhas")
        return df_gold
```

### Métodos Disponíveis

Você pode sobrescrever qualquer desses métodos da classe base:

| Método | Quando é chamado | Recebe | Deve retornar |
|--------|---|---|---|
| `validate_bronze(df, **ctx)` | Após conversão para Parquet | DataFrame da Bronze | DataFrame validado |
| `validate_silver(df, **ctx)` | Após limpeza e normalização | DataFrame da Silver | DataFrame validado |
| `validate_gold(df, **ctx)` | Após agregação e otimização | DataFrame da Gold | DataFrame validado |
| `mask_sensitive_data(df, **ctx)` | (futuro) | DataFrame | DataFrame mascarado |

### Acessando Contexto do Airflow

```python
def validate_silver(self, df_silver, **context):
    # Informações da execução
    ti = context.get('task_instance')
    dag_run = context.get('dag_run')
    execution_date = context.get('execution_date')
    ds = context.get('ds')  # data de execução em YYYY-MM-DD
    
    # Usar para logging customizado
    dag_id = context.get('dag', {}).dag_id if 'dag' in context else 'unknown'
    ti.xcom_push(key='silver_row_count', value=len(df_silver))
    
    log.info(f"[DAG: {dag_id}] Silver validada em {ds}")
    
    return df_silver
```

### Exemplos Reais

#### 1. Mascarar CPF antes de Silver

```python
class ValidadorCPF(raw_to_medallion):
    """Valida e mascara CPFs antes de publicar."""
    
    def validate_silver(self, df_silver, **context):
        df_silver = super().validate_silver(df_silver, **context)
        
        # Mascarar CPF se existir
        if 'cpf' in df_silver.columns:
            df_silver['cpf_masked'] = df_silver['cpf'].apply(
                lambda x: f"{x[:3]}***{x[-2:]}" if pd.notna(x) else None
            )
            # Remover CPF original
            df_silver = df_silver.drop('cpf', axis=1)
            log.info(f"✅ {len(df_silver)} CPFs mascarados")
        
        return df_silver
```

#### 2. Validar LGPD - Dados Pessoais

```python
class ValidadorLGPD(raw_to_medallion):
    """Garante conformidade LGPD."""
    
    def validate_gold(self, df_gold, **context):
        df_gold = super().validate_gold(df_gold, **context)
        
        sensitive_cols = ['cpf', 'rg', 'email', 'telefone']
        found_sensitive = [c for c in sensitive_cols if c in df_gold.columns]
        
        if found_sensitive:
            log.error(f"❌ LGPD VIOLATION: Colunas sensíveis na Gold: {found_sensitive}")
            raise Exception(f"Dados pessoais não podem estar em Gold: {found_sensitive}")
        
        log.info("✅ LGPD Compliance validado")
        return df_gold
```

#### 3. Quality Score Customizado

```python
class ValidadorQuality(raw_to_medallion):
    """Valida quality score por regra de negócio."""
    
    def validate_silver(self, df_silver, **context):
        df_silver = super().validate_silver(df_silver, **context)
        
        # Calcular qualidade por coluna
        quality_by_col = {}
        for col in df_silver.columns:
            filled = df_silver[col].notna().sum()
            quality_by_col[col] = (filled / len(df_silver)) * 100
        
        # Assertar qualidade mínima
        min_quality = 90.0
        bad_cols = {c: v for c, v in quality_by_col.items() if v < min_quality}
        
        if bad_cols:
            log.warning(f"⚠️ Colunas com qualidade baixa: {bad_cols}")
            # Você pode lançar erro ou apenas avisar
            # raise Exception("Quality abaixo do limite")
        
        # Salvar em XCom para próximas tasks
        ti = context['task_instance']
        ti.xcom_push(key='quality_report', value=quality_by_col)
        
        log.info(f"✅ Quality report: {quality_by_col}")
        return df_silver
```

#### 4. Regras de Negócio Específicas

```python
class ValidadorVendas(raw_to_medallion):
    """Validações específicas para dados de vendas."""
    
    def validate_gold(self, df_gold, **context):
        df_gold = super().validate_gold(df_gold, **context)
        
        # Regra 1: Preço nunca negativo
        if 'preco' in df_gold.columns:
            invalid_prices = df_gold[df_gold['preco'] < 0]
            if len(invalid_prices) > 0:
                raise ValueError(f"{len(invalid_prices)} preços negativos encontrados")
        
        # Regra 2: Data de venda não pode ser futura
        if 'data_venda' in df_gold.columns:
            from datetime import datetime
            today = datetime.now().date()
            future_dates = df_gold[df_gold['data_venda'].dt.date > today]
            if len(future_dates) > 0:
                log.warning(f"⚠️ {len(future_dates)} vendas com datas futuras")
                # Remover ou marcar
                df_gold = df_gold[df_gold['data_venda'].dt.date <= today]
        
        # Regra 3: Quantidade vendida sempre positiva
        if 'quantidade' in df_gold.columns:
            invalid_qty = df_gold[(df_gold['quantidade'] <= 0) | (df_gold['quantidade'] > 10000)]
            if len(invalid_qty) > 0:
                log.warning(f"⚠️ {len(invalid_qty)} quantidades fora do intervalo válido")
        
        log.info("✅ Validações de negócio OK")
        return df_gold
```

#### 5. Executar Comando Externo (ex: API de Validação)

```python
class ValidadorComAPI(raw_to_medallion):
    """Valida contra API externa."""
    
    def validate_gold(self, df_gold, **context):
        df_gold = super().validate_gold(df_gold, **context)
        
        import requests
        
        # Exemplo: validar contra API de risco
        api_url = "https://api.exemplo.com/validate"
        
        for idx, row in df_gold.iterrows():
            try:
                response = requests.post(api_url, json=row.to_dict())
                if response.status_code != 200:
                    log.warning(f"Row {idx} failed validation: {response.text}")
            except Exception as e:
                log.error(f"API error for row {idx}: {e}")
        
        log.info("✅ Validação com API concluída")
        return df_gold
```

### Tratamento de Erros

```python
class ValidadorRobusto(raw_to_medallion):
    """Exemplo de tratamento robusto de erros."""
    
    def validate_silver(self, df_silver, **context):
        df_silver = super().validate_silver(df_silver, **context)
        
        try:
            # Sua lógica de validação
            if df_silver.empty:
                log.warning("⚠️ DataFrame vazio em Silver, mas prosseguindo")
                return df_silver
            
            # Operação que pode falhar
            result = df_silver.groupby('categoria').size()
            log.info(f"✅ Agrupamento por categoria: {result.to_dict()}")
            
        except Exception as e:
            # Log detalhado
            log.error(f"❌ Erro na validação Silver: {str(e)}", exc_info=True)
            
            # Decidir: falhar ou continuar
            if context.get('hard_fail', False):
                raise  # Falha a DAG
            else:
                log.warning("Prosseguindo mesmo com erro (hard_fail=False)")
        
        return df_silver
```        context['task_instance'].xcom_push(
            key='duplicates_found',
            value=duplicates.to_dict('records')
        )
        raise ValueError(f"{len(duplicates)} duplicatas na chave {primary_key}")
    
    return {'status': 'ok', 'unique_records': len(df)}
```

#### 3. Quality Score com Threshold

```python
def validate(df, **context):
    total_cells = df.shape[0] * df.shape[1]
    null_cells = df.isnull().sum().sum()
    quality_score = ((total_cells - null_cells) / total_cells) * 100
    
    threshold = context.get('quality_threshold', 95.0)
    
    if quality_score < threshold:
        raise ValueError(
            f"Quality score {quality_score:.2f}% abaixo do threshold {threshold}%"
        )
    
    return {
        'status': 'ok',
        'quality_score': quality_score,
        'null_cells': null_cells,
        'total_cells': total_cells
    }
```

#### 4. Regras de Negócio

```python
def validate(df, **context):
    errors = []
    
    # Regra 1: Salário entre 1.320 e 50.000
    if 'salario' in df.columns:
        invalid = df[(df['salario'] < 1320) | (df['salario'] > 50000)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} salários fora do range")
    
    # Regra 2: CPF válido (11 dígitos)
    if 'cpf' in df.columns:
        invalid = df[~df['cpf'].str.match(r'^\d{11}$', na=False)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} CPFs inválidos")
    
    # Regra 3: Email com @
    if 'email' in df.columns:
        invalid = df[~df['email'].str.contains('@', na=False)]
        if len(invalid) > 0:
            errors.append(f"{len(invalid)} emails sem @")
    
    if errors:
        raise ValueError("; ".join(errors))
    
    return {'status': 'ok', 'business_rules_validated': 3}
```

---

## 🔧 Integração com DAGs

### Opção 1: Usar DAGBuilder Pronto

No banco de dados, configure `builder_type`:

```sql
UPDATE dags_config 
SET builder_type = 'custom_validation'
WHERE config_name = 'minha_dag';
```

Pronto! A DAG agora carrega validações automaticamente do MinIO.

### Opção 2: Criar DAGBuilder Customizado

```python
from dag_builder_base import DAGBuilder
from lib.custom_validators import create_validation_task_func

class MeuDAGBuilder(DAGBuilder):
    def customize_validation_task(self):
        """Injeta validações customizadas do usuário."""
        return create_validation_task_func(
            bucket=self.config.get('bucket_name', 'lab01'),
            layer='silver',
            table=self.config.get('table_name')
        )
```

### Opção 3: Validações em Múltiplas Camadas

```python
from builders.custom_validation_builder import MultiLayerValidationDAGBuilder

# No factory_master.py ou registro:
if builder_type == 'multi_layer_validation':
    builder = MultiLayerValidationDAGBuilder(dag_config)
    dag = builder.create_dag()
```

Isso cria validações separadas para Bronze, Silver e Gold:

```
Bronze → [Validação Bronze] → Silver → [Validação Silver] → Gold → [Validação Gold]
```

---

## 🔒 Segurança e Isolamento

### Isolamento por Bucket

Cada usuário tem suas validações no próprio bucket:

```
s3://user1/validation-rules/...  ← Isolado
s3://user2/validation-rules/...  ← Isolado
```

### Sandboxing de Código

Validações rodam em contexto controlado:
- ✅ Acesso a pandas, numpy, datetime
- ✅ Acesso a XCom do Airflow
- ❌ Sem acesso a system calls perigosos
- ❌ Sem acesso a arquivos fora do bucket

### Teste de Sintaxe

Antes de salvar, código é validado:

```bash
python3 -m py_compile validation.py
```

Garante que código não terá erro de sintaxe em runtime.

---

## 📊 Monitoramento

### Logs no Airflow

Validações customizadas geram logs detalhados:

```
[VALIDATORS] Carregando validações de s3://lab01/validation-rules/silver/
[VALIDATORS] ✓ Carregado: check_nulls
[VALIDATORS] ✓ Carregado: check_duplicates
[VALIDATORS] Total carregados: 2
[VALIDATOR] Executando: check_nulls
[VALIDATOR] ✓ check_nulls: ok
[VALIDATOR] Executando: check_duplicates
[VALIDATOR] ✓ check_duplicates: ok
[VALIDATORS] Resumo: 2/2 passaram
```

### XCom Results

Resultados ficam disponíveis em XCom:

```python
ti.xcom_pull(key='validation_results_silver')
# {
#   'status': 'ok',
#   'validators_run': 2,
#   'passed': 2,
#   'failed': 0,
#   'results': {
#     'check_nulls': {'status': 'ok', 'validated_rows': 1000},
#     'check_duplicates': {'status': 'ok', 'unique_records': 1000}
#   }
# }
```

---

## 🎯 Casos de Uso

### 1. Governança de Dados (LGPD)

```python
def validate(df, **context):
    """Valida que dados sensíveis foram mascarados."""
    sensitive_cols = ['cpf', 'rg', 'email']
    
    for col in sensitive_cols:
        if col in df.columns:
            # Verificar se valores parecem mascarados (****)
            unmasked = df[~df[col].str.contains(r'\*', na=False)]
            if len(unmasked) > 0:
                raise ValueError(f"{col} não mascarado: {len(unmasked)} registros")
    
    return {'status': 'ok', 'lgpd_compliant': True}
```

### 2. SLA de Qualidade

```python
def validate(df, **context):
    """Garante SLA de 99% de completude."""
    completeness = (1 - df.isnull().sum().sum() / (df.shape[0] * df.shape[1])) * 100
    
    if completeness < 99.0:
        # Alerta mas não quebra pipeline
        context['task_instance'].xcom_push(key='sla_warning', value=True)
        log.warning(f"⚠️ SLA violado: {completeness:.2f}%")
    
    return {'status': 'ok', 'completeness': completeness}
```

### 3. Detecção de Anomalias

```python
def validate(df, **context):
    """Detecta valores fora do padrão histórico."""
    if 'valor_venda' in df.columns:
        mean = df['valor_venda'].mean()
        std = df['valor_venda'].std()
        
        # Valores > 3 desvios-padrão são anomalias
        anomalies = df[abs(df['valor_venda'] - mean) > 3 * std]
        
        if len(anomalies) > 0:
            context['task_instance'].xcom_push(
                key='anomalies_detected',
                value=anomalies.to_dict('records')
            )
            log.warning(f"⚠️ {len(anomalies)} anomalias detectadas")
    
    return {'status': 'ok', 'anomalies_count': len(anomalies)}
```

---

## 🚫 Limitações e Boas Práticas

### ❌ Não Fazer

- **Não usar time.sleep()** - bloqueia o Airflow
- **Não fazer requests HTTP externos** sem timeout
- **Não processar volumes gigantes** - validação deve ser rápida
- **Não alterar dados** - use Silver transformation para isso

### ✅ Fazer

- **Validar amostras** se dataset for grande
- **Usar logging** para debugging
- **Retornar dicts informativos** com métricas
- **Documentar** regras de negócio no código
- **Testar** antes de salvar

### Performance

Para datasets grandes (> 1M linhas):

```python
def validate(df, **context):
    # Validar apenas amostra de 10k linhas
    sample_size = min(10000, len(df))
    df_sample = df.sample(n=sample_size, random_state=42)
    
    # Sua validação aqui
    ...
    
    return {'status': 'ok', 'sample_size': sample_size}
```

---

## 🆘 Troubleshooting & Status Atual

### ❓ "Como começar a usar a interface agora?"

**Passo 1:** Conecte seu GitHub na sidebar (token + repo)  
**Passo 2:** Crie uma classe herdando de `raw_to_medallion`  
**Passo 3:** Edite `dag_configurations` com o caminho do módulo  
**Passo 4:** Execute DAG normalmente (ela carregará seu validador)  

### ❓ "Erro ao testar: 'def validate' não encontrada"

**Anterior (descontinuado):** Esperava uma função `validate()`  
**Agora:** Espera uma **classe herdando de `raw_to_medallion`**

**Solução:** Estruture assim:
```python
from lib.medallion_pipeline import raw_to_medallion

class MeuValidador(raw_to_medallion):
    def validate_silver(self, df, **context):
        super().validate_silver(df, **context)  # ← importante!
        # sua lógica aqui
        return df
```

### ❓ "Super() chamado mas não funciona"

**Problema:** Classe base `raw_to_medallion` pode não ter métodos `validate_*`

**Solução:** Verifique se `raw_to_medallion` implementa:
```python
def validate_bronze(self, df, **context): ...
def validate_silver(self, df, **context): ...  
def validate_gold(self, df, **context): ...
```

Se não implementa, ignore o `super()` e implemente suas próprias regras.

### ❓ "Não consigo associar a validação à DAG na interface"

**Por que?** A UI ainda **não tem campo para associar validadores** a DAGs. Isso precisa ser feito manualmente.

**Solução provisória:**
1. Edite MySQL diretamente:
   ```sql
   UPDATE dag_configurations 
   SET python_module_path = 'seu_validador.MeuValidador'
   WHERE dag_id = 'sua_dag';
   ```
2. Ou implemente no seu builder customizado com campo dropdown

### ❓ "Salvei mas não consigo carregar na DAG"

**Porque?** Não há loader automático ainda. Você precisa:
1. Fazer commit/push do arquivo para o GitHub
2. Configurar `python_module_path` em `dag_configurations`
3. Factory detecta e carrega automaticamente

### ✅ O que ESTÁ funcionando

- ✓ Interface web para editar Python
- ✓ Teste de sintaxe em tempo real
- ✓ Versionamento no GitHub
- ✓ Sidebar com gerenciador de arquivos
- ✓ Commit & Push automático
- ✓ `raw_to_medallion` como base reutilizável

### ⏳ O que PRECISA ser implementado

- ⏳ UI com dropdown para selecionar validador (em `dag_configurations`)
- ⏳ Auto-descoberta de classes que herdam de `raw_to_medallion`
- ⏳ Métodos `validate_*()` chamados automaticamente em cada camada
- ⏳ Histórico de testes e validações
- ⏳ Relatório de qualidade integrado ao Airflow

---

## 📚 Referências Técnicas

### Classes Base

- **`lib.medallion_pipeline.raw_to_medallion`** - Pipeline completo (Bronze → Silver → Gold)
- **`lib.silver_layer.bronze_to_silver`** - Transformação Silver específica
- **`lib.gold_layer.silver_to_gold`** - Otimização Gold específica

### Documentação Relacionada

- **DAGBuilder Base**: `src/dags/dag_builder_base.py`
- **Factory Master**: `src/dags/factory_master.py`
- **Builders de Exemplo**: `src/dags/builders/`
- **Cheat Sheet**: `BUILDERS_CHEAT_SHEET.py`
- **Integração Atlas**: `ATLAS_LINEAGE_FIX.md`

### Arquivos Importantes

- **Editor Web**: [src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php](src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php)
- **Configuração BD**: [mysql-init/01-ddl.sql](mysql-init/01-ddl.sql) (tabela `dag_configurations`)
- **Pipeline**: [src/dags/lib/medallion_pipeline.py](src/dags/lib/medallion_pipeline.py)

---

## 🎯 Roteiro Proposto

**Fase 1 (Atual)** ✅  
- Interface web para editar código Python
- Teste de sintaxe
- Versionamento em Git

**Fase 2 (Próximo)**
- UI para associar validador a DAG
- Auto-load de classe herdada
- Chamadas automáticas de `validate_*()` em cada camada

**Fase 3 (Futuro)**
- Dashboard de quality metrics
- Histórico de validações
- Alertas e notificações

---

**Criado em:** 2026-01-14  
**Versão:** 2.0 (com arquitetura em herança)  
**Mantido por:** Sistema de Builders Extensível  
**Próxima revisão:** Quando Fase 2 for concluída
