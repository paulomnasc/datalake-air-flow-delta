# Plano de Ação: Rotação de Credenciais e Hardening do MinIO

## Objetivos
- Rotacionar `MINIO_ROOT_USER` e `MINIO_ROOT_PASSWORD` sem quebrar Airflow, CodeIgniter e DuckDB API.
- Reduzir superfície de ataque (portas, TLS, permissões, rede).

## Checklist resumido
- Gerar novas credenciais fortes e armazenar em `.env.local` fora do git ou em Docker secrets.
- Atualizar variáveis de ambiente dos serviços dependentes (Airflow, CodeIgniter, duckdb-api, spark/thrift se usado).
- Recriar usuários/policies no MinIO com privilégios mínimos para aplicações.
- Remover exposição externa ou proteger com TLS e autenticação forte.
- Validar conectividade pós-mudança (healthchecks e DAGs de ingestão).

## Passo a passo
1) **Criar segredos**
   - Gerar `MINIO_ROOT_USER` e `MINIO_ROOT_PASSWORD` novos (16+ chars). Guardar em `.env.local` (não versionado) ou secrets do Docker/Swarm/K8s.

2) **Atualizar docker-compose.yml**
   - No serviço `minio`, trocar as variáveis para referenciar o novo segredo (via `.env` externo). Evitar valores literais no compose.
   - Se exposto publicamente, bindar `9000/9001` em `127.0.0.1` ou remover publicação; preferir acesso via VPN/bastion.

3) **Propagar para dependentes**
   - Airflow (webserver/scheduler): ajustar `MINIO_ACCESS_KEY_ID` e `MINIO_SECRET_ACCESS_KEY` nas env vars.
   - CodeIgniter: ajustar `MINIO_ACCESS_KEY_ID` e `MINIO_SECRET_ACCESS_KEY` em `src/codeigniter-app/.env` (ou mover para `.env.local`).
   - duckdb-api: atualizar `MINIO_ACCESS_KEY_ID` e `MINIO_SECRET_ACCESS_KEY` nas env vars.
   - Serviços Spark/Thrift (se habilitados): atualizar chaves S3/MinIO.

4) **Criar usuário de aplicação (least privilege)**
   - No MinIO Console ou `mc admin user add`, criar usuário app (ex.: `app_user`) com senha forte.
   - Criar policy mínima para bucket (ex.: lab01) apenas com `s3:GetObject`, `s3:PutObject`, `s3:ListBucket`. Evitar usar root para apps.
   - Atualizar as aplicações para usarem o usuário app, não o root.

5) **TLS e rede**
   - Preferir terminar TLS no próprio MinIO com cert válido ou colocar atrás do Nginx/Traefik com TLS e autenticação (Basic/OIDC).
   - Isolar MinIO em rede interna (ex.: `storage_net`) e manter acesso externo apenas via proxy seguro.

6) **Recriar containers**
   - `docker compose down && docker compose up -d --build` após atualizar envs/secrets.
   - Limpar credenciais antigas em variáveis de ambiente e arquivos.

7) **Verificações pós-mudança**
   - Airflow: rodar healthcheck e uma DAG que lê/escreve no bucket.
   - CodeIgniter: upload/listagem via UI.
   - duckdb-api: endpoint de health e query simples que lê do bucket.
   - MinIO Console: confirmar que apps estão usando o usuário de aplicação (logs de acesso).

8) **Rotação e governança**
   - Definir periodicidade de rotação das credenciais de aplicação.
   - Auditar usuários e policies no MinIO periodicamente; remover contas não usadas.

## Itens de configuração a tocar
- docker-compose.yml (serviço minio + envs dos dependentes).
- `.env.local` ou secrets (novas chaves root/app).
- src/codeigniter-app/.env (ou equivalente externo) para chaves do app.
- Qualquer script CI/CD que injete variáveis de MinIO.

## Riscos se não seguir
- Credenciais root expostas permitem takeover completo do storage, deleção de buckets e leitura/escrita de dados sensíveis.
- Exposição de 9000/9001 sem TLS facilita interceptação e replay de credenciais.
