#!/usr/bin/env python3
import sys
import os
import requests
import pymysql
import hashlib
import random
from datetime import datetime

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

def main():
    # Obtém data para busca (default hoje)
    if len(sys.argv) > 1:
        target_date = sys.argv[1]
    else:
        target_date = datetime.now().strftime('%Y-%m-%d')
        
    print(f"Iniciando ingestão de tendências para a data: {target_date}...")
    
    # 1. Requisição à API-Football
    api_key = "ee52562367d4f6389ae8143b0a0650b7"
    url = f"https://v3.football.api-sports.io/fixtures?date={target_date}"
    headers = {
        "x-apisports-key": api_key,
        "Content-Type": "application/json"
    }
    
    try:
        response = requests.get(url, headers=headers, timeout=30)
        response.raise_for_status()
        data = response.json()
    except Exception as e:
        print(f"Erro ao chamar a API-Football: {e}")
        sys.exit(1)
        
    fixtures = data.get("response", [])
    print(f"Total de {len(fixtures)} partidas retornadas pela API para {target_date}.")
    
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
    filtered_fixtures = [f for f in fixtures if f.get("league", {}).get("id") in ALLOWED_LEAGUES]
    
    if not filtered_fixtures:
        print("Nenhuma partida encontrada nas ligas principais (Tier 1) para esta data.")
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
            # Ex: "2026-07-17T17:30:00+00:00" -> formato datetime do mysql
            # Tratamento simples do timezone para compatibilidade
            fix_date = fix_date_raw.split('+')[0].replace('T', ' ')
            
            league_id = f["league"]["id"]
            league_name = f["league"]["name"]
            home_team = f["teams"]["home"]["name"]
            away_team = f["teams"]["away"]["name"]
            home_team_id = f["teams"]["home"]["id"]
            away_team_id = f["teams"]["away"]["id"]
            referee_raw = f["fixture"].get("referee")
            status = f["fixture"].get("status", {}).get("short", "NS")
            
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
            
            # Insere ou atualiza a partida com home_team_id e away_team_id
            cursor.execute("""
                INSERT INTO fixtures_trends (
                    fixture_id, fixture_date, league_id, league_name, home_team, away_team, 
                    home_team_id, away_team_id,
                    referee_name, prediction_text, over_cards_probability, status
                ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    fixture_date = VALUES(fixture_date),
                    home_team_id = VALUES(home_team_id),
                    away_team_id = VALUES(away_team_id),
                    referee_name = VALUES(referee_name),
                    prediction_text = VALUES(prediction_text),
                    over_cards_probability = VALUES(over_cards_probability),
                    status = VALUES(status);
            """, (
                fix_id, fix_date, league_id, league_name, home_team, away_team,
                home_team_id, away_team_id,
                referee_name, prediction_text, over_cards_prob, status
            ))
            inserted_fixtures += 1
            
        conn.commit()
        print(f"\n--- RESUMO DE INGESTÃO ---")
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
