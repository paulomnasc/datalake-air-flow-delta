#!/usr/bin/env python3
"""
scripts/betano_direct_api.py

Módulo Canônico de Integração Direta com a API Pública da Betano.
Obtém cotações 1X2 e linhas de Handicap Asiático em tempo real, sem intermediários.
Utiliza FlareSolverr como proxy de resolução de desafios Cloudflare quando necessário,
com fallback gracioso para a API-Sports.
"""

import os
import sys
import json
import re
import unicodedata
import requests

# Cache em memória por ciclo de execução
_BETANO_DIRECT_CACHE = {}

def normalize_team_name(name: str) -> str:
    """
    Normaliza o nome do time para comparação fonética/textual flexível.
    """
    if not name:
        return ""
    # Remove acentos
    nfkd = unicodedata.normalize('NFKD', str(name).lower())
    clean = re.sub(r'[\u0300-\u036f]', '', nfkd)
    # Remove pontuações e prefixos/sufixos comuns
    clean = re.sub(r'[^a-z0-9\s]', ' ', clean)
    clean = ' '.join(clean.split())
    # Remove termos comuns que geram falsos desencontros
    stop_words = {'fc', 'ec', 'sc', 'cf', 'ac', 'de', 'do', 'da', 'clube', 'club', 'cd'}
    words = [w for w in clean.split() if w not in stop_words]
    return ' '.join(words) if words else clean

def is_team_match(name1: str, name2: str) -> bool:
    """
    Verifica se dois nomes de equipes se correspondem através de sobreposição de termos.
    """
    n1 = normalize_team_name(name1)
    n2 = normalize_team_name(name2)
    if not n1 or not n2:
        return False
    if n1 == n2 or n1 in n2 or n2 in n1:
        return True
    # Checa palavras-chave (ex: "operario" em "operario pr")
    words1 = set(n1.split())
    words2 = set(n2.split())
    intersection = words1.intersection(words2)
    return len(intersection) >= 1 and (len(intersection) >= len(words1) * 0.5 or len(intersection) >= len(words2) * 0.5)

def fetch_via_flaresolverr(url: str, timeout_ms: int = 15000) -> dict:
    """
    Encaminha a requisição HTTP GET através do container FlareSolverr (porta 8191).
    """
    endpoints = [
        "http://127.0.0.1:8191/v1",
        "http://localhost:8191/v1",
        "http://flaresolverr:8191/v1"
    ]
    payload = {
        "cmd": "request.get",
        "url": url,
        "maxTimeout": timeout_ms
    }
    for ep in endpoints:
        try:
            resp = requests.post(ep, json=payload, timeout=(timeout_ms / 1000) + 3)
            if resp.status_code == 200:
                data = resp.json()
                if data.get('status') == 'ok':
                    return data.get('solution', {})
        except Exception:
            continue
    return {}

def fetch_betano_event_by_teams(home_team: str, away_team: str) -> dict:
    """
    Busca o evento correspondente na API pública da Betano navegando no feed de futebol.
    """
    cache_key = f"{normalize_team_name(home_team)}__vs__{normalize_team_name(away_team)}"
    if cache_key in _BETANO_DIRECT_CACHE:
        return _BETANO_DIRECT_CACHE[cache_key]

    # Endpoints do catálogo Betano
    urls = [
        "https://www.betano.de/api/sport/football/upcoming/",
        "https://www.betano.com/api/sport/football/upcoming/"
    ]

    for url in urls:
        solution = fetch_via_flaresolverr(url)
        resp_text = solution.get('response', '')
        if not resp_text:
            continue

        try:
            data = json.loads(resp_text)
            events = []
            if 'data' in data:
                if 'events' in data['data']:
                    events = data['data']['events']
                elif 'blocks' in data['data']:
                    for blk in data['data']['blocks']:
                        events.extend(blk.get('events', []))

            for ev in events:
                ev_name = ev.get('name', '')
                parts = ev_name.split(' - ')
                if len(parts) == 2:
                    ev_home, ev_away = parts[0], parts[1]
                else:
                    parts = ev_name.split(' vs ')
                    if len(parts) == 2:
                        ev_home, ev_away = parts[0], parts[1]
                    else:
                        continue

                if is_team_match(home_team, ev_home) and is_team_match(away_team, ev_away):
                    # Evento localizado com sucesso!
                    parsed = parse_betano_event_markets(ev, home_team, away_team)
                    _BETANO_DIRECT_CACHE[cache_key] = parsed
                    return parsed
        except Exception as e:
            continue

    return {}

def parse_betano_event_markets(event_obj: dict, home_team: str, away_team: str) -> dict:
    """
    Decodifica os mercados (1X2 e Handicap Asiático) do objeto de evento retornado pela Betano.
    """
    result = {
        'event_id': event_obj.get('id'),
        'event_name': event_obj.get('name'),
        'source': 'BETANO_DIRECT',
        'odds_1x2': {},
        'ah_lines': []
    }

    markets = event_obj.get('markets', [])
    for m in markets:
        m_name = (m.get('name') or '').lower()
        selections = m.get('selections', [])

        # 1. Mercado 1X2 (Resultado Final / Match Result)
        if any(term in m_name for term in ['resultado final', 'match result', '1x2', 'endergebnis']):
            for s in selections:
                s_name = (s.get('name') or '').strip()
                s_price = float(s.get('price', 0.0) or 0.0)
                if s_price <= 1.0:
                    continue
                if is_team_match(home_team, s_name) or s_name in ['1', 'Home']:
                    result['odds_1x2']['home'] = s_price
                elif is_team_match(away_team, s_name) or s_name in ['2', 'Away']:
                    result['odds_1x2']['away'] = s_price
                elif any(draw_term in s_name.lower() for draw_term in ['empate', 'draw', 'x', 'unentschieden']):
                    result['odds_1x2']['draw'] = s_price

        # 2. Mercado de Handicap Asiático (Asian Handicap)
        if any(term in m_name for term in ['handicap asiatico', 'asian handicap', 'asiatisches handicap']):
            for s in selections:
                s_name = s.get('name', '')
                s_price = float(s.get('price', 0.0) or 0.0)
                handicap_val = s.get('handicap')
                if s_price <= 1.0 or handicap_val is None:
                    continue

                is_home = is_team_match(home_team, s_name) or s_name.startswith('1')
                team_target = home_team if is_home else away_team

                try:
                    h_float = float(handicap_val)
                    sign = "+" if h_float > 0 else ""
                    line_label = f"{team_target} {sign}{h_float:g} AH"
                    result['ah_lines'].append({
                        'line': f"{sign}{h_float:g}",
                        'odd': s_price,
                        'team': team_target,
                        'label': line_label,
                        'is_home': is_home,
                        'bookmaker': 'Betano'
                    })
                except (ValueError, TypeError):
                    continue

    return result

if __name__ == "__main__":
    print("🧪 Testando módulo betano_direct_api...")
    test_h = "Guadalajara Chivas"
    test_a = "Club Queretaro"
    print(f"🔍 Buscando cotações ao vivo na Betano para: {test_h} x {test_a}")
    data = fetch_betano_event_by_teams(test_h, test_a)
    if data:
        print("✅ Evento encontrado na Betano!")
        print(f"   • ID: {data.get('event_id')} | Nome: {data.get('event_name')}")
        print(f"   • Odds 1X2: {data.get('odds_1x2')}")
        print(f"   • Linhas AH encontradas: {len(data.get('ah_lines', []))}")
    else:
        print("ℹ️ Evento não listado no feed rápido de futebol (utilizará fallback automático da API-Sports).")
