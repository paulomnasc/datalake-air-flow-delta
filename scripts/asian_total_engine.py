#!/usr/bin/env python3
"""
Módulo Centralizado de Gols Asiáticos (Asian Total Engine - Single Source of Truth)
FootballWeb Pipeline

Implementa a metodologia estatística, precificação e ciclo de apostas para o mercado de Gols Asiáticos (Asian Total):
1. Expectativa de Gols (xG) derivada do histórico recente de 5 jogos (U5J) e probabilidades implícitas de 1X2;
2. Matriz Bivariada de Poisson para projeção exata da distribuição de gols totais da partida;
3. Avaliação analítica de linhas cheias (2.0, 3.0), meias (1.5, 2.5) e de quarto (1.75, 2.25, 2.75, 3.25);
4. Cálculo rigoroso de +EV (Valor Esperado Positivo), Odd Justa e Probabilidades Efetivas;
5. Gatekeeper de Gols Asiáticos (Odd Sweet Spot 1.65 - 2.25, +EV >= 3.0%, Consistência Ofensiva/Defensiva U5J);
6. Sincronização atômica entre fixtures_trends, palpites_gerados e apostas com débito e crédito na conta corrente.
"""

import sys
import os
import re
import math
import json
import requests
import pymysql
from datetime import datetime, timedelta

# Importar módulo canônico de banco de dados
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from db_config import get_db_connection, get_live_env_vars
from asian_handicap_engine import calculate_bivariate_poisson_matrix
try:
    from bet_tier import BetTier
except Exception:
    from scripts.bet_tier import BetTier

# Cache em memória para linhas de gols da Betano durante a execução
_goals_odds_cache = {}
_goals_raw_fixture_cache = {}

# ==============================================================================
# LIGAS E COPAS INELEGÍVEIS EXCLUSIVAMENTE PARA GOLS ASIÁTICOS
# ==============================================================================
# Torneios de seleções (abismo técnico com seleções semiprofissionais/amadoras)
# e copas mata-mata domésticas/continentais (onde equipes de 1ª divisão enfrentam
# times de 4ª/5ª divisão ou divisões semiprofissionais). Nesses cenários, a volatilidade
# de placares atípicos (goleadas como 7x2, 5x0, 8x0) quebra a premissa de estabilidade do Poisson.
GOALS_EXCLUDED_LEAGUE_IDS = {
    # Torneios de Seleções
    5,    # Nations League (Europa)
    4,    # Eurocopa / Qualifiers (Europa)
    9,    # Copa América (América do Sul)
    1,    # Copa do Mundo / Qualifiers (Mundo)

    # Copas Mata-Mata Domésticas e Continentais Heterogêneas
    66,   # Coupe de France (França)
    81,   # DFB Pokal (Alemanha)
    137,  # Coppa Italia (Itália)
    130,  # Copa Argentina (Argentina)
    73,   # Copa do Brasil (Brasil)
    16,   # CONCACAF Champions Cup (CONCACAF)
    772,  # Leagues Cup (América)
    17,   # AFC Champions League (Ásia)
    15,   # FIFA Club World Cup (Mundo)
    531,  # UEFA Super Cup (Europa)
    541,  # Recopa Sudamericana (América do Sul)
}

def evaluate_asian_total_line_poisson(matrix: dict, is_over: bool, line: float, odd: float) -> dict:
    """
    Avalia rigorosamente uma linha de Gols Asiáticos a partir da matriz de Poisson.
    Suporta linhas cheias (ex: 2.0, 3.0), meias (ex: 2.5, 3.5) e quartos (ex: 2.25, 2.75).
    Retorna métricas probabilísticas, odd justa, probabilidade efetiva e +EV%.
    """
    p_win = 0.0
    p_half_win = 0.0
    p_push = 0.0
    p_half_loss = 0.0
    p_loss = 0.0

    line_mod = round(line % 1.0, 2)

    for (x, y), p in matrix.items():
        k = x + y  # Total de gols da partida

        if is_over:
            # MERCADO OVER (Mais de X Gols)
            if line_mod == 0.5:
                # Linha Meia (ex: 1.5, 2.5, 3.5)
                if k > line:
                    p_win += p
                else:
                    p_loss += p
            elif line_mod == 0.0:
                # Linha Inteira (ex: 2.0, 3.0)
                if k > line:
                    p_win += p
                elif k == int(line):
                    p_push += p
                else:
                    p_loss += p
            elif line_mod == 0.25:
                # Linha Quarto .25 (ex: 2.25 -> split entre 2.0 e 2.5)
                # Se k >= 3: Ganha ambas
                # Se k == 2: Push na 2.0 (50% reembolso) e Perde na 2.5 (50% perda) -> Meio Perdida
                # Se k <= 1: Perde ambas
                if k > line + 0.25:  # k >= 3 para 2.25
                    p_win += p
                elif k == int(line):  # k == 2 para 2.25
                    p_half_loss += p
                else:
                    p_loss += p
            elif line_mod == 0.75:
                # Linha Quarto .75 (ex: 2.75 -> split entre 2.5 e 3.0)
                # Se k >= 4: Ganha ambas
                # Se k == 3: Ganha na 2.5 (50% lucro) e Push na 3.0 (50% reembolso) -> Meio Ganha
                # Se k <= 2: Perde ambas
                if k > line + 0.25:  # k >= 4 para 2.75
                    p_win += p
                elif k == math.ceil(line):  # k == 3 para 2.75
                    p_half_win += p
                else:
                    p_loss += p
        else:
            # MERCADO UNDER (Menos de X Gols)
            if line_mod == 0.5:
                # Linha Meia (ex: 1.5, 2.5, 3.5)
                if k < line:
                    p_win += p
                else:
                    p_loss += p
            elif line_mod == 0.0:
                # Linha Inteira (ex: 2.0, 3.0)
                if k < line:
                    p_win += p
                elif k == int(line):
                    p_push += p
                else:
                    p_loss += p
            elif line_mod == 0.25:
                # Linha Quarto .25 (ex: 2.25 -> split entre 2.0 e 2.5)
                # Se k <= 1: Ganha ambas
                # Se k == 2: Push na 2.0 (50% reembolso) e Ganha na 2.5 (50% lucro) -> Meio Ganha
                # Se k >= 3: Perde ambas
                if k < int(line):  # k <= 1 para 2.25
                    p_win += p
                elif k == int(line):  # k == 2 para 2.25
                    p_half_win += p
                else:
                    p_loss += p
            elif line_mod == 0.75:
                # Linha Quarto .75 (ex: 2.75 -> split entre 2.5 e 3.0)
                # Se k <= 2: Ganha ambas
                # Se k == 3: Perde na 2.5 (50% perda) e Push na 3.0 (50% reembolso) -> Meio Perdida
                # Se k >= 4: Perde ambas
                if k < math.ceil(line):  # k <= 2 para 2.75
                    p_win += p
                elif k == math.ceil(line):  # k == 3 para 2.75
                    p_half_loss += p
                else:
                    p_loss += p

    # Normalização de segurança
    total_p = p_win + p_half_win + p_push + p_half_loss + p_loss
    if total_p > 0:
        p_win /= total_p
        p_half_win /= total_p
        p_push /= total_p
        p_half_loss /= total_p
        p_loss /= total_p

    # Retorno Esperado do multiplicador de payout (por unidade apostada)
    # Win: odd * stake
    # Half Win: ((odd + 1.0) / 2.0) * stake
    # Push: 1.0 * stake
    # Half Loss: 0.5 * stake
    # Loss: 0.0 * stake
    expected_payout = (
        p_win * odd +
        p_half_win * ((odd + 1.0) / 2.0) +
        p_push * 1.0 +
        p_half_loss * 0.5 +
        p_loss * 0.0
    )
    ev_pct = round((expected_payout - 1.0) * 100.0, 2)

    # Probabilidade Efetiva de Sucesso (Considera Meio Ganha com peso 0.5 e Push neutro)
    prob_efetiva = (p_win + (p_half_win * 0.5) + (p_push * 0.5)) * 100.0

    # Odd Justa Teórica (Odd de Equilíbrio onde EV = 0)
    # Solvendo: p_win * odd + p_half_win * (odd/2) = 1.0 - (p_half_win * 0.5) - p_push - (p_half_loss * 0.5)
    denom = p_win + (p_half_win * 0.5)
    if denom > 0:
        fair_odd = (1.0 - (p_half_win * 0.5) - p_push - (p_half_loss * 0.5)) / denom
        fair_odd = round(max(1.05, fair_odd), 2)
    else:
        fair_odd = 99.0

    return {
        'line': line,
        'type': 'Over' if is_over else 'Under',
        'odd': odd,
        'p_win': p_win,
        'p_half_win': p_half_win,
        'p_push': p_push,
        'p_half_loss': p_half_loss,
        'p_loss': p_loss,
        'prob_efetiva': round(prob_efetiva, 1),
        'fair_odd': fair_odd,
        'ev_pct': ev_pct
    }


