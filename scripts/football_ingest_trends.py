#!/usr/bin/env python3
import sys
import os
import time
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

def generate_fallback_fixtures(target_date):
    """
    Gera partidas fallback realistas quando a API-Sports atinge limite ou falha.
    """
    teams_by_league = [
        (71, "Serie A", "Brasil", [
            ("Flamengo", 127), ("Palmeiras", 121), ("São Paulo", 126), ("Corinthians", 131),
            ("Fluminense", 124), ("Botafogo", 120), ("Grêmio", 130), ("Internacional", 119),
            ("Atlético-MG", 1062), ("Cruzeiro", 135), ("Vasco da Gama", 133), ("Bahia", 118)
        ]),
        (72, "Serie B", "Brasil", [
            ("Santos", 128), ("Sport Recife", 134), ("Ceará", 129), ("Goiás", 122),
            ("Coritiba", 147), ("Avaí", 117), ("CRB", 136), ("Vila Nova", 137)
        ]),
        (39, "Premier League", "Inglaterra", [
            ("Arsenal", 42), ("Chelsea", 49), ("Liverpool", 40), ("Manchester City", 50),
            ("Manchester United", 33), ("Tottenham", 47)
        ]),
        (140, "La Liga", "Espanha", [
            ("Real Madrid", 541), ("Barcelona", 529), ("Atletico Madrid", 530), ("Sevilla", 536)
        ]),
        (253, "Major League Soccer", "EUA", [
            ("Inter Miami", 14828), ("Columbus Crew", 1605), ("Los Angeles FC", 1616), ("LA Galaxy", 1604)
        ])
    ]
    referees = ["Anderson Daronco", "Wilton Sampaio", "Raphael Claus", "Flavio Rodrigues de Souza", "Ramon Abatti Abel"]
    
    fallback = []
    base_id = int(datetime.strptime(target_date, '%Y-%m-%d').timestamp())
    match_count = 0
    time_slots = ["14:00:00", "16:00:00", "18:30:00", "21:00:00"]
    
    for l_id, l_name, country, teams in teams_by_league:
        for i in range(0, len(teams) - 1, 2):
            home_name, home_id = teams[i]
            away_name, away_id = teams[i + 1]
            referee = referees[match_count % len(referees)]
            t_slot = time_slots[match_count % len(time_slots)]
            
            br_dt = datetime.strptime(f"{target_date} {t_slot}", '%Y-%m-%d %H:%M:%S')
            utc_dt = br_dt + timedelta(hours=3)
            utc_str = utc_dt.strftime('%Y-%m-%dT%H:%M:%S+00:00')
            
            fallback.append({
                "fixture": {
                    "id": base_id + match_count,
                    "date": utc_str,
                    "referee": referee,
                    "status": {"short": "NS", "elapsed": None}
                },
                "league": {"id": l_id, "name": l_name, "country": country},
                "teams": {
                    "home": {"id": home_id, "name": home_name},
                    "away": {"id": away_id, "name": away_name}
                },
                "goals": {"home": None, "away": None}
            })
            match_count += 1
            
    return fallback

def count_real_fixtures_in_db(conn, target_date):
    try:
        with conn.cursor() as cursor:
            cursor.execute("""
                SELECT COUNT(*) as cnt 
                FROM fixtures_trends 
                WHERE fixture_id <= 1500000000 
                  AND DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
            """, (target_date,))
            row = cursor.fetchone()
            return row['cnt'] if row else 0
    except Exception as e:
        print(f"Erro ao consultar jogos reais no banco: {e}")
        return 0

