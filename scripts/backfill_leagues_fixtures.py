#!/usr/bin/env python3
"""
Script de Backfill em Lote por Liga para a API-Sports / API-Football.
Popula o histórico de partidas finalizadas ('FT') de todas as equipes de uma liga
consumindo APENAS 1 requisição HTTP por liga inteira (em vez de 1 requisição por time).

Uso:
  python3 backfill_leagues_fixtures.py                # Executa para as ligas prioritárias
  python3 backfill_leagues_fixtures.py 71 2026        # Liga 71 (Série A), Temporada 2026
"""

import sys
import os
import requests
import pymysql
import json
import time
from datetime import datetime

# Ligas principais mapeadas no ecossistema FootballWeb
PRIORITY_LEAGUES = [
    {"id": 71, "name": "Brasileirão Série A", "season": 2026},
    {"id": 72, "name": "Brasileirão Série B", "season": 2026},
    {"id": 73, "name": "Copa do Brasil", "season": 2026},
    {"id": 39, "name": "Premier League (Inglaterra)", "season": 2025},
    {"id": 140, "name": "La Liga (Espanha)", "season": 2025},
    {"id": 135, "name": "Serie A (Itália)", "season": 2025},
    {"id": 78, "name": "Bundesliga (Alemanha)", "season": 2025},
    {"id": 61, "name": "Ligue 1 (França)", "season": 2025},
    {"id": 94, "name": "Primeira Liga (Portugal)", "season": 2025},
    {"id": 128, "name": "Liga Profesional (Argentina)", "season": 2026},
]

def get_mysql_connection():
    password = os.environ.get("MYSQL_ROOT_PASSWORD") or "YM11rMrT32xH0E6N"
    # Tenta conexão interna (Docker) ou local (host)
    hosts = [("mysql", 3306), ("127.0.0.1", 23306), ("localhost", 3306)]
    for host, port in hosts:
        try:
            conn = pymysql.connect(
                host=host,
                port=port,
                user="root",
                password=password,
                database="footballweb",
                charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
                autocommit=True
            )
            return conn
        except Exception:
            continue
    raise Exception("Não foi possível conectar ao MySQL footballweb.")

def backfill_league(conn, league_id, season, league_name="Liga Desconhecida"):
    api_key = os.environ.get("FOOTBALL_API_KEY") or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        "x-apisports-key": api_key,
        "User-Agent": "Mozilla/5.0"
    }

    url = f"https://v3.football.api-sports.io/fixtures?league={league_id}&season={season}&status=FT"
    print(f"\n📡 [Backfill] Buscando partidas encerradas da liga #{league_id} ({league_name}, temporada {season})...")
    
    try:
        resp = requests.get(url, headers=headers, timeout=25).json()
    except Exception as e:
        print(f"❌ Erro de conexão com a API-Sports: {e}")
        return False

    errors = resp.get("errors")
    if errors and isinstance(errors, dict) and any(k in errors for k in ["rateLimit", "requests", "access"]):
        print(f"⚠️ Limite ou bloqueio de cota na API-Sports: {errors}")
        return False

    fixtures = resp.get("response", [])
    print(f"✅ Total de {len(fixtures)} partidas finalizadas retornadas em 1 única requisição HTTP!")

    if not fixtures:
        return True

    cursor = conn.cursor()
    inserted_count = 0
    updated_count = 0

    sql = """
        INSERT INTO fixtures_trends (
            fixture_id, league_id, league_name, fixture_date,
            home_team, away_team, home_team_id, away_team_id,
            status, goals_home, goals_away, cards_api_checked_at
        ) VALUES (
            %s, %s, %s, %s,
            %s, %s, %s, %s,
            %s, %s, %s, NOW()
        )
        ON DUPLICATE KEY UPDATE
            goals_home = VALUES(goals_home),
            goals_away = VALUES(goals_away),
            status = VALUES(status),
            home_team_id = VALUES(home_team_id),
            away_team_id = VALUES(away_team_id),
            cards_api_checked_at = COALESCE(cards_api_checked_at, NOW())
    """

    for f in fixtures:
        fix_id = f.get("fixture", {}).get("id")
        fix_date_raw = f.get("fixture", {}).get("date", "")
        fix_date = fix_date_raw.split('+')[0].replace('T', ' ') if fix_date_raw else None
        
        home = f.get("teams", {}).get("home", {})
        away = f.get("teams", {}).get("away", {})
        
        h_name = home.get("name", "Mandante")
        a_name = away.get("name", "Visitante")
        h_id = home.get("id")
        a_id = away.get("id")
        
        status = f.get("fixture", {}).get("status", {}).get("short", "FT")
        goals = f.get("goals", {})
        gh = goals.get("home")
        ga = goals.get("away")

        try:
            affected = cursor.execute(sql, (
                fix_id, league_id, league_name, fix_date,
                h_name, a_name, h_id, a_id,
                status, gh, ga
            ))
            if affected == 1:
                inserted_count += 1
            elif affected == 2:
                updated_count += 1
        except Exception as e_sql:
            print(f"Aviso ao salvar fixture #{fix_id}: {e_sql}")

    print(f"📊 [Backfill Concluído] Inseridas: {inserted_count} novas partidas | Atualizadas: {updated_count} existentes.")
    return True

def main():
    conn = get_mysql_connection()
    print("🚀 [Backfill Ligas API-Football] Conexão MySQL estabelecida com sucesso.")

    if len(sys.argv) >= 3:
        l_id = int(sys.argv[1])
        s_year = int(sys.argv[2])
        backfill_league(conn, l_id, s_year, f"Liga #{l_id}")
    else:
        print(f"Iniciando backfill para {len(PRIORITY_LEAGUES)} ligas prioritárias configuradas.")
        for item in PRIORITY_LEAGUES:
            success = backfill_league(conn, item["id"], item["season"], item["name"])
            if not success:
                print("🛑 Interrompendo backfill devido a erro ou cota indisponível na API.")
                break
            time.sleep(1.0)

    conn.close()
    print("\n🏁 Processo de Backfill finalizado.")

if __name__ == "__main__":
    main()
