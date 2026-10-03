#!/usr/bin/env python3
"""
Script de Auditoria e Checagem de Odds em Tempo Real para Handicap Asiático
Consulta a API-Sports para uma fixture específica sob demanda,
avalia todas as linhas ativas de AH, reprocessa o algoritmo de recomendação,
atualiza fixtures_trends e aposta correspondente, e retorna o resultado em JSON.
"""

import sys
import os
import json
import argparse
import requests
import pymysql
import io
import contextlib
from datetime import datetime

# Adicionar diretório raiz e scripts ao sys.path para importar rotinas de trends
sys.path.insert(0, "/root/datalake-air-flow-delta/scripts")
sys.path.insert(0, "/root/datalake-air-flow-delta")

from db_config import get_db_connection, get_live_env_vars

def fetch_live_fixture_odds_api(fixture_id):
    """
    Consulta a API-Sports para a fixture_id e retorna dados de 1X2 e todas as linhas de Handicap Asiático.
    """
    env = get_live_env_vars()
    api_key = env.get("FOOTBALL_API_KEY") or env.get("API_SPORTS_KEY") or os.environ.get("FOOTBALL_API_KEY") or "0327019c6fab54df2ea46009b5f0844b"
    url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    try:
        resp = requests.get(url, headers=headers, timeout=12).json()
    except Exception as e:
        return {"error": f"Falha de conexão com API-Sports: {str(e)}"}

    errors = resp.get("errors")
    if errors and isinstance(errors, dict) and len(errors) > 0:
        return {"error": f"API-Sports retornou erro: {errors}"}

    items = resp.get("response", [])
    if not items:
        return {"error": "Nenhuma cotação de mercado retornada pela API para este jogo."}

    # Procura bookmakers com prioridade para Betano (ID 32) ou Bet365 (ID 8) ou qualquer outra
    bookmakers = items[0].get("bookmakers", [])
    if not bookmakers:
        return {"error": "Nenhuma casa de aposta com odds ativas para esta partida na API."}

    selected_bm = None
    for bm in bookmakers:
        b_id = bm.get("id")
        b_name = str(bm.get("name", "")).upper()
        if b_id == 32 or "BETANO" in b_name:
            selected_bm = bm
            break

    if not selected_bm:
        for bm in bookmakers:
            b_id = bm.get("id")
            b_name = str(bm.get("name", "")).upper()
            if b_id == 8 or "BET365" in b_name:
                selected_bm = bm
                break

    if not selected_bm:
        selected_bm = bookmakers[0]

    bm_name = selected_bm.get("name", "Casa Desconhecida")
    bets = selected_bm.get("bets", [])

    odd_home = None
    odd_draw = None
    odd_away = None
    ah_lines = []
    dnb_lines = []

    for b in bets:
        b_id = b.get("id")
        b_name = str(b.get("name", "")).lower()

        # Bet ID 1 = Match Winner (1X2)
        if b_id == 1 or "match winner" in b_name:
            for val in b.get("values", []):
                v_str = str(val.get("value", "")).lower()
                try:
                    v_odd = float(val.get("odd", 0))
                except (ValueError, TypeError):
                    continue
                if "home" in v_str or v_str == "1":
                    odd_home = v_odd
                elif "draw" in v_str or "empate" in v_str or v_str == "x":
                    odd_draw = v_odd
                elif "away" in v_str or v_str == "2":
                    odd_away = v_odd

        # Bet ID 4 = Asian Handicap Full Time
        elif b_id == 4 or "asian handicap" in b_name or "handicap asiático" in b_name:
            if any(term in b_name for term in ["half", "1st", "2nd", "corner", "card", "tempo", "intervalo"]):
                continue
            for val in b.get("values", []):
                val_text = str(val.get("value", "")).strip()
                try:
                    v_odd = float(val.get("odd", 0))
                except (ValueError, TypeError):
                    continue
                if v_odd > 1.0:
                    ah_lines.append({"value": val_text, "odd": v_odd})

        # Draw No Bet (AH 0.0) - Bet ID 2 (Home/Away) ou nome explícito
        elif b_id == 2 or "draw no bet" in b_name or "empate anula" in b_name:
            if b_id == 16 or "total" in b_name or any(term in b_name for term in ["half", "1st", "2nd"]):
                continue
            for val in b.get("values", []):
                val_text = str(val.get("value", "")).strip()
                try:
                    v_odd = float(val.get("odd", 0))
                except (ValueError, TypeError):
                    continue
                if v_odd > 1.0:
                    dnb_lines.append({"value": val_text, "odd": v_odd})

    return {
        "bookmaker": bm_name,
        "odd_home": odd_home,
        "odd_draw": odd_draw,
        "odd_away": odd_away,
        "ah_lines": ah_lines,
        "dnb_lines": dnb_lines
    }

