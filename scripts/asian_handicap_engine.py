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
    Calcula a pontuação de eficiência ponderada nos últimos 5 jogos (U5J) com Strength of Schedule (SOS)
    e Momentum de Curva Recente:
    - Vitória contra Gigante Tier 1: +5.0 pts (Super-Majorada)
    - Vitória Comum: +3.0 pts
    - Empate contra Gigante Tier 1: +2.5 pts (Majorada)
    - Empate Comum: +1.0 pt
    - Derrota Fora contra Gigante Tier 1: +0.5 pt (Atenuada por resiliência competitiva)
    - Derrota em Casa contra Gigante Tier 1: 0.0 pts
    - Derrota Comum: -1.0 pt (Subtraída)
    - Multiplicador SOS: 1.0 + (tier1_count * 0.12)
    - Fator de Momentum / Tendência Temporal (J0, J1 vs J2, J3, J4):
      * 🚀 CURVA_ASCENDENTE: 1.20x (+20% de majoração por aceleração de momentum)
      * ⚖️ CURVA_ESTAGNADA: 0.90x (-10% débito por platô / excesso de empates)
      * 📉 CURVA_DESCENDENTE: 0.75x (-25% penalização severa por queda recente de rendimento)
      * 🛡️ CURVA_ESTAVEL: 1.05x (0 derrotas) / 1.00x (neutro)
    - Teto de Invencibilidade sem Tier 1: máx 11.0 pts (antes de majoração de momentum)
    """
    if not last5_dict or not isinstance(last5_dict, dict):
        return 0.0

    matches = last5_dict.get('matches', [])
    if matches:
        # Ordena rigorosamente do mais recente (J0) para o mais antigo (J4)
        def _parse_match_date_key(m):
            d_str = str(m.get('date', '')).strip()
            if d_str and len(d_str) >= 5:
                parts = d_str.split('/')
                if len(parts) == 3:
                    return f"{parts[2]}-{parts[1]}-{parts[0]}"
                elif len(parts) == 2:
                    return f"2026-{parts[1]}-{parts[0]}"
            return ""

        if any(m.get('date') for m in matches):
            sorted_matches = sorted(matches[:5], key=_parse_match_date_key, reverse=True)
        else:
            sorted_matches = list(matches[:5])

        pts_eff = 0.0
        tier1_count = 0
        pts_raw = []
        for m in sorted_matches[:5]:
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
                pts_raw.append(3)
                pts_eff += 5.0 if is_t1 else 3.0
            elif res == 'E':
                pts_raw.append(1)
                pts_eff += 2.5 if is_t1 else 1.0
            elif res == 'D':
                pts_raw.append(0)
                if is_t1:
                    pts_eff += 0.5 if not is_home else 0.0
                else:
                    pts_eff -= 1.0

        # Análise de Momentum / Trajetória Temporal
        trend = "CURVA_ESTAVEL"
        trend_factor = 1.00
        trend_label = "🛡️ Estável"
        trend_desc = "Rendimento Estável"

        if len(pts_raw) >= 5:
            avg_recent = (pts_raw[0] + pts_raw[1]) / 2.0  # escala 0 a 3.0
            avg_baseline = (pts_raw[2] + pts_raw[3] + pts_raw[4]) / 3.0  # escala 0 a 3.0
            delta_trend = avg_recent - avg_baseline

            num_v = sum(1 for p in pts_raw if p == 3)
            num_e = sum(1 for p in pts_raw if p == 1)
            num_d = sum(1 for p in pts_raw if p == 0)

            # Definição e inicialização de variáveis de declínio (Regra 8)
            has_two_recent_stumbles = False
            is_j0_tier1_loss = False
            is_j1_tier1_loss = False
            is_tier1_mitigated = False
            is_real_decline = False

            has_two_recent_stumbles = (pts_raw[0] <= 1 and pts_raw[1] <= 1)
            is_j0_tier1_loss = (pts_raw[0] == 0 and bool(sorted_matches[0].get('is_tier_1'))) if len(sorted_matches) > 0 else False
            is_j1_tier1_loss = (pts_raw[1] == 0 and bool(sorted_matches[1].get('is_tier_1'))) if len(sorted_matches) > 1 else False
            is_tier1_mitigated = (is_j0_tier1_loss and pts_raw[1] >= 1) or (is_j1_tier1_loss and pts_raw[0] >= 1)

            # Declínio real exige tropeço nos 2 últimos jogos (sem vitória), sem atenuação Tier 1, e máximo 2 vitórias no U5J
            is_real_decline = (
                has_two_recent_stumbles and
                not is_tier1_mitigated and
                (delta_trend <= -0.50 or avg_recent <= 0.5) and
                num_v <= 2
            )

            if (delta_trend >= 0.70 or (avg_recent >= 2.5 and avg_recent > avg_baseline)) and pts_raw[0] == 3:
                trend = "CURVA_ASCENDENTE"
                trend_factor = 1.20  # +20% de aceleração de momentum
                trend_label = "🚀 Ascensão"
                trend_desc = f"Curva Ascendente em alta (Momentum positivo: {pts_raw[0]} e {pts_raw[1]} pts recentes vs {avg_baseline:.1f} pts de base)"
            elif num_d <= 1 and (num_v >= 3 or (num_v >= 2 and (pts_raw[0] == 3 or pts_raw[1] == 3))):
                trend = "CURVA_ESTAVEL"
                trend_factor = 1.05 if num_d == 0 else 1.00
                trend_label = "🛡️ Estável"
                inv_desc = "Invencibilidade sólida (0 derrotas)" if num_d == 0 else "Rendimento seguro (apenas 1 derrota)"
                trend_desc = f"Rendimento Sólido / Quase Invicto ({num_v}V-{num_e}E-{num_d}D) - {inv_desc}"
            elif (num_e >= 3) or (pts_raw[0] == 1 and pts_raw[1] == 1) or (num_v <= 1 and num_e >= 2):
                trend = "CURVA_ESTAGNADA"
                trend_factor = 0.90  # -10% por platô mediano / excesso de empates
                trend_label = "⚖️ Estagnação"
                trend_desc = f"Tendência de Estagnação / Platô Mediano ({num_e} empates nos últimos jogos)"
            elif is_real_decline:
                trend = "CURVA_DESCENDENTE"
                trend_factor = 0.80  # -20% por queda de rendimento recente
                trend_label = "📉 Declínio"
                trend_desc = f"Curva Descendente em queda (Tropeço recente nos 2 últimos jogos: {pts_raw[0]} e {pts_raw[1]} pts vs {avg_baseline:.1f} pts de base)"
            else:
                trend = "CURVA_ESTAVEL"
                trend_factor = 1.00
                trend_label = "🛡️ Estável"
                trend_desc = f"Rendimento Estável ({num_v}V-{num_e}E-{num_d}D)"

        sos_mult = 1.0 + (tier1_count * 0.12)
        pts_eff_total = round(pts_eff * sos_mult * trend_factor, 1)
        if tier1_count == 0 and pts_eff_total > 11.0:
            pts_eff_total = 11.0

        last5_dict['trend'] = trend
        last5_dict['trend_factor'] = trend_factor
        last5_dict['trend_label'] = trend_label
        last5_dict['trend_desc'] = trend_desc
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


GATEKEEPER_DIDACTIC_MAP = {
    'Falta de Valor Esperado (+EV)': 'A probabilidade real calculada pelo modelo não compensa o preço pago pela casa de apostas (o prêmio oferecido pela odd está abaixo do risco matemático do confronto).',
    'Equilíbrio Excessivo ou Inconsistência': 'Duelo onde o rendimento e pontuação recente de ambos os times são excessivamente parelhos e nenhuma linha de handicap ofereceu margem de segurança segura.',
    'Duelo de Crises': 'Ambos os times chegam em momento técnico muito ruim nos últimos 5 jogos (baixo aproveitamento e pontuação de eficiência precária), tornando o desfecho altamente imprevisível.',
    'Queda de Rendimento Recente': 'O favorito vem de queda acentuada de rendimento e pontuação recente nos últimos 5 jogos, elevando o risco de oscilação.',
    'Odd Abaixo do Piso Mínimo': 'A cotação disponível está abaixo do piso mínimo de 1.50, inviabilizando a relação de rentabilidade da banca.',
    'Amostragem Insuficiente': 'Histórico recente incompleto (< 5 partidas consolidadas), impedindo a modelagem estatística segura.',
    'Aguardando Abertura de Mercado': 'Cotações de Handicap Asiático aguardando abertura oficial de mercado nas casas de apostas.',
    'Odds de Mercado Indisponíveis': 'Ausência de cotações 1X2 de mercado para precificação da partida.',
    'Super-Favorito Dominante': 'Equipe de elite (Tier 1) com alta dominância técnica, histórico consistente e linha de proteção ajustada dentro da margem de segurança da banca.',
    'Valor Esperado Positivo (+EV)': 'Assimetria estatística identificada: a probabilidade de vitória/cobertura calculada pelo modelo Poisson supera a probabilidade precificada pela casa de apostas.',
    'Cobertura de Azarão em Alta': 'Azarão em grande momento de eficiência recente contra favorito nominal vulnerável, com linha de proteção que confere alta cobertura.',
    'Sobrevivência do Mandante': 'Mandante em situação crítica ou na segunda metade da tabela atuando em casa contra visitante em duelo parelho; abstenção por risco de sobrevivência.',
    'Mando de Campo Soberano': 'As casas de apostas precificam o mandante como favorito no 1X2, neutralizando a superioridade teórica do visitante.'
}


def determine_gatekeeper_category(status_gk: str, suggestion: str, reason: str, best_cand: dict = None) -> str:
    """
    Classifica formal e deterministicamente a decisão do Gatekeeper em uma categoria analítica
    para armazenamento nas tabelas 'fixtures_trends' e 'apostas', viabilizando filtros e relatórios futuros.
    """
    sug_clean = str(suggestion or '').strip().lower()
    r_text = str(reason or '').strip()
    is_abstencao = (status_gk == 'NO_BET') or any(w in sug_clean for w in ['abstenção', 'abstencao', 'sem entrada', 'bloquead', 'no_bet'])

    if not is_abstencao:
        # Categorias de Aprovação (APROVADO / Bet OK)
        if best_cand and (best_cand.get('destaque') == 1 or best_cand.get('is_destaque') == 1) or 'Tier 1 Dominante' in r_text or 'Super-Favorito' in r_text:
            return 'Super-Favorito Dominante'
        if ('0.0' in sug_clean or '+0.25' in sug_clean or 'empate anula' in sug_clean) and any(w in r_text.lower() for w in ['azarão', 'azarao', 'distorção', 'superioridade nos últimos 5 jogos']):
            return 'Cobertura de Azarão em Alta'
        return 'Valor Esperado Positivo (+EV)'

    # Categorias de Abstenção (NO_BET)
    if any(k in r_text for k in ['Sobrevivência do Mandante', 'pressão crítica na tabela']):
        return 'Sobrevivência do Mandante'
    if any(k in r_text for k in ['Mando de Campo Soberano', 'divergência de mercado']):
        return 'Mando de Campo Soberano'
    if any(k in r_text for k in ['Duelo de Crises', 'crise severa', 'ambas as equipes em momento técnico desfavorável']):
        return 'Duelo de Crises'
    if any(k in r_text for k in ['Queda de Rendimento', 'CURVA_DESCENDENTE']):
        return 'Queda de Rendimento Recente'
    if any(k in r_text for k in ['Odd Abaixo do Piso', 'abaixo do piso mínimo']):
        return 'Odd Abaixo do Piso Mínimo'
    if any(k in r_text for k in ['Amostragem Insuficiente', 'Histórico recente incompleto', '< 5 partidas']):
        return 'Amostragem Insuficiente'
    if any(k in r_text for k in ['Ausência de Linhas Reais', 'aguardando abertura']):
        return 'Aguardando Abertura de Mercado'
    if any(k in r_text for k in ['Odds 1X2 Ausentes', 'Ausência de cotações 1X2']):
        return 'Odds de Mercado Indisponíveis'
    if any(k in r_text for k in ['Sem EV+', 'limiares de rentabilidade', 'não atingiram os limiares']):
        return 'Falta de Valor Esperado (+EV)'

    return 'Equilíbrio Excessivo ou Inconsistência'


def format_gatekeeper_result(status_gk: str, suggestion: str, reason: str, category: str = None) -> str:
    """
    Padroniza rigorosamente o resultado do Gatekeeper no formato oficial:
    STATUS GK: {status_gk}
    SUGGESTION: {suggestion}
    CATEGORIA: {category}
    REASON: 💡 Síntese da IA: {didactic}
    {clean_reason}
    """
    clean_reason = str(reason or '').strip()
    if not category:
        category = determine_gatekeeper_category(status_gk, suggestion, clean_reason)
    
    didactic = GATEKEEPER_DIDACTIC_MAP.get(category, '')
    
    # Se clean_reason já possui o bloco STATUS GK, preserva e injeta CATEGORIA caso ausente
    if clean_reason.startswith("STATUS GK:"):
        if "CATEGORIA:" not in clean_reason:
            parts = clean_reason.split("REASON:", 1)
            if len(parts) == 2:
                header = parts[0].strip()
                body = parts[1].strip()
                if didactic and f"💡 Síntese da IA: {didactic}" not in body:
                    body = f"💡 Síntese da IA: {didactic}\n\n{body}"
                return f"{header}\nCATEGORIA: {category}\nREASON: {body}"
        return clean_reason

    # Se a síntese didática ainda não está no corpo do texto, insere no início
    if didactic and f"💡 Síntese da IA: {didactic}" not in clean_reason:
        reason_formatted = f"💡 Síntese da IA: {didactic}\n\n{clean_reason}"
    else:
        reason_formatted = clean_reason

    return f"STATUS GK: {status_gk}\nSUGGESTION: {suggestion}\nCATEGORIA: {category}\nREASON: {reason_formatted}"


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
    Regra 12 (AGENTS.md): Proibição absoluta de geração de dados sintéticos e odds fictícias.
    Retorna lista vazia ([]). É terminantemente proibido inventar ou interpolar linhas/odds sintéticas
    (POISSON_SYNTHETIC) a partir do 1X2. Toda aposta deve obrigatoriamente possuir cotações reais de bookmakers.
    """
    return []


