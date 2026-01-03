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
        
        # Carrega extensão Delta para leitura de Delta Lake
        try:
            con.execute("INSTALL delta;")
            con.execute("LOAD delta;")
            logger.info("✅ Delta Lake extension loaded")
        except Exception as delta_error:
            logger.warning(f"⚠️  Delta extension não disponível: {delta_error}")
        
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
        
        # Verifica se a query já tem um LIMIT explícito
        sql_upper = request.sql.upper().strip()
        has_limit = 'LIMIT' in sql_upper
        
        # Se não tem LIMIT, aplica o limite de segurança
        if not has_limit:
            final_sql = f"{request.sql.rstrip(';')} LIMIT {request.limit}"
            logger.info(f"🔒 Aplicando LIMIT de segurança: {request.limit}")
        else:
            final_sql = request.sql
            logger.info(f"✓ Query já possui LIMIT explícito")
        
        # Executa a query
        result = con.execute(final_sql).fetchall()
        
        # Obtém nomes das colunas
        columns = [desc[0] for desc in con.description] if con.description else []
        
        # Converte resultado para lista de dicts (sem truncar aqui)
        data = [dict(zip(columns, row)) for row in result]
        
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
    """
    Lista arquivos e estrutura de pastas no S3/MinIO.
    
    Retorna todos os arquivos (parquet e json) do bucket, organizados por camadas:
    - raw: arquivos JSON brutos (uploads)
    - bronze: arquivos parquet após primeira transformação
    - silver: arquivos parquet após enriquecimento
    - gold: arquivos parquet finais para BI/análise
    """
    path = request.path.strip() if request.path else ""
    
    try:
        con = get_duckdb_connection()
        
        logger.info(f"📁 Listando arquivos em: {path or 'bucket-root'}")
        
        # Se path é o bucket raiz (vazio ou sem camada específica)
        if not path or path.endswith("/") or path.endswith("eng-147") or path.endswith("lab01"):
            logger.info(f"📁 Modo 'bucket-root': listando todas as camadas")
            
            # Extrai nome do bucket do path se fornecido
            bucket = path.strip('/').split('/')[-1] if path else MINIO_BUCKET
            if bucket.startswith('s3://'):
                bucket = bucket.replace('s3://', '')
            
            # Tenta listar arquivos em cada camada conhecida (raw, bronze, silver, gold)
            layers = ["raw", "bronze", "silver", "gold"]
            all_files = []
            
            for layer in layers:
                try:
                    # Parquet
                    layer_path_parquet = f"s3://{bucket}/{layer}/**/*.parquet"
                    result_parquet = con.execute(f"""
                        SELECT file
                        FROM glob('{layer_path_parquet}')
                        LIMIT 500
                    """).fetchall()
                    if result_parquet:
                        for row in result_parquet:
                            all_files.append((row[0],))
                        logger.info(f"  ✅ '{layer}': {len(result_parquet)} arquivos Parquet")

                    # JSON
                    layer_path_json = f"s3://{bucket}/{layer}/**/*.json"
                    result_json = con.execute(f"""
                        SELECT file
                        FROM glob('{layer_path_json}')
                        LIMIT 500
                    """).fetchall()
                    if result_json:
                        for row in result_json:
                            all_files.append((row[0],))
                        logger.info(f"  ✅ '{layer}': {len(result_json)} arquivos JSON")

                    # CSV
                    layer_path_csv = f"s3://{bucket}/{layer}/**/*.csv"
                    result_csv = con.execute(f"""
                        SELECT file
                        FROM glob('{layer_path_csv}')
                        LIMIT 500
                    """).fetchall()
                    if result_csv:
                        for row in result_csv:
                            all_files.append((row[0],))
                        logger.info(f"  ✅ '{layer}': {len(result_csv)} arquivos CSV")

                except Exception as layer_error:
                    logger.info(f"  ⓘ '{layer}': {str(layer_error)[:80]}")
                    continue
            
            con.close()
            
            logger.info(f"Total de arquivos encontrados: {len(all_files)}")
            
            return {
                "success": True,
                "files": all_files,
                "path": path or "bucket-root",
                "count": len(all_files)
            }
        
        # Se path foi especificado para uma camada específica
        else:
            logger.info(f"📁 Modo 'path-específico': listando em {path}")
            
            all_files = []
            # Parquet
            try:
                result_parquet = con.execute(f"""
                    SELECT file
                    FROM glob('{path}/**/*.parquet')
                    LIMIT 500
                """).fetchall()
                if result_parquet:
                    for r in result_parquet:
                        all_files.append((r[0],))
                    logger.info(f"  ✅ Parquet: {len(result_parquet)} arquivos")
            except:
                logger.info(f"  ⓘ Nenhum parquet encontrado")
            # JSON
            try:
                result_json = con.execute(f"""
                    SELECT file
                    FROM glob('{path}/**/*.json')
                    LIMIT 500
                """).fetchall()
                if result_json:
                    for r in result_json:
                        all_files.append((r[0],))
                    logger.info(f"  ✅ JSON: {len(result_json)} arquivos")
            except:
                logger.info(f"  ⓘ Nenhum JSON encontrado")
            # CSV
            try:
                result_csv = con.execute(f"""
                    SELECT file
                    FROM glob('{path}/**/*.csv')
                    LIMIT 500
                """).fetchall()
                if result_csv:
                    for r in result_csv:
                        all_files.append((r[0],))
                    logger.info(f"  ✅ CSV: {len(result_csv)} arquivos")
            except:
                logger.info(f"  ⓘ Nenhum CSV encontrado")
            con.close()
            return {
                "success": True,
                "files": all_files,
                "path": path,
                "count": len(all_files)
            }
            
    except Exception as e:
        logger.error(f"❌ Error listing files: {e}")
        try:
            con.close()
        except:
            pass
        return {"success": False, "error": str(e), "files": [], "count": 0}


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


