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
from db_config import get_db_connection


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
        except Exception as e_sync:
            print(f"⚠️ Erro ao acionar contingência de placares: {e_sync}")

    jogos_atualizados = 0
    oportunidades_geradas = 0

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
                    lnk = f"/trends?destaque_id={fid}#fixture-{fid}"
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
                    lnk = f"/trends?destaque_id={fid}#fixture-{fid}"
                    if registrar_notificacao_live(cursor, target_uid, fid, tit, msg, lnk):
                        oportunidades_geradas += 1
                    break
        elif has_away_fav and (ga <= gh) and elapsed >= 25:
            # Visitante favorito está empatando ou perdendo
            for al in ah_lines:
                if not al.get('is_home') and al.get('line') in ('-0.25', '-0.5', '+0.0', '0.0') and al.get('odd', 0.0) >= 1.65:
                    tit = f"⚡ Oportunidade Ao Vivo (AH): {h_team} vs {a_team}"
                    msg = f"Favorito {a_team} {gh}x{ga} ({elapsed}'). Linha de valor encurtada na Betano: {al['label']} @ {al['odd']:.2f}."
                    lnk = f"/trends?destaque_id={fid}#fixture-{fid}"
                    if registrar_notificacao_live(cursor, target_uid, fid, tit, msg, lnk):
                        oportunidades_geradas += 1
                    break

    conn.close()
    print("-" * 70)
    print(f"📊 Resumo In-Play Betano:")
    print(f"   • Partidas atualizadas com placar/minuto real: {jogos_atualizados}")
    print(f"   • Novas oportunidades ao vivo alertadas: {oportunidades_geradas}")
    print("=" * 70)


if __name__ == "__main__":
    main()
