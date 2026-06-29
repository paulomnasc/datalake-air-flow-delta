# 📋 Evolução para Contas Corporativas e IAM na Stack MyDataFlow

Este documento detalha o impacto evolutivo, a estratégia de coexistência sem quebra de retrocompatibilidade, o backlog de sprints para um MVP e os pré-requisitos de hardware necessários para suportar **Contas Corporativas** com múltiplos usuários e controle de acessos (**IAM**) na stack **MyDataFlow**.

---

## 🏗️ 1. Análise de Impacto por Camada

A evolução de um modelo **Mono-usuário (1 Usuário = 1 Tenant)** para um modelo **Corporativo (1 Empresa = N Usuários com IAM)** altera profundamente o fluxo de dados e controle de segurança:

### A. Sessão e Identidade (PHP / CodeIgniter)
* **Antes**: O ID do inquilino baseia-se diretamente no usuário logado (`userId`). O `SessionHelper` busca as credenciais dele.
* **Depois**: A aplicação introduz o conceito de `tenantId` (ou `companyId`). O `SessionHelper` precisará retornar o `tenantId` da sessão ativa, mapeando todos os membros da empresa para o mesmo bucket e base analítica.
* **IAM**: Introdução de tabela de papéis/roles (Ex: `Admin`, `Data Engineer`, `Viewer`) para limitar quem pode alterar pipelines ou modelos dbt.

### B. Armazenamento (S3/MinIO) e Airflow
* **Antes**: Cada usuário grava e executa pipelines em seu próprio bucket `user-{userId}`.
* **Depois**: Todos os usuários da empresa compartilham o bucket corporativo `company-{tenantId}`. As DAGs do Airflow são executadas para o bucket corporativo, e os uploads são centralizados.

### C. Banco de Dados (PostgreSQL `postgres-bi`)
* **Antes**: Um schema por usuário (`user_{userId}_analytics`) com uma role exclusiva (`user_aluno_{userId}`).
* **Depois**: O schema analítico passa a ser corporativo (`company_{tenantId}_analytics`). A role de acesso de leitura pode ser compartilhada ou mapeada individualmente por usuário para fins de auditoria fina.

### D. dbt Core e Monaco Editor
* **Antes**: O dbt grava direto no schema do usuário (`user_{userId}_homolog_analytics`).
* **Depois**: Se vários engenheiros rodarem dbt concorrentemente, haverá conflito no mesmo schema de homologação corporativo. A solução exige a criação de schemas de desenvolvimento individuais (ex: `company_{tenantId}_dev_{userId}_analytics`) para desenvolvimento sandbox, com promoção ao schema compartilhado apenas via CI/CD ou aprovação do Admin.

### E. Visualização de Dados (Metabase)
* **Antes**: Uma conexão de banco por usuário (`datalake_bi_user_{userId}`).
* **Depois**: Apenas uma conexão por empresa (`datalake_bi_company_{tenantId}`), na qual múltiplos usuários são adicionados sob o mesmo grupo de segurança no Metabase, promovendo colaboração interna de painéis e relatórios.

---

## 🔄 2. Estratégia de Coexistência (Mono-Usuário vs. Corporativo)

Para garantir que a transição ocorra de forma transparente, **sem interromper os usuários individuais atualmente em produção**, a solução adotará a estratégia de **Tenant Polimórfico**:

```mermaid
graph TD
    subgraph Banco de Dados MySQL
        U[Usuarios] -->|N:1| T[Tenants]
    end

    subgraph Lógica de Negócio (SessionHelper)
        T -->|Type: Individual| A[Comportamento Legado: user-userId]
        T -->|Type: Corporate| B[Comportamento IAM: company-tenantId]
    end
```

### A. Modelagem de Coexistência no Banco de Dados
A tabela de usuários será desacoplada por meio de uma tabela intermediária de inquilinos (`tenants`):

```sql
-- 1. Criação da tabela de Tenants
CREATE TABLE tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    type ENUM('individual', 'corporate') NOT NULL DEFAULT 'individual',
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Modificação da tabela de Usuários
ALTER TABLE usuarios 
ADD COLUMN tenant_id INT,
ADD COLUMN role_iam VARCHAR(50) DEFAULT 'admin',
ADD CONSTRAINT fk_usuarios_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id);
```

