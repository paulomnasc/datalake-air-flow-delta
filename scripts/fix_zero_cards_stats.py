#!/usr/bin/env python3
"""
Script de Atualização e Recálculo Retroativo de Estatísticas de Cartões Zeradas.
Substitui registros em 'team_moving_averages' com avg_cards = 0.00 por médias extraídas
diretamente do histórico real de partidas encerradas (status = 'FT') na tabela 'fixtures_trends'.
"""

import sys
import pymysql

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

    print("❌ [ERRO CRÍTICO] Falha ao conectar no MySQL.")
    sys.exit(1)

def get_team_cards_from_db_history(cursor, team_name, venue_type=None, team_id=None, league_id=None, limit=10):
    """
    Busca no histórico de partidas encerradas ('FT') em fixtures_trends
    a média real de cartões (amarelos + vermelhos*2) do time.
    Se o time não tiver histórico suficiente no mando específico ou no geral,
    busca a média das partidas da própria liga no banco.
    """
    t_id = team_id or 0
    sql = """
        SELECT 
            fixture_id, home_team, away_team, home_team_id, away_team_id, league_id,
            yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away
        FROM fixtures_trends
        WHERE status = 'FT'
          AND (COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0)) > 0
          AND (
            (%s > 0 AND (home_team_id = %s OR away_team_id = %s))
            OR (LOWER(home_team) = LOWER(%s) OR LOWER(away_team) = LOWER(%s))
          )
        ORDER BY fixture_date DESC
        LIMIT %s
    """
    cursor.execute(sql, (t_id, t_id, t_id, team_name, team_name, limit * 3))
    rows = cursor.fetchall()

    cards_list = []
    found_league_id = league_id

    for r in rows:
        if not found_league_id and r.get('league_id'):
            found_league_id = r['league_id']

        is_home = (r['home_team_id'] == t_id) if (t_id and r['home_team_id']) else (r['home_team'].lower() == team_name.lower())
        
        if venue_type == 'home' and not is_home:
            continue
        if venue_type == 'away' and is_home:
            continue

        yh = r.get('yellow_cards_home') or 0
        rh = r.get('red_cards_home') or 0
        ya = r.get('yellow_cards_away') or 0
        ra = r.get('red_cards_away') or 0

        c = (yh + rh * 2) if is_home else (ya + ra * 2)
        if (yh + rh + ya + ra) > 0:
            cards_list.append(c)
            if len(cards_list) >= limit:
                break

    if cards_list and sum(cards_list) > 0:
        raw_avg = sum(cards_list) / len(cards_list)
        if len(cards_list) >= 3:
            return round(raw_avg, 2)
        
        # Suavização para Amostra Pequena (N < 3): Blending bayesiano com a média histórica da liga
        league_baseline = 2.85
        if found_league_id:
            cursor.execute("""
                SELECT AVG(COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0) + (COALESCE(red_cards_home, 0) + COALESCE(red_cards_away, 0))*2) / 2.0 as avg_team_cards
                FROM fixtures_trends
                WHERE status = 'FT'
                  AND league_id = %s
                  AND (COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0)) > 0
            """, (found_league_id,))
            l_row = cursor.fetchone()
            if l_row and l_row['avg_team_cards'] and float(l_row['avg_team_cards']) > 0:
                league_baseline = float(l_row['avg_team_cards'])

        weight_sample = len(cards_list) / 3.0 # N=1 -> 33% amostra + 67% liga
        blended_avg = (raw_avg * weight_sample) + (league_baseline * (1.0 - weight_sample))
        return round(blended_avg, 2)

    # Se a liga ainda não for conhecida, tenta descobrir via fixtures_trends
    if not found_league_id and t_id:
        cursor.execute("SELECT league_id FROM fixtures_trends WHERE home_team_id = %s OR away_team_id = %s LIMIT 1", (t_id, t_id))
        l_row_team = cursor.fetchone()
        if l_row_team:
            found_league_id = l_row_team.get('league_id')

    # Fallback por Liga na fixtures_trends do nosso banco
    if found_league_id:
        cursor.execute("""
            SELECT AVG(COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0) + (COALESCE(red_cards_home, 0) + COALESCE(red_cards_away, 0))*2) / 2.0 as avg_team_cards
            FROM fixtures_trends
            WHERE status = 'FT'
              AND league_id = %s
              AND (COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0)) > 0
        """, (found_league_id,))
        l_row = cursor.fetchone()
        if l_row and l_row['avg_team_cards'] and float(l_row['avg_team_cards']) > 0:
            return round(float(l_row['avg_team_cards']), 2)

    # Fallback da Média Geral de Partidas FT no Banco
    cursor.execute("""
        SELECT AVG(COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0) + (COALESCE(red_cards_home, 0) + COALESCE(red_cards_away, 0))*2) / 2.0 as avg_team_cards
        FROM fixtures_trends
        WHERE status = 'FT'
          AND (COALESCE(yellow_cards_home, 0) + COALESCE(yellow_cards_away, 0)) > 0
    """)
    g_row = cursor.fetchone()
    if g_row and g_row['avg_team_cards'] and float(g_row['avg_team_cards']) > 0:
        return round(float(g_row['avg_team_cards']), 2)

    return 2.20

def fix_zero_cards():
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT team_id, team_name, venue_type 
        FROM team_moving_averages
    """)
    teams = cursor.fetchall()
    print(f"📊 Processando recálculo real de cartões para {len(teams)} registros em team_moving_averages...")

    updated_count = 0
    for row in teams:
        t_id = row['team_id']
        t_name = row['team_name']
        v_type = row['venue_type']

        real_avg = get_team_cards_from_db_history(cursor, t_name, venue_type=v_type, team_id=t_id)
        if real_avg and real_avg > 0:
            cursor.execute("""
                UPDATE team_moving_averages
                SET avg_cards = %s, updated_at = NOW()
                WHERE team_id = %s AND venue_type = %s
            """, (real_avg, t_id, v_type))
            updated_count += 1

    conn.commit()
    print(f"✅ Concluída atualização de {updated_count} registros de cartões na team_moving_averages!")

    # Verificar saldo restante <= 0.05
    cursor.execute("SELECT COUNT(*) as rest FROM team_moving_averages WHERE avg_cards <= 0.05")
    rest = cursor.fetchone()['rest']
    print(f"🔍 Saldo restante com avg_cards <= 0.05: {rest}")

    conn.close()

if __name__ == '__main__':
    fix_zero_cards()
