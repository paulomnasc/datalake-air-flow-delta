#!/usr/bin/env python3
"""
scripts/reprocessar_cards_e_apostas_real_market.py

Reprocessa e saneia estruturalmente todos os cards em aberto e apostas pendentes
com data >= hoje (02/11/2026 em diante), aplicando estritamente a Regra 12 de Ouro
(proibição absoluta de odds sintéticas / fictícias).
- Avalia linhas 100% reais de casas de apostas (Betano / The Odds API).
- Linhas sem odd real >= 1.60 ou sem +EV recebem NO_BET de forma transparente.
- Cancela apostas pendentes não confirmadas reprovadas pelo Gatekeeper.
- Preserva integralmente o payload || U5J_DATA: (Regra 16).
"""

import sys
import os
import re
from datetime import datetime

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection
from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    fetch_all_betano_ah_lines,
    compose_compound_ah_reasoning,
    determine_gatekeeper_category
)

def reprocessar_tudo(dry_run=False):
    conn = get_db_connection()
    cursor = conn.cursor()

    print("=" * 80)
    print(f"🚀 INICIANDO REPROCESSAMENTO REAL DE MERCADO (>= HOJE) [DRY_RUN={dry_run}]")
    print("=" * 80)

    # 1. Reprocessar Cards em fixtures_trends
    cursor.execute("""
        SELECT *
        FROM fixtures_trends
        WHERE DATE(DATE_SUB(fixture_date, INTERVAL 3 HOUR)) >= CURDATE()
          AND status IN ('NS', 'TBD', 'PST', '1H', 'HT', '2H')
        ORDER BY fixture_date ASC
    """)
    fixtures = cursor.fetchall()
    print(f"📋 Total de partidas em aberto em fixtures_trends: {len(fixtures)}")

    cards_atualizados = 0
    cards_no_bet = 0
    cards_aprovados = 0

    for f in fixtures:
        fid = f["fixture_id"]
        h_team = f["home_team"]
        a_team = f["away_team"]
        h_tid = f.get("home_team_id")
        a_tid = f.get("away_team_id")

        lines = fetch_all_betano_ah_lines(fid, h_team, a_team)
        status_gk, sug, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
            fixture_dict=f,
            betano_lines=lines,
            allow_api_fetch=False,
            cursor=cursor
        )

        cat_desc = (best_cand or {}).get('gatekeeper_category') or determine_gatekeeper_category(status_gk, sug, reason, best_cand)

        compound_r = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=fid,
            main_calc=reason,
            suggestion=sug,
            home_team=h_team,
            away_team=a_team,
            home_team_id=h_tid,
            away_team_id=a_tid,
            existing_reasoning=f.get("ah_reasoning")
        )

        if status_gk == 'NO_BET':
            cards_no_bet += 1
        else:
            cards_aprovados += 1

        cards_atualizados += 1
        if not dry_run:
            cursor.execute("""
                UPDATE fixtures_trends
                SET ah_suggestion = %s,
                    ah_confidence = %s,
                    gatekeeper_category = %s,
                    ah_reasoning = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (sug, conf, cat_desc, compound_r, fid))

    if not dry_run:
        conn.commit()
    print(f"✅ Cards em fixtures_trends reprocessados: {cards_atualizados} (Aprovados: {cards_aprovados} | NO_BET: {cards_no_bet})")

    # 2. Reprocessar e Sanear Apostas Pendentes Não Confirmadas
    cursor.execute("""
        SELECT a.id, a.fixture_id, a.time_casa, a.time_fora, a.mercado, a.palpite, a.odd,
               a.confirmada, a.status, a.status_gatekeeper, a.gatekeeper_category, a.data_hora_jogo
        FROM apostas a
        JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
        WHERE DATE(DATE_SUB(ft.fixture_date, INTERVAL 3 HOUR)) >= CURDATE()
          AND a.status = 'Pendente'
          AND a.confirmada = 0
        ORDER BY a.data_hora_jogo ASC, a.id ASC
    """)
    nao_conf = cursor.fetchall()
    print(f"\n📋 Total de apostas pendentes não confirmadas: {len(nao_conf)}")

    apostas_canceladas = 0
    apostas_mantidas = 0

    for a in nao_conf:
        aid = a['id']
        fid = a['fixture_id']
        palpite = a['palpite']
        mercado = str(a['mercado'] or '').lower()
        odd = float(a['odd'])
        h_team = a['time_casa']
        a_team = a['time_fora']

        if 'handicap' in mercado:
            cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fid,))
            fix = cursor.fetchone()
            if fix:
                lines = fetch_all_betano_ah_lines(fid, h_team, a_team)
                status_gk, sug, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
                    fixture_dict=fix,
                    betano_lines=lines,
                    allow_api_fetch=False,
                    cursor=cursor
                )

                if status_gk == 'NO_BET':
                    cat_desc = 'Cotação Real Abaixo do Piso Operacional (min 1.60)'
                    motivo_cancel = (
                        f"🚫 APOSTA CANCELADA: Cotações reais de mercado abaixo do piso mínimo de 1.60 ou sem valor esperado positivo (+EV). "
                        f"O sistema rejeitou linhas sintéticas e aplicou a Regra 12 de Ouro (Abstenção Mandatória)."
                    )
                    compound_r = compose_compound_ah_reasoning(
                        cursor=cursor,
                        fixture_id=fid,
                        main_calc=motivo_cancel + "\n\n" + (reason or ""),
                        suggestion='Sem Entrada (Abstenção)',
                        home_team=h_team,
                        away_team=a_team,
                        home_team_id=fix.get("home_team_id"),
                        away_team_id=fix.get("away_team_id"),
                        existing_reasoning=fix.get("ah_reasoning")
                    )

                    print(f"  🛑 [CANCELAR APOSTA] Bet #{aid} | {palpite} @ {odd:.2f} ({h_team} vs {a_team}) -> Cancelada (NO_BET)")
                    apostas_canceladas += 1

                    if not dry_run:
                        cursor.execute("""
                            UPDATE apostas
                            SET status = 'Cancelada',
                                status_gatekeeper = 'NO_BET',
                                gatekeeper_category = %s,
                                resultado_detalhado = %s,
                                auditoria_ia_status = 'ALERTA_RISCO',
                                updated_at = NOW()
                            WHERE id = %s
                        """, (cat_desc, compound_r, aid))
                else:
                    novo_palpite = (best_cand or {}).get('palpite_str') or palpite
                    nova_odd = float((best_cand or {}).get('odd') or odd)
                    print(f"  ✅ [MANTER APOSTA] Bet #{aid} | {novo_palpite} @ {nova_odd:.2f} -> APROVADO")
                    apostas_mantidas += 1
                    if not dry_run:
                        cursor.execute("""
                            UPDATE apostas
                            SET palpite = %s,
                                odd = %s,
                                status_gatekeeper = 'APROVADO',
                                updated_at = NOW()
                            WHERE id = %s
                        """, (novo_palpite, nova_odd, aid))

        elif 'cart' in mercado:
            # Mercados de cartões
            print(f"  ℹ️ [CARTÕES] Bet #{aid} | {palpite} @ {odd:.2f} mantida sob motor de cartões.")
            apostas_mantidas += 1

    if not dry_run:
        conn.commit()

    print("\n" + "=" * 80)
    print(f"🏁 SANEAMENTO CONCLUÍDO! Cards Atualizados: {cards_atualizados} | Apostas Canceladas: {apostas_canceladas} | Mantidas: {apostas_mantidas}")
    print("=" * 80)

    cursor.close()
    conn.close()

if __name__ == '__main__':
    reprocessar_tudo(dry_run=False)

