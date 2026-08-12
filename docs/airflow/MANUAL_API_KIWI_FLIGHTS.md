# 🚀 Manual: Conectando API REST Amadeus no Airflow

## 1. Pré-requisitos

- Apache Airflow rodando e acessível
- Credenciais da API Amadeus (Client ID e Client Secret)
- Acesso ao painel web do sistema (dashboard)

---

## 2. Criando o Pipeline no Dashboard

### 2.1. Acesse o Dashboard

- Entre no painel web do sistema.

### 2.2. Inicie o Wizard de Pipeline

- Clique em “Criar Novo Pipeline” ou “Assistente de Criação de Pipeline”.

---

### 2.3. Preencha as Informações Básicas

- Nome do Pipeline: `job_cotacao_voos_brasilia_rio`
- Descrição: `Consulta voos BSB-RIO e envia JSON por e-mail`
- Pasta/Workspace: Selecione conforme sua organização

---

### 2.4. Configure a Fonte de Dados

- Tipo de Fonte: Selecione **API REST**
- Preencha o campo `transform_args` com o seguinte template, adaptando para sua API Amadeus:

```json
{
  "api_auth": "Bearer",
  "api_method": "GET",
  "api_headers": {
    "Content-Type": "application/json",
    "Authorization": "Bearer <Código obtido com o comando curl a seguir...>>"
  },
  "api_endpoint": "https://test.api.amadeus.com/v2/shopping/flight-offers"
}

Obtendo o token:
curl "https://test.api.amadeus.com/v1/security/oauth2/token" \
     -H "Content-Type: application/x-www-form-urlencoded" \
     -d "grant_type=client_credentials&client_id=EAVO8QrhAjfmbdM5tuXLsFgRY2uJQsK6&client_secret=58NOR7zPJBtUBvA7"

```

- Se precisar enviar parâmetros de consulta (query params), adicione o campo `api_params`:

```json
{
  ...,
  "api_params": {
    "originLocationCode": "BSB",
    "destinationLocationCode": "RIO",
    "departureDate": "2026-03-15",
    "adults": 1,
    "currencyCode": "BRL",
    "max": 5
  }
}
```

- Para métodos POST/PUT, inclua também o campo `api_payload`.

> **Dica:** Use `<sua API_KEY aqui>` ou `<seu token aqui>` para indicar onde inserir suas credenciais.

- Autenticação: Use o fluxo OAuth2 Client Credentials da Amadeus para obter o token de acesso e preencha o campo "Authorization" nos headers.

---

### 2.5. Configure a Tabela Destino

- Nome da Tabela Destino: `cotacao_voos_bsb_rio`

---

### 2.6. Escolha a Função Python

- Função Python: Selecione ou informe o módulo/função responsável por consumir APIs REST (exemplo: `lib.api_ingestion.rest_to_medallion`)
- Argumentos Extras: O campo `transform_args` deve conter o JSON acima, conforme sua necessidade.

---

### 2.7. Agendamento

- Frequência de Execução: `Diariamente às 08:00` (`0 8 * * *`)
- Status da DAG: Ativa

---

### 2.8. Revisar e Criar

- Revise todas as informações
- Clique em “Criar Pipeline”

---

## 3. Validando no Airflow

- Acesse o Airflow UI
- Verifique se a DAG `job_cotacao_voos_brasilia_rio` foi criada
- Execute manualmente ou aguarde o agendamento
- Veja o histórico, logs e resultados

---

## 4. Dicas

- Configure o envio de e-mail na DAG se desejar receber o resultado por e-mail
- Use o campo “Headers” para autenticação por apikey
- Consulte a documentação da Amadeus para mais parâmetros e exemplos

---

## 5. Troubleshooting

- Se a DAG falhar, verifique os logs no Airflow
- Confirme se a chave da API está correta
- Ajuste os parâmetros conforme necessário

---

Pronto! Seu pipeline está configurado para consumir a API da Amadeus e processar os dados automaticamente no Airflow.
