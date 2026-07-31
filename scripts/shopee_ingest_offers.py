#!/usr/bin/env python3
"""
🛍️ Extrator Customizado de Ofertas de Afiliados Shopee
======================================================
Este script faz a autenticação dinâmica HMAC-SHA256 com a API de Afiliados da Shopee,
executa a consulta GraphQL 'productOfferV2' para um determinado nicho/palavra-chave,
e salva o resultado na camada RAW do MinIO S3 (bucket: paulomnasc-558 / raw/promocao-shopee/).
"""

import os
import sys
import json
import time
import hmac
import hashlib
import logging
import requests
from datetime import datetime

# Configuração de logging
logging.basicConfig(level=logging.INFO, format="[%(asctime)s] %(levelname)s - %(message)s")
log = logging.getLogger(__name__)

# Configurações Padrão
DEFAULT_APP_ID = os.getenv("SHOPEE_APP_ID", "18312081087")
DEFAULT_APP_SECRET = os.getenv("SHOPEE_APP_SECRET", "3XUFNK25O7MSAMSWBDPZM6VMLB5HBPCQ")
SHOPEE_ENDPOINT = "https://open-api.affiliate.shopee.com.br/graphql"
DEFAULT_BUCKET = os.getenv("USER_BUCKET", "paulomnasc-558")
DEFAULT_TARGET_KEY = "raw/promocao-shopee/shopee_data.json"


def generate_shopee_signature(app_id: str, app_secret: str, payload_str: str, timestamp: int, use_hmac: bool = False):
    """
    Gera a assinatura para a API de Afiliados da Shopee.
    base_string = appId + timestamp + payload_str + appSecret
    """
    base_string = f"{app_id}{timestamp}{payload_str}{app_secret}"
    if use_hmac:
        return hmac.new(
            app_secret.encode('utf-8'),
            base_string.encode('utf-8'),
            hashlib.sha256
        ).hexdigest()
    else:
        return hashlib.sha256(base_string.encode('utf-8')).hexdigest()



def fetch_shopee_offers(keyword: str = "moda fitness", limit: int = 50, sort_type: int = 2, app_id: str = DEFAULT_APP_ID, app_secret: str = DEFAULT_APP_SECRET):
    """
    Executa a requisição POST GraphQL na API de Afiliados da Shopee.
    """
    log.info(f"🔎 Buscando ofertas na Shopee para keyword='{keyword}', limit={limit}, sort_type={sort_type}")

    graphql_query = """
    query SearchNicheProducts($keyword: String, $limit: Int, $sortType: Int) {
      productOfferV2(
        keyword: $keyword,
        listType: 0,
        sortType: $sortType,
        page: 0,
        limit: $limit
      ) {
        nodes {
          productName
          productLink
          offerLink
          commissionRate
          commission
          price
          imageUrl
          shopName
          shopType
          productCatIds
        }
        pageInfo {
          page
          limit
          hasNextPage
        }
      }
    }
    """


    variables = {
        "keyword": keyword,
        "limit": limit,
        "sortType": sort_type
    }

    payload_dict = {
        "query": graphql_query,
        "variables": variables
    }

    payload_str = json.dumps(payload_dict, separators=(',', ':'))
    timestamp = int(time.time())

    # Tenta primeiro SHA256 puro (padrão Open API Afiliados)
    for use_hmac in [False, True]:
        signature = generate_shopee_signature(app_id, app_secret, payload_str, timestamp, use_hmac=use_hmac)
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"SHA256 Credential={app_id},Timestamp={timestamp},Signature={signature}"
        }

        log.info(f"🚀 Enviando requisição POST para {SHOPEE_ENDPOINT} (use_hmac={use_hmac})...")
        response = requests.post(SHOPEE_ENDPOINT, data=payload_str, headers=headers, timeout=30)

        log.info(f"📥 Código HTTP retornado: {response.status_code}")
        if response.status_code == 200:
            data = response.json()
            if "errors" in data and data["errors"]:
                err_msg = str(data["errors"])
                if "10020" in err_msg or "Invalid Signature" in err_msg:
                    log.warning(f"⚠️ Assinatura recusada (use_hmac={use_hmac}). Tentando próximo método...")
                    continue
                log.error(f"❌ Erro GraphQL retornado pela Shopee: {json.dumps(data['errors'], indent=2)}")
                raise ValueError(f"GraphQL Error: {data['errors']}")

            offers = data.get("data", {}).get("productOfferV2", {}).get("nodes", [])
            log.info(f"✅ Recebidas {len(offers)} ofertas da Shopee com sucesso!")
            return offers
        else:
            log.error(f"❌ Erro HTTP {response.status_code}: {response.text}")
            response.raise_for_status()

    raise ValueError("Falha na autenticação da Shopee: Assinatura inválida (10020) com ambos os métodos de hash.")




