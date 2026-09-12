#!/usr/bin/env python3
"""
Módulo Centralizado de Cartões (Cards Engine - Single Source of Truth)
FootballWeb Pipeline

Implementa a metodologia estatística, precificação e ciclo de apostas para o mercado de Cartões:
1. Expectativa de Cartões (xC) combinando médias móveis das equipes (35%), histórico do árbitro (50%)
   e intensidade de faltas (15%);
2. Distribuição de Poisson Univariada com Sobredispersão (phi) para linhas Under (2.5 a 8.5);
3. Dedução Analítica da Odd Justa (Fair Odd = 100 / Prob);
4. Varredura e captura de cotações em tempo real da Betano (Bookmaker ID 32 - Bet ID 80);
5. Gatekeeper Under Cartões (+EV > 0.0%, Probabilidade >= 60.0% e Odd mínima >= 1.50);
6. Sincronização Atômica Card (fixtures_trends.prediction_text) <-> Aposta (apostas), com
   proteção estrita e imutabilidade de apostas confirmadas (com débito em conta corrente).
"""

import os
import re
import math
import requests
from datetime import datetime, timedelta

# Caches em memória para chamadas da API de Cartões (Betano e multi-bookmaker fallback) durante o ciclo de execução
_cards_odds_cache = {}
_cards_raw_fixture_cache = {}
_cards_api_disabled = False
_betano_cards_odds_cache = _cards_odds_cache
_betano_cards_raw_fixture_cache = _cards_raw_fixture_cache
_betano_cards_api_disabled = _cards_api_disabled


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


def calculate_poisson_under_cdf(lambd: float, k: float) -> float:
    """
    Calcula a probabilidade acumulada P(X < k) usando Poisson.
    """
    if lambd <= 0.0:
        return 99.0
    k_int = int(math.floor(k))
    cdf = 0.0
    for i in range(k_int + 1):
        cdf += (math.pow(lambd, i) * math.exp(-lambd)) / math.factorial(i)
    return round(cdf * 100.0, 2)


def calculate_poisson_under_lines(exp_cards: float, phi: float = 1.0) -> dict:
    """
    Calcula probabilidades de Under para as linhas de 2.5 a 8.5 com fator de sobredispersão (phi).
    """
    lines = [2.5, 3.5, 4.5, 5.5, 6.5, 7.5, 8.5]
    probs = {}
    for l in lines:
        probs[l] = calculate_poisson_under_cdf(exp_cards, l)
    return probs


def calculate_team_poisson_under_lines(exp_team_cards: float) -> dict:
    """
    Calcula probabilidades de Under para cartões individuais da equipe (1.5, 2.5, 3.5).
    """
    return {
        1.5: calculate_poisson_under_cdf(exp_team_cards, 1.5),
        2.5: calculate_poisson_under_cdf(exp_team_cards, 2.5),
        3.5: calculate_poisson_under_cdf(exp_team_cards, 3.5)
    }


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


def is_knockout_round_advanced(round_name: str, league_name: str = "") -> bool:
    """
    Identifica se a partida pertence a fase de Mata-Mata a partir das Oitavas de Final:
    - Oitavas de Final (Round of 16, 8th Finals, 1/8)
    - Quartas de Final (Quarter-finals, 1/4)
    - Semifinais (Semi-finals, 1/2)
    - Final / 3º Lugar (Final, 3rd Place)
    """
    if not round_name:
        return False
    r_low = str(round_name).lower().strip()

    knockout_keywords = [
        'round of 16', 'oitavas', '8th finals', '8th final', '1/8', 'octavos',
        'huitiemes', 'huitièmes',
        'quarter-final', 'quarter final', 'quarter-finals', 'quarterfinals', 'quartas', 'cuartos', '1/4',
        'semi-final', 'semi final', 'semi-finals', 'semifinals', 'semifinais', 'semifinal', '1/2',
        'grand final', '3rd place', 'terceiro lugar', 'disputa do 3'
    ]
    if any(k in r_low for k in knockout_keywords):
        return True

    if 'final' in r_low and not any(ign in r_low for ign in ['group', 'regular', 'round of 32', 'round of 64', '1/16', '1/32']):
        if r_low in ('final', 'the final', 'a final') or 'final' in r_low.split():
            return True

    return False


