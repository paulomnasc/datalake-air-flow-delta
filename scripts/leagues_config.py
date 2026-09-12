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
    75: "Copa do Nordeste (Brasil)",
    642: "Supercopa do Brasil (Brasil)",
    39: "Premier League (Inglaterra)",
    45: "FA Cup (Inglaterra)",
    48: "EFL Cup (Inglaterra)",
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
    848: "Conference League (Europa)",
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
    'liga profesional', 'primera division', 'copa argentina',
    'super league 1', 'super league', 'superliga',
    'champions league', 'europa league', 'conference league',
    'libertadores', 'copa sudamericana', 'sudamericana', 'recopa',
    'leagues cup',
    'liga mx',
    'allsvenskan', 'eliteserien',
    'j1 league', 'j-league', 'j.league',
    'k league', 'k-league', 'k league 1',
    'veikkausliiga', 'ekstraklasa', 'czech first league',
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

    # 3. Bloqueia divisões secundárias genéricas (exceto Série B do Brasil - ID 72)
    if any(tier in l_name_low for tier in ['2. liga', '2. bundesliga', 'segunda division', 'segunda división', 'serie c', 'serie d', 'championship', 'league one', 'league two']):
        try:
            lid = int(league_id) if league_id is not None else None
            if lid != 72:
                return False
        except (ValueError, TypeError):
            return False

    # 4. Validação primária por ID Numérico Oficial (O(1))
    if league_id is not None:
        try:
            lid = int(league_id)
            if lid in ALLOWED_LEAGUE_IDS:
                return True
            else:
                return False
        except (ValueError, TypeError):
            pass

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


def is_tier_1_elite_club(team_id: int = None, team_name: str = None) -> bool:
    """
    Verifica se a equipe informada pertence ao grupo de elite mundial (Tier 1).
    Prioridade Absoluta: Consulta o team_id oficial no dicionário canônico TIER_1_ELITE_CLUBS.
    Fallback Secundário: Correspondência estrita de nome canônico apenas se team_id for nulo/ausente.
    """
    # 1. Validação por ID oficial (100% determinística e imutável)
    if team_id is not None:
        try:
            tid = int(team_id)
            if tid in TIER_1_ELITE_CLUBS:
                return True
            # Se um team_id numérico válido foi fornecido e NÃO está no dicionário Tier 1,
            # ele categoricamente NÃO é Tier 1 (evita falso positivo por homônimo em string)
            return False
        except (ValueError, TypeError):
            pass

    # 2. Fallback Secundário por Nome (Apenas para registros legados onde team_id é nulo)
    if not team_name:
        return False

    import unicodedata
    raw = team_name.lower().strip()
    norm = unicodedata.normalize('NFKD', raw).encode('ASCII', 'ignore').decode('utf-8')

    # Desqualifica homônimos conhecidos fora do Tier 1 europeu/sul-americano
    disqualified_homonyms = [
        'guayaquil', 'sc', 'montevideo', 'sarandi', 'gijon', 'turku', 'limeira',
        'kansas', 'san jose', 'khalsa', 'miami', 'bogota', 'escaldes', 'intercity', 'laguna'
    ]
    if any(dh in norm for dh in disqualified_homonyms) and 'manchester city' not in norm:
        return False

    for c_id, c_name in TIER_1_ELITE_CLUBS.items():
        c_norm = unicodedata.normalize('NFKD', c_name.lower().strip()).encode('ASCII', 'ignore').decode('utf-8')
        if norm == c_norm or f" {c_norm} " in f" {norm} ":
            return True
        if len(c_norm) >= 6 and c_norm in norm:
            return True

    return False


