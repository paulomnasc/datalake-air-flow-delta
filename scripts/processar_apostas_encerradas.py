#!/usr/bin/env python3
"""
Script de Processamento de Apostas Encerradas (Airflow DAG Worker / Web Service)
Executado diariamente às 23:00 hs para verificar os jogos encerrados do dia, 
calcular estatísticas finais (cartões, gols, escanteios) e definir apostas como Ganhas ou Perdidas.
"""

import sys
import os
import re
import pymysql
import hashlib
import random
import requests
from datetime import datetime, timedelta

def get_db_connection():
    """
    Obtém conexão com o MySQL (tenta docker internal 'mysql' e localhost fallback).
    """
    try:
        conn = pymysql.connect(
            host="mysql",
            port=3306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
        print("✅ [DAG Processador Apostas] Conectado ao MySQL via docker (mysql:3306)")
        return conn
    except Exception:
        pass

    try:
        conn = pymysql.connect(
            host="127.0.0.1",
            port=23306,
            user="root",
            password="YM11rMrT32xH0E6N",
            database="footballweb",
            charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
            autocommit=True
        )
        print("✅ [DAG Processador Apostas] Conectado ao MySQL via localhost (127.0.0.1:23306)")
        return conn
    except Exception as e:
        print(f"❌ [ERRO CRÍTICO] Falha ao conectar no MySQL: {e}")
        sys.exit(1)

def creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor, status, descricao=None):
    """
    Credita o retorno de uma aposta resolvida/ganha/anulada na conta_corrente e atualiza usuario.saldo_conta_corrente.
    Possui checagem anti-duplicidade (idempotência) para evitar creditar a mesma aposta duas vezes.
    """
    try:
        valor = float(valor or 0.0)
        if valor <= 0 or not usuario_id:
            return False

        cursor.execute("""
            SELECT id FROM conta_corrente 
            WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'CREDITO_RETORNO_APOSTA'
            LIMIT 1
        """, (usuario_id, aposta_id))
        if cursor.fetchone():
            return False

        if not descricao:
            descricao = f"Retorno Aposta #{aposta_id} ({status})"

        cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
        row_u = cursor.fetchone()
        if row_u and row_u.get('saldo_conta_corrente') is not None:
            saldo_anterior = float(row_u['saldo_conta_corrente'])
        else:
            cursor.execute("""
                SELECT saldo_posterior FROM conta_corrente 
                WHERE usuario_id = %s 
                ORDER BY id DESC LIMIT 1
            """, (usuario_id,))
            row_cc = cursor.fetchone()
            saldo_anterior = float(row_cc['saldo_posterior']) if row_cc and row_cc.get('saldo_posterior') is not None else 0.0

        saldo_posterior = round(saldo_anterior + valor, 2)

        cursor.execute("""
            INSERT INTO conta_corrente (usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em)
            VALUES (%s, %s, 'CREDITO_RETORNO_APOSTA', %s, %s, %s, %s, NOW())
        """, (usuario_id, aposta_id, descricao, valor, saldo_anterior, saldo_posterior))

        cursor.execute("""
            UPDATE usuario
            SET saldo_conta_corrente = %s
            WHERE id = %s
        """, (saldo_posterior, usuario_id))

        print(f"💰 [Crédito Conta Corrente] Aposta #{aposta_id} -> Creditado R$ {valor:.2f} para Usuário #{usuario_id} (Novo Saldo: R$ {saldo_posterior:.2f})")
        return True
    except Exception as e:
        print(f"⚠️ Erro ao creditar retorno da aposta #{aposta_id} na conta corrente: {e}")
        return False

