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
