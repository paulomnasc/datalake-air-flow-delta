# 📋 Guia de Preenchimento da Interface de Configuração de DAGs

Este documento explica como preencher corretamente a interface web para criar configurações de DAGs de ingestão e transformação de dados.

## 📑 Índice

1. [Visão Geral](#-visão-geral)
2. [Campos Básicos da DAG](#-campos-básicos-da-dag)
3. [Configuração de Origem de Dados](#-configuração-de-origem-de-dados)
4. [Modo Multi-Tabela vs Single-Tabela](#-modo-multi-tabela-vs-single-tabela)
5. [Seleção de Função de Pipeline](#-seleção-de-função-de-pipeline)
6. [Conexão SQL (Direct vs SSH Tunnel)](#-conexão-sql-direct-vs-ssh-tunnel)
7. [Exemplos Práticos](#-exemplos-práticos)

---

## 🎯 Visão Geral

A interface permite configurar DAGs (Directed Acyclic Graphs) do Apache Airflow para:
- Ingestar dados de diferentes fontes (CSV, Parquet, MySQL, PostgreSQL)
- Processar uma ou múltiplas tabelas simultaneamente
- Aplicar transformações através da arquitetura Medallion (RAW → Bronze → Silver → Gold)

---

## 📝 Campos Básicos da DAG

### Nome da DAG
- **Campo**: `DAG ID`
- **Formato**: Apenas letras minúsculas, números e underscores (`_`)
- **Exemplos válidos**: `pipe_customers`, `dag_northwind_customers`, `ingest_vendas_2024`
- **Exemplos inválidos**: ❌ `Pipe-Customers`, ❌ `DAG Customers`, ❌ `pipe.customers`

### Descrição
- **Campo**: `Descrição`
- **Propósito**: Explicar o objetivo da DAG
- **Exemplo**: "Ingestão diária da tabela de clientes do banco Northwind"

### Tipo de Origem
- **Campo**: `Tipo de Origem`
- **Opções**:
  - 📄 **CSV Genérico** - Arquivos CSV separados por vírgula
  - 📊 **Parquet** - Arquivos Parquet (formato colunar)
  - 🗄️ **MySQL Northwind** - Banco de dados MySQL
  - 🐘 **PostgreSQL** - Banco de dados PostgreSQL

---

## 🔌 Configuração de Origem de Dados

### Para CSV/JSON
1. Selecione **CSV Genérico** ou **JSON**
2. Aparecerá o campo **Upload de Arquivo**
3. Clique em "Escolher arquivo" e selecione o arquivo local
4. O arquivo será armazenado em MinIO/RAW

### Para Parquet
1. Selecione **Parquet**
2. Aparecerá o campo **Caminho no MinIO (RAW)**
3. Digite o caminho: `raw/seu_arquivo.parquet` ou `raw/pasta/arquivo.parquet`

### Para MySQL/PostgreSQL
1. Selecione **MySQL Northwind** ou **PostgreSQL**
2. Aparecerão os **campos de conexão SQL** (veja seção específica abaixo)
3. Você pode configurar conexão **Direta** ou via **SSH Tunnel**

---

## 🔀 Modo Multi-Tabela vs Single-Tabela

### 📊 Single-Tabela (Padrão)
- **Quando usar**: Processar apenas 1 tabela por DAG
- **Campos visíveis**:
  - ✅ **Tabela/Destino Final**: Nome da tabela no destino (ex: `customers`)
- **Exemplo de uso**:
  - DAG `pipe_customers` → Processa apenas tabela `customers`
  - DAG `pipe_orders` → Processa apenas tabela `orders`

### 📚 Multi-Tabela (Avançado)
- **Quando usar**: Processar múltiplas tabelas de uma mesma fonte SQL em paralelo
- **Como ativar**: Marque o checkbox ☑️ **Modo Multi-Tabela**
- **Campos que aparecem**:
  - ✅ **Máximo de Tasks Paralelas**: Número de tabelas processadas simultaneamente (padrão: 16)
  - ✅ **Botão "Conectar"**: Testa conexão e lista tabelas disponíveis
  - ✅ **Checkboxes de seleção**: Lista de tabelas com metadados (linhas, tamanho)
- **Campos que desaparecem**:
  - ❌ **Tabela/Destino Final**: Não faz sentido, cada tabela mantém seu nome original

#### Exemplo de Uso Multi-Tabela
```
Fonte: MySQL Northwind
Banco: northwind
Tabelas selecionadas:
  ☑️ customers (91 linhas, 0.2 MB)
  ☑️ orders (830 linhas, 0.5 MB)
  ☑️ products (77 linhas, 0.1 MB)

Resultado: 3 tasks paralelas, cada uma processando uma tabela
```

---

## 🔧 Seleção de Função de Pipeline

### ⚙️ Funções Disponíveis

#### ⭐ Recomendado para CSV/Parquet
- **RAW → Medallion (Bronze/Silver/Gold)**
  - **ID**: `lib.medallion_pipeline.raw_to_medallion`
  - **Quando usar**: Dados já estão na camada RAW (upload manual ou pré-ingestão)
  - **Processo**: RAW → Bronze → Silver → Gold
  - ⚠️ **ATENÇÃO**: NÃO use para fontes SQL! Vai dar erro 404 (arquivo não encontrado)

#### 🔥 Ingestão de Fontes SQL
- **MySQL → Medallion (Ingestão Completa)** ✅
  - **ID**: `lib.mysql_ingestion.mysql_to_medallion`
  - **Quando usar**: Ingestar dados de MySQL/PostgreSQL + pipeline completo
  - **Processo**: MySQL → RAW → Bronze → Silver → Gold
  - ✅ **OBRIGATÓRIO para Multi-Tabela SQL**

- **MySQL → RAW (Apenas Ingestão)**
  - **ID**: `lib.mysql_ingestion.ingest_mysql_to_raw`
  - **Quando usar**: Apenas copiar dados de SQL para RAW (sem transformações)
  - **Processo**: MySQL → RAW (para)

#### 🎨 Camadas Individuais
- **Apenas Bronze**: `lib.medallion_pipeline.apply_bronze_layer`
- **Apenas Silver**: `lib.medallion_pipeline.apply_silver_layer`
- **Apenas Gold**: `lib.medallion_pipeline.apply_gold_layer`

### 🚨 Validação Automática

A interface valida automaticamente a compatibilidade entre **Tipo de Origem** e **Função de Pipeline**:

#### ✅ Configurações Válidas
```
Fonte: MySQL + Função: mysql_to_medallion
→ ✅ Configuração válida: Função compatível com fonte SQL

Fonte: CSV + Função: raw_to_medallion  
→ ✅ Configuração válida: Função compatível com arquivos CSV/Parquet
```

#### ⚠️ Configurações com Aviso
```
Fonte: MySQL + Função: raw_to_medallion
→ ⚠️ ATENÇÃO: Esta função espera que dados JÁ EXISTAM na camada RAW. 
  Para fontes SQL, use "MySQL → Medallion" que faz a ingestão primeiro!
```

#### ❌ Configurações com Erro (Auto-corrigido)
```
Fonte: MySQL + Multi-Tabela + Função: raw_to_medallion
→ ❌ ERRO: Para processar múltiplas tabelas SQL, você DEVE usar 
  "MySQL → Medallion" (faz ingestão + pipeline completo)
  
→ Sistema auto-corrige para: mysql_to_medallion
```

---

## 🔐 Conexão SQL (Direct vs SSH Tunnel)

### 🔌 Aba "Conexão Direta"

Use quando o banco de dados é **acessível diretamente** pela rede do Airflow.

#### Campos:
1. **ID da Conexão Airflow** (ex: `mysql_northwind`)
   - Nome único para identificar esta conexão
   - Será usado no código das DAGs

2. **Host** (ex: `mysql`, `192.168.1.100`)
   - Endereço do servidor de banco de dados
   - Pode ser IP, hostname ou nome do container Docker

3. **Porta** (ex: `3306` para MySQL, `5432` para PostgreSQL)
   - Porta de conexão do banco de dados

4. **Nome do Banco** (ex: `northwind`, `production_db`)
   - Database/schema a conectar

5. **Usuário** (ex: `root`, `airflow_user`)
   - Usuário do banco de dados

6. **Senha** (ex: `root123`)
   - Senha do usuário (armazenada de forma segura)

#### Exemplo de Conexão Direta:
```
ID da Conexão: mysql_northwind
Host: mysql
Porta: 3306
Banco: northwind
Usuário: root
Senha: root
```

### 🔒 Aba "SSH Tunnel"

Use quando o banco de dados está em uma **rede privada** acessível apenas via SSH.

#### Configuração SSH (Servidor Bastion):
1. **SSH Host** (ex: `bastion.empresa.com`, `10.0.0.50`)
   - Servidor SSH intermediário (jump server)

2. **SSH User** (ex: `ubuntu`, `admin`)
   - Usuário SSH

3. **SSH Port** (ex: `22`)
   - Porta SSH

4. **Local Bind Port** (ex: `13306`)
   - Porta local para o túnel (não conflitar com outras)

5. **SSH Key Path** (ex: `/path/to/key.pem`)
   - Caminho para chave privada SSH

6. **SSH Password** (opcional)
   - Senha SSH (se não usar chave)

#### Configuração do Banco de Dados (após o túnel):
1. **DB Host (Remoto)** (ex: `localhost`, `10.0.1.100`)
   - Host do banco **dentro da rede privada**

2. **DB Port (Remoto)** (ex: `3306`)
   - Porta do banco na rede privada

3. **Nome do Banco** (ex: `northwind`)
4. **Usuário DB** (ex: `root`)
5. **Senha DB** (ex: `secret123`)

#### Exemplo de SSH Tunnel:
```
=== SSH Tunnel ===
SSH Host: bastion.aws.com
SSH User: ubuntu
SSH Port: 22
Local Port: 13306
SSH Key: /home/airflow/.ssh/aws_key.pem

=== Database (via tunnel) ===
Host Remoto: localhost (ou IP privado 10.0.1.50)
Porta Remota: 3306
Banco: northwind
Usuário: root
Senha: secret
```

**Como funciona:**
```
Airflow → SSH Tunnel (porta 13306) → Bastion (bastion.aws.com:22) 
       → Rede Privada → MySQL (10.0.1.50:3306)
```

---

## 💡 Exemplos Práticos

### Exemplo 1: Ingestão Single-Tabela CSV
```yaml
Tipo de Origem: CSV Genérico
Upload de Arquivo: customers.csv
Tabela/Destino: customers
Função Pipeline: lib.medallion_pipeline.raw_to_medallion
Modo Multi-Tabela: ❌ Desmarcado
```

### Exemplo 2: Ingestão Single-Tabela MySQL
```yaml
Tipo de Origem: MySQL Northwind
Modo Multi-Tabela: ❌ Desmarcado
Tabela/Destino: customers

=== Conexão Direta ===
ID Conexão: mysql_northwind
Host: mysql
Porta: 3306
Banco: northwind
Usuário: root
Senha: root

Função Pipeline: lib.mysql_ingestion.mysql_to_medallion
```

### Exemplo 3: Ingestão Multi-Tabela MySQL
```yaml
Tipo de Origem: MySQL Northwind
Modo Multi-Tabela: ✅ Marcado
Máximo Tasks Paralelas: 16

=== Conexão Direta ===
ID Conexão: mysql_northwind
Host: mysql
Porta: 3306
Banco: northwind
Usuário: root
Senha: root

[Clicar em "Conectar"] → Lista tabelas disponíveis

Tabelas Selecionadas:
  ☑️ customers (91 linhas)
  ☑️ orders (830 linhas)
  ☑️ products (77 linhas)

Função Pipeline: lib.mysql_ingestion.mysql_to_medallion (auto-selecionado)
```

### Exemplo 4: SSH Tunnel para MySQL AWS
```yaml
Tipo de Origem: MySQL Northwind
Modo Multi-Tabela: ❌ Desmarcado
Tabela/Destino: sales

=== SSH Tunnel ===
SSH Host: bastion.empresa.com
SSH User: ec2-user
SSH Port: 22
Local Port: 13306
SSH Key: /home/airflow/.ssh/aws_production.pem
SSH Password: (deixar vazio)

DB Host Remoto: rds-mysql-prod.internal
DB Port Remoto: 3306
DB Name: production
DB User: etl_user
DB Password: ********

Função Pipeline: lib.mysql_ingestion.mysql_to_medallion
```

---

## 🎓 Boas Práticas

### ✅ DO's (Faça)
- ✅ Use nomes descritivos para DAG ID (`pipe_customers_daily`)
- ✅ Teste a conexão SQL antes de salvar (botão "Conectar" em multi-tabela)
- ✅ Para SQL, sempre use funções de ingestão (`mysql_to_medallion`)
- ✅ Configure max_parallel_tasks de acordo com recursos disponíveis
- ✅ Use SSH Tunnel para bancos em redes privadas/produção
- ✅ Documente a finalidade na descrição da DAG

### ❌ DON'Ts (Não faça)
- ❌ Não use espaços ou caracteres especiais no DAG ID
- ❌ Não use `raw_to_medallion` para fontes SQL (causará erro 404)
- ❌ Não selecione muitas tabelas sem ajustar `max_parallel_tasks`
- ❌ Não compartilhe senhas em texto plano (use secrets managers)
- ❌ Não crie múltiplas DAGs single-table quando pode usar multi-tabela
- ❌ Não esqueça de validar credenciais de SSH/Database antes de salvar

---

## 🔍 Troubleshooting

### Erro 404 "Not Found" em DAG SQL
**Causa**: Selecionou `raw_to_medallion` para fonte SQL  
**Solução**: Mudar para `mysql_to_medallion`

### Conexão SQL falha
**Verificar**:
1. Host/Porta corretos
2. Credenciais válidas
3. Firewall permite conexão
4. Para SSH: chave privada tem permissões 600 (`chmod 600 key.pem`)

### Tabelas não aparecem ao clicar "Conectar"
**Verificar**:
1. Credenciais corretas
2. Banco de dados existe
3. Usuário tem permissão `SELECT` nas tabelas
4. Verificar logs do CodeIgniter para detalhes do erro

### DAG não aparece no Airflow
**Verificar**:
1. DAG ID válido (sem espaços/caracteres especiais)
2. Arquivo Python gerado em `/src/dags/`
3. Reiniciar scheduler: `docker-compose restart airflow-scheduler`

---

## 📚 Documentação Relacionada

- [Arquitetura Medallion](DATALAKE_LAYERS.md)
- [Transformações Silver](TRANSFORMACOES_SILVER.md)
- [Delta Lake & Gold](DELTA_LAKE_IMPLEMENTATION.md)
- [Ingestão MySQL](MYSQL_INGESTION.md)
- [Troubleshooting](TroubleShooting.md)

---

**Última atualização**: 16 de dezembro de 2025
