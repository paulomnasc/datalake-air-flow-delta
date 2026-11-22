#!/usr/bin/env python3
"""
scripts/cloudbet_matcher.py

Módulo Canônico de Mapeamento de Eventos e Extração de Mercados da Cloudbet.
FootballWeb Pipeline - Automação 100% Robotizada.

Responsabilidades:
1. Buscar o catálogo diário de eventos de futebol da Cloudbet (/pub/v2/odds/fixtures?sport=soccer&date=YYYY-MM-DD);
2. Mapear partidas locais do MySQL (fixtures_trends) para os eventos correspondentes da Cloudbet com algoritmo de correspondência fonética/textual (Fuzzy Matching);
3. Extrair cotações ativas e líquidas de Handicap Asiático, Total de Gols e 1X2;
4. Fornecer os marketUrls canônicos para emissão automatizada de ordens no cloudbet_client.py.
"""

import os
import sys
import re
import time
import unicodedata
from datetime import datetime, timedelta
from typing import Dict, Any, Optional, List, Tuple
from difflib import SequenceMatcher

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection
from cloudbet_client import CloudbetClient

CANONICAL_ALIASES: Dict[str, str] = {
    'athletic club': 'athletic bilbao',
    'athletic club w': 'athletic bilbao',
    'athletic bilbao w': 'athletic bilbao',
    'barcelona w': 'barcelona',
    'real madrid w': 'real madrid',
    'arsenal w': 'arsenal',
    'chelsea w': 'chelsea',
    'manchester city w': 'manchester city',
    'manchester united w': 'manchester united',
    'lyon w': 'olympique lyonnais',
    'vfl wolfsburg w': 'wolfsburg',
    'wolfsburg w': 'wolfsburg',
    'rb leipzig w': 'rb leipzig',
    'bayern munich w': 'bayern munich',
    'inter': 'internazionale',
    'inter milan': 'internazionale',
    'sporting cp': 'sporting lisbon',
    'sporting lisboa': 'sporting lisbon',
    'paris sg': 'psg',
    'paris saint germain': 'psg',
}


def normalize_team_name(name: str) -> str:
    """
    Higieniza e normaliza o nome de um time de futebol removendo acentos,
    pontuações, sufixos comuns de clubes e espaços redundantes.
    Aplica dicionário de aliases canônicos para convergência de mercado.
    """
    if not name:
        return ""
    
    # 1. Remove acentuação
    nfkd = unicodedata.normalize('NFKD', str(name))
    cleaned = "".join([c for c in nfkd if not unicodedata.combining(c)]).lower()
    
    # 2. Converte '&' para 'and'
    cleaned = cleaned.replace('&', 'and')
    
    # 3. Remove caracteres especiais
    cleaned = re.sub(r'[^a-z0-9\s]', ' ', cleaned)
    cleaned = " ".join(cleaned.split())

    # 4. Aplica equivalências canônicas de mercado
    for alias_k, alias_v in CANONICAL_ALIASES.items():
        if cleaned == alias_k or cleaned.startswith(alias_k + ' ') or cleaned.endswith(' ' + alias_k):
            cleaned = cleaned.replace(alias_k, alias_v)
            break
    
    # 5. Remove sufixos e ruídos corporativos comuns de clubes
    noise_words = {
        'fc', 'cf', 'ec', 'sc', 'ac', 'fk', 'sk', 'afc', 'cd', 'ca', 'club', 
        'clube', 'futebol', 'football', 'de', 'do', 'da', 'united', 'city', 
        'town', 'athletic', 'atletico', 'sporting', 'real'
    }
    tokens = [t for t in cleaned.split() if t not in noise_words and len(t) > 1]
    
    if not tokens:
        # Se todos os tokens foram suprimidos, preserva o limpo sem pontuação
        tokens = cleaned.split()
        
    return " ".join(tokens).strip()


def calculate_similarity(s1: str, s2: str) -> float:
    """
    Calcula a similaridade textual entre dois nomes normalizados.
    """
    norm1 = normalize_team_name(s1)
    norm2 = normalize_team_name(s2)
    
    if not norm1 or not norm2:
        return 0.0
    
    if norm1 == norm2:
        return 1.0
        
    # Se um for substring perfeita do outro
    if norm1 in norm2 or norm2 in norm1:
        return 0.90
        
    return SequenceMatcher(None, norm1, norm2).ratio()


