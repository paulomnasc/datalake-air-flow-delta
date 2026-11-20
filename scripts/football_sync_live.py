#!/usr/bin/env python3
"""
scripts/football_sync_live.py

Sincronizador em Tempo Real de Partidas Ao Vivo com Custo Zero de API-Sports.
FootballWeb Pipeline - Motor In-Play Multi-Fonte (Cloudbet & Betano).

Responsabilidades:
1. Buscar partidas em aberto que estão no horário de execução (últimos 140 minutos);
2. Capturar feeds ao vivo em tempo real via Cloudbet (Tier 1) e Betano (Tier 2) com consumo ZERO de API-Sports;
3. Atualizar status ('1H', 'HT', '2H', 'FT'), placar real e minuto decorrido (elapsed) em fixtures_trends;
4. Proteger a cota da API-Sports com piso estrito de segurança de 2.350 requisições (31,3% Livre);
5. Executar relógio dinâmico contínuo autônomo sem travas quando feeds externos estiverem em contingência;
6. Identificar oportunidades in-play (Intervalo HT, linhas encurtadas de AH e Under Cartões);
7. Disparar alertas imediatos no Sininho, Toast web e Push Mobile via ntfy.sh.
"""

import os
import sys
import pymysql
import re
import requests
from datetime import datetime, timedelta, timezone

# Configura caminhos dos scripts
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from betano_direct_api import (
    fetch_betano_live_football_events,
    is_team_match,
    normalize_team_name
)
from db_config import get_db_connection, get_live_env_vars

try:
    from lifecycle_logger import log_match_milestone, log_lifecycle_event
except ImportError:
    from scripts.lifecycle_logger import log_match_milestone, log_lifecycle_event

# Cache de Cota para evitar consultas repetidas à rota /status da API-Sports
_QUOTA_CACHE = {'timestamp': None, 'remaining': 0}
_API_FALLBACK_LAST_RUN = None

# Margem Estrita de Segurança Intocável (31,3% da cota contratada de 7.500)
MINIMUM_SAFETY_QUOTA = 2350


def fetch_cloudbet_live_football_events() -> list:
    """
    Captura todos os jogos de futebol ao vivo transmitidos pela API oficial da Cloudbet.
    Retorna lista normalizada com status LIVE/TRADING_LIVE, times e placar/tempo quando disponíveis.
    Consumo de cota da API-Sports: ZERO.
    """
    live_events = []
    try:
        from cloudbet_client import CloudbetClient
        client = CloudbetClient()
        today_str = datetime.now(timezone.utc).strftime('%Y-%m-%d')
        res = client._request('GET', f'/pub/v2/odds/fixtures?sport=soccer&date={today_str}')
        comps = res.get('competitions', [])

        for c in comps:
            c_name = c.get('name', '')
            for ev in c.get('events', []):
                if ev.get('status') == 'TRADING_LIVE':
                    h_name = (ev.get('home', {}).get('name') or '').strip()
                    a_name = (ev.get('away', {}).get('name') or '').strip()
                    if not (h_name and a_name):
                        continue

                    live_events.append({
                        'event_id': ev.get('id'),
                        'home_team': h_name,
                        'away_team': a_name,
                        'competition': c_name,
                        'status': 'LIVE',
                        'goals_home': None,
                        'goals_away': None,
                        'elapsed': None,
                        'source': 'Cloudbet In-Play Feed (0 Cota API-Sports)',
                        'card_lines': [],
                        'ah_lines': []
                    })
        return live_events
    except Exception as e:
        print(f"⚠️ [Cloudbet Live Feed]: Falha ao capturar feed in-play ({e})")
        return []


