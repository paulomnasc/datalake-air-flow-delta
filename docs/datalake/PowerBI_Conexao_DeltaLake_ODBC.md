# 🔗 Conexão do Power BI Desktop ao Delta Lake (via Spark Thrift Server)

Este guia detalha o processo de conexão do Power BI Desktop ao Spark Thrift Server rodando em sua VM Linux (IP: 137.131.212.68), permitindo consultar dados armazenados no Delta Lake (MinIO) usando consultas SQL.

O Spark Thrift Server atua como uma camada semântica, traduzindo o JDBC/ODBC do Power BI para o Spark SQL.

---

## Pré-requisitos

1.  **Spark Thrift Server Ativo:** O serviço `spark-sql` no seu Docker Compose deve estar rodando e expondo a porta `10000` na VM Linux.
2.  **Acesso de Rede:** O Firewall da VM e o Grupo de Segurança da Nuvem (Oracle Cloud, neste caso) devem permitir o tráfego TCP de entrada na porta `10000` a partir do seu endereço IP local.
3.  **Power BI Desktop:** Instalado e rodando em sua máquina local (Windows).

---

## Passo 1: Instalação do Driver ODBC para Spark

O Power BI Desktop requer um software intermediário para "falar" com o protocolo Spark.

1.  **Baixe o Driver:** Faça o download e instale um Driver ODBC compatível com Apache Spark ou HiveServer2. As opções mais comuns e robustas são:
    * **Simba Apache Spark ODBC Driver** (Recomendado)
    * **Microsoft Spark ODBC Driver**
2.  **Instale a Versão 64-bit:** Certifique-se de instalar a versão de 64 bits do driver, pois o Power BI Desktop é um aplicativo de 64 bits.

---

## Passo 2: Configuração do DSN (Data Source Name)

O DSN configura o local e o protocolo de comunicação para o seu driver.

1.  **Abrir o Administrador ODBC:**
    * No Windows, pesquise por **"Fontes de Dados ODBC (64 bits)"** e abra o aplicativo.
2.  **Criar DSN de Sistema:**
    * Vá para a aba **DSN de Sistema** (`System DSN`).
    * Clique em **Adicionar...** (`Add...`).
    * Selecione o driver do Spark que você instalou (ex: `Simba Apache Spark ODBC Driver`).
    * Clique em **Concluir**.
3.  **Configurar os Detalhes da Conexão:**

    | Configuração | Valor a Ser Definido | Motivo / Observação |
    | :--- | :--- | :--- |
    | **Data Source Name (DSN)** | `Spark_DeltaLake_VM` (ou outro nome fácil) | Nome que aparecerá no Power BI. |
    | **Host** | **`137.131.212.68`** | IP Público da sua VM Linux. |
    | **Port** | **`10000`** | Porta padrão do Spark Thrift Server. |
    | **Enable SSL** | **DESMARCADO** (ou `No`) | Solução para o erro `Cannot enable SSL...` (o servidor não usa SSL). |
    | **Auth Mechanism** | **User Name** (ou equivalente) | Essencial para satisfazer o protocolo HiveServer2 (Solução para o erro `Unexpected response...`). |
    | **User Name** | `spark` ou `default` | Nome de usuário *dummy* (não é validado sem Kerberos). |
    | **Password** | **Deixe em branco.** | Não é necessário para ambientes de desenvolvimento sem Kerberos. |
    | **Thrift Transport Protocol** | `Binary` | Protocolo de comunicação entre o cliente e o Thrift Server. |

4.  **Testar e Salvar:**
    * Clique em **Test** para validar a conexão.
    * Se o teste for bem-sucedido, clique em **OK** para salvar o DSN.

---

## Passo 3: Conexão no Power BI Desktop

1.  **Abrir o Power BI:** No Power BI Desktop, clique em **Obter Dados** (`Get Data`).
2.  **Selecionar o Conector:** Pesquise por **ODBC** e selecione-o.
3.  **Escolher o DSN:** Na janela do conector ODBC:
    * Selecione o DSN que você acabou de criar na lista suspensa (ex: `Spark_DeltaLake_VM`).
    * (Opcional) Em **Opções Avançadas**, você pode inserir uma instrução SQL inicial, como `SELECT * FROM nome_da_sua_tabela_delta`, para carregar uma tabela específica.
4.  **Autenticação:**
    * Na janela de Credenciais, selecione a aba **Básico** (`Basic`).
    * Insira o **Nome de Usuário** que você definiu no DSN (ex: `spark` ou `default`).
    * Deixe o campo **Senha** vazio.
    * Clique em **Conectar**.


---
## ADENDO: Otimizando para BI com Tabelas Permanentes

Embora a conexão direta ao caminho (`SELECT * FROM \`s3a://...\``) funcione, a melhor prática para o Power BI é consultar uma **Tabela Registrada**. Isso permite que o Power BI use o **Navigator** (em vez de consultas SQL manuais) e facilita o uso do recurso **DirectQuery** (consulta em tempo real).

### 1. Entendendo o Hive Metastore

