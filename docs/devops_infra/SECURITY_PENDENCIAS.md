# Pendências de Segurança da Stack

## Escopo
Avaliação dos serviços com portas expostas e credenciais definidas em docker-compose.yml e src/codeigniter-app/.env.

## Observação sobre MySQL
- A senha está em src/codeigniter-app/.env, mas o usuário é `root`, a porta 23306 está publicada no host e não há restrição de origem nem TLS. O arquivo .env está no repositório, logo qualquer acesso ao código revela a senha. Portanto, o risco crítico permanece.

## Principais exposições
- Postgres (airflow) exposto em 25432 com senha fraca/default e sem TLS.
- Postgres-BI exposto em 25433 com credenciais fracas/default e sem TLS.
- MySQL exposto em 23306 com usuário root habilitado e init scripts executados do host.
- Redis exposto em 26379 sem autenticação ou TLS.
- MinIO e console expostos (9000/9001) com credenciais padrão `admin/admin123`, sem TLS.
- Credenciais de Airflow, Atlas e MinIO em texto claro na composição e herdadas pelos contêineres.
- Todos os serviços compartilham a mesma rede bridge e diversas portas estão publicadas sem firewall/restrição.
- Volumes de dados (Postgres, MySQL, MinIO) e backups não cifrados no host.

## Pendências prioritárias (1 = mais urgente)
1) Rotacionar todas as senhas e mover segredos para Docker secrets ou variáveis só no runtime (.env fora do git). Remover credenciais padrão (`admin/admin123`, `airflow/airflow`, `pbi_user/pbi_password`). Plano de Postgres em [SECURITY_POSTGRES_ACTION_PLAN.md](SECURITY_POSTGRES_ACTION_PLAN.md).
2) Fechar portas externas: não publicar Postgres/BI/MySQL/Redis; quando necessário, bind em 127.0.0.1 e proteger com firewall/VPN.
3) Harden MySQL: desabilitar login remoto do root, criar usuário de app com mínimos priviléggios, revisar e assinar scripts em ./mysql-init.
4) Harden Redis: exigir senha/ACL, bind local, habilitar TLS ou remover exposição.
5) Ativar TLS para bancos e MinIO (ou usar proxy TLS como Nginx/Traefik com autenticação forte). Plano detalhado em [SECURITY_MINIO_ACTION_PLAN.md](SECURITY_MINIO_ACTION_PLAN.md).
6) Segmentar redes Docker (ex.: db_net, app_net) e isolar serviços por profile/ambiente; subir somente o necessário em produção.
7) Proteger dados em repouso: volumes/partições cifradas no host ou criptografia nativa onde disponível; revisar política de backups.

## Ações rápidas sugeridas
- Criar arquivo .env.local (fora do repositório) com segredos reais e referenciar via docker compose.
- Ajustar docker-compose.yml para remover mapeamentos de porta dos bancos/Redis/MinIO ou bindar em localhost.
- Revisar permissões dos volumes e executar varredura de credenciais já expostas para rotação.
- Adicionar autenticação forte e TLS nos pontos públicos (Nginx/MinIO console/API).

## Rastros de origem
- docker-compose.yml
- src/codeigniter-app/.env
