Aqui está a **Especificação de Requisitos (ERS)** revisada e atualizada.

O feedback do desenvolvedor foi crucial: agora a especificação reflete exatamente a engenharia real da sua plataforma, onde a aplicação PHP (via `DbtController.php`) já antecipa a criação física dos schemas tanto no **PostgreSQL (`datalake_bi`)** quanto no **DuckDB local**, usando o gancho (`on-run-start`) do dbt.

---

# 📑 Especificação de Requisitos (ERS) - Versão Atualizada

## Feature: Provisionamento Automatizado de Analytics Multi-Tenant (Self-Service BI)

### 1. Visão Geral

Esta feature visa automatizar a integração entre a esteira de dados gerada pelo **dbt** e a camada de visualização no **Metabase**. Aproveitando que a aplicação PHP (`DbtController.php`) já realiza o provisionamento prévio dos schemas do aluno, o sistema deverá estender essa automação para garantir que o aluno possua um ambiente de Analytics isolado e seguro no Metabase, consumindo diretamente do **Banco de Dados Analítico PostgreSQL (datalake_bi)**.

---

### 2. Arquitetura da Solução e Ciclo de Vida do Dado

O isolamento baseia-se na sinergia entre a aplicação PHP, a engine de transformação e o banco analítico:

```
[MGI / MyDataFlow (PHP)]
   │
   ├── 1. Cria Schemas (Dev/Prod) no PostgreSQL (datalake_bi)
   ├── 2. Injeta 'on-run-start' no dbt_project.yml (Cria Schemas no DuckDB local)
   │
[Execução do dbt] ──> Processa no DuckDB ──> Grava tabelas Gold no PostgreSQL (datalake_bi)
                                                           │
                                                           ▼
[Automação Metabase] ◄─────────────────────────────────────┘
   │
   ├── 3. Executa GRANTS de segurança por Aluno no PostgreSQL
   └── 4. Dispara API do Metabase (Cria Database restrita e Usuário)

```

---

### 3. Requisitos Funcionais (RF)

#### **RF-001: Vinculação e Segurança no Banco de Dados Analítico PostgreSQL (datalake_bi)**

* **Descrição:** Logo após a aplicação PHP garantir a criação física dos schemas (`$schemaDev` e `$schemaProd`) no PostgreSQL, o sistema deverá configurar os privilégios de acesso estrito para o usuário específico daquele aluno.
* **Critérios de Aceite:**
* O sistema deve gerar e executar um script de permissões logo após as linhas de criação do `DbtController.php`:
```sql
-- Exemplo focado no schema de Produção/Gold do Aluno
GRANT USAGE ON SCHEMA aluno_001_prod TO user_aluno_001;
GRANT SELECT ON ALL TABLES IN SCHEMA aluno_001_prod TO user_aluno_001;
ALTER DEFAULT PRIVILEGES IN SCHEMA aluno_001_prod GRANT SELECT ON TABLES TO user_aluno_001;

```


* **Regra de Bloqueio:** O usuário `user_aluno_XXX` não pode possuir privilégios de leitura nos schemas de outros alunos e nem nos schemas estruturais (`public`, `raw`, `bronze`, `silver`).



#### **RF-002: Provisionamento da Conexão e Usuário via API do Metabase**

* **Descrição:** O backend em PHP deverá invocar a API do Metabase para disponibilizar o workspace do aluno de forma transparente.
* **Critérios de Aceite:**
* **Mapeamento de Banco (POST /api/database):** Cadastrar uma nova base de dados no Metabase apontando para o **PostgreSQL (`datalake_bi`)**, utilizando como credenciais de login o usuário SQL isolado do aluno (`user_aluno_XXX`). Desta forma, o Metabase deste inquilino nascerá enxergando apenas o seu próprio schema produtivo.
* **Criação de Usuário (POST /api/user):** Criar as credenciais de acesso do aluno para a interface do Metabase.
* **Restrição de Grupo:** Associar o usuário criado a um grupo de segurança exclusivo no Metabase que possua permissão de visualização e edição **apenas** na conexão gerada para ele.



#### **RF-003: Interface de Redirecionamento e Acesso**

* **Descrição:** O painel web do *MyDataFlow* deve exibir o botão "Acessar Meu Analytics" para que o aluno entre diretamente em seu ambiente configurado.

---

### 4. Requisitos Não-Funcionais (RNF)

* **RNF-001 (Segurança e Isolamento):** Nenhuma query executada por um aluno através do editor SQL ou da interface visual do Metabase pode interceptar dados ou metadados de outros tenants contidos no **PostgreSQL (`datalake_bi`)**.
* **RNF-002 (Idempotência no Controller):** O processo de verificação e aplicação de permissões no PostgreSQL e o disparo de mapeamento na API do Metabase devem ser idempotentes. Caso o `DbtController.php` seja acionado novamente para o mesmo aluno, ele deve validar que as estruturas e acessos já existem, sem gerar falhas ou duplicidades.
* **RNF-003 (Persistência):** Como o dbt usa o gancho `on-run-start` para garantir os schemas dinamicamente no arquivo temporário `/tmp/datalake.duckdb`, o provisionamento do Metabase deve focar exclusivamente na persistência final que reside no **PostgreSQL (`datalake_bi`)**.

---

### 5. Regras de Negócio (RN)

* **RN-001 (Gatilho de Ativação do Metabase):** O mapeamento do banco do aluno na API do Metabase (RF-002) deve ocorrer imediatamente **após a primeira execução com sucesso do dbt**, garantindo que as tabelas do Cubo/DW já tenham sido fisicamente povoadas na camada Gold/Prod do **PostgreSQL (`datalake_bi`)**.
* **RN-002 (Política de Retenção e Suspensão):** Em caso de inadimplência ou cancelamento da assinatura na Hotmart, o backend em PHP deve disparar comandos para revogar os acessos no PostgreSQL e desativar o usuário correspondente no Metabase.

---

Com esses ajustes, o escopo está perfeitamente alinhado à realidade do seu código atual (`CodeIgniter`, `PDO`, `dbt-duckdb` e `PostgreSQL`). Prontinho para passar para o desenvolvimento!