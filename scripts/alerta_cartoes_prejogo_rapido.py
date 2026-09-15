#!/usr/bin/env python3
"""
Script de Alerta Pré-Jogo Rápido de Cartões (15 a 45 minutos)
FootballWeb Pipeline - Monitoramento Rápido Pré-Jogo

Executado a cada 30 minutos pela DAG 'alerta_cartoes_prejogo_dag':
1. Busca partidas em aberto com início entre 15 e 45 minutos (momento em que a escala oficial é publicada na API);
2. Enriquece a arbitragem em lote com consumo mínimo de cota da API;
3. Captura odds reais em tempo real da Betano (Bookmaker ID 32);
4. Submete a partida ao Gatekeeper centralizado de cartões (cards_engine.py);
5. Sincroniza Card e Aposta;
6. Dispara notificação imediata no sistema do sininho (notificacoes_usuario) e pop-up Toast da web.
"""

import os
import sys
import re
import pymysql
from datetime import datetime, timedelta

# Adiciona caminhos dos scripts
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from cards_engine import (
    evaluate_best_card_under_line,
    sync_fixture_and_bet_cards,
    enrich_missing_referees_batch,
    calculate_u5j_card_friction,
    get_team_u5j_efficiency_cards,
    is_knockout_round_advanced
)
from leagues_config import is_allowed_league
from football_ingest_trends import get_league_card_multiplier


def get_db_connection():
    """
    Obtém conexão com o MySQL priorizando porta externa e sockets locais.
    """
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
                autocommit=True,
                connect_timeout=3
            )
            return conn
        except Exception:
            continue

    print("❌ [ERRO CRÍTICO] Falha ao conectar em qualquer porta do MySQL.")
    sys.exit(1)


def get_target_user_ids(cursor):
    """
    Retorna lista de IDs do usuário principal (paulomnasc).
    """
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE email LIKE '%paulomnasc%' OR nome LIKE '%paulomnasc%' OR id = 558
        ORDER BY id ASC
        LIMIT 1
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]

    cursor.execute("SELECT id FROM usuario ORDER BY id ASC LIMIT 1")
    first_user = cursor.fetchone()
    return [first_user['id']] if first_user else [1]


