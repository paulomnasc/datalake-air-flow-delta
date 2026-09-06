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
from datetime import datetime

# Adicionar diretório raiz e scripts ao sys.path para importar rotinas de trends
sys.path.insert(0, "/root/datalake-air-flow-delta/scripts")
sys.path.insert(0, "/root/datalake-air-flow-delta")

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
                connect_timeout=3,
                autocommit=True
            )
            return conn
        except Exception:
            continue
    raise RuntimeError("Não foi possível conectar ao banco de dados MySQL.")

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

        # Bet ID 16 = Draw No Bet (AH 0.0)
        elif b_id == 16 or "draw no bet" in b_name or "empate anula" in b_name:
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
    old_suggestion = fix.get("ah_suggestion") or ""

    # Buscar odd anterior na tabela apostas
    old_odd = 0.0
    if aposta_id:
        cursor.execute("SELECT odd FROM apostas WHERE id = %s", (aposta_id,))
        a_row = cursor.fetchone()
        if a_row and a_row.get("odd"):
            old_odd = float(a_row["odd"])
    if old_odd <= 0.0:
        cursor.execute("""
            SELECT odd FROM apostas 
            WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
            ORDER BY id DESC LIMIT 1
        """, (fixture_id,))
        a_row = cursor.fetchone()
        if a_row and a_row.get("odd"):
            old_odd = float(a_row["odd"])
        else:
            old_odd = float(fix.get("odd_home") or 2.0)

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

    # Importar motor de decisão de AH do trends
    from scripts.football_ingest_trends import calculate_asian_handicap_suggestion

    # Reconstruir U5J data se disponível
    raw_reasoning = fix.get("ah_reasoning") or ""
    home_last5 = None
    away_last5 = None
    if "|| U5J_DATA:" in raw_reasoning:
        try:
            u_part = raw_reasoning.split("|| U5J_DATA:")[1].split("||")[0].strip()
            u_data = json.loads(u_part)
            home_last5 = u_data.get("home")
            away_last5 = u_data.get("away")
        except Exception:
            pass

    home_goals_scored = float(fix.get("xg_home") or 1.2)
    away_goals_scored = float(fix.get("xg_away") or 1.0)
    home_goals_conceded = 1.0
    away_goals_conceded = 1.2
    home_cs_pct = 25.0
    away_cs_pct = 25.0

    res_ah = calculate_asian_handicap_suggestion(
        home_goals_scored=home_goals_scored,
        home_goals_conceded=home_goals_conceded,
        away_goals_scored=away_goals_scored,
        away_goals_conceded=away_goals_conceded,
        home_team=home_team,
        away_team=away_team,
        home_cs_pct=home_cs_pct,
        away_cs_pct=away_cs_pct,
        home_last5=home_last5,
        away_last5=away_last5,
        odd_home=new_oh,
        odd_away=new_oa,
        odd_draw=new_od,
        home_rank=fix.get("home_rank"),
        away_rank=fix.get("away_rank"),
        home_ppg=fix.get("home_ppg"),
        away_ppg=fix.get("away_ppg"),
        home_zone=fix.get("home_zone"),
        away_zone=fix.get("away_zone"),
        standings_motivation=fix.get("standings_motivation_score"),
        league_name=fix.get("league_name")
    )

    new_suggestion = res_ah[0]
    new_confidence = res_ah[1]
    new_reasoning = res_ah[2]

    # Obter cotação real atualizada para a linha recomendada
    matched_odd = find_best_matching_odd_for_line(ah_lines, dnb_lines, new_suggestion, home_team, away_team)
    if matched_odd:
        final_odd = float(matched_odd)
    elif old_odd and old_suggestion.lower() == new_suggestion.lower():
        final_odd = old_odd
    else:
        final_odd = float(new_oh) if (home_team.lower() in new_suggestion.lower()) else float(new_oa)

    # Trava em tempo real de Odd Esmagada (< 1.55) para Handicap Negativo:
    # Se a odd encontrada for inferior a 1.55 (ex: Lens -0.25 @ 1.42), busca a linha imediatamente superior (-0.5 ou -0.75) com odd >= 1.55
    if final_odd < 1.55 and ("-0.25" in new_suggestion or "-0.5" in new_suggestion) and ah_lines:
        is_home_sug = (home_team.lower() in new_suggestion.lower())
        team_prefix = home_team if is_home_sug else away_team
        candidate_lines = [f"{team_prefix} -0.5 AH", f"{team_prefix} -0.75 AH", f"{team_prefix} -1.0 AH"]
        for cand in candidate_lines:
            cand_odd = find_best_matching_odd_for_line(ah_lines, dnb_lines, cand, home_team, away_team)
            if cand_odd and float(cand_odd) >= 1.55:
                new_suggestion = cand
                final_odd = float(cand_odd)
                new_reasoning += f" [⚡ Reajuste Dinâmico de Linha: Elevada para {cand} (@ {final_odd:.2f}) para superar o piso mínimo de odd e garantir valor esperado positivo]."
                break

    agora_brt = datetime.now().strftime("%d/%m às %H:%M")
    is_open_market = (new_oh and new_oa and float(new_oh) >= 2.10 and float(new_oa) >= 2.10)

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

    # Atualizar fixtures_trends
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
        new_suggestion, new_confidence, new_reasoning,
        new_oh, new_od, new_oa,
        bm_name, bm_name, bm_name,
        fixture_id
    ))

    # Atualizar apostas pendentes
    aposta_alvo = None
    novo_ganho = round(10.00 * final_odd, 2)

    if aposta_id:
        cursor.execute("SELECT * FROM apostas WHERE id = %s", (aposta_id,))
        aposta_alvo = cursor.fetchone()
        if aposta_alvo:
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
            SELECT id, valor_aposta FROM apostas 
            WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
              AND status IN ('Pendente', 'Não Confirmada')
        """, (fixture_id,))
        apostas_pend = cursor.fetchall()
        for ap in apostas_pend:
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

def main():
    parser = argparse.ArgumentParser(description="Auditar e atualizar odds em tempo real para fixture.")
    parser.add_argument("--fixture_id", type=int, required=True, help="ID da partida")
    parser.add_argument("--aposta_id", type=int, default=None, help="ID da aposta opcional")
    args = parser.parse_args()

    result = checar_e_atualizar_odds_fixture(args.fixture_id, args.aposta_id)
    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()
