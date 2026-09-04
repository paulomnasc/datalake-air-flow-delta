#!/usr/bin/env python3
import sys
import os
import requests
import pymysql
import time
import hashlib
import random
from datetime import datetime, timedelta

# Conexão MySQL robusta
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

# Gerador determinístico de médias realistas para fallback/mock
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

    if not cards_list and venue_type:
        return get_team_cards_from_db_history(cursor, team_name, venue_type=None, team_id=team_id, league_id=found_league_id, limit=limit)

    if cards_list and len(cards_list) >= 1:
        return round(sum(cards_list) / len(cards_list), 2)

    return 0.00

def main():
    import argparse
    parser = argparse.ArgumentParser(description="Ingestão de performance e médias móveis dos times")
    parser.add_argument("--team-id", type=str, help="IDs dos times separados por vírgula para processar especificamente")
    parser.add_argument("--force-refresh", action="store_true", help="Força a atualização ignorando cache recente")
    args = parser.parse_args()

    if args.force_refresh:
        os.environ["FORCE_REFRESH"] = "1"

    print("Iniciando ingestão de performance dos times (médias móveis)...")
    
    conn = get_mysql_connection()
    cursor = conn.cursor()
    
    today = datetime.now().strftime('%Y-%m-%d')
    seven_days_later = (datetime.now() + timedelta(days=7)).strftime('%Y-%m-%d')
    
    query = """
        SELECT DISTINCT home_team_id as team_id, home_team as team_name, league_id, YEAR(fixture_date) as season
        FROM fixtures_trends
        WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
          AND fixture_date <= %s
          AND home_team_id IS NOT NULL
        UNION
        SELECT DISTINCT away_team_id as team_id, away_team as team_name, league_id, YEAR(fixture_date) as season
        FROM fixtures_trends
        WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 1 DAY) 
          AND fixture_date <= %s
          AND away_team_id IS NOT NULL
    """
    
    cursor.execute(query, (seven_days_later, seven_days_later))
    teams = cursor.fetchall()
    
    if args.team_id:
        filter_ids = [int(x.strip()) for x in args.team_id.split(",") if x.strip().isdigit()]
        teams = [t for t in teams if t["team_id"] in filter_ids]
        print(f"Filtrado para {len(teams)} time(s) especificado(s): {filter_ids}")
    else:
        print(f"Encontrados {len(teams)} times únicos na agenda para processar nos próximos 7 dias.")
    
    api_key = os.getenv("FOOTBALL_API_KEY", "0327019c6fab54df2ea46009b5f0844b")
    headers = {
        "x-apisports-key": api_key,
        "Content-Type": "application/json"
    }
    
    api_calls_made = 0
    
    def rate_limit_delay():
        nonlocal api_calls_made
        api_calls_made += 1
        time.sleep(6.5)

    for t in teams:
        team_id = t["team_id"]
        team_name = t["team_name"]
        league_id = t["league_id"]
        season = t["season"]
        
        print(f"\n--- Processando: {team_name} (ID: {team_id}) | Liga: {league_id} | Temporada: {season} ---")
        
        # 1. Verificar se precisa de atualização (se atualizado há menos de 12 horas e possui amostragem válida, ignora para poupar API)
        cursor.execute("SELECT updated_at, avg_cards, matches_count FROM team_moving_averages WHERE team_id = %s LIMIT 1", (team_id,))
        row = cursor.fetchone()
        if row and int(row.get("matches_count") or 0) >= 5 and os.environ.get("FORCE_REFRESH") != "1":
            updated_at = row["updated_at"]
            if datetime.now() - updated_at < timedelta(hours=12):
                print(f"⏩ {team_name} já atualizado recentemente ({updated_at}) com amostragem válida ({row.get('matches_count')} jogos). Pulando...")
                continue
        
        # 2. Tentar buscar `/teams/statistics` nas temporadas disponíveis (temporada atual, anterior, etc.)
        stats_data = None
        use_season = season
        seasons_to_try = [season, season - 1, season - 2]
        
        for s in seasons_to_try:
            stats_url = f"https://v3.football.api-sports.io/teams/statistics?league={league_id}&season={s}&team={team_id}"
            print(f"Chamando /teams/statistics (Temporada: {s}) para {team_name}...")
            try:
                rate_limit_delay()
                resp = requests.get(stats_url, headers=headers, timeout=20)
                resp.raise_for_status()
                stats_json = resp.json()
            except Exception as e:
                print(f"Erro na requisição: {e}")
                continue
                
            errors = stats_json.get("errors", {})
            if errors:
                print(f"Aviso API na temporada {s}: {errors}")
                continue
                
            resp_data = stats_json.get("response")
            played_total = (resp_data.get("fixtures", {}).get("played", {}).get("total", 0) or 0) if resp_data else 0
            if played_total >= 3:
                stats_data = resp_data
                use_season = s
                print(f"✅ Encontradas {played_total} partidas estatísticas na temporada {s} para {team_name}.")
                break
            else:
                print(f"Temporada {s} possui poucas partidas ({played_total}). Tentando temporada anterior...")
            
        # 3. Processamento de estatísticas com fallback
        played_home = 0
        played_away = 0
        avg_goals_for_home = 0.0
        avg_goals_for_away = 0.0
        avg_goals_against_home = 0.0
        avg_goals_against_away = 0.0
        clean_sheets_pct_home = 0.0
        clean_sheets_pct_away = 0.0

        if stats_data:
            played_home = stats_data.get("fixtures", {}).get("played", {}).get("home", 0) or 0
            played_away = stats_data.get("fixtures", {}).get("played", {}).get("away", 0) or 0
            
            avg_goals_for_home = float(stats_data.get("goals", {}).get("for", {}).get("average", {}).get("home", 0.00) or 0.00)
            avg_goals_for_away = float(stats_data.get("goals", {}).get("for", {}).get("average", {}).get("away", 0.00) or 0.00)
            avg_goals_against_home = float(stats_data.get("goals", {}).get("against", {}).get("average", {}).get("home", 0.00) or 0.00)
            avg_goals_against_away = float(stats_data.get("goals", {}).get("against", {}).get("average", {}).get("away", 0.00) or 0.00)
            
            cs_home_count = stats_data.get("clean_sheet", {}).get("home", 0) or 0
            cs_away_count = stats_data.get("clean_sheet", {}).get("away", 0) or 0
            
            clean_sheets_pct_home = round((cs_home_count / played_home) * 100, 2) if played_home > 0 else 0.00
            clean_sheets_pct_away = round((cs_away_count / played_away) * 100, 2) if played_away > 0 else 0.00

        home_corners = []
        home_cards = []
        away_corners = []
        away_cards = []
        all_cards = []

        # Extração dos 5 últimos jogos via API-Sports (sem restrição de liga para cobrir início de temporada e copas)
        # Endpoint idêntico ao já validado no Handicap Asiático: /fixtures?team={team_id}&last=5&status=FT
        fixtures_url = f"https://v3.football.api-sports.io/fixtures?team={team_id}&last=5&status=FT"
        print(f"📡 Buscando últimos 5 jogos gerais na API para {team_name} (ID: {team_id})...")
        
        fixtures_list = []
        try:
            rate_limit_delay()
            resp = requests.get(fixtures_url, headers=headers, timeout=20)
            resp.raise_for_status()
            fixtures_json = resp.json()
            fixtures_list = fixtures_json.get("response", [])
        except Exception as e:
            print(f"Erro ao buscar histórico de jogos: {e}")
            
        fixtures_list.sort(key=lambda x: x["fixture"]["date"], reverse=True)
        recent_fixtures = fixtures_list[:5]

        goals_scored_list = []
        goals_conceded_list = []
        clean_sheet_matches = 0
        
        for f in recent_fixtures:
            fix_id = f["fixture"]["id"]
            is_home = (f["teams"]["home"]["id"] == team_id)

            gh = f.get("goals", {}).get("home") if f.get("goals", {}).get("home") is not None else 0
            ga = f.get("goals", {}).get("away") if f.get("goals", {}).get("away") is not None else 0
            scored = gh if is_home else ga
            conceded = ga if is_home else gh
            goals_scored_list.append(scored)
            goals_conceded_list.append(conceded)
            if conceded == 0:
                clean_sheet_matches += 1
            
            # Check cache primeiro (match_statistics_cache ou fixtures_trends do nosso MySQL)
            cursor.execute("SELECT corners, yellow_cards, red_cards FROM match_statistics_cache WHERE fixture_id = %s AND team_id = %s", (fix_id, team_id))
            cached_stat = cursor.fetchone()

            if not cached_stat:
                cursor.execute("""
                    SELECT yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away, corners_home, corners_away, home_team_id
                    FROM fixtures_trends
                    WHERE fixture_id = %s AND (yellow_cards_home IS NOT NULL OR yellow_cards_away IS NOT NULL)
                """, (fix_id,))
                fix_db = cursor.fetchone()
                if fix_db:
                    is_h = (int(fix_db.get("home_team_id") or 0) == team_id)
                    cached_stat = {
                        "corners": (fix_db.get("corners_home") if is_h else fix_db.get("corners_away")) or 0,
                        "yellow_cards": (fix_db.get("yellow_cards_home") if is_h else fix_db.get("yellow_cards_away")) or 0,
                        "red_cards": (fix_db.get("red_cards_home") if is_h else fix_db.get("red_cards_away")) or 0
                    }

            if cached_stat is not None:
                corners = cached_stat.get("corners") or 0
                yellows = cached_stat.get("yellow_cards") or 0
                reds = cached_stat.get("red_cards") or 0
            else:
                print(f"Match ID {fix_id} não possui estatísticas em cache DB local. Buscando na API...")
                match_stats_url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fix_id}"
                try:
                    rate_limit_delay()
                    ms_resp = requests.get(match_stats_url, headers=headers, timeout=20)
                    ms_resp.raise_for_status()
                    ms_json = ms_resp.json()
                except Exception as e:
                    print(f"Erro ao buscar stats da partida {fix_id} na API: {e}.")
                    yellows = 0
                    reds = 0
                    corners = 4
                    total_cards = 0
                    all_cards.append(total_cards)
                    if is_home:
                        home_cards.append(total_cards)
                    else:
                        away_cards.append(total_cards)
                    continue
                    
                ms_response = ms_json.get("response", [])
                team_stats = None
                for team_entry in ms_response:
                    if team_entry.get("team", {}).get("id") == team_id:
                        team_stats = team_entry.get("statistics", [])
                        break
                        
                corners = 0
                yellows = 0
                reds = 0
                
                if team_stats is not None:
                    for s in team_stats:
                        t_type = s.get("type")
                        t_val = s.get("value")
                        if t_type == "Corner Kicks":
                            corners = int(t_val) if t_val is not None else 0
                        elif t_type == "Yellow Cards":
                            yellows = int(t_val) if t_val is not None else 0
                        elif t_type == "Red Cards":
                            reds = int(t_val) if t_val is not None else 0
                else:
                    yellows = 0
                    reds = 0
                    corners = 0
                            
                # Cache e persistência no banco principal
                cursor.execute("""
                    INSERT INTO match_statistics_cache (fixture_id, team_id, corners, yellow_cards, red_cards)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE
                        corners = VALUES(corners),
                        yellow_cards = VALUES(yellow_cards),
                        red_cards = VALUES(red_cards)
                """, (fix_id, team_id, corners, yellows, reds))

                if is_home:
                    cursor.execute("""
                        UPDATE fixtures_trends
                        SET yellow_cards_home = %s, red_cards_home = %s, corners_home = %s
                        WHERE fixture_id = %s
                    """, (yellows, reds, corners, fix_id))
                else:
                    cursor.execute("""
                        UPDATE fixtures_trends
                        SET yellow_cards_away = %s, red_cards_away = %s, corners_away = %s
                        WHERE fixture_id = %s
                    """, (yellows, reds, corners, fix_id))

                conn.commit()
                
            total_cards = yellows + (reds * 2)
            all_cards.append(total_cards)
            if is_home:
                home_corners.append(corners)
                home_cards.append(total_cards)
            else:
                away_corners.append(corners)
                away_cards.append(total_cards)
                
        # Fallback para gols e clean sheets a partir dos 5 jogos recentes caso stats_data seja nulo
        if not stats_data and goals_scored_list:
            avg_g_scored = round(sum(goals_scored_list) / len(goals_scored_list), 2)
            avg_g_conceded = round(sum(goals_conceded_list) / len(goals_conceded_list), 2)
            cs_pct = round((clean_sheet_matches / len(goals_scored_list)) * 100, 2)
            avg_goals_for_home = avg_g_scored
            avg_goals_for_away = avg_g_scored
            avg_goals_against_home = avg_g_conceded
            avg_goals_against_away = avg_g_conceded
            clean_sheets_pct_home = cs_pct
            clean_sheets_pct_away = cs_pct

        avg_corners_home = round(sum(home_corners) / len(home_corners), 2) if home_corners else (round(sum(away_corners) / len(away_corners), 2) if away_corners else 4.0)
        avg_corners_away = round(sum(away_corners) / len(away_corners), 2) if away_corners else (round(sum(home_corners) / len(home_corners), 2) if home_corners else 4.0)

        avg_all_cards = round(sum(all_cards) / len(all_cards), 2) if all_cards else 0.00
        avg_cards_home = avg_all_cards
        avg_cards_away = avg_all_cards

        matches_count_home = len(all_cards)
        matches_count_away = len(all_cards)
            
        # Salvar no banco
        # Home
        cursor.execute("""
            INSERT INTO team_moving_averages (
                team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                clean_sheets_pct, avg_corners, avg_cards, matches_count
            ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                avg_goals_scored = VALUES(avg_goals_scored),
                avg_goals_conceded = VALUES(avg_goals_conceded),
                clean_sheets_pct = VALUES(clean_sheets_pct),
                avg_corners = VALUES(avg_corners),
                avg_cards = VALUES(avg_cards),
                matches_count = VALUES(matches_count)
        """, (team_id, team_name, avg_goals_for_home, avg_goals_against_home, clean_sheets_pct_home, avg_corners_home, avg_cards_home, matches_count_home))
        
        # Away
        cursor.execute("""
            INSERT INTO team_moving_averages (
                team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                clean_sheets_pct, avg_corners, avg_cards, matches_count
            ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                team_name = VALUES(team_name),
                avg_goals_scored = VALUES(avg_goals_scored),
                avg_goals_conceded = VALUES(avg_goals_conceded),
                clean_sheets_pct = VALUES(clean_sheets_pct),
                avg_corners = VALUES(avg_corners),
                avg_cards = VALUES(avg_cards),
                matches_count = VALUES(matches_count)
        """, (team_id, team_name, avg_goals_for_away, avg_goals_against_away, clean_sheets_pct_away, avg_corners_away, avg_cards_away, matches_count_away))
        
        conn.commit()
        print(f"✅ Médias móveis salvas com sucesso para {team_name}!")
        print(f"   - Casa: Gols Pro {avg_goals_for_home} | Gols Contra {avg_goals_against_home} | Clean Sheets {clean_sheets_pct_home}% | Escanteios {avg_corners_home} | Cartões {avg_cards_home}")
        print(f"   - Fora: Gols Pro {avg_goals_for_away} | Gols Contra {avg_goals_against_away} | Clean Sheets {clean_sheets_pct_away}% | Escanteios {avg_corners_away} | Cartões {avg_cards_away}")
        
    cursor.close()
    conn.close()
    print("\nProcesso de ingestão concluído com sucesso!")

if __name__ == '__main__':
    main()