def registrar_alerta_sininho(cursor, usuario_id, aposta_id, fixture_id, home_team, away_team, palpite_str, odd_val, ev_perc, bookmaker):
    """
    Registra alerta no sininho do site (tabela notificacoes_usuario), evitando duplicações nos últimos 60 minutos.
    """
    try:
        cursor.execute("""
            SELECT id FROM notificacoes_usuario 
            WHERE usuario_id = %s 
              AND fixture_id = %s 
              AND tipo = 'APOSTA_CARTAO_APROVADA'
              AND criado_em >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        """, (usuario_id, fixture_id))
        ja_notificado = cursor.fetchone()
        if ja_notificado:
            return False

        titulo = f"🎯 Aposta Aprovada: {home_team} vs {away_team}"
        msg = f"Gatekeeper aprovou '{palpite_str}' @ {odd_val:.2f} ({bookmaker}) com EV de +{ev_perc}%. A partida inicia em instantes!"
        link = "/apostas"

        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, criado_em
            ) VALUES (
                %s, %s, %s, 'APOSTA_CARTAO_APROVADA', %s, %s, %s, 0, NOW()
            )
        """, (usuario_id, aposta_id, fixture_id, titulo, msg, link))

        print(f"🔔 [Sininho Alerta Notificado User #{usuario_id}] {titulo}")
        return True
    except Exception as e:
        print(f"⚠️ [Sininho] Falha ao registrar alerta na tabela notificacoes_usuario: {e}")
        return False


def executar_monitoramento_rapido():
    print(f"⚡ [Alerta Pré-Jogo Rápido Cartões] Iniciando varredura rápida (Janela: 15 a 45 min pré-jogo)...")
    conn = get_db_connection()
    cursor = conn.cursor()

    user_ids = get_target_user_ids(cursor)

    # 1. Busca partidas em aberto na janela imediata de 15 a 45 minutos
    cursor.execute("""
        SELECT * FROM fixtures_trends
        WHERE fixture_date >= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
          AND fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 45 MINUTE)
          AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
        ORDER BY fixture_date ASC
    """)
    fixtures = cursor.fetchall()

    if not fixtures:
        print("ℹ️ Nenhuma partida encontrada na janela de 15 a 45 minutos pré-jogo.")
        conn.close()
        return

    print(f"📋 Encontradas {len(fixtures)} partida(s) na janela de 15 a 45 minutos.")

    # 2. Enriquecimento de arbitragem em lote para jogos da janela (API-Sports /fixtures?ids=...)
    referees_enriched = enrich_missing_referees_batch(cursor, conn, fixtures)
    if referees_enriched:
        print(f"🪄 [Escala Atualizada] {len(referees_enriched)} partida(s) tiveram a escala oficial de arbitragem enriquecida.")

    total_alertas = 0
    apostas_processadas = 0

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        if fixture_id in referees_enriched:
            fix['referee_name'] = referees_enriched[fixture_id]

        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']
        league_id = fix.get('league_id')
        league_name = fix.get('league_name') or ''

        # Validação de escopo de ligas
        if not is_allowed_league(league_id, league_name, fixture_date):
            continue

        # Se já possui aposta confirmada pelo usuário, mantém intacta
        cursor.execute("""
            SELECT id, confirmada, status FROM apostas 
            WHERE fixture_id = %s AND mercado = 'Total de Cartões'
        """, (fixture_id,))
        apostas_existentes = cursor.fetchall()
        tem_confirmada = any(int(a.get('confirmada') or 0) == 1 or a.get('status') not in ('Pendente', 'Cancelada') for a in apostas_existentes)
        if tem_confirmada:
            print(f"🔒 [Aposta Confirmada Mantida] {home_team} vs {away_team} já possui bilhete confirmado.")
            continue

        # Identificação de árbitro oficial
        referee_name = (fix.get('referee_name') or '').strip()
        ref_low = referee_name.lower()
        is_ref_confirmed = bool(
            referee_name and not any(un in ref_low for un in [
                'árbitro não informado', 'arbitro nao informado', 'não informado', 
                'nao informado', 'unassigned', 'n/a', 'tbd', 'sem arbitro'
            ])
        )

        prediction_text = (fix.get('prediction_text') or '').strip()
        league_round = (fix.get('league_round') or '').strip()
        is_knockout = is_knockout_round_advanced(league_round, league_name)

        # Eficiência U5J e Atrito Disciplinar
        h_tid = fix.get('home_team_id')
        a_tid = fix.get('away_team_id')
        _, h_eff = get_team_u5j_efficiency_cards(cursor, h_tid, home_team)
        _, a_eff = get_team_u5j_efficiency_cards(cursor, a_tid, away_team)
        friction_mult, friction_desc = calculate_u5j_card_friction(h_eff, a_eff)
        if friction_mult is None:
            continue

        knockout_mult = 1.18 if is_knockout else 1.00

        # Expectativa base ajustada
        match_xc = re.search(r'Expectativa:\s*(\d+(?:\.\d+)?)\s*cartões', prediction_text, re.IGNORECASE)
        if match_xc:
            base_xc = float(match_xc.group(1))
            exp_cards = round(base_xc * friction_mult * knockout_mult, 2)
        else:
            exp_cards = round(4.20 * friction_mult * knockout_mult, 2)

        # Perfil disciplinar: árbitro confirmado ou média institucional da liga
        ref_cards_avg = None
        if is_ref_confirmed:
            cursor.execute("SELECT average_yellow_cards, average_red_cards FROM referee_stats WHERE name = %s", (referee_name,))
            r_row = cursor.fetchone()
            if r_row:
                ref_cards_avg = float(r_row.get('average_yellow_cards') or 0.0) + float(r_row.get('average_red_cards') or 0.0)
        else:
            l_mult, _ = get_league_card_multiplier(league_name, league_id)
            ref_cards_avg = 3.80 if l_mult <= 0.85 else (4.90 if l_mult >= 1.15 else 4.10)

        u5j_info = {
            'h_eff': h_eff,
            'a_eff': a_eff,
            'friction_mult': friction_mult,
            'desc': friction_desc
        }

        # Avaliação de mercado e Gatekeeper com Odds em Tempo Real da Betano
        selected_cand, valid_cands, pred_text, over_cards_prob = evaluate_best_card_under_line(
            exp_cards=exp_cards,
            fixture_id=fixture_id,
            allow_api=True,
            referee_cards_avg=ref_cards_avg,
            u5j_friction_info=u5j_info,
            is_knockout=is_knockout,
            home_team=home_team,
            away_team=away_team,
            is_referee_confirmed=is_ref_confirmed
        )

        if not selected_cand:
            print(f"🛡️ [Gatekeeper NO_BET] {home_team} vs {away_team} -> {pred_text}")
            continue

        # Sincroniza Aposta e Card no banco
        c_cnt, u_cnt = sync_fixture_and_bet_cards(
            cursor=cursor,
            fixture_id=fixture_id,
            home_team=home_team,
            away_team=away_team,
            fixture_date=fixture_date,
            selected_cand=selected_cand,
            user_ids=user_ids,
            prediction_text=pred_text,
            over_cards_prob=over_cards_prob
        )

        palpite_str = selected_cand['palpite_str']
        odd_val = selected_cand['real_odd']
        ev_perc = selected_cand['ev_calc']
        bookmaker = selected_cand.get('bookmaker', 'Betano')

        # Buscar o ID da aposta para vincular a notificação
        for uid in user_ids:
            cursor.execute("""
                SELECT id FROM apostas 
                WHERE fixture_id = %s AND usuario_id = %s AND mercado = 'Total de Cartões'
                ORDER BY id DESC LIMIT 1
            """, (fixture_id, uid))
            ap_row = cursor.fetchone()
            ap_id = ap_row['id'] if ap_row else None

            alerta_gerado = registrar_alerta_sininho(
                cursor=cursor,
                usuario_id=uid,
                aposta_id=ap_id,
                fixture_id=fixture_id,
                home_team=home_team,
                away_team=away_team,
                palpite_str=palpite_str,
                odd_val=odd_val,
                ev_perc=ev_perc,
                bookmaker=bookmaker
            )
            if alerta_gerado:
                total_alertas += 1

        apostas_processadas += 1

    print("\n=======================================================")
    print(f"✅ VARREDURA PRÉ-JOGO RÁPIDA DE CARTÕES CONCLUÍDA!")
    print(f"🎯 Apostas Aprovadas/Sincronizadas: {apostas_processadas}")
    print(f"🔔 Novos Alertas Gerados no Sininho: {total_alertas}")
    print("=======================================================")

    conn.close()


if __name__ == '__main__':
    executar_monitoramento_rapido()
