#!/usr/bin/env bash
set -euo pipefail

# Backup config
DATABASES=("${MYSQL_DB:-lista_revisao2}" "fiscal")
DB_HOST="${MYSQL_HOST:-172.18.0.1}"
DB_PORT="${MYSQL_PORT:-23306}"
DB_USER="${MYSQL_USER:-backup_lista_revisao2}"
MYSQL_CNF="${MYSQL_CNF:-$HOME/.my.cnf}"
BACKUP_DATE="$(date +%Y%m%d_%H%M%S)"
DUMP_DIR="${TMPDIR:-/tmp}/mysql_backups"
RCLONE_REMOTE="gdrive"

mkdir -p "$DUMP_DIR"

# Connect via TCP to Docker MySQL (port 23306)
# Password is read from ~/.my.cnf [client] section or MYSQL_PWD env var
MYSQL_DUMP_OPTIONS=(--protocol=TCP -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER")
if [ -n "${MYSQL_CNF:-}" ] && [ -f "$MYSQL_CNF" ]; then
  MYSQL_DUMP_OPTIONS=(--defaults-file="$MYSQL_CNF" "${MYSQL_DUMP_OPTIONS[@]}")
fi

for DB_NAME in "${DATABASES[@]}"; do
  DUMP_FILE="${DUMP_DIR}/${DB_NAME}_${BACKUP_DATE}.sql"
  ARCHIVE_FILE="${DUMP_FILE}.gz"
  RCLONE_PATH="backups/${DB_NAME}"

  mysqldump "${MYSQL_DUMP_OPTIONS[@]}" \
    --single-transaction --quick --routines --triggers --events \
    "$DB_NAME" > "$DUMP_FILE"

  gzip -f "$DUMP_FILE"

  # Upload to Google Drive via rclone
  rclone copy "$ARCHIVE_FILE" "${RCLONE_REMOTE}:${RCLONE_PATH}/"

  # Keep local temp files short-lived (7 days)
  find "$DUMP_DIR" -type f -name "${DB_NAME}_*.gz" -mtime +7 -delete

  printf "Backup completed: %s\n" "$ARCHIVE_FILE"
done
