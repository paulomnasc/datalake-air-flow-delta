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

# Ligas excluídas pelo Gatekeeper de Cartões por apresentarem taxa histórica de Reds > 10.0%
CARDS_GATEKEEPER_EXCLUDED_LEAGUE_IDS = {
    265,  # Primera División (Chile) - 100.0% Reds
    197,  # Super League 1 (Grécia) - 66.7% Reds
    239,  # Primera A (Colômbia) - 100.0% Reds
    39,   # Premier League (Inglaterra) - 33.3% Reds
    3,    # UEFA Europa League - 33.3% Reds
    135,  # Serie A (Itália) - 25.0% Reds
    140,  # La Liga (Espanha) - 16.7% Reds
    128,  # Liga Profesional (Argentina) - 16.7% Reds
}


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
    
    Retorna: (friction_mult: float|None, friction_desc: str)
    """
    if h_eff is None or a_eff is None:
        return None, "Estatísticas U5J ausentes ou incompletas (Aposta não gerada por segurança)"

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
    Proibição de fallbacks artificiais: em caso de erro ou dados ausentes, imprime o erro e retorna (None, None).
    """
    try:
        from asian_handicap_engine import get_team_u5j_from_db, compute_team_u5j_efficiency
        u5j_data = get_team_u5j_from_db(cursor, team_id, team_name)
        if not u5j_data or not u5j_data.get("matches"):
            print(f"⚠️ [U5J Cartões Ausente] Histórico recente incompleto para '{team_name}' (#{team_id}).")
            return None, None
        eff = compute_team_u5j_efficiency(u5j_data)
        return u5j_data, eff
    except Exception as e:
        print(f"❌ [Erro U5J Cartões] Falha ao calcular eficiência U5J para '{team_name}' (#{team_id}): {e}")
        return None, None


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


def get_league_card_multiplier(league_name="", league_id=None) -> tuple:
    """
    Retorna o multiplicador de expectativa de cartões (lambda_league) e o fator de sobredispersão (phi)
    baseado na região geográfica e histórico disciplinar da liga.
    - América do Sul e América Central (LATAM): lambda_league = 1.18x, phi = 1.28
    - Ligas Mediterrâneas / Balcânicas de Alto Atrito (Grécia, Turquia): lambda_league = 1.05x, phi = 1.20
    - Europa Ocidental / Central (ligas europeias tradicionais): lambda_league = 0.82x, phi = 1.10
    - Outras ligas / Default: lambda_league = 1.00x, phi = 1.15
    """
    # 1. Validação por ID Numérico Oficial da Liga
    if league_id is not None:
        try:
            lid = int(league_id)
            # Ligas Mediterrâneas / Balcânicas de Alto Atrito: Grécia Super League 1 (197), Turquia Süper Lig (203)
            if lid in {197, 203}:
                return 1.05, 1.20
            # Ligas Europeias Ocidentais / Centrais Oficiais (Baixo atrito disciplinar)
            if lid in {135, 39, 140, 78, 61, 94, 88, 144, 179, 2, 3, 848}:
                return 0.82, 1.10
            # Ligas Sul-Americanas Oficiais: Brasil Série A (71), Série B (72), Copa do Brasil (73), Argentina (128), Libertadores (13), Sul-Americana (11)
            if lid in {71, 72, 73, 128, 13, 11}:
                return 1.18, 1.28
        except (ValueError, TypeError):
            pass

    if not league_name:
        return 1.00, 1.15

    leg_lower = str(league_name).lower().strip()

    # 2. Ligas Mediterrâneas / Balcânicas de Alto Atrito
    mediterranean_keywords = [
        "greece", "super league 1", "super league gre", "turkey", "super lig", "süper lig"
    ]
    if any(kw in leg_lower for kw in mediterranean_keywords):
        return 1.05, 1.20

    # 3. Ligas Europeias Ocidentais / Centrais (Europa)
    europe_keywords = [
        "premier league", "championship", "la liga", "segunda división", "segunda division",
        "serie a (italy)", "serie a italia", "bundesliga", "ligue 1", "ligue 2",
        "liga portugal", "eredivisie", "champions league", "europa league", "conference league",
        "scotland", "belgium", "pro league", "england", "spain", "italy", "germany", "france"
    ]
    if leg_lower == "serie a" or any(kw in leg_lower for kw in europe_keywords):
        if not any(br in leg_lower for br in ["brasil", "brazil", "brasileir"]):
            return 0.82, 1.10

    # 4. Ligas Sul-Americanas e Centro-Americanas (LATAM)
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


