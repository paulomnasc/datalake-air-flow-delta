"""
Módulo de Arbitragem de Apostas Esportivas (Brasileirão Série A e Série B)
Integração LIVE via The Odds API com formatação estrita de 2 casas decimais para Odds e Stakes.
"""

import os
import re
import json
import logging
import difflib
import urllib.request
import pandas as pd
from datetime import datetime, timezone, timedelta

log = logging.getLogger(__name__)

# Chave Padrão The Odds API fornecida pelo usuário
DEFAULT_ODDS_API_KEY = "19034934454fd9bd0a06735a67cd8f1b"


# Mapeamento e Normalização de Nomes de Times do Brasileirão
TEAM_ALIASES = {
    # Série A
    "INTERNACIONAL": ["INTERNACIONAL", "SC INTERNACIONAL", "INTER", "INT", "INTERNACIONAL RS"],
    "FLAMENGO": ["FLAMENGO", "CR FLAMENGO", "FLAMENGO RJ", "FLA"],
    "PALMEIRAS": ["PALMEIRAS", "SE PALMEIRAS", "PALMEIRAS SP", "PAL"],
    "BOTAFOGO": ["BOTAFOGO", "BOTAFOGO RJ", "BOTAFOGO FR", "BOT"],
    "SAO PAULO": ["SAO PAULO", "SÃO PAULO", "SAO PAULO FC", "SÃO PAULO FC", "SPA"],
    "FLUMINENSE": ["FLUMINENSE", "FLUMINENSE FC", "FLU"],
    "VASCO": ["VASCO", "VASCO DA GAMA", "CR VASCO DA GAMA", "VAS"],
    "CORINTHIANS": ["CORINTHIANS", "SC CORINTHIANS", "CORINTHIANS SP", "COR"],
    "BAHIA": ["BAHIA", "EC BAHIA", "BAH"],
    "CRUZEIRO": ["CRUZEIRO", "CRUZEIRO EC", "CRU"],
    "ATLETICO-MG": ["ATLETICO-MG", "ATLÉTICO-MG", "ATLETICO MG", "ATLÉTICO MINEIRO", "CAM"],
    "GREMIO": ["GREMIO", "GRÊMIO", "GREMIO FBPA", "GRE"],
    "FORTALEZA": ["FORTALEZA", "FORTALEZA EC", "FORTALEZA CE", "FOR"],
    "RED BULL BRAGANTINO": ["RED BULL BRAGANTINO", "BRAGANTINO", "RBB"],
    "JUVENTUDE": ["JUVENTUDE", "EC JUVENTUDE", "JUV"],
    "VITORIA": ["VITORIA", "VITÓRIA", "EC VITÓRIA", "VIT"],
    "CRICIUMA": ["CRICIUMA", "CRICIÚMA", "CRICIÚMA EC", "CRI"],
    "CUIABA": ["CUIABA", "CUIABÁ", "CUIABÁ EC", "CUI"],
    "ATHLETICO-PR": ["ATHLETICO-PR", "ATLÉTICO-PR", "ATHLETICO PARANAENSE", "CAP"],
    "ATLETICO-GO": ["ATLETICO-GO", "ATLÉTICO-GO", "ATLÉTICO GOIANIENSE", "ACG"],
    # Série B & Adicionais
    "SANTOS": ["SANTOS", "SANTOS FC", "SAN"],
    "CEARA": ["CEARA", "CEARÁ", "CEARÁ SC", "CEA"],
    "MIRASSOL": ["MIRASSOL", "MIRASSOL FC", "MIR"],
    "NOVORIZONTINO": ["NOVORIZONTINO", "GE NOVORIZONTINO", "NOV"],
    "SPORT": ["SPORT", "SPORT RECIFE", "SPORT CLUB DO RECIFE", "SPT"],
    "AMERICA-MG": ["AMERICA-MG", "AMÉRICA-MG", "AMÉRICA MINEIRO", "AMERICA MINEIRO", "AMERICA MINEIRO MG", "AME"],
    "GOIAS": ["GOIAS", "GOIÁS", "GOIÁS EC", "GOI"],
    "VILA NOVA": ["VILA NOVA", "VILA NOVA FC", "VIL"],
    "CORITIBA": ["CORITIBA", "CORITIBA FBC", "CFC"],
    "AVAI": ["AVAI", "AVAÍ", "AVAÍ FC", "AVA"],
    "PAYSANDU": ["PAYSANDU", "PAYANDU", "PAYSANDU SC", "PAY"],
    "PONTE PRETA": ["PONTE PRETA", "AA PONTE PRETA", "PON"],
    "CRB": ["CRB", "CLUBE DE REGATAS BRASIL", "CRB AL"],
    "GUARANI": ["GUARANI", "GUARANI FC", "GUA"],
    "OPERARIO-PR": ["OPERARIO-PR", "OPERÁRIO-PR", "OPERÁRIO FEC", "OPE"],
    "CHAPECOENSE": ["CHAPECOENSE", "ASSOCIACAO CHAPECOENSE", "CHA"],
    "BOTAFOGO-SP": ["BOTAFOGO-SP", "BOTAFOGO SP", "BOTAFOGO FC SP", "BOTAFOGO/SP", "BSO"],
    "BRUSQUE": ["BRUSQUE", "BRUSQUE FC", "BRU"],
    "ITUANO": ["ITUANO", "ITUANO FC", "ITU"],
    "AMAZONAS": ["AMAZONAS", "AMAZONAS FC", "AMA"],
    "REMO": ["REMO", "CLUBE DO REMO", "REM"],
}

