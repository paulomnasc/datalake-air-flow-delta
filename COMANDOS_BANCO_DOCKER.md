# Comandos Essenciais MySQL e PostgreSQL (Docker)

## MySQL

### 1. Acessar o MySQL do container
```bash
mysql -h 127.0.0.1 -P 3306 -u root -p
# ou
mysql -h mysql -P 3306 -u root -p
# (use o nome do serviço/container se estiver dentro de outro container)
```

### 2. Acessar via Docker
```bash
docker exec -it mysql mysql -u root -p
```

### 3. Backup do banco
```bash
docker exec mysql mysqldump -u root -pSEUSENHA --all-databases > backup.sql
```

### 4. Restore do banco
```bash
docker exec -i mysql mysql -u root -pSEUSENHA < backup.sql
```

### 5. Verificar logs do MySQL
```bash
docker logs mysql | tail -40
```

### 6. Testar conexão do host
```bash
mysql -h 127.0.0.1 -P 3306 -u root -p
```

### 7. Alterar a senha do root no MySQL

Acesse o MySQL e execute:

```sql
ALTER USER 'root'@'%' IDENTIFIED BY 'NovaSenhaForte!';
FLUSH PRIVILEGES;
```

> Para conexões apenas locais (recomendado para segurança):
```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NovaSenhaForte!';
FLUSH PRIVILEGES;
```

Se precisar criar um novo usuário seguro:
```sql
CREATE USER 'admin'@'localhost' IDENTIFIED BY 'SenhaMuitoForte!';
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
```

> **Dica:** Sempre use senhas fortes e evite expor a porta 3306 para a internet.

### 9. Resetar senha do root (caso tenha perdido o acesso)

Acesse o container do MySQL com:
```bash
docker exec -it mysql bash
```

No shell do container, inicie o MySQL em modo seguro:
```bash
mysqld_safe --skip-grant-tables &
```

Abra outro terminal e conecte sem senha:
```bash
mysql -u root
```

No prompt do MySQL, execute:
```sql
FLUSH PRIVILEGES;
ALTER USER 'root'@'%' IDENTIFIED BY 'NovaSenhaForte!';
-- ou para acesso local apenas:
ALTER USER 'root'@'localhost' IDENTIFIED BY 'NovaSenhaForte!';
FLUSH PRIVILEGES;
```

Depois, reinicie o container para voltar ao modo normal:
```bash
docker restart mysql
```

> **Atenção:** Use sempre uma senha forte e nunca exponha a porta 3306 para a internet.

---

## PostgreSQL

### 1. Acessar o PostgreSQL do container
```bash
psql -h 127.0.0.1 -p 5433 -U pbi_user -d datalake_bi
# ou
psql -h postgres-bi -p 5432 -U pbi_user -d datalake_bi
# (use o nome do serviço/container se estiver dentro de outro container)
```

### 2. Acessar via Docker
```bash
docker exec -it postgres-bi psql -U pbi_user -d datalake_bi
```

### 3. Backup do banco
```bash
docker compose exec -T postgres-bi \
  pg_dump -U pbi_user datalake_bi > backup_datalake_bi_$(date +%Y%m%d).sql
```

### 4. Restore do banco
```bash
cat backup_datalake_bi_YYYYMMDD.sql | \
  docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi
```

### 5. Verificar tabelas
```bash
docker compose exec -T postgres-bi \
  psql -U pbi_user -d datalake_bi \
  -c "SELECT tablename FROM pg_tables WHERE schemaname='public';"
```

### 6. Verificar logs do PostgreSQL
```bash
docker logs postgres-bi | tail -40
```

### 7. Recuperação de dados após ataque/ransomware
Se aparecer o banco `RECOVER_YOUR_DATA`, tente explorar e recuperar dados assim:

```sql
-- Listar tabelas disponíveis
USE RECOVER_YOUR_DATA;
SHOW TABLES;

-- Ver estrutura da tabela
DESCRIBE RECOVER_YOUR_DATA;

-- Ver dados armazenados
SELECT * FROM RECOVER_YOUR_DATA LIMIT 50;

-- Exportar dados (se necessário, ajuste caminho e permissões)
SELECT * FROM RECOVER_YOUR_DATA INTO OUTFILE '/var/lib/mysql-files/recuperado.csv' FIELDS TERMINATED BY ',' ENCLOSED BY '"' LINES TERMINATED BY '\n';
```

> **Atenção:** Se encontrar mensagens de extorsão, significa que o banco foi alvo de ransomware e os dados originais foram removidos. Procure backups externos para restauração.

---

## Dicas Gerais
- Sempre use `-h 127.0.0.1` para forçar conexão TCP do host.
- Use o nome do serviço Docker para conexões entre containers.
- Para problemas de acesso, verifique logs do container.
- Para comandos Airflow, veja `MYSQL_INGESTION.md` e `MIGRACAO_DUCKDB_POSTGRESQL.md`.

---

## Referências
- [MYSQL_INGESTION.md](./MYSQL_INGESTION.md)
- [MIGRACAO_DUCKDB_POSTGRESQL.md](./MIGRACAO_DUCKDB_POSTGRESQL.md)