def settle_asian_total_bet(total_goals: int, palpite: str, odd: float, valor_aposta: float, moeda_codigo: str = 'BRL') -> tuple:
    """
    Liquida oficialmente uma aposta no mercado de Gols Asiáticos baseada no total de gols da partida (FT).
    Retorna: (status, ganhos_finais, lucro, detalhe)
    Status possíveis: 'Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Perdida'.
    """
    palpite_norm = (palpite or '').strip().lower()
    is_under = 'menos' in palpite_norm or 'under' in palpite_norm or 'abaixo' in palpite_norm
    is_over = 'mais' in palpite_norm or 'over' in palpite_norm or 'acima' in palpite_norm

    # Extrai o valor da linha (ex: 2.25, 2.5, 2.75, 2.0, 3.0)
    match = re.search(r'(\d+(?:[\.,]\d+)?)', palpite_norm)
    if not match:
        return 'Pendente', 0.0, 0.0, "Linha de gols não identificada no palpite."

    line = float(match.group(1).replace(',', '.'))
    line_mod = round(line % 1.0, 2)
    k = int(total_goals)
    dec = 8 if moeda_codigo in ('LTC', 'BTC', 'ETH') else 2

    status = 'Perdida'
    fator_retorno = 0.0

    if is_over:
        if line_mod == 0.5:
            if k > line:
                status = 'Ganha'
                fator_retorno = odd
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.0:
            if k > line:
                status = 'Ganha'
                fator_retorno = odd
            elif k == int(line):
                status = 'ANULADA'
                fator_retorno = 1.0
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.25:
            if k > line + 0.25:
                status = 'Ganha'
                fator_retorno = odd
            elif k == int(line):
                status = 'Meio Perdida'
                fator_retorno = 0.5
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.75:
            if k > line + 0.25:
                status = 'Ganha'
                fator_retorno = odd
            elif k == math.ceil(line):
                status = 'Meio Ganha'
                fator_retorno = (odd + 1.0) / 2.0
            else:
                status = 'Perdida'
                fator_retorno = 0.0
    else:  # Under
        if line_mod == 0.5:
            if k < line:
                status = 'Ganha'
                fator_retorno = odd
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.0:
            if k < line:
                status = 'Ganha'
                fator_retorno = odd
            elif k == int(line):
                status = 'ANULADA'
                fator_retorno = 1.0
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.25:
            if k < int(line):
                status = 'Ganha'
                fator_retorno = odd
            elif k == int(line):
                status = 'Meio Ganha'
                fator_retorno = (odd + 1.0) / 2.0
            else:
                status = 'Perdida'
                fator_retorno = 0.0
        elif line_mod == 0.75:
            if k < math.ceil(line):
                status = 'Ganha'
                fator_retorno = odd
            elif k == math.ceil(line):
                status = 'Meio Perdida'
                fator_retorno = 0.5
            else:
                status = 'Perdida'
                fator_retorno = 0.0

    retorno = round(valor_aposta * fator_retorno, dec)
    lucro = round(retorno - valor_aposta, dec)

    direcao_txt = "Mais de" if is_over else "Menos de"
    detalhe = f"FT | Placar Oficial: Total {k} Gols | Palpite: {direcao_txt} {line:g} Gols Asiáticos -> Aposta {status.upper()} (Retorno: {moeda_codigo} {retorno})"
    return status, retorno, lucro, detalhe