# Mapeamento e Normalização de Casas de Apostas (Bookmakers)
BOOKMAKER_ALIASES = {
    "BETNACIONAL": ["BETNACIONAL", "BETNACIONAL_BR", "BET NACIONAL", "BETNACIONAL BR"],
    "BET365": ["BET365", "BET365_EU", "BET 365", "BET365 UK", "BET365 BR"],
    "BETANO": ["BETANO", "BETANO_BR", "STOIXIMAN", "BETANO BR", "BETANO EU", "BETANO (UK)", "BETANO UK"],
    "SPORTINGBET": ["SPORTINGBET", "SPORTINGBET_BR", "SPORTING BET", "SPORTINGBET BR"],
    "SUPERBET": ["SUPERBET", "SUPERBET_BR", "SUPER BET", "SUPERBET BR"],
    "KTO": ["KTO", "KTO_BR", "KTO BR"],
    "NOVIBET": ["NOVIBET", "NOVIBET_BR", "NOVIBET BR"],
    "BETFAIR SPORTSBOOK": ["BETFAIR_SPORTSBOOK", "BETFAIR SPORTSBOOK", "BETFAIR BR", "BETFAIR"],
    "BETFAIR EXCHANGE": ["BETFAIR_EXCHANGE", "BETFAIR EXCHANGE", "BETFAIR EXCH"],
    "ESTRELABET": ["ESTRELABET", "ESTRELA BET", "ESTRELABET BR"],
    "PINNACLE": ["PINNACLE", "PINNACLE_SPORTS"],
    "1XBET": ["1XBET", "ONE X BET"],
    "BWIN": ["BWIN"],
    "UNIBET": ["UNIBET", "UNIBET_EU", "UNIBET UK", "UNIBET SE", "UNIBET NL", "UNIBET (UK)", "UNIBET (SE)", "UNIBET (NL)", "UNIBET (EU)", "UNIBET (FR)"],
    "BETSSON": ["BETSSON"],
    "BETWAY": ["BETWAY"],
    "888SPORT": ["888SPORT", "888 SPORT"],
    "COOLBET": ["COOLBET"],
}

def normalize_bookmaker_name(name: str) -> str:
    """Normaliza o nome da casa de apostas para uma chave padronizada."""
    if not name:
        return "OUTROS"
    clean_name = re.sub(r'[^A-Z0-9\s_-]', '', name.upper()).strip()
    
    for standard_name, aliases in BOOKMAKER_ALIASES.items():
        for alias in aliases:
            if alias == clean_name or difflib.SequenceMatcher(None, alias, clean_name).ratio() > 0.85:
                return standard_name
    return clean_name.title()

import unicodedata

def _remove_accents(text: str) -> str:
    if not text:
        return ""
    nfkd = unicodedata.normalize('NFKD', text)
    return ''.join([c for c in nfkd if not unicodedata.combining(c)])

