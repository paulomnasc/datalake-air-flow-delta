# 🔄 Guia de Troca de Ambiente

Este guia documenta os procedimentos para alternar entre ambientes (TEST, PRD).

## 📋 Arquivos de Configuração Disponíveis

- **`.env-test`** - Teste/Homologação (portas 29xxx)
- **`.env-prd`** - Produção (portas 28xxx)

## 🎯 Como Funciona

1. O arquivo **`.env`** é o arquivo ATIVO lido pelo `docker-compose.yml`
2. Os templates em `nginx/*.conf.template` usam variáveis do `.env`
3. O Docker substitui **automaticamente** as variáveis ao iniciar os containers
4. ⚠️ **NÃO edite** `nginx/myflow.conf` manualmente! É gerado automaticamente. Se precisar corrigir, delete-o e deixe o Docker regenerar ao fazer `docker-compose up -d`

## 🔧 Procedimento de Troca de Ambiente

### Para TESTE:
```bash
cd /root/datalake-air-flow-teste
cp .env .env.backup-$(date +%Y%m%d-%H%M%S)
cp .env-test .env
docker-compose down
docker-compose up -d
```

### Para PRODUÇÃO:
```bash
cd /root/datalake-air-flow-teste
cp .env .env.backup-$(date +%Y%m%d-%H%M%S)
cp .env-prd .env
docker-compose down
docker-compose up -d
```

### Passo adicional (CodeIgniter Web App)
Para que a aplicação web use as credenciais corretas do ambiente, ajuste os arquivos de ambiente do CodeIgniter:

```bash
# Para TESTE (desenvolvimento)
cd /root/datalake-air-flow-delta/src/codeigniter-app
mv -f .env .env-dsv-$(date +%Y%m%d-%H%M%S) 2>/dev/null || true
cp -f .env-test-template .env

# Para PRODUÇÃO
cd /root/datalake-air-flow-delta/src/codeigniter-app
mv -f .env .env-dsv-$(date +%Y%m%d-%H%M%S) 2>/dev/null || true
cp -f .env-prd .env
```

## 📊 Diferenças entre Ambientes

| Variável | TEST | PRD |
|----------|------|-----|
| `ENV_SUFFIX` | test | (vazio) |
| `NGINX_PORT_HTTP` | 29080 | 28080 |
| `NGINX_PORT_HTTPS` | 29443 | 28443 |
| `NGINX_SERVER_NAME` | 46.224.156.251 | myflow.estudotabela.com.br |
| `MYSQL_DATABASE` | lista_revisao2_test | lista_revisao2 |
| `AIRFLOW_PORT_WEB` | 29083 | 28083 |
| `MINIO_PORT_API` | 30000 | 29000 |
| `MINIO_PORT_CONSOLE` | 30001 | 29001 |
| `NGINX_SSL_ENABLED` | false | true |

## ✅ Verificação Pós-Troca

Após a troca, verificar:

```bash
# 1. Ver containers ativos
docker-compose ps

# 2. Ver logs do nginx
docker-compose logs nginx | tail -20

# 3. Verificar variável de ambiente ativa
grep "ENV_SUFFIX=" .env

# 4. Testar acesso
curl -I http://localhost:$(grep NGINX_PORT_HTTP .env | cut -d= -f2)
```

## 🚨 Cuidados Importantes

1. **Sempre fazer backup do `.env` atual antes de trocar**
2. **Ambiente PRD**: Verificar se certificados SSL estão válidos
3. **Dados**: Cada ambiente tem seu próprio banco MySQL (sufixo diferente)
4. **Portas**: Garantir que as portas do novo ambiente estejam livres
5. **DNS**: Ambiente PRD depende de DNS configurado corretamente

## 🔍 Identificar Ambiente Atual

```bash
# Ver qual ambiente está ativo
cat .env | head -10

# Ver sufixo dos containers
docker ps --format "{{.Names}}" | head -5
```

## 📝 Comandos Rápidos para Assistente

Quando o usuário solicitar **"trocar para [ambiente]"**, executar:

```bash
# Trocar para TEST
cd /root/datalake-air-flow-teste && \
cp .env .env.backup-$(date +%Y%m%d-%H%M%S) && \
cp .env-test .env && \
docker-compose down && \
docker-compose up -d

# Trocar para PRD
cd /root/datalake-air-flow-teste && \
cp .env .env.backup-$(date +%Y%m%d-%H%M%S) && \
cp .env-prd .env && \
docker-compose down && \
docker-compose up -d
```

```bash
# Ajuste rápido CodeIgniter (TEST)
cd /root/datalake-air-flow-delta/src/codeigniter-app && \
mv -f .env .env-dsv-$(date +%Y%m%d-%H%M%S) 2>/dev/null || true && \
cp -f .env-test-template .env

# Ajuste rápido CodeIgniter (PRD)
cd /root/datalake-air-flow-delta/src/codeigniter-app && \
mv -f .env .env-dsv-$(date +%Y%m%d-%H%M%S) 2>/dev/null || true && \
cp -f .env-prd .env
```

## 🗑️ Limpeza de Backups Antigos

```bash
# Listar backups
ls -lah .env.backup-*

# Remover backups com mais de 30 dias
find . -name ".env.backup-*" -mtime +30 -delete
```

---
**Última atualização:** 08/01/2026
**Ambiente atual:** TEST (29xxx)

---

## 🧩 Apêndice — Script Rápido (PRD no projeto delta)

Este bloco executa a troca completa para PRD no projeto `datalake-air-flow-delta`, incluindo ajuste do CodeIgniter e verificação básica.

```bash
# 1) Projeto delta (produção)
cd /root/datalake-air-flow-delta

# Backup e ativar .env de PRD
cp .env .env.backup-"$(date +%Y%m%d-%H%M%S)"
cp .env-prd .env

# 2) Ajustar CodeIgniter para PRD
cd src/codeigniter-app
mv -f .env .env-dsv-"$(date +%Y%m%d-%H%M%S)" 2>/dev/null || true
cp -f .env-prd .env

# 3) Subir stack
cd /root/datalake-air-flow-delta
docker-compose down
docker-compose up -d

# 4) Verificação rápida
docker-compose ps
grep -E "^(ENV_SUFFIX|NGINX_SERVER_NAME|NGINX_PORT_HTTP|NGINX_PORT_HTTPS)=" .env
PORT=$(grep -E "^NGINX_PORT_HTTP=" .env | cut -d= -f2)
curl -I "http://localhost:${PORT}" | head -1
```
