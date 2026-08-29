#!/usr/bin/env python3
"""
Script de Processamento e Liquidação de Apostas em Handicap Asiático (Airflow DAG Worker / Web Service)
Executado diariamente para verificar jogos encerrados, comparar placares com as linhas de Handicap Asiático
e atualizar o status (Ganha, Meio Ganha, ANULADA, Meio Perdida, Perdida) e os retornos em 'apostas'.
"""

import sys
import os
import re
import pymysql
import hashlib
import random
from datetime import datetime, timedelta

def get_db_connection():
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
            print(f"✅ [DAG Liquidação AH] Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ [ERRO CRÍTICO] Falha ao conectar no MySQL.")
    sys.exit(1)

def creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor, status, descricao=None):
    """
    Credita o retorno de uma aposta resolvida/ganha/anulada na conta_corrente e atualiza usuario.saldo_conta_corrente.
    Possui checagem anti-duplicidade (idempotência) para evitar creditar a mesma aposta duas vezes.
    Apenas apostas confirmadas (confirmada = 1) possuem direito a crédito na conta corrente.
    """
    try:
        valor = float(valor or 0.0)
        if valor <= 0 or not usuario_id:
            return False

        # Checagem de segurança: Apenas apostas confirmadas (confirmada = 1) podem creditar retorno na conta corrente
        if aposta_id:
            cursor.execute("SELECT confirmada FROM apostas WHERE id = %s", (aposta_id,))
            row_aposta = cursor.fetchone()
            if row_aposta and row_aposta.get('confirmada') is not None and int(row_aposta['confirmada']) == 0:
                print(f"ℹ️ [Crédito Ignorado] Aposta #{aposta_id} não é confirmada (confirmada = 0). Saldo em conta corrente não alterado.")
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

def get_fixture_handicap_stats(fixture):
    """
    Retorna os gols reais da partida se o status for finalizado (FT).
    Retorna None se os gols ou o status estiverem incompletos no banco.
    """
    status = (fixture.get('status') or '').strip().upper()
    finished_statuses = ['FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED']
    if status not in finished_statuses:
        return None

    goals_home = fixture.get('goals_home')
    goals_away = fixture.get('goals_away')

    if goals_home is None or goals_away is None:
        return None

    return {
        'status': status,
        'goals_home': goals_home,
        'goals_away': goals_away
    }

def evaluate_asian_handicap_bet(aposta, goals_home, goals_away):
    """
    Calcula o resultado da aposta no mercado de Handicap Asiático com base no placar oficial do jogo (FT).
    Retorna tupla: (novo_status, ganhos_potenciais, detalhe)
    """
    palpite = aposta.get('palpite', '').strip()
    time_casa = aposta.get('time_casa', '').strip()
    time_fora = aposta.get('time_fora', '').strip()
    valor_aposta = float(aposta.get('valor_aposta', 10.0) or 10.0)
    odd = float(aposta.get('odd', 1.60) or 1.60)

    palpite_norm = palpite.lower()

    # Identifica se a aposta é no Visitante ou no Mandante
    is_away_bet = False
    if time_fora and time_fora.lower() in palpite_norm:
        is_away_bet = True
    elif 'fora' in palpite_norm or 'visitante' in palpite_norm or ' 2 ' in palpite_norm:
        is_away_bet = True

    # Extrai o valor numérico da linha de Handicap (ex: -0.25, +0.25, -0.5, +0.5, -0.75, +0.75, -1.0, +1.0, 0.0)
    line = 0.0
    match_line = re.search(r'([+-]?\d+(?:[\.,]\d+)?)', palpite)
    if match_line:
        try:
            line = float(match_line.group(1).replace(',', '.'))
        except Exception:
            line = 0.0

    # Diferença de gols a partir da perspectiva da equipe apostada
    if is_away_bet:
        diff_gols = goals_away - goals_home
        team_bet = time_fora or "Visitante"
    else:
        diff_gols = goals_home - goals_away
        team_bet = time_casa or "Mandante"

    # Saldo ajustado do Handicap
    adj = diff_gols + line

    if adj > 0.25:
        # Aposta GANHA (100% Lucro + 100% Stake)
        payout = valor_aposta * odd
        detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> GANHA (Retorno R$ {payout:.2f})"
        return 'Ganha', round(payout, 2), detalhe

    elif abs(adj - 0.25) < 0.01:
        # MEIO GANHA (50% Lucro + 100% Stake)
        payout = valor_aposta * ((odd + 1.0) / 2.0)
        detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> MEIO GANHA (Retorno R$ {payout:.2f})"
        return 'Meio Ganha', round(payout, 2), detalhe

    elif abs(adj) < 0.01:
        # ANULADA / EMPATE ANULA (100% Reembolso da Stake)
        payout = valor_aposta
        detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> ANULADA (Reembolso R$ {payout:.2f})"
        return 'ANULADA', round(payout, 2), detalhe

    elif abs(adj - (-0.25)) < 0.01:
        # MEIO PERDIDA (50% Stake de volta + 50% Perdida)
        payout = valor_aposta * 0.5
        detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> MEIO PERDIDA (Retorno R$ {payout:.2f})"
        return 'Meio Perdida', round(payout, 2), detalhe

    else:
        # PERDIDA (100% Perdida)
        payout = 0.0
        detalhe = f"FT | Placar: {goals_home}x{goals_away} | Palpite: {team_bet} ({line:+.2f} AH) -> PERDIDA"
        return 'Perdida', 0.0, detalhe