FAST_TEAM_ALIASES = {}
for _std, _aliases in TEAM_ALIASES.items():
    for _alias in _aliases:
        _clean_a = re.sub(r'[^A-Z0-9\s-]', '', _remove_accents(_alias.upper())).strip()
        FAST_TEAM_ALIASES[_clean_a] = _std

def normalize_team_name(name: str) -> str:
    """Normaliza o nome do time para uma chave padronizada com busca O(1) sem acentos."""
    if not name:
        return "DESCONHECIDO"
    clean_name = re.sub(r'[^A-Z0-9\s-]', '', _remove_accents(name.upper())).strip()
    if clean_name in FAST_TEAM_ALIASES:
        return FAST_TEAM_ALIASES[clean_name]
        
    for standard_name, aliases in TEAM_ALIASES.items():
        for alias in aliases:
            _clean_alias = re.sub(r'[^A-Z0-9\s-]', '', _remove_accents(alias.upper())).strip()
            if len(_clean_alias) >= 4 and (_clean_alias in clean_name or clean_name in _clean_alias):
                return standard_name
    return clean_name

def calculate_surebet(odd_casa: float, odd_empate: float, odd_visitante: float, banca_total: float = 1000.0):
    """
    Calcula se existe arbitragem (Surebet) e determina a distribuição da banca com precisão de 2 casas decimais.
    Fórmula de Arbitragem: S = (1/Odd1) + (1/OddX) + (1/Odd2)
    """
    if any(o <= 1.0 for o in [odd_casa, odd_empate, odd_visitante]):
        return None
    
    prob_casa = 1.0 / odd_casa
    prob_empate = 1.0 / odd_empate
    prob_visitante = 1.0 / odd_visitante
    
    soma_probabilidades = prob_casa + prob_empate + prob_visitante
    is_surebet = soma_probabilidades < 1.0
    
    lucro_percentual = ((1.0 / soma_probabilidades) - 1.0) * 100.0
    retorno_esperado = banca_total / soma_probabilidades
    lucro_estimado = retorno_esperado - banca_total
    
    stake_casa = banca_total / (soma_probabilidades * odd_casa)
    stake_empate = banca_total / (soma_probabilidades * odd_empate)
    stake_visitante = banca_total / (soma_probabilidades * odd_visitante)
    
    return {
        "is_surebet": is_surebet,
        "soma_probabilidades": round(soma_probabilidades, 4),
        "lucro_percentual": round(lucro_percentual, 2),
        "lucro_estimado": round(lucro_estimado, 2),
        "retorno_esperado": round(retorno_esperado, 2),
        "stake_casa": round(stake_casa, 2),
        "stake_empate": round(stake_empate, 2),
        "stake_visitante": round(stake_visitante, 2),
    }

CACHE_FILE_PATH = "/tmp/sports_arbitrage_live_cache.json"

