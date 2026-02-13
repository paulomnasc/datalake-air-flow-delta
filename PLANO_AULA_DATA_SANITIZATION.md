# 📚 Plano de Aula: Data Sanitization - Limpeza Técnica de Dados

**Objetivo**: Aprender a implementar limpeza técnica (Data Sanitization) em pipelines de dados usando a classe `MeuValidador`.

**Duração Estimada**: 4 horas

**Público-alvo**: Desenvolvedores e Analistas de Dados

---

## 📋 Índice

1. [Introdução à Limpeza de Dados](#1-introdução-à-limpeza-de-dados)
2. [Análise do Dataset Invoice.json](#2-análise-do-dataset-invoicejson)
3. [Funcionalidades Faltantes](#3-funcionalidades-faltantes)
4. [Implementação Prática](#4-implementação-prática)
5. [Exercícios Hands-On](#5-exercícios-hands-on)
6. [Checklist de Validação](#6-checklist-de-validação)

---

## 1. Introdução à Limpeza de Dados

### 1.1 O que é Data Sanitization?

**Data Sanitization** é o processo de detectar e corrigir (ou remover) registros corrompidos, imprecisos ou irrelevantes de um dataset.

### 1.2 Por que é Importante?

| Problema | Impacto no Negócio | Exemplo Real |
|----------|-------------------|--------------|
| **Valores Nulos** | Cálculos incorretos, relatórios incompletos | Soma de vendas ignorando registros nulos = perda de receita |
| **Duplicatas** | Inflação de métricas, cobranças duplicadas | Cliente cobrado 2x pela mesma fatura |
| **Tipagem Errada** | Impossibilidade de análises temporais/numéricas | Datas em string impedem análise de tendências |

### 1.3 Os 3 Pilares da Limpeza Técnica

```
┌─────────────────────────────────────────────────┐
│  1. VALORES NULOS                               │
│     └─ Decisão: Apagar ou Preencher?            │
│                                                  │
│  2. DUPLICATAS                                  │
│     └─ Identificar chaves primárias repetidas   │
│                                                  │
│  3. TIPAGEM                                     │
│     └─ Converter para tipos apropriados         │
└─────────────────────────────────────────────────┘
```

---

## 2. Análise do Dataset Invoice.json

### 2.1 Estrutura dos Dados

O arquivo `Invoice.json` contém **412 faturas** com 9 campos:

```json
{
  "InvoiceId": 1,
  "CustomerId": 2,
  "InvoiceDate": "2009-01-01 00:00:00",
  "BillingAddress": "Theodor-Heuss-Straße 34",
  "BillingCity": "Stuttgart",
  "BillingState": null,
  "BillingCountry": "Germany",
  "BillingPostalCode": "70174",
  "Total": 1.98
}
```

### 2.2 Problemas Encontrados no Dataset

#### 🔴 Problema 1: Valores Nulos (null)

**Registros Afetados**: ~150 registros (36% do dataset)

```json
// Exemplo 1: Invoice #10 - Postal Code nulo
{
  "InvoiceId": 10,
  "BillingPostalCode": null  // ❌ Como calcular frete?
}

// Exemplo 2: Invoice #22 - Postal Code nulo
{
  "InvoiceId": 22,
  "BillingCity": "Santiago",
  "BillingState": null,      // ⚠️ Normal para Chile
  "BillingPostalCode": null  // ❌ CRÍTICO para entrega
}

// Exemplo 3: Invoice #28 - Postal Code nulo
{
  "InvoiceId": 28,
  "BillingCity": "Lisbon",
  "BillingCountry": "Portugal",
  "BillingPostalCode": null  // ❌ Impede análise geográfica
}
```

**Impacto no Negócio**:
- ❌ Impossível calcular custos de frete
- ❌ Análises de vendas por região ficam incompletas
- ❌ Integração com sistemas de logística falha

---

#### 🔴 Problema 2: Potenciais Duplicatas

**Risco**: Mesmos clientes fazendo compras no mesmo dia podem gerar registros duplicados.

```json
// Cliente #2 fez 2 compras em datas diferentes (OK)
{
  "InvoiceId": 1,
  "CustomerId": 2,
  "InvoiceDate": "2009-01-01 00:00:00",
  "Total": 1.98
}
{
  "InvoiceId": 12,
  "CustomerId": 2,
  "InvoiceDate": "2009-02-11 00:00:00",
  "Total": 13.86
}

// Cliente #4 também tem 2 faturas (OK)
{
  "InvoiceId": 2,
  "CustomerId": 4,
  "InvoiceDate": "2009-01-02 00:00:00",
  "Total": 3.96
}
{
  "InvoiceId": 24,
  "CustomerId": 4,
  "InvoiceDate": "2009-04-06 00:00:00",
  "Total": 5.94
}
```

**Como Detectar Duplicatas Reais?**
```python
# Chave primária: InvoiceId (deve ser único)
# Campos de negócio: CustomerId + InvoiceDate + Total
```

**Cenário de Duplicata**:
```json
// ❌ DUPLICATA SUSPEITA
{
  "InvoiceId": 999,
  "CustomerId": 2,
  "InvoiceDate": "2009-01-01 00:00:00",  // Mesma data
  "Total": 1.98                           // Mesmo valor
}
```

---

#### 🔴 Problema 3: Tipagem Incorreta

**Campos Afetados**: `InvoiceDate` e `Total`

```python
# Como os dados são lidos do JSON
df = pd.read_json('Invoice.json')
print(df.dtypes)

# Resultado ANTES da conversão:
"""
InvoiceId              int64
CustomerId             int64
InvoiceDate           object  ❌ Deveria ser datetime64
BillingAddress        object
BillingCity           object
BillingState          object
BillingCountry        object
BillingPostalCode     object
Total                float64  ✅ Já é numérico
"""
```

**Consequências**:

```python
# ❌ NÃO FUNCIONA - InvoiceDate é string
df['InvoiceDate'].dt.year  # AttributeError

# ❌ NÃO FUNCIONA - Impossível filtrar por período
df[df['InvoiceDate'] > '2009-06-01']  # Comparação de strings!

# ❌ NÃO FUNCIONA - Tendência temporal
df.groupby(df['InvoiceDate'].dt.month)['Total'].sum()
```

---

## 3. Funcionalidades Faltantes

### 3.1 Comparação: O que temos vs. O que precisamos

| Funcionalidade | Status Atual | O que Falta | Prioridade |
|----------------|--------------|-------------|------------|
| **Valores Nulos - CEP** | ✅ Implementado | Generalizar para outros campos | 🟡 Média |
| **Valores Nulos - Remover linhas** | ❌ Não implementado | Adicionar `dropna()` | 🔴 Alta |
| **Valores Nulos - Preencher** | ❌ Não implementado | Adicionar `fillna()` | 🔴 Alta |
| **Duplicatas** | ❌ Não implementado | Adicionar `drop_duplicates()` | 🔴 Alta |
| **Tipagem - Datas** | ❌ Não implementado | Converter para `datetime64` | 🔴 Alta |
| **Tipagem - Números** | ✅ Total já é float | Validar outros campos | 🟢 Baixa |

---

## 4. Implementação Prática

### 4.1 Estratégia de Valores Nulos

#### Opção 1: Remover Linhas com Nulos (dropna)

**Quando usar?**
- ✅ Campos críticos para análise (InvoiceId, CustomerId, Total)
- ✅ Dataset grande (perder algumas linhas não afeta)
- ❌ Campos opcionais (BillingState é nulo em muitos países)

**Implementação**:

```python
def _handle_null_values_strict(self, df):
    """
    Estratégia ESTRITA: Remove registros com nulos em campos críticos
    
    JUSTIFICATIVA:
    - InvoiceId: Chave primária, NUNCA pode ser nulo
    - CustomerId: Essencial para relacionamento com tabela Customer
    - Total: Valor financeiro, sem ele a fatura não faz sentido
    - InvoiceDate: Necessário para análises temporais
    """
    log.info("🔍 [Validador] Aplicando estratégia ESTRITA de nulos...")
    
    original_rows = len(df)
    
    # Campos críticos que NÃO podem ser nulos
    critical_fields = ['InvoiceId', 'CustomerId', 'Total', 'InvoiceDate']
    
    # Remove qualquer linha com nulo nesses campos
    df = df.dropna(subset=critical_fields)
    
    removed_rows = original_rows - len(df)
    
    if removed_rows > 0:
        log.warning(f"⚠️ Removidos {removed_rows} registros com nulos em campos críticos")
        log.warning(f"   Taxa de perda: {(removed_rows/original_rows)*100:.2f}%")
    else:
        log.info(f"✅ Nenhum registro removido - dataset íntegro")
    
    return df
```

**Exemplo Prático com Invoice.json**:

```python
# Antes: 412 registros
original_df = pd.read_json('Invoice.json')

# Simular registro corrompido
original_df.loc[0, 'Total'] = None  # Fatura sem valor!

# Aplicar limpeza
cleaned_df = _handle_null_values_strict(original_df)

# Depois: 411 registros (removeu a fatura sem valor)
# LOG: ⚠️ Removidos 1 registros com nulos em campos críticos
#      Taxa de perda: 0.24%
```

---

#### Opção 2: Preencher com Valores Padrão (fillna)

**Quando usar?**
- ✅ Campos opcionais (BillingState, BillingPostalCode)
- ✅ Possível inferir valor padrão razoável
- ✅ Importante manter quantidade de registros

**Implementação**:

```python
def _handle_null_values_fillna(self, df):
    """
    Estratégia TOLERANTE: Preenche nulos com valores padrão
    
    JUSTIFICATIVA:
    - BillingPostalCode: "00000" indica ausência (padrão postal)
    - BillingState: "N/A" para países sem estados
    - BillingAddress: "Não Informado" para rastreamento
    """
    log.info("🔍 [Validador] Aplicando estratégia TOLERANTE de nulos...")
    
    # Contadores
    filled_fields = {}
    
    # CEP: Preencher com código postal genérico
    if 'BillingPostalCode' in df.columns:
        null_count = df['BillingPostalCode'].isnull().sum()
        if null_count > 0:
            df['BillingPostalCode'] = df['BillingPostalCode'].fillna('00000')
            filled_fields['BillingPostalCode'] = null_count
            log.info(f"   └─ BillingPostalCode: {null_count} nulos → '00000'")
    
    # Estado: Preencher com "N/A"
    if 'BillingState' in df.columns:
        null_count = df['BillingState'].isnull().sum()
        if null_count > 0:
            df['BillingState'] = df['BillingState'].fillna('N/A')
            filled_fields['BillingState'] = null_count
            log.info(f"   └─ BillingState: {null_count} nulos → 'N/A'")
    
    # Endereço: Preencher com placeholder
    if 'BillingAddress' in df.columns:
        null_count = df['BillingAddress'].isnull().sum()
        if null_count > 0:
            df['BillingAddress'] = df['BillingAddress'].fillna('Não Informado')
            filled_fields['BillingAddress'] = null_count
            log.info(f"   └─ BillingAddress: {null_count} nulos → 'Não Informado'")
    
    total_filled = sum(filled_fields.values())
    log.info(f"✅ Total de valores preenchidos: {total_filled}")
    
    return df
```

**Exemplo Prático com Invoice.json**:

```python
# Antes
original_df = pd.read_json('Invoice.json')
print(original_df['BillingPostalCode'].isnull().sum())  # 3 registros

# Aplicar fillna
cleaned_df = _handle_null_values_fillna(original_df)

# Depois
print(cleaned_df['BillingPostalCode'].isnull().sum())  # 0 registros
print(cleaned_df[cleaned_df['InvoiceId'] == 10]['BillingPostalCode'])
# Output: '00000'

# LOG: └─ BillingPostalCode: 3 nulos → '00000'
#      └─ BillingState: 150 nulos → 'N/A'
#      ✅ Total de valores preenchidos: 153
```

---

#### ⚖️ Estratégia Híbrida (Recomendada)

**A melhor abordagem**: Combinar ambas!

```python
def _apply_validations(self, df):
    """Estratégia HÍBRIDA: Estrita + Tolerante"""
    
    # PASSO 1: Remover registros críticos inválidos
    df = self._handle_null_values_strict(df)
    
    # PASSO 2: Preencher campos opcionais
    df = self._handle_null_values_fillna(df)
    
    # PASSO 3: Remover duplicatas
    df = self._remove_duplicates(df)
    
    # PASSO 4: Converter tipos
    df = self._convert_data_types(df)
    
    return df
```

---

### 4.2 Detecção e Remoção de Duplicatas

#### Por que Duplicatas são Perigosas?

**Cenário Real - Invoice.json**:

```python
# Supondo que temos duplicatas acidentais:
df_with_duplicates = pd.concat([
    original_df,
    original_df[original_df['InvoiceId'] == 1]  # Duplica a fatura #1
])

# Cálculo ERRADO
total_revenue = df_with_duplicates['Total'].sum()
# Result: 2328.60 + 1.98 = 2330.58 ❌

# Cálculo CORRETO (sem duplicata)
total_revenue_correct = original_df['Total'].sum()
# Result: 2328.60 ✅

# DIFERENÇA: $1.98 (0.08% de erro)
# Em um dataset de milhões, isso = prejuízo de milhares!
```

#### Implementação

```python
def _remove_duplicates(self, df):
    """
    Remove registros duplicados baseado em chave primária e campos de negócio
    
    JUSTIFICATIVA:
    - InvoiceId: Chave primária, DEVE ser única
    - Duplicatas de (CustomerId + InvoiceDate + Total) indicam reprocessamento
    
    ESTRATÉGIAS:
    1. Duplicatas EXATAS (todos os campos iguais) → Remover
    2. Duplicatas de InvoiceId → CRÍTICO, remover todos menos o primeiro
    3. Duplicatas de negócio → Investigar antes de remover
    """
    log.info("🔍 [Validador] Detectando duplicatas...")
    
    original_rows = len(df)
    
    # ESTRATÉGIA 1: Remover duplicatas EXATAS (todas as colunas iguais)
    df_dedup = df.drop_duplicates()
    exact_duplicates = original_rows - len(df_dedup)
    
    if exact_duplicates > 0:
        log.warning(f"⚠️ Removidas {exact_duplicates} duplicatas EXATAS")
        df = df_dedup
    
    # ESTRATÉGIA 2: Verificar duplicatas de InvoiceId (CRÍTICO)
    if 'InvoiceId' in df.columns:
        duplicated_ids = df[df.duplicated(subset=['InvoiceId'], keep=False)]
        
        if len(duplicated_ids) > 0:
            log.error(f"❌ CRÍTICO: {len(duplicated_ids)} registros com InvoiceId duplicado!")
            log.error(f"   IDs afetados: {duplicated_ids['InvoiceId'].unique().tolist()}")
            
            # Manter apenas a primeira ocorrência
            df = df.drop_duplicates(subset=['InvoiceId'], keep='first')
            log.info(f"   └─ Mantida primeira ocorrência de cada ID")
    
    # ESTRATÉGIA 3: Detectar duplicatas de negócio (suspeitas)
    business_key = ['CustomerId', 'InvoiceDate', 'Total']
    
    if all(col in df.columns for col in business_key):
        business_duplicates = df[df.duplicated(subset=business_key, keep=False)]
        
        if len(business_duplicates) > 0:
            log.warning(f"⚠️ ATENÇÃO: {len(business_duplicates)} registros com chave de negócio duplicada")
            log.warning(f"   Campos: CustomerId + InvoiceDate + Total")
            log.warning(f"   Verificar manualmente se são compras legítimas!")
            
            # NÃO remove automaticamente - pode ser legítimo
            # Cliente pode comprar 2x no mesmo dia pelo mesmo valor
    
    # Resumo
    total_removed = original_rows - len(df)
    if total_removed > 0:
        log.info(f"📊 Resumo: {original_rows} → {len(df)} registros ({total_removed} removidos)")
    else:
        log.info(f"✅ Nenhuma duplicata encontrada")
    
    return df
```

**Exemplo Prático**:

```python
# Criar dataset com duplicatas para teste
import pandas as pd

invoices = [
    {"InvoiceId": 1, "CustomerId": 2, "InvoiceDate": "2009-01-01", "Total": 1.98},
    {"InvoiceId": 1, "CustomerId": 2, "InvoiceDate": "2009-01-01", "Total": 1.98},  # ❌ DUPLICATA EXATA
    {"InvoiceId": 2, "CustomerId": 4, "InvoiceDate": "2009-01-02", "Total": 3.96},
    {"InvoiceId": 3, "CustomerId": 4, "InvoiceDate": "2009-01-02", "Total": 3.96},  # ⚠️ Mesmo cliente, dia e valor (mas IDs diferentes)
]

df = pd.DataFrame(invoices)

# Aplicar limpeza
df_cleaned = _remove_duplicates(df)

# LOGS:
# ⚠️ Removidas 1 duplicatas EXATAS
# ❌ CRÍTICO: 2 registros com InvoiceId duplicado!
#    IDs afetados: [1]
#    └─ Mantida primeira ocorrência de cada ID
# ⚠️ ATENÇÃO: 2 registros com chave de negócio duplicada
#    Campos: CustomerId + InvoiceDate + Total
#    Verificar manualmente se são compras legítimas!
# 📊 Resumo: 4 → 3 registros (1 removidos)
```

---

### 4.3 Conversão de Tipos (Type Casting)

#### Por que Tipagem Importa?

```python
# ❌ ERRO COMUM - Datas como string
df['InvoiceDate'] = "2009-01-01 00:00:00"  # object (string)

# Tentar extrair mês
df['InvoiceDate'].dt.month
# AttributeError: Can only use .dt accessor with datetimelike values
```

#### Implementação

```python
def _convert_data_types(self, df):
    """
    Converte colunas para tipos apropriados
    
    JUSTIFICATIVA:
    - Datas em string impedem análises temporais (tendências, sazonalidade)
    - Números em string impedem cálculos matemáticos
    - Tipos corretos reduzem uso de memória
    """
    log.info("🔍 [Validador] Convertendo tipos de dados...")
    
    conversions = []
    
    # ══════════════════════════════════════════════════════════
    # 1. DATAS: String → datetime64
    # ══════════════════════════════════════════════════════════
    date_columns = ['InvoiceDate']
    
    for col in date_columns:
        if col in df.columns:
            try:
                original_type = df[col].dtype
                
                # Converter para datetime
                df[col] = pd.to_datetime(df[col], errors='coerce')
                
                # Verificar conversões falhadas
                null_after_conversion = df[col].isnull().sum()
                
                conversions.append(f"{col}: {original_type} → datetime64")
                log.info(f"   └─ {col}: {original_type} → datetime64")
                
                if null_after_conversion > 0:
                    log.warning(f"      ⚠️ {null_after_conversion} datas inválidas convertidas para NaT")
                
            except Exception as e:
                log.error(f"❌ Erro ao converter {col}: {e}")
    
    # ══════════════════════════════════════════════════════════
    # 2. NÚMEROS: String/Object → int/float
    # ══════════════════════════════════════════════════════════
    numeric_columns = {
        'InvoiceId': 'int64',
        'CustomerId': 'int64',
        'Total': 'float64'
    }
    
    for col, target_type in numeric_columns.items():
        if col in df.columns:
            try:
                original_type = df[col].dtype
                
                # Pular se já está no tipo correto
                if df[col].dtype == target_type:
                    continue
                
                # Converter
                if 'int' in target_type:
                    df[col] = pd.to_numeric(df[col], errors='coerce').astype('Int64')
                else:
                    df[col] = pd.to_numeric(df[col], errors='coerce')
                
                conversions.append(f"{col}: {original_type} → {target_type}")
                log.info(f"   └─ {col}: {original_type} → {target_type}")
                
            except Exception as e:
                log.error(f"❌ Erro ao converter {col}: {e}")
    
    # ══════════════════════════════════════════════════════════
    # 3. STRINGS: Garantir que textos sejam string
    # ══════════════════════════════════════════════════════════
    text_columns = ['BillingAddress', 'BillingCity', 'BillingState', 
                    'BillingCountry', 'BillingPostalCode']
    
    for col in text_columns:
        if col in df.columns:
            if df[col].dtype != 'object':
                df[col] = df[col].astype(str)
                conversions.append(f"{col}: {df[col].dtype} → string")
                log.info(f"   └─ {col}: → string")
    
    # ══════════════════════════════════════════════════════════
    # 4. RESUMO
    # ══════════════════════════════════════════════════════════
    log.info(f"✅ {len(conversions)} conversões realizadas")
    
    return df
```

**Exemplo Prático - Análise Temporal Após Conversão**:

```python
# ANTES da conversão
df = pd.read_json('Invoice.json')
print(df['InvoiceDate'].dtype)  # object

# ❌ NÃO FUNCIONA
df['Month'] = df['InvoiceDate'].dt.month  # ERROR!

# ═══════════════════════════════════════════════════════════

# DEPOIS da conversão
df = _convert_data_types(df)
print(df['InvoiceDate'].dtype)  # datetime64[ns]

# ✅ FUNCIONA!
df['Month'] = df['InvoiceDate'].dt.month
df['Year'] = df['InvoiceDate'].dt.year

# Análise de vendas por mês
monthly_sales = df.groupby('Month')['Total'].sum()
print(monthly_sales)

# Output:
# Month
# 1     58.41
# 2    110.88
# 3    104.94
# 4     78.21
# 5     83.16
# ...
```

**Análises Possibilitadas pela Tipagem Correta**:

```python
# 1. Filtrar faturas de um período
q1_2009 = df[
    (df['InvoiceDate'] >= '2009-01-01') & 
    (df['InvoiceDate'] <= '2009-03-31')
]

# 2. Calcular diferença entre datas
df['DaysSinceFirstInvoice'] = (
    df['InvoiceDate'] - df['InvoiceDate'].min()
).dt.days

# 3. Agrupar por trimestre
df['Quarter'] = df['InvoiceDate'].dt.quarter
quarterly_revenue = df.groupby('Quarter')['Total'].sum()

# 4. Análise de sazonalidade (dia da semana)
df['DayOfWeek'] = df['InvoiceDate'].dt.day_name()
weekend_sales = df[df['DayOfWeek'].isin(['Saturday', 'Sunday'])]['Total'].sum()
```

---

## 5. Exercícios Hands-On

### Exercício 1: Implementação Básica

**Objetivo**: Adicionar validação de nulos na classe `MeuValidador`

**Tarefa**:
1. Abra o arquivo `meu_validador.py`
2. Adicione o método `_handle_null_values_strict` dentro da classe
3. Chame-o em `_apply_validations` ANTES das validações existentes
4. Teste com o Invoice.json

**Código Base**:

```python
def _apply_validations(self, df):
    """Aplicar todas as validações no DataFrame"""
    log.info(f"⚙️ [MeuValidador] Iniciando validações...")
    
    original_rows = len(df)
    
    # ╔═══════════════════════════════════════════════════════╗
    # ║ ADICIONE AQUI: Chamar _handle_null_values_strict     ║
    # ╚═══════════════════════════════════════════════════════╝
    
    # ... resto do código existente ...
```

**Resposta Esperada**:

```python
def _apply_validations(self, df):
    log.info(f"⚙️ [MeuValidador] Iniciando validações...")
    
    original_rows = len(df)
    
    # NOVA VALIDAÇÃO: Remover nulos críticos
    df = self._handle_null_values_strict(df)
    
    # Validações existentes...
    if 'BillingPostalCode' in df.columns:
        # ... código existente ...
```

---

### Exercício 2: Estratégia Híbrida

**Objetivo**: Combinar estratégias estrita e tolerante

**Dados de Teste**:

```python
# Criar dataset de teste com problemas
test_data = {
    'InvoiceId': [1, 2, 3, 4, 5],
    'CustomerId': [10, None, 12, 13, 14],      # ❌ Cliente nulo (crítico)
    'InvoiceDate': [
        '2009-01-01',
        '2009-01-02',
        None,                                    # ❌ Data nula (crítico)
        '2009-01-04',
        '2009-01-05'
    ],
    'Total': [10.0, 20.0, 30.0, 40.0, None],    # ❌ Total nulo (crítico)
    'BillingPostalCode': [
        '12345',
        None,                                    # ⚠️ CEP nulo (opcional)
        '67890',
        None,                                    # ⚠️ CEP nulo (opcional)
        '11111'
    ]
}

df_test = pd.DataFrame(test_data)
```

**Tarefa**:
1. Aplicar estratégia estrita → Quantos registros sobraram?
2. Aplicar estratégia tolerante → CEPs preenchidos?
3. Qual a ordem correta de aplicação?

**Resposta Esperada**:

```python
# PASSO 1: Remover críticos
df = _handle_null_values_strict(df_test)
# Resultado: 2 registros (IDs 1 e 4)
# Removidos: ID 2 (CustomerId nulo), ID 3 (Date nulo), ID 5 (Total nulo)

# PASSO 2: Preencher opcionais
df = _handle_null_values_fillna(df)
# Invoice 4 agora tem BillingPostalCode = '00000'
```

---

### Exercício 3: Análise Temporal

**Objetivo**: Usar tipagem correta para análise de tendências

**Tarefa**: Calcular receita total por mês de 2009

**Código Starter**:

```python
df = pd.read_json('Invoice.json')

# 1. Converter InvoiceDate para datetime
# SEU CÓDIGO AQUI

# 2. Extrair mês
# SEU CÓDIGO AQUI

# 3. Agrupar e somar
# SEU CÓDIGO AQUI

# 4. Plotar gráfico (bonus)
import matplotlib.pyplot as plt
# SEU CÓDIGO AQUI
```

**Resposta Esperada**:

```python
df = pd.read_json('Invoice.json')

# 1. Converter
df['InvoiceDate'] = pd.to_datetime(df['InvoiceDate'])

# 2. Extrair mês
df['Month'] = df['InvoiceDate'].dt.month

# 3. Agrupar
monthly_revenue = df.groupby('Month')['Total'].sum()
print(monthly_revenue)

# 4. Plotar
import matplotlib.pyplot as plt
monthly_revenue.plot(kind='bar', title='Receita Mensal 2009')
plt.ylabel('Total ($)')
plt.xlabel('Mês')
plt.show()
```

---

## 6. Checklist de Validação

### ✅ Antes de Colocar em Produção

Use este checklist para garantir que sua implementação está completa:

#### 📋 Valores Nulos

- [ ] Identificados campos críticos (não podem ser nulos)
- [ ] Identificados campos opcionais (podem ser preenchidos)
- [ ] Implementado `dropna()` para campos críticos
- [ ] Implementado `fillna()` para campos opcionais
- [ ] Valores padrão de `fillna()` fazem sentido para o negócio
- [ ] Logs informativos sobre registros removidos/preenchidos
- [ ] Taxa de perda de dados é aceitável (< 5%)

#### 📋 Duplicatas

- [ ] Identificada chave primária (InvoiceId)
- [ ] Implementado detecção de duplicatas exatas
- [ ] Implementado detecção de duplicatas de chave primária
- [ ] Implementado alerta para duplicatas de negócio
- [ ] Estratégia de remoção documentada (`keep='first'` ou `keep='last'`)
- [ ] Logs críticos para duplicatas de chave primária

#### 📋 Tipagem

- [ ] Identificadas colunas de data
- [ ] Implementado conversão com `pd.to_datetime()`
- [ ] Parâmetro `errors='coerce'` para datas inválidas
- [ ] Identificadas colunas numéricas
- [ ] Implementado conversão com `pd.to_numeric()`
- [ ] Validação de valores convertidos (contagem de NaN)
- [ ] Logs informativos sobre conversões realizadas

#### 📋 Testes

- [ ] Testado com dataset real (Invoice.json)
- [ ] Testado com dataset com problemas simulados
- [ ] Validado que análises temporais funcionam
- [ ] Validado que cálculos numéricos estão corretos
- [ ] Performance aceitável (< 10s para 10k registros)

#### 📋 Documentação

- [ ] Docstrings explicando justificativas
- [ ] Comentários em lógica complexa
- [ ] Exemplos de uso na documentação
- [ ] Changelog atualizado com novas features

---

## 7. Código Completo Refatorado

### 📄 meu_validador_completo.py

```python
from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging

log = logging.getLogger(__name__)


class MeuValidador(RawToMedallionPipeline):
    """
    Validador customizado com Data Sanitization completa.
    
    FEATURES:
    ✅ Limpeza de valores nulos (estrita + tolerante)
    ✅ Detecção e remoção de duplicatas
    ✅ Conversão de tipos (datas, números)
    ✅ Validações de negócio (CEP, etc)
    ✅ Quality Score
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """Override do hook Silver"""
        try:
            log.info(f"🔍 [MeuValidador] Processando Silver: {silver_key}")
            
            # Download
            local_file = self.hook.download_file(
                key=silver_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            
            # Ler DataFrame
            df = pd.read_parquet(local_file)
            log.info(f"📊 [MeuValidador] Entrada: {len(df)} registros")
            
            # Aplicar validações
            df = self._apply_validations(df)
            
            # Salvar
            df.to_parquet(local_file, index=False)
            
            # Upload
            self.hook.load_file(
                filename=local_file,
                key=silver_key,
                bucket_name=self.bucket,
                replace=True
            )
            
            log.info(f"🚀 [MeuValidador] Silver validada ✅")
            return silver_key
            
        except Exception as e:
            log.error(f"❌ [MeuValidador] ERRO: {e}", exc_info=True)
            raise
    
    def _apply_validations(self, df):
        """Pipeline completo de validações"""
        log.info(f"⚙️ [MeuValidador] Iniciando pipeline de validações...")
        
        original_rows = len(df)
        original_cols = len(df.columns)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 1: Converter tipos PRIMEIRO (para facilitar validações)
        # ══════════════════════════════════════════════════════════
        df = self._convert_data_types(df)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 2: Remover nulos CRÍTICOS
        # ══════════════════════════════════════════════════════════
        df = self._handle_null_values_strict(df)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 3: Preencher nulos OPCIONAIS
        # ══════════════════════════════════════════════════════════
        df = self._handle_null_values_fillna(df)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 4: Remover duplicatas
        # ══════════════════════════════════════════════════════════
        df = self._remove_duplicates(df)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 5: Validações de negócio (CEP, etc)
        # ══════════════════════════════════════════════════════════
        df = self._business_validations(df)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 6: Normalizar nomes de colunas
        # ══════════════════════════════════════════════════════════
        df.columns = (df.columns
                     .str.strip()
                     .str.lower()
                     .str.replace(' ', '_')
                     .str.replace('-', '_'))
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 7: Remover colunas 100% nulas
        # ══════════════════════════════════════════════════════════
        cols_to_drop = [col for col in df.columns if df[col].isnull().all()]
        if cols_to_drop:
            log.info(f"🗑️ Removendo {len(cols_to_drop)} colunas 100% nulas")
            df = df.drop(columns=cols_to_drop)
        
        # ══════════════════════════════════════════════════════════
        # ETAPA 8: Data Quality Score
        # ══════════════════════════════════════════════════════════
        self._calculate_quality_score(df, original_rows, original_cols)
        
        return df
    
    def _handle_null_values_strict(self, df):
        """Estratégia ESTRITA: Remove registros com nulos em campos críticos"""
        log.info("🔍 [Validador] Estratégia ESTRITA de nulos...")
        
        original_rows = len(df)
        critical_fields = ['InvoiceId', 'CustomerId', 'Total', 'InvoiceDate']
        
        # Filtrar apenas campos que existem no DataFrame
        existing_critical = [f for f in critical_fields if f in df.columns]
        
        if existing_critical:
            df = df.dropna(subset=existing_critical)
            removed = original_rows - len(df)
            
            if removed > 0:
                log.warning(f"⚠️ Removidos {removed} registros com nulos críticos ({(removed/original_rows)*100:.2f}%)")
            else:
                log.info(f"✅ Nenhum registro com nulos críticos")
        
        return df
    
    def _handle_null_values_fillna(self, df):
        """Estratégia TOLERANTE: Preenche nulos com valores padrão"""
        log.info("🔍 [Validador] Estratégia TOLERANTE de nulos...")
        
        fill_config = {
            'BillingPostalCode': '00000',
            'BillingState': 'N/A',
            'BillingAddress': 'Não Informado'
        }
        
        total_filled = 0
        
        for col, default_value in fill_config.items():
            if col in df.columns:
                null_count = df[col].isnull().sum()
                if null_count > 0:
                    df[col] = df[col].fillna(default_value)
                    total_filled += null_count
                    log.info(f"   └─ {col}: {null_count} nulos → '{default_value}'")
        
        if total_filled > 0:
            log.info(f"✅ Total preenchido: {total_filled} valores")
        
        return df
    
    def _remove_duplicates(self, df):
        """Remove duplicatas exatas e de chave primária"""
        log.info("🔍 [Validador] Detectando duplicatas...")
        
        original_rows = len(df)
        
        # Duplicatas exatas
        df = df.drop_duplicates()
        exact_dups = original_rows - len(df)
        
        if exact_dups > 0:
            log.warning(f"⚠️ Removidas {exact_dups} duplicatas EXATAS")
        
        # Duplicatas de chave primária
        if 'InvoiceId' in df.columns:
            dup_ids = df[df.duplicated(subset=['InvoiceId'], keep=False)]
            
            if len(dup_ids) > 0:
                log.error(f"❌ CRÍTICO: {len(dup_ids)} InvoiceIds duplicados!")
                log.error(f"   IDs: {dup_ids['InvoiceId'].unique().tolist()}")
                df = df.drop_duplicates(subset=['InvoiceId'], keep='first')
        
        total_removed = original_rows - len(df)
        if total_removed > 0:
            log.info(f"📊 {original_rows} → {len(df)} registros ({total_removed} removidos)")
        else:
            log.info(f"✅ Nenhuma duplicata encontrada")
        
        return df
    
    def _convert_data_types(self, df):
        """Converte colunas para tipos apropriados"""
        log.info("🔍 [Validador] Convertendo tipos...")
        
        # Datas
        if 'InvoiceDate' in df.columns:
            df['InvoiceDate'] = pd.to_datetime(df['InvoiceDate'], errors='coerce')
            log.info(f"   └─ InvoiceDate → datetime64")
        
        # Números
        numeric_cols = {'InvoiceId': 'Int64', 'CustomerId': 'Int64', 'Total': 'float64'}
        
        for col, dtype in numeric_cols.items():
            if col in df.columns and df[col].dtype != dtype:
                df[col] = pd.to_numeric(df[col], errors='coerce')
                if 'Int' in dtype:
                    df[col] = df[col].astype('Int64')
                log.info(f"   └─ {col} → {dtype}")
        
        return df
    
    def _business_validations(self, df):
        """Validações específicas de negócio"""
        
        # Validação CEP (código original mantido)
        if 'BillingPostalCode' in df.columns:
            log.info("🔍 Validando CEP...")
            invalid_mask = (
                (df['BillingPostalCode'].isnull()) |
                (df['BillingPostalCode'].astype(str).str.strip().str.lower()
                 .isin(['nan', 'none', 'null', '', 'undefined']))
            )
            invalid_count = invalid_mask.sum()
            
            if invalid_count > 0:
                df.loc[invalid_mask, 'BillingPostalCode'] = None
                log.info(f"   └─ {invalid_count} CEPs inválidos normalizados")
        
        return df
    
    def _calculate_quality_score(self, df, original_rows, original_cols):
        """Calcula e loga score de qualidade"""
        
        total_cells = df.size
        filled_cells = df.notna().sum().sum()
        quality_score = (filled_cells / total_cells * 100) if total_cells > 0 else 100
        
        log.info(f"📊 Quality Score: {quality_score:.2f}%")
        log.info(f"📈 Resumo Final: {original_rows}→{len(df)} registros, {original_cols}→{len(df.columns)} colunas")
        
        if quality_score < 50:
            log.error(f"❌ Quality Score CRÍTICO!")
        elif quality_score < 80:
            log.warning(f"⚠️ Quality Score BAIXO!")
        else:
            log.info(f"✅ Quality Score ACEITÁVEL!")
```

---

## 📚 Recursos Adicionais

### Leitura Recomendada

1. **Pandas Documentation**:
   - [Working with missing data](https://pandas.pydata.org/docs/user_guide/missing_data.html)
   - [Duplicate data](https://pandas.pydata.org/docs/user_guide/duplicates.html)
   - [Time series](https://pandas.pydata.org/docs/user_guide/timeseries.html)

2. **Data Quality**:
   - "Data Cleaning" - Ian H. Witten
   - "The Data Warehouse Toolkit" - Ralph Kimball

### Próximos Passos

1. ✅ Implementar validações customizadas
2. ✅ Adicionar testes unitários
3. ⏭️ Integrar com Great Expectations
4. ⏭️ Dashboard de Quality Score
5. ⏭️ Alertas automáticos para dados ruins

---

## 🎓 Avaliação de Aprendizado

### Quiz Final

1. **Qual a diferença entre `dropna()` e `fillna()`?**
   - a) Não há diferença
   - b) `dropna` remove linhas, `fillna` preenche valores
   - c) `dropna` é mais rápido
   - d) `fillna` sempre remove duplicatas

2. **Por que duplicatas de InvoiceId são CRÍTICAS?**
   - a) Ocupam mais espaço
   - b) Inflam métricas financeiras
   - c) São difíceis de detectar
   - d) Não são críticas

3. **Qual o resultado de `pd.to_datetime('invalid', errors='coerce')`?**
   - a) Exception
   - b) NaT (Not a Time)
   - c) None
   - d) '1970-01-01'

**Respostas**: 1-b, 2-b, 3-b

---

## 📝 Notas do Instrutor

### Timing Sugerido

- **Teoria (1h)**: Seções 1-3
- **Demo ao Vivo (1h)**: Implementar uma validação completa
- **Hands-On (1.5h)**: Exercícios 1-3
- **Q&A (30min)**: Dúvidas e casos especiais

### Pontos de Atenção

1. **Estudantes podem confundir** `drop_duplicates()` com `dropna()`
   - **Solução**: Mostrar visualmente a diferença com exemplos

2. **Tipagem causa muitos erros**
   - **Solução**: Sempre usar `errors='coerce'` e verificar NaN após conversão

3. **Ordem das validações importa!**
   - **Solução**: Sempre converter tipos ANTES de aplicar outras validações

---

**Fim do Plano de Aula** 🎓

**Versão**: 1.0  
**Última Atualização**: Fevereiro 2026  
**Autor**: Sistema de Treinamento Datalake
