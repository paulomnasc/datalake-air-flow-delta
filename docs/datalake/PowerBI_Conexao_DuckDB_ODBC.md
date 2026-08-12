# 🔗 Conexão do Power BI Desktop ao DuckDB via ODBC

Guia completo para conectar o Power BI Desktop ao DuckDB usando o driver ODBC, permitindo consultar dados em Parquet, Delta Lake, MinIO (S3) e bancos locais.

## 📋 Visão Geral

- **DuckDB**: Engine SQL leve em-processo (in-process), sem servidor separado.
- **ODBC**: Protocolo padrão de acesso a dados; o Power BI suporta nativamente.
- **Fluxo**: Power BI → Driver ODBC → DuckDB (arquivo `.duckdb` local/rede) → Dados (Parquet/S3/Local).
- **Sincronização automática**: DAG Airflow mantém views atualizadas diariamente.

## 🤖 Sincronização Automática via Airflow (Recomendado)

### Por que usar a DAG?

✅ **Sem configuração manual**: Views criadas/atualizadas automaticamente  
✅ **Sempre atualizado**: Roda diariamente ou sob demanda  
✅ **Monitoramento**: Logs e status no Airflow UI  
✅ **Multi-datasets**: Sincroniza bronze, silver e delta automaticamente  

### Configuração Rápida

1. **Ative a DAG no Airflow**:
   - Acesse: http://localhost:8085
   - Procure: `sync_duckdb_views`
   - Toggle para **ON**
   - Clique em **Trigger DAG** (play ▶️) para executar agora

2. **Aguarde execução** (1-2 minutos):
   - Acompanhe logs em tempo real no Airflow
   - Arquivo gerado: `/home/cblna123456/datalake-air-flow/ddb/datalake.duckdb`

3. **Views criadas automaticamente**:
   - `orders_bronze` → `s3://lab01/processed/raw/orders/*.parquet`
   - `customers_bronze` → `s3://lab01/processed/raw/customers/*.parquet`
   - `orders_silver` → `s3://lab01/processed/silver/orders/*.parquet`
   - `customers_silver` → `s3://lab01/processed/silver/customers/*.parquet`
   - `orders_delta` → `s3://lab01/delta/orders/*.parquet`
   - `customers_delta` → `s3://lab01/delta/customers/*.parquet`

4. **Personalize datasets** (opcional):
   - Edite: `src/dags/sync_duckdb_views.py`
   - Modifique lista `DATASETS` com seus paths
   - Salve e a DAG será recarregada automaticamente

### Agenda de Execução

- **Automático**: Diariamente às 2h AM (horário do servidor)
- **Manual**: Clique "Trigger DAG" no Airflow UI quando necessário
- **Customizar**: Edite `schedule_interval` na DAG para alterar frequência

### Monitoramento

**Verificar última execução**:
1. Airflow UI → DAGs → `sync_duckdb_views`
2. Verifique status: ✅ Success / ❌ Failed
3. Clique em uma run → logs detalhados

**Logs mostram**:
- Quantas views foram criadas
- Número de registros em cada view
- Erros (se houver)

---

## ✅ Pré-requisitos

- **Power BI Desktop** instalado (Windows, versão 64-bit recomendada).
- **Sistema operacional**: Windows 10 ou superior.
- **Driver ODBC do DuckDB** instalado (veja passo 1).
- **Acesso a dados**: Um arquivo `.duckdb` acessível (local, rede compartilhada ou arquivo Parquet/S3 que o DuckDB possa consultar).

---

## 🛠️ Passo 1: Instalar o Driver ODBC do DuckDB

### Windows (Recomendado)

1. Acesse o site oficial do DuckDB:
   - https://duckdb.org/docs/guides/odbc/installation
   - Ou baixe direto: https://github.com/duckdb/duckdb/releases

2. Baixe o instalador ODBC (64-bit):
   - Procure por algo como `duckdb_odbc_x64.msi` ou semelhante.

3. Execute o instalador `.msi` e siga as instruções de instalação padrão.

4. Verifique a instalação:
   - Abra **Administrador ODBC** (Painel de Controle → Ferramentas Administrativas → Fontes de Dados ODBC, ou `odbcad32.exe` na barra de pesquisa).
   - Guia: **Drivers**
   - Você deve ver um driver chamado `DuckDB` listado.

### macOS / Linux (Alternativa)

Se estiver em macOS/Linux:
```bash
# macOS (via Homebrew)
brew install duckdb

# Linux (compile from source ou use pacote distro)
# Consulte: https://duckdb.org/docs/installation/index
```

