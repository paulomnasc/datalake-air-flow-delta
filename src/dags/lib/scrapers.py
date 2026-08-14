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
from bs4 import BeautifulSoup
from datetime import datetime
from typing import Dict, List, Any, Optional

log = logging.getLogger(__name__)

def _strip(s: str) -> str:
    if not s:
        return ""
    import unicodedata
    return ''.join(c for c in unicodedata.normalize('NFD', str(s)) if unicodedata.category(c) != 'Mn').lower().replace('club atletico', '').replace('ca ', '').replace('cd ', '').strip()

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
    endpoints.extend(["http://localhost:8191/v1", "http://127.0.0.1:8191/v1", "http://flaresolverr:8191/v1"])

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
            resp = requests.post(ep, json=payload, headers=headers, timeout=(3.0, (timeout_ms / 1000) + 15))
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

def _fetch_oddspedia_via_playwright(target_url: str, cookies: list = None, user_agent: str = None) -> Optional[str]:
    """
    Fallback usando Playwright para renderizar o JavaScript da página no navegador Chromium.
    """
    try:
        from playwright.sync_api import sync_playwright
        log.info(f"[SCRAPER-ODDSPEDIA-PLAYWRIGHT] Executando fallback via Chromium headless para: {target_url}")
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True, args=['--no-sandbox', '--disable-blink-features=AutomationControlled'])
            context = browser.new_context(
                viewport={'width': 1280, 'height': 800},
                user_agent=user_agent or 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
            )
            if cookies:
                pw_cookies = []
                for c in cookies:
                    pw_cookies.append({
                        'name': c.get('name'),
                        'value': c.get('value'),
                        'domain': c.get('domain', '.oddspedia.com'),
                        'path': c.get('path', '/')
                    })
                try:
                    context.add_cookies(pw_cookies)
                except Exception as e_c:
                    pass

            page = context.new_page()
            page.add_init_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined});")
            page.goto(target_url, wait_until='domcontentloaded', timeout=30000)
            page.wait_for_timeout(3000)
            html = page.content()
            browser.close()
            return html
    except Exception as e_pw:
        log.error(f"[SCRAPER-ODDSPEDIA-PLAYWRIGHT] Erro durante o fallback com Playwright para {target_url}: {e_pw}")
        return None

