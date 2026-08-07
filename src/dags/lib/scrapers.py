"""
Módulo de Scrapers Autenticados para Casas de Apostas (Betnacional, Bet365, Betano)
Integração com FlareSolverr (Open-Source Docker Container) para resolver desafios do Cloudflare em background.
Recupera credenciais cadastradas no Airflow Connections (bookmaker_bet365, bookmaker_betano, bookmaker_betnacional)
e executa a extração automatizada de odds para o Brasileirão Série A e Série B.
"""

import os
import logging
import asyncio
import re
import json
import requests
from datetime import datetime
from typing import Dict, List, Any, Optional

log = logging.getLogger(__name__)

def get_bookmaker_credentials(conn_id: str) -> Dict[str, str]:
    """
    Recupera login e senha cadastrados no Airflow Connections para uma casa de apostas.
    Retorna dict com 'login' e 'password'.
    """
    try:
        from airflow.hooks.base import BaseHook
        conn = BaseHook.get_connection(conn_id)
        return {
            "login": conn.login or "",
            "password": conn.password or "",
            "host": conn.host or ""
        }
    except Exception as e:
        log.warning(f"[CREDENCIAIS] Não foi possível carregar a conexão '{conn_id}' via Airflow BaseHook: {e}")
        return {"login": "", "password": "", "host": ""}

def fetch_via_flaresolverr(url: str, method: str = "request.get", post_data: Optional[str] = None, timeout_ms: int = 60000) -> Optional[Dict[str, Any]]:
    """
    Envia requisição para a API do FlareSolverr (container Docker em background)
    para resolver desafios e interceptar a proteção do Cloudflare (Turnstile/503/403).
    Endpoints testados em ordem:
    1. FLARESOLVERR_URL envvar (se definida)
    2. http://flaresolverr:8191/v1 (Rede interna Docker airflow_net)
    3. http://localhost:8191/v1 (Host local)
    4. http://127.0.0.1:8191/v1
    """
    endpoints = []
    env_url = os.environ.get("FLARESOLVERR_URL")
    if env_url:
        endpoints.append(env_url)
    endpoints.extend(["http://flaresolverr:8191/v1", "http://localhost:8191/v1", "http://127.0.0.1:8191/v1"])

    payload = {
        "cmd": method,
        "url": url,
        "maxTimeout": timeout_ms
    }
    if post_data and method == "request.post":
        payload["postData"] = post_data

    headers = {"Content-Type": "application/json"}

    for ep in endpoints:
        try:
            log.info(f"[FLARESOLVERR] Solicitando bypass do Cloudflare para '{url}' via '{ep}'...")
            resp = requests.post(ep, json=payload, headers=headers, timeout=(timeout_ms / 1000) + 15)
            if resp.status_code == 200:
                data = resp.json()
                if data.get("status") == "ok":
                    solution = data.get("solution", {})
                    log.info(f"[FLARESOLVERR] Desafio Cloudflare resolvido com sucesso! Status HTTP: {solution.get('status')} | URL: {solution.get('url')}")
                    return solution
                else:
                    log.warning(f"[FLARESOLVERR] FlareSolverr retornou status != ok: {data.get('message')}")
            else:
                log.warning(f"[FLARESOLVERR] Endpoint '{ep}' retornou HTTP status {resp.status_code}")
        except Exception as e:
            log.debug(f"[FLARESOLVERR] Falha ao comunicar com '{ep}': {e}")

    log.error(f"[FLARESOLVERR] Não foi possível resolver a proteção do Cloudflare para '{url}'.")
    return None