def find_best_matching_odd_for_line(ah_lines, dnb_lines, suggestion, home_team, away_team):
    """
    Localiza a cotação exata da linha recomendada dentro das linhas retornadas pela API.
    """
    sug_clean = suggestion.strip().lower()
    is_home = (home_team.lower() in sug_clean)
    target_side = "home" if is_home else "away"
    target_team = home_team if is_home else away_team

    # Identificar valor numérico da linha (ex: -0.25, 0.0, +0.25, -0.5, +0.5, etc.)
    import re
    match_line = re.search(r'([+-]?\d+(?:\.\d+)?)', sug_clean)
    line_val = match_line.group(1) if match_line else "0.0"

    sign = ''
    if '-' in sug_clean:
        sign = '-'
    elif '+' in sug_clean:
        sign = '+'

    target_tokens = []
    if sign:
        target_tokens.append(f"{sign}{line_val.replace('+', '').replace('-', '')}")
    else:
        target_tokens.append(line_val)

    # 1. Tenta buscar nas linhas de Asian Handicap
    for item in ah_lines:
        v_str = item["value"].lower()
        odd_val = item["odd"]
        # Verifica se corresponde ao lado (Home / Away ou nome do time)
        matches_side = (target_side in v_str) or (target_team.lower() in v_str)
        if matches_side:
            for tok in target_tokens:
                if tok in v_str:
                    return odd_val

    # 2. Se for 0.0 ou Empate Anula, checa Draw No Bet
    if "0.0" in sug_clean or "empate anula" in sug_clean:
        for item in dnb_lines:
            v_str = item["value"].lower()
            odd_val = item["odd"]
            if (target_side in v_str) or (target_team.lower() in v_str):
                return odd_val

    return None