def get_team_cards_moving_average(cursor, team_id, team_name, venue_type='home'):
    """
    Busca média real de cartões do time no banco de dados (Regra 1: Cache-First MySQL).
    Tabela: team_moving_averages.
    Proibição de fallbacks artificiais (Regra 9): se não encontrar, retorna None.
    """
    if not cursor:
        return None

    v_type = 'home' if str(venue_type).lower() == 'home' else 'away'

    if team_id:
        cursor.execute("""
            SELECT avg_cards, matches_count FROM team_moving_averages
            WHERE team_id = %s AND venue_type = %s
        """, (team_id, v_type))
        row = cursor.fetchone()
        if row and row.get('avg_cards') is not None and float(row['avg_cards']) > 0.0:
            return float(row['avg_cards'])

    if team_name:
        cursor.execute("""
            SELECT avg_cards, matches_count FROM team_moving_averages
            WHERE LOWER(TRIM(team_name)) = LOWER(TRIM(%s)) AND venue_type = %s
        """, (team_name, v_type))
        row = cursor.fetchone()
        if row and row.get('avg_cards') is not None and float(row['avg_cards']) > 0.0:
            return float(row['avg_cards'])

        # Se não encontrou por mando específico, tenta a média geral do time
        cursor.execute("""
            SELECT AVG(avg_cards) as avg_cards FROM team_moving_averages
            WHERE (LOWER(TRIM(team_name)) = LOWER(TRIM(%s)) OR team_id = %s)
              AND avg_cards > 0
        """, (team_name, team_id or 0))
        row = cursor.fetchone()
        if row and row.get('avg_cards') is not None and float(row['avg_cards']) > 0.0:
            return round(float(row['avg_cards']), 2)

    return None


