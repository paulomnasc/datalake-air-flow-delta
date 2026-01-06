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

## ✅ Checklist de Deploy

### Desenvolvimento:
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
