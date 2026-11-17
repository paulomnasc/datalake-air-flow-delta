#!/usr/bin/env python3
"""
scripts/criar_apostas_cloudbet_diario.py

Criador de Apostas 100% Robotizado da Cloudbet.
FootballWeb Pipeline - Automação Quantitativa em Cripto (USDT / LTC).

Funcionalidades:
1. Reconcilia as partidas abertas do MySQL (fixtures_trends) com os eventos oficiais da Cloudbet;
2. Extrai as cotações reais e líquidas da Cloudbet (Feed API);
3. Executa a avaliação estatística pelo Gatekeeper unificado (Poisson, U5J, EV e Liquidez);
4. Se chancelado como PREMIUM ou QUASE PREMIUM:
   - Emite a ordem de aposta via CloudbetClient (em modo Dry-Run ou Real);
   - Persiste a aposta no MySQL com casa_de_aposta = 'Cloudbet', moeda_codigo = 'USDT',
     e armazena o bilhete (bet_id e marketUrl) preservando rigorosamente o payload || U5J_DATA:;
5. Suporta execução programada via Airflow ou invocação manual via CLI.
"""

import os
import sys
import json
import argparse
from datetime import datetime, timedelta
from typing import Dict, Any, Optional, List

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars
from cloudbet_client import CloudbetClient
from cloudbet_matcher import CloudbetMatcher
from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    compose_compound_ah_reasoning,
    determine_gatekeeper_category,
    BetTier
)


def get_active_user_ids(cursor) -> List[int]:
    """
    Retorna a lista de usuários aptos a receberem ordens automáticas do robô.
    """
    cursor.execute("""
        SELECT DISTINCT u.id 
        FROM usuario u
        WHERE u.id = 558 OR u.status_assinatura = 'active'
        ORDER BY u.id ASC
    """)
    rows = cursor.fetchall()
    uids: List[int] = [int(r['id']) for r in rows] if rows else [558]
    return uids