def fetch_live_odds_from_api(api_key: str, casas_permitidas: list = None, min_pre_match_minutes: int = 30):
    """
    Busca odds das casas de apostas para ligas de futebol ativas via The Odds API,
    filtrando apenas partidas em PRÉ-JOGO que faltem no mínimo `min_pre_match_minutes` para iniciar.
    Utiliza cache local com TTL e regiões otimizadas (eu,uk) para economizar a cota da API.
    """
    # 0. Verifica cache local se estiver dentro do TTL
    cache_ttl_minutes = int(os.environ.get('ARBITRAGE_CACHE_TTL_MINUTES', '30'))
    bypass_cache = str(os.environ.get('ARBITRAGE_BYPASS_CACHE', 'false')).lower() in ('true', '1', 'yes')

    if not bypass_cache and os.path.exists(CACHE_FILE_PATH):
        try:
            mtime = os.path.getmtime(CACHE_FILE_PATH)
            age_minutes = (datetime.now().timestamp() - mtime) / 60.0
            if age_minutes < cache_ttl_minutes:
                with open(CACHE_FILE_PATH, 'r', encoding='utf-8') as f_cache:
                    cached_data = json.load(f_cache)
                    if cached_data:
                        log.info(f"[LIVE-ODDS-CACHE] Utilizando {len(cached_data)} partidas salvas em cache local ({age_minutes:.1f} min atrás / TTL: {cache_ttl_minutes} min). Nenhum token consumido!")
                        return cached_data
        except Exception as e_cache:
            log.warning(f"[LIVE-ODDS-CACHE] Falha ao ler cache local: {e_cache}. Prosseguindo com consulta à API.")

    # Suporte a múltiplas chaves separadas por vírgula em ODDS_API_KEY
    api_keys = [k.strip() for k in (api_key or '').split(',') if k.strip()]
    if not api_keys:
        log.error("[LIVE-ODDS] Nenhuma chave da The Odds API foi configurada.")
        return []

    current_key_idx = 0
    active_api_key = api_keys[current_key_idx]

    # Regiões otimizadas para casas brasileiras/européias (eu, uk). Evita us, us2 e au que multiplicam a cota sem trazer casas relevantes.
    regions = os.environ.get('ARBITRAGE_REGIONS', 'eu,uk')

    # Lista das principais ligas globais priorizadas para não estourar a cota da API
    priority_keys = [
        "soccer_brazil_campeonato",
        "soccer_brazil_serie_b",
        "soccer_brazil_copa_do_brasil",
        "soccer_conmebol_copa_libertadores",
        "soccer_conmebol_copa_sudamericana",
        "soccer_epl",
        "soccer_spain_la_liga",
        "soccer_italy_serie_a",
        "soccer_germany_bundesliga",
        "soccer_france_ligue_one",
        "soccer_portugal_primeira_liga",
        "soccer_netherlands_eredivisie",
        "soccer_uefa_champs_league_qualification",
        "soccer_argentina_primera_division",
        "soccer_usa_mls",
        "soccer_mexico_ligamx"
    ]
    
    sports_to_fetch = []
    
    # 1. Tenta buscar dinamicamente ligas ativas priorizando as principais competições
    try:
        sports_url = f"https://api.the-odds-api.com/v4/sports/?apiKey={active_api_key}"
        req_sports = urllib.request.Request(sports_url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req_sports, timeout=10) as resp_s:
            all_sports = json.loads(resp_s.read().decode('utf-8'))
            active_map = {s['key']: s.get('title', s['key']) for s in all_sports if s.get('group') == 'Soccer' and s.get('active', True)}
            
            # Adiciona primeiro as ligas prioritárias que estão ativas
            for pkey in priority_keys:
                if pkey in active_map:
                    sports_to_fetch.append((pkey, active_map[pkey]))
                    
            # Adiciona demais ligas ativas se houver espaço
            for skey, stitle in active_map.items():
                if skey not in priority_keys:
                    sports_to_fetch.append((skey, stitle))
    except Exception as e_sports:
        log.warning(f"[LIVE-ODDS] Não foi possível obter lista dinâmica de ligas: {e_sports}. Usando lista expandida padrão.")
        
    if not sports_to_fetch:
        sports_to_fetch = [
            ("soccer_brazil_campeonato", "Brasileirão Série A"),
            ("soccer_brazil_serie_b", "Brasileirão Série B"),
            ("soccer_brazil_copa_do_brasil", "Copa do Brasil"),
            ("soccer_conmebol_copa_libertadores", "Copa Libertadores"),
            ("soccer_conmebol_copa_sudamericana", "Copa Sudamericana")
        ]
        
    # Limita o número de ligas consultadas por execução para economizar cota da API (padrão: 3 ligas por rodada)
    max_leagues = int(os.environ.get('ARBITRAGE_MAX_LEAGUES', '3'))
    sports_to_fetch = sports_to_fetch[:max_leagues]
    
    parsed_matches = []

    for sport_key, league_name in sports_to_fetch:
        log.info(f"[LIVE-ODDS] Buscando partidas ao vivo de {league_name} ({sport_key}) [Regiões: {regions}]...")
        
        success = False
        while current_key_idx < len(api_keys) and not success:
            active_api_key = api_keys[current_key_idx]
            url = f"https://api.the-odds-api.com/v4/sports/{sport_key}/odds/?apiKey={active_api_key}&regions={regions}&markets=h2h"

            try:
                req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
                with urllib.request.urlopen(req, timeout=10) as resp:
                    events = json.loads(resp.read().decode('utf-8'))
                    log.info(f"[LIVE-ODDS] Sucesso! Encontradas {len(events)} partidas para {league_name}.")
                    success = True
                    
                    for ev in events:
                        home_team = ev.get('home_team')
                        away_team = ev.get('away_team')
                        commence_time = ev.get('commence_time', '')
                        
                        try:
                            dt = datetime.fromisoformat(commence_time.replace('Z', '+00:00'))
                            dt_brt = dt.astimezone(timezone(timedelta(hours=-3)))
                            date_str = dt_brt.strftime("%d/%m %H:%M")
                            
                            # Filtro PRÉ-JOGO: Valida se faltam pelo menos `min_pre_match_minutes` para o jogo iniciar
                            if min_pre_match_minutes > 0:
                                now_utc = datetime.now(timezone.utc)
                                minutes_until_start = (dt - now_utc).total_seconds() / 60.0
                                if minutes_until_start < min_pre_match_minutes:
                                    log.info(f"[LIVE-ODDS] Ignorando partida '{home_team} vs {away_team}': começa em {minutes_until_start:.1f} min (mínimo exigido para PRÉ-JOGO: {min_pre_match_minutes} min).")
                                    continue
                        except Exception as e_dt:
                            log.warning(f"[LIVE-ODDS] Falha ao processar commence_time ({commence_time}): {e_dt}")
                            date_str = commence_time
                            
                        bookmakers = ev.get('bookmakers', [])
                        odds_dict = {}
                        
                        for bm in bookmakers:
                            bm_raw_name = bm.get('title', bm.get('key'))
                            bm_norm_name = normalize_bookmaker_name(bm_raw_name)
                            
                            markets = bm.get('markets', [])
                            
                            for m in markets:
                                if m.get('key') == 'h2h':
                                    outcomes = m.get('outcomes', [])
                                    odd_h, odd_d, odd_a = 0.0, 0.0, 0.0
                                    for out in outcomes:
                                        name = out.get('name')
                                        price = float(out.get('price', 0.0))
                                        if name == home_team:
                                            odd_h = price
                                        elif name == away_team:
                                            odd_a = price
                                        elif name.lower() in ['draw', 'empate']:
                                            odd_d = price
                                            
                                    if odd_h > 0 and odd_d > 0 and odd_a > 0:
                                        odds_dict[bm_norm_name] = {
                                            "casa": round(odd_h, 2),
                                            "empate": round(odd_d, 2),
                                            "visitante": round(odd_a, 2),
                                            "nome_original": bm_raw_name
                                        }
                                        
                        if odds_dict:
                            parsed_matches.append({
                                "campeonato": league_name,
                                "time_casa": home_team,
                                "time_visitante": away_team,
                                "data_jogo": date_str,
                                "odds": odds_dict
                            })
            except urllib.error.HTTPError as e_http:
                log.error(f"[LIVE-ODDS] Erro HTTP {e_http.code} ao consultar {sport_key} com chave {current_key_idx+1}/{len(api_keys)}: {e_http}")
                if e_http.code in (401, 429):
                    current_key_idx += 1
                    if current_key_idx < len(api_keys):
                        log.warning(f"[LIVE-ODDS] Alternando automaticamente para a próxima chave de API ({current_key_idx+1}/{len(api_keys)})...")
                    else:
                        log.warning("[LIVE-ODDS] Todas as chaves da API atingiram limite de cota ou falharam. Interrompendo chamadas nesta rodada.")
                        break
                else:
                    break
            except Exception as e:
                log.error(f"[LIVE-ODDS] Erro ao consultar {sport_key}: {e}")
                break

    # Salva resultado no cache se houver dados
    if parsed_matches:
        try:
            with open(CACHE_FILE_PATH, 'w', encoding='utf-8') as f_cache:
                json.dump(parsed_matches, f_cache, ensure_ascii=False, indent=2)
            log.info(f"[LIVE-ODDS-CACHE] Salvos {len(parsed_matches)} partidas em cache local: {CACHE_FILE_PATH}")
        except Exception as e_save_cache:
            log.warning(f"[LIVE-ODDS-CACHE] Não foi possível salvar em cache: {e_save_cache}")

    return parsed_matches


