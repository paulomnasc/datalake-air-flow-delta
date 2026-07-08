import os
import base64
import json
import logging
import mimetypes
import hashlib
import tempfile
import time
import requests
from datetime import datetime
from typing import Optional, List

log = logging.getLogger(__name__)

def find_niche_websites(niche: str, limit: int = 3, api_key: Optional[str] = None) -> List[str]:
    """
    Usa a IA do Groq para obter uma lista de URLs de e-commerce reais ou exemplos
    de um determinado nicho de produtos.
    """
    log.info(f"[CRAWLER-GROQ] Buscando sites de e-commerce para o nicho: {niche}")
    
    api_key = api_key or os.environ.get("VISION_API_KEY", "")
    api_url = os.environ.get("VISION_API_URL", "https://api.groq.com/openai/v1/chat/completions")
    # Para texto puro, o Llama 3.3 70B é excelente
    text_model = os.environ.get("TEXT_API_MODEL", "llama-3.3-70b-versatile")
    
    if not api_key:
        raise ValueError("[CRAWLER-GROQ] VISION_API_KEY não configurada no ambiente.")
        
    prompt = (
        f"Você é um gerador de dados para testes de pipelines de webscraping. "
        f"Gere uma lista contendo exatamente {limit} URLs/domínios públicos válidos de e-commerce realistas "
        f"ou populares que vendam produtos no nicho de: '{niche}'. "
        "Evite sites que bloqueiam bots agressivamente (como Drogasil, Droga Raia e Raia). "
        "Prefira sites como Panvel (https://www.panvel.com.br), Pague Menos (https://www.paguemenos.com.br) ou outros que permitam scrapers. "
        f"Retorne ESTRITAMENTE um array JSON de strings como no exemplo: "
        f"[\"https://www.exemplo-loja.com.br/produtos\", \"https://www.outra-loja-teste.com/categoria\"] "
        f"Não adicione textos explicativos, markdown de código (como ```json) ou qualquer introdução. Apenas o JSON."
    )
    
    payload = {
        "model": text_model,
        "messages": [
            {
                "role": "user",
                "content": prompt
            }
        ],
        "temperature": 0.2,
        "response_format": {"type": "json_object"}
    }
    
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }
    
    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=60)
        response.raise_for_status()
        res_json = response.json()
        content = res_json['choices'][0]['message']['content'].strip()
        log.info(f"[CRAWLER-GROQ] Resposta bruta do modelo para nicho: {content}")
        
        # Algumas IAs retornam um objeto com uma propriedade contendo a lista
        data = json.loads(content)
        if isinstance(data, list):
            return data
        elif isinstance(data, dict):
            # Se for dicionário, tenta achar a primeira chave que seja lista
            for val in data.values():
                if isinstance(val, list):
                    return val
            # Caso contrário, se o dicionário tiver chaves como "urls" ou "sites"
            if "urls" in data:
                return data["urls"]
            if "sites" in data:
                return data["sites"]
                
        raise ValueError(f"Formato retornado inesperado: {content}")
    except Exception as e:
        log.error(f"[CRAWLER-GROQ] Erro ao buscar sites para o nicho no Groq: {e}")
        # Fallback de teste se a API falhar ou formato estiver corrompido
        fallback_urls = {
            "varejo farmácia": [
                "https://www.panvel.com.br",
                "https://www.paguemenos.com.br"
            ]
        }
        return fallback_urls.get(niche.lower(), [
            "https://www.amazon.com.br",
            "https://www.mercadolivre.com.br"
        ])[:limit]

