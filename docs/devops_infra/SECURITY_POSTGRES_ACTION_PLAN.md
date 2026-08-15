# Plano de Ação: Rotação de Credenciais e Hardening do PostgreSQL

## Objetivos
- Rotacionar senhas dos bancos Postgres (airflow, postgres-bi) e remover credenciais fracas/default.
- Reduzir superfície de ataque (portas, TLS, rede) e limitar privilégios por aplicação.

## Checklist resumido
- Gerar senhas fortes para cada usuário e armazenar em `.env.local` (fora do git) ou Docker secrets.
- Remover publicação externa ou bindar portas em `127.0.0.1` com firewall/VPN.
- Habilitar TLS ou colocar atrás de proxy TLS; desativar auth em texto claro.
- Criar usuários de aplicação com privilégio mínimo; evitar reutilizar `airflow/airflow` e `pbi_user/pbi_password`.
- Validar conectividade pós-rotação (Airflow, BI, migrações).

## Passo a passo
1) **Gerar segredos**
   - Criar senhas fortes distintas para: `airflow`, `pbi_user` (ou novos nomes). Guardar em `.env.local` ou secrets.

2) **Atualizar docker-compose.yml**
   - Trocar `POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB` dos serviços `postgres` e `postgres-bi` para referenciar variáveis externas (não hardcode).
   - Se possível, remover publicação de portas `25432`/`25433`; caso necessário, bindar em `127.0.0.1:25432`/`127.0.0.1:25433` e proteger com firewall/VPN.

3) **Propagar credenciais para dependentes**
   - Airflow: atualizar `AIRFLOW__CORE__SQL_ALCHEMY_CONN` e `AIRFLOW__CELERY__RESULT_BACKEND` com as novas credenciais/hosts.
   - Scripts/DAGs/BI: atualizar strings de conexão usadas para `postgres-bi` (Power BI, etc.).

4) **Criar usuários de aplicação (least privilege)**
   - No `postgres` (Airflow): manter um usuário de serviço com permissões apenas no DB `airflow` (CONNECT, USAGE no schema, CRUD nas tabelas necessárias). Evitar `superuser`.
   - No `postgres-bi`: criar usuário de leitura (para BI) e usuário de carga (se houver ingestões), cada um com privilégios mínimos (SELECT vs INSERT/UPDATE). Evitar `superuser`.

5) **TLS e parâmetros de segurança**
   - Habilitar SSL no PostgreSQL (certs válidos) ou colocar atrás de proxy TLS.
   - Ajustar `pg_hba.conf` para exigir `md5`/`scram-sha-256` e restringir origens (CIDRs internos/VPN).
   - Em Docker, montar configs personalizadas se necessário (volume com `pg_hba.conf`/`postgresql.conf`).

6) **Recriar containers**
   - `docker compose down && docker compose up -d --build` após atualizar envs/secrets.
   - Validar que volumes preservam dados; backups recomendados antes da troca.

7) **Verificações pós-mudança**
   - Airflow UI e scheduler sobem com sucesso (sem falha de conexão DB).
   - Testar DAG que lê/escreve no Postgres.
   - BI (Power BI/DBeaver) conecta usando o novo usuário de leitura.

8) **Rotação e governança**
   - Definir periodicidade de rotação de senhas.
   - Auditar roles e privilégios; remover roles não usadas e revogar grants excessivos.

## Itens de configuração a tocar
- docker-compose.yml (serviços `postgres` e `postgres-bi`).
- `.env.local` ou secrets (novas senhas e strings de conexão).
- Variáveis do Airflow (`SQL_ALCHEMY_CONN`, `RESULT_BACKEND`).
- Strings de conexão de BI e scripts de migração.

## Riscos se não seguir
- Credenciais fracas/default permitem acesso remoto aos bancos, leitura/escrita/extração de dados sensíveis.
- Portas expostas sem TLS permitem interceptação e roubo de credenciais em texto claro.
