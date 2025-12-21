
# 🧠 Datalake com Airflow, Spark e MinIO

Este projeto implementa um **Data Lake orquestrado pelo Apache Airflow**, utilizando **Spark** para processamento distribuído e **MinIO** como armazenamento compatível com S3.  
O objetivo é demonstrar o fluxo completo de ingestão e transformação de dados partindo de uma base **MySQL** até a camada **Delta Lake**, passando por estágios de limpeza, validação e enriquecimento.

---

## 🗺️ Arquitetura de Alto Nível

Fluxo principal:

```
MySQL → RAW (CSV) → TRUSTED (Parquet) → REFINED (Parquet enriquecido) → DELTA (Delta Table)
```

Cada etapa é controlada por uma DAG do Airflow.

---

## ⚙️ Componentes Principais

| Componente | Função |
|-------------|--------|
| **Airflow** | Orquestração das etapas de ingestão e transformação. |
| **Spark** | Processamento distribuído e carga na camada Delta Lake. |
| **MinIO** | Armazenamento S3-like das camadas Raw, Trusted e Refined. |
| **MySQL** | Fonte de dados transacional de origem. |

---

## 📦 Estrutura das Camadas do Datalake

### 1️⃣ RAW Zone — *Camada Bruta*
**DAG:** `ingestao_sgbd_mysql_para_raw`  
**Arquivo:** `ingest_raw_mysql_to_minio.py`

- Extrai dados do banco **MySQL** (`lista_revisao`).
- Exporta as tabelas `customers`, `orders` e `products` como CSV.
- Envia para o **MinIO** (bucket `lab01`) no diretório:
  ```
  processed/raw/<tabela>.csv
  ```
- Formato: **CSV**
- Frequência: diária (`@daily`)

---

### 2️⃣ TRUSTED Zone — *Camada Confiável*
**DAG:** `transform_raw_to_trusted_parquet`  
**Arquivo:** `validate_to_trusted_to_minio.py`

- Lê arquivos CSV da camada Raw.
- Padroniza nomes de colunas (minúsculas, sem espaços).
- Remove duplicatas e converte para **Parquet**.
- Gera nome versionado com timestamp.
- Salva em:
  ```
  processed/trusted/<tabela>_<timestamp>.parquet
  ```
- Formato: **Parquet**
- Frequência: diária (`@daily`)

---

### 3️⃣ REFINED Zone — *Camada Refinada*
**DAG:** `refinar_customers`  
**Arquivo:** `refine_to_refined_to_minio.py`

- Lê arquivos Parquet da zona Trusted (`customers`).
- Enriquecimento e curadoria:
  - Preenche dados ausentes (`creditlimit`, `state`, etc.).
  - Cria campos derivados (`nome_completo`, `valor_cliente`).
  - Classifica faixa de crédito (`Baixo`, `Médio`, `Alto`, `Premium`).
  - Simula conversão cambial e calcula `credito_brl`.
- Salva o resultado em:
  ```
  processed/refined/customers_<timestamp>.parquet
  ```
- Formato: **Parquet**
- Frequência: diária (`@daily`)

---

### 4️⃣ DELTA Zone — *Camada Delta Lake*
**DAG:** `ingestao_delta_clientes`  
**Arquivo:** `ingestao_delta_clientes.py`

- Executa o script Spark:
  ```
  docker exec spark spark-submit /opt/spark-apps/ingest_delta_clientes.py
  ```
- Lê dados da camada Refined.
- Grava em formato **Delta Lake**.
- Frequência: sob demanda (execução manual).

---

## 🔗 Fluxo Completo das DAGs

| Ordem | DAG | De | Para | Formato |
|-------|-----|----|------|----------|
| 1 | `ingestao_sgbd_mysql_para_raw` | MySQL | Raw | CSV |
| 2 | `transform_raw_to_trusted_parquet` | Raw | Trusted | Parquet |
| 3 | `refinar_customers` | Trusted | Refined | Parquet |
| 4 | `ingestao_delta_clientes` | Refined | Delta | Delta Table |

---

## 🧭 Diagrama de Fluxo

![Data Pipeline Flowchart](A_flowchart_in_the_image_illustrates_a_data_pipeli.png)

---

## 🧩 Estrutura Recomendada do Projeto

```
datalake-airflow-spark-minio/
├── dags/
│   ├── ingest_raw_mysql_to_minio.py
│   ├── validate_to_trusted_to_minio.py
│   ├── refine_to_refined_to_minio.py
│   └── ingestao_delta_clientes.py
├── spark-apps/
│   └── ingest_delta_clientes.py
├── docker compose.yml
├── README.md
└── requirements.txt
```

---

## 💡 Observações
- Todas as conexões com o MinIO utilizam o **aws_conn_id = `minio_conn`** configurado no Airflow.
- O bucket principal é **`lab01`**.
- As DAGs podem ser ajustadas para outras tabelas do MySQL apenas alterando a lista de tabelas em `ingest_raw_mysql_to_minio.py`.
- A camada Delta pode ser integrada a ferramentas analíticas (como SparkSQL, Databricks ou Athena com Delta I/O).

---

**Autor:** Paulo Nascimento  
**Última atualização:** outubro/2025
````