---

## 📝 Passo 2: Configurar DSN (Data Source Name)

Um DSN aponta para um banco de dados específico. Você pode criar um para cada projeto.

### Windows: Criar um DSN via Administrador ODBC

1. Abra **Administrador ODBC** (`odbcad32.exe`).

2. Guia: **DSN do Usuário** (ou **DSN do Sistema** se quiser compartilhado).

3. Clique em **Adicionar**.

4. Selecione **DuckDB** como driver.

5. Preencha os campos:

   | Campo | Valor | Descrição |
   |-------|-------|-----------|
   | **Data Source Name** | `DuckDB_BI` (ou escolha um nome) | Nome amigável que aparecerá no Power BI |
   | **Database** | `C:\data\datalake.duckdb` (exemplo) | Caminho completo para um arquivo `.duckdb` |
   | **Read Only** | ☐ (desmarque) | Se o Power BI precisar fazer cache/temp tables |

6. **Teste a conexão** (botão "Test" se disponível).

7. Clique **OK** para salvar.

### Alternativa: Conexão sem DSN (SQL Native Client)

Se preferir não criar um DSN, você pode conectar diretamente no Power BI, mas requer a pasta/arquivo acessível no caminho exato.

---

## 💾 Passo 3: Preparar o Arquivo DuckDB

### Opção A: Criar um arquivo `.duckdb` vazio (Desenvolvimento)

```bash
# Na máquina com DuckDB instalado ou via terminal
duckdb /path/to/datalake.duckdb
```

Isso criará um arquivo `datalake.duckdb` que o DSN apontará.

### Opção B: Criar tabelas/views a partir de Parquet/S3 (Mais Comum)

Dentro do DuckDB, você pode:

```sql
-- Criar uma VIEW que consulta um Parquet local
CREATE VIEW customers AS
SELECT * FROM read_parquet('C:\data\assets\customers\*.parquet');

-- Ou consultar S3/MinIO
CREATE VIEW orders_s3 AS
SELECT * FROM read_parquet('s3://lab01/processed/raw/orders/*.parquet')
WITH (
  's3_endpoint' = 'http://minio:9000',
  's3_access_key_id' = 'admin',
  's3_secret_access_key' = 'admin123'
);
```

Essas views ficarão persistidas no arquivo `.duckdb`.

### Opção C: Compartilhar via SMB/NFS (Equipe)

Coloque o arquivo `.duckdb` em uma pasta compartilhada:
```
\\servidor\compartilhado\bi\datalake.duckdb
```

E aponte o DSN para esse caminho UNC.

---

## 🔌 Passo 4: Conectar no Power BI Desktop

### 1. Abrir Power BI Desktop

1. Inicie **Power BI Desktop**.
2. Clique em **Obter Dados** (Get Data) ou use o menu **Arquivo → Obter Dados**.

### 2. Selecionar a Origem ODBC

1. Na caixa de busca, procure por **ODBC** ou role até encontrá-lo.
2. Clique em **ODBC** e depois **Conectar**.

   ![Obter Dados → ODBC](./imgs/powerbi_odbc_select.png) *(ilustrativo)*

### 3. Escolher o DSN

Uma caixa de diálogo aparecerá:
- **Data source name (DSN)**: Selecione `DuckDB_BI` (ou o nome que criou).
- Deixe **Advanced options** em branco (a menos que tenha SQL customizado).
- Clique **OK**.

### 4. Credenciais (se necessário)
Se o arquivo `.duckdb` estiver em rede protegida, o Power BI mostrará um prompt de autenticação. Preencha:

- **Método**: Windows (ou Basic, conforme sua rede).
- **Usuário**: `DOMINIO\usuario` ou o usuário de rede.
- **Senha**: senha de rede.
- Marque **Lembrar minhas credenciais** se quiser evitar novos prompts.

> Não é necessário informar credenciais do PostgreSQL aqui; esta conexão é apenas ODBC para o arquivo DuckDB.

### 5. Navigator - Escolher Tabelas/Views

A janela **Navigator** abrirá listando todas as tabelas e views no banco DuckDB:
- Selecione as tabelas desejadas.
- Visualize os dados clicando **Load** ou **Edit** (para transformações Power Query).

### 6. Carregar Dados

Clique **Load** para importar os dados no Power BI.

---

## 🎯 Consultas SQL Avançadas

Se o Navigator não mostrar exatamente o que precisa, use **SQL Native Query** no Power BI:

### No Power BI (Power Query Editor)

1. Guia **Home** → **New Source** → **ODBC**.
2. Escolha o DSN.
3. Em **Advanced options**, escreva uma consulta SQL:

