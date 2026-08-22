#!/usr/bin/env python3
"""
Script de Processamento e Liquidação de Apostas no Mercado de Cartões Under (Airflow DAG Worker / Web Service)
Executado periodicamente para verificar jogos encerrados, comparar estatísticas de cartões com as linhas Under apostadas
e atualizar o status (Ganha, Perdida) e os retornos na tabela 'apostas'.
"""

import sys
import os
import re
import pymysql
import hashlib
import random
from datetime import datetime, timedelta

def get_db_connection():
    """
    Obtém conexão com o MySQL (tenta docker internal 'mysql', 127.0.0.1:23306 e localhost fallback).
    """
    hosts_ports = [
        ("mysql", 3306),
        ("127.0.0.1", 23306),
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
                autocommit=True
            )
            print(f"✅ [DAG Liquidação Cartões] Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ [ERRO CRÍTICO] Falha ao conectar no MySQL.")
    sys.exit(1)

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

                # Se houver cartões > 0 ou eventos salvos no campo last_event, confia no banco.
                if (yh + ya + rh + ra) > 0 or (last_ev is not None and last_ev != ''):
                    return (yh, ya, rh, ra)

                # TRAVA DE COOLDOWN DE COTA DA API-SPORTS:
                # Se já consultou a API nas últimas 6 horas e a partida não retornou dados, pula requisição HTTP
                if checked_at and isinstance(checked_at, datetime):
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

def ensure_fixture_card_stats(cursor, fixture):
    """
    Garante estatísticas de cartões de fim de jogo (FT) para a partida consultando a API se estiverem NULAS.
    """
    fixture_id = fixture['fixture_id']
    home_team_id = fixture.get('home_team_id')

    yellow_home = fixture.get('yellow_cards_home')
    yellow_away = fixture.get('yellow_cards_away')
    red_home = fixture.get('red_cards_home')
    red_away = fixture.get('red_cards_away')

    # Se for NULO ou se for ZERO sem eventos confirmados em last_event, consulta a API de eventos
    if yellow_home is None or yellow_away is None or (yellow_home == 0 and yellow_away == 0 and not fixture.get('last_event')):
        print(f"📡 Verificando/buscando cartões na API-Sports para partida #{fixture_id}...")
        cards_res = fetch_real_fixture_cards_api(fixture_id, home_team_id, cursor=cursor)
        if cards_res is not None:
            yh, ya, rh, ra = cards_res
        else:
            yh, ya, rh, ra = None, None, None, None

        if yh is not None:
            yellow_home, yellow_away, red_home, red_away = yh, ya, rh, ra
            cursor.execute("""
                UPDATE fixtures_trends
                SET status = 'FT',
                    yellow_cards_home = %s,
                    yellow_cards_away = %s,
                    red_cards_home = %s,
                    red_cards_away = %s,
                    updated_at = NOW()
                WHERE fixture_id = %s
            """, (yellow_home, yellow_away, red_home, red_away, fixture_id))
    else:
        red_home = red_home or 0
        red_away = red_away or 0

    total_cards = yellow_home + yellow_away + red_home + red_away

    return {
        'status': 'FT',
        'yellow_cards_home': yellow_home,
        'yellow_cards_away': yellow_away,
        'red_cards_home': red_home,
        'red_cards_away': red_away,
        'total_cards': total_cards
    }

def evaluate_cards_under_bet(aposta, total_cards, yellow_home, yellow_away, red_home, red_away):
    """
    Avalia a aposta de Cartões Under comparando o total de cartões do jogo com o limite do palpite.
    Retorna tupla: (novo_status: 'Ganha'|'Perdida', payout: float, detalhe: str)
    """
    palpite = aposta.get('palpite', '').strip()
    valor_aposta = float(aposta.get('valor_aposta', 10.0) or 10.0)
    odd = float(aposta.get('odd', 1.80) or 1.80)

    # Extrai o limite numérico do palpite (ex: 5.5 a partir de "Menos de 5.5 Cartões" ou "Under 5.5")
    match_line = re.search(r'(\d+(?:\.\d+)?)', palpite)
    threshold = float(match_line.group(1)) if match_line else 5.5

    # Validação Under: Aposta GANHA se total_cards < threshold
    if total_cards < threshold:
        payout = round(valor_aposta * odd, 2)
        detalhe = f"FT | Total Cartões: {total_cards} ({yellow_home}+{yellow_away} Amarelos, {red_home}+{red_away} Vermelhos) < Limite {threshold} -> GANHA (Retorno R$ {payout:.2f})"
        return 'Ganha', payout, detalhe
    else:
        payout = 0.0
        detalhe = f"FT | Total Cartões: {total_cards} ({yellow_home}+{yellow_away} Amarelos, {red_home}+{red_away} Vermelhos) >= Limite {threshold} -> PERDIDA"
        return 'Perdida', 0.0, detalhe

