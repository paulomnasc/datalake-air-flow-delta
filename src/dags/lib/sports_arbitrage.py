"""
Módulo de Arbitragem de Apostas Esportivas (Brasileirão Série A e Série B)
Integração LIVE via The Odds API com formatação estrita de 2 casas decimais para Odds e Stakes.
"""

import os
import re
import json
import logging
import difflib
import urllib.request
import pandas as pd
from datetime import datetime, timezone, timedelta

log = logging.getLogger(__name__)

# Chave Padrão The Odds API fornecida pelo usuário
DEFAULT_ODDS_API_KEY = "d2f79607e3832b1f4b3003c14da3d70f"

# Mapeamento e Normalização de Nomes de Times do Brasileirão
TEAM_ALIASES = {
    # Série A
    "INTERNACIONAL": ["INTERNACIONAL", "SC INTERNACIONAL", "INTER", "INT", "INTERNACIONAL RS"],
    "FLAMENGO": ["FLAMENGO", "CR FLAMENGO", "FLAMENGO RJ", "FLA"],
    "PALMEIRAS": ["PALMEIRAS", "SE PALMEIRAS", "PALMEIRAS SP", "PAL"],
    "BOTAFOGO": ["BOTAFOGO", "BOTAFOGO RJ", "BOTAFOGO FR", "BOT"],
    "SAO PAULO": ["SAO PAULO", "SÃO PAULO", "SAO PAULO FC", "SÃO PAULO FC", "SPA"],
    "FLUMINENSE": ["FLUMINENSE", "FLUMINENSE FC", "FLU"],
    "VASCO": ["VASCO", "VASCO DA GAMA", "CR VASCO DA GAMA", "VAS"],
    "CORINTHIANS": ["CORINTHIANS", "SC CORINTHIANS", "CORINTHIANS SP", "COR"],
    "BAHIA": ["BAHIA", "EC BAHIA", "BAH"],
    "CRUZEIRO": ["CRUZEIRO", "CRUZEIRO EC", "CRU"],
    "ATLETICO-MG": ["ATLETICO-MG", "ATLÉTICO-MG", "ATLETICO MG", "ATLÉTICO MINEIRO", "CAM"],
    "GREMIO": ["GREMIO", "GRÊMIO", "GREMIO FBPA", "GRE"],
    "FORTALEZA": ["FORTALEZA", "FORTALEZA EC", "FORTALEZA CE", "FOR"],
    "RED BULL BRAGANTINO": ["RED BULL BRAGANTINO", "BRAGANTINO", "RBB"],
    "JUVENTUDE": ["JUVENTUDE", "EC JUVENTUDE", "JUV"],
    "VITORIA": ["VITORIA", "VITÓRIA", "EC VITÓRIA", "VIT"],
    "CRICIUMA": ["CRICIUMA", "CRICIÚMA", "CRICIÚMA EC", "CRI"],
    "CUIABA": ["CUIABA", "CUIABÁ", "CUIABÁ EC", "CUI"],
    "ATHLETICO-PR": ["ATHLETICO-PR", "ATLÉTICO-PR", "ATHLETICO PARANAENSE", "CAP"],
    "ATLETICO-GO": ["ATLETICO-GO", "ATLÉTICO-GO", "ATLÉTICO GOIANIENSE", "ACG"],
    # Série B & Adicionais
    "SANTOS": ["SANTOS", "SANTOS FC", "SAN"],
    "CEARA": ["CEARA", "CEARÁ", "CEARÁ SC", "CEA"],
    "MIRASSOL": ["MIRASSOL", "MIRASSOL FC", "MIR"],
    "NOVORIZONTINO": ["NOVORIZONTINO", "GE NOVORIZONTINO", "NOV"],
    "SPORT": ["SPORT", "SPORT RECIFE", "SPORT CLUB DO RECIFE", "SPT"],
    "AMERICA-MG": ["AMERICA-MG", "AMÉRICA-MG", "AMÉRICA MINEIRO", "AME"],
    "GOIAS": ["GOIAS", "GOIÁS", "GOIÁS EC", "GOI"],
    "VILA NOVA": ["VILA NOVA", "VILA NOVA FC", "VIL"],
    "CORITIBA": ["CORITIBA", "CORITIBA FBC", "CFC"],
    "AVAI": ["AVAI", "AVAÍ", "AVAÍ FC", "AVA"],
    "PAYSANDU": ["PAYSANDU", "PAYANDU", "PAYSANDU SC", "PAY"],
    "PONTE PRETA": ["PONTE PRETA", "AA PONTE PRETA", "PON"],
    "CRB": ["CRB", "CLUBE DE REGATAS BRASIL", "CRB AL"],
    "GUARANI": ["GUARANI", "GUARANI FC", "GUA"],
    "OPERARIO-PR": ["OPERARIO-PR", "OPERÁRIO-PR", "OPERÁRIO FEC", "OPE"],
    "CHAPECOENSE": ["CHAPECOENSE", "ASSOCIACAO CHAPECOENSE", "CHA"],
    "BOTAFOGO-SP": ["BOTAFOGO-SP", "BOTAFOGO SP", "BSO"],
    "BRUSQUE": ["BRUSQUE", "BRUSQUE FC", "BRU"],
    "ITUANO": ["ITUANO", "ITUANO FC", "ITU"],
    "AMAZONAS": ["AMAZONAS", "AMAZONAS FC", "AMA"],
    "REMO": ["REMO", "CLUBE DO REMO", "REM"],
}

