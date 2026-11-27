#!/usr/bin/env python3
"""
scripts/cloudbet_live_cashout.py

Mecanismo Canônico de Cash Out Autônomo In-Play da Cloudbet.
FootballWeb Pipeline - Automação 100% Robotizada.

Responsabilidades:
1. Monitorar em tempo real todas as apostas ativas da Cloudbet (casa_de_aposta = 'Cloudbet' e status = 'Pendente') em partidas ao vivo (1H, 2H, HT, LIVE);
2. Avaliar o velocímetro de risco e cenários críticos de preservação de banca ou meta de lucro batida;
3. Executar o Cash Out programático via Trading API (cloudbet_client.execute_cashout);
4. Encerrar a aposta no MySQL (status = 'Cashout') preservando o payload || U5J_DATA:;
5. Disparar notificação push imediata via ntfy para o smartphone.
"""

import os
import sys
import re
import json
import time
import argparse
import requests
import pymysql
from datetime import datetime
from typing import Dict, Any, Optional, List, Tuple

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars
from cloudbet_client import CloudbetClient
from asian_handicap_engine import compose_compound_ah_reasoning
from leagues_config import is_graylist_league

# Caches em memória para evitar chamadas de API repetidas e auditorias redundantes no in-play
_LAST_STATS_FETCH: Dict[int, float] = {}
_LAST_TEMP_AUDIT_LOG: Dict[str, bool] = {}
_LAST_PUSH_ALERT: Dict[str, float] = {}
_LAST_HT_WAIT_LOG: Dict[int, bool] = {}


def derive_inplay_xg_from_bookmakers(
    home_team: str,
    away_team: str,
    fixture_date: Optional[Any] = None,
    goals_home: int = 0,
    goals_away: int = 0
) -> Optional[Dict[str, Any]]:
    """
    Cascata de Contingência de xG via Bookmakers:
    Nível 2: Cloudbet (In-play Feed / Trading API via CloudbetMatcher).
    Nível 3: Betano (In-play Feed via betano_direct_api).
    """
    if not home_team or not away_team:
        return None

    # Nível 2: Consulta Cloudbet in-play via CloudbetMatcher
    try:
        from cloudbet_matcher import CloudbetMatcher
        client_cb = CloudbetClient()
        matcher = CloudbetMatcher(client_cb)
        matched = matcher.find_matching_event(home_team, away_team, fixture_date=fixture_date)
        if matched:
            matched_id = int(matched.get("event_id") or matched.get("id") or 0)
            if matched_id:
                cb_markets = matcher.get_event_markets(matched_id)
                tg_dict = cb_markets.get("total_goals", {})
                if tg_dict:
                    best_line = None
                    min_diff = 999.0
                    for line_val, outcomes in tg_dict.items():
                        p_over = float(outcomes.get("over", {}).get("price") or 0.0)
                        p_under = float(outcomes.get("under", {}).get("price") or 0.0)
                        if p_over > 1.0 and p_under > 1.0:
                            diff = abs(p_over - p_under)
                            if diff < min_diff:
                                min_diff = diff
                                best_line = float(line_val)
                    if best_line is not None:
                        tot_g = max(0, goals_home + goals_away)
                        rem_g = max(0.1, best_line - tot_g)
                        xg_h = round(goals_home + (rem_g * 0.58), 2)
                        xg_a = round(goals_away + (rem_g * 0.42), 2)
                        return {
                            "xg_home": xg_h,
                            "xg_away": xg_a,
                            "total_xg": round(xg_h + xg_a, 2),
                            "source": "CLOUDBET_INPLAY",
                            "reference_line": best_line
                        }
    except Exception:
        pass

    # Nível 3: Consulta Betano in-play via betano_direct_api
    try:
        from betano_direct_api import fetch_betano_live_football_events, is_team_match
        events = fetch_betano_live_football_events()
        for ev in events:
            ev_h = ev.get('home_team', '')
            ev_a = ev.get('away_team', '')
            if is_team_match(home_team, ev_h) and is_team_match(away_team, ev_a):
                markets = ev.get('markets', {})
                over_under = markets.get('over_under', {})
                if over_under:
                    best_line = None
                    min_diff = 999.0
                    for line_str, odds in over_under.items():
                        try:
                            m_l = re.search(r'(\d+(?:\.\d+)?)', str(line_str))
                            if not m_l:
                                continue
                            l_val = float(m_l.group(1))
                            o_val = float(odds.get('over') or 0.0)
                            u_val = float(odds.get('under') or 0.0)
                            if o_val > 1.0 and u_val > 1.0:
                                diff = abs(o_val - u_val)
                                if diff < min_diff:
                                    min_diff = diff
                                    best_line = l_val
                        except Exception:
                            continue
                    if best_line is not None:
                        tot_g = max(0, goals_home + goals_away)
                        rem_g = max(0.1, best_line - tot_g)
                        xg_h = round(goals_home + (rem_g * 0.58), 2)
                        xg_a = round(goals_away + (rem_g * 0.42), 2)
                        return {
                            "xg_home": xg_h,
                            "xg_away": xg_a,
                            "total_xg": round(xg_h + xg_a, 2),
                            "source": "BETANO_INPLAY",
                            "reference_line": best_line
                        }
    except Exception:
        pass

    return None