def fetch_betano_goals_lines(fixture_id: int, home_team: str, away_team: str) -> list:
    """
    Busca cotações em tempo real para o mercado de Gols Asiáticos na Betano (Bookmaker ID 32 ou Betano Direct API).
    Regra 12: Retorna apenas linhas e odds reais. Proibição estrita de odds sintéticas.
    """
    if fixture_id in _goals_odds_cache:
        return _goals_odds_cache[fixture_id]

    lines = []

    # 0. Tentativa Prioritária via Cloudbet API (Operação Cripto / USDT)
    try:
        from cloudbet_matcher import CloudbetMatcher
        matcher = CloudbetMatcher()
        ev = matcher.find_matching_event(home_team, away_team)
        if ev:
            ev_id = ev.get('event_id')
            if ev_id:
                cb_markets = matcher.get_event_markets(ev_id)
                tg = cb_markets.get('total_goals', {})
                if tg:
                    for l_val, outcomes in tg.items():
                        if 'over' in outcomes:
                            lines.append({
                                'line': float(l_val),
                                'type': 'Over',
                                'odd': float(outcomes['over']['price']),
                                'label': f"Mais de {float(l_val):g} Gols Asiáticos",
                                'bookmaker': 'Cloudbet',
                                'market_url': outcomes['over'].get('market_url', '')
                            })
                        if 'under' in outcomes:
                            lines.append({
                                'line': float(l_val),
                                'type': 'Under',
                                'odd': float(outcomes['under']['price']),
                                'label': f"Menos de {float(l_val):g} Gols Asiáticos",
                                'bookmaker': 'Cloudbet',
                                'market_url': outcomes['under'].get('market_url', '')
                            })
                    if lines:
                        _goals_odds_cache[fixture_id] = lines
                        return lines
    except Exception as e_cb:
        pass

    # 1. Tentativa de Contingência via Betano Direct API (Scraper Oficial)
    try:
        from betano_direct_api import get_event_markets_by_teams
        b_data = get_event_markets_by_teams(home_team, away_team)
        if b_data and b_data.get('goals_lines'):
            lines = b_data['goals_lines']
            _goals_odds_cache[fixture_id] = lines
            return lines
    except Exception as e_direct:
        print(f"ℹ️ [Betano Direct Goals API] Não encontrou linhas diretas: {e_direct}", file=sys.stderr)

    # 2. Tentativa via API-Sports (Bookmaker ID 32 - Betano)
    env_vars = get_live_env_vars()
    api_key = env_vars.get('FOOTBALL_API_KEY') or env_vars.get('APISPORTS_KEY')
    if not api_key:
        return []

    headers = {'x-apisports-key': api_key}
    url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"

    try:
        resp = requests.get(url, headers=headers, timeout=12)
        if resp.status_code == 200:
            data = resp.json().get('response', [])
            if data and data[0].get('bookmakers'):
                bm = data[0]['bookmakers'][0]
                for bet in bm.get('bets', []):
                    b_name = (bet.get('name') or '').strip().lower()
                    # Descartar expressamente mercados parciais (1º tempo, 2º tempo, equipes, etc.)
                    if any(bad in b_name for bad in [
                        '1st half', 'first half', '1st', '1h', '2nd half', 'second half', '2nd', '2h',
                        'home', 'away', 'team', 'extra time', 'half', 'tempo', 'equipe', 'exact'
                    ]):
                        continue
                    # Identificar mercados de Gols Asiáticos / Over Under do jogo completo (Full Time)
                    if any(term in b_name for term in ['asian total', 'total de gols asiatico', 'total de golos asiatico']) or b_name in ['goals over/under', 'total de gols', 'total de golos', 'gols mais/menos']:
                        for val in bet.get('values', []):
                            v_name = (val.get('value') or '').strip().lower()
                            v_odd = float(val.get('odd', 0.0) or 0.0)
                            if v_odd <= 1.0:
                                continue
                            is_over = 'over' in v_name or 'mais' in v_name or 'acima' in v_name
                            is_under = 'under' in v_name or 'menos' in v_name or 'abaixo' in v_name
                            if not (is_over or is_under):
                                continue
                            m_line = re.search(r'(\d+(?:\.\d+)?)', v_name)
                            if m_line:
                                line_f = float(m_line.group(1))
                                label = f"{'Mais de' if is_over else 'Menos de'} {line_f:g} Gols"
                                lines.append({
                                    'line': line_f,
                                    'type': 'Over' if is_over else 'Under',
                                    'odd': v_odd,
                                    'label': label,
                                    'bookmaker': 'Betano'
                                })
    except Exception as e_api:
        print(f"⚠️ [API-Sports Goals] Erro ao buscar odds Betano para fixture {fixture_id}: {e_api}", file=sys.stderr)

    _goals_odds_cache[fixture_id] = lines
    return lines


