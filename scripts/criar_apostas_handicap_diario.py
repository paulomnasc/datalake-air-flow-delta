#!/usr/bin/env python3
"""
Script de Criação Diária de Apostas de Handicap Asiático (Airflow DAG Worker / Web Service)
Executado diariamente para verificar jogos em aberto do dia corrente (fuso horário local Brasil -03:00)
e criar apostas na tabela 'apostas' para todos os usuários com base na sugestão de Handicap Asiático.
"""

import sys
import os
import re
import pymysql
from datetime import datetime, timedelta

def get_db_connection():
    """
    Obtém conexão com o MySQL (tenta docker internal 'mysql' e localhost fallback).
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
            print(f"✅ [DAG Criar Apostas AH] Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ [ERRO CRÍTICO] Falha ao conectar em qualquer porta do MySQL.")
    sys.exit(1)

def get_all_user_ids(cursor):
    """
    Retorna lista de IDs de todos os usuários cadastrados na tabela 'usuario'.
    """
    cursor.execute("SELECT id FROM usuario ORDER BY id ASC")
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]
    
    print("ℹ️ Criando usuário padrão no sistema para vinculação de apostas...")
    cursor.execute("""
        INSERT INTO usuario (nome, email, senha, email_confirmado, criado_em)
        VALUES ('Sistema Automação', 'sistema@footballweb.com', '123456', 1, NOW())
    """)
    return [cursor.lastrowid]

def criar_apostas_handicap_diario(target_date_str=None):
    """
    Busca os jogos em aberto do dia corrente (fuso horário local -03:00)
    e cria apostas no mercado de Handicap Asiático para todos os usuários.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    if not target_date_str:
        today_dt = datetime.now()
        tomorrow_dt = today_dt + timedelta(days=1)
        target_dates = [today_dt.strftime('%Y-%m-%d'), tomorrow_dt.strftime('%Y-%m-%d')]
        date_desc = f"datas {target_dates[0]} e {target_dates[1]}"
    else:
        target_dates = [target_date_str]
        date_desc = f"data {target_date_str}"

    print(f"🚀 [DAG Criar Apostas AH] Iniciando verificação de jogos em aberto para {date_desc} (Fuso -03:00)...")

    user_ids = get_all_user_ids(cursor)
    print(f"👥 Usuários identificados: {user_ids}")

    # 1. Buscar partidas em aberto das datas no fuso horário do Brasil (-03:00)
    placeholders = ', '.join(['%s'] * len(target_dates))
    cursor.execute(f"""
        SELECT * FROM fixtures_trends
        WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) IN ({placeholders})
          AND status NOT IN ('PST', 'CANCELLED', 'POSTPONED')
        ORDER BY fixture_date ASC
    """, tuple(target_dates))
    
    fixtures = cursor.fetchall()

    if not fixtures:
        print(f"ℹ️ Nenhuma partida em aberto encontrada para {date_desc}.")
        conn.close()
        return

    print(f"📋 Encontradas {len(fixtures)} partidas em aberto para o dia {target_date_str}.")

    apostas_criadas = 0
    apostas_duplicadas = 0
    apostas_abstenção = 0

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']

        ah_suggestion = (fix.get('ah_suggestion') or '').strip()

        if not ah_suggestion:
            print(f"⚠️ Sem ah_suggestion prévia para {home_team} vs {away_team} (ID #{fixture_id}). Ignorando...")
            continue

        ah_norm = ah_suggestion.lower()

        # Filtrar abstenções, bloqueios de risco e 'Sem Entrada'
        if any(term in ah_norm for term in ['sem entrada', 'abstenção', 'abstencao', 'bloqueada', 'no_bet', 'indisponível', 'indisponivel']):
            print(f"🛡️ [Abstenção] Partida {home_team} vs {away_team} -> Sugestão: '{ah_suggestion}'. Aposta não criada.")
            apostas_abstenção += 1
            continue

        # Determinar se o palpite é no time Visitante ou Mandante para escolher a Odd correta
        is_away = False
        if away_team.lower() in ah_norm or 'fora' in ah_norm or 'visitante' in ah_norm:
            is_away = True

        if is_away:
            odd_val = float(fix.get('odd_away') or 1.60)
        else:
            odd_val = float(fix.get('odd_home') or 1.60)

        if odd_val <= 1.0:
            odd_val = 1.60

        # Validação de Risco: Não cria aposta automática se a odd for inferior ao mínimo de 1.60
        if odd_val < 1.60:
            print(f"🛡️ [Odd Baixa < 1.60] Partida {home_team} vs {away_team} -> Odd {odd_val:.2f} é inferior ao mínimo permitido (1.60). Aposta não criada.")
            apostas_abstenção += 1
            continue

        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

        # Inserir aposta para cada usuário cadastrado (com checagem de idempotência por usuário)
        for uid in user_ids:
            cursor.execute("""
                SELECT id FROM apostas 
                WHERE fixture_id = %s AND usuario_id = %s AND mercado = 'Handicap Asiático'
            """, (fixture_id, uid))
            ja_existe = cursor.fetchone()

            if ja_existe:
                apostas_duplicadas += 1
                continue

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    valor_aposta, ganhos_potenciais, status_gatekeeper, status, data_hora_jogo, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Handicap Asiático', %s, %s,
                    %s, %s, 'APROVADO', 'Pendente', %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, ah_suggestion, odd_val,
                valor_aposta, ganhos_potenciais, fixture_date
            ))

            apostas_criadas += 1
            print(f"🟢 [Aposta Criada User #{uid}] ID #{cursor.lastrowid} | {home_team} vs {away_team} | Palpite: '{ah_suggestion}' @ Odd {odd_val:.2f}")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE CRIAÇÃO DE APOSTAS AH CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🔄 Apostas Já Existentes (Ignoradas): {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/Bloqueio: {apostas_abstenção}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    criar_apostas_handicap_diario(target_date)