def fetch_real_fixture_cards_api(fixture_id, home_team_id=None, cursor=None):
    """
    Busca estatísticas e eventos oficiais de cartões na API-Sports para a partida.
    Verifica primeiro se os cartões já estão armazenados no cache local (match_statistics_cache ou fixtures_trends).
    """
    if cursor is not None and fixture_id:
        try:
            cursor.execute("""
                SELECT yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away, last_event,
                       cards_api_checked_at, cards_api_retry_count
                FROM fixtures_trends 
                WHERE fixture_id = %s
                LIMIT 1
            """, (fixture_id,))
            row = cursor.fetchone()
            if row:
                yh = row.get('yellow_cards_home', 0) or 0
                ya = row.get('yellow_cards_away', 0) or 0
                rh = row.get('red_cards_home', 0) or 0
                ra = row.get('red_cards_away', 0) or 0
                last_ev = row.get('last_event')
                checked_at = row.get('cards_api_checked_at')
                retry_cnt = row.get('cards_api_retry_count', 0) or 0

                if checked_at is not None:
                    if (yh + ya + rh + ra) > 0 or (last_ev is not None and last_ev != ''):
                        return (yh, ya, rh, ra)

                    if isinstance(checked_at, datetime):
                        hours_since_check = (datetime.now() - checked_at).total_seconds() / 3600.0
                        if hours_since_check < 6.0:
                            print(f"⏳ [Cooldown API] Fixture #{fixture_id} consultada há {hours_since_check:.1f}h (tentativas: {retry_cnt}). Pulando chamada HTTP para economizar cota.")
                            return None
            
            cursor.execute("""
                SELECT team_id, yellow_cards, red_cards 
                FROM match_statistics_cache 
                WHERE fixture_id = %s
            """, (fixture_id,))
            cache_rows = cursor.fetchall()
            if cache_rows and len(cache_rows) > 0:
                yh, ya, rh, ra = 0, 0, 0, 0
                found = False
                for r in cache_rows:
                    t_id = r.get('team_id')
                    is_home = (t_id == home_team_id) if (home_team_id and t_id) else True
                    if is_home:
                        yh = r.get('yellow_cards', 0) or 0
                        rh = r.get('red_cards', 0) or 0
                        found = True
                    else:
                        ya = r.get('yellow_cards', 0) or 0
                        ra = r.get('red_cards', 0) or 0
                        found = True
                if found:
                    return (yh, ya, rh, ra)
        except Exception as e_cache:
            print(f"⚠️ Erro ao consultar cache local para fixture #{fixture_id}: {e_cache}")

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {'x-apisports-key': api_key, 'User-Agent': 'Mozilla/5.0'}
    yh, ya, rh, ra = None, None, None, None
    api_success = False

    try:
        url_st = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
        res_st = requests.get(url_st, headers=headers, timeout=10)
        if res_st.status_code == 200:
            api_success = True
            st_data = res_st.json().get("response", [])
            for idx, team_st in enumerate(st_data):
                t_id = team_st.get("team", {}).get("id")
                is_home = (t_id == home_team_id) if home_team_id else (idx == 0)
                for s in team_st.get("statistics", []):
                    s_type = (s.get("type") or "").strip()
                    s_val = s.get("value")
                    if s_type == "Yellow Cards" and s_val is not None:
                        if is_home: yh = int(s_val)
                        else: ya = int(s_val)
                    elif s_type == "Red Cards" and s_val is not None:
                        if is_home: rh = int(s_val)
                        else: ra = int(s_val)
    except Exception as e:
        print(f"⚠️ Erro ao buscar estatísticas na API para fixture {fixture_id}: {e}")

    try:
        url_ev = f"https://v3.football.api-sports.io/fixtures/events?fixture={fixture_id}"
        res_ev = requests.get(url_ev, headers=headers, timeout=10)
        if res_ev.status_code == 200:
            api_success = True
            ev_data = res_ev.json().get("response", [])
            eyh, eya, erh, era = 0, 0, 0, 0
            has_card_events = False
            for ev in ev_data:
                if ev.get("type") == "Card":
                    has_card_events = True
                    t_id = ev.get("team", {}).get("id")
                    is_home = (t_id == home_team_id) if home_team_id else True
                    detail = ev.get("detail", "")
                    if "Yellow" in detail:
                        if is_home: eyh += 1
                        else: eya += 1
                    elif "Red" in detail:
                        if is_home: erh += 1
                        else: era += 1
            if has_card_events or yh is None:
                yh = max(yh if yh is not None else 0, eyh)
                ya = max(ya if ya is not None else 0, eya)
                rh = max(rh if rh is not None else 0, erh)
                ra = max(ra if ra is not None else 0, era)
    except Exception as e:
        print(f"⚠️ Erro ao buscar eventos na API para fixture {fixture_id}: {e}")

    if not api_success and yh is None and ya is None:
        return None

    res_tuple = (
        yh if yh is not None else 0,
        ya if ya is not None else 0,
        rh if rh is not None else 0,
        ra if ra is not None else 0
    )

    if cursor is not None and fixture_id and api_success:
        try:
            if home_team_id:
                cursor.execute("""
                    INSERT INTO match_statistics_cache (fixture_id, team_id, corners, yellow_cards, red_cards)
                    VALUES (%s, %s, 0, %s, %s)
                    ON DUPLICATE KEY UPDATE yellow_cards = VALUES(yellow_cards), red_cards = VALUES(red_cards)
                """, (fixture_id, home_team_id, res_tuple[0], res_tuple[2]))

            total_c = (res_tuple[0] + res_tuple[1] + res_tuple[2] + res_tuple[3])
            if total_c > 0:
                cursor.execute("UPDATE fixtures_trends SET cards_api_checked_at = NOW(), cards_api_retry_count = 0 WHERE fixture_id = %s", (fixture_id,))
            else:
                cursor.execute("UPDATE fixtures_trends SET cards_api_checked_at = NOW(), cards_api_retry_count = cards_api_retry_count + 1 WHERE fixture_id = %s", (fixture_id,))
        except Exception:
            pass

    return res_tuple

