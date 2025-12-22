# 🚀 Solução Híbrida: Apache Airflow + PostgreSQL + MinIO + Delta Lake

Este projeto integra três componentes principais para orquestração de dados e armazenamento:

- **Apache Airflow**: Orquestração de workflows
- **PostgreSQL**: Banco de dados relacional para metadados do Airflow
- **MinIO**: Armazenamento de objetos compatível com S3
- **Delta Lake**: Camada ACID sobre Data Lake com versionamento e time travel
- **Apache Atlas**: Catálogo de dados e governança (standalone)
- **Jupyter + PySpark (Lab Atlas)**: Ambiente interativo para análise e integração com Atlas

A base foi clonada do repositório do Adriano e adaptada para incluir os três serviços integrados. Os artefatos de código (DAGs, scripts, configurações) estão versionados neste repositório.

## 📚 Documentação Completa

Para entender a arquitetura Medallion (Bronze → Silver → Gold) e todas as transformações aplicadas, consulte o **[Índice de Documentação](DOCS_INDEX.md)**.

### 🎯 Guias de Uso

- **[📋 Guia da Interface Web](GUIDE_WEBAPP_CONFIG.md)**: Como preencher formulário de configuração de DAGs, multi-tabela, conexões SQL, validações

### Documentação por Camada

- **[Transformações Silver](TRANSFORMACOES_SILVER.md)**: Data Quality, validações, dicionário de dados
- **[Delta Lake & Gold](DELTA_LAKE_IMPLEMENTATION.md)**: Feature Engineering, Delta Lake, ML/BI integration

### Navegação Rápida por Caso de Uso

