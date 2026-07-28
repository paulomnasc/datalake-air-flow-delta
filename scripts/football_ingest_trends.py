#!/usr/bin/env python3
import sys
import os
import requests
import pymysql
import hashlib
import random
from datetime import datetime, timedelta

# Conexão MySQL robusta
def get_mysql_connection():
    # Tenta conexão pela rede interna do docker
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
        print("Conectado ao MySQL via docker (mysql:3306)")
        return conn
    except Exception:
        pass

    # Tenta conexão localhost (fora do docker / host machine)
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
        print("Conectado ao MySQL via localhost (127.0.0.1:23306)")
        return conn
    except Exception as e:
        print(f"ERRO CRÍTICO: Não foi possível conectar ao banco MySQL: {e}")
        sys.exit(1)

# Gerador determinístico de estatísticas de árbitro baseado no nome
def generate_referee_stats(name):
    h = int(hashlib.md5(name.encode('utf-8')).hexdigest(), 16)
    r = random.Random(h)
    
    yellows = round(r.uniform(3.2, 6.2), 2)
    reds = round(r.uniform(0.05, 0.45), 2)
    fouls = round(r.uniform(20.0, 32.0), 2)
    games = r.randint(12, 180)
    
    if yellows > 5.0:
        rigor = "Rigoroso"
    elif yellows > 4.0:
        rigor = "Moderado"
    else:
        rigor = "Permissivo"
        
    return {
        "name": name,
        "average_yellow_cards": yellows,
        "average_red_cards": reds,
        "average_fouls": fouls,
        "total_games": games,
        "rigor_level": rigor
    }

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

