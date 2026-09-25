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
    73: "Copa do Brasil (Brasil)",
    39: "Premier League (Inglaterra)",
    140: "La Liga (Espanha)",
    143: "Copa del Rey (Espanha)",
    135: "Serie A (Italia)",
    137: "Coppa Italia (Italia)",
    78: "Bundesliga (Alemanha)",
    81: "DFB Pokal (Alemanha)",
    61: "Ligue 1 (Franca)",
    66: "Coupe de France (Franca)",
    2: "Champions League (Europa)",
    3: "Europa League (Europa)",
    531: "UEFA Super Cup (Europa)",
    5: "Nations League (Europa)",
    4: "Euro (Europa)",
    13: "Copa Libertadores (America do Sul)",
    11: "Copa Sudamericana (America do Sul)",
    541: "Recopa Sudamericana (America do Sul)",
    9: "Copa America (America do Sul)",
    772: "Leagues Cup (America)",
    262: "Liga MX (Mexico)",
    1028: "CONCACAF Central American Cup (CONCACAF)",
    16: "CONCACAF Champions Cup (CONCACAF)",
    113: "Allsvenskan (Suecia)",
    103: "Eliteserien (Noruega)",
    94: "Primeira Liga (Portugal)",
    88: "Eredivisie (Holanda)",
    128: "Primera Division (Argentina)",
    130: "Copa Argentina (Argentina)",
    ## 98: "J1 League (Japao)",
    ## 292: "K League 1 (Coreia do Sul)",
    283: "Liga I (Romenia)",
    286: "Super Liga (Servia)",
    244: "Veikkausliiga (Finlandia)",
    281: "Primera Division (Peru)",
    242: "Liga Pro (Equador)",
    917: "Copa Ecuador (Equador)",
    268: "Primera Division (Uruguai)",
    265: "Primera Division (Chile)",
    267: "Copa Chile (Chile)",
    239: "Primera Division (Colombia)",
    ## 501: "Copa Paraguay (Paraguai)",
    ## 169: "Super League (China)",
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
    253: "Major League Soccer (EUA)",
    479: "Canadian Premier League (Canada)",
    259: "Canadian Championship (Canada)",
    ## 10: "Friendlies (Amistosos de Selecoes)",
    1: "Copa do Mundo (Mundo)",
    15: "FIFA Club World Cup (Mundo)",
    17: "AFC Champions League (Asia)"
    ## 18: "AFC Champions League Two (Asia)"
}

# Conjunto de IDs para busca instantânea O(1)
ALLOWED_LEAGUE_IDS = set(ALLOWED_LEAGUES.keys())

# Palavras-chave para validação fallback por nome textual
ALLOWED_LEAGUE_NAMES = [
    'brasileirão', 'brasileirao', 'serie a', 'série a', 'serie b', 'série b',
    'copa do brasil', 'copa brasil', 'copa do nordeste', 'supercopa',
    'premier league', 'fa cup', 'efl cup',
    'la liga', 'copa del rey',
    'bundesliga', 'dfb pokal',
    'ligue 1', 'coupe de france',
    'primeira liga', 'liga portugal',
    'eredivisie',
    'pro league', 'jupiler pro league', 'saudi pro league',
    'super lig', 'süper lig',
    'premiership', 'scottish premiership',
    'liga profesional', 'primera division', 'copa argentina', 'copa chile',
    'super league 1', 'super league', 'superliga',
    'champions league', 'europa league',
    'libertadores', 'copa sudamericana', 'sudamericana', 'recopa',
    'leagues cup',
    'liga mx',
    'allsvenskan', 'eliteserien',
    'j1 league', 'j-league', 'j.league',
    'k league', 'k-league', 'k league 1',
    'veikkausliiga', 'ekstraklasa', 'czech first league',
    'mls', 'major league soccer', 'canadian premier league', 'canadian championship',
    'öfb cup', 'oefb cup', 'ofb cup', 'austria cup', 'copa da austria'
]


