# Documentação de Testes - Plataforma de Dados

Esta documentação detalha a estrutura e execução dos testes para os dois componentes principais do sistema: o Painel de Controle (Backend PHP) e a Ingestão de Dados (Python/Airflow).

---

## 1. Testes de Backend (CodeIgniter 4)

Estes testes validam a lógica de negócio, persistência no banco de dados e endpoints da API utilizados pela interface do usuário (UX).

### Arquivo: `ConfigControllerTest.php`
**Caminho:** `src/codeigniter-app/tests/app/Controllers/ConfigControllerTest.php`

#### O que é testado:
- **Criação de Pipelines:** Valida se uma nova configuração de DAG é inserida corretamente no banco.
- **Edição e Seleção de Tabelas:** Verifica se a atualização de um pipeline e a escolha de tabelas no banco de dados relacional são persistidas adequadamente.
- **Remoção:** Garante que a exclusão de configurações funciona e limpa os registros relacionados.
- **Validações de Erro:** Testa casos como nomes de DAG duplicados.

#### Como Executar:
Acesse a pasta do CodeIgniter e execute o PHPUnit via vendor:
```bash
cd src/codeigniter-app
php vendor/bin/phpunit tests/app/Controllers/ConfigControllerTest.php
```

---

## 2. Testes de Ingestão (Python / Airflow)

Estes testes garantem que a lógica de extração de dados das fontes (MySQL/PostgreSQL) para o Data Lake está funcionando conforme o esperado.

### Arquivo: `test_mysql_ingestion.py`
**Caminho:** `src/dags/tests/test_mysql_ingestion.py`

#### O que é testado:
- **Extração Relacional:** Simula a conexão com um banco de dados e verifica se a query `SELECT *` é gerada.
- **Criação de Arquivos RAW:** Valida se os dados extraídos são salvos em formato `.csv` temporariamente.
- **Upload para MinIO:** Verifica se o arquivo é carregado para o bucket correto no caminho `raw/{dag_id}/`.
- **Lógica Multi-Tabela:** Simula o comportamento do sistema quando múltiplas tabelas são selecionadas, garantindo que todas passem pelo processo de ingestão.

#### Como Executar:
Certifique-se de estar na raiz do projeto e use o ambiente virtual:
```bash
# Definir o PYTHONPATH para que os módulos locais sejam encontrados
export PYTHONPATH=$PYTHONPATH:$(pwd)/src/dags:$(pwd)/src/dags/lib

# Executar o teste via python ou pytest
./venv/bin/python3 src/dags/tests/test_mysql_ingestion.py
```

---

## 3. Teste de Pipeline Medallion (v2)

Este teste valida a orquestração de camadas (Bronze, Silver, Gold, Delta) da nova arquitetura de pipeline.

### Arquivo: `test_pipeline_v2.py`
**Caminho:** `src/dags/tests/test_pipeline_v2.py`

#### O que é testado:
- **Orquestração de Camadas:** Garante uma sequência de execução garantida e sincronizada de todas as etapas do pipeline Medallion.
- **Parametrização via CLI:** Permite testar o comportamento do pipeline para uma `dag_id` específica fornecida pelo terminal.

#### Como Executar:
Você pode passar o ID da DAG e o proprietário (bucket) diretamente no comando:
```bash
# Definir o PYTHONPATH
export PYTHONPATH=$PYTHONPATH:$(pwd)/src/dags:$(pwd)/src/dags/lib

# Executar para uma DAG específica
./venv/bin/python3 src/dags/tests/test_pipeline_v2.py --dag_id test_dag_001 --owner lab01
```

---

## Requisitos para os Testes
- **Backend:** Servidor PHP 8.1+ e banco de dados de teste configurado em `app/Config/Database.php`.
- **Python:** Ambiente virtual configurado com as dependências instaladas (`pandas`, `apache-airflow-providers-mysql`, etc.).
