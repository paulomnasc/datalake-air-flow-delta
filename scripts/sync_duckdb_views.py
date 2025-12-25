#!/usr/bin/env python3
"""
Script para sincronizar views do DuckDB com dados do MinIO/S3.
Cria automaticamente views para todos os datasets no MinIO.
"""
import os
import sys
import duckdb
from pathlib import Path

# Configurações (ajuste conforme necessário)
DUCKDB_PATH = os.getenv('DUCKDB_PATH', '/home/cblna123456/datalake-air-flow/ddb/datalake.duckdb')
MINIO_ENDPOINT = os.getenv('MINIO_ENDPOINT', 'localhost:9000')
MINIO_ACCESS_KEY = os.getenv('MINIO_ACCESS_KEY', 'admin')
MINIO_SECRET_KEY = os.getenv('MINIO_SECRET_KEY', 'admin123')
MINIO_BUCKET = os.getenv('MINIO_BUCKET', 'lab01')

# Datasets a serem expostos (path relativo ao bucket)
DATASETS = [
    {'name': 'orders_bronze', 'path': 'processed/raw/orders'},
    {'name': 'customers_bronze', 'path': 'processed/raw/customers'},
    {'name': 'orders_silver', 'path': 'processed/silver/orders'},
    {'name': 'customers_silver', 'path': 'processed/silver/customers'},
    {'name': 'orders_delta', 'path': 'delta/orders'},
    {'name': 'customers_delta', 'path': 'delta/customers'},
]


def setup_duckdb_connection():
    """Cria ou abre conexão com DuckDB e configura extensões."""
    # Garante que o diretório existe
    Path(DUCKDB_PATH).parent.mkdir(parents=True, exist_ok=True)
    
    # Remove arquivo corrompido se existir
    if Path(DUCKDB_PATH).exists():
        try:
            con = duckdb.connect(DUCKDB_PATH)
            con.close()
        except Exception:
            print(f"⚠️  Arquivo corrompido detectado, removendo: {DUCKDB_PATH}")
            Path(DUCKDB_PATH).unlink()
    
    # Conecta
    con = duckdb.connect(DUCKDB_PATH)
    
    # Instala e carrega extensões
    print("📦 Instalando extensões...")
    con.execute("INSTALL httpfs;")
    con.execute("LOAD httpfs;")
    
    # Configura S3/MinIO
    print("🔧 Configurando acesso ao MinIO...")
    con.execute(f"SET s3_endpoint='{MINIO_ENDPOINT}';")
    con.execute(f"SET s3_access_key_id='{MINIO_ACCESS_KEY}';")
    con.execute(f"SET s3_secret_access_key='{MINIO_SECRET_KEY}';")
    con.execute("SET s3_use_ssl=false;")
    con.execute("SET s3_url_style='path';")
    
    return con


def create_views(con):
    """Cria ou substitui views para cada dataset."""
    print("\n📊 Criando views...")
    
    for dataset in DATASETS:
        view_name = dataset['name']
        s3_path = f"s3://{MINIO_BUCKET}/{dataset['path']}/*.parquet"
        
        try:
            # DROP se já existir
            con.execute(f"DROP VIEW IF EXISTS {view_name};")
            
            # CREATE VIEW
            sql = f"""
            CREATE VIEW {view_name} AS
            SELECT * FROM read_parquet('{s3_path}');
            """
            con.execute(sql)
            
            # Testa leitura
            count = con.execute(f"SELECT COUNT(*) FROM {view_name};").fetchone()[0]
            print(f"  ✅ {view_name}: {count} registros")
            
        except Exception as e:
            print(f"  ⚠️  {view_name}: ERRO - {e}")


def list_views(con):
    """Lista todas as views criadas."""
    print("\n📋 Views disponíveis no DuckDB:")
    result = con.execute("SELECT * FROM information_schema.tables WHERE table_type='VIEW';").fetchall()
    
    if result:
        for row in result:
            print(f"  - {row[2]}")  # table_name
    else:
        print("  (nenhuma view encontrada)")


def main():
    print("="*60)
    print("🦆 DuckDB View Sync - MinIO to DuckDB")
    print("="*60)
    print(f"DuckDB: {DUCKDB_PATH}")
    print(f"MinIO:  {MINIO_ENDPOINT}")
    print(f"Bucket: {MINIO_BUCKET}")
    print("="*60)
    
    try:
        # Conecta
        con = setup_duckdb_connection()
        
        # Cria views
        create_views(con)
        
        # Lista resultado
        list_views(con)
        
        # Fecha conexão
        con.close()
        
        print("\n✅ Sincronização concluída com sucesso!")
        print(f"\n💡 Para usar no Power BI:")
        print(f"   1. Configure DSN ODBC apontando para: {DUCKDB_PATH}")
        print(f"   2. Conecte via Power BI → ODBC")
        print(f"   3. Selecione as views desejadas no Navigator")
        
        return 0
        
    except Exception as e:
        print(f"\n❌ ERRO: {e}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    sys.exit(main())
