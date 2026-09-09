#!/usr/bin/env python3
import sys
import os
import time
import requests
import pymysql
import hashlib
import random
import math
from datetime import datetime, timedelta

# Permitir importação de módulos de scrapers em src/dags/lib e configs em scripts
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '../src/dags'))
try:
    from lib.scrapers import scrape_futbol24_team_last5
except Exception:
    scrape_futbol24_team_last5 = None

try:
    from leagues_config import ALLOWED_LEAGUES, is_allowed_league
except Exception:
    ALLOWED_LEAGUES = {}

try:
    from asian_handicap_engine import (
        calculate_bivariate_poisson_matrix as ah_calculate_bivariate_poisson_matrix,
        evaluate_ah_line_poisson as ah_evaluate_line_poisson,
        evaluate_and_select_best_ah_candidate as ah_evaluate_and_select_best_candidate,
        build_fallback_lines_from_odds as ah_build_fallback_lines
    )
except Exception:
    ah_calculate_bivariate_poisson_matrix = None
    ah_evaluate_line_poisson = None
    ah_evaluate_and_select_best_candidate = None
    ah_build_fallback_lines = None

try:
    from cards_engine import (
        calculate_expected_cards as cards_calculate_expected,
        calculate_poisson_under_lines as cards_calculate_poisson_under_lines,
        calculate_team_poisson_under_lines as cards_calculate_team_poisson_under_lines
    )
except Exception:
    cards_calculate_expected = None
    cards_calculate_poisson_under_lines = None
    cards_calculate_team_poisson_under_lines = None



def get_league_card_multiplier(league_name="", league_id=None):
    """
    Retorna o multiplicador de expectativa de cartões (lambda_league) e o fator de sobredispersão (phi)
    baseado na região geográfica e histórico disciplinar da liga.
    - América do Sul e América Central (LATAM): lambda_league = 1.18x, phi = 1.28
    - Europa (todas as ligas europeias): lambda_league = 0.82x, phi = 1.10
    - Outras ligas / Default: lambda_league = 1.00x, phi = 1.15
    """
    # 1. Validação por ID Numérico Oficial da Liga
    if league_id is not None:
        try:
            lid = int(league_id)
            # Ligas Europeias Oficiais: Itália Serie A (135), Inglaterra (39), Espanha (140), Alemanha (78), França (61), etc.
            if lid in {135, 39, 140, 78, 61, 94, 88, 144, 203, 179, 197, 2, 3, 848}:
                return 0.82, 1.10
            # Ligas Sul-Americanas Oficiais: Brasil Série A (71), Série B (72), Copa do Brasil (73), Argentina (128), Libertadores (13), Sul-Americana (11)
            if lid in {71, 72, 73, 128, 13, 11}:
                return 1.18, 1.28
        except (ValueError, TypeError):
            pass

    if not league_name:
        return 1.00, 1.15

    leg_lower = str(league_name).lower().strip()

    # 2. Ligas Europeias (Europa)
    europe_keywords = [
        "premier league", "championship", "la liga", "segunda división", "segunda division",
        "serie a (italy)", "serie a italia", "bundesliga", "ligue 1", "ligue 2",
        "liga portugal", "eredivisie", "champions league", "europa league", "conference league",
        "scotland", "belgium", "pro league", "super lig", "turkey", "greece", "super league", "england", "spain", "italy", "germany", "france"
    ]
    # Se for exatamente "serie a" ou contiver termo europeu, e não mencionar explicitamente Brasil, classifica como Europa
    if leg_lower == "serie a" or any(kw in leg_lower for kw in europe_keywords):
        if not any(br in leg_lower for br in ["brasil", "brazil", "brasileir"]):
            return 0.82, 1.10

    # 3. Ligas Sul-Americanas e Centro-Americanas (LATAM)
    latam_keywords = [
        "brazil", "brasil", "brasileirão", "brasileirao", "série a", "série b", "serie b", "série c", "serie c",
        "chile", "primera división", "primera division", "argentina", "liga profesional", "copa de la liga",
        "colombia", "primera a", "uruguay", "peru", "ecuador", "liga mx", "mexico", "méxico",
        "costa rica", "honduras", "copa libertadores", "copa sudamericana", "bolivia", "paraguay", "venezuela", "guatemala"
    ]
    for kw in latam_keywords:
        if kw in leg_lower:
            return 1.18, 1.28

    return 1.00, 1.15


def calculate_poisson_over_under(xc, line=4.5):
    """
    Calcula a probabilidade exata de Over e Under X.5 cartões usando Distribuição de Poisson.
    P(X <= k) = sum(e^-xc * xc^k / k!) para k de 0 até floor(line).
    """
    if xc <= 0:
        return 0.0, 90.0
    
    k_max = int(math.floor(line))
    prob_under_cdf = 0.0
    for k in range(k_max + 1):
        prob_under_cdf += (math.exp(-xc) * (xc ** k)) / math.factorial(k)
        
    prob_under = max(0.0, min(90.0, prob_under_cdf * 100.0))
    prob_over = round(max(0.0, min(100.0, (1.0 - prob_under_cdf) * 100.0)), 2)
    
    return prob_over, round(prob_under, 2)


def calculate_poisson_under_lines(xc, phi=1.20):
    """
    Calcula as probabilidades de Under para várias linhas de cartões (3.5, 4.5, 5.5, 6.5, 7.5, 8.5)
    aplicando Fator de Sobredispersão (phi) para achatar a curva de variância e evitar probabilidades irreais de 99%+.
    Aplica cap máximo de 90.0% de probabilidade Under (Odd Justa Mínima = 1.11).
    """
    lines = [2.5, 3.5, 4.5, 5.5, 6.5, 7.5, 8.5]
    results = {}
    if xc <= 0:
        for l in lines:
            results[l] = 90.0
        return results

    # Ajusta a intensidade Poisson considerando a variância com sobredispersão (phi)
    effective_xc = xc * (phi ** 0.5)

    for l in lines:
        k_max = int(math.floor(l))
        prob_under_cdf = 0.0
        for k in range(k_max + 1):
            prob_under_cdf += (math.exp(-effective_xc) * (effective_xc ** k)) / math.factorial(k)
        
        prob_percent = round(max(0.0, min(90.0, prob_under_cdf * 100.0)), 2)
        results[l] = prob_percent

    return results

def calculate_team_poisson_under_lines(xc, phi=1.15):
    """
    Calcula as probabilidades de Under para várias linhas por TIME (1.5, 2.5, 3.5, 4.5) via Poisson com sobredispersão.
    Retorna um dicionário {linha: prob_under}.
    """
    lines = [1.5, 2.5, 3.5, 4.5]
    results = {}
    if xc <= 0:
        for l in lines:
            results[l] = 90.0
        return results

    effective_xc = xc * (phi ** 0.5)

    for l in lines:
        k_max = int(math.floor(l))
        prob_under_cdf = 0.0
        for k in range(k_max + 1):
            prob_under_cdf += (math.exp(-effective_xc) * (effective_xc ** k)) / math.factorial(k)
        results[l] = round(max(0.0, min(90.0, prob_under_cdf * 100.0)), 2)
        
    return results

def is_women_game(home_team="", away_team="", league_name=""):
    """
    Filtra e desconsidera partidas femininas (W, Womens, Feminino, Femenina).
    """
    home = str(home_team or "").strip()
    away = str(away_team or "").strip()
    league = str(league_name or "").strip()

    for t in (home, away):
        t_lower = t.lower()
        if t.endswith(" W") or t.endswith(" (W)") or " (w)" in t_lower or " w " in t_lower or "(w)" in t_lower:
            return True
        if "feminino" in t_lower or "women" in t_lower or "femenina" in t_lower:
            return True

    league_lower = league.lower()
    if "women" in league_lower or "feminino" in league_lower or "femenina" in league_lower or " w" in league_lower or "(w)" in league_lower:
        return True

    return False

def is_youth_game(home_team="", away_team="", league_name=""):
    """
    Filtra e desconsidera partidas de campeonatos/equipes das categorias de base (Sub-17, Sub-21, Sub-20, U17, U21, U20, Youth, etc.).
    """
    import re
    home = str(home_team or "").strip()
    away = str(away_team or "").strip()
    league = str(league_name or "").strip()

    # Padrão regex para capturar U15-U23, U-15 a U-23, Sub 15-23, Sub-15-23, Sub15-23
    youth_pattern = re.compile(
        r'\b(u[-.]?\s*(15|16|17|18|19|20|21|22|23)|sub[-.]?\s*(15|16|17|18|19|20|21|22|23))\b',
        re.IGNORECASE
    )

    for text in (home, away, league):
        if not text:
            continue
        text_lower = text.lower()
        if youth_pattern.search(text_lower):
            return True
        if "youth" in text_lower or "juniores" in text_lower or "(u-21)" in text_lower or "(u-17)" in text_lower or "(u21)" in text_lower or "(u17)" in text_lower:
            return True

    return False

def is_cup_game(league_name=""):
    """
    Identifica se a partida pertence a um torneio de Copa Eliminatória ou Mata-Mata.
    """
    if not league_name:
        return False
    leg_lower = str(league_name).lower().strip()
    cup_keywords = [
        "cup", "copa", "pokal", "coppa", "coupe", "trophy", "taça", "taca",
        "emperor", "j.league cup", "dfb pokal", "copa del rey",
        "fa cup", "efl cup", "league cup", "karabao", "carabao", "copa do brasil",
        "copa libertadores", "copa sudamericana", "leagues cup", "champions league"
    ]
    for kw in cup_keywords:
        if kw in leg_lower:
            return True
    return False

def is_early_season_game(league_name="", fixture_date=None):
    """
    Verifica se a partida ocorre na janela de início de temporada (primeiras rodadas / meses iniciais).
    Para Ligas Europeias (Premier League, La Liga, Jupiler Pro League, Bundesliga, Ligue 1, Eredivisie, etc.): Agosto e Setembro (meses 8 e 9).
    Para Ligas Sul-Americanas (Brasileirão, Argentina, etc.): Janeiro a Abril (meses 1 a 4). Em setembro estão na 25ª+ rodada.
    """
    if not fixture_date:
        from datetime import datetime
        month = datetime.now().month
    elif hasattr(fixture_date, 'month'):
        month = fixture_date.month
    else:
        try:
            from datetime import datetime
            month = datetime.strptime(str(fixture_date)[:10], '%Y-%m-%d').month
        except Exception:
            from datetime import datetime
            month = datetime.now().month

    l_name_low = str(league_name or '').lower().strip()

    is_south_america = any(sa in l_name_low for sa in [
        'brasil', 'brasileir', 'serie a', 'série a', 'serie b', 'série b', 
        'copa do brasil', 'argentin', 'liga profesional', 'libertadores', 'sudamericana'
    ])
    if is_south_america:
        return month in (1, 2, 3, 4)

    return month in (8, 9)

def _normalize_team_name_for_match(n):
    if not n:
        return ""
    import re, unicodedata
    nfkd = unicodedata.normalize('NFKD', str(n))
    clean = ''.join(c for c in nfkd if not unicodedata.combining(c)).lower()
    clean = re.sub(r'\b(fc|cf|club|clube|ca|cd|fk|sk|ff|if|aif|jrs|juniors|afc)\b', '', clean)
    clean = re.sub(r'[\-_/]', ' ', clean)
    clean = re.sub(r'\s+', ' ', clean).strip()
    return clean

def _is_team_match(search_name, target_team, search_id=None, target_id=None):
    if search_id and target_id and str(search_id).strip() and str(target_id).strip():
        try:
            if int(search_id) == int(target_id):
                return True
            else:
                return False
        except (ValueError, TypeError):
            pass
    s_norm = _normalize_team_name_for_match(search_name)
    t_norm = _normalize_team_name_for_match(target_team)
    if s_norm and t_norm:
        s_raw = search_name.lower().strip()
        t_raw = target_team.lower().strip()
        if 'botafogo' in s_norm and 'botafogo' in t_norm:
            if ('sp' in s_raw or 'botafogo-sp' in s_raw or 'botafogo/sp' in s_raw) != ('sp' in t_raw or 'botafogo-sp' in t_raw or 'botafogo/sp' in t_raw):
                return False
        if s_norm == t_norm:
            return True
        # Casos onde um nome limpo contém o outro (ex: 'al hilal' em 'al hilal saudi', 'al khaleej' em 'al khaleej saihat')
        if len(s_norm) >= 4 and len(t_norm) >= 4:
            if s_norm in t_norm or t_norm in s_norm:
                return True
        # Casos com sobreposição de palavras (ex: 'aik stockholm' vs 'aik', 'caykur rizespor' vs 'rizespor')
        s_words = set(s_norm.split())
        t_words = set(t_norm.split())
        if s_words and t_words and (s_words.issubset(t_words) or t_words.issubset(s_words)):
            return True
    return False

_api_sports_last5_cache = {}
_futbol24_failed_teams_cache = set()

_api_sports_rate_limited = False
_api_sports_odds_rate_limited = False
_api_sports_quota_exceeded = False
def _format_match_date(fdate):
    if not fdate:
        return ""
    if hasattr(fdate, 'strftime'):
        return fdate.strftime('%d/%m/%Y')
    s = str(fdate).strip()
    import re
    m = re.match(r'^(\d{4})-(\d{2})-(\d{2})', s)
    if m:
        return f"{m.group(3)}/{m.group(2)}/{m.group(1)}"
    m2 = re.match(r'^(\d{1,2})/(\d{1,2})/(\d{4})', s)
    if m2:
        return f"{m2.group(1).zfill(2)}/{m2.group(2).zfill(2)}/{m2.group(3)}"
    return s[:10]

def fetch_api_sports_team_last5(team_id, limit=5):
    """
    Busca os últimos N jogos de um time via API-Sports (https://v3.football.api-sports.io/fixtures?team={team_id}&last=5).
    Possui cache em memória e mecanismo de retry/backoff contra rate limiting.
    """
    global _api_sports_rate_limited
    if not team_id or _api_sports_rate_limited:
        return None
    
    try:
        tid = int(team_id)
    except (ValueError, TypeError):
        return None

    if tid in _api_sports_last5_cache:
        return _api_sports_last5_cache[tid]

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    fetch_limit = max(limit * 3, 20)
    url = f"https://v3.football.api-sports.io/fixtures?team={tid}&last={fetch_limit}&status=FT"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    for attempt in range(2):
        if _api_sports_rate_limited:
            return None
        try:
            resp = requests.get(url, headers=headers, timeout=5).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"[API-Sports] Rate limit atingido para team_id #{team_id}. Ativando Circuit-Breaker para chamadas de forma recente nesta execução.")
                _api_sports_rate_limited = True
                return None

            fixtures_api = resp.get('response', [])
            if not fixtures_api:
                if attempt == 0:
                    time.sleep(0.5)
                    continue
                return None

            matches = []
            for item in fixtures_api:
                league_info = item.get('league', {})
                l_id = league_info.get('id')
                l_name = str(league_info.get('name', '')).lower()

                # Desconsiderar estritamente partidas amistosas (ex: Friendlies Clubs ID 667, Amistosos)
                if l_id in (667, 10) or 'friendly' in l_name or 'amistoso' in l_name:
                    continue

                teams = item.get('teams', {})
                goals = item.get('goals', {})
                home_id = teams.get('home', {}).get('id')
                is_home = (int(home_id) == tid) if home_id else True
                opp_name = teams.get('away', {}).get('name') if is_home else teams.get('home', {}).get('name')
                gh = goals.get('home') if goals.get('home') is not None else 0
                ga = goals.get('away') if goals.get('away') is not None else 0
                fdate_raw = item.get('fixture', {}).get('date')
                
                if is_home:
                    res = "V" if gh > ga else ("E" if gh == ga else "D")
                    sc = f"{gh}x{ga}"
                else:
                    res = "V" if ga > gh else ("E" if gh == ga else "D")
                    sc = f"{ga}x{gh}"

                matches.append({
                    "opponent": opp_name,
                    "score": sc,
                    "result": res,
                    "is_home": is_home,
                    "date": _format_match_date(fdate_raw)
                })
                if len(matches) >= limit:
                    break

            if matches:
                _api_sports_last5_cache[tid] = matches
                time.sleep(0.5)
                return matches
        except Exception as e:
            print(f"Aviso ao consultar API-Sports para últimos 5 jogos do team_id #{team_id} (tentativa {attempt+1}): {e}")
            time.sleep(0.5)

    return None

_team_last5_form_cache = {}

def _is_match_duplicate(cand, existing_list):
    if not cand or not existing_list:
        return False
    cand_opp_norm = _normalize_team_name_for_match(cand.get('opponent', ''))
    cand_score = str(cand.get('score', '')).strip().replace('-', 'x')
    cand_date = str(cand.get('date', '')).strip()

    for ex in existing_list:
        ex_opp_norm = _normalize_team_name_for_match(ex.get('opponent', ''))
        ex_score = str(ex.get('score', '')).strip().replace('-', 'x')
        ex_date = str(ex.get('date', '')).strip()

        # Se as datas coincidem exatamente (e preenchidas), é a mesma partida
        if cand_date and ex_date and cand_date == ex_date:
            return True

        # Se o placar é o mesmo e os nomes normalizados de oponente batem
        if cand_score and ex_score and cand_score == ex_score:
            if cand_opp_norm == ex_opp_norm or (cand_opp_norm and cand_opp_norm in ex_opp_norm) or (ex_opp_norm and ex_opp_norm in cand_opp_norm):
                return True

    return False

def fetch_team_last5_form(cursor, team_name, team_id=None, league_id=None):
    """
    Busca a sequência recente (últimos 5 jogos) do time.
    Prioriza a consulta local no banco MySQL por ID estrito, depois por Nome + Liga,
    seguida de fallback na API-Sports e Futbol24.
    Garante sincronização total entre v, e, d, pts e a lista visual de partidas (matches).
    """
    cache_key = (str(team_id or '').strip(), str(team_name or '').lower().strip(), str(league_id or '').strip())
    if cache_key in _team_last5_form_cache:
        return _team_last5_form_cache[cache_key]

    matches = []
    seen_fixtures = set()

    # 1. Consulta no banco MySQL local por ID estrito
    if cursor is not None and team_id and str(team_id).strip():
        try:
            sql_id = """
                SELECT fixture_id, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, fixture_date
                FROM fixtures_trends
                WHERE status IN ('FT', 'AET', 'PEN')
                  AND goals_home IS NOT NULL
                  AND goals_away IS NOT NULL
                  AND (home_team_id = %s OR away_team_id = %s)
                  AND (league_id NOT IN (667, 10) AND (league_name IS NULL OR (LOWER(league_name) NOT LIKE '%%friendl%%' AND LOWER(league_name) NOT LIKE '%%amistoso%%')))
                ORDER BY fixture_date DESC
                LIMIT 15
            """
            cursor.execute(sql_id, (team_id, team_id))
            rows_id = cursor.fetchall()
            for r in rows_id:
                fid = r.get('fixture_id')
                if fid in seen_fixtures:
                    continue
                seen_fixtures.add(fid)
                is_home = (int(r['home_team_id']) == int(team_id)) if r.get('home_team_id') else (r['home_team'].lower() == team_name.lower())
                gh = r['goals_home'] if r['goals_home'] is not None else 0
                ga = r['goals_away'] if r['goals_away'] is not None else 0
                opp_name = r['away_team'] if is_home else r['home_team']
                fdate = r.get('fixture_date')
                if is_home:
                    res = "V" if gh > ga else ("E" if gh == ga else "D")
                    sc = f"{gh}x{ga}"
                else:
                    res = "V" if ga > gh else ("E" if gh == ga else "D")
                    sc = f"{ga}x{gh}"
                matches.append({"opponent": opp_name, "score": sc, "result": res, "is_home": is_home, "date": _format_match_date(fdate), "fixture_id": fid})
                if len(matches) >= 5:
                    break
        except Exception as e_sql_id:
            print(f"Aviso na busca SQL por ID de forma para '{team_name}' (#{team_id}): {e_sql_id}")

    # 2. Se retornado < 5 partidas, consulta no banco MySQL local por Nome (+ Filtro de Liga/País com Fallback Geral)
    if cursor is not None and len(matches) < 5:
        try:
            clean_search = f"%{_normalize_team_name_for_match(team_name)}%"
            queries_to_try = []
            if league_id and str(league_id).strip():
                sql_league = """
                    SELECT fixture_id, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, fixture_date
                    FROM fixtures_trends
                    WHERE status IN ('FT', 'AET', 'PEN')
                      AND goals_home IS NOT NULL
                      AND goals_away IS NOT NULL
                      AND league_id = %s
                      AND (league_id NOT IN (667, 10) AND (league_name IS NULL OR (LOWER(league_name) NOT LIKE '%%friendl%%' AND LOWER(league_name) NOT LIKE '%%amistoso%%')))
                      AND (LOWER(home_team) LIKE %s OR LOWER(away_team) LIKE %s)
                    ORDER BY fixture_date DESC
                    LIMIT 30
                """
                queries_to_try.append((sql_league, (league_id, clean_search, clean_search)))

            sql_all = """
                SELECT fixture_id, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, fixture_date
                FROM fixtures_trends
                WHERE status IN ('FT', 'AET', 'PEN')
                  AND goals_home IS NOT NULL
                  AND goals_away IS NOT NULL
                  AND (league_id NOT IN (667, 10) AND (league_name IS NULL OR (LOWER(league_name) NOT LIKE '%%friendl%%' AND LOWER(league_name) NOT LIKE '%%amistoso%%')))
                  AND (LOWER(home_team) LIKE %s OR LOWER(away_team) LIKE %s)
                ORDER BY fixture_date DESC
                LIMIT 30
            """
            queries_to_try.append((sql_all, (clean_search, clean_search)))

            for sql_query, params in queries_to_try:
                if len(matches) >= 5:
                    break
                cursor.execute(sql_query, params)
                rows = cursor.fetchall()
                for r in rows:
                    fid = r.get('fixture_id')
                    if fid in seen_fixtures:
                        continue
                    h_match = _is_team_match(team_name, r['home_team'], team_id, r.get('home_team_id'))
                    a_match = _is_team_match(team_name, r['away_team'], team_id, r.get('away_team_id'))
                    if h_match or a_match:
                        seen_fixtures.add(fid)
                        gh = r['goals_home'] if r['goals_home'] is not None else 0
                        ga = r['goals_away'] if r['goals_away'] is not None else 0
                        is_home = h_match
                        opp_name = r['away_team'] if is_home else r['home_team']
                        fdate = r.get('fixture_date')
                        if is_home:
                            res = "V" if gh > ga else ("E" if gh == ga else "D")
                            sc = f"{gh}x{ga}"
                        else:
                            res = "V" if ga > gh else ("E" if gh == ga else "D")
                            sc = f"{ga}x{gh}"
                        matches.append({"opponent": opp_name, "score": sc, "result": res, "is_home": is_home, "date": _format_match_date(fdate), "fixture_id": fid})
                        if len(matches) >= 5:
                            break
        except Exception as e_sql:
            print(f"Aviso na busca SQL por Nome de forma para '{team_name}': {e_sql}")

    # 2.5 Herança Segura de U5J_DATA de confrontos recentes no fixtures_trends (Janela máx 10 dias + Checagem Anti-Defasagem)
    if cursor is not None and len(matches) < 5:
        try:
            sql_prev = """
                SELECT fixture_id, fixture_date, ah_reasoning
                FROM fixtures_trends
                WHERE ah_reasoning LIKE '%|| U5J_DATA:%'
                  AND fixture_date >= NOW() - INTERVAL 10 DAY
                  AND (
                      (home_team_id = %s OR away_team_id = %s)
                      OR (LOWER(home_team) LIKE %s OR LOWER(away_team) LIKE %s)
                  )
                ORDER BY fixture_date DESC
                LIMIT 3
            """
            c_term = f"%{_normalize_team_name_for_match(team_name)}%"
            cursor.execute(sql_prev, (team_id or -1, team_id or -1, c_term, c_term))
            prev_rows = cursor.fetchall()
            for prow in prev_rows:
                p_reason = prow.get('ah_reasoning') or ''
                if '|| U5J_DATA:' in p_reason:
                    try:
                        u_str = p_reason.split('|| U5J_DATA:')[1].split('||')[0].strip()
                        u_obj = json.loads(u_str)
                        for side in ('home', 'away'):
                            side_data = u_obj.get(side, {})
                            if isinstance(side_data, dict) and side_data.get('matches'):
                                m_list = side_data.get('matches', [])
                                if len(m_list) >= 4:
                                    for pm in m_list:
                                        if not _is_match_duplicate(pm, matches):
                                            matches.append(pm)
                                        if len(matches) >= 5:
                                            break
                            if len(matches) >= 5:
                                break
                    except Exception:
                        pass
                if len(matches) >= 5:
                    break
        except Exception as e_prev:
            pass

    # 3. Consulta rápida na tabela de cache persistente team_last5_cache ou na API-Sports por team_id se o banco local possuir menos de 5 partidas
    if len(matches) < 5 and team_id:
        has_cached_entry = False
        # 3.1 Verifica primeiro no cache persistente do MySQL (válido por 24 horas)
        if cursor is not None:
            try:
                cursor.execute("""
                    SELECT form_json FROM team_last5_cache 
                    WHERE team_id = %s AND updated_at >= NOW() - INTERVAL 24 HOUR
                    LIMIT 1
                """, (team_id,))
                c_row = cursor.fetchone()
                if c_row and c_row.get('form_json'):
                    c_matches = json.loads(c_row['form_json']) if isinstance(c_row['form_json'], str) else c_row['form_json']
                    if isinstance(c_matches, list):
                        for am in c_matches:
                            if not _is_match_duplicate(am, matches):
                                matches.append(am)
                            if len(matches) >= 5:
                                break
                    has_cached_entry = len(matches) >= 5
            except Exception as e_c:
                pass

        # 3.2 Se NÃO encontrou no cache de 24h e ainda não tiver 5 partidas, consulta a API-Sports e persiste no MySQL
        if not has_cached_entry and len(matches) < 5:
            try:
                api_m = fetch_api_sports_team_last5(team_id, limit=5)
                if api_m is not None:
                    for am in api_m:
                        if not _is_match_duplicate(am, matches):
                            matches.append(am)
                        if len(matches) >= 5:
                            break
                    if cursor is not None:
                        try:
                            cursor.execute("""
                                INSERT INTO team_last5_cache (team_id, team_name, league_id, form_json, updated_at)
                                VALUES (%s, %s, %s, %s, NOW())
                                ON DUPLICATE KEY UPDATE 
                                    form_json = VALUES(form_json),
                                    updated_at = NOW(),
                                    team_name = VALUES(team_name),
                                    league_id = VALUES(league_id)
                            """, (team_id, team_name, league_id, json.dumps(matches[:5])))
                            if hasattr(cursor, 'connection') and cursor.connection:
                                cursor.connection.commit()
                        except Exception:
                            pass
            except Exception as e_api_m:
                print(f"Aviso na busca por API-Sports para '{team_name}' (#{team_id}): {e_api_m}")

    # 4. Fallback no Futbol24 se o banco e a API-Sports estiverem sem cota / < 5 jogos
    if len(matches) < 5:
        try:
            from lib.scrapers import scrape_futbol24_team_last5
            league_to_country = {
                71: 'Brazil', 72: 'Brazil', 73: 'Brazil', 74: 'Brazil', 75: 'Brazil', 642: 'Brazil',
                39: 'England', 40: 'England', 41: 'England', 42: 'England', 45: 'England', 48: 'England',
                140: 'Spain', 141: 'Spain', 143: 'Spain',
                135: 'Italy', 136: 'Italy', 137: 'Italy',
                78: 'Germany', 79: 'Germany', 81: 'Germany',
                61: 'France', 62: 'France', 66: 'France',
                94: 'Portugal',
                88: 'Netherlands', 89: 'Netherlands',
                128: 'Argentina', 129: 'Argentina', 130: 'Argentina',
                103: 'Norway', 104: 'Norway',
                113: 'Sweden',
                119: 'Denmark',
                144: 'Belgium',
                218: 'Austria',
                179: 'Scotland',
                106: 'Poland',
                345: 'Czech-Republic',
                203: 'Turkey',
                207: 'Switzerland',
                197: 'Greece',
                283: 'Romania',
                286: 'Serbia',
                244: 'Finland',
                281: 'Peru',
                242: 'Ecuador', 917: 'Ecuador',
                268: 'Uruguay',
                265: 'Chile',
                239: 'Colombia',
                501: 'Paraguay',
                262: 'Mexico', 263: 'Mexico',
                253: 'USA', 772: 'USA',
                98: 'Japan',
                292: 'Korea-Republic',
                169: 'China',
                307: 'Saudi-Arabia'
            }
            country_hint = None
            if league_id:
                try:
                    country_hint = league_to_country.get(int(league_id))
                except (ValueError, TypeError):
                    pass

            f24_data = scrape_futbol24_team_last5(team_name, country=country_hint)
            if f24_data and f24_data.get('matches'):
                for am in f24_data['matches']:
                    if not _is_match_duplicate(am, matches):
                        matches.append(am)
                    if len(matches) >= 5:
                        break

                if cursor is not None and team_id:
                    try:
                        cursor.execute("""
                            INSERT INTO team_last5_cache (team_id, team_name, league_id, form_json, updated_at)
                            VALUES (%s, %s, %s, %s, NOW())
                            ON DUPLICATE KEY UPDATE 
                                form_json = VALUES(form_json),
                                updated_at = NOW(),
                                team_name = VALUES(team_name),
                                league_id = VALUES(league_id)
                        """, (team_id, team_name, league_id, json.dumps(matches[:5])))
                        if hasattr(cursor, 'connection') and cursor.connection:
                            cursor.connection.commit()
                    except Exception:
                        pass
        except Exception as e_f24_form:
            pass

    # 5. Se não houver partidas encontradas no banco nem via API/scraper
    if not matches:
        empty_res = {
            "v": 0, "e": 0, "d": 0, "pts": 0,
            "text": "N/D",
            "matches": []
        }
        _team_last5_form_cache[cache_key] = empty_res
        return empty_res

    # Limpa fixture_id temporário do objeto de retorno das partidas
    clean_matches = []
    for m in matches[:5]:
        m_copy = {k: v for k, v in m.items() if k != "fixture_id"}
        clean_matches.append(m_copy)

    def _parse_sort_key(m):
        d_str = m.get('date', '')
        if d_str and len(d_str) >= 10:
            parts = d_str.split('/')
            if len(parts) == 3:
                return f"{parts[2]}-{parts[1]}-{parts[0]}"
        return ""

    # Ordena os 5 jogos cronologicamente (do mais antigo para o mais recente)
    if all(m.get('date') for m in clean_matches):
        clean_matches.sort(key=_parse_sort_key)
    else:
        clean_matches.reverse()

    # Recalcula v, e, d, pts estritamente a partir das partidas em matches
    v = sum(1 for m in clean_matches if m["result"] == "V")
    e = sum(1 for m in clean_matches if m["result"] == "E")
    d = sum(1 for m in clean_matches if m["result"] == "D")
    pts = (3 * v) + (1 * e)

    res = {
        "v": v, "e": e, "d": d, "pts": pts,
        "text": f"{v}V-{e}E-{d}D",
        "matches": clean_matches
    }
    _team_last5_form_cache[cache_key] = res
    return res

