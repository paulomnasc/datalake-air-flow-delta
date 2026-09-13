#!/usr/bin/env python3
"""
Módulo Centralizado de Handicap Asiático (Asian Handicap Engine - Single Source of Truth)
FootballWeb Pipeline

Implementa os quatro princípios matemáticos e operacionais definidos em:
docs/footballweb/PROCESSO_CRIACAO_PALPITES_HANDICAP_ASIATICO.md:
1. Modelagem Bivariada de Poisson (P(X=x, Y=y) para x,y in [0..9]);
2. Varredura Completa de Linhas da Betano (Bookmaker ID 32 - Bet ID 4 e 16);
3. Dedução Analítica da Odd Justa (Fair Odd);
4. Gatekeeper com Abstenção Mandatória (NO_BET: +EV% >= 5.0% e Prob. Efetiva >= 48.0%);
5. Sincronização Atômica Card (fixtures_trends) <-> Aposta (apostas), com proteção
   estrita e imutabilidade de apostas confirmadas (com débito em conta corrente).
"""

import os
import re
import json
import math
import requests
from datetime import datetime

try:
    from leagues_config import is_tier_1_elite_club
except Exception:
    try:
        from scripts.leagues_config import is_tier_1_elite_club
    except Exception:
        def is_tier_1_elite_club(team_id=None, team_name=None):
            return False

try:
    from football_ingest_trends import analyze_trend_and_momentum
except Exception:
    try:
        from scripts.football_ingest_trends import analyze_trend_and_momentum
    except Exception:
        analyze_trend_and_momentum = None


def compute_team_u5j_efficiency(last5_dict: dict) -> float:
    """
    Calcula a pontuação de eficiência ponderada nos últimos 5 jogos (U5J) com Strength of Schedule (SOS):
    - Vitória contra Gigante Tier 1: +5.0 pts (Super-Majorada)
    - Vitória Comum: +3.0 pts
    - Empate contra Gigante Tier 1: +2.5 pts (Majorada)
    - Empate Comum: +1.0 pt
    - Derrota Fora contra Gigante Tier 1: +0.5 pt (Atenuada por resiliência competitiva)
    - Derrota em Casa contra Gigante Tier 1: 0.0 pts
    - Derrota Comum: -1.0 pt (Subtraída)
    - Multiplicador SOS: 1.0 + (tier1_count * 0.12)
    - Teto de Invencibilidade sem Tier 1: máx 11.0 pts
    """
    if not last5_dict or not isinstance(last5_dict, dict):
        return 0.0

    matches = last5_dict.get('matches', [])
    if matches:
        pts_eff = 0.0
        tier1_count = 0
        for m in matches[:5]:
            opp = m.get('opponent', '')
            opp_id = m.get('opponent_id')
            is_t1 = m.get('is_tier_1')
            if is_t1 is None or opp_id is not None:
                is_t1 = is_tier_1_elite_club(team_id=opp_id, team_name=opp)
                m['is_tier_1'] = is_t1
            if is_t1:
                tier1_count += 1
            res = (m.get('result') or '').upper()
            is_home = m.get('is_home', True)
            if res == 'V':
                pts_eff += 5.0 if is_t1 else 3.0
            elif res == 'E':
                pts_eff += 2.5 if is_t1 else 1.0
            elif res == 'D':
                if is_t1:
                    pts_eff += 0.5 if not is_home else 0.0
                else:
                    pts_eff -= 1.0

        sos_mult = 1.0 + (tier1_count * 0.12)
        pts_eff_total = round(pts_eff * sos_mult, 1)
        if tier1_count == 0 and pts_eff_total > 11.0:
            pts_eff_total = 11.0
        return pts_eff_total

    if 'pts_efficiency' in last5_dict and last5_dict['pts_efficiency'] is not None:
        return float(last5_dict['pts_efficiency'])

    v = int(last5_dict.get('v', 0) or 0)
    e = int(last5_dict.get('e', 0) or 0)
    d = int(last5_dict.get('d', 0) or 0)
    return float((3 * v) + (1 * e) - (1 * d))

# Caches em memória para chamadas da API Betano durante o ciclo de execução
_betano_ah_odds_cache = {}
_betano_ah_raw_fixture_cache = {}
_betano_ah_1x2_cache = {}
_betano_ah_api_disabled = False


def get_cached_betano_1x2(fixture_id: int):
    """
    Retorna as odds 1X2 obtidas da Betano durante a varredura de pré-jogo:
    {'casa': float, 'empate': float, 'visitante': float, 'bookmaker': str} ou None.
    """
    return _betano_ah_1x2_cache.get(fixture_id)


def format_gatekeeper_result(status_gk: str, suggestion: str, reason: str) -> str:
    """
    Padroniza rigorosamente o resultado do Gatekeeper no formato oficial:
    STATUS GK: {status_gk}
    SUGGESTION: {suggestion}
    REASON: {reason}
    """
    clean_reason = str(reason or '').strip()
    if clean_reason.startswith("STATUS GK:"):
        return clean_reason
    return f"STATUS GK: {status_gk}\nSUGGESTION: {suggestion}\nREASON: {clean_reason}"


def get_live_env_vars():
    env_paths = [
        "/root/datalake-air-flow-delta/src/footballweb/.env",
        "/root/datalake-air-flow-delta/.env",
        "/opt/airflow/.env"
    ]
    env_vars = {}
    for p in env_paths:
        if os.path.exists(p):
            try:
                with open(p, "r", encoding="utf-8") as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith("#") and "=" in line:
                            k, v = line.split("=", 1)
                            env_vars[k.strip()] = v.strip().strip("'").strip('"')
            except Exception:
                pass
    return env_vars


def calculate_bivariate_poisson_matrix(lambda_h: float, lambda_a: float, max_goals: int = 10):
    """
    Gera a matriz de probabilidades conjuntas P(X=x, Y=y) para gols do Mandante (x) e Visitante (y).
    """
    matrix = {}
    total_prob = 0.0
    for x in range(max_goals):
        px = (math.pow(lambda_h, x) * math.exp(-lambda_h)) / math.factorial(x)
        for y in range(max_goals):
            py = (math.pow(lambda_a, y) * math.exp(-lambda_a)) / math.factorial(y)
            p = px * py
            matrix[(x, y)] = p
            total_prob += p

    if total_prob > 0:
        for k in matrix:
            matrix[k] /= total_prob

    return matrix


def evaluate_ah_line_poisson(matrix, is_away: bool, line: float, odd_betano: float):
    """
    Avalia uma linha de Handicap Asiático a partir da matriz bivariada de Poisson.
    Calcula P(win), P(half_win), P(push), P(half_loss), P(loss), Odd Justa, Prob. Efetiva e +EV%.
    """
    p_win = 0.0
    p_half_win = 0.0
    p_push = 0.0
    p_half_loss = 0.0
    p_loss = 0.0

    for (x, y), p in matrix.items():
        diff = (y - x) if is_away else (x - y)
        adj = diff + line

        if adj > 0.25:
            p_win += p
        elif abs(adj - 0.25) < 1e-4:
            p_half_win += p
        elif abs(adj) < 1e-4:
            p_push += p
        elif abs(adj - (-0.25)) < 1e-4:
            p_half_loss += p
        else:
            p_loss += p

    denom = p_win + (p_half_win / 2.0)
    numer = 1.0 - (p_push + 0.5 * p_half_loss - 0.5 * p_half_win)

    if denom > 1e-5:
        odd_justa = numer / denom
    else:
        odd_justa = 99.0

    odd_justa = max(1.01, min(99.0, odd_justa))
    prob_eff = (100.0 / odd_justa) if odd_justa > 0 else 0.0
    ev_percent = ((odd_betano / odd_justa) - 1.0) * 100.0

    return {
        'p_win': p_win * 100.0,
        'p_half_win': p_half_win * 100.0,
        'p_push': p_push * 100.0,
        'p_half_loss': p_half_loss * 100.0,
        'p_loss': p_loss * 100.0,
        'odd_justa': round(odd_justa, 2),
        'prob_eff': round(prob_eff, 1),
        'ev_percent': round(ev_percent, 1)
    }


def determine_bet_side(home_team: str, away_team: str, ah_suggestion: str) -> bool:
    """
    Determina se a linha de AH pertence ao Visitante (True) ou Mandante (False).
    """
    if not ah_suggestion:
        return False
    ah_low = ah_suggestion.lower().strip()
    h_low = (home_team or '').lower().strip()
    a_low = (away_team or '').lower().strip()

    if a_low and a_low in ah_low:
        return True
    if h_low and h_low in ah_low:
        return False
    if 'visitante' in ah_low or 'fora' in ah_low or 'away' in ah_low:
        return True
    return False