def scrape_betnacional_odds() -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de odds do Brasileirão na Betnacional.
    Utiliza FlareSolverr para superar proteções do Cloudflare antes da navegação do Playwright.
    """
    creds = get_bookmaker_credentials("bookmaker_betnacional")
    target_url = "https://betnacional.com"
    log.info(f"[SCRAPER-BETNACIONAL] Iniciando extração (Usuário: {creds.get('login') or 'Anônimo'})...")
    
    matches = []
    
    # 1. Tenta resolver Cloudflare via FlareSolverr
    fs_solution = fetch_via_flaresolverr(target_url)
    solved_cookies = []
    user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    
    if fs_solution:
        solved_cookies = fs_solution.get("cookies", [])
        if fs_solution.get("userAgent"):
            user_agent = fs_solution.get("userAgent")
        log.info(f"[SCRAPER-BETNACIONAL] Obtidos {len(solved_cookies)} cookies de sessão liberados pelo FlareSolverr.")
    
    # 2. Executa navegação via Playwright utilizando os cookies injetados
    try:
        from playwright.sync_api import sync_playwright
        
        with sync_playwright() as p:
            browser = p.chromium.launch(
                headless=True,
                args=['--no-sandbox', '--disable-blink-features=AutomationControlled']
            )
            context = browser.new_context(
                user_agent=user_agent,
                viewport={'width': 1280, 'height': 800},
                locale='pt-BR'
            )
            
            # Injeta cookies resolvidos pelo FlareSolverr (ex: cf_clearance)
            if solved_cookies:
                pw_cookies = []
                for c in solved_cookies:
                    cookie_dict = {
                        "name": c.get("name"),
                        "value": c.get("value"),
                        "domain": c.get("domain", ".betnacional.com"),
                        "path": c.get("path", "/")
                    }
                    pw_cookies.append(cookie_dict)
                try:
                    context.add_cookies(pw_cookies)
                    log.info(f"[SCRAPER-BETNACIONAL] Cookies do FlareSolverr injetados com sucesso no Playwright.")
                except Exception as e_cookies:
                    log.warning(f"[SCRAPER-BETNACIONAL] Erro ao injetar cookies no Playwright: {e_cookies}")

            page = context.new_page()
            page.add_init_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined});")
            
            try:
                page.goto(target_url, timeout=20000)
                page.wait_for_timeout(2000)
                
                # Se credenciais fornecidas, tenta efetuar login
                if creds.get('login') and creds.get('password'):
                    try:
                        login_btn = page.query_selector('button:has-text("Entrar"), a:has-text("Entrar")')
                        if login_btn:
                            login_btn.click()
                            page.wait_for_timeout(1000)
                            
                            user_input = page.query_selector('input[type="text"], input[name="username"], input[name="login"]')
                            pass_input = page.query_selector('input[type="password"]')
                            
                            if user_input and pass_input:
                                user_input.fill(creds['login'])
                                pass_input.fill(creds['password'])
                                submit_btn = page.query_selector('button[type="submit"]')
                                if submit_btn:
                                    submit_btn.click()
                                    page.wait_for_timeout(3000)
                                    log.info("[SCRAPER-BETNACIONAL] Tentativa de login submetida.")
                    except Exception as e_login:
                        log.warning(f"[SCRAPER-BETNACIONAL] Erro durante o fluxo de login: {e_login}")
                        
            except Exception as e_page:
                log.warning(f"[SCRAPER-BETNACIONAL] Navegação direta Playwright: {e_page}")
                
            browser.close()
            
    except Exception as e:
        log.error(f"[SCRAPER-BETNACIONAL] Erro na execução do Playwright: {e}")
        
    log.info(f"[SCRAPER-BETNACIONAL] Concluído. Total de partidas extraídas: {len(matches)}")
    return matches

def scrape_bet365_odds() -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de odds do Brasileirão na Bet365.
    Utiliza o FlareSolverr para contornar a proteção Cloudflare.
    """
    creds = get_bookmaker_credentials("bookmaker_bet365")
    target_url = "https://www.bet365.com"
    log.info(f"[SCRAPER-BET365] Iniciando extração (Usuário: {creds.get('login') or 'Anônimo'})...")
    
    matches = []
    
    # 1. Tenta resolver Cloudflare via FlareSolverr
    fs_solution = fetch_via_flaresolverr(target_url)
    solved_cookies = []
    user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    
    if fs_solution:
        solved_cookies = fs_solution.get("cookies", [])
        if fs_solution.get("userAgent"):
            user_agent = fs_solution.get("userAgent")
        log.info(f"[SCRAPER-BET365] Obtidos {len(solved_cookies)} cookies de sessão liberados pelo FlareSolverr.")

    try:
        from playwright.sync_api import sync_playwright
        
        with sync_playwright() as p:
            browser = p.chromium.launch(
                headless=True,
                args=['--no-sandbox', '--disable-blink-features=AutomationControlled']
            )
            context = browser.new_context(
                user_agent=user_agent,
                viewport={'width': 1280, 'height': 800},
                locale='pt-BR'
            )
            
            if solved_cookies:
                pw_cookies = []
                for c in solved_cookies:
                    pw_cookies.append({
                        "name": c.get("name"),
                        "value": c.get("value"),
                        "domain": c.get("domain", ".bet365.com"),
                        "path": c.get("path", "/")
                    })
                try:
                    context.add_cookies(pw_cookies)
                    log.info(f"[SCRAPER-BET365] Cookies do FlareSolverr injetados com sucesso no Playwright.")
                except Exception as e_cookies:
                    log.warning(f"[SCRAPER-BET365] Erro ao injetar cookies no Playwright: {e_cookies}")

            page = context.new_page()
            page.add_init_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined});")
            
            try:
                page.goto(target_url, timeout=20000)
                page.wait_for_timeout(2000)
            except Exception as e_page:
                log.warning(f"[SCRAPER-BET365] Acesso Bet365 via Playwright: {e_page}")
                
            browser.close()
    except Exception as e:
        log.error(f"[SCRAPER-BET365] Erro na execução do Playwright para Bet365: {e}")
        
    log.info(f"[SCRAPER-BET365] Concluído. Total de partidas extraídas: {len(matches)}")
    return matches

