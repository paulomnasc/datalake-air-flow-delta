#!/usr/bin/env python3
"""
scripts/sanear_u5j_selecoes_copa.py

Saneamento do cache de U5J (team_last5_cache) para seleções nacionais da Copa do Mundo 2026.
Garante que confrontos contra seleções Tier 1 (Brasil, França, Espanha, etc.)
tenham a flag is_tier_1: true devidamente propagada no form_json.
"""

import sys
import os
import json
import pymysql

# Garante inclusão do diretório scripts no path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from leagues_config import (
    get_world_cup_standings_cache,
    is_tier_1_elite_club,
    get_team_pedigree_bonus
)

def get_db_connection():
    """
    Obtém conexão com o MySQL testando rede interna Docker e portas do host.
    """
    is_docker = os.path.exists('/.dockerenv')
    hosts_ports = [("mysql", 3306), ("127.0.0.1", 23306), ("localhost", 3306)] if is_docker else [("127.0.0.1", 23306), ("mysql", 3306), ("localhost", 3306)]
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
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception as e:
            continue
    raise RuntimeError("❌ Não foi possível conectar ao MySQL em nenhuma das portas testadas.")

def sanear_cache_u5j_selecoes():
    """
    Varre os registros de team_last5_cache e atualiza is_tier_1 nas partidas da Copa do Mundo.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    total_scanned = 0
    total_updated = 0
    total_matches_tagged = 0

    print("🔍 [Saneamento U5J Copa] Carregando cache de seleções do Mundial...")
    cache_id, cache_name = get_world_cup_standings_cache()
    print(f"📋 [Saneamento U5J Copa] {len(cache_id)} seleções mapeadas no cache da Copa.")

    # Busca todos os registros de team_last5_cache
    cursor.execute("SELECT team_id, team_name, league_id, form_json FROM team_last5_cache")
    rows = cursor.fetchall()
    total_scanned = len(rows)
    print(f"📊 [Saneamento U5J Copa] Analisando {total_scanned} registros em team_last5_cache...")

    for row in rows:
        team_id = row['team_id']
        team_name = row['team_name']
        form_raw = row['form_json']

        if not form_raw:
            continue

        if isinstance(form_raw, str):
            try:
                matches = json.loads(form_raw)
            except Exception as e:
                print(f"⚠️ [Saneamento U5J Copa] JSON inválido para team_id {team_id}: {e}")
                continue
        elif isinstance(form_raw, list):
            matches = form_raw
        else:
            continue

        changed = False
        for m in matches:
            opp_id = m.get('opponent_id')
            opp_name = m.get('opponent', '')
            current_t1 = m.get('is_tier_1', False)

            # Reavalia se o adversário é Tier 1
            new_t1 = is_tier_1_elite_club(team_id=opp_id, team_name=opp_name)
            if new_t1 != current_t1:
                m['is_tier_1'] = new_t1
                changed = True
                if new_t1:
                    total_matches_tagged += 1
                    print(f"  🏷️ Time #{team_id} ({team_name}) -> Adversário '{opp_name}' (#{opp_id}) marcado como TIER 1")

        if changed:
            new_json_str = json.dumps(matches, ensure_ascii=False)
            cursor.execute(
                "UPDATE team_last5_cache SET form_json = %s WHERE team_id = %s",
                (new_json_str, team_id)
            )
            total_updated += 1

    conn.close()
    print(f"\n✅ [Saneamento U5J Copa Concluído]")
    print(f"   • Times analisados: {total_scanned}")
    print(f"   • Registros de cache atualizados: {total_updated}")
    print(f"   • Confrontos reclassificados como Tier 1: {total_matches_tagged}")

if __name__ == "__main__":
    sanear_cache_u5j_selecoes()