def fetch_all_betano_ah_lines(fixture_id: int, home_team: str, away_team: str):
    """
    Busca TODAS as linhas ativas de Handicap Asiático (Bet ID 4) e Draw No Bet (Bet ID 16)
    oferecidas pela Betano (Bookmaker ID 32) para a fixture.
    """
    global _betano_ah_api_disabled
    if not fixture_id or _betano_ah_api_disabled:
        return []

    if fixture_id in _betano_ah_odds_cache:
        return _betano_ah_odds_cache[fixture_id]

    available_lines = []

    if fixture_id in _betano_ah_raw_fixture_cache:
        items = _betano_ah_raw_fixture_cache[fixture_id]
    else:
        env = get_live_env_vars()
        api_key = env.get('FOOTBALL_API_KEY') or env.get('API_SPORTS_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
        headers = {
            'x-apisports-key': api_key,
            'User-Agent': 'Mozilla/5.0'
        }
        url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}"
        items = []
        try:
            resp = requests.get(url, headers=headers, timeout=10).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"⚠️ [API-Sports Betano AH] Limite de requisições atingido: {errs}. Ativando Circuit-Breaker.")
                _betano_ah_api_disabled = True
                _betano_ah_odds_cache[fixture_id] = []
                return []
            items = resp.get('response', [])
            _betano_ah_raw_fixture_cache[fixture_id] = items
        except Exception as e:
            print(f"⚠️ [API Betano AH] Erro ao buscar cotações para fixture #{fixture_id}: {e}")
            _betano_ah_raw_fixture_cache[fixture_id] = []

    # Prioridade de casas: 1º Betano (32), 2º Pinnacle (4), 3º Bet365 (8)
    preferred_order = [(32, 'BETANO'), (4, 'PINNACLE'), (8, 'BET365')]
    target_bm = None
    target_source = 'BETANO'

    for pref_id, pref_tag in preferred_order:
        for item in items:
            for bm in item.get('bookmakers', []):
                bm_name = str(bm.get('name', '')).strip().upper()
                bm_id = bm.get('id')
                if bm_id == pref_id or pref_tag in bm_name:
                    target_bm = bm
                    target_source = pref_tag
                    break
            if target_bm:
                break
        if target_bm:
            break

    if target_bm:
        bm_id = target_bm.get('id')
        for bet in target_bm.get('bets', []):
            b_id = bet.get('id')
            b_name = str(bet.get('name', '')).lower()

            # Ignora estritamente submercados parciais e outros tipos (escanteios, cartões, 1º/2º tempo)
            if any(term in b_name for term in ['half', '1st', '2nd', 'corner', 'card', 'cart', 'tempo', 'intervalo']):
                continue

            # Bet ID 4 = Asian Handicap Full Time (Gols)
            if b_id == 4 or 'asian handicap' in b_name or 'handicap asiático' in b_name:
                for val in bet.get('values', []):
                    v_str = str(val.get('value', '')).strip()
                    try:
                        v_odd = float(val.get('odd', 0))
                    except (ValueError, TypeError):
                        continue

                    if v_odd <= 1.0:
                        continue

                    m_line = re.search(r'([+-]?\d+(?:\.\d+)?)', v_str)
                    if m_line:
                        is_away = ('away' in v_str.lower() or away_team.lower() in v_str.lower())
                        try:
                            raw_line = float(m_line.group(1))
                        except Exception:
                            continue

                        # No mercado de Asian Handicap (Bet ID 4) da API-Sports, a linha numérica expressa o spread a partir do mandante (Home).
                        # Portanto, a seleção de Away reflete invariavelmente o espelho simétrico (-raw_line) em todas as casas (Betano, Pinnacle, Bet365, etc.).
                        if is_away and raw_line != 0.0:
                            line_num = -raw_line
                        else:
                            line_num = raw_line

                        target_team = away_team if is_away else home_team
                        sign_str = f"{line_num:+.2f}".rstrip('0').rstrip('.')
                        if line_num == 0:
                            sign_str = "0.0"
                        palpite_fmt = f"{target_team} {sign_str} AH"

                        available_lines.append({
                            'team': 'Away' if is_away else 'Home',
                            'target_team': target_team,
                            'is_away': is_away,
                            'line': line_num,
                            'palpite_str': palpite_fmt,
                            'odd': v_odd,
                            'raw_value': v_str,
                            'source': target_source
                        })

            # Draw No Bet (Handicap 0.0) - Bet ID 2 (Home/Away) ou nome explícito
            elif b_id == 2 or 'draw no bet' in b_name or 'empate anula' in b_name or b_name == 'home/away':
                if b_id == 16 or 'total' in b_name:
                    continue
                for val in bet.get('values', []):
                    v_str = str(val.get('value', '')).strip()
                    try:
                        v_odd = float(val.get('odd', 0))
                    except (ValueError, TypeError):
                        continue

                    if v_odd <= 1.0:
                        continue

                    is_away = ('away' in v_str.lower() or away_team.lower() in v_str.lower())
                    target_team = away_team if is_away else home_team
                    available_lines.append({
                        'team': 'Away' if is_away else 'Home',
                        'target_team': target_team,
                        'is_away': is_away,
                        'line': 0.0,
                        'palpite_str': f"{target_team} 0.0 AH",
                        'odd': v_odd,
                        'raw_value': v_str,
                        'source': target_source
                    })

            # Bet ID 1 = Match Winner (1X2 / Vencedor do Encontro)
            elif b_id == 1 or 'match winner' in b_name or b_name == '1x2' or 'resultado final' in b_name:
                c_home, c_draw, c_away = 0.0, 0.0, 0.0
                for val in bet.get('values', []):
                    v_str = str(val.get('value', '')).strip().lower()
                    try:
                        odd_val = float(val.get('odd', 0))
                    except (ValueError, TypeError):
                        odd_val = 0.0
                    if odd_val <= 1.0:
                        continue
                    if v_str in ['home', '1', 'casa', 'mandante'] or (home_team and home_team.lower() in v_str):
                        c_home = odd_val
                    elif v_str in ['draw', 'x', 'empate']:
                        c_draw = odd_val
                    elif v_str in ['away', '2', 'fora', 'visitante'] or (away_team and away_team.lower() in v_str):
                        c_away = odd_val
                if c_home > 1.0 and c_draw > 1.0 and c_away > 1.0:
                    _betano_ah_1x2_cache[fixture_id] = {
                        'casa': c_home,
                        'empate': c_draw,
                        'visitante': c_away,
                        'bookmaker': target_source
                    }

    _betano_ah_odds_cache[fixture_id] = available_lines
    return available_lines


def build_fallback_lines_from_odds(home_team: str, away_team: str, odd_home: float, odd_away: float, ah_suggestion: str = None, home_team_id: int = None, away_team_id: int = None):
    """
    Gera linhas simuladas estruturadas quando a API da Betano estiver momentaneamente
    fora do ar ou sem cotações de AH abertas, permitindo avaliação consistente de Poisson.
    Restrito exclusivamente à janela defensiva anti-empate: {0.0, 0.5, 0.75, 1.0, 1.25, 1.5}.
    """
    lines = []
    oh = float(odd_home or 2.0)
    oa = float(odd_away or 2.0)

    # Linha base sugerida se existir e pertencer à janela
    if ah_suggestion and not any(term in ah_suggestion.lower() for term in ['sem entrada', 'abstenção', 'no_bet', 'indisponível']):
        is_away_p = determine_bet_side(home_team, away_team, ah_suggestion)
        m_p = re.search(r'([+-]?\d+(?:\.\d+)?)', ah_suggestion)
        l_p = float(m_p.group(1)) if m_p else 0.0
        if l_p in {-1.5, -1.0, -0.75, -0.5, -0.25, 0.0, 0.25, 0.5, 0.75, 1.0, 1.25, 1.5}:
            raw_ref = oa if is_away_p else oh
            target_t = away_team if is_away_p else home_team
            if l_p < 0.0:
                est_odd = round(max(1.35, min(2.25, 1.0 + (raw_ref - 1.0) * 1.30 + abs(l_p) * 0.35)), 2)
            elif l_p == 0.0:
                est_odd = round(max(1.30, min(2.10, 1.0 + (raw_ref - 1.0) * 0.65)), 2)
            elif l_p <= 0.5:
                est_odd = round(max(1.22, min(1.85, 1.0 + (raw_ref - 1.0) * 0.38)), 2)
            elif l_p <= 1.0:
                est_odd = round(max(1.15, min(1.60, 1.0 + (raw_ref - 1.0) * 0.24)), 2)
            else:
                est_odd = round(max(1.15, min(1.50, 1.0 + (raw_ref - 1.0) * 0.18)), 2)

            palpite_formatado = f"{target_t} {l_p:+.2f} AH" if l_p not in (0.0, -1.0, 1.0) else (f"{target_t} -1.0 AH" if l_p == -1.0 else (f"{target_t} +1.0 AH" if l_p == 1.0 else f"{target_t} 0.0 AH"))
            lines.append({
                'team': 'Away' if is_away_p else 'Home',
                'target_team': target_t,
                'is_away': is_away_p,
                'line': l_p,
                'palpite_str': palpite_formatado,
                'odd': est_odd,
                'raw_value': f"{target_t} {l_p:+.2f}",
                'source': 'TRENDS_FALLBACK'
            })

    # Adicionar linhas padrão da janela defensiva anti-empate para Mandante e Visitante
    for (is_away, t_team, ref_odd, t_id) in [(False, home_team, oh, home_team_id), (True, away_team, oa, away_team_id)]:
        ratio_cur = (oa / oh) if not is_away else (oh / oa)
        is_this_super_fav = is_tier_1_elite_club(team_id=t_id, team_name=t_team) and (ref_odd <= 1.55 or (ref_odd <= 1.65 and ratio_cur >= 3.0))

        if not is_this_super_fav:
            for (l_val, factor) in [(-0.25, 0.56), (0.0, 0.65), (0.5, 0.35), (0.75, 0.28), (1.0, 0.22), (1.25, 0.18), (1.5, 0.15)]:
                calc_odd = round(max(1.30, min(2.35, 1.0 + (ref_odd - 1.0) * factor)), 2)
                lines.append({
                    'team': 'Away' if is_away else 'Home',
                    'target_team': t_team,
                    'is_away': is_away,
                    'line': l_val,
                    'palpite_str': f"{t_team} 0.0 AH" if l_val == 0.0 else f"{t_team} {l_val:+.2f} AH",
                    'odd': calc_odd,
                    'raw_value': f"{t_team} {l_val:+.2f}",
                    'source': 'POISSON_SYNTHETIC'
                })
        else:
            # Super-Favoritos Tier 1 operam em linhas conservadoras de handicap negativo (-0.75 e -1.0 AH)
            # Linhas superiores a -1.0 AH (-1.25, -1.5, -2.0) são expressamente proibidas por exigirem goleadas de alto risco.
            for l_neg, factor_neg in [(-0.75, 1.25), (-1.0, 1.45)]:
                calc_odd_neg = round(max(1.42, min(2.10, 1.0 + (ref_odd - 1.0) * factor_neg + 0.35)), 2)
                lines.append({
                    'team': 'Away' if is_away else 'Home',
                    'target_team': t_team,
                    'is_away': is_away,
                    'line': l_neg,
                    'palpite_str': f"{t_team} {l_neg:.2f} AH" if l_neg != -1.0 else f"{t_team} -1.0 AH",
                    'odd': calc_odd_neg,
                    'raw_value': f"{t_team} {l_neg:.2f}",
                    'source': 'POISSON_SYNTHETIC'
                })

    return lines


