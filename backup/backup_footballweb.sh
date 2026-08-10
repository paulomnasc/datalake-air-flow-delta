#!/usr/bin/env bash
set -euo pipefail

# Backup config para FootballWeb
DB_NAME="${MYSQL_DB:-footballweb}"
DB_HOST="${MYSQL_HOST:-172.18.0.1}"
DB_PORT="${MYSQL_PORT:-23306}"
DB_USER="${MYSQL_USER:-backup_lista_revisao2}"
MYSQL_CNF="${MYSQL_CNF:-$HOME/.my.cnf}"
BACKUP_DATE="$(date +%Y%m%d_%H%M%S)"
DUMP_DIR="${TMPDIR:-/tmp}/mysql_backups"
RCLONE_REMOTE="gdrive"
RCLONE_PATH="backups/${DB_NAME}"

mkdir -p "$DUMP_DIR"

# Conexão via TCP para o MySQL Docker (porta 23306)
MYSQL_DUMP_OPTIONS=(--protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
if [ -n "${MYSQL_CNF:-}" ] && [ -f "$MYSQL_CNF" ]; then
  MYSQL_DUMP_OPTIONS=(--defaults-file="$MYSQL_CNF" "${MYSQL_DUMP_OPTIONS[@]}")
fi

DUMP_FILE="${DUMP_DIR}/${DB_NAME}_${BACKUP_DATE}.sql"
ARCHIVE_FILE="${DUMP_FILE}.gz"

mysqldump "${MYSQL_DUMP_OPTIONS[@]}" \
  --single-transaction --quick --routines --triggers --events \
  "$DB_NAME" > "$DUMP_FILE"

gzip -f "$DUMP_FILE"

# Envio para o Google Drive via rclone
rclone copy "$ARCHIVE_FILE" "${RCLONE_REMOTE}:${RCLONE_PATH}/"

# Limpeza de backups locais com mais de 7 dias
find "$DUMP_DIR" -type f -name "${DB_NAME}_*.gz" -mtime +7 -delete

printf "Backup do FootballWeb concluído com sucesso: %s\n" "$ARCHIVE_FILE"
