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

def fetch_betano_url(url: str, timeout_ms: int = 12000) -> str:
    """
    Busca conteúdo de URL da Betano priorizando FlareSolverr, com fallback para requests direto.
    """
    # 1. Tenta via FlareSolverr
    sol = fetch_via_flaresolverr(url, timeout_ms=timeout_ms)
    resp_text = sol.get('response', '')
    if resp_text and len(resp_text) > 100:
        return resp_text

    # 2. Fallback direto via requests com headers de navegador
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Accept': 'application/json, text/plain, */*',
        'Accept-Language': 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
        'Referer': 'https://www.betano.com/',
        'Origin': 'https://www.betano.com'
    }
    try:
        r = requests.get(url, headers=headers, timeout=(timeout_ms / 1000))
        if r.status_code == 200:
            return r.text
    except Exception:
        pass
    return ""

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
        resp_text = fetch_betano_url(url)
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
        except Exception:
            continue

    return {}

def parse_betano_event_markets(event_obj: dict, home_team: str, away_team: str) -> dict:
    """
    Decodifica os mercados (1X2, Handicap Asiático e Cartões) do objeto de evento retornado pela Betano.
    """
    result = {
        'event_id': event_obj.get('id'),
        'event_name': event_obj.get('name'),
        'source': 'BETANO_DIRECT',
        'odds_1x2': {},
        'ah_lines': [],
        'card_lines': []
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

        # 3. Mercado de Cartões (Total de Cartões / Under & Over Cards)
        if any(term in m_name for term in ['total de cart', 'cartoes', 'karten', 'total cards']):
            for s in selections:
                s_name = (s.get('name') or '').strip().lower()
                s_price = float(s.get('price', 0.0) or 0.0)
                h_val = s.get('handicap')
                if s_price <= 1.0:
                    continue
                is_under = 'menos' in s_name or 'under' in s_name or 'unter' in s_name
                is_over = 'mais' in s_name or 'over' in s_name or 'uber' in s_name
                if not (is_under or is_over):
                    continue
                try:
                    line_f = float(h_val) if h_val is not None else float(re.findall(r'(\d+\.?\d*)', s_name)[0])
                    result['card_lines'].append({
                        'line': line_f,
                        'type': 'Under' if is_under else 'Over',
                        'odd': s_price,
                        'label': f"{'Menos de' if is_under else 'Mais de'} {line_f:g} Cartões",
                        'bookmaker': 'Betano'
                    })
                except Exception:
                    continue

    return result

def fetch_betano_live_football_events() -> list:
    """
    Obtém todos os eventos de futebol ao vivo transmitidos pela API pública da Betano.
    Retorna lista de dicionários normalizados com placar, minuto real, período e odds in-play.
    Consumo de cota da API-Football: ZERO.
    """
    urls = [
        "https://www.betano.de/api/live/",
        "https://www.betano.com/api/live/",
        "https://www.betano.de/api/sport/football/live/",
        "https://www.betano.com/api/sport/football/live/"
    ]

    live_events_normalized = []

    for url in urls:
        resp_text = fetch_betano_url(url)
        if not resp_text:
            continue

        try:
            data = json.loads(resp_text)
            events = []
            if isinstance(data, dict):
                d_body = data.get('data', {})
                if isinstance(d_body, dict):
                    if 'events' in d_body:
                        events = d_body.get('events', [])
                    elif 'blocks' in d_body:
                        for blk in d_body.get('blocks', []):
                            events.extend(blk.get('events', []))
                elif isinstance(d_body, list):
                    events = d_body

            for ev in events:
                sport_id = str(ev.get('sportId') or ev.get('sport', '')).lower()
                if sport_id and sport_id not in ('football', 'soccer', '1'):
                    continue

                ev_name = ev.get('name', '')
                parts = ev_name.split(' - ') if ' - ' in ev_name else ev_name.split(' vs ')
                if len(parts) != 2:
                    continue

                home_t, away_t = parts[0].strip(), parts[1].strip()

                # Extrai dados de tempo real (placar, período e minuto)
                live_data = ev.get('liveData') or ev.get('state') or {}
                raw_score = live_data.get('score') or {}
                gh = None
                ga = None

                if isinstance(raw_score, dict):
                    gh = raw_score.get('home') or raw_score.get('homeScore')
                    ga = raw_score.get('away') or raw_score.get('awayScore')
                elif isinstance(raw_score, str) and '-' in raw_score:
                    s_pts = raw_score.split('-')
                    gh, ga = s_pts[0].strip(), s_pts[1].strip()

                try:
                    gh = int(gh) if gh is not None else 0
                    ga = int(ga) if ga is not None else 0
                except (ValueError, TypeError):
                    gh, ga = 0, 0

                raw_phase = str(live_data.get('phase') or live_data.get('period') or '').upper()
                raw_time = str(live_data.get('time') or '').strip()

                status_norm = '1H'
                if any(ht_t in raw_phase for ht_t in ['HT', 'HALF', 'HALFTIME', 'INTERVALO']):
                    status_norm = 'HT'
                elif any(h2_t in raw_phase for h2_t in ['2H', 'SECOND', '2ND']):
                    status_norm = '2H'
                elif '1H' in raw_phase or 'FIRST' in raw_phase:
                    status_norm = '1H'

                # Extrai minuto decorrido
                elapsed_min = None
                nums = re.findall(r'(\d+)', raw_time)
                if nums:
                    try:
                        elapsed_min = int(nums[0])
                    except ValueError:
                        pass

                if elapsed_min is None:
                    if status_norm == 'HT':
                        elapsed_min = 45
                    elif status_norm == '2H':
                        elapsed_min = 60
                    else:
                        elapsed_min = 25

                # Decodifica mercados ao vivo
                parsed_markets = parse_betano_event_markets(ev, home_t, away_t)

                live_events_normalized.append({
                    'event_id': ev.get('id'),
                    'home_team': home_t,
                    'away_team': away_t,
                    'status': status_norm,
                    'elapsed': elapsed_min,
                    'goals_home': gh,
                    'goals_away': ga,
                    'odds_1x2': parsed_markets.get('odds_1x2', {}),
                    'ah_lines': parsed_markets.get('ah_lines', []),
                    'card_lines': parsed_markets.get('card_lines', [])
                })

            if live_events_normalized:
                break
        except Exception:
            continue

    return live_events_normalized

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
        print(f"   • Linhas Cartões encontradas: {len(data.get('card_lines', []))}")
    else:
        print("ℹ️ Evento não listado no feed rápido de futebol (utilizará fallback automático da API-Sports).")

    print("\n⚡ Testando captura de eventos ao vivo na Betano...")
    live_evs = fetch_betano_live_football_events()
    print(f"📡 Total de jogos ao vivo capturados: {len(live_evs)}")
    if live_evs:
        sample = live_evs[0]
        print(f"   • Exemplo: {sample['home_team']} {sample['goals_home']}x{sample['goals_away']} {sample['away_team']} ({sample['status']} - {sample['elapsed']}')")