def run_cloudbet_bet_creator(
    target_date_str: Optional[str] = None,
    fixture_id_filter: Optional[int] = None,
    dry_run_override: Optional[bool] = None
) -> Dict[str, Any]:
    """
    Rotina central de avaliação e criação autônoma de apostas na Cloudbet.
    """
    # Inicialização prévia mandatória de variáveis numéricas locais (Regra 8)
    apostas_criadas = 0
    apostas_puladas = 0
    apostas_duplicadas = 0
    apostas_sem_match = 0
    apostas_sem_linhas = 0
    apostas_rejeitadas_ev = 0
    total_jogos_analisados = 0

    conn = get_db_connection()
    cursor = conn.cursor()

    client = CloudbetClient(dry_run=dry_run_override)
    matcher = CloudbetMatcher(client=client)

    print("=" * 70)
    print("🤖 [Cloudbet 100% Robot Bet Creator] Inicializado")
    print(f"   • Modo Operacional: {'DRY-RUN / SIMULAÇÃO (Paper Trading) 🛡️' if client.dry_run else 'PRODUÇÃO FINANCEIRA REAL ⚠️'}")
    print(f"   • Moeda Base: {client.currency}")
    print("=" * 70)

    user_ids = get_active_user_ids(cursor)
    print(f"👥 Usuários ativos para envio de ordens: {user_ids}")

    # Seleção de partidas no banco
    if fixture_id_filter:
        cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id_filter,))
        fixtures = cursor.fetchall()
    elif target_date_str and target_date_str.lower() != 'all':
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE DATE(fixture_date) = %s
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'FINISHED')
            ORDER BY fixture_date ASC
        """, (target_date_str,))
        fixtures = cursor.fetchall()
    else:
        # Padrão: Próximas 24 horas a partir de agora
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE fixture_date >= NOW()
              AND fixture_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'FINISHED')
            ORDER BY fixture_date ASC
        """)
        fixtures = cursor.fetchall()

    total_jogos_analisados = len(fixtures)
    print(f"📋 Encontradas {total_jogos_analisados} partidas elegíveis no banco de dados.\n")

    for fix in fixtures:
        f_id = fix['fixture_id']
        home = fix['home_team'].strip()
        away = fix['away_team'].strip()
        dt = fix['fixture_date']

        # 1. Verifica se já existe aposta ativa para esta partida na Cloudbet
        cursor.execute("""
            SELECT id, palpite, status, status_gatekeeper, resultado_detalhado 
            FROM apostas 
            WHERE fixture_id = %s AND casa_de_aposta = 'Cloudbet'
              AND status NOT IN ('Cancelada', 'Não Confirmada')
            LIMIT 1
        """, (f_id,))
        ja_tem = cursor.fetchone()
        if ja_tem:
            print(f"ℹ️ [Partida #{f_id} Já Possui Aposta Cloudbet] {home} vs {away} -> Aposta #{ja_tem['id']} ({ja_tem['palpite']}). Pulando...")
            apostas_duplicadas += 1
            continue

        # 2. Localiza o evento correspondente na Cloudbet
        match = matcher.find_matching_event(home, away, dt)
        if not match:
            print(f"⚠️ [Sem Match Cloudbet] #{f_id} {home} vs {away} não localizado no catálogo Cloudbet.")
            apostas_sem_match += 1
            continue

        event_id = match['event_id']
        print(f"🔍 [Match Encontrado] #{f_id} {home} vs {away} ➔ Cloudbet ID {event_id} ('{match['event_name']}')")

        # 3. Extrai linhas de Handicap Asiático da Cloudbet
        markets = matcher.get_event_markets(event_id)
        ah_markets = markets.get('asian_handicap', {})

        if not ah_markets:
            print(f"   • Linhas de AH ainda não ativas na Cloudbet para este evento.")
            apostas_sem_linhas += 1
            continue

        cloudbet_lines = []
        for hcap, outcomes in ah_markets.items():
            if 'home' in outcomes:
                cloudbet_lines.append({
                    'team': 'Home',
                    'target_team': home,
                    'is_away': False,
                    'line': hcap,
                    'palpite_str': f"{home} {hcap:+} AH",
                    'odd': outcomes['home']['price'],
                    'market_url': outcomes['home']['market_url'],
                    'min_stake': outcomes['home']['min_stake'],
                    'max_stake': outcomes['home']['max_stake'],
                    'bookmaker': 'Cloudbet',
                    'source': 'Cloudbet'
                })
            if 'away' in outcomes:
                cloudbet_lines.append({
                    'team': 'Away',
                    'target_team': away,
                    'is_away': True,
                    'line': -hcap,
                    'palpite_str': f"{away} {-hcap:+} AH",
                    'odd': outcomes['away']['price'],
                    'market_url': outcomes['away']['market_url'],
                    'min_stake': outcomes['away']['min_stake'],
                    'max_stake': outcomes['away']['max_stake'],
                    'bookmaker': 'Cloudbet',
                    'source': 'Cloudbet'
                })

        # 4. Avaliação pelo Gatekeeper Unificado
        status_gk, selected_palpite, conf_val, detalhe_calculo, best_cand, approved_cands = calculate_unified_handicap_recommendation(
            fix, betano_lines=cloudbet_lines, allow_api_fetch=False, cursor=cursor
        )

        if status_gk == 'NO_BET' or not best_cand:
            print(f"   🛡️ [Gatekeeper NO_BET] Linhas avaliadas sem valor esperado positivo (+EV).")
            apostas_rejeitadas_ev += 1
            continue

        # 5. Validação de Tier de Governança (Apenas PREMIUM e QUASE PREMIUM geram apostas)
        cand_destaque = int(best_cand.get('destaque', 0))
        if cand_destaque not in (BetTier.PREMIUM.value, BetTier.QUASE_PREMIUM.value):
            print(f"   ℹ️ [Filtro de Governança] Classificada como Regular (destaque={cand_destaque}). Apenas PREMIUM e QUASE PREMIUM são apostadas.")
            apostas_puladas += 1
            continue

        eval_res = best_cand['eval']
        odd_val = float(best_cand['odd'])
        odd_justa = float(eval_res['odd_justa'])
        prob_poisson = float(eval_res['prob_eff'])
        ev_perc = float(eval_res['ev_percent'])
        market_url = best_cand.get('market_url', '')

        # 6. Cálculo da Stake em USDT (conforme metas do usuário ou stake padrão do bot)
        for uid in user_ids:
            cursor.execute("""
                SELECT stake_padrao FROM metas_diarias_config 
                WHERE usuario_id = %s AND is_ativa = 1 
                ORDER BY id DESC LIMIT 1
            """, (uid,))
            m_row = cursor.fetchone()
            env_default_stake = float(os.environ.get("CLOUDBET_DEFAULT_STAKE") or 0.10)
            stake_base = float(m_row['stake_padrao']) if (m_row and m_row.get('stake_padrao')) else env_default_stake
            if stake_base <= 0.0:
                stake_base = env_default_stake

            # Ajuste de stake pelo multiplicador de tier
            cand_tier = best_cand.get('tier', BetTier.QUASE_PREMIUM)
            stake_mult = cand_tier.stake_multiplier if hasattr(cand_tier, 'stake_multiplier') else 1.0
            valor_aposta = round(stake_base * stake_mult, 2)
            ganhos_potenciais = round(valor_aposta * odd_val, 2)

            # 7. Disparo da Ordem via CloudbetClient
            print(f"\n🚀 [Executando Ordem Cloudbet] Usuário #{uid} em #{f_id} {home} vs {away}:")
            print(f"   • Palpite: {selected_palpite} @ {odd_val:.2f} (EV: +{ev_perc:.1f}%)")
            print(f"   • Stake: {valor_aposta:.2f} {client.currency} | Ganhos: {ganhos_potenciais:.2f} {client.currency}")
            print(f"   • Market URL: {market_url}")

            order_res = client.place_bet(
                event_id=str(event_id),
                market_url=market_url,
                price=odd_val,
                stake=valor_aposta,
                currency=client.currency
            )

            bet_id = order_res.get("betId", f"cb_{f_id}_{uid}")
            is_simulated = order_res.get("is_simulated", client.dry_run)

            # 8. Composição do Raciocínio Preservando o Payload U5J (Regras 14 e 16)
            compound_reasoning = compose_compound_ah_reasoning(
                cursor=cursor,
                fixture_id=f_id,
                main_calc=detalhe_calculo,
                suggestion=selected_palpite,
                home_team=home,
                away_team=away,
                home_team_id=fix.get('home_team_id'),
                away_team_id=fix.get('away_team_id'),
                existing_reasoning=fix.get('ah_reasoning')
            )

            # Anexa metadados de execução da Cloudbet ao resultado detalhado
            cloudbet_audit_block = (
                f"|| CLOUDBET: {json.dumps({'bet_id': bet_id, 'event_id': event_id, 'market_url': market_url, 'is_simulated': is_simulated, 'currency': client.currency})} || "
            )
            final_resultado_detalhado = cloudbet_audit_block + compound_reasoning

            # 9. Gravação no Banco de Dados MySQL
            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, moeda_codigo, cotacao_brl_snapshot, fixture_id,
                    time_casa, time_fora, mercado, casa_de_aposta, palpite,
                    odd, odd_justa, probabilidade_poisson, ev_percentual,
                    status_gatekeeper, gatekeeper_category, data_hora_jogo,
                    valor_aposta, ganhos_potenciais, tipo, status,
                    confirmada, destaque, resultado_detalhado, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s,
                    %s, %s, 'Handicap Asiático', 'Cloudbet', %s,
                    %s, %s, %s, %s,
                    'APROVADO', %s, %s,
                    %s, %s, %s, 'Pendente',
                    1, %s, %s, NOW(), NOW()
                )
            """, (
                uid, client.currency, 5.80, f_id,
                home, away, selected_palpite,
                odd_val, odd_justa, prob_poisson, ev_perc,
                best_cand.get('gatekeeper_category', 'Entrada de Valor (+EV)'), dt,
                valor_aposta, ganhos_potenciais,
                'Robô Cloudbet (Simulação)' if is_simulated else 'Robô Cloudbet (Real)',
                cand_destaque, final_resultado_detalhado
            ))
            new_aposta_id = cursor.lastrowid
            conn.commit()

            print(f"✅ [Aposta Registrada com Sucesso] Aposta ID #{new_aposta_id} salva na tabela 'apostas'.")
            apostas_criadas += 1

            # Rastreamento transversal de ciclo de vida
            try:
                from lifecycle_logger import log_lifecycle_event
                log_lifecycle_event(
                    cursor=cursor,
                    fixture_id=f_id,
                    aposta_id=new_aposta_id,
                    notificacao_id=None,
                    notificacao_criada_em=None,
                    componente='CLOUDBET_ROBOT_BET_CREATOR',
                    tipo_evento='ORDEM_EMITIDA_CLOUDBET',
                    estado_anterior='PENDENTE_MERCADO',
                    estado_novo='PENDENTE_REGISTRADA',
                    delta={'bet_id': bet_id, 'is_simulated': is_simulated, 'palpite': selected_palpite, 'odd': odd_val, 'stake': valor_aposta},
                    motivo=f"Entrada quantitativa chancelada pelo Gatekeeper na Cloudbet com EV +{ev_perc:.1f}%"
                )
                conn.commit()
            except Exception:
                pass

    cursor.close()
    conn.close()

    summary = {
        "total_jogos_analisados": total_jogos_analisados,
        "apostas_criadas": apostas_criadas,
        "apostas_duplicadas": apostas_duplicadas,
        "apostas_sem_match": apostas_sem_match,
        "apostas_sem_linhas": apostas_sem_linhas,
        "apostas_rejeitadas_ev": apostas_rejeitadas_ev,
        "apostas_puladas": apostas_puladas,
        "dry_run": client.dry_run
    }

    print("\n" + "=" * 70)
    print("🏁 Resumo da Execução do Robô Cloudbet:")
    print(f"   • Total de partidas analisadas: {total_jogos_analisados}")
    print(f"   • Apostas emitidas / criadas: {apostas_criadas}")
    print(f"   • Partidas já com aposta ativa: {apostas_duplicadas}")
    print(f"   • Sem evento correspondente na Cloudbet: {apostas_sem_match}")
    print(f"   • Sem linhas de AH ativas na Cloudbet: {apostas_sem_linhas}")
    print(f"   • Rejeitadas pelo Gatekeeper (+EV insuficiente): {apostas_rejeitadas_ev}")
    print("=" * 70)

    return summary


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Criador Autônomo de Apostas Cloudbet (100% Robot)")
    parser.add_argument("--date", type=str, help="Data das partidas (YYYY-MM-DD ou 'all')")
    parser.add_argument("--fixture_id", type=int, help="Processa uma partida específica pelo fixture_id")
    parser.add_argument("--real", action="store_true", help="Força a execução financeira real (desativa dry-run)")
    args = parser.parse_args()

    dry_run_opt = False if args.real else None
    run_cloudbet_bet_creator(
        target_date_str=args.date,
        fixture_id_filter=args.fixture_id,
        dry_run_override=dry_run_opt
    )
