analisa o an# 🚀 Guia de Implantação - Teste vs Produção

> **Contexto deste servidor:** aqui rodam **apenas dois ambientes** do projeto `datalake-air-flow-teste`:
> - **TEST** (homologação) → use `.env-test` (portas 29xxx, `ENV_SUFFIX=test`)
> - **PRD** (produção) → use `.env-prd` (portas 28xxx, `ENV_SUFFIX=` vazio)
>
> Ambiente DEV não roda nesta máquina. Para alternar TEST/PRD basta copiar o `.env` correto e subir o `docker-compose`.

## 📋 Pré-requisitos
- Docker e Docker Compose instalados
- Certificados SSL (apenas produção)
- Acesso SSH ao servidor (apenas produção)

---

docker-compose up -d
## 🌐 PRODUÇÃO (Servidor Remoto)

### 1. Configurar variáveis de ambiente

```bash
# No servidor, copiar arquivo de produção
cp .env-prd .env
```

**Editar `.env` (raiz do projeto):**
```env
# IMPORTANTE: Sufixo para nomes de containers
ENV_SUFFIX=prd

MYSQL_ROOT_PASSWORD=SenhaSeguraProducao123!
MYSQL_DATABASE=lista_revisao2

# Portas altas (mesmas do dev para consistência)
NGINX_PORT_HTTP=28080
NGINX_PORT_HTTPS=28443
CODEIGNITER_PORT=28088
MYSQL_PORT=23306
AIRFLOW_WEBSERVER_PORT=28083
# ... (demais portas)

# Nginx com SSL
NGINX_SSL_ENABLED=true
NGINX_SERVER_NAME=myflow.estudotabela.com.br
NGINX_SSL_CERT=/etc/letsencrypt/live/myflow.estudotabela.com.br/fullchain.pem
NGINX_SSL_KEY=/etc/letsencrypt/live/myflow.estudotabela.com.br/privkey.pem
```

### 2. Configurar .env do CodeIgniter

```bash
cd src/codeigniter-app
cp ".env prd" .env
```

**Editar `src/codeigniter-app/.env`:**
```env
CI_ENVIRONMENT=production
app_baseURL='https://myflow.estudotabela.com.br:28443/'

database.default.hostname=mysql
database.default.username=root
database.default.password=SenhaSeguraProducao123!
database.default.database=lista_revisao2

smtp_host=mail.estudotabela.com.br
smtp_username=admin@estudotabela.com.br
smtp_password=SuaSenhaEmail

AIRFLOW_HOST=airflow-webserver
AIRFLOW_PORT=8080
```

---

## 🧪 TESTE / HOMOLOGAÇÃO (Mesmo servidor)

### 1. Configurar variáveis de ambiente

```bash
cp .env-test .env
```

**Verificar `.env` (raiz do projeto):**
```env
ENV_SUFFIX=test

# Portas 29xxx para não conflitar com PRD
NGINX_PORT_HTTP=29080
NGINX_PORT_HTTPS=29443
AIRFLOW_WEBSERVER_PORT=29083
CODEIGNITER_PORT=29088
MYSQL_PORT=24306
MINIO_API_PORT=30000
MINIO_CONSOLE_PORT=30001

# SSL desabilitado em teste (NGINX_SSL_ENABLED=false)
NGINX_SERVER_NAME=46.224.156.251
```

### 2. Subir os containers de TESTE

```bash
docker-compose down
docker-compose up -d
```

### 3. Acessar serviços (teste)
- WebApp: http://46.224.156.251:29088
- Airflow: http://46.224.156.251:29083
- MinIO Console: http://46.224.156.251:30001
- MySQL: 46.224.156.251:24306

---

### 3. Habilitar SSL no Nginx (IMPORTANTE!)

**Editar `docker-compose.yml`:**

Descomentar a linha do template SSL:

```yaml
  nginx:
    # ...
    volumes:
      - ./nginx/myflow.conf.template:/etc/nginx/templates/myflow.conf.template:ro
      # SSL template (descomentar para produção)
      - ./nginx/myflow-ssl.conf.template:/etc/nginx/templates/myflow-ssl.conf.template:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
```