def fetch_inplay_match_statistics(
    fixture_id: int,
    home_team_id: Optional[int] = None,
    away_team_id: Optional[int] = None,
    cursor = None,
    conn = None,
    home_team: Optional[str] = None,
    away_team: Optional[str] = None,
    goals_home: Optional[int] = None,
    goals_away: Optional[int] = None,
    fixture_date: Optional[Any] = None
) -> Dict[str, Any]:
    """
    Busca estatísticas in-play (chutes no gol, chutes totais, escanteios, xG em 4 níveis)
    para partidas com apostas ativas em andamento, respeitando cache de 180s.
    Atualiza fixtures_trends no MySQL.
    """
    now = time.time()
    last_fetch = _LAST_STATS_FETCH.get(fixture_id, 0.0)
    if now - last_fetch < 180.0:
        return {}

    try:
        env_vars = get_live_env_vars()
        api_key = os.environ.get("FOOTBALL_API_KEY") or env_vars.get("FOOTBALL_API_KEY") or "0327019c6fab54df2ea46009b5f0844b"
        headers = {
            "x-apisports-key": api_key,
            "Content-Type": "application/json"
        }
        url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
        resp = requests.get(url, headers=headers, timeout=10)
        if resp.status_code != 200:
            return {}

        res_json = resp.json()
        if res_json.get("errors"):
            print(f"⚠️ [Stats In-Play API Warning]: {res_json.get('errors')}")
            return {}

        data = res_json.get("response", [])
        if not data:
            return {}

        _LAST_STATS_FETCH[fixture_id] = now

        sh, sa = 0, 0
        s_tot_h, s_tot_a = 0, 0
        s_off_h, s_off_a = 0, 0
        s_blk_h, s_blk_a = 0, 0
        ch, ca = 0, 0
        yc_h, yc_a = 0, 0
        rc_h, rc_a = 0, 0
        xg_native_h, xg_native_a = 0.0, 0.0
        poss_h, poss_a = 50, 50

        for team_st in data:
            t_id = team_st.get("team", {}).get("id")
            is_home = (t_id == home_team_id) if home_team_id else (team_st == data[0])
            stats_list = team_st.get("statistics", [])
            sg, s_tot, s_off, s_blk, ck = 0, 0, 0, 0, 0
            y_cnt, r_cnt = 0, 0
            xg_item = 0.0
            p_val = 50

            for s in stats_list:
                stype = (s.get("type") or "").strip().lower()
                sval = s.get("value")
                if sval is not None:
                    try:
                        if "corner" in stype:
                            ck = int(sval)
                        elif "shots on goal" in stype or "shots on target" in stype:
                            sg = int(sval)
                        elif "shots off goal" in stype:
                            s_off = int(sval)
                        elif "blocked shots" in stype:
                            s_blk = int(sval)
                        elif "total shots" in stype or stype == "shots":
                            s_tot = int(sval)
                        elif "yellow card" in stype:
                            y_cnt = int(sval)
                        elif "red card" in stype:
                            r_cnt = int(sval)
                        elif any(k in stype for k in ("expected goals", "xg", "expectedgoals", "expected_goals")):
                            xg_item = float(sval)
                        elif "possession" in stype:
                            p_val = int(str(sval).replace("%", "").strip())
                    except (ValueError, TypeError):
                        pass

            s_total_calc = s_tot if s_tot > 0 else (sg + s_off + s_blk)

            if is_home:
                sh = sg
                s_tot_h = s_total_calc
                s_off_h = s_off
                s_blk_h = s_blk
                ch = ck
                yc_h = y_cnt
                rc_h = r_cnt
                xg_native_h = xg_item
                poss_h = p_val
            else:
                sa = sg
                s_tot_a = s_total_calc
                s_off_a = s_off
                s_blk_a = s_blk
                ca = ck
                yc_a = y_cnt
                rc_a = r_cnt
                xg_native_a = xg_item
                poss_a = p_val

        # Cascata de 4 Níveis para Obtenção / Dedução de xG In-Play
        gh_val = int(goals_home if goals_home is not None else 0)
        ga_val = int(goals_away if goals_away is not None else 0)
        xg_h, xg_a = 0.0, 0.0
        xg_source = "DESCONHECIDO"

        # Nível 1: Opta nativo na API-Sports
        if xg_native_h > 0.0 or xg_native_a > 0.0:
            xg_h = round(xg_native_h, 2)
            xg_a = round(xg_native_a, 2)
            xg_source = "APISPORTS_OPTA"
        else:
            # Níveis 2 e 3: Cloudbet e Betano In-play Feed
            bm_xg = derive_inplay_xg_from_bookmakers(
                home_team=home_team or "",
                away_team=away_team or "",
                fixture_date=fixture_date,
                goals_home=gh_val,
                goals_away=ga_val
            )
            if bm_xg and bm_xg.get("total_xg", 0.0) > 0.0:
                xg_h = float(bm_xg.get("xg_home", 0.0))
                xg_a = float(bm_xg.get("xg_away", 0.0))
                xg_source = bm_xg.get("source", "BOOKMAKER_INPLAY")
            else:
                # Nível 4: Cálculo Matemático de Campo por Finalizações Reais
                s_off_calc_h = s_off_h if s_off_h > 0 else max(0, s_tot_h - sh)
                calc_h = round((sh * 0.32) + (s_off_calc_h * 0.08) + (gh_val * 0.15), 2)
                if calc_h == 0.0 and gh_val > 0:
                    calc_h = round(gh_val * 0.75, 2)

                s_off_calc_a = s_off_a if s_off_a > 0 else max(0, s_tot_a - sa)
                calc_a = round((sa * 0.32) + (s_off_calc_a * 0.08) + (ga_val * 0.15), 2)
                if calc_a == 0.0 and ga_val > 0:
                    calc_a = round(ga_val * 0.75, 2)

                xg_h = max(0.0, calc_h)
                xg_a = max(0.0, calc_a)
                xg_source = "CALCULO_CAMPO_FINALIZACOES"

        # Consulta eventos em tempo real para captura precisa de cartões amarelos e vermelhos (a API-Sports atrasa a agregação em statistics)
        try:
            url_ev = f"https://v3.football.api-sports.io/fixtures/events?fixture={fixture_id}"
            resp_ev = requests.get(url_ev, headers=headers, timeout=8).json()
            ev_items = resp_ev.get("response", [])
            yc_ev_h, yc_ev_a = 0, 0
            rc_ev_h, rc_ev_a = 0, 0
            for ev in ev_items:
                if ev.get("type") == "Card":
                    t_id = ev.get("team", {}).get("id")
                    det = (ev.get("detail") or "").strip().lower()
                    is_h = (t_id == home_team_id) if home_team_id else False
                    if "yellow" in det:
                        if is_h: yc_ev_h += 1
                        else: yc_ev_a += 1
                    elif "red" in det:
                        if is_h: rc_ev_h += 1
                        else: rc_ev_a += 1
            yc_h = max(yc_h, yc_ev_h)
            yc_a = max(yc_a, yc_ev_a)
            rc_h = max(rc_h, rc_ev_h)
            rc_a = max(rc_a, rc_ev_a)
        except Exception:
            pass

        if cursor and conn:
            cursor.execute("""
                UPDATE fixtures_trends
                SET shots_home = %s, shots_away = %s,
                    corners_home = %s, corners_away = %s,
                    xg_home = IF(%s > 0, %s, xg_home),
                    xg_away = IF(%s > 0, %s, xg_away),
                    yellow_cards_home = GREATEST(COALESCE(yellow_cards_home, 0), %s),
                    yellow_cards_away = GREATEST(COALESCE(yellow_cards_away, 0), %s),
                    red_cards_home = GREATEST(COALESCE(red_cards_home, 0), %s),
                    red_cards_away = GREATEST(COALESCE(red_cards_away, 0), %s),
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (s_tot_h, s_tot_a, ch, ca, xg_h, xg_h, xg_a, xg_a, yc_h, yc_a, rc_h, rc_a, fixture_id))
            conn.commit()

        return {
            "shots_home": sh,
            "shots_away": sa,
            "total_shots": sh + sa,
            "shots_total_home": s_tot_h,
            "shots_total_away": s_tot_a,
            "total_shots_all": s_tot_h + s_tot_a,
            "corners_home": ch,
            "corners_away": ca,
            "total_corners": ch + ca,
            "yellow_cards_home": yc_h,
            "yellow_cards_away": yc_a,
            "red_cards_home": rc_h,
            "red_cards_away": rc_a,
            "xg_home": xg_h,
            "xg_away": xg_a,
            "total_xg": round(xg_h + xg_a, 2),
            "xg_source": xg_source,
            "possession_home": poss_h,
            "possession_away": poss_a
        }
    except Exception as e:
        print(f"⚠️ [Erro ao buscar estatísticas in-play fixture #{fixture_id}]: {e}")
        return {}


def enviar_push_cashout(
    titulo: str,
    mensagem: str,
    fixture_id: Optional[int] = None,
    aposta_id: Optional[int] = None,
    prioridade: int = 5
) -> bool:
    """
    Dispara notificação push instantânea para o smartphone Android via ntfy.sh (Alta Prioridade).
    """
    try:
        env_vars = get_live_env_vars()
        enabled = os.environ.get("NTFY_ENABLED") or env_vars.get("NTFY_ENABLED", "true")
        if str(enabled).strip().lower() not in ("true", "1", "yes", "on"):
            return False

        topic = os.environ.get("NTFY_TOPIC") or env_vars.get("NTFY_TOPIC", "footbalweb_sentinela")
        if not topic:
            return False

        server = (os.environ.get("NTFY_SERVER") or env_vars.get("NTFY_SERVER", "https://ntfy.sh")).rstrip("/")
        base_url = (os.environ.get("NTFY_BASE_URL") or env_vars.get("NTFY_BASE_URL", "https://cristalbet.com.br")).rstrip("/")
        click_url = f"{base_url}/apostas?fixture_id={fixture_id}" if fixture_id else f"{base_url}/apostas"

        payload = {
            "topic": topic,
            "title": titulo,
            "message": mensagem,
            "priority": prioridade,
            "tags": ["zap", "moneybag", "soccer"],
            "click": click_url
        }

        resp = requests.post(
            f"{server}",
            json=payload,
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=8
        )
        return resp.status_code in (200, 201)
    except Exception as e:
        print(f"⚠️ [Push Mobile Erro]: {e}")
        return False


def extract_cloudbet_metadata(resultado_detalhado: str) -> Dict[str, Any]:
    """
    Extrai os metadados da Cloudbet (bet_id, event_id, market_url, is_simulated) gravados em resultado_detalhado.
    """
    if resultado_detalhado and "|| CLOUDBET:" in resultado_detalhado:
        try:
            part = resultado_detalhado.split("|| CLOUDBET:")[1].split("||")[0].strip()
            return json.loads(part)
        except Exception:
            pass
    return {}


def derive_opposite_market_url(market_url: str) -> Optional[str]:
    """
    Determina o marketUrl canônico da ponta oposta para realização de Hedge in-play.
    Exemplos:
      - Over vira Under: soccer.total_goals/over?total=2.5 -> soccer.total_goals/under?total=2.5
      - Home vira Away: soccer.asian_handicap/home?handicap=-0.25 -> soccer.asian_handicap/away?handicap=-0.25
    """
    if not market_url:
        return None
    if "/over?" in market_url:
        return market_url.replace("/over?", "/under?")
    elif "/under?" in market_url:
        return market_url.replace("/under?", "/over?")
    elif "/home?" in market_url:
        return market_url.replace("/home?", "/away?")
    elif "/away?" in market_url:
        return market_url.replace("/away?", "/home?")
    elif market_url.endswith("/yes"):
        return market_url[:-4] + "/no"
    elif market_url.endswith("/no"):
        return market_url[:-3] + "/yes"
    return None


def get_live_opposite_quote(client: CloudbetClient, event_id: str, opposite_market_url: str) -> Optional[Dict[str, Any]]:
    """
    Consulta o book de ofertas ao vivo da Cloudbet para localizar a cotação e liquidez da ponta de cobertura.
    """
    try:
        odds_raw = client.get_event_odds(event_id)
        markets = odds_raw.get("markets", {}) if isinstance(odds_raw, dict) else {}
        for mk, mv in markets.items():
            for subk, subv in mv.get("submarkets", {}).items():
                for sel in subv.get("selections", []):
                    if sel.get("marketUrl") == opposite_market_url:
                        price = float(sel.get("price") or 0.0)
                        status = sel.get("status")
                        if status == "SELECTION_ENABLED" and price > 1.0:
                            return {
                                "market_url": opposite_market_url,
                                "price": price,
                                "min_stake": float(sel.get("minStake") or 0.10),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }
    except Exception as e:
        print(f"⚠️ [Hedge Quote Error] Erro ao buscar cotação in-play de hedge: {e}")
    return None


def extract_cloudbet_bet_id(resultado_detalhado: str, aposta_id: int) -> str:
    """
    Extrai o identificador do bilhete (betId) da Cloudbet a partir do JSON de auditoria.
    """
    meta = extract_cloudbet_metadata(resultado_detalhado)
    if "bet_id" in meta and meta["bet_id"]:
        return str(meta["bet_id"])
    return f"sim_cb_{aposta_id}"


def find_cloudbet_opposite_market_and_quote(
    client: CloudbetClient,
    home: str,
    away: str,
    palpite: str,
    market_url: Optional[str] = None,
    event_id: Optional[str] = None,
    fixture_date: Optional[Any] = None,
    is_live: bool = True,
    goals_home: Optional[int] = None,
    goals_away: Optional[int] = None
) -> Tuple[Optional[str], Optional[str], float, float, Optional[str]]:
    """
    Localiza o evento e a seleção da ponta oposta com cotação e liquidez na Cloudbet.
    Suporta tanto apostas nativas da Cloudbet quanto Hedge Cruzado para apostas Betano/outras casas.
    Se is_live=True, prioriza o catálogo in-play (live=true) e bypassa cache de odds para obter dados em tempo real.
    GARANTIA DE JOGO COMPLETO (FT): Rejeita estritamente mercados parciais ('which_team_wins', 'rest_of_the_match', '1st_half', etc.).
    Retorna: (event_id, opposite_market_url, price, min_stake, opposite_palpite)
    """
    opp_market_url = None
    opp_palpite = None
    price = 0.0
    min_stake = 0.10

    # 1. Se já temos market_url nativa Cloudbet, deriva a ponta oposta
    if market_url:
        opp_market_url = derive_opposite_market_url(market_url)
        # Veto preventivo se a URL de origem pertencer a mercados proibidos
        if opp_market_url and any(bad in opp_market_url.lower() for bad in ("which_team_wins", "rest_of_the_match", "1st_half", "2nd_half", "half_time")):
            opp_market_url = None

    # 2. Se temos event_id e opp_market_url, tenta a cotação direta no book ao vivo
    if event_id and opp_market_url:
        quote = get_live_opposite_quote(client, str(event_id), opp_market_url)
        if quote:
            if "cart" in palpite.lower() or "booking" in opp_market_url:
                m_c = re.search(r'total=(\d+(?:\.\d+)?)', opp_market_url)
                pts_val = float(m_c.group(1)) if m_c else 55.5
                c_val = round(pts_val / 10.0, 1) if pts_val > 15.0 else pts_val
                opp_palpite = f"Mais de {c_val:g} Cartões (Hedge)" if "/over" in opp_market_url else f"Menos de {c_val:g} Cartões (Hedge)"
            elif "gol" in palpite.lower() or "total_goals" in opp_market_url:
                m_g = re.search(r'total=(\d+(?:\.\d+)?)', opp_market_url)
                g_val = float(m_g.group(1)) if m_g else 2.5
                opp_palpite = f"Mais de {g_val:g} Gols (Hedge)" if "/over" in opp_market_url else f"Menos de {g_val:g} Gols (Hedge)"
            else:
                opposite_team = home if away.lower() in palpite.lower() else away
                opp_palpite = f"{opposite_team} 0.0 AH" if "0.0" in palpite else f"Hedge oposto ({opp_market_url})"
            return str(event_id), opp_market_url, float(quote['price']), float(quote['min_stake']), opp_palpite

    # 3. Hedge Dinâmico / Cruzado via CloudbetMatcher (procura o evento ao vivo e seleciona a melhor linha ativa)
    try:
        from cloudbet_matcher import CloudbetMatcher
        matcher = CloudbetMatcher(client)

        target_event_id = str(event_id).strip() if event_id else ""
        if not target_event_id:
            matched = matcher.find_matching_event(home, away, fixture_date=fixture_date, is_live=is_live)
            if matched:
                target_event_id = str(matched.get("event_id") or matched.get("id") or "").strip()

        if target_event_id:
            # use_cache=False para obter o book de ofertas ao vivo em tempo real
            cb_markets = matcher.get_event_markets(int(target_event_id), use_cache=False)

            # Caso Handicap Asiático
            if "ah" in palpite.lower() or "handicap" in palpite.lower() or "0.0" in palpite:
                is_home_bet = (home.lower() in palpite.lower())
                opp_outcome = "away" if is_home_bet else "home"
                opp_team = away if is_home_bet else home

                m_hcap = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite)
                hcap_val = float(m_hcap.group(1)) if m_hcap else 0.0
                opp_hcap = -hcap_val

                # Vantagem de gols do time adversário no momento (se placar disponível)
                gh_live = int(goals_home if is_home_bet else goals_away) if (goals_home is not None and goals_away is not None) else 0
                ga_live = int(goals_away if is_home_bet else goals_home) if (goals_home is not None and goals_away is not None) else 0
                adv_lead = ga_live - gh_live  # Vantagem de gols do adversário (ex: 1 no 0x1)

                ah_dict = cb_markets.get("asian_handicap", {})
                dnb_dict = cb_markets.get("draw_no_bet", {})
                mo_dict = cb_markets.get("match_odds", {})
                dc_dict = cb_markets.get("double_chance", {})
                sel_cand = None

                # Prioridade 0: Se aposta for 0.0 DNB, verifica mercado dedicado soccer.draw_no_bet (FT)
                if (abs(hcap_val) < 0.01 or "0.0" in palpite) and dnb_dict and opp_outcome in dnb_dict:
                    sel_cand = dnb_dict[opp_outcome]
                    opp_hcap = 0.0

                # Prioridade 1: Linha espelho exata (-opp_hcap ou linha correspondente indexada)
                if not sel_cand and ah_dict:
                    for test_h in [opp_hcap, hcap_val]:
                        if test_h in ah_dict and opp_outcome in ah_dict[test_h]:
                            # Trava de Cobertura: se adversário lidera por 1+, linha não pode exigir mais gols
                            if adv_lead > 0 and test_h < -0.5:
                                continue
                            sel_cand = ah_dict[test_h][opp_outcome]
                            opp_hcap = test_h
                            break

                # Prioridade 2: Linha neutra 0.0 DNB no Draw No Bet ou Asian Handicap (FT)
                if not sel_cand:
                    if dnb_dict and opp_outcome in dnb_dict:
                        sel_cand = dnb_dict[opp_outcome]
                        opp_hcap = 0.0
                    elif 0.0 in ah_dict and opp_outcome in ah_dict[0.0]:
                        sel_cand = ah_dict[0.0][opp_outcome]
                        opp_hcap = 0.0

                # Prioridade 3: Linha ativa in-play mais equilibrada (odds >= 1.15) COM TRAVA DE COBERTURA ESTREITA
                # Se o adversário lidera por 1 gol (adv_lead >= 1), linhas como -1.0, -1.25, -1.5 são VETADAS!
                # Somente linhas que cubram o placar atual (test_h >= -0.5) são aceitas!
                if not sel_cand and ah_dict:
                    best_diff = 999.0
                    for h_k, h_v in ah_dict.items():
                        if opp_outcome in h_v:
                            if adv_lead > 0 and h_k < -0.5:
                                continue
                            cand = h_v[opp_outcome]
                            cand_price = cand.get("price", 0.0)
                            diff = abs(cand_price - 1.85)
                            if diff < best_diff and cand_price >= 1.15:
                                best_diff = diff
                                sel_cand = cand
                                opp_hcap = h_k

                # Prioridade 4: Vitória Seca da partida inteira (Match Odds / 1X2 FT)
                # Cobre perfeitamente o resultado final caso o placar atual se confirme.
                # BANIMENTO PERMANENTE: soccer.which_team_wins_the_rest_of_the_match é terminantemente proibido.
                if not sel_cand and mo_dict and opp_outcome in mo_dict:
                    cand_mo = mo_dict[opp_outcome]
                    cand_mo_price = float(cand_mo.get("price") or 0.0)
                    if cand_mo_price >= 1.15:
                        sel_cand = cand_mo
                        opp_hcap = -0.5
                        opp_palpite = f"{opp_team} Vence (1X2 FT)"

                # Prioridade 5: Dupla Chance da partida inteira (Double Chance FT / +0.5 AH equivalente)
                if not sel_cand and dc_dict:
                    dc_outcome = 'draw_away' if opp_outcome == 'away' else 'home_draw'
                    if dc_outcome in dc_dict:
                        cand_dc = dc_dict[dc_outcome]
                        cand_dc_price = float(cand_dc.get("price") or 0.0)
                        if cand_dc_price >= 1.15:
                            sel_cand = cand_dc
                            opp_hcap = 0.5
                            opp_palpite = f"{opp_team} ou Empate (Dupla Chance FT)"

                # Prioridade 6: Fallback de simulação em Dry-Run estritamente com Draw No Bet FT
                if not sel_cand and getattr(client, "dry_run", False):
                    opp_market_url = f"soccer.draw_no_bet/{opp_outcome}"
                    price = 2.65
                    min_stake = 0.10
                    opp_palpite = f"{opp_team} 0.0 AH" if opp_hcap == 0.0 else f"{opp_team} {opp_hcap:+.2f} AH"
                    return target_event_id, opp_market_url, price, min_stake, opp_palpite

                # Validação Final de Segurança: Whitelist Estrita de Mercados de Jogo Completo (FT)
                if sel_cand:
                    opp_cand_url = str(sel_cand.get("market_url") or "")
                    is_prohibited = any(bad in opp_cand_url.lower() for bad in (
                        "which_team_wins", "rest_of_the_match", "1st_half", "2nd_half", "half_time"
                    ))
                    if is_prohibited:
                        print(f"   🛑 [Veto de Mercado Parcial Cloudbet]: Mercado {opp_cand_url} rejeitado por não ser cobertura de jogo completo.")
                        sel_cand = None
                    else:
                        opp_market_url = opp_cand_url
                        price = float(sel_cand["price"])
                        min_stake = float(sel_cand["min_stake"])
                        if not opp_palpite:
                            opp_palpite = f"{opp_team} 0.0 AH" if opp_hcap == 0.0 else f"{opp_team} {opp_hcap:+.2f} AH"
                        return target_event_id, opp_market_url, price, min_stake, opp_palpite

            # Caso Cartões (Under / Over) - Travas Matemáticas Canônicas Mandatórias
            elif "cart" in palpite.lower() or "cart" in str(palpite or "").lower():
                m_cards = re.search(r'(\d+(?:\.\d+)?)', palpite)
                tot_cards = float(m_cards.group(1)) if m_cards else 2.5
                orig_line = tot_cards
                is_under_orig = ("menos" in palpite.lower() or "under" in palpite.lower())
                opp_cards_outcome = "over" if is_under_orig else "under"

                tb_dict = cb_markets.get("total_bookings", {})
                sel_cand = None

                # Prioridade 1: Linha espelho exata do palpite original (se ainda estiver ativa)
                if orig_line in tb_dict and opp_cards_outcome in tb_dict[orig_line]:
                    sel_cand = tb_dict[orig_line][opp_cards_outcome]
                    tot_cards = orig_line

                # Prioridade 2: Seleção estrita respeitando travas matemáticas para evitar buraco/red duplo:
                # - Se aposta original for OVER (X) e hedge for UNDER (Y): Y DEVE SER >= X (Under igual ou maior)
                # - Se aposta original for UNDER (X) e hedge for OVER (Y): Y DEVE SER <= X (Over igual ou menor)
                if not sel_cand and tb_dict:
                    if opp_cards_outcome == "under":
                        # Cobrindo OVER original -> busca menor linha de UNDER que seja >= orig_line
                        cands = [k for k in sorted(tb_dict.keys()) if k >= orig_line and opp_cards_outcome in tb_dict[k]]
                        for t_k in cands:
                            p_cand = float(tb_dict[t_k][opp_cards_outcome].get("price") or 0.0)
                            if p_cand >= 1.25:
                                sel_cand = tb_dict[t_k][opp_cards_outcome]
                                tot_cards = t_k
                                break
                    else:
                        # Cobrindo UNDER original -> busca maior linha de OVER que seja <= orig_line
                        cands = [k for k in sorted(tb_dict.keys(), reverse=True) if k <= orig_line and opp_cards_outcome in tb_dict[k]]
                        for t_k in cands:
                            p_cand = float(tb_dict[t_k][opp_cards_outcome].get("price") or 0.0)
                            if p_cand >= 1.25:
                                sel_cand = tb_dict[t_k][opp_cards_outcome]
                                tot_cards = t_k
                                break

                if not sel_cand and getattr(client, "dry_run", False):
                    opp_market_url = f"soccer.total_bookings/{opp_cards_outcome}?total={tot_cards}"
                    price = 2.05
                    min_stake = 0.10
                    opp_palpite = f"{'Mais de' if opp_cards_outcome == 'over' else 'Menos de'} {tot_cards} Cartões (Hedge)"
                    return target_event_id, opp_market_url, price, min_stake, opp_palpite

                if sel_cand:
                    opp_market_url = sel_cand["market_url"]
                    price = float(sel_cand["price"])
                    min_stake = float(sel_cand["min_stake"])
                    opp_palpite = f"{'Mais de' if opp_cards_outcome == 'over' else 'Menos de'} {tot_cards} Cartões (Hedge)"
                    return target_event_id, opp_market_url, price, min_stake, opp_palpite

            # Caso Total de Gols (Over / Under) - Travas Matemáticas Anti-Buraco / Anti-Red Duplo
            elif "gol" in palpite.lower() or "over" in palpite.lower() or "under" in palpite.lower():
                m_tot = re.search(r'(\d+(?:\.\d+)?)', palpite)
                tot_val = float(m_tot.group(1)) if m_tot else 2.5
                orig_line = tot_val
                is_over = ("mais" in palpite.lower() or "over" in palpite.lower())
                opp_tot_outcome = "under" if is_over else "over"

                tg_dict = cb_markets.get("total_goals", {})
                sel_cand = None

                # Prioridade 1: Linha exata do total original (se ainda estiver ativa)
                if orig_line in tg_dict and opp_tot_outcome in tg_dict[orig_line]:
                    cand = tg_dict[orig_line][opp_tot_outcome]
                    p_exact = float(cand.get("price", 0.0))
                    if p_exact >= 1.25:
                        sel_cand = cand
                        tot_val = orig_line

                # Prioridade 2: Seleção estrita respeitando travas matemáticas para evitar buraco/red duplo:
                # - Se aposta original for OVER (X) e hedge for UNDER (Y): Y DEVE SER >= X (Under igual ou maior!)
                #   Ex: Over 2.5 coberto por Under 2.5, Under 3.0 ou Under 3.5.
                #   Se comprar Under 1.5, o placar de 2 gols causaria RED DUPLO!
                # - Se aposta original for UNDER (X) e hedge for OVER (Y): Y DEVE SER <= X (Over igual ou menor!)
                #   Ex: Under 2.5 coberto por Over 2.5, Over 2.0 ou Over 1.5.
                #   Se comprar Over 3.5, o placar de 3 gols causaria RED DUPLO!
                if not sel_cand and tg_dict:
                    if opp_tot_outcome == "under":
                        # Cobrindo OVER original -> busca menor linha de UNDER que seja >= orig_line
                        cands = [k for k in sorted(tg_dict.keys()) if k >= orig_line and opp_tot_outcome in tg_dict[k]]
                        for t_k in cands:
                            cand = tg_dict[t_k][opp_tot_outcome]
                            cand_price = float(cand.get("price", 0.0))
                            if cand_price >= 1.25:
                                sel_cand = cand
                                tot_val = t_k
                                break
                    else:
                        # Cobrindo UNDER original -> busca maior linha de OVER que seja <= orig_line
                        cands = [k for k in sorted(tg_dict.keys(), reverse=True) if k <= orig_line and opp_tot_outcome in tg_dict[k]]
                        for t_k in cands:
                            cand = tg_dict[t_k][opp_tot_outcome]
                            cand_price = float(cand.get("price", 0.0))
                            if cand_price >= 1.25:
                                sel_cand = cand
                                tot_val = t_k
                                break

                # Validação Final de Segurança: Whitelist de Jogo Completo
                if sel_cand:
                    opp_cand_url = str(sel_cand.get("market_url") or "")
                    is_prohibited = any(bad in opp_cand_url.lower() for bad in (
                        "which_team_wins", "rest_of_the_match", "1st_half", "2nd_half", "half_time"
                    ))
                    if is_prohibited:
                        print(f"   🛑 [Veto de Mercado Parcial Gols Cloudbet]: Mercado {opp_cand_url} rejeitado.")
                        sel_cand = None
                    else:
                        opp_market_url = opp_cand_url
                        price = float(sel_cand["price"])
                        min_stake = float(sel_cand["min_stake"])
                        opp_palpite = f"Menos de {tot_val} Gols Asiáticos" if opp_tot_outcome == "under" else f"Mais de {tot_val} Gols Asiáticos"
                        return target_event_id, opp_market_url, price, min_stake, opp_palpite

                if not sel_cand and getattr(client, "dry_run", False):
                    opp_market_url = f"soccer.total_goals/{opp_tot_outcome}?total={tot_val}"
                    price = 1.85
                    min_stake = 0.10
                    opp_palpite = f"Menos de {tot_val} Gols Asiáticos" if opp_tot_outcome == "under" else f"Mais de {tot_val} Gols Asiáticos"
                    return target_event_id, opp_market_url, price, min_stake, opp_palpite

            # Se target_event_id foi localizado mas nenhuma linha tinha liquidez aberta,
            # preserva target_event_id para evitar falso diagnóstico de evento não localizado
            return target_event_id, None, 0.0, 0.10, None

    except Exception as e_m:
        print(f"⚠️ [Cross-Hedge Matcher Error] Falha ao resolver mercado oposto na Cloudbet: {e_m}")

    resolved_ev_id = target_event_id if ('target_event_id' in locals() and target_event_id) else (event_id or None)
    return resolved_ev_id, None, 0.0, 0.10, None


def evaluate_live_cashout_trigger(
    bet_row: Dict[str, Any],
    stats_inplay: Optional[Dict[str, int]] = None,
    cursor = None,
    conn = None
) -> Tuple[bool, str]:
    """
    Avalia se uma aposta em andamento atingiu as condições críticas para Hedge Real na ponta oposta.
    Retorna: (should_hedge, motivo)
    Regra 8: inicialização prévia de variáveis locais.
    Regra 12: proibição de fatores sintéticos arbitrados.
    """
    diff_gols = 0
    elapsed = int(bet_row.get('elapsed') or 0)
    gh = int(bet_row.get('goals_home') or 0)
    ga = int(bet_row.get('goals_away') or 0)
    total_gols = gh + ga

    mercado = str(bet_row.get('mercado') or '').strip().lower()
    palpite = str(bet_row.get('palpite') or '').strip()
    home_team = str(bet_row.get('time_casa') or '').strip()
    away_team = str(bet_row.get('time_fora') or '').strip()

    status = str(bet_row.get('fixture_status') or '').strip().upper()
    if status not in ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED'):
        return False, "Partida não está em andamento ao vivo"

    # =========================================================================
    # 0. AVALIAÇÃO SITUACIONAL UNIFICADA (Paridade 1:1 com PHP LiveSituationalBuilder)
    # =========================================================================
    try:
        from situational_evaluator import LiveSituationalBuilder
        sit_eval = LiveSituationalBuilder.create().with_bet_row(bet_row).with_context(stats_inplay or {}).build()
        if sit_eval.get("is_cashout_moment"):
            motivo_sit = sit_eval.get("alerta_msg") or sit_eval.get("alerta_titulo")
            return True, f"Hedge Real Necessário [{sit_eval.get('estado_resultado')}]: {motivo_sit}"
    except Exception as e_sit:
        print(f"⚠️ [SituationalEvaluator Fallback]: {e_sit}")

    # =========================================================================
    # 1. CENÁRIOS DE HANDICAP ASIÁTICO
    # =========================================================================
    if 'handicap' in mercado or 'ah' in mercado.lower():
        is_away = (away_team.lower() in palpite.lower())
        team_name = away_team if is_away else home_team
        
        # Diferença de gols do ponto de vista do time apostado
        diff_gols = (ga - gh) if is_away else (gh - ga)

        # Extrai a linha do palpite (ex: -0.25, 0.0, +0.5)
        m_line = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite)
        line_val = float(m_line.group(1)) if m_line else 0.0

        # Cenário A: Desvantagem Crítica no 2º Tempo (Reta Final 76'+)
        if diff_gols < 0 and elapsed >= 76 and line_val <= 0.0:
            motivo = f"Hedge Real Necessário: {team_name} em desvantagem ({gh}x{ga}) aos {elapsed}'. Tempo escasso para reversão no 2º tempo."
            return True, motivo

        # Cenário B: Desvantagem Crítica de 2 ou mais gols (Hedge Just in Time - 1º ou 2º Tempo)
        if diff_gols <= -2:
            motivo = f"Hedge Just in Time Necessário: Desvantagem crítica de 2+ gols ({gh}x{ga}) aos {elapsed}'. Posição sob alto risco de perda total."
            return True, motivo

        # Cenário C: Expulsão Crítica (Cartão Vermelho) do time apostado no 2º tempo
        rc_home = int(bet_row.get('red_cards_home') or 0)
        rc_away = int(bet_row.get('red_cards_away') or 0)
        has_red = rc_away > 0 if is_away else rc_home > 0
        if has_red and elapsed >= 46 and diff_gols <= 0:
            motivo = f"Hedge Real de Gestão de Risco: {team_name} com jogador expulso e sem vantagem no placar ({gh}x{ga}) aos {elapsed}'."
            return True, motivo

        # Auditoria Tática Periódica para Handicap Asiático (Quando o time está em desvantagem ou alerta)
        if diff_gols < 0 and cursor and conn:
            f_id = bet_row.get('fixture_id')
            ap_id = bet_row.get('id')
            fase_bucket = 'HT' if status in ('HT', 'INTERVALO') else ('2H' if elapsed >= 45 else ('1H_late' if elapsed >= 30 else '1H_early'))
            audit_key = f"{f_id}_ah_alert_{fase_bucket}"
            if not _LAST_TEMP_AUDIT_LOG.get(audit_key):
                try:
                    from lifecycle_logger import log_lifecycle_event
                    sh = int((stats_inplay.get('shots_home') if stats_inplay else None) or bet_row.get('shots_home') or 0)
                    sa = int((stats_inplay.get('shots_away') if stats_inplay else None) or bet_row.get('shots_away') or 0)
                    ch = int((stats_inplay.get('corners_home') if stats_inplay else None) or bet_row.get('corners_home') or 0)
                    ca = int((stats_inplay.get('corners_away') if stats_inplay else None) or bet_row.get('corners_away') or 0)
                    tot_shots = sh + sa
                    tot_corners = ch + ca
                    fase_display = 'Intervalo' if status in ('HT', 'INTERVALO') else ('2º Tempo' if elapsed >= 45 else '1º Tempo')

                    motivo_audit = (
                        f"Auditoria Tática Handicap: {team_name} em desvantagem ({gh}x{ga} aos {elapsed}' · {fase_display} | "
                        f"Finalizações: {sh}x{sa} | Escanteios: {ch}x{ca}). "
                        f"Monitoramento ativo. Posição mantida sem hedge imediato (reavaliação tática programada para a janela dos 50'+)."
                    )
                    log_lifecycle_event(
                        cursor=cursor,
                        fixture_id=f_id,
                        aposta_id=ap_id,
                        componente='LIVE_GAME_TEMPERATURE',
                        tipo_evento='AUDITORIA_TEMPERATURA_JOGO',
                        estado_anterior='Pendente',
                        estado_novo='Pendente',
                        minuto_jogo=elapsed,
                        fase_jogo='HT' if status in ('HT', 'INTERVALO') else ('2H' if elapsed >= 45 else '1H'),
                        placar_momento=f"{gh}x{ga}",
                        delta={
                            'minuto': elapsed,
                            'fase': status,
                            'placar': f"{gh}x{ga}",
                            'diff_gols': diff_gols,
                            'total_shots': tot_shots,
                            'total_corners': tot_corners,
                            'temperatura': 'ALERTA_DESVANTAGEM',
                            'decisao': 'MANTER_APOSTA'
                        },
                        motivo=f"🌡️ {motivo_audit}"
                    )
                    conn.commit()
                    _LAST_TEMP_AUDIT_LOG[audit_key] = True
                except Exception as e_ah_log:
                    print(f"⚠️ [AH Audit Log Erro]: {e_ah_log}", file=sys.stderr)

        if diff_gols < 0:
            return False, f"⚠️ Em Observação Tática: {team_name} em desvantagem ({gh}x{ga} aos {elapsed}') - Posição monitorada"

    # =========================================================================
    # 2. CENÁRIOS DE TOTAL DE GOLS (OVER / UNDER COM AFERIÇÃO DE xG E TEMPERATURA)
    # =========================================================================
    elif 'gol' in mercado or 'over' in palpite.lower() or 'under' in palpite.lower():
        is_over = 'over' in palpite.lower() or 'mais' in palpite.lower()
        m_tot = re.search(r'(\d+(?:\.\d+)?)', palpite)
        line_total = float(m_tot.group(1)) if m_tot else 2.5

        # Coleta de xG e métricas estritamente in-play (proibido fallback pré-jogo do banco - Regras 6 e 9)
        xg_h = float(stats_inplay.get('xg_home') or 0.0) if (stats_inplay and stats_inplay.get('xg_home') is not None) else 0.0
        xg_a = float(stats_inplay.get('xg_away') or 0.0) if (stats_inplay and stats_inplay.get('xg_away') is not None) else 0.0
        tot_xg = round(xg_h + xg_a, 2)
        xg_source = (stats_inplay.get('xg_source') if stats_inplay else None) or "Ao Vivo (Campo)"

        sh = int((stats_inplay.get('shots_home') if stats_inplay else None) or bet_row.get('shots_home') or 0)
        sa = int((stats_inplay.get('shots_away') if stats_inplay else None) or bet_row.get('shots_away') or 0)
        ch = int((stats_inplay.get('corners_home') if stats_inplay else None) or bet_row.get('corners_home') or 0)
        ca = int((stats_inplay.get('corners_away') if stats_inplay else None) or bet_row.get('corners_away') or 0)
        tot_shots = sh + sa
        tot_corners = ch + ca
        tot_shots_all = int((stats_inplay.get('total_shots_all') if stats_inplay else None) or tot_shots)
        f_id = bet_row.get('fixture_id')
        ap_id = bet_row.get('id')

        if is_over:
            # Se a linha já foi superada (ex: 3 gols em Mais de 2.5), a aposta já é GREEN absoluto!
            if total_gols >= line_total:
                return False, f"Aposta de Mais de {line_total} Gols já superada ({gh}x{ga}). Posição vencedora mantida sem hedge."

            deficit_gols = line_total - total_gols
            progresso_over = (total_gols / line_total) if line_total > 0 else 0.0

            # A) Janela Nobre de Cash Out dos 46' aos 79' (Meio de Jogo - Resgate de capital com liquidez ativa)
            if 46 <= elapsed <= 79 and f_id:
                window_bucket = "46_60" if elapsed < 60 else "60_79"
                audit_key = f"{f_id}_over_{window_bucket}"

                # Subcenário A.1: Placar abaixo de 75% da meta de Over (ex: 0x0 em Over 2.0 / 2.5 = 0%; 1 gol em Over 2.5 = 40%)
                if progresso_over < 0.75:
                    motivo_nobre = (
                        f"Janela Nobre de Cash Out (46' a 79' - Ritmo Lento): Placar {gh}x{ga} aos {elapsed}' "
                        f"(abaixo de 75% da meta Over {line_total} | xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all} | Escanteios: {tot_corners}). "
                        f"Momento ideal de agir na Cloudbet para resgate de capital e garantia de liquidez na ponta de Under."
                    )
                    if cursor and conn and not _LAST_TEMP_AUDIT_LOG.get(audit_key):
                        try:
                            from lifecycle_logger import log_lifecycle_event
                            log_lifecycle_event(
                                cursor=cursor,
                                fixture_id=f_id,
                                aposta_id=ap_id,
                                componente='LIVE_GAME_TEMPERATURE',
                                tipo_evento='AUDITORIA_TEMPERATURA_JOGO',
                                estado_anterior='Pendente',
                                estado_novo='Pendente',
                                minuto_jogo=elapsed,
                                fase_jogo='2H',
                                placar_momento=f"{gh}x{ga}",
                                delta={'minuto': elapsed, 'fase': '2H', 'placar': f"{gh}x{ga}", 'xg_total': tot_xg, 'xg_source': xg_source, 'total_shots_all': tot_shots_all, 'total_corners': tot_corners, 'temperatura': 'JANELA_NOBRE_CASHOUT', 'decisao': 'DISPARAR_HEDGE_ANTECIPADO'},
                                motivo=f"⚡ {motivo_nobre}"
                            )
                            conn.commit()
                            _LAST_TEMP_AUDIT_LOG[audit_key] = True
                        except Exception:
                            pass
                    return True, motivo_nobre

                # Subcenário A.2: Faltando apenas 1 gol (progresso >= 75%, ex: 2 gols em Mais de 2.5)
                elif deficit_gols == 0.5:
                    if tot_xg >= 1.20 or tot_shots_all >= 6 or tot_corners >= 4:
                        motivo_quente = (
                            f"Temperatura do Jogo: Pressão Ofensiva a 1 gol da linha (Placar: {gh}x{ga} aos {elapsed}' | xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all}). "
                            f"Apenas 1 gol para o Green de Mais de {line_total}. Posição mantida."
                        )
                        if cursor and conn and not _LAST_TEMP_AUDIT_LOG.get(audit_key):
                            try:
                                from lifecycle_logger import log_lifecycle_event
                                log_lifecycle_event(
                                    cursor=cursor,
                                    fixture_id=f_id,
                                    aposta_id=ap_id,
                                    componente='LIVE_GAME_TEMPERATURE',
                                    tipo_evento='AUDITORIA_TEMPERATURA_JOGO',
                                    estado_anterior='Pendente',
                                    estado_novo='Pendente',
                                    minuto_jogo=elapsed,
                                    fase_jogo='2H',
                                    placar_momento=f"{gh}x{ga}",
                                    delta={'minuto': elapsed, 'fase': '2H', 'placar': f"{gh}x{ga}", 'xg_total': tot_xg, 'xg_source': xg_source, 'total_shots_all': tot_shots_all, 'total_corners': tot_corners, 'temperatura': 'QUENTE', 'decisao': 'MANTER_APOSTA'},
                                    motivo=f"🌡️ {motivo_quente}"
                                )
                                conn.commit()
                                _LAST_TEMP_AUDIT_LOG[audit_key] = True
                            except Exception:
                                pass

            # B) Janela de Reta Final (74' aos 85') - Fim do Apagão Cego no Over
            if 74 <= elapsed <= 85 and total_gols < line_total:
                # Se faltam 2 ou mais gols após os 74', a probabilidade é ínfima -> Hedge Mandatório!
                if deficit_gols >= 1.5:
                    motivo = (
                        f"Hedge de Reta Final (Over com Déficit de 2+ Gols): Placar {gh}x{ga} aos {elapsed}'. "
                        f"Faltam {deficit_gols:.1f} gols para a linha {line_total}. Tempo escasso. Hedge para Under necessário."
                    )
                    return True, motivo

                # Se falta apenas 1 gol (ex: 0x2 em Mais de 2.5) e já estamos nos 76'+
                elif deficit_gols == 0.5 and elapsed >= 76:
                    motivo = (
                        f"Hedge Defensivo de Reta Final (Over a 1 gol da linha): Placar {gh}x{ga} aos {elapsed}'. "
                        f"Faltando 1 gol para {line_total}. Resgate de liquidez na ponta de Under antes do encerramento dos mercados."
                    )
                    return True, motivo

            # C) Fallback Reta Final Extrema (86'+)
            if elapsed >= 86 and total_gols < line_total:
                motivo = f"Hedge de Encerramento (Over não concretizado): Placar {gh}x{ga} aos {elapsed}' em aposta {palpite}. Hedge para Under necessário."
                return True, motivo
        else:
            # Cenário 1: Risco Crítico no 1º Tempo (2+ gols marcados no 1T)
            if elapsed <= 45 and total_gols >= 2 and total_gols >= (line_total - 1):
                motivo = f"Risco Crítico de Over no 1º Tempo: Já foram marcados {total_gols} gols ({gh}x{ga}) aos {elapsed}' em aposta {palpite}. Hedge para Over necessário."
                return True, motivo

            # Cenário 2: Janela Nobre dos 46' aos 79' - A 1 gol ou menos do limite da linha
            if total_gols >= (line_total - 1) and (46 <= elapsed <= 79) and total_gols > 0:
                is_fractional_critical = ((line_total % 1.0 != 0) and total_gols == (line_total - 0.5))

                if is_fractional_critical:
                    # Linha Fracionária sem Reembolso (.5, ex: Menos de 2.5 com placar 2x0)
                    # NÃO HÁ MARGEM: se sair qualquer gol a aposta é 100% perdida sem Push.
                    # Só mantém se o jogo estiver REALMENTE frio e sem pressão (xG baixo e poucas chegadas).
                    if tot_xg < 1.10 and tot_shots_all <= 5 and tot_corners <= 4 and total_gols < line_total:
                        audit_key = f"{f_id}_under_morno_2h"
                        motivo_morno = (
                            f"Temperatura do Jogo: Jogo Morno/Travado (Placar: {gh}x{ga} aos {elapsed}' | xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all} | Escanteios: {tot_corners}). "
                            f"Ritmo ofensivo baixo na linha seca {line_total}. Aposta de Menos Gols caminhando para o Green. Posição mantida sem hedge."
                        )
                        if cursor and conn and not _LAST_TEMP_AUDIT_LOG.get(audit_key):
                            try:
                                from lifecycle_logger import log_lifecycle_event
                                log_lifecycle_event(
                                    cursor=cursor,
                                    fixture_id=f_id,
                                    aposta_id=ap_id,
                                    componente='LIVE_GAME_TEMPERATURE',
                                    tipo_evento='AUDITORIA_TEMPERATURA_JOGO',
                                    estado_anterior='Pendente',
                                    estado_novo='Pendente',
                                    minuto_jogo=elapsed,
                                    fase_jogo='2H',
                                    placar_momento=f"{gh}x{ga}",
                                    delta={'minuto': elapsed, 'fase': '2H', 'placar': f"{gh}x{ga}", 'xg_total': tot_xg, 'xg_source': xg_source, 'total_shots_all': tot_shots_all, 'total_corners': tot_corners, 'temperatura': 'MORNO', 'decisao': 'MANTER_APOSTA'},
                                    motivo=f"🌡️ {motivo_morno}"
                                )
                                conn.commit()
                                _LAST_TEMP_AUDIT_LOG[audit_key] = True
                            except Exception:
                                pass
                        return False, motivo_morno

                    motivo = (
                        f"Atenção ao Under (Linha Seca {line_total}): Placar {gh}x{ga} aos {elapsed}'. "
                        f"Pressão ofensiva ativa (xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all} | Escanteios: {tot_corners}). "
                        f"Risco iminente de perda irreversível a 0.5 gol da linha. Hedge para Over necessário."
                    )
                    return True, motivo

                else:
                    # Linha Inteira com Amortecedor de Push (.0, ex: Menos de 3.0 no 2x0)
                    # O próximo gol garante 100% de reembolso da stake, permitindo margem mais elástica
                    if tot_xg <= 1.80 and tot_shots_all <= 8 and tot_corners <= 6 and total_gols < line_total:
                        audit_key = f"{f_id}_under_morno_2h"
                        motivo_morno = (
                            f"Temperatura do Jogo: Jogo Morno/Travado (Placar: {gh}x{ga} aos {elapsed}' | xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all} | Escanteios: {tot_corners}). "
                            f"Ritmo ofensivo controlado na linha de proteção {line_total}. Aposta de Menos Gols caminhando para o Green/Push. Posição mantida sem hedge."
                        )
                        if cursor and conn and not _LAST_TEMP_AUDIT_LOG.get(audit_key):
                            try:
                                from lifecycle_logger import log_lifecycle_event
                                log_lifecycle_event(
                                    cursor=cursor,
                                    fixture_id=f_id,
                                    aposta_id=ap_id,
                                    componente='LIVE_GAME_TEMPERATURE',
                                    tipo_evento='AUDITORIA_TEMPERATURA_JOGO',
                                    estado_anterior='Pendente',
                                    estado_novo='Pendente',
                                    minuto_jogo=elapsed,
                                    fase_jogo='2H',
                                    placar_momento=f"{gh}x{ga}",
                                    delta={'minuto': elapsed, 'fase': '2H', 'placar': f"{gh}x{ga}", 'xg_total': tot_xg, 'xg_source': xg_source, 'total_shots_all': tot_shots_all, 'total_corners': tot_corners, 'temperatura': 'MORNO', 'decisao': 'MANTER_APOSTA'},
                                    motivo=f"🌡️ {motivo_morno}"
                                )
                                conn.commit()
                                _LAST_TEMP_AUDIT_LOG[audit_key] = True
                            except Exception:
                                pass
                        return False, motivo_morno

                    motivo = (
                        f"Atenção ao Under (Linha {line_total}): Placar {gh}x{ga} aos {elapsed}'. "
                        f"Ritmo ofensivo elevado (xG: {tot_xg:.2f} [{xg_source}] | Finalizações: {tot_shots_all} | Escanteios: {tot_corners}). "
                        f"Apenas 1 gol restante para o limite da linha. Hedge para Over necessário."
                    )
                    return True, motivo

    # =========================================================================
    # 3. CENÁRIOS DE TOTAL DE CARTÕES (UNDER CARTÕES)
    # =========================================================================
    elif 'cart' in mercado.lower() or 'cart' in palpite.lower() or 'booking' in str(bet_row.get('market_url') or '').lower():
        is_under = 'menos' in palpite.lower() or 'under' in palpite.lower()
        if is_under:
            m_cards = re.search(r'(\d+(?:\.\d+)?)', palpite)
            line_cards = float(m_cards.group(1)) if m_cards else 5.5

            yc_h = int((stats_inplay.get('yellow_cards_home') if stats_inplay else None) or bet_row.get('yellow_cards_home') or 0)
            yc_a = int((stats_inplay.get('yellow_cards_away') if stats_inplay else None) or bet_row.get('yellow_cards_away') or 0)
            rc_h = int((stats_inplay.get('red_cards_home') if stats_inplay else None) or bet_row.get('red_cards_home') or 0)
            rc_a = int((stats_inplay.get('red_cards_away') if stats_inplay else None) or bet_row.get('red_cards_away') or 0)

            tot_cards_live = yc_h + yc_a + rc_h + rc_a

            # Modo 'Morrer Tentando': Mesmo se a linha original foi superada,
            # continua disparando o gatilho ininterruptamente até o apito final para buscar Over na linha viva superior!
            if tot_cards_live >= line_cards:
                motivo = (
                    f"🚨 [Hedge de Resgate In-Play]: Linha Under {line_cards} atingida ({tot_cards_live} cartões aos {elapsed}'). "
                    f"Buscando compra de Over na linha viva superior para recuperar a stake e reverter a perda até o apito final."
                )
                return True, motivo

            # Gatilho 1: Acúmulo severo no 1º Tempo (ex: 3+ cartões no Under 5.5 ou 4+ no Under 6.5)
            if elapsed <= 45 and tot_cards_live >= (line_cards - 2.5):
                motivo = (
                    f"Alerta de Risco Severo Cartões: {tot_cards_live} cartões já aplicados aos {elapsed}' do 1T. "
                    f"Atrito disciplinar elevado ameaça a linha Under {line_cards}. Hedge para Over necessário."
                )
                return True, motivo

            # Gatilho 2: Apenas 1 cartão restante para estourar a linha a partir dos 50'
            if elapsed >= 50 and tot_cards_live >= (line_cards - 1.5):
                motivo = (
                    f"Atenção ao Under Cartões (Linha {line_cards}): {tot_cards_live} cartões aos {elapsed}'. "
                    f"Apenas 1 cartão restante para a perda total em partida com alta tensão. Hedge para Over necessário."
                )
                return True, motivo

    return False, "Nenhum gatilho de risco acionado"


def check_and_execute_cloudbet_live_cashouts(dry_run_override: Optional[bool] = None) -> Dict[str, Any]:
    """
    Executa uma varredura nas apostas Cloudbet ativas e encerra aquelas com gatilho ativado.
    """
    # Inicialização prévia mandatória de variáveis numéricas locais (Regra 8)
    total_abertas_cloudbet = 0
    total_jogos_ao_vivo = 0
    total_cashouts_executados = 0
    total_erros = 0

    conn = get_db_connection()
    cursor = conn.cursor()

    client = CloudbetClient(dry_run=dry_run_override)

    print("=" * 70)
    print("⚡ [Cloudbet In-Play Live Cashout Monitor] Iniciando Varredura...")
    print(f"   • Modo Operacional: {'DRY-RUN / SIMULAÇÃO 🛡️' if client.dry_run else 'PRODUÇÃO REAL ⚠️'}")
    print("=" * 70)

    # Consulta apostas ativas (Cloudbet e outras casas monitoradas) com dados live de fixtures_trends
    cursor.execute("""
        SELECT a.id, a.usuario_id, a.fixture_id, a.palpite, a.mercado, a.odd,
               a.valor_aposta, a.moeda_codigo, a.ganhos_potenciais, a.resultado_detalhado,
               a.data_hora_jogo, a.id_cross_edge_slip,
               a.casa_de_aposta,
               ft.status as fixture_status, ft.home_team as time_casa, ft.away_team as time_fora,
               ft.home_team_id, ft.away_team_id, ft.ah_reasoning,
               ft.league_id, ft.league_name,
               ft.goals_home, ft.goals_away, ft.elapsed,
               ft.yellow_cards_home, ft.yellow_cards_away,
               ft.red_cards_home, ft.red_cards_away,
               ft.corners_home, ft.corners_away,
               ft.shots_home, ft.shots_away, ft.fixture_date,
               ft.xg_home, ft.xg_away
        FROM apostas a
        INNER JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
        WHERE a.status = 'Pendente'
          AND a.confirmada = 1
          AND (a.tipo IS NULL OR a.tipo != 'Hedge Real')
          AND (a.id_cross_edge_slip IS NULL OR a.id_cross_edge_slip = '')
          AND (a.resultado_detalhado IS NULL OR (a.resultado_detalhado NOT LIKE '%HEDGE_COBERTO_POR%' AND a.resultado_detalhado NOT LIKE '%HEDGE_EXECUTADO%'))
          AND a.casa_de_aposta IN ('Cloudbet', 'Betano', 'Stake', 'Pinnacle', '1xBet')
          AND ft.status IN ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED')
        ORDER BY ft.elapsed DESC
    """)
    active_bets = cursor.fetchall()
    total_abertas_cloudbet = len(active_bets)

    if not active_bets:
        print("ℹ️ Nenhuma aposta ativa monitorada em jogos ao vivo no momento.")
        cursor.close()
        conn.close()
        return {
            "total_abertas": 0,
            "cashouts_executados": 0
        }

    print(f"🔍 Encontradas {total_abertas_cloudbet} aposta(s) aberta(s) em jogos ao vivo.")

    for bet in active_bets:
        aposta_id = bet['id']
        bet_id = str(aposta_id)
        f_id = bet['fixture_id']
        home = bet['time_casa']
        away = bet['time_fora']
        elapsed = bet['elapsed']
        gh = bet['goals_home']
        ga = bet['goals_away']
        palpite = bet['palpite']
        casa_nome = bet.get('casa_de_aposta') or 'Cloudbet'
        stake = float(bet['valor_aposta'] or 0.0)
        cur = bet.get('moeda_codigo') or client.currency

        print(f"\n📊 Analisando Aposta #{aposta_id} [{casa_nome}]: {home} {gh}x{ga} {away} ({elapsed}') | Palpite: {palpite}")

        # Se a aposta já possui cobertura ativa ou é ordem de hedge, pula imediatamente
        res_det = str(bet.get('resultado_detalhado') or '')
        tipo_aposta = str(bet.get('tipo') or '')
        if "HEDGE_COBERTO_POR" in res_det or "HEDGE_EXECUTADO" in res_det or tipo_aposta == 'Hedge Real':
            print(f"   🛡️ Aposta #{aposta_id} já possui hedge/cobertura ativa executada. Posição travada.")
            continue

        # Se a partida estiver a partir dos 35' OU com 1+ gol OU mercado de Cartões/Escanteios, busca/atualiza in-play stats
        total_gols_live = (int(gh or 0) + int(ga or 0))
        mercado_str = str(bet.get('mercado') or '').lower()
        palpite_str = str(palpite or '').lower()
        is_cards_or_corners = any(k in mercado_str or k in palpite_str for k in ('cart', 'card', 'escan', 'corner'))
        stats_inplay = {}
        if (elapsed and elapsed >= 35) or is_cards_or_corners or total_gols_live >= 1:
            stats_inplay = fetch_inplay_match_statistics(
                fixture_id=f_id,
                home_team_id=bet.get('home_team_id'),
                away_team_id=bet.get('away_team_id'),
                cursor=cursor,
                conn=conn,
                home_team=home,
                away_team=away,
                goals_home=gh,
                goals_away=ga,
                fixture_date=bet.get('fixture_date')
            )

        should_hedge, motivo = evaluate_live_cashout_trigger(
            bet_row=bet,
            stats_inplay=stats_inplay,
            cursor=cursor,
            conn=conn
        )

        if not should_hedge:
            if "⚠️" in motivo:
                print(f"   {motivo}")
            else:
                print(f"   🟢 Cenário Seguro / Controlado: {motivo}")
            continue

        print(f"   🚨 [GATILHO DE HEDGE ATIVADO]: {motivo}")

        # Se a partida estiver no intervalo (HT ou fim do 1T >= 45'), entra em espera estável sem spam
        status_fix = str(bet.get('fixture_status') or '').strip().upper()
        if status_fix in ('HT', 'INTERVALO', 'HALFTIME') or (status_fix == '1H' and elapsed >= 45):
            print(f"   ⏸️ Partida #{f_id} em intervalo ({gh}x{ga}). Aguardando retorno do 2º tempo para reabertura de liquidez...")
            if not _LAST_HT_WAIT_LOG.get(f_id):
                try:
                    from lifecycle_logger import log_lifecycle_event
                    log_lifecycle_event(
                        cursor=cursor,
                        fixture_id=f_id,
                        aposta_id=aposta_id,
                        componente='CLOUDBET_LIVE_HEDGE',
                        tipo_evento='HEDGE_EM_ESPERA_INTERVALO',
                        estado_anterior='Pendente',
                        estado_novo='Pendente',
                        minuto_jogo=45,
                        fase_jogo='HT',
                        placar_momento=f"{gh}x{ga}",
                        delta={'minuto': 45, 'fase': 'HT', 'placar': f"{gh}x{ga}"},
                        motivo=f"⏸️ Intervalo: Partida em pausa no vestiário ({gh}x{ga}). Mercados fechados pelas casas para o intervalo. Monitor aguardando retorno do 2º tempo para reavaliação de Hedge e liquidez."
                    )
                    conn.commit()
                    _LAST_HT_WAIT_LOG[f_id] = True
                except Exception as e_lf:
                    print(f"⚠️ [Erro Lifecycle Logger Intervalo]: {e_lf}")
            continue

        # Localiza o evento e cotação oposta na Cloudbet (para Cloudbet nativa ou Hedge Cruzado Betano)
        cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
        bet_id = str(cb_meta.get('bet_id') or f"cross_hedge_{aposta_id}")
        meta_event_id = str(cb_meta.get('event_id') or '')
        meta_market_url = str(cb_meta.get('market_url') or '')

        # Buffer de Liquidez In-Play: Micro-sondagens rápidas (até 4 tentativas a cada 4s)
        # para capturar a reabertura imediata da linha após suspensões dinâmicas da corretora
        event_id = None
        opposite_market_url = None
        hedge_price = 0.0
        min_stake = 0.10
        opp_palpite = None

        max_inplay_probes = 4
        probe_interval_sec = 4

        for probe_num in range(1, max_inplay_probes + 1):
            event_id, opposite_market_url, hedge_price, min_stake, opp_palpite = find_cloudbet_opposite_market_and_quote(
                client=client,
                home=home,
                away=away,
                palpite=palpite,
                market_url=meta_market_url,
                event_id=meta_event_id,
                fixture_date=bet.get('fixture_date') or bet.get('data_hora_jogo'),
                is_live=True,
                goals_home=gh,
                goals_away=ga
            )

            if event_id and opposite_market_url and hedge_price > 0.0:
                if probe_num > 1:
                    print(f"   ⚡ [Buffer de Liquidez Cloudbet] Linha reaberta e capturada com sucesso na tentativa #{probe_num}!")
                break

            if probe_num < max_inplay_probes:
                print(f"   ⏳ [Buffer de Liquidez Cloudbet] Linha temporariamente fechada/suspensa (tentativa {probe_num}/{max_inplay_probes}). Aguardando {probe_interval_sec}s para reabertura...")
                time.sleep(probe_interval_sec)

        if not event_id or not opposite_market_url:
            is_gray = is_graylist_league(bet.get('league_id'))
            gray_tag = " [⚠️ Liga em Observação - Graylist]" if is_gray else ""
            gray_prefix = "[Graylist] " if is_gray else ""

            print(f"   ℹ️ [{casa_nome}]{gray_tag} Evento sem mercado oposto líquido na Cloudbet...")
            tit = f"⚡ Alerta de Hedge ({casa_nome}){gray_tag}: {home} vs {away}"
            msg = f"Aposta {palpite} aos {elapsed}' ({gh}x{ga}). Avalie cobertura na ponta oposta. Motivo: {motivo}"
            
            # 1. Trava Estrita de Notificação Persistida no Banco (>= 5 min)
            cursor.execute("""
                SELECT TIMESTAMPDIFF(MINUTE, criado_em, NOW()) AS diff_m
                FROM notificacoes_usuario
                WHERE (aposta_id = %s OR fixture_id = %s)
                ORDER BY id DESC LIMIT 1
            """, (aposta_id, f_id))
            last_notif = cursor.fetchone()
            pode_enviar_push = (not last_notif or last_notif.get('diff_m') is None or last_notif['diff_m'] >= 5)

            if pode_enviar_push:
                enviar_push_cashout(tit, msg, fixture_id=f_id, aposta_id=aposta_id, prioridade=5)
                try:
                    cursor.execute("""
                        INSERT INTO notificacoes_usuario (
                            usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, criado_em
                        ) VALUES (%s, %s, %s, 'ALERTA_HEDGE_SEM_LIQUIDEZ', %s, %s, %s, 0, NOW())
                    """, (bet.get('usuario_id') or 558, aposta_id, f_id, tit, msg, f"/apostas?fixture_id={f_id}"))
                    conn.commit()
                except Exception:
                    pass

            # 2. Retentativa Contínua a Cada Minuto: registra a tentativa ativa a cada novo minuto de jogo
            cursor.execute("""
                SELECT delta_json
                FROM pipeline_lifecycle_logs
                WHERE aposta_id = %s AND tipo_evento IN ('HEDGE_ABORTADO_SEM_LIQUIDEZ', 'HEDGE_RETENTATIVA_SEM_LIQUIDEZ')
                ORDER BY id DESC LIMIT 1
            """, (aposta_id,))
            last_log = cursor.fetchone()
            last_min = None
            if last_log and last_log.get('delta_json'):
                try:
                    last_min = json.loads(last_log['delta_json']).get('minuto')
                except Exception:
                    pass

            # Só não repete se for exatamente o mesmo minuto já registrado
            ja_logou_neste_minuto = (last_min is not None and int(last_min) == int(elapsed))

            if not ja_logou_neste_minuto:
                try:
                    from lifecycle_logger import log_lifecycle_event
                    fase_calc = '2H' if elapsed > 45 else '1H'
                    if event_id and not opposite_market_url:
                        tipo_ev = 'HEDGE_AGUARDANDO_REABERTURA' if not last_log else 'HEDGE_RETENTATIVA_MERCADO_SUSPENSO'
                        motivo_log = (
                            f"{gray_prefix}Evento localizado na Cloudbet (ID {event_id}), mas seleções temporariamente suspensas aos {elapsed}' ({gh}x{ga}). Aguardando normalização do lance."
                            if not last_log else
                            f"{gray_prefix}Retentativa de Hedge Ao Vivo: Evento na Cloudbet (ID {event_id}) com mercado temporariamente suspenso aos {elapsed}' ({gh}x{ga}). Retentando no próximo minuto."
                        )
                    else:
                        tipo_ev = 'HEDGE_ABORTADO_SEM_LIQUIDEZ' if not last_log else 'HEDGE_RETENTATIVA_SEM_LIQUIDEZ'
                        motivo_log = (
                            f"{gray_prefix}Gatilho de Hedge acionado aos {elapsed}' ({gh}x{ga}), mas abortado: Evento não localizado no catálogo in-play da Cloudbet. Avalie encerramento manual."
                            if not last_log else
                            f"{gray_prefix}Retentativa de Hedge Ao Vivo: Consultando liquidez na Cloudbet aos {elapsed}' ({gh}x{ga})... Evento sem cobertura in-play."
                        )
                    log_lifecycle_event(
                        cursor=cursor,
                        fixture_id=f_id,
                        aposta_id=aposta_id,
                        componente='CLOUDBET_LIVE_HEDGE',
                        tipo_evento=tipo_ev,
                        estado_anterior='Pendente',
                        estado_novo='Pendente',
                        minuto_jogo=elapsed,
                        fase_jogo=fase_calc,
                        placar_momento=f"{gh}x{ga}",
                        delta={'elapsed': elapsed, 'minuto': elapsed, 'fase': fase_calc, 'placar': f"{gh}x{ga}", 'motivo_trigger': motivo, 'retentando': True, 'is_graylist': is_gray},
                        motivo=motivo_log
                    )
                    conn.commit()
                except Exception as e_log:
                    print(f"⚠️ [Erro Lifecycle Logger Hedge Retentativa]: {e_log}")
            continue

        if hedge_price <= 1.0 and not client.dry_run:
            print(f"   ⚠️ [Hedge Cloudbet] Mercado oposto '{opposite_market_url}' suspenso/sem liquidez neste instante.")
            try:
                from lifecycle_logger import log_lifecycle_event
                fase_calc = '2H' if elapsed > 45 else '1H'
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='CLOUDBET_LIVE_HEDGE',
                    tipo_evento='HEDGE_ABORTADO_MERCADO_SUSPENSO',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    minuto_jogo=elapsed,
                    fase_jogo=fase_calc,
                    placar_momento=f"{gh}x{ga}",
                    delta={'elapsed': elapsed, 'minuto': elapsed, 'fase': fase_calc, 'placar': f"{gh}x{ga}", 'market_url': opposite_market_url},
                    motivo=f"Gatilho de Hedge acionado aos {elapsed}' ({gh}x{ga}), mas abortado: Mercado oposto '{opposite_market_url}' suspenso/trancado pela casa de aposta."
                )
                conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Mercado Suspenso]: {e_log}")
            continue

        # Piso Mínimo de Cotação de Hedge (Resgate Patrimonial Inteligente):
        # - Padrão regular: 1.65 (CLOUDBET_HEDGE_MIN_ODD) para garantir simetria matemática
        # - Exceção de Emergência (Reta Crítica): quando a aposta de Cartões ou Gols está a 1 unidade do Red total,
        #   o mercado derrete a odd de cobertura. Um piso de 1.25 permite resgatar parte substancial do capital
        #   em vez de sofrer perda de 100% da stake por rigidez excessiva.
        is_cards_hedge = any(k in mercado_str or k in palpite_str for k in ('cart', 'card', 'booking'))
        min_hedge_odd = float(os.environ.get("CLOUDBET_HEDGE_MIN_ODD", "1.65"))
        if is_cards_hedge or "Apenas 1" in motivo or "A 1 gol" in motivo:
            min_hedge_odd = float(os.environ.get("CLOUDBET_HEDGE_EMERGENCY_MIN_ODD", "1.25"))

        if hedge_price < min_hedge_odd and not client.dry_run:
            print(f"   🛑 [Hedge Abortado - Odd Derretida] Cotação oposta de {hedge_price:.2f} inferior ao piso de segurança ({min_hedge_odd:.2f}). Abortando para evitar risco assimétrico prejudicial à banca.")
            tit_der = f"⚠️ Hedge Abortado (Odd Derretida): {home} vs {away}"
            msg_der = f"A cotação de cobertura derreteu para {hedge_price:.2f} (piso de segurança: {min_hedge_odd:.2f}). Ordem cancelada para proteger o patrimônio da banca."
            enviar_push_cashout(tit_der, msg_der, fixture_id=f_id, aposta_id=aposta_id, prioridade=4)
            try:
                from lifecycle_logger import log_lifecycle_event
                fase_calc = '2H' if elapsed > 45 else '1H'
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='CLOUDBET_LIVE_HEDGE',
                    tipo_evento='HEDGE_ABORTADO_ODD_DERRETIDA',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    minuto_jogo=elapsed,
                    fase_jogo=fase_calc,
                    placar_momento=f"{gh}x{ga}",
                    delta={'elapsed': elapsed, 'minuto': elapsed, 'fase': fase_calc, 'placar': f"{gh}x{ga}", 'hedge_price': hedge_price, 'min_odd': min_hedge_odd},
                    motivo=f"Gatilho de Hedge acionado aos {elapsed}' ({gh}x{ga}), mas abortado: Cotação de cobertura derreteu para @ {hedge_price:.2f} (abaixo do piso seguro de {min_hedge_odd:.2f})."
                )
                conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Odd Derretida]: {e_log}")
            continue

        if hedge_price <= 1.0:
            hedge_price = 1.90
        hedge_stake = max(min_stake, stake)
        opposite_palpite = opp_palpite or (f"{home if away.lower() in palpite.lower() else away} 0.0 AH" if "0.0" in palpite else f"Hedge oposto ({opposite_market_url})")

        # Idempotência Prévia Estrita: verifica se já existe hedge emitido para esta aposta
        cursor.execute("""
            SELECT id FROM apostas 
            WHERE resultado_detalhado LIKE %s
              AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (f"%HEDGE_ORIGEM: #{aposta_id}%",))
        hedge_existente = cursor.fetchone()
        if hedge_existente:
            print(f"   ℹ️ [Hedge Já Existe para Aposta #{aposta_id}] Hedge #{hedge_existente['id']} já emitido anteriormente. Duplicação de hedge bloqueada.")
            continue

        # Verifica se a linha oposta já existe no MySQL para este usuário e jogo (respeito à CONSTRAINT UNIQUE)
        uid_bet = bet.get('usuario_id')
        cursor.execute("""
            SELECT id, palpite, odd FROM apostas 
            WHERE fixture_id = %s AND usuario_id = %s AND casa_de_aposta = 'Cloudbet'
              AND palpite = %s AND confirmada = 1
              AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (f_id, uid_bet, opposite_palpite))
        linha_oposta_existe = cursor.fetchone()
        if linha_oposta_existe:
            print(f"   ℹ️ [Linha Oposta Já Apostada na Cloudbet #{f_id}] {opposite_palpite} (ID #{linha_oposta_existe['id']}). Duplicação de hedge bloqueada.")
            continue

        # 🔒 ETAPA 1 DO TWO-PHASE COMMIT: Trava Atômica e Checagem de Idempotência no MySQL
        cursor.execute("SELECT id_cross_edge_slip, resultado_detalhado FROM apostas WHERE id = %s FOR UPDATE", (aposta_id,))
        lock_row = cursor.fetchone()
        if lock_row and lock_row.get("id_cross_edge_slip"):
            conn.rollback()
            print(f"   🛡️ [Hedge Idempotência Ativa] Aposta #{aposta_id} já possui cross-edge gravado ({lock_row['id_cross_edge_slip']}). Abortando disparo duplicado.")
            continue

        # 🚀 ETAPA 2 DO TWO-PHASE COMMIT: Dispara ordem de Hedge na Cloudbet
        print(f"   🚀 [Disparando Hedge Real via API Cloudbet] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
        res_co = client.execute_cashout(
            bet_id=bet_id,
            event_id=event_id,
            market_url=opposite_market_url,
            price=hedge_price,
            stake=hedge_stake,
            dry_run=client.dry_run
        )

        # Extração tolerante de identificador da ordem aceita (betId, betslipId, referenceId ou id aninhado)
        bet_id_hedge = (
            res_co.get("betId") or 
            res_co.get("betslipId") or 
            res_co.get("referenceId") or 
            res_co.get("id") or 
            (res_co.get("bet") or {}).get("id") or
            (res_co.get("bet") or {}).get("betId")
        )
        # Validação Estrita de Aceite na Cloudbet (suporta PENDING_ACCEPTANCE do in-play e trata 'error': '')
        is_accepted = (
            bool(bet_id_hedge) and
            not res_co.get("error") and
            res_co.get("status") not in ("REJECTED", "FAILED", "REJECTED_SLIPPAGE") and
            res_co.get("state") not in ("REJECTED", "FAILED")
        )
        if not is_accepted:
            conn.rollback()
            print(f"   ❌ [Cloudbet API Rejeitou Ordem de Hedge] Bilhete não emitido pela Cloudbet: {res_co}. Rollback executado. Saldo preservado.")
            total_erros += 1
            continue

        # Transação Atômica: Inserção do Hedge, Débito e Atualização da Aposta Original com Captura de IntegrityError
        try:
            data_jogo_hedge = bet.get('data_hora_jogo') or bet.get('fixture_date') or datetime.now()
            cb_payload = json.dumps({
                "bet_id": bet_id_hedge,
                "event_id": str(event_id) if event_id else '',
                "market_url": opposite_market_url,
                "is_simulated": bool(res_co.get("is_simulated", client.dry_run)),
                "currency": cur
            })
            detalhado_hedge = f"|| CLOUDBET: {cb_payload} || Bilhete de Hedge Real emitido pelo robô para neutralizar a aposta #{aposta_id}. || HEDGE_ORIGEM: #{aposta_id} || CLOUDBET_BET_ID: {bet_id_hedge} || STATUS GK: APROVADO\nSUGGESTION: {opposite_palpite}\nREASON: Hedge Real In-Play acionado para recuperar stake e reverter perda até o apito final."

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, moeda_codigo, fixture_id, time_casa, time_fora, mercado,
                    casa_de_aposta, palpite, odd, odd_justa, status_gatekeeper, gatekeeper_category,
                    data_hora_jogo, valor_aposta, ganhos_potenciais, tipo, status,
                    resultado_detalhado, criado_em, updated_at, confirmada
                ) VALUES (
                    %s, %s, %s, %s, %s, %s,
                    'Cloudbet', %s, %s, %s, 'APROVADO', 'Hedge Real In-Play',
                    %s, %s, %s, 'Hedge Real', 'Pendente',
                    %s, NOW(), NOW(), 1
                )
            """, (
                uid_bet, cur, f_id, home, away, bet.get('mercado', 'Handicap Asiático'),
                opposite_palpite, hedge_price, hedge_price, data_jogo_hedge, hedge_stake, round(hedge_stake * hedge_price, 2),
                detalhado_hedge
            ))
            new_hedge_aposta_id = cursor.lastrowid

            # Registra débito da nova aposta de Hedge na conta corrente
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid_bet,))
            row_u = cursor.fetchone()
            s_ant = float(row_u['saldo_conta_corrente']) if row_u and row_u.get('saldo_conta_corrente') else 0.0
            s_post = round(s_ant - hedge_stake, 8)
            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (uid_bet, new_hedge_aposta_id, f"Débito Aposta #{new_hedge_aposta_id} (Hedge Real: {home} x {away} - {opposite_palpite})", -hedge_stake, s_ant, s_post, cur))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid_bet))

            # Atualiza a aposta original registrando a cobertura cruzada e a chave de idempotência id_cross_edge_slip
            cursor.execute("""
                UPDATE apostas
                SET id_cross_edge_slip = %s,
                    resultado_detalhado = CONCAT(COALESCE(resultado_detalhado, ''), %s),
                    updated_at = NOW()
                WHERE id = %s
            """, (str(bet_id_hedge), f" || HEDGE_COBERTO_POR: #{new_hedge_aposta_id} || SLIP: {bet_id_hedge}", aposta_id))

            conn.commit()
            print(f"   💰 [Hedge Gravado com Sucesso] Aposta #{new_hedge_aposta_id} salva e -{hedge_stake:.2f} {cur} debitado. Novo saldo: {s_post:.4f} {cur}")
        except pymysql.err.IntegrityError as e_int:
            conn.rollback()
            print(f"   🛑 [CONSTRAINT UNIQUE MySQL Barrou Duplicidade no Hedge In-Play] {opposite_palpite}: {e_int}. Rollback executado.")
            continue
        except Exception as e_tx:
            conn.rollback()
            print(f"   ❌ [Falha Transacional no Hedge In-Play] {e_tx}. Rollback executado.")
            total_erros += 1
            continue

        total_cashouts_executados += 1
        tit = f"⚡ Hedge Real Executado (Cloudbet): {home} vs {away}"
        msg = f"Aposta de cobertura #{new_hedge_aposta_id} comprada na linha oposta ({opposite_palpite} @ {hedge_price:.2f}) para travar a aposta #{aposta_id}!"
        enviar_push_cashout(tit, msg, fixture_id=f_id, aposta_id=new_hedge_aposta_id, prioridade=5)

        # 6. Rastreamento transversal de ciclo de vida
        try:
            from lifecycle_logger import log_lifecycle_event
            fase_calc = '2H' if elapsed > 45 else '1H'
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=f_id,
                aposta_id=new_hedge_aposta_id,
                notificacao_id=None,
                notificacao_criada_em=None,
                componente='CLOUDBET_LIVE_HEDGE',
                tipo_evento='HEDGE_ORDEM_REAL_EXECUTADA',
                estado_anterior='Pendente',
                estado_novo='Pendente',
                minuto_jogo=elapsed,
                fase_jogo=fase_calc,
                placar_momento=f"{gh}x{ga}",
                delta={'bet_id': bet_id, 'hedge_aposta_id': new_hedge_aposta_id, 'stake': hedge_stake, 'price': hedge_price, 'elapsed': elapsed, 'minuto': elapsed, 'fase': fase_calc, 'placar': f"{gh}x{ga}"},
                motivo=f"Hedge executado aos {elapsed}' ({gh}x{ga}): {motivo}"
            )
            conn.commit()
        except Exception as e_lf:
            print(f"   ⚠️ [Erro Lifecycle Logger Hedge #{new_hedge_aposta_id}]: {e_lf}")

    cursor.close()
    conn.close()

    print("\n" + "=" * 70)
    print("🏁 Resumo do Cash Out Live:")
    print(f"   • Total de apostas analisadas: {total_abertas_cloudbet}")
    print(f"   • Cash outs executados com sucesso: {total_cashouts_executados}")
    print(f"   • Erros de execução: {total_erros}")
    print("=" * 70)

    return {
        "total_abertas": total_abertas_cloudbet,
        "cashouts_executados": total_cashouts_executados,
        "erros": total_erros
    }


