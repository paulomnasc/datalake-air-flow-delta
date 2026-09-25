#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Revalidador e Gravador Universal Local de Cards (Zero Consumo de API)
====================================================================
Reprocessa e atualiza os palpites, categorias do Gatekeeper e razões analíticas
dos cards na tabela 'fixtures_trends' utilizando EXCLUSIVAMENTE o cache local
do MySQL (fixtures_trends, match_statistics_cache, team_moving_averages,
team_last5_cache, referee_stats).

Suporta revalidação universal:
1. Handicap Asiático (asian_handicap_engine.py)
2. Total de Cartões Under (cards_engine.py)

Garante consumo ZERO de chamadas para API-Sports e The Odds API.
Preserva integralmente a estrutura do payload || U5J_DATA: e a imutabilidade
de apostas confirmadas (confirmada = 1 ou com débito em conta).
"""

import sys
import os
import re
import argparse
from datetime import datetime, timedelta
import pymysql

# Adicionar diretório de scripts ao path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
if SCRIPT_DIR not in sys.path:
    sys.path.insert(0, SCRIPT_DIR)

from checar_odds_ah_fixture import get_db_connection
from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    determine_gatekeeper_category,
    format_gatekeeper_result as format_ah_gk_result,
    compose_compound_ah_reasoning,
    cancelar_e_estornar_aposta_handicap
)
from cards_engine import (
    compute_fixture_expected_cards,
    evaluate_best_card_under_line,
    format_gatekeeper_result as format_cards_gk_result,
    is_knockout_round_advanced,
    _cards_odds_cache
)


def parse_arguments():
    parser = argparse.ArgumentParser(
        description="Revalidador e Gravador Universal Local de Cards via Cache MySQL (Zero API)"
    )
    parser.add_argument(
        "--market",
        type=str,
        choices=["all", "ah", "cards"],
        default="all",
        help="Mercado a revalidar: 'all' (Handicap e Cartões), 'ah' (apenas Handicap) ou 'cards' (apenas Cartões). Padrão: all"
    )
    parser.add_argument(
        "--date",
        type=str,
        default=datetime.now().strftime("%Y-%m-%d"),
        help="Data base para revalidação (YYYY-MM-DD). Padrão: data atual"
    )
    parser.add_argument(
        "--days",
        type=int,
        default=1,
        help="Quantidade de dias à frente para incluir na revalidação (Padrão: 1)"
    )
    parser.add_argument(
        "--fixture_id",
        type=int,
        default=None,
        help="ID específico da partida para revalidar pontualmente (ignora filtro de data)"
    )
    parser.add_argument(
        "--status",
        type=str,
        default="NS,TBD,1H,HT,2H",
        help="Status das partidas a incluir separados por vírgula (Padrão: NS,TBD,1H,HT,2H)"
    )
    parser.add_argument(
        "--sync_apostas",
        action="store_true",
        help="Sincronizar e estornar/atualizar apostas pendentes nos mercados selecionados"
    )
    parser.add_argument(
        "--dry_run",
        action="store_true",
        help="Apenas simula a revalidação sem gravar alterações no banco de dados"
    )
    return parser.parse_args()


def fetch_fixtures_to_revalidate(conn, args):
    """
    Busca no MySQL as partidas elegíveis para revalidação local.
    """
    with conn.cursor() as cur:
        if args.fixture_id:
            cur.execute("""
                SELECT *
                FROM fixtures_trends
                WHERE fixture_id = %s
                LIMIT 1
            """, (args.fixture_id,))
            fixtures = cur.fetchall()
            return fixtures

        # Range de datas
        try:
            start_date_obj = datetime.strptime(args.date, "%Y-%m-%d")
        except ValueError:
            print(f"❌ Formato de data inválido: '{args.date}'. Utilize YYYY-MM-DD.")
            sys.exit(1)

        end_date_obj = start_date_obj + timedelta(days=max(0, args.days))
        start_str = start_date_obj.strftime("%Y-%m-%d 00:00:00")
        end_str = end_date_obj.strftime("%Y-%m-%d 23:59:59")

        status_list = [s.strip() for s in args.status.split(",") if s.strip()]
        is_all_status = any(s.upper() in ('ALL', '*') for s in status_list)
        if status_list and not is_all_status:
            placeholders = ", ".join(["%s"] * len(status_list))
            query = f"""
                SELECT *
                FROM fixtures_trends
                WHERE fixture_date >= %s
                  AND fixture_date <= %s
                  AND status IN ({placeholders})
                ORDER BY fixture_date ASC
            """
            params = [start_str, end_str] + status_list
            cur.execute(query, tuple(params))
        else:
            query = """
                SELECT *
                FROM fixtures_trends
                WHERE fixture_date >= %s
                  AND fixture_date <= %s
                ORDER BY fixture_date ASC
            """
            cur.execute(query, (start_str, end_str))

        fixtures = cur.fetchall()
        return fixtures


def revalidate_fixture_ah(cur, fix, args, counts):
    """
    Revalida o mercado de Handicap Asiático a partir exclusivamente do cache local MySQL.
    """
    fid = fix["fixture_id"]
    h_team = fix.get("home_team", "Mandante")
    a_team = fix.get("away_team", "Visitante")
    f_date = fix.get("fixture_date")
    old_sug = fix.get("ah_suggestion") or "N/A"
    old_cat = fix.get("gatekeeper_category") or "N/A"

    # Reconstrução das linhas candidatas a partir dos dados já presentes no card/banco
    candidate_lines = None
    ex_sug = (fix.get("ah_suggestion") or "").strip()
    ex_reason = fix.get("ah_reasoning") or ""
    has_prior_bet = ex_sug and not any(k in ex_sug.lower() for k in ["sem entrada", "abstenção", "abstencao", "no_bet", "bloqueada"])

    if has_prior_bet:
        m_line = re.search(r"([+-]?\d+(?:\.\d+)?)", ex_sug)
        c_line = float(m_line.group(1)) if m_line else 0.0
        is_away_cand = a_team.lower() in ex_sug.lower()
        target_team = a_team if is_away_cand else h_team

        # Buscar odd no texto da razão ou na tabela de apostas
        m_odd = re.search(r"Odd Betano\s*(\d+(?:\.\d+)?)", ex_reason)
        c_odd = float(m_odd.group(1)) if m_odd else 1.80

        candidate_lines = [{
            "team": "Away" if is_away_cand else "Home",
            "target_team": target_team,
            "is_away": is_away_cand,
            "line": c_line,
            "palpite_str": ex_sug,
            "odd": c_odd,
            "source": "CACHE_LOCAL"
        }]

    # Execução canônica com consumo estritamente local (allow_api_fetch=False)
    status_gk, palpite, conf, reason, best_cand, approved = calculate_unified_handicap_recommendation(
        fixture_dict=fix,
        betano_lines=candidate_lines,
        allow_api_fetch=False,
        cursor=cur
    )

    cat = determine_gatekeeper_category(status_gk, palpite, reason, best_cand)
    counts["ah_categories"][cat] = counts["ah_categories"].get(cat, 0) + 1
    formatted_reason = format_ah_gk_result(status_gk, palpite, reason, category=cat)

    compound_reasoning = compose_compound_ah_reasoning(
        cursor=cur,
        fixture_id=fid,
        main_calc=formatted_reason,
        suggestion=palpite,
        home_team=h_team,
        away_team=a_team,
        home_team_id=fix.get("home_team_id"),
        away_team_id=fix.get("away_team_id"),
        existing_reasoning=fix.get("ah_reasoning"),
        odd_home=fix.get("odd_home"),
        odd_away=fix.get("odd_away")
    )

    if status_gk == "APROVADO":
        counts["ah_approved"] += 1
        badge = "🟢 [AH APROVADO]"
    else:
        counts["ah_no_bet"] += 1
        badge = "⚪ [AH NO_BET]"

    print(f"     {badge} AH: {palpite} [{cat}] (Antes: {old_sug})")

    if not args.dry_run:
        cur.execute("""
            UPDATE fixtures_trends
            SET ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                gatekeeper_category = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (palpite, conf, compound_reasoning, cat, fid))
        counts["ah_cards_updated"] += 1

    if args.sync_apostas:
        cur.execute("""
            SELECT id, palpite, odd, confirmada,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = apostas.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas
            WHERE fixture_id = %s
              AND status = 'Pendente'
              AND (mercado LIKE '%%Handicap%%' OR mercado LIKE '%%AH%%')
        """, (fid,))
        pending_bet = cur.fetchone()

        if pending_bet:
            bet_id = pending_bet["id"]
            is_conf = (int(pending_bet.get("confirmada") or 0) == 1) or (int(pending_bet.get("tem_debito") or 0) > 0)

            if is_conf:
                print(f"     🔒 [AH Aposta Confirmada Mantida] Bet #{bet_id} possui confirmação/débito. Imutabilidade preservada.")
            elif status_gk == "NO_BET" or not best_cand:
                if not args.dry_run:
                    cancelar_e_estornar_aposta_handicap(cur, fid, formatted_reason)
                counts["ah_bets_cancelled"] += 1
                print(f"     ❌ Bet AH #{bet_id} cancelada/estornada por {cat}")
            else:
                if not args.dry_run:
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
                        best_cand.get("odd"),
                        best_cand.get("eval", {}).get("odd_justa"),
                        best_cand.get("eval", {}).get("prob_eff"),
                        best_cand.get("eval", {}).get("ev_percent"),
                        status_gk,
                        formatted_reason,
                        bet_id
                    ))
                counts["ah_bets_updated"] += 1
                print(f"     🔄 Bet AH #{bet_id} atualizada para '{palpite}' @ {best_cand.get('odd')}")