# Mapeamento e Normalização de Casas de Apostas (Bookmakers)
BOOKMAKER_ALIASES = {
    "BETNACIONAL": ["BETNACIONAL", "BETNACIONAL_BR", "BET NACIONAL", "BETNACIONAL BR"],
    "BET365": ["BET365", "BET365_EU", "BET 365", "BET365 UK", "BET365 BR"],
    "BETANO": ["BETANO", "BETANO_BR", "STOIXIMAN", "BETANO BR", "BETANO EU"],
    "SPORTINGBET": ["SPORTINGBET", "SPORTINGBET_BR", "SPORTING BET", "SPORTINGBET BR"],
    "SUPERBET": ["SUPERBET", "SUPERBET_BR", "SUPER BET", "SUPERBET BR"],
    "KTO": ["KTO", "KTO_BR", "KTO BR"],
    "NOVIBET": ["NOVIBET", "NOVIBET_BR", "NOVIBET BR"],
    "BETFAIR": ["BETFAIR", "BETFAIR_EXCHANGE", "BETFAIR_SPORTSBOOK", "BETFAIR BR"],
    "ESTRELABET": ["ESTRELABET", "ESTRELA BET", "ESTRELABET BR"],
    "PINNACLE": ["PINNACLE", "PINNACLE_SPORTS"],
    "1XBET": ["1XBET", "ONE X BET"],
    "BWIN": ["BWIN"],
    "UNIBET": ["UNIBET"],
}

def normalize_bookmaker_name(name: str) -> str:
    """Normaliza o nome da casa de apostas para uma chave padronizada."""
    if not name:
        return "OUTROS"
    clean_name = re.sub(r'[^A-Z0-9\s_-]', '', name.upper()).strip()
    
    for standard_name, aliases in BOOKMAKER_ALIASES.items():
        for alias in aliases:
            if alias == clean_name or difflib.SequenceMatcher(None, alias, clean_name).ratio() > 0.85:
                return standard_name
    return clean_name.title()

def normalize_team_name(name: str) -> str:
    """Normaliza o nome do time para uma chave padronizada."""
    if not name:
        return "DESCONHECIDO"
    clean_name = re.sub(r'[^A-Z0-9\s-]', '', name.upper()).strip()
    
    for standard_name, aliases in TEAM_ALIASES.items():
        for alias in aliases:
            if alias == clean_name or difflib.SequenceMatcher(None, alias, clean_name).ratio() > 0.85:
                return standard_name
    return clean_name

