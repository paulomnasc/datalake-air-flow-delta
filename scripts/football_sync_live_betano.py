#!/usr/bin/env python3
"""
scripts/football_sync_live_betano.py (Wrapper de Compatibilidade)
Redireciona para o módulo canônico scripts/football_sync_live.py
"""
import os
import sys

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
CANONICAL_SCRIPT = os.path.join(CURRENT_DIR, "football_sync_live.py")

if __name__ == "__main__":
    if os.path.exists(CANONICAL_SCRIPT):
        os.execv(sys.executable, [sys.executable, CANONICAL_SCRIPT] + sys.argv[1:])
    else:
        print(f"❌ Script canônico não encontrado em: {CANONICAL_SCRIPT}")
        sys.exit(1)