def revalidate_fixture_cards(cur, fix, args, counts):
    """
    Revalida o mercado de Total de Cartões Under usando cache local e cards_engine.py.
    """
    fid = fix["fixture_id"]
    h_team = fix.get("home_team", "Mandante")
    a_team = fix.get("away_team", "Visitante")
    old_ptext = fix.get("prediction_text") or "N/A"

    calc_res = compute_fixture_expected_cards(cur, fix)
    if not calc_res or calc_res[0] is None:
        ptext = format_cards_gk_result("NO_BET", "Sem Entrada (Abstenção)", "Dados estatísticos insuficientes de cartões no banco (Regra nº 9)")
        over_prob = 50.0
        counts["cards_no_bet"] += 1
        print(f"     ⚪ [CARTÕES NO_BET] Dados insuficientes de cartões no banco.")
        if not args.dry_run:
            cur.execute("""
                UPDATE fixtures_trends
                SET prediction_text = %s,
                    over_cards_probability = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (ptext, over_prob, fid))
        return

    exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, team_cards_combined = calc_res
    league_round = str(fix.get("league_round") or "").strip()
    league_name = str(fix.get("league_name") or "").strip()
    is_ko = is_knockout_round_advanced(league_round, league_name)

    # Pré-carregar cotações reais pré-existentes na tabela de apostas para consumo cache-first
    cur.execute("""
        SELECT palpite, odd, casa_de_aposta
        FROM apostas
        WHERE fixture_id = %s AND mercado = 'Total de Cartões' AND odd > 1.0
    """, (fid,))
    existing_card_odds = cur.fetchall()
    for r in existing_card_odds:
        p_str = r.get("palpite") or ""
        o_val = float(r.get("odd") or 0.0)
        b_name = r.get("casa_de_aposta") or "Betano"
        m = re.search(r"(\d+(?:\.\d+)?)", p_str)
        if m:
            l_val = float(m.group(1))
            _cards_odds_cache[f"{fid}_{p_str}_{l_val}"] = (o_val, b_name)
            _cards_odds_cache[f"{fid}_Menos de {l_val} Cartões_{l_val}"] = (o_val, b_name)

    # Reavaliação canônica via cards_engine.py com zero chamadas externas
    selected_cand, valid_cands, pred_text, over_cards_prob = evaluate_best_card_under_line(
        exp_cards=exp_cards,
        fixture_id=fid,
        allow_api=False,
        referee_cards_avg=ref_cards_avg,
        u5j_friction_info=u5j_info,
        is_knockout=is_ko,
        home_team=h_team,
        away_team=a_team,
        is_referee_confirmed=is_ref_confirmed,
        fixture_dict=fix
    )

    if selected_cand and selected_cand.get("status_gk") == "APROVADO":
        counts["cards_approved"] += 1
        palp_desc = selected_cand.get("palpite_str")
        badge = "🟢 [CARTÕES APROVADO]"
        print(f"     {badge} Palpite: {palp_desc} @ {selected_cand.get('real_odd')} (xC: {exp_cards})")
    else:
        counts["cards_no_bet"] += 1
        badge = "⚪ [CARTÕES NO_BET]"
        # Extrair motivo limpo
        m_reason = re.search(r"REASON:\s*(.+)$", pred_text, re.DOTALL)
        r_desc = m_reason.group(1).strip() if m_reason else pred_text
        first_line = r_desc.split("\n")[0]
        print(f"     {badge} {first_line[:90]}... (xC: {exp_cards})")

    if not args.dry_run:
        cur.execute("""
            UPDATE fixtures_trends
            SET prediction_text = %s,
                over_cards_probability = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (pred_text, over_cards_prob, fid))
        counts["cards_cards_updated"] += 1

    if args.sync_apostas:
        cur.execute("""
            SELECT id, palpite, odd, confirmada,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = apostas.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas
            WHERE fixture_id = %s
              AND status = 'Pendente'
              AND mercado = 'Total de Cartões'
        """, (fid,))
        pending_card_bets = cur.fetchall()

        for aposta in pending_card_bets:
            bet_id = aposta["id"]
            is_conf = (int(aposta.get("confirmada") or 0) == 1) or (int(aposta.get("tem_debito") or 0) > 0)

            if is_conf:
                print(f"     🔒 [Cartões Aposta Confirmada Mantida] Bet #{bet_id} possui confirmação/débito. Imutabilidade preservada.")
            elif not selected_cand or selected_cand.get("status_gk") != "APROVADO":
                if not args.dry_run:
                    cur.execute("DELETE FROM apostas WHERE id = %s", (bet_id,))
                counts["cards_bets_cancelled"] += 1
                print(f"     ❌ Bet Cartões #{bet_id} removida por {badge}")
            else:
                if not args.dry_run:
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
                        selected_cand["palpite_str"],
                        selected_cand["real_odd"],
                        selected_cand["odd_justa"],
                        selected_cand["prob"],
                        selected_cand["ev_calc"],
                        "APROVADO",
                        selected_cand["gatekeeper_reason"],
                        bet_id
                    ))
                counts["cards_bets_updated"] += 1
                print(f"     🔄 Bet Cartões #{bet_id} atualizada para '{selected_cand['palpite_str']}' @ {selected_cand['real_odd']}")