```sql
SELECT 
  customer_id,
  CAST(order_date AS DATE) AS order_date,
  total_amount
FROM read_parquet('C:\data\orders\*.parquet')
WHERE EXTRACT(YEAR FROM order_date) = 2024
```

4. Clique **OK**.

### Exemplos de Consultas Úteis

#### Consultar Parquet Local
```sql
SELECT * FROM read_parquet('C:\data\assets\*.parquet');
```

#### Ler S3/MinIO (com autenticação inline)
```sql
SELECT * 
FROM read_parquet(
  's3://lab01/processed/raw/customers/*.parquet',
  s3_access_key_id='admin',
  s3_secret_access_key='admin123',
  s3_endpoint='http://minio:9000',
  s3_url_style='path'
);
```

#### Agrupar/Agregar no DuckDB antes de enviar para Power BI
```sql
SELECT 
  category,
  COUNT(*) as total_products,
  AVG(price) as avg_price,
  MAX(price) as max_price
FROM read_parquet('s3://lab01/products/*.parquet')
GROUP BY category;
```

---

## 🔐 Segurança e Boas Práticas

1. **Arquivo `.duckdb` em rede compartilhada**:
   - Use NTFS com permissões adequadas.
   - Limitar a cópia do arquivo ou usar VPN.

2. **Credenciais S3/MinIO**:
   - **NÃO** coloque credenciais hardcoded em queries visíveis.
   - Considere usar perfis AWS ou variáveis de ambiente no servidor DuckDB (se escalável).

3. **Backup**:
   - Faça backup regular do arquivo `.duckdb`.
   - Versionize o arquivo em Git (se pequeno) ou em backup central.

4. **Performance**:
   - Para grandes datasets, crie indexes ou partições no DuckDB antes de expor ao Power BI.
   - Use `DirectQuery` (se suportado) para dados muito grandes, em vez de `Import`.

---

## 🐛 Troubleshooting

### Erro: "Driver não encontrado"

**Causa**: Driver ODBC do DuckDB não está instalado.

**Solução**:
```bash
# Verificar drivers disponíveis (Windows PowerShell)
Get-OdbcDriver | Select-Object Name

# Se DuckDB não aparecer, reinstale o driver
```

### Erro: "Arquivo não encontrado"

**Causa**: O arquivo `.duckdb` especificado no DSN não existe ou o caminho está incorreto.

**Solução**:
- Verifique o caminho no DSN.
- Use um caminho UNC completo se em rede: `\\servidor\compartilhado\datalake.duckdb`.
- Teste com um arquivo local primeiro.

### Erro: "Acesso negado" ao arquivo de rede

**Causa**: Permissões insuficientes na pasta compartilhada.

**Solução**:
```bash
# No Windows, defina permissões NTFS
icacls "\\servidor\compartilhado\bi" /grant "usuario:%USERNAME%":F
```

### Erro: "S3 connection failed"

**Causa**: Credenciais S3/MinIO incorretas ou endpoint inválido.

**Solução**:
- Teste manualmente em um cliente DuckDB:
  ```sql
  SELECT * FROM read_parquet('s3://lab01/test/*.parquet',
    s3_access_key_id='admin',
    s3_secret_access_key='admin123',
    s3_endpoint='http://minio:9000',
    s3_url_style='path'
  ) LIMIT 1;
  ```
- Valide host/porta do MinIO.

### Power BI importa lentamente

**Causa**: Arquivo `.duckdb` grande ou consulta não otimizada.

**Solução**:
- Use `DirectQuery` em vez de `Import` (se suportado).
- Pré-agrege dados no DuckDB (crie views resumidas).
- Comprima o arquivo com ZIP antes de compartilhar.

---

## 📚 Recursos Adicionais

- [Documentação DuckDB ODBC](https://duckdb.org/docs/guides/odbc)
- [Power BI - Conectar via ODBC](https://learn.microsoft.com/en-us/power-bi/connect-data/desktop-connect-to-data)
- [DuckDB - SQL Reference](https://duckdb.org/docs/sql/introduction)
- [MinIO para S3 Local](https://docs.min.io/)

---

## ✅ Próximos Passos

1. ✓ Instalar driver ODBC DuckDB.
2. ✓ Criar um DSN.
3. ✓ Preparar um arquivo `.duckdb` com views/tabelas.
4. ✓ Conectar do Power BI.
5. ✓ Criar relatórios e dashboards.

**Sucesso!** 🎉 Você agora tem uma pipeline SQL leve e flexível para BI.
