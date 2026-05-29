# Guia de Implantação e Execução: dbt Core + Metabase no Servidor

Este documento fornece o passo a passo detalhado para instalar, configurar e executar a camada de governança do **dbt Core** e o painel visual do **Metabase** no seu servidor de aplicação destino.

---

## 📋 Pré-requisitos no Servidor
* **Docker** e **Docker Compose** instalados e funcionando.
* **Python 3** (preferencialmente >= 3.8) instalado.
* Acesso de rede à porta do PostgreSQL analítico (`postgres-bi` na porta interna `5432` ou externa `5433`/`25433`).

---

## 🛠️ Passo 1: Subir o Metabase com docker-compose
Para evitar a sobreposição de portas no host, a porta externa mapeada do Metabase é configurada dinamicamente com base nas variáveis de ambiente.

1. Acesse o diretório do projeto no servidor destino.
2. Certifique-se de que a variável `METABASE_PORT` está declarada nos seus arquivos `.env`:
   * **Desenvolvimento/Local (`.env-dev` ou `.env`)**:
     ```ini
     METABASE_PORT=3000
     ```
   * **Produção (`.env-prd` ou `.env`)**:
     ```ini
     METABASE_PORT=28300
     ```
3. Re-carregue e inicie o container do Metabase com o Docker Compose:
   ```bash
   # Recarrega a configuração do Compose e sobe o Metabase em background
   docker compose up -d metabase
   ```
4. Verifique se o container está rodando e escutando na porta configurada:
   ```bash
   docker compose ps metabase
   ```

---

## 🐍 Passo 2: Instalar o dbt Core no Servidor (Python Virtualenv)
Para não poluir o Python global do sistema operacional do servidor, utilize o script automatizado `install_dbt.sh` criado na raiz do projeto.

1. Conceda permissão de execução ao script:
   ```bash
   chmod +x install_dbt.sh
   ```
2. Execute o script de instalação:
   ```bash
   ./install_dbt.sh
   ```
   *Este script cria uma pasta `.venv`, ativa o ambiente virtual, atualiza o pip e instala os pacotes `dbt-core` e `dbt-postgres` listados no `requirements.txt`.*

3. Ative o ambiente virtual para as execuções futuras:
   ```bash
   source .venv/bin/activate
   ```

---

## 🔒 Segurança em Produção: Ambiente de Homologação Lógico (Sandbox)
Como o PostgreSQL analítico de Produção (`datalake_bi`) divide recursos na mesma máquina física, o projeto implementa o conceito de **Sandbox de Homologação Lógico** para evitar sobrecarga de memória, Locks e corrupção das tabelas de produção:
1. **Target `dev` (Homologação Virtual)**:
   * Redireciona 100% das compilações do dbt para o schema **`homolog_analytics`**.
   * O parquinho de testes do desenvolvedor fica isolado. Tabelas de produção e o Metabase oficial continuam intocados.
2. **Target `prod` (Produção Oficial)**:
   * Grava as tabelas finais unicamente no schema **`analytics`** (consumido pelo Metabase produtivo).
3. **Macro `generate_schema_name`**:
   * O arquivo `macros/generate_schema_name.sql` garante que as regras de concatenação padrão do dbt sejam sobrescritas, forçando o isolamento físico no Postgres por schema limpo.

---

## 📊 Passo 3: Executar o dbt Core e Modelar o DW
Com o ambiente virtual ativado, acesse o diretório do dbt e execute os comandos apontando para a pasta local de perfis (`--profiles-dir .`).


Você pode rodar os comandos especificando o **target** (ambiente de execução):
* **No Host local / Dev** (aponta para `localhost` na porta exposta `5433`):
  ```bash
  dbt run --profiles-dir . --target dev
  dbt test --profiles-dir . --target dev
  ```
* **Dentro do Docker / Produção** (aponta para o container `postgres-bi` na porta interna `5432`):
  ```bash
  dbt run --profiles-dir . --target prod
  dbt test --profiles-dir . --target prod
  ```

1. Acesse o diretório do projeto dbt:
   ```bash
   cd dbt/analytics
   ```
2. Valide a conectividade com o banco analítico `postgres-bi`:
   ```bash
   dbt debug --profiles-dir . --target dev   # ou --target prod
   ```
3. Execute as transformações para materializar as Dimensões e Fatos no schema `analytics`:
   ```bash
   dbt run --profiles-dir . --target dev     # ou --target prod
   ```
4. Execute os testes de integridade e qualidade (IDs únicos, campos obrigatórios, etc.):
   ```bash
   dbt test --profiles-dir . --target dev    # ou --target prod
   ```


---

## 📖 Passo 4: Linhagem de Dados e Documentação (Lineage Graph)
O dbt gera de forma automática uma documentação estática rica e interativa com todo o grafo de linhagem dos dados.

1. Gere a documentação técnica do projeto:
   ```bash
   dbt docs generate --profiles-dir .
   ```
2. Inicie o servidor web para visualizar a documentação e linhagem (porta padrão `8080` ou passe outra porta via `--port`):
   ```bash
   dbt docs serve --profiles-dir . --port 8001
   ```
3. Acesse `http://<ip-do-servidor>:8001` no seu navegador para ver o gráfico interativo de como as tabelas do data lake fluem até a modelagem dimensional final.

---

## 🎨 Passo 5: Conectar o Metabase
Com os dados modelados e testados no PostgreSQL, configure o painel analítico do Metabase:

1. Abra o navegador e acesse `http://localhost:3000` (desenvolvimento) ou `http://<ip-do-servidor>:28300` (produção).
2. Siga o passo inicial de configuração do Metabase.
3. Ao adicionar o banco de dados, escolha **PostgreSQL** e configure os seguintes parâmetros:
   * **Host**: `postgres-bi` (quando conectado na rede interna do Docker) ou `localhost` (se acessado do host).
   * **Porta**: `5432` (interno da rede do Docker) ou `5433` / `25433` (portas mapeadas no host).
   * **Banco de Dados (Database)**: `datalake_bi`
   * **Usuário (Username)**: `pbi_user`
   * **Senha (Password)**: `pbi_password`
4. Na seção de modelos de dados do Metabase, selecione o schema **`analytics`** (onde estão as tabelas `dim_usuarios`, `dim_cursos`, `fato_vendas` e `fato_acessos`) para construir seus dashboards analíticos limpos e otimizados!
