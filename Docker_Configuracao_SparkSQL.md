# ⚙️ Configuração do Serviço Spark Thrift Server (spark-sql) no Docker Compose

Este documento detalha a configuração do serviço `spark-sql` no `docker-compose.yml` para iniciar o Spark Thrift Server, garantindo que ele encontre as classes necessárias e permaneça ativo (mantendo o container rodando) para conexões externas via ODBC/JDBC.

---

## 1. Configuração Inicial no `docker-compose.yml`

A configuração abaixo garante que o Spark Thrift Server seja inicializado com o comando correto e que todas as bibliotecas (JARs) necessárias para o ambiente (como Delta Lake) sejam carregadas.

### 1.1. Serviço `spark-sql`

O comando `command` deve ser substituído pelo uso direto do `spark-submit`. Este método garante que o processo do Spark permaneça em **modo `client` (primeiro plano)**, impedindo que o container saia imediatamente.

**Localização:** Dentro da definição do serviço `spark-sql` no seu `docker-compose.yml`.

```yaml
# Trecho do docker-compose.yml
services:
  spark-sql:
    image: apache/spark:3.5.1 # (ou a imagem que você está usando)
    container_name: spark-sql
    ports:
      - "10000:10000" # Porta para conexões ODBC/JDBC
    environment:
      # ... (outras variáveis de ambiente)
    command:
      [
        "bash",
        "-c",
        "spark-submit --master spark://spark:7077 --deploy-mode client --name SparkThriftServer --conf spark.driver.host=spark-sql --packages io.delta:delta-core_2.12:2.4.0 --conf spark.sql.extensions=io.delta.sql.DeltaSparkSessionExtension --conf spark.sql.catalog.spark_catalog=org.apache.spark.sql.delta.catalog.DeltaCatalog --class org.apache.spark.sql.hive.thriftserver.HiveThriftServer2"
      ]
    # ... (restante da configuração)