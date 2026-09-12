#!/usr/bin/env python3
"""
Script de Reprocessamento e Sincronização de Apostas e Cards de Handicap Asiático
FootballWeb Pipeline

Executa em modo estritamente offline/cache-first (0 chamadas à API-Sports):
1. Cancela apostas geradas retroativamente após o início da partida (jogos passados/em andamento);
2. Reprocessa apostas e cards de jogos futuros aplicando as novas regras do Gatekeeper:
   - Força da Tabela (SOS);
   - Piso de odd mínima para handicap positivo de visitante (odd >= 1.75);
   - Calibração de xG de Poisson por mando e paridade de eficiência.
"""

import os
import sys
import json
import pymysql
from datetime import datetime

# Adicionar caminhos locais
sys.path.insert(0, '/root/datalake-air-flow-delta/scripts')
sys.path.insert(0, '/root/datalake-air-flow-delta')

from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    compose_compound_ah_reasoning
)


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
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue
    raise RuntimeError("Falha ao conectar no MySQL.")


def reprocessar():
    conn = get_db_connection()
    cursor = conn.cursor()

    # Selecionar apostas de AH criadas no lote de 12/09/2026 às 19:00 UTC (16:00 BRT) que estão pendentes
    cursor.execute("""
        SELECT a.id as aposta_id, a.fixture_id, a.palpite as palpite_antigo, a.odd as odd_antiga, 
               a.data_hora_jogo, a.criado_em, a.confirmada,
               f.*
        FROM apostas a
        JOIN fixtures_trends f ON a.fixture_id = f.fixture_id
        WHERE a.criado_em BETWEEN '2026-09-12 18:50:00' AND '2026-09-12 19:15:00'
          AND a.mercado LIKE '%Handicap%'
          AND a.status = 'Pendente'
        ORDER BY a.data_hora_jogo ASC
    """)
    bets = cursor.fetchall()
    print(f"📋 Total de apostas localizadas no lote: {len(bets)}")

    retroativas_canceladas = 0
    futuras_ajustadas = 0
    futuras_mantidas = 0
    futuras_vetadas = 0

    for b in bets:
        aposta_id = b['aposta_id']
        fix_id = b['fixture_id']
        data_jogo = b['data_hora_jogo']
        data_criacao = b['criado_em']
        home_team = b['home_team']
        away_team = b['away_team']
        palpite_antigo = b['palpite_antigo']
        odd_antiga = float(b['odd_antiga'] or 0.0)

        # 1. Regra de cancelamento para jogos retroativos (jogo anterior ou no mesmo horário da criação)
        if data_jogo <= data_criacao:
            diff_min = (data_criacao - data_jogo).total_seconds() / 60.0
            motivo_cancel = (
                f"🚫 Aposta Cancelada: Partida gerada retroativamente com atraso de {diff_min:.0f} min "
                f"(Horário do Jogo: {data_jogo.strftime('%d/%m %H:%M')} | Criação: {data_criacao.strftime('%d/%m %H:%M')})."
            )
            cursor.execute("""
                UPDATE apostas SET
                    status = 'Cancelada',
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (motivo_cancel, aposta_id))
            retroativas_canceladas += 1
            print(f"🚫 [RETROATIVA CANCELADA] ID #{aposta_id} | {home_team} vs {away_team} | Jogo: {data_jogo.strftime('%H:%M')} | Criado: {data_criacao.strftime('%H:%M')} (-{diff_min:.0f} min)")
            continue

        # 2. Jogos futuros: reprocessar usando o Asian Handicap Engine calibrado (allow_api_fetch=False)
        fix_dict = dict(b)
        status_gk, selected_palpite, conf_val, detalhe_calc, best_cand, approved_cands = calculate_unified_handicap_recommendation(
            fix_dict, allow_api_fetch=False, cursor=cursor
        )

        if status_gk == 'NO_BET' or not best_cand:
            # Vetado pelo Gatekeeper calibrado (Piso de Odd / SOS / Mando)
            motivo_veto = f"🛡️ [Gatekeeper AH - Abstenção pós-reprocessamento SOS/Odds]: {detalhe_calc}"
            cursor.execute("""
                UPDATE apostas SET
                    status = 'Cancelada',
                    status_gatekeeper = 'NO_BET',
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (motivo_veto, aposta_id))

            # Atualiza o Card em fixtures_trends
            compound_r = compose_compound_ah_reasoning(
                cursor=cursor,
                fixture_id=fix_id,
                main_calc=detalhe_calc,
                suggestion='Sem Entrada (Abstenção)',
                home_team=home_team,
                away_team=away_team,
                home_team_id=b.get('home_team_id'),
                away_team_id=b.get('away_team_id'),
                existing_reasoning=b.get('ah_reasoning')
            )
            cursor.execute("""
                UPDATE fixtures_trends SET
                    ah_suggestion = 'Sem Entrada (Abstenção)',
                    ah_confidence = 50.00,
                    ah_reasoning = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (compound_r, fix_id))

            futuras_vetadas += 1
            print(f"❌ [VETADA GATEKEEPER] ID #{aposta_id} | {home_team} vs {away_team} | De '{palpite_antigo}' -> Sem Entrada (Abstenção)")
            continue

        # Se aprovado, sincronizar com os novos valores calibrados
        eval_res = best_cand['eval']
        new_odd = float(best_cand['odd'])
        odd_justa = float(eval_res['odd_justa'])
        prob_eff = float(eval_res['prob_eff'])
        ev_perc = float(eval_res['ev_percent'])
        ganhos_potenciais = round(10.0 * new_odd, 2)

        mudou = (selected_palpite != palpite_antigo or abs(new_odd - odd_antiga) > 0.01)

        cursor.execute("""
            UPDATE apostas SET
                palpite = %s,
                odd = %s,
                odd_justa = %s,
                probabilidade_poisson = %s,
                ev_percentual = %s,
                ganhos_potenciais = %s,
                status_gatekeeper = 'APROVADO',
                resultado_detalhado = %s,
                updated_at = NOW()
            WHERE id = %s
        """, (selected_palpite, new_odd, odd_justa, prob_eff, ev_perc, ganhos_potenciais, detalhe_calc, aposta_id))

        compound_r = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=fix_id,
            main_calc=detalhe_calc,
            suggestion=selected_palpite,
            home_team=home_team,
            away_team=away_team,
            home_team_id=b.get('home_team_id'),
            away_team_id=b.get('away_team_id'),
            existing_reasoning=b.get('ah_reasoning')
        )
        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (selected_palpite, conf_val, compound_r, fix_id))

        if mudou:
            futuras_ajustadas += 1
            print(f"🔄 [LINHA AJUSTADA] ID #{aposta_id} | {home_team} vs {away_team} | De: '{palpite_antigo}' @ {odd_antiga:.2f} -> Para: '{selected_palpite}' @ {new_odd:.2f}")
        else:
            futuras_mantidas += 1
            print(f"✅ [MANTIDA APROVADA] ID #{aposta_id} | {home_team} vs {away_team} | '{selected_palpite}' @ {new_odd:.2f}")

    print("\n" + "=" * 60)
    print("📊 RESUMO DO REPROCESSAMENTO CONCLUÍDO (100% OFFLINE / 0 CHAMADAS API)")
    print(f"🚫 Apostas retroativas canceladas (jogos passados/em andamento): {retroativas_canceladas}")
    print(f"❌ Apostas futuras vetadas pelo Gatekeeper (SOS/Piso de Odd): {futuras_vetadas}")
    print(f"🔄 Apostas futuras com linha/odd ajustada: {futuras_ajustadas}")
    print(f"✅ Apostas futuras mantidas aprovadas: {futuras_mantidas}")
    print(f"🎯 Total de apostas processadas: {len(bets)}")
    print("=" * 60)

    cursor.close()
    conn.close()


if __name__ == '__main__':
    reprocessar()
