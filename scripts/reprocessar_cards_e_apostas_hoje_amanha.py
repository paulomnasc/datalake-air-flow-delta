#!/usr/bin/env python3
"""
scripts/reprocessar_cards_e_apostas_hoje_amanha.py

Script Estrutural de Saneamento e Reprocessamento de Cards e Apostas Não Confirmadas
para as partidas de Hoje e Amanhã (25/10/2026 e 26/10/2026).
- Aplica a nova trava dinâmica de Armadilha da Odd Inflada (> 5% de drift).
- Aplica a descontinuação estrutural de linhas Under de cartões inferiores a 6.5.
- Saneia apostas não confirmadas que foram reprovadas pelos novos critérios.
- Atualiza os cards de fixtures_trends.
"""

import sys
import os
import re
from datetime import datetime

sys.path.insert(0, "/root/datalake-air-flow-delta/scripts")
sys.path.insert(0, "/root/datalake-air-flow-delta")

from db_config import get_db_connection
from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    compose_compound_ah_reasoning
)

def run_reprocessing(dry_run=False):
    conn = get_db_connection()
    cursor = conn.cursor()

    print("=" * 80)
    print(f"🔄 INICIANDO SANEAMENTO DE CARDS E APOSTAS (25/10 e 26/10) [DRY_RUN={dry_run}]")
    print("=" * 80)

    # 1. Reprocessar e Sanear Apostas Não Confirmadas
    cursor.execute("""
        SELECT a.id, a.fixture_id, a.time_casa, a.time_fora, a.mercado, a.palpite, a.odd,
               a.confirmada, a.status, a.status_gatekeeper, a.gatekeeper_category, a.data_hora_jogo
        FROM apostas a
        WHERE DATE(a.data_hora_jogo) IN ('2026-10-25', '2026-10-26')
          AND a.status IN ('Pendente', 'Não Confirmada')
          AND a.confirmada = 0
        ORDER BY a.data_hora_jogo ASC, a.id ASC
    """)
    nao_conf = cursor.fetchall()
    print(f"📋 Total de apostas não confirmadas encontradas: {len(nao_conf)}")

    apostas_canceladas = 0
    apostas_mantidas = 0

    for a in nao_conf:
        aid = a['id']
        fid = a['fixture_id']
        palpite = a['palpite']
        mercado = a['mercado']
        odd = float(a['odd'])

        # Checagem de Mercado de Cartões
        if 'cartõ' in mercado.lower() or 'card' in mercado.lower():
            m = re.search(r'(\d+\.\d+|\d+)', palpite)
            line = float(m.group(1)) if m else 6.5
            if line < 6.49:
                motivo = f"🚫 APOSTA CANCELADA: Linha {palpite} descontinuada estruturalmente pelo Gatekeeper (restrito a Under 6.5 e 7.5+)."
                print(f"  🛑 [CANCELAR CARTÕES] Bet #{aid} | {palpite} -> Cancelada (Linha curta descontinuada)")
                apostas_canceladas += 1
                if not dry_run:
                    cursor.execute("""
                        UPDATE apostas
                        SET status = 'Cancelada',
                            status_gatekeeper = 'NO_BET',
                            gatekeeper_category = 'Linha Curta Descontinuada',
                            resultado_detalhado = %s,
                            updated_at = NOW()
                        WHERE id = %s
                    """, (motivo, aid))
            else:
                print(f"  ✅ [MANTER CARTÕES] Bet #{aid} | {palpite} -> Aprovada")
                apostas_mantidas += 1

        # Checagem de Handicap Asiático
        elif 'handicap' in mercado.lower():
            cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fid,))
            fix = cursor.fetchone()
            if fix:
                status_gk, sug, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
                    fix, allow_api_fetch=True, cursor=cursor
                )
                if status_gk == 'NO_BET':
                    motivo = f"🚫 APOSTA CANCELADA: Reprovada na reavaliação dinâmica do Gatekeeper ({sug})."
                    print(f"  🛑 [CANCELAR HANDICAP] Bet #{aid} | {palpite} @ {odd} -> Cancelada ({sug})")
                    apostas_canceladas += 1
                    if not dry_run:
                        cursor.execute("""
                            UPDATE apostas
                            SET status = 'Cancelada',
                                status_gatekeeper = 'NO_BET',
                                gatekeeper_category = %s,
                                resultado_detalhado = %s,
                                updated_at = NOW()
                            WHERE id = %s
                        """, ((best_cand or {}).get('gatekeeper_category') or 'Abstenção de Risco', motivo, aid))
                else:
                    print(f"  ✅ [MANTER HANDICAP] Bet #{aid} | {palpite} @ {odd} -> Mantida Aprovada")
                    apostas_mantidas += 1

    # 2. Reprocessar Cards em Aberto (NS/TBD) de fixtures_trends
    cursor.execute("""
        SELECT *
        FROM fixtures_trends
        WHERE DATE(fixture_date) IN ('2026-10-25', '2026-10-26')
          AND status IN ('NS', 'TBD')
        ORDER BY fixture_date ASC
    """)
    fixtures = cursor.fetchall()
    print(f"\n📊 Total de partidas em aberto em fixtures_trends: {len(fixtures)}")

    cards_atualizados = 0
    for f in fixtures:
        fid = f["fixture_id"]
        status_gk, sug, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
            f, allow_api_fetch=True, cursor=cursor
        )
        cat_desc = (best_cand or {}).get('gatekeeper_category') if best_cand else 'Falta de Valor Esperado (+EV)'
        if status_gk == 'NO_BET':
            from asian_handicap_engine import determine_gatekeeper_category
            cat_desc = determine_gatekeeper_category('NO_BET', sug, reason)

        compound_r = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=fid,
            main_calc=reason,
            suggestion=sug,
            home_team=f['home_team'],
            away_team=f['away_team'],
            home_team_id=f.get('home_team_id'),
            away_team_id=f.get('away_team_id'),
            existing_reasoning=f.get('ah_reasoning')
        )

        cards_atualizados += 1
        if not dry_run:
            cursor.execute("""
                UPDATE fixtures_trends
                SET ah_suggestion = %s,
                    ah_confidence = %s,
                    ah_reasoning = %s,
                    gatekeeper_category = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (sug, conf, compound_r, cat_desc, fid))

    if not dry_run:
        conn.commit()
        print("\n✅ SANEAMENTO CONCLUÍDO COM SUCESSO NO BANCO DE DADOS!")
    else:
        print("\nℹ️ SIMULAÇÃO CONCLUÍDA (NENHUM DADO FOI GRAVADO).")

    print(f"  • Apostas Canceladas: {apostas_canceladas}")
    print(f"  • Apostas Mantidas: {apostas_mantidas}")
    print(f"  • Cards de Fixtures Atualizados: {cards_atualizados}")

    cursor.close()
    conn.close()

if __name__ == "__main__":
    dry_mode = "--dry-run" in sys.argv
    run_reprocessing(dry_run=dry_mode)