### 4. Gerar certificado SSL (se ainda não tiver)

```bash
sudo certbot certonly --standalone \
  -d myflow.estudotabela.com.br \
  -d airflow.estudotabela.com.br
```

### 5. Subir os containers

```bash
cd /home/cblna123456/datalake-air-flow
docker-compose down
docker-compose up -d --build
```

### 6. Criar Airflow Connections (IMPORTANTE!)

**Em ambiente NOVO do zero, as connections do Airflow não existem e precisam ser criadas manualmente:**

```bash
# Acessar o container Airflow de produção
docker exec airflow-webserver bash

# 1. Criar connection MinIO
airflow connections add minio_conn \
  --conn-type aws \
  --conn-login admin \
  --conn-password admin123 \
  --conn-extra '{"endpoint_url":"http://minio:9000"}'

# 2. Criar connection MySQL (dados de DAG)
airflow connections add mysql_dag_metadata \
  --conn-type mysql \
  --conn-host mysql \
  --conn-login root \
  --conn-password SenhaSeguraProducao123! \
  --conn-schema lista_revisao2 \
  --conn-port 3306

# 3. Sair do container
exit

# Verificar se as connections foram criadas
docker exec airflow-webserver airflow connections list | grep -E "minio|mysql_dag"
```

**Resultado esperado:**
```
minio_conn      | aws       | admin   | admin123      | http://minio:9000
mysql_dag_metadata | mysql | root    | *** | lista_revisao2
```

### 7. Verificar logs

```bash
# Verificar se todos os containers subiram
docker ps

# Ver logs do Nginx
docker logs nginx-gateway

# Ver logs do CodeIgniter
docker logs codeigniter-app

# Ver logs do Airflow
docker logs airflow-webserver
```

### 8. Acessar serviços

- **WebApp**: https://myflow.estudotabela.com.br:28443
- **Airflow**: http://airflow.estudotabela.com.br:28083
- **MinIO Console**: http://IP_SERVIDOR:29001

---

## 🔄 Mudando entre ambientes

### De Dev para Produção:

```bash
# 1. Trocar .env na raiz
cp .env-prd .env

# 2. Trocar .env do CodeIgniter
cd src/codeigniter-app
cp ".env prd" .env

# 3. Habilitar SSL no docker-compose.yml (descomentar linha)
# 4. Rebuild
cd ../..
docker-compose down
docker-compose up -d --build
```

### De Produção para Dev:

```bash
# 1. Trocar .env na raiz
git checkout .env  # ou cp .env.example .env

# 2. Trocar .env do CodeIgniter
cd src/codeigniter-app
git checkout .env  # ou usar o arquivo original

# 3. Comentar SSL no docker-compose.yml
# 4. Rebuild
cd ../..
docker-compose down
docker-compose up -d
```

---

## 🧪 TESTE/STAGING (Parallel Instance)

### 1. Clonar para nova pasta com sufixo -test

```bash
# No servidor, criar nova instância
cd /home/seu-usuario
cp -r datalake-air-flow datalake-air-flow-test

# Ou clonar do Git em nova branch
cd datalake-air-flow-test
git checkout evo-expire  # branch de teste
```

### 2. Configurar variáveis de ambiente para TESTE

```bash
# Na raiz do projeto de teste
cd /home/seu-usuario/datalake-air-flow-test

# Usar arquivo de configuração de teste
cp .env-test .env
```

**Verificar no `.env` (portas 29xxx):**
```env
# IMPORTANTE: Sufixo para nomes de containers (evita conflito com produção)
ENV_SUFFIX=test

MYSQL_ROOT_PASSWORD=YM11rMrT32xH0E6N
MYSQL_DATABASE=lista_revisao2_test

# Portas diferentes de produção (29xxx em vez de 28xxx)
NGINX_PORT_HTTP=29080
NGINX_PORT_HTTPS=29443
CODEIGNITER_PORT=29088
MYSQL_PORT=24306
AIRFLOW_WEBSERVER_PORT=29083
# ... (todas portas 29xxx para evitar conflitos)

# Nginx sem SSL
NGINX_SSL_ENABLED=false
NGINX_SERVER_NAME=localhost-test
```

