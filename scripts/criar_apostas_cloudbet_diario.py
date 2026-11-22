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
import pymysql
from datetime import datetime, timedelta, timezone
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
from asian_total_engine import (
    calculate_unified_goals_recommendation
)
from cards_engine import (
    compute_fixture_expected_cards,
    calculate_poisson_under_cdf,
    CARDS_GATEKEEPER_EXCLUDED_LEAGUE_IDS
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


def place_and_commit_cloudbet_bet(
    client: CloudbetClient,
    conn,
    cursor,
    uid: int,
    fix: dict,
    event_id: str,
    mercado: str,
    selected_palpite: str,
    odd_val: float,
    odd_justa: float,
    prob_poisson: float,
    ev_perc: float,
    market_url: str,
    cand_destaque: int,
    gk_category: str,
    reasoning_base: str
) -> tuple:
    """
    Executa a ordem na Trading API da Cloudbet e persiste a aposta e o débito via Two-Phase Commit.
    Possui dupla barreira de idempotência: pré-checagem e captura de IntegrityError da CONSTRAINT UNIQUE.
    Retorna: (success: bool, is_duplicate: bool)
    """
    f_id = fix['fixture_id']
    home = fix['home_team'].strip()
    away = fix['away_team'].strip()
    dt = fix['fixture_date']

    # 1. Barreira Pré-Execução de Idempotência Estrita por Linha/Mercado (Sem duplicar mesmo com variação de odd)
    cursor.execute("""
        SELECT id, palpite, odd FROM apostas 
        WHERE fixture_id = %s AND usuario_id = %s AND casa_de_aposta = 'Cloudbet'
          AND mercado = %s AND palpite = %s
          AND confirmada = 1
          AND status NOT IN ('Cancelada', 'Não Confirmada')
        LIMIT 1
    """, (f_id, uid, mercado, selected_palpite))
    ja_tem = cursor.fetchone()
    if ja_tem:
        print(f"   ℹ️ [Linha Já Apostada Cloudbet #{f_id}] {selected_palpite} (Odd anterior: {ja_tem['odd']}, atual: {odd_val:.2f}). Duplicação bloqueada.")
        return False, True

    # 2. Definição da Stake
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

    valor_aposta = round(stake_base, 2)
    ganhos_potenciais = round(valor_aposta * odd_val, 2)

    # 3. Disparo da Ordem via CloudbetClient (Trading API)
    print(f"\n🚀 [Executando Ordem Cloudbet {mercado}] User #{uid} em #{f_id} {home} vs {away}:")
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

    bet_id = order_res.get("betId")
    if not bet_id or "error" in order_res or order_res.get("status") in ("REJECTED", "FAILED") or not order_res.get("success", True):
        conn.rollback()
        print(f"❌ [Cloudbet API Rejeitou Ordem] Bilhete não gerado pela Cloudbet: {order_res}. Rollback executado. Saldo preservado.")
        return False, False

    is_simulated = order_res.get("is_simulated", client.dry_run)
    cloudbet_audit_block = (
        f"|| CLOUDBET: {json.dumps({'bet_id': bet_id, 'event_id': event_id, 'market_url': market_url, 'is_simulated': is_simulated, 'currency': client.currency})} || "
    )
    final_resultado_detalhado = cloudbet_audit_block + reasoning_base

    # 4. Gravação Atômica no MySQL com Proteção da CONSTRAINT UNIQUE
    try:
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
                %s, %s, %s, 'Cloudbet', %s,
                %s, %s, %s, %s,
                'APROVADO', %s, %s,
                %s, %s, %s, 'Pendente',
                1, %s, %s, NOW(), NOW()
            )
        """, (
            uid, client.currency, 5.80, f_id,
            home, away, mercado, selected_palpite,
            odd_val, odd_justa, prob_poisson, ev_perc,
            gk_category, dt,
            valor_aposta, ganhos_potenciais,
            'Robô Cloudbet (Simulação)' if is_simulated else 'Robô Cloudbet (Real)',
            cand_destaque, final_resultado_detalhado
        ))
        new_aposta_id = cursor.lastrowid

        # 5. Débito em Conta Corrente
        cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
        u_row = cursor.fetchone()
        s_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
        s_post = round(s_ant - valor_aposta, 8)
        desc_deb = f"Débito Aposta #{new_aposta_id} (Cloudbet {mercado}: {home} x {away} - {selected_palpite})"
        cursor.execute("""
            INSERT INTO conta_corrente (
                usuario_id, aposta_id, tipo, moeda_codigo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
            ) VALUES (
                %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
            )
        """, (uid, new_aposta_id, client.currency, desc_deb, -valor_aposta, s_ant, s_post))
        cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))

        conn.commit()
        print(f"✅ [Aposta {mercado} Registrada com Sucesso] ID #{new_aposta_id} gravada e debitada. Novo saldo: {s_post:.4f} {client.currency}")
        return True, False

    except pymysql.err.IntegrityError as e_dup:
        conn.rollback()
        print(f"🛑 [CONSTRAINT UNIQUE MySQL Barrou Duplicidade] {selected_palpite}: {e_dup}. Transação revertida com sucesso.")
        return False, True


def run_cloudbet_bet_creator(
    target_date_str: Optional[str] = None,
    fixture_id_filter: Optional[int] = None,
    dry_run_override: Optional[bool] = None
) -> Dict[str, Any]:
    """
    Rotina central de avaliação e criação autônoma de apostas na Cloudbet (Multimercados: AH + Gols).
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
    print("🤖 [Cloudbet 100% Robot Bet Creator - Multimercados Triplo AH + Gols + Cartões] Inicializado")
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
            WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'FINISHED')
            ORDER BY fixture_date ASC
        """, (target_date_str,))
        fixtures = cursor.fetchall()
    else:
        # Padrão: JANELA ÁUREA PRÉ-JOGO + IN-PLAY 0-10m (T-75 a +10 min com Súmula e 0x0)
        cursor.execute("""
            SELECT ft.* 
            FROM fixtures_trends ft
            JOIN match_lineups_cache mlc ON mlc.fixture_id = ft.fixture_id AND mlc.status = 'CONFIRMADA'
            WHERE ft.fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
              AND ft.fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 75 MINUTE)
              AND (
                  ft.status = 'NS'
                  OR (ft.status = '1H' AND COALESCE(ft.goals_home, 0) = 0 AND COALESCE(ft.goals_away, 0) = 0)
              )
              AND (ft.auditoria_ia_status IS NULL OR ft.auditoria_ia_status != 'ALERTA_RISCO')
            ORDER BY ft.fixture_date ASC
        """)
        fixtures = cursor.fetchall()

    total_jogos_analisados = len(fixtures)
    print(f"📋 Encontradas {total_jogos_analisados} partidas na Janela Áurea Pré-Jogo e In-Play 0-10m (com súmula confirmada e sem veto de risco).\n")

    now_utc_reference = datetime.now(timezone.utc).replace(tzinfo=None)

    for fix in fixtures:
        f_id = fix['fixture_id']
        home = fix['home_team'].strip()
        away = fix['away_team'].strip()
        dt_utc = fix['fixture_date']
        dt_brt = (dt_utc - timedelta(hours=3)) if isinstance(dt_utc, datetime) else dt_utc
        dt_str = dt_brt.strftime('%d/%m %H:%M BRT') if hasattr(dt_brt, 'strftime') else str(dt_brt)

        is_inplay = bool(dt_utc <= now_utc_reference or fix.get('status') == '1H')

        # 1. Localiza o evento correspondente na Cloudbet
        if is_inplay:
            g_home = int(fix.get('goals_home') or 0)
            g_away = int(fix.get('goals_away') or 0)
            if g_home > 0 or g_away > 0:
                print(f"ℹ️ [In-Play Descartado] #{f_id} {home} vs {away}: Placar alterado ({g_home}x{g_away}). Ordens in-play permitidas apenas em 0x0.")
                continue
            # Busca prioritária no feed TRADING_LIVE da Cloudbet
            match = matcher.find_matching_event(home, away, dt_utc, is_live=True)
            if not match:
                match = matcher.find_matching_event(home, away, dt_utc, is_live=False)
        else:
            match = matcher.find_matching_event(home, away, dt_utc, is_live=False)

        if not match:
            print(f"ℹ️ [Sem Match Cloudbet] #{f_id} {home} vs {away} ({dt_str}) ➔ Não localizado no catálogo esportivo da Cloudbet.")
            apostas_sem_match += 1
            continue

        event_id = match['event_id']
        # No ao vivo in-play, busca cotações frescas sem cache
        markets = matcher.get_event_markets(event_id, use_cache=not is_inplay)
        ah_markets = markets.get('asian_handicap', {})
        tg_markets = markets.get('total_goals', {})
        cards_markets = markets.get('cards', {})

        if not ah_markets and not tg_markets and not cards_markets:
            print(f"⏳ [Linhas Fechadas Cloudbet] #{f_id} {home} vs {away} ({dt_str}) ➔ Evento '{match['event_name']}' (ID {event_id}): Linhas de AH, Gols e Cartões ainda desativadas/fechadas.")
            apostas_sem_linhas += 1
            continue

        print(f"\n🔍 [Match Encontrado] #{f_id} {home} vs {away} ({dt_str}) ➔ Cloudbet ID {event_id} ('{match['event_name']}')")

        # =====================================================================
        # MÓDULO 1: HANDICAP ASIÁTICO (AH)
        # =====================================================================
        if ah_markets:
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

            sample_lines = ", ".join([f"{c['palpite_str']} @ {c['odd']:.2f}" for c in cloudbet_lines[:4]])
            print(f"   📊 [Cloudbet AH] {len(cloudbet_lines)} linha(s) cotada(s): {sample_lines}{'...' if len(cloudbet_lines) > 4 else ''}")

            status_gk, selected_palpite, conf_val, detalhe_calculo, best_cand, approved_cands = calculate_unified_handicap_recommendation(
                fix, betano_lines=cloudbet_lines, allow_api_fetch=False, cursor=cursor
            )

            if status_gk == 'NO_BET' or not best_cand:
                motivo = "Poisson desfavorável / Margem de +EV insuficiente no Gatekeeper"
                if detalhe_calculo:
                    import re
                    m_reason = re.search(r'REASON:\s*(.+)', detalhe_calculo)
                    if m_reason:
                        motivo = m_reason.group(1).strip()
                print(f"   ❌ [Gatekeeper AH - Reprovado] {home} vs {away}: NO_BET | Motivo: {motivo}")
                apostas_rejeitadas_ev += 1
            else:
                cand_destaque = int(best_cand.get('destaque', 0))
                eval_res = best_cand['eval']
                odd_val = float(best_cand['odd'])
                odd_justa = float(eval_res['odd_justa'])
                prob_poisson = float(eval_res['prob_eff'])
                ev_perc = float(eval_res['ev_percent'])
                market_url = best_cand.get('market_url', '')

                if cand_destaque not in (BetTier.PREMIUM.value, BetTier.QUASE_PREMIUM.value):
                    print(f"   ℹ️ [Filtro AH] {selected_palpite} @ {odd_val:.2f} classificada como Regular (destaque={cand_destaque}). Apenas PREMIUM e QUASE PREMIUM são emitidas.")
                    apostas_puladas += 1
                else:
                    cat_nome = best_cand.get('gatekeeper_category', 'Quase Premium (+EV)')
                    print(f"   🎯 [Gatekeeper AH - Aprovado] {selected_palpite} @ {odd_val:.2f} (Odd Justa: {odd_justa:.2f} | Prob: {prob_poisson:.1f}% | EV: +{ev_perc:.1f}%) | {cat_nome}")

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

                    for uid in user_ids:
                        ok, is_dup = place_and_commit_cloudbet_bet(
                            client=client,
                            conn=conn,
                            cursor=cursor,
                            uid=uid,
                            fix=fix,
                            event_id=event_id,
                            mercado='Handicap Asiático',
                            selected_palpite=selected_palpite,
                            odd_val=odd_val,
                            odd_justa=odd_justa,
                            prob_poisson=prob_poisson,
                            ev_perc=ev_perc,
                            market_url=market_url,
                            cand_destaque=cand_destaque,
                            gk_category=cat_nome,
                            reasoning_base=compound_reasoning
                        )
                        if ok:
                            apostas_criadas += 1
                        elif is_dup:
                            apostas_duplicadas += 1

        # =====================================================================
        # MÓDULO 2: GOLS ASIÁTICOS (TOTAL GOALS)
        # =====================================================================
        if tg_markets:
            cloudbet_goals_lines = []
            for t_val, outcomes in tg_markets.items():
                if 'over' in outcomes:
                    cloudbet_goals_lines.append({
                        'line': t_val,
                        'type': 'Over',
                        'odd': outcomes['over']['price'],
                        'label': f"Mais de {t_val:g} Gols",
                        'market_url': outcomes['over']['market_url'],
                        'min_stake': outcomes['over']['min_stake'],
                        'max_stake': outcomes['over']['max_stake'],
                        'bookmaker': 'Cloudbet',
                        'source': 'Cloudbet'
                    })
                if 'under' in outcomes:
                    cloudbet_goals_lines.append({
                        'line': t_val,
                        'type': 'Under',
                        'odd': outcomes['under']['price'],
                        'label': f"Menos de {t_val:g} Gols",
                        'market_url': outcomes['under']['market_url'],
                        'min_stake': outcomes['under']['min_stake'],
                        'max_stake': outcomes['under']['max_stake'],
                        'bookmaker': 'Cloudbet',
                        'source': 'Cloudbet'
                    })

            sample_g_lines = ", ".join([f"{c['label']} @ {c['odd']:.2f}" for c in cloudbet_goals_lines[:4]])
            print(f"   📊 [Cloudbet Gols] {len(cloudbet_goals_lines)} linha(s) cotada(s): {sample_g_lines}{'...' if len(cloudbet_goals_lines) > 4 else ''}")

            status_g, palpite_g, conf_g, reason_g, best_cand_g, approved_g = calculate_unified_goals_recommendation(
                fix, betano_lines=cloudbet_goals_lines, cursor=cursor
            )

            if status_g == 'APPROVED' and best_cand_g:
                odd_val_g = float(best_cand_g['odd'])
                odd_justa_g = float(best_cand_g['odd_justa'])
                prob_poisson_g = float(best_cand_g['prob_efetiva'])
                ev_perc_g = float(best_cand_g['ev_percent'])
                market_url_g = best_cand_g.get('market_url', '')
                cat_g = best_cand_g.get('category', 'Gols Asiáticos (+EV)')

                print(f"   🎯 [Gatekeeper Gols - Aprovado] {palpite_g} @ {odd_val_g:.2f} (Odd Justa: {odd_justa_g:.2f} | Prob: {prob_poisson_g:.1f}% | EV: +{ev_perc_g:.1f}%) | {cat_g}")

                goals_reasoning = f"|| EXPLICACAO: {reason_g} || MOTIVACAO: {reason_g} || MEMÓRIA DE CÁLCULO || STATUS GK: APROVADO\nSUGGESTION: {palpite_g}\nCATEGORIA: {cat_g}\nREASON: {reason_g}"

                for uid in user_ids:
                    ok_g, is_dup_g = place_and_commit_cloudbet_bet(
                        client=client,
                        conn=conn,
                        cursor=cursor,
                        uid=uid,
                        fix=fix,
                        event_id=event_id,
                        mercado='Gols Asiáticos',
                        selected_palpite=palpite_g,
                        odd_val=odd_val_g,
                        odd_justa=odd_justa_g,
                        prob_poisson=prob_poisson_g,
                        ev_perc=ev_perc_g,
                        market_url=market_url_g,
                        cand_destaque=1,
                        gk_category=cat_g,
                        reasoning_base=goals_reasoning
                    )
                    if ok_g:
                        apostas_criadas += 1
                    elif is_dup_g:
                        apostas_duplicadas += 1
            else:
                print(f"   ❌ [Gatekeeper Gols - Reprovado] {home} vs {away}: NO_BET | Motivo: {reason_g}")

        # =====================================================================
        # MÓDULO 3: TOTAL DE CARTÕES (UNDER CARTÕES)
        # =====================================================================
        if cards_markets:
            sample_c_lines = ", ".join([f"Under {lv:g} @ {d['under']['price']:.2f}" for lv, d in cards_markets.items() if 'under' in d][:4])
            print(f"   📊 [Cloudbet Cartões] {len(cards_markets)} linha(s) cotada(s): {sample_c_lines}{'...' if len(cards_markets) > 4 else ''}")

            league_id = fix.get('league_id')
            if league_id and int(league_id) in CARDS_GATEKEEPER_EXCLUDED_LEAGUE_IDS:
                print(f"   🛡️ [Gatekeeper Cartões NO_BET] Liga excluída por taxa de Reds > 10% (ID #{league_id}).")
            else:
                calc_cards = compute_fixture_expected_cards(cursor, fix)
                if not calc_cards or calc_cards[0] is None:
                    print(f"   ℹ️ [Gatekeeper Cartões] Dados estatísticos de cartões insuficientes no banco para {home} vs {away}.")
                else:
                    exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, _ = calc_cards
                    card_bet_placed = False

                    # Ordena linhas da maior para a menor (maior linha = menor risco: 8.5 -> 7.5 -> 6.5 -> 5.5)
                    sorted_lines = sorted(cards_markets.keys(), reverse=True)
                    for line_val in sorted_lines:
                        outcomes = cards_markets[line_val]
                        if 'under' not in outcomes or line_val < 5.49:
                            continue

                        odd_cards = float(outcomes['under']['price'] or 0.0)
                        prob_poisson_c = float(calculate_poisson_under_cdf(exp_cards, line_val) or 0.0)
                        odd_justa_c = round(100.0 / prob_poisson_c, 2) if prob_poisson_c > 0.0 else 99.0
                        ev_perc_c = round(((prob_poisson_c / 100.0) * odd_cards - 1.0) * 100.0, 2)

                        # Validação pelo Gatekeeper Canônico:
                        # 1. Árbitro oficial confirmado na escala
                        # 2. Trava de Piso do Árbitro: média do juiz < (linha - 0.30)
                        # 3. Margem xC: exp_cards <= (linha - 1.00)
                        # 4. Probabilidade Poisson >= 60.0%
                        # 5. Valor Esperado Positivo (+EV > 0.0%)
                        # 6. Piso de Odd >= 1.50
                        is_ref_ok = bool(is_ref_confirmed and (not ref_cards_avg or float(ref_cards_avg) < (line_val - 0.30)))
                        is_xc_ok = bool(exp_cards <= (line_val - 1.00))

                        if is_ref_ok and is_xc_ok and prob_poisson_c >= 60.0 and ev_perc_c > 0.0 and odd_cards >= 1.50:
                            palpite_c = f"Menos de {line_val:g} Cartões"
                            cat_c = "Valor Esperado Positivo (+EV)"
                            reason_cards = f"GATEKEEPER CARTÕES APROVADO (+EV {ev_perc_c:+.1f}%) | {palpite_c} @ {odd_cards:.2f} (Cloudbet) vs Odd Justa {odd_justa_c:.2f} (Prob: {prob_poisson_c:.1f}%) | xC Projetado: {exp_cards:.2f} cartões"

                            print(f"   🎯 [Gatekeeper Cartões - Aprovado] {palpite_c} @ {odd_cards:.2f} (Odd Justa: {odd_justa_c:.2f} | Prob: {prob_poisson_c:.1f}% | EV: +{ev_perc_c:.1f}%) | {cat_c}")

                            for uid in user_ids:
                                ok_c, is_dup_c = place_and_commit_cloudbet_bet(
                                    client=client,
                                    conn=conn,
                                    cursor=cursor,
                                    uid=uid,
                                    fix=fix,
                                    event_id=event_id,
                                    mercado='Total de Cartões',
                                    selected_palpite=palpite_c,
                                    odd_val=odd_cards,
                                    odd_justa=odd_justa_c,
                                    prob_poisson=prob_poisson_c,
                                    ev_perc=ev_perc_c,
                                    market_url=outcomes['under']['market_url'],
                                    cand_destaque=1,
                                    gk_category=cat_c,
                                    reasoning_base=reason_cards
                                )
                                if ok_c:
                                    apostas_criadas += 1
                                elif is_dup_c:
                                    apostas_duplicadas += 1
                            card_bet_placed = True
                            break # Encerra na melhor linha aprovada do jogo

                    if not card_bet_placed:
                        print(f"   ❌ [Gatekeeper Cartões - Reprovado] {home} vs {away}: NO_BET | Cotações ou margens insuficientes para Under Cartões.")

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
