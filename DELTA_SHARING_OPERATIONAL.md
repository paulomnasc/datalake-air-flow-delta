# Guia Operacional - Delta Sharing Server

## 🎯 Introdução ao Delta Sharing

O **Delta Sharing** é um protocolo aberto para o compartilhamento seguro e controlado de tabelas do **Delta Lake** (armazenadas na camada Gold do MinIO/S3) para ferramentas analíticas de terceiros (como Power BI, Python Jupyter Notebooks e Apache Spark) sem a necessidade de expor credenciais brutas de infraestrutura (chaves de acesso do S3/MinIO) e sem impacto ou concorrência com o processamento do Banco de Dados PostgreSQL analítico.

---

## 🏗️ Arquitetura de Consumo

```
                       ┌─────────────────────────┐
                       │      MinIO / S3         │
                       │  (Camada Gold Delta)    │
                       └────────────▲────────────┘
                                    │ (Leitura Direta)
┌──────────────┐       ┌────────────┴────────────┐       ┌──────────────┐
│ Analista BI  │       │  Delta Sharing Server   │       │  Cientista   │
│ (Power BI)   │ ◄───► │      (Porta 28085)      │ ◄───► │   de Dados   │
│              │ (JWT) │                         │ (JWT) │   (Python)   │
└──────────────┘       └─────────────────────────┘       └──────────────┘
```

---

## 🛠️ Configuração e Inicialização

O servidor do Delta Sharing é implantado como um container Docker integrado à nossa rede de dados.

### 1. Arquivo de Configuração do Servidor

As definições de tabelas compartilhadas ficam no arquivo [`delta-sharing-server.yaml`](./delta-sharing/delta-sharing-server.yaml):

```yaml
version: 1
host: "0.0.0.0"
port: 8080

authorization:
  bearerToken: "dbtSharingToken2026Secure"  # Token de autenticação

shares:
  - name: "gold_share"
    schemas:
      - name: "analytics"
        tables:
          - name: "dim_usuarios"
            location: "s3a://{bucket}/gold/{clean_dag_id}/dim_usuarios_delta"  # Mapeado dinamicamente em tempo de execução
          - name: "dim_cursos"
            location: "s3a://{bucket}/gold/{clean_dag_id}/dim_cursos_delta"
          - name: "fato_vendas"
            location: "s3a://{bucket}/gold/{clean_dag_id}/fato_vendas_delta"
```

### 2. Executando o Container

Adicione o bloco abaixo no `docker-compose.yml` e suba o serviço:

```bash
docker compose up -d delta-sharing-server
```

---

## 👥 Roteiro de Uso para o Usuário Admin

Como administrador da plataforma MyDataFlow, você é responsável por conceder o acesso aos parceiros e analistas de BI.

### Passo 1: Localizar as Credenciais do Recipiente
Na pasta `./delta-sharing/`, o sistema disponibiliza o arquivo de perfil do recipiente chamado [`gold_share_recipient.share`](./delta-sharing/gold_share_recipient.share). O arquivo possui o seguinte conteúdo JSON:

```json
{
  "shareCredentialsVersion": 1,
  "endpoint": "http://localhost:28085/share",
  "bearerToken": "dbtSharingToken2026Secure"
}
```

### Passo 2: Enviar o Arquivo para o Usuário Destinatário
Envie este arquivo de perfil `.share` para o analista de BI ou cientista de dados. Este arquivo é tudo o que eles precisam para se conectar aos dados de forma segura.

---

## 🔌 Conectando do Power BI (Analistas de BI)

O Power BI possui um conector nativo para o Delta Sharing que facilita o consumo:

1. Abra o **Power BI Desktop**.
2. Clique em **Obter Dados** → **Mais...**
3. Pesquise por **Delta Sharing** e selecione o conector.
4. No campo de conexão, o Power BI solicitará o arquivo de perfil. Selecione o arquivo `gold_share_recipient.share` que você recebeu do administrador.
5. O Power BI lerá as configurações e listará as tabelas disponíveis (`dim_usuarios`, `dim_cursos` e `fato_vendas`).
6. Selecione as tabelas desejadas e clique em **Carregar**.

---

## 🐍 Conectando de Notebooks Python (Cientistas de Dados)

Cientistas de dados podem importar e interagir com os dados compartilhados usando a biblioteca `delta-sharing`:

### 1. Instalação
```bash
pip install delta-sharing
```

### 2. Código Python para Leitura
```python
import delta_sharing

# 1. Caminho para o arquivo de perfil recebido
profile_file = "gold_share_recipient.share"

# 2. Criar cliente do Delta Sharing
client = delta_sharing.SharingClient(profile_file)

# 3. Listar tabelas compartilhadas
shares = client.list_shares()
for share in shares:
    print(f"Share: {share.name}")
    for schema in client.list_schemas(share):
        print(f"  Schema: {schema.name}")
        for table in client.list_tables(schema):
            print(f"    Table: {table.name}")

# 4. Carregar tabela diretamente como um DataFrame Pandas
# Formato do path: <profile_file>#<share_name>.<schema_name>.<table_name>
table_url = f"{profile_file}#gold_share.analytics.fato_vendas"
df = delta_sharing.load_as_pandas(table_url)

print(df.head())
```