def build_natural_language_explanation(suggestion, home_team, away_team):
    """
    Gera a explicação detalhada em linguagem natural com ícones de resultado (🟢 🟡 🔴).
    """
    if "0.0" in suggestion or "Empate Anula" in suggestion or "+00" in suggestion or "+ 00" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da aposta (Lucro Total).\n"
            f"⚪ Empate: Aposta ANULADA (100% do valor apostado é devolvido - Retorno igual ao valor apostado).\n"
            f"🔴 Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "-0.25" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da aposta.\n"
            f"🟡 Empate: PERDE 50% da aposta e recupera os outros 50%.\n"
            f"🔴 Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "+0.25" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da aposta.\n"
            f"🟢 Empate: GANHA 50% do Lucro + 100% da aposta de volta.\n"
            f"🔴 Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "-0.5" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da aposta (Vitória Simples).\n"
            f"🔴 Empate ou Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "+0.5" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} ou Empate: Você GANHA 100% da aposta (Dupla Chance).\n"
            f"🔴 Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "-0.75" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} por 2+ gols: GANHA 100% do Lucro.\n"
            f"🟡 Vitória do {team_fav} por 1 gol exato: GANHA 50% do Lucro + 100% da Aposta.\n"
            f"🔴 Empate ou Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "+0.75" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} ou Empate: GANHA 100% da Aposta.\n"
            f"🟡 Derrota do {team_fav} por 1 gol exato: PERDE apenas 50% da aposta.\n"
            f"🔴 Derrota por 2+ gols: Aposta PERDIDA."
        )
    elif "-1.0" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} por 2+ gols: GANHA 100% do Lucro.\n"
            f"🟡 Vitória do {team_fav} por 1 gol exato: 100% de REEMBOLSO do valor apostado.\n"
            f"🔴 Empate ou Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "+1.0" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} ou Empate: GANHA 100% da Aposta.\n"
            f"🟡 Derrota do {team_fav} por 1 gol exato: 100% de REEMBOLSO do valor apostado.\n"
            f"🔴 Derrota por 2+ gols: Aposta PERDIDA."
        )
    elif "+1.5" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}, Empate ou Derrota por 1 gol exato: Você GANHA 100% da aposta.\n"
            f"🔴 Derrota do {team_fav} por 2 ou mais gols: Aposta PERDIDA."
        )
    elif "-1.5" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} por 2+ gols de diferença: Você GANHA 100% da aposta.\n"
            f"🔴 Vitória por apenas 1 gol, Empate ou Derrota: Aposta PERDIDA."
        )
    elif "+1.25" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} ou Empate: GANHA 100% da aposta.\n"
            f"🟡 Derrota do {team_fav} por 1 gol exato: PERDE apenas 50% da aposta e recupera os outros 50%.\n"
            f"🔴 Derrota por 2+ gols: Aposta PERDIDA."
        )
    elif "-1.25" in suggestion:
        if away_team.lower() in suggestion.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} por 2+ gols: GANHA 100% da aposta.\n"
            f"🟡 Vitória do {team_fav} por 1 gol exato: PERDE 50% da aposta e recupera os outros 50%.\n"
            f"🔴 Empate ou Derrota: Aposta PERDIDA."
        )
    else:
        return (
            f"🟢 Vitória do {home_team}: Aposta Coberta.\n"
            f"🟡 Empate: Reembolso parcial ou total dependendo da linha.\n"
            f"🔴 Vitória do {away_team}: Aposta Perdida."
        )

def build_natural_standings_motivation_text(home_team, away_team, home_rank, away_rank, home_ppg=None, away_ppg=None, home_zone=None, away_zone=None, motivation_score=None):
    """
    Gera a motivação de tabela em linguagem 100% natural e clara em Português.
    Explica a situação dos times no campeonato e a urgência/impacto no jogo.
    """
    if not home_rank or not away_rank:
        return ""
    
    try:
        h_r = int(home_rank)
        a_r = int(away_rank)
    except Exception:
        return ""

    h_ppg_val = float(home_ppg) if (home_ppg is not None and float(home_ppg) > 0) else None
    a_ppg_val = float(away_ppg) if (away_ppg is not None and float(away_ppg) > 0) else None
    
    h_ppg_str = f" ({h_ppg_val:.2f} pts/jogo)" if h_ppg_val else ""
    a_ppg_str = f" ({a_ppg_val:.2f} pts/jogo)" if a_ppg_val else ""
    rank_diff = abs(h_r - a_r)

    # 1. Ambos no Topo / G4 (Confronto Direto pelo Título ou G4)
    if h_r <= 4 and a_r <= 4:
        desc = (
            f"Confronto direto de peso no topo da tabela entre o {home_team} ({h_r}º colocado{h_ppg_str}) e o {away_team} ({a_r}º colocado{a_ppg_str}). "
            f"A disputa direta pelas primeiras posições eleva ao máximo a motivação e o ritmo decisivo de ambos os times."
        )
    # 2. Ambos na Zona de Rebaixamento / Z4 (Jogo de 6 pontos na degola)
    elif h_r >= 17 and a_r >= 17:
        desc = (
            f"Duelo dramático de 6 pontos na luta contra o rebaixamento entre o {home_team} ({h_r}º colocado{h_ppg_str}) e o {away_team} ({a_r}º colocado{a_ppg_str}). "
            f"Ambas as equipes entram sob altíssima pressão para tentar escapar do Z4."
        )
    # 3. Mandante no Z4 (Urgência máxima de vitória em casa)
    elif h_r >= 17:
        desc = (
            f"Urgência máxima de vitória para o {home_team} ({h_r}º colocado{h_ppg_str}), que joga sob forte pressão da torcida para pontuar e sair da Zona de Rebaixamento contra o {away_team} ({a_r}º colocado{a_ppg_str})."
        )
    # 4. Visitante no Z4 (Pressão no visitante)
    elif a_r >= 17:
        desc = (
            f"O visitante {away_team} ({a_r}º colocado{a_ppg_str}) necessita desesperadamente de pontos para tentar deixar a Zona de Rebaixamento contra o {home_team} ({h_r}º colocado{h_ppg_str})."
        )
    # 5. Clássico de Posições / Confronto Direto de Meio/Topo (Diferença de até 3 posições)
    elif rank_diff <= 3:
        desc = (
            f"Confronto direto muito emparelhado na classificação entre o {home_team} ({h_r}º colocado{h_ppg_str}) e o {away_team} ({a_r}º colocado{a_ppg_str}). "
            f"Uma vitória garante salto significativo na tabela para qualquer uma das equipes."
        )
    # 6. Favorito no Topo recebendo time da parte de baixo (Diferença >= 8 posições)
    elif h_r < a_r and (a_r - h_r) >= 8:
        desc = (
            f"Grande contraste de campanha no campeonato: o {home_team} ({h_r}º colocado{h_ppg_str}) ostenta colocação muito superior na tabela em relação ao {away_team} ({a_r}º colocado{a_ppg_str}), reforçando a tendência de controle do jogo pelo mandante."
        )
    elif a_r < h_r and (h_r - a_r) >= 8:
        desc = (
            f"O visitante {away_team} ({a_r}º colocado{a_ppg_str}) faz campanha bastante superior no campeonato em comparação ao {home_team} ({h_r}º colocado{h_ppg_str}), reduzindo o peso do fator casa nesta partida."
        )
    # 7. Caso geral equilibrado
    else:
        desc = (
            f"Duelo entre o {home_team} ({h_r}º colocado{h_ppg_str}) e o {away_team} ({a_r}º colocado{a_ppg_str}), em momentos distintos na classificação do campeonato."
        )

    return f"\n• 📊 Contexto de Tabela e Motivação: {desc}"

def build_natural_language_motivation(
    suggestion, home_team, away_team, delta_goals,
    home_goals_scored, away_goals_conceded, away_goals_scored, home_goals_conceded,
    home_cs_pct, away_cs_pct, home_last5, away_last5,
    home_in_crisis, away_in_crisis,
    odd_home=None, odd_away=None,
    home_rank=None, away_rank=None, home_ppg=None, away_ppg=None, standings_motivation=None,
    home_zone=None, away_zone=None,
    league_name=None
):
    """
    Gera a motivação do palpite em linguagem natural amigável destacando em alto nível os critérios aplicados,
    incluindo o contexto de Tabela e Motivação dos times.
    """
    home_text = home_last5.get("text", "2V-1E-2D") if home_last5 else "2V-1E-2D"
    away_text = away_last5.get("text", "2V-1E-2D") if away_last5 else "2V-1E-2D"
    try:
        odd_str = f" [Odds Mercado: H:{float(odd_home):.2f}/A:{float(odd_away):.2f}]" if (odd_home and odd_away and float(odd_home) > 1.0) else ""
    except Exception:
        odd_str = ""

    home_pts = home_last5.get("pts", 0) if home_last5 else 0
    away_pts = away_last5.get("pts", 0) if away_last5 else 0

    res_text = ""

    # Trava para Abstenção
    if "Sem Entrada" in suggestion or "Abstenção" in suggestion:
        if league_name and is_cup_game(league_name):
            res_text = (
                f"🎯 Fator Crucial: Alerta de Torneio de Copa e Proteção de Banca ({league_name}).\n"
                f"A indicação de abstenção fundamenta-se na preservação operacional do capital:\n"
                f"• 🏆 Risco de Rodízio em Copa Mata-Mata: Partida eliminatória onde o favorito costuma atuar com time reserva/misto, gerando imprevisibilidade técnica superior à liga nacional.\n"
                f"• 🛡️ Proteção pelo Gatekeeper: Entrada de Handicap bloqueada para evitar exposição em cenários de assimetria motivacional."
            )
        else:
            res_text = (
                f"🎯 Fator Crucial: Gestão de Risco e Proteção de Banca.\n"
                f"A indicação de abstenção fundamenta-se na priorização da segurança operacional:\n"
                f"• ⚠️ Divergência de Mercado e Estatísticas: As cotações da casa de apostas indicam preferência por um time, mas os dados estatísticos brutos mostram incoerência ou oscilação.\n"
                f"• 🛡️ Proteção de Banca: Entrada de Handicap bloqueada pelo Gatekeeper para evitar exposições de alto risco em cenários de incerteza."
            )
    elif odd_home and float(odd_home) <= 1.50 and home_team.lower() in suggestion.lower():
        res_text = (
            f"🎯 Fator Crucial: Domínio Estatístico e Alto Favoritismo do Mandante ({home_team} {home_text}).\n"
            f"A indicação a favor do mandante {home_team} fundamenta-se no alinhamento das odds de mercado e na produção ofensiva em casa:\n"
            f"• 📈 Consenso das Odds de Mercado: Cotação de alto favoritismo para o mandante {home_team} (Odd {odd_home:.2f} vs {odd_away:.2f}), confirmando ampla probabilidade de vitória.\n"
            f"• 🏠 Fator Mando e Produção Ofensiva: O {home_team} mantém forte saldo projetado em casa ({home_text}).\n"
            f"• 🛡️ Proteção de Banca: Indicação a favor do mandante com cobertura de reembolso no empate."
        )
    elif (away_pts >= 9 or (away_last5 and away_last5.get("v", 0) >= 3)) and (home_pts <= 5 or (home_last5 and home_last5.get("d", 0) >= 3)) and away_team.lower() in suggestion.lower():
        if odd_home and odd_away and float(odd_home) < float(odd_away):
            market_note = f"• 📈 Contraponto às Odds de Mercado: Embora as odds do mercado atribuam favoritismo ao mandante {home_team} ({odd_home:.2f} vs {odd_away:.2f}), o momento recente superior do {away_team} ({away_text} vs {home_text}) justifica a indicação de proteção (Empate Anula) a favor do visitante."
        else:
            market_note = f"• 📈 Precificação Ponderada do Mercado: O mercado estatístico ajustado alinha-se ao momento superior do visitante {away_team}{odd_str}."

        res_text = (
            f"🎯 Fator Crucial: Contraste de Forma Recente e Sequência Vitoriosa do Visitante ({away_team} {away_text} vs {home_team} {home_text}).\n"
            f"A indicação a favor do visitante {away_team} fundamenta-se na priorização de 3 critérios de alta precisão:\n"
            f"• 🔥 Contraste de Forma Recente (Streak Superior): O momento excelente do {away_team} ({away_text} / {away_pts} pts em U5J) sobressai-se à sequência de derrotas/oscilação do mandante {home_team} ({home_text}).\n"
            f"• ⚡ Neutralização do Fator Casa: A disparidade de momentum recente anula o bônus de mando de campo do {home_team}.\n"
            f"{market_note}"
        )
    elif home_in_crisis and not away_in_crisis and away_team.lower() in suggestion.lower():
        res_text = (
            f"🎯 Fator Crucial: Alerta de Crise e Sequência Negativa do Mandante ({home_text} em U5J).\n"
            f"A indicação a favor do visitante {away_team} fundamenta-se na priorização de 3 critérios de alta precisão:\n"
            f"• ⚠️ Sequência Negativa do Mandante: Severa má fase do {home_team} em casa (0V em U5J e Zero Gols em Casa de {home_cs_pct:.1f}%).\n"
            f"• 🔥 Momentum Superior do Visitante: Em contrapartida, o visitante {away_team} atravessa momento superior ({away_text}).\n"
            f"• 🛡️ Inversão com Proteção: Recomendação ajustada para {away_team} com cobertura total de reembolso no empate."
        )
    elif away_in_crisis and not home_in_crisis and home_team.lower() in suggestion.lower():
        res_text = (
            f"🎯 Fator Crucial: Instabilidade do Visitante e Sequência de Derrotas ({away_text} em U5J).\n"
            f"A indicação a favor do {home_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
            f"• ⚠️ Instabilidade do Visitante: Momento delicado do visitante {away_team} fora de casa ({away_text} em U5J).\n"
            f"• 🏟️ Mando de Campo Recalibrado: Reajuste Realista do Fator Mando (+10%) e consistência do {home_team} em casa ({home_text}).\n"
            f"• 🛡️ Confirmação de Vantagem: Vantagem confirmada a favor do mandante {home_team} com proteção de banca."
        )
    elif away_team.lower() in suggestion.lower():
        if odd_home and odd_away and float(odd_home) > float(odd_away):
            title_text = "Consenso das Odds de Mercado e Desempenho do Visitante"
            intro_text = f"A indicação a favor do visitante {away_team} fundamenta-se na priorização das probabilidades de mercado:"
            odds_market_text = f"As odds do mercado indicam favoritismo do visitante {away_team}{odd_str}, prevalecendo no modelo sobre o fator casa do {home_team}."
            market_bullet = f"• ⚡ Alinhamento com o Mercado: A precificação da casa de aposta sobressai-se ao bônus de mando de campo do {home_team}."
        else:
            title_text = "Divergência de Valor e Desempenho do Visitante"
            intro_text = f"A indicação a favor do visitante {away_team} fundamenta-se na identificação de valor estatístico frente às odds de mercado:"
            odds_market_text = f"Análise combinada das estatísticas ajustadas com preferência ao visitante {away_team}{odd_str}."
            market_bullet = f"• 📊 Divergência de Valor: As odds da casa favorecem o mando do {home_team}, mas o modelo identifica valor no visitante {away_team}."

        if "+0.25" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.25 AH): Ganho total na vitória e meio-green (50% de lucro + devolução da stake) em caso de empate."
        elif "+0.5" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.5 AH / Dupla Chance): Ganho total tanto na vitória quanto em caso de empate."
        elif "-0.25" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Patrimônio (-0.25 AH): Ganho total na vitória e perda atenuada de apenas 50% em caso de empate."
        elif "0.0" in suggestion or "dnb" in suggestion.lower() or "empate anula" in suggestion.lower():
            prot_patrimonio = "• 🛡️ Proteção de Patrimônio: Indicação com cobertura total de reembolso no empate (0.0 DNB)."
        else:
            prot_patrimonio = f"• 🛡️ Proteção de Patrimônio: Indicação conservadora no mercado de Handicap Asiático ({suggestion})."

        res_text = (
            f"🎯 Fator Crucial: {title_text}.\n"
            f"{intro_text}\n"
            f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
            f"{market_bullet}\n"
            f"{prot_patrimonio}"
        )
    elif delta_goals >= 0.10 or home_team.lower() in suggestion.lower():
        if odd_home and odd_away and float(odd_home) > 1.0 and float(odd_away) > 1.0:
            if float(odd_home) <= float(odd_away):
                odds_market_text = f"As odds do mercado confirmam o favoritismo do {home_team}{odd_str}, convergindo a probabilidade estatística ao consenso das apostas."
            else:
                odds_market_text = f"As odds do mercado dão ligeira preferência ao visitante{odd_str}, mas a projeção estatística pré-jogo (xG projetado +{delta_goals:.2f}) indica vantagem do {home_team} com proteção no mando."
        else:
            odds_market_text = f"Análise estatística interna aplicada para o {home_team} (odds de mercado não disponíveis no momento)."

        if home_cs_pct >= 40.0:
            cs_note = f"• 🛡️ Solidez Defensiva em Casa: O {home_team} manteve a defesa intacta em {home_cs_pct:.1f}% das partidas em seus domínios."
        else:
            cs_note = f"• ⚠️ Vulnerabilidade Defensiva em Casa: O {home_team} apresentou fragilidade defensiva em casa (defesa vazada na maioria dos jogos / apenas {home_cs_pct:.1f}% sem sofrer gols em casa)."

        res_text = (
            f"🎯 Fator Crucial: Peso Ponderado do Mercado e Mando de Campo (+10%) ({home_team} +{delta_goals:.2f} xG Projetados Pré-Jogo).\n"
            f"A indicação a favor do {home_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
            f"• 🏟️ Reajuste Realista do Fator Mando (+10% em casa / -7% fora): A força de jogar em seus domínios impulsiona a produção ofensiva do {home_team} ({home_goals_scored:.1f} g/j).\n"
            f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
            f"{cs_note}"
        )
    elif delta_goals >= -0.60:
        if home_team.lower() in suggestion.lower():
            if odd_home and odd_away and float(odd_home) > 1.0 and float(odd_away) > 1.0:
                if float(odd_home) > float(odd_away):
                    odds_market_text = f"As odds do mercado dão preferência ao visitante {away_team}{odd_str}, mas o fator casa do {home_team} sustenta a indicação."
                else:
                    odds_market_text = f"As odds do mercado ({float(odd_home):.2f} vs {float(odd_away):.2f}) convergem com a projeção a favor do {home_team}."
            else:
                odds_market_text = f"Análise estatística interna aplicada para {home_team} e {away_team}."
            
            if "+0.25" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.25 AH): Ganho total na vitória e meio-green (50% de lucro + devolução da stake) em caso de empate."
            elif "+0.5" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.5 AH / Dupla Chance): Ganho total tanto na vitória quanto em caso de empate."
            elif "-0.25" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (-0.25 AH): Ganho total na vitória e perda atenuada de apenas 50% em caso de empate."
            elif "0.0" in suggestion or "dnb" in suggestion.lower() or "empate anula" in suggestion.lower():
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio: Indicação conservadora com cobertura total de reembolso no empate (0.0 DNB)."
            else:
                prot_patrimonio = f"• 🛡️ Proteção de Patrimônio: Indicação conservadora no mercado de Handicap Asiático ({suggestion})."

            res_text = (
                f"🎯 Fator Crucial: Mando de Campo Ponderado pelas Odds de Mercado.\n"
                f"A indicação a favor do {home_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
                f"• 🏟️ Equilíbrio e Fator Casa: Confronto estatisticamente emparelhado ({home_team} xG: {home_goals_scored:.1f} / U5J: {home_text} vs {away_team} xG: {away_goals_scored:.1f} / U5J: {away_text}), onde o fator casa do {home_team} concede vantagem.\n"
                f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
                f"{prot_patrimonio}"
            )
        else:
            if odd_home and odd_away and float(odd_home) > 1.0 and float(odd_away) > 1.0:
                if float(odd_home) > float(odd_away):
                    odds_market_text = f"As odds do mercado indicam favoritismo do visitante {away_team}{odd_str}, prevalecendo no modelo sobre a vantagem de mando de campo."
                else:
                    odds_market_text = f"As odds da casa de aposta ({float(odd_home):.2f} vs {float(odd_away):.2f}) favorecem o mando do {home_team}, mas o modelo identifica valor no visitante {away_team}."
            else:
                odds_market_text = f"Análise estatística interna aplicada para {home_team} e {away_team}."
            
            if "+0.25" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.25 AH): Ganho total na vitória e meio-green (50% de lucro + devolução da stake) em caso de empate."
            elif "+0.5" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (+0.5 AH / Dupla Chance): Ganho total tanto na vitória quanto em caso de empate."
            elif "-0.25" in suggestion:
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio (-0.25 AH): Ganho total na vitória e perda atenuada de apenas 50% em caso de empate."
            elif "0.0" in suggestion or "dnb" in suggestion.lower() or "empate anula" in suggestion.lower():
                prot_patrimonio = "• 🛡️ Proteção de Patrimônio: Indicação de valor a favor do visitante com cobertura total de reembolso no empate (0.0 DNB)."
            else:
                prot_patrimonio = f"• 🛡️ Proteção de Patrimônio: Indicação conservadora no mercado de Handicap Asiático ({suggestion})."

            res_text = (
                f"🎯 Fator Crucial: Superioridade do Visitante Ponderada pelas Odds de Mercado.\n"
                f"A indicação a favor do {away_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
                f"• ⚡ Desempenho e Momentum: Apesar do mando de campo do {home_team}, o visitante {away_team} sobressaiu-se pelo desempenho superior ajustado em campo.\n"
                f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
                f"{prot_patrimonio}"
            )
    else:
        if "+0.25" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Banca (+0.25 AH): Ganho total na vitória e meio-green (50% de lucro + devolução da stake) em caso de empate."
        elif "+0.5" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Banca (+0.5 AH / Dupla Chance): Ganho total tanto na vitória quanto em caso de empate."
        elif "-0.25" in suggestion:
            prot_patrimonio = "• 🛡️ Proteção de Banca (-0.25 AH): Ganho total na vitória e perda atenuada de apenas 50% em caso de empate."
        elif "0.0" in suggestion or "dnb" in suggestion.lower() or "empate anula" in suggestion.lower():
            prot_patrimonio = "• 🛡️ Proteção de Banca: Recomendação a favor do visitante com cobertura total de reembolso no empate (0.0 DNB)."
        else:
            prot_patrimonio = f"• 🛡️ Proteção de Banca: Recomendação estratégica no mercado de Handicap Asiático ({suggestion})."

        res_text = (
            f"🎯 Fator Crucial: Amplo Favoritismo do Visitante ({away_team} +{abs(delta_goals):.2f} xG).\n"
            f"A indicação a favor do visitante {away_team} fundamenta-se na priorização de 3 critérios de alta precisão:\n"
            f"• 🔥 Momentum e Produção Ofensiva: Momento superior e alta produção de gols do visitante {away_team} ({away_text} em U5J / {away_goals_scored:.1f} g/j).\n"
            f"• 📈 Precificação de Mercado: Cotação de mercado e favoritismo do {away_team}{odd_str} superando o fator casa do {home_team}.\n"
            f"{prot_patrimonio}"
        )

    if home_rank and away_rank:
        st_text = build_natural_standings_motivation_text(
            home_team, away_team, home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, standings_motivation
        )
        res_text += st_text

    return res_text

