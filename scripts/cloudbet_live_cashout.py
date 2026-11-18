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
from datetime import datetime
from typing import Dict, Any, Optional, List, Tuple

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars
from cloudbet_client import CloudbetClient
from asian_handicap_engine import compose_compound_ah_reasoning

# Caches em memória para evitar chamadas de API repetidas e auditorias redundantes no in-play
_LAST_STATS_FETCH: Dict[int, float] = {}
_LAST_TEMP_AUDIT_LOG: Dict[str, bool] = {}


def fetch_inplay_match_statistics(
    fixture_id: int,
    home_team_id: Optional[int] = None,
    away_team_id: Optional[int] = None,
    cursor = None,
    conn = None
) -> Dict[str, int]:
    """
    Busca estatísticas in-play (chutes no gol, chutes totais, escanteios) diretamente
    na API-Sports para partidas com apostas ativas em andamento, respeitando cache de 180s.
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
        ch, ca = 0, 0

        for team_st in data:
            t_id = team_st.get("team", {}).get("id")
            is_home = (t_id == home_team_id) if home_team_id else (team_st == data[0])
            stats_list = team_st.get("statistics", [])
            sg, s_tot, ck = 0, 0, 0
            for s in stats_list:
                stype = (s.get("type") or "").strip().lower()
                sval = s.get("value")
                if sval is not None:
                    try:
                        val_int = int(sval)
                        if "corner" in stype:
                            ck = val_int
                        elif "shots on goal" in stype or "shots on target" in stype:
                            sg = val_int
                        elif "total shots" in stype or stype == "shots":
                            s_tot = val_int
                    except (ValueError, TypeError):
                        pass
            effective_shots = sg if sg > 0 else s_tot
            if is_home:
                sh = effective_shots
                ch = ck
            else:
                sa = effective_shots
                ca = ck

        if cursor and conn:
            cursor.execute("""
                UPDATE fixtures_trends
                SET shots_home = %s, shots_away = %s,
                    corners_home = %s, corners_away = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (sh, sa, ch, ca, fixture_id))
            conn.commit()

        return {
            "shots_home": sh,
            "shots_away": sa,
            "corners_home": ch,
            "corners_away": ca,
            "total_shots": sh + sa,
            "total_corners": ch + ca
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
    fixture_date: Optional[Any] = None
) -> Tuple[Optional[str], Optional[str], float, float, Optional[str]]:
    """
    Localiza o evento e a seleção da ponta oposta com cotação e liquidez na Cloudbet.
    Suporta tanto apostas nativas da Cloudbet quanto Hedge Cruzado para apostas Betano/outras casas.
    Retorna: (event_id, opposite_market_url, price, min_stake, opposite_palpite)
    """
    opp_market_url = None
    opp_palpite = None
    price = 0.0
    min_stake = 0.10

    # 1. Se já temos market_url nativa Cloudbet, deriva a ponta oposta
    if market_url:
        opp_market_url = derive_opposite_market_url(market_url)

    # 2. Se temos event_id e opp_market_url, tenta a cotação direta
    if event_id and opp_market_url:
        quote = get_live_opposite_quote(client, str(event_id), opp_market_url)
        if quote:
            opposite_team = home if away.lower() in palpite.lower() else away
            opp_palpite = f"{opposite_team} 0.0 AH" if "0.0" in palpite else f"Hedge oposto ({opp_market_url})"
            return str(event_id), opp_market_url, float(quote['price']), float(quote['min_stake']), opp_palpite

    # 3. Hedge Cruzado via CloudbetMatcher (procura o evento e o mercado oposto)
    try:
        from cloudbet_matcher import CloudbetMatcher
        matcher = CloudbetMatcher(client)
        matched = matcher.find_matching_event(home, away, fixture_date=fixture_date)
        if matched:
            matched_event_id = str(matched.get("event_id") or matched.get("id") or "")
            if matched_event_id:
                cb_markets = matcher.get_event_markets(int(matched_event_id))

                # Caso Handicap Asiático
                if "ah" in palpite.lower() or "handicap" in palpite.lower() or "0.0" in palpite:
                    is_home_bet = (home.lower() in palpite.lower())
                    opp_outcome = "away" if is_home_bet else "home"
                    opp_team = away if is_home_bet else home

                    m_hcap = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite)
                    hcap_val = float(m_hcap.group(1)) if m_hcap else 0.0
                    opp_hcap = -hcap_val

                    ah_dict = cb_markets.get("asian_handicap", {})
                    sel_cand = None
                    if opp_hcap in ah_dict and opp_outcome in ah_dict[opp_hcap]:
                        sel_cand = ah_dict[opp_hcap][opp_outcome]
                    elif 0.0 in ah_dict and opp_outcome in ah_dict[0.0]:
                        sel_cand = ah_dict[0.0][opp_outcome]
                        opp_hcap = 0.0
                    elif ah_dict:
                        for h_k, h_v in ah_dict.items():
                            if opp_outcome in h_v:
                                sel_cand = h_v[opp_outcome]
                                opp_hcap = h_k
                                break

                    if sel_cand:
                        opp_market_url = sel_cand["market_url"]
                        price = float(sel_cand["price"])
                        min_stake = float(sel_cand["min_stake"])
                        opp_palpite = f"{opp_team} 0.0 AH" if opp_hcap == 0.0 else f"{opp_team} {opp_hcap:+.2f} AH"
                        return matched_event_id, opp_market_url, price, min_stake, opp_palpite

                # Caso Total de Gols (Over / Under)
                elif "gol" in palpite.lower() or "over" in palpite.lower() or "under" in palpite.lower():
                    m_tot = re.search(r'(\d+(?:\.\d+)?)', palpite)
                    tot_val = float(m_tot.group(1)) if m_tot else 2.5
                    is_over = ("mais" in palpite.lower() or "over" in palpite.lower())
                    opp_tot_outcome = "under" if is_over else "over"

                    tg_dict = cb_markets.get("total_goals", {})
                    sel_cand = None
                    if tot_val in tg_dict and opp_tot_outcome in tg_dict[tot_val]:
                        sel_cand = tg_dict[tot_val][opp_tot_outcome]
                    elif tg_dict:
                        for t_k, t_v in tg_dict.items():
                            if opp_tot_outcome in t_v:
                                sel_cand = t_v[opp_tot_outcome]
                                tot_val = t_k
                                break

                    if sel_cand:
                        opp_market_url = sel_cand["market_url"]
                        price = float(sel_cand["price"])
                        min_stake = float(sel_cand["min_stake"])
                        opp_palpite = f"Menos de {tot_val} Gols Asiáticos" if opp_tot_outcome == "under" else f"Mais de {tot_val} Gols Asiáticos"
                        return matched_event_id, opp_market_url, price, min_stake, opp_palpite
    except Exception as e_m:
        print(f"⚠️ [Cross-Hedge Matcher Error] Falha ao resolver mercado oposto na Cloudbet: {e_m}")

    return None, None, 0.0, 0.10, None


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

        # Cenário B: Desvantagem de 2 ou mais gols no 2º tempo (50'+)
        if diff_gols <= -2 and elapsed >= 50:
            motivo = f"Hedge Real Necessário: Desvantagem crítica de 2+ gols ({gh}x{ga}) aos {elapsed}'."
            return True, motivo

        # Cenário C: Expulsão Crítica (Cartão Vermelho) do time apostado no 2º tempo
        rc_home = int(bet_row.get('red_cards_home') or 0)
        rc_away = int(bet_row.get('red_cards_away') or 0)
        has_red = rc_away > 0 if is_away else rc_home > 0
        if has_red and elapsed >= 46 and diff_gols <= 0:
            motivo = f"Hedge Real de Gestão de Risco: {team_name} com jogador expulso e sem vantagem no placar ({gh}x{ga}) aos {elapsed}'."
            return True, motivo

    # =========================================================================
    # 2. CENÁRIOS DE TOTAL DE GOLS (OVER / UNDER)
    # =========================================================================
    elif 'gol' in mercado or 'over' in palpite.lower() or 'under' in palpite.lower():
        is_over = 'over' in palpite.lower() or 'mais' in palpite.lower()
        m_tot = re.search(r'(\d+(?:\.\d+)?)', palpite)
        line_total = float(m_tot.group(1)) if m_tot else 2.5

        if is_over:
            # A) Inferência de Ritmo e Temperatura do Jogo (Janela de Ouro: 52' a 68')
            sh = int((stats_inplay.get('shots_home') if stats_inplay else None) or bet_row.get('shots_home') or 0)
            sa = int((stats_inplay.get('shots_away') if stats_inplay else None) or bet_row.get('shots_away') or 0)
            ch = int((stats_inplay.get('corners_home') if stats_inplay else None) or bet_row.get('corners_home') or 0)
            ca = int((stats_inplay.get('corners_away') if stats_inplay else None) or bet_row.get('corners_away') or 0)
            tot_shots = sh + sa
            tot_corners = ch + ca
            f_id = bet_row.get('fixture_id')
            ap_id = bet_row.get('id')

            if 52 <= elapsed <= 68 and f_id:
                window_bucket = "50_60" if elapsed < 60 else "60_70"
                audit_key = f"{f_id}_{window_bucket}"

                # Cenário Jogo Morno / Travado (Cold Game) -> Dispara Hedge Antecipado
                if total_gols == 0 and tot_shots <= 4 and tot_corners <= 3:
                    motivo_morno = (
                        f"Temperatura do Jogo: Jogo Morno/Travado (Placar: {gh}x{ga} | Finalizações: {sh}x{sa} | Escanteios: {ch}x{ca}). "
                        f"Ritmo insuficiente para sustentar aposta de Mais de {line_total} Gols. Antecipando Hedge de Under para capturar odds líquidas."
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
                                delta={
                                    'minuto': elapsed,
                                    'fase': '2H',
                                    'placar': f"{gh}x{ga}",
                                    'shots_home': sh,
                                    'shots_away': sa,
                                    'corners_home': ch,
                                    'corners_away': ca,
                                    'total_shots': tot_shots,
                                    'total_corners': tot_corners,
                                    'temperatura': 'MORNO',
                                    'decisao': 'DISPARAR_HEDGE_ANTECIPADO'
                                },
                                motivo=f"🌡️ {motivo_morno}"
                            )
                            conn.commit()
                            _LAST_TEMP_AUDIT_LOG[audit_key] = True
                        except Exception as e_lt:
                            print(f"⚠️ [Erro Auditoria Temperatura Jogo Morno]: {e_lt}")

                    return True, motivo_morno

                # Cenário Jogo Quente / Pressão Ofensiva Alta -> Mantém aposta
                elif tot_shots >= 6 or tot_corners >= 4 or total_gols > 0:
                    motivo_quente = (
                        f"Temperatura do Jogo: Jogo Quente/Pressão Ofensiva (Placar: {gh}x{ga} | Finalizações: {sh}x{sa} | Escanteios: {ch}x{ca}). "
                        f"Volume ofensivo consistente. Posição mantida sem hedge antecipado."
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
                                delta={
                                    'minuto': elapsed,
                                    'fase': '2H',
                                    'placar': f"{gh}x{ga}",
                                    'shots_home': sh,
                                    'shots_away': sa,
                                    'corners_home': ch,
                                    'corners_away': ca,
                                    'total_shots': tot_shots,
                                    'total_corners': tot_corners,
                                    'temperatura': 'QUENTE',
                                    'decisao': 'MANTER_APOSTA'
                                },
                                motivo=f"🌡️ {motivo_quente}"
                            )
                            conn.commit()
                            _LAST_TEMP_AUDIT_LOG[audit_key] = True
                        except Exception as e_lt:
                            print(f"⚠️ [Erro Auditoria Temperatura Jogo Quente]: {e_lt}")

            # B) Seca Prolongada (Fallback clássico para 72'+)
            if total_gols == 0 and elapsed >= 72:
                motivo = f"Seca Prolongada: Placar 0x0 aos {elapsed}' em aposta {palpite}. Hedge para Under necessário."
                return True, motivo
        else:
            if total_gols == int(line_total) and elapsed <= 75:
                motivo = f"Atenção ao Under: Apenas 1 gol restante para o Red ({gh}x{ga}) aos {elapsed}'. Hedge para Over necessário."
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
               a.casa_de_aposta,
               ft.status as fixture_status, ft.home_team as time_casa, ft.away_team as time_fora,
               ft.home_team_id, ft.away_team_id, ft.ah_reasoning,
               ft.goals_home, ft.goals_away, ft.elapsed,
               ft.yellow_cards_home, ft.yellow_cards_away,
               ft.red_cards_home, ft.red_cards_away,
               ft.corners_home, ft.corners_away,
               ft.shots_home, ft.shots_away, ft.fixture_date
        FROM apostas a
        INNER JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
        WHERE a.status = 'Pendente'
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

        # Se a partida estiver no 2º tempo (janela in-play crítica >= 48'), busca/atualiza chutes e escanteios in-play
        stats_inplay = {}
        if elapsed and elapsed >= 48:
            stats_inplay = fetch_inplay_match_statistics(
                fixture_id=f_id,
                home_team_id=bet.get('home_team_id'),
                away_team_id=bet.get('away_team_id'),
                cursor=cursor,
                conn=conn
            )

        should_hedge, motivo = evaluate_live_cashout_trigger(
            bet_row=bet,
            stats_inplay=stats_inplay,
            cursor=cursor,
            conn=conn
        )

        if not should_hedge:
            print(f"   🟢 Cenário Seguro / Controlado: {motivo}")
            continue

        print(f"   🚨 [GATILHO DE HEDGE ATIVADO]: {motivo}")

        # Localiza o evento e cotação oposta na Cloudbet (para Cloudbet nativa ou Hedge Cruzado Betano)
        cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
        bet_id = str(cb_meta.get('bet_id') or f"cross_hedge_{aposta_id}")
        meta_event_id = str(cb_meta.get('event_id') or '')
        meta_market_url = str(cb_meta.get('market_url') or '')

        event_id, opposite_market_url, hedge_price, min_stake, opp_palpite = find_cloudbet_opposite_market_and_quote(
            client=client,
            home=home,
            away=away,
            palpite=palpite,
            market_url=meta_market_url,
            event_id=meta_event_id,
            fixture_date=bet.get('fixture_date') or bet.get('data_hora_jogo')
        )

        if not event_id or not opposite_market_url:
            print(f"   ℹ️ [{casa_nome}] Evento sem mercado oposto líquido na Cloudbet. Enviando alerta mobile...")
            tit = f"⚡ Alerta de Hedge ({casa_nome}): {home} vs {away}"
            msg = f"Aposta {palpite} aos {elapsed}' ({gh}x{ga}). Avalie cobertura na ponta oposta. Motivo: {motivo}"
            enviar_push_cashout(tit, msg, fixture_id=f_id, aposta_id=aposta_id, prioridade=5)
            try:
                from lifecycle_logger import log_lifecycle_event
                fase_calc = '2H' if elapsed > 45 else '1H'
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=aposta_id,
                    componente='CLOUDBET_LIVE_HEDGE',
                    tipo_evento='HEDGE_ABORTADO_SEM_LIQUIDEZ',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    minuto_jogo=elapsed,
                    fase_jogo=fase_calc,
                    placar_momento=f"{gh}x{ga}",
                    delta={'elapsed': elapsed, 'minuto': elapsed, 'fase': fase_calc, 'placar': f"{gh}x{ga}", 'motivo_trigger': motivo},
                    motivo=f"Gatilho de Hedge acionado aos {elapsed}' ({gh}x{ga}), mas abortado: Mercado oposto não localizado ou sem liquidez na Cloudbet. Avalie cashout manual na {casa_nome}."
                )
                conn.commit()
            except Exception as e_log:
                print(f"⚠️ [Erro Lifecycle Logger Hedge Abortado]: {e_log}")
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

        min_hedge_odd = float(os.environ.get("CLOUDBET_HEDGE_MIN_ODD", "1.65"))
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

        print(f"   🚀 [Disparando Hedge Real via API Cloudbet] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
        res_co = client.execute_cashout(
            bet_id=bet_id,
            event_id=event_id,
            market_url=opposite_market_url,
            price=hedge_price,
            stake=hedge_stake,
            dry_run=client.dry_run
        )

        if not client.dry_run and not res_co.get("success") and not res_co.get("referenceId"):
            print(f"   ❌ Falha ao processar ordem de cobertura na API Cloudbet: {res_co}")
            total_erros += 1
            continue

        # Inserção da Nova Aposta Real de Cobertura no Banco de Dados
        cursor.execute("""
            INSERT INTO apostas (
                usuario_id, moeda_codigo, fixture_id, time_casa, time_fora, mercado,
                casa_de_aposta, palpite, odd, odd_justa, status_gatekeeper, gatekeeper_category,
                data_hora_jogo, valor_aposta, ganhos_potenciais, tipo, status,
                resultado_detalhado, criado_em, updated_at, confirmada
            ) VALUES (
                %s, %s, %s, %s, %s, %s,
                'Cloudbet', %s, %s, %s, 'APROVADO', 'Hedge Real In-Play',
                NOW(), %s, %s, 'Hedge Real', 'Pendente',
                %s, NOW(), NOW(), 1
            )
        """, (
            bet.get('usuario_id'), cur, f_id, home, away, bet.get('mercado', 'Handicap Asiático'),
            opposite_palpite, hedge_price, hedge_price, hedge_stake, round(hedge_stake * hedge_price, 2),
            f"Bilhete de Hedge Real emitido pelo robô para neutralizar a aposta #{aposta_id}. || HEDGE_ORIGEM: #{aposta_id}"
        ))
        new_hedge_aposta_id = cursor.lastrowid

        # Registra débito da nova aposta de Hedge na conta corrente
        try:
            uid = bet.get('usuario_id')
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
            row_u = cursor.fetchone()
            s_ant = float(row_u['saldo_conta_corrente']) if row_u and row_u.get('saldo_conta_corrente') else 0.0
            s_post = round(s_ant - hedge_stake, 8)
            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (uid, new_hedge_aposta_id, f"Débito Aposta #{new_hedge_aposta_id} (Hedge Real: {home} x {away} - {opposite_palpite})", -hedge_stake, s_ant, s_post, cur))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))
            conn.commit()
            print(f"   💰 [Débito Hedge Salvo] -{hedge_stake:.2f} {cur} debitado para registro da aposta de cobertura #{new_hedge_aposta_id}.")
        except Exception as e_deb:
            print(f"   ⚠️ [Erro Débito Hedge]: {e_deb}")

        # Atualiza a aposta original registrando a cobertura cruzada
        cursor.execute("""
            UPDATE apostas
            SET resultado_detalhado = CONCAT(COALESCE(resultado_detalhado, ''), %s),
                updated_at = NOW()
            WHERE id = %s
        """, (f" || HEDGE_COBERTO_POR: #{new_hedge_aposta_id}", aposta_id))
        conn.commit()

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

    if client is None:
        client = CloudbetClient()

    owns_conn = False
    if cursor is None:
        conn = get_db_connection()
        cursor = conn.cursor()
        owns_conn = True

    try:
        print(f"\n🛡️ [Iniciando Hedge Pré-Jogo Autônomo] Aposta #{aposta_id} ({home} vs {away}) | Palpite: {palpite}")
        print(f"   Motivo Grok IA: {motivo_tatica[:200]}...")

        cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
        bet_id = str(cb_meta.get('bet_id') or f"cross_pregame_{aposta_id}")
        meta_event_id = str(cb_meta.get('event_id') or '')
        meta_market_url = str(cb_meta.get('market_url') or '')

        event_id, opposite_market_url, hedge_price, min_stake, opp_palpite = find_cloudbet_opposite_market_and_quote(
            client=client,
            home=home,
            away=away,
            palpite=palpite,
            market_url=meta_market_url,
            event_id=meta_event_id,
            fixture_date=bet.get('fixture_date') or bet.get('data_hora_jogo')
        )

        hedge_info = {}
        if not event_id or not opposite_market_url:
            print(f"   ⚠️ [Hedge Cloudbet Pré-Jogo] Evento/Mercado oposto não localizado na Cloudbet para partida #{f_id} ({home} vs {away}).")
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

        print(f"   🚀 [Disparando Ordem Real Pré-Jogo via API Cloudbet] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
        res_co = client.execute_cashout(
            bet_id=bet_id,
            event_id=event_id,
            market_url=opposite_market_url,
            price=hedge_price,
            stake=hedge_stake,
            dry_run=client.dry_run
        )

        if not client.dry_run and not res_co.get("success") and not res_co.get("referenceId"):
            print(f"   ❌ Falha ao processar ordem de hedge pré-jogo na API Cloudbet: {res_co}")
            return {"success": False, "motivo": "Falha na API Cloudbet"}

        hedge_info = {
            "mode": "dry_run_pregame_hedge" if client.dry_run else "real_pregame_hedge",
            "hedge_ref": res_co.get("referenceId") or res_co.get("betId"),
            "market_url": opposite_market_url,
            "price": hedge_price,
            "stake": hedge_stake
        }

        # Inserção da Nova Aposta Real de Cobertura Pré-Jogo no Banco de Dados
        cursor.execute("""
            INSERT INTO apostas (
                usuario_id, moeda_codigo, fixture_id, time_casa, time_fora, mercado,
                casa_de_aposta, palpite, odd, odd_justa, status_gatekeeper, gatekeeper_category,
                data_hora_jogo, valor_aposta, ganhos_potenciais, tipo, status,
                resultado_detalhado, criado_em, updated_at, confirmada
            ) VALUES (
                %s, %s, %s, %s, %s, %s,
                'Cloudbet', %s, %s, %s, 'APROVADO', 'Hedge Real Pré-Jogo',
                NOW(), %s, %s, 'Hedge Real', 'Pendente',
                %s, NOW(), NOW(), 1
            )
        """, (
            bet.get('usuario_id'), client.currency, f_id, home, away, bet.get('mercado', 'Handicap Asiático'),
            opposite_palpite, hedge_price, hedge_price, hedge_stake, round(hedge_stake * hedge_price, 2),
            f"Bilhete de Hedge Real Pré-Jogo emitido pelo robô para neutralizar aposta #{aposta_id}. Motivo Grok IA: {motivo_clean[:180]} || HEDGE_ORIGEM: #{aposta_id}"
        ))
        new_hedge_aposta_id = cursor.lastrowid

        # Registra débito da nova aposta de Hedge na conta corrente
        try:
            uid = bet.get('usuario_id')
            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
            row_u = cursor.fetchone()
            s_ant = float(row_u['saldo_conta_corrente']) if row_u and row_u.get('saldo_conta_corrente') else 0.0
            s_post = round(s_ant - hedge_stake, 8)
            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, moeda_codigo, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (uid, new_hedge_aposta_id, f"Débito Aposta #{new_hedge_aposta_id} (Hedge Real Pré-Jogo: {home} x {away} - {opposite_palpite})", -hedge_stake, s_ant, s_post, client.currency))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))
            if conn:
                conn.commit()
            print(f"   💰 [Débito Hedge Salvo] -{hedge_stake:.2f} {client.currency} debitado para registro da aposta de cobertura #{new_hedge_aposta_id}.")
        except Exception as e_deb:
            print(f"   ⚠️ [Erro Débito Hedge]: {e_deb}")

        # Atualiza a aposta original registrando a cobertura cruzada
        cursor.execute("""
            UPDATE apostas
            SET resultado_detalhado = CONCAT(COALESCE(resultado_detalhado, ''), %s),
                updated_at = NOW()
            WHERE id = %s
        """, (f" || HEDGE_COBERTO_POR: #{new_hedge_aposta_id}", aposta_id))

        if conn:
            conn.commit()

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