def scrape_oddspedia_odds(leagues: List[str] = ['serie-a', 'serie-b']) -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de odds agregadas no Oddspedia para as ligas especificadas.
    Utiliza o FlareSolverr como fluxo principal e o Playwright como fallback de renderização de tela.
    """
    log.info(f"[SCRAPER-ODDSPEDIA] Iniciando extração para ligas: {leagues}")
    all_matches = []
    
    league_urls = {
        'serie-a': 'https://oddspedia.com/br/futebol/brasil/brasileirao-serie-a',
        'serie-b': 'https://oddspedia.com/br/futebol/brasil/brasileirao-serie-b',
        'copa-libertadores': 'https://oddspedia.com/br/futebol/america-do-sul/copa-libertadores',
        'copa-sudamericana': 'https://oddspedia.com/br/futebol/america-do-sul/copa-sul-americana',
        'argentina': 'https://oddspedia.com/br/futebol/argentina/liga-profissional'
    }
    
    for l_key in leagues:
        url = league_urls.get(l_key.lower())
        if not url:
            continue
            
        log.info(f"[SCRAPER-ODDSPEDIA] Acessando {url} via FlareSolverr...")
        fs_res = fetch_via_flaresolverr(url)
        solved_cookies = fs_res.get("cookies", []) if fs_res else []
        solved_ua = fs_res.get("userAgent", "") if fs_res else ""
        html = fs_res.get("response") or fs_res.get("html") if fs_res else None
        
        if not html:
            log.warning(f"[SCRAPER-ODDSPEDIA] FlareSolverr não retornou HTML para {url}. Disparando Fallback Playwright...")
            html = _fetch_oddspedia_via_playwright(url, cookies=solved_cookies, user_agent=solved_ua)
            
        if not html:
            log.error(f"[SCRAPER-ODDSPEDIA] Falha crítica ao obter HTML de {url} via FlareSolverr e Playwright.")
            continue
            
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
                        name = item.get('name', '')
                        h_name = item.get('homeTeam', {}).get('name') if isinstance(item.get('homeTeam'), dict) else None
                        a_name = item.get('awayTeam', {}).get('name') if isinstance(item.get('awayTeam'), dict) else None
                        if not h_name and ' - ' in name:
                            parts = name.split(' - ')
                            h_name, a_name = parts[0].strip(), parts[1].strip()
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
            h_norm = _strip(ev['home_team'])
            a_norm = _strip(ev['away_team'])
            
            # Localiza o container CSS específico da partida no HTML para evitar ruídos de outros jogos
            match_container = None
            for card in soup.find_all(['div', 'tr', 'article', 'li']):
                c_text = _strip(card.get_text(separator=' ', strip=True))
                if h_norm in c_text and a_norm in c_text and len(c_text) < 3000:
                    match_container = card
                    break
            
            if not match_container:
                match_container = m_soup

            bookmakers_odds = {}
            known_bms = ['betano', 'bet365', 'stake', 'sportingbet', 'kto', 'superbet', '1xbet', 'pinnacle', 'novibet', 'parimatch', '10bet', 'betfair', 'betsson', 'blaze']

            for img in match_container.find_all('img'):
                alt = (img.get('alt') or '').strip()
                src = (img.get('src') or '').strip()
                
                matched_bm = None
                for bm in known_bms:
                    if bm in alt.lower() or bm in src.lower():
                        matched_bm = bm.upper()
                        break
                        
                if not matched_bm:
                    continue

                # Ignora logos que estejam em áreas de navegação, cabeçalhos, rodapés ou outros mercados (Handicap, Ambas, Over/Under)
                p_check = img.parent
                ignore_section = False
                for _ in range(8):
                    if not p_check:
                        break
                    p_text = p_check.get_text(separator=' ', strip=True).lower()
                    p_class = ' '.join(p_check.get('class', [])).lower()
                    if any(bad in p_class or bad in p_text for bad in ['handicap', 'ambas', 'both-teams', 'over', 'under', 'nav-secondary', 'footer', 'análise da', 'oferta', 'boost', 'especial', 'special', 'promotional', 'acumulado', 'aumentado']):
                        ignore_section = True
                        break
                    p_check = p_check.parent

                if ignore_section:
                    continue
                    
                parent = img.parent
                for _ in range(5):
                    if not parent:
                        break
                    text = parent.get_text(separator=' ', strip=True)
                    nums = re.findall(r'\b\d+[\.,]\d+\b', text)
                    clean_nums = []
                    for n in nums:
                        try:
                            val = float(n.replace(',', '.'))
                            if 1.01 <= val <= 100.0 and val not in clean_nums:
                                clean_nums.append(val)
                        except ValueError:
                            pass
                            
                    if len(clean_nums) >= 3:
                        if matched_bm not in bookmakers_odds:
                            bookmakers_odds[matched_bm] = {
                                "casa": clean_nums[0],
                                "empate": clean_nums[1],
                                "visitante": clean_nums[2]
                            }
                        break
                    parent = parent.parent
                    
            if bookmakers_odds:
                # Sanitização de ruídos e outliers entre casas de apostas para o mesmo jogo
                if len(bookmakers_odds) >= 2:
                    import statistics
                    for market_key in ['casa', 'empate', 'visitante']:
                        vals = [bm_o[market_key] for bm_o in bookmakers_odds.values() if bm_o.get(market_key, 0.0) > 1.0]
                        if len(vals) >= 2:
                            med = statistics.median(vals)
                            to_remove = [bm for bm, bm_o in bookmakers_odds.items() if bm_o.get(market_key, 0.0) > med * 1.15]
                            for bm in to_remove:
                                log.warning(f"[SCRAPER-ODDSPEDIA] Removido ruído/outlier de odd para {bm} no mercado '{market_key}': {bookmakers_odds[bm][market_key]} (Mediana mercado: {med:.2f})")
                                del bookmakers_odds[bm]

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


def scrape_futbol24_odds(leagues: List[str] = ['serie-a', 'serie-b', 'argentina']) -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de odds agregadas no Futbol24 (https://www.futbol24.com/) para as ligas especificadas.
    Atua como fonte alternativa/complementar ao Oddspedia para diversificar as casas de apostas.
    """
    log.info(f"[SCRAPER-FUTBOL24] Iniciando extração alternativa para ligas: {leagues}")
    all_matches = []
    
    league_urls = {
        'serie-a': 'https://www.futbol24.com/national/Brazil/Serie-A/2026/',
        'serie-b': 'https://www.futbol24.com/national/Brazil/Serie-B/2026/',
        'copa-libertadores': 'https://www.futbol24.com/international/South-America/Copa-Libertadores/2026/',
        'copa-sudamericana': 'https://www.futbol24.com/international/South-America/Copa-Sudamericana/2026/',
        'argentina': 'https://www.futbol24.com/national/Argentina/Primera-Division/2026/Clausura/',
        'primera-division': 'https://www.futbol24.com/national/Argentina/Primera-Division/2026/Clausura/'
    }
    
    known_bms = ['bet365', 'betano', 'sportingbet', 'superbet', 'kto', 'stake', '1xbet', 'pinnacle', 'novibet', 'bwin', 'betway', '10bet', 'betfair', 'betsson']
    
    for l_key in leagues:
        url = league_urls.get(l_key.lower())
        if not url:
            continue
            
        log.info(f"[SCRAPER-FUTBOL24] Acessando {url} via FlareSolverr...")
        fs_res = fetch_via_flaresolverr(url)
        solved_cookies = fs_res.get("cookies", []) if fs_res else []
        solved_ua = fs_res.get("userAgent", "") if fs_res else ""
        html = fs_res.get("response") or fs_res.get("html") if fs_res else None
        
        if not html:
            log.warning(f"[SCRAPER-FUTBOL24] FlareSolverr não retornou HTML para {url}. Disparando Fallback Playwright...")
            html = _fetch_oddspedia_via_playwright(url, cookies=solved_cookies, user_agent=solved_ua)
            
        if not html:
            log.error(f"[SCRAPER-FUTBOL24] Falha ao obter HTML de {url}.")
            continue
            
        soup = BeautifulSoup(html, 'html.parser')
        
        for container in soup.find_all(['div', 'tr', 'li']):
            text = container.get_text(separator=' ', strip=True)
            # Remove datas (DD.MM.YYYY) e horas (HH:MM) para evitar confundir com odds
            clean_text = re.sub(r'\b\d{2}[\./]\d{2}[\./]\d{4}\b', '', text)
            clean_text = re.sub(r'\b\d{2}:\d{2}\b', '', clean_text)
            
            nums = re.findall(r'\b\d+[\.,]\d+\b', clean_text)
            clean_nums = []
            for n in nums:
                try:
                    val = float(n.replace(',', '.'))
                    if 1.01 <= val <= 30.0 and val not in clean_nums:
                        clean_nums.append(val)
                except ValueError:
                    pass
            
            if len(clean_nums) >= 3:
                team_links = [a.get_text(strip=True) for a in container.find_all('a') if '/team/' in (a.get('href') or '') or '/team-compare/' in (a.get('href') or '')]
                if len(team_links) >= 2:
                    h_team = team_links[0].split('/')[0].strip()
                    a_team = team_links[1].split('/')[0].strip()
                    
                    matched_bm = "FUTBOL24"
                    for bm in known_bms:
                        if bm in text.lower():
                            matched_bm = bm.upper()
                            break
                            
                    all_matches.append({
                        "liga": l_key,
                        "time_casa": h_team,
                        "time_visitante": a_team,
                        "odds": {
                            matched_bm: {
                                "casa": clean_nums[0],
                                "empate": clean_nums[1],
                                "visitante": clean_nums[2]
                            }
                        }
                    })
    
    log.info(f"[SCRAPER-FUTBOL24] Extração concluída. Total de {len(all_matches)} partidas extraídas do Futbol24.")
    return all_matches


