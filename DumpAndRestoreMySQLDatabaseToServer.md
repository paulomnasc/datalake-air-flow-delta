
# 🐬 Backup e Restauração do MySQL com `mysqldump`, DBeaver e Docker

Este guia documenta como exportar um banco de dados MySQL usando `mysqldump` via DBeaver, e como restaurá-lo em uma instância MySQL rodando em um container Docker.

---

## ✅ Etapa 1: Exportar o banco de dados com `mysqldump` via DBeaver

1. Abra o DBeaver e conecte-se ao banco de dados `lista_revisao`.
2. Vá em **Driver Manager** → selecione **MySQL** → clique em **Editar**.
3. Na aba **Cliente Local**, configure o caminho do cliente nativo:
   - Exemplo:  
     ```
     /usr/bin/mysqldump
     ```
     ou  
     ```
     C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe
     ```
4. Salve e feche.
5. Clique com o botão direito no banco `lista_revisao` → **Tools** → **Dump Database**.
6. Escolha o diretório de destino e exporte o arquivo:
   - Exemplo: `dump-classicmodels-202510141225.sql`

---

## ✅ Etapa 2: Copiar o dump para o container Docker

1. Verifique o nome do container MySQL:

   ```bash
   docker ps
   ```

   Exemplo de saída:

   ```
   CONTAINER ID   IMAGE     ...   NAMES
   5278a3b0c1cf   mysql:8.0 ...   mysql
   ```

2. Navegue até a pasta onde está o dump:

   ```bash
   cd /home/opc/dump
   ```

3. Confirme que o arquivo existe:

   ```bash
   ls -lh dump-classicmodels-202510141225.sql
   ```

4. Copie o arquivo para dentro do container:

   ```bash
   docker cp northwind-data.sql mysql:/dump.sql
   ```

---

## ✅ Etapa 3: Restaurar o dump no MySQL dentro do container

Antes de importar, atenção: este dump contém INSERTs com valores explícitos para a coluna `id` (chaves primárias). Se tabelas com os mesmos ids já existirem, a importação gerará erros `ERROR 1062 (23000): Duplicate entry`.

Opções seguras para restaurar:

- Opção A — Importação limpa (recomendado se você puder sobrescrever o banco): dropar e recriar o banco, depois importar:

```bash
docker exec -i mysql sh -c 'mysql -u root -proot -e "DROP DATABASE IF EXISTS northwind; CREATE DATABASE northwind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
docker exec -i mysql sh -c 'mysql -u root -proot northwind < /dump.sql'
```

- Opção B — Reusar banco existente, mas limpar tabelas alvo antes de importar (quando quiser preservar outras bases/objetos):

  1. Liste tabelas e gere `TRUNCATE`/`DROP` conforme necessário.
  2. Em seguida execute a importação como na Opção A (apontando para o banco já existente).

- Opção C — Importação tolerante a erros (não recomendada para ambientes de produção): usar `--force` no cliente `mysql` para continuar apesar de duplicatas:

```bash
docker exec -i mysql sh -c 'mysql --force -u root -proot northwind < /dump.sql'
```

Comandos úteis para inspeção antes de importar:

```bash
# Ver as primeiras 200 linhas do dump (verifica se há CREATE TABLE ou cabeçalho)
docker exec -i mysql sh -c "sed -n '1,200p' /dump.sql"

# Verificar se o arquivo foi copiado corretamente
docker exec -i mysql sh -c 'ls -lh /dump.sql'

# Listar as tabelas atuais do banco northwind
docker exec -i mysql mysql -u root -proot -e "USE northwind; SHOW TABLES;"
```

Observações importantes:
- Não execute o redirecionamento (`< /dump.sql`) de dentro do cliente MySQL interativo; rode-o a partir do shell do host usando `docker exec -i`, como nos exemplos acima.
- O dump já contém comandos para desabilitar checagens de UNIQUE/FOREIGN KEY no início (`UNIQUE_CHECKS=0`, `FOREIGN_KEY_CHECKS=0`), o que facilita a carga de dados com dependências. Ainda assim, se houver dados pré-existentes, podem ocorrer conflitos de chave primária.
- Se desejar, posso executar a Opção A (dropar/recriar + importar) agora — isso sobrescreverá tudo em `northwind`. Deseja que eu proceda? 

---

## ✅ Etapa 4: Verificar os dados restaurados

1. Acesse o MySQL novamente:

   ```bash
   mysql -u root -p
   ```

2. Use o banco restaurado:

   ```sql
   USE lista_revisao;
   SHOW TABLES;
   ```

3. Faça uma consulta de teste:

   ```sql
   SELECT * FROM customers LIMIT 10;
   ```

---

## ✅ Resultado

O banco `lista_revisao` foi exportado com sucesso via DBeaver, transferido para o container Docker e restaurado na instância MySQL. A tabela `customers` está acessível e os dados foram validados.

---

Se quiser, posso te ajudar a transformar esse guia em um README interativo com comandos automatizados e validações. Mas esse Markdown já está pronto para ser publicado no GitHub 💪
