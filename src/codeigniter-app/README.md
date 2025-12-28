

````markdown
# 🚀 Smart-Tables - WebApp CodeIgniter + Datalake

Este projeto integra uma aplicação web CodeIgniter 4 com componentes para orquestração de dados e armazenamento:

- **CodeIgniter 4**: Framework PHP para WebApp
- **Apache Airflow**: Orquestração de workflows
- **PostgreSQL**: Banco de dados relacional para metadados do Airflow
- **MySQL**: Banco de dados da aplicação web
- **MinIO**: Armazenamento de objetos compatível com S3
- **PHPMailer**: Sistema de envio de emails SMTP

---

## 📧 Configuração de Email (SMTP)

### Sistema de Cadastro de Usuários

A aplicação possui um **sistema robusto de confirmação de email** para novos cadastros:

#### 🔄 Fluxo de Cadastro:
1. Usuário preenche formulário em `/sigInUsuario`
2. Sistema envia email de confirmação com token único
3. Usuário clica no link recebido
4. Email é confirmado e login é liberado

#### ⚙️ Configuração SMTP (Produção)

No arquivo `.env`, as seguintes variáveis controlam o envio de emails:

```env
# SMTP Production Credentials
smtp_host = mail.smarttables.x10.mx
smtp_port = 587
smtp_username = admin@smarttables.x10.mx
smtp_password = kJ#212394
smtp_nome_remetente = Smart_Tables
smtp_secure = tls
```

#### 📝 Notas Importantes:

- **Ambiente**: Use configurações de PRODUÇÃO para emails profissionais
- **TLS**: Conexão segura na porta 587
- **Domínio próprio**: Emails enviados de `admin@smarttables.x10.mx`
- **Confirmação obrigatória**: Usuários só conseguem fazer login após confirmar email
- **Token único**: Cada cadastro gera um token aleatório armazenado no banco

#### 🔐 Segurança:

- Flag `email_confirmado` no banco de dados
- Validação de token antes de permitir acesso
- Transações com rollback em caso de erro
- Proteção contra spam e contas falsas

---

## 🌐 Acessos ao Sistema

### URL de Acesso Principal

- **Produção**: `http://smarttables.x10.mx/`
- **Desenvolvimento**: `http://localhost:8088/`

### Rotas Principais

| Rota | Descrição |
|------|-----------|
| `/sigInUsuario` | Formulário de cadastro de novo usuário |
| `/loginUsuario` | Tela de login |
| `/confirmEmail?token=[TOKEN]` | Confirmação de email (via link no email) |
| `/home` | Dashboard após login |

A base foi clonada do repositório do Adriano e adaptada para incluir os três serviços integrados. Os artefatos de código (DAGs, scripts, configurações) estão versionados neste repositório.

---

## Configuração mínima de hardware

Os requisitos mínimos de hardware para um Apache Airflow básico são de 10GB de HD, 4GB de RAM e 2 CPUs. 

É importante notar que, para uma implantação em produção, pode ser necessário mais hardware, dependendo da carga de trabalho, e que o Airflow também pode rodar em ambientes como Kubernetes ou nuvem, com requisitos que variam de acordo com a plataforma escolhida. 
Requisitos mínimos (para ambientes de teste/desenvolvimento):

Disco: 10 GB de HD.
Memória: 4 GB de RAM.
Processador (CPU): 2 CPUs (ou VCPUs). 
Requisitos recomendados e considerações adicionais:
Banco de dados: O Airflow precisa de um banco de dados de metadados para funcionar. 
É recomendado um banco de dados como PostgreSQL ou MySQL. 

Ambiente virtual: Para evitar conflitos de dependências, é altamente recomendável usar um ambiente virtual (como o venv ou conda). 
Sistema operacional: Embora o Airflow possa ser instalado em Windows, ele funciona melhor em um ambiente tipo Unix, como o Linux, que pode ser executado nativamente ou através do Subsistema do Windows para Linux (WSL). 

