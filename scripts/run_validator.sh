#!/usr/bin/env bash
set -euo pipefail

TABLE=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --table)
      TABLE="$2"
      shift 2
      ;;
    *)
      shift
      ;;
  esac
done

echo "run_validator.sh: validating table=${TABLE:-<none>}" >&2

# If there's a python validator next to this script, delegate to it
if [ -f "/usr/local/bin/scripts/run_validator.py" ]; then
  echo "Found python validator, delegating..." >&2
  python /usr/local/bin/scripts/run_validator.py --table "${TABLE}"
  exit $?
fi

# Placeholder validator: currently a no-op that returns success.
echo "No python validator found; placeholder validator returns success (exit 0)" >&2
exit 0
