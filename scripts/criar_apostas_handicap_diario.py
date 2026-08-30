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
    Retorna lista de IDs contendo exclusivamente o usuário 'paulomnasc'.
    """
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE email LIKE '%paulomnasc%' OR nome LIKE '%paulomnasc%' OR id = 558
        ORDER BY id ASC
        LIMIT 1
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]
    
    cursor.execute("SELECT id FROM usuario ORDER BY id ASC LIMIT 1")
    first_user = cursor.fetchone()
    if first_user:
        return [first_user['id']]

    print("ℹ️ Criando usuário paulomnasc no sistema para vinculação de apostas...")
    cursor.execute("""
        INSERT INTO usuario (nome, email, senha, email_confirmado, criado_em)
        VALUES ('Paulo Nascimento', 'paulomnasc@gmail.com', '123456', 1, NOW())
    """)
    return [cursor.lastrowid]

def determine_bet_side(home_team: str, away_team: str, ah_suggestion: str) -> bool:
    """
    Retorna True se a aposta for no time visitante (away), False se for no mandante (home).
    Trata casos de times com números no nome (ex: 1. FC Köln, Mainz 05, Schalke 04).
    """
    home_low = home_team.lower().strip()
    away_low = away_team.lower().strip()
    ah_low = ah_suggestion.lower().strip()

    # 1. Checagem direta por prefixo
    if ah_low.startswith(away_low):
        return True
    if ah_low.startswith(home_low):
        return False

    # 2. Checagem de substrings exclusivas
    in_away = away_low in ah_low
    in_home = home_low in ah_low
    if in_away and not in_home:
        return True
    if in_home and not in_away:
        return False

    # 3. Termos genéricos 'fora' / 'visitante' vs 'casa' / 'mandante'
    if 'fora' in ah_low or 'visitante' in ah_low:
        return True
    if 'casa' in ah_low or 'mandante' in ah_low:
        return False

    # 4. Checagem por palavras significativas (>3 chars)
    away_words = [w for w in re.findall(r'[a-zA-Z0-9]+', away_low) if len(w) >= 4]
    home_words = [w for w in re.findall(r'[a-zA-Z0-9]+', home_low) if len(w) >= 4]
    away_matches = sum(1 for w in away_words if w in ah_low)
    home_matches = sum(1 for w in home_words if w in ah_low)

    if away_matches > home_matches:
        return True
    if home_matches > away_matches:
        return False

    return False

def is_allowed_league(league_id, league_name: str) -> bool:
    """
    Filtra o escopo de atuação do script de criação de apostas:
    - Campeonatos do Brasil (Série A, Série B, Copa do Brasil, Paulistão, etc. - Série C e Série D excluídas)
    - Internacional: CONMEBOL Libertadores, CONMEBOL Sudamericana, La Liga, Superliga (#119), Pro League (#307), Serie A Itália (#135), Super League 1 (#197), etc.
    - Desconsidera jogos femininos (Women / Feminino) e jogos do Brasil Série C e Série D.
    """
    l_name_low = (league_name or '').lower().strip()
    if 'women' in l_name_low or 'feminino' in l_name_low or 'femenina' in l_name_low:
        return False

    # Exclusão explícita do Brasil Série C e Série D (por nome)
    if any(s in l_name_low for s in ['serie c', 'série c', 'serie d', 'série d']):
        return False

    l_id = None
    if league_id is not None:
        try:
            l_id = int(league_id)
        except (ValueError, TypeError):
            pass

    # Exclusão explícita por ID do Brasil Série C (ID 75) e Série D (ID 76)
    if l_id in {75, 76}:
        return False

    # IDs Conhecidos da API-Football (Brasil, Libertadores, Sudamericana, La Liga, EFL Cup, UEFA Conference/Europa, Superliga #119, Pro League #307, Serie A #135, Super League 1 #197)
    ALLOWED_LEAGUE_IDS = {71, 72, 73, 13, 11, 140, 48, 848, 3, 119, 307, 135, 197}
    if l_id in ALLOWED_LEAGUE_IDS:
        return True

    # Checagem por Nome de Liga Internacional Permitida
    allowed_int_keywords = [
        'libertadores', 'sudamericana', 'sul-americana', 'sul americana',
        'la liga', 'laliga',
        'league cup', 'efl cup', 'carabao cup', 'efl',
        'conference league', 'europa conference league', 'uefa conference league', 'uefa europa conference league',
        'europa league', 'uefa europa league',
        'superliga', 'pro league', 'super league'
    ]
    if any(k in l_name_low for k in allowed_int_keywords):
        return True

    # Checagem por Nome de Liga Brasileira
    brazil_keywords = [
        'brasil', 'brasileiro', 'brasileira', 'copa do brasil', 
        'serie a', 'serie b', 
        'paulista', 'carioca', 'gaúcho', 'gaucho', 'mineiro', 
        'baiano', 'pernambucano', 'cearense', 'paranaense', 'catarinense'
    ]
    if any(kw in l_name_low for kw in brazil_keywords):
        if l_name_low in ['serie a', 'serie b'] and l_id not in {71, 72, 135}:
            return False
        return True

    return False

def criar_apostas_handicap_diario(target_date_str=None, confirmada=1):
    """
    Busca os jogos em aberto na janela pré-jogo iminente (30 a 45 min antes da partida)
    e cria apostas no mercado de Handicap Asiático para todos os usuários.
    Se target_date_str for especificado (ex: '2026-08-14', 'all' ou '2026-08-24,2026-08-25'), filtra pelas datas especificadas.
    O parâmetro confirmada (default=1) define se a aposta é confirmada (com impacto em conta corrente) ou simulada (0).
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    is_prematch_window = False
    confirmada_val = int(confirmada) if confirmada is not None else 1

    if not target_date_str or target_date_str.lower() in ('prematch', 'pre-match'):
        is_prematch_window = True
        date_desc = "janela pré-jogo (30 a 45 minutos antes do início)"
    elif target_date_str.lower() == 'all':
        today_dt = datetime.now()
        tomorrow_dt = today_dt + timedelta(days=1)
        target_dates = [today_dt.strftime('%Y-%m-%d'), tomorrow_dt.strftime('%Y-%m-%d')]
        date_desc = f"todas as partidas em aberto das datas {target_dates[0]} e {target_dates[1]}"
    else:
        target_dates = [d.strip() for d in target_date_str.split(',') if d.strip()]
        date_desc = f"datas {', '.join(target_dates)}"

    print(f"🚀 [DAG Criar Apostas AH] Iniciando verificação de jogos para {date_desc} (Confirmada={confirmada_val})...")

    user_ids = get_all_user_ids(cursor)
    print(f"👥 Usuários identificados: {user_ids}")

    # 1. Buscar partidas em aberto
    if is_prematch_window:
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
              AND fixture_date <= DATE_ADD(NOW(), INTERVAL 45 MINUTE)
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
            ORDER BY fixture_date ASC
        """)
    else:
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

    print(f"📋 Encontradas {len(fixtures)} partidas selecionadas.")

    apostas_criadas = 0
    apostas_duplicadas = 0
    apostas_abstenção = 0

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']
        league_id = fix.get('league_id')
        league_name = fix.get('league_name') or ''

        # Filtro Estrito de Escopo: Apenas campeonatos do Brasil e Internacional (Libertadores e Sul-Americana)
        if not is_allowed_league(league_id, league_name):
            print(f"🌍 [Fora do Escopo] Partida {home_team} vs {away_team} ({league_name} ID #{league_id}) ignorada. Escopo restrito a Brasil, Libertadores e Sul-Americana.")
            continue

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

        # Determinar com exatidão se o palpite é no time Visitante ou Mandante para escolher a Odd correta
        is_away = determine_bet_side(home_team, away_team, ah_suggestion)

        # Regra de Ajuste: Migração de linha para Visitantes (Fora de Casa)
        # Em vez de 0.0 ou -0.25 puro em visitantes, migra para cobertura +0.25 AH (garantindo meio-green no empate)
        if is_away:
            if '0.0' in ah_suggestion or 'empate anula' in ah_suggestion.lower() or '-0.25' in ah_suggestion:
                ah_suggestion = f"{away_team} +0.25 AH"

        raw_odd = fix.get('odd_away') if is_away else fix.get('odd_home')
        if not raw_odd or float(raw_odd) <= 1.0 or not fix.get('odd_home') or not fix.get('odd_away'):
            print(f"🛡️ [Sem Odds Reais] Partida {home_team} vs {away_team} -> Odds ausentes ou inválidas no mercado. Aposta não criada.")
            apostas_abstenção += 1
            continue

        odd_val = float(raw_odd)
        if odd_val < 1.50:
            print(f"🛡️ [Odd Baixa < 1.50] Partida {home_team} vs {away_team} -> Odd {odd_val:.2f} é inferior ao mínimo permitido (1.50). Aposta não criada.")
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
                cursor.execute("""
                    UPDATE apostas SET
                        palpite = %s,
                        odd = %s,
                        ganhos_potenciais = %s,
                        resultado_detalhado = %s,
                        updated_at = NOW()
                    WHERE id = %s AND status = 'Pendente'
                """, (ah_suggestion, odd_val, ganhos_potenciais, (fix.get('ah_reasoning') or '')[:250], ja_existe['id']))
                apostas_duplicadas += 1
                continue

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    valor_aposta, ganhos_potenciais, status_gatekeeper, status, confirmada, data_hora_jogo, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Handicap Asiático', %s, %s,
                    %s, %s, 'APROVADO', 'Pendente', %s, %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, ah_suggestion, odd_val,
                valor_aposta, ganhos_potenciais, confirmada_val, fixture_date
            ))

            apostas_criadas += 1
            print(f"🟢 [Aposta Criada User #{uid}] ID #{cursor.lastrowid} | {home_team} vs {away_team} | Palpite: '{ah_suggestion}' @ Odd {odd_val:.2f} | Confirmada={confirmada_val}")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE CRIAÇÃO DE APOSTAS AH CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🔄 Apostas Já Existentes (Ignoradas): {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/Bloqueio: {apostas_abstenção}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    confirmada_arg = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    criar_apostas_handicap_diario(target_date, confirmada_arg)
