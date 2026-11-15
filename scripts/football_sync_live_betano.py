#!/usr/bin/env python3
"""
scripts/football_sync_live_betano.py

Sincronizador em Tempo Real de Partidas Ao Vivo via API da Betano.
FootballWeb Pipeline - Motor In-Play de Custo Zero de API-Football.

Objetivo:
1. Buscar partidas em aberto que estão no horário de execução (últimos 140 minutos);
2. Consultar o catálogo ao vivo oficial da Betano (0 consumo de API-Football);
3. Atualizar status ('1H', 'HT', '2H'), placar real e minuto decorrido (elapsed) em fixtures_trends;
4. Identificar oportunidades de valor in-play (Intervalo HT, linhas encurtadas de AH e Under Cartões);
5. Disparar alertas imediatos no Sininho e Toast web (tipo OPORTUNIDADE_AO_VIVO).
"""

import os
import sys
import pymysql
import re
import requests
from datetime import datetime, timedelta

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
    Dispara notificação push instantânea para o smartphone Android via ntfy.sh (Alta Prioridade).
    Parâmetros carregados dinamicamente de src/footballweb/.env (Regra 18).
    Fail-safe: falhas no envio mobile nunca interrompem o fluxo principal do pipeline.
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
            print(f"⚠️ [Push Mobile In-Play Falhou] Status HTTP {resp.status_code}: {resp.text}")
            return False
    except Exception as e:
        print(f"⚠️ [Push Mobile In-Play Erro de Conexão]: {e}")
        return False


def registrar_alerta_gol_prematuro(cursor, usuario_id, aposta_id, fixture_id, titulo, mensagem, link, tipo="ALERTA_GOL_PREMATURO_UNDER"):
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
        print(f"🔔 [Alerta In-Play Under Enviado User #{usuario_id}] {titulo}")
        return True
    except Exception as e:
        print(f"⚠️ [Alerta In-Play Under] Erro ao registrar notificação: {e}")
        return False


