#!/bin/bash

# Diretório de destino
BACKUP_DIR="/var/oled/docker-backups"
mkdir -p "$BACKUP_DIR"

# Data atual
DATE=$(date +"%Y-%m-%d")

# Lista de containers
containers=(
  airflow-webserver
  airflow-scheduler
  spark
  spark-worker
  postgres
  mysql
  datalake-air-flow-minio-1
)

# Criar snapshots e salvar como .tar.gz
for c in "${containers[@]}"; do
  name="${c}-snap"
  image="${name}:v1"
  file="${BACKUP_DIR}/${name}-${DATE}.tar"

  echo "📸 Criando snapshot de $c..."
  docker commit "$c" "$image"
  docker save -o "$file" "$image"
  gzip "$file"
done

# Limpeza de backups antigos (mais de 7 dias)
echo "🧹 Removendo backups com mais de 7 dias..."
find "$BACKUP_DIR" -type f -name "*.tar.gz" -mtime +7 -exec rm {} \;

echo "✅ Snapshots criados, compactados e salvos em $BACKUP_DIR"