### 3. Configurar .env do CodeIgniter (TESTE)

```bash
cd src/codeigniter-app
cp .env-test .env
```

**Verificar `src/codeigniter-app/.env`:**
```env
CI_ENVIRONMENT=development
# app_baseURL construída automaticamente

database.default.hostname=mysql
database.default.username=root
database.default.password=YM11rMrT32xH0E6N
database.default.database=lista_revisao2_test  ← DATABASE DE TESTE

MINIO_BUCKET_RAW=data-lake-raw-test  ← BUCKET DE TESTE

AIRFLOW_HOST=airflow-webserver
AIRFLOW_PORT=8080
```

### 4. Subir stack de teste (isolada)

```bash
cd /home/seu-usuario/datalake-air-flow-test
docker-compose up -d --build
```
### 5.1. Criar Airflow Connections para TESTE

**Em ambiente NOVO do zero, as connections do Airflow não existem e precisam ser criadas manualmente:**

```bash
# Acessar o container Airflow de teste
docker exec airflow-webserver-test bash

# 1. Criar connection MinIO
airflow connections add minio_conn \
  --conn-type aws \
  --conn-login admin \
  --conn-password admin123 \
  --conn-extra '{"endpoint_url":"http://minio:9000"}'

# 2. Criar connection MySQL (dados de DAG - NOTE: usar database de TESTE)
airflow connections add mysql_dag_metadata \
  --conn-type mysql \
  --conn-host mysql \
  --conn-login root \
  --conn-password YM11rMrT32xH0E6N \
  --conn-schema lista_revisao2_test \
  --conn-port 3306

# 3. Sair do container
exit

# Verificar se as connections foram criadas
docker exec airflow-webserver-test airflow connections list | grep -E "minio|mysql_dag"
```

**Resultado esperado para TESTE:**
```
minio_conn      | aws       | admin   | admin123      | http://minio:9000
mysql_dag_metadata | mysql | root    | *** | lista_revisao2_test
```

⚠️ **IMPORTANTE:** Note que a database para TESTE é `lista_revisao2_test` (com sufixo `_test`), enquanto em produção seria `lista_revisao2`.
### 5. Acessar serviços de TESTE

- **WebApp Teste**: http://localhost:29088
- **Airflow Teste**: http://localhost:29083
- **MinIO Console Teste**: http://localhost:30001
- **MySQL Teste**: localhost:24306

### 6. Verificar isolamento

```bash
# Ambas instâncias rodando simultaneamente
docker ps | grep -E "(datalake-air-flow|test)"

# Resultado esperado:
# datalake-air-flow-codeigniter-app        (produção - porta 28088)
# datalake-air-flow-test-codeigniter-app   (teste - porta 29088)
# datalake-air-flow-mysql                  (produção - porta 23306)
# datalake-air-flow-test-mysql             (teste - porta 24306)
```

---

## 🔄 Workflow Multi-Instância

### Testar mudanças em branch específica

```bash
# 1. Entrar no diretório de teste
cd datalake-air-flow-test

# 2. Fetch e checkout da branch de teste
git fetch origin
git checkout evo-expire

# 3. Reconstruir containers (mesmas portas, dados isolados)
docker-compose down
docker-compose up -d --build

# 4. Verificar logs
docker logs datalake-air-flow-test-codeigniter-app --tail 50

# 5. Acessar em: http://localhost:29088
```

### Sincronizar produçã com teste (após validar)

```bash
# 1. Validar mudanças em teste (http://localhost:29088)
# 2. Merge da branch no Git
git checkout main
git merge evo-expire

# 3. Voltar para produção e atualizar
cd datalake-air-flow
docker-compose down
git pull origin main
docker-compose up -d --build
```

### Rodar múltiplas branches simultaneamente