def calculate_surebet(odd_casa: float, odd_empate: float, odd_visitante: float, banca_total: float = 1000.0):
    """
    Calcula se existe arbitragem (Surebet) e determina a distribuição da banca com precisão de 2 casas decimais.
    Fórmula de Arbitragem: S = (1/Odd1) + (1/OddX) + (1/Odd2)
    """
    if any(o <= 1.0 for o in [odd_casa, odd_empate, odd_visitante]):
        return None
    
    prob_casa = 1.0 / odd_casa
    prob_empate = 1.0 / odd_empate
    prob_visitante = 1.0 / odd_visitante
    
    soma_probabilidades = prob_casa + prob_empate + prob_visitante
    is_surebet = soma_probabilidades < 1.0
    
    lucro_percentual = ((1.0 / soma_probabilidades) - 1.0) * 100.0
    retorno_esperado = banca_total / soma_probabilidades
    lucro_estimado = retorno_esperado - banca_total
    
    stake_casa = banca_total / (soma_probabilidades * odd_casa)
    stake_empate = banca_total / (soma_probabilidades * odd_empate)
    stake_visitante = banca_total / (soma_probabilidades * odd_visitante)
    
    return {
        "is_surebet": is_surebet,
        "soma_probabilidades": round(soma_probabilidades, 4),
        "lucro_percentual": round(lucro_percentual, 2),
        "lucro_estimado": round(lucro_estimado, 2),
        "retorno_esperado": round(retorno_esperado, 2),
        "stake_casa": round(stake_casa, 2),
        "stake_empate": round(stake_empate, 2),
        "stake_visitante": round(stake_visitante, 2),
    }

def fetch_live_odds_from_api(api_key: str, casas_permitidas: list = None):
    """
    Busca odds AO VIVO das casas de apostas para o Brasileirão Série A e Série B via The Odds API.
    """
    sports_to_fetch = [
        ("soccer_brazil_campeonato", "Brasileirão Série A"),
        ("soccer_brazil_serie_b", "Brasileirão Série B")
    ]
    
    parsed_matches = []
    
    for sport_key, league_name in sports_to_fetch:
        log.info(f"[LIVE-ODDS] Buscando partidas ao vivo de {league_name} ({sport_key})...")
        url = f"https://api.the-odds-api.com/v4/sports/{sport_key}/odds/?apiKey={api_key}&regions=us,uk,eu,au&markets=h2h"
        
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
            with urllib.request.urlopen(req, timeout=10) as resp:
                events = json.loads(resp.read().decode('utf-8'))
                log.info(f"[LIVE-ODDS] Sucesso! Encontradas {len(events)} partidas para {league_name}.")
                
                for ev in events:
                    home_team = ev.get('home_team')
                    away_team = ev.get('away_team')
                    commence_time = ev.get('commence_time', '')
                    
                    try:
                        dt = datetime.fromisoformat(commence_time.replace('Z', '+00:00'))
                        dt_brt = dt.astimezone(timezone(timedelta(hours=-3)))
                        date_str = dt_brt.strftime("%d/%m %H:%M")
                    except Exception:
                        date_str = commence_time
                        
                    bookmakers = ev.get('bookmakers', [])
                    odds_dict = {}
                    
                    for bm in bookmakers:
                        bm_raw_name = bm.get('title', bm.get('key'))
                        bm_norm_name = normalize_bookmaker_name(bm_raw_name)
                        
                        markets = bm.get('markets', [])
                        
                        for m in markets:
                            if m.get('key') == 'h2h':
                                outcomes = m.get('outcomes', [])
                                odd_h, odd_d, odd_a = 0.0, 0.0, 0.0
                                for out in outcomes:
                                    name = out.get('name')
                                    price = float(out.get('price', 0.0))
                                    if name == home_team:
                                        odd_h = price
                                    elif name == away_team:
                                        odd_a = price
                                    elif name.lower() in ['draw', 'empate']:
                                        odd_d = price
                                        
                                if odd_h > 0 and odd_d > 0 and odd_a > 0:
                                    odds_dict[bm_norm_name] = {
                                        "casa": round(odd_h, 2),
                                        "empate": round(odd_d, 2),
                                        "visitante": round(odd_a, 2),
                                        "nome_original": bm_raw_name
                                    }
                                    
                    if odds_dict:
                        parsed_matches.append({
                            "campeonato": league_name,
                            "time_casa": home_team,
                            "time_visitante": away_team,
                            "data_jogo": date_str,
                            "odds": odds_dict
                        })
        except Exception as e:
            log.error(f"[LIVE-ODDS] Erro ao consultar {sport_key}: {e}")
            
    return parsed_matches