def capture_screenshot(url: str) -> str:
    """
    Navega no site informado usando Playwright Headless e salva uma captura
    de tela em um arquivo temporário local.
    """
    log.info(f"[CRAWLER-GROQ] Iniciando Playwright para capturar: {url}")
    from playwright.sync_api import sync_playwright
    
    # Criar diretório temporário para salvar o screenshot
    tmpdir = tempfile.mkdtemp()
    filename = f"capture_{int(time.time())}.png"
    screenshot_path = os.path.join(tmpdir, filename)
    
    with sync_playwright() as p:
        # Launch Chromium headless
        browser = p.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"]
        )
        # Criar contexto com User Agent realista para evitar bloqueios
        context = browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
            viewport={"width": 1280, "height": 800},
            extra_http_headers={
                "Accept-Language": "pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7",
                "Referer": "https://www.google.com/"
            }
        )
        context.add_init_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")
        page = context.new_page()
        
        try:
            # Ir para a URL com timeout de 30 segundos
            log.info(f"[CRAWLER-GROQ] Acessando URL: {url}")
            try:
                page.goto(url, wait_until="networkidle", timeout=20000)
            except Exception:
                log.warning("[CRAWLER-GROQ] Timeout aguardando networkidle. Prosseguindo com load.")
                try:
                    page.goto(url, wait_until="load", timeout=15000)
                except Exception:
                    log.warning("[CRAWLER-GROQ] Timeout no load. Tentando prosseguir mesmo assim.")
            
            time.sleep(5) # Aguarda renderizações dinâmicas iniciais
            
            # Tentar aceitar banners de cookie comuns para limpar a imagem
            try:
                cookie_selectors = [
                    "button:has-text('Aceitar')", "button:has-text('Aceito')", 
                    "button:has-text('Ok')", "button:has-text('OK')",
                    "button:has-text('Concordo')", "#lgpd-accept", ".cookie-banner__button",
                    ".accept-cookies", "button[id*='cookie']", "button[class*='cookie']"
                ]
                for selector in cookie_selectors:
                    element = page.locator(selector).first
                    if element.is_visible(timeout=500):
                        element.click()
                        log.info(f"[CRAWLER-GROQ] Banner de cookies aceito com seletor: {selector}")
                        time.sleep(1)
                        break
            except Exception:
                pass # Ignora falhas de clique em cookies
                
            # Rola a página devagar para baixo para carregar os produtos
            try:
                log.info("[CRAWLER-GROQ] Rolando a página incrementalmente para carregar produtos...")
                for i in range(4):
                    page.evaluate("window.scrollBy(0, 350)")
                    time.sleep(1.2)
                # Rola de volta para a seção de produtos (750px de altura)
                page.evaluate("window.scrollTo(0, 750)")
                time.sleep(2)
            except Exception as scroll_err:
                log.warning(f"[CRAWLER-GROQ] Falha ao rolar página: {scroll_err}")
                
            # Captura o screenshot da tela
            page.screenshot(path=screenshot_path, full_page=False)
            log.info(f"[CRAWLER-GROQ] Screenshot salvo em: {screenshot_path}")
            
        except Exception as e:
            log.error(f"[CRAWLER-GROQ] Falha ao capturar screenshot de {url}: {e}")
            # Tira print mesmo se falhar o load completo
            try:
                page.screenshot(path=screenshot_path)
                log.warning(f"[CRAWLER-GROQ] Screenshot parcial salvo de {url} após erro.")
            except Exception:
                screenshot_path = ""
                
        finally:
            try:
                browser.close()
            except Exception as close_err:
                log.warning(f"[CRAWLER-GROQ] Erro ao fechar o navegador: {close_err}")
            
    if screenshot_path and os.path.exists(screenshot_path):
        return screenshot_path
    else:
        raise RuntimeError(f"Não foi possível salvar o screenshot da URL: {url}")