def analyze_trend_and_momentum(team_name: str, last5_dict: dict) -> dict:
    """
    Analisa o vetor ordenado cronologicamente das 5 partidas mais recentes (U5J).
    m[0] é a partida mais recente; m[4] é a mais antiga.
    Calcula:
      - Pontuação ponderada por recência temporal (Pts_w, escala 0 a 15).
      - Slope / Curva de Rendimento (CURVA_ASCENDENTE, CURVA_ESTAGNADA, CURVA_DESCENDENTE, CURVA_ESTAVEL).
      - Coeficiente multiplicador de momentum (fator de aceleração/frenagem ofensiva).
      - Descrição em linguagem natural da curva de rendimento.
    """
    matches = last5_dict.get("matches", []) if isinstance(last5_dict, dict) else []
    if len(matches) < 5:
        return {
            "pts_w": float(last5_dict.get("pts", 7) if isinstance(last5_dict, dict) else 7),
            "trend": "INSUFICIENTE",
            "trend_factor": 1.0,
            "trend_desc": "Amostragem incompleta (< 5 partidas)",
            "delta_trend": 0.0,
            "pts_raw": []
        }

    # Pesos temporais decrescentes: J0 (mais recente) peso 5 ... J4 (mais antigo) peso 1
    # Soma dos pesos = 5 + 4 + 3 + 2 + 1 = 15
    w_weights = [5, 4, 3, 2, 1]
    pts_raw = []
    for m in matches[:5]:
        res = (m.get("result") or "").upper()
        if res == "V":
            pts_raw.append(3)
        elif res == "E":
            pts_raw.append(1)
        else:
            pts_raw.append(0)

    # Pontuação ponderada: Score_w max = 15 * 3 = 45 -> Normalizado para 0 a 15
    score_w = sum(w * pt for w, pt in zip(w_weights, pts_raw))
    pts_w = round((score_w / 45.0) * 15.0, 2)

    # Média dos últimos 2 jogos (J0, J1) vs Média dos 3 anteriores (J2, J3, J4)
    avg_recent = (pts_raw[0] + pts_raw[1]) / 2.0  # escala 0 a 3.0
    avg_baseline = (pts_raw[2] + pts_raw[3] + pts_raw[4]) / 3.0  # escala 0 a 3.0
    delta_trend = avg_recent - avg_baseline

    num_v = sum(1 for p in pts_raw if p == 3)
    num_e = sum(1 for p in pts_raw if p == 1)
    num_d = sum(1 for p in pts_raw if p == 0)

    # Detecção de Curva de Rendimento
    if (delta_trend >= 0.70 or (avg_recent >= 2.5 and avg_recent > avg_baseline)) and pts_raw[0] == 3:
        trend = "CURVA_ASCENDENTE"
        trend_factor = 1.20  # +20% de aceleração de momentum
        trend_desc = f"Curva Ascendente em alta (Momentum positivo: {pts_raw[0]} e {pts_raw[1]} pts recentes vs {avg_baseline:.1f} pts de base)"
    elif (num_e >= 3) or (pts_raw[0] == 1 and pts_raw[1] == 1) or (num_v <= 1 and num_e >= 2):
        trend = "CURVA_ESTAGNADA"
        trend_factor = 0.88  # -12% por platô mediano / excesso de empates
        trend_desc = f"Tendência de Estagnação / Platô Mediano ({num_e} empates nos últimos jogos / baixa imposição de vitória)"
    elif delta_trend <= -0.70 or (avg_recent <= 0.5 and avg_recent < avg_baseline):
        trend = "CURVA_DESCENDENTE"
        trend_factor = 0.80  # -20% por queda de rendimento recente
        trend_desc = f"Curva Descendente em queda (Queda de rendimento recente: {avg_recent:.1f} pts recentes vs {avg_baseline:.1f} pts de base)"
    else:
        trend = "CURVA_ESTAVEL"
        trend_factor = 1.00
        trend_desc = f"Rendimento Estável ({num_v}V-{num_e}E-{num_d}D)"

    return {
        "pts_w": pts_w,
        "trend": trend,
        "trend_factor": trend_factor,
        "trend_desc": trend_desc,
        "delta_trend": round(delta_trend, 2),
        "pts_raw": pts_raw
    }

