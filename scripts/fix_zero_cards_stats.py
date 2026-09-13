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

def get_team_cards_from_db_history(cursor, team_name, venue_type=None, team_id=None, league_id=None, limit=5):
    """
    Busca no histórico de partidas encerradas ('FT', 'AET', 'PEN') em fixtures_trends
    a média real de cartões (amarelos + vermelhos*2) do time.
    Se não houver histórico de cartões válido, retorna 0.00.
    """
    t_id = team_id or 0
    sql = """
        SELECT 
            fixture_id, home_team, away_team, home_team_id, away_team_id, league_id,
            yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away
        FROM fixtures_trends
        WHERE status IN ('FT', 'AET', 'PEN')
          AND league_id NOT IN (667)
          AND (league_name IS NULL OR (league_name NOT LIKE '%%Friendly%%' AND league_name NOT LIKE '%%Amistoso%%'))
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

        c = (yh + rh) if is_home else (ya + ra)
        cards_list.append(c)
        if len(cards_list) >= limit:
            break

    if len(cards_list) < 2 and venue_type:
        return get_team_cards_from_db_history(cursor, team_name, venue_type=None, team_id=team_id, league_id=found_league_id, limit=limit)

    if cards_list and len(cards_list) >= 1:
        return round(sum(cards_list) / len(cards_list), 2)

    return 0.00

def fix_zero_cards():
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT DISTINCT team_id, team_name 
        FROM team_moving_averages
    """)
    teams = cursor.fetchall()
    print(f"📊 Processando recálculo real de cartões para {len(teams)} times em team_moving_averages...")

    updated_count = 0
    zero_count = 0
    for row in teams:
        t_id = row['team_id']
        t_name = row['team_name']

        # Calcula a média real geral competitiva da equipe em todos os jogos (métrica unificada do clube)
        real_avg = get_team_cards_from_db_history(cursor, t_name, venue_type=None, team_id=t_id)
        if real_avg > 0:
            cursor.execute("""
                UPDATE team_moving_averages
                SET avg_cards = %s, updated_at = NOW()
                WHERE team_id = %s
            """, (real_avg, t_id))
            updated_count += 1
        else:
            zero_count += 1

    conn.commit()
    print(f"✅ Concluída atualização! {updated_count} times com histórico real e {zero_count} times sinalizados sem histórico de cartões (avg_cards = 0.00).")

    # Marcar prediction_text como NO_BET apenas para partidas onde os dados de cartões estejam zerados em ambos os times
    print("🛡️ Aplicando trava NO_BET em fixtures_trends para partidas com cartões indisponíveis (zerados em ambos os times)...")
    cursor.execute("""
        UPDATE fixtures_trends f
        JOIN team_moving_averages h ON (f.home_team_id = h.team_id AND h.venue_type = 'home')
        JOIN team_moving_averages a ON (f.away_team_id = a.team_id AND a.venue_type = 'away')
        SET f.prediction_text = '🚫 NO_BET: Dados de cartões zerados ou indisponíveis para as equipes. Entrada bloqueada pelo Gatekeeper por segurança.'
        WHERE (h.avg_cards <= 0.01 AND a.avg_cards <= 0.01)
          AND f.status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'FINISHED')
    """)
    conn.commit()
    print("✅ Trava de segurança NO_BET aplicada com sucesso!")

    conn.close()

if __name__ == '__main__':
    fix_zero_cards()