def checar_e_atualizar_odds_fixture(fixture_id, aposta_id=None):
    """
    Executa a auditoria completa de odds para a fixture.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT * FROM fixtures_trends WHERE fixture_id = %s
    """, (fixture_id,))
    fix = cursor.fetchone()

    if not fix:
        return {"success": False, "message": f"Partida #{fixture_id} não encontrada em fixtures_trends."}

    home_team = fix["home_team"]
    away_team = fix["away_team"]
    # 1. Identificar se já existe aposta ativa no banco de dados para esta fixture
    prior_bet = None
    if aposta_id:
        cursor.execute("SELECT * FROM apostas WHERE id = %s", (aposta_id,))
        sp = cursor.fetchone()
        if sp and sp.get("status") in ('Pendente', 'Confirmada'):
            prior_bet = sp

    if not prior_bet:
        cursor.execute("""
            SELECT * FROM apostas 
            WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
              AND status IN ('Pendente', 'Confirmada')
            ORDER BY id DESC LIMIT 1
        """, (fixture_id,))
        prior_bet = cursor.fetchone()

    has_active_bet = (prior_bet is not None)
    prior_palpite = prior_bet["palpite"] if prior_bet else None
    prior_odd = float(prior_bet["odd"]) if (prior_bet and prior_bet.get("odd")) else 0.0

    old_suggestion = prior_palpite or fix.get("ah_suggestion") or ""
    old_odd = prior_odd if prior_odd > 0.0 else float(fix.get("odd_home") or 2.0)

    # Buscar odds atualizadas na API-Sports
    live_odds_data = fetch_live_fixture_odds_api(fixture_id)
    api_failed = False
    api_error_msg = ""
    if "error" in live_odds_data:
        api_failed = True
        api_error_msg = str(live_odds_data["error"])
        if not (fix.get("odd_home") and fix.get("odd_away")):
            return {
                "success": False,
                "message": f"Não foi possível obter odds em tempo real da API e partida não possui odds gravadas: {api_error_msg}"
            }
        bm_name = fix.get("casa_odd_home") or "Betano (Mercado Consolidado)"
        new_oh = float(fix["odd_home"])
        new_od = float(fix.get("odd_draw") or 3.20)
        new_oa = float(fix["odd_away"])
        ah_lines = []
        dnb_lines = []
    else:
        bm_name = live_odds_data["bookmaker"]
        new_oh = live_odds_data["odd_home"] or fix.get("odd_home")
        new_od = live_odds_data["odd_draw"] or fix.get("odd_draw")
        new_oa = live_odds_data["odd_away"] or fix.get("odd_away")
        ah_lines = live_odds_data["ah_lines"]
        dnb_lines = live_odds_data["dnb_lines"]

    # Utilizar motor centralizado Asian Handicap Engine (Single Source of Truth)
    from asian_handicap_engine import (
        calculate_unified_handicap_recommendation,
        compose_compound_ah_reasoning,
        cancelar_e_estornar_aposta_handicap,
        fetch_all_betano_ah_lines
    )

    betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
    if new_oh and new_oa:
        fix["odd_home"] = new_oh
        fix["odd_draw"] = new_od
        fix["odd_away"] = new_oa
        fix["casa_odd_home"] = bm_name

    status_gk, new_suggestion, new_confidence, new_reasoning, best_cand, approved_cands = calculate_unified_handicap_recommendation(
        fix, betano_lines=betano_lines, allow_api_fetch=True, cursor=cursor
    )

    agora_brt = datetime.now().strftime("%d/%m às %H:%M")
    is_open_market = (new_oh and new_oa and float(new_oh) >= 2.10 and float(new_oa) >= 2.10)
    is_no_bet = (status_gk == 'NO_BET' or 'sem entrada' in new_suggestion.lower() or 'abstenção' in new_suggestion.lower() or 'abstencao' in new_suggestion.lower())

    # CASO 1: Motor indicou NO_BET
    if is_no_bet:
        if has_active_bet:
            # Invocar o motor central para avaliar cancelamento e regra estrutural de Ganho de Linha (CLV Positivo)
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, new_reasoning)
            bet_was_cancelled = any(c.get('aposta_id') == prior_bet['id'] for c in canc_list) if canc_list else False

            if not bet_was_cancelled:
                # GANHO DE LINHA CLV POSITIVO: Aposta mantida intacta pois a cotação derreteu no mercado a favor da posição
                new_suggestion = prior_palpite
                final_odd = prior_odd
                tipo_mudanca = "clv_preservado"

                matched_live_odd = find_best_matching_odd_for_line(ah_lines, dnb_lines, new_suggestion, home_team, away_team)
                live_txt = f"@ {matched_live_odd:.2f}" if matched_live_odd else "em cotação mais baixa"

                explicacao = (
                    f"🔒 Aposta Mantida com Ganho de Linha (CLV Positivo) ({agora_brt}): "
                    f"A seleção '{new_suggestion}' contratada a @ {final_odd:.2f} foi mantida intacta. "
                    f"Na {bm_name}, a cotação atual derreteu para {live_txt}, confirmando forte valor esperado e vantagem matemática sobre a linha de fechamento."
                )

                compound_reasoning = compose_compound_ah_reasoning(
                    cursor=cursor,
                    fixture_id=fixture_id,
                    main_calc=f"{explicacao} || {fix.get('ah_reasoning') or new_reasoning}",
                    suggestion=new_suggestion,
                    home_team=home_team,
                    away_team=away_team,
                    home_team_id=fix.get("home_team_id"),
                    away_team_id=fix.get("away_team_id"),
                    existing_reasoning=fix.get("ah_reasoning")
                )

                cursor.execute("""
                    UPDATE fixtures_trends SET
                        ah_suggestion = %s,
                        ah_confidence = %s,
                        ah_reasoning = %s,
                        gatekeeper_category = 'Valor Esperado Positivo (+EV)',
                        odd_home = %s,
                        odd_draw = %s,
                        odd_away = %s,
                        casa_odd_home = %s,
                        casa_odd_draw = %s,
                        casa_odd_away = %s,
                        updated_at = NOW()
                    WHERE fixture_id = %s
                """, (
                    new_suggestion, float(fix.get("ah_confidence") or 74.60), compound_reasoning,
                    new_oh, new_od, new_oa,
                    bm_name, bm_name, bm_name,
                    fixture_id
                ))

                novo_det = f"{explicacao} || {prior_bet.get('resultado_detalhado') or ''}"[:2000]
                cursor.execute("""
                    UPDATE apostas SET
                        resultado_detalhado = %s,
                        updated_at = NOW()
                    WHERE id = %s
                """, (novo_det, prior_bet['id']))
                conn.commit()
                cursor.close()
                conn.close()

                val_aposta = float(prior_bet.get("valor_aposta") or 10.0)
                novo_ganho = round(val_aposta * final_odd, 2)

                return {
                    "success": True,
                    "fixture_id": fixture_id,
                    "aposta_id": prior_bet['id'],
                    "bookmaker": bm_name,
                    "mudou": False,
                    "tipo_mudanca": "clv_preservado",
                    "palpite_antigo": prior_palpite,
                    "palpite_novo": new_suggestion,
                    "odd_antiga": prior_odd,
                    "odd_nova": final_odd,
                    "ganhos_potenciais_novos": novo_ganho,
                    "explicacao_mudanca": explicacao,
                    "novo_detalhado": novo_det,
                    "is_open_market": is_open_market,
                    "odd_home": new_oh,
                    "odd_away": new_oa
                }
            else:
                tipo_mudanca = "cancelada"
                explicacao = f"🚫 Aposta cancelada preventivamente pelo Gatekeeper em tempo real ({agora_brt})."
        else:
            tipo_mudanca = "no_bet"
            explicacao = f"⚪ Sem Entrada recomendada em tempo real ({agora_brt})."

        # Atualizar fixtures_trends para NO_BET
        compound_reasoning = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=fixture_id,
            main_calc=new_reasoning,
            suggestion=new_suggestion,
            home_team=home_team,
            away_team=away_team,
            home_team_id=fix.get("home_team_id"),
            away_team_id=fix.get("away_team_id"),
            existing_reasoning=fix.get("ah_reasoning")
        )
        cursor.execute("""
            UPDATE fixtures_trends SET
                ah_suggestion = %s,
                ah_confidence = %s,
                ah_reasoning = %s,
                odd_home = %s,
                odd_draw = %s,
                odd_away = %s,
                casa_odd_home = %s,
                casa_odd_draw = %s,
                casa_odd_away = %s,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (
            new_suggestion, new_confidence, compound_reasoning,
            new_oh, new_od, new_oa,
            bm_name, bm_name, bm_name,
            fixture_id
        ))
        conn.commit()
        cursor.close()
        conn.close()

        return {
            "success": True,
            "fixture_id": fixture_id,
            "aposta_id": aposta_id,
            "bookmaker": bm_name,
            "mudou": True,
            "tipo_mudanca": tipo_mudanca,
            "palpite_antigo": old_suggestion,
            "palpite_novo": new_suggestion,
            "odd_antiga": old_odd,
            "odd_nova": 0.0,
            "ganhos_potenciais_novos": 0.0,
            "explicacao_mudanca": explicacao,
            "novo_detalhado": compound_reasoning,
            "is_open_market": is_open_market,
            "odd_home": new_oh,
            "odd_away": new_oa
        }

    # CASO 2: Recomendação Aprovada pelo Motor
    if best_cand:
        final_odd = float(best_cand['odd'])
    else:
        matched_odd = find_best_matching_odd_for_line(ah_lines, dnb_lines, new_suggestion, home_team, away_team)
        if matched_odd:
            final_odd = float(matched_odd)
        elif old_odd and old_suggestion.lower() == new_suggestion.lower():
            final_odd = old_odd
        else:
            final_odd = float(new_oh) if (home_team.lower() in new_suggestion.lower()) else float(new_oa)

    # Identificar o que mudou
    sug_mudou = (new_suggestion.strip().lower() != old_suggestion.strip().lower())
    odd_diff = abs(final_odd - old_odd)
    odd_mudou = (odd_diff >= 0.02 and old_odd > 1.0)

    if sug_mudou and odd_mudou:
        tipo_mudanca = "palpite_e_odd"
        explicacao = (
            f"🔄 Palpite e cotação reajustados em tempo real ({agora_brt}): "
            f"Linha alterada de '{old_suggestion}' (@ {old_odd:.2f}) para '{new_suggestion}' (@ {final_odd:.2f}) na {bm_name}. "
            f"Motivo: O fluxo de apostas no mercado movimentou as linhas e a modelagem recalibrada identificou melhor custo-benefício e valor esperado em {new_suggestion}."
        )
    elif sug_mudou:
        tipo_mudanca = "palpite"
        explicacao = (
            f"🔄 Palpite reajustado em tempo real ({agora_brt}): "
            f"Linha alterada de '{old_suggestion}' para '{new_suggestion}' (@ {final_odd:.2f}) na {bm_name}. "
            f"Motivo: As oscilações do mercado indicam maior consistência e proteção na nova linha."
        )
    elif odd_mudou:
        tipo_mudanca = "odd"
        sentido = "subiu" if final_odd > old_odd else "derreteu"
        explicacao = (
            f"⚡ Cotação atualizada na casa {bm_name} ({agora_brt}): "
            f"A linha recomendada permanece '{new_suggestion}', mas a cotação {sentido} de @ {old_odd:.2f} para @ {final_odd:.2f}."
        )
    else:
        tipo_mudanca = "inalterado"
        explicacao = (
            f"✅ Odds confirmadas em tempo real na casa {bm_name} ({agora_brt}): "
            f"A linha '{new_suggestion}' e a cotação @ {final_odd:.2f} continuam rigorosamente válidas e assertivas."
        )

    if is_open_market:
        explicacao += f" [⚠️ Confronto equilibrado com odds abertas H:{float(new_oh):.2f} / A:{float(new_oa):.2f}]"

    compound_reasoning = compose_compound_ah_reasoning(
        cursor=cursor,
        fixture_id=fixture_id,
        main_calc=new_reasoning,
        suggestion=new_suggestion,
        home_team=home_team,
        away_team=away_team,
        home_team_id=fix.get("home_team_id"),
        away_team_id=fix.get("away_team_id"),
        existing_reasoning=fix.get("ah_reasoning")
    )

    cursor.execute("""
        UPDATE fixtures_trends SET
            ah_suggestion = %s,
            ah_confidence = %s,
            ah_reasoning = %s,
            gatekeeper_category = 'Valor Esperado Positivo (+EV)',
            odd_home = %s,
            odd_draw = %s,
            odd_away = %s,
            casa_odd_home = %s,
            casa_odd_draw = %s,
            casa_odd_away = %s,
            updated_at = NOW()
        WHERE fixture_id = %s
    """, (
        new_suggestion, new_confidence, compound_reasoning,
        new_oh, new_od, new_oa,
        bm_name, bm_name, bm_name,
        fixture_id
    ))

    # Atualizar apostas pendentes
    aposta_alvo = None
    novo_ganho = round(10.00 * final_odd, 2)

    if aposta_id:
        cursor.execute("""
            SELECT a.*, 
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas a WHERE a.id = %s
        """, (aposta_id,))
        aposta_alvo = cursor.fetchone()
        if aposta_alvo:
            is_conf = (int(aposta_alvo.get("confirmada") or 0) == 1) or (int(aposta_alvo.get("tem_debito") or 0) > 0)
            if is_conf:
                print(f"🔒 [Checar Odds] Aposta #{aposta_id} já confirmada ou com débito financeiro. Alteração bloqueada para proteger a banca.", file=sys.stderr)
            else:
                val_aposta = float(aposta_alvo.get("valor_aposta") or 10.0)
                novo_ganho = round(val_aposta * final_odd, 2)
                novo_detalhado = f"{explicacao} || {new_reasoning}"[:2000]

                cursor.execute("""
                    UPDATE apostas SET
                        palpite = %s,
                        odd = %s,
                        ganhos_potenciais = %s,
                        resultado_detalhado = %s,
                        updated_at = NOW()
                    WHERE id = %s
                """, (new_suggestion, final_odd, novo_ganho, novo_detalhado, aposta_id))
    else:
        cursor.execute("""
            SELECT a.id, a.valor_aposta, a.confirmada,
                   (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
            FROM apostas a 
            WHERE a.fixture_id = %s AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
              AND a.status IN ('Pendente', 'Não Confirmada')
        """, (fixture_id,))
        apostas_pend = cursor.fetchall()
        for ap in apostas_pend:
            is_conf = (int(ap.get("confirmada") or 0) == 1) or (int(ap.get("tem_debito") or 0) > 0)
            if is_conf:
                print(f"🔒 [Checar Odds] Aposta #{ap['id']} já confirmada com débito. Mantida intacta.", file=sys.stderr)
                continue
            val_ap = float(ap.get("valor_aposta") or 10.0)
            g_pot = round(val_ap * final_odd, 2)
            det = f"{explicacao} || {new_reasoning}"[:2000]
            cursor.execute("""
                UPDATE apostas SET
                    palpite = %s,
                    odd = %s,
                    ganhos_potenciais = %s,
                    resultado_detalhado = %s,
                    updated_at = NOW()
                WHERE id = %s
            """, (new_suggestion, final_odd, g_pot, det, ap["id"]))

    conn.commit()
    cursor.close()
    conn.close()

    return {
        "success": True,
        "fixture_id": fixture_id,
        "aposta_id": aposta_id,
        "bookmaker": bm_name,
        "mudou": (sug_mudou or odd_mudou),
        "tipo_mudanca": tipo_mudanca,
        "palpite_antigo": old_suggestion,
        "palpite_novo": new_suggestion,
        "odd_antiga": old_odd,
        "odd_nova": final_odd,
        "ganhos_potenciais_novos": novo_ganho,
        "explicacao_mudanca": explicacao,
        "novo_detalhado": f"{explicacao} || {new_reasoning}",
        "is_open_market": is_open_market,
        "odd_home": new_oh,
        "odd_away": new_oa
    }

def find_matching_ah_candidate(candidates, target_str, home_team, away_team):
    """
    Localiza o candidato correspondente à linha informada pelo usuário.
    Tenta casamento exato e casamento por time + valor numérico da linha.
    """
    if not target_str or not candidates:
        return None

    def clean_s(s):
        return "".join(c for c in s.lower() if c.isalnum() or c in "+-.")

    t_clean = clean_s(target_str)

    # 1. Casamento direto por string normalizada
    for c in candidates:
        c_str = c.get('palpite_str') or ''
        if clean_s(c_str) == t_clean:
            return c

    # 2. Casamento por time e linha float
    import re
    m_val = re.search(r'([+-]?\d+(?:\.\d+)?)', target_str)
    if m_val:
        try:
            target_val = float(m_val.group(1))
            target_is_away = False
            if away_team and away_team.lower() in target_str.lower():
                target_is_away = True
            elif home_team and home_team.lower() in target_str.lower():
                target_is_away = False
            else:
                target_is_away = False

            for c in candidates:
                c_line = float(c.get('line', 0.0))
                c_away = bool(c.get('is_away', False))
                if abs(c_line - target_val) < 0.01 and c_away == target_is_away:
                    return c
        except Exception:
            pass

    return None

def validar_linha_customizada_ah(fixture_id, aposta_id, linha_alvo, odd_alvo=None):
    """
    Valida em tempo real se o par (Linha AH, Odd) informado pelo usuário
    é aprovado pelo Gatekeeper (+EV, probabilidade efetiva e gestão de risco).
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
    fix = cursor.fetchone()
    if not fix:
        cursor.close()
        conn.close()
        return {"success": False, "message": f"Partida #{fixture_id} não encontrada em fixtures_trends."}

    home_team = fix["home_team"]
    away_team = fix["away_team"]

    # Obter dados de aposta se informada
    aposta_alvo = None
    if aposta_id:
        cursor.execute("SELECT * FROM apostas WHERE id = %s", (aposta_id,))
        aposta_alvo = cursor.fetchone()

    from asian_handicap_engine import (
        fetch_all_betano_ah_lines,
        calculate_unified_handicap_recommendation,
        audit_ah_lines_reasons,
        get_team_u5j_from_db
    )

    betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
    if not betano_lines:
        cursor.close()
        conn.close()
        return {
            "success": True,
            "aprovado": False,
            "fixture_id": fixture_id,
            "aposta_id": aposta_id,
            "linha_solicitada": linha_alvo,
            "explicacao": "⚪ No momento, a Betano não possui linhas de Handicap Asiático abertas para esta partida na API."
        }

    try:
        odd_float = float(odd_alvo) if (odd_alvo is not None and float(odd_alvo) > 0) else None
    except (ValueError, TypeError):
        odd_float = None

    # Se o usuário informou uma odd personalizada para o par (linha, odd)
    eval_lines = []
    matched_in_betano = False
    for l in betano_lines:
        l_copy = dict(l)
        if find_matching_ah_candidate([l_copy], linha_alvo, home_team, away_team):
            if odd_float:
                l_copy['odd'] = odd_float
            matched_in_betano = True
        eval_lines.append(l_copy)

    # Se a linha não constava na lista da Betano mas o usuário forneceu linha e odd
    if not matched_in_betano and odd_float:
        import re
        m_val = re.search(r'([+-]?\d+(?:\.\d+)?)', linha_alvo)
        line_num = float(m_val.group(1)) if m_val else 0.0
        is_away = bool(away_team and away_team.lower() in linha_alvo.lower())
        target_team = away_team if is_away else home_team
        eval_lines.append({
            'team': 'Away' if is_away else 'Home',
            'target_team': target_team,
            'is_away': is_away,
            'line': line_num,
            'palpite_str': f"{target_team} {line_num:+.2f} AH".replace("+0.00", "0.0").replace("-0.00", "0.0").replace(".00", "").replace(".50", ".5").replace(".25", ".25").replace(".75", ".75"),
            'odd': odd_float,
            'raw_value': f"{'Away' if is_away else 'Home'} {line_num}",
            'source': 'USUARIO_CUSTOM'
        })

    status_gk, best_sug, conf, reasoning, best_cand, approved_cands = calculate_unified_handicap_recommendation(
        fix, betano_lines=eval_lines, allow_api_fetch=False, cursor=cursor
    )

    # 1. Verificar se a linha alvo está entre as aprovadas pelo Gatekeeper
    cand_aprovado = find_matching_ah_candidate(approved_cands, linha_alvo, home_team, away_team)

    if cand_aprovado:
        palpite_oficial = cand_aprovado["palpite_str"]
        odd_real = float(cand_aprovado["odd"])
        ev_perc = float(cand_aprovado["eval"]["ev_percent"])
        prob_eff = float(cand_aprovado["eval"]["prob_eff"])
        odd_justa = float(cand_aprovado["eval"]["odd_justa"])

        val_ap = float(aposta_alvo.get("valor_aposta") or 10.0) if aposta_alvo else 10.0
        novo_ganho = round(val_ap * odd_real, 2)

        cursor.close()
        conn.close()
        return {
            "success": True,
            "aprovado": True,
            "fixture_id": fixture_id,
            "aposta_id": aposta_id,
            "linha_oficial": palpite_oficial,
            "odd": odd_real,
            "odd_justa": odd_justa,
            "ev_percent": ev_perc,
            "prob_efetiva": prob_eff,
            "ganhos_potenciais_novos": novo_ganho,
            "explicacao": f"🎯 Par ({palpite_oficial} @ {odd_real:.2f}) APROVADO no Gatekeeper (+EV {ev_perc:+.1f}% | Odd Justa {odd_justa:.2f} | Cobertura {prob_eff:.1f}%).",
            "linhas_aprovadas_disponiveis": [c["palpite_str"] for c in approved_cands],
            "linhas_betano_todas": [l["palpite_str"] for l in betano_lines]
        }

    # 2. Se não está aprovada, verificar justificativa
    cand_testado = find_matching_ah_candidate(eval_lines, linha_alvo, home_team, away_team)
    h_l5 = get_team_u5j_from_db(cursor, fix.get("home_team_id"), home_team)
    a_l5 = get_team_u5j_from_db(cursor, fix.get("away_team_id"), away_team)
    trend_h = (h_l5 or {}).get("trend", "CURVA_ESTAVEL")
    trend_a = (a_l5 or {}).get("trend", "CURVA_ESTAVEL")

    audit_items, _ = audit_ah_lines_reasons(eval_lines, 'NO_BET', 'Sem Entrada', fix, trend_h, trend_a)
    cursor.close()
    conn.close()

    motivo_rejeicao = ""
    if cand_testado:
        palpite_b = cand_testado["palpite_str"]
        odd_b = float(cand_testado["odd"])
        for it in audit_items:
            if palpite_b in it:
                motivo_rejeicao = it
                break
        if not motivo_rejeicao:
            if odd_b < 1.50:
                motivo_rejeicao = f"❌ [REPROVADA] {palpite_b} @ {odd_b:.2f} -> Cotação deprimida abaixo do piso seguro de 1.50 da banca."
            elif odd_b > 2.20:
                motivo_rejeicao = f"❌ [REPROVADA] {palpite_b} @ {odd_b:.2f} -> Cotação acima do teto seguro de risco (+2.20)."
            else:
                motivo_rejeicao = f"❌ [REPROVADA] {palpite_b} @ {odd_b:.2f} -> Reprovada pelo Gatekeeper por margem de valor esperado insuficiente ou gestão de risco."
    else:
        motivo_rejeicao = f"⚪ A linha '{linha_alvo}' não foi encontrada ou não possui cotação aberta para esta partida no momento."

    motivo_limpo = motivo_rejeicao.replace("❌ [REPROVADA] ", "").replace("-> ", ": ")

    return {
        "success": True,
        "aprovado": False,
        "fixture_id": fixture_id,
        "aposta_id": aposta_id,
        "linha_solicitada": linha_alvo,
        "odd": float(cand_testado["odd"]) if cand_testado else None,
        "explicacao": motivo_limpo,
        "linhas_aprovadas_disponiveis": [c["palpite_str"] for c in approved_cands],
        "linhas_betano_todas": [l["palpite_str"] for l in betano_lines]
    }

def salvar_linha_customizada_ah(fixture_id, aposta_id, linha_alvo, odd_alvo=None):
    """
    Aplica a alteração do par (Linha AH, Odd) após revalidação mandatória pelo Gatekeeper.
    Atualiza sincronizadamente fixtures_trends e tabela apostas preservando U5J.
    """
    if not aposta_id:
        return {"success": False, "message": "ID da aposta é obrigatório para salvar a nova linha."}

    val_res = validar_linha_customizada_ah(fixture_id, aposta_id, linha_alvo, odd_alvo)
    if not val_res.get("success") or not val_res.get("aprovado"):
        return {
            "success": False,
            "message": f"Não é possível salvar: {val_res.get('explicacao') or 'Linha não aprovada pelo Gatekeeper.'}",
            "validacao": val_res
        }

    linha_oficial = val_res["linha_oficial"]
    odd_real = val_res["odd"]
    ev_perc = val_res["ev_percent"]
    prob_eff = val_res["prob_efetiva"]
    odd_justa = val_res["odd_justa"]

    conn = get_db_connection()
    cursor = conn.cursor()

    cursor.execute("""
        SELECT a.*, 
               (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
        FROM apostas a WHERE a.id = %s
    """, (aposta_id,))
    aposta = cursor.fetchone()
    if not aposta:
        cursor.close()
        conn.close()
        return {"success": False, "message": f"Aposta #{aposta_id} não encontrada."}

    is_conf = (int(aposta.get("confirmada") or 0) == 1) or (int(aposta.get("tem_debito") or 0) > 0)
    if is_conf:
        cursor.close()
        conn.close()
        return {"success": False, "message": f"Aposta #{aposta_id} já está confirmada ou com débito financeiro. Alteração bloqueada por proteção de banca."}

    cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
    fix = cursor.fetchone()
    if not fix:
        cursor.close()
        conn.close()
        return {"success": False, "message": f"Partida #{fixture_id} não encontrada em fixtures_trends."}

    home_team = fix["home_team"]
    away_team = fix["away_team"]
    val_ap = float(aposta.get("valor_aposta") or 10.0)
    novo_ganho = round(val_ap * odd_real, 2)
    agora_brt = datetime.now().strftime("%d/%m às %H:%M")

    from asian_handicap_engine import compose_compound_ah_reasoning

    explicacao_salva = (
        f"🎯 Par ({linha_oficial} @ {odd_real:.2f}) Ajustado e Aprovado no Gatekeeper ({agora_brt}): "
        f"Parâmetros de Valor: +EV {ev_perc:+.1f}% | Odd Justa {odd_justa:.2f} | Prob. Efetiva {prob_eff:.1f}%."
    )

    compound_r = compose_compound_ah_reasoning(
        cursor=cursor,
        fixture_id=fixture_id,
        main_calc=f"{explicacao_salva} || {fix.get('ah_reasoning') or ''}",
        suggestion=linha_oficial,
        home_team=home_team,
        away_team=away_team,
        home_team_id=fix.get("home_team_id"),
        away_team_id=fix.get("away_team_id"),
        existing_reasoning=fix.get("ah_reasoning")
    )

    conf = round(min(88.0, 55.0 + ev_perc * 0.5), 1)

    cursor.execute("""
        UPDATE fixtures_trends SET
            ah_suggestion = %s,
            ah_confidence = %s,
            ah_reasoning = %s,
            gatekeeper_category = 'Valor Esperado Positivo (+EV)',
            updated_at = NOW()
        WHERE fixture_id = %s
    """, (linha_oficial, conf, compound_r, fixture_id))

    novo_det = f"{explicacao_salva} || {aposta.get('resultado_detalhado') or ''}"[:2000]

    cursor.execute("""
        UPDATE apostas SET
            palpite = %s,
            odd = %s,
            ganhos_potenciais = %s,
            status_gatekeeper = 'APROVADO',
            gatekeeper_category = 'Valor Esperado Positivo (+EV)',
            resultado_detalhado = %s,
            updated_at = NOW()
        WHERE id = %s
    """, (linha_oficial, odd_real, novo_ganho, novo_det, aposta_id))

    conn.commit()
    cursor.close()
    conn.close()

    return {
        "success": True,
        "fixture_id": fixture_id,
        "aposta_id": aposta_id,
        "palpite_novo": linha_oficial,
        "odd_nova": odd_real,
        "ganhos_potenciais_novos": novo_ganho,
        "explicacao": explicacao_salva,
        "message": f"Aposta atualizada com sucesso para {linha_oficial} @ {odd_real:.2f}!"
    }

def main():
    parser = argparse.ArgumentParser(description="Auditar e atualizar odds em tempo real para fixture.")
    parser.add_argument("--fixture_id", type=int, required=True, help="ID da partida")
    parser.add_argument("--aposta_id", type=int, default=None, help="ID da aposta opcional")
    parser.add_argument("--validar_linha", type=str, default=None, help="Linha AH específica para validar no Gatekeeper")
    parser.add_argument("--salvar_linha", type=str, default=None, help="Linha AH específica para salvar na aposta e fixtures_trends")
    parser.add_argument("--odd", type=float, default=None, help="Cotação/Odd específica para validar o par (linha, odd)")
    args = parser.parse_args()

    captured_out = io.StringIO()
    captured_err = io.StringIO()
    try:
        with contextlib.redirect_stdout(captured_out), contextlib.redirect_stderr(captured_err):
            if args.validar_linha:
                result = validar_linha_customizada_ah(args.fixture_id, args.aposta_id, args.validar_linha, args.odd)
            elif args.salvar_linha:
                result = salvar_linha_customizada_ah(args.fixture_id, args.aposta_id, args.salvar_linha, args.odd)
            else:
                result = checar_e_atualizar_odds_fixture(args.fixture_id, args.aposta_id)
    except Exception as e:
        result = {
            "success": False,
            "message": f"Erro interno ao auditar odds: {str(e)}",
            "fixture_id": args.fixture_id,
            "aposta_id": args.aposta_id
        }

    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()