class CloudbetMatcher:
    """
    Mecanismo de reconciliação de eventos e precificação em tempo real da Cloudbet.
    """
    
    def __init__(self, client: Optional[CloudbetClient] = None):
        self.client = client or CloudbetClient()
        self.fixtures_cache: Dict[str, List[Dict[str, Any]]] = {}
        self.live_fixtures_cache: Dict[str, List[Dict[str, Any]]] = {}
        self.live_cache_ts: float = 0.0
        self.odds_cache: Dict[int, Dict[str, Any]] = {}

    def fetch_daily_fixtures(self, date_str: str) -> List[Dict[str, Any]]:
        """
        Consulta e armazena em cache todos os eventos de futebol da Cloudbet para uma data (YYYY-MM-DD).
        """
        if date_str in self.fixtures_cache:
            return self.fixtures_cache[date_str]
            
        endpoint = f"/pub/v2/odds/fixtures?sport=soccer&date={date_str}"
        res = self.client._request("GET", endpoint)
        
        events_list: List[Dict[str, Any]] = []
        competitions = res.get("competitions", []) if isinstance(res, dict) else []
        
        for comp in competitions:
            comp_name = comp.get("name") or comp.get("key", "")
            for ev in comp.get("events", []):
                ev_st = ev.get("status", "")
                if ev_st in ("PRE_TRADING", "TRADING", "TRADING_LIVE") or ev_st not in ("RESULTED", "CLOSED"):
                    ev["competition_name"] = comp_name
                    events_list.append(ev)
                
        self.fixtures_cache[date_str] = events_list
        return events_list

    def fetch_live_fixtures(self, date_str: Optional[str] = None) -> List[Dict[str, Any]]:
        """
        Consulta e armazena em cache curto (15s) os eventos ao vivo (TRADING_LIVE) na Cloudbet
        via endpoint /pub/v2/odds/fixtures?sport=soccer&date={date_str}&live=true.
        """
        if not date_str:
            date_str = datetime.utcnow().strftime('%Y-%m-%d')

        now_ts = time.time()
        if date_str in self.live_fixtures_cache and (now_ts - self.live_cache_ts) < 15.0:
            return self.live_fixtures_cache[date_str]

        endpoint = f"/pub/v2/odds/fixtures?sport=soccer&date={date_str}&live=true"
        res = self.client._request("GET", endpoint)

        events_list: List[Dict[str, Any]] = []
        competitions = res.get("competitions", []) if isinstance(res, dict) else []

        for comp in competitions:
            comp_name = comp.get("name") or comp.get("key", "")
            for ev in comp.get("events", []):
                if ev.get("status") == "TRADING_LIVE":
                    ev["competition_name"] = comp_name
                    events_list.append(ev)

        self.live_fixtures_cache[date_str] = events_list
        self.live_cache_ts = now_ts
        return events_list

    def find_matching_event(
        self,
        home_team: str,
        away_team: str,
        fixture_date: Optional[datetime] = None,
        threshold: float = 0.65,
        is_live: bool = False
    ) -> Optional[Dict[str, Any]]:
        """
        Localiza o evento correspondente na Cloudbet para uma partida do MySQL.
        Se is_live for True, pesquisa com prioridade máxima no catálogo de eventos ao vivo (live=true).
        Pesquisa na data exata e datas adjacentes (+/- 1 dia) para acomodar fusos horários UTC.
        """
        if fixture_date is None:
            fixture_date = datetime.now()
        elif isinstance(fixture_date, str):
            try:
                fixture_date = datetime.fromisoformat(fixture_date.replace('Z', ''))
            except Exception:
                fixture_date = datetime.now()

        target_dates = [
            fixture_date.strftime('%Y-%m-%d'),
            (fixture_date - timedelta(days=1)).strftime('%Y-%m-%d'),
            (fixture_date + timedelta(days=1)).strftime('%Y-%m-%d')
        ]
        
        best_match = None
        best_score = 0.0

        # 1. Se a partida estiver ao vivo ou em janela in-play, busca prioritariamente no feed live=true
        if is_live:
            for d_str in target_dates:
                live_events = self.fetch_live_fixtures(d_str)
                for ev in live_events:
                    ev_status = ev.get("status", "")
                    ev_name = ev.get("name", "")
                    ev_home = ev.get("home", {}).get("name") if ev.get("home") else ""
                    ev_away = ev.get("away", {}).get("name") if ev.get("away") else ""

                    if not ev_home and ' v ' in ev_name:
                        parts = ev_name.split(' v ', 1)
                        ev_home, ev_away = parts[0], parts[1]
                    elif not ev_home and ' vs ' in ev_name:
                        parts = ev_name.split(' vs ', 1)
                        ev_home, ev_away = parts[0], parts[1]

                    score_home = calculate_similarity(home_team, ev_home)
                    score_away = calculate_similarity(away_team, ev_away)
                    avg_score = (score_home + score_away) / 2.0

                    if score_home >= 0.60 and score_away >= 0.60:
                        avg_score = max(avg_score, (score_home + score_away) * 0.55)

                    # Bônus para eventos que estejam com status TRADING_LIVE
                    if ev_status == "TRADING_LIVE":
                        avg_score = min(1.0, avg_score + 0.05)

                    if avg_score > best_score and avg_score >= threshold:
                        best_score = avg_score
                        best_match = {
                            "event_id": ev.get("id"),
                            "event_name": ev_name,
                            "home_team_cloudbet": ev_home,
                            "away_team_cloudbet": ev_away,
                            "cutoff_time": ev.get("cutoffTime"),
                            "status": ev_status,
                            "is_live": True,
                            "match_score": round(avg_score, 3)
                        }

            if best_match and best_score >= threshold:
                return best_match
        
        # 2. Busca no catálogo diário pré-jogo
        for d_str in target_dates:
            events = self.fetch_daily_fixtures(d_str)
            for ev in events:
                ev_name = ev.get("name", "")
                ev_home = ev.get("home", {}).get("name") if ev.get("home") else ""
                ev_away = ev.get("away", {}).get("name") if ev.get("away") else ""
                
                # Se não houver campos home/away estruturados, divide pelo separador ' v ' ou ' vs '
                if not ev_home and ' v ' in ev_name:
                    parts = ev_name.split(' v ', 1)
                    ev_home, ev_away = parts[0], parts[1]
                elif not ev_home and ' vs ' in ev_name:
                    parts = ev_name.split(' vs ', 1)
                    ev_home, ev_away = parts[0], parts[1]
                    
                score_home = calculate_similarity(home_team, ev_home)
                score_away = calculate_similarity(away_team, ev_away)
                avg_score = (score_home + score_away) / 2.0
                
                # Bônus se ambos superarem 0.60
                if score_home >= 0.60 and score_away >= 0.60:
                    avg_score = max(avg_score, (score_home + score_away) * 0.55)
                    
                if avg_score > best_score and avg_score >= threshold:
                    best_score = avg_score
                    best_match = {
                        "event_id": ev.get("id"),
                        "event_name": ev_name,
                        "home_team_cloudbet": ev_home,
                        "away_team_cloudbet": ev_away,
                        "cutoff_time": ev.get("cutoffTime"),
                        "status": ev.get("status"),
                        "is_live": False,
                        "match_score": round(avg_score, 3)
                    }
                    
        return best_match

    def get_event_markets(self, event_id: int, use_cache: bool = True) -> Dict[str, Any]:
        """
        Retorna as linhas ativas e líquidas de Handicap Asiático, Total de Gols e 1X2 para um event_id.
        Se use_cache=False, força requisição direta à API para obter odds em tempo real in-play.
        """
        if use_cache and event_id in self.odds_cache:
            return self.odds_cache[event_id]
            
        odds_raw = self.client.get_event_odds(event_id)
        markets_raw = odds_raw.get("markets", {}) if isinstance(odds_raw, dict) else {}
        
        parsed = {
            "event_id": event_id,
            "status": odds_raw.get("status"),
            "asian_handicap": {},
            "total_goals": {},
            "match_odds": {},
            "draw_no_bet": {},
            "cards": {}
        }
        
        # 1. Handicap Asiático (FT) Puro
        ah_market = markets_raw.get("soccer.asian_handicap", {})
        ah_sub = ah_market.get("submarkets", {}).get("period=ft", {})
        for sel in ah_sub.get("selections", []):
            if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                # Exemplo params: 'handicap=-0.25' ou 'handicap=1.5'
                params = sel.get("params", "")
                m = re.search(r'handicap=([+-]?\d+(?:\.\d+)?)', params)
                if m:
                    hcap_val = float(m.group(1))
                    outcome = sel.get("outcome") # 'home' ou 'away'
                    
                    if hcap_val not in parsed["asian_handicap"]:
                        parsed["asian_handicap"][hcap_val] = {}
                        
                    parsed["asian_handicap"][hcap_val][outcome] = {
                        "price": float(sel.get("price")),
                        "market_url": sel.get("marketUrl"),
                        "min_stake": float(sel.get("minStake") or 0.1),
                        "max_stake": float(sel.get("maxStake") or 0.0)
                    }

        # 1.1 Contingência de Linhas Equivalentes de AH se soccer.asian_handicap estiver desativado/vazio
        if not parsed["asian_handicap"]:
            # A) Linha 0.0 AH via soccer.draw_no_bet (DNB = 0.0 AH)
            dnb_market = markets_raw.get("soccer.draw_no_bet", {})
            for sub_k, sub_v in dnb_market.get("submarkets", {}).items():
                for sel in sub_v.get("selections", []):
                    if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                        outcome = sel.get("outcome") # 'home' ou 'away'
                        if 0.0 not in parsed["asian_handicap"]:
                            parsed["asian_handicap"][0.0] = {}
                        parsed["asian_handicap"][0.0][outcome] = {
                            "price": float(sel.get("price")),
                            "market_url": sel.get("marketUrl"),
                            "min_stake": float(sel.get("minStake") or 0.1),
                            "max_stake": float(sel.get("maxStake") or 0.0)
                        }

            # B) Linha +0.5 AH via soccer.double_chance (Dupla Chance 1X = +0.5 Home / X2 = +0.5 Away)
            dc_market = markets_raw.get("soccer.double_chance", {})
            for sub_k, sub_v in dc_market.get("submarkets", {}).items():
                for sel in sub_v.get("selections", []):
                    if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                        outcome_dc = sel.get("outcome")
                        if outcome_dc == 'home_draw':
                            if 0.5 not in parsed["asian_handicap"]:
                                parsed["asian_handicap"][0.5] = {}
                            parsed["asian_handicap"][0.5]['home'] = {
                                "price": float(sel.get("price")),
                                "market_url": sel.get("marketUrl"),
                                "min_stake": float(sel.get("minStake") or 0.1),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }
                        elif outcome_dc == 'draw_away':
                            if -0.5 not in parsed["asian_handicap"]:
                                parsed["asian_handicap"][-0.5] = {}
                            parsed["asian_handicap"][-0.5]['away'] = {
                                "price": float(sel.get("price")),
                                "market_url": sel.get("marketUrl"),
                                "min_stake": float(sel.get("minStake") or 0.1),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }

            # C) Linha -0.5 AH via soccer.match_odds (1X2 Home Win = -0.5 Home / Away Win = -0.5 Away)
            mo_market = markets_raw.get("soccer.match_odds", {})
            for sub_k, sub_v in mo_market.get("submarkets", {}).items():
                for sel in sub_v.get("selections", []):
                    if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                        outcome_mo = sel.get("outcome")
                        if outcome_mo == 'home':
                            if -0.5 not in parsed["asian_handicap"]:
                                parsed["asian_handicap"][-0.5] = {}
                            parsed["asian_handicap"][-0.5]['home'] = {
                                "price": float(sel.get("price")),
                                "market_url": sel.get("marketUrl"),
                                "min_stake": float(sel.get("minStake") or 0.1),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }
                        elif outcome_mo == 'away':
                            if 0.5 not in parsed["asian_handicap"]:
                                parsed["asian_handicap"][0.5] = {}
                            parsed["asian_handicap"][0.5]['away'] = {
                                "price": float(sel.get("price")),
                                "market_url": sel.get("marketUrl"),
                                "min_stake": float(sel.get("minStake") or 0.1),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }
                    
        # 2. Total de Gols (Over / Under FT)
        tg_market = markets_raw.get("soccer.total_goals", {})
        tg_sub = tg_market.get("submarkets", {}).get("period=ft", {})
        for sel in tg_sub.get("selections", []):
            if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                params = sel.get("params", "")
                m = re.search(r'total=(\d+(?:\.\d+)?)', params)
                if m:
                    total_val = float(m.group(1))
                    outcome = sel.get("outcome") # 'over' ou 'under'
                    
                    if total_val not in parsed["total_goals"]:
                        parsed["total_goals"][total_val] = {}
                        
                    parsed["total_goals"][total_val][outcome] = {
                        "price": float(sel.get("price")),
                        "market_url": sel.get("marketUrl"),
                        "min_stake": float(sel.get("minStake") or 0.1),
                        "max_stake": float(sel.get("maxStake") or 0.0)
                    }
                    
        # 3. 1X2 Match Odds (FT)
        mo_market = markets_raw.get("soccer.match_odds", {})
        mo_sub = mo_market.get("submarkets", {}).get("period=ft", {})
        for sel in mo_sub.get("selections", []):
            if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                outcome = sel.get("outcome") # 'home', 'draw', 'away'
                parsed["match_odds"][outcome] = {
                    "price": float(sel.get("price")),
                    "market_url": sel.get("marketUrl"),
                    "min_stake": float(sel.get("minStake") or 0.1),
                    "max_stake": float(sel.get("maxStake") or 0.0)
                }

        # 4. Draw No Bet (Empate Anula FT)
        dnb_market = markets_raw.get("soccer.draw_no_bet", {})
        dnb_sub = dnb_market.get("submarkets", {}).get("period=ft", {})
        for sel in dnb_sub.get("selections", []):
            if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                outcome = sel.get("outcome") # 'home', 'away'
                parsed["draw_no_bet"][outcome] = {
                    "price": float(sel.get("price")),
                    "market_url": sel.get("marketUrl"),
                    "min_stake": float(sel.get("minStake") or 0.1),
                    "max_stake": float(sel.get("maxStake") or 0.0)
                }

        # 5. Which Team Wins the Rest of the Match (In-Play Live 1X2 / DNB Equivalent)
        parsed["which_team_wins"] = {}
        rest_market = markets_raw.get("soccer.which_team_wins_the_rest_of_the_match", {})
        for sub_k, sub_v in rest_market.get("submarkets", {}).items():
            for sel in sub_v.get("selections", []):
                if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                    outcome = sel.get("outcome") # 'home', 'draw', 'away'
                    if outcome not in parsed["which_team_wins"]:
                        parsed["which_team_wins"][outcome] = {
                            "price": float(sel.get("price")),
                            "market_url": sel.get("marketUrl"),
                            "min_stake": float(sel.get("minStake") or 0.1),
                            "max_stake": float(sel.get("maxStake") or 0.0)
                        }

        # 6. Total Bookings e Total Booking Points / Cartões (Under / Over)
        parsed["total_bookings"] = {}
        for m_card_key in ("soccer.total_booking_points", "soccer.total_bookings"):
            c_market = markets_raw.get(m_card_key, {})
            for sub_k, sub_v in c_market.get("submarkets", {}).items():
                for sel in sub_v.get("selections", []):
                    if sel.get("status") == "SELECTION_ENABLED" and float(sel.get("price") or 0) > 1.0:
                        params = sel.get("params", "")
                        m_b = re.search(r'total=(\d+(?:\.\d+)?)', params)
                        if m_b:
                            raw_val = float(m_b.group(1))
                            # Normalização: Booking Points (55.5 pts -> 5.5 cartões, 65.5 pts -> 6.5 cartões)
                            line_cards = (raw_val / 10.0) if raw_val > 15.0 else raw_val
                            outcome = sel.get("outcome") # 'over' ou 'under'
                            
                            if line_cards not in parsed["cards"]:
                                parsed["cards"][line_cards] = {}
                            parsed["cards"][line_cards][outcome] = {
                                "price": float(sel.get("price")),
                                "market_url": sel.get("marketUrl"),
                                "min_stake": float(sel.get("minStake") or 0.1),
                                "max_stake": float(sel.get("maxStake") or 0.0),
                                "source_market": m_card_key
                            }
                            # Preserva compatibilidade com total_bookings
                            if line_cards not in parsed["total_bookings"]:
                                parsed["total_bookings"][line_cards] = {}
                            parsed["total_bookings"][line_cards][outcome] = parsed["cards"][line_cards][outcome]
                
        if use_cache:
            self.odds_cache[event_id] = parsed
        return parsed