def revalidate_fixtures():
    args = parse_arguments()

    print("=" * 80)
    print("🚀 [REVALIDADOR UNIVERSAL LOCAL DE CARDS] Processamento via Cache MySQL (Zero API)")
    print(f"📅 Data Base: {args.date} (+{args.days} dias) | Fixture Específico: {args.fixture_id or 'Todos'}")
    print(f"🎯 Mercado(s): {args.market.upper()} | Status: {args.status} | Sincronizar Apostas: {'SIM' if args.sync_apostas else 'NÃO'} | Dry-Run: {'ATIVADO' if args.dry_run else 'DESATIVADO'}")
    print("🔒 Garantia Operacional: Consumo ZERO de API externa (allow_api=False)")
    print("=" * 80)

    conn = get_db_connection()
    try:
        fixtures = fetch_fixtures_to_revalidate(conn, args)
        total_fixtures = len(fixtures)
        print(f"📋 Encontradas {total_fixtures} partidas para revalidação no cache MySQL.\n")

        if total_fixtures == 0:
            print("ℹ️ Nenhuma partida encontrada para os critérios informados.")
            return

        counts = {
            "processed": 0,
            "ah_approved": 0,
            "ah_no_bet": 0,
            "ah_cards_updated": 0,
            "ah_bets_cancelled": 0,
            "ah_bets_updated": 0,
            "ah_categories": {},
            "cards_approved": 0,
            "cards_no_bet": 0,
            "cards_cards_updated": 0,
            "cards_bets_cancelled": 0,
            "cards_bets_updated": 0
        }

        for idx, fix in enumerate(fixtures, 1):
            fid = fix["fixture_id"]
            h_team = fix.get("home_team", "Mandante")
            a_team = fix.get("away_team", "Visitante")
            f_date = fix.get("fixture_date")

            print(f"\n[{idx}/{total_fixtures}] ⚽ Fixture #{fid} | {h_team} vs {a_team} ({f_date})")

            with conn.cursor() as cur:
                try:
                    # 1. Revalidação de Handicap Asiático
                    if args.market in ("all", "ah"):
                        revalidate_fixture_ah(cur, fix, args, counts)

                    # 2. Revalidação de Cartões Under
                    if args.market in ("all", "cards"):
                        revalidate_fixture_cards(cur, fix, args, counts)

                    if not args.dry_run:
                        conn.commit()

                    counts["processed"] += 1

                except Exception as ex:
                    conn.rollback()
                    print(f"❌ [ERRO] Falha ao processar fixture #{fid} ({h_team} vs {a_team}): {ex}")
                    import traceback
                    traceback.print_exc()

        print("\n" + "=" * 80)
        print("🏁 [REVALIDAÇÃO UNIVERSAL CONCLUÍDA]")
        print(f"📊 Total de Partidas Processadas: {counts['processed']} / {total_fixtures}")

        if args.market in ("all", "ah"):
            print("\n📈 RESUMO - HANDICAP ASIÁTICO:")
            print(f"   🟢 Palpites Aprovados: {counts['ah_approved']}")
            print(f"   ⚪ Abstenções (NO_BET): {counts['ah_no_bet']}")
            if not args.dry_run:
                print(f"   💾 Cards Atualizados no MySQL: {counts['ah_cards_updated']}")
            if args.sync_apostas:
                print(f"   🚫 Apostas Canceladas/Estornadas: {counts['ah_bets_cancelled']}")
                print(f"   🔄 Apostas Atualizadas: {counts['ah_bets_updated']}")
            print("   📋 Distribuição de Categorias do Gatekeeper AH:")
            for category_name, count in sorted(counts["ah_categories"].items(), key=lambda x: x[1], reverse=True):
                print(f"      - {category_name}: {count}")

        if args.market in ("all", "cards"):
            print("\n🟨 RESUMO - TOTAL DE CARTÕES UNDER:")
            print(f"   🟢 Palpites Aprovados: {counts['cards_approved']}")
            print(f"   ⚪ Abstenções (NO_BET): {counts['cards_no_bet']}")
            if not args.dry_run:
                print(f"   💾 Cards Atualizados no MySQL: {counts['cards_cards_updated']}")
            if args.sync_apostas:
                print(f"   🚫 Apostas Canceladas/Removidas: {counts['cards_bets_cancelled']}")
                print(f"   🔄 Apostas Atualizadas: {counts['cards_bets_updated']}")

        print("=" * 80)

    finally:
        conn.close()


if __name__ == "__main__":
    revalidate_fixtures()
