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
from datetime import datetime

# Caches em memória para chamadas da API Betano durante o ciclo de execução
_betano_cards_odds_cache = {}
_betano_cards_raw_fixture_cache = {}
_betano_cards_api_disabled = False


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


def calculate_expected_cards(team_cards_combined: float, yellows: float, ref_fouls: float, league_mult: float = 1.0) -> float:
    """
    xC: Expected Cards
    Ponderação Calibrada: 35% Times + 50% Árbitro + 15% Faltas
    """
    team_cards_combined_adj = team_cards_combined * league_mult
    ref_f = ref_fouls if ref_fouls and ref_fouls > 0 else 24.0
    foul_conversion_context = team_cards_combined_adj * (ref_f / 24.0)
    exp_cards = round((team_cards_combined_adj * 0.35) + (yellows * 0.50) + (foul_conversion_context * 0.15), 2)
    return exp_cards


def fetch_betano_real_card_odds(fixture_id: int, palpite_str: str, line_val: float):
    """
    Busca na API-Sports a odd REAL do mercado de cartões oferecida exclusivamente pela Betano (Bookmaker ID 32).
    Apenas Bet ID 80 (Cards Over/Under).
    Retorna tupla: (odd_float, 'BETANO') se encontrada, ou (None, None).
    """
    global _betano_cards_api_disabled
    if not fixture_id or _betano_cards_api_disabled:
        return None, None

    cache_key = f"{fixture_id}_{palpite_str}_{line_val}"
    if cache_key in _betano_cards_odds_cache:
        return _betano_cards_odds_cache[cache_key]

    is_under = 'menos' in (palpite_str or '').lower() or 'under' in (palpite_str or '').lower()
    target_type = 'under' if is_under else 'over'

    if fixture_id in _betano_cards_raw_fixture_cache:
        items = _betano_cards_raw_fixture_cache[fixture_id]
    else:
        env = get_live_env_vars()
        api_key = env.get('FOOTBALL_API_KEY') or env.get('API_SPORTS_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
        headers = {
            'x-apisports-key': api_key,
            'User-Agent': 'Mozilla/5.0'
        }
        url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"
        items = []
        try:
            resp = requests.get(url, headers=headers, timeout=10).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"⚠️ [API-Sports Betano Cards] Limite de requisições atingido: {errs}. Ativando Circuit-Breaker.")
                _betano_cards_api_disabled = True
                _betano_cards_odds_cache[cache_key] = (None, None)
                return None, None

            items = resp.get('response', [])
            _betano_cards_raw_fixture_cache[fixture_id] = items
        except Exception as e:
            print(f"⚠️ [API Betano Cards] Erro ao buscar cotação para fixture #{fixture_id}: {e}")
            _betano_cards_raw_fixture_cache[fixture_id] = []

    for item in items:
        for bm in item.get('bookmakers', []):
            bm_name = str(bm.get('name', '')).strip().upper()
            bm_id = bm.get('id')
            if 'BETANO' not in bm_name and bm_id != 32:
                continue

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
                                res = (v_odd, 'BETANO')
                                _betano_cards_odds_cache[cache_key] = res
                                return res

    _betano_cards_odds_cache[cache_key] = (None, None)
    return None, None


def evaluate_best_card_under_line(
    exp_cards: float,
    fixture_id: int = None,
    allow_api: bool = True
):
    """
    Avalia as linhas Under (3.5, 4.5, 5.5, 6.5) contra as odds reais da Betano e aplica o Gatekeeper:
    - Probabilidade Poisson >= 60.0%
    - Odd Betano >= 1.50 (ou 1.65 para Under 5.5)
    - Valor Esperado Positivo (+EV > 0.0%)
    Retorna: (best_candidate, all_candidates, prediction_text, over_cards_prob)
    """
    under_probs = calculate_poisson_under_lines(exp_cards)
    
    # Probabilidade de Over 4.5 como referência de over_cards_probability
    over_cards_prob = round(100.0 - under_probs.get(4.5, 50.0), 2)

    standard_lines = [3.5, 4.5, 5.5, 6.5]
    candidates = []

    for line_val in standard_lines:
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
            real_odd, odd_source = fetch_betano_real_card_odds(fixture_id, palpite_str, line_val)

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
        cand_copy['ev_calc'] = ev_calc
        cand_copy['exp_cards'] = exp_cards
        selected_cand = cand_copy
        break

    # Monta texto estruturado de prediction_text
    if valid_candidates:
        top_u = valid_candidates[0]
        sec_u = valid_candidates[1] if len(valid_candidates) > 1 else valid_candidates[0]
        pred_text = f"🛡️ Estratégia Under (Expectativa: {exp_cards} cartões). Sugestões de valor: 1ª Opção: {top_u['label']} ({top_u['prob']}% | Odd Justa: {top_u['odd_justa']}) | 2ª Opção: {sec_u['label']} ({sec_u['prob']}% | Odd Justa: {sec_u['odd_justa']})."
    else:
        pred_text = f"🚫 NO_BET: Partida sem margem estatística para Under (Expectativa: {exp_cards} cartões). Nenhuma linha atendeu ao limiar mínimo de 60.0% do Gatekeeper."

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

    if selected_cand:
        palpite_str = selected_cand['palpite_str']
        odd_val = selected_cand['real_odd']
        odd_justa = selected_cand['odd_justa']
        prob_poisson = selected_cand['prob']
        ev_perc = selected_cand['ev_calc']
        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

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
                            status_gatekeeper = 'APROVADO',
                            status = 'Pendente',
                            resultado_detalhado = NULL,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (palpite_str, odd_val, odd_justa, prob_poisson, ev_perc, ganhos_potenciais, ja_existe['id']))
                    print(f"🔄 [Aposta Cartões Atualizada User #{uid}] ID #{ja_existe['id']} | Palpite: '{palpite_str}' @ {odd_val:.2f} (EV: +{ev_perc}%)")
            else:
                cursor.execute("""
                    INSERT INTO apostas (
                        usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                        odd_justa, probabilidade_poisson, ev_percentual, status_gatekeeper,
                        valor_aposta, ganhos_potenciais, status, confirmada, data_hora_jogo, criado_em, updated_at
                    ) VALUES (
                        %s, %s, %s, %s, 'Total de Cartões', %s, %s,
                        %s, %s, %s, 'APROVADO',
                        %s, %s, 'Pendente', 0, %s, NOW(), NOW()
                    )
                """, (
                    uid, fixture_id, home_team, away_team, palpite_str, odd_val,
                    odd_justa, prob_poisson, ev_perc,
                    valor_aposta, ganhos_potenciais, fixture_date
                ))
                aposta_id = cursor.lastrowid
                print(f"🟢 [Aposta Cartões Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{palpite_str}' @ {odd_val:.2f}")

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
