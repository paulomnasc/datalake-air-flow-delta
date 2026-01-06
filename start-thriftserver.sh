#!/bin/bash

# Script para iniciar o Spark SQL Thrift Server
# Garante que o SPARK_HOME está configurado

set -e

# Define o SPARK_HOME (deve estar em /opt/spark no container)
export SPARK_HOME=${SPARK_HOME:-/opt/spark}

# Adiciona o Spark ao PATH
export PATH=$SPARK_HOME/bin:$PATH

echo "🚀 Iniciando Spark SQL Thrift Server..."
echo "SPARK_HOME: $SPARK_HOME"
echo "PATH: $PATH"

# Parâmetros padrão
MASTER_URL=${SPARK_MASTER:-spark://spark:7077}
WAREHOUSE_DIR=${SPARK_WAREHOUSE_DIR:-/home/spark/warehouse}
METASTORE_URL=${SPARK_METASTORE_URL:-jdbc:derby:;databaseName=/home/spark/metastore_db/metastore_db;create=true}

# Verifica se spark-submit existe
if [ ! -x "$SPARK_HOME/sbin/start-thriftserver.sh" ]; then
    echo "❌ Erro: start-thriftserver.sh não encontrado em $SPARK_HOME/sbin/"
    ls -la "$SPARK_HOME/sbin/" || echo "Diretório não existe"
    exit 1
fi

# Inicia o Thrift Server em primeiro plano usando spark-class
"$SPARK_HOME/sbin/start-thriftserver.sh" \
    --master "$MASTER_URL" \
    --packages io.delta:delta-core_2.12:2.4.0,io.delta:delta-storage:2.4.0,org.apache.hadoop:hadoop-aws:3.3.4,com.amazonaws:aws-java-sdk-bundle:1.12.262 \
    --conf spark.sql.extensions=io.delta.sql.DeltaSparkSessionExtension \
    --conf spark.sql.catalog.spark_catalog=org.apache.spark.sql.delta.catalog.DeltaCatalog \
    --conf spark.jars.ivy=/home/spark/.ivy2 \
    --conf spark.driver.host=spark-sql \
    --conf spark.hadoop.fs.s3a.impl=org.apache.hadoop.fs.s3a.S3AFileSystem \
    --conf spark.hadoop.fs.s3a.endpoint=http://minio:9000 \
    --conf spark.hadoop.fs.s3a.access.key=admin \
    --conf spark.hadoop.fs.s3a.secret.key=admin123 \
    --conf spark.hadoop.fs.s3a.path.style.access=true \
    --hiveconf javax.jdo.option.ConnectionURL="$METASTORE_URL" \
    --hiveconf hive.metastore.warehouse.dir="$WAREHOUSE_DIR"

# Mantém o container ativo seguindo os logs do Thrift Server
LOG_DIR="$SPARK_HOME/logs"
echo "📄 Acompanhe os logs em $LOG_DIR"
# Tenta seguir os principais arquivos de log; se não existirem ainda, mantém o container vivo
tail -F "$LOG_DIR"/spark--org.apache.spark.sql.hive.thriftserver.HiveThriftServer2-*.out "$LOG_DIR"/*thriftserver*.log 2>/dev/null || exec sleep infinity
