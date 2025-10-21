
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
   docker cp dump-classicmodels-202510141225.sql mysql:/dump.sql
   ```

---

## ✅ Etapa 3: Restaurar o dump no MySQL dentro do container

1. Acesse o container:

   ```bash
   docker exec -it mysql bash
   ```

2. Entre no MySQL:

   ```bash
   mysql -u root -p
   ```

3. Crie o banco de dados (se necessário):

   ```sql
   CREATE DATABASE lista_revisao;
   EXIT;
   ```

4. Importe o dump:

   ```bash
   mysql -u root -p lista_revisao < /dump.sql
   ```

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