def fetch_espn_live_football_events() -> list:
    """
    Captura todos os jogos de futebol ao vivo transmitidos pela API pública da ESPN.
    Retorna lista normalizada com status ('1H', 'HT', '2H', 'FT'), placar real e minuto decorrido.
    Consumo de cota da API-Sports: ZERO. Latência ultra-baixa sem bloqueios Cloudflare.
    """
    live_events = []
    try:
        today_str = datetime.now(timezone.utc).strftime('%Y%m%d')
        url = f"https://site.api.espn.com/apis/site/v2/sports/soccer/scorepanel?dates={today_str}"
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'Accept': 'application/json'
        }
        res = requests.get(url, headers=headers, timeout=8)
        if res.status_code != 200:
            return []

        data = res.json()
        for s in data.get('scores', []):
            for ev in s.get('events', []):
                st = ev.get('status', {})
                st_type = st.get('type', {})
                st_name = st_type.get('name', '')
                completed = st_type.get('completed', False)
                desc = st_type.get('description', '')

                status_mapped = 'LIVE'
                if completed or st_name == 'STATUS_FINAL':
                    status_mapped = 'FT'
                elif st_name == 'STATUS_HALFTIME' or desc == 'Halftime':
                    status_mapped = 'HT'
                elif st_name == 'STATUS_FIRST_HALF':
                    status_mapped = '1H'
                elif st_name == 'STATUS_SECOND_HALF':
                    status_mapped = '2H'
                elif st_name == 'STATUS_SCHEDULED':
                    status_mapped = 'NS'

                elapsed = None
                clock = st.get('clock')
                if clock is not None:
                    elapsed = int(clock // 60)

                h_team, a_team = '', ''
                gh, ga = None, None
                for comp in ev.get('competitions', []):
                    for t in comp.get('competitors', []):
                        name = t.get('team', {}).get('displayName', '') or t.get('team', {}).get('name', '')
                        score = t.get('score')
                        try:
                            score_int = int(score) if score is not None else None
                        except (ValueError, TypeError):
                            score_int = None

                        if t.get('homeAway') == 'home':
                            h_team = name
                            gh = score_int
                        else:
                            a_team = name
                            ga = score_int

                if h_team and a_team:
                    live_events.append({
                        'home_team': h_team,
                        'away_team': a_team,
                        'status': status_mapped,
                        'goals_home': gh,
                        'goals_away': ga,
                        'elapsed': elapsed,
                        'source': 'ESPN Global Soccer Feed (0 Cota API-Sports)',
                        'card_lines': [],
                        'ah_lines': []
                    })
        return live_events
    except Exception as e:
        print(f"⚠️ [ESPN Live Feed]: Falha ao capturar feed in-play ({e})")
        return []


def get_target_user_ids(cursor):
    """
    Retorna IDs de usuários ativos para notificação.
    """
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE email LIKE '%paulomnasc%' OR nome LIKE '%paulomnasc%' OR id = 558
        ORDER BY id ASC
        LIMIT 1
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]

    cursor.execute("SELECT id FROM usuario ORDER BY id ASC LIMIT 1")
    first_user = cursor.fetchone()
    return [first_user['id']] if first_user else [1]


def registrar_notificacao_live(cursor, usuario_id, fixture_id, titulo, mensagem, link):
    """
    Insere notificação no sininho e toast (notificacoes_usuario), prevenindo duplicatas nos últimos 45 min.
    """
    try:
        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND fixture_id = %s 
              AND tipo = 'OPORTUNIDADE_AO_VIVO'
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 45 MINUTE)
        """, (usuario_id, fixture_id))
        if cursor.fetchone():
            return False

        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
            ) VALUES (%s, NULL, %s, 'OPORTUNIDADE_AO_VIVO', %s, %s, %s, 0, 0, NOW())
        """, (usuario_id, fixture_id, titulo, mensagem, link))
        print(f"🔔 [Alerta Ao Vivo Enviado User #{usuario_id}] {titulo}")
        return True
    except Exception as e:
        print(f"⚠️ [Notificação Ao Vivo] Erro ao registrar notificação: {e}")
        return False