- **Machine Learning**: [Feature Engineering Guide](DELTA_LAKE_IMPLEMENTATION.md#-dicionário-de-dados---camada-gold-delta-lake)
- **Análise Temporal**: [Temporal Features](DELTA_LAKE_IMPLEMENTATION.md#5-features-temporais-date-columns)
- **Qualidade de Dados**: [Data Quality Dictionary](TRANSFORMACOES_SILVER.md#-dicionário-de-dados---camadas-silver)
- **Segmentação**: [Categorical Features](DELTA_LAKE_IMPLEMENTATION.md#4-features-categóricas-categorical-columns)
- **Detecção de Outliers**: [Statistical Features](DELTA_LAKE_IMPLEMENTATION.md#3-features-numéricas-numeric-columns)

---

## 💻 Configuração de Hardware

### ⚙️ Stack Completa

Esta solução executa simultaneamente:
- **Apache Airflow** (Webserver + Scheduler)
- **PostgreSQL** (metadados Airflow)
- **MySQL** (banco de dados de origem para ingestão)
- **MinIO** (armazenamento S3-compatible)
- **Apache Spark** (processamento distribuído)
- **Spark SQL Thrift Server** (camada semântica para BI)
- **Apache Atlas** (catálogo de dados com HBase + Solr embarcados) ⚠️ **Componente mais pesado**
- **Jupyter + PySpark** (ambiente interativo)
- **Delta Lake** (camada ACID sobre data lake)

#### 🐳 Serviços Docker

| Service ID | Nome do Serviço | Descrição |
|------------|----------------|-----------|
| `airflow-webserver` | Apache Airflow (Webserver) | Interface web para orquestração de workflows |
| `airflow-scheduler` | Apache Airflow (Scheduler) | Agendamento e execução de DAGs |
| `airflow-worker` | Airflow Worker | Executor Celery para processamento de tarefas |
| `postgres` | PostgreSQL | Banco de dados de metadados do Airflow |
| `mysql` | MySQL | Banco de dados de origem para ingestão |
| `minio` | MinIO | Armazenamento S3-compatible para data lake |
| `spark` | Apache Spark (Master) | Nó master do cluster de processamento distribuído |
| `spark-worker` | Spark Worker | Nó worker do cluster Spark |
| `spark-sql` | Spark SQL Thrift Server | Interface ODBC/JDBC para consultas SQL em Delta Lake |
| `atlas` | Apache Atlas | Catálogo de dados e governança (HBase + Solr) |
| `pyspark-aula` | Jupyter + PySpark (Lab) | Ambiente interativo para análise e experimentação |
| `redis` | Redis | Broker de mensagens para Celery Executor |
| `codeigniter-app` | CodeIgniter WebApp | Interface web para configuração de DAGs |

### 📊 Requisitos Mínimos (Desenvolvimento/Teste)

**Stack Completa (todos os serviços ativos):**
- **Disco:** 50 GB mínimo (recomendado 100 GB)
  - Imagens Docker: ~10-15 GB
  - Apache Atlas (HBase/Solr): ~10-15 GB
  - MinIO storage: ~10-20 GB
  - Logs e Delta Lake: ~10-20 GB
- **Memória:** 24 GB de RAM
  - Airflow: ~3 GB
  - PostgreSQL + MySQL: ~2 GB
  - MinIO: ~512 MB
  - **Apache Atlas: ~8 GB** (HBase + Solr)
  - Spark + PySpark: ~6 GB
  - Spark Thrift: ~3 GB
  - Sistema: ~2 GB
- **Processador:** 4 CPUs (ou vCPUs)

**Stack Reduzida (sem Atlas/Jupyter):**
- **Disco:** 40 GB
- **Memória:** 16 GB de RAM *(funcional mas com performance reduzida)*
- **Processador:** 2-4 CPUs

### 🚀 Requisitos Recomendados (Produção)

- **Disco:** 200+ GB (SSD recomendado)
- **Memória:** 32-64 GB de RAM
- **Processador:** 8+ CPUs
- **Rede:** 1 Gbps+

### 📝 Considerações Adicionais

**Otimização de Recursos:**
- Para ambientes limitados, desabilite o Apache Atlas durante desenvolvimento: não execute `--profile atlas`
- Configure limites de memória no docker-compose.yml para cada serviço
- Use swap apenas como fallback (pode degradar performance)

**Sistema Operacional:**
- **Linux:** Melhor performance e compatibilidade nativa
- **WSL2:** Funcional, mas configure memória adequada no `.wslconfig`
- **Windows:** Não recomendado para produção

**Ambientes Cloud:**
- **AWS (MWAA):** Custos variáveis por uso (a partir de ~$350/mês)
- **GCP/Azure:** Similar, com opções de managed services
- **Kubernetes:** Requer orquestração adicional, mas escala horizontalmente 

## 📁 Estrutura do Projeto

```
airflow-spark-minio-postgres/
├── docker compose.yml
├── Dockerfile
├── entrypoint.sh
└── src/
    └── dags/
        └── suas_dags.py
```

---

## ⚙️ Etapas de Implantação

### 1. Clonar o Projeto

```bash
git clone https://github.com/paulomnasc/datalake-air-flow.git
cd datalake-air-flow
```

> Substitua o link acima pelo repositório real, se necessário.

---

### 2. Build e Inicialização dos Containers

```bash
chmod +x entrypoint.sh
docker compose down --remove-orphans
docker compose build
docker compose up -d
# Subir serviços de catálogo e Jupyter (perfil atlas)
docker compose --profile atlas up -d atlas pyspark-aula
```

## 2.1 Verifique os containers ativos

```bash
docker ps
```


> Neste momento:
> - O **PostgreSQL** é instanciado com o banco `airflow`, usuário `airflow` e senha `airflow`
> - O **MinIO** é iniciado com o volume `/data` e console web na porta 9001
> - O **Airflow Webserver e Scheduler** são construídos e iniciados com base nas variáveis de ambiente

---

### 2.1 Passo opcional de verificação se o Airflow está Up (opercional)
```bash
docker exec -it airflow-webserver airflow dags list

```

### 3. Inicializar o Banco de Dados do Airflow (! Apenas novas instalações)

```bash
docker exec -it airflow-webserver airflow db init
```

> Esse comando aplica as migrações e cria as tabelas no banco `airflow` do PostgreSQL.

---

### 4. Criar Usuário Admin no Airflow (! Apenas novas instalações)

Via CLI:

```bash
docker exec -it airflow-webserver airflow users create \
  --username admin \
  --firstname Air \
  --lastname Flow \
  --role Admin \
  --email admin@example.com \
  --password admin
```

## Instalação de dependências

Este projeto utiliza o Airflow com integração ao MinIO via S3Hook. Para garantir que todos os operadores e hooks estejam disponíveis, instale os seguintes pacotes:

```bash
pip install apache-airflow-providers-amazon
```
⚠️ Atenção: o pacote `oci` requer `cryptography < 46.0.0`. Se houver conflito, recomenda-se usar:

```bash
pip install cryptography==45.0.0
```

Ou instalar o provedor Amazon sem dependências:

``` bash
pip install apache-airflow-providers-amazon --no-deps
```




---

## 🌐 Consoles Administrativas e Acesso

| Serviço             | Endereço de Acesso                     | Porta | Usuário / Senha           | Banco de Dados     | Observações                          |
|---------------------|----------------------------------------|-------|----------------------------|--------------------|--------------------------------------|
| **Airflow UI**      | [http://localhost:8085](http://localhost:8085) | 8085  | `admin` / `admin`          | —                  | Criado após `airflow db init` e `users create` |
| **MinIO Console**   | [http://localhost:9001](http://localhost:9001) | 9001  | `admin` / `admin123`| —                  | Interface web de armazenamento S3   |
| **MinIO API S3**    | `http://localhost:9000`                | 9000  | `admin` / `admin123`| —                  | Usado por boto3, S3Hook, etc.        |
| **PostgreSQL**      | via cliente externo ou terminal        | 5432  | `airflow` / `airflow`      | `airflow`          | Banco de metadados do Airflow        |
| **Apache Atlas**    | [http://localhost:21000](http://localhost:21000) | 21000 | `admin` / `admin`          | —                  | Catálogo de dados standalone (HBase/Solr embarcados) |
| **Jupyter Notebook**| [http://localhost:8888](http://localhost:8888) | 8888  | Token: `tavares1234`       | —                  | Lab de integração Atlas (pyspark-notebook) |
—
| **Spark SQL (Thrift)**      | via Conector JDBC/ODBC	10000        | 10000  | `nenum` / `nenhum`      | `nenhum`          | Ponto de Acesso para Power BI/Tableau (Camada Semântica sobre Delta Lake)        |

---

## Detalhes Importantes para o Spark SQL (Thrift)## 
Usuário/Senha: O Spark Thrift Server (a menos que configurado com Kerberos ou autenticação complexa, o que é raro em desenvolvimento local) geralmente não requer autenticação. Basta deixar em branco ou usar valores dummy na ferramenta de BI.Banco de Dados: Ele expõe o catálogo de tabelas do Spark/Hive. Você acessa as tabelas Delta diretamente com comandos SQL, como SELECT * FROM nome_da_tabela_delta.Conexão BI: Use o driver Spark Thrift JDBC/ODBC (ou driver Hive) para conectar ferramentas de BI. O host será localhost e a porta será 10000.
---

## 🧪 Testes de Acesso

### Airflow:

```bash
curl http://localhost:8085
```

### MinIO:

```bash
curl http://localhost:9001
```

### PostgreSQL via terminal:

```bash
docker exec -it postgres psql -U airflow -d airflow
```

### Caso precise reiniciar os serviços:

```bash
docker compose restart airflow-webserver airflow-scheduler minio mysql spark atlas
# Jupyter/PySpark (perfil atlas)
docker compose --profile atlas restart pyspark-aula
```

## Verificar os processo que estão rodando
```bash
docker compose ps
```


## ✅ Status Final

Com essa implantação:

- Airflow está orquestrando suas DAGs com interface acessível
- MinIO está disponível como armazenamento S3 local
- PostgreSQL está persistindo os metadados e acessível via terminal ou cliente gráfico
- Todos os serviços estão integrados e prontos para produção ou desenvolvimento local

### Configurando o Airflow para conectar no MinIO

## 🔗 Conexão Airflow com MinIO (`minio_conn`)

Para que o Airflow consiga enviar arquivos para o MinIO usando `S3Hook`, é necessário configurar uma conexão do tipo **Amazon S3** com os seguintes parâmetros:

### 📋 Detalhes da conexão

- **Conn Id**: `minio_conn`
- **Conn Type**: `Amazon Web Serices`
- **Login**: `admin` *(Access Key do MinIO)*
- **Password**: `admin123` *(Secret Key do MinIO)*

### ⚙️ Campo Extra (JSON)

```json
{
  "host": "http://minio:9000",
  "port": 9000,
  "secure": false
}
```

### Utilidades


**O comando mais direto para verificar se o Airflow carregou totalmente é:**

```bash
docker logs <nome_do_container_airflow>
```

Por exemplo, se estiver usando Docker Compose e seu serviço se chama `airflow`, você pode usar:

```bash
docker logs datalake-local_airflow_1
```

---

### 🧩 O que procurar nos logs

Você saberá que o Airflow carregou com sucesso quando encontrar mensagens como:

```
Scheduler started...
Starting webserver at http://0.0.0.0:8080
```

Essas mensagens indicam que tanto o *scheduler* quanto o *webserver* estão ativos e prontos.

---

### ✅ Alternativas úteis

Se estiver usando o Airflow fora de containers, você pode verificar com:

```bash
airflow webserver
```

ou

```bash
airflow scheduler
```

E observar no terminal se os serviços iniciam sem erros.
---

Navegar um recurso com interface amigável 

```bash
mc ls local/lab01/processed/raw/
```
