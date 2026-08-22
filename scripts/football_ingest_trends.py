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

# Permitir importação de módulos de scrapers em src/dags/lib
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '../src/dags'))
try:
    from lib.scrapers import scrape_futbol24_team_last5
except Exception:
    scrape_futbol24_team_last5 = None


def calculate_poisson_over_under(xc, line=4.5):
    """
    Calcula a probabilidade exata de Over e Under X.5 cartões usando Distribuição de Poisson.
    P(X <= k) = sum(e^-xc * xc^k / k!) para k de 0 até floor(line).
    """
    if xc <= 0:
        return 0.0, 100.0
    
    k_max = int(math.floor(line))
    prob_under_cdf = 0.0
    for k in range(k_max + 1):
        prob_under_cdf += (math.exp(-xc) * (xc ** k)) / math.factorial(k)
        
    prob_over = max(0.0, min(100.0, (1.0 - prob_under_cdf) * 100.0))
    prob_under = max(0.0, min(100.0, prob_under_cdf * 100.0))
    
    return round(prob_over, 2), round(prob_under, 2)


def calculate_poisson_under_lines(xc):
    """
    Calcula as probabilidades de Under para várias linhas de cartões (3.5, 4.5, 5.5, 6.5, 7.5, 8.5) via Poisson.
    Retorna um dicionário {linha: prob_under}.
    """
    lines = [3.5, 4.5, 5.5, 6.5, 7.5, 8.5]
    results = {}
    if xc <= 0:
        for l in lines:
            results[l] = 100.0
        return results
    
    for l in lines:
        k_max = int(math.floor(l))
        prob_under_cdf = 0.0
        for k in range(k_max + 1):
            prob_under_cdf += (math.exp(-xc) * (xc ** k)) / math.factorial(k)
        results[l] = round(max(0.0, min(100.0, prob_under_cdf * 100.0)), 2)
        
    return results

def calculate_team_poisson_under_lines(xc):
    """
    Calcula as probabilidades de Under para várias linhas por TIME (1.5, 2.5, 3.5, 4.5) via Poisson.
    Retorna um dicionário {linha: prob_under}.
    """
    lines = [1.5, 2.5, 3.5, 4.5]
    results = {}
    if xc <= 0:
        for l in lines:
            results[l] = 100.0
        return results
    
    for l in lines:
        k_max = int(math.floor(l))
        prob_under_cdf = 0.0
        for k in range(k_max + 1):
            prob_under_cdf += (math.exp(-xc) * (xc ** k)) / math.factorial(k)
        results[l] = round(max(0.0, min(100.0, prob_under_cdf * 100.0)), 2)
        
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

def _normalize_team_name_for_match(n):
    if not n:
        return ""
    import re, unicodedata
    nfkd = unicodedata.normalize('NFKD', str(n))
    clean = ''.join(c for c in nfkd if not unicodedata.combining(c)).lower()
    clean = re.sub(r'\b(fc|club|ca|cd)\b', '', clean).strip()
    return clean.strip()

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
        return s_norm == t_norm
    return False

_api_sports_last5_cache = {}
_futbol24_failed_teams_cache = set()