def get_fixture_stats(fixture, cursor=None):
    """
    Extrai as estatísticas reais finais da partida (FT).
    Retorna None se os dados de gols ou status do jogo estiverem incompletos/nulos no banco.
    """
    status = (fixture.get('status') or '').strip().upper()
    finished_statuses = ['FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED']
    if status not in finished_statuses:
        return None

    goals_home = fixture.get('goals_home')
    goals_away = fixture.get('goals_away')

    if goals_home is None or goals_away is None:
        return None

    yellow_home = fixture.get('yellow_cards_home')
    yellow_away = fixture.get('yellow_cards_away')
    red_home = fixture.get('red_cards_home')
    red_away = fixture.get('red_cards_away')

    if (yellow_home is None or yellow_away is None or (yellow_home == 0 and yellow_away == 0 and not fixture.get('last_event'))) and fixture.get('fixture_id'):
        fid = fixture['fixture_id']
        htid = fixture.get('home_team_id')
        cards_res = fetch_real_fixture_cards_api(fid, htid, cursor=cursor)
        if cards_res is not None:
            yh, ya, rh, ra = cards_res
        else:
            yh, ya, rh, ra = None, None, None, None

        if yh is not None:
            yellow_home, yellow_away, red_home, red_away = yh, ya, rh, ra
            if cursor:
                cursor.execute("""
                    UPDATE fixtures_trends
                    SET yellow_cards_home = %s,
                        yellow_cards_away = %s,
                        red_cards_home = %s,
                        red_cards_away = %s,
                        updated_at = NOW()
                    WHERE fixture_id = %s
                """, (yellow_home, yellow_away, red_home, red_away, fid))

    yellow_home = yellow_home or 0
    yellow_away = yellow_away or 0
    red_home = red_home or 0
    red_away = red_away or 0
    corners_home = fixture.get('corners_home') or 0
    corners_away = fixture.get('corners_away') or 0

    return {
        'status': status,
        'goals_home': goals_home,
        'goals_away': goals_away,
        'yellow_cards_home': yellow_home,
        'yellow_cards_away': yellow_away,
        'red_cards_home': red_home,
        'red_cards_away': red_away,
        'corners_home': corners_home,
        'corners_away': corners_away,
        'total_cards': yellow_home + yellow_away + red_home + red_away,
        'total_goals': goals_home + goals_away,
        'total_corners': corners_home + corners_away
    }