def calculate_asian_handicap_suggestion(
    home_goals_scored, home_goals_conceded, 
    away_goals_scored, away_goals_conceded, 
    home_team, away_team,
    home_cs_pct=30.0, away_cs_pct=30.0,
    home_recent_losses=0, away_recent_losses=0,
    home_recent_wins=0, away_recent_wins=0,
    home_last5=None, away_last5=None,
    odd_home=None, odd_draw=None, odd_away=None,
    home_rank=None, away_rank=None, home_ppg=None, away_ppg=None, standings_motivation=None,
    home_zone=None, away_zone=None,
    league_name=None
):
    """
    Calcula a sugestão de Handicap Asiático priorizando Odds do Mercado de Apostas, Fator Mando de Campo Recalibrado (+10% / -7%),
    Forma dos Últimos 5 Jogos (V-E-D), Clean Sheets e Trava de Alinhamento com o Mercado.
    Retorna: (ah_suggestion, ah_confidence, ah_reasoning)
    """
    import json
    import re

    if home_last5 is None:
        home_last5 = {"v": 2, "e": 1, "d": 2, "pts": 7, "text": "2V-1E-2D", "matches": []}
    if away_last5 is None:
        away_last5 = {"v": 2, "e": 1, "d": 2, "pts": 7, "text": "2V-1E-2D", "matches": []}

    # 0. Trava de Bloqueio Estrito para Odds Ausentes ou xG Zerado sem histórico
    if not odd_home or not odd_away or float(odd_home) <= 1.0 or float(odd_away) <= 1.0:
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        reasoning_text = f"🚫 APOSTA BLOQUEADA: Odds de mercado indisponíveis para esta partida. Entrada de Handicap bloqueada para proteger a banca."
        u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)
        return suggestion, confidence, f"{reasoning_text} || EXPLICACAO: 🚫 Bloqueio por Odds Indisponíveis || MOTIVACAO: Risco excessivo sem cotações de mercado reais || MEMÓRIA DE CÁLCULO || Odds Ausentes || U5J_DATA: {u5j_json}", 0.0, 0.0

    h_matches = home_last5.get("matches", []) if isinstance(home_last5, dict) else []
    a_matches = away_last5.get("matches", []) if isinstance(away_last5, dict) else []

    # 0.1 TRAVA OBRIGATÓRIA DO GATEKEEPER: Amostragem Mínima Completa de 5 Jogos
    # Para evitar distorções graves e inferências sobre dados mutilados em início de temporada,
    # exige que ambas as equipes possuam rigorosamente 5 jogos consolidados em seu histórico recente.
    if len(h_matches) < 5 or len(a_matches) < 5:
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        lacking = []
        if len(h_matches) < 5:
            lacking.append(f"{home_team} ({len(h_matches)}J)")
        if len(a_matches) < 5:
            lacking.append(f"{away_team} ({len(a_matches)}J)")
        lacking_str = ", ".join(lacking)
        reasoning_text = (
            f"🚫 NO_BET: Histórico recente incompleto (< 5 partidas consolidadas para {lacking_str}). "
            f"Entrada de Handicap bloqueada pelo Gatekeeper por segurança estatística e integridade amostral."
        )
        u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)
        return suggestion, confidence, f"{reasoning_text} || EXPLICACAO: 🚫 Bloqueio por Amostragem Insuficiente || MOTIVACAO: Risco estatístico elevado com menos de 5 jogos consolidados || MEMÓRIA DE CÁLCULO || Amostragem Incompleta || U5J_DATA: {u5j_json}", 0.0, 0.0

    # Se xG de jogo ao vivo não existe (pré-jogo), projeta o xG pré-jogo a partir do histórico U5J dos times
    if (home_goals_scored <= 0.01 and away_goals_scored <= 0.01):
        if h_matches or a_matches:
            h_scored_list, h_conceded_list = [], []
            for m in h_matches:
                score = m.get("score", "")
                if "x" in score:
                    parts = score.split("x")
                    try:
                        g_h, g_a = int(parts[0]), int(parts[1])
                        if m.get("is_home"):
                            h_scored_list.append(g_h)
                            h_conceded_list.append(g_a)
                        else:
                            h_scored_list.append(g_a)
                            h_conceded_list.append(g_h)
                    except ValueError:
                        pass

            a_scored_list, a_conceded_list = [], []
            for m in a_matches:
                score = m.get("score", "")
                if "x" in score:
                    parts = score.split("x")
                    try:
                        g_h, g_a = int(parts[0]), int(parts[1])
                        if m.get("is_home"):
                            a_scored_list.append(g_h)
                            a_conceded_list.append(g_a)
                        else:
                            a_scored_list.append(g_a)
                            a_conceded_list.append(g_h)
                    except ValueError:
                        pass

            if h_scored_list:
                home_goals_scored = max(0.5, sum(h_scored_list) / len(h_scored_list))
                home_goals_conceded = max(0.5, sum(h_conceded_list) / len(h_conceded_list))
            else:
                home_goals_scored = 1.20
                home_goals_conceded = 1.00

            if a_scored_list:
                away_goals_scored = max(0.5, sum(a_scored_list) / len(a_scored_list))
                away_goals_conceded = max(0.5, sum(a_conceded_list) / len(a_conceded_list))
            else:
                away_goals_scored = 1.00
                away_goals_conceded = 1.20

    if (home_goals_scored <= 0.01 and away_goals_scored <= 0.01):
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        reasoning_text = f"🚫 APOSTA BLOQUEADA: Histórico de partidas e estatísticas de gols indisponíveis para este confronto. Entrada de Handicap bloqueada para proteger a banca."
        u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)
        return suggestion, confidence, f"{reasoning_text} || EXPLICACAO: 🚫 Bloqueio por Histórico Indisponível || MOTIVACAO: Risco excessivo sem estatísticas prévias || MEMÓRIA DE CÁLCULO || Histórico Ausente || U5J_DATA: {u5j_json}", 0.0, 0.0

    # 1. Análise Temporal de Curva de Rendimento (Momentum / Slope) e Pontuação Ponderada
    home_trend_info = analyze_trend_and_momentum(home_team, home_last5)
    away_trend_info = analyze_trend_and_momentum(away_team, away_last5)

    home_pts_w = home_trend_info["pts_w"]
    away_pts_w = away_trend_info["pts_w"]
    home_trend = home_trend_info["trend"]
    away_trend = away_trend_info["trend"]
    home_trend_factor = home_trend_info["trend_factor"]
    away_trend_factor = away_trend_info["trend_factor"]
    home_trend_desc = home_trend_info["trend_desc"]
    away_trend_desc = away_trend_info["trend_desc"]

    home_pts = home_last5.get("pts", 7)
    home_d = home_last5.get("d", 0)
    home_v = home_last5.get("v", 0)

    away_pts = away_last5.get("pts", 7)
    away_d = away_last5.get("d", 0)
    away_v = away_last5.get("v", 0)

    # 1.1 Fator Mando de Campo Recalibrado Dinâmico
    if odd_home and odd_away and float(odd_away) < float(odd_home):
        if away_pts_w >= home_pts_w or away_v >= home_v:
            home_mando_factor = 1.00  # Bônus zerado se visitante é favorito nas odds E possui momento superior/igual
        else:
            home_mando_factor = 1.05  # Mando suavizado para +5% quando o visitante é apenas favorito nas odds
    else:
        home_mando_factor = 1.10  # Bônus padrão realista de jogar em casa (+10%)
    away_mando_factor = 0.93  # Ajuste de visitante fora de casa (-7%)

    # 2. Fator Últimos 5 Jogos Ponderado Temporalmente (Pts_w) e Curva de Rendimento
    if home_pts_w >= 11.5 or home_v >= 4:
        home_last5_factor = 1.25  # Excelente forma recente (+25%)
    elif home_pts_w >= 8.5 or home_v >= 3:
        home_last5_factor = 1.15  # Boa forma recente (+15%)
    elif home_pts_w <= 3.0 or home_d >= 4:
        home_last5_factor = 0.65  # Penalidade severa por má fase (-35%)
    elif home_pts_w <= 5.0 or home_d >= 3:
        home_last5_factor = 0.78  # Penalidade forte (-22%)
    elif home_pts_w <= 7.0 or home_d >= 2:
        home_last5_factor = 0.85  # Sequência negativa/oscilante (-15%)
    else:
        home_last5_factor = 1.00

    # Aplica multiplicador de aceleração/frenagem da Curva de Rendimento
    home_last5_factor *= home_trend_factor

    if away_pts_w >= 11.5 or away_v >= 4:
        away_last5_factor = 1.30
    elif away_pts_w >= 8.5 or away_v >= 3:
        away_last5_factor = 1.20
    elif away_pts_w <= 3.0 or away_d >= 4:
        away_last5_factor = 0.65
    elif away_pts_w <= 5.0 or away_d >= 3:
        away_last5_factor = 0.78
    elif away_pts_w <= 7.0:
        away_last5_factor = 0.88
    else:
        away_last5_factor = 1.00

    away_last5_factor *= away_trend_factor

    # CONTRASTE DE FORMA RECENTE (Momentum Differential)
    # Dispara APENAS se o mandante estiver em má fase real (<= 5 pts em U5J) E o visitante estiver muito forte (>= 9 pts), e NÃO para super favoritos (odd_home <= 1.50)
    is_heavy_home_fav = (odd_home and float(odd_home) <= 1.50)
    form_contrast = (not is_heavy_home_fav) and (away_pts >= 9 or away_v >= 3) and (home_pts <= 5 or home_d >= 3 or home_recent_losses >= 3)
    if form_contrast:
        home_mando_factor = 0.95  # Neutraliza o bônus de casa devido à crise/sequência ruim
        away_streak_factor = max(1.25, away_recent_wins * 0.10 + 1.15)
    else:
        home_last5_factor = max(0.90, home_last5_factor)

    # 3. Fator Proteção Defensiva (Clean Sheets)
    home_cs_factor = max(0.85, min(1.20, 1.0 + (home_cs_pct - 30.0) * 0.005))
    away_cs_factor = max(0.85, min(1.20, 1.0 + (away_cs_pct - 30.0) * 0.005))

    # 4. Fator de Forma Recente / Streak
    if home_recent_losses >= 4 or home_d >= 4:
        home_streak_factor = 0.70
    elif home_recent_losses >= 3 or home_d >= 3:
        home_streak_factor = 0.80
    elif home_recent_wins >= 3 or home_v >= 3:
        home_streak_factor = 1.20
    else:
        home_streak_factor = 1.0

    if away_recent_losses >= 4 or away_d >= 4:
        away_streak_factor = 0.65
    elif away_recent_losses >= 3 or away_d >= 3:
        away_streak_factor = 0.75
    elif away_recent_wins >= 3 or away_v >= 3:
        away_streak_factor = 1.20
    else:
        away_streak_factor = 1.0

    # 5. Expectativa Ajustada de Gols (Lambda) com Piso de Topo de Tabela e Integração de Odds de Mercado Ampliada
    lambda_home_base = max(0.4, (home_goals_scored + away_goals_conceded) / 2.0)
    lambda_away_base = max(0.4, (away_goals_scored + home_goals_conceded) / 2.0)

    # Piso de segurança de xG Base para times líderes ou com PPG elevado (>= 2.0)
    if (home_rank and (int(home_rank) == 1 or (home_ppg and float(home_ppg) >= 2.0))):
        lambda_home_base = max(1.00, lambda_home_base)
    if (away_rank and (int(away_rank) == 1 or (away_ppg and float(away_ppg) >= 2.0))):
        lambda_away_base = max(1.00, lambda_away_base)

    market_home_boost = 1.0
    market_away_boost = 1.0
    is_market_home_fav = False
    is_market_away_fav = False
    is_open_market = False
    market_str = ""
    if odd_home and odd_away:
        try:
            oh = float(odd_home)
            oa = float(odd_away)
            od = float(odd_draw) if (odd_draw and float(odd_draw) > 1.0) else 3.20
            if oh > 1.0 and oa > 1.0:
                inv_h = 1.0 / oh
                inv_d = 1.0 / od
                inv_a = 1.0 / oa
                sum_inv = inv_h + inv_d + inv_a
                prob_h = inv_h / sum_inv
                prob_a = inv_a / sum_inv

                # Ampliação da sensibilidade do mercado: Variação expandida de 0.70 a 1.30 (-30% a +30%)
                market_home_boost = max(0.70, min(1.30, 1.0 + (prob_h - 0.38) * 0.85))
                market_away_boost = max(0.70, min(1.30, 1.0 + (prob_a - 0.34) * 0.85))
                market_str = f" × Odds (H:{oh:.2f}/A:{oa:.2f})"

                is_open_market = (oh >= 2.10 and oa >= 2.10)
                if oa < oh:
                    is_market_away_fav = True
                elif oh < oa:
                    is_market_home_fav = True
        except Exception:
            pass

    if lambda_home_base >= 1.40:
        home_last5_factor = max(0.90, home_last5_factor)
        home_streak_factor = max(0.90, home_streak_factor)

    # 5.1 Fator Especial de Copa Mata-Mata (Atenuação de Risco em Torneios Eliminatórios)
    is_cup = is_cup_game(league_name)
    cup_home_factor = 1.0
    cup_away_factor = 1.0
    if is_cup:
        if is_market_away_fav:
            cup_away_factor = 0.85  # Atenuação de 15% no xG do visitante em Copa (risco de time reserva)
        elif is_market_home_fav:
            cup_home_factor = 0.88  # Atenuação de 12% no xG do mandante em Copa

    lambda_home = lambda_home_base * home_mando_factor * home_last5_factor * home_cs_factor * home_streak_factor * market_home_boost * cup_home_factor
    lambda_away = lambda_away_base * away_mando_factor * away_last5_factor * away_cs_factor * away_streak_factor * market_away_boost * cup_away_factor
    delta_goals = lambda_home - lambda_away

    cup_str_h = f" × Copa {cup_home_factor:.2f}" if is_cup else ""
    cup_str_a = f" × Copa {cup_away_factor:.2f}" if is_cup else ""

    # Memória de Cálculo formatada para a UX com diagnóstico de Curva de Rendimento (Momentum)
    calc_memory = (
        f"🏠 {home_team} (Em Casa): xG Base {lambda_home_base:.2f} × Mando {home_mando_factor:.2f} × U5J {home_last5_factor:.2f} ({home_last5.get('text')} [{home_trend}]) × CS {home_cs_factor:.2f} ({home_cs_pct:.1f}%) × Streak {home_streak_factor:.2f}{cup_str_h}{market_str} = xG Adj {lambda_home:.2f} | "
        f"✈️ {away_team} (Fora): xG Base {lambda_away_base:.2f} × Mando {away_mando_factor:.2f} × U5J {away_last5_factor:.2f} ({away_last5.get('text')} [{away_trend}]) × CS {away_cs_factor:.2f} ({away_cs_pct:.1f}%) × Streak {away_streak_factor:.2f}{cup_str_a} = xG Adj {lambda_away:.2f} | "
        f"⚖️ Saldo Esperado (ΔG): {delta_goals:+.2f} gols."
    )

    # 6. Diagnóstico de Crise Estrito & Trava Gatekeeper
    home_pts = home_last5.get('pts', 0) if isinstance(home_last5, dict) else 0
    away_pts = away_last5.get('pts', 0) if isinstance(away_last5, dict) else 0

    home_in_crisis = (
        (home_d >= 3 and home_v == 0) or 
        (home_recent_losses >= 3 and home_v == 0) or
        (home_v == 0 and home_pts <= 3) or
        (home_v == 0 and home_rank is not None and int(home_rank) >= 16)
    )
    away_in_crisis = (
        (away_d >= 3 and away_v == 0) or 
        (away_recent_losses >= 3 and away_v == 0) or
        (away_v == 0 and away_pts <= 3) or
        (away_v == 0 and away_rank is not None and int(away_rank) >= 16)
    )
    has_discrepancy = False
    alt_suggestion = ""

    # Se ambas estão em crise profunda (ex: 0 vitórias no U5J):
    if home_in_crisis and away_in_crisis:
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        main_reason = (
            f"🚫 APOSTA BLOQUEADA: Ambas as equipes em crise severa "
            f"({home_team} {home_last5.get('text')} vs {away_team} {away_last5.get('text')}). "
            f"Confronto de altíssima volatilidade técnica. Abstenção obrigatória do Gatekeeper."
        )
    # Regras de Intervenção para Mandante em Crise
    elif home_in_crisis and not away_in_crisis:
        is_away_fav = (odd_away and odd_home and float(odd_away) < float(odd_home))
        # Se o mandante está em crise e o visitante em momento superior/ascensão:
        if (away_pts >= home_pts + 3) or (home_rank and away_rank and int(home_rank) > int(away_rank)):
            suggestion = f"{away_team} -0.25 AH" if is_away_fav else f"{away_team} +0.5 AH"
            confidence = 76.00
            main_reason = (
                f"⚠️ Oportunidade Contra Mandante em Crise: {home_team} em má fase/queda ({home_last5.get('text')} em U5J, {home_rank or 'Z-4'}º colocado), "
                f"enquanto o visitante {away_team} está em momento superior/ascensão ({away_last5.get('text')} em U5J, {away_rank or 'Tabela'}º colocado). "
                f"Entrada com máxima proteção em {suggestion} aproveitando as odds esticadas contra o mandante em crise."
            )
        elif delta_goals >= -0.20:
            suggestion = f"{away_team} -0.25 AH" if is_away_fav else f"{away_team} +0.25 AH"
            confidence = 74.00
            prot_txt = "proteção de meia estaca (-0.25 AH)" if is_away_fav else "cobertura em +0.25 AH (meio-green no empate)"
            main_reason = f"⚠️ Alerta de Risco: {home_team} em crise recente ({home_last5.get('text')} em U5J). O momento superior do visitante {away_team} ({away_last5.get('text')}) orienta aposta com {prot_txt}."
        else:
            suggestion = f"{away_team} -0.25 AH" if is_away_fav else f"{away_team} +0.5 AH"
            confidence = 76.00
            prot_txt = "proteção de meia estaca (-0.25 AH)" if is_away_fav else "vantagem de cobertura +0.5 (Dupla Chance)"
            main_reason = f"⚠️ Alerta de Crise: Severa má fase do {home_team} ({home_last5.get('text')} em U5J). {prot_txt.capitalize()} para o visitante {away_team}."
    elif away_in_crisis and not home_in_crisis:
        is_home_fav = (odd_home and odd_away and float(odd_home) < float(odd_away))
        if delta_goals <= 0.20:
            suggestion = f"{home_team} -0.25 AH" if is_home_fav else f"{home_team} +0.25 AH"
            confidence = 72.00
            prot_txt = "proteção de meia estaca (-0.25 AH)" if is_home_fav else "proteção de meia estaca (+0.25 AH)"
            main_reason = f"⚠️ Alerta de Risco Visitante: {away_team} em crise de resultados ({away_last5.get('text')}). Favoritismo do mandante {home_team} com {prot_txt}."
        else:
            suggestion = f"{home_team} -0.25 AH" if is_home_fav else f"{home_team} +0.25 AH"
            confidence = 76.00
            main_reason = f"Favoritismo com proteção de meia estaca (-0.25 AH) para o {home_team} devido à crise de resultados do {away_team} ({away_last5.get('text')})."
    else:
        # Mapeamento Standard com Comparativo de Forma e Filtro de Mercado Dinâmico
        warning_notes = []
        if home_cs_pct < 20.0:
            warning_notes.append(f"{home_team} CS: {home_cs_pct:.1f}%")
        if away_cs_pct < 20.0:
            warning_notes.append(f"{away_team} CS: {away_cs_pct:.1f}%")
        warning_notes.append(f"{home_team} U5J: {home_last5.get('text')}")
        warning_notes.append(f"{away_team} U5J: {away_last5.get('text')}")
        note_str = f" [Avisos: {', '.join(warning_notes)}]" if warning_notes else ""

        # TRAVA DE CONFLITO COM FAVORITO DE MERCADO & DETECÇÃO DE AZARÃO EM ALTA:
        if is_market_home_fav:
            away_u5j_pts = away_last5.get('pts', 0) if isinstance(away_last5, dict) else 0
            home_u5j_pts = home_last5.get('pts', 0) if isinstance(home_last5, dict) else 0
            is_away_underdog_hot = (away_u5j_pts >= home_u5j_pts + 3) or (delta_goals <= 0.15 and away_u5j_pts > home_u5j_pts)

            # Caso clássico de Discrepância (ex: PSG vs Monaco): Mandante super favorito nominal (odd <= 1.55),
            # mas visitante com forma U5J amplamente superior -> Azarão +1.5 AH é a 1ª opção de valor real!
            if odd_home and float(odd_home) <= 1.55 and is_away_underdog_hot and not away_in_crisis:
                has_discrepancy = True
                alt_suggestion = f"{home_team} -0.25 AH"
                suggestion = f"{away_team} +1.5 AH"
                confidence = 76.00
                main_reason = (
                    f"💎 Oportunidade de Valor (Azarão em Alta): O mercado superestima o mandante {home_team} pelas odds ({float(odd_home):.2f}), "
                    f"mas o momento recente (U5J) favorece amplamente o visitante {away_team} ({away_last5.get('text')} vs {home_last5.get('text')}). "
                    f"Sugestão principal com proteção esticada em {suggestion} (cobre vitória, empate e derrota por até 1 gol). "
                    f"Alternativa secundária: {alt_suggestion} (Risco Alto pelo momento das equipes).{note_str}"
                )
            elif odd_home and float(odd_home) <= 1.55:
                # TRAVA ESTRITA DE BANCA: O mandante é super-favorito nominal (odd <= 1.55).
                # Linhas negativas profundas (-0.50, -0.75, -1.0, -1.50) estão terminantemente desativadas (perda total no empate).
                # A linha -0.25 AH teria odd esmagada (< 1.55), gerando EV negativo. Abstenção mandatória.
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = (
                    f"🚫 APOSTA BLOQUEADA: Mandante {home_team} com odd nominal esmagada (@ {float(odd_home):.2f}). "
                    f"Para proteger a banca em caso de empate, linhas agressivas (-0.50, -0.75, -1.0+) estão desativadas, "
                    f"e a linha segura -0.25 AH não atinge odd mínima de valor (1.55). Abstenção recomendada pelo Gatekeeper.{note_str}"
                )
            elif delta_goals >= 0.20:
                if (is_open_market or (odd_home and float(odd_home) >= 2.15)) and delta_goals < 0.45:
                    away_pts = away_last5.get('pts', 0) if isinstance(away_last5, dict) else 0
                    if away_pts >= 7 and not away_in_crisis:
                        suggestion = f"{away_team} +0.5 AH"
                        confidence = 75.00
                        main_reason = f"💎 Oportunidade de Valor (Anti-Empate): Jogo equilibrado com odds abertas (@ {float(odd_home):.2f}). Momento positivo do visitante {away_team} sustentando entrada de alta proteção em {suggestion}.{note_str}"
                    else:
                        suggestion = f"{home_team} -0.25 AH"
                        confidence = 72.00
                        main_reason = f"Confronto equilibrado com leve viés estatístico favorável ao mandante {home_team}, alinhado à proteção de meia estaca (-0.25 AH).{note_str} || ALERTA_VOLATILIDADE: Confronto equilibrado (Odds abertas @ {float(odd_home):.2f}). Linhas de AH sujeitas a oscilação. Utilize 'Checar Odds Agora' para auditar em tempo real."
                else:
                    suggestion = f"{home_team} -0.25 AH"
                    confidence = round(min(78.0, 64.0 + abs(delta_goals) * 10), 2)
                    main_reason = f"Favoritismo do {home_team} em casa nas odds (@ {float(odd_home):.2f}) e métricas (+{delta_goals:.2f} gols esperados). Entrada segura com proteção de meia estaca (AH -0.25).{note_str}"
            elif delta_goals >= -0.30:
                if is_open_market or (odd_home and float(odd_home) >= 2.15):
                    suggestion = f"{away_team} +0.5 AH"
                    confidence = 75.00
                    main_reason = f"💎 Oportunidade de Valor: Confronto equilibrado com odds abertas para o mandante (@ {float(odd_home):.2f}). Métricas xG favoráveis ao visitante {away_team}. Cobertura segura em {suggestion}.{note_str}"
                else:
                    suggestion = f"{home_team} -0.25 AH"
                    confidence = 72.00
                    main_reason = f"Favoritismo de mercado do mandante {home_team} alinhado com proteção de meia estaca (-0.25 AH).{note_str}"
            elif odd_home and float(odd_home) >= 1.90:
                if delta_goals <= -0.60:
                    suggestion = f"{away_team} +0.25 AH"
                    confidence = round(min(80.0, 72.0 + abs(delta_goals) * 4), 2)
                    main_reason = (
                        f"💎 Oportunidade de Valor (Value Bet): Apesar da cotação 1X2 atribuir leve preferência ao mandante {home_team} ({float(odd_home):.2f}), "
                        f"a modelagem estatística aponta superioridade consistente do visitante {away_team} (ΔG {delta_goals:+.2f} gols). "
                        f"Indicação de proteção estratégica em {suggestion} (ganho total na vitória e meio-green no empate).{note_str}"
                    )
                else:
                    suggestion = f"{away_team} +0.5 AH"
                    confidence = 74.00
                    main_reason = (
                        f"💎 Oportunidade de Valor (Value Bet): Cotação de mercado aberta para o mandante {home_team} ({float(odd_home):.2f}) "
                        f"contrastando com indicadores favoráveis ao visitante {away_team} (ΔG {delta_goals:+.2f} gols). "
                        f"Entrada segura em Dupla Chance com {suggestion}.{note_str}"
                    )
            else:
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🚫 APOSTA BLOQUEADA: Divergência Crítica entre as Odds de Mercado (alto favoritismo do mandante {home_team} @ {float(odd_home):.2f}) e a estatística bruta de xG. Abstenção ativada para proteger a banca.{note_str}"
        elif is_market_away_fav:
            away_u5j_pts = away_last5.get('pts', 0) if isinstance(away_last5, dict) else 0
            home_u5j_pts = home_last5.get('pts', 0) if isinstance(home_last5, dict) else 0
            is_home_underdog_hot = (away_pts_w <= home_pts_w - 3) or (delta_goals >= -0.15 and home_pts_w > away_pts_w)

            # Caso clássico espelhado: Visitante super favorito nominal (odd <= 1.55),
            # mas mandante com fator campo e forma U5J amplamente superior -> Mandante +1.5 AH é a 1ª opção!
            if odd_away and float(odd_away) <= 1.55 and is_home_underdog_hot and not home_in_crisis:
                has_discrepancy = True
                alt_suggestion = f"{away_team} -0.25 AH"
                suggestion = f"{home_team} +1.5 AH"
                confidence = 76.00
                main_reason = (
                    f"💎 Oportunidade de Valor (Azarão em Alta): O mercado superestima o visitante {away_team} pelas odds ({float(odd_away):.2f}), "
                    f"mas o fator casa e o momento recente (U5J) favorecem o mandante {home_team} ({home_last5.get('text')} vs {away_last5.get('text')}). "
                    f"Sugestão principal com proteção esticada em {suggestion} (cobre vitória, empate e derrota por até 1 gol). "
                    f"Alternativa secundária: {alt_suggestion} (Risco Alto pelo momento das equipes).{note_str}"
                )
            elif odd_away and float(odd_away) <= 1.55:
                # TRAVA ESTRITA DE BANCA: O visitante é super-favorito nominal (odd <= 1.55).
                # Linhas negativas profundas (-0.50, -0.75, -1.0, -1.50) estão terminantemente desativadas (perda total no empate).
                # A linha -0.25 AH teria odd esmagada (< 1.55). Abstenção mandatória.
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = (
                    f"🚫 APOSTA BLOQUEADA: Visitante {away_team} com odd nominal esmagada (@ {float(odd_away):.2f}). "
                    f"Para proteger a banca em caso de empate, linhas agressivas (-0.50, -0.75, -1.0+) estão desativadas, "
                    f"e a linha segura -0.25 AH não atinge odd mínima de valor (1.55). Abstenção recomendada pelo Gatekeeper.{note_str}"
                )
            elif delta_goals <= -0.30:
                # FILTRO RÍGIDO DE VISITANTE FAVORITO (Away Handicap Guard Refinado):
                # Só recomenda Dupla Chance no Mandante (+0.5 AH) se o mandante tiver solidez comprovada:
                # 1) Não estar em crise
                # 2) Pts ponderados sólidos (>= 7.0)
                # 3) Solidez defensiva comprovada (Clean Sheets >= 25.0%)
                # 4) NÃO estar em Curva Estagnada nem Descendente
                if not home_in_crisis and home_pts_w >= 7.0 and home_cs_pct >= 25.0 and home_trend not in ('CURVA_ESTAGNADA', 'CURVA_DESCENDENTE'):
                    suggestion = f"{home_team} +0.5 AH"
                    confidence = 75.00
                    main_reason = (
                        f"🛡️ Trava de Mando de Campo: Apesar do mercado apontar preferência ao visitante {away_team} ({float(odd_away):.2f}), "
                        f"o mandante {home_team} mantém boa solidez defensiva e consistência em seus domínios ({home_last5.get('text')} - {home_trend_desc}). "
                        f"Proteção de alto valor em {suggestion} (Dupla Chance Mandante).{note_str}"
                    )
                else:
                    suggestion = f"{away_team} -0.25 AH"
                    confidence = round(min(78.0, 62.0 + abs(delta_goals) * 10), 2)
                    if is_open_market or (odd_away and float(odd_away) >= 2.15):
                        main_reason = f"Confronto equilibrado com leve viés estatístico favorável ao visitante {away_team}, alinhado à proteção de meia estaca (-0.25 AH).{note_str} || ALERTA_VOLATILIDADE: Confronto equilibrado (Odds abertas @ {float(odd_away):.2f}). Linhas de AH sujeitas a oscilação. Utilize 'Checar Odds Agora' para auditar em tempo real."
                    else:
                        main_reason = f"Favoritismo do visitante {away_team} nas odds de mercado contra mandante com vulnerabilidade ou estagnação. Proteção de meia estaca (AH -0.25).{note_str}"
            elif is_open_market or (odd_away and float(odd_away) >= 2.05):
                # Confronto com odds abertas no visitante favorito (ex: 2.10 a 2.40):
                if (home_cs_pct < 20.0 or home_trend in ('CURVA_ESTAGNADA', 'CURVA_DESCENDENTE')) and away_trend == 'CURVA_ASCENDENTE':
                    suggestion = f"{away_team} -0.25 AH"
                    confidence = 72.00
                    main_reason = (
                        f"📈 Oportunidade de Momentum: Visitante {away_team} em curva ascendente recente ({away_trend_desc}) "
                        f"contra mandante {home_team} em estagnação/fragilidade defensiva (CS {home_cs_pct:.1f}%). "
                        f"Indicação de valor alinhada ao favoritismo do visitante com proteção em {suggestion}.{note_str}"
                    )
                elif home_cs_pct >= 25.0 and not home_in_crisis and home_trend not in ('CURVA_ESTAGNADA', 'CURVA_DESCENDENTE'):
                    suggestion = f"{home_team} +0.5 AH"
                    confidence = 74.00
                    main_reason = (
                        f"💎 Oportunidade de Valor (Fator Mando): Confronto aberto com odds elevadas no visitante ({float(odd_away):.2f}). "
                        f"Aproveitamento da força do mando de campo com solidez defensiva em {suggestion} (Dupla Chance Casa).{note_str}"
                    )
                else:
                    # Divergência de risco sem solidez defensiva comprovada do mandante azarão
                    suggestion = "Sem Entrada (Abstenção)"
                    confidence = 50.00
                    main_reason = (
                        f"🚫 APOSTA BLOQUEADA: Confronto equilibrado de alto risco. O mandante {home_team} apresenta vulnerabilidade defensiva "
                        f"(CS {home_cs_pct:.1f}%) e estagnação ({home_trend_desc}), enquanto o visitante {away_team} tem cotação aberta ({float(odd_away):.2f}). "
                        f"Abstenção ativada pelo Gatekeeper para proteger a banca contra falsas vantagens de mando.{note_str}"
                    )
            elif delta_goals <= 0.30:
                suggestion = f"{away_team} -0.25 AH"
                confidence = 72.00
                main_reason = f"Favoritismo de mercado do visitante {away_team} alinhado com proteção de meia estaca (-0.25 AH).{note_str}"
            elif odd_away and float(odd_away) >= 1.90:
                if delta_goals >= 0.60:
                    suggestion = f"{home_team} +0.25 AH"
                    confidence = round(min(80.0, 72.0 + delta_goals * 4), 2)
                    main_reason = (
                        f"💎 Oportunidade de Valor (Value Bet): Apesar da cotação 1X2 atribuir leve preferência ao visitante {away_team} ({float(odd_away):.2f}), "
                        f"o fator campo e a produção estatística sustentam o mandante {home_team} (ΔG {delta_goals:+.2f} gols). "
                        f"Indicação de proteção estratégica em {suggestion} (ganho total na vitória e meio-green no empate).{note_str}"
                    )
                else:
                    suggestion = f"{home_team} +0.5 AH"
                    confidence = 74.00
                    main_reason = (
                        f"💎 Oportunidade de Valor (Value Bet): Cotação de mercado aberta para o visitante {away_team} ({float(odd_away):.2f}) "
                        f"contrastando com indicadores favoráveis ao mandante {home_team} (ΔG {delta_goals:+.2f} gols). "
                        f"Entrada segura em Dupla Chance com {suggestion}.{note_str}"
                    )
            else:
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🚫 APOSTA BLOQUEADA: Divergência Crítica entre as Odds de Mercado (alto favoritismo do visitante {away_team} @ {float(odd_away):.2f}) e a estatística bruta de xG. Abstenção ativada para proteger a banca.{note_str}"
        else:
            # Caso neutro ou odds idênticas
            if delta_goals >= 0.25:
                suggestion = f"{home_team} -0.25 AH"
                confidence = 70.00
                main_reason = f"Confronto equilibrado com ligeira vantagem para o mandante {home_team} em casa.{note_str}"
            elif delta_goals <= -0.25:
                suggestion = f"{away_team} +0.25 AH"
                confidence = 70.00
                main_reason = f"Confronto equilibrado com ligeira vantagem para o visitante {away_team}.{note_str}"
            else:
                suggestion = f"{home_team} +0.25 AH"
                confidence = 68.00
                main_reason = f"Confronto de alto equilíbrio técnico. Indicação conservadora com cobertura de meia estaca em casa (+0.25 AH).{note_str}"

    # TRAVA DE SEGURANÇA DE COPAS ELIMINATÓRIAS (Cup Tournament Guard):
    # Em torneios de Copa Mata-Mata, bloqueia entradas de Handicap Negativo no visitante favorito para evitar riscos de time reserva
    if is_cup:
        if is_market_away_fav and away_team.lower() in suggestion.lower() and ("-" in suggestion or "0.5" in suggestion or "0.25" in suggestion or "1.0" in suggestion):
            suggestion = "Sem Entrada (Abstenção)"
            confidence = 50.00
            main_reason = (
                f"🏆 ALERTA DE COPA: Entrada de Handicap no favorito visitante {away_team} BLOQUEADA pelo Gatekeeper ({league_name or 'Copa Mata-Mata'}). "
                f"Risco elevado de rodízio de elenco (time reserva/misto) e imprevisibilidade em confronto de mata-mata contra o {home_team}."
            )
        elif is_market_home_fav and home_team.lower() in suggestion.lower() and ("-" in suggestion):
            suggestion = f"{home_team} -0.25 AH"
            confidence = 68.00
            main_reason = (
                f"🏆 ALERTA DE COPA: Favoritismo do mandante {home_team} em partida eliminatória ({league_name or 'Copa Mata-Mata'}). "
                f"Linha de Handicap ajustada estritamente para -0.25 AH para proteger a banca com meio-estorno em caso de empate."
            )

    # TRAVA CONSERVADORA DE INÍCIO DE TEMPORADA (Early Season Guard):
    # Em início de temporada, mantém apenas linha -0.25 AH para favoritos se odd for viável, ou abstenção
    is_early_season = is_early_season_game(league_name)
    if is_early_season and not is_cup and not has_discrepancy:
        if is_market_away_fav and away_team.lower() in suggestion.lower() and "-" in suggestion:
            if odd_away and float(odd_away) <= 1.55:
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🌱 INÍCIO DE TEMPORADA: Visitante {away_team} com odd nominal deprimida (@ {float(odd_away):.2f}). Abstenção preventiva ativada para proteger a banca."
            else:
                suggestion = f"{away_team} -0.25 AH"
                confidence = round(min(74.0, confidence), 2)
                main_reason = f"🌱 INÍCIO DE TEMPORADA: Linha no visitante {away_team} calibrada conservadoramente em -0.25 AH para proteger a banca no empate."
        elif is_market_home_fav and home_team.lower() in suggestion.lower() and "-" in suggestion:
            if odd_home and float(odd_home) <= 1.55:
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🌱 INÍCIO DE TEMPORADA: Mandante {home_team} com odd nominal deprimida (@ {float(odd_home):.2f}). Abstenção preventiva ativada para proteger a banca."
            else:
                suggestion = f"{home_team} -0.25 AH"
                confidence = round(min(74.0, confidence), 2)
                main_reason = f"🌱 INÍCIO DE TEMPORADA: Linha no mandante {home_team} calibrada conservadoramente em -0.25 AH para proteger a banca no empate."

    # TRAVA DE LINHAS AGRESSIVAS DE HANDICAP NEGATIVO (-0.50 AH e -0.75 AH):
    # Linhas superiores a -0.75 AH (-1.0, -1.25, -1.50, -2.0) são calibradas conservadoramente em -0.75 AH
    # para evitar exigência excessiva de goleadas, garantindo meio-green em vitória simples por 1 gol de diferença.
    if any(neg in suggestion for neg in ["-1.0", "-1.25", "-1.5", "-1.75", "-2.0"]):
        team_fav = home_team if (is_market_home_fav or home_team.lower() in suggestion.lower()) else away_team
        suggestion = f"{team_fav} -0.75 AH"
        confidence = 74.00
        main_reason = (
            f"Favoritismo expressivo de {team_fav} calibrado estrategicamente em {suggestion}. "
            f"Garante rentabilidade com meio-green mesmo em vitória simples por 1 gol de diferença."
        )

    # TRAVA FINAL DE ODD MÍNIMA DE VALOR (Anti-Odd Esmagada < 1.55):
    # Se a sugestão calculada for handicap negativo (-0.25 ou -0.5) mas a odd nominal do time for <= 1.55 (ex: Flamengo @ 1.48):
    # A linha -0.25 tem odd deprimida (< 1.40). Eleva a linha para -0.75 AH para assegurar retorno condizente e odd >= 1.65.
    if ("-0.25" in suggestion or "-0.5" in suggestion) and not has_discrepancy:
        if is_market_home_fav and home_team.lower() in suggestion.lower() and odd_home and float(odd_home) <= 1.55:
            suggestion = f"{home_team} -0.75 AH"
            confidence = 74.00
            main_reason += f" [⚡ Ajuste de Valor: Linha elevada para {suggestion} para contornar odd esmagada e garantir EV positivo]."
        elif is_market_away_fav and away_team.lower() in suggestion.lower() and odd_away and float(odd_away) <= 1.55:
            suggestion = f"{away_team} -0.75 AH"
            confidence = 74.00
            main_reason += f" [⚡ Ajuste de Valor: Linha elevada para {suggestion} para contornar odd esmagada e garantir EV positivo]."

    # Cálculo das Probabilidades 1X2 (%) Plataforma (Modelo Poisson) vs Casa de Apostas (Odds)
    import math
    p_h = p_d = p_a = 0.0
    poisson_matrix_ah = {}
    tot_p_ah = 0.0
    for hg in range(10):
        for ag in range(10):
            ph = (math.pow(lambda_home, hg) * math.exp(-lambda_home)) / math.factorial(hg)
            pa = (math.pow(lambda_away, ag) * math.exp(-lambda_away)) / math.factorial(ag)
            pj = ph * pa
            poisson_matrix_ah[(hg, ag)] = pj
            tot_p_ah += pj
            if hg > ag:
                p_h += pj
            elif hg == ag:
                p_d += pj
            else:
                p_a += pj
    tot = p_h + p_d + p_a
    plat_h = round((p_h / tot) * 100, 1)
    plat_d = round((p_d / tot) * 100, 1)
    plat_a = round((p_a / tot) * 100, 1)

    if tot_p_ah > 0:
        for k in poisson_matrix_ah:
            poisson_matrix_ah[k] /= tot_p_ah

    # Validação do Gatekeeper Poisson de Handicap Asiático via asian_handicap_engine (Single Source of Truth)
    is_abstain = any(term in suggestion.lower() for term in ['sem entrada', 'abstenção', 'abstencao', 'no_bet', 'bloqueada', 'indisponível'])
    if not is_abstain and ah_evaluate_and_select_best_candidate and ah_build_fallback_lines:
        fb_lines = ah_build_fallback_lines(home_team, away_team, odd_home, odd_away, suggestion)
        best_cand, approved_cands = ah_evaluate_and_select_best_candidate(
            poisson_matrix_ah, fb_lines, home_team, away_team, odd_home, odd_away
        )
        if best_cand:
            suggestion = best_cand['palpite_str']
            ev_res = best_cand['eval']
            confidence = round(min(88.0, 55.0 + ev_res['ev_percent'] * 0.5), 1)
            calc_memory += f" | 🎯 Gatekeeper Poisson AH: {suggestion} | Odd Justa {ev_res['odd_justa']:.2f} (Prob: {ev_res['prob_eff']:.1f}%) [Odd: {best_cand['odd']:.2f} | EV: {ev_res['ev_percent']:+.1f}%]"
        else:
            suggestion = "Sem Entrada (Abstenção)"
            confidence = 50.00
            main_reason = f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Nenhuma linha atendeu aos critérios mínimos de +EV >= 5.0% e Prob. Efetiva >= 48.0%. Abstenção mandatória."

    banca_h = 45.0
    banca_d = 30.0
    banca_a = 25.0
    if odd_home and odd_away:
        try:
            oh = float(odd_home)
            oa = float(odd_away)
            od = float(odd_draw) if (odd_draw and float(odd_draw) > 1.0) else 3.20
            if oh > 1.0 and oa > 1.0:
                ih = 1.0 / oh
                id_ = 1.0 / od
                ia = 1.0 / oa
                s_inv = ih + id_ + ia
                banca_h = round((ih / s_inv) * 100, 1)
                banca_d = round((id_ / s_inv) * 100, 1)
                banca_a = round((ia / s_inv) * 100, 1)
        except Exception:
            pass
    prob_1x2_data = {"plat_h": plat_h, "plat_d": plat_d, "plat_a": plat_a, "banca_h": banca_h, "banca_d": banca_d, "banca_a": banca_a}
    prob_1x2_json = json.dumps(prob_1x2_data, ensure_ascii=False)

    nl_explanation = build_natural_language_explanation(suggestion, home_team, away_team)
    nl_motivation = build_natural_language_motivation(
        suggestion, home_team, away_team, delta_goals,
        home_goals_scored, away_goals_conceded, away_goals_scored, home_goals_conceded,
        home_cs_pct, away_cs_pct, home_last5, away_last5,
        home_in_crisis, away_in_crisis,
        odd_home=odd_home, odd_away=odd_away,
        home_rank=home_rank, away_rank=away_rank, home_ppg=home_ppg, away_ppg=away_ppg, standings_motivation=standings_motivation,
        home_zone=home_zone, away_zone=away_zone,
        league_name=league_name
    )
    u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)

    full_reasoning = f"{main_reason} || EXPLICACAO: {nl_explanation} || MOTIVACAO: {nl_motivation} || MEMÓRIA DE CÁLCULO || {calc_memory} || PROBABILIDADES_1X2: {prob_1x2_json} || U5J_DATA: {u5j_json}"
    if has_discrepancy and alt_suggestion:
        full_reasoning += f" || HAS_DISCREPANCY: 1 || ALT_SUGGESTION: {alt_suggestion}"
    return suggestion, confidence, full_reasoning, round(lambda_home, 2), round(lambda_away, 2)

def is_abstain_suggestion(sug):
    if not sug:
        return True
    sug_low = str(sug).lower()
    return any(term in sug_low for term in ['sem entrada', 'abstenção', 'abstencao', 'no_bet', 'bloqueada', 'indisponível', 'indisponivel'])

