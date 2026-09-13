#!/usr/bin/env python3
"""
Script de Saneamento e Restauração Sistêmica de U5J e Status Gatekeeper
1. Saneia apostas canceladas com status_gatekeeper = 'APROVADO' -> 'NO_BET'.
2. Recompõe estruturalmente fixtures_trends.ah_reasoning para todas as partidas
   que perderam o payload || U5J_DATA: ou foram corrompidas por cancelamento de aposta.
"""

import sys
import os
import pymysql
from datetime import datetime

sys.path.insert(0, "/root/datalake-air-flow-delta/scripts")
sys.path.insert(0, "/root/datalake-air-flow-delta")

from asian_handicap_engine import compose_compound_ah_reasoning

def get_db_connection():
    hosts_ports = [
        ("127.0.0.1", 23306),
        ("mysql", 3306),
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
                connect_timeout=3,
                autocommit=True
            )
            return conn
        except Exception:
            continue
    raise RuntimeError("Não foi possível conectar ao banco de dados MySQL.")

def run_remediation():
    conn = get_db_connection()
    cursor = conn.cursor()

    print("--- 1. Saneamento de Status Gatekeeper em Apostas Canceladas ---")
    cursor.execute("""
        UPDATE apostas 
        SET status_gatekeeper = 'NO_BET', updated_at = NOW()
        WHERE status = 'Cancelada' AND status_gatekeeper = 'APROVADO'
    """)
    saneadas = cursor.rowcount
    print(f"✅ {saneadas} apostas canceladas saneadas para status_gatekeeper = 'NO_BET'.")

    print("\n--- 2. Identificação de Partidas sem U5J ou com Reasoning Corrompido ---")
    cursor.execute("""
        SELECT fixture_id, home_team, away_team, home_team_id, away_team_id, 
               ah_suggestion, ah_reasoning, fixture_date
        FROM fixtures_trends
        WHERE DATE(fixture_date) >= CURDATE() - INTERVAL 1 DAY
          AND (
              ah_reasoning NOT LIKE '%|| U5J_DATA:%'
              OR ah_reasoning LIKE '%APOSTA CANCELADA%'
          )
        ORDER BY fixture_date ASC
    """)
    corrupted_fixtures = cursor.fetchall()
    print(f"🔍 Encontradas {len(corrupted_fixtures)} partidas com reasoning corrompido ou sem U5J.")

    restauradas = 0
    for fix in corrupted_fixtures:
        fid = fix['fixture_id']
        home_team = fix['home_team']
        away_team = fix['away_team']
        h_id = fix.get('home_team_id')
        a_id = fix.get('away_team_id')
        sug = fix.get('ah_suggestion') or 'Sem Entrada (Abstenção)'
        raw_r = fix.get('ah_reasoning') or ''

        # Se o palpite estava com texto de aposta cancelada, ajusta a sugestão para NO_BET / Abstenção se não tiver palpite ativo
        if "APOSTA CANCELADA" in raw_r:
            clean_main = "Proteção de Risco Estatístico: Partida sob análise de volatilidade e liquidez de mercado."
        else:
            clean_main = raw_r.split("||")[0].strip() if "||" in raw_r else raw_r

        # Recompõe com U5J estruturado
        comp_reasoning = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=fid,
            main_calc=clean_main,
            suggestion=sug,
            home_team=home_team,
            away_team=away_team,
            home_team_id=h_id,
            away_team_id=a_id,
            existing_reasoning=None  # Força reconstrução limpa a partir do banco
        )

        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_reasoning = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (comp_reasoning, fid))

        restauradas += 1
        has_u5j = "|| U5J_DATA:" in comp_reasoning
        print(f"  [{restauradas}/{len(corrupted_fixtures)}] #{fid} {home_team} vs {away_team} -> U5J Restaurado? {has_u5j}")

    print(f"\n✅ Total de {restauradas} partidas restauradas com sucesso!")

if __name__ == "__main__":
    run_remediation()