def evaluate_bet(aposta, stats):
    """
    Avalia se o palpite da aposta foi GANHO, PERDIDO ou ANULADO com base nas estatísticas finais da partida.
    Retorna tupla: (novo_status: 'Ganha'|'Perdida'|'ANULADA', detalhe: str, valor_computado: float)
    """
    palpite = aposta.get('palpite', '').strip()
    mercado = aposta.get('mercado', '').strip()
    valor_aposta = float(aposta.get('valor_aposta', 0.0) or 0.0)
    odd_original = float(aposta.get('odd', 1.0) or 1.0)
    ganhos_originais = float(aposta.get('ganhos_potenciais', 0.0) or (valor_aposta * odd_original))
    
    total_cards = stats['total_cards']
    total_goals = stats['total_goals']
    total_corners = stats['total_corners']
    goals_home = stats['goals_home']
    goals_away = stats['goals_away']
    time_casa = aposta.get('time_casa', '').strip()
    time_fora = aposta.get('time_fora', '').strip()
    
    # Normalização de texto para regex
    palpite_norm = palpite.lower()
    mercado_norm = mercado.lower()

    # 0. MERCADO: HANDICAP ASIÁTICO / EMPATE ANULA / DNB
    is_handicap_market = (
        'handicap' in mercado_norm or 'handicap' in palpite_norm or
        'empate anula' in palpite_norm or 'empate anula' in mercado_norm or
        'dnb' in palpite_norm or 'dnb' in mercado_norm or
        'ah' in palpite_norm or 'ah' in mercado_norm
    )

    if is_handicap_market or '0.0' in palpite_norm or '0,0' in palpite_norm:
        # Identifica se a aposta é no Visitante ou Mandante
        is_away_bet = False
        if time_fora and time_fora.lower() in palpite_norm:
            is_away_bet = True
        elif 'fora' in palpite_norm or ' 2 ' in palpite_norm:
            is_away_bet = True

        # Extrai o valor da linha de Handicap (ex: -0.25, +0.25, -0.75, +0.75, 0.0, -0.5, +0.5, -1.0)
        line = 0.0
        match_line = re.search(r'([+-]?\d+(?:[\.,]\d+)?)', palpite)
        if match_line:
            try:
                line = float(match_line.group(1).replace(',', '.'))
            except Exception:
                line = 0.0

        # Diferença de gols do time apostado
        if is_away_bet:
            diff_gols = goals_away - goals_home
            team_bet = time_fora or "Visitante"
        else:
            diff_gols = goals_home - goals_away
            team_bet = time_casa or "Mandante"

        # Saldo Ajustado do Handicap
        adj = diff_gols + line

        if adj > 0.25:
            detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> Aposta GANHA!"
            return 'Ganha', detalhe, ganhos_originais
        elif abs(adj - 0.25) < 0.01:
            # MEIO GANHA (50% ganha na odd + 50% reembolsada)
            valor_computado = valor_aposta * ((odd_original + 1.0) / 2.0)
            detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> MEIO GANHA (Retorno R$ {valor_computado:.2f})"
            return 'Meio Ganha', detalhe, valor_computado
        elif abs(adj) < 0.01:
            # ANULADA (100% Reembolsada)
            detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> Aposta ANULADA (Reembolso R$ {valor_aposta:.2f})"
            return 'ANULADA', detalhe, valor_aposta
        elif abs(adj - (-0.25)) < 0.01:
            # MEIO PERDIDA (50% Reembolsada + 50% Perdida)
            valor_computado = valor_aposta * 0.5
            detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> MEIO PERDIDA (Retorno R$ {valor_computado:.2f})"
            return 'Meio Perdida', detalhe, valor_computado
        else:
            # PERDIDA (100% Perdida)
            detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> Aposta PERDIDA"
            return 'Perdida', detalhe, 0.0

    # 1. MERCADO: CARTÕES (Total da partida ou por time individual)
    if 'cart' in mercado_norm or 'cart' in palpite_norm:
        match_thresh = re.search(r'(\d+(?:\.\d+)?)', palpite)
        threshold = float(match_thresh.group(1)) if match_thresh else 5.5
        
        cards_home = (stats.get('yellow_cards_home') or 0) + (stats.get('red_cards_home') or 0)
        cards_away = (stats.get('yellow_cards_away') or 0) + (stats.get('red_cards_away') or 0)

        tc_lower = (time_casa or '').lower()
        tf_lower = (time_fora or '').lower()

        is_home_target = False
        is_away_target = False

        if (tc_lower and tc_lower in palpite_norm) or (tc_lower and tc_lower in mercado_norm) or 'time casa' in palpite_norm or 'time casa' in mercado_norm:
            is_home_target = True
        elif (tf_lower and tf_lower in palpite_norm) or (tf_lower and tf_lower in mercado_norm) or 'time fora' in palpite_norm or 'time fora' in mercado_norm:
            is_away_target = True

        if is_home_target:
            actual_cards = cards_home
            target_name = f"Cartões Time Casa ({time_casa})"
        elif is_away_target:
            actual_cards = cards_away
            target_name = f"Cartões Time Fora ({time_fora})"
        else:
            actual_cards = total_cards
            target_name = "Total Cartões Jogo"

        is_under = ('menos' in palpite_norm or 'abaixo' in palpite_norm or 'under' in palpite_norm)
        is_over  = ('mais' in palpite_norm or 'acima' in palpite_norm or 'over' in palpite_norm)
        
        if is_over:
            won = (actual_cards > threshold)
            comp = ">"
        else:
            won = (actual_cards < threshold)
            comp = "<"

        status_str = 'Ganha' if won else 'Perdida'
        detalhe = f"FT | {target_name}: {actual_cards} {comp} Limite {threshold} -> Aposta {status_str.upper()}"
        return status_str, detalhe, (ganhos_originais if won else 0.0)

    # 2. MERCADO: GOLS (Ex: "Menos de 2.5", "Mais de 2.5")
    elif 'gol' in mercado_norm or 'gol' in palpite_norm:
        match_thresh = re.search(r'(\d+(?:\.\d+)?)', palpite)
        threshold = float(match_thresh.group(1)) if match_thresh else 2.5
        
        is_under = ('menos' in palpite_norm or 'abaixo' in palpite_norm or 'under' in palpite_norm)
        is_over  = ('mais' in palpite_norm or 'acima' in palpite_norm or 'over' in palpite_norm)
        
        if is_under:
            won = (total_goals < threshold)
            comp = "<"
        else:
            won = (total_goals > threshold)
            comp = ">"

        status_str = 'Ganha' if won else 'Perdida'
        detalhe = f"FT 23:00 | Placar: {goals_home}x{goals_away} (Total {total_goals} Gols {comp} {threshold}) -> Aposta {status_str.upper()}"
        return status_str, detalhe, (ganhos_originais if won else 0.0)

    # 3. MERCADO: RESULTADO / VENCEDOR (Ex: "Casa Vence", "Empate", "Fora Vence")
    elif 'vencedor' in mercado_norm or 'resultado' in mercado_norm or 'vence' in palpite_norm:
        if 'casa' in palpite_norm or '1' in palpite_norm:
            won = (goals_home > goals_away)
        elif 'fora' in palpite_norm or '2' in palpite_norm:
            won = (goals_away > goals_home)
        elif 'empate' in palpite_norm or 'x' in palpite_norm:
            won = (goals_home == goals_away)
        else:
            won = (goals_home > goals_away)

        status_str = 'Ganha' if won else 'Perdida'
        detalhe = f"FT 23:00 | Placar Final: {goals_home}x{goals_away} -> Aposta {status_str.upper()}"
        return status_str, detalhe, (ganhos_originais if won else 0.0)

    # FALLBACK GENÉRICO (Trata palpites tipo "Menos de X")
    match_thresh = re.search(r'(\d+(?:\.\d+)?)', palpite)
    if match_thresh:
        threshold = float(match_thresh.group(1))
        won = (total_cards < threshold)
        status_str = 'Ganha' if won else 'Perdida'
        detalhe = f"FT 23:00 | Resultado {total_cards} vs Limite {threshold} -> Aposta {status_str.upper()}"
        return status_str, detalhe, (ganhos_originais if won else 0.0)

    # Se não conseguir avaliar, marca como Ganha por default para simulação
    return 'Ganha', f"FT 23:00 | Jogo Encerrado -> Aposta GANHA!", ganhos_originais