def avaliar_risco_under(cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed):
    """
    Avalia risco in-play para apostas ativas em 'Menos de Gols Asiáticos'.
    Dispara alerta de Gol Prematuro (até 20') ou Risco Crítico (2 gols no 1T).
    Garante cobertura homogênea via feed Betano ou contingência de placares API-Sports.
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

        # Critério 1: Gol Prematuro nos primeiros 20 minutos (1 gol marcado)
        if elapsed_num <= 20 and total_gols_live == 1:
            tit = f"⚠️ Gol Prematuro aos {elapsed_num}'! Risco em {h_team} vs {a_team}"
            msg = (
                f"⚽ Gol marcado aos {elapsed_num}' ({h_team} {gh_val}x{ga_val} {a_team})!\n\n"
                f"🎯 Sua Aposta Ativa: {palp} (@ {odd_val:.2f})\n"
                f"💰 Investimento: {moeda_val} {stake_val:.2f}\n"
                f"⚡ O ritmo do jogo acelerou cedo. Avalie a oportunidade de Cash Out "
                f"defensivo na Betano para preservar a maior parte do capital antes de nova alteração de placar."
            )
            lnk = f"/apostas?fixture_id={fid}"
            if registrar_alerta_gol_prematuro(cursor, target_uid, ap_id, fid, tit, msg, lnk, tipo='ALERTA_GOL_PREMATURO_UNDER'):
                enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=5, tags=["warning", "soccer", "moneybag"])
                alertas_emitidos += 1

        # Critério 2: Risco Crítico no 1º Tempo (2 gols até 45')
        elif elapsed_num <= 45 and total_gols_live >= 2:
            tit = f"🚨 Risco Crítico de Over aos {elapsed_num}': {h_team} vs {a_team}"
            msg = (
                f"⚠️ Já foram marcados {total_gols_live} gols no 1º Tempo ({h_team} {gh_val}x{ga_val} {a_team} aos {elapsed_num}')!\n\n"
                f"🎯 Sua Aposta Ativa: {palp} (@ {odd_val:.2f})\n"
                f"💰 Investimento: {moeda_val} {stake_val:.2f}\n"
                f"🔴 A linha de gols está sob risco iminente de perda definitiva. Verifique opções de encerramento imediato."
            )
            lnk = f"/apostas?fixture_id={fid}"
            if registrar_alerta_gol_prematuro(cursor, target_uid, ap_id, fid, tit, msg, lnk, tipo='ALERTA_RISCO_CRITICO_UNDER'):
                enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=5, tags=["rotating_light", "soccer", "skull"])
                alertas_emitidos += 1

    return alertas_emitidos


def notificar_gol_ao_vivo(cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed, live_status):
    """
    Detecta e dispara notificação em tempo real (Sininho/Toast e Push Mobile)
    sempre que um gol for marcado em partida ao vivo monitorada.
    Se o usuário possuir aposta ativa na partida, vincula os detalhes da aposta e eleva para prioridade máxima.
    Prevenção de duplicatas: cada placar específico (ex: '0x1') só é notificado uma única vez por partida.
    """
    if gh is None or ga is None:
        return 0

    try:
        gh_val = int(gh)
        ga_val = int(ga)
        elapsed_num = int(elapsed) if elapsed is not None else 0
    except (ValueError, TypeError):
        return 0

    if (gh_val + ga_val) == 0:
        return 0

    tag_placar = f"{gh_val}x{ga_val}"

    # Trava anti-duplicidade estrita por partida e placar exato
    cursor.execute("""
        SELECT id FROM notificacoes_usuario 
        WHERE usuario_id = %s 
          AND fixture_id = %s 
          AND tipo = 'GOL_AO_VIVO'
          AND mensagem LIKE %s
        LIMIT 1
    """, (target_uid, fid, f"%({h_team} {tag_placar} {a_team})%"))
    if cursor.fetchone():
        return 0

    # Busca se o usuário possui aposta pendente nesta partida
    cursor.execute("""
        SELECT id, mercado, palpite, odd, valor_aposta, moeda_codigo
        FROM apostas
        WHERE fixture_id = %s
          AND usuario_id = %s
          AND status = 'Pendente'
        ORDER BY id DESC
    """, (fid, target_uid))
    active_bets = cursor.fetchall()

    # FILTRO ESTRITO: Notifica gols exclusivamente para jogos com apostas ativas do usuário
    if not active_bets:
        return 0

    minuto_str = f"{elapsed_num}'" if elapsed_num > 0 else (live_status or "Ao Vivo")

    notifs_enviadas = 0
    # Notificação contextualizada com as apostas ativas do usuário
    for ab in active_bets:
        ap_id = ab['id']
        mercado = ab['mercado']
        palp = ab['palpite']
        odd_val = float(ab.get('odd') or 1.0)
        stake_val = float(ab.get('valor_aposta') or 0.0)
        moeda_val = ab.get('moeda_codigo') or 'USDT'

        tit = f"⚽ GOL aos {minuto_str}! {h_team} {gh_val}x{ga_val} {a_team}"
        msg = (
            f"⚽ GOL marcado aos {minuto_str}!\n"
            f"🏟️ Placar: ({h_team} {tag_placar} {a_team})\n\n"
            f"🎯 Sua Aposta Ativa: {palp} ({mercado} @ {odd_val:.2f})\n"
            f"💰 Investimento: {moeda_val} {stake_val:.2f}\n"
            f"⚡ Acompanhe o andamento no Dashboard ou avalie a partida na Betano."
        )
        lnk = f"/apostas?fixture_id={fid}"
        try:
            cursor.execute("""
                INSERT INTO notificacoes_usuario (
                    usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
                ) VALUES (%s, %s, %s, 'GOL_AO_VIVO', %s, %s, %s, 0, 0, NOW())
            """, (target_uid, ap_id, fid, tit, msg, lnk))
            enviar_push_mobile(tit, msg, fixture_id=fid, prioridade=5, tags=["soccer", "goal", "bell"])
            print(f"🔔 [Gol Ao Vivo com Aposta Enviado User #{target_uid}] #{fid} {tit}")
            notifs_enviadas += 1
        except Exception as e_notif:
            print(f"⚠️ [Gol Ao Vivo] Erro ao registrar notificação com aposta: {e_notif}")

    return notifs_enviadas


def main():
    print("=" * 70)
    print(f"⚡ [FootballWeb In-Play] Sincronização Ao Vivo Betano - {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 70)

    conn = get_db_connection()
    cursor = conn.cursor()

    user_ids = get_target_user_ids(cursor)
    target_uid = user_ids[0] if user_ids else 558

    # 1. Busca partidas em aberto no banco que estejam na janela de execução (início há até 140 min ou iniciando em 10 min)
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

    # 2. Captura catálogo de futebol ao vivo da Betano (0 consumo da API-Football)
    print("📡 Consultando feed ao vivo oficial da Betano...")
    live_events = fetch_betano_live_football_events()
    print(f"✅ Jogos ao vivo recebidos da Betano: {len(live_events)}")

    # Contingência de Placares: se o catálogo da Betano estiver vazio (Cloudflare/403),
    # sincroniza placares oficiais via API-Sports (1 única chamada para a data de hoje)
    if not live_events:
        print("⚠️ Feed ao vivo da Betano inacessível (bloqueio 403 / Cloudflare).")
        print("🔄 Acionando contingência de sincronização de placares oficiais (sync_pending_past_fixtures)...")
        try:
            from football_ingest_trends import sync_pending_past_fixtures
            api_key = os.getenv("FOOTBALL_API_KEY", "0327019c6fab54df2ea46009b5f0844b")
            headers = {
                "x-apisports-key": api_key,
                "Content-Type": "application/json"
            }
            sync_pending_past_fixtures(conn, headers)
            print("✅ Placares e status oficiais atualizados com sucesso pela contingência.")
            # Recarrega partidas atualizadas do banco para obter os placares recém-sincronizados
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
        except Exception as e_sync:
            print(f"⚠️ Erro ao acionar contingência de placares: {e_sync}")

    jogos_atualizados = 0
    oportunidades_geradas = 0
    alertas_gol_prematuro = 0
    alertas_gols_ao_vivo = 0

    for fix in db_fixtures:
        fid = fix['fixture_id']
        h_team = fix['home_team'].strip()
        a_team = fix['away_team'].strip()

        # Busca correspondência no feed da Betano
        matched_ev = None
        for ev in live_events:
            if is_team_match(h_team, ev['home_team']) and is_team_match(a_team, ev['away_team']):
                matched_ev = ev
                break

        if not matched_ev:
            # Fallback cosmético do cronômetro quando o evento não estiver listado na Betano
            # mas o horário de início já passou (evita status 'NS' congelado no dashboard)
            f_date = fix['fixture_date']
            if isinstance(f_date, datetime):
                diff_sec = (datetime.utcnow() - f_date).total_seconds()
                diff_min = int(diff_sec // 60)
                if 5 <= diff_min <= 130:
                    status_estimado = '1H' if diff_min < 45 else ('HT' if diff_min <= 60 else '2H')
                    elapsed_est = min(diff_min if diff_min < 45 else (45 if diff_min <= 60 else diff_min - 15), 90)
                    if fix['status'] == 'NS':
                        cursor.execute("""
                            UPDATE fixtures_trends SET
                                status = %s,
                                elapsed = %s,
                                updated_at = NOW()
                            WHERE fixture_id = %s
                        """, (status_estimado, elapsed_est, fid))
                        print(f"⏱️ [Relógio Estimado] #{fid} {h_team} vs {a_team} atualizado para {status_estimado} (~{elapsed_est}')")
            
            # Se a partida já possuir placar em andamento no banco (via contingência), avalia gols e Under:
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

        # Dados ao vivo confirmados pela Betano
        live_status = matched_ev['status']
        gh = matched_ev['goals_home']
        ga = matched_ev['goals_away']
        elapsed = matched_ev['elapsed']

        # Atualiza banco de dados com dados reais
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
        print(f"🟢 [Live Sync Betano] #{fid} {h_team} {gh}x{ga} {a_team} ({live_status} - {elapsed}')")

        # 3. Avaliação de Novas Oportunidades In-Play
        # A) Oportunidade de Cartões no Intervalo (HT) ou reta final do 1T
        card_lines = matched_ev.get('card_lines', [])
        ref_avg_yellow = float(fix.get('average_yellow_cards') or 0.0)
        ref_rigor = str(fix.get('rigor_level') or '').lower()
        is_referee_mild = (0.0 < ref_avg_yellow <= 4.2) or ('baixo' in ref_rigor or 'brando' in ref_rigor)

        if (live_status == 'HT' or (live_status == '1H' and elapsed >= 38)) and is_referee_mild:
            for cl in card_lines:
                if cl.get('type') == 'Under' and cl.get('line', 0.0) >= 3.5 and cl.get('odd', 0.0) >= 1.62:
                    tit = f"⚡ Oportunidade Ao Vivo (Cartões): {h_team} vs {a_team}"
                    msg = f"Jogo no {live_status} ({elapsed}'). Árbitro brando ({ref_avg_yellow:.1f} cartões/j). Linha disponível na Betano: {cl['label']} @ {cl['odd']:.2f}."
                    lnk = f"/apostas?fixture_id={fid}"
                    if registrar_notificacao_live(cursor, target_uid, fid, tit, msg, lnk):
                        oportunidades_geradas += 1
                    break

        # B) Oportunidade de Handicap Asiático (Favorito tropeçando com linha encurtada)
        ah_sug = str(fix.get('ah_suggestion') or '')
        has_home_fav = any(s in ah_sug for s in ['-0.75', '-1.0', '-1.25', '-1.5']) and (fix.get('home_team') in ah_sug)
        has_away_fav = any(s in ah_sug for s in ['-0.75', '-1.0', '-1.25', '-1.5']) and (fix.get('away_team') in ah_sug)

        ah_lines = matched_ev.get('ah_lines', [])
        if has_home_fav and (gh <= ga) and elapsed >= 25:
            # Mandante favorito está empatando ou perdendo: busca linha encurtada (-0.25 ou -0.5 AH)
            for al in ah_lines:
                if al.get('is_home') and al.get('line') in ('-0.25', '-0.5', '+0.0', '0.0') and al.get('odd', 0.0) >= 1.65:
                    tit = f"⚡ Oportunidade Ao Vivo (AH): {h_team} vs {a_team}"
                    msg = f"Favorito {h_team} {gh}x{ga} {a_team} ({elapsed}'). Linha de valor encurtada na Betano: {al['label']} @ {al['odd']:.2f}."
                    lnk = f"/apostas?fixture_id={fid}"
                    if registrar_notificacao_live(cursor, target_uid, fid, tit, msg, lnk):
                        oportunidades_geradas += 1
                    break
        elif has_away_fav and (ga <= gh) and elapsed >= 25:
            # Visitante favorito está empatando ou perdendo
            for al in ah_lines:
                if not al.get('is_home') and al.get('line') in ('-0.25', '-0.5', '+0.0', '0.0') and al.get('odd', 0.0) >= 1.65:
                    tit = f"⚡ Oportunidade Ao Vivo (AH): {h_team} vs {a_team}"
                    msg = f"Favorito {a_team} {gh}x{ga} ({elapsed}'). Linha de valor encurtada na Betano: {al['label']} @ {al['odd']:.2f}."
                    lnk = f"/apostas?fixture_id={fid}"
                    if registrar_notificacao_live(cursor, target_uid, fid, tit, msg, lnk):
                        oportunidades_geradas += 1
                    break

        # C) Alerta de Gols Ao Vivo (Tempo Real)
        alertas_gols_ao_vivo += notificar_gol_ao_vivo(
            cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed, live_status
        )

        # D) Alerta de Risco In-Play: Gol Prematuro em Apostas Under Gols Asiáticos
        alertas_gol_prematuro += avaliar_risco_under(
            cursor, target_uid, fid, h_team, a_team, gh, ga, elapsed
        )

    conn.commit()
    conn.close()
    print("-" * 70)
    print(f"📊 Resumo In-Play Betano:")
    print(f"   • Partidas atualizadas com placar/minuto real: {jogos_atualizados}")
    print(f"   • Novas oportunidades ao vivo alertadas: {oportunidades_geradas}")
    print(f"   • Alertas de Gols ao Vivo emitidos: {alertas_gols_ao_vivo}")
    print(f"   • Alertas de risco Under (Gol Prematuro) emitidos: {alertas_gol_prematuro}")
    print("=" * 70)


if __name__ == "__main__":
    main()
