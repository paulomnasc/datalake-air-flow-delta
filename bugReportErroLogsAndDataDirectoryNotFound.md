Claro! Aqui está o Relatório de Estabilização em formato Markdown otimizado para visualização no GitHub (usando tópicos hierárquicos).

---

# 🐞 Relatório de Estabilização: Airflow e MinIO (Docker Compose)

**Status:** Sucesso na execução da DAG e estabilização total do ambiente.

## 1. Problemas de Comunicação e Segurança (Airflow Core)

Os erros iniciais impediam que o Webserver, Scheduler e Worker se comunicassem de forma segura ou que o Airflow iniciasse corretamente.

* **Problema:** Erro de log `403 FORBIDDEN` e aviso de segurança sobre a `secret_key`.
    * **Causa Raiz:** A variável `AIRFLOW__WEBSERVER__SECRET_KEY` não estava definida ou não era idêntica em todos os serviços Airflow (Webserver e Scheduler).
    * **Solução:** Foi definido e aplicado um valor **idêntico, longo e aleatório** para `AIRFLOW__WEBSERVER__SECRET_KEY` em todos os serviços Airflow no `docker-compose.yml`.

* **Problema:** Não conseguir localizar a senha do MinIO.
    * **Causa Raiz:** A senha estava definida em variáveis de ambiente Docker (`MINIO_ROOT_PASSWORD`) e não em um arquivo de projeto.
    * **Solução:** Credenciais `admin` / `admin123` foram identificadas no serviço `minio` e usadas para configurar a conexão **`minio_conn`** no Airflow UI.

## 2. Problemas de Sintaxe e Codificação de Arquivo (YAML)

Vários erros impediram o `docker-compose` de ler o arquivo de configuração.

* **Problema:** Erros `yaml: invalid trailing UTF-8 octet` e `yaml: invalid Unicode character`.
    * **Causa Raiz:** Caracteres não-padrão (como emojis ou símbolos copiados) ou quebras de linha de formato Windows (`CRLF`) estavam presentes no arquivo `docker-compose.yml`.
    * **Solução:** Uso do utilitário **`dos2unix`** e **remoção manual** de todos os caracteres Unicode/símbolos não reconhecidos pelo parser YAML.

* **Problema:** Erro `unexpected type map[string]interface {}` na seção `environment`.
    * **Causa Raiz:** Mistura de sintaxes YAML (`CHAVE: VALOR` e `- CHAVE=VALOR`) dentro da mesma lista de ambiente.
    * **Solução:** Padronização de todas as variáveis na seção `environment` para o formato de lista **`- CHAVE=VALOR`**.

## 3. Problemas de Persistência e Log Remoto (MinIO)

A comunicação correta com o MinIO e a estabilidade do estado foram cruciais para a conclusão.

* **Problema:** Perda do estado das DAGs e histórico de execuções após `docker-compose down`.
    * **Causa Raiz:** O serviço `postgres` não tinha um volume persistente mapeado.
    * **Solução:** Adição do volume persistente (`pg_data`) ao serviço `postgres` no `docker-compose.yml`.

* **Problema:** Logs das tarefas não apareciam na UI, apesar da `SECRET_KEY` estar correta.
    * **Causa Raiz:** O Airflow Webserver não conseguia resolver o endereço do MinIO para ler os logs.
    * **Solução:** Garantia de que a conexão **`minio_conn`** na UI contenha a configuração correta do DNS do Docker no campo **Extra (JSON)**: `{"endpoint_url": "http://minio:9000"}`.
    * *(Nota: O botão "Test Connection" falha com MinIO, mas a funcionalidade de log funciona.)*

* **Problema:** Falha final na execução da tarefa (`upload_to_minio`) com `FileNotFoundError`.
    * **Causa Raiz:** O Worker/Scheduler estava procurando o arquivo de dados (`Persons.json`) em um caminho que não estava mapeado no contêiner.
    * **Solução:** O mapeamento de volumes no `docker-compose.yml` foi ajustado para incluir a pasta com os dados de origem (ex: `./datasources`) no caminho que a DAG estava tentando acessar.