```bash
# Produção: main branch, portas 28xxx
cd /home/seu-usuario/datalake-air-flow
git checkout main
cp .env-prd .env

# Teste 1: evo-expire, portas 29xxx
cd /home/seu-usuario/datalake-air-flow-test
git checkout evo-expire
cp .env-test .env

# Teste 2: feature-X, portas 30xxx (criar .env-test2 com portas 30xxx)
cd /home/seu-usuario/datalake-air-flow-test2
git checkout feature-X
cp .env-test2 .env

# Todas rodando isoladamente!
docker ps | wc -l  # Muitos containers
```

---

## ✅ Checklist de TESTE

- [ ] Pasta `-test` criada com sucesso
- [ ] `.env-test` e `src/codeigniter-app/.env-test` copiados
- [ ] Branch de teste feito checkout
- [ ] Portas 29xxx confirmadas no `.env-test`
- [ ] `docker-compose up -d --build` executado
- [ ] Todos containers rodando (`docker ps`)
- [ ] Acesso via http://localhost:29088 funcionando
- [ ] MySQL de teste isolado (porta 24306)
- [ ] Airflow de teste isolado (porta 29083)
- [ ] Sem conflitos com produção (portas 28xxx)

---

## 🏷️ Sistema de Sufixos de Ambiente (ENV_SUFFIX)

### O que é?

O `ENV_SUFFIX` é uma variável que **adiciona automaticamente um sufixo aos nomes de todos os containers Docker**. Isso permite rodar **múltiplos ambientes simultaneamente no mesmo servidor sem conflitos**.

### Como funciona?

**No `.env` da raiz:**
```env
ENV_SUFFIX=test
```

**No `docker-compose.yml`:**
```yaml
services:
  mysql:
    container_name: mysql-${ENV_SUFFIX:-dev}
    # Resultado: mysql-test

  nginx:
    container_name: nginx-gateway-${ENV_SUFFIX:-dev}
    # Resultado: nginx-gateway-test

  codeigniter-app:
    container_name: codeigniter-app-${ENV_SUFFIX:-dev}
    # Resultado: codeigniter-app-test
```

### Valores recomendados:

| Ambiente | ENV_SUFFIX | Containers | Uso |
|----------|------------|------------|-----|
| **Desenvolvimento** | `dev` | `mysql-dev`, `nginx-gateway-dev` | Ambiente local padrão |
| **Teste/Staging** | `test` | `mysql-test`, `nginx-gateway-test` | Testes paralelos à produção |
| **Produção** | `prd` | `mysql-prd`, `nginx-gateway-prd` | Ambiente de produção |
| **Feature Branch** | `feature1` | `mysql-feature1`, `nginx-gateway-feature1` | Teste de feature específica |

### Benefícios:

✅ **Isola ambientes completamente** - Cada ambiente tem seus próprios containers  
✅ **Evita conflitos de nomes** - Containers com nomes únicos  
✅ **Execução simultânea** - Prod + Teste + Dev ao mesmo tempo  
✅ **Fácil identificação** - `docker ps` mostra claramente qual ambiente  
✅ **Valor padrão seguro** - Se omitido, usa `dev` automaticamente  

### Exemplo prático:

```bash
# Terminal 1: Subir produção
cd /root/datalake-air-flow-delta
echo "ENV_SUFFIX=prd" >> .env
docker-compose up -d
# Containers: mysql-prd, nginx-gateway-prd, codeigniter-app-prd

# Terminal 2: Subir teste (simultâneo!)
cd /root/datalake-air-flow-teste
echo "ENV_SUFFIX=test" >> .env
docker-compose up -d
# Containers: mysql-test, nginx-gateway-test, codeigniter-app-test

# Verificar ambos rodando
docker ps --format "table {{.Names}}\t{{.Ports}}"
# mysql-prd         0.0.0.0:23306->3306/tcp
# mysql-test        0.0.0.0:24306->3306/tcp
# codeigniter-app-prd    0.0.0.0:28088->80/tcp
# codeigniter-app-test   0.0.0.0:29088->80/tcp
```

### ⚠️ IMPORTANTE:

1. **Sempre defina ENV_SUFFIX no `.env`** antes de subir os containers
2. **Use sufixos diferentes para cada ambiente** (nunca repita)
3. **Mantenha consistência** - não mude o sufixo após criar os containers
4. **Documente seus sufixos** - registre qual pasta usa qual sufixo

