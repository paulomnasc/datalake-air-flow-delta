#!/usr/bin/env python3
"""
DuckDB SQL Query API
Expõe endpoint REST para executar queries SQL em Parquet armazenado no MinIO S3
"""

from fastapi import FastAPI, HTTPException, Query
from pydantic import BaseModel
import duckdb
import os
import logging
from typing import List, Dict, Any
import json

# Configuração de logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="DuckDB Query API", version="1.0.0")

# Configurações do MinIO
MINIO_ENDPOINT = os.getenv('MINIO_ENDPOINT', 'http://minio:9000')
MINIO_ACCESS_KEY = os.getenv('MINIO_ACCESS_KEY_ID', 'admin')
MINIO_SECRET_KEY = os.getenv('MINIO_SECRET_ACCESS_KEY', 'admin123')
MINIO_BUCKET = os.getenv('MINIO_BUCKET_RAW', 'lab01')
DUCKDB_PATH = os.getenv('DUCKDB_PATH', '/opt/duckdb/datalake.duckdb')

logger.info(f"🔧 MinIO Configuration:")
logger.info(f"   Endpoint: {MINIO_ENDPOINT}")
logger.info(f"   Bucket: {MINIO_BUCKET}")
logger.info(f"   DuckDB: {DUCKDB_PATH}")


class QueryRequest(BaseModel):
    """Modelo para requisição de query"""
    sql: str
    limit: int = 1000


class QueryResponse(BaseModel):
    """Modelo para resposta de query"""
    success: bool
    data: List[Dict[str, Any]] = []
    columns: List[str] = []
    rows_affected: int = 0
    error: str = None


def get_duckdb_connection():
    """Cria conexão com DuckDB e configura extensões"""
    try:
        con = duckdb.connect(DUCKDB_PATH)
        
        # Carrega extensão httpfs para acesso a S3
        con.execute("INSTALL httpfs;")
        con.execute("LOAD httpfs;")
        
        # Remove http:// do endpoint se existir (DuckDB espera apenas host:porta)
        endpoint = MINIO_ENDPOINT.replace('http://', '').replace('https://', '')
        
        # Configura credenciais S3 (MinIO)
        s3_config = f"""
        SET s3_endpoint='{endpoint}';
        SET s3_access_key_id='{MINIO_ACCESS_KEY}';
        SET s3_secret_access_key='{MINIO_SECRET_KEY}';
        SET s3_use_ssl=false;
        SET s3_url_style='path';
        """
        con.execute(s3_config)
        
        logger.info(f"✅ DuckDB connection established with S3 config (endpoint: {endpoint})")
        return con
    except Exception as e:
        logger.error(f"❌ Failed to connect to DuckDB: {e}")
        raise


@app.on_event("startup")
async def startup_event():
    """Testa conexão ao iniciar"""
    try:
        con = get_duckdb_connection()
        # Testa listagem de arquivos no bucket
        result = con.execute(f"SELECT * FROM read_parquet('s3://{MINIO_BUCKET}/**/*.parquet') LIMIT 0;").fetchall()
        con.close()
        logger.info("✅ DuckDB startup check passed")
    except Exception as e:
        logger.warning(f"⚠️  DuckDB startup check: {e}")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "DuckDB Query API",
        "minio_bucket": MINIO_BUCKET,
        "duckdb_path": DUCKDB_PATH
    }


@app.post("/query", response_model=QueryResponse)
async def execute_query(request: QueryRequest):
    """
    Executa query SQL em Parquet no MinIO
    
    Exemplo:
    {
        "sql": "SELECT * FROM read_parquet('s3://lab01/bronze/**/*.parquet') LIMIT 10",
        "limit": 1000
    }
    """
    if not request.sql.strip():
        raise HTTPException(status_code=400, detail="SQL query cannot be empty")
    
    try:
        con = get_duckdb_connection()
        
        # Executa a query
        result = con.execute(request.sql).fetchall()
        
        # Obtém nomes das colunas
        columns = [desc[0] for desc in con.description] if con.description else []
        
        # Converte resultado para lista de dicts
        data = [dict(zip(columns, row)) for row in result[:request.limit]]
        
        con.close()
        
        return QueryResponse(
            success=True,
            data=data,
            columns=columns,
            rows_affected=len(data)
        )
    
    except Exception as e:
        logger.error(f"❌ Query execution error: {e}")
        return QueryResponse(
            success=False,
            error=str(e)
        )


@app.post("/query/tables")
async def list_tables():
    """Lista todas as tabelas/views disponíveis no DuckDB"""
    try:
        con = get_duckdb_connection()
        
        # Query para listar tabelas do schema padrão
        tables = con.execute(
            "SELECT table_name FROM information_schema.tables WHERE table_schema='main' ORDER BY table_name"
        ).fetchall()
        
        con.close()
        
        return {
            "success": True,
            "tables": [t[0] for t in tables]
        }
    except Exception as e:
        logger.error(f"❌ Error listing tables: {e}")
        return {"success": False, "error": str(e)}


class ParquetFilesRequest(BaseModel):
    path: str

class SchemaRequest(BaseModel):
    path: str

@app.post("/query/parquet-files")
async def list_parquet_files(request: ParquetFilesRequest):
    """Lista arquivos Parquet disponíveis no S3/MinIO"""
    path = request.path
    
    try:
        con = get_duckdb_connection()
        
        logger.info(f"📁 Listando arquivos em: {path}")
        
        # Query para listar arquivos (função glob do DuckDB)
        # glob() retorna apenas a coluna 'file'
        result = con.execute(f"""
            SELECT 
                file
            FROM glob('{path}/**/*.parquet')
            LIMIT 100
        """).fetchall()
        
        con.close()
        
        return {
            "success": True,
            "files": result,
            "path": path
        }
    except Exception as e:
        logger.error(f"❌ Error listing parquet files: {e}")
        return {"success": False, "error": str(e)}


@app.post("/query/schema")
async def get_schema(request: SchemaRequest):
    """Obtém schema de um arquivo Parquet"""
    path = request.path
    
    try:
        con = get_duckdb_connection()
        
        logger.info(f"📋 Obtendo schema de: {path}")
        
        # Query para obter schema
        schema = con.execute(f"""
            SELECT *
            FROM read_parquet('{path}/**/*.parquet')
            LIMIT 0
        """).description
        
        columns = [
            {"name": col[0], "type": str(col[1]) if col[1] else "UNKNOWN"}
            for col in schema
        ]
        
        con.close()
        
        return {
            "success": True,
            "columns": columns,
            "path": path
        }
    except Exception as e:
        logger.error(f"❌ Error getting schema: {e}")
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000, log_level="info")
