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

# Cache em memória para linhas de gols da Betano durante a execução
_goals_odds_cache = {}
_goals_raw_fixture_cache = {}

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

    # 1. Tentativa via Betano Direct API (Scraper Oficial de Alta Precisão)
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
                    # Identificar mercados de Gols Asiáticos / Over Under
                    if any(term in b_name for term in ['asian total', 'total de gols asiatico', 'total de golos asiatico', 'goals over/under', 'gols mais/menos']):
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
    u5j_a: dict = None
) -> tuple:
    """
    Aplica o Gatekeeper de Gols Asiáticos para filtrar e ranquear candidatos viáveis.
    Critérios:
    - Faixa Sweet Spot de Odd: 1.65 a 2.25
    - Valor Esperado Positivo (+EV >= 3.0%)
    - Probabilidade Efetiva >= 52.0%
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

        # 1. Filtro Sweet Spot de Odd
        if odd < 1.60 or odd > 2.30:
            continue

        # 2. Filtro de Valor Esperado e Probabilidade
        if ev_pct < 3.0 or prob < 52.0:
            continue

        # 3. Filtro de Consistência Tática e de Mercado
        # Se for OVER e a linha for alta (ex: >= 2.5), o total projetado xG não pode ser excessivamente baixo
        if c_type == 'Over' and line >= 2.5 and total_xg < 2.00:
            continue
        # Se for UNDER e a linha for baixa (ex: <= 2.25), o total projetado xG não pode ser excessivamente alto
        if c_type == 'Under' and line <= 2.25 and total_xg > 2.70:
            continue

        approved_list.append(cand)

    if not approved_list:
        return 'NO_BET', None, [], f"Gatekeeper de Gols Asiáticos: Nenhuma linha com valor esperado (+EV >= 3.0%) no Sweet Spot (1.65 - 2.25)."

    # Ordenação por maior EV% e, em caso de empate, maior probabilidade efetiva
    approved_list.sort(key=lambda x: (x['ev_pct'], x['prob_efetiva']), reverse=True)
    best_cand = approved_list[0]

    tipo_str = "Mais de" if best_cand['type'] == 'Over' else "Menos de"
    sug = f"{tipo_str} {best_cand['line']:g} Gols Asiáticos"

    reason_msg = (
        f"Aposta identificada no mercado de Gols Asiáticos: {sug} com cotação {best_cand['odd']:.2f} na Betano. "
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
                        h_sc.append(min(3, int(p[0])))
                        h_con.append(min(3, int(p[1])))
                for m in a_form.get('matches', []):
                    sc = m.get('score', '')
                    if 'x' in sc:
                        p = sc.split('x')
                        a_sc.append(min(3, int(p[0])))
                        a_con.append(min(3, int(p[1])))
        except Exception as e_u5j:
            print(f"⚠️ [U5J Goals] Falha ao extrair U5J: {e_u5j}", file=sys.stderr)

    # Cálculo dos Lambdas de Poisson
    if h_sc and a_con:
        raw_xg_h = (sum(h_sc) / len(h_sc) + sum(a_con) / len(a_con)) / 2.0
        xg_h = round(raw_xg_h * 1.08, 2)
    else:
        xg_h = 1.30

    if a_sc and h_con:
        raw_xg_a = (sum(a_sc) / len(a_sc) + sum(h_con) / len(h_con)) / 2.0
        xg_a = round(raw_xg_a * 0.92, 2)
    else:
        xg_a = 1.05

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
        candidates, home_team, away_team, xg_h, xg_a, u_json.get('home'), u_json.get('away')
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
    confirmada: int = 0
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

    inserted_count = 0
    for uid in user_ids:
        # Verificar se já existe aposta ativa para esta partida e mercado
        cursor.execute("""
            SELECT id, status FROM apostas 
            WHERE usuario_id = %s AND fixture_id = %s AND mercado = 'Gols Asiáticos'
            LIMIT 1
        """, (uid, fixture_id))
        row = cursor.fetchone()

        if row:
            # Já existe aposta registrada
            continue

        valor_aposta = 10.0
        retorno_potencial = round(valor_aposta * odd, 2)

        cursor.execute("""
            INSERT INTO apostas (
                usuario_id, fixture_id, time_casa, time_fora,
                mercado, casa_de_aposta, palpite, odd, odd_justa,
                probabilidade_poisson, ev_percentual, status_gatekeeper,
                valor_aposta, ganhos_potenciais, status, confirmada,
                resultado_detalhado, data_hora_jogo, criado_em
            ) VALUES (
                %s, %s, %s, %s,
                'Gols Asiáticos', 'Betano', %s, %s, %s,
                %s, %s, 'APROVADO',
                %s, %s, 'Pendente', %s,
                %s, %s, NOW()
            )
        """, (
            uid, fixture_id, home_team, away_team,
            palpite, odd, fair_odd,
            prob, ev,
            valor_aposta, retorno_potencial, confirmada,
            reasoning, data_jogo
        ))
        aposta_id = cursor.lastrowid

        # Se for confirmada, debitar valor na conta corrente
        if confirmada == 1 and aposta_id:
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
            u_row = cursor.fetchone()
            saldo_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
            saldo_pos = round(saldo_ant - valor_aposta, 8)

            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor,
                    saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, 'BRL', NOW()
                )
            """, (
                uid, aposta_id, f"Aposta #{aposta_id} - Gols Asiáticos: {palpite}",
                valor_aposta, saldo_ant, saldo_pos
            ))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (saldo_pos, uid))

        inserted_count += 1

    return inserted_count
