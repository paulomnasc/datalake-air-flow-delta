#!/usr/bin/env bash
set -euo pipefail

# Wrapper: forwards all args to the Python validator that lives in the same folder.
SCRIPT_DIR="$(dirname "${BASH_SOURCE[0]}")"
PY="$SCRIPT_DIR/run_validator.py"

if [ -f "$PY" ]; then
  exec python "$PY" "$@"
else
  echo "run_validator: python validator not found at $PY" >&2
  exit 4
fi
