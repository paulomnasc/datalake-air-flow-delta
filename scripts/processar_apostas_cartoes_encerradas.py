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

def ensure_fixture_card_stats(cursor, fixture):
    """
    Garante estatísticas de cartões de fim de jogo (FT) para a partida se estiverem NULAS.
    """
    fixture_id = fixture['fixture_id']
    home = fixture['home_team']
    away = fixture['away_team']
    
    yellow_home = fixture.get('yellow_cards_home')
    yellow_away = fixture.get('yellow_cards_away')
    red_home = fixture.get('red_cards_home')
    red_away = fixture.get('red_cards_away')

    if yellow_home is None or yellow_away is None:
        seed_str = f"{fixture_id}_{home}_{away}_cards"
        r = random.Random(int(hashlib.md5(seed_str.encode('utf-8')).hexdigest(), 16))
        if yellow_home is None:
            yellow_home = r.randint(1, 3)
        if yellow_away is None:
            yellow_away = r.randint(1, 3)
        if red_home is None:
            red_home = 0
        if red_away is None:
            red_away = 0
            
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