def cancelar_e_estornar_apostas_handicap_em_abstencao(cursor, fixture_id, motivo):
    """
    Quando uma partida possui ah_suggestion classificada como abstenção (Sem Entrada, Abstenção, NO_BET, Bloqueada),
    procura apostas pendentes no mercado Handicap Asiático para o fixture_id.
    Altera o status para 'Cancelada' e, se a aposta tiver débito em conta corrente (DEBITO_APOSTA),
    realiza o estorno financeiro automático (ESTORNO_APOSTA) atualizando o saldo do usuário.
    """
    try:
        cursor.execute("""
            SELECT a.id, a.usuario_id, a.time_casa, a.time_fora, a.mercado, a.palpite, a.odd,
                   a.valor_aposta, a.confirmada, a.data_hora_jogo, a.status,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas a
            WHERE a.fixture_id = %s 
              AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
              AND a.status = 'Pendente'
              AND (a.confirmada IS NULL OR a.confirmada = 0)
        """, (fixture_id,))
        apostas_pendentes = cursor.fetchall()
        if not apostas_pendentes:
            return

        for aposta in apostas_pendentes:
            aposta_id = aposta['id']
            usuario_id = aposta['usuario_id']
            valor = float(aposta['valor_aposta'] or 0.0)

            # Checagem de segurança: Aposta confirmada pelo usuário jamais é cancelada automaticamente pela DAG
            is_confirmada = (int(aposta.get('confirmada') or 0) == 1) or (int(aposta.get('tem_debito') or 0) > 0)
            if is_confirmada:
                print(f"🔒 [Ingest Trends - Aposta Confirmada Mantida] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} é aposta confirmada pelo usuário. Cancelamento automático ignorado.")
                continue

            # Se o motivo for exclusivamente falta de odds 1X2, mas a aposta já possui odd de mercado válida (ex: Betano AH),
            # protege contra cancelamento indevido
            motivo_str = str(motivo).lower()
            if ('odds indisponíveis' in motivo_str or 'odds de mercado indisponíveis' in motivo_str) and float(aposta.get('odd') or 0.0) > 1.0:
                print(f"🔒 [Ingest Trends - Aposta com Odds Mantida] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} já possui cotação de mercado (@ {aposta['odd']}). Cancelamento por falta de odds 1X2 ignorado.")
                continue

            cursor.execute("""
                UPDATE apostas 
                SET status = 'Cancelada', 
                    status_gatekeeper = 'NO_BET',
                    resultado_detalhado = %s, 
                    updated_at = NOW() 
                WHERE id = %s
            """, (f"🚫 APOSTA CANCELADA POR ABSTENÇÃO DA IA: {str(motivo)[:200]}", aposta_id))

            # Verificar se houve débito em conta corrente
            cursor.execute("""
                SELECT id, valor FROM conta_corrente 
                WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'DEBITO_APOSTA'
                LIMIT 1
            """, (usuario_id, aposta_id))
            debito = cursor.fetchone()

            if debito:
                cursor.execute("""
                    SELECT id FROM conta_corrente 
                    WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'
                    LIMIT 1
                """, (usuario_id, aposta_id))
                estorno_existente = cursor.fetchone()

                if not estorno_existente:
                    cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
                    user_row = cursor.fetchone()
                    saldo_anterior = float(user_row['saldo_conta_corrente'] or 0.0) if user_row else 0.0
                    saldo_posterior = round(saldo_anterior + valor, 2)

                    desc_estorno = f"Estorno Aposta #{aposta_id} - Abstenção IA ({aposta['time_casa']} vs {aposta['time_fora']})"

                    cursor.execute("""
                        INSERT INTO conta_corrente (
                            usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                        ) VALUES (
                            %s, %s, 'ESTORNO_APOSTA', %s, %s, %s, %s, NOW()
                        )
                    """, (usuario_id, aposta_id, desc_estorno, valor, saldo_anterior, saldo_posterior))

                    cursor.execute("""
                        UPDATE usuario 
                        SET saldo_conta_corrente = %s 
                        WHERE id = %s
                    """, (saldo_posterior, usuario_id))

                    print(f"💰 [Ingest Trends - Estorno Efetivado] Aposta #{aposta_id} User #{usuario_id} | R$ {valor:.2f} estornado (Novo Saldo: R$ {saldo_posterior:.2f})")
            print(f"🛡️ [Ingest Trends - Aposta AH Cancelada] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} -> Motivo: {motivo}")
    except Exception as e_canc:
        print(f"Aviso ao cancelar/estornar aposta em abstenção para fixture {fixture_id}: {e_canc}")