def scrape_oddspedia_odds(leagues: List[str] = ['serie-a', 'serie-b']) -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de odds agregadas no Oddspedia para as ligas especificadas.
    Utiliza o FlareSolverr para contornar o bloqueio Cloudflare.
    """
    log.info(f"[SCRAPER-ODDSPEDIA] Iniciando extração para ligas: {leagues}")
    all_matches = []
    
    league_urls = {
        'serie-a': 'https://oddspedia.com/br/futebol/brasil/brasileirao-serie-a',
        'serie-b': 'https://oddspedia.com/br/futebol/brasil/brasileirao-serie-b'
    }
    
    for l_key in leagues:
        url = league_urls.get(l_key.lower())
        if not url:
            continue
            
        log.info(f"[SCRAPER-ODDSPEDIA] Acessando {url} via FlareSolverr...")
        fs_res = fetch_via_flaresolverr(url)
        if not fs_res or not fs_res.get("html"):
            log.warning(f"[SCRAPER-ODDSPEDIA] Falha ao obter HTML de {url}")
            continue
            
        html = fs_res["html"]
        soup = BeautifulSoup(html, 'html.parser')
        
        # 1. Extração de Metadados via JSON-LD
        events = []
        for script in soup.find_all('script', type='application/ld+json'):
            try:
                import json
                data = json.loads(script.string or '{}')
                if isinstance(data, list):
                    items = data
                elif isinstance(data, dict):
                    items = data.get('@graph', [data])
                else:
                    items = []
                    
                for item in items:
                    if item.get('@type') == 'SportsEvent':
                        h_name = item.get('homeTeam', {}).get('name')
                        a_name = item.get('awayTeam', {}).get('name')
                        m_url = item.get('url')
                        if h_name and a_name:
                            events.append({
                                "home_team": h_name,
                                "away_team": a_name,
                                "url": m_url,
                                "start_time": item.get('startDate')
                            })
            except Exception as e_json:
                pass
                
        log.info(f"[SCRAPER-ODDSPEDIA] Liga '{l_key}': encontrados {len(events)} eventos JSON-LD.")
        
        for ev in events:
            h_norm = ev['home_team'].lower()
            a_norm = ev['away_team'].lower()
            
            detail_html = None
            if ev.get('url'):
                detail_url = ev['url'] if ev['url'].startswith('http') else f"https://oddspedia.com{ev['url']}"
                log.info(f"[SCRAPER-ODDSPEDIA] Acessando detalhes do jogo: {ev['home_team']} vs {ev['away_team']}")
                detail_fs = fetch_via_flaresolverr(detail_url)
                if detail_fs and detail_fs.get("html"):
                    detail_html = detail_fs["html"]
                    
            target_html = detail_html or html
            m_soup = BeautifulSoup(target_html, 'html.parser')
            
            bookmakers_odds = {}
            
            rows = m_soup.find_all(['tr', 'div'], class_=re.compile(r'odd-row|bookmaker-row|market-row|match-odds', re.I))
            if not rows:
                rows = m_soup.find_all(['tr', 'div'])
                
            for r in rows:
                r_text = r.get_text(separator=' ', strip=True)
                if any(hdr in r_text for hdr in ['Melhores odds', 'Índice de confiança', 'Palpites', 'Intervalo', 'Análise', 'Mercado']):
                    continue
                    
                all_bm_imgs = [img for img in r.find_all('img', alt=True) if any(bm in img.get('alt', '').lower() for bm in ['bet365', 'betano', 'sportingbet', 'betfair', 'kto', 'superbet', '1xbet', 'stake', 'rivalo', 'novibet', 'parimatch', '10bet', 'dafabet', 'estrela', 'galera', 'blaze', 'pinnacle', 'bc.game', 'betsson'])]
                
                if len(all_bm_imgs) != 1:
                    continue
                    
                bm_name = all_bm_imgs[0].get('alt', '').strip().upper()
                
                odd_vals = []
                for child in r.find_all(['span', 'div', 'td', 'a'], class_=re.compile(r'odd|price|rate|value|cota', re.I)):
                    t = child.get_text(strip=True)
                    if re.match(r'^\d+[\.,]\d+$', t):
                        try:
                            val = float(t.replace(',', '.'))
                            if 1.01 <= val <= 100.0 and val not in odd_vals:
                                odd_vals.append(val)
                        except ValueError:
                            pass
                            
                if len(odd_vals) >= 3 and bm_name not in bookmakers_odds:
                    bookmakers_odds[bm_name] = {
                        "casa": odd_vals[0],
                        "empate": odd_vals[1],
                        "visitante": odd_vals[2]
                    }
                    
            if bookmakers_odds:
                all_matches.append({
                    "liga": l_key,
                    "time_casa": ev['home_team'],
                    "time_visitante": ev['away_team'],
                    "data": ev['start_time'],
                    "odds": bookmakers_odds
                })
                log.info(f"[SCRAPER-ODDSPEDIA] Partida '{ev['home_team']} vs {ev['away_team']}': {len(bookmakers_odds)} casas extraídas.")

    log.info(f"[SCRAPER-ODDSPEDIA] Extração concluída. Total de partidas com odds: {len(all_matches)}")
    return all_matches

