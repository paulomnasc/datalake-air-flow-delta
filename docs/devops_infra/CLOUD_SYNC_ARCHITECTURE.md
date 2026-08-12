# Arquitetura de Sincronização em Nuvem (Strategy Pattern) e AWS Data Discovery

Este documento fornece a especificação técnica e o modelo operacional da nova arquitetura de sincronização em nuvem (`cloud_sync`), reestruturada com base no **Provider Pattern (Strategy)**, além dos detalhes de implementação do **Data Discovery automático** (AWS Glue Data Catalog) gerado via MyDataFlow.

---

## 1. Visão Geral da Arquitetura

Antigamente o arquivo `cloud_sync.py` era monolítico, com inúmeras estruturas condicionais (`if/elif/else`) lidando com múltiplas APIs simultaneamente (S3, Azure Blobs). 
Para garantir escalabilidade, adotamos o padrão **Strategy**. Dessa forma, o orquestrador base apenas invoca uma fábrica (`ProviderFactory`) que instanciará e acionará de forma independente as lógicas isoladas de cada nuvem. 

Isso traz um duplo benefício:
- Desacoplar SDKs de terceiros (`boto3`, `azure-storage-blob`).
- Possibilitar rotinas complexas isoladas (Ex: a Injeção Nativa de Metadados via AWS Glue).

## 2. Estrutura de Diretórios e Componentes

```text
src/dags/lib/
├── cloud_sync.py             # Entrypoint da DAG do Airflow. Apenas inicializa a Factory e chama .sync_files()
└── providers/                # Módulo de estratégias plugáveis
    ├── __init__.py           
    ├── base_provider.py      # Abstract Base Class obrigando o contrato do método .sync_files()
    ├── provider_factory.py   # Roteador responsável por decidir qual classe será chamada (Factory Method)
    ├── aws_provider.py       # Lógica desacoplada do Amazon S3 + AWS Glue Catalog
    └── azure_provider.py     # Lógica desacoplada do Microsoft Azure Blob Storage
```

### Componentes Técnicos
1. **`lib/providers/base_provider.py`**: Define a interface genérica onde todo novo provedor obrigatoriamente deve conter o método assíncrono padrão: 
   `def sync_files(self, local_hook, bucket, files, config)`
2. **`lib/providers/provider_factory.py`**: Interage semanticamente com o banco de dados e UX do MyDataFlow (ex: ao ler `cloudDestType=aws` nos *kwargs* convertidos, instancia dinamicamente a `AWSCloudProvider`).
3. **`lib/cloud_sync.py`**: Fica reduzido a literalmente capturar o array de destinos (Camadas Delta, Gold ou Silver do MinIO) e mandar o provedor despachar o lote.

---

## 3. Data Discovery Automático (Deep Dive no AWS_Provider)

A revolução operacional no `aws_provider.py` consiste em dar um "Bypass" aos atrasos e custos de catalogação dos Glue Crawlers. Assumindo a figura de arquitetura ativa, o Airflow toma para si a responsabilidade do Governance.

### 3.1. Procedimento Lógico:
Ao executar o upload em lote de arquivos para o S3 destino, o provedor:
1. **Captura o Schema (Inferência via PyArrow):** Faz o parser binário no topo do aquivo Parquet (`pq.ParquetFile(local_path).schema_arrow`) enquanto ele se encontra residente na zona transitória (.tmp).
2. **Tradução (Pyarrow -> Hive)**: Invoca a função `map_pyarrow_to_hive_type(pa_type)`. Ela iterage varrendo o schema de métricas PyArrow para tipos que o **Amazon Athena** leia nativamente, convertendo, por exemplo, o vetor lógico `is_int64` para a string `bigint`.
3. **Check-in via API (Boto3 Glue Client):** Aciona via SDK uma instrução de metadados à API da AWS contendo a declaração completa das colunas, definindo input formats de Big Data (`MapredParquetInputFormat`) com engine de leitura (`ParquetHiveSerDe`).
4. **Resilição:** Trata via SDK AWS caso a Database (`medataflow_db`) ou a tabela não existam. Mapeia a tabela atachando-a diretamente à URL de pastas no S3 (`s3://meubucket/gold/tabela/`). Se a tabela for nova, ele usa o *Create*, se ocorrer evolução de Schema, executa *Update*.

---

## 4. Manual Operacional: Como acoplar um novo Provedor de Cloud (Ex: Google Cloud Storage)

Graças ao padrão Strategy, se futuramente for necessário incorporar suporte ao GCP Storage (Google Cloud), a implantação será puramente incremental. Não será necessário mexer no `cloud_sync.py` minimamente.

**Siga os Passos Padrões:**
1. Crie um novo script na pasta `lib/providers/gcp_provider.py`.
2. Herde a interface e declare a classe `class GCPCloudProvider(BaseCloudProvider):`.
3. Programe sua lógica consumindo o pacote `google-cloud-storage` dentro de `def sync_files(...)`.
4. Importe sua nova classe no arquivo `lib/providers/provider_factory.py`.
5. Modifique a condicional da Fábrica injetando:
   `elif tipo == 'gcp': return GCPCloudProvider()`

---

## 5. Troubleshooting & Logs Mapeados

Como testar, ler e entender a robustez desta arquitetura diretamente pelos *Logs* do orquestrador Apache Airflow:

### Cenário de Sucesso
No output da etapa `push_to_external_cloud`, você verá sequencialmente:
> `[CLOUD_SYNC][AWS] Sincronizando: gold/api-place/file.parquet`
> `[CLOUD_SYNC][GLUE] Schema inferido localmente do Parquet 'gold/api-place/file.parquet'`
> `[CLOUD_SYNC][AWS] ✅ Sucesso! 1 arquivos encaminhados para S3`
> `[GLUE_CATALOG] Declarando inédita tabela 'api-place'...`
> `[GLUE_CATALOG] ✅ Data Discovery estabelecido! Athena já consegue consultar...`

### Cenários de Erro Tratado
Nossa arquitetura não derrubará o pipeline se a injeção do Metadado falhar, já que ela aceita o S3 como a "Fonte da Verdade".
Se a UX repassar as *AWS Keys* do usuário sob políticas de IAM incorretas (usuário do aluno na AWS atachou a Policy de *AmazonS3FullAccess*, mas se esqueceu do AWS Glue), a console gerará logs passivos como:
> `[GLUE_CATALOG] Falta de permissões credenciais IAM para glue:CreateTable.`
 **Solução Orientada:** O painel administrativo saberá instantaneamente que o aluno deve ser instruído a atachar apólices `glue:GetTable` e `glue:CreateTable` ao usuário utilizado no IAM Key do MyDataFlow.