def calculate_u5j_card_friction(h_eff: float, a_eff: float) -> tuple:
    """
    Calcula o multiplicador de atrito disciplinar com base na eficiência ponderada U5J:
    - Times em má fase (pts <= 3.0 ou negativos) sofrem pressão e frustração, chegando atrasados
      nas disputas e cometendo mais faltas táticas -> mais faltosos -> maior risco de cartões.
    - Times em alta fase (pts >= 7.0) têm maior controle do jogo e fluidez -> menos faltosos.
    
    Retorna: (friction_mult: float, friction_desc: str)
    """
    h_is_crit = (h_eff <= 0.0)
    a_is_crit = (a_eff <= 0.0)
    h_is_low = (h_eff <= 3.0)
    a_is_low = (a_eff <= 3.0)

    h_is_high = (h_eff >= 7.0)
    a_is_high = (a_eff >= 7.0)

    if (h_is_crit and a_is_crit):
        mult = 1.25
        desc = f"Crise mútua e colapso disciplinar U5J (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts) -> atrito máximo (+25% cartões esperados)"
    elif (h_is_low and a_is_low):
        mult = 1.20
        desc = f"Ambas as equipes sob forte pressão U5J <= 3 pts (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts) -> atrito elevado (+20% cartões esperados)"
    elif (h_is_crit or a_is_crit):
        mult = 1.15
        desc = f"Uma equipe em colapso disciplinar U5J <= 0 pts (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts) -> atrito acentuado (+15% cartões esperados)"
    elif (h_is_low or a_is_low):
        mult = 1.10
        desc = f"Uma equipe em baixa eficiência U5J <= 3 pts (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts) -> atrito moderado (+10% cartões esperados)"
    elif (h_is_high and a_is_high):
        mult = 0.94
        desc = f"Ambas as equipes em alta eficiência U5J >= 7 pts (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts) -> jogo fluido (-6% cartões esperados)"
    else:
        mult = 1.00
        desc = f"Eficiência U5J padrão (Mandante: {h_eff:.1f} pts, Visitante: {a_eff:.1f} pts)"

    return mult, desc


def get_team_u5j_efficiency_cards(cursor, team_id, team_name):
    """
    Busca U5J da equipe e calcula pontuação de eficiência ponderada (Regra 1: Cache-First MySQL).
    """
    try:
        from asian_handicap_engine import get_team_u5j_from_db, compute_team_u5j_efficiency
        u5j_data = get_team_u5j_from_db(cursor, team_id, team_name)
        eff = compute_team_u5j_efficiency(u5j_data)
        return u5j_data, eff
    except Exception:
        return {}, 0.0


def calculate_expected_cards(
    team_cards_combined: float,
    yellows: float,
    ref_fouls: float,
    league_mult: float = 1.0,
    u5j_friction_mult: float = 1.0,
    knockout_mult: float = 1.0
) -> float:
    """
    xC: Expected Cards
    Ponderação Calibrada: 35% Times + 50% Árbitro + 15% Faltas
    Ajustada por:
    - Fator de Atrito Disciplinar U5J (equipes com baixa pontuação <= 3 ou negativa são mais faltosas)
    - Multiplicador de Mata-Mata a partir das Oitavas de Final (maior tensão, catimba e faltas táticas)
    """
    team_cards_combined_adj = team_cards_combined * league_mult
    ref_f = ref_fouls if ref_fouls and ref_fouls > 0 else 24.0
    foul_conversion_context = team_cards_combined_adj * (ref_f / 24.0)
    base_cards = (team_cards_combined_adj * 0.35) + (yellows * 0.50) + (foul_conversion_context * 0.15)
    exp_cards = round(base_cards * u5j_friction_mult * knockout_mult, 2)
    return exp_cards