def process_pending_bets():
    """
    Busca todas as apostas com status 'Pendente' e processa os palpites com base nos jogos encerrados.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    print("🔍 [DAG 23:00] Iniciando verificação de apostas pendentes...")

    # Buscar apostas pendentes
    cursor.execute("""
        SELECT a.* 
        FROM apostas a 
        WHERE a.status = 'Pendente'
    """)
    pendentes = cursor.fetchall()

    if not pendentes:
        print("ℹ️ [DAG 23:00] Nenhuma aposta pendente encontrada para processamento.")
        conn.close()
        return

    print(f"📋 Encontradas {len(pendentes)} apostas pendentes para processar.")

    processadas = 0
    ganhas = 0
    perdidas = 0
    anuladas = 0

    for aposta in pendentes:
        aposta_id = aposta['id']
        time_casa = aposta['time_casa'].strip()
        time_fora = aposta['time_fora'].strip()

        # Tentar buscar partida correspondente em fixtures_trends
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE (home_team LIKE %s OR home_team LIKE %s)
               OR (away_team LIKE %s OR away_team LIKE %s)
            ORDER BY fixture_date DESC
            LIMIT 1
        """, (f"%{time_casa}%", f"%{time_fora}%", f"%{time_casa}%", f"%{time_fora}%"))

        fixture = cursor.fetchone()

        if not fixture:
            print(f"⏳ Fixture não encontrada no banco para {time_casa} vs {time_fora}. Aposta #{aposta_id} permanece Pendente.")
            continue

        # Obter estatísticas reais de encerramento do jogo
        stats = get_fixture_stats(fixture, cursor)
        if not stats:
            status_curr = fixture.get('status', 'NS')
            print(f"⏳ Partida {time_casa} vs {time_fora} com status '{status_curr}' ou estatísticas nulas/incompletas. Aposta #{aposta_id} permanece Pendente.")
            continue

        # Avaliar Aposta
        novo_status, detalhe, valor_computado = evaluate_bet(aposta, stats)

        # Atualizar aposta no banco de dados
        cursor.execute("""
            UPDATE apostas
            SET status = %s,
                resultado_detalhado = %s,
                ganhos_potenciais = %s,
                processado_em = NOW(),
                updated_at = NOW()
            WHERE id = %s
        """, (novo_status, detalhe, valor_computado, aposta_id))

        if novo_status in ['Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida']:
            creditar_retorno_aposta(
                cursor,
                aposta.get('usuario_id'),
                aposta_id,
                valor_computado,
                novo_status,
                f"Retorno Aposta #{aposta_id} ({time_casa} x {time_fora} - {novo_status})"
            )

        processadas += 1
        if novo_status == 'Ganha':
            ganhas += 1
        elif novo_status == 'ANULADA':
            anuladas += 1
        else:
            perdidas += 1

        print(f"  ⚡ Aposta ID #{aposta_id} [{time_casa} vs {time_fora}] -> Status: {novo_status} ({detalhe})")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE APOSTAS CONCLUÍDO!")
    print(f"📊 Total Apostas Processadas: {processadas}")
    print(f"🟢 Apostas Ganhas: {ganhas}")
    print(f"⚪ Apostas Anuladas: {anuladas}")
    print(f"🔴 Apostas Perdidas: {perdidas}")
    print("=======================================================")

    # Processar liquidação dos palpites gerados pela IA
    process_palpites_gerados(cursor)

    conn.close()

