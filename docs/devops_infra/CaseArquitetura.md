## 🧭 Arquitetura de Pipeline de Dados

Este projeto implementa uma arquitetura de ingestão e transformação de dados com três zonas principais:

### 🔹 1. **Raw Zone** (`lab01/processed/raw`)
- **Fonte:** Banco de dados MySQL local
- **Processo:** Dados são extraídos e armazenados em formato bruto (ex: JSON ou CSV) no MinIO
- **Objetivo:** Preservar os dados originais para auditoria e reprocessamento

### 🔹 2. **Trusted Zone** (`lab01/processed/trusted`)
- **Processo:** Os dados da raw zone são validados, limpos e transformados
- **Formato:** Persistência em arquivos **Parquet**, otimizados para leitura analítica
- **Objetivo:** Garantir qualidade e estrutura dos dados para consumo confiável

### 🔹 3. **Refined Zone** (PostgreSQL)
- **Processo:** Os dados da trusted zone são carregados no PostgreSQL
- **Modelo:** Estrutura de **datamart analítico**, com tabelas fato e dimensão
- **Objetivo:** Disponibilizar os dados para BI, dashboards e análises avançadas

---

## 🔄 Fluxo da Pipeline

```plaintext
[MySQL local]
      │
      ▼
[Airflow DAG: ingestão]
→ Salva em MinIO (raw zone)
      │
      ▼
[Airflow DAG: validação + transformação]
→ Converte para Parquet (trusted zone)
      │
      ▼
[Airflow DAG: carga analítica]
→ Insere no PostgreSQL (refined zone)
```

---

## 🛠️ Tecnologias Utilizadas

| Componente     | Função                                      |
|----------------|---------------------------------------------|
| **Airflow**    | Orquestração das DAGs                       |
| **MinIO**      | Armazenamento de objetos (raw/trusted)      |
| **MySQL**      | Fonte de dados local                        |
| **PostgreSQL** | Camada analítica (refined zone)             |
| **Pandas**     | Transformações e conversão para Parquet     |
| **SQLAlchemy** | Integração com PostgreSQL                   |

---

## 🧠 Benefícios da Arquitetura

- Separação clara entre dados brutos, confiáveis e analíticos
- Versionamento e rastreabilidade dos dados
- Otimização de leitura com Parquet
- Facilidade de integração com ferramentas de BI e ML
