# 📋 Guia de Preenchimento da Interface de Configuração de DAGs

Este documento explica como preencher corretamente a interface web para criar configurações de DAGs de ingestão e transformação de dados.

## 📑 Índice

1. [Visão Geral](#-visão-geral)
2. [Interface do Usuário](#-interface-do-usuário)
3. [Campos Básicos da DAG](#-campos-básicos-da-dag)
4. [Configuração de Origem de Dados](#-configuração-de-origem-de-dados)
5. [Upload Múltiplo de Arquivos](#-upload-múltiplo-de-arquivos)
6. [Modo Multi-Tabela vs Single-Tabela](#-modo-multi-tabela-vs-single-tabela)
7. [Seleção de Função de Pipeline](#-seleção-de-função-de-pipeline)
8. [Conexão SQL (Direct vs SSH Tunnel)](#-conexão-sql-direct-vs-ssh-tunnel)
9. [Exemplos Práticos](#-exemplos-práticos)

---

## 🎯 Visão Geral

A interface permite configurar DAGs (Directed Acyclic Graphs) do Apache Airflow para:
- Ingestar dados de diferentes fontes (CSV, JSON, Parquet, MySQL, PostgreSQL)
- Processar uma ou múltiplas tabelas simultaneamente
- Fazer upload múltiplo de arquivos em lote
- Aplicar transformações através da arquitetura Medallion (RAW → Bronze → Silver → Gold)

---

## 🖥️ Interface do Usuário

### Mensagens de Feedback

As mensagens de sucesso e erro aparecem **centralizadas no topo da tela**, com as seguintes características:

#### ✅ Mensagem de Sucesso
- **Cor**: Verde (alert-success)
- **Posição**: Centralizada, 20px do topo
- **Estilo**: Fundo verde com sombra
- **Exemplo**: "✅ DAG criada com sucesso! 3 arquivo(s) serão processados em modo paralelo"

#### ⚠️ Mensagem de Erro/Aviso
- **Cor**: Amarelo (alert-warning)
- **Posição**: Centralizada, 20px do topo
- **Estilo**: Fundo amarelo com sombra
- **Exemplo**: "❌ Erro: Preencha todos os campos obrigatórios"

**Observação**: As mensagens ficam fixas no topo mesmo ao rolar a página, garantindo visibilidade total.

### Campos Dinâmicos

A interface adapta os campos visíveis baseado na seleção do usuário:

#### Para Fontes de Arquivo (CSV/JSON/Parquet)
- ✅ Upload de arquivo único **OU** Upload múltiplo
- ✅ Checkbox "Modo Multi-Upload" 
- ❌ Checkbox "Multi-Tabela" (oculto)
- ❌ Campos de conexão SQL (ocultos)

#### Para Fontes SQL (MySQL/PostgreSQL)
- ✅ Checkbox "Multi-Tabela"
- ✅ Campos de conexão SQL (abas Direct/SSH)
- ✅ Botão "🔌 Conectar e Listar Tabelas" (sempre visível)
- ❌ Upload de arquivo (oculto)

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
3. **Opção 1: Upload Único**
   - Clique em "Escolher arquivo" e selecione o arquivo local
   - O arquivo será armazenado em MinIO/RAW
4. **Opção 2: Upload Múltiplo** (veja seção dedicada abaixo)
   - Marque o checkbox ☑️ **Modo Multi-Upload**
   - Arraste múltiplos arquivos ou selecione vários de uma vez
   - Todos os arquivos serão processados por uma única DAG

### Para Parquet
1. Selecione **Parquet**
2. Aparecerá o campo **Caminho no MinIO (RAW)**
3. Digite o caminho: `raw/seu_arquivo.parquet` ou `raw/pasta/arquivo.parquet`

### Para MySQL/PostgreSQL
1. Selecione **MySQL Northwind** ou **PostgreSQL**
2. Aparecerão os **campos de conexão SQL** (veja seção específica abaixo)
3. Você pode configurar conexão **Direta** ou via **SSH Tunnel**

---

## 📤 Upload Múltiplo de Arquivos

### 🎯 Quando Usar

Use o **Modo Multi-Upload** quando você precisa:
- Processar múltiplos arquivos JSON/CSV de uma mesma fonte
- Fazer upload em lote de arquivos relacionados
- Criar uma única DAG que processa vários arquivos em paralelo ou sequencial

### 🔧 Como Ativar

1. Selecione fonte de dados **CSV Genérico** ou **JSON**
2. Marque o checkbox ☑️ **Modo Multi-Upload de Arquivos (Batch Processing)**
3. A interface mudará para o modo de upload múltiplo

### 📋 Interface de Upload Múltiplo

#### Área de Drag & Drop
```
┌─────────────────────────────────────────┐
│   📤 Arraste arquivos aqui              │
│      ou clique para selecionar          │
│                                         │
│   Aceita: .csv, .json                   │
└─────────────────────────────────────────┘
```

#### Opções de Processamento

**1. Modo de Processamento em Lote**
- 🔄 **Paralelo** (Padrão)
  - Processa múltiplos arquivos simultaneamente
  - Mais rápido para grandes volumes
  - Recomendado quando os arquivos são independentes
  
- 📝 **Sequencial**
  - Processa um arquivo por vez
  - Recomendado quando há dependências ou limitações de recursos

**2. Máximo de Arquivos Paralelos**
- Valor entre 1 e 16 (padrão: 4)
- Controla quantos arquivos são processados ao mesmo tempo
- Ajuste baseado nos recursos disponíveis

**3. Selecionar Pasta Completa**
- ☑️ Marque para fazer upload de uma pasta inteira
- Todos os arquivos da pasta serão enviados
- Útil para conjuntos de dados relacionados

### 📁 Estrutura no MinIO

Os arquivos são salvos com seus **nomes originais** (sem timestamp) no MinIO:

```
MinIO Bucket: data-lake-raw
├── raw/
│   └── pipe-albuns/          ← DAG ID como pasta
│       ├── Track.json        ← Nome original do arquivo
│       ├── Album.json        ← Nome original do arquivo
│       └── Artist.json       ← Nome original do arquivo
```

### 🗄️ Configuração no Banco de Dados

Uma **única configuração** é criada para processar todos os arquivos:

```sql
dag_id: pipe-albuns
source_filename: raw/pipe-albuns/  ← Apenas o caminho da pasta
description: Batch processing - pasta com 3 arquivo(s)
max_parallel_tasks: 4
is_active: 1

-- Campos SQL/SSH são NULL para fontes de arquivo
sql_connection_id: NULL
sql_host: NULL
sql_port: NULL
ssh_host: NULL
```

### ⚙️ Como o Airflow Processa

1. **Detecção de Pasta**: O Airflow verifica se `source_filename` termina com `/`
2. **Listagem Dinâmica**: Lista todos os arquivos em `raw/pipe-albuns/` no MinIO
3. **Criação de Tasks**: Cria uma task para cada arquivo encontrado
4. **Execução**: Processa em paralelo ou sequencial conforme configurado

### 💡 Exemplo Completo de Upload Múltiplo

```yaml
=== Configuração ===
Tipo de Origem: JSON
DAG ID: pipe-albuns
Descrição: Ingestão de dados musicais
Modo Multi-Upload: ✅ Marcado

=== Upload ===
Arquivos Selecionados:
  📄 Track.json (45 KB)
  📄 Album.json (12 KB)
  📄 Artist.json (8 KB)

Modo de Processamento: 🔄 Paralelo
Máximo Paralelo: 4

=== Resultado ===
✅ 3 arquivos salvos em: raw/pipe-albuns/
✅ DAG criada: pipe-albuns
✅ 3 tasks serão executadas em paralelo

=== MinIO ===
raw/pipe-albuns/Track.json   ← Arquivo 1
raw/pipe-albuns/Album.json   ← Arquivo 2
raw/pipe-albuns/Artist.json  ← Arquivo 3

=== Airflow ===
DAG: pipe-albuns
├── Task 1: process_Track.json
├── Task 2: process_Album.json
└── Task 3: process_Artist.json
```

### ⚠️ Validações e Restrições

#### ✅ Validações Automáticas
- Apenas arquivos `.csv` e `.json` são aceitos
- Nome do arquivo é validado (extensão real, não MIME type)
- Todos os arquivos devem passar na validação

#### ❌ Campos Incompatíveis
Quando **Multi-Upload** está ativado:
- ❌ Campos SQL são definidos como `NULL`
- ❌ Campos SSH são definidos como `NULL`
- ❌ `is_multi_table` é sempre `0`
- ✅ `max_parallel_tasks` contém o valor configurado

### 🔄 Atualização de DAG Multi-Upload

Ao editar uma DAG com multi-upload:
1. O sistema **deleta** a configuração antiga
2. Faz **novo upload** dos arquivos selecionados
3. Cria **nova configuração** com o mesmo `dag_id`

⚠️ **Atenção**: Os arquivos antigos no MinIO são substituídos se tiverem o mesmo nome.

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

2. **Host** (ex: `mysql`, `192.168.1.10`, `db.empresa.com`)
   - Endereço do servidor de banco de dados
   - **💡 Dica Importante**:
     - Use `localhost` para o MySQL local do Docker (será traduzido automaticamente para `mysql`)
     - Use IP ou hostname para bancos **externos** (ex: `203.0.113.45`, `rds.amazonaws.com`)
     - Qualquer valor diferente de `localhost`/`127.0.0.1` será usado **exatamente como digitado**

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
Host: localhost          ← Será automaticamente traduzido para "mysql" (container Docker)
Porta: 3306
Banco: northwind
Usuário: root
Senha: root
```

#### Exemplo de Conexão Externa:
```
ID da Conexão: mysql_aws_rds
Host: mydb.abc123.us-east-1.rds.amazonaws.com  ← Usado exatamente como digitado
Porta: 3306
Banco: production
Usuário: etl_user
Senha: ********
```

### 🔘 Botão "Conectar e Listar Tabelas"

O botão **🔌 Conectar e Listar Tabelas** aparece automaticamente quando:
- ✅ Você seleciona uma fonte SQL (MySQL, PostgreSQL, etc.)
- ✅ Está disponível tanto em modo **Single-Tabela** quanto **Multi-Tabela**

#### Funcionalidades:
1. **Testa a Conexão**: Valida as credenciais antes de salvar
2. **Lista Tabelas**: Mostra todas as tabelas disponíveis no banco
3. **Metadados**: Exibe número de linhas e tamanho de cada tabela
4. **Seleção Múltipla**: Permite selecionar várias tabelas (modo multi-tabela)

#### Mensagens de Feedback:
```
✅ Sucesso: "✅ 12 tabelas encontradas"
❌ Erro: "❌ Falha ao conectar ao MySQL: Access denied for user 'root'@'mysql'"
⚠️ Aviso: "⚠️ Nenhuma tabela encontrada"
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

### Exemplo 4: Upload Múltiplo de Arquivos JSON
```yaml
Tipo de Origem: JSON
DAG ID: pipe-albuns
Modo Multi-Upload: ✅ Marcado

=== Upload ===
Arquivos:
  - Track.json
  - Album.json
  - Artist.json

Modo de Processamento: 🔄 Paralelo
Máximo Paralelo: 4

=== Resultado ===
MinIO: raw/pipe-albuns/Track.json
       raw/pipe-albuns/Album.json
       raw/pipe-albuns/Artist.json

Banco: source_filename = "raw/pipe-albuns/"
       max_parallel_tasks = 4
       sql_* = NULL (todos os campos SQL)
       ssh_* = NULL (todos os campos SSH)

Airflow: 3 tasks paralelas processando cada arquivo
```

### Exemplo 5: SSH Tunnel para MySQL AWS
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
- ✅ Digite `localhost` no campo Host para conexões MySQL locais do Docker
- ✅ Use IP/hostname real para bancos de dados externos
- ✅ Teste a conexão SQL antes de salvar (botão "🔌 Conectar")
- ✅ Para SQL, sempre use funções de ingestão (`mysql_to_medallion`)
- ✅ Configure max_parallel_tasks de acordo com recursos disponíveis
- ✅ Use Upload Múltiplo para processar lotes de arquivos relacionados
- ✅ Use SSH Tunnel para bancos em redes privadas/produção
- ✅ Documente a finalidade na descrição da DAG

### ❌ DON'Ts (Não faça)
- ❌ Não use espaços ou caracteres especiais no DAG ID
- ❌ Não use `raw_to_medallion` para fontes SQL (causará erro 404)
- ❌ Não selecione muitas tabelas sem ajustar `max_parallel_tasks`
- ❌ Não misture campos SQL com fontes de arquivo (sistema limpa automaticamente)
- ❌ Não compartilhe senhas em texto plano (use secrets managers)
- ❌ Não crie múltiplas DAGs single-table quando pode usar multi-tabela ou multi-upload
- ❌ Não esqueça de validar credenciais de SSH/Database antes de salvar

---

## 🔍 Troubleshooting

### Mensagens não aparecem ou estão mal posicionadas
**Causa**: CSS pode estar sendo sobrescrito  
**Solução**: Mensagens estão com `position:fixed` e `z-index:9999`, sempre centralizadas no topo

### Erro "No such file or directory" ao conectar MySQL
**Causa**: Tentando conectar via `localhost` dentro do container Docker  
**Solução**: 
- ✅ Digite `localhost` no campo Host (sistema traduz automaticamente para `mysql`)
- ✅ Para bancos externos, use o IP/hostname real

### Botão "Conectar" não aparece
**Verificar**:
1. Tipo de fonte selecionado é SQL? (MySQL, PostgreSQL)
2. O botão aparece tanto em single-tabela quanto multi-tabela
3. Recarregue a página se mudou de fonte não-SQL para SQL

### Erro 404 "Not Found" em DAG SQL
**Causa**: Selecionou `raw_to_medallion` para fonte SQL  
**Solução**: Mudar para `mysql_to_medallion`

### Upload múltiplo salva campos SQL incorretamente
**Causa**: Bug corrigido - campos SQL não estavam sendo limpos  
**Solução**: Sistema agora define automaticamente todos os campos SQL/SSH como `NULL` para fontes de arquivo

### Conexão SQL falha
**Verificar**:
1. Host/Porta corretos (use `localhost` para MySQL do Docker)
2. Credenciais válidas
3. Firewall permite conexão
4. Para SSH: chave privada tem permissões 600 (`chmod 600 key.pem`)

### Tabelas não aparecem ao clicar "Conectar"
**Verificar**:
1. Credenciais corretas
2. Banco de dados existe
3. Usuário tem permissão `SELECT` nas tabelas
4. Verificar logs: `docker exec codeigniter-app tail -f /var/www/html/writable/logs/log-$(date +%Y-%m-%d).log`

### Arquivos de upload múltiplo não processam no Airflow
**Verificar**:
1. `source_filename` termina com `/` (ex: `raw/pipe-albuns/`)
2. Arquivos existem no MinIO bucket `data-lake-raw`
3. `factory_master.py` tem lógica para detectar pastas e listar arquivos

### DAG não aparece no Airflow
**Verificar**:
1. DAG ID válido (sem espaços/caracteres especiais)
2. Registro existe no banco: `SELECT * FROM dag_configurations WHERE dag_id = 'seu-dag-id'`
3. `is_active = 1`
4. Reiniciar scheduler: `docker-compose restart airflow-scheduler`

---

## 📚 Documentação Relacionada

- [Arquitetura Medallion](DATALAKE_LAYERS.md)
- [Transformações Silver](TRANSFORMACOES_SILVER.md)
- [Delta Lake & Gold](DELTA_LAKE_IMPLEMENTATION.md)
- [Ingestão MySQL](MYSQL_INGESTION.md)
- [Troubleshooting](TroubleShooting.md)

---

**Última atualização**: 20 de dezembro de 2025

---

## 📝 Histórico de Alterações

### v2.1 - 20/12/2025
- ✅ Adicionada funcionalidade de **Upload Múltiplo de Arquivos**
- ✅ Mensagens de sucesso/erro agora **centralizadas e fixas no topo**
- ✅ Botão "Conectar" agora aparece para **todas as fontes SQL** (não só multi-tabela)
- ✅ Correção automática de `localhost` → `mysql` para conexões Docker
- ✅ Campos SQL/SSH automaticamente limpos (`NULL`) para fontes de arquivo
- ✅ Adicionadas dicas visuais no campo Host sobre localhost vs IPs externos
- ✅ Documentação completa de upload múltiplo, validações e estrutura MinIO

### v2.0 - 16/12/2025
- Versão inicial da documentação consolidada