def is_allowed_league(league_id, league_name: str = "", fixture_date=None) -> bool:
    """
    Verifica se a liga informada (por ID ou Nome) pertence ao escopo global unificado de ligas monitoradas.
    Filtra automaticamente partidas femininas, torneios de base e divisões secundárias não autorizadas.
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
            return lid in ALLOWED_LEAGUE_IDS
        except (ValueError, TypeError):
            pass

    # 4. Bloqueia divisões secundárias genéricas (Fallback para validação apenas textual)
    if any(tier in l_name_low for tier in ['2. liga', '2. bundesliga', 'segunda division', 'segunda división', 'serie c', 'serie d', 'championship', 'league one', 'league two']):
        try:
            lid = int(league_id) if league_id is not None else None
            if lid != 72:
                return False
        except (ValueError, TypeError):
            return False

    # 5. Validação por Nome da Liga (Fallback caso league_id venha nulo)
    if any(allowed in l_name_low for allowed in ALLOWED_LEAGUE_NAMES):
        return True

    return False


# ==============================================================================
# CLUBES CONSAGRADOS DE ELITE MUNDIAL / CONTINENTAL (TIER 1)
# ==============================================================================
# Dicionário canônico indexado pelo ID numérico oficial da API-Sports / Banco de Dados.
# Elimina 100% dos riscos de homônimos (ex: Barcelona da Espanha vs Barcelona SC Guayaquil)
# e divergências de abreviações textuais.
TIER_1_ELITE_CLUBS = {
    # Espanha (La Liga)
    529: "Barcelona",
    541: "Real Madrid",
    530: "Atlético Madrid",
    531: "Athletic Club",
    536: "Sevilla",

    # Inglaterra (Premier League - Big Six)
    33:  "Manchester United",
    50:  "Manchester City",
    40:  "Liverpool",
    42:  "Arsenal",
    49:  "Chelsea",
    47:  "Tottenham",

    # Alemanha (Bundesliga)
    157: "Bayern Munich",
    165: "Borussia Dortmund",
    168: "Bayer Leverkusen",
    173: "RB Leipzig",

    # França (Ligue 1)
    85:  "Paris Saint Germain",
    91:  "Monaco",
    80:  "Lyon",
    81:  "Marseille",

    # Itália (Serie A)
    505: "Inter",
    489: "AC Milan",
    496: "Juventus",
    492: "Napoli",
    497: "AS Roma",
    487: "Lazio",

    # Portugal (Primeira Liga - Os Três Grandes)
    211: "Benfica",
    212: "FC Porto",
    228: "Sporting CP",

    # Holanda (Eredivisie - Os Três Grandes)
    194: "Ajax",
    197: "PSV Eindhoven",
    209: "Feyenoord",

    # Escócia (Scottish Premiership - Old Firm)
    247: "Celtic",
    257: "Rangers",

    # Turquia (Süper Lig - Os Três Grandes)
    645: "Galatasaray",
    611: "Fenerbahçe",
    549: "Beşiktaş",

    # Grécia (Super League - Big Four)
    553: "Olympiakos Piraeus",
    617: "Panathinaikos",
    619: "PAOK",
    575: "AEK Athens FC",

    # Bélgica (Pro League)
    569: "Club Brugge KV",
    554: "Anderlecht",
    1393: "Union St. Gilloise",
    742: "Genk",

    # Áustria (Bundesliga)
    571: "Red Bull Salzburg",
    637: "Sturm Graz",
    781: "Rapid Vienna",

    # Suíça (Super League)
    565: "BSC Young Boys",
    551: "FC Basel 1893",

    # Dinamarca (Superliga)
    400: "FC Copenhagen",

    # Sérvia (Super Liga - Dérbi Eterno)
    598: "FK Crvena Zvezda",
    573: "FK Partizan",

    # Tchéquia (Czech First League)
    560: "Slavia Praha",
    628: "Sparta Praha",
    567: "Plzen",

    # Romênia (Liga I)
    559: "FCSB",
    2246: "CFR 1907 Cluj",

    # Polônia (Ekstraklasa)
    339: "Legia Warszawa",
    347: "Lech Poznan",

    # Suécia (Allsvenskan)
    375: "Malmo FF",

    # Noruega (Eliteserien)
    327: "Bodo/Glimt",
    329: "Molde",
    331: "Rosenborg",

    # Finlândia (Veikkausliiga)
    649: "HJK Helsinki",

    # Arábia Saudita (Saudi Pro League - PIF Big Four)
    2932: "Al-Hilal Saudi FC",
    2939: "Al-Nassr",
    2938: "Al-Ittihad FC",
    2929: "Al-Ahli Jeddah",

    # Brasil (G-12 do Futebol Brasileiro)
    127:  "Flamengo",
    121:  "Palmeiras",
    1062: "Atlético Mineiro",
    126:  "Sao Paulo",
    131:  "Corinthians",
    130:  "Gremio",
    119:  "Internacional",
    124:  "Fluminense",
    120:  "Botafogo",
    135:  "Cruzeiro",
    133:  "Vasco DA Gama",
    128:  "Santos",

    # Argentina (Cinco Grandes + Potências)
    451: "Boca Juniors",
    435: "River Plate",
    436: "Racing Club",
    453: "Independiente",
    460: "San Lorenzo",
    450: "Estudiantes L.P.",
    438: "Velez Sarsfield",

    # Uruguai (Primera Division - Os Dois Grandes)
    2348: "Penarol",
    2356: "Club Nacional",

    # Colômbia (Primera Division - Grandes Históricos)
    1137: "Atletico Nacional",
    1125: "Millonarios",
    1139: "Santa Fe",
    1135: "Junior",
    1138: "America de Cali",

    # Chile (Primera Division - Os Três Grandes)
    2315: "Colo Colo",
    2323: "Universidad de Chile",
    2994: "U. Catolica",

    # Equador (Liga Pro - Grandes & Potências Internacionais)
    1158: "LDU de Quito",
    1153: "Independiente del Valle",
    1152: "Barcelona SC",
    1148: "Emelec",

    # Peru (Primera Division - Trio de Ferro de Lima)
    2540: "Universitario",
    2553: "Alianza Lima",
    2546: "Sporting Cristal",

    # Paraguai (Copa Paraguay - Os Três Grandes)
    1182: "Olimpia",
    1176: "Cerro Porteno",
    1179: "Libertad Asuncion",

    # México (Liga MX - Quatro Grandes + Potências e Campeões Internacionais)
    2287: "Club America",
    2279: "Tigres UANL",
    2282: "Monterrey",
    2278: "Guadalajara Chivas",
    2295: "Cruz Azul",
    2286: "U.N.A.M. - Pumas",
    2281: "Toluca",
    2292: "CF Pachuca",

    # Japão (J1 League)
    289: "Vissel Kobe",
    296: "Yokohama F. Marinos",
    294: "Kawasaki Frontale",
    287: "Urawa",

    # Coreia do Sul (K League 1)
    2762: "Jeonbuk Motors",
    2767: "Ulsan Hyundai FC",
    2766: "FC Seoul",

    # China (Super League)
    836: "SHANGHAI SIPG",
    833: "Shanghai Shenhua",
    844: "Shandong Luneng"
}


# Dicionário Canônico de Aliases para Resolução Determinística de team_id (quando id for nulo)
TIER_1_NAME_TO_ID = {
    # Brasil (G-12)
    "flamengo": 127, "cr flamengo": 127,
    "palmeiras": 121, "se palmeiras": 121,
    "atletico mineiro": 1062, "atletico-mg": 1062, "atletico mg": 1062, "galo": 1062, "atlético mineiro": 1062, "atlético-mg": 1062,
    "sao paulo": 126, "spfc": 126, "são paulo": 126,
    "corinthians": 131, "sc corinthians": 131, "sc corinthians paulista": 131,
    "gremio": 130, "grêmio": 130, "gremio fbpa": 130,
    "internacional": 119, "sc internacional": 119, "inter": 119,
    "fluminense": 124, "fluminense fc": 124,
    "botafogo": 120, "botafogo fr": 120, "botafogo rj": 120,
    "cruzeiro": 135, "cruzeiro ec": 135,
    "vasco da gama": 133, "vasco": 133, "cr vasco da gama": 133,
    "santos": 128, "santos fc": 128,
    # Europa & Outros
    "manchester city": 50, "man city": 50,
    "manchester united": 33, "man united": 33, "man utd": 33,
    "arsenal": 42, "arsenal fc": 42,
    "liverpool": 40, "liverpool fc": 40,
    "chelsea": 49, "chelsea fc": 49,
    "tottenham": 47, "tottenham hotspur": 47,
    "real madrid": 541,
    "barcelona": 529, "fc barcelona": 529,
    "atletico madrid": 530, "atlético madrid": 530, "atlético de madrid": 530, "atletico de madrid": 530,
    "bayern munich": 157, "bayern munchen": 157, "bayern de munique": 157,
    "borussia dortmund": 165, "bvb": 165,
    "bayer leverkusen": 168,
    "paris saint germain": 85, "psg": 85,
    "juventus": 496,
    "inter milan": 505, "internazionale": 505,
    "ac milan": 489, "milan": 489,
    "benfica": 211, "sl benfica": 211,
    "porto": 212, "fc porto": 212,
    "sporting cp": 228, "sporting": 228, "sporting lisbon": 228,
    "ajax": 194, "afc ajax": 194,
    "psv": 197, "psv eindhoven": 197,
    "feyenoord": 209,
    "boca juniors": 451, "boca": 451,
    "river plate": 435, "river": 435,
    "racing club": 436,
    "independiente": 453,
    "san lorenzo": 460,
    "penarol": 2348, "peñarol": 2348,
    "club nacional": 2356, "nacional montevideo": 2356,
    # Seleções Nacionais (Top Mundial / Copa do Mundo 2026)
    "espanha": 9, "spain": 9,
    "argentina": 26,
    "inglaterra": 10, "england": 10,
    "franca": 2, "frança": 2, "france": 2,
    "noruega": 1090, "norway": 1090,
    "belgica": 1, "bélgica": 1, "belgium": 1,
    "marrocos": 1530, "morocco": 1530,
    "suica": 15, "suíça": 15, "switzerland": 15,
    "mexico": 16, "méxico": 16,
    "colombia": 8, "colômbia": 8,
    "brasil": 6, "brazil": 6,
    "estados unidos": 2384, "usa": 2384, "united states": 2384,
    "portugal": 27,
    "canada": 1533, "canadá": 1533,
    "egito": 32, "egypt": 32,
    "paraguai": 1569, "paraguay": 1569,
    "paises baixos": 1118, "países baixos": 1118, "netherlands": 1118, "holanda": 1118,
    "alemanha": 25, "germany": 25,
    "croacia": 3, "croácia": 3, "croatia": 3,
    "japao": 1534, "japão": 1534, "japan": 1534,
    "suecia": 5, "suécia": 5, "sweden": 5,
    "austria": 775, "áustria": 775,
    "uruguai": 7, "uruguay": 7,
    "italia": 768, "itália": 768, "italy": 768,
    "dinamarca": 1118, "denmark": 1118,
    "turquia": 777, "turkey": 777,
    "wales": 767, "gales": 767, "pais de gales": 767, "país de gales": 767,
}

# Cache em memória para consulta instantânea O(1) de seleções do Mundial
_WORLD_CUP_CACHE_BY_ID = None
_WORLD_CUP_CACHE_BY_NAME = None

def get_world_cup_standings_cache():
    """
    Retorna o cache em memória da tabela world_cup_standings_cache.
    Carrega do MySQL de forma preguiçosa (Lazy Loading) e Cache-First.
    """
    global _WORLD_CUP_CACHE_BY_ID, _WORLD_CUP_CACHE_BY_NAME
    if _WORLD_CUP_CACHE_BY_ID is not None and _WORLD_CUP_CACHE_BY_NAME is not None:
        return _WORLD_CUP_CACHE_BY_ID, _WORLD_CUP_CACHE_BY_NAME

    _WORLD_CUP_CACHE_BY_ID = {}
    _WORLD_CUP_CACHE_BY_NAME = {}

    try:
        import pymysql
        import unicodedata
        conn = pymysql.connect(
            host='127.0.0.1',
            port=23306,
            user='root',
            password='YM11rMrT32xH0E6N',
            database='footballweb',
            connect_timeout=3
        )
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        cursor.execute("SELECT pos, team_name, team_id, stage_reached, efficiency_tier, pedigree_bonus, is_tier1 FROM world_cup_standings_cache WHERE is_latest = 1")
        rows = cursor.fetchall()
        conn.close()

        for r in rows:
            t_id = r.get('team_id')
            t_name = str(r.get('team_name', '')).strip()
            item = {
                'pos': int(r.get('pos', 99)),
                'team_name': t_name,
                'team_id': int(t_id) if t_id is not None else None,
                'stage_reached': r.get('stage_reached', ''),
                'efficiency_tier': r.get('efficiency_tier', ''),
                'pedigree_bonus': float(r.get('pedigree_bonus', 0.0)),
                'is_tier1': bool(r.get('is_tier1', 0))
            }
            if t_id is not None:
                _WORLD_CUP_CACHE_BY_ID[int(t_id)] = item

            # Normalização textual
            norm = unicodedata.normalize('NFKD', t_name.lower()).encode('ASCII', 'ignore').decode('utf-8')
            clean = norm.replace('-', ' ').replace('.', ' ').strip()
            _WORLD_CUP_CACHE_BY_NAME[norm] = item
            _WORLD_CUP_CACHE_BY_NAME[clean] = item

    except Exception:
        pass

    return _WORLD_CUP_CACHE_BY_ID, _WORLD_CUP_CACHE_BY_NAME


def get_world_cup_team_info(team_id: int = None, team_name: str = None) -> dict:
    """
    Recupera informações de classificação e prestígio de mundial da seleção informada.
    """
    cache_id, cache_name = get_world_cup_standings_cache()

    if team_id is not None:
        try:
            tid = int(team_id)
            if tid in cache_id:
                return cache_id[tid]
        except (ValueError, TypeError):
            pass

    if team_name:
        import unicodedata
        raw = str(team_name).lower().strip()
        norm = unicodedata.normalize('NFKD', raw).encode('ASCII', 'ignore').decode('utf-8')
        clean = norm.replace('-', ' ').replace('.', ' ').strip()
        clean = ' '.join(clean.split())

        # 1. Alias direto para ID
        if norm in TIER_1_NAME_TO_ID and TIER_1_NAME_TO_ID[norm] in cache_id:
            return cache_id[TIER_1_NAME_TO_ID[norm]]
        if clean in TIER_1_NAME_TO_ID and TIER_1_NAME_TO_ID[clean] in cache_id:
            return cache_id[TIER_1_NAME_TO_ID[clean]]

        # 2. Busca no cache de nomes
        if norm in cache_name:
            return cache_name[norm]
        if clean in cache_name:
            return cache_name[clean]

    return {}


def get_team_pedigree_bonus(team_id: int = None, team_name: str = None) -> float:
    """
    Retorna o bônus de pedigree de Copa do Mundo da seleção (escala 0.0 a 3.0 pts).
    """
    info = get_world_cup_team_info(team_id=team_id, team_name=team_name)
    return float(info.get('pedigree_bonus', 0.0))


def is_tier_1_elite_club(team_id: int = None, team_name: str = None) -> bool:
    """
    Verifica se a equipe informada pertence ao grupo de elite mundial (Tier 1).
    Consulta primeiro os clubes em TIER_1_ELITE_CLUBS e depois as seleções em world_cup_standings_cache.
    Fallback Secundário: Resolução determinística por nome canônico/alias.
    """
    # 1. Validação de Clubes de Elite por ID
    if team_id is not None:
        try:
            tid = int(team_id)
            if tid in TIER_1_ELITE_CLUBS:
                return True
        except (ValueError, TypeError):
            pass

    # 2. Validação de Seleções da Copa do Mundo no Cache MySQL
    wc_info = get_world_cup_team_info(team_id=team_id, team_name=team_name)
    if wc_info and wc_info.get('is_tier1'):
        return True

    # 3. Fallback Secundário por Nome
    if not team_name:
        return False

    import unicodedata
    raw = str(team_name).lower().strip()
    norm = unicodedata.normalize('NFKD', raw).encode('ASCII', 'ignore').decode('utf-8')
    clean_norm = norm.replace('-', ' ').replace('.', ' ').strip()
    clean_norm = ' '.join(clean_norm.split())

    # Checagem direta por alias normalizado
    if norm in TIER_1_NAME_TO_ID:
        mapped_id = TIER_1_NAME_TO_ID[norm]
        if mapped_id in TIER_1_ELITE_CLUBS:
            return True
        if mapped_id in _WORLD_CUP_CACHE_BY_ID and _WORLD_CUP_CACHE_BY_ID[mapped_id].get('is_tier1'):
            return True

    if clean_norm in TIER_1_NAME_TO_ID:
        mapped_id = TIER_1_NAME_TO_ID[clean_norm]
        if mapped_id in TIER_1_ELITE_CLUBS:
            return True
        if mapped_id in _WORLD_CUP_CACHE_BY_ID and _WORLD_CUP_CACHE_BY_ID[mapped_id].get('is_tier1'):
            return True

    # Checagem exata normalizada com os nomes oficiais de TIER_1_ELITE_CLUBS
    for c_id, c_name in TIER_1_ELITE_CLUBS.items():
        c_norm = unicodedata.normalize('NFKD', c_name.lower().strip()).encode('ASCII', 'ignore').decode('utf-8')
        c_clean = c_norm.replace('-', ' ').replace('.', ' ').strip()
        c_clean = ' '.join(c_clean.split())
        if clean_norm == c_clean or norm == c_norm:
            return True

    return False




