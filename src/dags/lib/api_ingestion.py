import logging
import os
import tempfile
import json
import requests
from datetime import datetime
import hashlib

log = logging.getLogger(__name__)

def ingest_api_to_raw(
    api_endpoint: str,
    api_method: str = "GET",
    api_headers: dict = None,
    api_params: dict = None,
    api_payload: dict = None,
    target_table_name: str = None,
    dag_id: str = None,
    **kwargs
):
    """
    Ingesta dados de uma API REST para a camada Raw do Data Lake.
    Args:
        api_endpoint: URL da API
        api_method: Método HTTP (GET, POST, etc)
        api_headers: Headers HTTP (dict)
        api_params: Parâmetros de consulta (dict)
        api_payload: Payload/body (dict, para POST/PUT)
        target_table_name: Nome da tabela de destino
        dag_id: ID da DAG
    Returns:
        dict: Informações do arquivo criado na camada Raw
    """
    log.info(f"[API→RAW] Iniciando ingestão da API: {api_endpoint}")
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        import pandas as pd
    except Exception as e:
        log.error("S3Hook/Pandas não disponível: %s", e)
        raise

    bucket = kwargs.get('bucket_name') or os.environ.get("MINIO_BUCKET", "lab01")
    target_table = target_table_name or "api_data"

    if not dag_id and 'dag' in kwargs:
        dag_id = kwargs['dag'].dag_id
    elif not dag_id and 'ti' in kwargs:
        dag_id = kwargs['ti'].dag_id
    dag_name = dag_id or target_table

    timestamp = int(datetime.now().timestamp())
    hash_suffix = hashlib.md5(f"{api_endpoint}{timestamp}".encode()).hexdigest()[:20]
    filename = f"{timestamp}_{hash_suffix}.json"
    raw_key = f"raw/{dag_name}/{filename}"

    # Requisição à API
    method = api_method.upper()
    headers = api_headers or {}
    params = api_params or {}
    payload = api_payload or {}

    log.info(f"[API→RAW] Método: {method}, Headers: {headers}, Params: {params}, Payload: {payload}")
    response = requests.request(method, api_endpoint, headers=headers, params=params, json=payload)
    response.raise_for_status()
    data = response.json()

    # Salva o resultado em arquivo temporário
    tmpdir = tempfile.mkdtemp()
    local_file = os.path.join(tmpdir, filename)
    with open(local_file, "w") as f:
        json.dump(data, f, indent=2)

    # Upload para MinIO
    hook = S3Hook(aws_conn_id='minio_conn')
    hook.load_file(
        filename=local_file,
        key=raw_key,
        bucket_name=bucket,
        replace=True
    )

    log.info(f"[API→RAW] ✅ Dados salvos em s3://{bucket}/{raw_key}")
    return {
        "layer": "raw",
        "key": raw_key,
        "filename": filename,
        "bucket": bucket,
        "api_endpoint": api_endpoint,
        "status_code": response.status_code
    }