_api_sports_rate_limited = False

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
    url = f"https://v3.football.api-sports.io/fixtures?team={tid}&last={limit}&status=FT"
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
                teams = item.get('teams', {})
                goals = item.get('goals', {})
                home_id = teams.get('home', {}).get('id')
                is_home = (int(home_id) == tid) if home_id else True
                opp_name = teams.get('away', {}).get('name') if is_home else teams.get('home', {}).get('name')
                gh = goals.get('home') if goals.get('home') is not None else 0
                ga = goals.get('away') if goals.get('away') is not None else 0
                
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
                    "is_home": is_home
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
                WHERE status = 'FT'
                  AND goals_home IS NOT NULL
                  AND goals_away IS NOT NULL
                  AND (home_team_id = %s OR away_team_id = %s)
                ORDER BY fixture_date DESC
                LIMIT 5
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
                if is_home:
                    res = "V" if gh > ga else ("E" if gh == ga else "D")
                    sc = f"{gh}x{ga}"
                else:
                    res = "V" if ga > gh else ("E" if gh == ga else "D")
                    sc = f"{ga}x{gh}"
                matches.append({"opponent": opp_name, "score": sc, "result": res, "is_home": is_home, "fixture_id": fid})
        except Exception as e_sql_id:
            print(f"Aviso na busca SQL por ID de forma para '{team_name}' (#{team_id}): {e_sql_id}")

    # 2. Se retornado < 5 partidas, consulta no banco MySQL local por Nome (+ Filtro de Liga/País)
    if cursor is not None and len(matches) < 5:
        try:
            clean_search = f"%{_normalize_team_name_for_match(team_name)}%"
            if league_id and str(league_id).strip():
                sql_name = """
                    SELECT fixture_id, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, fixture_date
                    FROM fixtures_trends
                    WHERE status = 'FT'
                      AND goals_home IS NOT NULL
                      AND goals_away IS NOT NULL
                      AND league_id = %s
                      AND (LOWER(home_team) LIKE %s OR LOWER(away_team) LIKE %s)
                    ORDER BY fixture_date DESC
                    LIMIT 30
                """
                cursor.execute(sql_name, (league_id, clean_search, clean_search))
            else:
                sql_name = """
                    SELECT fixture_id, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, fixture_date
                    FROM fixtures_trends
                    WHERE status = 'FT'
                      AND goals_home IS NOT NULL
                      AND goals_away IS NOT NULL
                      AND (LOWER(home_team) LIKE %s OR LOWER(away_team) LIKE %s)
                    ORDER BY fixture_date DESC
                    LIMIT 30
                """
                cursor.execute(sql_name, (clean_search, clean_search))

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
                    if is_home:
                        res = "V" if gh > ga else ("E" if gh == ga else "D")
                        sc = f"{gh}x{ga}"
                    else:
                        res = "V" if ga > gh else ("E" if gh == ga else "D")
                        sc = f"{ga}x{gh}"
                    matches.append({"opponent": opp_name, "score": sc, "result": res, "is_home": is_home, "fixture_id": fid})
                    if len(matches) >= 5:
                        break
        except Exception as e_sql:
            print(f"Aviso na busca SQL por Nome de forma para '{team_name}': {e_sql}")

    # 3. Consulta rápida na API-Sports por team_id se o banco local possuir menos de 5 partidas
    if len(matches) < 5 and team_id:
        try:
            api_m = fetch_api_sports_team_last5(team_id, limit=5)
            if api_m:
                existing_keys = {(m.get('opponent'), m.get('score')) for m in matches}
                for am in api_m:
                    key = (am.get('opponent'), am.get('score'))
                    if key not in existing_keys:
                        matches.append(am)
                        existing_keys.add(key)
                    if len(matches) >= 5:
                        break
        except Exception as e_api_m:
            print(f"Aviso na busca por API-Sports para '{team_name}' (#{team_id}): {e_api_m}")

    # 4. Fallback na raspagem Futbol24 desativado dentro do loop para evitar I/O síncrono e lentidão
    # Os dados do histórico dos times já são obtidos do banco local e da API-Sports.

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
    home_zone=None, away_zone=None
):
    """
    Gera a motivação do palpite em linguagem natural amigável destacando em alto nível os critérios aplicados,
    incluindo o contexto de Tabela e Motivação dos times.
    """
    home_text = home_last5.get("text", "2V-1E-2D") if home_last5 else "2V-1E-2D"
    away_text = away_last5.get("text", "2V-1E-2D") if away_last5 else "2V-1E-2D"
    odd_str = f" [Odds Mercado: H:{odd_home:.2f}/A:{odd_away:.2f}]" if (odd_home and odd_away and float(odd_home) > 1.0) else ""

    home_pts = home_last5.get("pts", 0) if home_last5 else 0
    away_pts = away_last5.get("pts", 0) if away_last5 else 0

    res_text = ""

    # Trava para Abstenção
    if "Sem Entrada" in suggestion or "Abstenção" in suggestion:
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

        res_text = (
            f"🎯 Fator Crucial: {title_text}.\n"
            f"{intro_text}\n"
            f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
            f"{market_bullet}\n"
            f"• 🛡️ Proteção de Patrimônio: Indicação com cobertura total de reembolso no empate (0.0 DNB)."
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
            res_text = (
                f"🎯 Fator Crucial: Mando de Campo Ponderado pelas Odds de Mercado.\n"
                f"A indicação a favor do {home_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
                f"• 🏟️ Equilíbrio e Fator Casa: Confronto estatisticamente emparelhado ({home_team} xG: {home_goals_scored:.1f} / U5J: {home_text} vs {away_team} xG: {away_goals_scored:.1f} / U5J: {away_text}), onde o fator casa do {home_team} concede vantagem.\n"
                f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
                f"• 🛡️ Proteção de Patrimônio: Indicação conservadora com cobertura total de reembolso no empate (0.0 DNB)."
            )
        else:
            if odd_home and odd_away and float(odd_home) > 1.0 and float(odd_away) > 1.0:
                if float(odd_home) > float(odd_away):
                    odds_market_text = f"As odds do mercado indicam favoritismo do visitante {away_team}{odd_str}, prevalecendo no modelo sobre a vantagem de mando de campo."
                else:
                    odds_market_text = f"As odds da casa de aposta ({float(odd_home):.2f} vs {float(odd_away):.2f}) favorecem o mando do {home_team}, mas o modelo identifica valor no visitante {away_team}."
            else:
                odds_market_text = f"Análise estatística interna aplicada para {home_team} e {away_team}."
            res_text = (
                f"🎯 Fator Crucial: Superioridade do Visitante Ponderada pelas Odds de Mercado.\n"
                f"A indicação a favor do {away_team} fundamenta-se na aplicação de 3 critérios de alta precisão:\n"
                f"• ⚡ Desempenho e Momentum: Apesar do mando de campo do {home_team}, o visitante {away_team} sobressaiu-se pelo desempenho superior ajustado em campo.\n"
                f"• 📈 Integração das Odds de Mercado: {odds_market_text}\n"
                f"• 🛡️ Proteção de Patrimônio: Indicação de valor a favor do visitante com cobertura total de reembolso no empate (0.0 DNB)."
            )
    else:
        res_text = (
            f"🎯 Fator Crucial: Amplo Favoritismo do Visitante ({away_team} +{abs(delta_goals):.2f} xG).\n"
            f"A indicação a favor do visitante {away_team} fundamenta-se na priorização de 3 critérios de alta precisão:\n"
            f"• 🔥 Momentum e Produção Ofensiva: Momento superior e alta produção de gols do visitante {away_team} ({away_text} em U5J / {away_goals_scored:.1f} g/j).\n"
            f"• 📈 Precificação de Mercado: Cotação de mercado e favoritismo do {away_team}{odd_str} superando o fator casa do {home_team}.\n"
            f"• 🛡️ Proteção de Banca: Recomendação a favor do visitante com cobertura total de reembolso no empate (0.0 DNB)."
        )

    if home_rank and away_rank:
        st_text = build_natural_standings_motivation_text(
            home_team, away_team, home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, standings_motivation
        )
        res_text += st_text

    return res_text

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
    home_zone=None, away_zone=None
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

    # 0. Trava de Bloqueio Estrito para Odds Ausentes ou xG Zerado
    if not odd_home or not odd_away or float(odd_home) <= 1.0 or float(odd_away) <= 1.0:
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        reasoning_text = f"🚫 APOSTA BLOQUEADA: Odds de mercado indisponíveis para esta partida. Entrada de Handicap bloqueada para proteger a banca."
        u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)
        return suggestion, confidence, f"{reasoning_text} || EXPLICACAO: 🚫 Bloqueio por Odds Indisponíveis || MOTIVACAO: Risco excessivo sem cotações de mercado reais || MEMÓRIA DE CÁLCULO || Odds Ausentes || U5J_DATA: {u5j_json}"

    if (home_goals_scored <= 0.01 and away_goals_scored <= 0.01 and home_goals_conceded <= 0.01 and away_goals_conceded <= 0.01):
        suggestion = "Sem Entrada (Abstenção)"
        confidence = 50.00
        reasoning_text = f"🚫 APOSTA BLOQUEADA: Dados de Expectativa de Gols (xG) indisponíveis para esta partida (xG = 0.00). Entrada de Handicap bloqueada para proteger a banca."
        u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)
        return suggestion, confidence, f"{reasoning_text} || EXPLICACAO: 🚫 Bloqueio por xG Indisponível || MOTIVACAO: Risco excessivo sem estatísticas de xG || MEMÓRIA DE CÁLCULO || xG Base 0.00 || U5J_DATA: {u5j_json}"

    # 1. Fator Últimos 5 Jogos (Forma Recente: V-E-D)
    home_pts = home_last5.get("pts", 7)
    home_d = home_last5.get("d", 0)
    home_v = home_last5.get("v", 0)

    away_pts = away_last5.get("pts", 7)
    away_d = away_last5.get("d", 0)
    away_v = away_last5.get("v", 0)

    # 1. Fator Mando de Campo Recalibrado Dinâmico
    if odd_home and odd_away and float(odd_away) < float(odd_home):
        if away_pts >= home_pts or away_v >= home_v:
            home_mando_factor = 1.00  # Bônus zerado se visitante é favorito nas odds E possui momento superior/igual
        else:
            home_mando_factor = 1.05  # Mando suavizado para +5% quando o visitante é apenas favorito nas odds
    else:
        home_mando_factor = 1.10  # Bônus padrão realista de jogar em casa (+10%)
    away_mando_factor = 0.93  # Ajuste de visitante fora de casa (-7%)

    # 2. Fator Últimos 5 Jogos (Forma Recente: V-E-D)
    if home_pts >= 12 or home_v >= 4:
        home_last5_factor = 1.25  # Excelente forma (+25%)
    elif home_pts >= 9 or home_v >= 3:
        home_last5_factor = 1.15  # Boa forma (+15%)
    elif home_pts <= 2 or home_d >= 4:
        home_last5_factor = 0.65  # Penalidade severa por má fase (-35%)
    elif home_pts <= 4 or home_d >= 3:
        home_last5_factor = 0.78  # Penalidade forte por derrotas (-22%)
    elif home_pts <= 7 or home_d >= 2:
        home_last5_factor = 0.85  # Sequência negativa/oscilante (-15%)
    else:
        home_last5_factor = 1.00

    if away_pts >= 12 or away_v >= 4:
        away_last5_factor = 1.30
    elif away_pts >= 9 or away_v >= 3:
        away_last5_factor = 1.20
    elif away_pts <= 2 or away_d >= 4:
        away_last5_factor = 0.65
    elif away_pts <= 4 or away_d >= 3:
        away_last5_factor = 0.78
    elif away_pts <= 5:
        away_last5_factor = 0.88
    else:
        away_last5_factor = 1.00

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

                if oa < oh:
                    is_market_away_fav = True
                elif oh < oa:
                    is_market_home_fav = True
        except Exception:
            pass

    if lambda_home_base >= 1.40:
        home_last5_factor = max(0.90, home_last5_factor)
        home_streak_factor = max(0.90, home_streak_factor)

    lambda_home = lambda_home_base * home_mando_factor * home_last5_factor * home_cs_factor * home_streak_factor * market_home_boost
    lambda_away = lambda_away_base * away_mando_factor * away_last5_factor * away_cs_factor * away_streak_factor * market_away_boost
    delta_goals = lambda_home - lambda_away

    # Memória de Cálculo formatada para a UX
    calc_memory = (
        f"🏠 {home_team} (Em Casa): xG Base {lambda_home_base:.2f} × Mando {home_mando_factor:.2f} × U5J {home_last5_factor:.2f} ({home_last5.get('text')}) × CS {home_cs_factor:.2f} ({home_cs_pct:.1f}%) × Streak {home_streak_factor:.2f}{market_str} = xG Adj {lambda_home:.2f} | "
        f"✈️ {away_team} (Fora): xG Base {lambda_away_base:.2f} × Mando {away_mando_factor:.2f} × U5J {away_last5_factor:.2f} ({away_last5.get('text')}) × CS {away_cs_factor:.2f} ({away_cs_pct:.1f}%) × Streak {away_streak_factor:.2f} = xG Adj {lambda_away:.2f} | "
        f"⚖️ Saldo Esperado (ΔG): {delta_goals:+.2f} gols."
    )

    # 6. Diagnóstico de Crise Estrito & Trava Gatekeeper
    home_in_crisis = (home_d >= 3 and home_v == 0) or (home_recent_losses >= 3 and home_v == 0)
    away_in_crisis = (away_d >= 3 and away_v == 0) or (away_recent_losses >= 3 and away_v == 0)

    # Regras de Intervenção para Mandante em Crise
    if home_in_crisis and not away_in_crisis:
        if delta_goals >= -0.20:
            suggestion = f"{away_team} +0.25 AH"
            confidence = 74.00
            main_reason = f"⚠️ Alerta de Risco: {home_team} em crise recente ({home_last5.get('text')} em U5J). O momento superior do visitante {away_team} ({away_last5.get('text')}) orienta aposta com cobertura em +0.25 AH (meio-green no empate)."
        else:
            suggestion = f"{away_team} +0.5 AH (Dupla Chance)"
            confidence = 78.00
            main_reason = f"⚠️ Alerta de Crise: Severa má fase do {home_team} ({home_last5.get('text')} em U5J). Favoritismo e vantagem de cobertura +0.5 (Dupla Chance) para o visitante {away_team}."
    elif away_in_crisis and not home_in_crisis:
        if delta_goals <= 0.20:
            suggestion = f"{home_team} 0.0 (Empate Anula)"
            confidence = 72.00
            main_reason = f"⚠️ Alerta de Risco Visitante: {away_team} em crise de resultados ({away_last5.get('text')}). Favoritismo do mandante {home_team} com proteção de reembolso ativado."
        else:
            suggestion = f"{home_team} -0.5 AH"
            confidence = 78.00
            main_reason = f"Favoritismo direto para o {home_team} devido à crise de resultados do {away_team} ({away_last5.get('text')})."
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

        # TRAVA DE CONFLITO COM FAVORITO DE MERCADO:
        # Nunca gerar aposta contra o time com a menor odd de mercado!
        if is_market_home_fav:
            if delta_goals >= 1.80:
                suggestion = f"{home_team} -1.0 AH"
                confidence = round(min(88.0, 68.0 + delta_goals * 10), 2)
                main_reason = f"Domínio estrito do mandante favorito {home_team} ({home_goals_scored:.1f} g/j) contra {away_team}. Expectativa de vitória por 2+ gols.{note_str}"
            elif delta_goals >= 1.20:
                suggestion = f"{home_team} -0.5 AH"
                confidence = round(min(82.0, 62.0 + delta_goals * 12), 2)
                main_reason = f"Vantagem sólida de mando e menor odd para o {home_team} em casa (+{delta_goals:.2f} gols esperados).{note_str}"
            elif delta_goals >= 0.20:
                suggestion = f"{home_team} -0.25 AH"
                confidence = round(min(76.0, 60.0 + abs(delta_goals) * 14), 2)
                main_reason = f"Favoritismo do {home_team} em casa nas odds e produção (+{delta_goals:.2f} gols esperados). Proteção de meia estaca (AH -0.25).{note_str}"
            elif delta_goals >= -0.30:
                suggestion = f"{home_team} 0.0 (Empate Anula)"
                confidence = 72.00
                main_reason = f"Favoritismo de mercado do mandante {home_team} alinhado com cobertura conservadora de reembolso no empate (0.0 DNB).{note_str}"
            else:
                # Conflito severo de xG vs Menor Odd do Mandante -> ABSTENÇÃO POR SEGURANÇA
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🚫 APOSTA BLOQUEADA: Divergência Crítica entre as Odds de Mercado (favoritismo do mandante {home_team}) e a estatística bruta de xG. Abstenção ativada para proteger a banca.{note_str}"
        elif is_market_away_fav:
            if delta_goals <= -1.80:
                suggestion = f"{away_team} -1.0 AH"
                confidence = round(min(88.0, 68.0 + abs(delta_goals) * 10), 2)
                main_reason = f"Domínio estrito do visitante favorito {away_team} contra o {home_team}. Expectativa de vitória por 2+ gols.{note_str}"
            elif delta_goals <= -1.20:
                suggestion = f"{away_team} -0.5 AH"
                confidence = round(min(82.0, 62.0 + abs(delta_goals) * 12), 2)
                main_reason = f"Vantagem sólida de favoritismo de mercado e produção para o visitante {away_team}.{note_str}"
            elif delta_goals <= -0.20:
                suggestion = f"{away_team} -0.25 AH"
                confidence = round(min(76.0, 60.0 + abs(delta_goals) * 14), 2)
                main_reason = f"Favoritismo do visitante {away_team} nas odds de mercado. Proteção conservadora de meia estaca (AH -0.25).{note_str}"
            elif delta_goals <= 0.30:
                suggestion = f"{away_team} 0.0 (Empate Anula)"
                confidence = 72.00
                main_reason = f"Favoritismo de mercado do visitante {away_team} alinhado com cobertura conservadora de reembolso no empate (0.0 DNB).{note_str}"
            else:
                # Conflito severo de xG vs Menor Odd do Visitante -> ABSTENÇÃO POR SEGURANÇA
                suggestion = "Sem Entrada (Abstenção)"
                confidence = 50.00
                main_reason = f"🚫 APOSTA BLOQUEADA: Divergência Crítica entre as Odds de Mercado (favoritismo do visitante {away_team}) e a estatística bruta de xG. Abstenção ativada para proteger a banca.{note_str}"
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
                suggestion = f"{home_team} 0.0 (Empate Anula)"
                confidence = 68.00
                main_reason = f"Confronto de alto equilíbrio técnico. Indicação conservadora com reembolso total no empate.{note_str}"

    # Cálculo das Probabilidades 1X2 (%) Plataforma (Modelo Poisson) vs Casa de Apostas (Odds)
    import math
    p_h = p_d = p_a = 0.0
    for hg in range(10):
        for ag in range(10):
            ph = (math.pow(lambda_home, hg) * math.exp(-lambda_home)) / math.factorial(hg)
            pa = (math.pow(lambda_away, ag) * math.exp(-lambda_away)) / math.factorial(ag)
            pj = ph * pa
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
        home_zone=home_zone, away_zone=away_zone
    )
    u5j_json = json.dumps({"home": home_last5, "away": away_last5}, ensure_ascii=False)

    full_reasoning = f"{main_reason} || EXPLICACAO: {nl_explanation} || MOTIVACAO: {nl_motivation} || MEMÓRIA DE CÁLCULO || {calc_memory} || PROBABILIDADES_1X2: {prob_1x2_json} || U5J_DATA: {u5j_json}"
    return suggestion, confidence, full_reasoning



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
    Sincroniza automaticamente resultados de partidas encerradas ou passadas que continuam sem placar/status no banco.
    """
    cursor = conn.cursor()
    try:
        cursor.execute("""
            SELECT fixture_id, fixture_date, home_team, away_team, status
            FROM fixtures_trends
            WHERE fixture_date <= UTC_TIMESTAMP() 
              AND (
                status NOT IN ('FT', 'AET', 'PEN', 'PST', 'CANC') 
                OR goals_home IS NULL 
                OR (yellow_cards_home IS NULL AND fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR))
              )
            ORDER BY fixture_date DESC
            LIMIT 100
        """)
        pending = cursor.fetchall()
        if not pending:
            return

        print(f"\n🔄 Sincronizando resultados de {len(pending)} partidas encerradas pendentes no banco...")
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
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (status, gh, ga, elapsed, fid))
                        updated_count += 1
            except Exception as e_date:
                print(f"Aviso ao sincronizar partidas passadas da data {d}: {e_date}")

        conn.commit()
        print(f"✅ Sincronizadas {updated_count} partidas passadas no banco com sucesso!")
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
        print(f"Iniciando ingestão de tendências para a data BRT: {target_date} (buscando UTC {prev_date}, {target_date} e {next_date})...")
        for d in [prev_date, target_date, next_date]:
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
        
    fixtures = list(fixtures_map.values())
    print(f"Total de {len(fixtures)} partidas únicas retornadas pela API.")
    
    # Ligas e Copas permitidas (inclui principais campeonatos, copas continentais/nacionais e amistosos de clubes)
    ALLOWED_LEAGUES = {
        71: "Serie A (Brasil)",
        72: "Serie B (Brasil)",
        74: "Serie C (Brasil)",
        73: "Copa do Brasil (Brasil)",
        75: "Copa do Nordeste (Brasil)",
        642: "Supercopa do Brasil (Brasil)",
        39: "Premier League (Inglaterra)",
        40: "Championship (Inglaterra)",
        41: "League One (Inglaterra)",
        42: "League Two (Inglaterra)",
        45: "FA Cup (Inglaterra)",
        48: "EFL Cup (Inglaterra)",
        140: "La Liga (Espanha)",
        141: "La Liga 2 (Espanha)",
        143: "Copa del Rey (Espanha)",
        135: "Serie A (Italia)",
        136: "Serie B (Italia)",
        137: "Coppa Italia (Italia)",
        78: "Bundesliga (Alemanha)",
        79: "2. Bundesliga (Alemanha)",
        81: "DFB Pokal (Alemanha)",
        61: "Ligue 1 (Franca)",
        62: "Ligue 2 (Franca)",
        66: "Coupe de France (Franca)",
        2: "Champions League (Europa)",
        3: "Europa League (Europa)",
        848: "Conference League (Europa)",
        531: "UEFA Super Cup (Europa)",
        5: "Nations League (Europa)",
        4: "Euro (Europa)",
        13: "Copa Libertadores (America do Sul)",
        11: "Copa Sudamericana (America do Sul)",
        541: "Recopa Sudamericana (America do Sul)",
        9: "Copa America (America do Sul)",
        253: "Major League Soccer (EUA)",
        772: "Leagues Cup (America)",
        262: "Liga MX (Mexico)",
        263: "Liga de Expansao MX (Mexico)",
        1028: "CONCACAF Central American Cup (CONCACAF)",
        16: "CONCACAF Champions Cup (CONCACAF)",
        113: "Allsvenskan (Suecia)",
        114: "Superettan (Suecia)",
        103: "Eliteserien (Noruega)",
        104: "1. Division (Noruega)",
        94: "Primeira Liga (Portugal)",
        95: "Segunda Liga (Portugal)",
        88: "Eredivisie (Holanda)",
        89: "Eerste Divisie (Holanda)",
        128: "Primera Division (Argentina)",
        129: "Primera Nacional (Argentina)",
        130: "Copa Argentina (Argentina)",
        98: "J1 League (Japao)",
        292: "K League 1 (Coreia do Sul)",
        283: "Liga I (Romenia)",
        286: "Super Liga (Servia)",
        244: "Veikkausliiga (Finlandia)",
        281: "Primera Division (Peru)",
        242: "Liga Pro (Equador)",
        917: "Copa Ecuador (Equador)",
        268: "Primera Division (Uruguai)",
        265: "Primera Division (Chile)",
        239: "Primera Division (Colombia)",
        501: "Copa Paraguay (Paraguai)",
        169: "Super League (China)",
        307: "Saudi Pro League (Arabia Saudita)",
        203: "Super Lig (Turquia)",
        207: "Super League (Suica)",
        144: "Pro League (Belgica)",
        119: "Superliga (Dinamarca)",
        121: "DBU Pokalen (Dinamarca)",
        218: "Bundesliga (Austria)",
        197: "Super League (Grecia)",
        179: "Scottish Premiership (Escocia)",
        106: "Ekstraklasa (Polonia)",
        345: "Czech First League (Tchequia)",
        10: "Friendlies (Amistosos de Selecoes)",
        1: "Copa do Mundo (Mundo)",
        15: "FIFA Club World Cup (Mundo)",
        17: "AFC Champions League (Asia)",
        18: "AFC Champions League Two (Asia)"
    }

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

    conn = get_mysql_connection()
    sync_pending_past_fixtures(conn, headers)
    cursor = conn.cursor()

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
    
    inserted_referees = 0
    inserted_fixtures = 0
    
    cached_ft_stats = set()
    try:
        cursor.execute("""
            SELECT fixture_id FROM fixtures_trends 
            WHERE status IN ('FT', 'AET', 'PEN') 
              AND yellow_cards_home IS NOT NULL 
              AND yellow_cards_away IS NOT NULL
              AND (
                  (yellow_cards_home + yellow_cards_away + red_cards_home + red_cards_away) > 0 
                  OR (last_event IS NOT NULL AND last_event != '')
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
            
            # Busca estatísticas reais consolidadas na tabela team_moving_averages
            def get_real_team_stats_from_db(cur_db, t_name, t_id, v_type):
                if t_id:
                    cur_db.execute("""
                        SELECT avg_goals_scored, avg_goals_conceded, clean_sheets_pct, avg_corners, avg_cards
                        FROM team_moving_averages
                        WHERE team_id = %s AND venue_type = %s
                    """, (t_id, v_type))
                    r_db = cur_db.fetchone()
                    if r_db:
                        return {
                            "avg_goals_scored": float(r_db["avg_goals_scored"]),
                            "avg_goals_conceded": float(r_db["avg_goals_conceded"]),
                            "clean_sheets_pct": float(r_db["clean_sheets_pct"]),
                            "avg_corners": float(r_db["avg_corners"]),
                            "avg_cards": float(r_db["avg_cards"])
                        }
                cur_db.execute("""
                    SELECT avg_goals_scored, avg_goals_conceded, clean_sheets_pct, avg_corners, avg_cards
                    FROM team_moving_averages
                    WHERE LOWER(team_name) = LOWER(%s) AND venue_type = %s
                """, (t_name, v_type))
                r_db = cur_db.fetchone()
                if r_db:
                    return {
                        "avg_goals_scored": float(r_db["avg_goals_scored"]),
                        "avg_goals_conceded": float(r_db["avg_goals_conceded"]),
                        "clean_sheets_pct": float(r_db["clean_sheets_pct"]),
                        "avg_corners": float(r_db["avg_corners"]),
                        "avg_cards": float(r_db["avg_cards"])
                    }
                return generate_deterministic_team_stats(t_name, v_type)

            home_c_stats = get_real_team_stats_from_db(cursor, home_team, home_team_id, 'home')
            away_c_stats = get_real_team_stats_from_db(cursor, away_team, away_team_id, 'away')
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

            # Cálculo do Handicap Asiático (xG / Mando Casa-Fora / Odds Mercado / Últimos 5 Jogos / Clean Sheets / Streak)
            ah_suggestion, ah_confidence, ah_reasoning = calculate_asian_handicap_suggestion(
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
                odd_home=f.get("odd_home"),
                odd_draw=f.get("odd_draw"),
                odd_away=f.get("odd_away")
            )

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
                
            # Gera predição combinada ponderada (50% Times, 35% Árbitro, 15% Faltas/Contexto)
            rigor = ref_data["rigor_level"]
            yellows = float(ref_data["average_yellow_cards"])
            ref_fouls = float(ref_data.get("average_fouls", 24.0))
            
            # Fator de conversão e intensidade de faltas
            foul_conversion_context = team_cards_combined * (ref_fouls / 24.0)
            
            # xC: Expected Cards
            exp_cards = round((team_cards_combined * 0.50) + (yellows * 0.35) + (foul_conversion_context * 0.15), 2)
            
            # Probabilidades de Under via Distribuição de Poisson em múltiplas linhas
            under_probs = calculate_poisson_under_lines(exp_cards)
            u35 = under_probs[3.5]
            u45 = under_probs[4.5]
            u55 = under_probs[5.5]
            u65 = under_probs[6.5]
            u75 = under_probs[7.5]
            u85 = under_probs[8.5]
            
            over_cards_prob = round(100.0 - u45, 2)

            # POLÍTICA EXCLUSIVA UNDER E SELEÇÃO DINÂMICA DE LINHAS DA BETANO:
            # Calcula Odds Justas (100 / P) para cada linha
            odd_u45 = round(100.0 / u45, 2) if u45 > 0 else 99.00
            odd_u55 = round(100.0 / u55, 2) if u55 > 0 else 99.00
            odd_u65 = round(100.0 / u65, 2) if u65 > 0 else 99.00
            odd_u75 = round(100.0 / u75, 2) if u75 > 0 else 99.00
            odd_u85 = round(100.0 / u85, 2) if u85 > 0 else 99.00

            if exp_cards <= 3.30 and u45 >= 75.0:
                # Cenário de Baixíssima Expectativa (Expectativa <= 3.30 cartões) -> 1ª Opção Under 4.5
                op1 = f"Under 4.5 ({u45}% | Odd Justa: {odd_u45})"
                op2 = f"Under 5.5 ({u55}% | Odd Justa: {odd_u55})"
                prediction_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {op1} | 2ª Opção: {op2}."
            elif exp_cards <= 4.20 and u55 >= 60.0:
                # Cenário de Baixo Risco (Expectativa <= 4.20 cartões) -> 1ª Opção Under 5.5
                op1 = f"Under 5.5 ({u55}% | Odd Justa: {odd_u55})"
                op2 = f"Under 6.5 ({u65}% | Odd Justa: {odd_u65})"
                prediction_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {op1} | 2ª Opção: {op2}."
            elif exp_cards <= 5.80 and u65 >= 60.0:
                # Cenário de Segurança Conservadora Betano (Expectativa 4.21 a 5.80 cartões) -> 1ª Opção Under 6.5
                op1 = f"Under 6.5 ({u65}% | Odd Justa: {odd_u65})"
                op2 = f"Under 7.5 ({u75}% | Odd Justa: {odd_u75})"
                prediction_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {op1} | 2ª Opção: {op2}."
            elif exp_cards <= 6.50 and u75 >= 60.0:
                # Cenário de Cobertura Alta Betano (Expectativa 5.81 a 6.50 cartões) -> 1ª Opção Under 7.5
                op1 = f"Under 7.5 ({u75}% | Odd Justa: {odd_u75})"
                op2 = f"Under 8.5 ({u85}% | Odd Justa: {odd_u85})"
                prediction_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {op1} | 2ª Opção: {op2}."
            else:
                # Trava NO_BET: Risco elevado para entradas Under (xC > 6.50 ou probabilidade Under insuficiente)
                prediction_text = f"🚫 NO_BET: Partida com Expectativa de Cartões elevada ({exp_cards} cartões). Nenhuma linha de segurança Under atendeu a margem aprovada pelo Gatekeeper."

            # CÁLCULO DE PALPITES DE UNDER CARTÕES POR TIME (MANDANTE & VISITANTE)
            home_cards_avg = float(home_c_stats.get("avg_cards", 2.0))
            away_cards_avg = float(away_c_stats.get("avg_cards", 2.0))
            ref_scale = (yellows / 4.20) if yellows > 0 else 1.0

            xc_home = round(home_cards_avg * (0.65 + 0.35 * ref_scale), 2)
            xc_away = round(away_cards_avg * (0.65 + 0.35 * ref_scale), 2)

            home_u_probs = calculate_team_poisson_under_lines(xc_home)
            away_u_probs = calculate_team_poisson_under_lines(xc_away)

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




            
            # Garante que os times possuam estatísticas na tabela team_moving_averages
            if home_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (home_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(home_team, 'home')
                    mock_away = generate_deterministic_team_stats(home_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s)
                    """, (
                        home_team_id, home_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"], 
                        mock_home["avg_cards"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s)
                    """, (
                        home_team_id, home_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"], 
                        mock_away["avg_cards"]
                    ))

            if away_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (away_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(away_team, 'home')
                    mock_away = generate_deterministic_team_stats(away_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s)
                    """, (
                        away_team_id, away_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"], 
                        mock_home["avg_cards"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s)
                    """, (
                        away_team_id, away_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"], 
                        mock_away["avg_cards"]
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
                if not has_cached_stats:
                    # 1. Busca estatísticas oficiais da partida (escanteios, chutes no gol, xG, cartões)
                    try:
                        stats_url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fix_id}"
                        st_res = requests.get(stats_url, headers=headers, timeout=10)
                        if st_res.status_code == 200:
                            st_data = st_res.json().get("response", [])
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
                    try:
                        events_url = f"https://v3.football.api-sports.io/fixtures/events?fixture={fix_id}"
                        ev_res = requests.get(events_url, headers=headers, timeout=10)
                        goals_list = []
                        last_ev_text = None
                        if ev_res.status_code == 200:
                            ev_data = ev_res.json().get("response", [])
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
                    inserted_fixtures += 1
                    break
                except pymysql.err.OperationalError as e_dl:
                    if e_dl.args[0] in (1213, 1205) and attempt < 2:
                        time.sleep(0.5)
                    else:
                        raise e_dl
        
        # Enriquecimento com Odds, Surebets e Prévias Futbol24
        try:
            update_oddspedia_odds(conn)
        except Exception as e_op:
            print(f"Aviso ao executar update_oddspedia_odds: {e_op}")

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

def fetch_single_api_sports_odds(fix_id):
    if not fix_id:
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
                    print(f"[API-Sports Odds] Limite de requisições por minuto atingido: {errs}. Aguardando 1s...")
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

        from lib.scrapers import scrape_oddspedia_odds, scrape_futbol24_odds, scrape_futbol24_previews, fetch_futbol24_direct_match_odds
        from lib.sports_arbitrage import normalize_team_name, calculate_surebet, fetch_live_odds_from_api
        
        print("\n--- INICIANDO ENRIQUECIMENTO DE ODDS MULTI-FONTE (API-SPORTS + FALLBACKS SEGUNDÁRIOS) ---")
        
        cursor = conn.cursor()
        cursor.execute("SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, league_id FROM fixtures_trends WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 1 DAY")
        db_fixtures = cursor.fetchall()
        if not db_fixtures:
            print("ℹ️ Nenhuma partida recente encontrada no banco para enriquecimento de odds.")
            return

        # 1. Ingestão oficial de Odds via API-Sports (Pro Plan por fixture_id) - FONTE PRINCIPAL VIA API
        api_sports_odds = {}
        try:
            api_sports_odds = fetch_api_sports_odds_by_date()
            if api_sports_odds:
                print(f"✅ Odds oficiais da API-Sports obtidas para {len(api_sports_odds)} partidas por fixture_id!")
        except Exception as e_apis:
            print(f"Aviso ao consultar Odds oficiais da API-Sports: {e_apis}")

        # Identifica partidas que já possuem odds via API-Sports
        fixtures_with_api_odds = set()
        if api_sports_odds:
            for fix in db_fixtures:
                fid = fix['fixture_id']
                if fid in api_sports_odds and api_sports_odds[fid]:
                    bms = api_sports_odds[fid]
                    if any(float(bms[bm].get('casa', 0.0)) > 1.0 for bm in bms if isinstance(bms[bm], dict)):
                        fixtures_with_api_odds.add(fid)

        missing_count = len(db_fixtures) - len(fixtures_with_api_odds)
        print(f"📊 Status das Odds da API: {len(fixtures_with_api_odds)} de {len(db_fixtures)} partidas obtiveram odds diretamente via API-Sports.")

        scraped_matches_op = []
        scraped_matches_f24 = []
        scraped_previews_f24 = []
        api_odds_matches = []

        # 2. ACIONA WEB SCRAPING PESADO APENAS SE HOUVER PARTIDAS SEM ODDS NA API
        if missing_count > 0 or not api_sports_odds:
            print(f"⚠️ {missing_count} partidas sem odds na API-Sports. Acionando Web Scraping de Fallback...")
            
            # The Odds API desativada em favor da API-Sports oficial
            api_odds_matches = []

            # Só executa web scraping se ainda faltarem odds para algumas partidas
            try:
                print("🕸️ Acionando Fallback Web Scraping (Oddspedia / Futbol24)...")
                scraped_matches_op = scrape_oddspedia_odds(leagues=None) or []
            except Exception as e_op:
                print(f"Aviso ao consultar Oddspedia: {e_op}")

            try:
                scraped_matches_f24 = scrape_futbol24_odds(leagues=None) or []
            except Exception as e_f24:
                print(f"Aviso ao consultar Futbol24: {e_f24}")

            try:
                scraped_previews_f24 = scrape_futbol24_previews() or []
            except Exception as e_prev:
                print(f"Aviso ao consultar Prévias Futbol24: {e_prev}")
        else:
            print("⚡ Todas as partidas obtiveram Odds oficiais via API! Pulando Web Scraping pesado (Oddspedia/Futbol24) para alta performance.")
        
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

        updated_count = 0
        for fix in db_fixtures:
            fix_id = fix['fixture_id']
            db_home = normalize_team_name(fix['home_team'])
            db_away = normalize_team_name(fix['away_team'])
            
            best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2 = 0.0, "", 0.0, "", 0.0, ""

            # 1. Prioridade Absoluta: Web Scraping ao vivo da Betano / Oddspedia / Futbol24
            matched_m = None
            for m in scraped_matches:
                s_home = normalize_team_name(m['time_casa'])
                s_away = normalize_team_name(m['time_visitante'])
                if db_home == s_home and db_away == s_away:
                    matched_m = m
                    break

            if not matched_m:
                for m in scraped_matches:
                    if _is_team_match(fix['home_team'], m['time_casa']) and _is_team_match(fix['away_team'], m['time_visitante']):
                        matched_m = m
                        break

            if matched_m:
                best_c1 = float(matched_m.get('odd_casa') or 0.0)
                best_bm1 = matched_m.get('casa_odd') or 'BETANO'
                best_cX = float(matched_m.get('odd_empate') or 0.0)
                best_bmX = matched_m.get('casa_odd') or 'BETANO'
                best_c2 = float(matched_m.get('odd_visitante') or 0.0)
                best_bm2 = matched_m.get('casa_odd') or 'BETANO'

            # 2. Fallback: API-Sports oficial por fixture_id se não houver raspagem ao vivo
            if not best_c1 or best_c1 <= 1.0:
                api_fixture_bms = None
                if api_sports_odds and fix_id in api_sports_odds:
                    api_fixture_bms = api_sports_odds[fix_id]

                if api_fixture_bms:
                    valid_c1 = {bm: float(api_fixture_bms[bm]['casa']) for bm in api_fixture_bms if float(api_fixture_bms[bm].get('casa', 0.0)) > 1.0}
                    valid_cX = {bm: float(api_fixture_bms[bm]['empate']) for bm in api_fixture_bms if float(api_fixture_bms[bm].get('empate', 0.0)) > 1.0}
                    valid_c2 = {bm: float(api_fixture_bms[bm]['visitante']) for bm in api_fixture_bms if float(api_fixture_bms[bm].get('visitante', 0.0)) > 1.0}
                    if valid_c1 and valid_cX and valid_c2:
                        best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2 = select_multi_bookmaker_odds(valid_c1, valid_cX, valid_c2)

                if not matched_m:
                    for m in scraped_matches:
                        if _is_team_match(fix['home_team'], m['time_casa']) and _is_team_match(fix['away_team'], m['time_visitante']):
                            matched_m = m
                            break
                
                if matched_m:
                    odds = matched_m.get('odds', {})
                    if odds:
                        import statistics
                        valid_c1 = {bm: float(odds[bm]['casa']) for bm in odds if float(odds[bm].get('casa', 0.0)) > 1.0}
                        valid_cX = {bm: float(odds[bm]['empate']) for bm in odds if float(odds[bm].get('empate', 0.0)) > 1.0}
                        valid_c2 = {bm: float(odds[bm]['visitante']) for bm in odds if float(odds[bm].get('visitante', 0.0)) > 1.0}

                        if len(valid_c1) >= 2:
                            med1 = statistics.median(valid_c1.values())
                            valid_c1 = {bm: val for bm, val in valid_c1.items() if val <= med1 * 1.12}
                        if len(valid_cX) >= 2:
                            medX = statistics.median(valid_cX.values())
                            valid_cX = {bm: val for bm, val in valid_cX.items() if val <= medX * 1.12}
                        if len(valid_c2) >= 2:
                            med2 = statistics.median(valid_c2.values())
                            valid_c2 = {bm: val for bm, val in valid_c2.items() if val <= med2 * 1.12}

                        best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2 = select_multi_bookmaker_odds(valid_c1, valid_cX, valid_c2)

            # 3. Fallback direto via Futbol24
            if (not best_c1 or best_c1 <= 1.0) and fetch_futbol24_direct_match_odds is not None:
                try:
                    f24_odds = fetch_futbol24_direct_match_odds(fix['home_team'], fix['away_team'])
                    if f24_odds:
                        best_c1 = f24_odds['odd_home']
                        best_bm1 = 'FUTBOL24'
                        best_cX = f24_odds['odd_draw']
                        best_bmX = 'FUTBOL24'
                        best_c2 = f24_odds['odd_away']
                        best_bm2 = 'FUTBOL24'
                except Exception as e_f24_dir:
                    print(f"Aviso ao consultar odds diretas Futbol24 para '{fix['home_team']} vs {fix['away_team']}': {e_f24_dir}")

            # Grava no banco APENAS se tiver odds reais de casas de apostas (eliminado o fallback de odds sintéticas POISSON)
            if best_c1 > 1.0 and best_cX > 1.0 and best_c2 > 1.0:
                calc = calculate_surebet(best_c1, best_cX, best_c2)
                casas_usadas = {best_bm1.upper(), best_bmX.upper(), best_bm2.upper()} - {""}
                is_surebet = 1 if (calc and calc['is_surebet'] and len(casas_usadas) > 1 and calc['lucro_percentual'] <= 15.0) else 0
                profit_pct = calc['lucro_percentual'] if (calc and is_surebet) else 0.0
                
                # Recalcula palpite AH com odds reais e estatísticas de xG do banco
                home_last5 = fetch_team_last5_form(cursor, fix['home_team'], fix.get('home_team_id'), fix.get('league_id'))
                away_last5 = fetch_team_last5_form(cursor, fix['away_team'], fix.get('away_team_id'), fix.get('league_id'))
                home_losses = home_last5.get('d', 0) if home_last5.get('v', 0) == 0 else 0
                away_losses = away_last5.get('d', 0) if away_last5.get('v', 0) == 0 else 0
                
                cursor.execute("SELECT xg_home, xg_away FROM fixtures_trends WHERE fixture_id = %s", (fix_id,))
                row_xg = cursor.fetchone()
                xg_h = float(row_xg.get('xg_home') or 0.0) if row_xg else 0.0
                xg_a = float(row_xg.get('xg_away') or 0.0) if row_xg else 0.0
                if xg_h <= 0.01: xg_h = 1.35
                if xg_a <= 0.01: xg_a = 1.10

                sug, conf, reason = calculate_asian_handicap_suggestion(
                    xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                    home_losses, away_losses, home_last5.get('v', 0), away_last5.get('v', 0),
                    home_last5, away_last5, best_c1, best_cX, best_c2
                )

                for attempt in range(3):
                    try:
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                                odd_home = %s, casa_odd_home = %s,
                                odd_draw = %s, casa_odd_draw = %s,
                                odd_away = %s, casa_odd_away = %s,
                                is_surebet = %s, surebet_profit_pct = %s,
                                ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s,
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (best_c1, best_bm1, best_cX, best_bmX, best_c2, best_bm2, is_surebet, profit_pct, sug, conf, reason, fix_id))
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
    """)
    fixtures = cursor.fetchall()
    if not fixtures:
        print("ℹ️ Nenhuma partida recente encontrada para enriquecimento de classificação.")
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
            if xg_h <= 0.01: xg_h = 1.35
            if xg_a <= 0.01: xg_a = 1.10

            sug, conf, reason = calculate_asian_handicap_suggestion(
                xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                h_losses, a_losses, h_l5.get('v', 0), a_l5.get('v', 0),
                h_l5, a_l5,
                float(fix_row['odd_home']), float(fix_row.get('odd_draw') or 3.5), float(fix_row.get('odd_away') or 2.5),
                home_rank, away_rank, home_ppg, away_ppg, motivation_score, home_zone, away_zone
            )
            
            cursor.execute("""
                UPDATE fixtures_trends SET
                    home_rank = %s, away_rank = %s, home_ppg = %s, away_ppg = %s,
                    home_zone = %s, away_zone = %s, standings_motivation_score = %s,
                    ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s
                WHERE fixture_id = %s
            """, (home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, motivation_score, sug, conf, reason, fix_id))
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
            SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, league_id,
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
                if xg_h <= 0.01: xg_h = 1.35
                if xg_a <= 0.01: xg_a = 1.10

                sug, conf, reason = calculate_asian_handicap_suggestion(
                    xg_h, 1.0, xg_a, 1.0, fix['home_team'], fix['away_team'], 30.0, 30.0,
                    h_losses, a_losses, h_l5.get('v', 0), a_l5.get('v', 0),
                    h_l5, a_l5,
                    float(fix['odd_home']), float(fix['odd_draw']), float(fix['odd_away']),
                    fix.get('home_rank'), fix.get('away_rank'), fix.get('home_ppg'), fix.get('away_ppg'),
                    fix.get('standings_motivation_score'), fix.get('home_zone'), fix.get('away_zone')
                )

                cursor.execute("""
                    UPDATE fixtures_trends SET
                        ah_suggestion = %s, ah_confidence = %s, ah_reasoning = %s, updated_at = NOW()
                    WHERE fixture_id = %s
                """, (sug, conf, reason, fix_id))
            conn.commit()
            print(f"✅ Sincronização concluída: {len(inconsistent_fixtures)} partidas tiveram seus palpites de IA corrigidos!")
    except Exception as e_fix_inc:
        print(f"Aviso ao recalcular palpites com odds disponíveis: {e_fix_inc}")

if __name__ == '__main__':
    main()