---

## 📊 Tabela de Portas por Ambiente

| Serviço | Dev (padrão) | Teste (29xxx) | Produção (28xxx) |
|---------|---|---|---|
| Nginx HTTP | 8090 | 29080 | 28080 |
| Nginx HTTPS | 8443 | 29443 | 28443 |
| CodeIgniter | 8088 | 29088 | 28088 |
| Airflow | 8082 | 29083 | 28083 |
| Airflow Scheduler | 5678 | 26678 | 25678 |
| MySQL | 3306 | 24306 | 23306 |
| PostgreSQL | 5432 | 26432 | 25432 |
| PostgreSQL BI | 5433 | 26433 | 25433 |
| Redis | 6379 | 26380 | 26379 |
| MinIO API | 9000 | 30000 | 29000 |
| MinIO Console | 9001 | 30001 | 29001 |
| DuckDB API | 5000 | 26000 | 25000 |
| XDebug | 9003 | 30003 | 29003 |
| Spark Master RPC | 7077 | 27078 | 27077 |
| Spark Master Web | 8080 | 29081 | 28081 |
| Spark Worker | 8081 | 29082 | 28082 |
| Atlas | 21000 | 32000 | 31000 |
| Jupyter | 8888 | 29888 | 28888 |
| Spark UI | 4040 | 25040 | 24040 |

**Como usar:**
- **Dev** (`.env`): Portas padrão - ideal para localhost com poucos conflitos
- **Teste** (`.env-test`): Portas 29xxx - instância paralela para testar features
- **Produção** (`.env-prd`): Portas 28xxx - ambiente de produção seguro

---

## 🗂️ Instâncias de Banco por Ambiente

Resumo dos nomes/hosts das instâncias de banco de dados por ambiente. Os serviços se comunicam pelo nome do serviço na rede interna Docker (`airflow_net`), independentemente do `container_name` com sufixo.

### Teste (ENV_SUFFIX=test)

- PostgreSQL (Airflow metadata)
  - Host interno: `postgres`
  - Container: `postgres-test`
  - Database: `airflow`
  - Usuário/Senha: `airflow` / `airflow`
  - Porta externa: 26432 (variável `POSTGRES_PORT`)

- PostgreSQL BI (Power BI)
  - Host interno: `postgres-bi`
  - Container: `postgres-bi-test`
  - Database: `datalake_bi` ou `northwind`
  - Usuário/Senha: `pbi_user` / `pbi_password`
  - Porta externa: 26433 (variável `POSTGRES_BI_PORT`)

- MySQL (app/metadados)
  - Host interno: `mysql`
  - Container: `mysql-test`
  - Database: `lista_revisao2_test`
  - Usuário/Senha: `root` / `YM11rMrT32xH0E6N`
  - Porta externa: 24306 (variável `MYSQL_PORT`)

### Desenvolvimento (ENV_SUFFIX=dev)

- PostgreSQL (Airflow metadata)
  - Host interno: `postgres`
  - Container: `postgres-dev`
  - Database: `airflow`
  - Usuário/Senha: `airflow` / `airflow`
  - Porta externa: 25432 (ajuste conforme seu `.env`)

- PostgreSQL BI (Power BI)
  - Host interno: `postgres-bi`
  - Container: `postgres-bi-dev`
  - Database: `datalake_bi` ou `northwind`
  - Usuário/Senha: `pbi_user` / `pbi_password`
  - Porta externa: 25433 (ajuste conforme seu `.env`)

- MySQL (app/metadados)
  - Host interno: `mysql`
  - Container: `mysql-dev`
  - Database: `lista_revisao2`
  - Usuário/Senha: `root` / `root`
  - Porta externa: 23306 (ajuste conforme seu `.env`)

### Produção (ENV_SUFFIX=prd)

- PostgreSQL (Airflow metadata)
  - Host interno: `postgres`
  - Container: `postgres-prd`
  - Database: `airflow`
  - Usuário/Senha: `airflow` / senha forte definida em produção
  - Porta externa: 25432 (ajuste conforme seu `.env-prd`)