A "criação da tabela" não significa que os dados são movidos. Significa que o Spark Thrift Server está registrando os **metadados** (nome da tabela, esquema de colunas, e o **caminho no MinIO**) em um catálogo, conhecido como **Hive Metastore**.

Quando o Power BI consulta `customers_delta`, o Thrift Server consulta o Metastore para saber: "Onde estão os dados de `customers_delta`? Ah, estão em `s3a://lab01/delta/customers`."

### 2. Criando a Tabela Permanente (DDL)

Como o Power BI só envia consultas (`SELECT`), você precisa de um cliente SQL robusto para enviar comandos de Definição de Dados (`CREATE TABLE`).

#### 2.1. Cliente Recomendado: DBeaver

Use o DBeaver (ou outra ferramenta como Beeline) para se conectar ao Thrift Server:

| Parâmetro | Configuração |
| :--- | :--- |
| **Driver** | Apache Spark ou Apache Hive 4+ JDBC Driver. |
| **URL (JDBC)** | `jdbc:hive2://137.131.212.68:10000/default` |
| **Usuário** | `spark` |
| **Password** | *(Vazio)* |
| **Propriedade: `transportMode`** | `binary` |

#### 2.2. Comando DDL para Registro

Execute o seguinte comando SQL **apenas uma vez** no DBeaver para registrar seu diretório Delta:

```sql
CREATE TABLE IF NOT EXISTS default.customers_delta
USING DELTA
-- OPCIONAL: Se a tabela for particionada (como sugere sua estrutura)
PARTITIONED BY (partition_date)
LOCATION 's3a://lab01/delta/customers';

```

```sql

Scripts Reais Utilizados:
-- Garante que o banco de dados 'default' esteja selecionado
USE default;

-- O comando CREATE TABLE que registra o caminho Delta
CREATE TABLE IF NOT EXISTS default.customers_delta
USING DELTA
LOCATION 's3a://lab01/delta/customers';

-- O comando CREATE TABLE que registra o caminho Delta
CREATE TABLE IF NOT EXISTS default.orders_delta
USING DELTA
LOCATION 's3a://lab01/delta/orders';

CREATE TABLE IF NOT EXISTS default.products_delta
USING DELTA
LOCATION 's3a://lab01/delta/products';

```


Entendido. Refiz a última resposta sobre a diferença entre o Navegador do Power BI e a Consulta SQL direta, formatando-a como um adendo em Markdown, pronta para ser adicionada ao seu documento principal.

-----

## 📄 Adendo: Navegador vs. SQL Personalizado no Power BI

### Cenário: Tabela Funciona via SQL, mas Falha no Navegador

Após configurar o DSN, é comum encontrar uma falha de exibição de metadados no Power BI ao conectar a fontes de Datalake como o Spark Thrift Server:

| Situação | Resultado | Diagnóstico |
| :--- | :--- | :--- |
| **1. Navegador de Tabelas (GUI)** | Erro: `A tabela não tem nenhuma coluna visível e não pode ser consultada.` | O Power BI está lendo metadados **stale** (desatualizados ou incompletos) do catálogo do Spark, o que impede a pré-visualização. |
| **2. Consulta SQL Manual (Opções Avançadas)** | **Sucesso.** A tabela é carregada corretamente. | Prova que a tabela existe no Metastore e que a conexão ODBC está íntegra. O problema é puramente na camada de leitura de catálogo. |

### Ações e Estratégias Recomendadas

Para garantir a melhor estabilidade e contornar a falha de sincronização de metadados, adote a seguinte estratégia:

#### Estratégia A: Preferir o SQL na Conexão (Recomendado)

O método mais robusto é sempre enviar o comando SQL diretamente, forçando o Spark a processar a tabela em tempo de execução:

1.  No Power BI, em **Obter Dados** \> **ODBC**.

2.  Selecione o seu DSN (`Spark_DeltaLake_VM`).

3.  Vá em **Opções Avançadas** (`Advanced options`).

4.  No campo **Instrução SQL**, insira a consulta desejada (Exemplo: para limitar o volume inicial de dados):

    ```sql
    SELECT *
    FROM default.customers_delta
    LIMIT 1000
    ```

5.  Clique em **OK**. O Power BI executará a consulta com sucesso e carregará os dados.

#### Estratégia B: Sincronizar o Esquema Manualmente (`REFRESH TABLE`)

Se você realmente precisar que o Navegador funcione (para ver todas as tabelas rapidamente) ou se os metadados estiverem incorretos, force o Spark a atualizar o Metastore:

1.  **Use o DBeaver** (ou outro cliente SQL funcional).

2.  Execute o comando `REFRESH TABLE` para forçar o Spark a reler o `_delta_log` e atualizar o esquema no catálogo:

    ```sql
    REFRESH TABLE default.customers_delta;
    ```

    ***Observação:** Envie este comando como uma **instrução única** no DBeaver para evitar o erro de sintaxe do Spark (`REFRESH statements cannot contain ... \n`).*

3.  Após a execução bem-sucedida, o esquema estará correto no servidor. Tente a conexão no Power BI novamente. Se o Navegador ainda falhar, utilize a **Estratégia A (SQL Manual)**.

<!-- end list -->

```
```