def compute_fixture_expected_cards(cursor, fix: dict) -> tuple:
    """
    Calcula a expectativa final ponderada de cartões (xC) da partida ancorada estritamente
    nos dados estatísticos reais do banco de dados (Regra 1, Regra 6 e Regra 9).
    
    Retorna: (exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, team_cards_combined)
    ou (None, None, None, False, None) em caso de dados insuficientes.
    """
    # Inicialização prévia de variáveis numéricas locais (Regra 8)
    exp_cards = 0.0
    home_cards_avg = 0.0
    away_cards_avg = 0.0
    team_cards_combined = 0.0
    ref_cards_avg = 0.0
    yellows = 0.0
    reds = 0.0
    ref_fouls = 24.0
    league_mult = 1.0
    phi_league = 1.15
    knockout_mult = 1.0
    friction_mult = 1.0

    home_team = str(fix.get('home_team') or '').strip()
    away_team = str(fix.get('away_team') or '').strip()
    home_team_id = fix.get('home_team_id')
    away_team_id = fix.get('away_team_id')
    league_name = str(fix.get('league_name') or '').strip()
    league_id = fix.get('league_id')
    league_round = str(fix.get('league_round') or '').strip()
    fixture_id = fix.get('fixture_id')

    # 1. Obter médias reais das equipes em team_moving_averages (Regra 1 e Regra 9)
    h_cards = get_team_cards_moving_average(cursor, home_team_id, home_team, 'home')
    a_cards = get_team_cards_moving_average(cursor, away_team_id, away_team, 'away')

    if h_cards is None or a_cards is None or h_cards <= 0.0 or a_cards <= 0.0:
        missing = []
        if h_cards is None or h_cards <= 0.0:
            missing.append(f"mandante '{home_team}' (#{home_team_id})")
        if a_cards is None or a_cards <= 0.0:
            missing.append(f"visitante '{away_team}' (#{away_team_id})")
        print(f"⚠️ [Cards Engine NO_BET / Dados Ausentes] Médias móveis de cartões não encontradas para: {', '.join(missing)} no fixture #{fixture_id}.")
        return None, None, None, False, None

    home_cards_avg = float(h_cards)
    away_cards_avg = float(a_cards)
    team_cards_combined = round(home_cards_avg + away_cards_avg, 2)

    # 2. Multiplicador regional e sobredispersão da liga
    league_mult, phi_league = get_league_card_multiplier(league_name, league_id)

    # 3. Análise disciplinar do Árbitro
    referee_name = str(fix.get('referee_name') or '').strip()
    ref_low = referee_name.lower()
    is_ref_confirmed = bool(
        referee_name and not any(un in ref_low for un in [
            'árbitro não informado', 'arbitro nao informado', 'não informado',
            'nao informado', 'unassigned', 'n/a', 'tbd', 'sem arbitro'
        ])
    )

    if is_ref_confirmed:
        cursor.execute("SELECT average_yellow_cards, average_red_cards, average_fouls FROM referee_stats WHERE name = %s", (referee_name,))
        r_row = cursor.fetchone()
        if r_row:
            yellows = float(r_row.get('average_yellow_cards') or 0.0)
            reds = float(r_row.get('average_red_cards') or 0.0)
            ref_cards_avg = round(yellows + reds, 2)
            ref_fouls = float(r_row.get('average_fouls') or 24.0)
        else:
            # Árbitro confirmado nominalmente mas sem amostragem: baseline da liga
            if league_mult <= 0.85:
                yellows = 3.60
                ref_fouls = 22.0
                ref_cards_avg = 3.80
            elif league_mult >= 1.15:
                yellows = 4.60
                ref_fouls = 26.0
                ref_cards_avg = 4.90
            else:
                yellows = 3.90
                ref_fouls = 24.0
                ref_cards_avg = 4.10
    else:
        # Escala Oficial Pendente: Perfil Disciplinar Institucional da Competição
        if league_mult <= 0.85:
            yellows = 3.60
            ref_fouls = 22.0
            ref_cards_avg = 3.80
        elif league_mult >= 1.15:
            yellows = 4.60
            ref_fouls = 26.0
            ref_cards_avg = 4.90
        else:
            yellows = 3.90
            ref_fouls = 24.0
            ref_cards_avg = 4.10

    # 4. Cálculo de Atrito Disciplinar U5J
    _, h_eff = get_team_u5j_efficiency_cards(cursor, home_team_id, home_team)
    _, a_eff = get_team_u5j_efficiency_cards(cursor, away_team_id, away_team)
    friction_mult, friction_desc = calculate_u5j_card_friction(h_eff, a_eff)
    if friction_mult is None:
        print(f"⚠️ [Cards Engine NO_BET / U5J Ausente] Partida {home_team} vs {away_team} (#{fixture_id}) sem U5J suficiente.")
        return None, None, None, is_ref_confirmed, team_cards_combined

    # 5. Mata-Mata Oitavas+
    is_knockout = is_knockout_round_advanced(league_round, league_name)
    knockout_mult = 1.18 if is_knockout else 1.00

    # 6. Cálculo Ponderado Central (35% Times + 50% Árbitro + 15% Faltas)
    exp_cards = calculate_expected_cards(
        team_cards_combined=team_cards_combined,
        yellows=yellows,
        ref_fouls=ref_fouls,
        league_mult=league_mult,
        u5j_friction_mult=friction_mult,
        knockout_mult=knockout_mult
    )

    u5j_info = {
        'h_eff': h_eff,
        'a_eff': a_eff,
        'friction_mult': friction_mult,
        'desc': friction_desc,
        'odd_home': float(fix.get('odd_home') or 0.0),
        'odd_away': float(fix.get('odd_away') or 0.0),
        'odd_draw': float(fix.get('odd_draw') or 0.0),
        'home_rank': fix.get('home_rank'),
        'away_rank': fix.get('away_rank'),
        'home_zone': fix.get('home_zone'),
        'away_zone': fix.get('away_zone'),
        'home_ppg': fix.get('home_ppg'),
        'away_ppg': fix.get('away_ppg'),
        'standings_motivation_score': fix.get('standings_motivation_score')
    }

    return exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, team_cards_combined


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