- PostgreSQL BI (Power BI)
  - Host interno: `postgres-bi`
  - Container: `postgres-bi-prd`
  - Database: `datalake_bi` ou `northwind`
  - Usuário/Senha: `pbi_user` / senha forte definida em produção
  - Porta externa: 25433 (ajuste conforme seu `.env-prd`)

- MySQL (app/metadados)
  - Host interno: `mysql`
  - Container: `mysql-prd`
  - Database: `lista_revisao2`
  - Usuário/Senha: `root` / senha forte definida em produção
  - Porta externa: 23306 (ajuste conforme seu `.env-prd`)

Notas:
- Dentro da rede Docker, use sempre o host interno (`postgres`, `postgres-bi`, `mysql`).
- Os `container_name` incluem o sufixo do ambiente e ajudam na identificação via `docker ps`.
- As portas externas são configuráveis via `.env` e podem variar; os valores acima seguem o padrão documentado na tabela de portas.


---

## ⚠️ Notas Importantes (Multi-Instância)

1. **Isolamento de dados:**
   - Cada instância tem seu próprio MySQL (portas diferentes)
   - Cada instância tem seu próprio bucket MinIO
   - Volumes Docker são separados (sufixo `-test`)

2. **Nomes de containers automáticos:**
   - Produção: `datalake-air-flow-mysql`, `datalake-air-flow-airflow-webserver`
   - Teste: `datalake-air-flow-test-mysql`, `datalake-air-flow-test-airflow-webserver`
   - Docker compõe automaticamente com nome da pasta

3. **Sem conflitos de porta:**
   - Produção usa 28xxx
   - Teste usa 29xxx
   - Possível adicionar 30xxx para outro teste paralelo

4. **Senhas diferentes:**
   - Produção: senha real forte
   - Teste: pode usar senha de teste (menos crítico)

5. **Databases separados:**
   - Produção: `lista_revisao2`
   - Teste: `lista_revisao2_test`
   - Não se misturam!

6. **Buckets MinIO separados:**
   - Produção: `data-lake-raw`
   - Teste: `data-lake-raw-test`
- [ ] `.env` na raiz com portas altas e SSL=false
- [ ] `src/codeigniter-app/.env` com baseURL localhost
- [ ] SSL template **comentado** no docker-compose.yml
- [ ] `docker-compose up -d`

### Produção:
- [ ] `.env` na raiz com credenciais de produção
- [ ] `src/codeigniter-app/.env` com baseURL do domínio
- [ ] Certificados SSL válidos em `/etc/letsencrypt`
- [ ] SSL template **descomentado** no docker-compose.yml
- [ ] Firewall liberando portas 28080, 28443, 28083
- [ ] `docker-compose up -d --build`
- [ ] Testar acesso HTTPS

---

---

## 🔐 Segurança: Git e Merge entre Branches

### ⚠️ NUNCA versione arquivos de configuração sensível!

Esses arquivos **DEVEM estar no `.gitignore`** e **NUNCA fazer merge automático**:

```gitignore
# Raiz do projeto
.env
.env.local
.env.*.local

# CodeIgniter App
src/codeigniter-app/.env
src/codeigniter-app/.env.local

# Dados sensíveis
.env.production
.env-prd
secrets/
```

### Por quê?

1. **Proteção de senhas** - Credenciais de produção nunca no Git
2. **Dados isolados** - Cada ambiente tem sua configuração
3. **Merge seguro** - Não sobrescreve configurações ao fazer merge
4. **Variáveis de ambiente** - Cada servidor tem a sua

### ✅ Solução: Use Templates

**Crie versões template para o Git:**

```bash
# Na raiz do projeto
cp .env .env.template
cp .env-prd .env-prd.template
cp .env-test .env-test.template
```

**Arquivo `.env.template` (exemplo):**
```env
# IMPORTANTE: Copie este arquivo para .env e preencha com seus valores

# AMBIENTE (Sufixo para nomes de containers)
ENV_SUFFIX=dev

# VARIÁVEIS DO DOCKER (MySQL)
MYSQL_ROOT_PASSWORD=ALTERAR_PARA_SUA_SENHA
MYSQL_DATABASE=lista_revisao2

# ... resto das variáveis com valores PADRÃO/EXEMPLO
```

