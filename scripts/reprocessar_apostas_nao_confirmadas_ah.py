#!/usr/bin/env python3
"""
Script para reprocessar todas as apostas de Handicap Asiático pendentes e NÃO confirmadas,
aplicando as novas regras calibradas do Gatekeeper:
- Anti-meio-red em jogos equilibrados com odds abertas;
- Trava de mando contra visitante favorito;
- Desbloqueio de -0.50 e -0.75 para mandantes com superioridade real;
- Piso mínimo de rentabilidade (odd >= 1.55);
- Cancelamento de apostas que entraram em abstenção.
"""

import sys
import os
import pymysql
import json
from datetime import datetime

# Adicionar raiz ao path
sys.path.insert(0, '/root/datalake-air-flow-delta')
sys.path.insert(0, '/datalake-root')

from scripts.football_ingest_trends import calculate_asian_handicap_suggestion

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

def reprocessar_apostas_nao_confirmadas():
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT 
            a.id as aposta_id,
            a.fixture_id,
            a.usuario_id,
            a.palpite as palpite_antigo,
            a.odd as odd_antiga,
            a.valor_aposta,
            a.status,
            f.home_team,
            f.away_team,
            f.odd_home,
            f.odd_draw,
            f.odd_away,
            f.xg_home,
            f.xg_away,
            f.ah_suggestion,
            f.ah_reasoning,
            f.home_rank,
            f.away_rank,
            f.home_ppg,
            f.away_ppg,
            f.home_zone,
            f.away_zone,
            f.standings_motivation_score,
            f.league_name
        FROM apostas a
        JOIN fixtures_trends f ON a.fixture_id = f.fixture_id
        WHERE a.status = 'Pendente'
          AND (a.confirmada = 0 OR a.confirmada IS NULL)
          AND a.mercado LIKE '%Handicap%'
        ORDER BY a.id ASC
    """)
    apostas = cursor.fetchall()
    total = len(apostas)
    print(f"📋 Encontradas {total} apostas não confirmadas pendentes para reprocessamento.")

    atualizadas = 0
    canceladas_abstencao = 0
    inalteradas = 0

    detalhes_alteracoes = []

    for ap in apostas:
        aposta_id = ap['aposta_id']
        fixture_id = ap['fixture_id']
        home_team = ap['home_team']
        away_team = ap['away_team']
        palpite_antigo = ap['palpite_antigo']
        odd_antiga = float(ap['odd_antiga'] or 2.0)
        valor_aposta = float(ap['valor_aposta'] or 10.0)

        # U5J data
        raw_reasoning = ap.get('ah_reasoning') or ''
        home_last5 = None
        away_last5 = None
        if '|| U5J_DATA:' in raw_reasoning:
            try:
                u_part = raw_reasoning.split('|| U5J_DATA:')[1].split('||')[0].strip()
                u_data = json.loads(u_part)
                home_last5 = u_data.get('home')
                away_last5 = u_data.get('away')
            except Exception:
                pass

        home_goals_scored = float(ap.get('xg_home') or 1.2)
        away_goals_scored = float(ap.get('xg_away') or 1.0)
        new_oh = float(ap.get('odd_home') or 2.0)
        new_oa = float(ap.get('odd_away') or 2.0)
        new_od = float(ap.get('odd_draw') or 3.20)

        res = calculate_asian_handicap_suggestion(
            home_goals_scored=home_goals_scored,
            home_goals_conceded=1.0,
            away_goals_scored=away_goals_scored,
            away_goals_conceded=1.2,
            home_team=home_team,
            away_team=away_team,
            home_cs_pct=25.0,
            away_cs_pct=25.0,
            home_last5=home_last5,
            away_last5=away_last5,
            odd_home=new_oh,
            odd_away=new_oa,
            odd_draw=new_od,
            home_rank=ap.get('home_rank'),
            away_rank=ap.get('away_rank'),
            home_ppg=ap.get('home_ppg'),
            away_ppg=ap.get('away_ppg'),
            home_zone=ap.get('home_zone'),
            away_zone=ap.get('away_zone'),
            standings_motivation=ap.get('standings_motivation_score'),
            league_name=ap.get('league_name')
        )

        new_suggestion = res[0]
        new_confidence = res[1]
        new_reasoning = res[2]

        is_abstencao = any(term in new_suggestion.lower() for term in ['sem entrada', 'abstenção', 'abstencao', 'bloqueada', 'no_bet'])

        if is_abstencao:
            cursor.execute("""
                UPDATE apostas SET
                    status = 'Cancelada',
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (f"🚫 APOSTA CANCELADA POR REPROCESSAMENTO: {new_reasoning[:300]}", aposta_id))
            canceladas_abstencao += 1
            detalhes_alteracoes.append({
                'id': aposta_id,
                'jogo': f"{home_team} vs {away_team}",
                'acao': 'CANCELADA',
                'de': palpite_antigo,
                'para': 'Sem Entrada (Abstenção)',
                'motivo': 'Conflito de risco / Abstenção nas novas regras'
            })
            continue

        # Se a linha mudou:
        mudou = (new_suggestion.strip().lower() != palpite_antigo.strip().lower())
        
        # Estimar ou manter odd saudável
        final_odd = odd_antiga
        if mudou:
            # Se mudou para +0.5 AH (Dupla Chance)
            if '+0.5' in new_suggestion:
                raw_ref = new_oh if (home_team.lower() in new_suggestion.lower()) else new_oa
                final_odd = round(max(1.55, min(1.90, 1.0 + (raw_ref - 1.0) * 0.32)), 2)
            elif '+0.25' in new_suggestion:
                raw_ref = new_oh if (home_team.lower() in new_suggestion.lower()) else new_oa
                final_odd = round(max(1.60, min(2.10, 1.0 + (raw_ref - 1.0) * 0.45)), 2)
            elif '-0.75' in new_suggestion:
                raw_ref = new_oh if (home_team.lower() in new_suggestion.lower()) else new_oa
                final_odd = round(max(1.65, min(2.15, raw_ref + 0.20)), 2)
            elif '-0.5' in new_suggestion:
                raw_ref = new_oh if (home_team.lower() in new_suggestion.lower()) else new_oa
                final_odd = round(max(1.55, raw_ref), 2)
            else:
                final_odd = odd_antiga

        # Garantir piso mínimo de 1.55
        if final_odd < 1.55:
            final_odd = 1.55

        novo_ganho = round(valor_aposta * final_odd, 2)
        agora_brt = datetime.now().strftime("%d/%m às %H:%M")
        
        if mudou:
            novo_detalhe = (
                f"🔄 Palpite reajustado pelo novo Gatekeeper ({agora_brt}): "
                f"Linha alterada de '{palpite_antigo}' para '{new_suggestion}' (@ {final_odd:.2f}). "
                f"Motivo: Otimização contra empates e proteção de mando. || {new_reasoning}"
            )[:2000]

            cursor.execute("""
                UPDATE apostas SET
                    palpite = %s,
                    odd = %s,
                    ganhos_potenciais = %s,
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (new_suggestion, final_odd, novo_ganho, novo_detalhe, aposta_id))

            cursor.execute("""
                UPDATE fixtures_trends SET
                    ah_suggestion = %s,
                    ah_confidence = %s,
                    ah_reasoning = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (new_suggestion, new_confidence, new_reasoning, fixture_id))

            atualizadas += 1
            detalhes_alteracoes.append({
                'id': aposta_id,
                'jogo': f"{home_team} vs {away_team}",
                'acao': 'ATUALIZADA',
                'de': palpite_antigo,
                'para': f"{new_suggestion} (@ {final_odd:.2f})",
                'motivo': 'Calibração das novas regras de valor'
            })
        else:
            # Atualiza apenas a narrativa enriquecida
            cursor.execute("""
                UPDATE apostas SET
                    resultado_detalhado = CONCAT('✅ Palpite confirmado pelas novas regras do Gatekeeper || ', %s),
                    updated_at = NOW()
                WHERE id = %s
            """, (new_reasoning[:1800], aposta_id))
            inalteradas += 1

    cursor.close()
    conn.close()

    print("\n" + "="*60)
    print("📊 RESULTADO DO REPROCESSAMENTO EM LOTE:")
    print(f"  • Total avaliado: {total}")
    print(f"  • Linhas atualizadas / Otimizadas: {atualizadas}")
    print(f"  • Canceladas por Abstenção/Risco: {canceladas_abstencao}")
    print(f"  • Inalteradas (já validadas): {inalteradas}")
    print("="*60)
    
    if detalhes_alteracoes:
        print("\n🔍 DETALHE DAS ALTERAÇÕES:")
        for d in detalhes_alteracoes:
            print(f"  [{d['acao']}] Aposta #{d['id']} | {d['jogo']}: {d['de']} ➔ {d['para']}")

    return {
        "total": total,
        "atualizadas": atualizadas,
        "canceladas": canceladas_abstencao,
        "inalteradas": inalteradas,
        "detalhes": detalhes_alteracoes
    }

if __name__ == "__main__":
    reprocessar_apostas_nao_confirmadas()
