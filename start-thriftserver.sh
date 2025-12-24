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

# Verifica se spark-submit existe
if [ ! -f "$SPARK_HOME/bin/spark-submit" ]; then
    echo "❌ Erro: spark-submit não encontrado em $SPARK_HOME/bin/"
    echo "Conteúdo de $SPARK_HOME/bin:"
    ls -la "$SPARK_HOME/bin/" || echo "Diretório não existe"
    exit 1
fi

# Inicia o Thrift Server
exec "$SPARK_HOME/bin/spark-submit" \
    --master spark://spark:7077 \
    --deploy-mode client \
    --name SparkThriftServer \
    --conf spark.driver.host=spark-sql \
    --packages io.delta:delta-core_2.12:2.4.0 \
    --conf spark.sql.extensions=io.delta.sql.DeltaSparkSessionExtension \
    --conf spark.sql.catalog.spark_catalog=org.apache.spark.sql.delta.catalog.DeltaCatalog \
    --conf spark.jars.ivy=/home/spark/.ivy2 \
    --conf spark.sql.catalogImplementation=hive \
    --class org.apache.spark.sql.hive.thriftserver.HiveThriftServer2