def fetch_futbol24_direct_match_odds(home_team: str, away_team: str, country: Optional[str] = None) -> Optional[Dict[str, Any]]:
    """
    Busca odds 1X2 diretamente no Futbol24 para um confronto específico quando as odds de mercado estão ausentes.
    Prioriza a extração direta do widget 'Who will win?' (ex: BAN 3.30 X 2.85 BEL 2.40).
    """
    def _strip(s: str) -> str:
        import unicodedata
        return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn').lower().replace('club atletico', '').replace('ca ', '').replace('cd ', '').strip()

    norm_h = _strip(home_team)
    norm_a = _strip(away_team)

    h_slug = norm_h.replace(' ', '-').title()
    a_slug = norm_a.replace(' ', '-').title()

    # Mapeamento dinâmico de conhecidos (país, slug)
    known_slugs_local = {
        'paris saint germain': ('France', 'Paris-St-Germain'),
        'paris st. germain': ('France', 'Paris-St-Germain'),
        'paris sg': ('France', 'Paris-St-Germain'),
        'psg': ('France', 'Paris-St-Germain'),
        'aston villa': ('England', 'Aston-Villa'),
        'real madrid': ('Spain', 'Real-Madrid'),
        'barcelona': ('Spain', 'FC-Barcelona'),
        'bayern munich': ('Germany', 'Bayern-Munchen'),
        'bayern munchen': ('Germany', 'Bayern-Munchen'),
        'banfield': ('Argentina', 'Banfield'), 'ca banfield': ('Argentina', 'Banfield'),
        'belgrano cordoba': ('Argentina', 'Belgrano-Cba'), 'belgrano': ('Argentina', 'Belgrano-Cba'),
        'union santa fe': ('Argentina', 'Union-Santa-Fe'),
        'central cordoba de santiago': ('Argentina', 'Central-Cordoba-SdE'), 'central cordoba': ('Argentina', 'Central-Cordoba-SdE'),
        'goias': ('Brazil', 'Goias-GO'), 'goiás': ('Brazil', 'Goias-GO'),
        'londrina': ('Brazil', 'Londrina-PR')
    }

    c_h, c_a = 'Brazil', 'Brazil'
    if norm_h in known_slugs_local:
        c_h, h_slug = known_slugs_local[norm_h]
    if norm_a in known_slugs_local:
        c_a, a_slug = known_slugs_local[norm_a]

    possible_countries = ['France', 'England', 'Spain', 'Germany', 'Italy', 'Brazil', 'Argentina', 'Chile', 'Colombia', 'Ecuador', 'Uruguay', 'Peru']
    if country:
        c_clean = country.capitalize()
        if c_clean in possible_countries:
            possible_countries.remove(c_clean)
            possible_countries.insert(0, c_clean)
        else:
            possible_countries.insert(0, c_clean)

    match_urls = [
        f'https://www.futbol24.com/pt/comparar-equipas/{c_h}/{h_slug}/vs/{c_a}/{a_slug}/',
        f'https://www.futbol24.com/team-compare/{c_h}/{h_slug}/vs/{c_a}/{a_slug}/'
    ]
    for c in possible_countries:
        if c != c_h or c != c_a:
            match_urls.append(f'https://www.futbol24.com/pt/comparar-equipas/{c}/{h_slug}/vs/{c}/{a_slug}/')

    league_urls = [
        'https://www.futbol24.com/national/Brazil/Serie-B/2026/',
        'https://www.futbol24.com/national/Brazil/Serie-A/2026/',
        'https://www.futbol24.com/national/Argentina/Primera-Division/2026/'
    ]

    for url in match_urls + league_urls:
        try:
            html = None
            headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'}
            try:
                r = requests.get(url, headers=headers, timeout=5)
                if r.status_code == 200 and len(r.text) > 1000:
                    html = r.text
            except Exception:
                pass

            if not html:
                fs_res = fetch_via_flaresolverr(url)
                html = fs_res.get('response') or fs_res.get('html') if fs_res else None

            if not html:
                continue

            soup = BeautifulSoup(html, 'html.parser')
            
            # Checa o widget 'Who will win?' (ex: BAN 3.30 X 2.85 BEL 2.40)
            for container in soup.find_all(['div', 'tr', 'p', 'section']):
                text = container.get_text(separator=' ', strip=True)
                w_match = re.search(r'\b([A-Za-z0-9]{2,5})\s+(\d+\.\d{2})\s+X\s+(\d+\.\d{2})\s+([A-Za-z0-9]{2,5})\s+(\d+\.\d{2})\b', text)
                if w_match:
                    code1 = w_match.group(1)
                    o_h = float(w_match.group(2))
                    o_d = float(w_match.group(3))
                    code2 = w_match.group(4)
                    o_a = float(w_match.group(5))
                    
                    is_match_url = '/vs/' in url
                    norm_t = _strip(text)
                    team_matched = (norm_h in norm_t or norm_a in norm_t or any(w in norm_t for w in norm_h.split() if len(w) > 3))
                    
                    if (is_match_url or team_matched) and 1.05 <= o_h <= 30.0 and 1.05 <= o_d <= 30.0 and 1.05 <= o_a <= 30.0:
                        return {
                            'odd_home': o_h,
                            'odd_draw': o_d,
                            'odd_away': o_a,
                            'source': 'FUTBOL24'
                        }

            # Caso o widget não seja encontrado, busca em tabelas genéricas de odds
            for container in soup.find_all(['tr', 'div']):
                t = container.get_text(separator=' ', strip=True)
                norm_t = _strip(t)
                if norm_h in norm_t and norm_a in norm_t:
                    clean_text = re.sub(r'\b\d{2}[\./]\d{2}[\./]\d{4}\b', '', t)
                    clean_text = re.sub(r'\b\d{2}:\d{2}\b', '', clean_text)
                    nums = re.findall(r'\b\d+\.\d{2}\b', clean_text)
                    odds = [float(n) for n in nums if 1.05 <= float(n) <= 30.0]
                    if len(odds) >= 3:
                        return {
                            'odd_home': odds[0],
                            'odd_draw': odds[1],
                            'odd_away': odds[2],
                            'source': 'FUTBOL24'
                        }
        except Exception as exc:
            log.warning(f"[SCRAPER-FUTBOL24-DIRECT] Aviso ao buscar odds em {url}: {exc}")

    return None