def get_live_env_vars():
    """Lê diretamente o arquivo .env para refletir alterações instantaneamente sem reiniciar os containers."""
    env_vars = {}
    search_paths = [
        '/opt/airflow/.env',
        '/opt/airflow/dags/../../.env',
        '/opt/airflow/dags/../.env',
        '/root/datalake-air-flow-delta/.env',
        './.env',
        '../.env'
    ]
    for p in search_paths:
        if os.path.exists(p):
            try:
                with open(p, 'r', encoding='utf-8') as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith('#') and '=' in line:
                            k, v = line.split('=', 1)
                            env_vars[k.strip()] = v.strip().strip('"').strip("'")
            except Exception:
                pass
    return env_vars


def fetch_bookmaker_odds(casas_permitidas: list = None, min_pre_match_minutes: int = 30):
    """
    Recupera odds ao vivo da The Odds API e executa scrapers complementares (Betnacional, Bet365).
    Funde todas as odds por partida em um dicionário único por confronto.
    """
    file_env = get_live_env_vars()
    odds_api_key = file_env.get('ODDS_API_KEY') or os.environ.get('ODDS_API_KEY')
    
    if not odds_api_key:
        try:
            from airflow.models import Variable
            odds_api_key = Variable.get("ODDS_API_KEY", default_var=DEFAULT_ODDS_API_KEY)
        except Exception:
            odds_api_key = DEFAULT_ODDS_API_KEY
            
    live_data = fetch_live_odds_from_api(odds_api_key, casas_permitidas=casas_permitidas, min_pre_match_minutes=min_pre_match_minutes)

    
    # Importa os scrapers customizados
    try:
        from lib.scrapers import scrape_betnacional_odds, scrape_bet365_odds
        scraped_betnacional = scrape_betnacional_odds()
        scraped_bet365 = scrape_bet365_odds()
        scraped_data = scraped_betnacional + scraped_bet365
    except Exception as e_scrapers:
        log.warning(f"[ODDS-MERGE] Scrapers complementares não executados: {e_scrapers}")
        scraped_data = []
        
    # Unificação/Merge dos dados da API e dos Scrapers
    merged_matches = {}
    
    for game in (live_data + scraped_data):
        home_norm = normalize_team_name(game["time_casa"])
        away_norm = normalize_team_name(game["time_visitante"])
        match_key = f"{home_norm}_VS_{away_norm}"
        
        if match_key not in merged_matches:
            merged_matches[match_key] = {
                "campeonato": game.get("campeonato", "Brasileirão"),
                "time_casa": game["time_casa"],
                "time_visitante": game["time_visitante"],
                "data_jogo": game.get("data_jogo", ""),
                "odds": {}
            }
            
        merged_matches[match_key]["odds"].update(game.get("odds", {}))
        
    final_list = list(merged_matches.values())
    if final_list:
        return final_list
        
    log.warning("[ODDS] Nenhuma partida ou odds puderam ser recuperadas das fontes.")
    return []

