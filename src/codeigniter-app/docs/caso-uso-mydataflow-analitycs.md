# Caso de Uso: MyDataflow Analytics

## Diferenças Encontradas Entre os Registros (id=146 vs id=176)

### 1. Campos de Data
- **id=146:** Muitos campos de data estão como `None` ou string "None"/"null".
- **id=176:** Todos os campos de data possuem valores válidos (string ISO ou data).

### 2. Campos JSON/Complexos
- **id=146:** Campos como `google_token`, `google_id`, `google_refresh_token`, `auth_provider` estão como `None` ou ausentes.
- **id=176:** Esses campos possuem valores válidos (string ou JSON).

### 3. Campos Booleanos/Flag
- **id=146:** Muitos campos booleanos estão como 0 ou `None`.
- **id=176:** Campos booleanos estão como 1 ou valores válidos.


## Diagnóstico Técnico da Camada Silver

### Métodos e Lógicas Relevantes

- **bronze_to_silver:** Função principal que faz a transformação Bronze → Silver, leitura de arquivos (CSV, Parquet, JSON), limpeza básica, chama transformações inteligentes e validação de qualidade.
- **_normalize_json_payload:** Normaliza payloads JSON para DataFrame, suporta NDJSON, lista de dicts, dict com chave-raiz, fallback.
- **_apply_smart_transformations:** Aplica normalização automática de dados:
    - Normaliza nomes de colunas
    - Detecta e converte datas
    - Normaliza strings (trim)
    - Detecta colunas categóricas
    - Inferência de tipos numéricos
    - Preenchimento inteligente de nulos
    - Adiciona colunas de auditoria

### Pontos de Melhoria e Recomendações

1. **Normalização de Tipos**
   - Adicionar detecção e conversão explícita de booleanos (ex: 'true', 'false', '1', '0', 'sim', 'não').
   - Permitir configuração de mapeamento de tipos por coluna.

2. **Tratamento de Nulos**
   - Permitir configuração de estratégia de preenchimento por coluna.
   - Logar colunas com excesso de nulos (>50%).

3. **Validação de Datas**
   - Validar formatos e logar valores inválidos.

4. **Validação de JSON**
   - Integrar validação de schema (ex: com `jsonschema`) se disponível.

5. **Conversão de Booleanos**
   - Implementar conversão robusta para booleanos, incluindo valores localizados.

6. **Colunas de Auditoria**
   - Adicionar coluna `_silver_type_map` (dict de tipos inferidos por coluna).

### Exemplo de Refatoração (Conversão de Booleanos)

```python
for col in df.select_dtypes(include=['object']).columns:
    bool_map = {'true': True, 'false': False, '1': True, '0': False, 'yes': True, 'no': False, 'sim': True, 'não': False}
    if df[col].str.lower().isin(bool_map.keys()).sum() / len(df) > 0.8:
        df[col] = df[col].str.lower().map(bool_map)
        log.info(f"[SILVER] ✓ Coluna '{col}' convertida para booleano")
```

### Resumo das Boas Práticas
- Garantir tipos corretos e consistentes em todas as colunas
- Tratar nulos e valores inválidos antes de enviar para a gold
- Validar datas, JSON e booleanos
- Adicionar colunas de auditoria para rastreabilidade
- Documentar e configurar estratégias de tratamento conforme necessidade do negócio

## Log de Registros Reprovados na Silver

Para garantir rastreabilidade e facilitar auditoria, recomenda-se que todo registro categorizado como "Fail" na validação de qualidade seja logado no Airflow, incluindo o critério de reprovação e o registro completo.

### Exemplo de implementação:

```python
# Após a validação de qualidade:
df, quality_metrics = validate_dataframe(df, target_table_name)

# Logar registros reprovados
failed_rows = df[df['DataQualityEvaluationResult'] == 'Failed']
for idx, row in failed_rows.iterrows():
    motivos = []
    if row['DataQualityRulesFail'] > 0:
        if row.isna().any():
            motivos.append('Campos nulos')
        # Adicione outros critérios conforme regras
    log.warning(f"[SILVER][FAIL] Registro reprovado (índice {idx}): {row.to_dict()} | Motivos: {motivos}")
```

- O log deve conter o índice, os dados do registro e os motivos principais da reprovação (ex: campos nulos, tipos inválidos, duplicatas, etc).
- Expanda os motivos conforme as regras de qualidade implementadas.
- Isso facilita o diagnóstico e auditoria dos dados processados.

---

**Diagnóstico realizado em 28/02/2026.**