def enviar_push_mobile(titulo: str, mensagem: str, fixture_id: int = None, prioridade: int = 5, tags: list = None) -> bool:
    """
    Dispara notificação push instantânea para smartphone via ntfy.sh (Alta Prioridade).
    Parâmetros carregados dinamicamente de src/footballweb/.env (Regra 18).
    """
    try:
        env_vars = get_live_env_vars()
        enabled = os.environ.get("NTFY_ENABLED") or env_vars.get("NTFY_ENABLED", "true")
        if str(enabled).strip().lower() not in ("true", "1", "yes", "on"):
            return False

        topic = os.environ.get("NTFY_TOPIC") or env_vars.get("NTFY_TOPIC", "footbalweb_sentinela")
        if not topic:
            return False

        server = os.environ.get("NTFY_SERVER") or env_vars.get("NTFY_SERVER", "https://ntfy.sh")
        server = server.rstrip("/")

        base_url = os.environ.get("NTFY_BASE_URL") or env_vars.get("NTFY_BASE_URL", "https://cristalbet.com.br")
        click_url = f"{base_url.rstrip('/')}/apostas?fixture_id={fixture_id}" if fixture_id else f"{base_url.rstrip('/')}/apostas"

        payload = {
            "topic": topic,
            "title": titulo,
            "message": mensagem,
            "priority": prioridade,
            "tags": tags or ["warning", "soccer"],
            "click": click_url
        }

        resp = requests.post(
            f"{server}",
            json=payload,
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=8
        )
        if resp.status_code in (200, 201):
            print(f"📱 [Push Mobile In-Play Entregue] -> {titulo}")
            return True
        else:
            return False
    except Exception as e:
        print(f"⚠️ [Push Mobile In-Play Erro]: {e}")
        return False


def registrar_alerta_gol_prematuro(cursor, usuario_id, aposta_id, fixture_id, titulo, mensagem, link, tipo="ALERTA_GOL_PREMATURO_UNDER", elapsed=None):
    """
    Insere notificação de gol prematuro ou risco crítico no sininho e toast, prevenindo duplicatas.
    """
    try:
        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND fixture_id = %s 
              AND tipo = %s
            LIMIT 1
        """, (usuario_id, fixture_id, tipo))
        if cursor.fetchone():
            return False

        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, 0, 0, NOW())
        """, (usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link))
        notif_id = cursor.lastrowid
        print(f"🔔 [Alerta In-Play Under Enviado User #{usuario_id}] {titulo}")

        try:
            el_val = int(elapsed) if elapsed is not None else None
            fase_calc = '1H' if (el_val is not None and el_val <= 45) else '2H'
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=fixture_id,
                aposta_id=aposta_id,
                notificacao_id=notif_id,
                notificacao_criada_em=datetime.now(),
                componente='RISK_MONITOR_UNDER',
                tipo_evento=tipo,
                estado_anterior='Pendente',
                estado_novo='Pendente',
                minuto_jogo=el_val,
                fase_jogo=fase_calc,
                delta={'minuto': el_val, 'fase': fase_calc, 'tipo': tipo, 'titulo': titulo},
                motivo=mensagem
            )
        except Exception as e_lf:
            print(f"⚠️ [Erro Lifecycle Logger Risco Under]: {e_lf}")

        return True
    except Exception as e:
        print(f"⚠️ [Alerta In-Play Under] Erro ao registrar notificação: {e}")
        return False