def process_arbitrage_report(banca_total: float = 1000.0, casas_usuario: list = None, apenas_casas_usuario: bool = False, min_pre_match_minutes: int = 30) -> pd.DataFrame:
    """Processa todas as partidas em pré-jogo (com antecedência mínima) e calcula o relatório de arbitragem."""
    
    casas_normalizadas_usuario = set()
    if casas_usuario:
        if isinstance(casas_usuario, str):
            casas_usuario = [c.strip() for c in casas_usuario.split(',') if c.strip()]
        for c in casas_usuario:
            casas_normalizadas_usuario.add(normalize_bookmaker_name(c))
            
    games = fetch_bookmaker_odds(
        casas_permitidas=list(casas_normalizadas_usuario) if casas_normalizadas_usuario else None,
        min_pre_match_minutes=min_pre_match_minutes
    )
    report_rows = []
    
    for game in games:
        campeonato = game["campeonato"]
        time_casa = normalize_team_name(game["time_casa"])
        time_visitante = normalize_team_name(game["time_visitante"])
        data_jogo = game["data_jogo"]
        odds = game["odds"]
        
        # Busca a melhor combinação de 3 casas 100% DISTINTAS por jogo (uma casa diferente para cada resultado)
        best_distinct_combo = None
        best_prob_sum = 999.0
        
        bookmakers_available = [
            bm for bm in odds.keys()
            if not (apenas_casas_usuario and casas_normalizadas_usuario and (bm not in casas_normalizadas_usuario))
        ]
        
        for bm1 in bookmakers_available:
            c1 = odds[bm1]["casa"]
            if c1 <= 1.0: continue
            for bm2 in bookmakers_available:
                cX = odds[bm2]["empate"]
                if cX <= 1.0: continue
                for bm3 in bookmakers_available:
                    c2 = odds[bm3]["visitante"]
                    if c2 <= 1.0: continue
                    if len({bm1, bm2, bm3}) < 2: continue
                    
                    prob_sum = (1.0 / c1) + (1.0 / cX) + (1.0 / c2)
                    if prob_sum < best_prob_sum:
                        best_prob_sum = prob_sum
                        best_distinct_combo = {
                            "bm1": bm1, "odd1": c1,
                            "bmX": bm2, "oddX": cX,
                            "bm2": bm3, "odd2": c2
                        }

        if best_distinct_combo:
            melhor_casa_odd1 = best_distinct_combo["bm1"]
            melhor_odd_casa = round(best_distinct_combo["odd1"], 2)
            melhor_casa_oddX = best_distinct_combo["bmX"]
            melhor_odd_empate = round(best_distinct_combo["oddX"], 2)
            melhor_casa_odd2 = best_distinct_combo["bm2"]
            melhor_odd_visitante = round(best_distinct_combo["odd2"], 2)
        else:
            # Fallback caso não existam 2+ casas distintas registradas na partida
            melhor_odd_casa = 0.0
            melhor_casa_odd1 = ""
            melhor_odd_empate = 0.0
            melhor_casa_oddX = ""
            melhor_odd_visitante = 0.0
            melhor_casa_odd2 = ""
            for casa_nome in bookmakers_available:
                cota = odds[casa_nome]
                if cota["casa"] > melhor_odd_casa:
                    melhor_odd_casa = round(cota["casa"], 2); melhor_casa_odd1 = casa_nome
                if cota["empate"] > melhor_odd_empate:
                    melhor_odd_empate = round(cota["empate"], 2); melhor_casa_oddX = casa_nome
                if cota["visitante"] > melhor_odd_visitante:
                    melhor_odd_visitante = round(cota["visitante"], 2); melhor_casa_odd2 = casa_nome
                
        calc = calculate_surebet(melhor_odd_casa, melhor_odd_empate, melhor_odd_visitante, banca_total)
        
        if calc:
            casas_usadas = {melhor_casa_odd1, melhor_casa_oddX, melhor_casa_odd2} - {""}
            eh_surebet_valida = calc["is_surebet"] and (len(casas_usadas) > 1) and (calc["lucro_percentual"] <= 15.0)

            row = {
                "Campeonato": campeonato,
                "Data_Jogo": data_jogo,
                "Time_Casa": time_casa,
                "Time_Visitante": time_visitante,
                "Casa_Odd_1": melhor_casa_odd1,
                "Odd_1": round(melhor_odd_casa, 2),
                "Stake_Odd_1_R$": round(calc["stake_casa"], 2),
                "Casa_Odd_X": melhor_casa_oddX,
                "Odd_X": round(melhor_odd_empate, 2),
                "Stake_Odd_X_R$": round(calc["stake_empate"], 2),
                "Casa_Odd_2": melhor_casa_odd2,
                "Odd_2": round(melhor_odd_visitante, 2),
                "Stake_Odd_2_R$": round(calc["stake_visitante"], 2),
                "Indice_Arbitragem": round(calc["soma_probabilidades"], 4),
                "Eh_Surebet": "SIM" if eh_surebet_valida else "NAO",
                "Lucro_Percentual_%": round(calc["lucro_percentual"], 2),
                "Lucro_Estimado_R$": round(calc["lucro_estimado"], 2),
                "Banca_Total_R$": round(banca_total, 2)
            }
            report_rows.append(row)
            
    cols = [
        "Campeonato", "Data_Jogo", "Time_Casa", "Time_Visitante",
        "Casa_Odd_1", "Odd_1", "Stake_Odd_1_R$",
        "Casa_Odd_X", "Odd_X", "Stake_Odd_X_R$",
        "Casa_Odd_2", "Odd_2", "Stake_Odd_2_R$",
        "Indice_Arbitragem", "Eh_Surebet", "Lucro_Percentual_%",
        "Lucro_Estimado_R$", "Banca_Total_R$"
    ]
    df = pd.DataFrame(report_rows, columns=cols)
    if not df.empty and "Lucro_Percentual_%" in df.columns:
        df = df.sort_values(by="Lucro_Percentual_%", ascending=False)
    return df