def evaluate_and_select_best_ah_candidate(
    poisson_matrix: dict,
    candidate_lines: list,
    home_team: str,
    away_team: str,
    odd_home: float,
    odd_away: float,
    min_ev: float = 5.0,
    min_prob: float = 48.0,
    home_team_id: int = None,
    away_team_id: int = None,
    home_last5: dict = None,
    away_last5: dict = None,
    xg_home: float = None,
    xg_away: float = None
):
    """
    Aplica o crivo rigoroso do Gatekeeper do Handicap Asiático em todas as linhas candidatas:
    - Janela estrita de linhas permitidas: {0.0, 0.5, 0.75, 1.0, 1.25, 1.5} (anti-empate / colchão defensivo)
    - Exceção de Super-Favoritos Tier 1 (odd <= 1.22, ratio >= 8.0x): permite linhas negativas moderadas {-0.75, -1.0}
    - Proibição Absoluta: Nenhuma linha mais agressiva que -1.0 AH (-1.25, -1.5, -1.75, -2.0) é tolerada.
    - Trava de Mando Consagrado: Se mandante for favorito sólido (H <= 2.00 e A >= 3.80),
      bloqueia terminantemente entradas em +AH na zebra visitante, EXCETO quando o visitante estiver
      invicto no U5J (0D), em Curva Ascendente e com xG superior (Distorção de Mercado), onde prevalece o +0.75 AH.
    - Trava de Time em Crise: Bloqueia apostas a favor de equipes sem vitórias recentes (0V no U5J)
      em situação de zebra contra favoritos de mercado
    - Faixa de odd segura: 1.50 a 2.35 (1.40 a 2.25 para super-favoritos negativos)
    - Trava de coerência: favorito 1X2 não recebe handicap positivo > 0.0
    - Gatekeeper: EV% >= min_ev (5.0%) e Probabilidade Efetiva >= min_prob (48.0% / 52.0% para negativos)
    - Score de Valor = EV% * (Prob / 100.0)
    """
    standard_allowed_lines = {0.0, 0.5, 0.75, 1.0, 1.25, 1.5}
    moderate_negative_lines = {-0.5, -0.75, -1.0}
    approved = []
    raw_h_odd = float(odd_home or 0.0)
    raw_a_odd = float(odd_away or 0.0)
    if raw_h_odd <= 1.0 or raw_a_odd <= 1.0:
        return None, []

    # 1. Trava de Mando Consagrado (Anti-Zebra em Caldeirões):
    # Mandante favorito consolidado de mercado (H <= 2.00) vs Visitante zebra (A >= 3.80 ou ratio A/H >= 2.0)
    is_strong_home_fav = (raw_h_odd <= 2.00 and (raw_a_odd >= 3.80 or (raw_h_odd > 0 and raw_a_odd / raw_h_odd >= 2.0)))

    for cand in candidate_lines:
        c_line = cand['line']
        c_odd = cand['odd']
        c_is_away = cand['is_away']

        # Trava de Segurança Máxima Anti-Goleada:
        if c_line < -1.0:
            continue

        cand_odd = raw_a_odd if c_is_away else raw_h_odd
        opp_odd = raw_h_odd if c_is_away else raw_a_odd
        cand_team = cand.get('target_team') or (away_team if c_is_away else home_team)
        cand_id = cand.get('team_id') or (away_team_id if c_is_away else home_team_id)
        opp_team = home_team if c_is_away else away_team
        opp_id = home_team_id if c_is_away else away_team_id

        cand_l5 = away_last5 if c_is_away else home_last5
        opp_l5 = home_last5 if c_is_away else away_last5

        cand_pts = cand_l5.get('pts', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        opp_pts = opp_l5.get('pts', 0) if (opp_l5 and isinstance(opp_l5, dict)) else 0
        cand_v = cand_l5.get('v', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        opp_v = opp_l5.get('v', 0) if (opp_l5 and isinstance(opp_l5, dict)) else 0
        cand_d = cand_l5.get('d', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        opp_d = opp_l5.get('d', 0) if (opp_l5 and isinstance(opp_l5, dict)) else 0

        cand_pts_eff = compute_team_u5j_efficiency(cand_l5)
        opp_pts_eff = compute_team_u5j_efficiency(opp_l5)

        cand_trend_info = analyze_trend_and_momentum(cand_team, cand_l5) if analyze_trend_and_momentum else {}
        opp_trend_info = analyze_trend_and_momentum(opp_team, opp_l5) if analyze_trend_and_momentum else {}
        cand_trend = cand_trend_info.get("trend", "CURVA_ESTAVEL")
        opp_trend = opp_trend_info.get("trend", "CURVA_ESTAVEL")

        # =========================================================================
        # CRITÉRIO PRIMÁRIO MANDATÓRIO: SOBERANIA DA PERFORMANCE REAL (U5J) SOBRE AS ODDS
        # A equipe com melhor performance recente (total de pontos + em ascensão ou invicta)
        # prevalece soberanamente sobre as odds das bancas. As odds 1X2 caem para critério secundário.
        # =========================================================================
        cand_is_better_performance = (
            (cand_pts_eff > opp_pts_eff and (cand_pts_eff - opp_pts_eff) >= 1.5) or
            (cand_pts_eff >= opp_pts_eff and cand_pts > opp_pts) or
            (cand_trend == "CURVA_ASCENDENTE" and opp_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA") and cand_pts_eff >= opp_pts_eff) or
            (cand_d == 0 and opp_d >= 2 and cand_pts_eff >= opp_pts_eff)
        )
        opp_is_better_performance = (
            (opp_pts_eff > cand_pts_eff and (opp_pts_eff - cand_pts_eff) >= 1.5) or
            (opp_pts_eff >= cand_pts_eff and opp_pts > cand_pts) or
            (opp_trend == "CURVA_ASCENDENTE" and cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA") and opp_pts_eff >= cand_pts_eff) or
            (opp_d == 0 and cand_d >= 2 and opp_pts_eff >= cand_pts_eff)
        )

        # 1. PROIBIÇÃO SOBERANA DE APOIAR TIME COM PIOR PERFORMANCE EM LINHA SECA OU FAVORÁVEL:
        # Se o adversário possui melhor performance no U5J (mais pontos, em ascensão ou invicto),
        # é expressamente PROIBIDO apostar na equipe candidata em qualquer linha que exija vitória ou DNB (c_line <= 0.0),
        # mesmo que as odds 1X2 da banca a coloquem como favorita por mero mando de campo.
        if opp_is_better_performance and c_line <= 0.0:
            continue

        # 2. TRAVA DE TIME EM CRISE OU FORMA NEGATIVA:
        # Equipes com 4+ derrotas no U5J, sem vitórias (0V) ou com eficiência negativa (<= 0.0)
        # NUNCA podem ser apoiadas em c_line <= 0.0, independentemente de odds de mercado.
        if cand_l5 and isinstance(cand_l5, dict) and (cand_d >= 4 or cand_v == 0 or cand_pts_eff <= 0.0):
            if c_line <= 0.0:
                continue

        # Identificação de Exceção por Distorção de Mercado (Visitante com Melhor Performance):
        is_away_momentum_surge = (
            c_is_away and
            cand_is_better_performance and
            cand_l5 and isinstance(cand_l5, dict) and
            (cand_l5.get('d', 1) <= 1 or cand_trend == "CURVA_ASCENDENTE")
        )

        # Filtro Trava de Mando Consagrado: Bloqueia qualquer linha a favor de zebra fraca em caldeirão
        if is_strong_home_fav and c_is_away:
            # Se o visitante for a equipe com MELHOR PERFORMANCE (Soberania U5J),
            # permite linhas de proteção (+0.5 AH, +0.75 AH, +1.0 AH) para punir a distorção da banca!
            if (cand_is_better_performance or is_away_momentum_surge) and c_line in (0.5, 0.75, 1.0):
                pass
            else:
                continue

        # 1. Trava de Coerência de Mercado 1X2:
        # Se a equipe for azarão de mercado (cand_odd > opp_odd com margem >= 0.20):
        # Se a equipe NÃO tiver melhor performance, proíbe 0.0 AH seco.
        # Mas se a equipe TIVER melhor performance (mais pontos/ascensão), a odd alta é distorção da banca:
        # permite 0.0 AH (DNB com odd alta) ou colchão positivo!
        is_cand_fav = (cand_odd < opp_odd)
        is_cand_underdog = (cand_odd > opp_odd and (cand_odd - opp_odd) >= 0.20)
        if is_cand_underdog and c_line == 0.0 and not cand_is_better_performance:
            continue

        # 1.1 Trava Anti-Queda e Estagnação em Linha Seca (0.0 AH / DNB):
        # Apoiar vitória seca DNB (0.0 AH) em equipe com curva descendente ou platô de estagnação
        # contra adversário com maior eficiência ponderada ou momento superior é risco inaceitável de banca.
        if c_line == 0.0:
            if cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA"):
                if (opp_pts_eff > cand_pts_eff) or (opp_trend == "CURVA_ASCENDENTE"):
                    continue

        # 2. Trava Anti-Aposta Seca Contra Gigante Tier 1 Favorito:
        # Se o adversário for clube Tier 1 de Elite e tiver favoritismo de mercado (opp_odd < cand_odd e opp_odd <= 2.65),
        # e a equipe candidata NÃO for Tier 1:
        # Bloqueia terminantemente apostas nas linhas curtas (0.0 AH e +0.5 AH).
        # A zebra só pode ser apoiada com vantagem ampla (+0.75 ou superior) para proteger a banca.
        is_tier1 = is_tier_1_elite_club(team_id=cand_id, team_name=cand_team)
        is_opp_tier1 = is_tier_1_elite_club(team_id=opp_id, team_name=opp_team)
        if is_opp_tier1 and not is_tier1:
            is_opp_fav = (opp_odd < cand_odd and opp_odd <= 2.65)
            if is_opp_fav and c_line <= 0.5:
                continue

        # 3. Trava de Assimetria de Eficiência e Forma Recente (U5J):
        # Se o adversário vem embalado (>= 4 vitórias / >= 12 pts brutos ou >= 9.0 pts de eficiência) e o candidato tem desempenho
        # inferior por >= 3.0 pontos de eficiência em situação de desvantagem nas odds (cand_odd > opp_odd),
        # bloqueia terminantemente qualquer aposta a favor do azarão inferior!
        if cand_l5 and opp_l5 and isinstance(cand_l5, dict) and isinstance(opp_l5, dict):
            opp_pts = opp_l5.get('pts', 0)
            cand_pts = cand_l5.get('pts', 0)
            if (opp_pts >= 12 or opp_pts_eff >= 9.0) and ((opp_pts_eff - cand_pts_eff) >= 3.0 or (opp_pts - cand_pts) >= 4) and cand_odd > opp_odd:
                continue

        # 4. Trava de Confronto de Equilíbrio de Odds (Anti-Aposta contra Quase Invicto):
        # Quando as odds 1X2 apontam equilíbrio de forças (|odd_home - odd_away| <= 0.20),
        # se o adversário for quase invicto no U5J (opp_d <= 1) ou tiver maior eficiência (opp_pts_eff > cand_pts_eff)
        # e o candidato tiver mais derrotas (cand_d > opp_d), bloqueia aposta no candidato em linha seca 0.0 AH (DNB).
        # Em jogos espelhados, não se aposta em vitória seca contra time que quase não perde.
        if cand_l5 and opp_l5 and isinstance(cand_l5, dict) and isinstance(opp_l5, dict):
            cand_d = cand_l5.get('d', 0)
            opp_d = opp_l5.get('d', 0)
            is_tight_match = abs(raw_h_odd - raw_a_odd) <= 0.20
            if is_tight_match and (opp_d <= 1 or opp_pts_eff > cand_pts_eff) and cand_d > opp_d and c_line == 0.0:
                continue

        # 4.1 Trava de Piso de Odd para Handicap Positivo de Visitante (+0.75 AH e +0.5 AH):
        # Linhas defensivas de visitante pagando cotação deprimida embutem assimetria de risco inaceitável:
        # Se o mandante vencer por 2+ gols, o apostador amarga Red integral sem ter recebido prêmio adequado.
        # Bloqueia terminantemente:
        # - Visitante +0.75 AH com odd < 1.75
        # - Visitante +0.5 AH com odd < 1.65
        if c_is_away:
            if c_line == 0.75 and c_odd < 1.75:
                continue
            if c_line == 0.50 and c_odd < 1.65:
                continue

        # Identificação de Super-Favoritos Tier 1 com odd esmagadora (<= 1.22) e ratio >= 8.0x
        ratio_h = (raw_a_odd / raw_h_odd) if raw_h_odd > 0 else 0
        ratio_a = (raw_h_odd / raw_a_odd) if raw_a_odd > 0 else 0
        is_super_fav_crushed = (
            is_tier1 and (
                (not c_is_away and raw_h_odd <= 1.22 and ratio_h >= 8.0) or
                (c_is_away and raw_a_odd <= 1.22 and ratio_a >= 8.0)
            )
        )

        # Exceção Estrita de Assimetria Técnica Dominante (Tier 1 Dominante no U5J):
        # Permite avaliar linhas moderadas {-0.5, -0.75, -1.0 AH} SOMENTE em cenários de dominância indiscutível:
        # 1) Clube candidato é Tier 1 Elite comprovado
        # 2) Favorito sólido de mercado: cand_odd <= 1.65 e ratio de odds >= 2.5x contra o adversário
        # 3) Momento de elite no U5J: candidato tem >= 12 pontos (ou invicto com >= 4V) e adversário fragilizado (<= 5 pontos)
        # 4) Diferença de aproveitamento no U5J >= 7 pontos
        cand_pts = cand_l5.get('pts', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        opp_pts = opp_l5.get('pts', 0) if (opp_l5 and isinstance(opp_l5, dict)) else 0
        cand_v = cand_l5.get('v', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        cand_d = cand_l5.get('d', 0) if (cand_l5 and isinstance(cand_l5, dict)) else 0
        cand_ratio = (opp_odd / cand_odd) if cand_odd > 0 else 0

        is_dominant_tier1 = (
            is_tier1 and
            cand_odd <= 1.65 and
            cand_ratio >= 2.5 and
            (cand_pts >= 12 or (cand_v >= 4 and cand_d == 0)) and
            opp_pts <= 5 and
            (cand_pts - opp_pts) >= 7
        )

        is_eligible_negative = (is_super_fav_crushed or is_dominant_tier1)

        # Identificação de Favorito em Grande Fase (U5J >= 9.0 pts, vantagem >= 3.0 pts e cand_odd < opp_odd):
        is_fav_in_form = (
            is_cand_fav and
            (cand_pts >= 12 or cand_pts_eff >= 9.0) and
            ((cand_pts_eff - opp_pts_eff) >= 3.0 or (cand_pts - opp_pts) >= 4)
        )

        # Filtro 1: Linhas permitidas e faixas de odds seguras
        required_ev = min_ev
        if cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA"):
            required_ev += 2.0

        if c_line in moderate_negative_lines:
            # Linhas de handicap negativo (-0.5, -0.75, -1.0) são restritas a Super-Favoritos ou Tier 1 com Assimetria Dominante
            if not is_eligible_negative:
                continue
            if c_odd < 1.50 or c_odd > 2.25:
                continue
            required_prob = 52.0 if is_super_fav_crushed else 60.0
            required_ev = min_ev if is_super_fav_crushed else 15.0
        elif c_line == -0.25:
            # Linha conservadora de -0.25 AH: permitida para Favoritos em Grande Fase ou Super-Favoritos
            if not (is_fav_in_form or is_eligible_negative):
                continue
            if c_odd < 1.50 or c_odd > 2.25:
                continue
            required_prob = 48.0
            required_ev = min_ev
        elif c_line in standard_allowed_lines:
            # Super-favoritos com odd esmagada não operam na linha 0.0 AH
            if is_super_fav_crushed and c_line == 0.0:
                continue
            if c_odd < 1.50 or c_odd > 2.35:
                continue
            # Inversão de Handicap: favorito 1X2 não recebe handicap positivo > 0.0
            if is_cand_fav and c_line > 0.0:
                continue
            # Trava Anti-Inversão de Mercado: Linha positiva alta (>= 0.5 AH) com odd alta (>= 2.05)
            # só é aceitável se a equipe for comprovadamente zebra nas odds 1X2 (cand_odd > opp_odd + 0.20)
            if c_line >= 0.5 and c_odd >= 2.05 and not is_cand_underdog:
                continue
            # Trava de Proteção Tier 1: Clube Tier 1 Elite não recebe vantagem excessiva (>= 0.75 AH) contra não-Tier 1
            if is_tier1 and not is_opp_tier1 and c_line >= 0.75 and cand_odd <= 2.50:
                continue
            required_prob = min_prob
        else:
            continue
            continue

        # Avaliação com a Matriz de Poisson
        res = evaluate_ah_line_poisson(poisson_matrix, c_is_away, c_line, c_odd)
        ev = res['ev_percent']
        prob_eff = res['prob_eff']

        # 4. Ancoragem Bayesiana (Filtro de Sanidade Poisson vs Mercado 1X2):
        # A casa de apostas precifica o 1X2 com altíssima eficiência de mercado.
        # Se a probabilidade pura de vitória estimada por Poisson para o azarão divergir
        # mais de 12 pontos percentuais da probabilidade implícita justa do mercado 1X2,
        # rejeita a entrada por distorção de Poisson (xG inflado por desnível de ligas).
        # Em cenários de Distorção de Banca / Momentum Surge comprovada, expande tolerância para até 22.0 pp.
        if is_cand_underdog:
            inv_cand = 1.0 / cand_odd if cand_odd > 0 else 0.5
            inv_opp = 1.0 / opp_odd if opp_odd > 0 else 0.5
            market_cand_win_prob = (inv_cand / (inv_cand + inv_opp)) * 100.0
            p_pure_win = sum(p for (x, y), p in poisson_matrix.items() if (y > x if c_is_away else x > y)) * 100.0
            max_divergence = 25.0 if (cand_is_better_performance or is_away_momentum_surge) else 12.0
            if (p_pure_win - market_cand_win_prob) > max_divergence:
                continue

        if ev >= required_ev and prob_eff >= required_prob:
            score = ev * (prob_eff / 100.0)
            cand_copy = dict(cand)
            cand_copy['eval'] = res
            cand_copy['score'] = score
            cand_copy['is_momentum_surge'] = (cand_is_better_performance and is_cand_underdog) or is_away_momentum_surge
            approved.append(cand_copy)

    if not approved:
        return None, []

    # Em situações de Distorção de Banca / Soberania da Performance, prevalece a linha de maior proteção (+0.75 AH ou +0.5 AH)
    surge_cushion = [c for c in approved if c.get('is_momentum_surge') and c['line'] in (0.75, 0.5)]
    if surge_cushion:
        surge_cushion.sort(key=lambda x: (x['line'] == 0.75, x['score']), reverse=True)
        approved = surge_cushion + [c for c in approved if c not in surge_cushion]
        return approved[0], approved

    approved.sort(key=lambda x: x['score'], reverse=True)
    return approved[0], approved


def calculate_unified_handicap_recommendation(
    fixture_dict: dict,
    betano_lines: list = None,
    allow_api_fetch: bool = True,
    cursor = None
):
    """
    Função Mestre Unificada para Ingestão, Criação de Apostas e Auditoria 'Checar Odds Agora'.
    Retorna: (status, palpite_sugerido, confianca, reasoning, best_cand, approved_list)
    """
    home_team = (fixture_dict.get('home_team') or '').strip()
    away_team = (fixture_dict.get('away_team') or '').strip()
    fixture_id = fixture_dict.get('fixture_id')
    reasoning = fixture_dict.get('ah_reasoning') or ''

    # 0. Verificação ESTRITA e MANDATÓRIA de Amostragem Completa de 5 Jogos (U5J)
    # Exige que rigorosamente AMBAS as equipes possuam 5 partidas consolidadas em seu histórico recente.
    h_matches_cnt = None
    a_matches_cnt = None

    u_json = {}
    if '|| U5J_DATA:' in reasoning:
        try:
            u_part = reasoning.split('|| U5J_DATA:')[1].split('||')[0].strip()
            u_json = json.loads(u_part)
            if 'home' in u_json and isinstance(u_json['home'], dict):
                h_matches_cnt = len(u_json['home'].get('matches', []))
            if 'away' in u_json and isinstance(u_json['away'], dict):
                a_matches_cnt = len(u_json['away'].get('matches', []))
        except Exception:
            pass

    # Se a amostragem estiver ausente ou incompleta (< 5 jogos) na string anterior, tenta obter via banco/cache/API
    if (h_matches_cnt is None or a_matches_cnt is None or h_matches_cnt < 5 or a_matches_cnt < 5) and cursor:
        try:
            from football_ingest_trends import fetch_team_last5_form
            h_id = fixture_dict.get('home_team_id')
            a_id = fixture_dict.get('away_team_id')
            l_id = fixture_dict.get('league_id')
            
            if h_matches_cnt is None or h_matches_cnt < 5:
                h_form = fetch_team_last5_form(cursor, home_team, h_id, l_id)
                if h_form and isinstance(h_form, dict) and 'matches' in h_form:
                    h_matches_cnt = len(h_form['matches'])
                    if 'home' not in u_json or not isinstance(u_json.get('home'), dict):
                        u_json['home'] = {}
                    u_json['home'] = h_form

            if a_matches_cnt is None or a_matches_cnt < 5:
                a_form = fetch_team_last5_form(cursor, away_team, a_id, l_id)
                if a_form and isinstance(a_form, dict) and 'matches' in a_form:
                    a_matches_cnt = len(a_form['matches'])
                    if 'away' not in u_json or not isinstance(u_json.get('away'), dict):
                        u_json['away'] = {}
                    u_json['away'] = a_form
        except Exception:
            pass

    if h_matches_cnt is not None and a_matches_cnt is not None:
        if h_matches_cnt < 5 or a_matches_cnt < 5:
            lacking = []
            if h_matches_cnt < 5:
                lacking.append(f"{home_team} ({h_matches_cnt}J)")
            if a_matches_cnt < 5:
                lacking.append(f"{away_team} ({a_matches_cnt}J)")
            lacking_str = ", ".join(lacking)
            reason_block = (
                f"🛡️ [Gatekeeper AH NO_BET / Amostragem Insuficiente] Histórico recente incompleto (< 5 partidas consolidadas para {lacking_str}). "
                f"Entrada de Handicap bloqueada pelo Gatekeeper por segurança estatística e integridade amostral."
            )
            return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason_block), None, []

    if (h_matches_cnt is None or a_matches_cnt is None) and any(b in reasoning.lower() for b in [
        'amostragem insuficiente', 'histórico indisponível', 'histórico ausente', 
        '< 5 partidas consolidadas', 'amostragem incompleta', 'dados insuficientes'
    ]):
        reason_block = f"🛡️ [Gatekeeper AH NO_BET / Amostragem Insuficiente] Histórico U5J insuficiente ou ausente para {home_team} vs {away_team}. Abstenção mandatória."
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason_block), None, []

    # Projeção de xG:
    # 1. Fonte Primária: xG calibrado do banco de dados (fixtures_trends.xg_home / xg_away) da modelagem estatística robusta
    xg_h = float(fixture_dict.get('xg_home') or 0.0)
    xg_a = float(fixture_dict.get('xg_away') or 0.0)

    # 2. Fallback Secundário: Projeção de xG a partir dos 5 jogos consolidados de U5J caso xG do banco esteja zerado/indisponível
    if xg_h <= 0.1 or xg_a <= 0.1:
        if 'home' in u_json and 'away' in u_json:
            h_m = u_json['home'].get('matches', [])
            a_m = u_json['away'].get('matches', [])
            h_sc, h_con = [], []
            a_sc, a_con = [], []
            for m in h_m:
                sc = m.get('score', '')
                if 'x' in sc:
                    try:
                        p = sc.split('x')
                        h_sc.append(int(p[0]))
                        h_con.append(int(p[1]))
                    except (ValueError, TypeError):
                        pass
            for m in a_m:
                sc = m.get('score', '')
                if 'x' in sc:
                    try:
                        p = sc.split('x')
                        a_sc.append(int(p[0]))
                        a_con.append(int(p[1]))
                    except (ValueError, TypeError):
                        pass
            if h_sc and a_con:
                raw_xg_h = (sum(h_sc) / len(h_sc) + sum(a_con) / len(a_con)) / 2.0
                xg_h = round(raw_xg_h * 1.08, 2)
            if a_sc and h_con:
                raw_xg_a = (sum(a_sc) / len(a_sc) + sum(h_con) / len(h_con)) / 2.0
                xg_a = round(raw_xg_a * 0.92, 2)

    # 3. Fallback Terciário: Extração de xG a partir do reasoning em texto
    if xg_h <= 0.1 or xg_a <= 0.1:
        m_h = re.search(r'\(Em Casa\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
        m_a = re.search(r'\(Fora\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
        if m_h:
            xg_h = float(m_h.group(1))
        if m_a:
            xg_a = float(m_a.group(1))

    if xg_h <= 0.1 or xg_a <= 0.1:
        reason_block = f"🛡️ [Gatekeeper AH NO_BET / Sem xG] Métricas de xG derivadas de U5J ausentes para {home_team} vs {away_team}. Abstenção mandatória."
        clean_gk = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason_block)
        compound_r = compose_compound_ah_reasoning(cursor, fixture_id, clean_gk, 'Sem Entrada (Abstenção)', home_team, away_team, fixture_dict.get('home_team_id'), fixture_dict.get('away_team_id'), reasoning)
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, compound_r, None, []

    # 1. Matriz de Poisson
    poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

    # 2. Resolução de Odds 1X2 de Mercado (Prioridade: Banco de Dados -> Cache -> The Odds API):
    odd_h = float(fixture_dict.get('odd_home') or 0.0)
    odd_a = float(fixture_dict.get('odd_away') or 0.0)
    odd_d = float(fixture_dict.get('odd_draw') or 0.0)

    # Camada 2: Payload da Casa Selecionada (Betano/Pinnacle/Bet365 via Bet ID 1) se banco estiver zerado
    if (odd_h <= 1.0 or odd_a <= 1.0) and fixture_id:
        b1x2 = _betano_ah_1x2_cache.get(fixture_id)
        if b1x2 and float(b1x2.get('casa', 0)) > 1.0 and float(b1x2.get('visitante', 0)) > 1.0:
            odd_h = float(b1x2['casa'])
            odd_a = float(b1x2['visitante'])
            odd_d = float(b1x2.get('empate', 0.0))
            if cursor:
                try:
                    bm_lbl = b1x2.get('bookmaker', 'Betano').capitalize()
                    cursor.execute("""
                        UPDATE fixtures_trends SET
                            odd_home = %s, casa_odd_home = %s,
                            odd_draw = %s, casa_odd_draw = %s,
                            odd_away = %s, casa_odd_away = %s,
                            updated_at = NOW()
                        WHERE fixture_id = %s AND (odd_home IS NULL OR odd_home <= 1.0)
                    """, (odd_h, bm_lbl, odd_d, bm_lbl, odd_a, bm_lbl, fixture_id))
                except Exception:
                    pass

    # Camada 3: Contingência The Odds API (quando API-Sports quota excedida e odds 1X2 ausentes no banco)
    if (odd_h <= 1.0 or odd_a <= 1.0):
        try:
            from sports_arbitrage import fetch_live_odds_from_api
            from football_ingest_trends import normalize_team_name, _is_team_match
            odds_api_key = os.environ.get('ODDS_API_KEY') or 'd2f79607e3832b1f4b3003c14da3d70f'
            toapi_matches = fetch_live_odds_from_api(odds_api_key, min_pre_match_minutes=0) or []
            h_norm = normalize_team_name(home_team)
            a_norm = normalize_team_name(away_team)
            matched_toapi = None
            for m in toapi_matches:
                sh = normalize_team_name(m.get('time_casa', ''))
                sa = normalize_team_name(m.get('time_visitante', ''))
                if (h_norm == sh and a_norm == sa) or (_is_team_match(home_team, m.get('time_casa', '')) and _is_team_match(away_team, m.get('time_visitante', ''))):
                    matched_toapi = m.get('odds', {})
                    break
            if matched_toapi:
                for pref in ['BETANO', 'PINNACLE', 'BET365', 'SPORTINGBET', 'BETFAIR']:
                    for bm_name, cota in matched_toapi.items():
                        if pref in bm_name.upper() and float(cota.get('casa', 0)) > 1.0 and float(cota.get('visitante', 0)) > 1.0:
                            odd_h = float(cota['casa'])
                            odd_a = float(cota['visitante'])
                            odd_d = float(cota.get('empate', 0.0))
                            break
                    if odd_h > 1.0 and odd_a > 1.0:
                        break
                if (odd_h <= 1.0 or odd_a <= 1.0) and matched_toapi:
                    first_bm = list(matched_toapi.keys())[0]
                    odd_h = float(matched_toapi[first_bm].get('casa', 0))
                    odd_a = float(matched_toapi[first_bm].get('visitante', 0))
                    odd_d = float(matched_toapi[first_bm].get('empate', 0))
                if odd_h > 1.0 and odd_a > 1.0 and cursor:
                    try:
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                               odd_home = %s, casa_odd_home = 'TheOddsAPI',
                               odd_draw = %s, casa_odd_draw = 'TheOddsAPI',
                               odd_away = %s, casa_odd_away = 'TheOddsAPI',
                               updated_at = NOW()
                            WHERE fixture_id = %s AND (odd_home IS NULL OR odd_home <= 1.0)
                        """, (odd_h, odd_d, odd_a, fixture_id))
                    except Exception:
                        pass
        except Exception:
            pass

    # NO_BET mandatário: apenas se as odds 1X2 estiverem nulas/zeradas
    if odd_h <= 1.0 or odd_a <= 1.0:
        reason_no_1x2 = (
            f"🛡️ [Gatekeeper AH NO_BET / Odds 1X2 Ausentes] Ausência de cotações 1X2 de mercado no banco, na Betano/Pinnacle e na The Odds API para {home_team} vs {away_team}. "
            f"Abstenção mandatória: odds nulas."
        )
        clean_gk = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason_no_1x2)
        compound_r = compose_compound_ah_reasoning(cursor, fixture_id, clean_gk, 'Sem Entrada (Abstenção)', home_team, away_team, fixture_dict.get('home_team_id'), fixture_dict.get('away_team_id'), reasoning)
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, compound_r, None, []

    # Calibração Estrutural de Paridade de Eficiência U5J e Mando de Campo:
    # Se a diferença de eficiência ponderada entre as duas equipes for estreita (|h_eff - a_eff| <= 2.0)
    # e o mandante for o favorito nas odds de mercado da Betano/1X2 (odd_h < odd_a),
    # o visitante NÃO pode ter xG de Poisson desproporcionalmente superior ao mandante.
    h_eff_calib = compute_team_u5j_efficiency(u_json.get('home') if isinstance(u_json, dict) else None)
    a_eff_calib = compute_team_u5j_efficiency(u_json.get('away') if isinstance(u_json, dict) else None)
    if abs(h_eff_calib - a_eff_calib) <= 2.0 and odd_h > 1.0 and odd_a > 1.0 and odd_h < odd_a:
        if xg_a > xg_h:
            xg_a = round(min(xg_a, xg_h * 0.95), 2)
            poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

    # 3. Obtenção de linhas ativas na Betano (Bookmaker ID 32):
    if betano_lines is None:
        if allow_api_fetch and fixture_id:
            betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
        else:
            betano_lines = []

    # Se a API da Betano estiver sem cota ou indisponível, mantém os dados do banco e gera as linhas a partir das odds do banco:
    if not betano_lines:
        betano_lines = build_fallback_lines_from_odds(
            home_team=home_team,
            away_team=away_team,
            odd_home=odd_h,
            odd_away=odd_a,
            ah_suggestion=fixture_dict.get('ah_suggestion'),
            home_team_id=fixture_dict.get('home_team_id'),
            away_team_id=fixture_dict.get('away_team_id')
        )

    if not betano_lines:
        reason_no_odds = f"🛡️ [Gatekeeper AH NO_BET / Sem Linhas Disponíveis] Linhas de Handicap Asiático indisponíveis para {home_team} vs {away_team}. Abstenção mandatória."
        clean_gk = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason_no_odds)
        compound_r = compose_compound_ah_reasoning(cursor, fixture_id, clean_gk, 'Sem Entrada (Abstenção)', home_team, away_team, fixture_dict.get('home_team_id'), fixture_dict.get('away_team_id'), reasoning)
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, compound_r, None, []

    # 3. Avaliação do Gatekeeper
    h_tid = fixture_dict.get('home_team_id')
    a_tid = fixture_dict.get('away_team_id')
    h_l5 = u_json.get('home') if isinstance(u_json, dict) else None
    a_l5 = u_json.get('away') if isinstance(u_json, dict) else None

    if h_l5 and isinstance(h_l5, dict):
        h_l5['pts_efficiency'] = compute_team_u5j_efficiency(h_l5)
        if analyze_trend_and_momentum:
            h_tr_info = analyze_trend_and_momentum(home_team, h_l5)
            h_l5['trend'] = h_tr_info.get('trend')
            h_l5['trend_desc'] = h_tr_info.get('trend_desc')
    if a_l5 and isinstance(a_l5, dict):
        a_l5['pts_efficiency'] = compute_team_u5j_efficiency(a_l5)
        if analyze_trend_and_momentum:
            a_tr_info = analyze_trend_and_momentum(away_team, a_l5)
            a_l5['trend'] = a_tr_info.get('trend')
            a_l5['trend_desc'] = a_tr_info.get('trend_desc')

    best_cand, approved = evaluate_and_select_best_ah_candidate(
        poisson_matrix, betano_lines, home_team, away_team, odd_h, odd_a,
        home_team_id=h_tid, away_team_id=a_tid,
        home_last5=h_l5, away_last5=a_l5,
        xg_home=xg_h, xg_away=xg_a
    )

    if not best_cand:
        sug = "Sem Entrada (Abstenção)"
        conf = 50.0
        # Diagnóstico contextual de cobertura e checagem de piso mínimo de odd
        fav_is_home = (odd_h < odd_a)
        fav_team = home_team if fav_is_home else away_team
        dog_team = away_team if fav_is_home else home_team
        fav_id = h_tid if fav_is_home else a_tid
        is_t1 = is_tier_1_elite_club(team_id=fav_id, team_name=fav_team)
        t1_str = " (Tier 1)" if is_t1 else ""

        # Verifica se a linha defensiva DNB (0.0 AH) do favorito existia na Betano mas foi reprovada por cotação deprimida ou curva/eficiência
        dnb_cand = next((c for c in betano_lines if c.get('line') == 0.0 and c.get('is_away') == (not fav_is_home)), None)
        dnb_odd = float(dnb_cand.get('odd') or 0.0) if dnb_cand else 0.0

        fav_l5 = h_l5 if fav_is_home else a_l5
        dog_l5 = a_l5 if fav_is_home else h_l5
        fav_eff = compute_team_u5j_efficiency(fav_l5)
        dog_eff = compute_team_u5j_efficiency(dog_l5)
        fav_tr_obj = analyze_trend_and_momentum(fav_team, fav_l5) if analyze_trend_and_momentum else {}
        dog_tr_obj = analyze_trend_and_momentum(dog_team, dog_l5) if analyze_trend_and_momentum else {}
        fav_trend = fav_tr_obj.get("trend", "CURVA_ESTAVEL")
        dog_trend = dog_tr_obj.get("trend", "CURVA_ESTAVEL")

        fav_pts = fav_l5.get('pts', 0) if (fav_l5 and isinstance(fav_l5, dict)) else 0
        dog_pts = dog_l5.get('pts', 0) if (dog_l5 and isinstance(dog_l5, dict)) else 0
        fav_in_form = (fav_pts >= 12 or fav_eff >= 9.0) and ((fav_eff - dog_eff) >= 3.0 or (fav_pts - dog_pts) >= 4)

        if fav_in_form:
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} -> "
                f"As odds 1x2 da casa apontam {fav_team}{t1_str} como favorito, respaldado por sua superioridade no U5J "
                f"({fav_eff:.1f} pts vs {dog_eff:.1f} pts do {dog_team}). As linhas conservadoras a favor do favorito (0.0 AH e -0.25 AH) "
                f"não atingiram os limiares mínimos de rentabilidade (+EV >= 5.0%), e as linhas defensivas no azarão foram terminantemente "
                f"bloqueadas por inferioridade técnica. Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team}. Abstenção mandatória."
            )
        elif dnb_cand and (fav_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA")) and (dog_eff > fav_eff or dog_trend == "CURVA_ASCENDENTE"):
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Queda de Rendimento e Eficiência] Partida {home_team} vs {away_team} -> "
                f"Favorito {fav_team}{t1_str} em momento desfavorável no U5J ({fav_trend}, {fav_eff:.1f} pts de eficiência ponderada) "
                f"contra {dog_team} ({dog_eff:.1f} pts de eficiência em {dog_trend}). "
                f"A linha seca DNB ({fav_team} 0.0 AH @ {dnb_odd:.2f}) foi terminantemente vetada pelo Gatekeeper por risco de momento/platô. "
                f"Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team}. Abstenção mandatória."
            )
        elif dnb_cand and dnb_odd < 1.50:
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Odd Abaixo do Piso] Partida {home_team} vs {away_team} -> "
                f"A linha segura DNB ({fav_team}{t1_str} 0.0 AH) está cotada a apenas @ {dnb_odd:.2f} na Betano, "
                f"abaixo do piso mínimo aceito de rentabilidade (@ 1.50). "
                f"Linhas de handicap negativo no favorito exigem perfil de Super-Favorito Tier 1 (odd <= 1.22) "
                f"e as linhas na zebra foram rejeitadas por EV negativo ou gestão de risco. "
                f"Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team}. Abstenção mandatória."
            )
        else:
            context_extra = ""
            if (odd_h <= 1.55) or (odd_a <= 1.55):
                context_extra = f" Favorito {fav_team}{t1_str} com odd nominal esmagada: linha DNB (0.0 AH) sem odd mínima (+EV) e linhas positivas bloqueadas por coerência de mercado."
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} ->{context_extra} "
                f"Nenhuma linha da Betano atingiu os limiares de rentabilidade (+EV >= 5.0%, Prob. Efetiva >= 48.0% ajustada por momento). "
                f"Eficiência U5J: {home_team} ({fav_eff if fav_is_home else dog_eff:.1f} pts) vs {away_team} ({dog_eff if fav_is_home else fav_eff:.1f} pts). "
                f"Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team}. Abstenção mandatória."
            )
        clean_gk = format_gatekeeper_result('NO_BET', sug, reason)
        compound_r = compose_compound_ah_reasoning(cursor, fixture_id, clean_gk, sug, home_team, away_team, h_tid, a_tid, reasoning)
        return 'NO_BET', sug, conf, compound_r, None, []

    eval_res = best_cand['eval']
    selected_palpite = best_cand['palpite_str']
    odd_val = best_cand['odd']
    odd_justa = eval_res['odd_justa']
    prob_poisson = eval_res['prob_eff']
    ev_perc = eval_res['ev_percent']
    conf = round(min(88.0, 55.0 + ev_perc * 0.5), 1)

    h_eff = compute_team_u5j_efficiency(h_l5)
    a_eff = compute_team_u5j_efficiency(a_l5)
    raw_calc = (
        f"🎯 GATEKEEPER AH APROVADO (+EV {ev_perc:+.1f}%) | "
        f"Odd Betano {odd_val:.2f} vs Odd Justa {odd_justa:.2f} (Prob. Efetiva: {prob_poisson:.1f}%) | "
        f"Eficiência U5J: {home_team} {h_eff:.1f} pts vs {away_team} {a_eff:.1f} pts | "
        f"Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team} | "
        f"Desfechos: Vitória {eval_res['p_win']:.1f}%, Meio-Green {eval_res['p_half_win']:.1f}%, "
        f"Push {eval_res['p_push']:.1f}%, Meio-Red {eval_res['p_half_loss']:.1f}%, Red {eval_res['p_loss']:.1f}%."
    )
    detalhe_calculo = format_gatekeeper_result('APROVADO', selected_palpite, raw_calc)

    compound_r = compose_compound_ah_reasoning(
        cursor=cursor,
        fixture_id=fixture_id,
        main_calc=detalhe_calculo,
        suggestion=selected_palpite,
        home_team=home_team,
        away_team=away_team,
        home_team_id=fixture_dict.get('home_team_id'),
        away_team_id=fixture_dict.get('away_team_id'),
        existing_reasoning=reasoning
    )

    # Avaliação de Destaque Sistêmico: Equipe Tier 1 de Elite contra Não-Tier 1 com baixo desempenho recente no U5J (<= 5 pts ou 0V)
    cand_team_name = best_cand.get('target_team') or best_cand.get('team') or (away_team if best_cand.get('is_away') else home_team)
    cand_team_id = fixture_dict.get('away_team_id') if cand_team_name == away_team else fixture_dict.get('home_team_id')
    opp_team_name = home_team if cand_team_name == away_team else away_team
    opp_team_id = fixture_dict.get('home_team_id') if cand_team_name == away_team else fixture_dict.get('away_team_id')

    is_cand_tier1 = is_tier_1_elite_club(team_id=cand_team_id, team_name=cand_team_name)
    is_opp_tier1 = is_tier_1_elite_club(team_id=opp_team_id, team_name=opp_team_name)
    opp_l5_data = h_l5 if cand_team_name == away_team else a_l5
    opp_pts = opp_l5_data.get('pts', 0) if isinstance(opp_l5_data, dict) else 0
    opp_v = opp_l5_data.get('v', 0) if isinstance(opp_l5_data, dict) else 0

    is_destaque = 1 if (is_cand_tier1 and not is_opp_tier1 and (opp_pts <= 5 or opp_v == 0)) else 0
    best_cand['destaque'] = is_destaque

    return 'APROVADO', selected_palpite, conf, compound_r, best_cand, approved


def get_team_u5j_from_db(cursor, team_id, team_name):
    """
    Busca no MySQL local os últimos 5 jogos FT consolidados de uma equipe para alimentar o card U5J.
    Prioridade Absoluta (Regra 1): Cache-First na tabela team_last5_cache (TTL 72 horas).
    Fallback seguro na tabela fixtures_trends se o cache estiver ausente ou incompleto.
    """
    matches = []
    seen = set()

    # 1. Prioridade Absoluta: team_last5_cache com TTL de 72 horas (Regra 1)
    if cursor and team_id:
        try:
            cursor.execute("""
                SELECT form_json FROM team_last5_cache 
                WHERE team_id = %s AND updated_at >= NOW() - INTERVAL 72 HOUR
                LIMIT 1
            """, (team_id,))
            c_row = cursor.fetchone()
            if c_row and c_row.get('form_json'):
                c_matches = json.loads(c_row['form_json']) if isinstance(c_row['form_json'], str) else c_row['form_json']
                if isinstance(c_matches, list) and len(c_matches) > 0:
                    for cm in c_matches:
                        c_opp = cm.get('opponent')
                        c_sc = str(cm.get('score', '')).strip().replace('-', 'x')
                        c_dt = str(cm.get('date', '')).strip()
                        if '/' in c_dt and len(c_dt) > 5:
                            c_dt = '/'.join(c_dt.split('/')[:2])
                        matches.append({
                            "opponent": c_opp,
                            "score": c_sc,
                            "result": cm.get('result'),
                            "is_home": cm.get('is_home'),
                            "date": c_dt,
                            "fixture_id": cm.get('fixture_id')
                        })
                        if len(matches) >= 5:
                            break
        except Exception as e_cache:
            print(f"⚠️ [U5J Cache Fetch] Erro ao consultar team_last5_cache para '{team_name}' (#{team_id}): {e_cache}")

    # 2. Fallback na tabela fixtures_trends se o cache tiver menos de 5 partidas
    if len(matches) < 5 and cursor and team_id:
        try:
            cursor.execute("""
                SELECT fixture_id, fixture_date, home_team, away_team, goals_home, goals_away, home_team_id, away_team_id, league_id, league_name
                FROM fixtures_trends
                WHERE status IN ('FT', 'AET', 'PEN')
                  AND goals_home IS NOT NULL
                  AND goals_away IS NOT NULL
                  AND (home_team_id = %s OR away_team_id = %s)
                  AND (league_id NOT IN (667, 10) AND (league_name IS NULL OR (LOWER(league_name) NOT LIKE '%%friendl%%' AND LOWER(league_name) NOT LIKE '%%amistoso%%')))
                ORDER BY fixture_date DESC
                LIMIT 15
            """, (team_id, team_id))
            for r in cursor.fetchall():
                fid = r.get('fixture_id')
                if fid in seen:
                    continue
                seen.add(fid)
                is_home = (int(r['home_team_id']) == int(team_id)) if r.get('home_team_id') else (str(r.get('home_team') or '').lower() == str(team_name or '').lower())
                gh = int(r['goals_home']) if r.get('goals_home') is not None else 0
                ga = int(r['goals_away']) if r.get('goals_away') is not None else 0
                opp = r.get('away_team') if is_home else r.get('home_team')
                fdate = r.get('fixture_date')
                dt_str = fdate.strftime("%d/%m") if hasattr(fdate, 'strftime') else (str(fdate)[:10] if fdate else "")
                if is_home:
                    res = "V" if gh > ga else ("E" if gh == ga else "D")
                    sc = f"{gh}x{ga}"
                else:
                    res = "V" if ga > gh else ("E" if ga == gh else "D")
                    sc = f"{ga}x{gh}"

                # Checagem anti-duplicidade com itens já em matches
                is_dup = False
                for m in matches:
                    m_dt = str(m.get('date', '')).strip()
                    m_sc = str(m.get('score', '')).strip().replace('-', 'x')
                    m_opp = str(m.get('opponent', '')).strip().lower()
                    if dt_str and m_dt and (dt_str == m_dt or dt_str.startswith(m_dt) or m_dt.startswith(dt_str)):
                        is_dup = True
                        break
                    if m_opp == str(opp).strip().lower() and m_sc and sc and m_sc == sc:
                        is_dup = True
                        break
                if not is_dup:
                    matches.append({
                        "opponent": opp,
                        "score": sc,
                        "result": res,
                        "is_home": is_home,
                        "date": dt_str,
                        "fixture_id": fid
                    })
                if len(matches) >= 5:
                    break
        except Exception as e:
            print(f"⚠️ [U5J DB Fetch] Erro ao buscar últimos 5 jogos de '{team_name}' (#{team_id}): {e}")

    num_v = sum(1 for m in matches if m.get("result") == "V")
    num_e = sum(1 for m in matches if m.get("result") == "E")
    num_d = sum(1 for m in matches if m.get("result") == "D")
    pts = (num_v * 3) + num_e

    pts_eff = 0.0
    tier1_cnt = 0
    for m in matches:
        opp_name = m.get("opponent", "")
        is_t1 = is_tier_1_elite_club(team_name=opp_name)
        m["is_tier_1"] = is_t1
        if is_t1:
            tier1_cnt += 1
        res = (m.get("result") or "").upper()
        if res == "V":
            pts_eff += 5.0 if is_t1 else 3.0
        elif res == "E":
            pts_eff += 2.0 if is_t1 else 1.0
        elif res == "D":
            pts_eff += 0.0 if is_t1 else -1.0

    txt = f"{num_v}V-{num_e}E-{num_d}D ({pts} pts | {pts_eff:.1f} pts ef.)" if matches else "Não localizado (0 pts)"
    return {
        "v": num_v,
        "e": num_e,
        "d": num_d,
        "pts": pts,
        "pts_efficiency": round(pts_eff, 1),
        "tier1_opponents": tier1_cnt,
        "text": txt,
        "matches": matches
    }


def build_natural_language_explanation(suggestion, home_team, away_team):
    """
    Gera a explicação detalhada em linguagem natural para o card do dashboard.
    """
    sug_str = str(suggestion or '')
    if "0.0" in sug_str or "Empate Anula" in sug_str or "+00" in sug_str or "+ 00" in sug_str:
        if away_team.lower() in sug_str.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da simulação de aposta (Lucro Total).\n"
            f"⚪ Empate: Simulação de Aposta ANULADA (100% Reembolso).\n"
            f"🔴 Vitória do {team_opp}: Simulação de Aposta PERDIDA."
        )
    elif "+0.5" in sug_str:
        if away_team.lower() in sug_str.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav} ou Empate: Você GANHA 100% da aposta (Dupla Chance).\n"
            f"🔴 Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "-0.5" in sug_str:
        if away_team.lower() in sug_str.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Você GANHA 100% da aposta (Vitória Simples).\n"
            f"🔴 Empate ou Vitória do {team_opp}: Aposta PERDIDA."
        )
    elif "+0.75" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "-0.75" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "+1.0" in sug_str or "+1 " in sug_str or "+1 AH" in sug_str or "+1.00" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "-1.0" in sug_str or "-1 " in sug_str or "-1 AH" in sug_str or "-1.00" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "+1.25" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "+1.5" in sug_str:
        if away_team.lower() in sug_str.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}, Empate ou Derrota por 1 gol exato: Você GANHA 100% da aposta.\n"
            f"🔴 Derrota do {team_fav} por 2 ou mais gols: Aposta PERDIDA."
        )
    elif "-0.25" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    elif "+0.25" in sug_str:
        if away_team.lower() in sug_str.lower():
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
    else:
        if away_team.lower() in sug_str.lower():
            team_fav = away_team
            team_opp = home_team
        else:
            team_fav = home_team
            team_opp = away_team
        return (
            f"🟢 Vitória do {team_fav}: Aposta Coberta.\n"
            f"⚪ Empate: Devolução ou ajuste conforme a linha.\n"
            f"🔴 Vitória do {team_opp}: Aposta Perdida."
        )


def generate_high_level_ah_narrative(
    suggestion: str,
    home_team: str,
    away_team: str,
    main_calc: str = "",
    h_u5j: dict = None,
    a_u5j: dict = None,
    odd_home: float = None,
    odd_away: float = None
) -> str:
    """
    Gera uma descrição objetiva e de alto nível para a saída do Gatekeeper.
    Explica a relação entre as odds 1X2 da casa, a pontuação dos últimos 5 jogos (U5J)
    e a razão estratégica pela qual a linha de handicap foi indicada com sua respectiva cobertura.
    """
    sug_clean = (suggestion or "").strip()
    is_abstencao = any(w in sug_clean.lower() for w in ['abstenção', 'abstencao', 'sem entrada', 'bloquead', 'no_bet'])

    # 1. Caso Abstenção / NO_BET
    if is_abstencao:
        if "Gatekeeper AH NO_BET" in main_calc:
            return main_calc.strip()
        h_eff = (h_u5j or {}).get("pts_efficiency") or (h_u5j or {}).get("pts", 0.0)
        a_eff = (a_u5j or {}).get("pts_efficiency") or (a_u5j or {}).get("pts", 0.0)
        fav_t = home_team if (odd_home and odd_away and float(odd_home) < float(odd_away)) else (away_team if (odd_home and odd_away and float(odd_away) < float(odd_home)) else None)
        und_t = away_team if fav_t == home_team else home_team
        fav_e = h_eff if fav_t == home_team else a_eff
        und_e = a_eff if fav_t == home_team else h_eff
        if fav_t and fav_e >= 9.0 and (fav_e - und_e) >= 3.0:
            return (
                f"STATUS GK: NO_BET\n"
                f"SUGGESTION: Sem Entrada (Abstenção)\n"
                f"REASON: 🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} -> "
                f"As odds 1x2 da casa apontam {fav_t} como favorito, respaldado por superioridade nos últimos 5 jogos "
                f"({fav_e:.1f} pts vs {und_e:.1f} pts do {und_t}). As linhas a favor do favorito (0.0 AH e -0.25 AH) "
                f"não atingiram os limiares de rentabilidade (+EV >= 5.0%), e linhas a favor do azarão foram bloqueadas por inferioridade técnica. Abstenção mandatória."
            )
        return (
            f"STATUS GK: NO_BET\n"
            f"SUGGESTION: Sem Entrada (Abstenção)\n"
            f"REASON: 🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} -> "
            f"Nenhuma linha da Betano atingiu os limiares de rentabilidade (+EV >= 5.0%, Prob. Efetiva >= 48.0% ajustada por momento). "
            f"Eficiência U5J: {home_team} ({h_eff:.1f} pts) vs {away_team} ({a_eff:.1f} pts). Abstenção mandatória."
        )

    # 2. Caso APROVADO (Palpite com Linha)
    prob_eff = 50.0
    m_prob = re.search(r'Prob\.\s*Efetiva:\s*([\d\.]+)%', main_calc)
    if m_prob:
        prob_eff = float(m_prob.group(1))

    # Eficiência U5J
    h_eff = (h_u5j or {}).get("pts_efficiency") or (h_u5j or {}).get("pts", 0.0)
    a_eff = (a_u5j or {}).get("pts_efficiency") or (a_u5j or {}).get("pts", 0.0)
    if h_eff == 0.0 and a_eff == 0.0:
        m_u5j = re.search(r'Eficiência U5J:\s*[^0-9]+([\d\.]+)\s*pts\s*vs\s*[^0-9]+([\d\.]+)\s*pts', main_calc)
        if m_u5j:
            h_eff = float(m_u5j.group(1))
            a_eff = float(m_u5j.group(2))

    # Identifica time apostado
    is_home_bet = home_team.lower() in sug_clean.lower()
    backed_team = home_team if is_home_bet else away_team
    opp_team = away_team if is_home_bet else home_team
    backed_eff = h_eff if is_home_bet else a_eff
    opp_eff = a_eff if is_home_bet else h_eff

    # Identifica favorito pelas odds 1X2 da casa
    fav_team = None
    und_team = None
    try:
        oh = float(odd_home) if odd_home else None
        oa = float(odd_away) if odd_away else None
        if oh and oa:
            if oh < oa:
                fav_team = home_team
                und_team = away_team
            elif oa < oh:
                fav_team = away_team
                und_team = home_team
    except Exception:
        pass

    if not fav_team:
        if "-" in sug_clean:
            fav_team = backed_team
            und_team = opp_team
        elif "+" in sug_clean:
            fav_team = opp_team
            und_team = backed_team
        else:
            fav_team = home_team
            und_team = away_team

    # Cobertura descritiva da linha de handicap
    if "+0.75" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, cobrindo vitória, empate e garantindo meio-reembolso em caso de derrota mínima por 1 gol"
    elif "+0.5" in sug_clean or "+0.50" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de retorno tanto na vitória quanto no empate (Dupla Chance)"
    elif "+0.25" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, com ganho total na vitória e meio-green (50% de lucro + devolução da stake) em caso de empate"
    elif "0.0" in sug_clean or "dnb" in sug_clean.lower():
        cov_text = f"oferece {prob_eff:.0f}% de cobertura efetiva, garantindo lucro na vitória e reembolso integral de 100% da banca em caso de empate"
    elif "-0.25" in sug_clean:
        cov_text = f"oferece {prob_eff:.0f}% de probabilidade efetiva de vitória com proteção de retorno parcial (apenas meio-red) em caso de empate"
    elif "-0.5" in sug_clean or "-0.50" in sug_clean:
        cov_text = f"oferece {prob_eff:.0f}% de probabilidade efetiva de vitória direta"
    elif "-0.75" in sug_clean:
        cov_text = f"garante retorno integral na vitória por 2+ gols e meio-green na vitória simples, com {prob_eff:.0f}% de cobertura calculada"
    elif "+1.0" in sug_clean or "+1.00" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, cobrindo vitória e empate com 100% de ganho e reembolso total em caso de derrota por 1 gol"
    else:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"

    diff_eff = abs(h_eff - a_eff)

    # Cenário 1: Casa dá favoritismo a um time, mas pontuações U5J são muito aproximadas (Exemplo Palestino)
    if fav_team and fav_team != backed_team and diff_eff <= 2.5:
        role_label = "o azarão " if und_team == backed_team else ""
        narrative = (
            f"As odds 1x2 da casa de aposta dão favoritismo ao {fav_team}, porém com base na análise "
            f"dos resultados dos últimos 5 jogos os 2 times têm pontuação muito aproximada ({home_team} {h_eff:.1f} pts vs {away_team} {a_eff:.1f} pts). "
            f"Para estes casos, entende-se que a linha de proteção com cobertura para {role_label}{sug_clean} {cov_text}."
        )
    # Cenário 2: Favorito convergente com superioridade comprovada (odds + U5J)
    elif fav_team == backed_team and (backed_eff >= 9.0 or (backed_eff - opp_eff) >= 2.5) and backed_eff > opp_eff:
        if "0.0" in sug_clean or "dnb" in sug_clean.lower():
            cov_desc = f"oferece {prob_eff:.0f}% de cobertura efetiva, garantindo 100% de ganho na vitória e reembolso integral de 100% da banca em caso de empate"
        elif "-0.25" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória e proteção com perda atenuada de apenas 50% em caso de empate"
        elif "-0.5" in sug_clean or "-0.50" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória direta"
        elif "-0.75" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória por 2+ gols e meio-ganho na vitória simples por 1 gol"
        elif "+0.25" in sug_clean or "+0.5" in sug_clean or "+0.75" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"
        else:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"

        narrative = (
            f"As odds 1x2 da casa de aposta apontam corretamente o {backed_team} como favorito, "
            f"respaldado por sua notável superioridade de desempenho nos últimos 5 jogos "
            f"({backed_eff:.1f} pts vs {opp_eff:.1f} pts do {opp_team}). "
            f"A seleção {sug_clean} {cov_desc}."
        )
    # Cenário 3: Casa aponta o adversário, mas time apostado tem momento U5J nitidamente superior (Valor no azarão)
    elif fav_team != backed_team and backed_eff > opp_eff:
        narrative = (
            f"Embora as odds 1x2 da casa de aposta apontem o {fav_team} como favorito, a análise "
            f"dos últimos 5 jogos revela momento superior do {backed_team} ({backed_eff:.1f} pts vs {opp_eff:.1f} pts). "
            f"Para este cenário de distorção, a linha de proteção {sug_clean} {cov_text}."
        )
    # Cenário 4: Confronto parelho ou time apostado com ligeira vantagem
    else:
        narrative = (
            f"Confronto com forças equilibradas nas cotações 1x2 e na pontuação dos últimos 5 jogos "
            f"({home_team} {h_eff:.1f} pts vs {away_team} {a_eff:.1f} pts). Diante do equilíbrio técnico, "
            f"a indicação estratégica {sug_clean} {cov_text}."
        )

    return narrative


def compose_compound_ah_reasoning(
    cursor,
    fixture_id: int,
    main_calc: str,
    suggestion: str,
    home_team: str,
    away_team: str,
    home_team_id: int = None,
    away_team_id: int = None,
    existing_reasoning: str = None
) -> str:
    """
    Constrói e garante a integridade do formato composto de fixtures_trends.ah_reasoning:
    f"{main_calc} || EXPLICACAO: {nl_exp} || MOTIVACAO: {nl_mot} || MEMÓRIA DE CÁLCULO || {calc_details} || U5J_DATA: {u5j_json_str}"

    Preserva rigorosamente a lista de jogos consolidados (U5J) e explicações em linguagem natural,
    reconstruindo o payload JSON via MySQL caso tenha sido corrompido ou sobrescrito.
    """
    u5j_json_str = None
    existing_motivation = None
    h_u5j = None
    a_u5j = None

    # Prioridade Absoluta (Regra 1): Sempre consulta a fonte mais atualizada via get_team_u5j_from_db (Cache-First MySQL)
    if cursor:
        if not home_team_id or not away_team_id:
            try:
                cursor.execute("SELECT home_team_id, away_team_id FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
                row_f = cursor.fetchone()
                if row_f:
                    home_team_id = home_team_id or row_f.get("home_team_id")
                    away_team_id = away_team_id or row_f.get("away_team_id")
            except Exception:
                pass

        h_u5j = get_team_u5j_from_db(cursor, home_team_id, home_team)
        a_u5j = get_team_u5j_from_db(cursor, away_team_id, away_team)
        if h_u5j.get("matches") or a_u5j.get("matches"):
            u5j_json_str = json.dumps({"home": h_u5j, "away": a_u5j}, ensure_ascii=False)

    if not u5j_json_str and existing_reasoning and "|| U5J_DATA:" in existing_reasoning:
        try:
            u_part = existing_reasoning.split("|| U5J_DATA:")[1].split("||")[0].strip()
            parsed_u = json.loads(u_part)
            if (parsed_u.get("home", {}).get("matches") or parsed_u.get("away", {}).get("matches")):
                u5j_json_str = u_part
                h_u5j = h_u5j or parsed_u.get("home", {})
                a_u5j = a_u5j or parsed_u.get("away", {})
        except Exception:
            u5j_json_str = None

    if existing_reasoning:
        if "|| MOTIVACAO:" in existing_reasoning:
            try:
                cand_mot = existing_reasoning.split("|| MOTIVACAO:")[1].split("||")[0].strip()
                is_sub_abstencao = any(w in str(suggestion).lower() for w in ['abstenção', 'abstencao', 'sem entrada', 'bloquead', 'no_bet'])
                is_mot_abstencao = any(w in cand_mot.lower() for w in ['abstenção', 'bloqueada', 'incerteza', 'proteção de banca: entrada de handicap bloqueada', 'sem entrada'])
                # Só reaproveita a motivação se a natureza semântica for estritamente idêntica (ambas abstenção ou ambas aprovadas)
                if is_sub_abstencao == is_mot_abstencao:
                    existing_motivation = cand_mot
                else:
                    existing_motivation = None
            except Exception:
                existing_motivation = None

    # Descarta descrições genéricas antigas que não explicam o motivo do palpite ou textos de equilíbrio incorretos
    generic_phrases = [
        "alinhamento estatístico da modelagem poisson",
        "alinhamento estatistico da modelagem poisson",
        "alinhamento de eficiência ponderada (sos)"
    ]
    if existing_motivation:
        if any(gp in existing_motivation.lower() for gp in generic_phrases):
            existing_motivation = None
        elif "confronto com forças equilibradas" in existing_motivation.lower():
            h_eff_val = (h_u5j or {}).get("pts_efficiency") or (h_u5j or {}).get("pts", 0.0)
            a_eff_val = (a_u5j or {}).get("pts_efficiency") or (a_u5j or {}).get("pts", 0.0)
            if abs(h_eff_val - a_eff_val) >= 2.5:
                existing_motivation = None

    if not u5j_json_str:
        u5j_json_str = json.dumps({
            "home": {"v": 0, "e": 0, "d": 0, "pts": 0, "text": "Aguardando", "matches": []},
            "away": {"v": 0, "e": 0, "d": 0, "pts": 0, "text": "Aguardando", "matches": []}
        }, ensure_ascii=False)

    nl_exp = build_natural_language_explanation(suggestion, home_team, away_team)
    if not existing_motivation:
        odd_home, odd_away = None, None
        if cursor and fixture_id:
            try:
                cursor.execute("SELECT odd_home, odd_away FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
                r_odds = cursor.fetchone()
                if r_odds:
                    odd_home = r_odds.get("odd_home")
                    odd_away = r_odds.get("odd_away")
            except Exception:
                pass
        nl_mot = generate_high_level_ah_narrative(
            suggestion=suggestion,
            home_team=home_team,
            away_team=away_team,
            main_calc=main_calc,
            h_u5j=h_u5j,
            a_u5j=a_u5j,
            odd_home=odd_home,
            odd_away=odd_away
        )
    else:
        nl_mot = existing_motivation

    calc_details = main_calc

    full_reasoning = f"{main_calc} || EXPLICACAO: {nl_exp} || MOTIVACAO: {nl_mot} || MEMÓRIA DE CÁLCULO || {calc_details} || U5J_DATA: {u5j_json_str}"
    return full_reasoning


def sync_fixture_and_bet_handicap(
    cursor,
    fixture_id: int,
    home_team: str,
    away_team: str,
    fixture_date,
    selected_palpite: str,
    odd_val: float,
    odd_justa: float,
    prob_poisson: float,
    ev_perc: float,
    detalhe_calculo: str,
    user_ids: list,
    confirmada_val: int = 0,
    destaque_val: int = 0
):
    """
    Sincroniza atômica e simultaneamente o Card (fixtures_trends) e a Aposta (apostas).
    TRAVA MANDATÓRIA DE PROTEÇÃO FINANCEIRA:
    - Se qualquer aposta para o usuário tiver confirmada = 1 ou constar débito em conta corrente (DEBITO_APOSTA),
      a aposta é MANTIDA INTACTA (imutável) e não é sobrescrita.
    - Se a aposta for pendente (não confirmada e sem débito), atualiza a aposta E atualiza fixtures_trends.
    """
    valor_aposta = 10.00
    ganhos_potenciais = round(valor_aposta * odd_val, 2)
    has_confirmed_bet = False

    for uid in user_ids:
        cursor.execute("""
            SELECT a.id, a.palpite, a.confirmada,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas a
            WHERE a.fixture_id = %s AND a.usuario_id = %s AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
        """, (fixture_id, uid))
        ja_existe = cursor.fetchone()

        if ja_existe:
            tem_debito = (int(ja_existe.get('tem_debito') or 0) > 0)
            is_conf = (int(ja_existe.get('confirmada') or 0) == 1) or tem_debito
            if is_conf:
                has_confirmed_bet = True
                print(f"🔒 [Aposta Confirmada Mantida User #{uid}] ID #{ja_existe['id']} com confirmação/débito financeiro mantida intacta.")
                continue

            # Atualizar aposta pendente não confirmada
            cursor.execute("""
                UPDATE apostas SET
                    palpite = %s,
                    odd = %s,
                    odd_justa = %s,
                    probabilidade_poisson = %s,
                    ev_percentual = %s,
                    status_gatekeeper = 'APROVADO',
                    ganhos_potenciais = %s,
                    resultado_detalhado = %s,
                    destaque = %s,
                    status = 'Pendente',
                    updated_at = NOW()
                WHERE id = %s
            """, (selected_palpite, odd_val, odd_justa, prob_poisson, ev_perc, ganhos_potenciais, detalhe_calculo, destaque_val, ja_existe['id']))
            print(f"🔄 [Aposta AH Atualizada User #{uid}] ID #{ja_existe['id']} | Palpite: '{selected_palpite}' @ {odd_val:.2f}")
        else:
            # Inserir nova aposta
            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    odd_justa, probabilidade_poisson, ev_percentual,
                    valor_aposta, ganhos_potenciais, status_gatekeeper, status, confirmada, destaque, data_hora_jogo, resultado_detalhado, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Handicap Asiático', %s, %s,
                    %s, %s, %s,
                    %s, %s, 'APROVADO', 'Pendente', %s, %s, %s, %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, selected_palpite, odd_val,
                odd_justa, prob_poisson, ev_perc,
                valor_aposta, ganhos_potenciais, confirmada_val, destaque_val, fixture_date,
                detalhe_calculo
            ))
            aposta_id = cursor.lastrowid
            print(f"🟢 [Aposta AH Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{selected_palpite}' @ {odd_val:.2f}")

            if confirmada_val == 1:
                cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
                u_row = cursor.fetchone()
                s_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
                s_post = round(s_ant - valor_aposta, 2)
                desc_deb = f"Débito Aposta #{aposta_id} ({home_team} x {away_team} - {selected_palpite})"
                cursor.execute("""
                    INSERT INTO conta_corrente (
                        usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                    ) VALUES (
                        %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, NOW()
                    )
                """, (uid, aposta_id, desc_deb, -valor_aposta, s_ant, s_post))
                cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))

    # Sincroniza fixtures_trends com o palpite aprovado (card sempre alinhado com a aposta aprovada)
    cursor.execute("SELECT ah_reasoning, home_team_id, away_team_id FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
    cur_f = cursor.fetchone()
    existing_r = cur_f.get("ah_reasoning") if cur_f else None
    h_tid = cur_f.get("home_team_id") if cur_f else None
    a_tid = cur_f.get("away_team_id") if cur_f else None

    compound_reasoning = compose_compound_ah_reasoning(
        cursor=cursor,
        fixture_id=fixture_id,
        main_calc=detalhe_calculo,
        suggestion=selected_palpite,
        home_team=home_team,
        away_team=away_team,
        home_team_id=h_tid,
        away_team_id=a_tid,
        existing_reasoning=existing_r
    )

    b1x2 = _betano_ah_1x2_cache.get(fixture_id)
    if b1x2 and b1x2.get('casa') and b1x2.get('empate') and b1x2.get('visitante'):
        bm_label = b1x2.get('bookmaker', 'Betano').capitalize()
        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                odd_home = %s,
                casa_odd_home = %s,
                odd_draw = %s,
                casa_odd_draw = %s,
                odd_away = %s,
                casa_odd_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (
            selected_palpite, prob_poisson, compound_reasoning,
            b1x2['casa'], bm_label,
            b1x2['empate'], bm_label,
            b1x2['visitante'], bm_label,
            fixture_id
        ))
        print(f"🔗 [Sincronismo Card AH & 1X2] fixtures_trends #{fixture_id} sincronizado com '{selected_palpite}' e Odds 1X2 {bm_label} ({b1x2['casa']}/{b1x2['empate']}/{b1x2['visitante']}).")
    else:
        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (selected_palpite, prob_poisson, compound_reasoning, fixture_id))
        print(f"🔗 [Sincronismo Card AH] fixtures_trends #{fixture_id} sincronizado com '{selected_palpite}'.")


def cancelar_e_estornar_aposta_handicap(cursor, fixture_id, motivo="Abstenção da IA / Gestão de Risco"):
    """
    Busca apostas pendentes no mercado de Handicap Asiático para o fixture_id.
    Altera o status para 'Cancelada' e, se a aposta tiver débito em conta corrente (DEBITO_APOSTA),
    efetua o estorno financeiro (ESTORNO_APOSTA) atualizando o saldo do usuário.
    Retorna lista de dicionários com detalhes das apostas canceladas/estornadas.
    """
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

    canceladas_detalhes = []
    for aposta in apostas_pendentes:
        aposta_id = aposta['id']
        usuario_id = aposta['usuario_id']
        valor = float(aposta['valor_aposta'] or 0.0)

        # Checagem de segurança: Aposta confirmada pelo usuário jamais é cancelada automaticamente pela DAG
        is_confirmada = (int(aposta.get('confirmada') or 0) == 1) or (int(aposta.get('tem_debito') or 0) > 0)
        if is_confirmada:
            print(f"🔒 [Aposta Confirmada Mantida] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} é aposta confirmada pelo usuário. Cancelamento automático ignorado.")
            continue

        # Obter cotações 1X2 para descrição natural e clara
        cursor.execute("SELECT odd_home, odd_draw, odd_away FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
        f_row = cursor.fetchone() or {}
        oh = float(f_row.get('odd_home') or 0.0)
        od = float(f_row.get('odd_draw') or 0.0)
        oa = float(f_row.get('odd_away') or 0.0)
        if oh > 0 and od > 0 and oa > 0:
            human_desc = (
                f"A inteligência artificial analisou a partida ({aposta['time_casa']} vs {aposta['time_fora']}) "
                f"e as cotações de mercado 1X2 (Casa: {oh:.2f}, Empate: {od:.2f}, Fora: {oa:.2f}), "
                f"porém a gestão de risco ativou o bloqueio preventivo (Abstenção da IA) no Handicap Asiático "
                f"por ausência de margem de segurança matemática."
            )
        else:
            human_desc = (
                f"A inteligência artificial analisou a partida ({aposta['time_casa']} vs {aposta['time_fora']}), "
                f"porém a gestão de risco ativou o bloqueio preventivo (Abstenção da IA) no Handicap Asiático "
                f"por ausência de margem de segurança matemática."
            )

        cursor.execute("""
            UPDATE apostas 
            SET status = 'Cancelada', 
                status_gatekeeper = 'NO_BET',
                palpite = 'Sem Entrada (Abstenção)',
                resultado_detalhado = %s, 
                updated_at = NOW() 
            WHERE id = %s
        """, (human_desc, aposta_id))

        estornado = False
        saldo_posterior = None

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

                estornado = True
                print(f"💰 [Estorno Efetivado] Aposta #{aposta_id} User #{usuario_id} | R$ {valor:.2f} estornado (Novo Saldo: R$ {saldo_posterior:.2f})")

        detail = dict(aposta)
        detail['motivo'] = motivo
        detail['estornado'] = estornado
        detail['saldo_posterior'] = saldo_posterior
        canceladas_detalhes.append(detail)

        print(f"🚫 [Aposta Handicap Cancelada] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} -> Motivo: {motivo}")

    return canceladas_detalhes