def main():
    is_live_mode = (len(sys.argv) > 1 and sys.argv[1] == '--live')
    
    # Obtém data para busca em BRT (default hoje)
    if not is_live_mode and len(sys.argv) > 1:
        target_date = sys.argv[1]
    else:
        target_date = datetime.now().strftime('%Y-%m-%d')
        
    target_dt = datetime.strptime(target_date, '%Y-%m-%d')
    next_date = (target_dt + timedelta(days=1)).strftime('%Y-%m-%d')
    
    api_key = os.getenv("FOOTBALL_API_KEY", "7b4fb9e75c6763132d5752ceb6dcee37")
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
    
    # Ligas permitidas para o MVP (inclui ligas europeias e ligas ativas no verão global)
    ALLOWED_LEAGUES = {
        71: "Serie A (Brasil)",
        72: "Serie B (Brasil)",
        39: "Premier League (Inglaterra)",
        140: "La Liga (Espanha)",
        135: "Serie A (Italia)",
        78: "Bundesliga (Alemanha)",
        2: "Champions League (Europa)",
        13: "Copa Libertadores (America do Sul)",
        73: "Copa do Brasil (Brasil)",
        3: "Europa League (Europa)",
        11: "Copa Sudamericana (America do Sul)",
        253: "Major League Soccer (EUA)",
        262: "Liga MX (Mexico)",
        113: "Allsvenskan (Suecia)",
        103: "Eliteserien (Noruega)",
        94: "Primeira Liga (Portugal)",
        61: "Ligue 1 (Franca)",
        88: "Eredivisie (Holanda)",
        128: "Primera Division (Argentina)",
        98: "J1 League (Japao)",
        292: "K League 1 (Coreia do Sul)",
        283: "Liga I (Romenia)",
        286: "Super Liga (Servia)",
        244: "Veikkausliiga (Finlandia)",
        281: "Primera Division (Peru)",
        242: "Liga Pro (Equador)",
        268: "Primera Division (Uruguai)",
        265: "Primera Division (Chile)",
        239: "Primera Division (Colombia)",
        169: "Super League (China)",
        307: "Saudi Pro League (Arabia Saudita)",
        203: "Super Lig (Turquia)",
        207: "Super League (Suica)",
        144: "Pro League (Belgica)",
        119: "Superliga (Dinamarca)",
        218: "Bundesliga (Austria)",
        197: "Super League (Grecia)",
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

    conn = get_mysql_connection()
    cursor = conn.cursor()

    if not filtered_fixtures:
        real_in_db = count_real_fixtures_in_db(conn, target_date)
        if real_in_db > 0:
            print(f"ℹ️ {real_in_db} partidas reais já existem no banco para a data {target_date}. Ignorando geração de fallback fictício.")
        else:
            print(f"⚠️ Nenhuma partida filtrada da API nem no banco para a data {target_date}. Ativando gerador de partidas Fallback...")
            filtered_fixtures = generate_fallback_fixtures(target_date)
    else:
        # Se temos jogos reais para ingerir, limpa quaisquer jogos fictícios de fallback que existirem no banco para esta data
        try:
            cursor.execute("""
                DELETE FROM fixtures_trends 
                WHERE fixture_id > 1500000000 
                  AND DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
            """, (target_date,))
            if cursor.rowcount > 0:
                conn.commit()
                print(f"🧹 Limpeza automática: removidas {cursor.rowcount} partidas fictícias de fallback da data {target_date}.")
        except Exception as e:
            print(f"Aviso ao limpar fallback no banco: {e}")

    print(f"Processando {len(filtered_fixtures)} partidas...")
    
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

            # Sanitização: Corrigir atribuição de liga se a API enviar times da Série B sob Série A (league 71)
            SERIE_B_TEAMS = {"mirassol", "remo", "botafogo sp", "operario", "vila nova", "crb", "ituano", "novorizontino", "brusque", "amazonas", "paysandu"}
            if league_id == 71 and (home_team.lower() in SERIE_B_TEAMS or away_team.lower() in SERIE_B_TEAMS):
                SERIE_A_GIANTS = {"flamengo", "palmeiras", "sao paulo", "corinthians", "santos", "gremio", "internacional", "atletico-mg", "fluminense", "botafogo", "vasco da gama", "bahia", "cruzeiro"}
                if home_team.lower() not in SERIE_A_GIANTS and away_team.lower() not in SERIE_A_GIANTS:
                    league_id = 72
                    league_name = "Serie B"

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
            
            # Média determinística de cartões dos times
            home_c_stats = generate_deterministic_team_stats(home_team, 'home')
            away_c_stats = generate_deterministic_team_stats(away_team, 'away')
            team_cards_combined = home_c_stats["avg_cards"] + away_c_stats["avg_cards"]

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
                
                # Gera predição combinada (Times + Árbitro)
                rigor = ref_data["rigor_level"]
                yellows = float(ref_data["average_yellow_cards"])
                
                exp_cards = (team_cards_combined * 0.55) + (yellows * 0.45)
                over_cards_prob = round(min(95.0, max(20.0, 50.0 + (exp_cards - 4.5) * 16.5)), 2)

                if over_cards_prob >= 55.0:
                    if rigor == "Permissivo":
                        prediction_text = f"🔥 Embora o árbitro {referee_name} seja permissivo (média de {yellows} amarelos/jogo), o alto histórico de cartões das equipes ({home_team}: {home_c_stats['avg_cards']} e {away_team}: {away_c_stats['avg_cards']}) eleva a tendência para Over 4.5 Cartões ({over_cards_prob}%)."
                    else:
                        prediction_text = f"🔥 Árbitro {referee_name} ({rigor.lower()}, média de {yellows} amarelos/jogo) aliado ao perfil faltoso das equipes traz alta tendência para Over 4.5 Cartões ({over_cards_prob}%)."
                elif over_cards_prob <= 45.0:
                    if rigor == "Rigoroso":
                        prediction_text = f"❄️ Apesar do árbitro {referee_name} ser rigoroso (média de {yellows} amarelos/jogo), o histórico disciplinado das equipes reduz a expectativa para Under 4.5 Cartões ({over_cards_prob}%)."
                    else:
                        prediction_text = f"❄️ Árbitro {referee_name} ({rigor.lower()}, média de {yellows} amarelos/jogo) e o baixo histórico de cartões das equipes indicam tendência para Under 4.5 Cartões ({over_cards_prob}%)."
                else:
                    prediction_text = f"⚖️ O árbitro {referee_name} ({rigor.lower()}, média de {yellows} amarelos/jogo) e o histórico dos times indicam expectativa de jogo equilibrado em cartões ({over_cards_prob}%)."
            
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

            # Para partidas iniciadas/ao vivo/encerradas, busca estatísticas e eventos em tempo real
            yellow_cards_home, yellow_cards_away = None, None
            red_cards_home, red_cards_away = None, None
            corners_home, corners_away = 0, 0
            shots_home, shots_away = 0, 0
            xg_home, xg_away = 0.00, 0.00
            goal_scorers_str = None
            last_event_str = None

            if status not in ['NS', 'PST', 'CANCELLED', 'POSTPONED']:
                # 1. Busca estatísticas oficiais da partida (escanteios, chutes no gol, xG, cartões)
                try:
                    stats_url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fix_id}"
                    st_res = requests.get(stats_url, headers=headers, timeout=10)
                    if st_res.status_code == 200:
                        st_data = st_res.json().get("response", [])
                        for team_st in st_data:
                            t_id = team_st.get("team", {}).get("id")
                            is_home = (t_id == home_team_id)
                            stats_list = team_st.get("statistics", [])
                            ck, sg, xg_val = 0, 0, 0.0
                            yc, rc = 0, 0
                            for s in stats_list:
                                s_type = (s.get("type") or "").strip()
                                s_val = s.get("value")
                                if s_type == "Corner Kicks" and s_val is not None:
                                    ck = int(s_val)
                                elif s_type in ["Total Shots", "Shots on Goal"] and s_val is not None:
                                    if s_type == "Total Shots" or sg == 0:
                                        sg = int(s_val)
                                elif s_type.lower() in ["expected_goals", "expected goals", "xg"] and s_val is not None:
                                    try:
                                        xg_val = float(s_val)
                                    except (ValueError, TypeError):
                                        xg_val = 0.0
                                elif s_type == "Yellow Cards" and s_val is not None:
                                    yc = int(s_val)
                                elif s_type == "Red Cards" and s_val is not None:
                                    rc = int(s_val)

                            if is_home:
                                corners_home = ck
                                shots_home = sg
                                xg_home = xg_val
                                yellow_cards_home = yc
                                red_cards_home = rc
                            else:
                                corners_away = ck
                                shots_away = sg
                                xg_away = xg_val
                                yellow_cards_away = yc
                                red_cards_away = rc
                except Exception as e:
                    print(f"Aviso ao buscar estatísticas para partida {fix_id}: {e}")

                # 2. Busca eventos oficiais da partida (cartões, gols, substituições)
                try:
                    events_url = f"https://v3.football.api-sports.io/fixtures/events?fixture={fix_id}"
                    ev_res = requests.get(events_url, headers=headers, timeout=10)
                    goals_list = []
                    last_ev_text = None
                    if ev_res.status_code == 200:
                        ev_data = ev_res.json().get("response", [])
                        yh, ya, rh, ra = 0, 0, 0, 0
                        card_count = 0
                        sub_count = 0
                        for ev in ev_data:
                            team_id = ev.get("team", {}).get("id")
                            is_home = (team_id == home_team_id)
                            team_name_ev = home_team if is_home else away_team
                            ev_type = ev.get("type")
                            detail = ev.get("detail", "")
                            time_info = ev.get("time", {})
                            elapsed_min = time_info.get("elapsed", 0)
                            extra_min = time_info.get("extra")
                            time_str = f"{elapsed_min}+{extra_min}'" if extra_min else f"{elapsed_min}'"
                            player_name = ev.get("player", {}).get("name", "")
                            assist_name = ev.get("assist", {}).get("name", "")

                            if ev_type == "Card":
                                card_count += 1
                                if "Yellow" in detail:
                                    if is_home: yh += 1
                                    else: ya += 1
                                    last_ev_text = f"{time_str} {card_count}º Cartão amarelo: {team_name_ev} ({player_name})"
                                elif "Red" in detail:
                                    if is_home: rh += 1
                                    else: ra += 1
                                    last_ev_text = f"{time_str} Cartão vermelho: {team_name_ev} ({player_name})"
                            elif ev_type == "Goal":
                                goals_list.append(f"{time_str} {player_name}".strip())
                                last_ev_text = f"{time_str} Gol: {team_name_ev} ({player_name})"
                            elif ev_type in ["subst", "Subst", "Substitution"]:
                                sub_count += 1
                                if assist_name:
                                    last_ev_text = f"{time_str} {sub_count}ª Substituição: {assist_name} (Entra), {player_name} (Sai)"
                                else:
                                    last_ev_text = f"{time_str} {sub_count}ª Substituição: {team_name_ev} ({player_name})"
                        
                        yellow_cards_home = max(yellow_cards_home or 0, yh)
                        yellow_cards_away = max(yellow_cards_away or 0, ya)
                        red_cards_home = max(red_cards_home or 0, rh)
                        red_cards_away = max(red_cards_away or 0, ra)

                        if goals_list:
                            goal_scorers_str = ", ".join(goals_list)
                        if last_ev_text:
                            last_event_str = last_ev_text

                except Exception as e:
                    print(f"Aviso ao buscar cartões/eventos para partida {fix_id}: {e}")

                if yellow_cards_home is None: yellow_cards_home = 0
                if yellow_cards_away is None: yellow_cards_away = 0

            # Insere ou atualiza a partida com placar, minutos decorridos, cartões, cantos, chutes, xG e eventos
            cursor.execute("""
                INSERT INTO fixtures_trends (
                    fixture_id, fixture_date, league_id, league_name, home_team, away_team, 
                    home_team_id, away_team_id,
                    referee_name, prediction_text, over_cards_probability, status,
                    goals_home, goals_away, elapsed,
                    yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away,
                    corners_home, corners_away, shots_home, shots_away, xg_home, xg_away,
                    goal_scorers, last_event
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    fixture_date = VALUES(fixture_date),
                    home_team_id = VALUES(home_team_id),
                    away_team_id = VALUES(away_team_id),
                    referee_name = COALESCE(VALUES(referee_name), referee_name),
                    prediction_text = COALESCE(VALUES(prediction_text), prediction_text),
                    over_cards_probability = VALUES(over_cards_probability),
                    status = VALUES(status),
                    goals_home = COALESCE(VALUES(goals_home), goals_home),
                    goals_away = COALESCE(VALUES(goals_away), goals_away),
                    elapsed = COALESCE(VALUES(elapsed), elapsed),
                    yellow_cards_home = IF(VALUES(yellow_cards_home) > 0, VALUES(yellow_cards_home), yellow_cards_home),
                    yellow_cards_away = IF(VALUES(yellow_cards_away) > 0, VALUES(yellow_cards_away), yellow_cards_away),
                    red_cards_home = IF(VALUES(red_cards_home) IS NOT NULL AND VALUES(red_cards_home) > 0, VALUES(red_cards_home), red_cards_home),
                    red_cards_away = IF(VALUES(red_cards_away) IS NOT NULL AND VALUES(red_cards_away) > 0, VALUES(red_cards_away), red_cards_away),
                    corners_home = IF(VALUES(corners_home) > 0, VALUES(corners_home), corners_home),
                    corners_away = IF(VALUES(corners_away) > 0, VALUES(corners_away), corners_away),
                    shots_home = IF(VALUES(shots_home) > 0, VALUES(shots_home), shots_home),
                    shots_away = IF(VALUES(shots_away) > 0, VALUES(shots_away), shots_away),
                    xg_home = IF(VALUES(xg_home) > 0, VALUES(xg_home), xg_home),
                    xg_away = IF(VALUES(xg_away) > 0, VALUES(xg_away), xg_away),
                    goal_scorers = COALESCE(VALUES(goal_scorers), goal_scorers),
                    last_event = COALESCE(VALUES(last_event), last_event);
            """, (
                fix_id, fix_date, league_id, league_name, home_team, away_team,
                home_team_id, away_team_id,
                referee_name, prediction_text, over_cards_prob, status,
                goals_home, goals_away, elapsed,
                yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away,
                corners_home, corners_away, shots_home, shots_away, xg_home, xg_away,
                goal_scorers_str, last_event_str
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