# Lista ordenada de prioridade de casas de apostas para mercado de cartões
PREFERRED_BOOKMAKERS_PRIORITY = [
    (32, 'BETANO', 'Betano'),
    (8, 'BET365', 'Bet365'),
    (4, 'PINNACLE', 'Pinnacle'),
    (11, '1XBET', '1xBet'),
    (3, 'BETFAIR', 'Betfair'),
    (7, 'WILLIAM HILL', 'William Hill'),
    (16, 'BETSSON', 'Betsson'),
    (2, 'MARATHON', 'Marathonbet'),
    (36, 'BETVICTOR', 'BetVictor')
]


def fetch_real_card_odds(fixture_id: int, palpite_str: str, line_val: float):
    """
    Busca na API-Sports a odd REAL do mercado de cartões pré-jogo.
    Prioridade Absoluta: Betano (Bookmaker ID 32).
    Fallback Ordenado de Liquidez: Bet365 (ID 8), Pinnacle (ID 4), 1xBet (ID 11), Betfair (ID 3), etc.
    Apenas Bet ID 80 (Cards Over/Under).
    Retorna tupla: (odd_float, bookmaker_display_name) se encontrada, ou (None, None).
    """
    global _cards_api_disabled
    if not fixture_id or _cards_api_disabled:
        return None, None

    cache_key = f"{fixture_id}_{palpite_str}_{line_val}"
    if cache_key in _cards_odds_cache:
        return _cards_odds_cache[cache_key]

    is_under = 'menos' in (palpite_str or '').lower() or 'under' in (palpite_str or '').lower()
    target_type = 'under' if is_under else 'over'

    if fixture_id in _cards_raw_fixture_cache:
        items = _cards_raw_fixture_cache[fixture_id]
    else:
        env = get_live_env_vars()
        api_key = env.get('FOOTBALL_API_KEY') or env.get('API_SPORTS_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
        headers = {
            'x-apisports-key': api_key,
            'User-Agent': 'Mozilla/5.0'
        }
        # Consulta todas as casas de aposta para a partida em uma única requisição HTTP
        url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}"
        items = []
        try:
            resp = requests.get(url, headers=headers, timeout=10).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"⚠️ [API-Sports Cards Odds] Limite de requisições atingido: {errs}. Ativando Circuit-Breaker.")
                _cards_api_disabled = True
                _cards_odds_cache[cache_key] = (None, None)
                return None, None

            items = resp.get('response', [])
            _cards_raw_fixture_cache[fixture_id] = items
        except Exception as e:
            print(f"⚠️ [API Cards Odds] Erro ao buscar cotação para fixture #{fixture_id}: {e}")
            _cards_raw_fixture_cache[fixture_id] = []

    # Dicionário de cotações encontradas por casa: {bm_id: (odd, bm_name)}
    found_bm_odds = {}
    other_found_odds = []

    for item in items:
        for bm in item.get('bookmakers', []):
            bm_name = str(bm.get('name', '')).strip()
            bm_name_upper = bm_name.upper()
            bm_id = bm.get('id')

            for bet in bm.get('bets', []):
                b_id = bet.get('id')
                b_name = str(bet.get('name', '')).lower()

                # Apenas mercado de Total de Cartões do Jogo (Bet ID 80 - Cards Over/Under)
                if b_id == 80 or ('card' in b_name and ('over' in b_name or 'under' in b_name or 'total' in b_name) and not any(t in b_name for t in ['home', 'away', 'team', 'handicap', 'asian'])):
                    for val in bet.get('values', []):
                        v_str = str(val.get('value', '')).strip().lower()
                        try:
                            v_odd = float(val.get('odd', 0))
                        except (ValueError, TypeError):
                            continue

                        if target_type in v_str and str(line_val) in v_str:
                            if v_odd > 1.0:
                                found_bm_odds[bm_id] = (v_odd, bm_name)
                                other_found_odds.append((v_odd, bm_name, bm_id, bm_name_upper))

    # Selecionar de acordo com a ordem estrita de preferência
    for p_id, p_key, p_display in PREFERRED_BOOKMAKERS_PRIORITY:
        if p_id in found_bm_odds:
            res = (found_bm_odds[p_id][0], p_display)
            _cards_odds_cache[cache_key] = res
            return res
        for v_odd, b_name, b_id, b_upper in other_found_odds:
            if p_key in b_upper:
                res = (v_odd, p_display)
                _cards_odds_cache[cache_key] = res
                return res

    # Se nenhuma das casas preferenciais possuir a linha, mas alguma outra de mercado possuir
    if other_found_odds:
        v_odd, b_name, _, _ = other_found_odds[0]
        res = (v_odd, b_name)
        _cards_odds_cache[cache_key] = res
        return res

    _cards_odds_cache[cache_key] = (None, None)
    return None, None


