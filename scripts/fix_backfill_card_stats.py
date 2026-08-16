#!/usr/bin/env python3
"""
Script de Backfill Retroativo de Cartões para Partidas Encerradas (FT)
Busca dados de estatísticas e eventos na API-Sports para partidas que possuem cartões NULOS ou mock (1-1),
atualiza a tabela 'fixtures_trends' e reprocessa a liquidação das apostas.
"""

import os
import sys
import time
import requests
import pymysql
from datetime import datetime

def get_db_connection():
    hosts_ports = [
        ("mysql", 3306),
        ("127.0.0.1", 23306),
        ("localhost", 3306)
    ]
    for host, port in hosts_ports:
        try:
            conn = pymysql.connect(
                host=host,
                port=port,
                user="root",
                password="YM11rMrT32xH0E6N",
                database="footballweb",
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ Falha ao conectar no MySQL.")
    sys.exit(1)

def fetch_real_fixture_cards_api(fixture_id, home_team_id=None):
    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {'x-apisports-key': api_key, 'User-Agent': 'Mozilla/5.0'}
    yh, ya, rh, ra = None, None, None, None

    # 1. statistics endpoint
    try:
        url_st = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
        res_st = requests.get(url_st, headers=headers, timeout=10)
        if res_st.status_code == 200:
            st_data = res_st.json().get("response", [])
            for idx, team_st in enumerate(st_data):
                t_id = team_st.get("team", {}).get("id")
                is_home = (t_id == home_team_id) if home_team_id else (idx == 0)
                for s in team_st.get("statistics", []):
                    s_type = (s.get("type") or "").strip()
                    s_val = s.get("value")
                    if s_type == "Yellow Cards" and s_val is not None:
                        if is_home: yh = int(s_val)
                        else: ya = int(s_val)
                    elif s_type == "Red Cards" and s_val is not None:
                        if is_home: rh = int(s_val)
                        else: ra = int(s_val)
    except Exception as e:
        print(f"⚠️ Erro no endpoint statistics para fixture {fixture_id}: {e}")

    # 2. events endpoint
    try:
        url_ev = f"https://v3.football.api-sports.io/fixtures/events?fixture={fixture_id}"
        res_ev = requests.get(url_ev, headers=headers, timeout=10)
        if res_ev.status_code == 200:
            ev_data = res_ev.json().get("response", [])
            eyh, eya, erh, era = 0, 0, 0, 0
            has_card_events = False
            for ev in ev_data:
                if ev.get("type") == "Card":
                    has_card_events = True
                    t_id = ev.get("team", {}).get("id")
                    is_home = (t_id == home_team_id) if home_team_id else True
                    detail = ev.get("detail", "")
                    if "Yellow" in detail:
                        if is_home: eyh += 1
                        else: eya += 1
                    elif "Red" in detail:
                        if is_home: erh += 1
                        else: era += 1
            if has_card_events or yh is None:
                yh = max(yh if yh is not None else 0, eyh)
                ya = max(ya if ya is not None else 0, eya)
                rh = max(rh if rh is not None else 0, erh)
                ra = max(ra if ra is not None else 0, era)
    except Exception as e:
        print(f"⚠️ Erro no endpoint events para fixture {fixture_id}: {e}")

    return (
        yh if yh is not None else 0,
        ya if ya is not None else 0,
        rh if rh is not None else 0,
        ra if ra is not None else 0
    )

def main():
    print("🚀 [Backfill Cartões] Iniciando varredura de jogos encerrados com cartões pendentes ou incorretos...")
    conn = get_db_connection()
    cursor = conn.cursor()

    # Priorizar jogos que possuem apostas cadastradas primeiro!
    cursor.execute("""
        SELECT DISTINCT f.fixture_id, f.home_team, f.away_team, f.home_team_id, f.yellow_cards_home, f.yellow_cards_away
        FROM fixtures_trends f
        JOIN apostas a ON (a.fixture_id = f.fixture_id OR (f.home_team COLLATE utf8mb4_general_ci LIKE CONCAT('%', a.time_casa, '%') COLLATE utf8mb4_general_ci AND f.away_team COLLATE utf8mb4_general_ci LIKE CONCAT('%', a.time_fora, '%') COLLATE utf8mb4_general_ci))
        WHERE f.status = 'FT'
    """)
    bet_fixtures = cursor.fetchall()
    print(f"📊 Encontradas {len(bet_fixtures)} partidas encerradas associadas a apostas de usuários.")

    for fix in bet_fixtures:
        fid = fix['fixture_id']
        htid = fix.get('home_team_id')
        h_team = fix['home_team']
        a_team = fix['away_team']
        
        yh, ya, rh, ra = fetch_real_fixture_cards_api(fid, htid)
        print(f"⚡ Fixture {fid} [{h_team} vs {a_team}]: Atualizado -> Amarelos: {yh} Casa / {ya} Fora | Vermelhos: {rh} Casa / {ra} Fora")
        
        cursor.execute("""
            UPDATE fixtures_trends
            SET yellow_cards_home = %s,
                yellow_cards_away = %s,
                red_cards_home = %s,
                red_cards_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (yh, ya, rh, ra, fid))
        time.sleep(0.2)

    # Agora atualizar outras partidas FT que possuem cartões NULOS no banco
    cursor.execute("""
        SELECT fixture_id, home_team, away_team, home_team_id
        FROM fixtures_trends
        WHERE status = 'FT' AND (yellow_cards_home IS NULL OR (yellow_cards_home = 1 AND yellow_cards_away = 1))
        ORDER BY fixture_date DESC
        LIMIT 200
    """)
    pending_fixes = cursor.fetchall()
    print(f"📋 Encontradas {len(pending_fixes)} partidas FT adicionais com cartões NULOS ou mock.")

    for idx, fix in enumerate(pending_fixes, 1):
        fid = fix['fixture_id']
        htid = fix.get('home_team_id')
        h_team = fix['home_team']
        a_team = fix['away_team']
        
        yh, ya, rh, ra = fetch_real_fixture_cards_api(fid, htid)
        print(f"[{idx}/{len(pending_fixes)}] Fixture {fid} [{h_team} vs {a_team}]: Cartões API -> {yh}C+{ya}F (Vermelhos {rh}C+{ra}F)")
        
        cursor.execute("""
            UPDATE fixtures_trends
            SET yellow_cards_home = %s,
                yellow_cards_away = %s,
                red_cards_home = %s,
                red_cards_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (yh, ya, rh, ra, fid))
        time.sleep(0.15)

    conn.close()
    print("\n✅ Backfill de estatísticas de cartões concluído no banco MySQL!")

    print("\n🔄 Executando re-liquidação de apostas...")
    from processar_apostas_cartoes_encerradas import processar_apostas_cartoes_encerradas
    from processar_apostas_encerradas import process_pending_bets, process_palpites_gerados
    
    conn2 = get_db_connection()
    cursor2 = conn2.cursor()
    
    # Resetar apostas de cartões que foram liquidadas com números incorretos
    cursor2.execute("""
        UPDATE apostas a
        JOIN fixtures_trends f ON (a.fixture_id = f.fixture_id OR (f.home_team COLLATE utf8mb4_general_ci LIKE CONCAT('%', a.time_casa, '%') COLLATE utf8mb4_general_ci AND f.away_team COLLATE utf8mb4_general_ci LIKE CONCAT('%', a.time_fora, '%') COLLATE utf8mb4_general_ci))
        SET a.status = 'Pendente',
            a.resultado_detalhado = NULL,
            a.ganhos_potenciais = (a.valor_aposta * a.odd)
        WHERE (a.mercado LIKE '%Cartõ%' OR a.mercado LIKE '%Card%' OR a.palpite LIKE '%Cartões%')
          AND f.status = 'FT'
    """)
    print("🔄 Apostas de cartões de jogos encerrados resetadas para status 'Pendente' para re-avaliação limpa.")

    processar_apostas_cartoes_encerradas()
    process_pending_bets()
    process_palpites_gerados(cursor2)
    conn2.close()

if __name__ == '__main__':
    main()