Ambientes de nuvem: Se for usar serviços como Amazon MWAA, os requisitos de hardware são variáveis e o pagamento é por uso. Os custos e recursos dependem do nível de uso. 

---

## 🐋 Instalação do Docker (Pré-requisito)

É essencial ter o Docker Engine instalado e rodando para construir e orquestrar os serviços. Escolha a opção mais adequada ao seu ambiente:

### Opção A: Windows Subsystem for Linux (WSL 2)

Esta é a opção **recomendada** para usuários de Windows, pois oferece a melhor performance e integração.

1.  **Instale o Docker Desktop no Windows:**
    * Baixe e execute o instalador do **Docker Desktop for Windows** no site oficial.
    * Durante a instalação, mantenha a opção **"Use WSL 2 instead of Hyper-V"** ativada.
2.  **Configurar a Integração com o WSL 2 (CRUCIAL) 🔑:**
    * Inicie o Docker Desktop.
    * Vá em **Settings (Configurações) > Resources (Recursos) > WSL Integration**.
    * Garanta que as seguintes opções estejam **ativadas** (marcadas):
        * **"Enable integration with my default WSL distro"**
        * O checkbox para a sua distribuição **Ubuntu** (na seção "Enable integration with additional distros").
    * **O que isso faz:** Esta integração é o que permite que o motor Docker, rodando no Windows/VM, **enxergue automaticamente o sistema de arquivos do seu Ubuntu**, eliminando a necessidade de mapear manualmente as pastas do projeto (como `~/datalake-air-flow`).
    * Clique em **Apply & Restart** para aplicar as mudanças.
3.  **Verifique no Terminal WSL:** Abra seu terminal WSL e confirme a instalação:
    ```bash
    docker run hello-world
    ```

### Opção B: Ubuntu Puro (Linux Nativo)

Siga os passos padrão para instalar o Docker Engine no seu ambiente Ubuntu nativo:

1.  **Atualizar e Instalar Dependências:**
    ```bash
    sudo apt update
    sudo apt install apt-transport-https ca-certificates curl gnupg lsb-release -y
    ```
2.  **Adicionar Repositório Docker:**
    ```bash
    sudo mkdir -p /etc/apt/keyrings
    curl -fsSL [https://download.docker.com/linux/ubuntu/gpg](https://download.docker.com/linux/ubuntu/gpg) | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    ```
3.  **Configurar Repositório:**
    ```bash
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] [https://download.docker.com/linux/ubuntu](https://download.docker.com/linux/ubuntu) $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
    ```
4.  **Instalar Docker Engine e Ferramentas:**
    ```bash
    sudo apt update
    sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker compose-plugin -y
    ```
5.  **Adicionar Usuário ao Grupo `docker` (Opcional, mas Recomendado):** Para usar `docker` sem `sudo`:
    ```bash
    sudo usermod -aG docker $USER
    ```
    *É necessário reiniciar o terminal ou fazer logoff/login para que esta alteração tenha efeito.*

---

## 📁 Estrutura do Projeto

````

airflow-spark-minio-postgres/
├── docker compose.yml
├── Dockerfile
├── entrypoint.sh
└── src/
└── dags/
└── suas\_dags.py

````

---

## ⚙️ Etapas de Implantação

### 1. Clonar o Projeto

```bash
git clone [https://github.com/paulomnasc/datalake-air-flow.git](https://github.com/paulomnasc/datalake-air-flow.git)
cd datalake-air-flow
````

> Substitua o link acima pelo repositório real, se necessário.

-----

### 2\. Build e Inicialização dos Containers

1.  **Ajuste de Permissões e Build Inicial:**

    ```bash
    chmod +x entrypoint.sh
    docker compose down --remove-orphans
    docker compose build
    ```

    > **⚠️ SOLUÇÃO DE CONTORNO (CodeIgniter):** Se o *build* falhar ou a imagem `codeigniter-app` não aparecer na sua lista de imagens, a construção pode ter sido interrompida.

    > Para garantir que este serviço seja criado corretamente, force a construção novamente, focando apenas nele:

    > ```bash
    > docker compose build codeigniter-app
    > ```

2.  **Inicialização dos Contêineres:**

    ```bash
    docker compose up -d
    ```

3.  **Verifique os contêineres ativos:**

    ```bash
    docker ps
    ```

> Neste momento:
>
>   - O **PostgreSQL** é instanciado com o banco `airflow`, usuário `airflow` e senha `airflow`
>   - O **MinIO** é iniciado com o volume `/data` e console web na porta 9001
>   - O **Airflow Webserver e Scheduler** são construídos e iniciados com base nas variáveis de ambiente

-----

### 2.1 Passo opcional de verificação se o Airflow está Up (opercional)

```bash
docker exec -it airflow-webserver airflow dags list

