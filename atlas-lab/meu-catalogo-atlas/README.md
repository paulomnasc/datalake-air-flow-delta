# Catálogo de Dados com Apache Atlas

Sistema de catalogação automática de dados que integra PostgreSQL com Apache Atlas para descoberta de metadados e criação de linhagem de dados.

## Funcionalidades

- **Cliente Atlas**: Integração completa com API REST do Apache Atlas
- **Extrator PostgreSQL**: Descoberta automática de metadados de tabelas
- **Catalogador Automático**: Criação hierárquica de entidades no Atlas
- **Relatórios**: Geração de relatórios em JSON e CSV

## Estrutura do Projeto

```
meu-catalogo-atlas/
├── README.md                 # Documentação
├── requirements.txt          # Dependências Python
├── config.py                # Configurações
├── atlas_client.py          # Cliente Atlas (Tarefa 1)
├── postgres_extractor.py    # Extrator PostgreSQL (Tarefa 2)
├── data_catalogger.py       # Catalogador Automático (Tarefa 3)
├── discovery_report.py      # Relatórios (Tarefa 4)
└── main.py                  # Script principal
```

## Pré-requisitos

1. **Ambiente Docker** rodando:
   - Apache Atlas (localhost:21000)
   - PostgreSQL Northwind (localhost:2001)

2. **Python 3.8+** com dependências:
   ```bash
   pip install -r requirements.txt
   ```

## Como Usar

### 1. Executar Catalogação Completa
```bash
python main.py
```

### 2. Usar Componentes Individualmente

#### Cliente Atlas
```python
from atlas_client import AtlasClient
from config import ATLAS_CONFIG

atlas = AtlasClient(**ATLAS_CONFIG)
results = atlas.search_entities("hive_table")
```

#### Extrator PostgreSQL
```python
from postgres_extractor import PostgreSQLExtractor
from config import POSTGRES_CONFIG

extractor = PostgreSQLExtractor(**POSTGRES_CONFIG)
metadata = extractor.extract_tables_metadata()
```

#### Relatórios
```python
from discovery_report import DiscoveryReport

report = DiscoveryReport(atlas)
report.generate_report("meu_relatorio")
report.print_summary()
```

## Arquitetura

### Fluxo de Dados
1. **Extração**: PostgreSQLExtractor conecta ao Northwind e extrai metadados
2. **Catalogação**: DataCatalogger cria entidades hierárquicas no Atlas
3. **Relatórios**: DiscoveryReport gera estatísticas e exporta dados

### Entidades Criadas
- **Database**: `northwind_postgres@cluster1`
- **Tables**: Uma para cada tabela do Northwind
- **Columns**: Todas as colunas com tipos e posições
- **Relationships**: Baseados em foreign keys

## Configuração

Edite `config.py` para ajustar conexões:

```python
ATLAS_CONFIG = {
    "url": "http://localhost:21000",
    "username": "admin", 
    "password": "admin"
}

POSTGRES_CONFIG = {
    "host": "localhost",
    "port": 2001,
    "database": "northwind",
    "user": "postgres",
    "password": "postgres"
}
```

## Saídas Geradas

### Logs
- Processo completo de catalogação
- Estatísticas de criação de entidades
- Tratamento de erros

### Relatórios
- `discovery_report.json`: Estatísticas completas em JSON
- `discovery_report.csv`: Dados tabulares para análise
- Console: Resumo executivo

## Tratamento de Erros

- **Conexão Atlas**: Retry automático e mensagens claras
- **Conexão PostgreSQL**: Validação de credenciais
- **Criação de Entidades**: Log detalhado de falhas
- **Geração de Relatórios**: Fallback para dados parciais

## Melhorias Futuras

- Classificação automática de dados sensíveis
- Interface web para visualização
- Suporte a múltiplos bancos de dados
- Integração com ferramentas de BI
- Testes automatizados

## Autor

Desenvolvido como parte do exercício prático de DataOps com Apache Atlas.