#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Revalidador e Gravador Local de Cards (Zero Consumo de API)
===========================================================
Reprocessa e atualiza os palpites, categorias do Gatekeeper e razões analíticas
dos cards na tabela 'fixtures_trends' utilizando EXCLUSIVAMENTE o cache local
do MySQL (fixtures_trends, match_statistics_cache, team_moving_averages,
team_last5_cache, referee_stats).

Garante consumo ZERO de chamadas para API-Sports e The Odds API.
Preserva integralmente a estrutura do payload || U5J_DATA: e a higienização da UX.
"""

import sys
import os
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
    format_gatekeeper_result,
    compose_compound_ah_reasoning,
    cancelar_e_estornar_aposta_handicap
)


def parse_arguments():
    parser = argparse.ArgumentParser(
        description="Revalidador e Gravador Local de Cards via Cache MySQL (Zero API)"
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
        help="Sincronizar e estornar/atualizar apostas pendentes no mercado de Handicap"
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


def revalidate_fixtures():
    args = parse_arguments()

    print("=" * 80)
    print("🚀 [REVALIDADOR LOCAL DE CARDS] Iniciando Processamento Baseado Exclusivamente em Cache")
    print(f"📅 Data Base: {args.date} (+{args.days} dias) | Fixture Específico: {args.fixture_id or 'Todos'}")
    print(f"⚙️  Filtro de Status: {args.status} | Sincronizar Apostas: {'SIM' if args.sync_apostas else 'NÃO'} | Modo Dry-Run: {'ATIVADO' if args.dry_run else 'DESATIVADO'}")
    print("🔒 Garantia Operacional: Consumo ZERO de API externa (allow_api_fetch=False)")
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
            "approved": 0,
            "no_bet": 0,
            "cards_updated": 0,
            "bets_cancelled": 0,
            "bets_updated": 0,
            "categories": {}
        }

        for idx, fix in enumerate(fixtures, 1):
            fid = fix["fixture_id"]
            h_team = fix.get("home_team", "Mandante")
            a_team = fix.get("away_team", "Visitante")
            f_date = fix.get("fixture_date")
            old_sug = fix.get("ah_suggestion") or "N/A"
            old_cat = fix.get("gatekeeper_category") or "N/A"

            with conn.cursor() as cur:
                try:
                    # Reconstrução das linhas candidatas a partir dos dados já presentes no card/banco
                    candidate_lines = None
                    ex_sug = (fix.get("ah_suggestion") or "").strip()
                    ex_reason = fix.get("ah_reasoning") or ""
                    has_prior_bet = ex_sug and not any(k in ex_sug.lower() for k in ["sem entrada", "abstenção", "abstencao", "no_bet", "bloqueada"])

                    if has_prior_bet:
                        import re
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

                    # Determinar categoria analítica determinística
                    cat = determine_gatekeeper_category(status_gk, palpite, reason, best_cand)
                    counts["categories"][cat] = counts["categories"].get(cat, 0) + 1

                    # Formatar mensagem canônica do Gatekeeper
                    formatted_reason = format_gatekeeper_result(status_gk, palpite, reason, category=cat)

                    # Compor raciocínio estruturado preservando || U5J_DATA: e linguagem natural
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

                    # Classificação estatística
                    if status_gk == "APROVADO":
                        counts["approved"] += 1
                        badge = "🟢 [APROVADO]"
                    else:
                        counts["no_bet"] += 1
                        badge = "⚪ [NO_BET]"

                    # Exibir no log o resumo da partida
                    print(f"[{idx}/{total_fixtures}] {badge} Fixture #{fid} | {h_team} vs {a_team} ({f_date})")
                    print(f"     Antes:  {old_sug} ({old_cat})")
                    print(f"     Depois: {palpite} [{cat}]")

                    # Persistência no MySQL
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
                        counts["cards_updated"] += 1

                    # Sincronização de apostas pendentes se solicitado
                    if args.sync_apostas:
                        cur.execute("""
                            SELECT * FROM apostas
                            WHERE fixture_id = %s
                              AND status = 'Pendente'
                              AND (mercado LIKE '%%Handicap%%' OR mercado LIKE '%%AH%%')
                        """, (fid,))
                        pending_bet = cur.fetchone()

                        if pending_bet:
                            bet_id = pending_bet["id"]
                            old_bet_palpite = pending_bet.get("palpite")

                            if status_gk == "NO_BET" or not best_cand:
                                if not args.dry_run:
                                    cancelar_e_estornar_aposta_handicap(cur, fid, formatted_reason)
                                counts["bets_cancelled"] += 1
                                print(f"     ❌ Bet #{bet_id} cancelada/estornada por {cat}")
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
                                counts["bets_updated"] += 1
                                print(f"     🔄 Bet #{bet_id} atualizada para '{palpite}' @ {best_cand.get('odd')}")

                    if not args.dry_run:
                        conn.commit()

                    counts["processed"] += 1

                except Exception as ex:
                    conn.rollback()
                    print(f"❌ [ERRO] Falha ao processar fixture #{fid} ({h_team} vs {a_team}): {ex}")
                    import traceback
                    traceback.print_exc()

        print("\n" + "=" * 80)
        print("🏁 [REVALIDAÇÃO CONCLUÍDA]")
        print(f"📊 Total de Partidas Processadas: {counts['processed']} / {total_fixtures}")
        print(f"🟢 Palpites Aprovados: {counts['approved']}")
        print(f"⚪ Abstenções (NO_BET): {counts['no_bet']}")
        if not args.dry_run:
            print(f"💾 Cards Atualizados no MySQL: {counts['cards_updated']}")
        if args.sync_apostas:
            print(f"🚫 Apostas Canceladas/Estornadas: {counts['bets_cancelled']}")
            print(f"🔄 Apostas Atualizadas: {counts['bets_updated']}")

        print("\n📈 Distribuição por Categoria do Gatekeeper:")
        for category_name, count in sorted(counts["categories"].items(), key=lambda x: x[1], reverse=True):
            print(f"   - {category_name}: {count}")
        print("=" * 80)

    finally:
        conn.close()


if __name__ == "__main__":
    revalidate_fixtures()