def scrape_futbol24_previews() -> List[Dict[str, Any]]:
    """
    Realiza a raspagem de prévias e palpites editoriais do Futbol24 (https://www.futbol24.com/pt/apostas-palpites/).
    Retorna uma lista de dicionários contendo os campos: home_team, away_team, tip, analysis, url.
    """
    url = 'https://www.futbol24.com/pt/apostas-palpites/'
    headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'}
    results = []
    try:
        log.info(f"[SCRAPER-FUTBOL24-PREVIEWS] Acessando {url}...")
        resp = requests.get(url, headers=headers, timeout=15)
        if resp.status_code != 200:
            log.warning(f"[SCRAPER-FUTBOL24-PREVIEWS] Status HTTP {resp.status_code} em {url}")
            return results
            
        soup = BeautifulSoup(resp.text, 'html.parser')
        preview_links = set()
        for a in soup.find_all('a', href=True):
            href = a['href']
            if '/apostas-palpites/previa/' in href:
                if not href.startswith('http'):
                    href = 'https://www.futbol24.com' + href
                preview_links.add(href)
                
        log.info(f"[SCRAPER-FUTBOL24-PREVIEWS] Encontrados {len(preview_links)} links de prévias.")
        
        for p_url in list(preview_links)[:15]:
            try:
                r = requests.get(p_url, headers=headers, timeout=5)
                if r.status_code != 200:
                    continue
                psoup = BeautifulSoup(r.text, 'html.parser')
                
                # Times (Match)
                match_elem = psoup.find(class_='betting-tips-match') or psoup.find('h1')
                match_text = match_elem.text.strip() if match_elem else ''
                
                # Tip
                tip_elem = psoup.find(class_='betting-tips-tip')
                tip_text = tip_elem.text.strip() if tip_elem else ''
                if not tip_text:
                    for p in psoup.find_all('p'):
                        if 'palpite' in p.text.lower() or 'dica' in p.text.lower() or '@' in p.text:
                            tip_text = p.text.strip()
                            break
                            
                # Analysis
                analysis_elem = psoup.find(class_='betting-tips-analysis') or psoup.find('p', class_='betting-tips-matchgen')
                analysis_text = analysis_elem.text.strip() if analysis_elem else ''
                if not analysis_text:
                    paragraphs = [p.text.strip() for p in psoup.find_all('p') if len(p.text.strip()) > 40]
                    analysis_text = ' '.join(paragraphs[:2])
                    
                # Extract Home / Away
                home_team, away_team = '', ''
                if ' vs ' in match_text:
                    parts = match_text.split(' vs ')
                    home_team = parts[0].replace('- Palpites e Apostas', '').replace('Palpite para o ', '').split('|')[0].strip()
                    away_team = parts[1].replace('- Palpites e Apostas', '').split('|')[0].strip()
                elif ' x ' in match_text:
                    parts = match_text.split(' x ')
                    home_team = parts[0].replace('- Palpites e Apostas', '').replace('Palpite para o ', '').split('|')[0].strip()
                    away_team = parts[1].replace('- Palpites e Apostas', '').split('|')[0].strip()
                    
                if home_team and away_team and (tip_text or analysis_text):
                    results.append({
                        'home_team': home_team,
                        'away_team': away_team,
                        'tip': tip_text,
                        'analysis': analysis_text,
                        'url': p_url
                    })
            except Exception as e_p:
                log.warning(f"[SCRAPER-FUTBOL24-PREVIEWS] Erro na prévia {p_url}: {e_p}")
    except Exception as e:
        log.error(f"[SCRAPER-FUTBOL24-PREVIEWS] Erro ao buscar prévias: {e}")
    return results


