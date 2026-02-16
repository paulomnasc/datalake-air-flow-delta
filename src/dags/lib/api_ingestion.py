import logging
from typing import Optional
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
    api_headers: Optional[dict] = None,
    api_params: Optional[dict] = None,
    api_payload: Optional[dict] = None,
    target_table_name: Optional[str] = None,
    dag_id: Optional[str] = None,
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
    # Remove hardcoded: usa endpoint e parâmetros exatamente como enviados
    # Apenas remove apóstrofos acidentais dos parâmetros
    if api_params:
        api_params = dict(api_params)
        for k, v in list(api_params.items()):
            if isinstance(v, str):
                api_params[k] = v.replace("'", "")
    log.info(f"[API→RAW] Iniciando ingestão da API: {api_endpoint}")
    log.info(f"[API→RAW] Parâmetros: método={api_method}, headers={api_headers}, params={api_params}, payload={api_payload}")
    # Loga explicitamente o token Authorization (se presente)
    if api_headers and 'Authorization' in api_headers:
        log.info(f"[API→RAW] Authorization header: {api_headers['Authorization']}")
    else:
        log.warning("[API→RAW] Authorization header NÃO encontrado nos headers!")

    # Log de bucket, tabela e raw_key será feito após definição das variáveis

    # Requisição à API
    method = api_method.upper()
    import requests
    try:
        log.info(f"[API→RAW] Fazendo requisição: {method} {api_endpoint}")
        response = requests.request(
            method,
            api_endpoint,
            headers=api_headers,
            params=api_params,
            json=api_payload,
            timeout=60
        )
        log.info(f"[API→RAW] Resposta HTTP: status={response.status_code}")
        log.info(f"[API→RAW] Corpo da resposta: {response.text[:1000]}")
        response.raise_for_status()
    except Exception as e:
        log.error(f"[API→RAW] Erro na requisição HTTP: {e}")
        raise
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        import pandas as pd
    except Exception as e:
        log.error("S3Hook/Pandas não disponível: %s", e)
        raise

    # O bucket deve ser sempre igual ao owner da DAG (campo 'owner' do kwargs)
    # Nunca usar bucket_name de kwargs nem variável de ambiente!
    bucket = kwargs.get('owner', 'lab01')
    target_table = target_table_name or "api_data"

    if not dag_id and 'dag' in kwargs:
        dag_id = kwargs['dag'].dag_id
    elif not dag_id and 'ti' in kwargs:
        dag_id = kwargs['ti'].dag_id
    dag_name = dag_id or target_table
    # Ajusta para pasta esperada pelo pipeline
    folder = f"job_cotacao_voos_brasilia_rio27" if dag_name == "job_cotacao_voos_brasilia_rio27" else dag_name
    timestamp = int(datetime.now().timestamp())
    hash_suffix = hashlib.md5(f"{api_endpoint}{timestamp}".encode()).hexdigest()[:20]
    filename = f"{timestamp}_{hash_suffix}.json"
    raw_key = f"raw/{folder}/{filename}"

    # Requisição à API
    method = api_method.upper()
    headers = api_headers or {}
    params = api_params or {}
    payload = api_payload or {}

    try:
        log.info(f"[API→RAW] Executando request: {method} {api_endpoint}")
        response = requests.request(method, api_endpoint, headers=headers, params=params, json=payload)
        log.info(f"[API→RAW] Status code: {response.status_code}")
        log.info(f"[API→RAW] Headers resposta: {response.headers}")
        log.info(f"[API→RAW] Resposta completa: {response.text}")
        response.raise_for_status()
        data = response.json()
        log.info(f"[API→RAW] JSON extraído: {str(data)[:500]}")
    except Exception as e:
        log.error(f"[API→RAW] Erro ao requisitar API: {e}")
        log.error(f"[API→RAW] Resposta bruta: {getattr(response, 'text', None)}")
        raise

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
    log.info(f"[API→RAW] Finalizando ingestão: layer=raw, key={raw_key}, filename={filename}, bucket={bucket}, api_endpoint={api_endpoint}, status_code={response.status_code}")
    return {
        "layer": "raw",
        "key": raw_key,
        "filename": filename,
        "bucket": bucket,
        "api_endpoint": api_endpoint,
        "status_code": response.status_code
    }
