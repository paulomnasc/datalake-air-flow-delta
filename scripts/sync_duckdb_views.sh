#!/bin/bash
# Script wrapper para executar sync_duckdb_views.py no ambiente correto

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Ativa venv se existir
if [ -d "$PROJECT_ROOT/.venv" ]; then
    source "$PROJECT_ROOT/.venv/bin/activate"
fi

# Executa o script Python
python3 "$SCRIPT_DIR/sync_duckdb_views.py" "$@"