def evaluate_and_select_best_goals_candidate(
    candidates: list,
    home_team: str,
    away_team: str,
    xg_h: float,
    xg_a: float,
    u5j_h: dict = None,
    u5j_a: dict = None,
    has_superfavorite: bool = False
) -> tuple:
    """
    Aplica o Gatekeeper de Gols Asiáticos para filtrar e ranquear candidatos viáveis.
    Critérios:
    - Faixa Sweet Spot de Odd: 1.65 a 2.25
    - Valor Esperado Positivo (+EV >= 3.0%)
    - Probabilidade Efetiva >= 52.0%
    - Trava Universal Anti-Goleada: Se 1X2 <= 1.20, veta terminantemente apostas em Under
    - Filtro de Coerência de Gols (Over exige histórico de gols recentes; Under exige consistência defensiva)
    Retorna: (status_gk, best_cand, approved_list, reason_msg)
    """
    total_xg = round(xg_h + xg_a, 2)
    approved_list = []

    for cand in candidates:
        odd = cand['odd']
        line = cand['line']
        c_type = cand['type']
        ev_pct = cand['ev_pct']
        prob = cand['prob_efetiva']

        # 0. Trava Soberana de Linhas Inteiras: Descontinuação absoluta de linhas fracionadas (.25, .5 e .75)
        # Autorizadas exclusivamente linhas inteiras com amortecedor de Push integral (2.0 e 3.0 Gols)
        if round(line % 1.0, 2) != 0.0 or line not in (2.0, 3.0):
            continue

        # 1. Filtro Sweet Spot de Odd Conservador (Alta Assertividade 1.60 a 1.95)
        if odd < 1.60 or odd > 1.95:
            continue

        # 2. Trava de Sanidade de Mercado (Full Time 90 min)
        # Em 90 minutos de jogo, linhas de Over <= 1.25 JAMAIS pagam acima de 1.50 em pré-jogo.
        # Se uma linha Over <= 1.25 possuir odd no sweet spot (1.60-1.95), trata-se de mercado parcial contaminado (1º tempo ou time individual).
        if c_type == 'Over' and line <= 1.25:
            continue
        # Da mesma forma, Under >= 4.0 não tem liquidez no sweet spot em ligas regulares
        if c_type == 'Under' and line >= 4.0:
            continue

        # 3. Trava Universal Anti-Goleada: Superfavorito Nominal (1X2 <= 1.20)
        # Se uma das equipes possui odd 1X2 <= 1.20, o mercado antecipa vitória avassaladora.
        # Linhas de UNDER são expressamente proibidas contra superfavoritos.
        if c_type == 'Under' and has_superfavorite:
            continue

        # 4. Filtro de Valor Esperado (+EV) e Probabilidade Efetiva Rigorosa
        # Elevação de maturidade: piso de +EV passa de +3.0% para +8.0%, e probabilidade efetiva mínima de 62.0%
        if ev_pct < 8.0 or prob < 62.0:
            continue

        # 5. Filtro de Consistência Tática e Perfil de Gols do Confronto (Anti-Push Ilusório)
        # Menos de 3.0 Gols: exige perfil de jogo moderado/truncado (total xG projetado <= 2.30)
        # Evita apostar em Under em jogos de alta intensidade apenas pelo benefício do push
        if c_type == 'Under' and line == 3.0 and total_xg > 2.30:
            continue
        if c_type == 'Under' and line == 2.0 and total_xg > 1.85:
            continue

        # Mais de 2.0 Gols: exige vocação real para gols (total xG projetado >= 2.35)
        if c_type == 'Over' and line == 2.0 and total_xg < 2.35:
            continue
        # Mais de 3.0 Gols: exige confronto francamente ofensivo (total xG projetado >= 2.80)
        if c_type == 'Over' and line == 3.0 and total_xg < 2.80:
            continue

        # 6. Governança Canônica de BetTier (Regra 21: Classificação Qualitativa Estrita)
        if ev_pct >= 15.0 and prob >= 68.0:
            cand['tier'] = BetTier.PREMIUM
            cand['destaque'] = BetTier.PREMIUM.value
            cand['gatekeeper_category'] = BetTier.PREMIUM.gatekeeper_category
        elif ev_pct >= 8.0 and prob >= 62.0:
            cand['tier'] = BetTier.QUASE_PREMIUM
            cand['destaque'] = BetTier.QUASE_PREMIUM.value
            cand['gatekeeper_category'] = BetTier.QUASE_PREMIUM.gatekeeper_category
        else:
            # Descartar entradas regulares com perfil fraco
            continue

        approved_list.append(cand)

    if not approved_list:
        return 'NO_BET', None, [], f"Gatekeeper de Gols Asiáticos: Nenhuma linha inteira autorizada (2.0 ou 3.0) com perfil tático condizente, valor esperado (+EV >= 8.0%) e probabilidade >= 62.0% no Sweet Spot Conservador (1.60 - 1.95)."

    # Ordenação Soberana por Maior Probabilidade Efetiva (Win-Rate First) e desempate por maior EV%
    approved_list.sort(key=lambda x: (x['prob_efetiva'], x['ev_pct']), reverse=True)
    best_cand = approved_list[0]

    tipo_str = "Mais de" if best_cand['type'] == 'Over' else "Menos de"
    sug = f"{tipo_str} {best_cand['line']:g} Gols Asiáticos"
    tier_label = best_cand['tier'].label

    reason_msg = (
        f"{tier_label}: Aposta identificada no mercado de Gols Asiáticos: {sug} com cotação {best_cand['odd']:.2f} na Betano/Cloudbet. "
        f"O modelo estatístico de Poisson projeta uma expectativa de {total_xg:.2f} gols no confronto "
        f"({home_team} xG {xg_h:.2f} vs {xg_a:.2f} {away_team}), gerando probabilidade efetiva de {best_cand['prob_efetiva']:.1f}% "
        f"e margem matemática de valor esperado de +{best_cand['ev_pct']:.1f}% (+EV)."
    )

    return 'APPROVED', best_cand, approved_list, reason_msg