### B. Migração Transparente dos Dados Existentes
Para os usuários atuais em produção:
1. Criar um registro correspondente em `tenants` com `type = 'individual'` para cada usuário existente.
2. Associar o usuário atual a este novo tenant.
3. **Mapeamento de Nomenclatura**:
   * Se `tenant.type = 'individual'`: O `SessionHelper` mantém o mapeamento do bucket para `user-{userId}` e o schema do banco para `user_{userId}_analytics`.
   * Se `tenant.type = 'corporate'`: O `SessionHelper` mapeia o bucket para `company-{tenantId}` e o schema para `company_{tenantId}_analytics`.

---

## 🔐 3. Estratégia de Integração de SSO (MS AD, OpenLDAP, SAML/OIDC)

Para viabilizar a entrada simplificada e segura dos colaboradores das contas corporativas, a stack **MyDataFlow** suportará a integração federada com provedores de identidade corporativos (IdPs).

### A. Estrutura de Identidade Federada no Banco
Para mapear usuários autenticados externamente, a tabela `usuarios` será estendida:
```sql
ALTER TABLE usuarios
ADD COLUMN sso_provider ENUM('local', 'ldap', 'saml', 'oidc') DEFAULT 'local',
ADD COLUMN sso_uid VARCHAR(255) DEFAULT NULL COMMENT 'Identificador único do usuário no IdP (e.g. objectGUID ou sub)',
ADD COLUMN sso_attributes JSON DEFAULT NULL COMMENT 'Dados extras importados na autenticação';
```

### B. Protocolos de Autenticação Suportados
1. **LDAP / Active Directory (MS AD e OpenLDAP)**:
   * A aplicação PHP se conectará ao servidor de diretório interno via extensão PHP-LDAP (porta `389` ou `636` para LDAPS).
   * O login será realizado via *bind* direto com as credenciais do usuário.
2. **SAML 2.0 / OpenID Connect (OIDC)**:
   * Fluxo moderno via redirecionamento de navegador.
   * Utilização de bibliotecas padrão (ex: `SimpleSAMLphp` ou `php-openid-connect-client`).
   * Compatível com provedores em nuvem (Okta, Ping Identity, Azure AD / Entra ID, Google Workspace).

### C. Mapeamento Automático de Papéis e Provisionamento Just-In-Time (JIT)
* **Provisionamento Automático**: Ao autenticar via SSO com sucesso pela primeira vez, se o usuário não existir no banco local do MyDataFlow, ele é criado automaticamente e associado ao `tenant_id` da empresa (mapeado através do domínio do email ou claim corporativo enviado pelo IdP).
* **Mapeamento de Grupos (Role Sync)**: Durante o handshake do SSO, os grupos do usuário no AD/LDAP (ex: `CN=DataEngineers,OU=Groups...`) são lidos e mapeados para as permissões (roles IAM) internas do MyDataFlow:
  * Grupo AD `TI_DataEngineers` ➔ Role local `Data Engineer`
  * Grupo AD `Diretoria_BI` ➔ Role local `Viewer`

### D. Propagação de SSO para Airflow e Metabase
* **Metabase**: A integração SSO atual já utiliza JWT (Single Sign-On). O backend PHP continuará gerando tokens JWT assinados com base nas informações do usuário federado logado.
* **Airflow**: Os usuários federados do SSO são sincronizados automaticamente na API do Airflow pelo `AirflowHelper` com o perfil (`Viewer`/`User`/`Admin`) correspondente ao seu grupo no AD/LDAP.

---

## 📅 4. Backlog e Sprints do MVP

O plano de desenvolvimento do MVP está dividido em **5 Sprints de 1 a 2 semanas**:

```markdown
- [ ] **Sprint 1: Estrutura de Identidade & Coexistência no Core**
  - [ ] Criar tabelas `tenants` e executar migrations no MySQL.
  - [ ] Atualizar script de migração de produção para gerar tenants de tipo `individual` para contas legadas.
  - [ ] Implementar a lógica polimórfica no [SessionHelper.php](file:///root/datalake-air-flow-delta/src/codeigniter-app/app/Helpers/SessionHelper.php) (`getTenantId()`, `getTenantType()`, `getUserBucket()`).
  - [ ] Homologar login e navegação das contas legadas individuais.

- [ ] **Sprint 2: Isolamento de Armazenamento Corporativo & IAM Básico**
  - [ ] Implementar gerenciamento básico de membros do time na interface do Admin Corporativo.
  - [ ] Adaptar o `ConfigController` para salvar arquivos no bucket unificado da empresa (`company-{tenantId}`).
  - [ ] Validar acesso cruzado de buckets via `validateUserS3Path` baseado em privilégios corporativos.
  - [ ] Ajustar o Query Builder para apontar para o bucket da corporação quando o usuário for corporativo.

- [ ] **Sprint 3: Integração de SSO Corporativo (LDAP/AD/OIDC)**
  - [ ] Estender tabela de usuários para colunas de login federado (`sso_provider`, `sso_uid`).
  - [ ] Desenvolver driver de autenticação LDAP para MS AD e OpenLDAP no PHP.
  - [ ] Implementar o auto-provisionamento (JIT) e sincronização de grupos AD ➔ Roles IAM locais.
  - [ ] Testar fluxos de autenticação SSO integrados.

- [ ] **Sprint 4: Orquestração no Airflow & Sandbox dbt**
  - [ ] Adaptar `factory_master.py` e bibliotecas medallion para utilizar caminhos do bucket corporativo.
  - [ ] Atualizar `DbtController.php` para criar schemas isolados de dev (`company_{tenantId}_dev_{userId}_analytics`) na compilação do Monaco Editor.
  - [ ] Gerar dinamicamente o `profiles.yml` e configurar o roteamento dos modelos no `dbt_project.yml`.

- [ ] **Sprint 5: Colaboração de BI no Metabase & Homologação Geral**
  - [ ] Adaptar `MetabaseHelper.php` para provisionar apenas 1 conexão por empresa (`datalake_bi_company_{tenantId}`).
  - [ ] Criar grupos corporativos no Metabase e associar usuários via SSO JWT.
  - [ ] Realizar testes de carga, concorrência e homologação end-to-end do fluxo corporativo.
```

---

## ⚡ 5. Pré-requisitos de Evolução de Hardware

A VM atual que hospeda a stack MyDataFlow executa um ecossistema denso de serviços (Nginx, Spark Master/Worker, PostgreSQL Airflow, PostgreSQL BI, Redis, MySQL, Airflow, MinIO, DuckDB API e Metabase) em um único nó. A evolução para multi-usuários ativos simultaneamente rodando pipelines e compilações dbt exige um redimensionamento de infraestrutura:

### A. Gargalos Identificados na Infraestrutura Atual
1. **CPU**: A compilação do dbt Core em contêineres temporários concorre diretamente com as execuções de workers do Airflow e as threads do Spark local.
2. **Memória (RAM)**: Cada executor Spark consome entre 1GB a 2GB, e cada container transiente dbt consome 512MB. Com 5 a 10 desenvolvedores de uma mesma empresa ativos no dbt, o servidor central pode sofrer exaustão de memória.
3. **Escrita/Leitura em Disco (IOPS)**: O processamento de dados Delta/Parquet via DuckDB/Spark no MinIO gera alto volume de I/O local nos volumes montados do Docker.

### B. Especificação Recomendada para a VM

| Recurso | VM Atual (Mono-usuário) | VM Recomendada (Suporte Corporativo MVP) |
| :--- | :--- | :--- |
| **vCPUs** | 4 vCPUs | **8 a 16 vCPUs** (otimizadas para computação) |
| **Memória RAM** | 8 GB | **16 a 32 GB RAM** (evita falhas de OOM no dbt/Spark) |
| **Armazenamento** | SSD Padrão | **SSD NVMe (Min. 3000 IOPS)** |
| **Rede** | 1 Gbps | **5 a 10 Gbps** (comunicação interna MinIO ➔ Spark ➔ Postgres) |

> [!TIP]
> **Recomendação de Escalar Horizontalmente**: Conforme o MVP ganhe tração, a estratégia ideal de hardware é mover os contêineres transientes dbt, Workers do Airflow e Apache Spark de uma VM central para um cluster Kubernetes gerenciado (como EKS ou GKE). Isso permite que o processamento pesado de dados escale horizontalmente sob demanda, mantendo a VM principal apenas para a aplicação CodeIgniter, PostgreSQL e MySQL.