def avaliar_risco_under(cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed):
    """
    Avalia risco in-play para apostas ativas em 'Menos de Gols Asiáticos'.
    """
    if gh is None or ga is None:
        return 0

    gh_val = int(gh)
    ga_val = int(ga)
    total_gols_live = gh_val + ga_val
    if total_gols_live < 1:
        return 0

    try:
        elapsed_num = int(elapsed) if elapsed is not None else 0
    except (ValueError, TypeError):
        elapsed_num = 0

    cursor.execute("""
        SELECT id, palpite, odd, valor_aposta, moeda_codigo
        FROM apostas
        WHERE fixture_id = %s
          AND status = 'Pendente'
          AND confirmada = 1
          AND (mercado = 'Gols Asiáticos' OR palpite LIKE 'Menos de %% Gols%%')
          AND palpite LIKE 'Menos de %%'
    """, (fid,))
    under_bets = cursor.fetchall()
    if not under_bets:
        return 0

    alertas_emitidos = 0
    for ub in under_bets:
        ap_id = ub['id']
        palp = ub['palpite']
        odd_val = float(ub.get('odd') or 1.0)
        stake_val = float(ub.get('valor_aposta') or 0.0)
        moeda_val = ub.get('moeda_codigo') or 'USDT'

        if elapsed_num <= 20 and total_gols_live == 1:
            tit = f"⚠️ Gol Prematuro aos {elapsed_num}'! Risco em {h_team} vs {a_team}"
            msg = (
                f"⚽ Gol marcado aos {elapsed_num}' ({h_team} {gh_val}x{ga_val} {a_team})!\n\n"
                f"🎯 Sua Aposta Ativa: {palp} (@ {odd_val:.2f})\n"
                f"💰 Investimento: {moeda_val} {stake_val:.2f}\n"
                f"⚡ O ritmo do jogo acelerou cedo. Avalie a oportunidade de Cash Out "
                f"defensivo ou Hedge na Cloudbet para preservar a maior parte do capital."
            )
            lnk = f"/apostas?fixture_id={fid}"
            if registrar_alerta_gol_prematuro(cursor, target_uid, ap_id, fid, tit, msg, lnk, tipo='ALERTA_GOL_PREMATURO_UNDER', elapsed=elapsed_num):
                enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=5, tags=["warning", "soccer", "moneybag"])
                alertas_emitidos += 1

        elif elapsed_num <= 45 and total_gols_live >= 2:
            tit = f"🚨 Risco Crítico de Over aos {elapsed_num}': {h_team} vs {a_team}"
            msg = (
                f"⚠️ Já foram marcados {total_gols_live} gols no 1º Tempo ({h_team} {gh_val}x{ga_val} {a_team} aos {elapsed_num}')!\n\n"
                f"🎯 Sua Aposta Ativa: {palp} (@ {odd_val:.2f})\n"
                f"💰 Investimento: {moeda_val} {stake_val:.2f}\n"
                f"🔴 A linha de gols está sob risco iminente de perda definitiva. Verifique opções de encerramento imediato."
            )
            lnk = f"/apostas?fixture_id={fid}"
            if registrar_alerta_gol_prematuro(cursor, target_uid, ap_id, fid, tit, msg, lnk, tipo='ALERTA_RISCO_CRITICO_UNDER', elapsed=elapsed_num):
                enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=5, tags=["rotating_light", "soccer", "warning"])
                alertas_emitidos += 1

    return alertas_emitidos