def calculate_unified_goals_recommendation(
    fixture_dict: dict,
    betano_lines: list = None,
    cursor = None
) -> tuple:
    """
    Função Mestre Unificada para Ingestão, Criação e Auditoria de Apostas em Gols Asiáticos.
    Retorna: (status, palpite_sugerido, confianca, reasoning, best_cand, approved_list)
    """
    home_team = (fixture_dict.get('home_team') or '').strip()
    away_team = (fixture_dict.get('away_team') or '').strip()
    fixture_id = fixture_dict.get('fixture_id')
    h_id = fixture_dict.get('home_team_id')
    a_id = fixture_dict.get('away_team_id')
    l_id = fixture_dict.get('league_id')

    # Trava 1: Exclusão de Competições Inelegíveis para Gols Asiáticos (Seleções e Copas Mata-Mata)
    if l_id is not None:
        try:
            lid_int = int(l_id)
            if lid_int in GOALS_EXCLUDED_LEAGUE_IDS:
                lname = fixture_dict.get('league_name') or 'Copa/Seleções'
                reason_msg = (
                    f"Gatekeeper Gols (NO_BET): Competição ID {lid_int} ({lname}) é inelegível para o mercado de Gols Asiáticos "
                    f"devido a desnível técnico acentuado e alta volatilidade de placares atípicos."
                )
                return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, reason_msg, None, []
        except (ValueError, TypeError):
            pass

    # Trava 2: Verificação de Superfavorito Nominal (1X2 <= 1.20)
    odd_h = float(fixture_dict.get('odd_home') or 0.0)
    odd_a = float(fixture_dict.get('odd_away') or 0.0)
    has_superfavorite = (0.0 < odd_h <= 1.20) or (0.0 < odd_a <= 1.20)

    # 1. Obter Histórico U5J e Calcular xG
    h_sc, h_con = [], []
    a_sc, a_con = [], []
    u_json = {}

    if cursor:
        try:
            from football_ingest_trends import fetch_team_last5_form
            h_form = fetch_team_last5_form(cursor, home_team, h_id, l_id)
            a_form = fetch_team_last5_form(cursor, away_team, a_id, l_id)
            if h_form and a_form:
                u_json = {'home': h_form, 'away': a_form}
                for m in h_form.get('matches', []):
                    sc = m.get('score', '')
                    if 'x' in sc:
                        p = sc.split('x')
                        h_sc.append(min(5, int(p[0])))
                        h_con.append(min(5, int(p[1])))
                for m in a_form.get('matches', []):
                    sc = m.get('score', '')
                    if 'x' in sc:
                        p = sc.split('x')
                        a_sc.append(min(5, int(p[0])))
                        a_con.append(min(5, int(p[1])))
        except Exception as e_u5j:
            print(f"⚠️ [U5J Goals] Falha ao extrair U5J: {e_u5j}", file=sys.stderr)

    # Cálculo dos Lambdas de Poisson (Regra 9: Proibição Absoluta de Fallbacks Fictícios)
    if not (h_sc and a_con) or not (a_sc and h_con):
        reason_msg = (
            f"Gatekeeper Gols (NO_BET): Ausência ou inconsistência de dados estatísticos de U5J "
            f"para {home_team} e/ou {away_team}. Interrupção mandatória de cálculo (Regra de Ouro 9)."
        )
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, reason_msg, None, []

    raw_xg_h = (sum(h_sc) / len(h_sc) + sum(a_con) / len(a_con)) / 2.0
    xg_h = round(raw_xg_h * 1.08, 2)

    raw_xg_a = (sum(a_sc) / len(a_sc) + sum(h_con) / len(h_con)) / 2.0
    xg_a = round(raw_xg_a * 0.92, 2)

    # 2. Gerar Matriz de Poisson
    poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

    # 3. Obter Linhas Reais da Betano (Regra 12)
    if betano_lines is None:
        betano_lines = fetch_betano_goals_lines(fixture_id, home_team, away_team)

    if not betano_lines:
        reason_msg = f"Abstenção mandatória: Nenhuma cotação de Gols Asiáticos disponível na Betano para {home_team} x {away_team}."
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, reason_msg, None, []

    # 4. Avaliar todas as linhas candidatas via Poisson
    candidates = []
    for line_item in betano_lines:
        line_val = float(line_item['line'])
        is_over = (line_item['type'] == 'Over')
        odd_val = float(line_item['odd'])
        res = evaluate_asian_total_line_poisson(poisson_matrix, is_over, line_val, odd_val)
        res['label'] = line_item.get('label') or f"{'Mais de' if is_over else 'Menos de'} {line_val:g} Gols"
        res['bookmaker'] = line_item.get('bookmaker', 'Betano')
        candidates.append(res)

    # 5. Executar Gatekeeper
    status_gk, best_cand, approved_list, reason_msg = evaluate_and_select_best_goals_candidate(
        candidates, home_team, away_team, xg_h, xg_a, u_json.get('home'), u_json.get('away'),
        has_superfavorite=has_superfavorite
    )

    if status_gk != 'APPROVED' or not best_cand:
        return 'NO_BET', 'Sem Entrada (Abstenção)', 50.0, reason_msg, None, []

    palpite_sug = f"{'Mais de' if best_cand['type'] == 'Over' else 'Menos de'} {best_cand['line']:g} Gols Asiáticos"
    confianca = best_cand['prob_efetiva']

    return 'APPROVED', palpite_sug, confianca, reason_msg, best_cand, approved_list