def upload_to_minio_s3(data_dict: dict, bucket_name: str = DEFAULT_BUCKET, target_key: str = DEFAULT_TARGET_KEY):
    """
    Envia o dicionário JSON para o MinIO S3 usando boto3 ou Airflow S3Hook.
    """
    json_str = json.dumps(data_dict, ensure_ascii=False, indent=2)

    # Tenta usar o S3Hook do Airflow (se estiver no ambiente Airflow)
    try:
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        log.info(f"📦 Usando S3Hook (conn_id='minio_conn') para salvar no MinIO...")
        hook = S3Hook(aws_conn_id='minio_conn')

        # Salva o arquivo principal
        hook.load_string(string_data=json_str, key=target_key, bucket_name=bucket_name, replace=True)
        log.info(f"✅ Arquivo salvo em s3://{bucket_name}/{target_key}")

        # Salva uma cópia com timestamp para histórico
        timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
        history_key = f"raw/promocao-shopee/shopee_data_{timestamp_str}.json"
        hook.load_string(string_data=json_str, key=history_key, bucket_name=bucket_name, replace=True)
        log.info(f"✅ Arquivo histórico salvo em s3://{bucket_name}/{history_key}")
        return True

    except Exception as e:
        log.warning(f"⚠️ Não foi possível usar S3Hook ({e}). Tentando fallback boto3...")

    # Fallback para boto3 caso esteja rodando localmente/fora do Airflow
    try:
        import boto3
        endpoint_url = os.getenv("MINIO_ENDPOINT", "http://127.0.0.1:9000")
        access_key = os.getenv("MINIO_ACCESS_KEY", "minioadmin")
        secret_key = os.getenv("MINIO_SECRET_KEY", "minioadmin")

        s3_client = boto3.client(
            's3',
            endpoint_url=endpoint_url,
            aws_access_key_id=access_key,
            aws_secret_access_key=secret_key
        )

        try:
            s3_client.head_bucket(Bucket=bucket_name)
        except Exception:
            log.info(f"📦 Criando bucket '{bucket_name}' no MinIO...")
            s3_client.create_bucket(Bucket=bucket_name)

        s3_client.put_object(
            Bucket=bucket_name,
            Key=target_key,
            Body=json_str.encode('utf-8'),
            ContentType='application/json'
        )
        log.info(f"✅ (boto3) Arquivo salvo em s3://{bucket_name}/{target_key}")

        timestamp_str = datetime.now().strftime("%Y%m%d_%H%M%S")
        history_key = f"raw/promocao-shopee/shopee_data_{timestamp_str}.json"
        s3_client.put_object(
            Bucket=bucket_name,
            Key=history_key,
            Body=json_str.encode('utf-8'),
            ContentType='application/json'
        )
        log.info(f"✅ (boto3) Arquivo histórico salvo em s3://{bucket_name}/{history_key}")
        return True
    except Exception as e:
        log.error(f"❌ Falha ao salvar no MinIO via boto3: {e}")
        raise



def run_ingestion(keyword: str = "moda fitness", limit: int = 50):
    """
    Função principal de orquestração do extrator.
    """
    log.info("=== INICIANDO EXTRAÇÃO DA API DE AFILIADOS SHOPEE ===")
    data = fetch_shopee_offers(keyword=keyword, limit=limit)
    upload_to_minio_s3(data)
    log.info("=== EXTRAÇÃO SHOPEE FINALIZADA COM SUCESSO ===")


if __name__ == "__main__":
    kw = sys.argv[1] if len(sys.argv) > 1 else "moda fitness"
    lim = int(sys.argv[2]) if len(sys.argv) > 2 else 50
    run_ingestion(keyword=kw, limit=lim)