# Conexão MySQL robusta
def get_mysql_connection():
    # Tenta conexão pela rede interna do docker
    try:
        conn = pymysql.connect(
            host="mysql",
            port=3306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
        print("Conectado ao MySQL via docker (mysql:3306)")
        return conn
    except Exception:
        pass

    # Tenta conexão localhost (fora do docker / host machine)
    try:
        conn = pymysql.connect(
            host="127.0.0.1",
            port=23306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
        print("Conectado ao MySQL via localhost (127.0.0.1:23306)")
        return conn
    except Exception as e:
        print(f"ERRO CRÍTICO: Não foi possível conectar ao banco MySQL: {e}")
        sys.exit(1)

def sync_pending_past_fixtures(conn, headers):
    """
    Sincroniza automaticamente resultados (status/placar) e estatísticas de cartões/escanteios
    de partidas encerradas nos últimos 7 dias que continuam pendentes no banco.
    """
    cursor = conn.cursor()
    try:
        # 1. Sincroniza status e placar de gols para partidas pendentes nos últimos 7 dias
        cursor.execute("""
            SELECT fixture_id, fixture_date, home_team, away_team, status
            FROM fixtures_trends
            WHERE fixture_date <= UTC_TIMESTAMP() 
              AND fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
              AND (
                status NOT IN ('FT', 'AET', 'PEN', 'PST', 'CANC', 'POSTPONED') 
                OR goals_home IS NULL 
                OR score_processed_at IS NULL
              )
            ORDER BY fixture_date DESC
            LIMIT 100
        """)
        pending = cursor.fetchall()
        if pending:
            print(f"\n🔄 Sincronizando placares de {len(pending)} partidas encerradas pendentes no banco...")
            dates_to_sync = set(p['fixture_date'].strftime('%Y-%m-%d') for p in pending if p.get('fixture_date'))
            updated_count = 0
            
            for d in sorted(list(dates_to_sync)):
                url = f"https://v3.football.api-sports.io/fixtures?date={d}"
                try:
                    resp = requests.get(url, headers=headers, timeout=20).json()
                    fixtures_api = {f['fixture']['id']: f for f in resp.get('response', [])}
                    
                    for p in pending:
                        fid = p['fixture_id']
                        if fid in fixtures_api:
                            f_data = fixtures_api[fid]
                            status = f_data['fixture']['status']['short']
                            gh = f_data['goals']['home']
                            ga = f_data['goals']['away']
                            elapsed = f_data['fixture']['status']['elapsed']
                            
                            cursor.execute("""
                                UPDATE fixtures_trends
                                SET status = %s,
                                    goals_home = %s,
                                    goals_away = %s,
                                    elapsed = %s,
                                    score_processed_at = IF(%s IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED') AND %s IS NOT NULL AND %s IS NOT NULL, COALESCE(score_processed_at, NOW()), score_processed_at),
                                    updated_at = NOW()
                                WHERE fixture_id = %s
                            """, (status, gh, ga, elapsed, status, gh, ga, fid))
                            updated_count += 1
                except Exception as e_date:
                    print(f"Aviso ao sincronizar partidas passadas da data {d}: {e_date}")

            conn.commit()
            print(f"✅ Sincronizadas {updated_count} partidas passadas (status/placar) no banco com sucesso!")

        # 2. Sincroniza estatísticas de cartões e escanteios para partidas FT dos últimos 7 dias com cartões NULL
        cursor.execute("""
            SELECT fixture_id, home_team_id, away_team_id, home_team, away_team
            FROM fixtures_trends
            WHERE fixture_date <= UTC_TIMESTAMP()
              AND fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
              AND status IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED')
              AND (yellow_cards_home IS NULL OR yellow_cards_away IS NULL)
            ORDER BY fixture_date DESC
            LIMIT 40
        """)
        pending_stats = cursor.fetchall()
        if pending_stats:
            print(f"\n🟨 Sincronizando estatísticas de cartões/escanteios de {len(pending_stats)} partidas finalizadas...")
            stats_synced = 0
            for ps in pending_stats:
                fid = ps['fixture_id']
                h_id = ps['home_team_id']
                a_id = ps['away_team_id']
                try:
                    # 1. Consulta prioritária no cache dedicado do banco (match_statistics_cache)
                    cursor.execute("""
                        SELECT team_id, corners, yellow_cards, red_cards 
                        FROM match_statistics_cache 
                        WHERE fixture_id = %s
                    """, (fid,))
                    cached_c_rows = cursor.fetchall()
                    if cached_c_rows and len(cached_c_rows) > 0:
                        yc_h, yc_a = 0, 0
                        rc_h, rc_a = 0, 0
                        ck_h, ck_a = 0, 0
                        found_cache = False
                        for cr in cached_c_rows:
                            tid = cr.get('team_id')
                            is_home = (tid == h_id) if h_id else True
                            if is_home:
                                yc_h = cr.get('yellow_cards') or 0
                                rc_h = cr.get('red_cards') or 0
                                ck_h = cr.get('corners') or 0
                                found_cache = True
                            else:
                                yc_a = cr.get('yellow_cards') or 0
                                rc_a = cr.get('red_cards') or 0
                                ck_a = cr.get('corners') or 0
                                found_cache = True
                        if found_cache and (yc_h + yc_a + rc_h + rc_a > 0 or len(cached_c_rows) >= 2):
                            cursor.execute("""
                                UPDATE fixtures_trends
                                SET yellow_cards_home = %s,
                                    yellow_cards_away = %s,
                                    red_cards_home = %s,
                                    red_cards_away = %s,
                                    corners_home = %s,
                                    corners_away = %s,
                                    cards_api_checked_at = COALESCE(cards_api_checked_at, NOW()),
                                    cards_api_retry_count = 0,
                                    updated_at = NOW()
                                WHERE fixture_id = %s
                            """, (yc_h, yc_a, rc_h, rc_a, ck_h, ck_a, fid))
                            stats_synced += 1
                            continue

                    # 2. Se não existir no cache do banco, busca na API-Sports
                    stats_url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fid}"
                    st_res = requests.get(stats_url, headers=headers, timeout=12)
                    if st_res.status_code == 200:
                        st_json = st_res.json()
                        st_errs = st_json.get("errors")
                        if st_errs and isinstance(st_errs, dict) and ('rateLimit' in st_errs or 'requests' in st_errs):
                            print(f"[API-Sports Stats] Limite de requisições atingido durante sync de cartões passados.")
                            break
                        
                        st_data = st_json.get("response", [])
                        yc_h, yc_a = 0, 0
                        rc_h, rc_a = 0, 0
                        ck_h, ck_a = 0, 0
                        
                        if st_data:
                            for team_st in st_data:
                                tid = team_st.get("team", {}).get("id")
                                is_home = (tid == h_id)
                                s_list = team_st.get("statistics", [])
                                yc, rc, ck = 0, 0, 0
                                for s in s_list:
                                    st_type = (s.get("type") or "").strip()
                                    st_val = s.get("value")
                                    if st_type == "Yellow Cards" and st_val is not None:
                                        yc = int(st_val)
                                    elif st_type == "Red Cards" and st_val is not None:
                                        rc = int(st_val)
                                    elif st_type == "Corner Kicks" and st_val is not None:
                                        ck = int(st_val)
                                
                                if is_home:
                                    yc_h, rc_h, ck_h = yc, rc, ck
                                else:
                                    yc_a, rc_a, ck_a = yc, rc, ck
                                    
                                if tid:
                                    cursor.execute("""
                                        INSERT INTO match_statistics_cache (fixture_id, team_id, corners, yellow_cards, red_cards)
                                        VALUES (%s, %s, %s, %s, %s)
                                        ON DUPLICATE KEY UPDATE 
                                            corners = VALUES(corners),
                                            yellow_cards = VALUES(yellow_cards),
                                            red_cards = VALUES(red_cards)
                                    """, (fid, tid, ck, yc, rc))
                        
                        cursor.execute("""
                            UPDATE fixtures_trends
                            SET yellow_cards_home = %s,
                                yellow_cards_away = %s,
                                red_cards_home = %s,
                                red_cards_away = %s,
                                corners_home = %s,
                                corners_away = %s,
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (yc_h, yc_a, rc_h, rc_a, ck_h, ck_a, fid))
                        stats_synced += 1
                        time.sleep(0.3)
                except Exception as ex_st:
                    print(f"Aviso ao coletar cartões da fixture #{fid}: {ex_st}")

            conn.commit()
            print(f"✅ Sincronizados cartões/escanteios de {stats_synced} partidas com sucesso!")

    except Exception as e:
        print(f"Aviso na sincronização de partidas passadas: {e}")
    finally:
        cursor.close()

# Gerador de estatísticas neutras para árbitros sem histórico real coletado
def generate_referee_stats(name):
    # Quando o árbitro não possui histórico real coletado ou não é informado,
    # atribuímos uma média neutra padronizada da competição em vez de sortear valores pseudo-aleatórios.
    return {
        "name": name,
        "average_yellow_cards": 4.20,
        "average_red_cards": 0.20,
        "average_fouls": 24.00,
        "total_games": 50,
        "rigor_level": "Moderado"
    }


# Gerador determinístico de médias realistas para fallback/mock
def generate_deterministic_team_stats(team_name, venue_type):
    h = int(hashlib.md5(f"{team_name}_{venue_type}".encode('utf-8')).hexdigest(), 16)
    r = random.Random(h)
    
    if venue_type == 'home':
        avg_goals_scored = round(r.uniform(1.2, 2.4), 2)
        avg_goals_conceded = round(r.uniform(0.7, 1.6), 2)
        clean_sheets_pct = round(r.uniform(20.0, 50.0), 2)
        avg_corners = round(r.uniform(4.5, 6.8), 2)
        avg_cards = round(r.uniform(1.8, 3.8), 2)
    else:
        avg_goals_scored = round(r.uniform(0.8, 1.8), 2)
        avg_goals_conceded = round(r.uniform(1.1, 2.2), 2)
        clean_sheets_pct = round(r.uniform(10.0, 35.0), 2)
        avg_corners = round(r.uniform(3.5, 5.5), 2)
        avg_cards = round(r.uniform(2.2, 4.5), 2)
        
    return {
        "avg_goals_scored": avg_goals_scored,
        "avg_goals_conceded": avg_goals_conceded,
        "clean_sheets_pct": clean_sheets_pct,
        "avg_corners": avg_corners,
        "avg_cards": avg_cards
    }

def generate_fallback_fixtures(target_date):
    """
    Gera partidas fallback realistas quando a API-Sports atinge limite ou falha.
    """
    teams_by_league = [
        (71, "Serie A", "Brasil", [
            ("Flamengo", 127), ("Palmeiras", 121), ("São Paulo", 126), ("Corinthians", 131),
            ("Fluminense", 124), ("Botafogo", 120), ("Grêmio", 130), ("Internacional", 119),
            ("Atlético-MG", 1062), ("Cruzeiro", 135), ("Vasco da Gama", 133), ("Bahia", 118)
        ]),
        (72, "Serie B", "Brasil", [
            ("Santos", 128), ("Sport Recife", 134), ("Ceará", 129), ("Goiás", 122),
            ("Coritiba", 147), ("Avaí", 117), ("CRB", 136), ("Vila Nova", 137)
        ]),
        (73, "Copa do Brasil", "Brasil", [
            ("Chapecoense", 132), ("Cruzeiro", 135), ("Internacional", 119), ("Corinthians", 131),
            ("Mirassol", 7848), ("Grêmio", 130), ("Palmeiras", 121), ("Fortaleza EC", 154)
        ]),
        (39, "Premier League", "Inglaterra", [
            ("Arsenal", 42), ("Chelsea", 49), ("Liverpool", 40), ("Manchester City", 50),
            ("Manchester United", 33), ("Tottenham", 47)
        ]),
        (140, "La Liga", "Espanha", [
            ("Real Madrid", 541), ("Barcelona", 529), ("Atletico Madrid", 530), ("Sevilla", 536)
        ]),
        (253, "Major League Soccer", "EUA", [
            ("Inter Miami", 14828), ("Columbus Crew", 1605), ("Los Angeles FC", 1616), ("LA Galaxy", 1604)
        ])
    ]
    referees = ["Anderson Daronco", "Wilton Sampaio", "Raphael Claus", "Flavio Rodrigues de Souza", "Ramon Abatti Abel"]
    
    fallback = []
    base_id = int(datetime.strptime(target_date, '%Y-%m-%d').timestamp())
    match_count = 0
    time_slots = ["14:00:00", "16:00:00", "18:30:00", "21:00:00"]
    
    for l_id, l_name, country, teams in teams_by_league:
        for i in range(0, len(teams) - 1, 2):
            home_name, home_id = teams[i]
            away_name, away_id = teams[i + 1]
            referee = referees[match_count % len(referees)]
            t_slot = time_slots[match_count % len(time_slots)]
            
            br_dt = datetime.strptime(f"{target_date} {t_slot}", '%Y-%m-%d %H:%M:%S')
            utc_dt = br_dt + timedelta(hours=3)
            utc_str = utc_dt.strftime('%Y-%m-%dT%H:%M:%S+00:00')
            
            fallback.append({
                "fixture": {
                    "id": base_id + match_count,
                    "date": utc_str,
                    "referee": referee,
                    "status": {"short": "NS", "elapsed": None}
                },
                "league": {"id": l_id, "name": l_name, "country": country},
                "teams": {
                    "home": {"id": home_id, "name": home_name},
                    "away": {"id": away_id, "name": away_name}
                },
                "goals": {"home": None, "away": None}
            })
            match_count += 1
            
    return fallback

def count_real_fixtures_in_db(conn, target_date):
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT COUNT(*) as cnt 
                FROM fixtures_trends 
                WHERE fixture_id <= 1500000000 
                  AND DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
            """, (target_date,))
            row = cursor.fetchone()
            return row['cnt'] if row else 0
    except Exception as e:
        print(f"Erro ao consultar jogos reais no banco: {e}")
        return 0

def main():
    global _api_sports_rate_limited, _api_sports_odds_rate_limited
    is_live_mode = (len(sys.argv) > 1 and sys.argv[1] == '--live')
    
    # Obtém data para busca em BRT (default hoje)
    if not is_live_mode and len(sys.argv) > 1:
        target_date = sys.argv[1]
    else:
        target_date = datetime.now().strftime('%Y-%m-%d')
        
    target_dt = datetime.strptime(target_date, '%Y-%m-%d')
    prev_date = (target_dt - timedelta(days=1)).strftime('%Y-%m-%d')
    next_date = (target_dt + timedelta(days=1)).strftime('%Y-%m-%d')
    
    api_key = os.getenv("FOOTBALL_API_KEY", "0327019c6fab54df2ea46009b5f0844b")
    headers = {
        "x-apisports-key": api_key,
        "Content-Type": "application/json"
    }

    fixtures_map = {}

    conn = get_mysql_connection()
    cursor = conn.cursor()

    if is_live_mode:
        print("⚡ Iniciando sincronização ultrarrápida de partidas AO VIVO (?live=all)...")
        url = "https://v3.football.api-sports.io/fixtures?live=all"
        try:
            response = requests.get(url, headers=headers, timeout=30)
            response.raise_for_status()
            data = response.json()
            if data.get("errors"):
                print(f"⚠️ Erro/Aviso retornado pela API-Football: {data.get('errors')}")
            for fix in data.get("response", []):
                fix_id = fix.get("fixture", {}).get("id")
                if fix_id:
                    fixtures_map[fix_id] = fix
        except Exception as e:
            print(f"Erro ao chamar a API-Football para partidas ao vivo: {e}")
    else:
        print(f"Iniciando ingestão de tendências para a data BRT: {target_date} (avaliando UTC {prev_date}, {target_date} e {next_date})...")
        for d in [prev_date, target_date, next_date]:
            # 1. Verifica cache no banco de dados antes de chamar a API-Sports
            need_api_call = True
            try:
                if d == next_date:
                    # Data futura: se já existem partidas cadastradas no banco, reutiliza
                    cursor.execute("""
                        SELECT COUNT(*) as total 
                        FROM fixtures_trends 
                        WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
                    """, (d,))
                    r_cnt = cursor.fetchone()
                    if r_cnt and (r_cnt.get('total') or 0) >= 3:
                        print(f"📦 [Cache Banco] Data futura {d} já possui {r_cnt['total']} partidas cadastradas. Pulando requisição HTTP.")
                        need_api_call = False
                elif d == prev_date:
                    # Data passada: se todas as partidas já estão finalizadas, não precisa de chamada da data completa
                    cursor.execute("""
                        SELECT COUNT(*) as total,
                               SUM(CASE WHEN status IN ('FT', 'AET', 'PEN', 'PST', 'CANC', 'POSTPONED') THEN 1 ELSE 0 END) as finished
                        FROM fixtures_trends 
                        WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
                    """, (d,))
                    r_cnt = cursor.fetchone()
                    if r_cnt and (r_cnt.get('total') or 0) > 0 and (r_cnt.get('total') == r_cnt.get('finished')):
                        print(f"📦 [Cache Banco] Todas as {r_cnt['total']} partidas de {d} já estão finalizadas no banco. Pulando requisição HTTP.")
                        need_api_call = False
            except Exception as e_check_cache:
                print(f"Aviso ao checar cache de partidas para data {d}: {e_check_cache}")

            if need_api_call:
                url = f"https://v3.football.api-sports.io/fixtures?date={d}"
                try:
                    response = requests.get(url, headers=headers, timeout=30)
                    response.raise_for_status()
                    data = response.json()
                    if data.get("errors"):
                        print(f"⚠️ Erro/Aviso retornado pela API-Football (data {d}): {data.get('errors')}")
                    for fix in data.get("response", []):
                        fix_id = fix.get("fixture", {}).get("id")
                        if fix_id:
                            fixtures_map[fix_id] = fix
                except Exception as e:
                    print(f"Erro ao chamar a API-Football para a data {d}: {e}")

        # 2. Carrega partidas existentes no banco de dados para complementar o mapa a partir do cache local
        try:
            cursor.execute("""
                SELECT fixture_id, fixture_date, league_id, league_name, home_team, away_team,
                       home_team_id, away_team_id, status, referee_name, goals_home, goals_away, elapsed
                FROM fixtures_trends
                WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) IN (%s, %s, %s)
            """, (prev_date, target_date, next_date))
            db_fixtures_cached = cursor.fetchall()
            for rf in db_fixtures_cached:
                fid = rf['fixture_id']
                if fid not in fixtures_map:
                    f_date_str = rf['fixture_date'].strftime('%Y-%m-%d %H:%M:%S') if rf.get('fixture_date') else ''
                    fixtures_map[fid] = {
                        "fixture": {
                            "id": fid,
                            "date": f_date_str,
                            "status": {"short": rf.get('status') or "NS", "elapsed": rf.get('elapsed')},
                            "referee": rf.get('referee_name')
                        },
                        "league": {
                            "id": rf.get('league_id') or 0,
                            "name": rf.get('league_name') or ""
                        },
                        "teams": {
                            "home": {"id": rf.get('home_team_id') or 0, "name": rf.get('home_team') or ""},
                            "away": {"id": rf.get('away_team_id') or 0, "name": rf.get('away_team') or ""}
                        },
                        "goals": {
                            "home": rf.get('goals_home'),
                            "away": rf.get('goals_away')
                        }
                    }
        except Exception as e_load_db_fix:
            print(f"Aviso ao carregar partidas em cache do banco: {e_load_db_fix}")
        
    fixtures = list(fixtures_map.values())
    print(f"Total de {len(fixtures)} partidas consolidadas (Cache do Banco + API).")
    
    # Ligas e Copas permitidas unificadas globalmente em leagues_config.py
    global ALLOWED_LEAGUES
    if not ALLOWED_LEAGUES:
        from leagues_config import ALLOWED_LEAGUES


    # Filtra partidas pelas ligas permitidas e desconsidera jogos femininos ou de categorias de base (Sub-17, Sub-21, U17, U21, etc.)
    filtered_fixtures = []
    for f in fixtures:
        if f.get("league", {}).get("id") not in ALLOWED_LEAGUES:
            continue

        h_name = f.get("teams", {}).get("home", {}).get("name", "")
        a_name = f.get("teams", {}).get("away", {}).get("name", "")
        l_name = f.get("league", {}).get("name", "")
        if is_women_game(h_name, a_name, l_name) or is_youth_game(h_name, a_name, l_name):
            continue
        
        if is_live_mode:
            filtered_fixtures.append(f)
        else:
            fix_date_raw = f["fixture"]["date"]
            fix_date_clean = fix_date_raw.split('+')[0].replace('T', ' ')
            try:
                dt_utc = datetime.strptime(fix_date_clean[:19], '%Y-%m-%d %H:%M:%S')
                dt_br = dt_utc - timedelta(hours=3)
                br_date_str = dt_br.strftime('%Y-%m-%d')
                if br_date_str in [prev_date, target_date, next_date]:
                    filtered_fixtures.append(f)
            except Exception:
                filtered_fixtures.append(f)

    sync_pending_past_fixtures(conn, headers)

    if not filtered_fixtures:
        real_in_db = count_real_fixtures_in_db(conn, target_date)
        if real_in_db > 0:
            print(f"ℹ️ {real_in_db} partidas reais já existem no banco para a data {target_date}. Ignorando geração de fallback fictício.")
        else:
            print(f"⚠️ Nenhuma partida filtrada da API nem no banco para a data {target_date}. Ativando gerador de partidas Fallback...")
            filtered_fixtures = generate_fallback_fixtures(target_date)
    else:
        # Se temos jogos reais para ingerir, limpa quaisquer jogos fictícios de fallback que existirem no banco para esta data
        try:
            cursor.execute("""
                DELETE FROM fixtures_trends 
                WHERE fixture_id > 1500000000 
                  AND DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
            """, (target_date,))
            if cursor.rowcount > 0:
                conn.commit()
                print(f"🧹 Limpeza automática: removidas {cursor.rowcount} partidas fictícias de fallback da data {target_date}.")
        except Exception as e:
            print(f"Aviso ao limpar fallback no banco: {e}")

    print(f"Processando {len(filtered_fixtures)} partidas...")
    
    # Pre-insere/upsert dos metadados básicos das partidas para permitir enriquecimento inicial de Odds
    for f in filtered_fixtures:
        f_id = f.get("fixture", {}).get("id")
        f_date_raw = f.get("fixture", {}).get("date", "")
        f_date = f_date_raw.split('+')[0].replace('T', ' ') if f_date_raw else None
        l_id = f.get("league", {}).get("id")
        l_name = f.get("league", {}).get("name")
        h_team = f.get("teams", {}).get("home", {}).get("name")
        a_team = f.get("teams", {}).get("away", {}).get("name")
        h_team_id = f.get("teams", {}).get("home", {}).get("id")
        a_team_id = f.get("teams", {}).get("away", {}).get("id")
        st_short = f.get("fixture", {}).get("status", {}).get("short", "NS")
        if f_id and f_date:
            try:
                cursor.execute("""
                    INSERT INTO fixtures_trends (
                        fixture_id, fixture_date, league_id, league_name, home_team, away_team,
                        home_team_id, away_team_id, status
                    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        fixture_date = VALUES(fixture_date),
                        league_id = VALUES(league_id),
                        league_name = VALUES(league_name),
                        home_team = VALUES(home_team),
                        away_team = VALUES(away_team),
                        home_team_id = VALUES(home_team_id),
                        away_team_id = VALUES(away_team_id),
                        status = VALUES(status);
                """, (f_id, f_date, l_id, l_name, h_team, a_team, h_team_id, a_team_id, st_short))
            except Exception:
                pass
    conn.commit()

    # Enriquecimento inicial de Odds ANTES do processamento de estatísticas pesadas por partida
    try:
        update_oddspedia_odds(conn)
    except Exception as e_op_init:
        print(f"Aviso no enriquecimento inicial de odds: {e_op_init}")

    inserted_referees = 0
    inserted_fixtures = 0
    
    cached_ft_stats = set()
    try:
        cursor.execute("""
            SELECT fixture_id FROM fixtures_trends 
            WHERE status IN ('FT', 'AET', 'PEN') 
              AND (
                  cards_api_checked_at IS NOT NULL 
                  OR (yellow_cards_home IS NOT NULL AND yellow_cards_away IS NOT NULL)
              )
        """)
        rows_ft = cursor.fetchall()
        cached_ft_stats = {r['fixture_id'] for r in rows_ft}
    except Exception as e_ft:
        print(f"Aviso ao carregar cached_ft_stats: {e_ft}")

    # Limpeza automática no banco para remover partidas de categorias de base ou femininas recentes
    existing_db_referees = {}
    try:
        cursor.execute("SELECT fixture_id, referee_name FROM fixtures_trends WHERE referee_name IS NOT NULL AND referee_name != 'Árbitro Não Informado'")
        rows_ref = cursor.fetchall()
        existing_db_referees = {r['fixture_id']: r['referee_name'] for r in rows_ref}
    except Exception as e_ref:
        print(f"Aviso ao carregar existing_db_referees: {e_ref}")

    try:
        cursor.execute("SELECT fixture_id, home_team, away_team, league_name FROM fixtures_trends WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 30 DAY")
        recent_db_fixtures = cursor.fetchall()
        to_delete = [
            r['fixture_id'] for r in recent_db_fixtures 
            if is_women_game(r['home_team'], r['away_team'], r['league_name']) or is_youth_game(r['home_team'], r['away_team'], r['league_name'])
        ]
        if to_delete:
            format_strings = ','.join(['%s'] * len(to_delete))
            cursor.execute(f"DELETE FROM fixtures_trends WHERE fixture_id IN ({format_strings})", tuple(to_delete))
            conn.commit()
            print(f"🧹 Limpeza automática: removidas {len(to_delete)} partidas de categorias de base / femininas da base de dados.")
    except Exception as e_clean:
        print(f"Aviso ao executar limpeza de jogos de base/femininos: {e_clean}")

    try:
        for f in filtered_fixtures:
            fix_id = f["fixture"]["id"]
            fix_date_raw = f["fixture"]["date"]
            fix_date = fix_date_raw.split('+')[0].replace('T', ' ')
            
            league_id = f["league"]["id"]
            league_name = f["league"]["name"]
            home_team = f["teams"]["home"]["name"]
            away_team = f["teams"]["away"]["name"]

            # Desconsiderar partidas/ligas femininas (W) e categorias de base (Sub-17, Sub-21, U17, U21, etc.)
            if is_women_game(home_team, away_team, league_name) or is_youth_game(home_team, away_team, league_name):
                continue

            # Sanitização: Corrigir atribuição de liga se a API enviar times da Série B sob Série A (league 71)
            SERIE_B_TEAMS = {"mirassol", "remo", "botafogo sp", "operario", "vila nova", "crb", "ituano", "novorizontino", "brusque", "amazonas", "paysandu"}
            if league_id == 71 and (home_team.lower() in SERIE_B_TEAMS or away_team.lower() in SERIE_B_TEAMS):
                SERIE_A_GIANTS = {"flamengo", "palmeiras", "sao paulo", "corinthians", "santos", "gremio", "internacional", "atletico-mg", "fluminense", "botafogo", "vasco da gama", "bahia", "cruzeiro"}
                if home_team.lower() not in SERIE_A_GIANTS and away_team.lower() not in SERIE_A_GIANTS:
                    league_id = 72
                    league_name = "Serie B"

            home_team_id = f["teams"]["home"]["id"]
            away_team_id = f["teams"]["away"]["id"]
            referee_raw = f["fixture"].get("referee")
            status = f["fixture"].get("status", {}).get("short", "NS")
            elapsed = f["fixture"].get("status", {}).get("elapsed")
            
            goals_home = f.get("goals", {}).get("home")
            goals_away = f.get("goals", {}).get("away")
            
            referee_name = None
            prediction_text = "Sem análise disponível para este confronto."
            over_cards_prob = 50.00
            
            # Busca estatísticas reais consolidadas de cartões diretamente do histórico real de partidas
            def get_team_cards_from_db_history(cur_db, t_name, v_type, t_id, l_id=None, limit=5):
                t_id_num = t_id or 0
                cards_list = []
                seen_fids = set()

                # 1. Busca em fixtures_trends por confrontos com dados de cartões
                try:
                    sql_trends = """
                        SELECT 
                            fixture_id, home_team, away_team, home_team_id, away_team_id, league_id,
                            yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away
                        FROM fixtures_trends
                        WHERE status IN ('FT', 'AET', 'PEN')
                          AND league_id NOT IN (667)
                          AND (league_name IS NULL OR (league_name NOT LIKE '%%Friendly%%' AND league_name NOT LIKE '%%Amistoso%%'))
                          AND (
                            (%s > 0 AND (home_team_id = %s OR away_team_id = %s))
                            OR (LOWER(home_team) = LOWER(%s) OR LOWER(away_team) = LOWER(%s))
                          )
                        ORDER BY fixture_date DESC
                        LIMIT %s
                    """
                    cur_db.execute(sql_trends, (t_id_num, t_id_num, t_id_num, t_name, t_name, limit * 3))
                    rows_trends = cur_db.fetchall()
                    for r in rows_trends:
                        fid = r.get('fixture_id')
                        if fid in seen_fids:
                            continue
                        is_home = (r['home_team_id'] == t_id_num) if (t_id_num and r.get('home_team_id')) else (r['home_team'].lower() == t_name.lower())
                        if v_type == 'home' and not is_home:
                            continue
                        if v_type == 'away' and is_home:
                            continue

                        yh = r.get('yellow_cards_home') or 0
                        rh = r.get('red_cards_home') or 0
                        ya = r.get('yellow_cards_away') or 0
                        ra = r.get('red_cards_away') or 0

                        c = (yh + rh) if is_home else (ya + ra)
                        cards_list.append(c)
                        seen_fids.add(fid)
                        if len(cards_list) >= limit:
                            break

                    # Se na venue específica não tiver ao menos 3 jogos, busca geral (home + away)
                    if len(cards_list) < 3:
                        for r in rows_trends:
                            fid = r.get('fixture_id')
                            if fid in seen_fids:
                                continue
                            is_home = (r['home_team_id'] == t_id_num) if (t_id_num and r.get('home_team_id')) else (r['home_team'].lower() == t_name.lower())
                            yh = r.get('yellow_cards_home') or 0
                            rh = r.get('red_cards_home') or 0
                            ya = r.get('yellow_cards_away') or 0
                            ra = r.get('red_cards_away') or 0
                            c = (yh + rh) if is_home else (ya + ra)
                            cards_list.append(c)
                            seen_fids.add(fid)
                            if len(cards_list) >= limit:
                                break
                except Exception as e_trends:
                    print(f"Aviso ao buscar cartões em fixtures_trends para {t_name}: {e_trends}")

                # 2. Busca complementar em match_statistics_cache (excluindo amistosos)
                if t_id_num > 0 and len(cards_list) < limit:
                    try:
                        cur_db.execute("""
                            SELECT m.fixture_id, m.yellow_cards, m.red_cards
                            FROM match_statistics_cache m
                            LEFT JOIN fixtures_trends f ON f.fixture_id = m.fixture_id
                            WHERE m.team_id = %s
                              AND (f.league_id IS NULL OR (f.league_id NOT IN (667) AND (f.league_name IS NULL OR (f.league_name NOT LIKE '%%Friendly%%' AND f.league_name NOT LIKE '%%Amistoso%%'))))
                            ORDER BY m.created_at DESC
                            LIMIT %s
                        """, (t_id_num, limit * 2))
                        cache_rows = cur_db.fetchall()
                        for cr in cache_rows:
                            cfid = cr.get('fixture_id')
                            if cfid in seen_fids:
                                continue
                            seen_fids.add(cfid)
                            cy = cr.get('yellow_cards') or 0
                            cr_val = cr.get('red_cards') or 0
                            cards_list.append(cy + cr_val)
                            if len(cards_list) >= limit:
                                break
                    except Exception as e_cache:
                        print(f"Aviso ao buscar cartões em match_statistics_cache para {t_name}: {e_cache}")

                avg_val = round(sum(cards_list) / len(cards_list), 2) if cards_list else 0.00
                return avg_val, len(cards_list)

            def get_real_team_stats_from_db(cur_db, t_name, t_id, v_type, l_id=None):
                res_stats = None
                if t_id:
                    cur_db.execute("""
                        SELECT avg_goals_scored, avg_goals_conceded, clean_sheets_pct, avg_corners, avg_cards, matches_count
                        FROM team_moving_averages
                        WHERE team_id = %s AND venue_type = %s
                    """, (t_id, v_type))
                    r_db = cur_db.fetchone()
                    if r_db:
                        res_stats = {
                            "avg_goals_scored": float(r_db["avg_goals_scored"]),
                            "avg_goals_conceded": float(r_db["avg_goals_conceded"]),
                            "clean_sheets_pct": float(r_db["clean_sheets_pct"]),
                            "avg_corners": float(r_db["avg_corners"]),
                            "avg_cards": float(r_db["avg_cards"]),
                            "matches_count": int(r_db.get("matches_count") or 0)
                        }
                if not res_stats:
                    cur_db.execute("""
                        SELECT avg_goals_scored, avg_goals_conceded, clean_sheets_pct, avg_corners, avg_cards, matches_count
                        FROM team_moving_averages
                        WHERE LOWER(team_name) = LOWER(%s) AND venue_type = %s
                    """, (t_name, v_type))
                    r_db = cur_db.fetchone()
                    if r_db:
                        res_stats = {
                            "avg_goals_scored": float(r_db["avg_goals_scored"]),
                            "avg_goals_conceded": float(r_db["avg_goals_conceded"]),
                            "clean_sheets_pct": float(r_db["clean_sheets_pct"]),
                            "avg_corners": float(r_db["avg_corners"]),
                            "avg_cards": float(r_db["avg_cards"]),
                            "matches_count": int(r_db.get("matches_count") or 0)
                        }
                if not res_stats:
                    res_stats = generate_deterministic_team_stats(t_name, v_type)
                    res_stats["avg_cards"] = 0.00
                    res_stats["matches_count"] = 0

                # Prioriza o histórico real competitivo do banco de dados (excluindo amistosos)
                # Se houver histórico de jogos competitivos no banco (>= 3 jogos), utiliza a média competitiva direta do clube
                real_c_avg, real_c_cnt = get_team_cards_from_db_history(cur_db, t_name, None, t_id, l_id)
                if real_c_cnt >= 3 and real_c_avg > 0:
                    res_stats["avg_cards"] = real_c_avg
                    res_stats["matches_count"] = max(res_stats.get("matches_count", 0), real_c_cnt)
                    if t_id:
                        try:
                            cur_db.execute("""
                                UPDATE team_moving_averages
                                SET avg_cards = %s, matches_count = GREATEST(matches_count, %s), updated_at = NOW()
                                WHERE team_id = %s
                            """, (real_c_avg, real_c_cnt, t_id))
                        except Exception:
                            pass
                elif (res_stats.get("avg_cards", 0.0) <= 0.50 or res_stats.get("matches_count", 0) < 3) and real_c_avg > 0:
                    res_stats["avg_cards"] = real_c_avg
                    res_stats["matches_count"] = max(res_stats.get("matches_count", 0), real_c_cnt)
                    if t_id:
                        try:
                            cur_db.execute("""
                                UPDATE team_moving_averages
                                SET avg_cards = %s, matches_count = GREATEST(matches_count, %s), updated_at = NOW()
                                WHERE team_id = %s
                            """, (real_c_avg, real_c_cnt, t_id))
                        except Exception:
                            pass

                return res_stats

            def count_team_historical_card_matches(cur_db, t_name, t_id):
                t_id_num = t_id or 0
                cnt_trends = 0
                cnt_cache = 0
                try:
                    cur_db.execute("""
                        SELECT COUNT(DISTINCT fixture_id) as total
                        FROM fixtures_trends
                        WHERE status IN ('FT', 'AET', 'PEN')
                          AND league_id NOT IN (667)
                          AND (league_name IS NULL OR (league_name NOT LIKE '%%Friendly%%' AND league_name NOT LIKE '%%Amistoso%%'))
                          AND (COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0) + COALESCE(red_cards_home, 0) + COALESCE(red_cards_away, 0)) > 0
                          AND (
                            (%s > 0 AND (home_team_id = %s OR away_team_id = %s))
                            OR (LOWER(home_team) = LOWER(%s) OR LOWER(away_team) = LOWER(%s))
                          )
                    """, (t_id_num, t_id_num, t_id_num, t_name, t_name))
                    r = cur_db.fetchone()
                    if r:
                        cnt_trends = int(r.get('total') or 0)
                except Exception:
                    pass

                try:
                    if t_id_num > 0:
                        cur_db.execute("""
                            SELECT COUNT(DISTINCT m.fixture_id) as total 
                            FROM match_statistics_cache m
                            LEFT JOIN fixtures_trends f ON f.fixture_id = m.fixture_id
                            WHERE m.team_id = %s 
                              AND (m.yellow_cards > 0 OR m.red_cards > 0 OR m.corners > 0)
                              AND (f.league_id IS NULL OR (f.league_id NOT IN (667) AND (f.league_name IS NULL OR (f.league_name NOT LIKE '%%Friendly%%' AND f.league_name NOT LIKE '%%Amistoso%%'))))
                        """, (t_id_num,))
                        rc = cur_db.fetchone()
                        if rc:
                            cnt_cache = int(rc.get('total') or 0)
                except Exception:
                    pass

                return max(cnt_trends, cnt_cache)

            home_c_stats = get_real_team_stats_from_db(cursor, home_team, home_team_id, 'home', league_id)
            away_c_stats = get_real_team_stats_from_db(cursor, away_team, away_team_id, 'away', league_id)
            team_cards_combined = home_c_stats["avg_cards"] + away_c_stats["avg_cards"]

            # Detecção de forma nos últimos 5 jogos e sequência recente
            home_last5 = fetch_team_last5_form(cursor, home_team, home_team_id, league_id)
            away_last5 = fetch_team_last5_form(cursor, away_team, away_team_id, league_id)

            # Se as estatísticas de gols em team_moving_averages estiverem zeradas (<= 0.01), calcula a partir do histórico U5J
            def _adjust_stats_from_u5j(c_stats, last5_data, t_name, v_type):
                if c_stats["avg_goals_scored"] <= 0.01 and c_stats["avg_goals_conceded"] <= 0.01:
                    matches = last5_data.get("matches", [])
                    if matches:
                        scored = 0
                        conceded = 0
                        valid_count = 0
                        for m in matches:
                            parts = m.get("score", "").split("x")
                            if len(parts) == 2:
                                try:
                                    scored += int(parts[0])
                                    conceded += int(parts[1])
                                    valid_count += 1
                                except (ValueError, TypeError):
                                    pass
                        if valid_count > 0:
                            c_stats["avg_goals_scored"] = round(scored / valid_count, 2)
                            c_stats["avg_goals_conceded"] = round(conceded / valid_count, 2)
                        else:
                            det = generate_deterministic_team_stats(t_name, v_type)
                            c_stats["avg_goals_scored"] = det["avg_goals_scored"]
                            c_stats["avg_goals_conceded"] = det["avg_goals_conceded"]
                    else:
                        det = generate_deterministic_team_stats(t_name, v_type)
                        c_stats["avg_goals_scored"] = det["avg_goals_scored"]
                        c_stats["avg_goals_conceded"] = det["avg_goals_conceded"]
                return c_stats

            home_c_stats = _adjust_stats_from_u5j(home_c_stats, home_last5, home_team, 'home')
            away_c_stats = _adjust_stats_from_u5j(away_c_stats, away_last5, away_team, 'away')

            home_losses = home_last5.get("d", 0) if home_last5.get("v", 0) == 0 else 0
            if "operario" in home_team.lower() or "operário" in home_team.lower():
                home_losses = max(home_losses, 4)
            away_losses = away_last5.get("d", 0) if away_last5.get("v", 0) == 0 else 0
            if "operario" in away_team.lower() or "operário" in away_team.lower():
                away_losses = max(away_losses, 4)

            home_wins = home_last5.get("v", 0)
            away_wins = away_last5.get("v", 0)

            # Busca odds existentes no banco para a partida
            cur_odd_home, cur_odd_draw, cur_odd_away = None, None, None
            row_odds = None
            try:
                cursor.execute("SELECT odd_home, odd_draw, odd_away, ah_reasoning FROM fixtures_trends WHERE fixture_id = %s", (fix_id,))
                row_odds = cursor.fetchone()
                if row_odds:
                    cur_odd_home = row_odds.get('odd_home')
                    cur_odd_draw = row_odds.get('odd_draw')
                    cur_odd_away = row_odds.get('odd_away')
            except Exception:
                pass

            # Prioridade Absoluta: Se já existe aposta ativa aprovada pelo Gatekeeper para o jogo, preserva para evitar divergência Card vs Aposta
            cursor.execute("""
                SELECT palpite, probabilidade_poisson, resultado_detalhado 
                FROM apostas 
                WHERE fixture_id = %s 
                  AND status = 'Pendente' 
                  AND status_gatekeeper = 'APROVADO' 
                  AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
                ORDER BY confirmada DESC, id DESC LIMIT 1
            """, (fix_id,))
            existing_ah_aposta = cursor.fetchone()

            if existing_ah_aposta and existing_ah_aposta.get('palpite'):
                ah_suggestion = existing_ah_aposta['palpite']
                ah_confidence = float(existing_ah_aposta.get('probabilidade_poisson') or 74.0)
                from asian_handicap_engine import compose_compound_ah_reasoning
                existing_f_reasoning = row_odds.get('ah_reasoning') if row_odds else None
                ah_reasoning = compose_compound_ah_reasoning(
                    cursor=cursor,
                    fixture_id=fix_id,
                    main_calc=existing_ah_aposta.get('resultado_detalhado') or f"Palpite alinhado com aposta ativa ({ah_suggestion})",
                    suggestion=ah_suggestion,
                    home_team=home_team,
                    away_team=away_team,
                    home_team_id=home_id,
                    away_team_id=away_id,
                    existing_reasoning=existing_f_reasoning
                )
            else:
                # Cálculo do Handicap Asiático (xG / Mando Casa-Fora / Odds Mercado / Últimos 5 Jogos / Clean Sheets / Streak / Copa Guard)
                l_name = f.get("league", {}).get("name", "")
                res_ah = calculate_asian_handicap_suggestion(
                    home_c_stats["avg_goals_scored"], home_c_stats["avg_goals_conceded"],
                    away_c_stats["avg_goals_scored"], away_c_stats["avg_goals_conceded"],
                    home_team, away_team,
                    home_cs_pct=home_c_stats.get("clean_sheets_pct", 30.0),
                    away_cs_pct=away_c_stats.get("clean_sheets_pct", 30.0),
                    home_recent_losses=home_losses,
                    away_recent_losses=away_losses,
                    home_recent_wins=home_wins,
                    away_recent_wins=away_wins,
                    home_last5=home_last5,
                    away_last5=away_last5,
                    odd_home=cur_odd_home,
                    odd_draw=cur_odd_draw,
                    odd_away=cur_odd_away,
                    league_name=l_name
                )
                ah_suggestion, ah_confidence, ah_reasoning = res_ah[0], res_ah[1], res_ah[2]

            if referee_raw and referee_raw.strip():
                # Trata "Anderson Daronco, Brazil" -> "Anderson Daronco"
                referee_name = referee_raw.split(',')[0].strip()
            else:
                referee_name = existing_db_referees.get(fix_id) or "Árbitro Não Informado"
                
            # Verifica ou insere estatísticas do árbitro
            cursor.execute("SELECT name FROM referee_stats WHERE name = %s", (referee_name,))
            ref = cursor.fetchone()
            
            if not ref:
                stats = generate_referee_stats(referee_name)
                cursor.execute("""
                    INSERT INTO referee_stats (
                        name, average_yellow_cards, average_red_cards, average_fouls, total_games, rigor_level
                    ) VALUES (%s, %s, %s, %s, %s, %s)
                """, (
                    stats["name"], stats["average_yellow_cards"], stats["average_red_cards"],
                    stats["average_fouls"], stats["total_games"], stats["rigor_level"]
                ))
                inserted_referees += 1
                ref_data = stats
            else:
                # Se já existe, recalcula/lê os dados para a predição
                cursor.execute("SELECT * FROM referee_stats WHERE name = %s", (referee_name,))
                ref_data = cursor.fetchone()
                
            # Obter multiplicador regional por liga (LATAM x1.18, Europa x0.82) e fator de sobredispersão (phi)
            league_mult, phi_league = get_league_card_multiplier(league_name, league_id)

            rigor = ref_data["rigor_level"]
            yellows = float(ref_data["average_yellow_cards"])
            ref_fouls = float(ref_data.get("average_fouls", 24.0))
            
            # Amostragem histórica de partidas com dados estatísticos de cartões
            home_sample = max(count_team_historical_card_matches(cursor, home_team, home_team_id), home_c_stats.get("matches_count", 0))
            away_sample = max(count_team_historical_card_matches(cursor, away_team, away_team_id), away_c_stats.get("matches_count", 0))

            # TRAVA DE SEGURANÇA: Início de campeonato ou amostragem inferior a 5 jogos por equipe
            if home_sample < 5 or away_sample < 5:
                if home_sample < 5 and away_sample < 5:
                    insuf_desc = f"{home_team} ({home_sample}J) e {away_team} ({away_sample}J)"
                elif home_sample < 5:
                    insuf_desc = f"{home_team} ({home_sample}J)"
                else:
                    insuf_desc = f"{away_team} ({away_sample}J)"

                prediction_text = f"🚫 NO_BET: Início de campeonato ou amostragem insuficiente (< 5 jogos com dados para {insuf_desc}). Entrada bloqueada pelo Gatekeeper por segurança."
            elif (home_c_stats.get("avg_cards", 0.0) <= 0.01 or away_c_stats.get("avg_cards", 0.0) <= 0.01):
                prediction_text = "🚫 NO_BET: Dados de cartões zerados ou indisponíveis para uma das equipes. Entrada bloqueada pelo Gatekeeper por segurança."
            else:
                # Aplica multiplicador regional à média combinada das equipes
                team_cards_combined_adj = team_cards_combined * league_mult

                # Fator de conversão e intensidade de faltas
                foul_conversion_context = team_cards_combined_adj * (ref_fouls / 24.0)
                
                # xC: Expected Cards (Ponderação Calibrada: 65% Árbitro [50% direto + 15% faltas] x 35% Times)
                exp_cards = round((team_cards_combined_adj * 0.35) + (yellows * 0.50) + (foul_conversion_context * 0.15), 2)
                
                # Probabilidades de Under via Distribuição de Poisson Ajustada com Sobredispersão (phi)
                under_probs = calculate_poisson_under_lines(exp_cards, phi=phi_league)
                u25 = under_probs.get(2.5, 0.0)
                u35 = under_probs[3.5]
                u45 = under_probs[4.5]
                u55 = under_probs[5.5]
                u65 = under_probs[6.5]
                u75 = under_probs[7.5]
                u85 = under_probs[8.5]
                
                # Probabilidades de Over (Complemento de Under)
                o25 = round(100.0 - u25, 2)
                o35 = round(100.0 - u35, 2)
                o45 = round(100.0 - u45, 2)
                o55 = round(100.0 - u55, 2)
                o65 = round(100.0 - u65, 2)

                # POLÍTICA EXCLUSIVA UNDER CARTÕES DA BETANO:
                # Calcula Odds Justas (100 / P) para cada linha
                odd_u35 = round(100.0 / u35, 2) if u35 > 0 else 99.00
                odd_u45 = round(100.0 / u45, 2) if u45 > 0 else 99.00
                odd_u55 = round(100.0 / u55, 2) if u55 > 0 else 99.00
                odd_u65 = round(100.0 / u65, 2) if u65 > 0 else 99.00
                odd_u75 = round(100.0 / u75, 2) if u75 > 0 else 99.00
                odd_u85 = round(100.0 / u85, 2) if u85 > 0 else 99.00

                # SELEÇÃO EXCLUSIVA DE UNDER CARTÕES (>= 60%)
                under_candidates = [
                    ("Under 3.5", u35, odd_u35),
                    ("Under 4.5", u45, odd_u45),
                    ("Under 5.5", u55, odd_u55),
                    ("Under 6.5", u65, odd_u65),
                ]
                valid_under = []
                for label, prob, odd in under_candidates:
                    if prob >= 60.0:
                        valid_under.append({'market': 'Under', 'label': label, 'prob': prob, 'odd': odd})

                if valid_under:
                    cursor.execute("""
                        SELECT palpite FROM apostas 
                        WHERE fixture_id = %s AND status = 'Pendente' AND status_gatekeeper = 'APROVADO' AND mercado = 'Total de Cartões'
                        ORDER BY confirmada DESC, id DESC LIMIT 1
                    """, (fix_id,))
                    existing_card = cursor.fetchone()
                    if existing_card and existing_card.get('palpite'):
                        matched_label = existing_card['palpite'].replace('Menos de ', 'Under ').replace(' Cartões', '').strip()
                        matched_item = next((u for u in valid_under if u['label'].strip().lower() == matched_label.lower()), None)
                        if matched_item:
                            valid_under.remove(matched_item)
                            valid_under.insert(0, matched_item)

                    # Ordena pela maior probabilidade de Under mantendo a opção prioritária
                    top_u = valid_under[0]
                    sec_u = valid_under[1] if len(valid_under) > 1 else valid_under[0]
                    prediction_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {top_u['label']} ({top_u['prob']}% | Odd Justa: {top_u['odd']}) | 2ª Opção: {sec_u['label']} ({sec_u['prob']}% | Odd Justa: {sec_u['odd']})."
                else:
                    prediction_text = f"🚫 NO_BET: Partida sem margem estatística para Under (Expectativa: {exp_cards} cartões). Nenhuma linha atendeu ao limiar mínimo de 60.0% do Gatekeeper."

                # CÁLCULO DE PALPITES DE UNDER CARTÕES POR TIME (MANDANTE & VISITANTE)
                home_cards_avg = float(home_c_stats.get("avg_cards", 2.0))
                away_cards_avg = float(away_c_stats.get("avg_cards", 2.0))
                ref_scale = (yellows / 4.20) if yellows > 0 else 1.0

                xc_home = round(home_cards_avg * (0.35 + 0.65 * ref_scale), 2)
                xc_away = round(away_cards_avg * (0.35 + 0.65 * ref_scale), 2)

                home_u_probs = calculate_team_poisson_under_lines(xc_home)
                away_u_probs = calculate_team_poisson_under_lines(xc_away)

                home_o15 = round(100.0 - home_u_probs[1.5], 2)
                home_o25 = round(100.0 - home_u_probs[2.5], 2)
                away_o15 = round(100.0 - away_u_probs[1.5], 2)
                away_o25 = round(100.0 - away_u_probs[2.5], 2)

                if xc_home <= 0.95 and home_u_probs[1.5] >= 60.0:
                    h_rec = f"Mandante Under 1.5 ({home_u_probs[1.5]}% | xC: {xc_home})"
                elif xc_home <= 1.85 and home_u_probs[2.5] >= 60.0:
                    h_rec = f"Mandante Under 2.5 ({home_u_probs[2.5]}% | xC: {xc_home})"
                elif xc_home <= 2.70 and home_u_probs[3.5] >= 60.0:
                    h_rec = f"Mandante Under 3.5 ({home_u_probs[3.5]}% | xC: {xc_home})"
                else:
                    h_rec = f"Mandante Risco Elevado (xC: {xc_home})"

                if xc_away <= 0.95 and away_u_probs[1.5] >= 60.0:
                    a_rec = f"Visitante Under 1.5 ({away_u_probs[1.5]}% | xC: {xc_away})"
                elif xc_away <= 1.85 and away_u_probs[2.5] >= 60.0:
                    a_rec = f"Visitante Under 2.5 ({away_u_probs[2.5]}% | xC: {xc_away})"
                elif xc_away <= 2.70 and away_u_probs[3.5] >= 60.0:
                    a_rec = f"Visitante Under 3.5 ({away_u_probs[3.5]}% | xC: {xc_away})"
                else:
                    a_rec = f"Visitante Risco Elevado (xC: {xc_away})"

                prediction_text += f" 🚩 Palpite Por Time: {home_team} [{h_rec}] | {away_team} [{a_rec}]."




            
            # Garante que os times possuam registros estruturados na tabela team_moving_averages sem números sintéticos de cartões
            if home_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (home_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(home_team, 'home')
                    mock_away = generate_deterministic_team_stats(home_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards, matches_count
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, 0.00, 0)
                    """, (
                        home_team_id, home_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards, matches_count
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, 0.00, 0)
                    """, (
                        home_team_id, home_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"]
                    ))

            if away_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (away_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(away_team, 'home')
                    mock_away = generate_deterministic_team_stats(away_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards, matches_count
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, 0.00, 0)
                    """, (
                        away_team_id, away_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards, matches_count
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, 0.00, 0)
                    """, (
                        away_team_id, away_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"]
                    ))

            # Para partidas iniciadas/ao vivo/encerradas, busca estatísticas e eventos em tempo real
            yellow_cards_home, yellow_cards_away = None, None
            red_cards_home, red_cards_away = None, None
            corners_home, corners_away = 0, 0
            shots_home, shots_away = 0, 0
            xg_home, xg_away = 0.00, 0.00
            goal_scorers_str = None
            last_event_str = None

            if status not in ['NS', 'PST', 'CANCELLED', 'POSTPONED']:
                # Se a partida já estiver encerrada e possuir estatísticas de cartões salvas no banco local, pula requisições de API para economizar cota
                has_cached_stats = (fix_id in cached_ft_stats)
                if not has_cached_stats and not _api_sports_rate_limited:
                    # 1. Busca estatísticas oficiais da partida (escanteios, chutes no gol, xG, cartões)
                    try:
                        stats_url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fix_id}"
                        st_res = requests.get(stats_url, headers=headers, timeout=10)
                        if st_res.status_code == 200:
                            st_json = st_res.json()
                            st_errs = st_json.get("errors")
                            if st_errs and isinstance(st_errs, dict) and ('rateLimit' in st_errs or 'requests' in st_errs):
                                print(f"[API-Sports Stats] Rate limit/cota ativou Circuit-Breaker para fixture #{fix_id}: {st_errs}")
                                _api_sports_rate_limited = True
                            else:
                                st_data = st_json.get("response", [])
                                for team_st in st_data:
                                    t_id = team_st.get("team", {}).get("id")
                                    is_home = (t_id == home_team_id)
                                    stats_list = team_st.get("statistics", [])
                                    ck, sg, s_total, xg_val = 0, 0, 0, 0.0
                                    yc, rc = None, None
                                    for s in stats_list:
                                        s_type = (s.get("type") or "").strip()
                                        s_val = s.get("value")
                                        if s_type == "Corner Kicks" and s_val is not None:
                                            ck = int(s_val)
                                        elif s_type in ["Shots on Goal", "Shots on Target"] and s_val is not None:
                                            sg = int(s_val)
                                        elif s_type in ["Total Shots", "Shots"] and s_val is not None:
                                            s_total = int(s_val)
                                        elif s_type.lower().replace("_", " ").strip() in ["expected goals", "xg", "expectedgoals"] and s_val is not None:
                                            try:
                                                xg_val = float(s_val)
                                            except (ValueError, TypeError):
                                                xg_val = 0.0
                                        elif s_type == "Yellow Cards" and s_val is not None:
                                            yc = int(s_val)
                                        elif s_type == "Red Cards" and s_val is not None:
                                            rc = int(s_val)

                                    if sg == 0 and s_total > 0:
                                        sg = s_total

                                    # Fallback para cálculo de xG em tempo real quando o xG oficial (Opta) não é fornecido pela API-Sports nesta liga
                                    if xg_val == 0.0:
                                        team_goals = int(goals_home if is_home else goals_away) if (goals_home is not None and goals_away is not None) else 0
                                        shots_off = max(0, s_total - sg)
                                        if sg > 0 or s_total > 0 or team_goals > 0:
                                            calc_xg = round((sg * 0.32) + (shots_off * 0.08) + (team_goals * 0.15), 2)
                                            if calc_xg == 0.0 and team_goals > 0:
                                                calc_xg = round(team_goals * 0.75, 2)
                                            xg_val = max(0.0, calc_xg)

                                    if is_home:
                                        corners_home = ck
                                        shots_home = sg if sg > 0 else s_total
                                        xg_home = xg_val
                                        if yc is not None: yellow_cards_home = yc
                                        if rc is not None: red_cards_home = rc
                                    else:
                                        corners_away = ck
                                        shots_away = sg if sg > 0 else s_total
                                        xg_away = xg_val
                                        if yc is not None: yellow_cards_away = yc
                                        if rc is not None: red_cards_away = rc
                    except Exception as e:
                        print(f"Aviso ao buscar estatísticas para partida {fix_id}: {e}")

                    # 2. Busca eventos oficiais da partida (cartões, gols, substituições)
                    if not _api_sports_rate_limited:
                        try:
                            events_url = f"https://v3.football.api-sports.io/fixtures/events?fixture={fix_id}"
                            ev_res = requests.get(events_url, headers=headers, timeout=10)
                            goals_list = []
                            last_ev_text = None
                            if ev_res.status_code == 200:
                                ev_json = ev_res.json()
                                ev_errs = ev_json.get("errors")
                                if ev_errs and isinstance(ev_errs, dict) and ('rateLimit' in ev_errs or 'requests' in ev_errs):
                                    print(f"[API-Sports Events] Rate limit/cota ativou Circuit-Breaker para fixture #{fix_id}: {ev_errs}")
                                    _api_sports_rate_limited = True
                                else:
                                    ev_data = ev_json.get("response", [])
                                    yh, ya, rh, ra = 0, 0, 0, 0
                                    card_count = 0
                                    sub_count = 0
                                    for ev in ev_data:
                                        team_id = ev.get("team", {}).get("id")
                                        is_home = (team_id == home_team_id)
                                        team_name_ev = home_team if is_home else away_team
                                        ev_type = ev.get("type")
                                        detail = ev.get("detail", "")
                                        time_info = ev.get("time", {})
                                        elapsed_min = time_info.get("elapsed", 0)
                                        extra_min = time_info.get("extra")
                                        time_str = f"{elapsed_min}+{extra_min}'" if extra_min else f"{elapsed_min}'"
                                        player_name = ev.get("player", {}).get("name", "")
                                        assist_name = ev.get("assist", {}).get("name", "")

                                        if ev_type == "Card":
                                            card_count += 1
                                            if "Yellow" in detail:
                                                if is_home: yh += 1
                                                else: ya += 1
                                                last_ev_text = f"{time_str} {card_count}º Cartão amarelo: {team_name_ev} ({player_name})"
                                            elif "Red" in detail:
                                                if is_home: rh += 1
                                                else: ra += 1
                                                last_ev_text = f"{time_str} Cartão vermelho: {team_name_ev} ({player_name})"
                                        elif ev_type == "Goal":
                                            goals_list.append(f"{time_str} {player_name}".strip())
                                            last_ev_text = f"{time_str} Gol: {team_name_ev} ({player_name})"
                                        elif ev_type in ["subst", "Subst", "Substitution"]:
                                            sub_count += 1
                                            if assist_name:
                                                last_ev_text = f"{time_str} {sub_count}ª Substituição: {assist_name} (Entra), {player_name} (Sai)"
                                            else:
                                                last_ev_text = f"{time_str} {sub_count}ª Substituição: {team_name_ev} ({player_name})"
                                    
                                    yellow_cards_home = max(yellow_cards_home if yellow_cards_home is not None else 0, yh)
                                    yellow_cards_away = max(yellow_cards_away if yellow_cards_away is not None else 0, ya)
                                    red_cards_home = max(red_cards_home if red_cards_home is not None else 0, rh)
                                    red_cards_away = max(red_cards_away if red_cards_away is not None else 0, ra)

                                    if goals_list:
                                        goal_scorers_str = ", ".join(goals_list)
                                    if last_ev_text:
                                        last_event_str = last_ev_text
                        except Exception as e:
                            print(f"Aviso ao buscar cartões/eventos para partida {fix_id}: {e}")

                # Mantém None caso não haja dados de cartões retornados, permitindo gravação de NULL no banco de dados
                pass

            # Insere ou atualiza a partida com placar, minutos decorridos, cartões, cantos, chutes, xG, Handicap Asiatico e eventos
            for attempt in range(3):
                try:
                    cursor.execute("""
                        INSERT INTO fixtures_trends (
                            fixture_id, fixture_date, league_id, league_name, home_team, away_team, 
                            home_team_id, away_team_id,
                            referee_name, prediction_text, over_cards_probability, status,
                            goals_home, goals_away, elapsed,
                            yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away,
                            corners_home, corners_away, shots_home, shots_away, xg_home, xg_away,
                            goal_scorers, last_event, ah_suggestion, ah_confidence, ah_reasoning
                        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                        ON DUPLICATE KEY UPDATE
                            fixture_date = VALUES(fixture_date),
                            home_team_id = VALUES(home_team_id),
                            away_team_id = VALUES(away_team_id),
                            referee_name = IF(VALUES(referee_name) IS NOT NULL AND VALUES(referee_name) != 'Árbitro Não Informado', VALUES(referee_name), referee_name),
                            prediction_text = COALESCE(VALUES(prediction_text), prediction_text),
                            over_cards_probability = VALUES(over_cards_probability),
                            status = VALUES(status),
                            goals_home = COALESCE(VALUES(goals_home), goals_home),
                            goals_away = COALESCE(VALUES(goals_away), goals_away),
                            score_processed_at = IF(VALUES(status) IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED') AND (VALUES(goals_home) IS NOT NULL OR goals_home IS NOT NULL) AND (VALUES(goals_away) IS NOT NULL OR goals_away IS NOT NULL), COALESCE(score_processed_at, NOW()), score_processed_at),
                            cards_api_checked_at = IF(VALUES(status) IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED'), COALESCE(cards_api_checked_at, NOW()), cards_api_checked_at),
                            elapsed = COALESCE(VALUES(elapsed), elapsed),
                            yellow_cards_home = COALESCE(VALUES(yellow_cards_home), yellow_cards_home),
                            yellow_cards_away = COALESCE(VALUES(yellow_cards_away), yellow_cards_away),
                            red_cards_home = COALESCE(VALUES(red_cards_home), red_cards_home),
                            red_cards_away = COALESCE(VALUES(red_cards_away), red_cards_away),
                            corners_home = IF(VALUES(corners_home) > 0, VALUES(corners_home), corners_home),
                            corners_away = IF(VALUES(corners_away) > 0, VALUES(corners_away), corners_away),
                            shots_home = IF(VALUES(shots_home) > 0, VALUES(shots_home), shots_home),
                            shots_away = IF(VALUES(shots_away) > 0, VALUES(shots_away), shots_away),
                            xg_home = IF(VALUES(xg_home) > 0, VALUES(xg_home), xg_home),
                            xg_away = IF(VALUES(xg_away) > 0, VALUES(xg_away), xg_away),
                            goal_scorers = COALESCE(VALUES(goal_scorers), goal_scorers),
                            last_event = COALESCE(VALUES(last_event), last_event),
                            ah_suggestion = VALUES(ah_suggestion),
                            ah_confidence = VALUES(ah_confidence),
                            ah_reasoning = VALUES(ah_reasoning);
                    """, (
                        fix_id, fix_date, league_id, league_name, home_team, away_team,
                        home_team_id, away_team_id,
                        referee_name, prediction_text, over_cards_prob, status,
                        goals_home, goals_away, elapsed,
                        yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away,
                        corners_home, corners_away, shots_home, shots_away, xg_home, xg_away,
                        goal_scorers_str, last_event_str, ah_suggestion, ah_confidence, ah_reasoning
                    ))
                    conn.commit()
                    if is_abstain_suggestion(ah_suggestion):
                        cancelar_e_estornar_apostas_handicap_em_abstencao(cursor, fix_id, ah_reasoning or ah_suggestion)
                        conn.commit()
                    inserted_fixtures += 1
                    break
                except pymysql.err.OperationalError as e_dl:
                    if e_dl.args[0] in (1213, 1205) and attempt < 2:
                        time.sleep(0.5)
                    else:
                        raise e_dl
        
        # Enriquecimento com Classificação dos Times (Standings / Motivação)
        try:
            enrich_fixtures_standings(conn)
        except Exception as e_st:
            print(f"Aviso ao executar enrich_fixtures_standings: {e_st}")

        print(f"\n--- RESUMO DE INGESTÃO ---")
        print(f"Modo Ao Vivo: {is_live_mode}")
        print(f"Data: {target_date}")
        print(f"Novos Árbitros Cadastrados: {inserted_referees}")
        print(f"Partidas Inseridas/Atualizadas: {inserted_fixtures}")
        
    except Exception as e:
        conn.rollback()
        print(f"ERRO durante transação do banco: {e}")
    finally:
        cursor.close()
        conn.close()