def processar_apostas_cartoes_encerradas():
    """
    Busca todas as apostas pendentes no mercado de Total de Cartões e realiza a liquidação com base no placar FT.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    print("🔍 [DAG Liquidação Cartões] Buscando apostas pendentes no mercado de Cartões...")

    cursor.execute("""
        SELECT a.* 
        FROM apostas a 
        WHERE a.status = 'Pendente'
          AND (
              a.mercado LIKE '%Cartõ%' 
              OR a.mercado LIKE '%Card%' 
              OR a.mercado = 'Total de Cartões'
              OR a.palpite LIKE '%Cartões%'
          )
    """)
    pendentes = cursor.fetchall()

    if not pendentes:
        print("ℹ️ Nenhuma aposta pendente no mercado de Cartões encontrada para processamento.")
        conn.close()
        return

    print(f"📋 Encontradas {len(pendentes)} apostas de Cartões pendentes.")

    processadas = 0
    ganhas = 0
    perdidas = 0

    for aposta in pendentes:
        aposta_id = aposta['id']
        time_casa = aposta['time_casa'].strip()
        time_fora = aposta['time_fora'].strip()
        fixture_id = aposta.get('fixture_id')

        # Buscar partida em fixtures_trends
        fixture = None
        if fixture_id:
            cursor.execute("SELECT * FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
            fixture = cursor.fetchone()

        if not fixture:
            cursor.execute("""
                SELECT * FROM fixtures_trends
                WHERE (home_team LIKE %s OR home_team LIKE %s)
                   OR (away_team LIKE %s OR away_team LIKE %s)
                ORDER BY fixture_date DESC
                LIMIT 1
            """, (f"%{time_casa}%", f"%{time_fora}%", f"%{time_casa}%", f"%{time_fora}%"))
            fixture = cursor.fetchone()

        if not fixture:
            print(f"⚠️ Partida não encontrada para aposta #{aposta_id} [{time_casa} vs {time_fora}]. Ignorando por enquanto...")
            continue

        status_fix = fixture.get('status')
        fixture_date = fixture.get('fixture_date')
        now = datetime.now()

        # Se a partida ainda não foi encerrada (e menos de 110 min se passaram), pula
        if status_fix != 'FT':
            if fixture_date and (fixture_date + timedelta(minutes=110)) > now:
                print(f"⏳ Partida {time_casa} vs {time_fora} ainda em andamento. Aposta #{aposta_id} permanece Pendente.")
                continue

        stats = ensure_fixture_card_stats(cursor, fixture)
        total_cards = stats['total_cards']
        yh = stats['yellow_cards_home']
        ya = stats['yellow_cards_away']
        rh = stats['red_cards_home']
        ra = stats['red_cards_away']

        novo_status, valor_computado, detalhe = evaluate_cards_under_bet(aposta, total_cards, yh, ya, rh, ra)

        # Atualiza aposta no banco de dados
        cursor.execute("""
            UPDATE apostas
            SET status = %s,
                resultado_detalhado = %s,
                ganhos_potenciais = %s,
                processado_em = NOW(),
                updated_at = NOW()
            WHERE id = %s
        """, (novo_status, detalhe, valor_computado, aposta_id))

        processadas += 1
        if novo_status == 'Ganha':
            ganhas += 1
        else:
            perdidas += 1

        print(f"⚡ Aposta Cartões ID #{aposta_id} [{time_casa} vs {time_fora}] -> {novo_status} ({detalhe})")

    print("\n=======================================================")
    print(f"✅ LIQUIDAÇÃO DE APOSTAS CARTÕES UNDER CONCLUÍDA!")
    print(f"📊 Total Apostas Processadas: {processadas}")
    print(f"🟢 Ganhas: {ganhas}")
    print(f"🔴 Perdidas: {perdidas}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    processar_apostas_cartoes_encerradas()