**No `.gitignore`:**
```
.env*
!.env.template
!.env-prd.template
!.env-test.template
```

### 📋 Workflow seguro para Merge

**Antes de fazer merge de outra branch para produção:**

```bash
# 1. Fazer merge do docker-compose.yml (seguro)
git merge evo-expire -- docker-compose.yml

# 2. NÃO fazer merge automático de .env ou src/codeigniter-app/.env
git checkout HEAD -- .env src/codeigniter-app/.env

# 3. Atualizar manualmente APENAS variáveis de funcionalidade
# Exemplo: se a PR adicionou nova variável FEATURE_X=true
nano .env  # Adicione manualmente
nano src/codeigniter-app/.env

# 4. Verificar diferenças
git diff --no-pager .env | head -20

# 5. Commit (sem sobrescrever)
git add docker-compose.yml
git commit -m "Merge docker-compose.yml de evo-expire (sem sobrescrever .env)"
```

### 🛡️ Proteger produção no Git

**Adicione ao `.gitattributes`:**
```
.env merge=union
.env-prd merge=union
src/codeigniter-app/.env merge=union
```

Isso força merge manual em vez de automático para esses arquivos.

### 📝 Documentação por Ambiente

**Criar arquivo `SETUP_ENV.md` para cada admin:**

```markdown
# Setup de Ambiente

## Produção
1. Copie `.env-prd.template` para `.env`
2. Atualize: MYSQL_ROOT_PASSWORD, NGINX_SERVER_NAME, etc.
3. Copie `src/codeigniter-app/.env-prd.template` para `.env`
4. Nunca faça commit desses arquivos!

## Teste
1. Copie `.env-test.template` para `.env`
2. Deixe ENV_SUFFIX=test (NÃO MUDE!)
3. Copie `src/codeigniter-app/.env-test.template` para `.env`
4. Nunca faça commit desses arquivos!
```

### ✅ Checklist antes de Merge

- [ ] `.env` está em `.gitignore`
- [ ] `src/codeigniter-app/.env` está em `.gitignore`
- [ ] Templates (`.env.template`, `.env-prd.template`) estão NO repositório
- [ ] Não há senhas reais nos arquivos versionados
- [ ] Merge de `docker-compose.yml` foi manual/cuidadoso
- [ ] `.env` local foi preservado (não sobrescrito)
- [ ] Dados MySQL/PostgreSQL continuam intactos (volumes separados)

---

---

## 🔌 Airflow Connections (Setup Completo)

### O que são Airflow Connections?

Connections são **credenciais e endereços** que os DAGs usam para conectar em serviços externos:
- MinIO (S3) - para leitura/escrita de dados
- MySQL - para acessar banco de dados de metadados
- PostgreSQL, APIs, etc.

**Em ambiente novo, precisam ser criadas manualmente (não vêm pré-configuradas).**

### Criar todas as Connections (Produção)

```bash
# Entrar no container Airflow
docker exec -it airflow-webserver bash

# 1. MinIO Connection (S3-compatible)
airflow connections add minio_conn \
  --conn-type aws \
  --conn-login admin \
  --conn-password admin123 \
  --conn-extra '{"endpoint_url":"http://minio:9000"}'

# 2. MySQL Metadata Connection
airflow connections add mysql_dag_metadata \
  --conn-type mysql \
  --conn-host mysql \
  --conn-login root \
  --conn-password SenhaSeguraProducao123! \
  --conn-schema lista_revisao2 \
  --conn-port 3306

# 3. Listar todas as connections
airflow connections list

# 4. Sair
exit
```

### Criar todas as Connections (Teste)

```bash
# Entrar no container Airflow de teste
docker exec -it airflow-webserver-test bash

# 1. MinIO Connection
airflow connections add minio_conn \
  --conn-type aws \
  --conn-login admin \
  --conn-password admin123 \
  --conn-extra '{"endpoint_url":"http://minio:9000"}'

# 2. MySQL Metadata Connection (NOTE: database de TESTE)
airflow connections add mysql_dag_metadata \
  --conn-type mysql \
  --conn-host mysql \
  --conn-login root \
  --conn-password YM11rMrT32xH0E6N \
  --conn-schema lista_revisao2_test \
  --conn-port 3306

# 3. Listar
airflow connections list

# 4. Sair
exit
```