def processar_apostas_handicap_encerradas():
    """
    Busca todas as apostas pendentes no mercado de Handicap Asiático e realiza a liquidação com base no placar FT.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    print("🔍 [DAG Liquidação AH] Buscando apostas pendentes no mercado de Handicap Asiático...")

    cursor.execute("""
        SELECT a.* 
        FROM apostas a 
        WHERE a.status = 'Pendente'
          AND (
              a.mercado LIKE '%Handicap%' 
              OR a.mercado IN ('Handicap Asiático', 'Empate Anula', 'DNB')
              OR a.palpite LIKE '%AH%'
          )
    """)
    pendentes = cursor.fetchall()

    if not pendentes:
        print("ℹ️ Nenhuma aposta pendente de Handicap Asiático encontrada para processamento.")
        conn.close()
        return

    print(f"📋 Encontradas {len(pendentes)} apostas de Handicap Asiático pendentes.")

    processadas = 0
    ganhas = 0
    meio_ganhas = 0
    anuladas = 0
    meio_perdidas = 0
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

        finished_statuses = ['FT', 'AET', 'PEN', 'FINISHED', 'MATCH FINISHED']

        # Se a partida ainda não foi encerrada (e menos de 110 min se passaram), pula
        if status_fix not in finished_statuses:
            if fixture_date and (fixture_date + timedelta(minutes=110)) > now:
                print(f"⏳ Partida {time_casa} vs {time_fora} ainda em andamento (status '{status_fix}'). Aposta #{aposta_id} permanece Pendente.")
                continue

        stats = get_fixture_handicap_stats(fixture)
        if not stats:
            print(f"⏳ Partida {time_casa} vs {time_fora} sem dados finais encerrados de placar. Aposta #{aposta_id} permanece Pendente.")
            continue
        goals_home = stats['goals_home']
        goals_away = stats['goals_away']

        novo_status, valor_computado, detalhe = evaluate_asian_handicap_bet(aposta, goals_home, goals_away)

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

        if novo_status in ['Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida']:
            is_confirmada = (int(aposta.get('confirmada') or 0) == 1) if ('confirmada' in aposta and aposta.get('confirmada') is not None) else True
            if is_confirmada:
                creditar_retorno_aposta(
                    cursor,
                    aposta.get('usuario_id'),
                    aposta_id,
                    valor_computado,
                    novo_status,
                    f"Retorno Aposta #{aposta_id} ({time_casa} x {time_fora} - {novo_status})"
                )
            else:
                print(f"ℹ️ [Crédito Ignorado] Aposta #{aposta_id} encerrada como '{novo_status}', mas não creditada pois não está confirmada (confirmada = 0).")

        processadas += 1
        if novo_status == 'Ganha':
            ganhas += 1
        elif novo_status == 'Meio Ganha':
            meio_ganhas += 1
        elif novo_status == 'ANULADA':
            anuladas += 1
        elif novo_status == 'Meio Perdida':
            meio_perdidas += 1
        else:
            perdidas += 1

        print(f"⚡ Aposta ID #{aposta_id} [{time_casa} vs {time_fora}] -> {novo_status} ({detalhe})")

    print("\n=======================================================")
    print(f"✅ LIQUIDAÇÃO DE APOSTAS HANDICAP ASIÁTICO CONCLUÍDA!")
    print(f"📊 Total Apostas Processadas: {processadas}")
    print(f"🟢 Ganhas: {ganhas}")
    print(f"🟡 Meio Ganhas: {meio_ganhas}")
    print(f"⚪ Anuladas (Reembolso): {anuladas}")
    print(f"🟠 Meio Perdidas: {meio_perdidas}")
    print(f"🔴 Perdidas: {perdidas}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    processar_apostas_handicap_encerradas()