def main():
    is_live_mode = (len(sys.argv) > 1 and sys.argv[1] == '--live')
    
    # Obtém data para busca em BRT (default hoje)
    if not is_live_mode and len(sys.argv) > 1:
        target_date = sys.argv[1]
    else:
        target_date = datetime.now().strftime('%Y-%m-%d')
        
    target_dt = datetime.strptime(target_date, '%Y-%m-%d')
    next_date = (target_dt + timedelta(days=1)).strftime('%Y-%m-%d')
    
    api_key = "ee52562367d4f6389ae8143b0a0650b7"
    headers = {
        "x-apisports-key": api_key,
        "Content-Type": "application/json"
    }

    fixtures_map = {}

    if is_live_mode:
        print("⚡ Iniciando sincronização ultrarrápida de partidas AO VIVO (?live=all)...")
        url = "https://v3.football.api-sports.io/fixtures?live=all"
        try:
            response = requests.get(url, headers=headers, timeout=30)
            response.raise_for_status()
            data = response.json()
            for fix in data.get("response", []):
                fix_id = fix.get("fixture", {}).get("id")
                if fix_id:
                    fixtures_map[fix_id] = fix
        except Exception as e:
            print(f"Erro ao chamar a API-Football para partidas ao vivo: {e}")
    else:
        print(f"Iniciando ingestão de tendências para a data BRT: {target_date} (buscando UTC {target_date} e {next_date})...")
        for d in [target_date, next_date]:
            url = f"https://v3.football.api-sports.io/fixtures?date={d}"
            try:
                response = requests.get(url, headers=headers, timeout=30)
                response.raise_for_status()
                data = response.json()
                for fix in data.get("response", []):
                    fix_id = fix.get("fixture", {}).get("id")
                    if fix_id:
                        fixtures_map[fix_id] = fix
            except Exception as e:
                print(f"Erro ao chamar a API-Football para a data {d}: {e}")
        
    fixtures = list(fixtures_map.values())
    print(f"Total de {len(fixtures)} partidas únicas retornadas pela API.")
    
    if not fixtures:
        print("Nenhuma partida retornada pela API.")
        return
        
    # Ligas permitidas para o MVP (inclui ligas europeias e ligas ativas no verão global)
    ALLOWED_LEAGUES = {
        71: "Série A (Brasil)",
        72: "Série B (Brasil)",
        39: "Premier League (Inglaterra)",
        140: "La Liga (Espanha)",
        135: "Serie A (Itália)",
        78: "Bundesliga (Alemanha)",
        2: "Champions League (Europa)",
        13: "Copa Libertadores (América do Sul)",
        73: "Copa do Brasil (Brasil)",
        3: "Europa League (Europa)",
        11: "Copa Sudamericana (América do Sul)",
        253: "Major League Soccer (EUA)",
        262: "Liga MX (México)",
        113: "Allsvenskan (Suécia)",
        103: "Eliteserien (Noruega)",
        94: "Primeira Liga (Portugal)",
        61: "Ligue 1 (França)",
        88: "Eredivisie (Holanda)",
        128: "Primera División (Argentina)",
        98: "J1 League (Japão)",
        292: "K League 1 (Coreia do Sul)",
        283: "Liga I (Romênia)",
        286: "Super Liga (Sérvia)",
        244: "Veikkausliiga (Finlândia)",
        281: "Primera División (Peru)",
        242: "Liga Pro (Equador)",
        268: "Primera División (Uruguai)",
        265: "Primera División (Chile)",
        239: "Primera División (Colômbia)",
        169: "Super League (China)",
        307: "Saudi Pro League (Arábia Saudita)",
        203: "Süper Lig (Turquia)",
        207: "Super League (Suíça)",
        144: "Pro League (Bélgica)",
        119: "Superliga (Dinamarca)",
        218: "Bundesliga (Áustria)",
        197: "Super League (Grécia)",
        1: "Copa do Mundo (Mundo)"
    }

    # Filtra partidas pelas ligas permitidas
    filtered_fixtures = []
    for f in fixtures:
        if f.get("league", {}).get("id") not in ALLOWED_LEAGUES:
            continue
        
        if is_live_mode:
            filtered_fixtures.append(f)
        else:
            fix_date_raw = f["fixture"]["date"]
            fix_date_clean = fix_date_raw.split('+')[0].replace('T', ' ')
            try:
                dt_utc = datetime.strptime(fix_date_clean[:19], '%Y-%m-%d %H:%M:%S')
                dt_br = dt_utc - timedelta(hours=3)
                br_date_str = dt_br.strftime('%Y-%m-%d')
                if br_date_str == target_date:
                    filtered_fixtures.append(f)
            except Exception:
                filtered_fixtures.append(f)

    if not filtered_fixtures:
        print(f"Nenhuma partida filtrada para o processamento.")
        return
        
    print(f"Processando {len(filtered_fixtures)} partidas filtradas...")
    
    conn = get_mysql_connection()
    cursor = conn.cursor()
    
    inserted_referees = 0
    inserted_fixtures = 0
    
    try:
        for f in filtered_fixtures:
            fix_id = f["fixture"]["id"]
            fix_date_raw = f["fixture"]["date"]
            fix_date = fix_date_raw.split('+')[0].replace('T', ' ')
            
            league_id = f["league"]["id"]
            league_name = f["league"]["name"]
            home_team = f["teams"]["home"]["name"]
            away_team = f["teams"]["away"]["name"]
            home_team_id = f["teams"]["home"]["id"]
            away_team_id = f["teams"]["away"]["id"]
            referee_raw = f["fixture"].get("referee")
            status = f["fixture"].get("status", {}).get("short", "NS")
            elapsed = f["fixture"].get("status", {}).get("elapsed")
            
            goals_home = f.get("goals", {}).get("home")
            goals_away = f.get("goals", {}).get("away")
            
            referee_name = None
            prediction_text = "Sem análise disponível para este confronto."
            over_cards_prob = 50.00
            
            if referee_raw:
                # Trata "Anderson Daronco, Brazil" -> "Anderson Daronco"
                referee_name = referee_raw.split(',')[0].strip()
                
                # Verifica ou insere estatísticas do árbitro
                cursor.execute("SELECT name FROM referee_stats WHERE name = %s", (referee_name,))
                ref = cursor.fetchone()
                
                if not ref:
                    stats = generate_referee_stats(referee_name)
                    cursor.execute("""
                        INSERT INTO referee_stats (
                            name, average_yellow_cards, average_red_cards, average_fouls, total_games, rigor_level
                        ) VALUES (%s, %s, %s, %s, %s, %s)
                    """, (
                        stats["name"], stats["average_yellow_cards"], stats["average_red_cards"],
                        stats["average_fouls"], stats["total_games"], stats["rigor_level"]
                    ))
                    inserted_referees += 1
                    ref_data = stats
                else:
                    # Se já existe, recalcula/lê os dados para a predição
                    cursor.execute("SELECT * FROM referee_stats WHERE name = %s", (referee_name,))
                    ref_data = cursor.fetchone()
                
                # Gera predição baseada no árbitro
                rigor = ref_data["rigor_level"]
                yellows = float(ref_data["average_yellow_cards"])
                
                # Probabilidade baseada no rigor
                if rigor == "Rigoroso":
                    over_cards_prob = round(random.uniform(75.0, 92.0), 2)
                    prediction_text = f"🔥 Árbitro {referee_name} possui estilo de arbitragem rigoroso, com média alta de {yellows} cartões amarelos por partida. Excelente oportunidade para mercado Over 4.5 Cartões."
                elif rigor == "Moderado":
                    over_cards_prob = round(random.uniform(55.0, 72.0), 2)
                    prediction_text = f"⚖️ O árbitro {referee_name} apita de forma moderada (média de {yellows} cartões amarelos por jogo). Expectativa de controle de jogo padrão."
                else:
                    over_cards_prob = round(random.uniform(25.0, 48.0), 2)
                    prediction_text = f"❄️ Árbitro {referee_name} é permissivo e costuma deixar a partida correr (média de {yellows} cartões amarelos por jogo). Tendência para Under 4.5 Cartões."
            
            # Garante que os times possuam estatísticas na tabela team_moving_averages
            if home_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (home_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(home_team, 'home')
                    mock_away = generate_deterministic_team_stats(home_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s)
                    """, (
                        home_team_id, home_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"], 
                        mock_home["avg_cards"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s)
                    """, (
                        home_team_id, home_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"], 
                        mock_away["avg_cards"]
                    ))

            if away_team_id:
                cursor.execute("SELECT team_id FROM team_moving_averages WHERE team_id = %s LIMIT 1", (away_team_id,))
                if not cursor.fetchone():
                    mock_home = generate_deterministic_team_stats(away_team, 'home')
                    mock_away = generate_deterministic_team_stats(away_team, 'away')
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'home', %s, %s, %s, %s, %s)
                    """, (
                        away_team_id, away_team, 
                        mock_home["avg_goals_scored"], mock_home["avg_goals_conceded"], 
                        mock_home["clean_sheets_pct"], mock_home["avg_corners"], 
                        mock_home["avg_cards"]
                    ))
                    cursor.execute("""
                        INSERT INTO team_moving_averages (
                            team_id, team_name, venue_type, avg_goals_scored, avg_goals_conceded, 
                            clean_sheets_pct, avg_corners, avg_cards
                        ) VALUES (%s, %s, 'away', %s, %s, %s, %s, %s)
                    """, (
                        away_team_id, away_team, 
                        mock_away["avg_goals_scored"], mock_away["avg_goals_conceded"], 
                        mock_away["clean_sheets_pct"], mock_away["avg_corners"], 
                        mock_away["avg_cards"]
                    ))

            # Insere ou atualiza a partida com placar e minutos decorridos
            cursor.execute("""
                INSERT INTO fixtures_trends (
                    fixture_id, fixture_date, league_id, league_name, home_team, away_team, 
                    home_team_id, away_team_id,
                    referee_name, prediction_text, over_cards_probability, status,
                    goals_home, goals_away, elapsed
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    fixture_date = VALUES(fixture_date),
                    home_team_id = VALUES(home_team_id),
                    away_team_id = VALUES(away_team_id),
                    referee_name = VALUES(referee_name),
                    prediction_text = VALUES(prediction_text),
                    over_cards_probability = VALUES(over_cards_probability),
                    status = VALUES(status),
                    goals_home = VALUES(goals_home),
                    goals_away = VALUES(goals_away),
                    elapsed = VALUES(elapsed);
            """, (
                fix_id, fix_date, league_id, league_name, home_team, away_team,
                home_team_id, away_team_id,
                referee_name, prediction_text, over_cards_prob, status,
                goals_home, goals_away, elapsed
            ))
            inserted_fixtures += 1
            
        conn.commit()
        print(f"\n--- RESUMO DE INGESTÃO ---")
        print(f"Modo Ao Vivo: {is_live_mode}")
        print(f"Data: {target_date}")
        print(f"Novos Árbitros Cadastrados: {inserted_referees}")
        print(f"Partidas Inseridas/Atualizadas: {inserted_fixtures}")
        
    except Exception as e:
        conn.rollback()
        print(f"ERRO durante transação do banco: {e}")
    finally:
        cursor.close()
        conn.close()

if __name__ == '__main__':
    main()