def sync_fixture_and_bet_goals(
    cursor,
    fixture_dict: dict,
    best_cand: dict,
    reasoning: str,
    user_ids: list,
    confirmada: int = 1
) -> int:
    """
    Sincroniza atomicamente a aposta no mercado de Gols Asiáticos na tabela 'apostas'.
    Regra 18: Zero hardcoded credentials. Utiliza conexão ativa via cursor.
    """
    if not best_cand or not user_ids:
        return 0

    fixture_id = fixture_dict.get('fixture_id')
    home_team = fixture_dict.get('home_team')
    away_team = fixture_dict.get('away_team')
    data_jogo = fixture_dict.get('fixture_date') or datetime.now()

    odd = float(best_cand['odd'])
    line = float(best_cand['line'])
    tipo = best_cand['type']
    tipo_str = "Mais de" if tipo == 'Over' else "Menos de"
    palpite = f"{tipo_str} {line:g} Gols Asiáticos"
    fair_odd = float(best_cand.get('fair_odd') or odd)
    prob = float(best_cand.get('prob_efetiva') or 50.0)
    ev = float(best_cand.get('ev_pct') or 0.0)

    # Consulta a moeda padrão ativa no sistema para criação de apostas (Regra Multi-Moedas)
    cursor.execute("SELECT codigo, cotacao_brl, casas_decimais FROM moedas_config WHERE is_default = 1 AND ativo = 1 LIMIT 1")
    m_def = cursor.fetchone()
    moeda_padrao_cod = m_def['codigo'] if m_def else 'BRL'
    cotacao_brl_padrao = float(m_def['cotacao_brl']) if m_def else 1.0
    casas_dec_padrao = int(m_def['casas_decimais']) if m_def else 2

    inserted_count = 0
    for uid in user_ids:
        # Verificar se já existe aposta ativa para esta partida e mercado (Idempotência Universal)
        cursor.execute("""
            SELECT id, status FROM apostas 
            WHERE usuario_id = %s AND fixture_id = %s AND mercado = 'Gols Asiáticos'
              AND confirmada = 1 AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (uid, fixture_id))
        row = cursor.fetchone()

        if row:
            # Já existe aposta confirmada registrada
            continue

        # Consulta stake ativa parametrizada do usuário em metas_diarias_config
        cursor.execute("""
            SELECT stake_padrao, moeda_codigo 
            FROM metas_diarias_config 
            WHERE usuario_id = %s AND is_ativa = 1 
            ORDER BY id DESC LIMIT 1
        """, (uid,))
        meta_row = cursor.fetchone()
        user_moeda = meta_row.get('moeda_codigo') if (meta_row and meta_row.get('moeda_codigo')) else moeda_padrao_cod
        user_stake = float(meta_row['stake_padrao']) if (meta_row and meta_row.get('stake_padrao')) else 10.00
        if user_stake <= 0.0:
            user_stake = 10.00

        # Obter cotação snapshot e casas decimais da moeda do usuário
        cursor.execute("SELECT cotacao_brl, casas_decimais FROM moedas_config WHERE codigo = %s LIMIT 1", (user_moeda,))
        m_user = cursor.fetchone()
        user_cotacao = float(m_user['cotacao_brl']) if m_user else cotacao_brl_padrao
        user_decimais = int(m_user['casas_decimais']) if m_user else casas_dec_padrao

        valor_aposta = round(user_stake, user_decimais)
        retorno_potencial = round(valor_aposta * odd, user_decimais)

        ia_resumo = 'Aprovado pelo Motor Estatístico de Poisson (+EV Puro / Isolado da Sentinela)'
        ia_parecer = 'Aposta validada exclusivamente sob a ótica estatística de Poisson e dados históricos U5J. O mercado de Gols Asiáticos opera de forma 100% independente de análises qualitativas de elenco.'

        cloudbet_bet_id = None
        if user_moeda == 'USDT':
            # Padrão Universal API-First: Consulta e emite ordem real na Cloudbet (soccer.total_goals)
            try:
                import json
                from cloudbet_client import CloudbetClient
                from cloudbet_matcher import CloudbetMatcher
                cb_client = CloudbetClient()
                cb_matcher = CloudbetMatcher(client=cb_client)
                cb_match = cb_matcher.find_matching_event(home_team, away_team, data_jogo)
                if not cb_match:
                    print(f"⚠️ [Cloudbet Gols Sem Match] #{fixture_id} {home_team} vs {away_team} não encontrado na Cloudbet. Abstenção mandatória.")
                    continue
                cb_mkts = cb_matcher.get_event_markets(cb_match['event_id'])
                tg_mkts = cb_mkts.get('total_goals', {})
                outcome_key = 'over' if tipo == 'Over' else 'under'

                cb_line_info = tg_mkts.get(line, {}).get(outcome_key)
                if not cb_line_info or float(cb_line_info.get('price') or 0.0) < 1.30:
                    print(f"⚠️ [Cloudbet Gols Sem Linha Ativa] Linha {line} {outcome_key} não ativa na Cloudbet para {home_team} vs {away_team}. Abstenção mandatória.")
                    continue

                cb_odd = float(cb_line_info['price'])
                cb_market_url = cb_line_info['market_url']

                print(f"🚀 [Cloudbet Gols API] Enviando ordem real: #{fixture_id} {home_team} vs {away_team} | {palpite} @ {cb_odd:.2f} (Stake: {valor_aposta:.2f} USDT)")
                order_res = cb_client.place_bet(
                    event_id=str(cb_match['event_id']),
                    market_url=cb_market_url,
                    price=cb_odd,
                    stake=valor_aposta,
                    currency=cb_client.currency
                )

                cloudbet_bet_id = order_res.get("betId")
                # Validação Estrita API-First: Rollback se a API recusar ou não retornar betId
                if not cloudbet_bet_id or "error" in order_res or order_res.get("status") in ("REJECTED", "FAILED") or not order_res.get("success", True):
                    cursor.connection.rollback()
                    print(f"❌ [Cloudbet Gols Rejeitado] Ordem recusada pela API: {order_res}. Rollback executado. Nenhuma aposta persistida.")
                    continue

                casa_aposta_val = 'Cloudbet'
                odd = cb_odd
                confirmada_val = 1
                retorno_potencial = round(valor_aposta * odd, user_decimais)
                cb_meta = json.dumps({'bet_id': cloudbet_bet_id, 'event_id': cb_match['event_id'], 'market_url': cb_market_url, 'is_simulated': order_res.get("is_simulated", cb_client.dry_run), 'currency': cb_client.currency})
                reasoning = f"|| CLOUDBET: {cb_meta} || " + str(reasoning)
            except Exception as e_cb:
                cursor.connection.rollback()
                print(f"❌ [Cloudbet Gols Exceção] Falha ao processar ordem na Cloudbet: {e_cb}. Rollback executado.")
                continue
        else:
            casa_aposta_val = best_cand.get('bookmaker') or 'Betano'
            confirmada_val = confirmada

        gk_cat = best_cand.get('gatekeeper_category') or 'Aposta Quase Premium (+EV Selecionado)'
        destaque_val = int(best_cand.get('destaque') or 1)

        cursor.execute("""
            INSERT INTO apostas (
                usuario_id, moeda_codigo, cotacao_brl_snapshot, fixture_id, time_casa, time_fora,
                mercado, casa_de_aposta, palpite, odd, odd_justa,
                probabilidade_poisson, ev_percentual, status_gatekeeper, gatekeeper_category, destaque,
                valor_aposta, ganhos_potenciais, status, confirmada,
                resultado_detalhado, data_hora_jogo,
                auditoria_ia_status, auditoria_ia_resumo, auditoria_ia_parecer,
                auditoria_ia_checked_at, notificacao_escalacao_enviada,
                criado_em, updated_at
            ) VALUES (
                %s, %s, %s, %s, %s, %s,
                'Gols Asiáticos', %s, %s, %s, %s,
                %s, %s, 'APROVADO', %s, %s,
                %s, %s, 'Pendente', %s,
                %s, %s,
                'CONFIRMADO',
                %s,
                %s,
                NOW(), 1,
                NOW(), NOW()
            )
        """, (
            uid, user_moeda, user_cotacao, fixture_id, home_team, away_team,
            casa_aposta_val, palpite, odd, fair_odd,
            prob, ev, gk_cat, destaque_val,
            valor_aposta, retorno_potencial, confirmada_val,
            reasoning, data_jogo,
            ia_resumo, ia_parecer
        ))
        aposta_id = cursor.lastrowid

        # Se for confirmada, debitar valor na conta corrente
        if confirmada_val == 1 and aposta_id:
            saldo_ant = 0.0
            saldo_pos = 0.0
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
            u_row = cursor.fetchone()
            if u_row:
                saldo_ant = float(u_row['saldo_conta_corrente'] or 0.0)
            saldo_pos = round(saldo_ant - valor_aposta, 8)

            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor,
                    saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (
                uid, aposta_id, f"Débito Aposta #{aposta_id} ({home_team} x {away_team} - {palpite})",
                -valor_aposta, saldo_ant, saldo_pos, user_moeda
            ))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (saldo_pos, uid))

        inserted_count += 1

    return inserted_count


def cli_evaluate_goals_bet(
    fixture_id: int = None,
    home_team: str = "",
    away_team: str = "",
    palpite: str = "",
    odd: float = 1.80
) -> dict:
    """
    Avalia uma aposta específica no mercado de Gols Asiáticos via Poisson e Gatekeeper.
    Fonte Única da Verdade (SSOT) para consumo pelo ApostaController.php.
    """
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # 1. Localizar a partida
            fix = None
            if fixture_id:
                cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
                fix = cursor.fetchone()
            if not fix and home_team and away_team:
                cursor.execute("""
                    SELECT * FROM fixtures_trends 
                    WHERE home_team LIKE %s AND away_team LIKE %s 
                    ORDER BY fixture_date DESC LIMIT 1
                """, (f"%{home_team}%", f"%{away_team}%"))
                fix = cursor.fetchone()

            if not fix:
                return {
                    "fixtureId": fixture_id,
                    "oddJusta": None,
                    "probPoisson": None,
                    "evPercentual": None,
                    "statusGatekeeper": "NO_BET",
                    "gatekeeperCategory": "Partida Não Encontrada",
                    "gatekeeperMsg": "Partida não localizada no banco para validação de gols.",
                    "destaque": 0
                }

            # Trava 1: Exclusão de Competições Inelegíveis para Gols Asiáticos (Seleções e Copas Mata-Mata)
            l_id = fix.get('league_id')
            if l_id is not None:
                try:
                    lid_int = int(l_id)
                    if lid_int in GOALS_EXCLUDED_LEAGUE_IDS:
                        lname = fix.get('league_name') or 'Copa/Seleções'
                        return {
                            "fixtureId": fix['fixture_id'],
                            "oddJusta": odd,
                            "probPoisson": 50.0,
                            "evPercentual": 0.0,
                            "statusGatekeeper": "NO_BET",
                            "gatekeeperCategory": "Competição Inelegível para Gols",
                            "gatekeeperMsg": f"Gatekeeper Gols (NO_BET): Competição ({lname}) é inelegível para o mercado de Gols Asiáticos devido a desnível técnico acentuado.",
                            "destaque": 0
                        }
                except (ValueError, TypeError):
                    pass

            # 2. Extrair linha e direção (Over / Under) do palpite
            is_over = bool(re.search(r'(?:mais|over)', palpite, re.IGNORECASE))
            m_line = re.search(r'(\d+\.\d+|\d+)', palpite)
            line_val = float(m_line.group(1)) if m_line else 2.5

            # 2.1 Trava Soberana de Linhas Inteiras: Descontinuação absoluta de linhas fracionadas (.25, .5 e .75)
            # Autorizadas exclusivamente linhas inteiras com amortecedor de Push integral (2.0 e 3.0 Gols)
            if round(line_val % 1.0, 2) != 0.0 or line_val not in (2.0, 3.0):
                return {
                    "fixtureId": fix['fixture_id'] if fix else fixture_id,
                    "oddJusta": odd,
                    "probPoisson": 50.0,
                    "evPercentual": 0.0,
                    "statusGatekeeper": "NO_BET",
                    "gatekeeperCategory": "Linha Fracionada Descontinuada",
                    "gatekeeperMsg": "Regra de Bloqueio Gatekeeper (NO_BET): Linhas fracionadas de Gols Asiáticos (.25, .5 e .75) foram permanentemente descontinuadas para proteção da banca contra variância binária e meias-perdas. Autorizadas estritamente linhas inteiras com amortecedor de Push integral (2.0 e 3.0 Gols).",
                    "destaque": 0
                }

            # 3. Obter Histórico U5J e Lambdas de Poisson
            h_name = fix.get('home_team') or home_team
            a_name = fix.get('away_team') or away_team
            h_id = fix.get('home_team_id')
            a_id = fix.get('away_team_id')
            l_id = fix.get('league_id')

            h_form = None
            a_form = None

            try:
                from football_ingest_trends import fetch_team_last5_form
                h_form = fetch_team_last5_form(cursor, h_name, h_id, l_id)
                a_form = fetch_team_last5_form(cursor, a_name, a_id, l_id)
            except Exception as e_u5j:
                print(f"⚠️ [U5J Goals] Falha ao extrair U5J via fetch: {e_u5j}", file=sys.stderr)

            # Fallback seguro: extrair U5J_DATA de ah_reasoning
            if (not h_form or not a_form) and fix.get('ah_reasoning') and 'U5J_DATA:' in fix['ah_reasoning']:
                try:
                    m_u5 = re.search(r'U5J_DATA:\s*(\{.*?\})\s*(?:\|\||$)', fix['ah_reasoning'], re.DOTALL)
                    if m_u5:
                        u_data = json.loads(m_u5.group(1))
                        if not h_form:
                            h_form = u_data.get('home')
                        if not a_form:
                            a_form = u_data.get('away')
                except Exception:
                    pass

            h_sc, h_con, a_sc, a_con = [], [], [], []
            if h_form:
                for m in h_form.get('matches', []):
                    sc = m.get('score', '')
                    if 'x' in sc:
                        p = sc.split('x')
                        h_sc.append(min(5, int(p[0])))
                        h_con.append(min(5, int(p[1])))
            if a_form:
                for m in a_form.get('matches', []):
                    sc = m.get('score', '')
                    if 'x' in sc:
                        p = sc.split('x')
                        a_sc.append(min(5, int(p[0])))
                        a_con.append(min(5, int(p[1])))

            # Eliminação de fallbacks artificiais (Regra 9)
            if not (h_sc and a_con) or not (a_sc and h_con):
                return {
                    "fixtureId": fix['fixture_id'],
                    "oddJusta": None,
                    "probPoisson": None,
                    "evPercentual": None,
                    "statusGatekeeper": "NO_BET",
                    "gatekeeperCategory": "Dados U5J Insuficientes",
                    "gatekeeperMsg": f"Gatekeeper Gols (NO_BET): Ausência de dados estatísticos de U5J para {h_name} e/ou {a_name} (Regra 9).",
                    "destaque": 0
                }

            xg_h = round(((sum(h_sc) / len(h_sc) + sum(a_con) / len(a_con)) / 2.0) * 1.08, 2)
            xg_a = round(((sum(a_sc) / len(a_sc) + sum(h_con) / len(h_con)) / 2.0) * 0.92, 2)

            # 4. Avaliar na Matriz Bivariada de Poisson
            matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)
            eval_res = evaluate_asian_total_line_poisson(matrix, is_over, line_val, odd)

            prob_eff = eval_res['prob_efetiva']
            fair_odd = eval_res['fair_odd']
            ev_pct = eval_res['ev_pct']
            total_xg = round(xg_h + xg_a, 2)

            # 5. Critérios do Gatekeeper
            status_gk = "APROVADO"
            cat_gk = "Gols Asiáticos (+EV)"
            msg_gk = (
                f"Gatekeeper Gols APROVADO (+EV +{ev_pct:.1f}%): Linha {palpite} @ {odd:.2f} vs Odd Justa {fair_odd:.2f} "
                f"(Prob: {prob_eff:.1f}%) | Expectativa total: {total_xg:.2f} gols ({h_name} xG {xg_h:.2f} vs {xg_a:.2f} {a_name})."
            )

            # Trava 2: Verificação de Superfavorito Nominal (1X2 <= 1.20)
            odd_h = float(fix.get('odd_home') or 0.0)
            odd_a = float(fix.get('odd_away') or 0.0)
            has_superfavorite = (0.0 < odd_h <= 1.20) or (0.0 < odd_a <= 1.20)

            if not is_over and has_superfavorite:
                status_gk = "NO_BET"
                cat_gk = "Risco Extremo de Goleada (Superfavorito)"
                msg_gk = f"Gatekeeper Gols (NO_BET): Entrada em Under bloqueada para partidas com superfavorito nominal (odd 1X2 <= 1.20)."
            elif odd < 1.60 or odd > 1.95:
                status_gk = "NO_BET"
                cat_gk = "Odd Fora do Sweet Spot"
                msg_gk = f"Gatekeeper Gols (NO_BET): Odd {odd:.2f} fora da faixa autorizada (1.60 a 1.95)."
            elif ev_pct < 8.0 or prob_eff < 62.0:
                status_gk = "NO_BET"
                cat_gk = "Sem Valor Esperado (+EV) ou Probabilidade Baixa"
                msg_gk = f"Gatekeeper Gols (NO_BET): Probabilidade ({prob_eff:.1f}%) abaixo de 62.0% ou Valor Esperado (+EV {ev_pct:.1f}%) abaixo de +8.0%."
            elif not is_over and line_val == 3.0 and total_xg > 2.30:
                status_gk = "NO_BET"
                cat_gk = "Risco Ofensivo Elevado para Under"
                msg_gk = f"Gatekeeper Gols (NO_BET): Expectativa de gols ({total_xg:.2f}) excede o limite seguro de 2.30 para Menos de 3.0 Gols."
            elif not is_over and line_val == 2.0 and total_xg > 1.85:
                status_gk = "NO_BET"
                cat_gk = "Risco Ofensivo Elevado para Under"
                msg_gk = f"Gatekeeper Gols (NO_BET): Expectativa de gols ({total_xg:.2f}) excede o limite de 1.85 para Menos de 2.0 Gols."
            elif is_over and line_val == 2.0 and total_xg < 2.35:
                status_gk = "NO_BET"
                cat_gk = "Expectativa de Gols Baixa para Over"
                msg_gk = f"Gatekeeper Gols (NO_BET): Expectativa de gols ({total_xg:.2f}) insuficiente para Mais de 2.0 Gols (mínimo 2.35)."
            elif is_over and line_val == 3.0 and total_xg < 2.80:
                status_gk = "NO_BET"
                cat_gk = "Expectativa de Gols Insuficiente para Over 3.0"
                msg_gk = f"Gatekeeper Gols (NO_BET): Expectativa de gols ({total_xg:.2f}) insuficiente para Mais de 3.0 Gols (mínimo 2.80)."

            destaque_val = 0
            if status_gk == 'APROVADO':
                if ev_pct >= 15.0 and prob_eff >= 68.0:
                    destaque_val = BetTier.PREMIUM.value
                    cat_gk = BetTier.PREMIUM.gatekeeper_category
                else:
                    destaque_val = BetTier.QUASE_PREMIUM.value
                    cat_gk = BetTier.QUASE_PREMIUM.gatekeeper_category

            return {
                "fixtureId": fix['fixture_id'],
                "oddJusta": fair_odd,
                "probPoisson": prob_eff,
                "evPercentual": ev_pct,
                "statusGatekeeper": status_gk,
                "gatekeeperCategory": cat_gk,
                "gatekeeperMsg": msg_gk,
                "destaque": destaque_val
            }
    finally:
        conn.close()


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser(description="Motor canônico de Gols Asiáticos")
    parser.add_argument("--eval_bet", action="store_true", help="Avaliar aposta via Gatekeeper")
    parser.add_argument("--fixture_id", type=int, default=None)
    parser.add_argument("--home_team", type=str, default="")
    parser.add_argument("--away_team", type=str, default="")
    parser.add_argument("--palpite", type=str, default="")
    parser.add_argument("--odd", type=float, default=1.80)
    args = parser.parse_args()

    if args.eval_bet:
        res = cli_evaluate_goals_bet(
            fixture_id=args.fixture_id,
            home_team=args.home_team,
            away_team=args.away_team,
            palpite=args.palpite,
            odd=args.odd
        )
        print(json.dumps(res, ensure_ascii=False))