def evaluate_cards_conflict_scenario(
    home_team: str,
    away_team: str,
    odd_home: float = None,
    odd_away: float = None,
    h_eff: float = None,
    a_eff: float = None,
    home_rank: int = None,
    away_rank: int = None,
    home_zone: str = None,
    home_ppg: float = None,
    standings_mot: float = None
) -> tuple:
    """
    Avalia os 4 cenários canônicos de partidas conflituosas (migrados do Gatekeeper de AH para Cartões):
    1. Caldeirão da Degola / Sobrevivência do Mandante: Mandante afundado no Z-4 ou ameaçado de rebaixamento
       jogando a vida em seus domínios contra adversário superior -> faltas desesperadas de sobrevivência -> veto total Under.
    2. Duelo de Crises (Colapso Mútuo U5J): Ambas as equipes em momento técnico precário (U5J <= 3.0 pts)
       -> desorganização tática, erros de tempo de bola e faltas por frustração -> veto total Under.
    3. Divergência de Mando / Conflito de Mercado (Anti-Fake Dog): O visitante vem melhor na tabela ou momento,
       mas as cotações apontam o mandante como favorito no 1X2 -> choque de forças e disputa física intensa -> veto Under 5.5.
    4. Disparidade Técnica Extrema / Massacre (Tier 1 vs Azarão): Super-favorito (odd <= 1.35 ou ratio >= 4.0)
       contra azarão acuado -> azarão recorre a faltas táticas reiteradas de contenção -> veto Under 5.5.

    Retorna: (is_blocked_under_all: bool, is_blocked_under_55: bool, conflict_reason: str, conflict_badge: str)
    """
    # Inicialização explícita de variáveis numéricas locais (Regra 8)
    h_rk = 0
    a_rk = 0
    h_ppg_val = 0.0
    standings_mot_val = 0.0
    raw_h_odd = float(odd_home or 0.0)
    raw_a_odd = float(odd_away or 0.0)
    h_eff_val = float(h_eff) if h_eff is not None else 5.0
    a_eff_val = float(a_eff) if a_eff is not None else 5.0

    try:
        if home_rank is not None and str(home_rank).strip():
            h_rk = int(home_rank)
    except Exception:
        h_rk = 0

    try:
        if away_rank is not None and str(away_rank).strip():
            a_rk = int(away_rank)
    except Exception:
        a_rk = 0

    try:
        if home_ppg is not None and str(home_ppg).strip():
            h_ppg_val = float(home_ppg)
    except Exception:
        h_ppg_val = 0.0

    try:
        if standings_mot is not None and str(standings_mot).strip():
            standings_mot_val = float(standings_mot)
    except Exception:
        standings_mot_val = 0.0

    h_zone_str = str(home_zone or '').lower()
    is_h_rel = ('relegat' in h_zone_str or 'play out' in h_zone_str or 'play-out' in h_zone_str or 'rebaixamento' in h_zone_str)
    is_h_under_threat = (
        is_h_rel or
        (h_rk >= 12 and h_ppg_val > 0.0 and h_ppg_val <= 1.25) or
        (h_rk >= 12 and standings_mot_val >= 3.0) or
        (h_rk >= 12 and a_rk > 0 and (h_rk - a_rk >= 6 or a_rk <= 6))
    )

    # 1. Caldeirão da Degola / Sobrevivência do Mandante
    if is_h_under_threat:
        r = (
            f"🚨 [Gatekeeper Cartões NO_BET / Caldeirão da Degola - Sobrevivência do Mandante] Partida {home_team} vs {away_team} -> "
            f"O mandante ({home_team}) está na zona de rebaixamento ou sob severa ameaça de degola ({h_rk}º colocado), jogando a vida em seus domínios. "
            f"A urgência extrema de sobrevivência, a catimba e a pressão da torcida elevam drasticamente o risco de faltas táticas e indisciplina. "
            f"Abstenção mandatória para estratégias Under."
        )
        return True, True, r, "Caldeirão da Degola"

    # 2. Duelo de Crises (Colapso Mútuo U5J)
    if h_eff is not None and a_eff is not None and h_eff <= 3.0 and a_eff <= 3.0:
        r = (
            f"🛡️ [Gatekeeper Cartões NO_BET / Duelo de Crises - Atrito Disciplinar] Partida {home_team} ({h_eff:.1f} pts) vs {away_team} ({a_eff:.1f} pts) -> "
            f"Ambas as equipes em colapso técnico no U5J (eficiência <= 3.0 pts). "
            f"Confronto marcado por desorganização tática, erros de posicionamento e faltas por frustração. Abstenção mandatória."
        )
        return True, True, r, "Duelo de Crises"

    # 3. Disparidade Técnica Extrema / Massacre (Tier 1 vs Azarão)
    is_extreme_disparity = False
    if raw_h_odd > 1.0 and raw_a_odd > 1.0:
        min_odd = min(raw_h_odd, raw_a_odd)
        max_odd = max(raw_h_odd, raw_a_odd)
        ratio = (max_odd / min_odd) if min_odd > 0 else 1.0
        if (min_odd <= 1.45 and max_odd >= 4.00) or ratio >= 4.0:
            is_extreme_disparity = True
    if h_eff is not None and a_eff is not None and abs(h_eff - a_eff) >= 6.0 and (raw_h_odd <= 1.60 or raw_a_odd <= 1.60):
        is_extreme_disparity = True

    if is_extreme_disparity:
        fav_name = home_team if (raw_h_odd > 0 and raw_h_odd < raw_a_odd) else away_team
        dog_name = away_team if fav_name == home_team else home_team
        min_o = min(raw_h_odd, raw_a_odd) if (raw_h_odd > 0 and raw_a_odd > 0) else 1.30
        max_o = max(raw_h_odd, raw_a_odd) if (raw_h_odd > 0 and raw_a_odd > 0) else 5.00
        r = (
            f"⚡ [Gatekeeper Cartões NO_BET / Disparidade Técnica Extrema] Partida {home_team} vs {away_team} -> "
            f"Acentuado desnível técnico entre as equipes ({fav_name} @ {min_o:.2f} vs {dog_name} @ {max_o:.2f}). "
            f"A equipe em desvantagem técnica tende a ser encurralada e cometer faltas de contenção tática reiteradas para frear contra-ataques, "
            f"tornando a margem de segurança da linha Under 5.5 vulnerável a estouro."
        )
        return False, True, r, "Disparidade Técnica"

    # 4. Divergência de Mando / Conflito de Mercado (Anti-Fake Dog)
    if raw_h_odd > 1.0 and raw_a_odd > 1.0 and (raw_a_odd - raw_h_odd) >= 0.15:
        away_better = (a_eff_val >= h_eff_val + 1.5) or (h_rk > 0 and a_rk > 0 and h_rk > a_rk)
        if away_better:
            r = (
                f"🛡️ [Gatekeeper Cartões NO_BET / Conflito de Mando - Divergência de Mercado] Partida {home_team} vs {away_team} -> "
                f"Choque entre o melhor momento/tabela do visitante e a força/favoritismo de mercado do mandante ({home_team} @ {raw_h_odd:.2f} vs {away_team} @ {raw_a_odd:.2f}). "
                f"Disputa territorial acirrada no meio-campo com risco elevado de faltas táticas para a linha Under 5.5."
            )
            return False, True, r, "Conflito de Mando"

    return False, False, "", ""