```

### 3\. Inicializar o Banco de Dados do Airflow (\! Apenas novas instalações)

```bash
docker exec -it airflow-webserver airflow db init
```

> Esse comando aplica as migrações e cria as tabelas no banco `airflow` do PostgreSQL.

-----

### 4\. Criar Usuário Admin no Airflow (\! Apenas novas instalações)

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

```bash
pip install apache-airflow-providers-amazon --no-deps
```

-----

## 🌐 Consoles Administrativas e Acesso

| Serviço | Endereço de Acesso | Porta | Usuário / Senha | Banco de Dados | Observações |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **WebApp CodeIgniter** | http://smarttables.x10.mx/ | 8088 | Cadastro via `/sigInUsuario` | `lista_revisao2` | Confirmação via email obrigatória |
| **Airflow UI** | [http://localhost:8085](https://www.google.com/search?q=http://localhost:8085) | 8085 | `admin` / `admin` | — | Criado após `airflow db init` e `users create` |
| **MinIO Console** | [http://localhost:9001](https://www.google.com/search?q=http://localhost:9001) | 9001 | `admin` / `admin123`| — | Interface web de armazenamento S3 |
| **MinIO API S3** | `http://localhost:9000` | 9000 | `admin` / `admin123`| — | Usado por boto3, S3Hook, etc. |
| **PostgreSQL** | via cliente externo ou terminal | 5432 | `airflow` / `airflow` | `airflow` | Banco de metadados do Airflow |
| **MySQL** | via cliente externo | 3306 | `root` / `root` | `lista_revisao2` | Banco da aplicação web |
| **Spark SQL (Thrift)** | via Conector JDBC/ODBC 10000 | 10000 | `nenum` / `nenhum` | `nenhum` | Ponto de Acesso para Power BI/Tableau (Camada Semântica sobre Delta Lake) |

-----

## Detalhes Importantes para o Spark SQL (Thrift)\#\#

## Usuário/Senha: O Spark Thrift Server (a menos que configurado com Kerberos ou autenticação complexa, o que é raro em desenvolvimento local) geralmente não requer autenticação. Basta deixar em branco ou usar valores dummy na ferramenta de BI. Banco de Dados: Ele expõe o catálogo de tabelas do Spark/Hive. Você acessa as tabelas Delta diretamente com comandos SQL, como SELECT \* FROM nome\_da\_tabela\_delta. Conexão BI: Use o driver Spark Thrift JDBC/ODBC (ou driver Hive) para conectar ferramentas de BI. O host será localhost e a porta será 10000.

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
docker compose restart airflow-webserver airflow-scheduler minio mysql spark
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

-----

### 🧩 O que procurar nos logs

Você saberá que o Airflow carregou com sucesso quando encontrar mensagens como:

```
Scheduler started...
Starting webserver at [http://0.0.0.0:8080](http://0.0.0.0:8080)
```

Essas mensagens indicam que tanto o *scheduler* quanto o *webserver* estão ativos e prontos.

-----

### ✅ Alternativas úteis

Se estiver usando o Airflow fora de containers, você pode verificar com:

```bash
airflow webserver
```

ou

```bash
airflow scheduler
```

## E observar no terminal se os serviços iniciam sem erros.

Navegar um recurso com interface amigável

```bash
mc ls local/lab01/processed/raw/
```

```
```