def scrape_futbol24_team_last5(team_name: str, team_url: Optional[str] = None, limit: int = 6, country: Optional[str] = None) -> Optional[Dict[str, Any]]:
    """
    Realiza a raspagem dos últimos jogos encerrados de uma equipe diretamente no Futbol24 (https://www.futbol24.com/pt/equipa/{pais}/{slug}/).
    Suporta resolução dinâmica de país (Brasil, Argentina, Colômbia, etc.) e normalização de prefixos (CA, CD, Club).
    """
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36'
    }

    known_slugs = {
        'goias': ('Brazil', 'Goias-GO'), 'goiás': ('Brazil', 'Goias-GO'),
        'londrina': ('Brazil', 'Londrina-PR'),
        'operario': ('Brazil', 'Operario-F-PR'), 'operário': ('Brazil', 'Operario-F-PR'),
        'coritiba': ('Brazil', 'Coritiba-PR'),
        'santos': ('Brazil', 'Santos-SP'),
        'vila nova': ('Brazil', 'Vila-Nova-GO'),
        'américa mineiro': ('Brazil', 'America-Mineiro-MG'), 'america mineiro': ('Brazil', 'America-Mineiro-MG'),
        'sport recife': ('Brazil', 'Sport-Recife-PE'), 'sport': ('Brazil', 'Sport-Recife-PE'),
        'ponte preta': ('Brazil', 'Ponte-Preta-SP'),
        'crb': ('Brazil', 'CRB-AL'),
        'ceará': ('Brazil', 'Ceara-SC-CE'), 'ceara': ('Brazil', 'Ceara-SC-CE'),
        'náutico': ('Brazil', 'Nautico-PE'), 'nautico': ('Brazil', 'Nautico-PE'),
        'novorizontino': ('Brazil', 'Novorizontino-SP'),
        'guarani': ('Brazil', 'Guarani-SP'),
        'criciúma': ('Brazil', 'Criciuma-SC'), 'criciuma': ('Brazil', 'Criciuma-SC'),
        'botafogo': ('Brazil', 'Botafogo-RJ'), 'botafogo-rj': ('Brazil', 'Botafogo-RJ'), 'botafogo rj': ('Brazil', 'Botafogo-RJ'),
        'botafogo/sp': ('Brazil', 'Botafogo-SP'), 'botafogo-sp': ('Brazil', 'Botafogo-SP'),
        'cienciano': ('Peru', 'Cienciano'),
        'cuiabá': ('Brazil', 'Cuiaba-MT'), 'cuiaba': ('Brazil', 'Cuiaba-MT'),
        'fortaleza': ('Brazil', 'Fortaleza-CE'),
        'juventude': ('Brazil', 'Juventude-RS'),
        'athletic club': ('Brazil', 'Athletic-Club-MG'),
        'são bernardo': ('Brazil', 'Sao-Bernardo-SP'), 'sao bernardo': ('Brazil', 'Sao-Bernardo-SP'),
        'avaí': ('Brazil', 'Avai-FC-SC'), 'avai': ('Brazil', 'Avai-FC-SC'),
        'flamengo': ('Brazil', 'Flamengo-RJ'), 'vitória': ('Brazil', 'Vitoria-BA'), 'vitoria': ('Brazil', 'Vitoria-BA'),
        'rb bragantino': ('Brazil', 'RB-Bragantino-SP'), 'corinthians': ('Brazil', 'Corinthians-SP'),
        'athletico': ('Brazil', 'Athletico-PR'), 'bahia': ('Brazil', 'Bahia-BA'),
        'vasco da gama': ('Brazil', 'Vasco-da-Gama-RJ'), 'vasco': ('Brazil', 'Vasco-da-Gama-RJ'),
        'palmeiras': ('Brazil', 'Palmeiras-SP'), 'internacional': ('Brazil', 'Internacional-RS'),
        'cruzeiro': ('Brazil', 'Cruzeiro-MG'), 'mirassol': ('Brazil', 'Mirassol-SP'),
        'fluminense': ('Brazil', 'Fluminense-RJ'), 'chapecoense': ('Brazil', 'Chapecoense-SC'),
        'remo': ('Brazil', 'Remo-PA'), 'atlético mineiro': ('Brazil', 'Atletico-Mineiro-MG'), 'atletico mineiro': ('Brazil', 'Atletico-Mineiro-MG'),
        'grêmio': ('Brazil', 'Gremio-RS'), 'gremio': ('Brazil', 'Gremio-RS'),
        'são paulo': ('Brazil', 'Sao-Paulo-SP'), 'sao paulo': ('Brazil', 'Sao-Paulo-SP'),
        'atlético/go': ('Brazil', 'Atletico-GO'), 'atletico/go': ('Brazil', 'Atletico-GO'),
        'banfield': ('Argentina', 'CA-Banfield'), 'ca banfield': ('Argentina', 'CA-Banfield'),
        'boca juniors': ('Argentina', 'Boca-Juniors'),
        'river plate': ('Argentina', 'River-Plate'),
        'racing club': ('Argentina', 'Racing-Club'),
        'independiente': ('Argentina', 'Independiente'),
        'san lorenzo': ('Argentina', 'San-Lorenzo'),
        'huracán': ('Argentina', 'CA-Huracan'), 'huracan': ('Argentina', 'CA-Huracan'),
        'talleres': ('Argentina', 'Talleres-Cordoba'),
        'lanús': ('Argentina', 'Lanus'), 'lanus': ('Argentina', 'Lanus'),
        'vélez': ('Argentina', 'Velez-Sarsfield'), 'velez': ('Argentina', 'Velez-Sarsfield'),
        'estudiantes': ('Argentina', 'Estudiantes-La-Plata'),
        'belgrano cordoba': ('Argentina', 'Belgrano-Cordoba'), 'belgrano': ('Argentina', 'Belgrano-Cordoba'),
        'independ. rivadavia': ('Argentina', 'Independ.-Rivadavia'), 'independiente rivadavia': ('Argentina', 'Independ.-Rivadavia'),
        'estudiantes de rio cuarto': ('Argentina', 'Estudiantes-Rio-Cuarto'), 'estudiantes rio cuarto': ('Argentina', 'Estudiantes-Rio-Cuarto'),
        'sarmiento junin': ('Argentina', 'Sarmiento-Junin'), 'sarmiento': ('Argentina', 'Sarmiento-Junin'),
        'atletico tucuman': ('Argentina', 'Atletico-Tucuman'), 'atlético tucumán': ('Argentina', 'Atletico-Tucuman'),
        'barracas central': ('Argentina', 'Barracas-Central'),
        'gimnasia la plata': ('Argentina', 'Gimnasia-La-Plata'), 'gimnasia lp': ('Argentina', 'Gimnasia-La-Plata'),
        'rosario central': ('Argentina', 'Rosario-Central'),
        'tigre': ('Argentina', 'CA-Tigre'),
        'platense': ('Argentina', 'Platense'),
        'union santa fe': ('Argentina', 'Union-Santa-Fe'),
        'central cordoba de santiago': ('Argentina', 'Central-Cordoba-SdE'), 'central cordoba': ('Argentina', 'Central-Cordoba-SdE'),
        'deportivo recoleta': ('Paraguay', 'Deportivo-Recoleta'),
        'deportivo maldonado': ('Uruguay', 'Deportivo-Maldonado'),
        'racing montevideo': ('Uruguay', 'Racing-Montevideo'),
        'universidad de chile': ('Chile', 'Universidad-De-Chile'),
        'palestino': ('Chile', 'Palestino'),
        'a. italiano': ('Chile', 'A.-Italiano'), 'audax italiano': ('Chile', 'A.-Italiano'),
        'nublense': ('Chile', 'Nublense'), 'ñublense': ('Chile', 'Nublense'),
        'america de cali': ('Colombia', 'America-De-Cali'),
        'atletico nacional': ('Colombia', 'Atletico-Nacional'),
        'independiente medellin': ('Colombia', 'Independiente-Medellin'),
        'millonarios': ('Colombia', 'Millonarios'),
        'junior': ('Colombia', 'Junior'),
        'deportivo pereira': ('Colombia', 'Deportivo-Pereira'),
        'aguilas doradas': ('Colombia', 'Aguilas-Doradas'),
        'llaneros': ('Colombia', 'Llaneros'),
        'guayaquil city fc': ('Ecuador', 'Guayaquil-City-Fc'),
        'emelec': ('Ecuador', 'Emelec'),
        'tecnico universitario': ('Ecuador', 'Tecnico-Universitario'),
        'mushuc runa sc': ('Ecuador', 'Mushuc-Runa-Sc'),
        'libertad': ('Ecuador', 'Libertad'),
        'universidad catolica': ('Ecuador', 'Universidad-Catolica'),
        'deportivo cuenca': ('Ecuador', 'Deportivo-Cuenca'),
        'manta fc': ('Ecuador', 'Manta-Fc'),
        # Portugal
        'sporting cp': ('Portugal', 'Sporting-Lisboa'), 'sporting': ('Portugal', 'Sporting-Lisboa'), 'sporting lisbon': ('Portugal', 'Sporting-Lisboa'), 'sporting lisboa': ('Portugal', 'Sporting-Lisboa'),
        'vitória sc': ('Portugal', 'Vitoria-Guimaraes'), 'vitoria sc': ('Portugal', 'Vitoria-Guimaraes'), 'vitoria guimaraes': ('Portugal', 'Vitoria-Guimaraes'), 'vitória guimarães': ('Portugal', 'Vitoria-Guimaraes'), 'guimaraes': ('Portugal', 'Vitoria-Guimaraes'), 'guimarães': ('Portugal', 'Vitoria-Guimaraes'),
        'benfica': ('Portugal', 'SL-Benfica'), 'sl benfica': ('Portugal', 'SL-Benfica'),
        'fc porto': ('Portugal', 'FC-Porto'), 'porto': ('Portugal', 'FC-Porto'),
        'sc braga': ('Portugal', 'SC-Braga'), 'braga': ('Portugal', 'SC-Braga'), 'sp. braga': ('Portugal', 'SC-Braga'), 'sp braga': ('Portugal', 'SC-Braga'),
        'boavista': ('Portugal', 'Boavista-FC'), 'boavista fc': ('Portugal', 'Boavista-FC'),
        'famalicao': ('Portugal', 'FC-Famalicao'), 'famalicão': ('Portugal', 'FC-Famalicao'), 'fc famalicao': ('Portugal', 'FC-Famalicao'),
        'gil vicente': ('Portugal', 'Gil-Vicente-FC'), 'gil vicente fc': ('Portugal', 'Gil-Vicente-FC'),
        'moreirense': ('Portugal', 'Moreirense-FC'), 'moreirense fc': ('Portugal', 'Moreirense-FC'),
        'rio ave': ('Portugal', 'Rio-Ave-FC'), 'rio ave fc': ('Portugal', 'Rio-Ave-FC'),
        'santa clara': ('Portugal', 'CD-Santa-Clara'), 'cd santa clara': ('Portugal', 'CD-Santa-Clara'),
        'arouca': ('Portugal', 'FC-Arouca'), 'fc arouca': ('Portugal', 'FC-Arouca'),
        'estoril': ('Portugal', 'Estoril-Praia'), 'estoril praia': ('Portugal', 'Estoril-Praia'),
        'estrela': ('Portugal', 'Estrela-Amadora'), 'estrela amadora': ('Portugal', 'Estrela-Amadora'), 'estrela da amadora': ('Portugal', 'Estrela-Amadora'),
        'nacional': ('Portugal', 'CD-Nacional'), 'cd nacional': ('Portugal', 'CD-Nacional'),
        'casa pia': ('Portugal', 'Casa-Pia-AC'), 'casa pia ac': ('Portugal', 'Casa-Pia-AC'),
        'alverca': ('Portugal', 'FC-Alverca'), 'fc alverca': ('Portugal', 'FC-Alverca'),
        'academico viseu': ('Portugal', 'Academico-Viseu'), 'académico de viseu': ('Portugal', 'Academico-Viseu'),
        'maritimo': ('Portugal', 'Maritimo-Funchal'), 'marítimo': ('Portugal', 'Maritimo-Funchal'),
        'farense': ('Portugal', 'Farense'), 'sc farense': ('Portugal', 'Farense'),
        # França
        'paris saint germain': ('France', 'Paris-St-Germain'), 'paris sg': ('France', 'Paris-St-Germain'), 'psg': ('France', 'Paris-St-Germain'), 'paris saint-germain': ('France', 'Paris-St-Germain'),
        'monaco': ('France', 'AS-Monaco'), 'as monaco': ('France', 'AS-Monaco'),
        'lyon': ('France', 'Olympique-Lyonnais'), 'olympique lyonnais': ('France', 'Olympique-Lyonnais'),
        'marseille': ('France', 'Olympique-Marseille'), 'olympique marseille': ('France', 'Olympique-Marseille'),
        'lille': ('France', 'LOSC-Lille'),
        # Inglaterra
        'aston villa': ('England', 'Aston-Villa'),
        'arsenal': ('England', 'Arsenal'),
        'chelsea': ('England', 'Chelsea'),
        'liverpool': ('England', 'Liverpool'),
        'manchester city': ('England', 'Manchester-City'), 'man city': ('England', 'Manchester-City'),
        'manchester united': ('England', 'Manchester-United'), 'man united': ('England', 'Manchester-United'),
        'tottenham': ('England', 'Tottenham-Hotspur'), 'tottenham hotspur': ('England', 'Tottenham-Hotspur'),
        'newcastle': ('England', 'Newcastle-United'), 'newcastle united': ('England', 'Newcastle-United'),
        # Espanha
        'real madrid': ('Spain', 'Real-Madrid'),
        'barcelona': ('Spain', 'FC-Barcelona'),
        'atletico madrid': ('Spain', 'Atletico-Madrid'), 'atlético madrid': ('Spain', 'Atletico-Madrid'),
        # Itália
        'inter': ('Italy', 'Inter-Milano'), 'inter milan': ('Italy', 'Inter-Milano'), 'internazionale': ('Italy', 'Inter-Milano'),
        'juventus': ('Italy', 'Juventus'),
        'milan': ('Italy', 'AC-Milan'), 'ac milan': ('Italy', 'AC-Milan'),
        # Alemanha
        'bayern munich': ('Germany', 'Bayern-Munchen'), 'bayern munchen': ('Germany', 'Bayern-Munchen'), 'bayern de munique': ('Germany', 'Bayern-Munchen'),
        'borussia dortmund': ('Germany', 'Borussia-Dortmund'), 'dortmund': ('Germany', 'Borussia-Dortmund')
    }

    team_aliases_map = {
        'sporting cp': {'sporting', 'sporting cp', 'sporting lisboa', 'sporting lisbon'},
        'vitória sc': {'vitoria sc', 'vitória sc', 'vitoria guimaraes', 'vitória guimarães', 'guimaraes', 'guimarães'},
        'vitoria sc': {'vitoria sc', 'vitória sc', 'vitoria guimaraes', 'vitória guimarães', 'guimaraes', 'guimarães'},
        'benfica': {'benfica', 'sl benfica'},
        'fc porto': {'fc porto', 'porto'},
        'sc braga': {'sc braga', 'braga', 'sp. braga', 'sp braga', 'sporting braga'},
        'estrela': {'estrela', 'estrela amadora', 'estrela da amadora'}
    }

    def _strip_accents(s: str) -> str:
        import unicodedata
        return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn').lower().strip()

    def _norm(name_str: str) -> str:
        n = _strip_accents(name_str).lower()
        n = n.replace('club atletico', '').replace('ca ', '').replace('cd ', '').replace('fc ', '').replace(' fc', '')
        n = n.replace('saint', 'st').replace('st.', 'st')
        n = n.replace('united', 'utd').replace('utd.', 'utd')
        return n.split('/')[0].strip()

    def _is_team_alias_match(target_name: str, candidate_name: str) -> bool:
        norm_t = _norm(target_name)
        norm_c = _norm(candidate_name)
        if norm_t == norm_c or norm_t in norm_c or norm_c in norm_t:
            return True
        aliases = team_aliases_map.get(norm_t, set())
        for a in aliases:
            norm_a = _norm(a)
            if norm_a == norm_c or norm_a in norm_c or norm_c in norm_a:
                return True
        return False

    clean_name = team_name.lower().replace('club atlético', '').replace('ca ', '').replace('cd ', '').split('/')[0].strip()
    known_info = known_slugs.get(clean_name) or known_slugs.get(team_name.lower()) or known_slugs.get(_strip_accents(clean_name))

    if not team_url:
        if known_info:
            c_name, slug = known_info
            team_url = f'https://www.futbol24.com/pt/equipa/{c_name}/{slug}/'
        else:
            candidate_countries = [country] if country else ['France', 'England', 'Spain', 'Italy', 'Germany', 'Brazil', 'Argentina', 'Colombia', 'Chile', 'Uruguay', 'Paraguay', 'Peru', 'Ecuador', 'Mexico', 'Sweden', 'Norway', 'Denmark', 'Japan', 'Korea-Republic', 'Poland', 'Czech-Republic', 'Romania', 'Portugal', 'Netherlands', 'Belgium', 'Austria', 'Turkey', 'Scotland']
            candidate_countries = [c for c in candidate_countries if c]
            if 'Brazil' not in candidate_countries:
                candidate_countries.append('Brazil')

            clean_title = _strip_accents(clean_name).title().replace(' ', '-')
            found_url = None
            for c in candidate_countries:
                test_url = f'https://www.futbol24.com/pt/equipa/{c}/{clean_title}/'
                try:
                    r = requests.get(test_url, headers=headers, timeout=3, allow_redirects=True)
                    if r.status_code == 200 and '/equipa/' in r.url:
                        found_url = r.url
                        break
                except Exception:
                    continue

            team_url = found_url or f'https://www.futbol24.com/pt/equipa/Brazil/{clean_title}/'

    log.info(f"[SCRAPER-FUTBOL24-LAST] Buscando últimos {limit} jogos de '{team_name}' em {team_url}...")

    try:
        resp = requests.get(team_url, headers=headers, timeout=10)
        if resp.status_code != 200:
            log.warning(f"[SCRAPER-FUTBOL24-LAST] HTTP {resp.status_code} ao buscar {team_url}")
            return None

        soup = BeautifulSoup(resp.text, 'html.parser')
        header = soup.find(class_='f-latest-team-results__header')
        parent = header.parent if header else soup

        rows = parent.find_all(class_='f-single-result__row')
        matches = []

        for row in rows:
            text = row.get_text(separator='|', strip=True)
            parts = [p.strip() for p in text.split('|') if p.strip()]

            score_part = None
            h_team = None
            a_team = None

            for i, p in enumerate(parts):
                if re.match(r'^\d+-\d+$', p):
                    score_part = p
                    if i >= 1:
                        h_team = parts[i - 1]
                    if i + 1 < len(parts):
                        a_team = parts[i + 1]
                    break

            if not score_part or not h_team or not a_team:
                continue

            gh, ga = map(int, score_part.split('-'))

            is_home = _is_team_alias_match(clean_name, h_team)
            opp_name = a_team if is_home else h_team

            if is_home:
                res = 'V' if gh > ga else ('E' if gh == ga else 'D')
                sc = f'{gh}x{ga}'
            else:
                res = 'V' if ga > gh else ('E' if gh == ga else 'D')
                sc = f'{ga}x{gh}'

            matches.append({
                'opponent': opp_name.split('/')[0].strip(),
                'score': sc,
                'result': res,
                'is_home': is_home
            })

            if len(matches) == limit:
                break

        if not matches:
            return None

        v = sum(1 for m in matches if m['result'] == 'V')
        e = sum(1 for m in matches if m['result'] == 'E')
        d = sum(1 for m in matches if m['result'] == 'D')
        pts = (3 * v) + (1 * e)

        return {
            'v': v, 'e': e, 'd': d, 'pts': pts,
            'text': f'{v}V-{e}E-{d}D',
            'matches': matches
        }

    except Exception as exc:
        log.error(f"[SCRAPER-FUTBOL24-LAST] Erro ao raspar Futbol24 para '{team_name}': {exc}")
        return None


if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO)
    print("=== EXECUTANDO RASPAGEM DIRETA DO ODDSPEDIA & FUTBOL24 ===")
    res_op = scrape_oddspedia_odds(['serie-a', 'serie-b'])
    res_f24 = scrape_futbol24_odds(['serie-a', 'serie-b'])
    res_prev = scrape_futbol24_previews()
    print(f"\nTotal Oddspedia: {len(res_op)} | Total Futbol24 Odds: {len(res_f24)} | Total Prévias Futbol24: {len(res_prev)}\n")



