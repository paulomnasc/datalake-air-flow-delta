#!/bin/bash
containers=(
  airflow-webserver
  airflow-scheduler
  spark
  spark-worker
  postgres
  mysql
  datalake-air-flow-minio-1
)

for c in "${containers[@]}"; do
  name="${c}-snap"
  echo "📸 Criando snapshot de $c..."
  docker commit "$c" "${name}:v1"
  docker save -o "${name}-v1.tar" "${name}:v1"
done

echo "✅ Snapshots criados e exportados!"