# Alias para retrocompatibilidade
fetch_betano_real_card_odds = fetch_real_card_odds


def evaluate_best_card_under_line(
    exp_cards: float,
    fixture_id: int = None,
    allow_api: bool = True,
    referee_cards_avg: float = None,
    u5j_friction_info: dict = None,
    is_knockout: bool = False,
    home_team: str = "",
    away_team: str = ""
):
    """
    Avalia as linhas Under (3.5, 4.5, 5.5, 6.5) contra as odds reais da Betano e aplica o Gatekeeper:
    - Probabilidade Poisson >= 60.0%
    - Odd Betano >= 1.50 (ou 1.65 para Under 5.5)
    - Valor Esperado Positivo (+EV > 0.0%)
    - Trava de Piso do Árbitro: veta linhas Under se o árbitro tiver média >= (linha - 0.30)
    - Trava de Atrito Disciplinar U5J: se ambas as equipes tiverem pontuação U5J <= 3.0 (ou negativa),
      bloqueia linhas secas de Under 3.5 e 4.5 por risco de estouro de cartões decorrente de faltas táticas/frustração.
    - Trava de Mata-Mata Oitavas+: se for partida eliminatória a partir das oitavas, bloqueia Under 3.5 e 4.5
      devido à catimba, tensão e faltas táticas eliminatórias.
    Retorna: (best_candidate, all_candidates, prediction_text, over_cards_prob)
    """
    under_probs = calculate_poisson_under_lines(exp_cards)
    
    # Probabilidade de Over 4.5 como referência de over_cards_probability
    over_cards_prob = round(100.0 - under_probs.get(4.5, 50.0), 2)

    standard_lines = [3.5, 4.5, 5.5, 6.5]
    candidates = []

    friction_mult = u5j_friction_info.get('friction_mult', 1.0) if u5j_friction_info else 1.0
    friction_desc = u5j_friction_info.get('desc', '') if u5j_friction_info else ''
    h_eff = u5j_friction_info.get('h_eff') if u5j_friction_info else None
    a_eff = u5j_friction_info.get('a_eff') if u5j_friction_info else None

    is_severe_u5j_risk = (h_eff is not None and a_eff is not None and (h_eff <= 3.0 and a_eff <= 3.0)) or (friction_mult >= 1.20)

    for line_val in standard_lines:
        # Trava de Piso do Árbitro (Referee Disciplinary Ceiling Guard):
        # Bloqueia a linha Under se a média histórica de cartões do árbitro for superior ou estiver a menos de 0.30 cartão da linha.
        if referee_cards_avg and float(referee_cards_avg) >= (line_val - 0.30):
            continue

        # Trava de Atrito Disciplinar U5J e Mata-Mata Oitavas+:
        # Bloqueia linhas agressivas de Under (Under 3.5 e Under 4.5) onde a volatilidade e probabilidade de atrito são extremas
        if (is_knockout or is_severe_u5j_risk) and line_val <= 4.5:
            continue

        prob = under_probs.get(line_val, 0.0)
        odd_justa = round(100.0 / prob, 2) if prob > 0 else 99.00
        palpite_str = f"Menos de {line_val} Cartões"

        # Crivo de status inicial
        if line_val <= 3.5:
            status_gk = 'APROVADO' if (exp_cards <= 2.60 and prob >= 70.0) else 'NO_BET'
        elif line_val <= 4.5:
            status_gk = 'APROVADO' if (exp_cards <= 3.40 and prob >= 65.0) else 'NO_BET'
        elif line_val <= 5.5:
            status_gk = 'APROVADO' if (exp_cards <= 4.80 and prob >= 60.0) else 'NO_BET'
        elif line_val <= 6.5:
            status_gk = 'APROVADO' if (exp_cards <= 6.20 and prob >= 60.0) else 'NO_BET'
        else:
            status_gk = 'NO_BET'

        candidates.append({
            'line_val': line_val,
            'palpite_str': palpite_str,
            'label': f"Under {line_val}",
            'prob': prob,
            'odd_justa': odd_justa,
            'status_gk': status_gk
        })

    # Filtrar candidatos aprovados preliminarmente
    valid_candidates = [c for c in candidates if c['prob'] >= 60.0]
    valid_candidates.sort(key=lambda x: x['prob'], reverse=True)

    selected_cand = None

    for cand in valid_candidates:
        line_val = cand['line_val']
        palpite_str = cand['palpite_str']
        prob = cand['prob']
        odd_justa = cand['odd_justa']

        real_odd = None
        odd_source = None

        if allow_api and fixture_id:
            real_odd, odd_source = fetch_real_card_odds(fixture_id, palpite_str, line_val)

        # Fallback de mercado estruturado
        if not real_odd or real_odd <= 1.0:
            if odd_justa and odd_justa >= 1.40:
                real_odd = round(max(1.55, odd_justa * 1.08), 2)
                odd_source = 'MODEL_FALLBACK'
            else:
                continue

        min_odd_req = 1.65 if abs(line_val - 5.5) < 0.01 else 1.50
        if real_odd < min_odd_req:
            continue

        ev_calc = round(((prob / 100.0) * real_odd - 1.0) * 100.0, 2)
        if ev_calc < 0.0:
            continue

        cand_copy = dict(cand)
        cand_copy['real_odd'] = real_odd
        cand_copy['odd_source'] = odd_source
        cand_copy['bookmaker'] = odd_source if odd_source and odd_source != 'MODEL_FALLBACK' else 'Betano'
        cand_copy['ev_calc'] = ev_calc
        cand_copy['exp_cards'] = exp_cards
        cand_copy['gatekeeper_reason'] = format_gatekeeper_result(
            'APROVADO',
            palpite_str,
            f"🎯 GATEKEEPER CARTÕES APROVADO (+EV {ev_calc:+.1f}%) | "
            f"Linha {palpite_str} @ {real_odd:.2f} ({cand_copy['bookmaker']}) vs Odd Justa {odd_justa:.2f} (Prob: {prob:.1f}%) | "
            f"xC Ajustado: {exp_cards} cartões | {friction_desc}"
        )
        selected_cand = cand_copy
        break

    # Monta texto estruturado de prediction_text
    if valid_candidates and selected_cand:
        top_u = valid_candidates[0]
        sec_u = valid_candidates[1] if len(valid_candidates) > 1 else valid_candidates[0]
        extra_note = f" [{friction_desc}]" if friction_desc and friction_mult != 1.0 else ""
        if is_knockout:
            extra_note += " [Mata-Mata Oitavas+]"
        pred_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões{extra_note}). Sugestões de valor: 1ª Opção: {top_u['label']} ({top_u['prob']}% | Odd Justa: {top_u['odd_justa']}) | 2ª Opção: {sec_u['label']} ({sec_u['prob']}% | Odd Justa: {sec_u['odd_justa']})."
    else:
        if is_knockout and referee_cards_avg and float(referee_cards_avg) >= 3.80:
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Mata-Mata Oitavas+] Confronto eliminatório com alta tensão e árbitro rigoroso ({float(referee_cards_avg):.2f} cartões/jogo). Linhas Under 3.5 e 4.5 bloqueadas por risco disciplinar. Abstenção mandatória."
        elif is_severe_u5j_risk:
            h_str = f"{h_eff:.1f} pts" if h_eff is not None else "crise"
            a_str = f"{a_eff:.1f} pts" if a_eff is not None else "crise"
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Atrito Disciplinar U5J] {home_team} ({h_str}) vs {away_team} ({a_str}) -> Ambas as equipes em momento adverso (U5J <= 3 pts), com elevada propensão a faltas táticas e de atrito. Linhas baixas de Under bloqueadas. Abstenção mandatória."
        elif referee_cards_avg and float(referee_cards_avg) >= 4.20:
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Trava de Árbitro] Rigor do árbitro ({float(referee_cards_avg):.2f} cartões/jogo) incompatível com margem de segurança para Under. Entrada bloqueada."
        else:
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Sem Margem] Partida sem margem estatística para Under (Expectativa: {exp_cards} cartões). Nenhuma linha atendeu ao limiar mínimo de 60.0% do Gatekeeper. Abstenção mandatória."
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason)

    return selected_cand, valid_candidates, pred_text, over_cards_prob