def fetch_bookmaker_odds(casas_permitidas: list = None):
    """
    Recupera a chave The Odds API (variável do Airflow / env / chave padrão) e consulta odds ao vivo.
    """
    odds_api_key = os.environ.get('ODDS_API_KEY')
    
    if not odds_api_key:
        try:
            from airflow.models import Variable
            odds_api_key = Variable.get("ODDS_API_KEY", default_var=DEFAULT_ODDS_API_KEY)
        except Exception:
            odds_api_key = DEFAULT_ODDS_API_KEY
            
    live_data = fetch_live_odds_from_api(odds_api_key, casas_permitidas=casas_permitidas)
    if live_data:
        return live_data
        
    log.warning("[ODDS] Não foi possível obter dados ao vivo da API. Carregando dados de contingência.")
    return []

def process_arbitrage_report(banca_total: float = 1000.0, casas_usuario: list = None, apenas_casas_usuario: bool = False) -> pd.DataFrame:
    """Processa todas as partidas ao vivo e calcula o relatório de arbitragem com formato decimal rigoroso (2 casas)."""
    
    casas_normalizadas_usuario = set()
    if casas_usuario:
        if isinstance(casas_usuario, str):
            casas_usuario = [c.strip() for c in casas_usuario.split(',') if c.strip()]
        for c in casas_usuario:
            casas_normalizadas_usuario.add(normalize_bookmaker_name(c))
            
    games = fetch_bookmaker_odds(casas_permitidas=list(casas_normalizadas_usuario) if casas_normalizadas_usuario else None)
    report_rows = []
    
    for game in games:
        campeonato = game["campeonato"]
        time_casa = normalize_team_name(game["time_casa"])
        time_visitante = normalize_team_name(game["time_visitante"])
        data_jogo = game["data_jogo"]
        odds = game["odds"]
        
        melhor_odd_casa = 0.0
        melhor_casa_odd1 = ""
        
        melhor_odd_empate = 0.0
        melhor_casa_oddX = ""
        
        melhor_odd_visitante = 0.0
        melhor_casa_odd2 = ""
        
        for casa_nome, cota in odds.items():
            if apenas_casas_usuario and casas_normalizadas_usuario and (casa_nome not in casas_normalizadas_usuario):
                continue
                
            if cota["casa"] > melhor_odd_casa:
                melhor_odd_casa = round(cota["casa"], 2)
                melhor_casa_odd1 = casa_nome
                
            if cota["empate"] > melhor_odd_empate:
                melhor_odd_empate = round(cota["empate"], 2)
                melhor_casa_oddX = casa_nome
                
            if cota["visitante"] > melhor_odd_visitante:
                melhor_odd_visitante = round(cota["visitante"], 2)
                melhor_casa_odd2 = casa_nome
                
        calc = calculate_surebet(melhor_odd_casa, melhor_odd_empate, melhor_odd_visitante, banca_total)
        
        if calc:
            row = {
                "Campeonato": campeonato,
                "Data_Jogo": data_jogo,
                "Time_Casa": time_casa,
                "Time_Visitante": time_visitante,
                "Casa_Odd_1": melhor_casa_odd1,
                "Odd_1": round(melhor_odd_casa, 2),
                "Stake_Odd_1_R$": round(calc["stake_casa"], 2),
                "Casa_Odd_X": melhor_casa_oddX,
                "Odd_X": round(melhor_odd_empate, 2),
                "Stake_Odd_X_R$": round(calc["stake_empate"], 2),
                "Casa_Odd_2": melhor_casa_odd2,
                "Odd_2": round(melhor_odd_visitante, 2),
                "Stake_Odd_2_R$": round(calc["stake_visitante"], 2),
                "Indice_Arbitragem": round(calc["soma_probabilidades"], 4),
                "Eh_Surebet": "SIM" if calc["is_surebet"] else "NAO",
                "Lucro_Percentual_%": round(calc["lucro_percentual"], 2),
                "Lucro_Estimado_R$": round(calc["lucro_estimado"], 2),
                "Banca_Total_R$": round(banca_total, 2)
            }
            report_rows.append(row)
            
    df = pd.DataFrame(report_rows)
    if not df.empty and "Lucro_Percentual_%" in df.columns:
        df = df.sort_values(by="Lucro_Percentual_%", ascending=False)
    return df

