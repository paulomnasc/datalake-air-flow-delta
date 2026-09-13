#!/usr/bin/env python3
import sys
import os
import json
import pymysql

sys.path.insert(0, "/root/datalake-air-flow-delta/scripts")
from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    cancelar_e_estornar_aposta_handicap
)

conn = pymysql.connect(
    host="127.0.0.1",
    port=23306,
    user="root",
    password="YM11rMrT32xH0E6N",
    database="footballweb",
    cursorclass=pymysql.cursors.DictCursor
)

print("=" * 70)
print("INICIANDO REPROCESSAMENTO GERAL DE CARDS E APOSTAS PENDENTES DE HOJE (13/09/2026)")
print("=" * 70)

# 1. Selecionar todas as partidas de hoje ainda não iniciadas (NS ou TBD)
with conn.cursor() as cur:
    cur.execute("""
        SELECT *
        FROM fixtures_trends
        WHERE fixture_date >= '2026-09-13 00:00:00' 
          AND fixture_date <= '2026-09-13 23:59:59'
          AND status IN ('NS', 'TBD')
        ORDER BY fixture_date ASC
    """)
    fixtures = cur.fetchall()

print(f"Total de partidas não iniciadas encontradas em fixtures_trends: {len(fixtures)}")

processed_count = 0
updated_cards = 0
cancelled_bets = 0
updated_bets = 0

for f in fixtures:
    fid = f["fixture_id"]
    h_team = f["home_team"]
    a_team = f["away_team"]
    old_ah = f["ah_suggestion"]
    
    with conn.cursor() as cur:
        status_gk, palpite, conf, detalhe, best_cand, approved = calculate_unified_handicap_recommendation(
            f, allow_api_fetch=True, cursor=cur
        )
        
        # Atualizar card no fixtures_trends
        cur.execute("""
            UPDATE fixtures_trends
            SET ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (palpite, conf, detalhe, fid))
        updated_cards += 1
        
        # Verificar se existe aposta pendente para este fixture
        cur.execute("""
            SELECT * FROM apostas
            WHERE fixture_id = %s AND status = 'Pendente' AND mercado LIKE '%%Handicap%%'
        """, (fid,))
        pending_bet = cur.fetchone()
        
        if pending_bet:
            bet_id = pending_bet["id"]
            old_palpite = pending_bet["palpite"]
            
            if status_gk == "NO_BET" or not best_cand:
                # Cancelar e estornar
                cancelar_e_estornar_aposta_handicap(cur, fid, detalhe)
                cancelled_bets += 1
                print(f"  ❌ [CANCELADA] Bet #{bet_id} | Fixture {fid} ({h_team} x {a_team}) | '{old_palpite}' -> CANCELADA / ESTORNADA (GK: {status_gk})")
            else:
                # Atualizar aposta existente com os novos dados de Poisson / Betano
                cur.execute("""
                    UPDATE apostas
                    SET palpite = %s,
                        odd = %s,
                        odd_justa = %s,
                        probabilidade_poisson = %s,
                        ev_percentual = %s,
                        status_gatekeeper = %s,
                        resultado_detalhado = %s,
                        updated_at = NOW()
                    WHERE id = %s
                """, (
                    palpite,
                    best_cand.get('odd'),
                    best_cand.get('eval', {}).get('odd_justa'),
                    best_cand.get('eval', {}).get('prob_eff'),
                    best_cand.get('eval', {}).get('ev_percent'),
                    status_gk,
                    detalhe,
                    bet_id
                ))
                updated_bets += 1
                print(f"  🔄 [ATUALIZADA] Bet #{bet_id} | Fixture {fid} ({h_team} x {a_team}) | '{old_palpite}' -> '{palpite}' @ {best_cand.get('odd')}")
        else:
            # Não havia aposta pendente. Informar status do card
            if status_gk == "APROVADO" and best_cand:
                print(f"  🟢 [CARD APROVADO] Fixture {fid} ({h_team} x {a_team}) | AH: '{palpite}' @ {best_cand.get('odd')}")
            else:
                if old_ah != palpite:
                    print(f"  ⚪ [CARD ATUALIZADO] Fixture {fid} ({h_team} x {a_team}) | De '{old_ah}' -> '{palpite}'")

    conn.commit()
    processed_count += 1

print("\n" + "=" * 70)
print(f"REPROCESSAMENTO CONCLUÍDO:")
print(f"- Total de fixtures processados: {processed_count}")
print(f"- Cards atualizados em fixtures_trends: {updated_cards}")
print(f"- Apostas pendentes canceladas/estornadas: {cancelled_bets}")
print(f"- Apostas pendentes atualizadas: {updated_bets}")
print("=" * 70)

# Verificar se ainda resta alguma aposta com status Pendente e GK NO_BET
with conn.cursor() as cur:
    cur.execute("""
        SELECT a.id, a.fixture_id, a.time_casa, a.time_fora, a.mercado, a.palpite, a.odd, a.status, a.status_gatekeeper
        FROM apostas a
        WHERE DATE(a.data_hora_jogo) = '2026-09-13' AND a.status = 'Pendente'
    """)
    active_bets = cur.fetchall()
    print(f"\nTotal de apostas que permanecem ativas (Pendente) para hoje: {len(active_bets)}")
    for ab in active_bets:
        print(f"  Bet #{ab['id']} | Fixture {ab['fixture_id']} | {ab['time_casa']} x {ab['time_fora']} | {ab['mercado']} | {ab['palpite']} @ {ab['odd']} | GK: {ab['status_gatekeeper']}")

conn.close()
