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

def fetch_real_fixture_cards_api(fixture_id, home_team_id=None, cursor=None):
    if cursor is not None and fixture_id:
        try:
            cursor.execute("""
                SELECT yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away, last_event, cards_api_checked_at 
                FROM fixtures_trends 
                WHERE fixture_id = %s AND yellow_cards_home IS NOT NULL AND yellow_cards_away IS NOT NULL
                LIMIT 1
            """, (fixture_id,))
            row = cursor.fetchone()
            if row:
                yh = row.get('yellow_cards_home', 0) or 0
                ya = row.get('yellow_cards_away', 0) or 0
                rh = row.get('red_cards_home', 0) or 0
                ra = row.get('red_cards_away', 0) or 0
                last_ev = row.get('last_event')
                checked_at = row.get('cards_api_checked_at')
                if checked_at is not None and ((yh + ya + rh + ra) > 0 or (last_ev is not None and last_ev != '')):
                    return (yh, ya, rh, ra)
            
            cursor.execute("""
                SELECT team_id, yellow_cards, red_cards 
                FROM match_statistics_cache 
                WHERE fixture_id = %s
            """, (fixture_id,))
            cache_rows = cursor.fetchall()
            if cache_rows and len(cache_rows) > 0:
                yh, ya, rh, ra = 0, 0, 0, 0
                found = False
                for r in cache_rows:
                    t_id = r.get('team_id')
                    is_home = (t_id == home_team_id) if (home_team_id and t_id) else True
                    if is_home:
                        yh = r.get('yellow_cards', 0) or 0
                        rh = r.get('red_cards', 0) or 0
                        found = True
                    else:
                        ya = r.get('yellow_cards', 0) or 0
                        ra = r.get('red_cards', 0) or 0
                        found = True
                if found:
                    return (yh, ya, rh, ra)
        except Exception as e_cache:
            print(f"⚠️ Erro ao consultar cache local para fixture #{fixture_id}: {e_cache}")

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {'x-apisports-key': api_key, 'User-Agent': 'Mozilla/5.0'}
    yh, ya, rh, ra = None, None, None, None
    api_success = False

    # 1. statistics endpoint
    try:
        url_st = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
        res_st = requests.get(url_st, headers=headers, timeout=10)
        if res_st.status_code == 200:
            api_success = True
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

    # Se a API de estatísticas oficiais não retornou dados de cartões, NÃO faz fallback para /events.
    # A aposta/partida deve aguardar a consolidação oficial em /fixtures/statistics.
    if not api_success or yh is None or ya is None:
        return None

    return (
        yh,
        ya,
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
        
        res = fetch_real_fixture_cards_api(fid, htid, cursor=cursor)
        if res is not None:
            yh, ya, rh, ra = res
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
        WHERE status = 'FT' AND (yellow_cards_home IS NULL OR (yellow_cards_home = 0 AND yellow_cards_away = 0 AND (last_event IS NULL OR last_event = '')) OR (yellow_cards_home = 1 AND yellow_cards_away = 1))
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
        
        res = fetch_real_fixture_cards_api(fid, htid, cursor=cursor)
        if res is not None:
            yh, ya, rh, ra = res
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