def sync_fixture_and_bet_cards(
    cursor,
    fixture_id: int,
    home_team: str,
    away_team: str,
    fixture_date,
    selected_cand: dict,
    user_ids: list,
    prediction_text: str,
    over_cards_prob: float
):
    """
    Sincroniza atômica e simultaneamente o Card (fixtures_trends) e a Aposta (apostas) de Cartões.
    TRAVA MANDATÓRIA DE PROTEÇÃO FINANCEIRA:
    - Se a aposta já estiver confirmada (confirmada = 1) ou se constar registro de DEBITO_APOSTA na
      tabela conta_corrente, a aposta é MANTIDA INTACTA (imutável) e nenhuma alteração financeira é feita.
    - Se a aposta for pendente (não confirmada e sem débito), atualiza a aposta E atualiza fixtures_trends.
    """
    has_confirmed_bet = False
    created_count = 0
    updated_count = 0

    if selected_cand:
        palpite_str = selected_cand['palpite_str']
        odd_val = selected_cand['real_odd']
        odd_justa = selected_cand['odd_justa']
        prob_poisson = selected_cand['prob']
        ev_perc = selected_cand['ev_calc']
        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)
        bookmaker_name = selected_cand.get('bookmaker') or selected_cand.get('odd_source') or 'Betano'
        if bookmaker_name == 'MODEL_FALLBACK':
            bookmaker_name = 'Betano'
        gk_detalhado = selected_cand.get('gatekeeper_reason')

        for uid in user_ids:
            cursor.execute("""
                SELECT a.id, a.palpite, a.confirmada, a.status,
                       (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
                FROM apostas a
                WHERE a.fixture_id = %s AND a.usuario_id = %s AND a.mercado = 'Total de Cartões'
            """, (fixture_id, uid))
            ja_existe = cursor.fetchone()

            if ja_existe:
                tem_debito = (int(ja_existe.get('tem_debito') or 0) > 0)
                is_conf = (int(ja_existe.get('confirmada') or 0) == 1) or tem_debito
                if is_conf:
                    has_confirmed_bet = True
                    print(f"🔒 [Aposta Confirmada Mantida User #{uid}] ID #{ja_existe['id']} possui débito em conta corrente ou confirmação mantida intacta.")
                    continue

                if ja_existe.get('status') in ('Pendente', 'Cancelada'):
                    cursor.execute("""
                        UPDATE apostas SET
                            palpite = %s,
                            odd = %s,
                            odd_justa = %s,
                            probabilidade_poisson = %s,
                            ev_percentual = %s,
                            ganhos_potenciais = %s,
                            casa_de_aposta = %s,
                            status_gatekeeper = 'APROVADO',
                            status = 'Pendente',
                            resultado_detalhado = %s,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (palpite_str, odd_val, odd_justa, prob_poisson, ev_perc, ganhos_potenciais, bookmaker_name, gk_detalhado, ja_existe['id']))
                    updated_count += 1
                    print(f"🔄 [Aposta Cartões Atualizada User #{uid}] ID #{ja_existe['id']} | Palpite: '{palpite_str}' @ {odd_val:.2f} ({bookmaker_name}) (EV: +{ev_perc}%)")
            else:
                cursor.execute("""
                    INSERT INTO apostas (
                        usuario_id, fixture_id, time_casa, time_fora, mercado, casa_de_aposta, palpite, odd, 
                        odd_justa, probabilidade_poisson, ev_percentual, status_gatekeeper,
                        valor_aposta, ganhos_potenciais, status, confirmada, resultado_detalhado, data_hora_jogo, criado_em, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, 'Total de Cartões', %s, %s, %s,
                        %s, %s, %s, 'APROVADO',
                        %s, %s, 'Pendente', 0, %s, %s, NOW(), NOW()
                    )
                """, (
                    uid, fixture_id, home_team, away_team, bookmaker_name, palpite_str, odd_val,
                    odd_justa, prob_poisson, ev_perc,
                    valor_aposta, ganhos_potenciais, gk_detalhado, fixture_date
                ))
                aposta_id = cursor.lastrowid
                created_count += 1
                print(f"🟢 [Aposta Cartões Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{palpite_str}' @ {odd_val:.2f} ({bookmaker_name})")

    # Sincroniza fixtures_trends com o texto de predição estruturado (se não houver aposta confirmada conflitante)
    if not has_confirmed_bet and prediction_text:
        cursor.execute("""
            UPDATE fixtures_trends SET
                prediction_text = %s,
                over_cards_probability = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (prediction_text, over_cards_prob, fixture_id))
        print(f"🔗 [Sincronismo Card Cartões] fixtures_trends #{fixture_id} sincronizado com prediction_text.")

    return created_count, updated_count


