# Guia de Configuração e Uso do FlareSolverr

O **FlareSolverr** é um servidor proxy open-source que resolve desafios do Cloudflare (Turnstile, 503, 403, Javascript Challenge) em background utilizando um navegador headless interno (Chromium). Ele entrega o HTML da página limpa, cookies de sessão e User-Agent para os scrapers Python.

---

## 🛠️ Como Funciona na Arquitetura

1. O container `flaresolverr` é executado via Docker Compose na porta `8191` e faz parte da rede `airflow_net`.
2. Quando a DAG de Arbitragem (`sports_arbitrage_dag`) executa raspagens nas casas de apostas (Betnacional e Bet365):
   - O módulo `src/dags/lib/scrapers.py` envia uma requisição `POST` para `http://flaresolverr:8191/v1`.
   - O FlareSolverr processa o desafio Cloudflare e retorna os cookies (ex: `cf_clearance`) e o User-Agent correspondente.
   - Os scrapers injetam estes cookies no contexto do Playwright ou requisições HTTP para navegar sem ser bloqueados.

---

## 🚀 Inicialização do Container

Para iniciar o container do FlareSolverr:

```bash
docker compose up -d flaresolverr
```

Para verificar o status e os logs:

```bash
docker compose logs -f flaresolverr
```

---

## 🔍 Teste de Conectividade e Healthcheck

### 1. Teste via curl na API do FlareSolverr

```bash
curl -s -X POST http://localhost:8191/v1 \
  -H "Content-Type: application/json" \
  -d '{
    "cmd": "request.get",
    "url": "https://betnacional.com",
    "maxTimeout": 60000
  }'
```

---

## 💻 Integração no Código Python

```python
from lib.scrapers import fetch_via_flaresolverr

# Soluta o desafio para uma URL
solution = fetch_via_flaresolverr("https://betnacional.com")

if solution:
    html = solution.get("response")       # HTML limpo
    cookies = solution.get("cookies")     # Lista de cookies
    user_agent = solution.get("userAgent")# User-Agent utilizado
```