### Usar Connections nos DAGs

**Exemplo em um DAG Python:**

```python
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
from airflow.providers.mysql.hooks.mysql import MySqlHook

# Usar MinIO via connection
s3_hook = S3Hook(aws_conn_id='minio_conn')
files = s3_hook.list_keys(bucket_name='data-lake-raw')

# Usar MySQL via connection
mysql_hook = MySqlHook(mysql_conn_id='mysql_dag_metadata')
result = mysql_hook.get_records('SELECT * FROM alguma_tabela')
```

### Verificar Connection Details

```bash
# Ver detalhes de uma connection específica
docker exec airflow-webserver airflow connections get minio_conn
docker exec airflow-webserver airflow connections get mysql_dag_metadata

# Atualizar uma connection (se precisar mudar senha)
docker exec airflow-webserver airflow connections delete minio_conn
docker exec airflow-webserver airflow connections add minio_conn \
  --conn-type aws \
  --conn-login admin \
  --conn-password NOVA_SENHA \
  --conn-extra '{"endpoint_url":"http://minio:9000"}'
```

---

## 🐛 Troubleshooting

### Nginx não inicia:
```bash
# Ver logs
docker logs nginx-gateway

# Erro de certificado SSL? Verificar se arquivo existe:
ls -la /etc/letsencrypt/live/myflow.estudotabela.com.br/
```

### CodeIgniter não conecta ao Airflow:
```bash
# Testar conexão
docker exec codeigniter-app curl http://airflow-webserver:8080/api/v1/health

# Verificar variável AIRFLOW_HOST e AIRFLOW_PORT no docker-compose.yml
grep AIRFLOW docker-compose.yml | grep environment -A 10

# Verificar no container
docker exec codeigniter-app-test printenv | grep AIRFLOW
```

### Airflow DAG não consegue acessar MinIO/MySQL:

```bash
# Verificar se as connections existem
docker exec airflow-webserver airflow connections list | grep -E "minio|mysql"

# Se não existir, criar manualmente (ver seção "Airflow Connections" acima)

# Testar conexão MinIO manualmente
docker exec airflow-webserver python -c "
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
hook = S3Hook(aws_conn_id='minio_conn')
buckets = hook.list_buckets()
print(f'Buckets: {buckets}')
"

# Testar conexão MySQL manualmente
docker exec airflow-webserver python -c "
from airflow.providers.mysql.hooks.mysql import MySqlHook
hook = MySqlHook(mysql_conn_id='mysql_dag_metadata')
records = hook.get_records('SELECT 1')
print(f'MySQL OK: {records}')
"
```

### MySQL não conecta:
```bash
# Verificar senha no .env
grep MYSQL_ROOT_PASSWORD .env

# Testar conexão
docker exec -it mysql mysql -uroot -p

# Do container CodeIgniter/Airflow
docker exec codeigniter-app mysql -h mysql -u root -p lista_revisao2_test -e "SELECT 1;"
```

---

## 📝 Notas Importantes

1. **Portas internas vs externas:**
   - CodeIgniter escuta na porta **80 DENTRO do container**
   - Docker mapeia para **28088 FORA** (localhost:28088)
   - Nginx faz proxy para `codeigniter-app:80` (rede interna)

2. **Nginx templates:**
   - Em **dev**: Apenas `myflow.conf.template` (HTTP)
   - Em **prod**: `myflow.conf.template` + `myflow-ssl.conf.template` (HTTP + HTTPS)

3. **Senhas seguras em produção:**
   - Nunca usar `root` / `admin123` em produção
   - Gerar senhas fortes: `openssl rand -base64 32`

4. **Backup antes de deploy:**
   ```bash
   ./backup-mysql.sh
   docker-compose down
   tar -czf backup-$(date +%Y%m%d).tar.gz .
   ```
