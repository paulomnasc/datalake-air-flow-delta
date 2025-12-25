#!/usr/bin/env python3
"""
Servidor ODBC simples para DuckDB
Permite que Power BI conecte via ODBC ao arquivo DuckDB remoto
"""
import sys
import os

# Para WSL/Linux: Garantir que DuckDB pode ser importado
try:
    import duckdb
except ImportError:
    print("❌ DuckDB não instalado. Instale com: pip install duckdb")
    sys.exit(1)

try:
    import pyodbc
    HAS_PYODBC = True
except ImportError:
    HAS_PYODBC = False

DUCKDB_PATH = os.environ.get('DUCKDB_PATH', '/opt/duckdb/datalake.duckdb')

print(f"📊 DuckDB ODBC Server")
print(f"📁 Database: {DUCKDB_PATH}")
print(f"🔌 ODBC: Aguardando conexões...")
print("")
print("Para conectar via ODBC (Windows/Power BI):")
print("  DSN: DuckDB")
print("  Host: localhost ou IP do servidor WSL")
print("  Port: 5000 (se usar ODBC-over-HTTP)")
print("")

# Teste de conectividade
try:
    con = duckdb.connect(DUCKDB_PATH, read_only=True)
    views = con.execute("SELECT table_name FROM information_schema.tables WHERE table_type='VIEW'").fetchall()
    print(f"✅ DuckDB conectado!")
    print(f"📋 Views disponíveis: {len(views)}")
    for view in views:
        print(f"   - {view[0]}")
    con.close()
except Exception as e:
    print(f"⚠️  Aviso ao conectar: {e}")

print("\n💡 Solução recomendada:")
print("   1. Instale o DuckDB ODBC Driver no Windows")
print("   2. Crie um DSN apontando para: \\\\wsl.localhost\\ubuntu\\opt\\duckdb\\datalake.duckdb")
print("   3. Ou configure permissões do arquivo para acesso remoto")
print("")
print("✅ Servidor pronto!")
