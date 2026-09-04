import os
import base64
import json
import logging
import mimetypes
import hashlib
import tempfile
import requests
from datetime import datetime
from typing import Optional

log = logging.getLogger(__name__)

def ingest_groq_vision_to_raw(
    image_path: str,
    target_table_name: Optional[str] = None,
    custom_prompt: Optional[str] = None,
    api_key: Optional[str] = None,
    api_model: Optional[str] = None,
    dag_id: Optional[str] = None,
    **kwargs
):
    """
    Ingesta dados extraídos de uma imagem via Groq Vision API para a camada Raw do Data Lake.
    
    Args:
        image_path: Caminho local da imagem para processamento.
        target_table_name: Nome da tabela de destino no Data Lake (pasta na camada Raw).
        custom_prompt: Prompt customizado de extração para o Groq (se None, usa o padrão).
        api_key: Chave da API do Groq (se None, busca no .env / env variables).
        api_model: Modelo de visão (se None, busca no .env ou usa o padrão da Llama 4).
        dag_id: ID da DAG do Airflow.
    Returns:
        dict: Metadados do arquivo criado na camada Raw.
    """
    log.info(f"[GROQ-VISION→RAW] Iniciando processamento da imagem: {image_path}")
    
    # 1. Carregar chaves e configurações
    api_key = api_key or os.environ.get("VISION_API_KEY", "")
    api_model = api_model or os.environ.get("VISION_API_MODEL", "qwen/qwen3.8-27b")
    api_url = os.environ.get("VISION_API_URL", "https://api.groq.com/openai/v1/chat/completions")
    
    if not api_key:
        raise ValueError("[GROQ-VISION→RAW] A chave de API (VISION_API_KEY) não foi fornecida e não está no ambiente.")

    # Prompt padrão para extração de varejo/farmácia
    default_prompt = (
        "Você é um extrator de dados de varejo farmácia. Analise a imagem fornecida e extraia o "
        "nome do produto, o preço cheio e o preço com desconto (se houver). Retorne estritamente "
        "um array JSON estruturado com os campos: `produto`, `preco_original`, `preco_final` (ou null "
        "se não houver desconto). Não adicione texto explicativo antes ou depois do JSON."
    )
    prompt = custom_prompt or os.environ.get("VISION_API_PROMPT", default_prompt)
    
    # 2. Codificar a imagem em base64
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"[GROQ-VISION→RAW] Imagem não encontrada no caminho: {image_path}")
        
    mime_type, _ = mimetypes.guess_type(image_path)
    if not mime_type:
        mime_type = "image/png"
        
    with open(image_path, "rb") as image_file:
        encoded_string = base64.b64encode(image_file.read()).decode("utf-8")
    
    image_data_url = f"data:{mime_type};base64,{encoded_string}"
    
    # 3. Construir Payload
    payload = {
        "model": api_model,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "text",
                        "text": prompt
                    },
                    {
                        "type": "image_url",
                        "image_url": {
                            "url": image_data_url
                        }
                    }
                ]
            }
        ],
        "temperature": 0.0,
        "response_format": {"type": "json_object"}
    }
    
    # 4. Cabeçalhos HTTP (User-Agent evita bloqueio Cloudflare erro 1010)
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }
    
    # 5. Executar chamada à API
    log.info(f"[GROQ-VISION→RAW] Enviando requisição ao Groq usando modelo: {api_model}")
    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=120)
        log.info(f"[GROQ-VISION→RAW] Resposta HTTP: status={response.status_code}")
        response.raise_for_status()
        
        response_data = response.json()
        raw_content = response_data['choices'][0]['message']['content']
        log.info(f"[GROQ-VISION→RAW] Resposta bruta extraída: {raw_content[:500]}")
        
        # Validar se o conteúdo retornado é um JSON válido
        extracted_json = json.loads(raw_content)
    except Exception as e:
        log.error(f"[GROQ-VISION→RAW] Erro ao chamar a API ou fazer o parse do JSON: {e}")
        if 'response' in locals() and hasattr(response, 'text'):
            log.error(f"[GROQ-VISION→RAW] Corpo da resposta de erro: {response.text}")
        raise

    # 6. Preparar conexões com Airflow e S3/MinIO
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    except ImportError as e:
        log.error("[GROQ-VISION→RAW] S3Hook do Airflow não disponível.")
        raise
        
    bucket = kwargs.get('owner', 'lab01')
    target_table = target_table_name or "groq_scraped_data"
    
    if not dag_id and 'dag' in kwargs:
        dag_id = kwargs['dag'].dag_id
    elif not dag_id and 'ti' in kwargs:
        dag_id = kwargs['ti'].dag_id
    
    folder = target_table
    timestamp = int(datetime.now().timestamp())
    hash_suffix = hashlib.md5(f"{image_path}{timestamp}".encode()).hexdigest()[:20]
    filename = f"{timestamp}_{hash_suffix}.json"
    raw_key = f"raw/{folder}/{filename}"
    
    # 7. Salvar dados em arquivo temporário local
    tmpdir = tempfile.mkdtemp()
    local_file = os.path.join(tmpdir, filename)
    with open(local_file, "w") as f:
        json.dump(extracted_json, f, indent=2)
        
    # 8. Upload para o MinIO
    log.info(f"[GROQ-VISION→RAW] Fazendo upload para s3://{bucket}/{raw_key}")
    hook = S3Hook(aws_conn_id='minio_conn')
    hook.load_file(
        filename=local_file,
        key=raw_key,
        bucket_name=bucket,
        replace=True
    )
    
    # Limpeza
    try:
        os.remove(local_file)
        os.rmdir(tmpdir)
    except Exception:
        pass
        
    log.info(f"[GROQ-VISION→RAW] ✅ Sucesso! Dados salvos na key: {raw_key}")
    
    return {
        "layer": "raw",
        "key": raw_key,
        "filename": filename,
        "bucket": bucket,
        "status_code": response.status_code,
        "usage": response_data.get("usage", {})
    }