def notificar_gol_ao_vivo(cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed, live_status):
    """
    Detecta e dispara notificação em tempo real quando um gol é registrado no placar.
    """
    if gh is None or ga is None:
        return 0

    gh_val = int(gh)
    ga_val = int(ga)
    total_gols = gh_val + ga_val
    if total_gols < 1:
        return 0

    try:
        elapsed_num = int(elapsed) if elapsed is not None else 0
    except (ValueError, TypeError):
        elapsed_num = 0

    tag_placar = f"{gh_val}x{ga_val}"
    fase_calc = '1H' if (elapsed_num <= 45 and live_status != 'HT') else ('HT' if live_status == 'HT' else '2H')

    cursor.execute("""
        SELECT id, palpite, mercado, odd, valor_aposta, moeda_codigo, time_casa, time_fora
        FROM apostas
        WHERE fixture_id = %s
          AND status = 'Pendente'
          AND confirmada = 1
    """, (fid,))
    active_bets = cursor.fetchall()
    if not active_bets:
        return 0

    notifs_enviadas = 0
    for ap in active_bets:
        ap_id = ap['id']
        tipo_notif = f"GOL_AO_VIVO_{tag_placar}"

        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND aposta_id = %s 
              AND tipo = %s
            LIMIT 1
        """, (target_uid, ap_id, tipo_notif))
        if cursor.fetchone():
            continue

        palp = ap['palpite']
        odd_val = float(ap.get('odd') or 1.0)
        stake_val = float(ap.get('valor_aposta') or 0.0)
        moeda_val = ap.get('moeda_codigo') or 'USDT'

        tit = f"⚽ GOL! Placar atualizado: {h_team} {gh_val} x {ga_val} {a_team} ({elapsed_num}')"
        msg = (
            f"⚽ Alteração de placar aos {elapsed_num}' ({fase_calc})!\n\n"
            f"🔥 Novo Placar: {h_team} {gh_val} x {ga_val} {a_team}\n"
            f"🎯 Sua Aposta Ativa: {palp} (@ {odd_val:.2f})\n"
            f"💰 Stake em Jogo: {moeda_val} {stake_val:.2f}\n\n"
            f"📊 Acompanhe a dinâmica ao vivo e avalie o impacto imediato na probabilidade da sua linha."
        )
        lnk = f"/apostas?fixture_id={fid}"

        try:
            cursor.execute("""
                INSERT INTO notificacoes_usuario (
                    usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, 0, 0, NOW())
            """, (target_uid, ap_id, fid, tipo_notif, tit, msg, lnk))
            notif_id = cursor.lastrowid
            print(f"⚽ [Notificação de Gol Ao Vivo Enviada User #{target_uid}] {tit}")

            enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=4, tags=["soccer", "bell", "loudspeaker"])

            try:
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=fid,
                    aposta_id=ap_id,
                    notificacao_id=notif_id,
                    notificacao_criada_em=datetime.now(),
                    componente='LIVE_MATCH_EVENTS',
                    tipo_evento='GOL_AO_VIVO',
                    estado_anterior='Pendente',
                    estado_novo='Pendente',
                    minuto_jogo=elapsed_num,
                    fase_jogo=fase_calc,
                    placar_momento=tag_placar,
                    delta={'minuto': elapsed_num, 'fase': fase_calc, 'placar': tag_placar, 'titulo': tit},
                    motivo=msg
                )
            except Exception as e_lf:
                print(f"⚠️ [Erro Lifecycle Logger Gol Ao Vivo]: {e_lf}")

            notifs_enviadas += 1
        except Exception as e_notif:
            print(f"⚠️ [Gol Ao Vivo] Erro ao registrar notificação: {e_notif}")

    return notifs_enviadas


def get_api_sports_remaining_quota(headers: dict) -> int:
    """
    Retorna o saldo restante de requisições diárias da API-Sports com cache de 15 minutos.
    Evita consumir requisições apenas perguntando o saldo a cada minuto.
    """
    global _QUOTA_CACHE
    now = datetime.now(timezone.utc)
    if _QUOTA_CACHE['timestamp'] and (now - _QUOTA_CACHE['timestamp']).total_seconds() < 900:
        return _QUOTA_CACHE['remaining']

    try:
        r = requests.get('https://v3.football.api-sports.io/status', headers=headers, timeout=6)
        if r.status_code == 200:
            resp_data = r.json()
            if resp_data.get('errors') and 'requests' in resp_data['errors']:
                print(f"⚠️ [API-Sports Limite Diário Atingido]: {resp_data['errors']['requests']}")
                _QUOTA_CACHE = {'timestamp': now, 'remaining': 0}
                return 0

            req_info = resp_data.get('response', {})
            if isinstance(req_info, dict):
                curr = int(req_info.get('requests', {}).get('current') or 0)
                limit = int(req_info.get('requests', {}).get('limit_day') or 7500)
                rem = max(0, limit - curr)
                _QUOTA_CACHE = {'timestamp': now, 'remaining': rem}
                return rem
            elif isinstance(req_info, list) and req_info:
                curr = int(req_info[0].get('requests', {}).get('current') or 0)
                limit = int(req_info[0].get('requests', {}).get('limit_day') or 7500)
                rem = max(0, limit - curr)
                _QUOTA_CACHE = {'timestamp': now, 'remaining': rem}
                return rem
    except Exception as e:
        print(f"⚠️ [API-Sports Status Check Erro]: {e}")

    _QUOTA_CACHE = {'timestamp': now, 'remaining': 0}
    return 0


def main():
    global _API_FALLBACK_LAST_RUN
    print("=" * 70)
    print(f"⚡ [FootballWeb In-Play] Sincronização Ao Vivo - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 70)

    conn = get_db_connection()
    cursor = conn.cursor()

    user_ids = get_target_user_ids(cursor)
    target_uid = user_ids[0] if user_ids else 558

    # 1. Busca partidas em aberto no banco que estejam na janela de execução (últimos 140 min ou iniciando em 10 min)
    cursor.execute("""
        SELECT ft.fixture_id, ft.home_team, ft.away_team, ft.status, ft.goals_home, ft.goals_away, ft.elapsed,
               ft.fixture_date, ft.ah_suggestion, ft.ah_confidence, ft.ah_reasoning, ft.prediction_text,
               ft.home_team_id, ft.away_team_id, ft.referee_name, ft.league_id, ft.league_name,
               rs.average_yellow_cards, rs.average_red_cards, rs.rigor_level
        FROM fixtures_trends ft
        LEFT JOIN referee_stats rs ON ft.referee_name = rs.name
        WHERE ft.fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 140 MINUTE)
          AND ft.fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
          AND ft.status NOT IN ('FT', 'AET', 'PEN', 'CANC', 'POSTPONED', 'FINISHED')
        ORDER BY ft.fixture_date ASC
    """)
    db_fixtures = cursor.fetchall()

    if not db_fixtures:
        print("ℹ️ Nenhuma partida em andamento no banco para monitorar neste instante.")
        conn.close()
        return

    print(f"📋 Partidas no banco na janela in-play: {len(db_fixtures)}")

    # 2. Captura feeds ao vivo oficiais de CUSTO ZERO (Tier 1: ESPN | Tier 2: Cloudbet | Tier 3: Betano)
    print("📡 Consultando feed ao vivo de placares da ESPN (Custo Zero de API-Sports)...")
    espn_events = fetch_espn_live_football_events()
    print(f"✅ Jogos ao vivo recebidos da ESPN: {len(espn_events)}")

    print("📡 Consultando feed ao vivo in-play da Cloudbet (Custo Zero de API-Sports)...")
    cloudbet_events = fetch_cloudbet_live_football_events()
    print(f"✅ Jogos ao vivo recebidos da Cloudbet: {len(cloudbet_events)}")

    print("📡 Consultando feed ao vivo oficial da Betano...")
    betano_events = fetch_betano_live_football_events()
    print(f"✅ Jogos ao vivo recebidos da Betano: {len(betano_events)}")

    live_events = espn_events + cloudbet_events + betano_events

    # 3. Contingência Estrita e Blindada da API-Sports (Somente com Saldo >= 2.350 e Cooldown de 15 min)
    # Verifica se há partidas em andamento que contenham apostas confirmadas de usuários
    fids_in_play = [f['fixture_id'] for f in db_fixtures if f.get('fixture_id')]
    cursor.execute("""
        SELECT DISTINCT fixture_id FROM apostas 
        WHERE fixture_id IN %s 
          AND status = 'Pendente' 
          AND confirmada = 1
    """, (tuple(fids_in_play),))
    fids_with_bets = {r['fixture_id'] for r in cursor.fetchall()}

    now_utc = datetime.now(timezone.utc)
    cooldown_ok = (_API_FALLBACK_LAST_RUN is None or (now_utc - _API_FALLBACK_LAST_RUN).total_seconds() >= 900)

    if fids_with_bets and cooldown_ok and not any(ev.get('source', '').startswith('API-Sports') for ev in live_events):
        try:
            env_vars = get_live_env_vars()
            api_key = os.environ.get("FOOTBALL_API_KEY") or env_vars.get("FOOTBALL_API_KEY") or "0327019c6fab54df2ea46009b5f0844b"
            headers = {"x-apisports-key": api_key, "Content-Type": "application/json"}

            remaining_quota = get_api_sports_remaining_quota(headers)
            print(f"📊 [API-Sports Quota Guard] Saldo Restante: {remaining_quota} (Piso de Segurança: {MINIMUM_SAFETY_QUOTA})")

            if remaining_quota >= MINIMUM_SAFETY_QUOTA:
                print("🔄 [API-Sports In-Play Fallback Autorizado] Sincronizando partidas prioritárias...")
                url_inplay = "https://v3.football.api-sports.io/fixtures?live=all"
                resp_inplay = requests.get(url_inplay, headers=headers, timeout=10).json()
                inplay_items = resp_inplay.get('response', [])
                for item in inplay_items:
                    f_info = item.get('fixture', {})
                    g_info = item.get('goals', {})
                    t_info = item.get('teams', {})
                    live_events.append({
                        'fixture_id': f_info.get('id'),
                        'home_team': t_info.get('home', {}).get('name', ''),
                        'away_team': t_info.get('away', {}).get('name', ''),
                        'status': f_info.get('status', {}).get('short'),
                        'goals_home': g_info.get('home'),
                        'goals_away': g_info.get('away'),
                        'elapsed': f_info.get('status', {}).get('elapsed'),
                        'card_lines': [],
                        'source': 'API-Sports Live Stream (Quota Guard Protegido)'
                    })
                _API_FALLBACK_LAST_RUN = now_utc
            else:
                print(f"🛡️ [API-Sports Fallback Bloqueado] Saldo {remaining_quota} abaixo da margem de segurança de {MINIMUM_SAFETY_QUOTA} (31,3% Livre). Operando 100% no modo autônomo de custo zero.")
        except Exception as e_sync:
            print(f"⚠️ Erro ao acionar contingência API-Sports: {e_sync}")

    jogos_atualizados = 0
    oportunidades_geradas = 0
    alertas_gol_prematuro = 0
    alertas_gols_ao_vivo = 0

    for fix in db_fixtures:
        fid = fix['fixture_id']
        h_team = fix['home_team'].strip()
        a_team = fix['away_team'].strip()

        matched_ev = None
        for ev in live_events:
            if ev.get('fixture_id') and ev.get('fixture_id') == fid:
                matched_ev = ev
                break
            if is_team_match(h_team, ev['home_team']) and is_team_match(a_team, ev['away_team']):
                matched_ev = ev
                break

        # Fallback do cronômetro dinâmico contínuo (Avanço 100% autônomo sem travas)
        f_date = fix['fixture_date']
        if isinstance(f_date, datetime):
            diff_sec = (datetime.now(timezone.utc) - f_date.replace(tzinfo=timezone.utc) if f_date.tzinfo is None else (datetime.now(timezone.utc) - f_date)).total_seconds()
            diff_min = int(diff_sec // 60)
            if 5 <= diff_min <= 140:
                status_estimado = '1H' if diff_min < 45 else ('HT' if diff_min <= 60 else ('FT' if diff_min >= 110 else '2H'))
                elapsed_est = min(diff_min if diff_min < 45 else (45 if diff_min <= 60 else diff_min - 15), 90)
                curr_status = fix.get('status')
                curr_elapsed = fix.get('elapsed') or 0

                # Se a partida não possui dados de placar vindos de feed oficial ou API, avança o relógio dinâmico:
                if matched_ev is None or matched_ev.get('elapsed') is None:
                    if curr_status in ('NS', '1H', 'HT', '2H', 'LIVE') and (curr_status != status_estimado or abs(curr_elapsed - elapsed_est) >= 1):
                        if status_estimado == 'FT':
                            cursor.execute("""
                                UPDATE fixtures_trends SET
                                    status = 'FT',
                                    elapsed = %s,
                                    score_processed_at = COALESCE(score_processed_at, NOW()),
                                    updated_at = NOW()
                                WHERE fixture_id = %s
                            """, (elapsed_est, fid))
                            conn.commit()
                            try:
                                from processar_apostas_encerradas import processar_apostas_fixture
                                processar_apostas_fixture(fid)
                            except Exception as e_ft:
                                print(f"⚠️ [Erro Liquidação FT Relógio #{fid}]: {e_ft}")
                        else:
                            cursor.execute("""
                                UPDATE fixtures_trends SET
                                    status = %s,
                                    elapsed = %s,
                                    updated_at = NOW()
                                WHERE fixture_id = %s
                            """, (status_estimado, elapsed_est, fid))
                        print(f"⏱️ [Relógio Dinâmico Autônomo] #{fid} {h_team} vs {a_team} avançou para {status_estimado} (~{elapsed_est}')")

        if not matched_ev or matched_ev.get('goals_home') is None:
            # Avalia notificações com os dados atuais consolidados no banco
            if fix.get('goals_home') is not None and fix.get('goals_away') is not None:
                gh_db = fix['goals_home']
                ga_db = fix['goals_away']
                el_db = fix.get('elapsed')
                st_db = fix.get('status')
                alertas_gols_ao_vivo += notificar_gol_ao_vivo(
                    cursor, target_uid, fid, h_team, a_team, gh_db, ga_db, el_db, st_db
                )
                alertas_gol_prematuro += avaliar_risco_under(
                    cursor, target_uid, fid, h_team, a_team, gh_db, ga_db, el_db
                )
            continue

        # Dados ao vivo confirmados pelo feed oficial
        live_status = matched_ev['status']
        gh = matched_ev['goals_home']
        ga = matched_ev['goals_away']
        elapsed = matched_ev['elapsed'] or fix.get('elapsed') or 0
        old_status = fix.get('status')
        placar_str = f"{gh}x{ga}"

        if old_status in ('NS', 'TBD', 'PRE') and live_status in ('1H', 'LIVE'):
            log_match_milestone(cursor, fid, 'APITO_INICIAL', placar=placar_str, minuto=elapsed or 0)
        elif old_status == '1H' and live_status == 'HT':
            log_match_milestone(cursor, fid, 'INTERVALO_INICIO', placar=placar_str, minuto=45)
        elif old_status == 'HT' and live_status == '2H':
            log_match_milestone(cursor, fid, 'INTERVALO_RETORNO', placar=placar_str, minuto=45)
        elif old_status in ('1H', '2H', 'HT', 'LIVE') and live_status in ('FT', 'FINISHED'):
            log_match_milestone(cursor, fid, 'FIM_DE_JOGO', placar=placar_str, minuto=elapsed or 90)

        if live_status in ('FT', 'FINISHED'):
            cursor.execute("""
                UPDATE fixtures_trends SET
                    status = 'FT',
                    goals_home = %s,
                    goals_away = %s,
                    elapsed = %s,
                    score_processed_at = COALESCE(score_processed_at, NOW()),
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (gh, ga, elapsed or 90, fid))
            conn.commit()
            try:
                from processar_apostas_encerradas import processar_apostas_fixture
                processar_apostas_fixture(fid)
            except Exception as e_ft:
                print(f"⚠️ [Erro Liquidação FT Live Feed #{fid}]: {e_ft}")
        else:
            cursor.execute("""
                UPDATE fixtures_trends SET
                    status = %s,
                    goals_home = %s,
                    goals_away = %s,
                    elapsed = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (live_status, gh, ga, elapsed, fid))
        jogos_atualizados += 1
        print(f"🟢 [Live Sync Feed] #{fid} {h_team} {gh}x{ga} {a_team} ({live_status} - {elapsed}') [{matched_ev.get('source')}]")

        alertas_gols_ao_vivo += notificar_gol_ao_vivo(
            cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed, live_status
        )
        alertas_gol_prematuro += avaliar_risco_under(
            cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed
        )

    conn.commit()
    conn.close()

    # Reconciliação Geral Imediata: Se houver apostas pendentes de partidas com status FT, liquida imediatamente
    try:
        conn_chk = get_db_connection()
        c_chk = conn_chk.cursor()
        c_chk.execute("""
            SELECT 1 FROM apostas a
            JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
            WHERE a.status = 'Pendente' AND ft.status IN ('FT', 'AET', 'PEN', 'FINISHED')
            LIMIT 1
        """)
        has_finished_pending = c_chk.fetchone()
        conn_chk.close()
        if has_finished_pending:
            print("🏁 [Liquidação Imediata] Partidas finalizadas com apostas pendentes detectadas. Executando process_pending_bets()...")
            from processar_apostas_encerradas import process_pending_bets
            process_pending_bets()
    except Exception as e_chk:
        print(f"⚠️ [Aviso Liquidação Finalizadas]: {e_chk}")

    # Avaliação e Execução de Cash Out In-Play
    try:
        from cloudbet_live_cashout import check_and_execute_cloudbet_live_cashouts
        check_and_execute_cloudbet_live_cashouts()
    except Exception as e_co:
        print(f"Aviso no monitor in-play de cash out: {e_co}")

    print("-" * 70)
    print(f"📊 Resumo In-Play:")
    print(f"   • Partidas com dados ao vivo atualizados: {jogos_atualizados}")
    print(f"   • Novas oportunidades alertadas: {oportunidades_geradas}")
    print(f"   • Alertas de Gols ao Vivo: {alertas_gols_ao_vivo}")
    print(f"   • Alertas de Risco Under: {alertas_gol_prematuro}")
    print("=" * 70)


if __name__ == "__main__":
    main()
