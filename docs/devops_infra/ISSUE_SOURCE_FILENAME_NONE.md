# Issue: source_filename está None na pipeline Airflow (API)

## Resumo do Problema
Ao executar uma DAG no Airflow para ingestão de dados via API, a pipeline falha com o erro:

```
[PIPELINE] source_filename está None! Verifique ingest_api_to_raw e se o campo 'key' está correto.
```

Esse erro ocorre porque a pipeline espera receber o caminho do arquivo gerado pela função `ingest_api_to_raw`, mas está recebendo `None`.

---

## Diagnóstico Detalhado

### 1. Fluxo Esperado
- A função `raw_to_medallion` detecta o tipo de fonte (API, MySQL, arquivo).
- Para API, chama `ingest_api_to_raw`, que deve:
  - Fazer a requisição à API.
  - Salvar o resultado em arquivo temporário.
  - Fazer upload para o MinIO.
  - Retornar um dicionário com o campo `key` (caminho S3 do arquivo).
- O valor de `key` é passado como `source_filename` para a pipeline.

### 2. Sintoma
- O log mostra que `source_filename` está `None` ao entrar na pipeline.
- Não há logs do retorno de `ingest_api_to_raw` nem do campo `key`.
- O erro ocorre antes de qualquer processamento de arquivo.

### 3. Possíveis Causas
- `ingest_api_to_raw` não está sendo chamada (erro na detecção do tipo de fonte).
- O retorno de `ingest_api_to_raw` não está sendo propagado corretamente.
- Alguma exceção ocorre antes do return, interrompendo a execução.
- O campo `key` não está presente ou está `None` no dicionário retornado.

### 4. Diagnóstico no Código
- Foram adicionados logs detalhados antes e depois da chamada de `ingest_api_to_raw`.
- Também foram sugeridos logs globais para rastrear exceções e fluxo de entrada/saída.
- Um patch com bloco try/except/finally foi proposto, mas gerou erro de sintaxe devido à estrutura dos elif/else.
- O patch foi desfeito para evitar múltiplos erros.

### 5. Ambiente Docker
- O ambiente utiliza múltiplos containers (webserver, scheduler, worker, minio, etc).
- Os logs das DAGs não estavam sendo gravados corretamente nos containers worker/scheduler.
- Foi necessário buscar o log correto para análise.

---

## Solução Recomendada
1. Adicionar logs apenas antes e depois da chamada de `ingest_api_to_raw` (sem alterar a estrutura global da função).
2. Garantir que qualquer exceção em `ingest_api_to_raw` seja logada.
3. Verificar se o campo `key` está presente e não é `None` no retorno.
4. Validar se o tipo de fonte está sendo detectado corretamente.
5. Garantir que os volumes de logs estejam corretamente mapeados entre os containers.

---

## Lições Aprendidas
- Alterações estruturais em funções críticas devem ser feitas de forma incremental.
- Sempre validar a sintaxe e indentação ao envolver grandes blocos de código em try/except/finally.
- O rastreamento de logs é fundamental para identificar o ponto exato de falha.
- O ambiente Docker pode dificultar a localização dos logs se os volumes não estiverem corretamente configurados.

---

## Referências
- Função `raw_to_medallion` (medallion_pipeline_v2.py)
- Função `ingest_api_to_raw` (api_ingestion.py)
- docker-compose.yml (configuração dos containers)
- Logs do Airflow (worker, scheduler)

---

## Status Atual
- O patch de logs detalhados foi desfeito devido a erros de sintaxe.
- Recomenda-se aplicar logs incrementais e validar o fluxo do campo `key`.
- Aguardando novo log após ajuste para análise final.
