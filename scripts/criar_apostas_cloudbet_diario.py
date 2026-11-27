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
import math
import argparse
import re
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
    is_team_in_relegation_threat,
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

def get_tactical_reset_context_for_fixture(cursor, fixture_id: int, home_team: str, away_team: str) -> dict:
    """
    Recupera contexto de Reset Tático por Súmula ou Drift previamente homologado pela Sentinela
    para evitar amnésia de contexto entre a esteira pré-jogo e a esteira in-play.
    """
    if not cursor or not fixture_id:
        return None
    try:
        cursor.execute("""
            SELECT tipo_evento, motivo_detalhado, criado_em 
            FROM pipeline_lifecycle_logs 
            WHERE fixture_id = %s 
              AND tipo_evento IN ('REVERSAO_TATICA_SUMULA', 'REVERSAO_TATICA_DRIFT')
            ORDER BY id DESC LIMIT 1
        """, (fixture_id,))
        row = cursor.fetchone()
        if row and row.get('motivo_detalhado'):
            motivo = row['motivo_detalhado']
            time_desfalcado = None
            if home_team.lower() in motivo.lower() and ('desfalque' in motivo.lower() or 'time misto' in motivo.lower() or 'descartado' in motivo.lower()):
                m_h = re.search(re.escape(home_team) + r'[^\.]*?(?:time misto|\d+\s*desfalque)', motivo, re.IGNORECASE)
                if m_h:
                    time_desfalcado = home_team
            if not time_desfalcado and away_team.lower() in motivo.lower():
                m_a = re.search(re.escape(away_team) + r'[^\.]*?(?:time misto|\d+\s*desfalque)', motivo, re.IGNORECASE)
                if m_a:
                    time_desfalcado = away_team
            
            if not time_desfalcado:
                if f"{home_team} com time misto" in motivo or f"Descaracterização estrutural de {home_team}" in motivo or f"descartou {home_team}" in motivo:
                    time_desfalcado = home_team
                elif f"{away_team} com time misto" in motivo or f"Descaracterização estrutural de {away_team}" in motivo or f"descartou {away_team}" in motivo:
                    time_desfalcado = away_team
            
            if time_desfalcado:
                tipo = 'DRIFT_MERCADO' if row['tipo_evento'] == 'REVERSAO_TATICA_DRIFT' else 'SUMULA'
                return {'time_desfalcado': time_desfalcado, 'tipo': tipo, 'motivo': motivo}
    except Exception as e_ctx:
        print(f"Aviso ao consultar contexto de reset tático: {e_ctx}")
    return None


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

    # 1. Barreira Pré-Execução por Direção (Proibido 2 Over ou 2 Under para o mesmo jogo/mercado)
    is_new_under = any(k in selected_palpite.lower() for k in ('menos', 'under'))
    is_new_over = any(k in selected_palpite.lower() for k in ('mais', 'over'))

    cursor.execute("""
        SELECT id, palpite, odd, tipo FROM apostas 
        WHERE (fixture_id = %s OR resultado_detalhado LIKE %s)
          AND usuario_id = %s AND casa_de_aposta = 'Cloudbet'
          AND mercado = %s
          AND confirmada = 1
          AND status NOT IN ('Cancelada', 'Não Confirmada')
    """, (f_id, f"%{event_id}%", uid, mercado))
    apostas_ativas = cursor.fetchall()

    for ap in apostas_ativas:
        ap_palp = str(ap.get('palpite') or '').lower()
        is_exist_under = any(k in ap_palp for k in ('menos', 'under'))
        is_exist_over = any(k in ap_palp for k in ('mais', 'over'))

        # Se for na MESMA direção (2 Under ou 2 Over), bloqueia imediatamente!
        if (is_new_under and is_exist_under) or (is_new_over and is_exist_over):
            print(f"   🛑 [Direção Já Apostada #{f_id}] Já existe aposta ativa em {'Under' if is_new_under else 'Over'} ({ap['palpite']} @ {ap['odd']}). Proibido duplicar a mesma ponta! Duplicação bloqueada.")
            return False, True
        elif selected_palpite.strip().lower() == ap_palp.strip():
            print(f"   ℹ️ [Linha Já Apostada Cloudbet #{f_id}] {selected_palpite} (Odd anterior: {ap['odd']}, atual: {odd_val:.2f}). Duplicação bloqueada.")
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

    bet_id = order_res.get("betId") or order_res.get("referenceId")
    is_accepted = (
        bool(bet_id) and
        not order_res.get("error") and
        order_res.get("status") not in ("REJECTED", "FAILED") and
        order_res.get("state") not in ("REJECTED", "FAILED")
    )
    if not is_accepted:
        conn.rollback()
        print(f"❌ [Cloudbet API Rejeitou Ordem] Bilhete não gerado pela Cloudbet: {order_res}. Rollback executado. Saldo preservado.")
        return False, False

    is_simulated = order_res.get("is_simulated", client.dry_run)
    cloudbet_audit_block = (
        f"|| CLOUDBET: {json.dumps({'bet_id': bet_id, 'event_id': event_id, 'market_url': market_url, 'is_simulated': is_simulated, 'currency': client.currency})} || "
    )
    final_resultado_detalhado = cloudbet_audit_block + reasoning_base

    # 4. Gravação Atômica no MySQL com Proteção da CONSTRAINT UNIQUE
    ia_status = fix.get('auditoria_ia_status')
    ia_resumo = fix.get('auditoria_ia_resumo')
    ia_parecer = fix.get('auditoria_ia_parecer')
    ia_checked_at = fix.get('auditoria_ia_checked_at')

    try:
        cursor.execute("""
            INSERT INTO apostas (
                usuario_id, moeda_codigo, cotacao_brl_snapshot, fixture_id,
                time_casa, time_fora, mercado, casa_de_aposta, palpite,
                odd, odd_justa, probabilidade_poisson, ev_percentual,
                status_gatekeeper, gatekeeper_category, data_hora_jogo,
                valor_aposta, ganhos_potenciais, tipo, status,
                confirmada, destaque, resultado_detalhado,
                auditoria_ia_status, auditoria_ia_resumo, auditoria_ia_parecer, auditoria_ia_checked_at,
                criado_em, updated_at
            ) VALUES (
                %s, %s, %s, %s,
                %s, %s, %s, 'Cloudbet', %s,
                %s, %s, %s, %s,
                'APROVADO', %s, %s,
                %s, %s, %s, 'Pendente',
                1, %s, %s,
                %s, %s, %s, %s,
                NOW(), NOW()
            )
        """, (
            uid, client.currency, 5.80, f_id,
            home, away, mercado, selected_palpite,
            odd_val, odd_justa, prob_poisson, ev_perc,
            gk_category, dt,
            valor_aposta, ganhos_potenciais,
            'Robô Cloudbet (Simulação)' if is_simulated else 'Robô Cloudbet (Real)',
            cand_destaque, final_resultado_detalhado,
            ia_status, ia_resumo, ia_parecer, ia_checked_at
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
    except Exception as e_db:
        conn.rollback()
        print(f"🚨 [ERRO CRÍTICO NO MYSQL APÓS ORDEM CLOUDBET ACEITA #{bet_id}] Falha ao gravar localmente: {e_db}")
        # Log fail-fast e alerta para auto-reconciliador recuperar
        return False, False


def reconcile_cloudbet_open_bets(client: CloudbetClient, conn, cursor, user_ids: List[int]) -> int:
    """
    Auto-reconciliador de segurança e idempotência:
    Consulta as últimas apostas registradas na Cloudbet via API e verifica se alguma
    aposta ativa/aceita não consta no banco de dados local. Caso positivo, efetua a recuperação
    automática garantindo integridade e paridade com a banca real.
    """
    reconciled_cnt = 0
    if not user_ids or client.dry_run:
        return 0

    try:
        cloudbet_bets = client.list_bets(limit=30)
    except Exception as e_api:
        print(f"   ⚠️ [Reconciliação Cloudbet] Falha ao listar apostas da API: {e_api}")
        return 0

    primary_uid = user_ids[0]
    for b in cloudbet_bets:
        b_id = b.get("betId")
        b_slip = b.get("betslipId")
        b_state = b.get("state")
        # Reconciliador de apostas ABERTAS: processa estritamente ordens ACCEPTED / PENDING
        if not b_id or b_state not in ("ACCEPTED", "PENDING", "ACTIVE"):
            continue

        # Verifica se já existe por betId ou betslipId no resultado_detalhado
        cursor.execute("""
            SELECT id FROM apostas 
            WHERE resultado_detalhado LIKE %s OR (%s IS NOT NULL AND resultado_detalhado LIKE %s)
            LIMIT 1
        """, (f"%{b_id}%", b_slip, f"%{b_slip}%" if b_slip else "%none%"))
        ja_existe = cursor.fetchone()
        if ja_existe:
            continue

        # Segunda barreira de desduplicação: checa extrato de conta_corrente
        cursor.execute("""
            SELECT id FROM conta_corrente 
            WHERE descricao LIKE %s
            LIMIT 1
        """, (f"%{b_id}%",))
        ja_existe_cc = cursor.fetchone()
        if ja_existe_cc:
            continue

        # Ordem aceita pela Cloudbet ausente no MySQL! Reconciliar agora
        sel = b.get("selection", {})
        ev_id = sel.get("eventId")
        m_url = sel.get("marketUrl", "")
        price_val = float(b.get("price") or sel.get("price") or 1.0)
        stake_val = float(b.get("stake") or 1.0)
        potential_val = float(b.get("potentialReturnAmount") or (price_val * stake_val))
        cur = b.get("currency") or client.currency

        # Tenta enriquecer metadados do evento (Times e Fixture ID) via Cloudbet API e MySQL
        home_nome = "Cloudbet Event"
        away_nome = str(ev_id)
        f_id_match = None
        data_jogo = None

        try:
            ev_meta = client._request("GET", f"/pub/v2/odds/events/{ev_id}")
            if ev_meta and not ev_meta.get("error"):
                h_api = (ev_meta.get("home", {}) or {}).get("name", "")
                a_api = (ev_meta.get("away", {}) or {}).get("name", "")
                if h_api and a_api:
                    home_nome = h_api
                    away_nome = a_api

                # Normalização ortográfica para busca tolerante em fixtures_trends
                def _norm_t(s):
                    return s.lower().replace("oe", "o").replace("ae", "a").replace("ø", "o").replace("å", "a").replace(" if", "").replace(" fc", "").replace(" fk", "").strip()[:5]

                h_clean = _norm_t(home_nome)
                a_clean = _norm_t(away_nome)

                # Busca no fixtures_trends pelo nome do time mandante ou visitante
                cursor.execute("""
                    SELECT fixture_id, home_team, away_team, fixture_date 
                    FROM fixtures_trends 
                    WHERE (home_team LIKE %s OR away_team LIKE %s OR home_team LIKE %s OR away_team LIKE %s)
                      AND fixture_date >= NOW() - INTERVAL 1 DAY
                    ORDER BY fixture_id DESC LIMIT 1
                """, (f"%{home_nome[:6]}%", f"%{away_nome[:6]}%", f"%{h_clean}%", f"%{a_clean}%"))
                matched_fix = cursor.fetchone()
                if matched_fix:
                    f_id_match = matched_fix['fixture_id']
                    home_nome = matched_fix['home_team']
                    away_nome = matched_fix['away_team']
                    data_jogo = matched_fix['fixture_date']
        except Exception as e_ev:
            print(f"   ⚠️ [Reconciliação Evento #{ev_id}] Detalhes não enriquecidos: {e_ev}")

        # Mapeia mercado e palpite a partir da marketUrl via Hash Table canônica
        from cloudbet_market_translator import CloudbetMarketTranslator
        m_nome, palpite_nome = CloudbetMarketTranslator.translate(
            market_url=m_url,
            home_team=home_nome,
            away_team=away_nome
        )

        cloudbet_audit = f"|| CLOUDBET: {json.dumps({'bet_id': b_id, 'event_id': ev_id, 'market_url': m_url, 'is_simulated': False, 'currency': cur, 'reconciled': True})} || "
        reasoning = (
            f"Auto-Reconciliação Sistêmica: Ordem #{b_id} aceita na Cloudbet e sincronizada com sucesso "
            f"para garantir idempotência e paridade com a banca real."
        )
        detalhado = f"{cloudbet_audit}|| EXPLICACAO: {reasoning} || MOTIVACAO: {reasoning} || STATUS GK: APROVADO\nSUGGESTION: {palpite_nome}\nREASON: {reasoning}"

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
                    %s, %s, 5.80, %s,
                    %s, %s, %s, 'Cloudbet', %s,
                    %s, %s, 50.00, 0.00,
                    'APROVADO', %s, %s,
                    %s, %s, 'Robô Cloudbet (Real)', 'Pendente',
                    1, 1, %s, NOW(), NOW()
                )
            """, (
                primary_uid, cur, f_id_match,
                home_nome, away_nome, m_nome, palpite_nome,
                price_val, price_val, m_nome,
                data_jogo or datetime.now(),
                stake_val, potential_val, detalhado
            ))
            rec_aposta_id = cursor.lastrowid

            cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (primary_uid,))
            u_r = cursor.fetchone()
            s_a = float(u_r['saldo_conta_corrente'] or 0.0) if u_r else 0.0
            s_p = round(s_a - stake_val, 8)
            desc_rec = f"Débito Reconciliação Aposta #{rec_aposta_id} (Cloudbet {m_nome} - {b_id})"

            cursor.execute("""
                INSERT INTO conta_corrente (
                    usuario_id, aposta_id, tipo, moeda_codigo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                ) VALUES (
                    %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, %s, NOW()
                )
            """, (primary_uid, rec_aposta_id, cur, desc_rec, -stake_val, s_a, s_p))
            cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_p, primary_uid))

            conn.commit()
            reconciled_cnt += 1
            print(f"   🔄 [Auto-Reconciliação Cloudbet] Aposta #{b_id} recuperada e gravada com sucesso como #{rec_aposta_id}!")
        except Exception as e_rec:
            conn.rollback()
            print(f"   ❌ [Falha ao Auto-Reconciliar #{b_id}]: {e_rec}")

    return reconciled_cnt


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

    # Reconciliação Automática Pré-Execução (Idempotência e Paridade com Banca Real)
    rec_count = reconcile_cloudbet_open_bets(client, conn, cursor, user_ids)
    if rec_count > 0:
        print(f"🔄 [Idempotência Cloudbet] {rec_count} aposta(s) aberta(s) reconciliada(s) automaticamente no MySQL.")

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
        # Padrão: JANELA PRÉ-JOGO (T-75) + IN-PLAY 0-35m (com Súmula e 0x0)
        cursor.execute("""
            SELECT ft.* 
            FROM fixtures_trends ft
            LEFT JOIN match_lineups_cache mlc ON mlc.fixture_id = ft.fixture_id
            WHERE ft.fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 MINUTE)
              AND ft.fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 75 MINUTE)
              AND (
                  ft.status = 'NS'
                  OR (ft.status IN ('1H', 'LIVE', 'IN_PLAY') AND COALESCE(ft.goals_home, 0) = 0 AND COALESCE(ft.goals_away, 0) = 0)
                  OR (
                      ft.status IN ('1H', 'LIVE', 'IN_PLAY') 
                      AND EXISTS (
                          SELECT 1 FROM pipeline_lifecycle_logs pll 
                          WHERE pll.fixture_id = ft.fixture_id 
                            AND pll.tipo_evento IN ('REVERSAO_TATICA_SUMULA', 'REVERSAO_TATICA_DRIFT')
                      )
                  )
              )
              AND (
                  -- Em andamento: titulares já em campo
                  ft.status IN ('1H', 'LIVE', 'IN_PLAY')
                  -- Pré-jogo: súmula oficial OBRIGATORIAMENTE confirmada no banco
                  OR (ft.status = 'NS' AND mlc.status = 'CONFIRMADA')
              )
              AND (ft.auditoria_ia_status IS NULL OR ft.auditoria_ia_status != 'ALERTA_RISCO')
            ORDER BY ft.fixture_date ASC
        """)
        fixtures = cursor.fetchall()

    total_jogos_analisados = len(fixtures)
    print(f"📋 Encontradas {total_jogos_analisados} partidas na Janela Pré-Jogo (T-75) e In-Play 0-35m (com súmula confirmada e sem veto de risco).\n")

    now_utc_reference = datetime.now(timezone.utc).replace(tzinfo=None)

    for fix in fixtures:
        f_id = fix['fixture_id']
        home = fix['home_team'].strip()
        away = fix['away_team'].strip()
        dt_utc = fix['fixture_date']
        dt_brt = (dt_utc - timedelta(hours=3)) if isinstance(dt_utc, datetime) else dt_utc
        dt_str = dt_brt.strftime('%d/%m %H:%M BRT') if hasattr(dt_brt, 'strftime') else str(dt_brt)

        is_inplay = bool(dt_utc <= now_utc_reference or fix.get('status') == '1H')
        g_home = int(fix.get('goals_home') or 0)
        g_away = int(fix.get('goals_away') or 0)

        # 0. Herança de Contexto Cognitivo (Reset Tático por Súmula ou Drift)
        tactical_ctx = get_tactical_reset_context_for_fixture(cursor, f_id, home, away)
        has_tactical_reset = bool(tactical_ctx and tactical_ctx.get('time_desfalcado'))

        # 1. Localiza o evento correspondente na Cloudbet
        if is_inplay:
            # Protocolo de Gol Prematuro Contra o Fluxo (Resiliência In-Play para Reset Tático):
            # Se houver Reset Tático confirmado e o jogo estiver no 1º tempo (<= 45m), o placar circunstancial
            # não descarta a partida, estendendo a janela de oportunidade até os 45 minutos do 1º tempo.
            if g_home > 0 or g_away > 0:
                elapsed_min = int((now_utc_reference - dt_utc).total_seconds() / 60) if isinstance(dt_utc, datetime) else 0
                if has_tactical_reset and (fix.get('status') == '1H' or elapsed_min <= 45):
                    desf_team = tactical_ctx.get('time_desfalcado')
                    print(f"⚡ [In-Play Reset Tático - Gol Prematuro Contra o Fluxo] #{f_id} {home} vs {away} ({g_home}x{g_away} aos {elapsed_min}'):")
                    print(f"   ↳ Placar circunstancial no 1º tempo contra a equipe esfacelada ({desf_team}). Janela estendida até 45' para captura de +EV!")
                else:
                    print(f"ℹ️ [In-Play Descartado] #{f_id} {home} vs {away}: Placar alterado ({g_home}x{g_away}). Ordens in-play regulares permitidas apenas em 0x0.")
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

        # Dupla Verificação In-Play: Sincronização Dinâmica de Cotações 1X2 / DNB da Cloudbet (Regra 8 e 12)
        eval_fix = dict(fix)
        mo_live = markets.get('match_odds', {})
        dnb_live = markets.get('draw_no_bet', {})
        wtw_live = markets.get('which_team_wins', {})
        dnb_h = 0.0
        dnb_a = 0.0
        old_h = 0.0
        old_a = 0.0

        if mo_live.get('home') and mo_live.get('away'):
            eval_fix['odd_home'] = float(mo_live['home']['price'])
            eval_fix['odd_away'] = float(mo_live['away']['price'])
            if mo_live.get('draw'):
                eval_fix['odd_draw'] = float(mo_live['draw']['price'])
            print(f"   🔄 [Odds 1X2 Cloudbet Ao Vivo] {home} @ {eval_fix['odd_home']:.2f} | Empate @ {eval_fix.get('odd_draw', 0.0):.2f} | {away} @ {eval_fix['odd_away']:.2f}")
        elif wtw_live.get('home') and wtw_live.get('away'):
            eval_fix['odd_home'] = float(wtw_live['home']['price'])
            eval_fix['odd_away'] = float(wtw_live['away']['price'])
            if wtw_live.get('draw'):
                eval_fix['odd_draw'] = float(wtw_live['draw']['price'])
            print(f"   🔄 [Odds 1X2 In-Play Cloudbet] {home} @ {eval_fix['odd_home']:.2f} | {away} @ {eval_fix['odd_away']:.2f} (Rest of Match)")
        elif dnb_live.get('home') and dnb_live.get('away'):
            dnb_h = float(dnb_live['home']['price'])
            dnb_a = float(dnb_live['away']['price'])
            old_h = float(eval_fix.get('odd_home') or 0.0)
            old_a = float(eval_fix.get('odd_away') or 0.0)
            if (dnb_h < dnb_a and old_h > old_a) or (dnb_a < dnb_h and old_a > old_h):
                print(f"   ⚡ [Inversão de Favoritismo no DNB Cloudbet] {home} DNB @ {dnb_h:.2f} vs {away} DNB @ {dnb_a:.2f}. Calibrando consenso 1X2 ao vivo.")
                eval_fix['odd_home'] = dnb_h
                eval_fix['odd_away'] = dnb_a

        # Inicialização Prévia de Variáveis de Tempo de Jogo (Regra de Ouro 8)
        elapsed_min = 0
        cur_status = fix.get('status') or 'NS'
        if cur_status in ('1H', 'LIVE', 'IN_PLAY'):
            if fix.get('elapsed') is not None and int(fix.get('elapsed')) > 0:
                elapsed_min = int(fix.get('elapsed'))
            elif fix.get('fixture_date'):
                diff_secs = (now_utc_reference - fix['fixture_date']).total_seconds()
                elapsed_min = max(1, int(diff_secs // 60))

        # =====================================================================
        # MÓDULO 1: HANDICAP ASIÁTICO (AH) - Restrito: Pré-Jogo até 35 minutos (0x0)
        # =====================================================================
        if is_inplay and (g_home > 0 or g_away > 0):
            print(f"   🛡️ [Handicap Standby] Placar alterado ({g_home}x{g_away}). Entradas in-play de Handicap restritas exclusivamente a 0x0.")
        elif elapsed_min > 35:
            print(f"   🛡️ [Handicap Standby] Partida aos {elapsed_min}' do 1T. Entradas em Handicap restritas até os 35 minutos.")
        elif ah_markets:
            cloudbet_lines = []
            for hcap, outcomes in ah_markets.items():
                if 'home' in outcomes:
                    line_h = 0.0 if abs(hcap) < 0.001 else float(hcap)
                    palp_h = f"{home} +0.0 AH" if abs(line_h) < 0.001 else f"{home} {line_h:+} AH"
                    cloudbet_lines.append({
                        'team': 'Home',
                        'target_team': home,
                        'is_away': False,
                        'line': line_h,
                        'palpite_str': palp_h,
                        'odd': outcomes['home']['price'],
                        'market_url': outcomes['home']['market_url'],
                        'min_stake': outcomes['home']['min_stake'],
                        'max_stake': outcomes['home']['max_stake'],
                        'bookmaker': 'Cloudbet',
                        'source': 'Cloudbet'
                    })
                if 'away' in outcomes:
                    line_a = 0.0 if abs(hcap) < 0.001 else float(-hcap)
                    palp_a = f"{away} +0.0 AH" if abs(line_a) < 0.001 else f"{away} {line_a:+} AH"
                    cloudbet_lines.append({
                        'team': 'Away',
                        'target_team': away,
                        'is_away': True,
                        'line': line_a,
                        'palpite_str': palp_a,
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
                eval_fix, betano_lines=cloudbet_lines, allow_api_fetch=False, cursor=cursor, desfalques_criticos=tactical_ctx
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
                            fix=eval_fix,
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
        else:
            print(f"   ⏳ [Cloudbet AH] Nenhuma linha ativa de Handicap Asiático aberta no momento (livro fechado pela corretora).")

        # =====================================================================
        # MÓDULO 2: GOLS ASIÁTICOS (TOTAL GOALS) - Pré-Jogo (0-10m) & In-Play (15-35m)
        # =====================================================================
        if 10 < elapsed_min < 15:
            print(f"   ⏳ [Gols In-Play Standby] Partida aos {elapsed_min}' do 1T (0x0). Aguardando consolidação da Janela Áurea In-Play (15' a 35').")
        elif 15 <= elapsed_min <= 35 and tg_markets:
            # Submódulo Sniper In-Play de Gols Asiáticos (0x0 aos 15'-35' do 1T)
            minutos_restantes = max(10, 90 - elapsed_min)
            tempo_restante_fator = minutos_restantes / 90.0
            
            # Métricas Reais de Campo e Pressão Ofensiva (Regra 8 e 9)
            shots_tot = (fix.get('shots_home') or 0) + (fix.get('shots_away') or 0)
            corners_tot = (fix.get('corners_home') or 0) + (fix.get('corners_away') or 0)
            xg_tot = float((fix.get('xg_home') or 0.0) + (fix.get('xg_away') or 0.0))
            
            # Trava de Segurança Ofensiva: Exige volume mínimo de jogo (Anti-Truncado)
            atividade_ofensiva = shots_tot + corners_tot
            if elapsed_min >= 20 and atividade_ofensiva < 3:
                print(f"   🛡️ [Sniper Gols Standby] Jogo aos {elapsed_min}' (0x0): Volume ofensivo insuficiente ({shots_tot} chutes, {corners_tot} cantos). Abstenção para proteção contra 0x0 monótono.")
                continue

            # Expectativa Base de Gols
            lambda_base = xg_tot if xg_tot >= 1.20 else 2.40
            lambda_restante = lambda_base * tempo_restante_fator
            if atividade_ofensiva >= 4:
                lambda_restante *= 1.10  # Ritmo ofensivo ativo verificado em campo
                
            # Avaliação de Linhas de Over no Sweet Spot (1.65 a 2.15)
            inplay_cands = []
            rejeicoes_sniper = []
            for t_val, outcomes in tg_markets.items():
                if 'over' in outcomes:
                    price = float(outcomes['over']['price'])
                    if t_val in (1.25, 1.5, 1.75, 2.0):
                        if price < 1.65:
                            rejeicoes_sniper.append(f"Over {t_val:g} @ {price:.2f} (abaixo do piso 1.65)")
                            continue
                        if price > 2.15:
                            rejeicoes_sniper.append(f"Over {t_val:g} @ {price:.2f} (acima do teto 2.15)")
                            continue
                            
                        # Probabilidade de Poisson para minutos restantes
                        p0 = math.exp(-lambda_restante)
                        p1 = lambda_restante * p0
                        p2 = ((lambda_restante ** 2) / 2.0) * p0
                        
                        if t_val == 1.5:
                            prob_efetiva = max(0.01, 1.0 - p0 - p1)
                        elif t_val == 1.0:
                            prob_efetiva = max(0.01, (1.0 - p0 - p1) + (0.5 * p1))
                        elif t_val == 1.75 or t_val == 2.0:
                            p_ge3 = max(0.0, 1.0 - p0 - p1 - p2)
                            prob_efetiva = max(0.01, p_ge3 + (0.5 * p2))
                        else:
                            prob_efetiva = max(0.01, 1.0 - p0 - p1)
                            
                        odd_justa = 1.0 / prob_efetiva
                        ev_perc = ((price / odd_justa) - 1.0) * 100.0
                        
                        if prob_efetiva >= 0.52 and ev_perc >= 5.0:
                            inplay_cands.append({
                                'line': t_val,
                                'odd': price,
                                'odd_justa': odd_justa,
                                'prob': prob_efetiva * 100.0,
                                'ev_perc': ev_perc,
                                'market_url': outcomes['over']['market_url'],
                                'label': f"Mais de {t_val:g} Gols (In-Play)"
                            })
                        else:
                            rejeicoes_sniper.append(f"Over {t_val:g} @ {price:.2f} (Prob {prob_efetiva*100:.1f}%, EV {ev_perc:+.1f}%)")
                            
            if inplay_cands:
                # Seleciona linha de maior probabilidade / win-rate
                inplay_cands.sort(key=lambda x: (x['prob'], x['ev_perc']), reverse=True)
                best_inplay = inplay_cands[0]
                
                print(f"   🎯 [Sniper Gols In-Play Aprovado] {best_inplay['label']} @ {best_inplay['odd']:.2f} (Odd Justa: {best_inplay['odd_justa']:.2f} | Prob: {best_inplay['prob']:.1f}% | EV: +{best_inplay['ev_perc']:.1f}%) aos {elapsed_min}' 1T")
                
                inplay_reasoning = (
                    f"Sniper In-Play Gols: Partida aos {elapsed_min}' do 1º tempo com placar em 0x0. "
                    f"Com o decaimento temporal, a linha de {best_inplay['label']} atingiu a cotação {best_inplay['odd']:.2f} na Cloudbet. "
                    f"A projeção para os {minutos_restantes} minutos restantes com produção ofensiva de {shots_tot} chutes e {corners_tot} escanteios "
                    f"aponta probabilidade de {best_inplay['prob']:.1f}% e valor esperado positivo de +{best_inplay['ev_perc']:.1f}%."
                )
                detailed_reasoning = f"|| EXPLICACAO: {inplay_reasoning} || MOTIVACAO: {inplay_reasoning} || MEMÓRIA DE CÁLCULO || STATUS GK: APROVADO\nSUGGESTION: {best_inplay['label']}\nCATEGORIA: Gols Asiáticos (In-Play)\nREASON: {inplay_reasoning}"
                
                for uid in user_ids:
                    ok_g, is_dup_g = place_and_commit_cloudbet_bet(
                        client=client,
                        conn=conn,
                        cursor=cursor,
                        uid=uid,
                        fix=eval_fix,
                        event_id=event_id,
                        mercado='Gols Asiáticos (In-Play)',
                        selected_palpite=best_inplay['label'],
                        odd_val=best_inplay['odd'],
                        odd_justa=best_inplay['odd_justa'],
                        prob_poisson=best_inplay['prob'],
                        ev_perc=best_inplay['ev_perc'],
                        market_url=best_inplay['market_url'],
                        cand_destaque=1,
                        gk_category='Gols Asiáticos (In-Play)',
                        reasoning_base=detailed_reasoning
                    )
                    if ok_g:
                        apostas_criadas += 1
                    elif is_dup_g:
                        apostas_duplicadas += 1
            else:
                motivo_espera = ", ".join(rejeicoes_sniper[:2]) if rejeicoes_sniper else "Sem linhas cotadas no intervalo de valor"
                print(f"   ℹ️ [Sniper Gols In-Play] Jogo aos {elapsed_min}' do 1T (0x0): {motivo_espera}. Aguardando decaimento temporal.")
        elif elapsed_min > 35:
            print(f"   🛡️ [Janela In-Play Encerrada] Partida aos {elapsed_min}' do 1T. Entradas em Gols no 0x0 restritas até os 35 minutos.")
        elif tg_markets:
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
                eval_fix, betano_lines=cloudbet_goals_lines, cursor=cursor
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
                        fix=eval_fix,
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
        else:
            print(f"   ⏳ [Cloudbet Gols] Nenhuma linha de Over/Under Gols aberta no momento (seleções desativadas pela corretora).")

        # =====================================================================
        # MÓDULO 3: TOTAL DE CARTÕES (UNDER CARTÕES) - Restrito: Exclusivamente Pré-Jogo (NS)
        # =====================================================================
        if is_inplay or elapsed_min > 0 or cur_status not in ('NS', 'TBD'):
            print(f"   🛡️ [Cartões Standby] Partida em andamento ({cur_status} aos {elapsed_min}'). Entradas em Cartões Under restritas exclusivamente ao Pré-Jogo (NS) para blindagem contra advertências precoces.")
        elif cards_markets:
            sample_c_lines = ", ".join([f"Under {lv:g} @ {d['under']['price']:.2f}" for lv, d in cards_markets.items() if 'under' in d][:4])
            print(f"   📊 [Cloudbet Cartões] {len(cards_markets)} linha(s) cotada(s): {sample_c_lines}{'...' if len(cards_markets) > 4 else ''}")

            league_id = eval_fix.get('league_id')
            if league_id and int(league_id) in CARDS_GATEKEEPER_EXCLUDED_LEAGUE_IDS:
                print(f"   🛡️ [Gatekeeper Cartões NO_BET] Liga excluída por taxa de Reds > 10% (ID #{league_id}).")
            else:
                calc_cards = compute_fixture_expected_cards(cursor, eval_fix)
                if not calc_cards or calc_cards[0] is None:
                    print(f"   ℹ️ [Gatekeeper Cartões] Dados estatísticos de cartões insuficientes no banco para {home} vs {away}.")
                else:
                    exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, _ = calc_cards
                    card_bet_placed = False
                    reject_reasons = []

                    # Avaliação de Crise e Tensão U5J / Degola via Expressões Regulares
                    h_eff = (u5j_info or {}).get('h_eff') if (u5j_info or {}).get('h_eff') is not None else (u5j_info or {}).get('home_eff')
                    a_eff = (u5j_info or {}).get('a_eff') if (u5j_info or {}).get('a_eff') is not None else (u5j_info or {}).get('away_eff')
                    h_zone = eval_fix.get('home_zone')
                    a_zone = eval_fix.get('away_zone')
                    h_rank = eval_fix.get('home_rank')
                    a_rank = eval_fix.get('away_rank')
                    h_ppg = eval_fix.get('home_ppg')
                    a_ppg = eval_fix.get('away_ppg')
                    league_name = eval_fix.get('league_name', '')

                    is_h_relegation = is_team_in_relegation_threat(zone_str=h_zone, rank_val=h_rank, ppg_val=h_ppg, league_name=league_name)
                    is_a_relegation = is_team_in_relegation_threat(zone_str=a_zone, rank_val=a_rank, ppg_val=a_ppg, league_name=league_name)

                    is_relegation_threat = is_h_relegation or is_a_relegation
                    is_crisis = (h_eff is not None and h_eff <= 3.0) or \
                                (a_eff is not None and a_eff <= 3.0) or \
                                is_relegation_threat

                    if is_crisis:
                        if is_relegation_threat:
                            motivo_crise_lbl = "Degola"
                            crise_desc = f"{home} vs {away} sob forte ameaça de rebaixamento/degola"
                        else:
                            motivo_crise_lbl = "Crise U5J"
                            h_str = f"{h_eff:.1f} pts" if h_eff is not None else "crise"
                            a_str = f"{a_eff:.1f} pts" if a_eff is not None else "crise"
                            crise_desc = f"{home} ({h_str}) vs {away} ({a_str}) em colapso técnico recente (U5J <= 3.0 pts)"

                        print(f"   🛡️ [Gatekeeper Cartões NO_BET / {motivo_crise_lbl}] {crise_desc}. Risco de atrito disciplinar extremo: Under vetado e sem entradas em Over (Abstenção Mandatória).")
                        continue

                    # Ordena linhas da maior para a menor (maior linha = menor risco: 8.5 -> 7.5 -> 6.5 -> 5.5)
                    sorted_lines = sorted(cards_markets.keys(), reverse=True)
                    for line_val in sorted_lines:
                        outcomes = cards_markets[line_val]
                        if 'under' not in outcomes or line_val < 6.49:
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
                                    fix=eval_fix,
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
                        else:
                            motivo_linha = []
                            if not is_ref_confirmed:
                                motivo_linha.append("Árbitro não confirmado")
                            elif not is_ref_ok:
                                motivo_linha.append(f"Árbitro rigoroso ({ref_cards_avg:.1f} cartões/jogo)")
                            if not is_xc_ok:
                                motivo_linha.append(f"Expectativa alta ({exp_cards:.2f} xC vs linha {line_val:g})")
                            if prob_poisson_c < 60.0:
                                motivo_linha.append(f"Probabilidade insuficiente ({prob_poisson_c:.1f}%)")
                            if ev_perc_c <= 0.0:
                                motivo_linha.append(f"Sem valor esperado ({ev_perc_c:+.1f}% EV @ {odd_cards:.2f})")
                            if odd_cards < 1.50:
                                motivo_linha.append(f"Odd abaixo de 1.50 (@ {odd_cards:.2f})")
                            reject_reasons.append(f"Under {line_val:g}: {', '.join(motivo_linha)}")

                    if not card_bet_placed:
                        resumo_reprov = reject_reasons[0] if reject_reasons else "Cotações ou margens insuficientes para Under Cartões."
                        print(f"   ❌ [Gatekeeper Cartões - Reprovado] {home} vs {away}: NO_BET | Motivo: {resumo_reprov}")
        else:
            print(f"   ⏳ [Cloudbet Cartões] Nenhuma linha de Cartões aberta no momento (seleções desativadas pela corretora).")

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
