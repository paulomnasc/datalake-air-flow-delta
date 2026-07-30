#!/usr/bin/env python3
import pymysql
import hashlib
import random
import sys

def get_mysql_connection():
    try:
        conn = pymysql.connect(
            host="mysql",
            port=3306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
        return conn
    except Exception:
        pass

    try:
        conn = pymysql.connect(
            host="127.0.0.1",
            port=23306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor
        )
        return conn
    except Exception as e:
        print(f"ERRO CRÍTICO: Não foi possível conectar ao banco MySQL: {e}")
        sys.exit(1)

def generate_deterministic_team_stats(team_name, venue_type):
    h = int(hashlib.md5(f"{team_name}_{venue_type}".encode('utf-8')).hexdigest(), 16)
    r = random.Random(h)
    
    if venue_type == 'home':
        avg_goals_scored = round(r.uniform(1.2, 2.4), 2)
        avg_goals_conceded = round(r.uniform(0.7, 1.6), 2)
        clean_sheets_pct = round(r.uniform(20.0, 50.0), 2)
        avg_corners = round(r.uniform(4.5, 6.8), 2)
        avg_cards = round(r.uniform(1.8, 3.8), 2)
    else:
        avg_goals_scored = round(r.uniform(0.8, 1.8), 2)
        avg_goals_conceded = round(r.uniform(1.1, 2.2), 2)
        clean_sheets_pct = round(r.uniform(10.0, 35.0), 2)
        avg_corners = round(r.uniform(3.5, 5.5), 2)
        avg_cards = round(r.uniform(2.2, 4.5), 2)
        
    return {
        "avg_goals_scored": avg_goals_scored,
        "avg_goals_conceded": avg_goals_conceded,
        "clean_sheets_pct": clean_sheets_pct,
        "avg_corners": avg_corners,
        "avg_cards": avg_cards
    }

def main():
    print("Iniciando populacao rapida de estatísticas mock...")
    conn = get_mysql_connection()
    cursor = conn.cursor()
    
    # Query all scheduled teams in the next 7 days that do not have stats in team_moving_averages
    query = """
        SELECT DISTINCT team_id, team_name FROM (
            SELECT DISTINCT home_team_id as team_id, home_team as team_name
            FROM fixtures_trends
            WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
              AND fixture_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
              AND home_team_id IS NOT NULL
            UNION
            SELECT DISTINCT away_team_id as team_id, away_team as team_name
            FROM fixtures_trends
            WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
              AND fixture_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
              AND away_team_id IS NOT NULL
        ) AS t
        WHERE team_id NOT IN (SELECT DISTINCT team_id FROM team_moving_averages)
    """
    
    cursor.execute(query)
    missing_teams = cursor.fetchall()
    
    print(f"Encontrados {len(missing_teams)} times sem estatisticas no banco.")
    
    inserted_count = 0
    for team in missing_teams:
        team_id = team["team_id"]
        team_name = team["team_name"]
        
        print(f"Populando mock stats para {team_name} (ID: {team_id})...")
        
        mock_home = generate_deterministic_team_stats(team_name, 'home')
        mock_away = generate_deterministic_team_stats(team_name, 'away')
        
        # Save Home stats
        cursor.execute("""
            INSERT INTO team_moving_averages (
                team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                clean_sheets_pct, avg_corners, avg_cards
            ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                avg_goals_scored = VALUES(avg_goals_scored),
                avg_goals_conceded = VALUES(avg_goals_conceded),
                clean_sheets_pct = VALUES(clean_sheets_pct),
                avg_corners = VALUES(avg_corners),
                avg_cards = VALUES(avg_cards)
        """, (
            team_id, team_name, 
            mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
            mock_home["clean_sheets_pct"], mock_home["avg_corners"], 
            mock_home["avg_cards"]
        ))
        
        # Save Away stats
        cursor.execute("""
            INSERT INTO team_moving_averages (
                team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                clean_sheets_pct, avg_corners, avg_cards
            ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                avg_goals_scored = VALUES(avg_goals_scored),
                avg_goals_conceded = VALUES(avg_goals_conceded),
                clean_sheets_pct = VALUES(clean_sheets_pct),
                avg_corners = VALUES(avg_corners),
                avg_cards = VALUES(avg_cards)
        """, (
            team_id, team_name, 
            mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
            mock_away["clean_sheets_pct"], mock_away["avg_corners"], 
            mock_away["avg_cards"]
        ))
        
        inserted_count += 1
        
    conn.commit()
    cursor.close()
    conn.close()
    
    print(f"Sucesso! {inserted_count} times foram populados com estatisticas mock.")

if __name__ == '__main__':
    main()