def evaluate_and_select_best_ah_candidate(
    poisson_matrix: dict,
    candidate_lines: list,
    home_team: str,
    away_team: str,
    odd_home: float,
    odd_away: float,
    min_ev: float = 5.0,
    min_prob: float = 58.0,
    home_team_id: int = None,
    away_team_id: int = None,
    home_last5: dict = None,
    away_last5: dict = None,
    xg_home: float = None,
    xg_away: float = None,
    home_rank: int = None,
    away_rank: int = None,
    home_ppg: float = None,
    away_ppg: float = None,
    home_zone: str = None,
    away_zone: str = None,
    standings_motivation_score: float = None
):
    """
    Aplica o crivo rigoroso do Gatekeeper do Handicap Asiático em todas as linhas candidatas:
    - Janela estrita de linhas permitidas: {0.0, 0.5, 1.0, 1.25, 1.5} (anti-empate / colchão defensivo; +0.75 AH excluído para evitar meio-reds)
    - Exceção de Super-Favoritos Tier 1 (odd <= 1.22, ratio >= 8.0x): permite linhas negativas moderadas {-0.75, -1.0} EXCLUSIVAMENTE para Mandantes (Home).
    - Trava de Mando de Campo (Opção B): Para Visitantes (Away), o teto de agressividade é -0.25 AH (com proteção de empate). Linhas < -0.25 AH (-0.5, -0.75, -1.0) são estritamente proibidas fora de casa.
    - Proibição Absoluta: Nenhuma linha mais agressiva que -1.0 AH (-1.25, -1.5, -1.75, -2.0) é tolerada.
    - Teto de cotação para -0.25 AH: máximo @ 1.85 (odds > 1.85 indicam favoritismo frágil da banca).
    - Trava de Sobrevivência do Mandante (Anti-Caldeirão da Degola): Bloqueia apoio a visitante em linha curta (0.0 AH ou -0.25 AH) quando mandante está na zona de degola ou sob pressão crítica de tabela com abismo de posições em jogo parelho.
    - Trava de Divergência de Mando (Anti-Fake Dog): Bloqueia apoio a visitante em linha curta se o mercado precifica o mandante como favorito no 1X2 (odd_home < odd_away).
    - Faixa de odd segura: 1.50 a 2.10 (1.40 a 1.95 para super-favoritos negativos).
    - Trava de coerência: favorito 1X2 não recebe handicap positivo > 0.0.
    - Gatekeeper: EV% >= min_ev (5.0%) e Probabilidade Efetiva >= min_prob (58.0% / 55.0% para -0.25 AH).
    - Score de Valor = EV% * (Prob / 100.0)
    """
    standard_allowed_lines = {0.0}
    moderate_negative_lines = {-0.5, -0.75, -1.0}
    approved = []
    raw_h_odd = float(odd_home or 0.0)
    raw_a_odd = float(odd_away or 0.0)
    if raw_h_odd <= 1.0 or raw_a_odd <= 1.0:
        return None, []

    # Inicialização explícita de variáveis numéricas locais (Regra 8)
    cand_pts = 0
    opp_pts = 0
    cand_v = 0
    opp_v = 0
    cand_d = 0
    opp_d = 0
    cand_pts_eff = 0.0
    opp_pts_eff = 0.0
    cand_odd = 0.0
    opp_odd = 0.0
    cand_ratio = 0.0
    opp_ratio = 1.0
    u5j_diff = 0.0
    required_ev = 5.0
    required_prob = 58.0
    ev = 0.0
    prob_eff = 0.0
    score = 0.0
    inv_cand = 0.5
    inv_opp = 0.5
    market_cand_win_prob = 50.0
    p_pure_win = 50.0
    max_divergence = 18.0
    xg_h_val = 0.0
    xg_a_val = 0.0
    xg_diff = 0.0
    is_home_poisson_dominant_minus_half = False

    h_rank_val = 0
    a_rank_val = 0
    h_ppg_val = 0.0
    a_ppg_val = 0.0
    standings_mot_val = 0.0
    is_home_in_relegation = False
    is_home_under_threat = False

    try:
        if home_rank is not None and str(home_rank).strip():
            h_rank_val = int(home_rank)
    except Exception:
        h_rank_val = 0

    try:
        if away_rank is not None and str(away_rank).strip():
            a_rank_val = int(away_rank)
    except Exception:
        a_rank_val = 0

    try:
        if home_ppg is not None and str(home_ppg).strip():
            h_ppg_val = float(home_ppg)
    except Exception:
        h_ppg_val = 0.0

    try:
        if away_ppg is not None and str(away_ppg).strip():
            a_ppg_val = float(away_ppg)
    except Exception:
        a_ppg_val = 0.0

    try:
        if standings_motivation_score is not None and str(standings_motivation_score).strip():
            standings_mot_val = float(standings_motivation_score)
    except Exception:
        standings_mot_val = 0.0

    h_zone_str = str(home_zone or '').lower()
    is_home_in_relegation = ('relegat' in h_zone_str or 'play out' in h_zone_str or 'play-out' in h_zone_str or 'rebaixamento' in h_zone_str)
    is_home_under_threat = (
        is_home_in_relegation or
        (h_rank_val >= 12 and h_ppg_val > 0.0 and h_ppg_val <= 1.25) or
        (h_rank_val >= 12 and standings_mot_val >= 3.0) or
        (h_rank_val >= 12 and a_rank_val > 0 and (h_rank_val - a_rank_val >= 6 or a_rank_val <= 6))
    )

    if (xg_home is None or xg_away is None) and poisson_matrix:
        try:
            xg_h_val = float(sum(x * p for (x, y), p in poisson_matrix.items()))
            xg_a_val = float(sum(y * p for (x, y), p in poisson_matrix.items()))
        except Exception:
            xg_h_val = float(xg_home or 0.0)
            xg_a_val = float(xg_away or 0.0)
    else:
        xg_h_val = float(xg_home or 0.0)
        xg_a_val = float(xg_away or 0.0)
    xg_diff = xg_h_val - xg_a_val

    # 0. Trava Sistêmica Mandatória de Duelo de Crises (Ambas as Equipes em Má Fase):
    # Se ambas as equipes apresentarem aproveitamento precário no U5J (eficiência <= 3.0 pts ou <= 1 vitória recente cada),
    # o confronto é marcado por desorganização tática e extrema aleatoriedade.
    # A estratégia de Handicap Asiático é vetada pelo Gatekeeper (abstenção mandatória / NO_BET).
    h_eff_val = compute_team_u5j_efficiency(home_last5)
    a_eff_val = compute_team_u5j_efficiency(away_last5)
    h_v_cnt = home_last5.get('v', 0) if isinstance(home_last5, dict) else 0
    a_v_cnt = away_last5.get('v', 0) if isinstance(away_last5, dict) else 0
    h_pts_cnt = home_last5.get('pts', 0) if isinstance(home_last5, dict) else 0
    a_pts_cnt = away_last5.get('pts', 0) if isinstance(away_last5, dict) else 0

    if (h_eff_val <= 3.0 and a_eff_val <= 3.0) or (h_v_cnt <= 1 and a_v_cnt <= 1 and h_pts_cnt <= 4 and a_pts_cnt <= 4):
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

        # =========================================================================
        # REGRA ESTRUTURAL ANTI-ZEBRA: BLOQUEIO DE LINHAS POSITIVAS (+AH > 0.0)
        # O histórico empírico consolidado comprovou que apoiar azarões em linhas positivas
        # (+0.25, +0.5, +0.75, +1.0, +1.25, +1.5) com cotações espremidas (1.50 a 1.75)
        # gera expectativa matemática negativa (-25.3% ROI).
        # O Gatekeeper passa a operar com FOCO ESTRITO EM FAVORITOS (-AH) e DNB (0.0 AH).
        # =========================================================================
        if c_line > 0.0:
            continue

        # =========================================================================
        # REGRA MANDATÓRIA DE MANDO DE CAMPO (OPÇÃO B - PROTEÇÃO FORA DE CASA):
        # Linhas esticadas que não toleram empate (c_line < -0.25, como -0.5, -0.75, -1.0)
        # são autorizadas EXCLUSIVAMENTE para Mandantes (Home).
        # Para Visitantes (Away), o teto de agressividade é -0.25 AH (com tolerância/meio-reembolso no empate).
        # =========================================================================
        if c_is_away and c_line < -0.25:
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
        cand_trend = (cand_l5 or {}).get('trend') or cand_trend_info.get("trend", "CURVA_ESTAVEL")
        opp_trend = (opp_l5 or {}).get('trend') or opp_trend_info.get("trend", "CURVA_ESTAVEL")

        is_tier1 = is_tier_1_elite_club(team_id=cand_id, team_name=cand_team)
        is_opp_tier1 = is_tier_1_elite_club(team_id=opp_id, team_name=opp_team)
        u5j_diff = abs(cand_pts_eff - opp_pts_eff)
        opp_ratio = (cand_odd / opp_odd) if opp_odd > 0 else 1.0
        cand_ratio = (opp_odd / cand_odd) if cand_odd > 0 else 1.0

        is_tier1_massacre_close_u5j = (
            is_tier1 and not is_opp_tier1 and
            (cand_odd <= 1.35 or cand_ratio >= 4.5) and
            u5j_diff <= 2.0
        )

        # =========================================================================
        # REGRA DE OURO MANDATÓRIA: TIER 1 EM MASSACRE (ODD <= 1.35) COM U5J PRÓXIMO (<= 2.0 PTS)
        # 1) Se o adversário for Tier 1 em massacre e U5J próximo: NUNCA apoiar o azarão!
        # 2) Se o candidato for o Tier 1 em massacre com U5J próximo: A única linha permitida é 0.0 AH!
        # =========================================================================
        if is_opp_tier1 and not is_tier1 and (opp_odd <= 1.35 or opp_ratio >= 4.5) and u5j_diff <= 2.0:
            continue

        if is_tier1_massacre_close_u5j and c_line != 0.0:
            continue

        # =========================================================================
        # CRITÉRIO MANDATÓRIO: TRAVA DIRECIONAL DE MOMENTUM (ASCENSÃO VS DECLÍNIO)
        # Se a equipe candidata estiver em CURVA_DESCENDENTE ou CURVA_ESTAGNADA
        # e o adversário estiver em CURVA_ASCENDENTE, é expressamente PROIBIDO apoiar
        # a candidata em qualquer linha de favoritismo ou DNB (c_line <= 0.0)!
        # =========================================================================
        if opp_trend == "CURVA_ASCENDENTE" and cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA") and c_line <= 0.0:
            continue

        # =========================================================================
        # REGRA ESTRUTURAL ANTI-EMBOSCADA 1: TRAVA DE SOBREVIVÊNCIA DO MANDANTE
        # (Anti-Caldeirão da Degola / Abismo de Tabela)
        # Quando o candidato é o Visitante em linha curta (c_line <= 0.0) e o mandante
        # está sob pressão extrema na tabela (rebaixamento, fragilidade ou abismo),
        # o fator campo e a urgência de sobrevivência neutralizam a superioridade teórica.
        # Em jogos competitivos (cand_odd >= 2.10) sem dominância Tier 1, ABSTENÇÃO MANDATÓRIA!
        # =========================================================================
        if c_is_away and c_line <= 0.0 and not is_tier1 and cand_odd >= 2.10:
            if is_home_in_relegation:
                continue
            if h_rank_val >= 12 and (a_rank_val > 0 and (h_rank_val - a_rank_val >= 6 or a_rank_val <= 6)):
                continue
            if h_rank_val >= 12 and h_ppg_val > 0.0 and h_ppg_val <= 1.25:
                continue

        # =========================================================================
        # REGRA ESTRUTURAL ANTI-EMBOSCADA 2: TRAVA DE DIVERGÊNCIA DE MANDO (ANTI-FAKE DOG)
        # Quando as casas de apostas precificam o mandante como favorito no 1X2
        # (raw_h_odd < raw_a_odd com margem >= 0.15), o mercado alerta sobre a força e
        # adaptação do mandante em seus domínios.
        # É expressamente PROIBIDO forçar aposta no visitante em linha curta (c_line <= 0.0)
        # apenas pelo momento recente do U5J, respeitando a precificação da banca.
        # =========================================================================
        if c_is_away and c_line <= 0.0 and not is_tier1:
            if raw_h_odd > 0 and raw_a_odd > 0 and (raw_a_odd - raw_h_odd) >= 0.15:
                continue

        # =========================================================================
        # CRITÉRIO PRIMÁRIO MANDATÓRIO: SOBERANIA DA PERFORMANCE REAL (U5J) SOBRE AS ODDS
        # A equipe com melhor performance recente (total de pontos + em ascensão ou invicta)
        # prevalece soberanamente sobre as odds das bancas. As odds 1X2 caem para critério secundário.
        # =========================================================================
        cand_is_better_performance = (
            (cand_pts_eff > opp_pts_eff and (cand_pts_eff - opp_pts_eff) >= 1.5 and cand_trend != "CURVA_DESCENDENTE") or
            (cand_pts_eff >= opp_pts_eff and cand_pts > opp_pts and cand_trend != "CURVA_DESCENDENTE") or
            (cand_trend == "CURVA_ASCENDENTE" and opp_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA")) or
            (cand_d == 0 and opp_d >= 2 and cand_pts_eff >= opp_pts_eff and cand_trend != "CURVA_DESCENDENTE")
        )
        opp_is_better_performance = (
            (opp_pts_eff > cand_pts_eff and (opp_pts_eff - cand_pts_eff) >= 1.5 and opp_trend != "CURVA_DESCENDENTE") or
            (opp_pts_eff >= cand_pts_eff and opp_pts > cand_pts and opp_trend != "CURVA_DESCENDENTE") or
            (opp_trend == "CURVA_ASCENDENTE" and cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA")) or
            (opp_d == 0 and cand_d >= 2 and opp_pts_eff >= cand_pts_eff and opp_trend != "CURVA_DESCENDENTE")
        )

        # 1. PROIBIÇÃO SOBERANA DE APOIAR TIME COM PIOR PERFORMANCE EM LINHA SECA OU FAVORÁVEL:
        # Se o adversário possui melhor performance no U5J (mais pontos, em ascensão ou invicto),
        # é expressamente PROIBIDO apostar na equipe candidata em qualquer linha que exija vitória ou DNB (c_line <= 0.0),
        # exceto se a candidata for Tier 1 em massacre com U5J próximo na linha mandatória 0.0 AH!
        if opp_is_better_performance and c_line <= 0.0 and not (is_tier1_massacre_close_u5j and c_line == 0.0):
            continue

        # 2. TRAVA DE TIME EM CRISE OU FORMA NEGATIVA:
        # Equipes com 4+ derrotas no U5J, sem vitórias (0V) ou com eficiência negativa (<= 0.0)
        # NUNCA podem ser apoiadas em c_line <= 0.0, independentemente de odds de mercado.
        if cand_l5 and isinstance(cand_l5, dict) and (cand_d >= 4 or cand_v == 0 or cand_pts_eff <= 0.0):
            if c_line <= 0.0:
                continue

        # 2.1 TRAVA DE AZARÃO EM DECLÍNIO / HISTÓRICO FRÁGIL (+AH):
        # Linhas positivas (+0.5 AH, +1.0 AH) só podem apoiar zebras com competitividade comprovada.
        # É TERMINANTEMENTE PROIBIDO apoiar equipe em linha positiva (> 0.0 AH) se:
        # 1) Estiver em CURVA_DESCENDENTE no U5J; ou
        # 2) Tiver apenas 1 ou nenhuma vitória recente (cand_v <= 1 com cand_pts <= 4); ou
        # 3) Tiver eficiência ponderada precária (cand_pts_eff <= 3.0 pts).
        if c_line > 0.0:
            if cand_trend == "CURVA_DESCENDENTE":
                continue
            if cand_l5 and isinstance(cand_l5, dict):
                if cand_v <= 1 and cand_pts <= 4:
                    continue
                if cand_pts_eff <= 3.0:
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
            # permite linhas de proteção (+0.5 AH, +1.0 AH) para punir a distorção da banca!
            if (cand_is_better_performance or is_away_momentum_surge) and c_line in (0.5, 1.0):
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

        # 2. Trava Anti-Aposta Contra Gigante Tier 1 Favorito:
        # Se o adversário for clube Tier 1 de Elite e tiver favoritismo de mercado (opp_odd < cand_odd e opp_odd <= 2.65),
        # e a equipe candidata NÃO for Tier 1:
        # - Bloqueia terminantemente apostas nas linhas curtas (0.0 AH e +0.5 AH).
        # - Bloqueia terminantemente qualquer apoio à zebra se ela estiver em declínio (cand_trend == 'CURVA_DESCENDENTE')
        #   ou tiver eficiência U5J inferior à do Tier 1 (cand_pts_eff < opp_pts_eff).
        if is_opp_tier1 and not is_tier1:
            is_opp_fav = (opp_odd < cand_odd and opp_odd <= 2.65)
            if is_opp_fav:
                if c_line <= 0.5:
                    continue
                if cand_trend == "CURVA_DESCENDENTE" or cand_pts_eff < opp_pts_eff:
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

        # 4.1 Trava de Piso de Odd para Handicap Positivo de Visitante (+0.5 AH e +1.0 AH):
        # Linhas defensivas de visitante pagando cotação deprimida embutem assimetria de risco inaceitável:
        # Se o mandante vencer por 2+ gols, o apostador amarga Red integral sem ter recebido prêmio adequado.
        # Bloqueia terminantemente:
        # - Visitante +0.5 AH com odd < 1.65
        # - Visitante +1.0 AH com odd < 1.50
        if c_is_away:
            if c_line == 0.50 and c_odd < 1.65:
                continue
            if c_line == 1.0 and c_odd < 1.50:
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

        # NOVA REGRA ESTRUTURAL: Mandante com Dominância de Poisson e Cotação 1X2 Deprimida (DESTRAVA -0.5 AH APENAS)
        # Permite avaliar a linha de vitória simples (-0.5 AH) exclusivamente para Mandante quando:
        # 1) É mandante (not c_is_away) e favorito sólido no 1X2 (odd <= 1.50 ou Tier 1 <= 1.55)
        # 2) Poisson projeta superioridade indiscutível: xg_home >= 2.40 e saldo projetado (xg_home - xg_away) >= 1.40 gols
        # 3) Adversário NÃO é Tier 1 de elite e NÃO está em CURVA_ASCENDENTE
        # 4) Mandante NÃO está em CURVA_DESCENDENTE e tem no máximo 1 derrota recente (cand_d <= 1)
        is_home_poisson_dominant_minus_half = (
            not c_is_away and
            (cand_odd <= 1.50 or (is_tier1 and cand_odd <= 1.55)) and
            (xg_h_val >= 2.40 and xg_diff >= 1.40) and
            not is_opp_tier1 and
            opp_trend != "CURVA_ASCENDENTE" and
            cand_trend != "CURVA_DESCENDENTE" and
            cand_d <= 1
        )

        is_eligible_negative = (is_super_fav_crushed or is_dominant_tier1)

        # Identificação de Favorito em Grande Fase (U5J >= 9.0 pts, vantagem >= 3.0 pts e cand_odd < opp_odd):
        opp_t1_cnt = opp_l5.get('tier1_opponents', 0) if isinstance(opp_l5, dict) else 0
        pts_diff_allowed = (cand_pts - opp_pts) >= 4 if opp_t1_cnt < 2 else False
        is_fav_in_form = (
            is_cand_fav and
            (cand_pts >= 12 or cand_pts_eff >= 9.0) and
            ((cand_pts_eff - opp_pts_eff) >= 3.0 or pts_diff_allowed)
        )

        # Filtro 1: Linhas permitidas e faixas de odds seguras
        required_ev = min_ev
        if cand_trend in ("CURVA_DESCENDENTE", "CURVA_ESTAGNADA"):
            required_ev += 2.0

        if c_line in moderate_negative_lines:
            # -0.5 AH pode ser liberado para mandantes com dominância de Poisson OU super-favoritos/assimetria
            # Linhas mais profundas (-0.75 AH e -1.0 AH) permanecem terminantemente restritas a Super-Favoritos ou Tier 1 Dominante
            if c_line == -0.5:
                if not (is_eligible_negative or is_home_poisson_dominant_minus_half):
                    continue
                # Faixa de odd segura para -0.5 AH (permite cotas a partir de 1.35 para mandantes de elite)
                if c_odd < 1.35 or c_odd > 1.95:
                    continue
                required_prob = 58.0 if is_super_fav_crushed else (60.0 if is_home_poisson_dominant_minus_half else 65.0)
                required_ev = 8.0 if is_super_fav_crushed else (5.0 if is_home_poisson_dominant_minus_half else 15.0)
            else:
                # Linhas -0.75 AH e -1.0 AH: destravamento de Poisson PROIBIDO (destravando -0.5 AH APENAS)
                if not is_eligible_negative:
                    continue
                if c_odd < 1.50 or c_odd > 1.95:
                    continue
                required_prob = 58.0 if is_super_fav_crushed else 65.0
                required_ev = 8.0 if is_super_fav_crushed else 15.0
        elif c_line == -0.25:
            # Linha conservadora de -0.25 AH:
            # 1. Somente para Mandante (Home) ou Super-Favorito comprovado
            if c_is_away and not is_super_fav_crushed:
                continue
            # 2. Exclusivamente quando a odd 1X2 da equipe favorita for <= 1.75 (favorito sólido de mercado).
            #    Se a odd 1X2 for > 1.75 (ex: 1.80 a 2.30), o mercado precifica equilíbrio com alto risco de empate;
            #    nesses cenários, é mandatório operar na linha 0.0 AH (DNB) com proteção total de capital.
            if cand_odd > 1.75:
                continue
            # 3. Trava de Adversário Competitivo no U5J: se o adversário tiver bom aproveitamento (>= 10 pts ou >= 3 vitórias),
            #    o confronto é parelho e a proteção do empate via 0.0 AH é mandatória.
            if opp_pts >= 10 or opp_v >= 3:
                continue
            # 4. Exige Favorito em Grande Fase comprovada no U5J (ou Super-Favorito)
            if not (is_fav_in_form or is_eligible_negative):
                continue
            # 5. Teto de odd estrito para -0.25 AH: máximo 1.85 (odds > 1.85 indicam favoritismo frágil da casa e geram reds)
            if c_odd < 1.50 or c_odd > 1.85:
                continue
            # 6. Proibir terminantemente -0.25 AH se a equipe favorita estiver em curva descendente
            if cand_trend == "CURVA_DESCENDENTE":
                continue
            required_prob = 62.0  # Calibrado de 55.0% para 62.0% (filtro de alta convicção pré-PR #103)
            required_ev = 8.0     # Elevado de 5.0% para 8.0% (exige margem real de valor)
        elif c_line in standard_allowed_lines:
            # Super-favoritos com odd esmagada não operam na linha 0.0 AH, exceto na regra mandatória de massacre Tier 1 com U5J próximo
            if is_super_fav_crushed and c_line == 0.0 and not is_tier1_massacre_close_u5j:
                continue
            if is_tier1_massacre_close_u5j and c_line == 0.0:
                if c_odd < 1.05 or c_odd > 2.35:
                    continue
                required_prob = 55.0
                required_ev = -35.0  # Prioridade de proteção no DNB de massacre do Tier 1
            else:
                if c_odd < 1.50 or c_odd > 2.10:
                    continue
                required_prob = min_prob
            # Inversão de Handicap: favorito 1X2 não recebe handicap positivo > 0.0
            if is_cand_fav and c_line > 0.0:
                continue
            # Trava Anti-Inversão de Mercado: Linha positiva alta (>= 0.5 AH) com odd alta (>= 2.05)
            # só é aceitável se a equipe for comprovadamente zebra nas odds 1X2 (cand_odd > opp_odd + 0.20)
            if c_line >= 0.5 and c_odd >= 2.05 and not is_cand_underdog:
                continue
            # Trava de Proteção Tier 1: Clube Tier 1 Elite não recebe vantagem excessiva (>= 1.0 AH) contra não-Tier 1
            if is_tier1 and not is_opp_tier1 and c_line >= 1.0 and cand_odd <= 2.50:
                continue
        else:
            continue

        # Avaliação com a Matriz de Poisson
        res = evaluate_ah_line_poisson(poisson_matrix, c_is_away, c_line, c_odd)
        ev = res['ev_percent']
        prob_eff = res['prob_eff']

        # 4. Ancoragem Bayesiana (Filtro Universal de Sanidade Poisson vs Mercado 1X2):
        # A casa de apostas precifica o 1X2 com altíssima eficiência de mercado.
        # Se a probabilidade pura de vitória estimada por Poisson divergir mais de 18 pontos percentuais
        # da probabilidade implícita justa do mercado 1X2, rejeita a entrada por distorção de Poisson.
        # Em cenários de Distorção de Banca / Momentum Surge comprovada, a tolerância máxima é de 25.0 pp.
        if cand_odd > 0 and opp_odd > 0:
            inv_cand = 1.0 / cand_odd
            inv_opp = 1.0 / opp_odd
            market_cand_win_prob = (inv_cand / (inv_cand + inv_opp)) * 100.0
            p_pure_win = sum(p for (x, y), p in poisson_matrix.items() if (y > x if c_is_away else x > y)) * 100.0
            max_divergence = 25.0 if (cand_is_better_performance or is_away_momentum_surge) else 18.0
            if (p_pure_win - market_cand_win_prob) > max_divergence:
                continue

        # Teto de Sanidade para Probabilidade Efetiva em Linhas Comerciais (Odd >= 1.45 não pode registrar ilusão > 82%)
        if c_odd >= 1.45 and prob_eff > 82.0:
            prob_eff = 82.0
            res['prob_eff'] = 82.0
            ev = round((prob_eff / 100.0 * c_odd - 1.0) * 100.0, 2)
            res['ev_percent'] = ev

        if ev >= required_ev and prob_eff >= required_prob:
            score = (100.0 + prob_eff) if is_tier1_massacre_close_u5j else (ev * (prob_eff / 100.0))
            cand_copy = dict(cand)
            cand_copy['eval'] = res
            cand_copy['score'] = score
            cand_copy['is_tier1_massacre'] = is_tier1_massacre_close_u5j
            cand_copy['is_momentum_surge'] = (cand_is_better_performance and is_cand_underdog) or is_away_momentum_surge
            approved.append(cand_copy)

    if not approved:
        return None, []

    # Prioridade Absoluta: Regra de Massacre Tier 1 com U5J próximo seleciona obrigatoriamente 0.0 AH
    tier1_massacre_picks = [c for c in approved if c.get('is_tier1_massacre')]
    if tier1_massacre_picks:
        return tier1_massacre_picks[0], approved

    # Prioridade Estrutural e Preservação de Capital: Ancoragem em Empate Anula (0.0 AH)
    # Quando ambas as linhas (0.0 AH e -0.25 AH) forem aprovadas pelo Gatekeeper:
    # 1) Se a odd 1X2 da equipe favorita for > 1.70, o risco de empate é estatisticamente significativo (~25-30%).
    #    A linha 0.0 AH (DNB) é compulsoriamente selecionada para garantir 100% de reembolso no empate e evitar meio-reds.
    # 2) Se a odd 1X2 for <= 1.70, a linha -0.25 AH só é preferida se apresentar valor esperado decisivamente superior
    #    (pelo menos +4.0 pontos percentuais de EV acima de 0.0 AH). Caso contrário, 0.0 AH prevalece como porto seguro.
    dnb_cand = next((c for c in approved if c.get('line') == 0.0), None)
    neg25_cand = next((c for c in approved if c.get('line') == -0.25), None)
    if dnb_cand and neg25_cand:
        c_odd_1x2 = raw_a_odd if neg25_cand.get('is_away') else raw_h_odd
        dnb_ev = dnb_cand.get('eval', {}).get('ev_percent', 0.0)
        neg25_ev = neg25_cand.get('eval', {}).get('ev_percent', 0.0)
        if c_odd_1x2 > 1.70 or (neg25_ev < dnb_ev + 4.0):
            approved = [dnb_cand] + [c for c in approved if c is not dnb_cand]
            return approved[0], approved

    # Em situações de Distorção de Banca / Soberania da Performance, prioriza linhas equilibradas (0.0 AH ou -0.25 AH)
    surge_cushion = [c for c in approved if c.get('is_momentum_surge') and c['line'] in (0.0, -0.25)]
    if surge_cushion:
        surge_cushion.sort(key=lambda x: x['score'], reverse=True)
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
    # 1. Prioridade Absoluta: xG Adj contextualmente calibrado no reasoning (com mando, streak e odds integradas)
    xg_adj_h = None
    xg_adj_a = None
    if reasoning:
        m_h = re.search(r'\(Em Casa\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
        m_a = re.search(r'\(Fora\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
        if m_h and m_a:
            try:
                xg_adj_h = float(m_h.group(1))
                xg_adj_a = float(m_a.group(1))
            except Exception:
                pass

    if xg_adj_h is not None and xg_adj_a is not None and xg_adj_h > 0.1 and xg_adj_a > 0.1:
        xg_h = xg_adj_h
        xg_a = xg_adj_a
    else:
        # 2. Fonte Secundária: xG calibrado do banco de dados (fixtures_trends.xg_home / xg_away)
        xg_h = float(fixture_dict.get('xg_home') or 0.0)
        xg_a = float(fixture_dict.get('xg_away') or 0.0)

    # 3. Fallback: Projeção de xG a partir dos 5 jogos consolidados de U5J caso xG esteja zerado/indisponível
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
    h_eff_calib = compute_team_u5j_efficiency(u_json.get('home') if isinstance(u_json, dict) else None)
    a_eff_calib = compute_team_u5j_efficiency(u_json.get('away') if isinstance(u_json, dict) else None)
    if abs(h_eff_calib - a_eff_calib) <= 2.0 and odd_h > 1.0 and odd_a > 1.0 and odd_h < odd_a:
        if xg_a > xg_h:
            xg_a = round(min(xg_a, xg_h * 0.95), 2)
            poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

    # Trava Universal de Coerência de xG Relativo às Odds 1X2 Oficiais (Prevenção de xG Descolado do Banco):
    if odd_h > 1.0 and odd_a > 1.0 and xg_h > 0 and xg_a > 0:
        is_tight_market = abs(odd_h - odd_a) <= 0.35 or (2.20 <= odd_h <= 2.90 and 2.20 <= odd_a <= 2.90)
        odds_ratio = (odd_a / odd_h) if odd_h < odd_a else (odd_h / odd_a)
        max_allowed_ratio = 1.55 if is_tight_market else max(2.20, min(6.0, odds_ratio * 0.90))
        ratio_recalc = False
        if (xg_a / xg_h) > max_allowed_ratio:
            xg_a = round(xg_h * max_allowed_ratio, 2)
            ratio_recalc = True
        elif (xg_h / xg_a) > max_allowed_ratio:
            xg_h = round(xg_a * max_allowed_ratio, 2)
            ratio_recalc = True
        if ratio_recalc:
            poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

    # 3. Obtenção de linhas ativas na Betano (Bookmaker ID 32):
    if betano_lines is None:
        if allow_api_fetch and fixture_id:
            betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
        else:
            betano_lines = []

    # Regra 12 (AGENTS.md): Ausência de linhas reais das casas de apostas (API-Football / The Odds API) -> Abstenção Mandatória
    if not betano_lines:
        # Se a partida já possui sugestão e odds de mercado válidas gravadas no banco de dados (fixtures_trends ou apostas),
        # JAMAIS pode dar NO_BET por falta de odds de mercado em tempo real. Os dados gravados devem ser preservados!
        ex_sug = (fixture_dict.get('ah_suggestion') or '').strip()
        ex_conf = float(fixture_dict.get('ah_confidence') or 0.0)
        ex_reason = fixture_dict.get('ah_reasoning') or ''
        has_valid_saved_sug = ex_sug and not any(k in ex_sug.lower() for k in ['sem entrada', 'abstenção', 'abstencao', 'no_bet', 'bloqueada'])

        existing_bet_row = None
        if cursor and fixture_id:
            try:
                cursor.execute("""
                    SELECT id, palpite, odd, odd_justa, probabilidade_poisson, ev_percentual, status_gatekeeper, resultado_detalhado
                    FROM apostas
                    WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
                      AND status = 'Pendente' AND status_gatekeeper = 'APROVADO'
                    ORDER BY confirmada DESC, id DESC LIMIT 1
                """, (fixture_id,))
                existing_bet_row = cursor.fetchone()
            except Exception as e_bet_fetch:
                print(f"⚠️ [Asian Handicap Engine / DB SELECT] Falha ao consultar aposta prévia na tabela 'apostas' para fixture #{fixture_id}: {e_bet_fetch}")

        if has_valid_saved_sug or (existing_bet_row and existing_bet_row.get('palpite')):
            sug_preserved = ex_sug if has_valid_saved_sug else existing_bet_row['palpite']
            conf_preserved = ex_conf if (has_valid_saved_sug and ex_conf > 50.0) else float(existing_bet_row.get('probabilidade_poisson') or 70.0)
            reason_preserved = ex_reason if (has_valid_saved_sug and ex_reason) else (existing_bet_row.get('resultado_detalhado') or reasoning)

            # Reconstrução do best_cand preservado a partir dos dados já gravados no banco
            m_l = re.search(r'([+-]?\d+(?:\.\d+)?)', sug_preserved)
            line_val = float(m_l.group(1)) if m_l else 0.0
            is_away_cand = away_team.lower() in sug_preserved.lower()
            target_team_cand = away_team if is_away_cand else home_team
            odd_val = float(existing_bet_row.get('odd') or 0.0) if existing_bet_row else 0.0
            if odd_val <= 1.0:
                m_odd = re.search(r'Odd Betano\s*(\d+(?:\.\d+)?)', reason_preserved)
                odd_val = float(m_odd.group(1)) if m_odd else 1.70

            preserved_cand = {
                'team': 'Away' if is_away_cand else 'Home',
                'target_team': target_team_cand,
                'is_away': is_away_cand,
                'line': line_val,
                'palpite_str': sug_preserved,
                'odd': odd_val,
                'source': 'BANCO_PRESERVADO',
                'eval': {
                    'odd_justa': float(existing_bet_row.get('odd_justa') or 1.40) if existing_bet_row else 1.40,
                    'prob_eff': conf_preserved,
                    'ev_percent': float(existing_bet_row.get('ev_percentual') or 15.0) if existing_bet_row else 15.0
                }
            }
            app_cat = determine_gatekeeper_category('APROVADO', sug_preserved, reason_preserved, preserved_cand)
            preserved_cand['gatekeeper_category'] = app_cat
            return 'APROVADO', sug_preserved, conf_preserved, reason_preserved, preserved_cand, [preserved_cand]

        # Partida sem nenhuma linha da API e sem nenhum dado gravado no banco: Abstenção Mandatória
        has_real_1x2_odds = bool(odd_home and odd_away and float(odd_home) > 1.0 and float(odd_away) > 1.0)
        if has_real_1x2_odds:
            reason_no_odds = (
                f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} -> "
                f"Cotações de mercado 1X2 (Casa: {float(odd_home):.2f}, Fora: {float(odd_away):.2f}) analisadas. "
                f"Nenhuma linha de Handicap Asiático atingiu os limiares de rentabilidade (+EV >= 5.0%, Prob. Efetiva >= 58.0%). "
                f"Abstenção mandatória pelo Gatekeeper para proteção de banca."
            )
        else:
            reason_no_odds = (
                f"🛡️ [Gatekeeper AH NO_BET / Ausência de Linhas Reais] Cotações oficiais de Handicap Asiático indisponíveis "
                f"na API-Football e na The Odds API para {home_team} vs {away_team}. "
                f"Abstenção mandatória (Regra 12: Proibição de dados sintéticos)."
            )
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

    h_rk_val = 0
    a_rk_val = 0
    h_ppg_val = 0.0
    a_ppg_val = 0.0
    standings_mot_val = 0.0

    try:
        if fixture_dict.get('home_rank') is not None and str(fixture_dict.get('home_rank')).strip():
            h_rk_val = int(fixture_dict.get('home_rank'))
    except Exception:
        h_rk_val = 0

    try:
        if fixture_dict.get('away_rank') is not None and str(fixture_dict.get('away_rank')).strip():
            a_rk_val = int(fixture_dict.get('away_rank'))
    except Exception:
        a_rk_val = 0

    try:
        if fixture_dict.get('home_ppg') is not None and str(fixture_dict.get('home_ppg')).strip():
            h_ppg_val = float(fixture_dict.get('home_ppg'))
    except Exception:
        h_ppg_val = 0.0

    try:
        if fixture_dict.get('away_ppg') is not None and str(fixture_dict.get('away_ppg')).strip():
            a_ppg_val = float(fixture_dict.get('away_ppg'))
    except Exception:
        a_ppg_val = 0.0

    try:
        if fixture_dict.get('standings_motivation_score') is not None and str(fixture_dict.get('standings_motivation_score')).strip():
            standings_mot_val = float(fixture_dict.get('standings_motivation_score'))
    except Exception:
        standings_mot_val = 0.0

    best_cand, approved = evaluate_and_select_best_ah_candidate(
        poisson_matrix, betano_lines, home_team, away_team, odd_h, odd_a,
        min_ev=5.0, min_prob=58.0,
        home_team_id=h_tid, away_team_id=a_tid,
        home_last5=h_l5, away_last5=a_l5,
        xg_home=xg_h, xg_away=xg_a,
        home_rank=h_rk_val,
        away_rank=a_rk_val,
        home_ppg=h_ppg_val,
        away_ppg=a_ppg_val,
        home_zone=fixture_dict.get('home_zone'),
        away_zone=fixture_dict.get('away_zone'),
        standings_motivation_score=standings_mot_val
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

        h_zone_str = str(fixture_dict.get('home_zone') or '').lower()
        is_h_rel = ('relegat' in h_zone_str or 'play out' in h_zone_str or 'play-out' in h_zone_str or 'rebaixamento' in h_zone_str)
        is_h_under_threat = (
            is_h_rel or
            (h_rk_val >= 12 and h_ppg_val > 0.0 and h_ppg_val <= 1.25) or
            (h_rk_val >= 12 and standings_mot_val >= 3.0) or
            (h_rk_val >= 12 and a_rk_val > 0 and (h_rk_val - a_rk_val >= 6 or a_rk_val <= 6))
        )

        is_crisis_clash = (
            (fav_eff <= 3.0 and dog_eff <= 3.0) or
            (h_l5 and a_l5 and isinstance(h_l5, dict) and isinstance(a_l5, dict) and h_l5.get('v', 0) <= 1 and a_l5.get('v', 0) <= 1 and fav_pts <= 4 and dog_pts <= 4)
        )

        has_away_cand = any(c.get('is_away') for c in betano_lines) if betano_lines else False
        away_has_better_metrics = (dog_eff > fav_eff) or (a_rk_val > 0 and h_rk_val > 0 and h_rk_val > a_rk_val)

        if not fav_is_home and not is_t1 and (odd_a >= 2.10) and is_h_under_threat:
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Sobrevivência do Mandante] Partida {home_team} vs {away_team} -> "
                f"Mandante {home_team} ({h_rk_val}º colocado) sob pressão crítica na tabela atuando em seus domínios "
                f"contra visitante {away_team} ({a_rk_val}º colocado) em confronto de mercado parelho (Odd Fora: {odd_a:.2f}). "
                f"O fator campo e a urgência de sobrevivência do mandante anulam a vantagem teórica. Abstenção mandatória."
            )
        elif fav_is_home and not is_t1 and (odd_h > 0 and odd_a > 0 and (odd_a - odd_h) >= 0.15) and (has_away_cand or away_has_better_metrics):
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Mando de Campo Soberano] Partida {home_team} vs {away_team} -> "
                f"O mercado precifica o mandante ({home_team} @ {odd_h:.2f}) como favorito sobre o visitante "
                f"({away_team} @ {odd_a:.2f}), neutralizando a superioridade teórica recente. Abstenção mandatória por divergência de mercado."
            )
        elif is_crisis_clash:
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Duelo de Crises] Partida {home_team} vs {away_team} -> "
                f"Ambas as equipes em momento técnico desfavorável no U5J ({home_team} {fav_eff if fav_is_home else dog_eff:.1f} pts vs {away_team} {dog_eff if fav_is_home else fav_eff:.1f} pts). "
                f"Confronto de alta imprevisibilidade e desorganização tática. Abstenção mandatória."
            )
        elif fav_in_form:
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
                if not fav_is_home:
                    context_extra = f" Favorito visitante {fav_team}{t1_str}: linhas esticadas (<= -0.50 AH) vetadas por proteção de mando de campo (teto para visitante é -0.25 AH com tolerância a empate) e linha DNB sem cotação mínima."
                else:
                    context_extra = f" Favorito mandante {fav_team}{t1_str} com odd nominal esmagada: linha DNB (0.0 AH) sem odd mínima (+EV) e linhas positivas bloqueadas por coerência de mercado."
            reason = (
                f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} ->{context_extra} "
                f"Nenhuma linha da Betano atingiu os limiares de rentabilidade (+EV >= 5.0%, Prob. Efetiva >= 58.0% ajustada por momento e teto de odd 1.85 no favorito). "
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
        existing_reasoning=reasoning,
        odd_home=odd_h,
        odd_away=odd_a
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

    cand_l5_data = a_l5 if cand_team_name == away_team else h_l5
    cand_pts = cand_l5_data.get('pts', 0) if isinstance(cand_l5_data, dict) else 0
    odd_cand = fixture_dict.get('odd_away') if cand_team_name == away_team else fixture_dict.get('odd_home')
    try:
        odd_cand = float(odd_cand) if odd_cand is not None else 99.0
    except (ValueError, TypeError):
        odd_cand = 99.0

    is_crisis_opp = (opp_pts <= 5 or opp_v == 0)
    is_destaque = 0
    if is_cand_tier1:
        if not is_opp_tier1 and is_crisis_opp:
            is_destaque = 1
        elif is_opp_tier1 and is_crisis_opp and (cand_pts >= 8 or odd_cand <= 1.60 or cand_pts >= opp_pts + 4):
            is_destaque = 1

    best_cand['destaque'] = is_destaque
    app_cat = determine_gatekeeper_category('APROVADO', selected_palpite, raw_calc, best_cand)
    best_cand['gatekeeper_category'] = app_cat

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

    tier1_cnt = 0
    for m in matches:
        opp_name = m.get("opponent", "")
        opp_id = m.get("opponent_id")
        is_t1 = is_tier_1_elite_club(team_id=opp_id, team_name=opp_name)
        m["is_tier_1"] = is_t1
        if is_t1:
            tier1_cnt += 1

    res_dict = {
        "v": num_v,
        "e": num_e,
        "d": num_d,
        "pts": pts,
        "tier1_opponents": tier1_cnt,
        "matches": matches
    }
    pts_eff = compute_team_u5j_efficiency(res_dict)
    res_dict["pts_efficiency"] = pts_eff
    tr_lbl = res_dict.get("trend_label") or ""
    tr_suffix = f" | {tr_lbl}" if tr_lbl else ""
    res_dict["text"] = f"{num_v}V-{num_e}E-{num_d}D ({pts} pts | {pts_eff:.1f} pts ef.{tr_suffix})" if matches else "Não localizado (0 pts)"
    return res_dict


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
            f"Nenhuma linha da Betano atingiu os limiares de rentabilidade (+EV >= 5.0%, Prob. Efetiva >= 58.0% ajustada por momento e teto de odd 1.85 no favorito). "
            f"Eficiência U5J: {home_team} ({h_eff:.1f} pts) vs {away_team} ({a_eff:.1f} pts). Abstenção mandatória."
        )

    # 2. Caso APROVADO (Palpite com Linha)
    prob_eff = 65.0
    m_prob = re.search(r'Prob\.\s*Efetiva:\s*([\d\.]+)%', main_calc)
    if m_prob:
        prob_eff = float(m_prob.group(1))
    else:
        m_prob2 = re.search(r'\((\d+(?:\.\d+)?)%\)', str(suggestion) + ' ' + str(main_calc))
        if m_prob2:
            prob_eff = float(m_prob2.group(1))
        elif 'prob_eff' in locals() and locals()['prob_eff']:
            prob_eff = float(locals()['prob_eff'])

    # Eficiência U5J e Pontos Brutos
    h_eff = (h_u5j or {}).get("pts_efficiency")
    if h_eff is None:
        h_eff = (h_u5j or {}).get("pts", 0.0)
    a_eff = (a_u5j or {}).get("pts_efficiency")
    if a_eff is None:
        a_eff = (a_u5j or {}).get("pts", 0.0)

    h_pts = int((h_u5j or {}).get("pts") or 0)
    a_pts = int((a_u5j or {}).get("pts") or 0)

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
    backed_pts = h_pts if is_home_bet else a_pts
    opp_pts = a_pts if is_home_bet else h_pts

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
    if "+1.5" in sug_clean or "+1.50" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, cobrindo vitória, empate e qualquer derrota por até 1 gol de diferença com 100% de ganho"
    elif "+1.25" in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho no empate ou vitória e meio-green na derrota mínima por 1 gol"
    elif "+1.0" in sug_clean or "+1.00" in sug_clean or "+1 ah" in sug_clean.lower() or "+1 " in sug_clean:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, cobrindo vitória e empate com 100% de ganho e reembolso total em caso de derrota por 1 gol"
    elif "+0.75" in sug_clean:
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
    elif "-1.0" in sug_clean or "-1.00" in sug_clean or "-1 ah" in sug_clean.lower() or "-1 " in sug_clean:
        cov_text = f"oferece {prob_eff:.0f}% de probabilidade efetiva calculada, garantindo 100% de lucro na vitória por 2+ gols e reembolso integral de 100% da stake na vitória por 1 gol de diferença"
    elif "-1.25" in sug_clean:
        cov_text = f"oferece {prob_eff:.0f}% de probabilidade efetiva calculada, garantindo 100% de retorno na vitória por 2+ gols e perda atenuada de apenas 50% na vitória por 1 gol"
    elif "-1.5" in sug_clean or "-1.50" in sug_clean:
        cov_text = f"oferece {prob_eff:.0f}% de probabilidade efetiva calculada, garantindo 100% de retorno na vitória direta por 2 ou mais gols de diferença"
    else:
        cov_text = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"

    diff_eff = abs(h_eff - a_eff)

    # Cenário 1: Casa dá favoritismo a um time, mas pontuações U5J são muito aproximadas (Exemplo Palestino)
    if fav_team and fav_team != backed_team and diff_eff <= 2.5:
        role_label = "o azarão " if und_team == backed_team else ""
        narrative = (
            f"As odds 1x2 da casa de aposta dão favoritismo ao {fav_team}, porém com base na análise "
            f"dos resultados dos últimos 5 jogos os 2 times têm pontuação muito aproximada "
            f"({home_team} {h_pts} pts [{h_eff:.1f} pts ef.] vs {away_team} {a_pts} pts [{a_eff:.1f} pts ef.]). "
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
        elif "-1.0" in sug_clean or "-1.00" in sug_clean or "-1 ah" in sug_clean.lower() or "-1 " in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória por 2+ gols e reembolso integral de 100% do valor apostado na vitória simples por 1 gol exato"
        elif "-1.25" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória por 2+ gols e proteção com perda atenuada de apenas 50% na vitória por 1 gol"
        elif "-1.5" in sug_clean or "-1.50" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória direta por 2+ gols"
        elif "+1.0" in sug_clean or "+1.00" in sug_clean or "+1 ah" in sug_clean.lower() or "+1 " in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais, garantindo 100% de ganho na vitória ou empate e reembolso integral na derrota por 1 gol"
        elif "+0.25" in sug_clean or "+0.5" in sug_clean or "+0.75" in sug_clean or "+1.25" in sug_clean or "+1.5" in sug_clean:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"
        else:
            cov_desc = f"possui uma cobertura de {prob_eff:.0f}% sobre as demais opções de mercado"

        narrative = (
            f"As odds 1x2 da casa de aposta apontam corretamente o {backed_team} como favorito, "
            f"respaldado por sua notável superioridade de desempenho nos últimos 5 jogos "
            f"({backed_pts} pts [{backed_eff:.1f} pts ef.] vs {opp_pts} pts [{opp_eff:.1f} pts ef.] do {opp_team}). "
            f"A seleção {sug_clean} {cov_desc}."
        )
    # Cenário 3: Casa aponta o adversário, mas time apostado tem momento U5J nitidamente superior (Valor no azarão)
    elif fav_team != backed_team and backed_eff > opp_eff:
        narrative = (
            f"Embora as odds 1x2 da casa de aposta apontem o {fav_team} como favorito, a análise "
            f"dos últimos 5 jogos revela momento superior do {backed_team} "
            f"({backed_pts} pts [{backed_eff:.1f} pts ef.] vs {opp_pts} pts [{opp_eff:.1f} pts ef.]). "
            f"Para este cenário de distorção, a linha de proteção {sug_clean} {cov_text}."
        )
    # Cenário 4: Confronto parelho ou time apostado com ligeira vantagem
    else:
        narrative = (
            f"Confronto com forças equilibradas nas cotações 1x2 e na pontuação dos últimos 5 jogos "
            f"({home_team} {h_pts} pts [{h_eff:.1f} pts ef.] vs {away_team} {a_pts} pts [{a_eff:.1f} pts ef.]). Diante do equilíbrio técnico, "
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
    existing_reasoning: str = None,
    odd_home: float = None,
    odd_away: float = None
) -> str:
    """
    Constrói e garante a integridade do formato composto de fixtures_trends.ah_reasoning:
    f"{main_calc} || EXPLICACAO: {nl_exp} || MOTIVACAO: {nl_mot} || MEMÓRIA DE CÁLCULO || {calc_details} || U5J_DATA: {u5j_json_str}"

    Preserva rigorosamente a lista de jogos consolidados (U5J) e explicações em linguagem natural,
    reconstruindo o payload JSON via MySQL caso tenha sido corrompido ou sobrescrito.
    """
    # Higienização anti-duplicação: limpa qualquer bloco composto prévio embutido em main_calc
    if main_calc and " || EXPLICACAO:" in main_calc:
        main_calc = main_calc.split(" || EXPLICACAO:")[0].strip()

    u5j_json_str = None
    existing_motivation = None
    h_u5j = None
    a_u5j = None

    # Prioridade Absoluta (Regra 1): Reutiliza o payload U5J_DATA pré-existente no reasoning
    if existing_reasoning and "|| U5J_DATA:" in existing_reasoning:
        try:
            u_part = existing_reasoning.split("|| U5J_DATA:")[1].split("||")[0].strip()
            parsed_u = json.loads(u_part)
            if (parsed_u.get("home", {}).get("matches") or parsed_u.get("away", {}).get("matches")):
                u5j_json_str = u_part
                h_u5j = parsed_u.get("home", {})
                a_u5j = parsed_u.get("away", {})
                
                # Garante coerência de pts_efficiency com a fórmula oficial compute_team_u5j_efficiency
                rebuilt_json = False
                if h_u5j and isinstance(h_u5j, dict) and h_u5j.get("matches"):
                    h_calc = compute_team_u5j_efficiency(h_u5j)
                    if h_u5j.get("pts_efficiency") != h_calc:
                        h_u5j["pts_efficiency"] = h_calc
                        h_u5j["text"] = f"{h_u5j.get('v',0)}V-{h_u5j.get('e',0)}E-{h_u5j.get('d',0)}D ({h_u5j.get('pts',0)} pts | {h_calc:.1f} pts ef.)"
                        rebuilt_json = True
                if a_u5j and isinstance(a_u5j, dict) and a_u5j.get("matches"):
                    a_calc = compute_team_u5j_efficiency(a_u5j)
                    if a_u5j.get("pts_efficiency") != a_calc:
                        a_u5j["pts_efficiency"] = a_calc
                        a_u5j["text"] = f"{a_u5j.get('v',0)}V-{a_u5j.get('e',0)}E-{a_u5j.get('d',0)}D ({a_u5j.get('pts',0)} pts | {a_calc:.1f} pts ef.)"
                        rebuilt_json = True
                if rebuilt_json:
                    u5j_json_str = json.dumps({"home": h_u5j, "away": a_u5j}, ensure_ascii=False)
        except Exception:
            u5j_json_str = None

    # Fallback no MySQL se U5J_DATA não constar em existing_reasoning
    if not u5j_json_str and cursor:
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

    generic_phrases = [
        "alinhamento estatístico da modelagem poisson",
        "alinhamento estatistico da modelagem poisson",
        "alinhamento de eficiência ponderada (sos)"
    ]

    if existing_reasoning and "|| MOTIVACAO:" in existing_reasoning:
        try:
            cand_mot = existing_reasoning.split("|| MOTIVACAO:")[1].split("||")[0].strip()
            is_sub_abstencao = any(w in str(suggestion).lower() for w in ['abstenção', 'abstencao', 'sem entrada', 'bloquead', 'no_bet'])
            is_mot_abstencao = any(w in cand_mot.lower() for w in ['abstenção', 'bloqueada', 'incerteza', 'proteção de banca: entrada de handicap bloqueada', 'sem entrada'])
            
            if is_sub_abstencao != is_mot_abstencao:
                existing_motivation = None
            elif is_sub_abstencao:
                existing_motivation = cand_mot
            else:
                # Para palpites aprovados: só preserva a motivação se ela for RIGOROSAMENTE IDÊNTICA em linha, time e pontos
                def _get_ah_line_key(t):
                    t_low = str(t).lower()
                    for l_str in ['-1.5', '+1.5', '-1.25', '+1.25', '-1.0', '-1', '+1.0', '+1', '-0.75', '+0.75', '-0.5', '+0.5', '-0.25', '+0.25', '0.0', 'dnb']:
                        if l_str in t_low:
                            return l_str
                    return None

                sug_l = _get_ah_line_key(suggestion)
                mot_l = _get_ah_line_key(cand_mot)

                # Verifica se o time apostado confere
                team_match = True
                if home_team and away_team:
                    sug_home = home_team.lower() in str(suggestion).lower()
                    sug_away = away_team.lower() in str(suggestion).lower()
                    mot_home = home_team.lower() in cand_mot.lower()
                    mot_away = away_team.lower() in cand_mot.lower()
                    if (sug_home and mot_away and not mot_home) or (sug_away and mot_home and not mot_away):
                        team_match = False

                # Verifica se os pontos U5J citados no texto diferem dos pontos atuais
                pts_match = True
                h_pts_now = int((h_u5j or {}).get('pts') or 0)
                a_pts_now = int((a_u5j or {}).get('pts') or 0)
                h_eff_now = float((h_u5j or {}).get('pts_efficiency') or (h_u5j or {}).get('pts') or 0.0)
                a_eff_now = float((a_u5j or {}).get('pts_efficiency') or (a_u5j or {}).get('pts') or 0.0)

                if sug_l and mot_l and sug_l != mot_l:
                    existing_motivation = None
                elif not team_match:
                    existing_motivation = None
                elif any(gp in cand_mot.lower() for gp in generic_phrases):
                    existing_motivation = None
                else:
                    m_pts = re.search(r'\((\d+(?:\.\d+)?)\s*pts.*?vs\s*(\d+(?:\.\d+)?)\s*pts', cand_mot)
                    if m_pts:
                        p1 = float(m_pts.group(1))
                        p2 = float(m_pts.group(2))
                        is_home_sug = home_team.lower() in str(suggestion).lower()
                        b_pts = h_pts_now if is_home_sug else a_pts_now
                        o_pts = a_pts_now if is_home_sug else h_pts_now
                        b_eff = h_eff_now if is_home_sug else a_eff_now
                        o_eff = a_eff_now if is_home_sug else h_eff_now
                        if (abs(p1 - b_pts) > 0.5 and abs(p1 - b_eff) > 0.5) or (abs(p2 - o_pts) > 0.5 and abs(p2 - o_eff) > 0.5):
                            pts_match = False

                    if not pts_match:
                        existing_motivation = None
                    else:
                        existing_motivation = cand_mot
        except Exception:
            existing_motivation = None

    if existing_motivation:
        if any(gp in existing_motivation.lower() for gp in generic_phrases):
            existing_motivation = None
        elif "confronto com forças equilibradas" in existing_motivation.lower():
            h_eff_val = (h_u5j or {}).get("pts_efficiency") or (h_u5j or {}).get("pts", 0.0)
            a_eff_val = (a_u5j or {}).get("pts_efficiency") or (a_u5j or {}).get("pts", 0.0)
            if abs(h_eff_val - a_eff_val) >= 2.5:
                existing_motivation = None

    # Resolução dinâmica e robusta de cotações 1X2 para validação e geração da narrativa
    if (odd_home is None or odd_away is None) and fixture_id:
        b1x2 = _betano_ah_1x2_cache.get(fixture_id) if '_betano_ah_1x2_cache' in globals() else None
        if b1x2 and b1x2.get('casa') and b1x2.get('visitante'):
            try:
                odd_home = float(b1x2['casa'])
                odd_away = float(b1x2['visitante'])
            except Exception:
                pass
        elif cursor:
            try:
                cursor.execute("SELECT odd_home, odd_away FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
                r_odds = cursor.fetchone()
                if r_odds:
                    odd_home = r_odds.get("odd_home")
                    odd_away = r_odds.get("odd_away")
            except Exception:
                pass

    # Validação de coerência das odds 1X2 com a narrativa existente
    if existing_motivation and odd_home and odd_away:
        try:
            oh_val = float(odd_home)
            oa_val = float(odd_away)
            if oh_val > 1.0 and oa_val > 1.0:
                is_home_sug = home_team.lower() in str(suggestion).lower()
                backed_team_name = home_team if is_home_sug else away_team
                opp_team_name = away_team if is_home_sug else home_team
                backed_odd_val = oh_val if is_home_sug else oa_val
                opp_odd_val = oa_val if is_home_sug else oh_val

                # Se a narrativa gravada afirma que a casa aponta o time apostado como favorito, mas as odds 1X2 mostram que o adversário tem odd menor
                if f"apontam corretamente o {backed_team_name} como favorito" in existing_motivation and opp_odd_val < backed_odd_val:
                    existing_motivation = None
                # Se a narrativa gravada afirma que a casa aponta o adversário como favorito, mas o time apostado tem odd menor
                elif f"apontem o {opp_team_name} como favorito" in existing_motivation and backed_odd_val < opp_odd_val:
                    existing_motivation = None
        except Exception:
            pass

    if not u5j_json_str:
        u5j_json_str = json.dumps({
            "home": {"v": 0, "e": 0, "d": 0, "pts": 0, "text": "Aguardando", "matches": []},
            "away": {"v": 0, "e": 0, "d": 0, "pts": 0, "text": "Aguardando", "matches": []}
        }, ensure_ascii=False)

    nl_exp = build_natural_language_explanation(suggestion, home_team, away_team)
    if not existing_motivation:
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

    # Obtém metadados para compor reasoning unificado
    cursor.execute("SELECT ah_reasoning, home_team_id, away_team_id, gatekeeper_category FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
    cur_f = cursor.fetchone()
    existing_r = cur_f.get("ah_reasoning") if cur_f else None
    h_tid = cur_f.get("home_team_id") if cur_f else None
    a_tid = cur_f.get("away_team_id") if cur_f else None
    app_cat = (best_cand or {}).get("gatekeeper_category") or (cur_f.get("gatekeeper_category") if cur_f else None) or determine_gatekeeper_category('APROVADO', selected_palpite, detalhe_calculo, best_cand)

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

    created_count = 0
    updated_count = 0
    skipped_count = 0

    for uid in user_ids:
        cursor.execute("""
            SELECT a.id, a.palpite, a.confirmada, a.status,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas a
            WHERE a.fixture_id = %s AND a.usuario_id = %s AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
            ORDER BY a.id DESC LIMIT 1
        """, (fixture_id, uid))
        ja_existe = cursor.fetchone()

        if ja_existe:
            tem_debito = (int(ja_existe.get('tem_debito') or 0) > 0)
            is_conf = (int(ja_existe.get('confirmada') or 0) == 1) or tem_debito
            is_settled = ja_existe.get('status') in ('Cashout', 'Ganha', 'Perdida', 'Anulada', 'Meio Ganha', 'Meio Perdida')
            if is_conf or is_settled:
                has_confirmed_bet = True
                status_motivo = "liquidada/salvaguarda" if is_settled else "confirmação/débito financeiro"
                print(f"🔒 [Aposta Mantida User #{uid}] ID #{ja_existe['id']} com {status_motivo} mantida intacta.")
                skipped_count += 1
                continue

            # Atualizar aposta pendente ou reativar aposta cancelada não confirmada com compound_reasoning uniforme
            cursor.execute("""
                UPDATE apostas SET
                    palpite = %s,
                    odd = %s,
                    odd_justa = %s,
                    probabilidade_poisson = %s,
                    ev_percentual = %s,
                    status_gatekeeper = 'APROVADO',
                    gatekeeper_category = %s,
                    ganhos_potenciais = %s,
                    resultado_detalhado = %s,
                    destaque = %s,
                    status = 'Pendente',
                    updated_at = NOW()
                WHERE id = %s
            """, (selected_palpite, odd_val, odd_justa, prob_poisson, ev_perc, app_cat, ganhos_potenciais, compound_reasoning, destaque_val, ja_existe['id']))
            print(f"🔄 [Aposta AH Atualizada/Reativada User #{uid}] ID #{ja_existe['id']} | Palpite: '{selected_palpite}' @ {odd_val:.2f} | Categoria: '{app_cat}'")
            updated_count += 1
        else:
            # Inserir nova aposta
            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    odd_justa, probabilidade_poisson, ev_percentual,
                    valor_aposta, ganhos_potenciais, status_gatekeeper, gatekeeper_category, status, confirmada, destaque, data_hora_jogo, resultado_detalhado, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Handicap Asiático', %s, %s,
                    %s, %s, %s,
                    %s, %s, 'APROVADO', %s, 'Pendente', %s, %s, %s, %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, selected_palpite, odd_val,
                odd_justa, prob_poisson, ev_perc,
                valor_aposta, ganhos_potenciais, app_cat, confirmada_val, destaque_val, fixture_date,
                compound_reasoning
            ))
            aposta_id = cursor.lastrowid
            created_count += 1
            print(f"🟢 [Aposta AH Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{selected_palpite}' @ {odd_val:.2f} | Categoria: '{app_cat}'")

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

                tit = f"Nova Aposta Gerada: {selected_palpite}"
                msg = f"A IA gerou a entrada {selected_palpite} @ {odd_val:.2f} para {home_team} vs {away_team} (Débito: R$ {valor_aposta:.2f})."
                lnk = f"/apostas?fixture_id={fixture_id}"
                registrar_notificacao_usuario(cursor, uid, aposta_id, fixture_id, 'info', tit, msg, lnk)

    # Sincroniza fixtures_trends com o palpite aprovado (card sempre alinhado com a aposta aprovada)
    cursor.execute("SELECT ah_reasoning, home_team_id, away_team_id, gatekeeper_category FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
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

    app_cat_f = (cur_f.get("gatekeeper_category") if cur_f else None) or app_cat

    b1x2 = _betano_ah_1x2_cache.get(fixture_id)
    if b1x2 and b1x2.get('casa') and b1x2.get('empate') and b1x2.get('visitante'):
        bm_label = b1x2.get('bookmaker', 'Betano').capitalize()
        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                gatekeeper_category = %s,
                odd_home = %s,
                casa_odd_home = %s,
                odd_draw = %s,
                casa_odd_draw = %s,
                odd_away = %s,
                casa_odd_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (
            selected_palpite, prob_poisson, compound_reasoning, app_cat_f,
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
                gatekeeper_category = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (selected_palpite, prob_poisson, compound_reasoning, app_cat_f, fixture_id))
        print(f"🔗 [Sincronismo Card AH] fixtures_trends #{fixture_id} sincronizado com '{selected_palpite}' e Categoria '{app_cat_f}'.")

    return created_count, updated_count, skipped_count


def registrar_notificacao_usuario(cursor, usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link):
    """
    Insere notificação na tabela notificacoes_usuario para exibição em tempo real (Sino + Toast Pop-up).
    """
    try:
        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, criado_em
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, 0, NOW())
        """, (usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link))
        print(f"🔔 [Notificação Registrada User #{usuario_id}] {titulo}")
    except Exception as e:
        print(f"⚠️ [Notificação] Falha ao registrar notificação para user #{usuario_id}: {e}")

def cancelar_e_estornar_aposta_handicap(cursor, fixture_id, motivo="Abstenção da IA / Gestão de Risco"):
    """
    Busca apostas pendentes no mercado de Handicap Asiático para o fixture_id.
    Altera o status para 'Cancelada' e, se a aposta tiver débito em conta corrente (DEBITO_APOSTA),
    efetua o estorno financeiro (ESTORNO_APOSTA) atualizando o saldo do usuário.
    Gera notificação em tempo real (notificacoes_usuario) com link direto para ação de Cash Out na Betano.
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
    """, (fixture_id,))
    apostas_pendentes = cursor.fetchall()
    if not apostas_pendentes:
        return []

    # Se a abstenção for decorrente de indisponibilidade de odds da API, cota esgotada ou ausência de linhas em tempo real,
    # NUNCA cancela apostas pendentes já criadas anteriormente com +EV
    is_api_odds_missing = any(k.lower() in (motivo or '').lower() for k in [
        'sem odd betano', 'indisponível ou fechado na betano', 'limite de requisições', 
        'circuit-breaker', 'ausência de linhas reais', 'cotações oficiais de handicap asiático indisponíveis',
        'linhas reais', 'ausência de cotações'
    ]) and 'odds 1x2 ausentes' not in (motivo or '').lower()
    if is_api_odds_missing:
        print(f"🔒 [Apostas Preservadas / Indisponibilidade de Odds API] Partida #{fixture_id} possui aposta(s) pendente(s). Cancelamento abortado pois a ausência de odds da Betano via API é temporária/cota.")
        return []

    canceladas_detalhes = []
    for aposta in apostas_pendentes:
        aposta_id = aposta['id']
        usuario_id = aposta['usuario_id']
        valor = float(aposta['valor_aposta'] or 0.0)

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

        cat_desc = determine_gatekeeper_category('NO_BET', 'Sem Entrada (Abstenção)', detalhe_calculo or human_desc)
        didactic = GATEKEEPER_DIDACTIC_MAP.get(cat_desc, '')
        full_human_desc = f"💡 Síntese da IA: {didactic}\n\n{human_desc}" if didactic else human_desc

        cursor.execute("""
            UPDATE apostas 
            SET status = 'Cancelada', 
                status_gatekeeper = 'NO_BET',
                gatekeeper_category = %s,
                palpite = 'Sem Entrada (Abstenção)',
                resultado_detalhado = %s, 
                updated_at = NOW() 
            WHERE id = %s
        """, (cat_desc, full_human_desc, aposta_id))

        cursor.execute("""
            UPDATE fixtures_trends
            SET gatekeeper_category = %s
            WHERE fixture_id = %s
        """, (cat_desc, fixture_id))

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

        # Disparo da Notificação em Tempo Real (Sininho & Toast Pop-up) para todas as apostas canceladas
        is_aposta_confirmada = (aposta.get('confirmada') == 1) or (aposta.get('tem_debito', 0) > 0)
        if is_aposta_confirmada:
            titulo_notif = f"⚠️ Aposta Cancelada (Estornada): {aposta['time_casa']} vs {aposta['time_fora']}"
            msg_notif = (
                f"A IA ativou Abstenção no Handicap Asiático para {aposta.get('palpite', 'Handicap')} "
                f"({aposta['time_casa']} vs {aposta['time_fora']}). Saldo de R$ {valor:.2f} estornado em conta. "
                f"Caso já tenha realizado o bilhete na Betano, efetue o Cash Out imediatamente para proteger o capital."
            )
        else:
            titulo_notif = f"⚠️ Sugestão Cancelada (Abstenção): {aposta['time_casa']} vs {aposta['time_fora']}"
            msg_notif = (
                f"A IA ativou Abstenção no Handicap Asiático para {aposta.get('palpite', 'Handicap')} "
                f"({aposta['time_casa']} vs {aposta['time_fora']}) por ausência de margem de segurança matemática (Gatekeeper NO_BET). "
                f"Não realizar entrada nesta partida."
            )

        dj = aposta.get('data_hora_jogo')
        dj_str = ""
        if isinstance(dj, datetime):
            try:
                from zoneinfo import ZoneInfo
                dj_brt = dj.astimezone(ZoneInfo("America/Sao_Paulo")) if dj.tzinfo else (dj - timedelta(hours=3))
                dj_str = dj_brt.strftime("%Y-%m-%d")
            except Exception:
                dj_str = (dj - timedelta(hours=3)).strftime("%Y-%m-%d")
        elif isinstance(dj, str) and len(dj) >= 10:
            dj_str = dj[:10]

        data_query = f"&data_jogo={dj_str}" if dj_str else ""
        link_notif = f"/apostas?filtro_status=Cancelada&destaque_id={aposta_id}{data_query}#aposta-card-{aposta_id}"
        registrar_notificacao_usuario(
            cursor=cursor,
            usuario_id=usuario_id,
            aposta_id=aposta_id,
            fixture_id=fixture_id,
            tipo="APOSTA_CANCELADA",
            titulo=titulo_notif,
            mensagem=msg_notif,
            link=link_notif
        )

        detail = dict(aposta)
        detail['motivo'] = motivo
        detail['estornado'] = estornado
        detail['saldo_posterior'] = saldo_posterior
        canceladas_detalhes.append(detail)

        print(f"🚫 [Aposta Handicap Cancelada] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} -> Motivo: {motivo}")

    return canceladas_detalhes


def cli_evaluate_handicap_bet(fixture_id: int = None, home_team: str = None, away_team: str = None, palpite: str = None, odd: float = None):
    """
    Ponto de entrada CLI para avaliação instantânea de aposta/palpite de AH via Gatekeeper.
    Consumido pelo ApostaController (PHP) e ferramentas de auditoria.
    """
    from checar_odds_ah_fixture import get_db_connection
    conn = get_db_connection()
    try:
        with conn.cursor() as cur:
            fix = None
            if fixture_id:
                cur.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s LIMIT 1", (fixture_id,))
                fix = cur.fetchone()
            if not fix and home_team and away_team:
                cur.execute("""
                    SELECT * FROM fixtures_trends 
                    WHERE (home_team LIKE %s OR away_team LIKE %s) 
                      AND (home_team LIKE %s OR away_team LIKE %s) 
                    ORDER BY fixture_date DESC LIMIT 1
                """, (f"%{home_team}%", f"%{home_team}%", f"%{away_team}%", f"%{away_team}%"))
                fix = cur.fetchone()
            
            if not fix:
                return {
                    "fixtureId": fixture_id,
                    "statusGatekeeper": "NAO_ANALISADO",
                    "gatekeeperMsg": "Partida não encontrada no banco de dados local.",
                    "destaque": 0
                }

            m = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite or '')
            line = float(m.group(1)) if m else 0.0
            h_name = fix.get('home_team') or home_team or ''
            a_name = fix.get('away_team') or away_team or ''
            is_away = determine_bet_side(h_name, a_name, palpite)

            c_odd = float(odd) if (odd is not None and float(odd) > 0) else 1.80
            candidate = [{'line': line, 'odd': c_odd, 'is_away': is_away, 'palpite_str': palpite}]
            status, sug, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
                fixture_dict=fix,
                betano_lines=candidate,
                allow_api_fetch=False,
                cursor=cur
            )

            odd_justa = None
            prob_poisson = None
            ev_perc = None
            destaque = 0
            if best_cand and best_cand.get('eval'):
                ev_data = best_cand['eval']
                odd_justa = ev_data.get('odd_justa')
                prob_poisson = ev_data.get('prob_eff')
                ev_perc = ev_data.get('ev_percent')
                destaque = 1 if (best_cand.get('is_tier1_massacre') or best_cand.get('destaque') == 1) else 0
                msg = f"Gatekeeper AH Green Light (+EV): Odd Real ({c_odd:.2f}) >= Odd Justa ({odd_justa:.2f}) | EV: +{ev_perc:.1f}% | Prob. Efetiva: {prob_poisson:.1f}%."
            else:
                msg = f"Aviso Gatekeeper AH (NO_BET): Entrada rejeitada pela gestão de risco ou sem margem de valor (+EV)."

            gk_cat = (best_cand or {}).get('gatekeeper_category') or determine_gatekeeper_category(status, sug, reason, best_cand)
            return {
                "fixtureId": fix['fixture_id'],
                "oddJusta": odd_justa,
                "probPoisson": prob_poisson,
                "evPercentual": ev_perc,
                "statusGatekeeper": status,
                "gatekeeperCategory": gk_cat,
                "gatekeeperMsg": msg,
                "destaque": destaque
            }
    finally:
        conn.close()


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser(description="Motor canônico de Handicap Asiático")
    parser.add_argument("--eval_bet", action="store_true", help="Avaliar aposta específica via Gatekeeper")
    parser.add_argument("--fixture_id", type=int, default=None, help="ID da partida")
    parser.add_argument("--home_team", type=str, default="", help="Time mandante")
    parser.add_argument("--away_team", type=str, default="", help="Time visitante")
    parser.add_argument("--palpite", type=str, required=False, default="", help="Linha/Palpite de AH")
    parser.add_argument("--odd", type=float, required=False, default=1.80, help="Cotação da aposta")
    args = parser.parse_args()

    if args.eval_bet:
        res = cli_evaluate_handicap_bet(
            fixture_id=args.fixture_id,
            home_team=args.home_team,
            away_team=args.away_team,
            palpite=args.palpite,
            odd=args.odd
        )
        print(json.dumps(res, ensure_ascii=False))

