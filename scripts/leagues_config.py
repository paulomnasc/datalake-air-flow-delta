#!/usr/bin/env python3
"""
Módulo Global de Configuração de Ligas e Copas Monitoradas (FootballWeb Pipeline).
Centraliza o escopo de ligas permitidas para ingestão (football_ingest_trends.py)
e simulação/criação de apostas diárias (criar_apostas_cartoes_diario.py e criar_apostas_handicap_diario.py).
"""

# Catálogo Unificado Global: ID API-Sports -> Nome Oficial / País
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
    103: "Eliteserien (Noruega)",
    104: "1. Division (Noruega)",
    94: "Primeira Liga (Portugal)",
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

# Conjunto de IDs para busca instantânea O(1)
ALLOWED_LEAGUE_IDS = set(ALLOWED_LEAGUES.keys())

# Palavras-chave para validação fallback por nome textual
ALLOWED_LEAGUE_NAMES = [
    'brasileirão', 'brasileirao', 'serie a', 'série a', 'serie b', 'série b', 'serie c', 'série c',
    'copa do brasil', 'copa brasil', 'copa do nordeste', 'supercopa',
    'premier league', 'championship', 'league one', 'league two', 'fa cup', 'efl cup',
    'la liga', 'la liga 2', 'copa del rey',
    'bundesliga', '2. bundesliga', 'dfb pokal',
    'ligue 1', 'ligue 2', 'coupe de france',
    'primeira liga', 'segunda liga', 'liga portugal',
    'eredivisie', 'eerste divisie',
    'pro league', 'jupiler pro league', 'saudi pro league',
    'super lig', 'süper lig',
    'premiership', 'scottish premiership',
    'liga profesional', 'primera division', 'primera nacional', 'copa argentina',
    'super league 1', 'super league', 'superliga',
    'champions league', 'europa league', 'conference league',
    'libertadores', 'copa sudamericana', 'sudamericana', 'recopa',
    'major league soccer', 'mls', 'leagues cup',
    'liga mx', 'liga de expansion',
    'allsvenskan', 'superettan', 'eliteserien',
    'j1 league', 'j-league', 'j.league',
    'k league', 'k-league', 'k league 1',
    'veikkausliiga', 'ekstraklasa', 'czech first league'
]


def is_allowed_league(league_id, league_name: str = "", fixture_date=None) -> bool:
    """
    Verifica se a liga informada (por ID ou Nome) pertence ao escopo global unificado de ligas monitoradas.
    Filtra automaticamente partidas femininas e torneios de categorias de base.
    """
    if not league_name and league_id is None:
        return False

    l_name_low = str(league_name or '').lower().strip()

    # 1. Bloqueia partidas femininas
    if any(w in l_name_low for w in ['women', 'feminino', 'femenina', ' w ', '(w)']):
        return False

    # 2. Bloqueia categorias de base
    if any(w in l_name_low for w in ['u17', 'u19', 'u20', 'u21', 'u23', 'sub-17', 'sub-20', 'sub-23']):
        return False

    # 3. Validação primária por ID Numérico Oficial (O(1))
    if league_id is not None:
        try:
            lid = int(league_id)
            if lid in ALLOWED_LEAGUE_IDS:
                return True
            else:
                return False
        except (ValueError, TypeError):
            pass

    # 4. Validação por Nome da Liga (Fallback caso league_id venha nulo)
    if any(allowed in l_name_low for allowed in ALLOWED_LEAGUE_NAMES):
        return True

    return False
