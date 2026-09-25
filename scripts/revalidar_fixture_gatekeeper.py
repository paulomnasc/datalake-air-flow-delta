#!/usr/bin/env python3
"""
Script de Revalidação de Odds e Gatekeeper em Tempo Real (Betano ID 32)
Disparado sob demanda pelo Dashboard para uma partida específica (fixture_id).
Consulta a API-Sports para obter cotações atualizadas de 1X2, Asian Handicap e Cartões,
reexecuta os Gatekeepers de Handicap Asiático e Cartões, sincroniza banco de dados (fixtures_trends e apostas),
e retorna o resultado consolidado e higienizado em JSON.
"""

import sys
import os
import json
import argparse
import requests
import pymysql
from datetime import datetime

# Adicionar scripts ao path para importação dos motores
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, SCRIPT_DIR)
sys.path.insert(0, os.path.abspath(os.path.join(SCRIPT_DIR, '..')))

from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    sync_fixture_and_bet_handicap,
    cancelar_e_estornar_aposta_handicap,
    compose_compound_ah_reasoning,
    determine_gatekeeper_category,
    fetch_all_betano_ah_lines,
    analyze_trend_and_momentum,
    is_tier_1_elite_club,
    audit_ah_lines_reasons,
    inject_audit_into_compound_reasoning
)
from cards_engine import (
    compute_fixture_expected_cards,
    evaluate_best_card_under_line,
    sync_fixture_and_bet_cards,
    is_knockout_round_advanced,
    format_gatekeeper_result as format_cards_gk
)

def get_live_env_vars():
    env_paths = [
        "/root/datalake-air-flow-delta/src/footballweb/.env",
        "/root/datalake-air-flow-delta/.env"
    ]
    env_vars = {}
    for p in env_paths:
        if os.path.exists(p):
            with open(p, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        env_vars[k.strip()] = v.strip().strip("'").strip('"')
    return env_vars

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
                connect_timeout=4,
                autocommit=True
            )
            return conn
        except Exception:
            continue
    raise RuntimeError("Não foi possível conectar ao banco de dados MySQL.")

def get_user_ids(cursor, target_user_id=None):
    if target_user_id:
        return [int(target_user_id)]
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE email LIKE '%paulomnasc%' OR nome LIKE '%paulomnasc%' OR id = 558
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]
    return [558]

def fetch_live_betano_odds(fixture_id):
    """
    Busca odds da Betano (Bookmaker 32) na API-Sports.
    Retorna 1X2 e bookmaker encontrado.
    """
    env = get_live_env_vars()
    api_key = env.get("FOOTBALL_API_KEY") or env.get("API_SPORTS_KEY") or "0327019c6fab54df2ea46009b5f0844b"
    url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    try:
        r = requests.get(url, headers=headers, timeout=12)
        data = r.json()
    except Exception as e:
        return {"error": f"Falha na requisição da API-Sports: {str(e)}"}

    errors = data.get("errors")
    if errors and isinstance(errors, dict) and len(errors) > 0:
        return {"error": f"API-Sports retornou erro: {errors}"}

    items = data.get("response", [])
    if not items or not items[0].get("bookmakers"):
        try:
            url_all = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}"
            r_all = requests.get(url_all, headers=headers, timeout=12)
            data_all = r_all.json()
            items = data_all.get("response", [])
        except Exception:
            pass

    if not items or not items[0].get("bookmakers"):
        return {"error": "Nenhuma cotação de mercado retornada pela API para este jogo."}

    bookmakers = items[0].get("bookmakers", [])
    selected_bm = None
    for bm in bookmakers:
        if bm.get("id") == 32 or "BETANO" in str(bm.get("name", "")).upper():
            selected_bm = bm
            break
    if not selected_bm and bookmakers:
        selected_bm = bookmakers[0]

    bm_name = selected_bm.get("name", "Betano") if selected_bm else "Betano"
    bets = selected_bm.get("bets", []) if selected_bm else []

    odd_h, odd_d, odd_a = None, None, None
    for b in bets:
        b_id = b.get("id")
        b_name = str(b.get("name", "")).lower()
        if b_id == 1 or "match winner" in b_name:
            for val in b.get("values", []):
                v_str = str(val.get("value", "")).lower()
                try:
                    v_odd = float(val.get("odd", 0))
                except (ValueError, TypeError):
                    continue
                if "home" in v_str or v_str == "1":
                    odd_h = v_odd
                elif "draw" in v_str or "empate" in v_str or v_str == "x":
                    odd_d = v_odd
                elif "away" in v_str or v_str == "2":
                    odd_a = v_odd

    return {
        "bookmaker": bm_name,
        "odd_home": odd_h,
        "odd_draw": odd_d,
        "odd_away": odd_a
    }