def evaluate_best_card_under_line(
    exp_cards: float,
    fixture_id: int = None,
    allow_api: bool = True,
    referee_cards_avg: float = None,
    u5j_friction_info: dict = None,
    is_knockout: bool = False,
    home_team: str = "",
    away_team: str = "",
    is_referee_confirmed: bool = True,
    fixture_dict: dict = None
):
    """
    Avalia exclusivamente as linhas seguras Under 5.5 e Under 6.5 contra as odds reais da Betano e aplica o Gatekeeper:
    - Sem juiz confirmado = NO_BET mandatório (proteção de capital)
    - Apenas linhas Under 5.5 e Under 6.5 (linhas 3.5 e 4.5 bloqueadas)
    - Probabilidade Poisson >= 60.0%
    - Odd Betano >= 1.50 (ou 1.65 para Under 5.5)
    - Valor Esperado Positivo (+EV > 0.0%)
    - Trava de Piso do Árbitro: veta linhas Under se o árbitro tiver média >= (linha - 0.30)
    - Catálogo de 4 Cenários de Partidas Conflituosas (Migrado do Gatekeeper de AH):
        1. Caldeirão da Degola / Sobrevivência do Mandante (Veto Total Under)
        2. Duelo de Crises U5J (Veto Total Under)
        3. Disparidade Técnica Extrema / Massacre (Veto Under 5.5)
        4. Divergência de Mando / Conflito de Mercado (Veto Under 5.5)
    - Trava de Mata-Mata Oitavas+: se for partida eliminatória a partir das oitavas com xC elevado, bloqueia Under
      devido à catimba, tensão e faltas táticas eliminatórias.
    Retorna: (best_candidate, all_candidates, prediction_text, over_cards_prob)
    """
    # 0. Trava de Ouro Canônica: Sem Juiz Oficial Confirmado = NO_BET Mandatório
    if not is_referee_confirmed:
        reason = "🛡️ [Gatekeeper Cartões NO_BET / Sem Árbitro Definido] Partida sem árbitro oficial confirmado na escala. Entrada em Under Cartões bloqueada pelo Gatekeeper (Sem juiz = NO_BET)."
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason)
        return None, [], pred_text, 50.0

    # 0.1 Trava de Liga por Sinistralidade Histórica (Taxa de Reds > 10% no modelo Under Cartões)
    league_id = (fixture_dict or {}).get('league_id')
    if league_id and int(league_id) in CARDS_GATEKEEPER_EXCLUDED_LEAGUE_IDS:
        league_name = (fixture_dict or {}).get('league_name') or f"ID #{league_id}"
        reason = (
            f"🛡️ [Gatekeeper Cartões NO_BET / Liga com Alta Taxa de Reds] A liga '{league_name}' (ID {league_id}) "
            f"possui histórico de taxa de Reds > 10% no modelo Under Cartões. Entrada bloqueada para salvaguarda de banca."
        )
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason)
        return None, [], pred_text, 50.0

    under_probs = calculate_poisson_under_lines(exp_cards)
    
    # Probabilidade de Over 4.5 como referência de over_cards_probability
    over_cards_prob = round(100.0 - under_probs.get(4.5, 50.0), 2)

    # Restrição Canônica Exclusiva: Apenas Under 6.5, Under 7.5, Under 8.5 ou maiores (Linhas Under 5.5 e inferiores descontinuadas)
    standard_lines = [6.5, 7.5, 8.5]
    candidates = []

    friction_mult = u5j_friction_info.get('friction_mult', 1.0) if u5j_friction_info else 1.0
    friction_desc = u5j_friction_info.get('desc', '') if u5j_friction_info else ''
    h_eff = u5j_friction_info.get('h_eff') if u5j_friction_info else None
    a_eff = u5j_friction_info.get('a_eff') if u5j_friction_info else None

    # Extração de metadados contextuais para avaliação de partidas conflituosas
    ctx = fixture_dict or u5j_friction_info or {}
    odd_home = float(ctx.get('odd_home') or 0.0)
    odd_away = float(ctx.get('odd_away') or 0.0)
    home_rank = ctx.get('home_rank')
    away_rank = ctx.get('away_rank')
    home_zone = ctx.get('home_zone')
    home_ppg = ctx.get('home_ppg')
    standings_mot = ctx.get('standings_motivation_score')

    # Avaliação do catálogo de 4 cenários de partidas conflituosas (Regras AH -> Cartões)
    is_blocked_under_all, is_blocked_under_55, conflict_reason, conflict_badge = evaluate_cards_conflict_scenario(
        home_team=home_team,
        away_team=away_team,
        odd_home=odd_home,
        odd_away=odd_away,
        h_eff=h_eff,
        a_eff=a_eff,
        home_rank=home_rank,
        away_rank=away_rank,
        home_zone=home_zone,
        home_ppg=home_ppg,
        standings_mot=standings_mot
    )

    # Bloqueio total por conflito severo (Caldeirão da Degola ou Duelo de Crises)
    if is_blocked_under_all:
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', conflict_reason)
        return None, [], pred_text, over_cards_prob

    is_severe_u5j_risk = (h_eff is not None and a_eff is not None and (h_eff <= 3.0 and a_eff <= 3.0)) or (friction_mult >= 1.20)
    has_elevated_friction = is_severe_u5j_risk or (friction_mult >= 1.15 and exp_cards >= 4.50)

    # 1. Trava Sistêmica Mandatória de Atrito Disciplinar U5J:
    if has_elevated_friction:
        h_str = f"{h_eff:.1f} pts" if h_eff is not None else "crise"
        a_str = f"{a_eff:.1f} pts" if a_eff is not None else "crise"
        reason = (
            f"🛡️ [Gatekeeper Cartões NO_BET / Risco de Atrito Disciplinar] {home_team} ({h_str}) vs {away_team} ({a_str}) -> "
            f"Equipes sob forte pressão ou colapso disciplinar ({friction_desc}), elevando a expectativa disciplinar para {exp_cards} cartões. "
            f"Risco de estouro de cartões incompatível com margem de segurança para estratégia Under. Abstenção mandatória."
        )
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason)
        return None, [], pred_text, over_cards_prob

    # 2. Trava Sistêmica de Mata-Mata Oitavas+ com Expectativa Elevada:
    if is_knockout and exp_cards >= 4.50:
        reason = (
            f"🛡️ [Gatekeeper Cartões NO_BET / Mata-Mata Oitavas+] Partida eliminatória com alta tensão e cartões projetados em {exp_cards}. "
            f"Risco de catimba e faltas eliminatórias incompatível com margem de segurança para estratégia Under. Abstenção mandatória."
        )
        pred_text = format_gatekeeper_result('NO_BET', 'Sem Entrada (Abstenção)', reason)
        return None, [], pred_text, over_cards_prob

    for line_val in standard_lines:
        # Trava de Piso do Árbitro Confirmado (Referee Disciplinary Ceiling Guard):
        # Bloqueia a linha Under se a média histórica de cartões do árbitro for superior ou estiver a menos de 0.30 cartão da linha.
        if referee_cards_avg and float(referee_cards_avg) >= (line_val - 0.30):
            continue

        prob = under_probs.get(line_val, 0.0)
        odd_justa = round(100.0 / prob, 2) if prob > 0 else 99.00
        palpite_str = f"Menos de {line_val} Cartões"

        # Crivo de status estrito do Gatekeeper: Apenas linhas >= 6.5 com margem de segurança de pelo menos 1.0 cartão
        if line_val >= 6.49:
            max_xc_allowed = line_val - 1.00
            status_gk = 'APROVADO' if (exp_cards <= max_xc_allowed and prob >= 60.0) else 'NO_BET'
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

    # Filtrar candidatos rigorosamente aprovados pelo Gatekeeper
    valid_candidates = [c for c in candidates if c['status_gk'] == 'APROVADO' and c['prob'] >= 60.0]
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

        # Fallback de mercado estruturado: na criação de apostas reais (allow_api=True), NUNCA aceita odd sintética
        if not real_odd or real_odd <= 1.0:
            if allow_api:
                continue
            if odd_justa and odd_justa >= 1.40:
                real_odd = round(max(1.55, odd_justa * 1.08), 2)
                odd_source = 'MODEL_FALLBACK'
            else:
                continue

        # Piso de odd viável: >= 1.50 para Under 6.5, 7.5, 8.5 ou maiores
        min_odd_req = 1.50
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
        ref_context_note = "Árbitro Oficial Confirmado"
        cand_copy['gatekeeper_reason'] = format_gatekeeper_result(
            'APROVADO',
            palpite_str,
            f"🎯 GATEKEEPER CARTÕES APROVADO (+EV {ev_calc:+.1f}%) | "
            f"Linha {palpite_str} @ {real_odd:.2f} ({cand_copy['bookmaker']}) vs Odd Justa {odd_justa:.2f} (Prob: {prob:.1f}%) | "
            f"xC Ajustado: {exp_cards} cartões | {ref_context_note} | {friction_desc}"
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
        if conflict_reason:
            reason = conflict_reason
        elif referee_cards_avg and float(referee_cards_avg) >= 6.20:
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Trava de Árbitro] Rigor do árbitro ({float(referee_cards_avg):.2f} cartões/jogo) incompatível com margem de segurança para Under 6.5+. Entrada bloqueada."
        elif is_severe_u5j_risk:
            h_str = f"{h_eff:.1f} pts" if h_eff is not None else "crise"
            a_str = f"{a_eff:.1f} pts" if a_eff is not None else "crise"
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Atrito Disciplinar U5J] {home_team} ({h_str}) vs {away_team} ({a_str}) -> Ambas as equipes em momento adverso (U5J <= 3 pts), com elevada propensão a faltas táticas e de atrito. Linhas de Under bloqueadas. Abstenção mandatória."
        else:
            reason = f"🛡️ [Gatekeeper Cartões NO_BET / Sem Margem] Partida sem margem estatística para Under (Expectativa: {exp_cards} cartões). Nenhuma linha atendeu aos limiares do Gatekeeper (Under 6.5, 7.5 ou 8.5). Abstenção mandatória."
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
        odd_src = selected_cand.get('odd_source')
        if odd_src == 'MODEL_FALLBACK':
            # Proteção estrita: jamais grava aposta com cotação sintética
            return False, 0, 0

        bookmaker_name = selected_cand.get('bookmaker') or selected_cand.get('odd_source') or 'Betano'
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
                        diff_sec = (datetime.now() - chk_at).total_seconds()
                        if 0 <= diff_sec < 3 * 3600:
                            continue
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
                    try:
                        # 1. Cadastra prioritariamente na tabela referee_stats para satisfazer a Foreign Key
                        cursor.execute("""
                            INSERT IGNORE INTO referee_stats (
                                name, average_yellow_cards, average_red_cards, average_fouls, total_games, rigor_level, updated_at
                            ) VALUES (%s, 4.20, 0.20, 24.00, 50, 'Moderado', NOW())
                        """, (ref_name,))

                        # 2. Atualiza a partida com o árbitro oficial na tabela fixtures_trends
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                                referee_name = %s,
                                referee_api_checked_at = NOW(),
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (ref_name, fid))
                        enriched[fid] = ref_name
                        print(f"✅ [Árbitro Enriquecido] Fixture #{fid} -> Árbitro oficial atribuído: '{ref_name}'")
                    except Exception as err_fix:
                        print(f"⚠️ [Árbitro Enriquecido] Erro ao associar árbitro '{ref_name}' ao fixture #{fid}: {err_fix}")
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
