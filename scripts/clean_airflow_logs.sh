#!/bin/bash
# ==============================================================================
# Limpeza diária de logs do Airflow (executado via cron às 00:00)
# Remove arquivos de log com mais de 2 dias nos containers do Airflow
# ==============================================================================

set -o pipefail

LOG_RETENTION_DAYS=2
LOG_FILE="/var/log/clean_airflow_logs.log"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando limpeza de logs do Airflow (> ${LOG_RETENTION_DAYS} dias)..." >> "$LOG_FILE"

# 1. Limpar no container airflow-scheduler
if docker ps --format '{{.Names}}' | grep -q '^airflow-scheduler$'; then
    docker exec airflow-scheduler find /opt/airflow/logs -type f -mtime +"$LOG_RETENTION_DAYS" -delete 2>> "$LOG_FILE" || true
    docker exec airflow-scheduler find /opt/airflow/logs -type d -empty -delete 2>> "$LOG_FILE" || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Logs de airflow-scheduler processados." >> "$LOG_FILE"
fi

# 2. Limpar no container airflow-webserver
if docker ps --format '{{.Names}}' | grep -q '^airflow-webserver$'; then
    docker exec airflow-webserver find /opt/airflow/logs -type f -mtime +"$LOG_RETENTION_DAYS" -delete 2>> "$LOG_FILE" || true
    docker exec airflow-webserver find /opt/airflow/logs -type d -empty -delete 2>> "$LOG_FILE" || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Logs de airflow-webserver processados." >> "$LOG_FILE"
fi

# 3. Limpar no container airflow-worker se existir
WORKER_CONTAINER=$(docker ps --format '{{.Names}}' | grep 'airflow-worker' | head -n 1)
if [ -n "$WORKER_CONTAINER" ]; then
    docker exec "$WORKER_CONTAINER" find /opt/airflow/logs -type f -mtime +"$LOG_RETENTION_DAYS" -delete 2>> "$LOG_FILE" || true
    docker exec "$WORKER_CONTAINER" find /opt/airflow/logs -type d -empty -delete 2>> "$LOG_FILE" || true
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Logs de $WORKER_CONTAINER processados." >> "$LOG_FILE"
fi

FREE_SPACE=$(df -h / | awk 'NR==2 {print $4 " livres (" $5 " usado)"}')
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Limpeza concluida. Disco atual: $FREE_SPACE" >> "$LOG_FILE"