def clean_natural_language_reason(text: str) -> str:
    """
    Remove delimitadores técnicos internos e memória de cálculo (Regras 13 e 17).
    """
    if not text:
        return ""
    if "REASON:" in text:
        part = text.split("REASON:")[1]
        part = part.split("||")[0].strip()
        text = part
    else:
        text = text.split("|| MEMÓRIA")[0].split("|| MEMORIA")[0]
        text = text.split("|| U5J_DATA")[0]
        text = text.split("|| PROBABILIDADES")[0]
    return text.strip().strip("| ")



def revalidar_fixture(fixture_id: int, usuario_id: int = None):
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
    fix = cursor.fetchone()
    if not fix:
        return {"success": False, "message": f"Partida #{fixture_id} não encontrada no banco de dados."}

    user_ids = get_user_ids(cursor, usuario_id)
    home_team = fix["home_team"]
    away_team = fix["away_team"]
    fixture_date = fix["fixture_date"]

    # Extrai tendências de U5J
    u5j_json = {}
    r_text = fix.get('ah_reasoning') or ''
    if '|| U5J_DATA:' in r_text:
        try:
            u5j_json = json.loads(r_text.split('|| U5J_DATA:')[1].split('||')[0].strip())
        except Exception:
            pass
    h_l5 = u5j_json.get('home') if isinstance(u5j_json, dict) else None
    a_l5 = u5j_json.get('away') if isinstance(u5j_json, dict) else None
    h_trend = (h_l5 or {}).get('trend') or (analyze_trend_and_momentum(home_team, h_l5).get('trend') if analyze_trend_and_momentum else 'CURVA_ESTAVEL')
    a_trend = (a_l5 or {}).get('trend') or (analyze_trend_and_momentum(away_team, a_l5).get('trend') if analyze_trend_and_momentum else 'CURVA_ESTAVEL')

    # 1. Obter Odds 1X2 atualizadas (Tenta Betano Direto com fallback para API-Sports)
    live_1x2 = {}
    if home_team and away_team:
        try:
            try:
                from betano_direct_api import fetch_betano_event_by_teams
            except ImportError:
                from scripts.betano_direct_api import fetch_betano_event_by_teams
            direct_data = fetch_betano_event_by_teams(home_team, away_team)
            if direct_data and direct_data.get('odds_1x2'):
                d1 = direct_data['odds_1x2']
                if d1.get('home') and d1.get('away'):
                    live_1x2 = {
                        "odd_home": d1.get('home'),
                        "odd_draw": d1.get('draw'),
                        "odd_away": d1.get('away'),
                        "bookmaker": "Betano (Direto)"
                    }
        except Exception:
            pass

    if not live_1x2 or not live_1x2.get("odd_home"):
        live_1x2 = fetch_live_betano_odds(fixture_id)

    bm_name = live_1x2.get("bookmaker", "Betano")
    odd_h = live_1x2.get("odd_home") or fix.get("odd_home")
    odd_d = live_1x2.get("odd_draw") or fix.get("odd_draw")
    odd_a = live_1x2.get("odd_away") or fix.get("odd_away")

    # Atualiza 1X2 em fixtures_trends se obtidas da API
    if odd_h and odd_a:
        cursor.execute("""
            UPDATE fixtures_trends SET
                odd_home = %s,
                casa_odd_home = %s,
                odd_draw = %s,
                casa_odd_draw = %s,
                odd_away = %s,
                casa_odd_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (odd_h, bm_name, odd_d, bm_name, odd_a, bm_name, fixture_id))
        fix["odd_home"] = odd_h
        fix["odd_draw"] = odd_d
        fix["odd_away"] = odd_a
        fix["casa_odd_home"] = bm_name

    # 2. Revalidação do Handicap Asiático (asian_handicap_engine)
    betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
    
    status_ah, sug_ah, conf_ah, reason_ah, best_cand_ah, app_cands_ah = calculate_unified_handicap_recommendation(
        fix, betano_lines=betano_lines, allow_api_fetch=True, cursor=cursor
    )

    clean_reason_ah = clean_natural_language_reason(reason_ah)

    # Gera a auditoria detalhada das linhas lidas na Betano
    audit_items, structured_lines = audit_ah_lines_reasons(betano_lines, status_ah, sug_ah, fix, h_trend, a_trend)
    audit_bullet = "• 📋 Linhas de Handicap Asiático Auditadas na Betano:\n" + "\n".join(f"  {it}" for it in audit_items)

    # Injeta a auditoria no bloco MOTIVACAO da string composta para exibição na aba Motivação Detalhada
    enriched_reason_ah = inject_audit_into_compound_reasoning(reason_ah, audit_bullet)

    if status_ah == 'APROVADO' and best_cand_ah:
        app_cat_ah = best_cand_ah.get('gatekeeper_category') or determine_gatekeeper_category('APROVADO', sug_ah, reason_ah, best_cand_ah)
        odd_ah = float(best_cand_ah.get('odd') or 0.0)
        odd_justa_ah = float(best_cand_ah.get('eval', {}).get('odd_justa') or 1.40)
        ev_ah = float(best_cand_ah.get('eval', {}).get('ev_percent') or 10.0)

        sync_fixture_and_bet_handicap(
            cursor=cursor,
            fixture_id=fixture_id,
            home_team=home_team,
            away_team=away_team,
            fixture_date=fixture_date,
            selected_palpite=sug_ah,
            odd_val=odd_ah,
            odd_justa=odd_justa_ah,
            prob_poisson=conf_ah,
            ev_perc=ev_ah,
            detalhe_calculo=enriched_reason_ah,
            user_ids=user_ids,
            confirmada_val=0,
            destaque_val=1 if best_cand_ah.get('is_tier1_massacre') else 0,
            best_cand=best_cand_ah
        )
        # Garante a persistência da auditoria de linhas da Betano em fixtures_trends e apostas
        cursor.execute("SELECT ah_reasoning FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
        curr_fix_r = cursor.fetchone()
        if curr_fix_r and curr_fix_r.get('ah_reasoning'):
            enriched_app_r = inject_audit_into_compound_reasoning(curr_fix_r['ah_reasoning'], audit_bullet)
            cursor.execute("UPDATE fixtures_trends SET ah_reasoning = %s WHERE fixture_id = %s", (enriched_app_r, fixture_id))
            cursor.execute("""
                UPDATE apostas SET resultado_detalhado = %s
                WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
            """, (enriched_app_r, fixture_id))
    else:
        cancelar_e_estornar_aposta_handicap(cursor, fixture_id, motivo=clean_reason_ah[:250])
        app_cat_ah = determine_gatekeeper_category('NO_BET', sug_ah, reason_ah)
        odd_ah = None
        compound_r_ah = compose_compound_ah_reasoning(
            cursor, fixture_id, enriched_reason_ah, sug_ah, home_team, away_team,
            fix.get('home_team_id'), fix.get('away_team_id'), fix.get('ah_reasoning')
        )
        compound_r_ah = inject_audit_into_compound_reasoning(compound_r_ah, audit_bullet)

        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                gatekeeper_category = %s,
                ah_reasoning = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (sug_ah, conf_ah, app_cat_ah, compound_r_ah, fixture_id))

    # 3. Revalidação de Cartões (cards_engine)
    calc_res = compute_fixture_expected_cards(cursor, fix)
    status_cards = "NO_BET"
    sug_cards = "Sem Entrada (Abstenção)"
    odd_cards = None
    prob_cards = float(fix.get('over_cards_probability') or 50.0)
    clean_reason_cards = "Dados estatísticos insuficientes de cartões."

    if calc_res and calc_res[0] is not None:
        exp_cards, u5j_info, ref_cards_avg, is_ref_confirmed, team_cards_combined = calc_res
        league_round = (fix.get('league_round') or '').strip()
        league_name = (fix.get('league_name') or '').strip()
        is_knockout = is_knockout_round_advanced(league_round, league_name)

        sel_card_cand, valid_card_cands, pred_text_cards, over_cards_prob = evaluate_best_card_under_line(
            exp_cards=exp_cards,
            fixture_id=fixture_id,
            allow_api=True,
            referee_cards_avg=ref_cards_avg,
            u5j_friction_info=u5j_info,
            is_knockout=is_knockout,
            home_team=home_team,
            away_team=away_team,
            is_referee_confirmed=is_ref_confirmed,
            fixture_dict=fix
        )

        clean_reason_cards = clean_natural_language_reason(pred_text_cards)
        prob_cards = float(over_cards_prob or 50.0)

        if sel_card_cand and sel_card_cand.get('real_odd'):
            sync_fixture_and_bet_cards(
                cursor=cursor,
                fixture_id=fixture_id,
                home_team=home_team,
                away_team=away_team,
                fixture_date=fixture_date,
                selected_cand=sel_card_cand,
                user_ids=user_ids,
                prediction_text=pred_text_cards,
                over_cards_prob=over_cards_prob
            )
            status_cards = "APROVADO"
            sug_cards = sel_card_cand.get('palpite_str')
            odd_cards = float(sel_card_cand.get('real_odd') or 0.0)
        else:
            status_cards = "NO_BET"
            sug_cards = "Sem Entrada (Abstenção)"
            odd_cards = None
            cursor.execute("""
                UPDATE fixtures_trends SET
                    prediction_text = %s,
                    over_cards_probability = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (pred_text_cards, over_cards_prob, fixture_id))

    return {
        "success": True,
        "fixture_id": fixture_id,
        "home_team": home_team,
        "away_team": away_team,
        "bookmaker": bm_name,
        "odds_1x2": {
            "home": float(odd_h) if odd_h else None,
            "draw": float(odd_d) if odd_d else None,
            "away": float(odd_a) if odd_a else None,
        },
        "handicap": {
            "status": status_ah,
            "suggestion": sug_ah,
            "confidence": float(conf_ah),
            "category": app_cat_ah,
            "odd": odd_ah,
            "reason_clean": clean_reason_ah,
            "lines_read": structured_lines
        },
        "cards": {
            "status": status_cards,
            "suggestion": sug_cards,
            "probability": prob_cards,
            "odd": odd_cards,
            "reason_clean": clean_reason_cards
        },
        "message": f"Odds da {bm_name} e Gatekeepers (Handicap e Cartões) revalidados com sucesso!"
    }

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Revalidar Odds e Gatekeeper em Tempo Real")
    parser.add_argument("--fixture_id", type=int, required=True, help="ID da partida")
    parser.add_argument("--usuario_id", type=int, default=None, help="ID do usuário")
    args = parser.parse_args()

    try:
        res = revalidar_fixture(args.fixture_id, args.usuario_id)
        print(json.dumps(res, ensure_ascii=False))
    except Exception as e:
        err = {"success": False, "message": f"Erro interno ao revalidar: {str(e)}"}
        print(json.dumps(err, ensure_ascii=False))
        sys.exit(1)