def evaluate_palpite_status(home_team, away_team, goals_home, goals_away, yellow_home, yellow_away, red_home, red_away, corners_home, corners_away, mercado, linha, odd):
    """
    Avalia a acurácia de um palpite (GREEN, RED, VOID, NO_BET) comparando o mercado e a linha sugerida
    com as estatísticas reais do jogo encerrado (FT).
    """
    linha_norm = (linha or '').strip().lower()
    mercado_norm = (mercado or '').strip().lower()
    
    g_home = int(goals_home or 0)
    g_away = int(goals_away or 0)
    tot_goals = g_home + g_away
    tot_cards = int(yellow_home or 0) + int(yellow_away or 0) + int(red_home or 0) + int(red_away or 0)
    tot_corners = int(corners_home or 0) + int(corners_away or 0)

    # 1. ABSTENÇÃO / NO_BET / BLOQUEADA
    if 'sem entrada' in linha_norm or 'abstenção' in linha_norm or 'bloqueada' in linha_norm or 'no_bet' in linha_norm:
        if 'bloqueada' in linha_norm or 'xg' in linha_norm:
            return 'NO_BET', f"🚫 APOSTA BLOQUEADA: Dados de Expectativa de Gols (xG) indisponíveis para esta partida (xG = 0.00). Entrada de Handicap bloqueada para proteger a banca."
        return 'NO_BET', f"Abstenção da IA - Falta de valor/confiança (FT {g_home}x{g_away})"

    # 2. HANDICAP ASIÁTICO / DNB / EMPATE ANULA / VENCEDOR
    is_handicap_or_winner = (
        'handicap' in mercado_norm or 'handicap' in linha_norm or
        'empate anula' in linha_norm or 'empate anula' in mercado_norm or
        'dnb' in linha_norm or 'dnb' in mercado_norm or
        'ah' in linha_norm or 'ah' in mercado_norm or
        '0.0' in linha_norm or '0,0' in linha_norm or
        'vencedor' in mercado_norm or 'resultado' in mercado_norm
    )

    if is_handicap_or_winner:
        is_away_bet = False
        if away_team and away_team.lower() in linha_norm:
            is_away_bet = True
        elif 'fora' in linha_norm or 'visitante' in linha_norm or ' 2 ' in linha_norm:
            is_away_bet = True

        match_line = re.search(r'([+-]?\d+(?:[\.,]\d+)?)', linha)
        handicap_line = float(match_line.group(1).replace(',', '.')) if match_line else 0.0

        diff_gols = (g_away - g_home) if is_away_bet else (g_home - g_away)
        adj = diff_gols + handicap_line

        if adj > 0.25:
            return 'GREEN', f"FT {g_home}x{g_away} -> Palpite GANHO ({linha})"
        elif abs(adj) < 0.01:
            return 'VOID', f"FT {g_home}x{g_away} -> Empate Anulou ({linha})"
        elif abs(adj - (-0.25)) < 0.01:
            return 'RED', f"FT {g_home}x{g_away} -> Meio Perdida ({linha})"
        else:
            return 'RED', f"FT {g_home}x{g_away} -> Palpite PERDIDO ({linha})"

    # 3. TOTAL DE CARTÕES
    if 'cart' in mercado_norm or 'cart' in linha_norm:
        match_thresh = re.search(r'(\d+(?:\.\d+)?)', linha)
        thresh = float(match_thresh.group(1)) if match_thresh else 4.5
        is_under = ('menos' in linha_norm or 'under' in linha_norm)
        won = (tot_cards < thresh) if is_under else (tot_cards > thresh)
        st = 'GREEN' if won else 'RED'
        return st, f"FT {tot_cards} Cartões vs Limite {thresh} -> {st}"

    # 4. TOTAL DE GOLS
    if 'gol' in mercado_norm or 'gol' in linha_norm:
        match_thresh = re.search(r'(\d+(?:\.\d+)?)', linha)
        thresh = float(match_thresh.group(1)) if match_thresh else 2.5
        is_under = ('menos' in linha_norm or 'under' in linha_norm)
        won = (tot_goals < thresh) if is_under else (tot_goals > thresh)
        st = 'GREEN' if won else 'RED'
        return st, f"FT {tot_goals} Gols vs Limite {thresh} -> {st}"

    # FALLBACK GENÉRICO
    if g_home > g_away:
        return 'GREEN', f"FT {g_home}x{g_away} -> Mandante Venceu"
    elif g_home == g_away:
        return 'VOID', f"FT {g_home}x{g_away} -> Empate"
    else:
        return 'RED', f"FT {g_home}x{g_away} -> Visitante Venceu"

