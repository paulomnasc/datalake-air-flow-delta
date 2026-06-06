# 📊 Arquitetura de Execução do dbt Core Multi-Tenant Isolado

Este documento detalha o funcionamento, fluxo de dados, configuração de ambientes e o esquema de relações entre os artefatos necessários (como `.sql` e `.yaml`) para a execução isolada do dbt Core na stack do **MyDataFlow**.

---

## 🏗️ 1. Diagrama de Arquitetura da Solução

O dbt Core roda de forma isolada por aluno/inquilino utilizando contêineres Docker temporários (transientes). Abaixo está o fluxo de interação, desde o Monaco Editor até a persistência no PostgreSQL:

```mermaid
graph TD
    subgraph Cliente (Web Interface)
        A[Monaco Editor: dim_customers.sql] -->|Salvar| B[Git File Manager / API]
    end

    subgraph Armazenamento (S3 MinIO)
        B -->|putObject| C[User Bucket: scripts/owner/repo/models/...]
    end

    subgraph Aplicação (CodeIgniter App)
        D[DbtController::execute] -->|listObjects / getObject| C
        D -->|Download| E[VM Local Temp Dir: /writable/dbt-tmp/user_id]
        D -->|Gera Dinamicamente| F[profiles.yml com schema do usuário]
        D -->|Detecta e injeta se ausente| G[dbt_project.yml e macros/]
    end

    subgraph Docker Daemon (Transiente)
        H[Transient dbt Container] -->|Monta Volume| E
        H -->|Define working dir| E
        H -->|Executa dbt run| I[dbt Engine]
    end

    subgraph Banco de Dados (PostgreSQL)
        I -->|Lê Origens| J[(Schema: public)]
        I -->|Valida Schema Name Macro| K[generate_schema_name.sql]
        K -->|Materializa Views/Tabelas| L[(Schema: user_id_homolog_analytics)]
    end

    D -->|Executa| H
```

---

## ⚙️ 2. Configurações de Ambientes

O dbt é parametrizado para garantir isolamento físico (limite de memória) e lógico (schemas por inquilino):

### A. Isolamento Lógico (Multi-Tenancy)
O dbt determina o local físico das tabelas baseado no `userId` da sessão ativa do aluno no CodeIgniter:
* **Ambiente de Desenvolvimento (Dev)**: Schema `user_{userId}_homolog_analytics` (Sandbox de teste isolado).
* **Ambiente de Produção (Prod)**: Schema `user_{userId}_analytics` (Consumido pelo Metabase para geração de painéis).

### B. Limitação de Recursos e Rede do Docker
O contêiner do dbt é invocado a partir do Docker Socket do host VM com os seguintes parâmetros de segurança:
* `--rm`: O contêiner é completamente apagado após a execução, liberando espaço em disco.
* `--network`: O contêiner conecta-se dinamicamente na mesma rede do container de backend (detectada automaticamente pelo `DbtController`), permitindo acesso interno ao host `postgres-bi`.
* `--memory="512m"`: Limite físico de 512MB de RAM por execução para impedir ataques de negação de serviço (DoS) no servidor central.
* `-v {hostTempDir}:/usr/app`: Monta o diretório de execução temporário do usuário no contêiner.
* `-w {projectSubDir}`: Define o diretório de trabalho apontando exatamente para onde o `dbt_project.yml` foi localizado, suportando projetos dbt dentro de subpastas do repositório Git.

---

## 📁 3. Esquema de Relações entre Artefatos dbt

Para que o dbt execute e compile corretamente a partir do repositório Git do usuário, os seguintes arquivos são relacionados:

```
📦 Projeto dbt (Repositório do Usuário)
├── 📄 dbt_project.yml           # Arquivo principal do projeto dbt
├── 📂 macros/
│   └── 📄 generate_schema_name.sql # Customização de roteamento de schema
├── 📂 models/
│   ├── 📄 sources.yml           # Mapeamento de tabelas de origem PostgreSQL
│   ├── 📄 schema.yml            # Declaração de testes e documentação
│   └── 📄 dim_customers.sql     # Arquivo de modelo SQL analítico (Jinja + SQL)
└── 📄 profiles.yml              # Conexões geradas dinamicamente
```

### A. O Arquivo Principal: `dbt_project.yml`
Define as configurações globais do projeto dbt (nome, versão, diretórios de modelos e tipos de materialização padrão). 
* **Fallback Inteligente**: Se o repositório do usuário não tiver esse arquivo, o `DbtController` copia automaticamente o arquivo base do template da VM para permitir a compilação do dbt sem exigir que o aluno configure isso do zero.

### B. A Macro: `generate_schema_name.sql`
Por padrão, o dbt concatena o nome do schema do perfil (`profiles.yml`) com qualquer schema configurado nos modelos. No MyDataFlow, a macro foi redefinida para **respeitar estritamente o schema dinâmico** do inquilino definido na conexão:
```sql
{% macro generate_schema_name(custom_schema_name, node) -%}
    {{ target.schema }}
{%- endmacro %}
```
Isso garante que todas as tabelas geradas pelo contêiner transiente dbt caiam no schema `user_{userId}_homolog_analytics` (ou `user_{userId}_analytics`), impedindo a poluição de schemas compartilhados.

### C. Mapeamento de Origens: `sources.yml`
O dbt precisa saber onde ler as tabelas brutas (que foram sincronizadas pelo Airflow no PostgreSQL). A relação é feita mapeando as tabelas sob a fonte (geralmente `public` ou `raw_lakehouse`):
```yaml
version: 2
sources:
  - name: public
    schema: public
    tables:
      - name: customers_gold
      - name: orders_gold
```

### D. O Modelo SQL: `dim_customers.sql` (ou similares)
O arquivo SQL utiliza referências Jinja (`source`) para se relacionar com a origem definida no `sources.yml`:
```sql
{{ config(materialized='table') }}

with source_data as (
    select * from {{ source('public', 'customers_gold') }}
)

select
    customernumber as id_usuario,
    customername as nome,
    contactemail as email
from source_data
```

### E. O Arquivo de Conexão: `profiles.yml`
Este arquivo **não deve ser commitado no repositório**. Ele é gerado e regravado dinamicamente em tempo de execução pelo `DbtController` na pasta do projeto do usuário, injetando as credenciais e o schema isolado (`user_{userId}_homolog_analytics`) de forma segura:
```yaml
analytics:
  target: dev
  outputs:
    dev:
      type: postgres
      host: postgres-bi
      port: 5432
      user: pbi_user
      password: pbi_password
      dbname: datalake_bi
      schema: user_146_homolog_analytics
      threads: 4
```

---

## 🛠️ 4. Guia Rápido de Resolução de Problemas (Troubleshooting)

### A. Erro: `relation "public.xxx" does not exist`
* **Causa**: O seu modelo SQL tenta ler uma tabela que não foi sincronizada ou criada no PostgreSQL.
* **Solução**: Vá ao Airflow e execute a DAG de sincronização correspondente (`sync_delta_dw_{seu_usuario}`) para popular a tabela a partir do Delta Lake no MinIO.

### B. Erro: `depends on a source named 'public.xxx' which was not found`
* **Causa**: O modelo SQL usa `{{ source('public', 'xxx') }}` mas a tabela `xxx` não está listada no seu arquivo `models/sources.yml`.
* **Solução**: Crie ou atualize o arquivo `models/sources.yml` no seu repositório Git incluindo a tabela sob a fonte correspondente.