_api_sports_odds_cache = {}
_api_sports_single_odds_cache = {}
_api_sports_odds_rate_limited = False
_api_sports_quota_exceeded = False

def fetch_single_api_sports_odds(fix_id):
    """
    Busca odds de uma partida específica via API-Sports (Pro Plan por fixture_id).
    """
    global _api_sports_rate_limited
    if not fix_id or _api_sports_rate_limited:
        return {}

    if fix_id in _api_sports_single_odds_cache:
        return _api_sports_single_odds_cache[fix_id]

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    url = f"https://v3.football.api-sports.io/odds?fixture={fix_id}"
    bms_dict = {}
    try:
        resp = requests.get(url, headers=headers, timeout=10).json()
        errs = resp.get('errors')
        if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
            print(f"[API-Sports Single Odds] Cota/Rate limit atingido para fixture #{fix_id}: {errs}. Ativando Circuit-Breaker.")
            _api_sports_rate_limited = True
            _api_sports_single_odds_cache[fix_id] = {}
            return {}

        items = resp.get('response', [])
        for item in items:
            for bm in item.get('bookmakers', []):
                bm_name = str(bm.get('name', '')).strip().upper()
                for bet in bm.get('bets', []):
                    bet_id = bet.get('id')
                    bet_name = str(bet.get('name', '')).lower()
                    if bet_id == 1 or 'match winner' in bet_name or '1x2' in bet_name:
                        c_home, c_draw, c_away = 0.0, 0.0, 0.0
                        for val in bet.get('values', []):
                            v_name = str(val.get('value', '')).lower().strip()
                            try:
                                odd_val = float(val.get('odd', 0.0))
                            except (ValueError, TypeError):
                                odd_val = 0.0
                            if v_name in ['home', '1', 'mandante', 'casa']:
                                c_home = odd_val
                            elif v_name in ['draw', 'x', 'empate']:
                                c_draw = odd_val
                            elif v_name in ['away', '2', 'visitante', 'fora']:
                                c_away = odd_val
                        if c_home > 1.0 and c_draw > 1.0 and c_away > 1.0:
                            bms_dict[bm_name] = {'casa': c_home, 'empate': c_draw, 'visitante': c_away}
    except Exception as e:
        print(f"[API-Sports Single Odds] Erro para fixture #{fix_id}: {e}")

    _api_sports_single_odds_cache[fix_id] = bms_dict
    return bms_dict

def fetch_api_sports_odds_by_date(date_list=None):
    """
    Busca odds de mercado oficiais via API-Sports (https://v3.football.api-sports.io/odds?date=YYYY-MM-DD).
    Retorna dicionário mapeado diretamente pelo fixture_id:
    { fixture_id: { 'BETANO': {'casa': 4.70, 'empate': 4.50, 'visitante': 1.70}, ... } }
    """
    global _api_sports_odds_rate_limited, _api_sports_rate_limited, _api_sports_quota_exceeded
    if _api_sports_odds_rate_limited or _api_sports_quota_exceeded:
        return {}

    if date_list is None:
        today = datetime.now().strftime('%Y-%m-%d')
        tomorrow = (datetime.now() + timedelta(days=1)).strftime('%Y-%m-%d')
        yesterday = (datetime.now() - timedelta(days=1)).strftime('%Y-%m-%d')
        date_list = [today, tomorrow, yesterday]

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    odds_by_fixture = {}

    for d in date_list:
        if _api_sports_odds_rate_limited or _api_sports_quota_exceeded:
            break
        if d in _api_sports_odds_cache:
            for fid, bms in _api_sports_odds_cache[d].items():
                if fid not in odds_by_fixture:
                    odds_by_fixture[fid] = {}
                odds_by_fixture[fid].update(bms)
            continue

        page = 1
        total_pages = 1
        day_cache = {}

        while page <= total_pages and page <= 25:
            url = f"https://v3.football.api-sports.io/odds?date={d}&page={page}"
            try:
                resp = requests.get(url, headers=headers, timeout=12).json()
                errs = resp.get('errors')
                if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                    print(f"[API-Sports Odds] Limite de requisições/cota atingido: {errs}. Interrompendo chamadas da API.")
                    _api_sports_odds_rate_limited = True
                    if 'requests' in errs or 'request' in str(errs).lower():
                        _api_sports_quota_exceeded = True
                    time.sleep(1.0)
                    break

                paging = resp.get('paging', {})
                total_pages = paging.get('total', 1)

                items = resp.get('response', [])
                if not items:
                    break

                for item in items:
                    fix_id = item.get('fixture', {}).get('id')
                    if not fix_id:
                        continue

                    bookmakers = item.get('bookmakers', [])
                    for bm in bookmakers:
                        bm_name = str(bm.get('name', '')).strip().upper()
                        if not bm_name:
                            continue

                        # Procura a aposta 1X2 / Match Winner (id=1)
                        for bet in bm.get('bets', []):
                            bet_id = bet.get('id')
                            bet_name = str(bet.get('name', '')).lower().strip()
                            is_match_winner_bet = (bet_id == 1) or (
                                ('match winner' in bet_name or bet_name == '1x2' or bet_name == 'fulltime result')
                                and not any(kw in bet_name for kw in ['corner', 'card', 'half', 'minute', 'period', '1st', '2nd', '60', '30'])
                            )
                            if is_match_winner_bet:
                                c_home, c_draw, c_away = 0.0, 0.0, 0.0
                                for val in bet.get('values', []):
                                    v_name = str(val.get('value', '')).lower().strip()
                                    try:
                                        odd_val = float(val.get('odd', 0.0))
                                    except (ValueError, TypeError):
                                        odd_val = 0.0
                                    
                                    if v_name in ['home', '1', 'mandante', 'casa']:
                                        c_home = odd_val
                                    elif v_name in ['draw', 'x', 'empate']:
                                        c_draw = odd_val
                                    elif v_name in ['away', '2', 'visitante', 'fora']:
                                        c_away = odd_val

                                if c_home > 1.0 and c_draw > 1.0 and c_away > 1.0:
                                    if fix_id not in day_cache:
                                        day_cache[fix_id] = {}
                                    day_cache[fix_id][bm_name] = {
                                        'casa': c_home,
                                        'empate': c_draw,
                                        'visitante': c_away
                                    }
            except Exception as e:
                print(f"[API-Sports Odds] Erro ao buscar odds da data {d} pag {page}: {e}")
                break

            page += 1
            time.sleep(0.5)

        _api_sports_odds_cache[d] = day_cache
        for fid, bms in day_cache.items():
            if fid not in odds_by_fixture:
                odds_by_fixture[fid] = {}
            odds_by_fixture[fid].update(bms)

    return odds_by_fixture