@app.post("/query/delta")
async def query_delta(request: QueryRequest):
    """
    Executa query em tabela Delta Lake
    
    Automaticamente converte queries Delta para leitura dos arquivos Parquet subjacentes.
    
    Exemplos de uso:
    - SELECT * FROM delta.customers LIMIT 10
    - SELECT * FROM customers_delta WHERE age > 30
    """
    sql = request.sql.strip()
    
    try:
        con = get_duckdb_connection()
        
        # Detecta se é query Delta e converte para read_parquet
        sql_upper = sql.upper()
        
        # Pattern: delta.{table} ou {table}_delta
        if 'DELTA.' in sql_upper or '_DELTA' in sql_upper:
            logger.info(f"🔷 Detectada query Delta Lake")
            
            # Extrai nome da tabela
            import re
            
            # Busca padrão: delta.table_name ou table_name_delta
            delta_pattern = r'(?:DELTA\.(\w+)|(\w+)_DELTA)'
            matches = re.findall(delta_pattern, sql_upper)
            
            if matches:
                # Pega o primeiro match (pode ser do grupo 1 ou 2)
                table_name = matches[0][0] if matches[0][0] else matches[0][1]
                table_name = table_name.lower()
                
                logger.info(f"📊 Tabela Delta detectada: {table_name}")
                
                # Converte para read_parquet apontando para camada delta
                delta_path = f"s3://{MINIO_BUCKET}/delta/{table_name}/**/*.parquet"
                
                # Substitui referências Delta por read_parquet
                sql_converted = re.sub(
                    r'(?i)(FROM|JOIN)\s+(?:delta\.(\w+)|(\w+)_delta)',
                    lambda m: f"{m.group(1).upper()} read_parquet('{delta_path}')",
                    sql
                )
                
                logger.info(f"🔄 Query convertida: {sql_converted[:200]}...")
                sql = sql_converted
        
        # Verifica se a query já tem um LIMIT explícito
        has_limit = 'LIMIT' in sql_upper
        
        # Se não tem LIMIT, aplica o limite de segurança
        if not has_limit:
            final_sql = f"{sql.rstrip(';')} LIMIT {request.limit}"
            logger.info(f"🔒 Aplicando LIMIT de segurança: {request.limit}")
        else:
            final_sql = sql
            logger.info(f"✓ Query já possui LIMIT explícito")
        
        # Executa a query
        result = con.execute(final_sql).fetchall()
        
        # Obtém nomes das colunas
        columns = [desc[0] for desc in con.description] if con.description else []
        
        # Converte resultado para lista de dicts
        data = [dict(zip(columns, row)) for row in result]
        
        con.close()
        
        return QueryResponse(
            success=True,
            data=data,
            columns=columns,
            rows_affected=len(data)
        )
    
    except Exception as e:
        logger.error(f"❌ Delta query execution error: {e}")
        return QueryResponse(
            success=False,
            error=str(e)
        )


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5000, log_level="info")