def executar_hedge_prejogo_cloudbet(
    bet: Dict[str, Any],
    motivo_tatica: str,
    client: Optional[CloudbetClient] = None,
    cursor = None,
    conn = None
) -> Dict[str, Any]:
    """
    Executa o Hedge Autônomo Pré-Jogo para apostas na Cloudbet
    quando o Grok IA emite veredito de CASHOUT_IMEDIATO por sinistro na súmula oficial.
    Regra 8: inicialização prévia de variáveis numéricas locais.
    Regra 16: preservação obrigatória do bloco || U5J_DATA:.
    """
    total_erros = 0
    valor_cashout = 0.0

    aposta_id = bet.get('id') or bet.get('aposta_id')
    f_id = bet.get('fixture_id')
    home = bet.get('home_team') or bet.get('time_casa', 'Mandante')
    away = bet.get('away_team') or bet.get('time_fora', 'Visitante')
    palpite = bet.get('palpite', 'N/D')
    stake = float(bet.get('valor_aposta') or 0.0)
    usuario_id = int(bet.get('usuario_id') or 558)
    motivo_clean = str(motivo_tatica or "").strip()

    if client is None:
        client = CloudbetClient()

    owns_conn = False
    if cursor is None:
        conn = get_db_connection()
        cursor = conn.cursor()
        owns_conn = True

    try:
        # =========================================================================
        # TRAVA DE INTEGRIDADE: Proibido emitir hedge para posições não confirmadas
        # =========================================================================
        if bet.get('confirmada') is not None and int(bet.get('confirmada') or 0) == 0:
            print(f"🛑 [Hedge Abortado] Aposta #{aposta_id} não confirmada (confirmada = 0). Proibido emitir hedge para posições não confirmadas.")
            return {
                "success": False,
                "motivo": f"Aposta #{aposta_id} não confirmada (confirmada = 0)."
            }

        # =========================================================================
        # TRAVA DE CORTE SOBERANO (T-0): Se a bola já rolou, o pré-jogo expirou!
        # =========================================================================
        status_fx = str(bet.get('fixture_status') or '').strip().upper()
        elapsed_fx = int(bet.get('elapsed') or 0)
        if not status_fx or status_fx == 'NS':
            try:
                cursor.execute("SELECT status, elapsed FROM fixtures_trends WHERE fixture_id = %s", (f_id,))
                row_fx = cursor.fetchone()
                if row_fx:
                    if isinstance(row_fx, dict):
                        status_fx = str(row_fx.get('status') or '').strip().upper()
                        elapsed_fx = int(row_fx.get('elapsed') or 0)
                    else:
                        status_fx = str(row_fx[0] or '').strip().upper()
                        elapsed_fx = int(row_fx[1] or 0)
            except Exception as e_fx:
                pass

        if status_fx in ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED') or elapsed_fx > 0:
            print(f"🛑 [Hedge Pré-Jogo Abortado] A partida #{f_id} já iniciou ({status_fx} aos {elapsed_fx}'). Decisão transferida integralmente para o motor Situacional Ao Vivo.")
            return {
                "success": False,
                "motivo": f"Partida já em andamento ({status_fx} aos {elapsed_fx}'). Pré-jogo expirado."
            }

        print(f"\n🛡️ [Iniciando Hedge Pré-Jogo Autônomo] Aposta #{aposta_id} ({home} vs {away}) | Palpite: {palpite}")
        print(f"   Motivo Grok IA: {motivo_tatica[:200]}...")

        cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
        bet_id = str(cb_meta.get('bet_id') or f"cross_pregame_{aposta_id}")
        meta_event_id = str(cb_meta.get('event_id') or '')
        meta_market_url = str(cb_meta.get('market_url') or '')

        casa_nome = bet.get('casa_de_aposta') or 'Cloudbet'
        event_id, opposite_market_url, hedge_price, min_stake, opp_palpite = find_cloudbet_opposite_market_and_quote(
            client=client,
            home=home,
            away=away,
            palpite=palpite,
            market_url=meta_market_url,
            event_id=meta_event_id,
            fixture_date=bet.get('fixture_date') or bet.get('data_hora_jogo'),
            is_live=False,
            goals_home=0,
            goals_away=0
        )

        hedge_info = {}
        if not event_id or not opposite_market_url:
            print(f"   ⚠️ [Hedge Cloudbet] Evento/Mercado oposto não localizado na Cloudbet para partida #{f_id} ({home} vs {away}).")
            tit_push = f"⚠️ Alerta Pré-Jogo ({casa_nome}): {home} vs {away}"
            msg_push = f"Escalação desfavorável para {palpite}. Motivo Grok IA: {motivo_tatica[:150]}"
            enviar_push_cashout(tit_push, msg_push, fixture_id=f_id, aposta_id=aposta_id, prioridade=5)
            try:
                from lifecycle_logger import log_lifecycle_event
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='GROK_SENTINEL_HEDGE',
                    tipo_evento='HEDGE_PREJOGO_ABORTADO_SEM_MERCADO',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    delta={'motivo_tatica': motivo_tatica},
                    motivo=f"Hedge pré-jogo recomendado pela IA, mas abortado: Mercado oposto não localizado na Cloudbet."
                )
                if owns_conn:
                    conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Hedge Pré-Jogo]: {e_log}")
            return {"success": False, "motivo": "Mercado Cloudbet não disponível"}

        if hedge_price <= 1.0 and not client.dry_run:
            print(f"   ⚠️ [Hedge Cloudbet Pré-Jogo] Mercado oposto '{opposite_market_url}' sem cotação pré-jogo disponível.")
            try:
                from lifecycle_logger import log_lifecycle_event
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='GROK_SENTINEL_HEDGE',
                    tipo_evento='HEDGE_PREJOGO_ABORTADO_SEM_COTACAO',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    delta={'market_url': opposite_market_url},
                    motivo=f"Hedge pré-jogo abortado: Mercado oposto '{opposite_market_url}' sem cotação disponível na Cloudbet."
                )
                if owns_conn:
                    conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Sem Cotação Pré-Jogo]: {e_log}")
            return {"success": False, "motivo": "Mercado oposto sem cotação"}

        min_hedge_odd = float(os.environ.get("CLOUDBET_HEDGE_MIN_ODD", "1.65"))
        if hedge_price < min_hedge_odd and not client.dry_run:
            print(f"   🛑 [Hedge Pré-Jogo Abortado - Odd Derretida] Cotação oposta de {hedge_price:.2f} inferior ao piso ({min_hedge_odd:.2f}).")
            tit_der = f"⚠️ Hedge Pré-Jogo Abortado (Odd Derretida): {home} vs {away}"
            msg_der = f"A cotação de cobertura derreteu para {hedge_price:.2f} (piso de segurança: {min_hedge_odd:.2f}). Ordem cancelada para proteger a banca."
            enviar_push_cashout(tit_der, msg_der, fixture_id=f_id, aposta_id=aposta_id, prioridade=4)
            try:
                from lifecycle_logger import log_lifecycle_event
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='GROK_SENTINEL_HEDGE',
                    tipo_evento='HEDGE_PREJOGO_ABORTADO_ODD_DERRETIDA',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    delta={'hedge_price': hedge_price, 'min_odd': min_hedge_odd},
                    motivo=f"Hedge pré-jogo abortado: Cotação derreteu para @ {hedge_price:.2f} (abaixo do piso de {min_hedge_odd:.2f})."
                )
                if owns_conn:
                    conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Odd Derretida Pré-Jogo]: {e_log}")
            return {"success": False, "motivo": f"Odd oposta {hedge_price:.2f} inferior ao piso de {min_hedge_odd:.2f}"}

        if hedge_price <= 1.0:
            hedge_price = 1.90
        hedge_stake = max(min_stake, round(stake, 2))
        opposite_palpite = opp_palpite or (f"{home if away.lower() in palpite.lower() else away} 0.0 AH" if "0.0" in palpite else f"Hedge oposto ({opposite_market_url})")

        # Idempotência Prévia Estrita: verifica se já existe hedge emitido para esta aposta
        cursor.execute("""
            SELECT id FROM apostas 
            WHERE resultado_detalhado LIKE %s
              AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (f"%HEDGE_ORIGEM: #{aposta_id}%",))
        hedge_existente = cursor.fetchone()
        if hedge_existente:
            print(f"   ℹ️ [Hedge Pré-Jogo Já Existe para Aposta #{aposta_id}] Hedge #{hedge_existente['id']} já emitido anteriormente. Duplicação de hedge bloqueada.")
            return {"success": True, "is_duplicate": True, "hedge_aposta_id": hedge_existente['id']}

        # Verifica se a linha oposta já existe no MySQL para este usuário e jogo (respeito à CONSTRAINT UNIQUE)
        cursor.execute("""
            SELECT id, palpite, odd FROM apostas 
            WHERE fixture_id = %s AND usuario_id = %s AND casa_de_aposta = 'Cloudbet'
              AND palpite = %s AND confirmada = 1
              AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (f_id, usuario_id, opposite_palpite))
        linha_oposta_existe = cursor.fetchone()
        if linha_oposta_existe:
            print(f"   ℹ️ [Linha Oposta Já Apostada na Cloudbet #{f_id}] {opposite_palpite} (ID #{linha_oposta_existe['id']}). Duplicação de hedge bloqueada.")
            return {"success": True, "is_duplicate": True, "hedge_aposta_id": linha_oposta_existe['id']}

        print(f"   🚀 [Disparando Ordem Real Pré-Jogo via API Cloudbet] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
        res_co = client.execute_cashout(
            bet_id=bet_id,
            event_id=event_id,
            market_url=opposite_market_url,
            price=hedge_price,
            stake=hedge_stake,
            dry_run=client.dry_run
        )

        bet_id_hedge = res_co.get("betId") or res_co.get("referenceId") or res_co.get("betslipId")
        # Validação Estrita de Two-Phase Commit (API-First, suporta PENDING_ACCEPTANCE e trata 'error': ''):
        is_accepted = (
            bool(bet_id_hedge) and
            not res_co.get("error") and
            res_co.get("status") not in ("REJECTED", "FAILED") and
            res_co.get("state") not in ("REJECTED", "FAILED")
        )
        if not is_accepted:
            if conn:
                conn.rollback()
            print(f"   ❌ [Cloudbet API Rejeitou Ordem de Hedge Pré-Jogo] Bilhete não emitido pela Cloudbet: {res_co}. Rollback executado. Saldo preservado.")
            return {"success": False, "motivo": f"Falha na API Cloudbet: {res_co}"}

        hedge_info = {
            "mode": "dry_run_pregame_hedge" if client.dry_run else "real_pregame_hedge",
            "hedge_ref": bet_id_hedge,
            "market_url": opposite_market_url,
            "price": hedge_price,
            "stake": hedge_stake
        }

        # Transação Atômica: Inserção do Hedge, Débito e Atualização da Aposta Original com Captura de IntegrityError
        try:
            data_jogo_hedge = bet.get('data_hora_jogo') or bet.get('fixture_date') or datetime.now()
            cb_payload = json.dumps({
                "bet_id": bet_id_hedge,
                "event_id": str(event_id) if event_id else '',
                "market_url": opposite_market_url,
                "is_simulated": bool(res_co.get("is_simulated", client.dry_run)),
                "currency": client.currency
            })
            detalhado_hedge = f"|| CLOUDBET: {cb_payload} || Bilhete de Hedge Real Pré-Jogo emitido pelo robô para neutralizar aposta #{aposta_id}. Motivo Grok IA: {motivo_clean[:180]} || HEDGE_ORIGEM: #{aposta_id} || CLOUDBET_BET_ID: {bet_id_hedge} || STATUS GK: APROVADO\nSUGGESTION: {opposite_palpite}\nREASON: Hedge Real Pré-Jogo emitido."

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, moeda_codigo, fixture_id, time_casa, time_fora, mercado,
                    casa_de_aposta, palpite, odd, odd_justa, status_gatekeeper, gatekeeper_category,
                    data_hora_jogo, valor_aposta, ganhos_potenciais, tipo, status,
                    resultado_detalhado, criado_em, updated_at, confirmada
                ) VALUES (
                    %s, %s, %s, %s, %s, %s,
                    'Cloudbet', %s, %s, %s, 'APROVADO', 'Hedge Real Pré-Jogo',
                    %s, %s, %s, 'Hedge Real', 'Pendente',
                    %s, NOW(), NOW(), 1
                )
            """, (
                usuario_id, client.currency, f_id, home, away, bet.get('mercado', 'Handicap Asiático'),
                opposite_palpite, hedge_price, hedge_price, data_jogo_hedge, hedge_stake, round(hedge_stake * hedge_price, 2),
                detalhado_hedge
            ))
            new_hedge_aposta_id = cursor.lastrowid

            # Registra débito da nova aposta de Hedge na conta corrente
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
            row_u = cursor.fetchone()
            s_ant = float(row_u['saldo_conta_corrente']) if row_u and row_u.get('saldo_conta_corrente') else 0.0
            s_post = round(s_ant - hedge_stake, 8)
            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (usuario_id, new_hedge_aposta_id, f"Débito Aposta #{new_hedge_aposta_id} (Hedge Real Pré-Jogo: {home} x {away} - {opposite_palpite})", -hedge_stake, s_ant, s_post, client.currency))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, usuario_id))

            # Atualiza a aposta original registrando a cobertura cruzada e a chave de idempotência id_cross_edge_slip
            cursor.execute("""
                UPDATE apostas
                SET id_cross_edge_slip = %s,
                    resultado_detalhado = CONCAT(COALESCE(resultado_detalhado, ''), %s),
                    updated_at = NOW()
                WHERE id = %s
            """, (str(bet_id_hedge), f" || HEDGE_COBERTO_POR: #{new_hedge_aposta_id} || SLIP: {bet_id_hedge}", aposta_id))

            if conn:
                conn.commit()
            print(f"   💰 [Débito Hedge Salvo] -{hedge_stake:.2f} {client.currency} debitado para registro da aposta de cobertura #{new_hedge_aposta_id}.")
        except pymysql.err.IntegrityError as e_int:
            if conn:
                conn.rollback()
            print(f"   🛑 [CONSTRAINT UNIQUE MySQL Barrou Duplicidade no Hedge Pré-Jogo] {opposite_palpite}: {e_int}. Rollback executado.")
            return {"success": False, "motivo": f"CONSTRAINT UNIQUE barrou duplicidade: {e_int}"}
        except Exception as e_tx:
            if conn:
                conn.rollback()
            print(f"   ❌ [Falha Transacional no Hedge Pré-Jogo]: {e_tx}. Rollback executado.")
            return {"success": False, "motivo": str(e_tx)}

        tit_push = f"🛡️ Hedge Real Pré-Jogo Executado: {home} vs {away}"
        msg_push = (
            f"O Grok IA identificou desequilíbrio crítico na súmula oficial de {home} x {away}. "
            f"Aposta de cobertura #{new_hedge_aposta_id} ({opposite_palpite} @ {hedge_price:.2f}) comprada na Cloudbet para neutralizar a aposta #{aposta_id}!"
        )
        enviar_push_cashout(
            titulo=tit_push,
            mensagem=msg_push,
            fixture_id=f_id,
            aposta_id=new_hedge_aposta_id,
            prioridade=5
        )

        try:
            from lifecycle_logger import log_lifecycle_event
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=f_id,
                aposta_id=new_hedge_aposta_id,
                notificacao_id=None,
                notificacao_criada_em=None,
                componente='GROK_SENTINEL_HEDGE',
                tipo_evento='HEDGE_ORDEM_REAL_EXECUTADA',
                estado_anterior='Pendente',
                estado_novo='Pendente',
                delta={'bet_id': bet_id, 'hedge_aposta_id': new_hedge_aposta_id, 'stake': hedge_stake, 'price': hedge_price},
                motivo=motivo_tatica
            )
            if conn:
                conn.commit()
        except Exception as e_lf:
            print(f"⚠️ [Erro Lifecycle Logger Hedge Pré-Jogo #{new_hedge_aposta_id}]: {e_lf}")

        print(f"✅ [Hedge Real Pré-Jogo Concluído] Aposta #{new_hedge_aposta_id} criada e posição travada com sucesso!")
        return {"success": True, "hedge_aposta_id": new_hedge_aposta_id, "hedge_info": hedge_info}

    except Exception as e_h:
        print(f"❌ [Erro Hedge Pré-Jogo]: {e_h}")
        return {"success": False, "motivo": str(e_h)}
    finally:
        if owns_conn and conn:
            cursor.close()
            conn.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Monitor de Cash Out In-Play 100% Autônomo da Cloudbet")
    parser.add_argument("--loop", action="store_true", help="Executa em loop contínuo a cada 30 segundos")
    parser.add_argument("--real", action="store_true", help="Força a execução financeira real na API")
    args = parser.parse_args()

    dry_run_opt = False if args.real else None

    if args.loop:
        print("🔁 [Modo Daemon Contínuo] Monitorando cash outs a cada 30 segundos. Pressione CTRL+C para sair.")
        while True:
            try:
                check_and_execute_cloudbet_live_cashouts(dry_run_override=dry_run_opt)
                time.sleep(30)
            except KeyboardInterrupt:
                print("\n🛑 Monitor de cash out interrompido pelo usuário.")
                break
            except Exception as e:
                print(f"⚠️ Erro inesperado no loop de cash out: {e}")
                time.sleep(30)
    else:
        check_and_execute_cloudbet_live_cashouts(dry_run_override=dry_run_opt)