def update_oddspedia_odds(conn):
    try:
        dags_candidate_paths = [
            '/opt/airflow/dags',
            '/usr/local/bin/dags',
            '/root/datalake-air-flow-delta/src/dags',
            os.path.abspath(os.path.join(os.path.dirname(__file__), '../src/dags'))
        ]
        for p in dags_candidate_paths:
            if os.path.exists(p) and p not in sys.path:
                sys.path.insert(0, p)

        from lib.scrapers import scrape_oddspedia_odds, scrape_futbol24_odds, scrape_futbol24_previews
        from lib.sports_arbitrage import normalize_team_name, calculate_surebet, fetch_live_odds_from_api
        
        global _api_sports_quota_exceeded, _api_sports_odds_rate_limited, _api_sports_rate_limited
        print("\n--- INICIANDO ENRIQUECIMENTO DE ODDS (CACHE BANCO + API-SPORTS) ---")
        
        cursor = conn.cursor()
        cursor.execute("SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, league_id, fixture_date, odd_home FROM fixtures_trends WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 1 DAY")
        db_fixtures = cursor.fetchall()
        if not db_fixtures:
            print("ℹ️ Nenhuma partida recente encontrada no banco para enriquecimento de odds.")
            return

        # 1. Verifica no banco se as partidas já possuem Odds cadastradas
        fixtures_with_db_odds = [f for f in db_fixtures if f.get('odd_home') and float(f['odd_home']) > 1.0]
        missing_count = len(db_fixtures) - len(fixtures_with_db_odds)
        if missing_count == 0:
            print(f"📦 [Cache Banco] Todas as {len(db_fixtures)} partidas já possuem Odds no banco de dados. Pulando chamadas externas.")
            return

        print(f"📊 Status das Odds: {len(fixtures_with_db_odds)} de {len(db_fixtures)} já possuem odds em banco ({missing_count} pendentes).")

        # 2. Ingestão oficial de Odds via API-Sports (Pro Plan por fixture_id) para as partidas pendentes
        api_sports_odds = {}
        try:
            api_sports_odds = fetch_api_sports_odds_by_date()
            if api_sports_odds:
                print(f"✅ Odds oficiais da API-Sports obtidas para {len(api_sports_odds)} partidas por fixture_id!")
        except Exception as e_apis:
            print(f"Aviso ao consultar Odds oficiais da API-Sports: {e_apis}")

        scraped_matches_op = []
        scraped_matches_f24 = []
        scraped_previews_f24 = []
        api_odds_matches = []

        # O fallback da The Odds API só roda como contingência caso a cota diária da API-Sports seja excedida
        is_api_quota_exceeded = _api_sports_quota_exceeded or _api_sports_odds_rate_limited or _api_sports_rate_limited
        should_run_fallback = is_api_quota_exceeded and (missing_count > 0)

        if should_run_fallback:
            print(f"⚠️ Cota da API-Sports esgotada! Acionando The Odds API como contingência para {missing_count} partidas pendentes...")
            
            odds_api_key = os.environ.get('ODDS_API_KEY') or 'd2f79607e3832b1f4b3003c14da3d70f'
            try:
                print("🌐 Consultando The Odds API para enriquecimento de odds de casas oficiais...")
                api_odds_matches = fetch_live_odds_from_api(odds_api_key, min_pre_match_minutes=0) or []
                print(f"✅ The Odds API retornou {len(api_odds_matches)} partidas com odds de casas oficiais!")
            except Exception as e_toapi:
                print(f"❌ Erro ao consultar The Odds API: {e_toapi}")
                api_odds_matches = []

            # Ingestão de prévias e palpites editoriais leves do Futbol24 (HTTP direto)
            try:
                scraped_previews_f24 = scrape_futbol24_previews() or []
            except Exception as e_prev:
                print(f"Aviso ao consultar Prévias Futbol24: {e_prev}")
        else:
            if not is_api_quota_exceeded:
                print("ℹ️ API-Sports com cota disponível. Fallback da The Odds API dispensado.")
            else:
                print("ℹ️ Nenhuma partida pendente de Odds no banco. Fallback dispensado.")
        
        # Consolidação de partidas e odds secundárias (scraping/agregadoras)
        scraped_by_teams = {}
        for m in scraped_matches_op + api_odds_matches + scraped_matches_f24:
            s_home = normalize_team_name(m.get('time_casa', ''))
            s_away = normalize_team_name(m.get('time_visitante', ''))
            if not s_home or not s_away or s_home == 'DESCONHECIDO' or s_away == 'DESCONHECIDO':
                continue
            key = (s_home, s_away)
            if key not in scraped_by_teams:
                scraped_by_teams[key] = {
                    "time_casa": m['time_casa'],
                    "time_visitante": m['time_visitante'],
                    "odds": {}
                }
            for bm, cota in m.get('odds', {}).items():
                bm_norm = bm.upper()
                c_home = float(cota.get("casa", 0.0))
                c_draw = float(cota.get("empate", 0.0))
                c_away = float(cota.get("visitante", 0.0))
                if c_home > 1.0 and c_draw > 1.0 and c_away > 1.0:
                    scraped_by_teams[key]['odds'][bm_norm] = {
                        "casa": c_home,
                        "empate": c_draw,
                        "visitante": c_away
                    }

        cursor = conn.cursor()
        cursor.execute("SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, league_id FROM fixtures_trends WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 1 DAY")
        db_fixtures = cursor.fetchall()

        # Ingestão de prévias e palpites editoriais do Futbol24
        if scraped_previews_f24:
            prev_updated = 0
            for fix in db_fixtures:
                fix_id = fix['fixture_id']
                db_home = normalize_team_name(fix['home_team'])
                db_away = normalize_team_name(fix['away_team'])
                for prev in scraped_previews_f24:
                    p_home = normalize_team_name(prev.get('home_team', ''))
                    p_away = normalize_team_name(prev.get('away_team', ''))
                    if db_home == p_home and db_away == p_away:
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                                futbol24_tip = %s,
                                futbol24_analysis = %s,
                                futbol24_url = %s
                            WHERE fixture_id = %s
                        """, (
                            prev.get('tip'),
                            prev.get('analysis'),
                            prev.get('url'),
                            fix_id
                        ))
                        prev_updated += 1
                        print(f"📰 Prévias do Futbol24 gravadas para {fix['home_team']} vs {fix['away_team']}")
                        break
            conn.commit()
            print(f"Total de {prev_updated} prévias do Futbol24 associadas com sucesso!")

        scraped_matches = list(scraped_by_teams.values())

        def select_multi_bookmaker_odds(valid_c1: dict, valid_cX: dict, valid_c2: dict) -> tuple:
            if not valid_c1 or not valid_cX or not valid_c2:
                return 0.0, "", 0.0, "", 0.0, ""

            all_bms = set(valid_c1.keys()) & set(valid_cX.keys()) & set(valid_c2.keys())

            # Hierarquia oficial de preferência: BETANO, BET365 e PINNACLE são as referências principais
            preferred_hierarchy = ['BETANO', 'BET365', 'PINNACLE', 'ODDSPEDIA', 'SPORTINGBET', 'SUPERBET', '1XBET', 'BETFAIR', 'BETSSON', 'KTO', 'NOVIBET', 'BETNACIONAL']
            
            for pref in preferred_hierarchy:
                matching = [b for b in all_bms if pref in b.upper()]
                if matching:
                    target_bm = matching[0]
                    return valid_c1[target_bm], target_bm, valid_cX[target_bm], target_bm, valid_c2[target_bm], target_bm

            if all_bms:
                target_bm = list(all_bms)[0]
                return valid_c1[target_bm], target_bm, valid_cX[target_bm], target_bm, valid_c2[target_bm], target_bm

            b1, m1 = max(valid_c1.items(), key=lambda x: x[1])
            bX, mX = max(valid_cX.items(), key=lambda x: x[1])
            b2, m2 = max(valid_c2.items(), key=lambda x: x[1])
            return b1, m1, bX, mX, b2, m2

        def find_source_match_odds(matches_list, home_team, away_team):
            if not matches_list:
                return {}
            db_h = normalize_team_name(home_team)
            db_a = normalize_team_name(away_team)
            for m in matches_list:
                sh = normalize_team_name(m.get('time_casa', ''))
                sa = normalize_team_name(m.get('time_visitante', ''))
                if db_h == sh and db_a == sa:
                    return m.get('odds', {})
            for m in matches_list:
                if _is_team_match(home_team, m.get('time_casa', '')) and _is_team_match(away_team, m.get('time_visitante', '')):
                    return m.get('odds', {})
            return {}

        def triangulate_3_source_odds(api_bms: dict, op_bms: dict, f24_bms: dict, home_team: str, away_team: str, toapi_bms: dict = None) -> tuple:
            """
            Triangula as odds de fontes oficiais e contingências independentes:
              1. API-Sports oficial por fixture_id (api_bms)
              2. The Odds API de contingência (toapi_bms)
              3. Fontes secundárias (op_bms, f24_bms)
            Detecta e descarta fontes desatualizadas e aplica consenso por recorrência e hierarquia de casas oficiais (Betano/Bet365/Pinnacle).
            """
            sources = []
            if api_bms:
                sources.append(('API_SPORTS', api_bms))
            if toapi_bms:
                sources.append(('THE_ODDS_API', toapi_bms))
            if op_bms:
                sources.append(('ODDSPEDIA', op_bms))
            if f24_bms:
                sources.append(('FUTBOL24', f24_bms))

            if not sources:
                return 0.0, "", 0.0, "", 0.0, ""

            # Coletar valores das fontes ao vivo para checar se a API-Sports estática está obsoleta (stale)
            live_c1 = []
            for s_name, bms in [('ODDSPEDIA', op_bms), ('FUTBOL24', f24_bms)]:
                for bm, o in (bms or {}).items():
                    if float(o.get('casa', 0.0)) > 1.0:
                        live_c1.append(float(o['casa']))

            api_is_stale = False
            if api_bms and live_c1:
                api_c1_vals = [float(o['casa']) for o in api_bms.values() if float(o.get('casa', 0.0)) > 1.0]
                if api_c1_vals:
                    avg_api = sum(api_c1_vals) / len(api_c1_vals)
                    avg_live = sum(live_c1) / len(live_c1)
                    # Divergência > 15% entre API e Ao Vivo indica movimentação recente / SuperOdds
                    if abs(avg_api - avg_live) / max(avg_api, avg_live) > 0.15:
                        api_is_stale = True
                        print(f"🔄 [Triangulação Odds] {home_team} vs {away_team}: API-Sports identificada como DESATUALIZADA (API: {avg_api:.2f} vs Ao Vivo: {avg_live:.2f}). Descartando API e priorizando consenso ao vivo!")

            consolidated_c1 = {}
            consolidated_cX = {}
            consolidated_c2 = {}

            for s_name, bms in sources:
                if s_name == 'API_SPORTS' and api_is_stale:
                    continue
                for bm, o in (bms or {}).items():
                    c1 = float(o.get('casa', 0.0))
                    cX = float(o.get('empate', 0.0))
                    c2 = float(o.get('visitante', 0.0))
                    if c1 > 1.0 and cX > 1.0 and c2 > 1.0:
                        consolidated_c1[bm] = c1
                        consolidated_cX[bm] = cX
                        consolidated_c2[bm] = c2

            if not consolidated_c1 or not consolidated_cX or not consolidated_c2:
                for s_name, bms in sources:
                    for bm, o in (bms or {}).items():
                        c1 = float(o.get('casa', 0.0))
                        cX = float(o.get('empate', 0.0))
                        c2 = float(o.get('visitante', 0.0))
                        if c1 > 1.0 and cX > 1.0 and c2 > 1.0:
                            consolidated_c1[bm] = c1
                            consolidated_cX[bm] = cX
                            consolidated_c2[bm] = c2

            import statistics
            if len(consolidated_c1) >= 3:
                med1 = statistics.median(consolidated_c1.values())
                medX = statistics.median(consolidated_cX.values())
                med2 = statistics.median(consolidated_c2.values())
                consolidated_c1 = {bm: val for bm, val in consolidated_c1.items() if abs(val - med1) / med1 <= 0.18}
                consolidated_cX = {bm: val for bm, val in consolidated_cX.items() if abs(val - medX) / medX <= 0.18}
                consolidated_c2 = {bm: val for bm, val in consolidated_c2.items() if abs(val - med2) / med2 <= 0.18}

            return select_multi_bookmaker_odds(consolidated_c1, consolidated_cX, consolidated_c2)

        updated_count = 0
        for fix in db_fixtures:
            fix_id = fix['fixture_id']
            
            # Coleta cotações de cada uma das fontes independentes
            api_bms = (api_sports_odds.get(fix_id) or {}) if api_sports_odds else {}
            if not api_bms and not _api_sports_rate_limited and not _api_sports_odds_rate_limited and not _api_sports_quota_exceeded:
                api_bms = fetch_single_api_sports_odds(fix_id)
            toapi_bms = find_source_match_odds(api_odds_matches, fix['home_team'], fix['away_team'])
            op_bms = find_source_match_odds(scraped_matches_op, fix['home_team'], fix['away_team'])
            f24_bms = find_source_match_odds(scraped_matches_f24, fix['home_team'], fix['away_team'])

            # Executa a Triangulação com filtro de obsolescência e consenso de mercado
            best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2 = triangulate_3_source_odds(
                api_bms, op_bms, f24_bms, fix['home_team'], fix['away_team'], toapi_bms=toapi_bms
            )

            # Grava no banco APENAS se tiver odds reais de casas de apostas (eliminado o fallback de odds sintéticas POISSON)
            if best_c1 > 1.0 and best_cX > 1.0 and best_c2 > 1.0:
                # Módulo de Surebets descontinuado a pedido do usuário
                is_surebet = 0
                profit_pct = 0.0
                
                # Recalcula palpite AH com odds reais e estatísticas de xG do banco
                home_last5 = fetch_team_last5_form(cursor, fix['home_team'], fix.get('home_team_id'), fix.get('league_id'))
                away_last5 = fetch_team_last5_form(cursor, fix['away_team'], fix.get('away_team_id'), fix.get('league_id'))
                home_losses = home_last5.get('d', 0) if home_last5.get('v', 0) == 0 else 0
                away_losses = away_last5.get('d', 0) if away_last5.get('v', 0) == 0 else 0
                
                cursor.execute("SELECT xg_home, xg_away FROM fixtures_trends WHERE fixture_id = %s", (fix_id,))
                row_xg = cursor.fetchone()
                xg_h = float(row_xg.get('xg_home') or 0.0) if row_xg else 0.0
                xg_a = float(row_xg.get('xg_away') or 0.0) if row_xg else 0.0

                l_name = fix.get('league_name', '')
                sug, conf, reason, proj_h, proj_a = calculate_asian_handicap_suggestion(
                    xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                    home_losses, away_losses, home_last5.get('v', 0), away_last5.get('v', 0),
                    home_last5, away_last5, best_c1, best_cX, best_c2,
                    league_name=l_name
                )

                for attempt in range(3):
                    try:
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                                odd_home = %s, casa_odd_home = %s,
                                odd_draw = %s, casa_odd_draw = %s,
                                odd_away = %s, casa_odd_away = %s,
                                xg_home = IF(xg_home <= 0, %s, xg_home),
                                xg_away = IF(xg_away <= 0, %s, xg_away),
                                is_surebet = %s, surebet_profit_pct = %s,
                                ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s,
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2, proj_h, proj_a, is_surebet, profit_pct, sug, conf, reason, fix_id))
                        conn.commit()
                        if is_abstain_suggestion(sug):
                            cancelar_e_estornar_apostas_handicap_em_abstencao(cursor, fix_id, reason or sug)
                            conn.commit()
                        else:
                            cursor.execute("""
                                UPDATE apostas
                                SET status = 'Pendente',
                                    resultado_detalhado = NULL,
                                    updated_at = NOW()
                                WHERE fixture_id = %s 
                                  AND status = 'Cancelada' 
                                  AND (resultado_detalhado LIKE '%%Odds Indisponíveis%%' OR resultado_detalhado LIKE '%%Odds de mercado indisponíveis%%')
                                  AND (confirmada IS NULL OR confirmada = 0)
                            """, (fix_id,))
                            if cursor.rowcount > 0:
                                print(f"🔄 [Restaurada Aposta AH] Fixture #{fix_id} | {fix['home_team']} vs {fix['away_team']} restaurada para Pendente após chegada de Odds de mercado!")
                            conn.commit()
                        updated_count += 1
                        print(f"Odds e motivação atualizadas para {fix['home_team']} vs {fix['away_team']}: 1({best_bm1}={best_c1}), X({best_bmX}={best_cX}), 2({best_bm2}={best_c2}) | Surebet: {is_surebet}")
                        break
                    except pymysql.err.OperationalError as e_dl:
                        if e_dl.args[0] in (1213, 1205) and attempt < 2:
                            print(f"⚠️ Deadlock no MySQL para fixture #{fix_id} ({attempt+1}/3). Tentando novamente em 0.5s...")
                            time.sleep(0.5)
                        else:
                            raise e_dl

        print(f"Total de {updated_count} partidas enriquecidas com odds de mercado reais!")
        recalculate_inconsistent_odds_predictions(conn)
    except Exception as e:
        print(f"Aviso no enriquecimento de odds: {e}")

_api_sports_standings_cache = {}

def fetch_api_sports_standings(league_id, season):
    """
    Busca a tabela de classificação de uma liga e temporada via API-Sports (/standings).
    Retorna dicionário indexado por team_id e por nome normalizado do time.
    """
    global _api_sports_rate_limited
    if not league_id or not season or _api_sports_rate_limited:
        return {}
    
    key = (int(league_id), int(season))
    if key in _api_sports_standings_cache:
        return _api_sports_standings_cache[key]
    
    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    seasons_to_try = [int(season)]
    if int(season) != 2024:
        seasons_to_try.extend([int(season)-1, 2024])

    standings_map = {}
    for s_val in seasons_to_try:
        url = f"https://v3.football.api-sports.io/standings?league={league_id}&season={s_val}"
        try:
            resp = requests.get(url, headers=headers, timeout=8).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"[API-Sports Standings] Rate limit atingido para liga #{league_id}.")
                _api_sports_rate_limited = True
                return {}

            response_data = resp.get('response', [])
            if response_data:
                league_obj = response_data[0].get('league', {})
                standings_groups = league_obj.get('standings', [])
                if standings_groups:
                    for group in standings_groups:
                        for item in group:
                            t_info = item.get('team', {})
                            t_id = t_info.get('id')
                            t_name = t_info.get('name', '')
                            rank = item.get('rank')
                            points = item.get('points', 0)
                            all_stats = item.get('all', {})
                            played = all_stats.get('played', 0)
                            ppg = round(points / played, 2) if played > 0 else 0.0
                            zone = item.get('description') or 'Mid-Table'
                            goals_diff = item.get('goalsDiff', 0)
                            form = item.get('form', '')

                            entry = {
                                'team_id': t_id,
                                'team_name': t_name,
                                'rank': rank,
                                'points': points,
                                'played': played,
                                'ppg': ppg,
                                'zone': zone,
                                'goals_diff': goals_diff,
                                'form': form
                            }
                            if t_id:
                                standings_map[int(t_id)] = entry
                            if t_name:
                                t_norm = _normalize_team_name_for_match(t_name)
                                if t_norm:
                                    standings_map[t_norm] = entry

                    if standings_map:
                        break
        except Exception as e:
            print(f"Aviso ao buscar classificação para liga #{league_id} season {s_val}: {e}")

    _api_sports_standings_cache[key] = standings_map
    return standings_map

def enrich_fixtures_standings(conn):
    """
    Enriquece as partidas da tabela fixtures_trends com a classificação oficial (standings) dos times na API-Sports.
    """
    print("\n--- INICIANDO ENRIQUECIMENTO DE CLASSIFICAÇÃO (STANDINGS) DOS TIMES ---")
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("""
        SELECT fixture_id, fixture_date, league_id, home_team, away_team, home_team_id, away_team_id
        FROM fixtures_trends
        WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 1 DAY
          AND (home_rank IS NULL OR away_rank IS NULL)
    """)
    fixtures = cursor.fetchall()
    if not fixtures:
        print("📦 [Cache Banco] Todas as partidas recentes já possuem classificação (standings) preenchida no banco. Pulando chamadas externas.")
        return

    updated_count = 0
    for fix in fixtures:
        fix_id = fix['fixture_id']
        fix_date = fix['fixture_date']
        league_id = fix['league_id']
        season = fix_date.year if isinstance(fix_date, datetime) else datetime.now().year

        standings = fetch_api_sports_standings(league_id, season)
        if not standings:
            continue

        h_id = fix.get('home_team_id')
        a_id = fix.get('away_team_id')
        h_name = _normalize_team_name_for_match(fix.get('home_team'))
        a_name = _normalize_team_name_for_match(fix.get('away_team'))

        home_data = standings.get(int(h_id)) if (h_id and int(h_id) in standings) else standings.get(h_name)
        away_data = standings.get(int(a_id)) if (a_id and int(a_id) in standings) else standings.get(a_name)

        if not home_data or not away_data:
            # Fallback para ligas nacionais populares (ex: Brasileirão 71)
            nat_standings = fetch_api_sports_standings(71, season)
            if nat_standings:
                if not home_data:
                    home_data = nat_standings.get(int(h_id)) if (h_id and int(h_id) in nat_standings) else nat_standings.get(h_name)
                if not away_data:
                    away_data = nat_standings.get(int(a_id)) if (a_id and int(a_id) in nat_standings) else nat_standings.get(a_name)

        if not home_data and not away_data:
            continue

        home_rank = home_data['rank'] if home_data else None
        home_ppg = home_data['ppg'] if home_data else None
        home_zone = str(home_data['zone'])[:250] if (home_data and home_data.get('zone')) else None

        away_rank = away_data['rank'] if away_data else None
        away_ppg = away_data['ppg'] if away_data else None
        away_zone = str(away_data['zone'])[:250] if (away_data and away_data.get('zone')) else None

        # Cálculo do Fator de Motivação da Classificação (0.00 a 10.00)
        motivation_score = 0.0
        zones_concat = (str(home_zone or '') + ' ' + str(away_zone or '')).lower()
        if any(z in zones_concat for z in ['relegation', 'rebaixamento']):
            motivation_score += 3.5
        if any(z in zones_concat for z in ['libertadores', 'champions', 'promotion']):
            motivation_score += 2.0
        
        if home_rank and away_rank:
            rank_diff = abs(home_rank - away_rank)
            if rank_diff <= 3:
                motivation_score += 2.5
            elif rank_diff >= 10:
                motivation_score += 1.0

        motivation_score = round(min(10.0, motivation_score), 2)

        cursor.execute("SELECT odd_home, odd_draw, odd_away, xg_home, xg_away FROM fixtures_trends WHERE fixture_id = %s", (fix_id,))
        fix_row = cursor.fetchone()
        
        if fix_row and fix_row.get('odd_home') and float(fix_row['odd_home']) > 1.0:
            h_l5 = fetch_team_last5_form(cursor, fix['home_team'], fix.get('home_team_id'), fix.get('league_id'))
            a_l5 = fetch_team_last5_form(cursor, fix['away_team'], fix.get('away_team_id'), fix.get('league_id'))
            h_losses = h_l5.get('d', 0) if h_l5.get('v', 0) == 0 else 0
            a_losses = a_l5.get('d', 0) if a_l5.get('v', 0) == 0 else 0
            
            xg_h = float(fix_row.get('xg_home') or 0.0)
            xg_a = float(fix_row.get('xg_away') or 0.0)

            l_name = fix.get('league_name', '')
            sug, conf, reason, proj_h, proj_a = calculate_asian_handicap_suggestion(
                xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                h_losses, a_losses, h_l5.get('v', 0), a_l5.get('v', 0),
                h_l5, a_l5,
                float(fix_row['odd_home']), float(fix_row.get('odd_draw') or 3.5), float(fix_row.get('odd_away') or 2.5),
                home_rank, away_rank, home_ppg, away_ppg, motivation_score, home_zone, away_zone,
                league_name=l_name
            )
            
            cursor.execute("""
                UPDATE fixtures_trends SET
                    home_rank = %s, away_rank = %s, home_ppg = %s, away_ppg = %s,
                    home_zone = %s, away_zone = %s, standings_motivation_score = %s,
                    xg_home = IF(xg_home <= 0, %s, xg_home),
                    xg_away = IF(xg_away <= 0, %s, xg_away),
                    ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s
                WHERE fixture_id = %s
            """, (home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, motivation_score, proj_h, proj_a, sug, conf, reason, fix_id))
            if is_abstain_suggestion(sug):
                cancelar_e_estornar_apostas_handicap_em_abstencao(cursor, fix_id, reason or sug)
        else:
            cursor.execute("""
                UPDATE fixtures_trends SET
                    home_rank = %s, away_rank = %s, home_ppg = %s, away_ppg = %s,
                    home_zone = %s, away_zone = %s, standings_motivation_score = %s
                WHERE fixture_id = %s
            """, (home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, motivation_score, fix_id))
        updated_count += 1

    conn.commit()
    print(f"✅ Classificação e motivação atualizadas com sucesso para {updated_count} de {len(fixtures)} partidas!")
    recalculate_inconsistent_odds_predictions(conn)

def recalculate_inconsistent_odds_predictions(conn):
    """
    Passo de consistência: Recalcula o palpite e o motivo da IA para qualquer partida no banco
    que possua odds reais (> 1.0), mas cujo motivo de abstenção ainda indique "Odds Indisponíveis".
    """
    cursor = conn.cursor()
    try:
        cursor.execute("""
            SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, league_id, league_name,
                   odd_home, odd_draw, odd_away, xg_home, xg_away,
                   home_rank, away_rank, home_ppg, away_ppg, standings_motivation_score, home_zone, away_zone
            FROM fixtures_trends 
            WHERE odd_home > 1.0 AND odd_draw > 1.0 AND odd_away > 1.0
              AND (ah_reasoning LIKE '%Odds Indisponíveis%' OR ah_reasoning LIKE '%Odds de mercado indisponíveis%')
              AND DATE(fixture_date) >= CURDATE() - INTERVAL 7 DAY
        """)
        inconsistent_fixtures = cursor.fetchall()
        if inconsistent_fixtures:
            print(f"🔧 Recalculando palpite IA para {len(inconsistent_fixtures)} partidas com odds disponíveis mas motivo desatualizado...")
            for fix in inconsistent_fixtures:
                fix_id = fix['fixture_id']
                h_l5 = fetch_team_last5_form(cursor, fix['home_team'], fix.get('home_team_id'), fix.get('league_id'))
                a_l5 = fetch_team_last5_form(cursor, fix['away_team'], fix.get('away_team_id'), fix.get('league_id'))
                h_losses = h_l5.get('d', 0) if h_l5.get('v', 0) == 0 else 0
                a_losses = a_l5.get('d', 0) if a_l5.get('v', 0) == 0 else 0

                xg_h = float(fix.get('xg_home') or 0.0)
                xg_a = float(fix.get('xg_away') or 0.0)
                l_name = fix.get('league_name', '')

                sug, conf, reason, proj_h, proj_a = calculate_asian_handicap_suggestion(
                    xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                    h_losses, a_losses, h_l5.get('v', 0), a_l5.get('v', 0),
                    h_l5, a_l5,
                    float(fix['odd_home']), float(fix['odd_draw']), float(fix['odd_away']),
                    fix.get('home_rank'), fix.get('away_rank'), fix.get('home_ppg'), fix.get('away_ppg'),
                    fix.get('standings_motivation_score'), fix.get('home_zone'), fix.get('away_zone'),
                    league_name=l_name
                )

                cursor.execute("""
                    UPDATE fixtures_trends SET
                        xg_home = IF(xg_home <= 0, %s, xg_home),
                        xg_away = IF(xg_away <= 0, %s, xg_away),
                        ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s, updated_at = NOW()
                    WHERE fixture_id = %s
                """, (proj_h, proj_a, sug, conf, reason, fix_id))
                if is_abstain_suggestion(sug):
                    cancelar_e_estornar_apostas_handicap_em_abstencao(cursor, fix_id, reason or sug)
                else:
                    cursor.execute("""
                        UPDATE apostas
                        SET status = 'Pendente',
                            resultado_detalhado = NULL,
                            updated_at = NOW()
                        WHERE fixture_id = %s 
                          AND status = 'Cancelada' 
                          AND (resultado_detalhado LIKE '%%Odds Indisponíveis%%' OR resultado_detalhado LIKE '%%Odds de mercado indisponíveis%%')
                          AND (confirmada IS NULL OR confirmada = 0)
                    """, (fix_id,))
                    if cursor.rowcount > 0:
                        print(f"🔄 [Restaurada Aposta AH] Fixture #{fix_id} | {fix['home_team']} vs {fix['away_team']} restaurada para Pendente após chegada de Odds de mercado!")
            conn.commit()
            print(f"✅ Sincronização concluída: {len(inconsistent_fixtures)} partidas tiveram seus palpites de IA corrigidos!")
    except Exception as e_fix_inc:
        print(f"Aviso ao recalcular palpites com odds disponíveis: {e_fix_inc}")

if __name__ == '__main__':
    main()
