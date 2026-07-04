# 📘 Visão Geral da Solução

Este documento apresenta uma visão geral sobre o tipo de solução implementada neste projeto e o papel da interface web (WebApp).

---

## 🎯 Tipo de Solução

**Plataforma de Data Lake com Orquestração de Dados e Governança**

Esta é uma **solução de Data Lake completa** baseada na **arquitetura Medallion (Bronze-Silver-Gold)**, implementando um pipeline moderno de engenharia de dados.

### Componentes Principais:

1. **Apache Airflow** - Orquestração de workflows e pipelines ETL/ELT
2. **Delta Lake** - Camada ACID sobre Data Lake com versionamento e time travel
3. **Apache Spark** - Processamento distribuído de dados
4. **MinIO** - Armazenamento de objetos (compatível S3)
5. **Apache Atlas** - Catálogo de dados e governança/lineage
6. **PostgreSQL** - Metadados do Airflow
7. **MySQL** - Fonte de dados transacionais
8. **Power BI** - Integração para visualização e analytics
9. **WebApp CodeIgniter** - Interface visual para configuração de pipelines
10. **Delta Sharing Server** - Compartilhamento de dados analíticos seguro com BI/Cientistas sem expor credenciais de S3/MinIO

### Arquitetura Medallion:

- **🥉 Bronze (Raw)**: Dados brutos sem transformação (CSV)
- **🥈 Silver (Trusted)**: Dados limpos, validados e padronizados (Parquet)
- **🥇 Gold (Refined)**: Dados agregados e enriquecidos para analytics/ML (Delta Lake)

### Casos de Uso:

- Ingestão de dados de fontes relacionais
- Transformações de Data Quality
- Feature Engineering para Machine Learning
- Análise de dados com governança e lineage
- Integração com ferramentas de BI (Metabase / Power BI)
- Compartilhamento seguro de tabelas Gold em tempo real (Delta Sharing)

### Características:

Esta é uma solução **enterprise-grade** containerizada (Docker) para ambientes de teste/produção, com documentação completa em português.

---

## 🌐 WebApp - Interface de Gerenciamento

A webapp é uma **interface web de gerenciamento e configuração** desenvolvida em **PHP CodeIgniter** que serve como **painel de controle visual** para simplificar a criação e configuração de pipelines de dados. Ela elimina a necessidade de editar código manualmente.

### 🎯 Funcionalidades Principais:

#### 1. 📋 Criação de DAGs via Formulário Visual
- Interface amigável para configurar DAGs do Airflow sem tocar em Python
- Gera automaticamente os arquivos YAML de configuração
- Validação de nomenclatura e compatibilidade

#### 2. 📤 Upload de Arquivos para o Data Lake
- Upload de arquivos CSV, Parquet e JSON direto para a camada RAW (MinIO)
- Eliminação de processos manuais de transferência
- Integração automática com os pipelines de transformação

#### 3. 🔌 Configuração de Conexões SQL
- Interface para configurar conexões MySQL e PostgreSQL
- Suporte a conexões diretas ou via SSH Tunnel
- Teste de conectividade antes de salvar
- Armazenamento seguro de credenciais

#### 4. 📊 Modo Multi-Tabela
- Botão "Conectar" que lista todas as tabelas disponíveis no banco
- Seleção visual de múltiplas tabelas com checkboxes
- Exibição de metadados (quantidade de linhas, tamanho)
- Processamento paralelo configurável

#### 5. ⚙️ Seleção de Pipelines
- Escolha visual das transformações (RAW → Bronze → Silver → Gold)
- Pipelines pré-configurados:
  - `RAW → Medallion (Bronze/Silver/Gold)` - Para arquivos CSV/Parquet
  - `MySQL → Medallion` - Ingestão completa de fontes SQL
  - `MySQL → RAW` - Apenas extração de dados
  - Camadas individuais (Bronze, Silver, Gold)
- Validação automática de compatibilidade entre fonte e pipeline

#### 6. ✅ Validações Automáticas
- Verifica se a configuração é compatível
- Alertas em tempo real sobre erros de configuração
- Avisos quando função não é adequada para o tipo de origem
- Validação de campos obrigatórios

### 🔗 Acesso:

**URL**: `http://localhost:8088`

**Credenciais**: Configuradas no ambiente Docker

### 💡 Benefício Principal:

A webapp essencialmente **democratiza** o uso do Data Lake, permitindo que usuários sem conhecimento em Python ou Airflow possam criar pipelines de dados complexos através de uma interface intuitiva e visual.

---

## 📚 Documentação Relacionada

Para mais detalhes sobre como usar a WebApp, consulte:

- **[📋 Guia da Interface Web](GUIDE_WEBAPP_CONFIG.md)**: Tutorial completo de preenchimento do formulário
- **[Arquitetura do Data Lake](DATALAKE_LAYERS.md)**: Estrutura de camadas e fluxo de dados
- **[Transformações Silver](TRANSFORMACOES_SILVER.md)**: Data Quality e validações
- **[Delta Lake & Gold](DELTA_LAKE_IMPLEMENTATION.md)**: Feature Engineering e ML
- **[Guia Operacional - Delta Sharing](DELTA_SHARING_OPERATIONAL.md)**: Compartilhamento de dados da camada Gold
- **[Índice de Documentação](DOCS_INDEX.md)**: Visão geral de toda a documentação

---

**Data de Criação**: 20 de Dezembro de 2025
