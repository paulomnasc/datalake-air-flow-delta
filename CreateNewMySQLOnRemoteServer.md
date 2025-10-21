

# 🐳 Instanciação de MySQL via Docker em Servidor de Destino

Este guia descreve como criar e iniciar uma nova instância MySQL em um servidor remoto usando Docker, com configuração de porta, volume persistente e senha de root.

---

## ✅ Pré-requisitos

- Docker instalado e funcionando no servidor
- Acesso ao terminal com permissões de sudo
- Porta `3306` liberada no firewall (ou configurada via Docker)

---

## ✅ Etapa 1: Criar diretório para persistência de dados

```bash
mkdir -p ~/mysql-data
```

> Esse diretório será montado como volume para armazenar os dados do MySQL de forma persistente.

---

## ✅ Etapa 2: Subir o container MySQL

```bash
docker run -d \
  --name mysql \
  -e MYSQL_ROOT_PASSWORD=MinhaSenhaSegura123 \
  -p 3306:3306 \
  -v ~/mysql-data:/var/lib/mysql \
  mysql:8.0
```

### 🔹 Explicação dos parâmetros:

| Parâmetro               | Descrição                                      |
|------------------------|-----------------------------------------------|
| `--name mysql`         | Nome do container                             |
| `-e MYSQL_ROOT_PASSWORD` | Define a senha do usuário root               |
| `-p 3306:3306`         | Mapeia a porta do host para o container       |
| `-v ~/mysql-data:/var/lib/mysql` | Volume persistente para os dados     |
| `mysql:8.0`            | Imagem oficial do MySQL versão 8.0            |

---

## ✅ Etapa 3: Verificar se o container está rodando

```bash
docker ps
```

> Você deve ver o container `mysql` ativo e escutando na porta `3306`.

---

## ✅ Etapa 4: Acessar o MySQL dentro do container

```bash
docker exec -it mysql bash
```

Dentro do container:

```bash
mysql -u root -p
```

Digite a senha definida em `MYSQL_ROOT_PASSWORD`.

---

## ✅ Etapa 5: Criar banco de dados para restauração

```sql
CREATE DATABASE lista_revisao;
```

> Esse será o banco onde o dump será restaurado.

---

## ✅ Etapa 6: Validar a instância

```sql
SHOW DATABASES;
```

> Você deve ver `lista_revisao` listado entre os bancos disponíveis.

---

## ✅ Resultado

A instância MySQL foi criada com sucesso no servidor remoto via Docker, com dados persistentes, acesso root e pronta para receber o dump do banco `lista_revisao`.

---

Se quiser, posso te ajudar a montar um `docker-compose.yml` para facilitar futuras reinstanciações ou escalar com múltiplos serviços. Mas esse guia já está pronto para ser publicado no GitHub e usado em produção 💪