# =========================================================================
# CLI DE TESTE E RECONCILIAÇÃO
# =========================================================================

if __name__ == "__main__":
    import argparse
    
    parser = argparse.ArgumentParser(description="Cloudbet Event Matcher & Odds Extractor")
    parser.add_argument("--test_today", action="store_true", help="Testa o matching dos jogos de hoje no MySQL contra a Cloudbet")
    parser.add_argument("--fixture_id", type=int, help="Mapeia uma partida específica pelo ID do MySQL")
    args = parser.parse_args()
    
    matcher = CloudbetMatcher()
    conn = get_db_connection()
    cursor = conn.cursor()
    
    print("=" * 70)
    print("🤖 [Cloudbet Matcher] Inicializando Reconciliação...")
    print("=" * 70)
    
    if args.fixture_id:
        cursor.execute("SELECT fixture_id, home_team, away_team, fixture_date FROM fixtures_trends WHERE fixture_id = %s", (args.fixture_id,))
        fix = cursor.fetchone()
        if not fix:
            print(f"❌ Partida #{args.fixture_id} não encontrada no banco.")
            sys.exit(1)
            
        h = fix['home_team']
        a = fix['away_team']
        dt = fix['fixture_date']
        print(f"🔍 Buscando correspondência para: #{fix['fixture_id']} {h} vs {a} ({dt})...")
        match = matcher.find_matching_event(h, a, dt)
        if match:
            print(f"✅ [MATCH ENCONTRADO] Score: {match['match_score']*100:.1f}%")
            print(f"   • Evento Cloudbet: {match['event_name']} (ID: {match['event_id']})")
            print(f"   • Cutoff: {match['cutoff_time']} | Status: {match['status']}")
            
            markets = matcher.get_event_markets(match['event_id'])
            print(f"\n📊 Linhas de Handicap Asiático:")
            for hcap, outcomes in sorted(markets['asian_handicap'].items()):
                print(f"   Linha {hcap:+}: {outcomes}")
                
            print(f"\n📊 Linhas de Total de Gols:")
            for tot, outcomes in sorted(markets['total_goals'].items()):
                print(f"   Total {tot}: {outcomes}")
        else:
            print("⚠️ Nenhuma correspondência localizada na Cloudbet para esta partida.")
            
    elif args.test_today or len(sys.argv) == 1:
        cursor.execute("""
            SELECT fixture_id, home_team, away_team, fixture_date 
            FROM fixtures_trends 
            WHERE DATE(fixture_date) = CURDATE()
            ORDER BY fixture_date ASC
        """)
        today_games = cursor.fetchall()
        print(f"📋 Analisando {len(today_games)} partidas de hoje no banco MySQL...\n")
        
        matches_found = 0
        for fix in today_games:
            h = fix['home_team']
            a = fix['away_team']
            dt = fix['fixture_date']
            match = matcher.find_matching_event(h, a, dt)
            if match:
                matches_found += 1
                print(f"✅ #{fix['fixture_id']} {h} vs {a} ➔ Cloudbet ID {match['event_id']}: '{match['event_name']}' (Score: {match['match_score']*100:.0f}%)")
            else:
                print(f"❌ #{fix['fixture_id']} {h} vs {a} ➔ Não encontrado na Cloudbet")
                
        print("\n" + "=" * 70)
        print(f"🏁 Reconciliação Concluída: {matches_found}/{len(today_games)} partidas mapeadas com sucesso na Cloudbet.")
        print("=" * 70)
        
    cursor.close()
    conn.close()
