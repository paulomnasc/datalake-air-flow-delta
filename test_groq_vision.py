#!/usr/bin/env python3
import os
import sys
import base64
import json
import time
import mimetypes
from urllib.request import Request, urlopen
from urllib.error import URLError, HTTPError

# Tentativa de carregar o python-dotenv se estiver disponível
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    # Caso não esteja instalado, leremos as variáveis direto do ambiente do OS
    pass

# Configurações via Variáveis de Ambiente com fallbacks padrão para o Groq Cloud
API_URL = os.environ.get("VISION_API_URL", "https://api.groq.com/openai/v1/chat/completions")
API_KEY = os.environ.get("VISION_API_KEY", "")
API_MODEL = os.environ.get("VISION_API_MODEL", "meta-llama/llama-4-scout-17b-16e-instruct")
API_TIMEOUT = int(os.environ.get("VISION_API_TIMEOUT", "60"))

# Prompt padrão para extração de preços
DEFAULT_PROMPT = (
    "Você é um extrator de dados de varejo farmácia. Analise a imagem fornecida e extraia o "
    "nome do produto, o preço cheio e o preço com desconto (se houver). Retorne estritamente "
    "um array JSON estruturado com os campos: `produto`, `preco_original`, `preco_final` (ou null "
    "se não houver desconto). Não adicione texto explicativo antes ou depois do JSON."
)
API_PROMPT = os.environ.get("VISION_API_PROMPT", DEFAULT_PROMPT)

# Habilitar/Desabilitar JSON mode (algumas APIs/modelos exigem ou não suportam)
JSON_MODE = os.environ.get("VISION_API_JSON_MODE", "true").lower() in ("true", "1", "yes")

# Cabeçalhos extras (JSON string opcional para flexibilidade completa)
EXTRA_HEADERS_RAW = os.environ.get("VISION_API_EXTRA_HEADERS", "{}")

def encode_image_to_base64(image_path):
    """Lê uma imagem local e retorna a representação Base64 com o MIME-type correspondente."""
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"Imagem não encontrada no caminho: {image_path}")
    
    mime_type, _ = mimetypes.guess_type(image_path)
    if not mime_type:
        # Fallback genérico caso não consiga adivinhar
        mime_type = "image/png"
        
    with open(image_path, "rb") as image_file:
        encoded_string = base64.b64encode(image_file.read()).decode("utf-8")
        
    return f"data:{mime_type};base64,{encoded_string}"

def test_vision_api(image_path):
    print("=" * 60)
    print("🧪 POC MONITORAMENTO DE PREÇOS - TESTE DE VISÃO COMPUTACIONAL")
    print("=" * 60)
    
    # 1. Validações básicas de configuração
    if not API_KEY:
        print("[AVISO] VISION_API_KEY não está configurada! A requisição pode falhar se a API exigir autenticação.")
    
    print(f"-> Endpoint API: {API_URL}")
    print(f"-> Modelo:       {API_MODEL}")
    masked_key = f"{API_KEY[:6]}...{API_KEY[-4:]}" if len(API_KEY) > 10 else "NÃO CONFIGURADA"
    print(f"-> API Key:      {masked_key}")
    print(f"-> Imagem Alvo:  {image_path}")
    print(f"-> JSON Mode:    {JSON_MODE}")
    
    # 2. Conversão da imagem
    try:
        print("\n[+] Codificando imagem em Base64...")
        image_data_url = encode_image_to_base64(image_path)
        print(f"   Tamanho da imagem base64: {len(image_data_url) / 1024:.2f} KB")
    except Exception as e:
        print(f"[-] Erro ao codificar imagem: {e}")
        return False

    # 3. Construção do Payload compatível com OpenAI/Groq Vision
    payload = {
        "model": API_MODEL,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "text",
                        "text": API_PROMPT
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
        "temperature": 0.0
    }
    
    # Adiciona response_format se o modo JSON estiver ativo
    if JSON_MODE:
        payload["response_format"] = {"type": "json_object"}

    # 4. Construção dos Cabeçalhos HTTP
    headers = {
        "Content-Type": "application/json",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
    }
    if API_KEY:
        headers["Authorization"] = f"Bearer {API_KEY}"
        
    # Adicionar cabeçalhos adicionais definidos na env variable
    try:
        extra_headers = json.loads(EXTRA_HEADERS_RAW)
        for key, val in extra_headers.items():
            headers[key] = val
    except Exception as e:
        print(f"[-] Erro ao fazer o parser de VISION_API_EXTRA_HEADERS: {e}")

    # 5. Execução da requisição HTTP usando urllib padrão (evita dependência externa do requests)
    data = json.dumps(payload).encode("utf-8")
    req = Request(API_URL, data=data, headers=headers, method="POST")
    
    print("\n[+] Enviando requisição para a API...")
    start_time = time.time()
    
    try:
        with urlopen(req, timeout=API_TIMEOUT) as response:
            latency = time.time() - start_time
            status_code = response.getcode()
            response_body = response.read().decode("utf-8")
            
            print(f"[+] Resposta recebida com Sucesso em {latency:.2f} segundos! (Status HTTP: {status_code})")
            
            # Tratar a resposta
            try:
                res_json = json.loads(response_body)
                content = res_json['choices'][0]['message']['content']
                print("\n" + "=" * 60)
                print("📝 CONTEÚDO EXTRAÍDO DA IA:")
                print("=" * 60)
                
                # Tentar formatar como JSON bonito se for um JSON válido
                try:
                    parsed_content = json.loads(content)
                    print(json.dumps(parsed_content, indent=4, ensure_ascii=False))
                except json.JSONDecodeError:
                    # Se não for JSON direto, printa o texto bruto retornado
                    print(content)
                print("=" * 60)
                
                # Exibir detalhes de consumo de tokens (se retornado pela API)
                usage = res_json.get("usage")
                if usage:
                    print(f"\n📊 Uso de Tokens:")
                    print(f"   - Prompt Tokens:     {usage.get('prompt_tokens')}")
                    print(f"   - Completion Tokens: {usage.get('completion_tokens')}")
                    print(f"   - Total Tokens:      {usage.get('total_tokens')}")
                    
                return True
            except Exception as e:
                print(f"[-] Erro ao processar o JSON retornado: {e}")
                print(f"Resposta bruta recebida:\n{response_body}")
                return False
                
    except HTTPError as e:
        latency = time.time() - start_time
        print(f"[-] Erro HTTP ({e.code}) após {latency:.2f} segundos: {e.reason}")
        try:
            error_body = e.read().decode("utf-8")
            print(f"Detalhes do erro do servidor:\n{error_body}")
        except Exception:
            pass
        return False
    except URLError as e:
        latency = time.time() - start_time
        print(f"[-] Erro de Conexão/URL após {latency:.2f} segundos: {e.reason}")
        return False
    except Exception as e:
        latency = time.time() - start_time
        print(f"[-] Erro inesperado após {latency:.2f} segundos: {e}")
        return False

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Uso: python3 test_groq_vision.py <caminho_da_imagem>")
        print("\nExemplo usando imagem existente na stack:")
        print("  python3 test_groq_vision.py imgs/connections.png")
        sys.exit(1)
        
    image_path = sys.argv[1]
    test_vision_api(image_path)
