# 🚀 Guia de Implantação - Dev vs Produção

## 📋 Pré-requisitos
- Docker e Docker Compose instalados
- Certificados SSL (apenas produção)
- Acesso SSH ao servidor (apenas produção)

---

## 🏠 DESENVOLVIMENTO (Localhost)

### 1. Configurar variáveis de ambiente

```bash
# Copiar arquivo de configuração de desenvolvimento
cp .env.example .env
```

**Verificar no `.env` (raiz do projeto):**
```env
MYSQL_ROOT_PASSWORD=root
MYSQL_DATABASE=lista_revisao2

# Portas altas para evitar conflitos
NGINX_PORT_HTTP=28080
NGINX_PORT_HTTPS=28443
CODEIGNITER_PORT=28088
MYSQL_PORT=23306
AIRFLOW_WEBSERVER_PORT=28083
# ... (demais portas)

# Nginx sem SSL
NGINX_SSL_ENABLED=false
NGINX_SERVER_NAME=localhost
```

### 2. Configurar .env do CodeIgniter

```bash
cd src/codeigniter-app
# Usar o arquivo de desenvolvimento (já existe)
```

**Verificar `src/codeigniter-app/.env`:**
```env
CI_ENVIRONMENT=development
app_baseURL='http://localhost:28088/'

database.default.hostname=mysql
database.default.username=root
database.default.password=root
database.default.database=lista_revisao2

AIRFLOW_HOST=airflow-webserver
AIRFLOW_PORT=8080
```

### 3. Subir os containers

```bash
cd /home/cblna123456/datalake-air-flow
docker-compose up -d
```

### 4. Acessar serviços

- **WebApp**: http://localhost:28088
- **Airflow**: http://localhost:28083
- **MinIO Console**: http://localhost:29001
- **MySQL**: localhost:23306

---

## 🌐 PRODUÇÃO (Servidor Remoto)

### 1. Configurar variáveis de ambiente

```bash
# No servidor, copiar arquivo de produção
cp .env-prd .env
```

**Editar `.env` (raiz do projeto):**
```env
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

### 6. Verificar logs

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

### 7. Acessar serviços

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

## 📊 Tabela de Portas por Ambiente

| Serviço | Dev (padrão) | Teste (29xxx) | Produção (28xxx) |
|---------|---|---|---|
| Nginx HTTP | 80 | 29080 | 28080 |
| Nginx HTTPS | 443 | 29443 | 28443 |
| CodeIgniter | 80 | 29088 | 28088 |
| Airflow | 8080 | 29083 | 28083 |
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

# Verificar variável AIRFLOW_PORT no .env do CodeIgniter
grep AIRFLOW src/codeigniter-app/.env
```

### MySQL não conecta:
```bash
# Verificar senha no .env
grep MYSQL_ROOT_PASSWORD .env

# Testar conexão
docker exec -it mysql mysql -uroot -p
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