def process_palpites_gerados(cursor):
    """
    Processa e liquida os palpites gerados pela IA/plataforma na tabela palpites_gerados
    para todas as partidas encerradas (status = 'FT').
    """
    print("\n⚽ [Worker Settlement] Iniciando liquidação da tabela palpites_gerados para jogos encerrados (FT)...")
    
    # 1. Buscar todos os palpites associados a jogos que já encerraram (FT) e re-avaliar
    cursor.execute("""
        SELECT p.id_palpite, p.fixture_id, p.mercado, p.linha_sugerida, p.odd_momento,
               f.home_team, f.away_team, f.goals_home, f.goals_away,
               f.yellow_cards_home, f.yellow_cards_away, f.red_cards_home, f.red_cards_away,
               f.corners_home, f.corners_away
        FROM palpites_gerados p
        JOIN fixtures_trends f ON p.fixture_id = f.fixture_id
        WHERE f.status IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED')
    """)
    palpites = cursor.fetchall()
    
    if palpites:
        print(f"📋 Re-avaliando {len(palpites)} palpites de jogos encerrados...")
        for p in palpites:
            pid = p['id_palpite']
            home = p['home_team']
            away = p['away_team']
            status, detalhe = evaluate_palpite_status(
                home, away, p['goals_home'], p['goals_away'],
                p['yellow_cards_home'], p['yellow_cards_away'],
                p['red_cards_home'], p['red_cards_away'],
                p['corners_home'], p['corners_away'],
                p['mercado'], p['linha_sugerida'], p['odd_momento']
            )
            
            cursor.execute("""
                UPDATE palpites_gerados
                SET home_team = %s,
                    away_team = %s,
                    resultado_status = %s,
                    detalhe_resultado = %s,
                    updated_at = NOW()
                WHERE id_palpite = %s
            """, (home, away, status, detalhe, pid))

    # 2. Se houver partidas encerradas (FT) sem registro em palpites_gerados, gerar entradas automáticas
    cursor.execute("""
        SELECT f.fixture_id, f.home_team, f.away_team, f.prediction_text, f.ah_suggestion, f.over_cards_probability,
               f.odd_home, f.odd_draw, f.odd_away, f.goals_home, f.goals_away,
               f.yellow_cards_home, f.yellow_cards_away, f.red_cards_home, f.red_cards_away,
               f.corners_home, f.corners_away
        FROM fixtures_trends f
        LEFT JOIN palpites_gerados p ON f.fixture_id = p.fixture_id
        WHERE p.id_palpite IS NULL
          AND f.status IN ('FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED')
        LIMIT 300
    """)
    fixtures_sem_palpite = cursor.fetchall()
    
    if fixtures_sem_palpite:
        print(f"🌱 Gerando palpites e abstenções para {len(fixtures_sem_palpite)} partidas FT sem registro prévio...")
        for fix in fixtures_sem_palpite:
            fid = fix['fixture_id']
            home = fix['home_team']
            away = fix['away_team']
            pred = (fix.get('prediction_text') or '').strip()
            ah = (fix.get('ah_suggestion') or '').strip()
            probCards = float(fix.get('over_cards_probability') or 50.0)
            
            if not pred and not ah and 45.0 <= probCards <= 55.0:
                mercado = 'Sem Entrada'
                linha = 'Sem Entrada (Abstenção)'
                odd = None
            elif ah:
                mercado = 'Handicap Asiático'
                linha = ah
                odd = float(fix.get('odd_home') or 1.40)
            elif probCards > 55.0:
                mercado = 'Total de Cartões'
                linha = 'Over 4.5 Cartões'
                odd = 1.85
            else:
                mercado = 'Total de Gols'
                linha = 'Over 2.5 Gols'
                odd = 1.80

            status, detalhe = evaluate_palpite_status(
                home, away, fix['goals_home'], fix['goals_away'],
                fix['yellow_cards_home'], fix['yellow_cards_away'],
                fix['red_cards_home'], fix['red_cards_away'],
                fix['corners_home'], fix['corners_away'],
                mercado, linha, odd
            )

            cursor.execute("""
                INSERT INTO palpites_gerados (fixture_id, home_team, away_team, mercado, linha_sugerida, odd_momento, resultado_status, detalhe_resultado)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (fid, home, away, mercado, linha, odd, status, detalhe))

        print("✅ Liquidação de palpites concluída com sucesso!")

if __name__ == '__main__':
    process_pending_bets()