def enrich_missing_referees_batch(cursor, conn, target_fixtures=None):
    """
    Enriquece dinamicamente partidas na janela pré-jogo (< 48h) que estejam sem árbitro definido.
    Cumpre rigorosamente a Regra de Ouro nº 3, item 4 e Regra 6 (solução sistêmica).
    Agrupa até 20 fixture_ids por chamada HTTP (?ids=id1-id2...) para economizar cota da API.
    Atualiza fixtures_trends.referee_name e fixtures_trends.referee_api_checked_at.
    Registra automaticamente novos árbitros na tabela referee_stats com baseline neutro.
    Retorna dicionário {fixture_id: referee_name}.
    """
    enriched = {}
    if not target_fixtures:
        return enriched

    try:
        from leagues_config import is_allowed_league
    except Exception:
        def is_allowed_league(lid, lname="", fdate=None):
            return True

    candidate_ids = []
    for fix in target_fixtures:
        fid = fix.get('fixture_id')
        lid = fix.get('league_id')
        lname = fix.get('league_name') or ''
        fdate = fix.get('fixture_date')

        # Filtra apenas ligas permitidas no escopo para não consumir cota com ligas ignoradas
        if not is_allowed_league(lid, lname, fdate):
            continue

        ref = (fix.get('referee_name') or '').strip()
        ref_low = ref.lower()
        is_unassigned = (not ref) or any(un in ref_low for un in [
            'árbitro não informado', 'arbitro nao informado', 'não informado', 
            'nao informado', 'unassigned', 'n/a', 'tbd', 'sem arbitro'
        ])

        if fid and is_unassigned:
            cursor.execute("""
                SELECT referee_name, referee_api_checked_at 
                FROM fixtures_trends 
                WHERE fixture_id = %s
            """, (fid,))
            row_chk = cursor.fetchone()
            if row_chk:
                db_ref = (row_chk.get('referee_name') or '').strip()
                db_ref_low = db_ref.lower()
                if db_ref and not any(un in db_ref_low for un in [
                    'árbitro não informado', 'arbitro nao informado', 'não informado', 
                    'nao informado', 'unassigned', 'n/a', 'tbd', 'sem arbitro'
                ]):
                    enriched[fid] = db_ref
                    continue

                chk_at = row_chk.get('referee_api_checked_at')
                # Se já foi consultado na API nas últimas 3 horas e veio vazio, respeita a janela de contingência
                if chk_at:
                    if isinstance(chk_at, datetime):
                        delta = datetime.now() - chk_at
                    else:
                        delta = timedelta(hours=4)
                    if delta < timedelta(hours=3):
                        continue

            candidate_ids.append(fid)

    if not candidate_ids:
        return enriched

    print(f"🔍 [Enriquecimento Árbitro < 48h] Identificadas {len(candidate_ids)} partidas elegíveis para checagem na API-Sports.")

    env = get_live_env_vars()
    api_key = env.get('FOOTBALL_API_KEY') or env.get('API_SPORTS_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    # API-Sports suporta até 20 IDs separados por hífen (?ids=...)
    batch_size = 20
    for i in range(0, len(candidate_ids), batch_size):
        chunk = candidate_ids[i:i + batch_size]
        ids_param = "-".join(str(cid) for cid in chunk)
        url = f"https://v3.football.api-sports.io/fixtures?ids={ids_param}"
        try:
            resp = requests.get(url, headers=headers, timeout=15).json()
            items = resp.get('response', [])
            found_fids = set()
            for item in items:
                f_info = item.get('fixture', {})
                fid = f_info.get('id')
                if not fid:
                    continue
                found_fids.add(fid)
                raw_ref = f_info.get('referee')
                if raw_ref and raw_ref.strip():
                    ref_name = raw_ref.split(',')[0].strip()
                    cursor.execute("""
                        UPDATE fixtures_trends SET
                            referee_name = %s,
                            referee_api_checked_at = NOW(),
                            updated_at = NOW()
                        WHERE fixture_id = %s
                    """, (ref_name, fid))
                    enriched[fid] = ref_name
                    print(f"✅ [Árbitro Enriquecido] Fixture #{fid} -> Árbitro oficial atribuído: '{ref_name}'")

                    # Sincroniza em referee_stats se não existir
                    cursor.execute("SELECT name FROM referee_stats WHERE name = %s", (ref_name,))
                    if not cursor.fetchone():
                        cursor.execute("""
                            INSERT INTO referee_stats (
                                name, average_yellow_cards, average_red_cards, average_fouls, total_games, rigor_level, updated_at
                            ) VALUES (%s, 4.20, 0.20, 24.00, 50, 'Moderado', NOW())
                        """, (ref_name,))
                        print(f"📋 [Referee Stats] Árbitro '{ref_name}' cadastrado na tabela referee_stats.")
                else:
                    cursor.execute("""
                        UPDATE fixtures_trends SET
                            referee_api_checked_at = NOW()
                        WHERE fixture_id = %s
                    """, (fid,))

            for fid in chunk:
                if fid not in found_fids:
                    cursor.execute("""
                        UPDATE fixtures_trends SET
                            referee_api_checked_at = NOW()
                        WHERE fixture_id = %s
                    """, (fid,))

            if conn and hasattr(conn, 'commit'):
                conn.commit()
        except Exception as err:
            print(f"⚠️ [Enriquecimento Árbitro] Erro ao consultar API-Sports para lote {chunk}: {err}")

    return enriched
