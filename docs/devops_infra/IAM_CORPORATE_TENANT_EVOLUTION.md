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

## ⚡ 5. Pré-requisitos de Evolução de Hardware e Avaliação do Host Atual

A VM host atual que hospeda a stack MyDataFlow executa um ecossistema denso de serviços (Nginx, Spark Master/Worker, PostgreSQL Airflow, PostgreSQL BI, Redis, MySQL, Airflow, MinIO, DuckDB API e Metabase) em um único nó. A evolução para a versão corporativa com multi-usuários ativos rodando pipelines e contêineres dbt exige avaliarmos a capacidade do servidor:

### A. Gargalos de Recursos com Múltiplos Usuários
1. **CPU**: A compilação concorrente do dbt Core em contêineres temporários concorre com o processamento do Spark local.
2. **Memória (RAM)**: Com 5 a 10 desenvolvedores de uma mesma empresa ativos no dbt, o consumo de RAM dos contêineres transientes dbt (512MB cada) e executores Spark local (1GB a 2GB cada) pode gerar picos de uso.
3. **Escrita em Disco (IOPS)**: O processamento de dados Parquet/Delta no MinIO local gera picos de I/O de disco.

### B. Avaliação da VM Host Atual vs. Requisitos do MVP e Escala

Com base nas especificações do seu host atual, avaliamos o cenário de implantação do MVP:

| Recurso | VM Host Atual | Capacidade para MVP | Próximo Upgrade (Escalar Pós-MVP) |
| :--- | :--- | :--- | :--- |
| **vCPUs** | **8 vCPUs** | ✅ **Suficiente**. Ideal para rodar o Core da stack e até 3 execuções simultâneas de dbt/Spark. | **16 vCPUs** (para suportar múltiplos tenants corporativos simultâneos). |
| **Memória RAM** | **16 GB** | ✅ **Suficiente**. Mantém os containers básicos ativos e fornece margem para sandbox de desenvolvimento do MVP. | **32 GB RAM** (evita falhas de Out-Of-Memory com concorrência alta). |
| **Armazenamento**| **160 GB SSD** | ✅ **Suficiente**. Espaço amplo para o início da operação analítica. | **500 GB+ NVMe** (conforme volumetria do Data Lake corporativo crescer). |
| **Rede/Tráfego** | **20 TB Out** (Uso: ~14 TB) | ✅ **Suficiente**. Tráfego confortável para ingestão e consumo do Metabase. | Monitorar uso. |

> [!TIP]
> **Veredito**: A sua VM atual com **8 vCPUs e 16 GB de RAM** é perfeitamente adequada para hospedar o desenvolvimento e o lançamento inicial (MVP) da versão corporativa de forma estável. Para crescer além do MVP, a recomendação é a migração dos contêineres transientes do dbt e do Spark para um cluster Kubernetes (ex: EKS ou GKE), desacoplando a carga pesada de processamento analítico do host de aplicação web.

---

## 🛡️ 6. Estratégia de Ambientes DTH (Desenvolvimento, Teste, Homologação) e Mitigação de Riscos

Concordamos plenamente com a preocupação. Realizar uma evolução de arquitetura deste porte (transição de modelo mono-inquilino para corporativo + implantação de IAM/SSO) diretamente em um único ambiente de produção envolve riscos críticos, como indisponibilidade da plataforma para os alunos ativos, corrupção de schemas analíticos e vazamento de permissões.

Para mitigar esses riscos, é indispensável adotar um pipeline estruturado de ambientes **DTH (Dev ➔ Test ➔ Homolog ➔ Prod)**:

```mermaid
graph LR
    Dev[1. Dev Local / VM Dev] -->|Git PR| Test[2. Test / VM QA]
    Test -->|Aprovação QA| Staging[3. Staging / Homolog]
    Staging -->|Promover / Release| Prod[4. Produção]
```

### A. Papel de cada Ambiente no Pipeline

1. **Desenvolvimento (Dev - Local / VM Isolada)**:
   * **Objetivo**: Implementação inicial das migrations, ajustes nas classes `SessionHelper` e `MetabaseHelper`, e criação do driver SSO.
   * **Isolamento**: Utilização do Docker Compose local. Nenhuma alteração afeta outros desenvolvedores ou usuários reais.
2. **Testes / QA (VM de Teste dedicada)**:
   * **Objetivo**: Execução de baterias de testes automatizados unitários e de integração (Python, PHPUnit, validação de integridade de schemas).
   * **Isolamento**: Limpeza de banco e reinstalação a cada bateria de testes.
3. **Homologação / Staging (Réplica da Produção)**:
   * **Objetivo**: Validar a coexistência do modelo mono-inquilino com a versão corporativa.
   * **Estratégia de Risco Zero**: Esta VM deve conter uma cópia anonimizada dos dados da produção atual. É aqui que o script de migração para o **Tenant Polimórfico** será homologado.
   * **Testes Críticos**: Testes de concorrência com múltiplos usuários corporativos simulados e auditoria fina de logs no Metabase e PostgreSQL.
4. **Produção (Prod)**:
   * **Objetivo**: Entrega segura para o cliente final.
   * **Regra de Deploy**: Apenas código exaustivamente testado e aprovado em Staging é deployado em produção.

### B. Protocolo de Implantação e Plano de Rollback

No dia da migração em Produção, o seguinte protocolo de mitigação deve ser seguido rigorosamente:

1. **Janela de Manutenção**: Agendar período de menor uso do sistema.
2. **Backup Completo e Físico (Ponto de Restauração)**:
   * Snapshot da VM de produção.
   * Dump do banco MySQL (`backup.sql`) e PostgreSQL (`datalake_bi`).
   * Cópia de segurança dos metadados do Airflow.
3. **Aplicação das Migrations em Modo Transacional**:
   * As migrations do banco MySQL que alteram as tabelas `usuarios` e adicionam `tenants` devem rodar sob transações SQL (`START TRANSACTION`), permitindo rollback automático em caso de erro no script.
4. **Deploy por Blue-Green ou Feature Flags**:
   * A lógica de redirecionamento corporativo no CodeIgniter pode ser protegida por uma *Feature Flag* (ex: `enable_corporate_tenant = true`). Em caso de qualquer comportamento anômalo, a flag pode ser desligada instantaneamente via painel admin, retornando 100% dos usuários ao comportamento monousuário legado sem necessidade de novo deploy.
5. **Rollback Rápido**: Caso a feature flag não sane o problema e ocorram falhas catastróficas, o snapshot da VM é restaurado imediatamente.