def process_and_ingest_site(
    url: str,
    screenshot_path: str,
    target_table: str,
    api_key: Optional[str] = None,
    api_model: Optional[str] = None,
    **kwargs
) -> dict:
    """
    Processa a imagem capturada enviando ao Groq Vision, extrai os produtos/preços
    e salva os resultados (JSON + Imagem se ativado) na camada Raw do MinIO.
    """
    log.info(f"[CRAWLER-GROQ] Processando extração da imagem {screenshot_path} obtida de {url}")
    
    # 1. Obter configurações
    api_key = api_key or os.environ.get("VISION_API_KEY", "")
    api_model = api_model or os.environ.get("VISION_API_MODEL", "meta-llama/llama-4-scout-17b-16e-instruct")
    api_url = os.environ.get("VISION_API_URL", "https://api.groq.com/openai/v1/chat/completions")
    save_screenshot_env = os.environ.get("VISION_API_SAVE_SCREENSHOT", "true").lower() in ("true", "1", "yes")
    
    if not api_key:
        raise ValueError("[CRAWLER-GROQ] VISION_API_KEY não fornecida.")

    # 2. Codificar Imagem para Base64
    mime_type, _ = mimetypes.guess_type(screenshot_path)
    if not mime_type:
        mime_type = "image/png"
        
    with open(screenshot_path, "rb") as img_file:
        encoded_img = base64.b64encode(img_file.read()).decode("utf-8")
        
    image_data_url = f"data:{mime_type};base64,{encoded_img}"
    
    # 3. Prompt de extração
    prompt = (
        "Você é um extrator de dados estruturados inteligente. Analise a captura de tela do site "
        f"de e-commerce ({url}) fornecida. Identifique os produtos em exibição, seus nomes, seus "
        "preços originais (cheios) e os preços finais (com desconto, se houver). "
        "Retorne estritamente um objeto JSON com uma chave `site` contendo o domínio do site e uma "
        "chave `produtos` contendo uma lista de objetos, onde cada objeto tem os campos: "
        "`produto` (nome do produto), `preco_original` (número ou null), `preco_final` (número). "
        "Não adicione texto explicativo ou markdown fora do JSON."
    )
    
    # 4. Payload
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
    
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }
    
    # 5. Fazer chamada da API
    try:
        response = requests.post(api_url, json=payload, headers=headers, timeout=120)
        response.raise_for_status()
        res_json = response.json()
        raw_content = res_json['choices'][0]['message']['content']
        extracted_data = json.loads(raw_content)
    except Exception as e:
        log.error(f"[CRAWLER-GROQ] Erro ao extrair dados via Groq Vision para {url}: {e}")
        if 'response' in locals() and hasattr(response, 'text'):
            log.error(f"[CRAWLER-GROQ] Detalhes do erro HTTP: {response.text}")
        raise
        
    # 6. Salvar dados no Data Lake (MinIO)
    from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    
    bucket = kwargs.get('owner', 'lab01').replace('_', '-')
    timestamp = int(datetime.now().timestamp())
    url_hash = hashlib.md5(url.encode()).hexdigest()[:12]
    filename_json = f"{timestamp}_{url_hash}.json"
    filename_png = f"{timestamp}_{url_hash}.png"
    
    raw_key_json = f"raw/{target_table}/{filename_json}"
    raw_key_png = f"raw/{target_table}_screenshots/{filename_png}"
    
    # Salvar JSON temporariamente
    tmpdir = tempfile.mkdtemp()
    local_json_path = os.path.join(tmpdir, filename_json)
    with open(local_json_path, "w") as f:
        json.dump(extracted_data, f, indent=2)
        
    # Upload do JSON
    s3_hook = S3Hook(aws_conn_id='minio_conn')
    log.info(f"[CRAWLER-GROQ] Enviando dados extraídos para s3://{bucket}/{raw_key_json}")
    s3_hook.load_file(
        filename=local_json_path,
        key=raw_key_json,
        bucket_name=bucket,
        replace=True
    )
    
    # Upload do screenshot se habilitado
    if save_screenshot_env:
        log.info(f"[CRAWLER-GROQ] Enviando screenshot para s3://{bucket}/{raw_key_png}")
        s3_hook.load_file(
            filename=screenshot_path,
            key=raw_key_png,
            bucket_name=bucket,
            replace=True
        )
    else:
        log.info("[CRAWLER-GROQ] Salvamento de screenshot de auditoria desabilitado pela env var.")

    # Limpeza local
    try:
        os.remove(local_json_path)
        os.remove(screenshot_path)
        os.rmdir(tmpdir)
        # Remove pasta temporária da captura
        os.rmdir(os.path.dirname(screenshot_path))
    except Exception:
        pass
        
    return {
        "url": url,
        "json_key": raw_key_json,
        "image_key": raw_key_png if save_screenshot_env else None,
        "bucket": bucket,
        "products_count": len(extracted_data.get("produtos", []))
    }
