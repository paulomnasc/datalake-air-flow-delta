#!/usr/bin/env python3
"""
Script de Criação Diária de Apostas no Mercado de Cartões Under (Airflow DAG Worker / Web Service)
Executado periodicamente para verificar jogos em aberto da janela pré-jogo (fuso horário local Brasil -03:00)
e criar apostas na tabela 'apostas' no mercado de 'Total de Cartões' (Estratégia Exclusiva Under).
"""

import sys
import os
import re
import math
import pymysql
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
            print(f"✅ [DAG Criar Apostas Cartões] Conectado ao MySQL ({host}:{port})")
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

def is_allowed_league(league_id, league_name: str) -> bool:
    """
    Filtra o escopo de atuação do script de criação de apostas:
    - Campeonatos do Brasil (Série A, Série B, Série C, Série D, Copa do Brasil, Paulistão, etc.)
    - Internacional: Apenas CONMEBOL Libertadores e CONMEBOL Sudamericana.
    - Desconsidera jogos femininos (Women / Feminino)
    """
    l_name_low = (league_name or '').lower().strip()
    if 'women' in l_name_low or 'feminino' in l_name_low or 'femenina' in l_name_low:
        return False

    l_id = None
    if league_id is not None:
        try:
            l_id = int(league_id)
        except (ValueError, TypeError):
            pass

    # IDs Conhecidos da API-Football (Brasil & Libertadores/Sudamericana)
    ALLOWED_LEAGUE_IDS = {71, 72, 73, 74, 75, 76, 13, 11}
    if l_id in ALLOWED_LEAGUE_IDS:
        return True

    l_name_low = (league_name or '').lower().strip()

    # Checagem por Nome de Liga Internacional Permitida
    if any(k in l_name_low for k in ['libertadores', 'sudamericana', 'sul-americana', 'sul americana']):
        return True

    # Checagem por Nome de Liga Brasileira
    brazil_keywords = [
        'brasil', 'brasileiro', 'brasileira', 'copa do brasil', 
        'serie a', 'serie b', 'serie c', 'serie d', 
        'paulista', 'carioca', 'gaúcho', 'gaucho', 'mineiro', 
        'baiano', 'pernambucano', 'cearense', 'paranaense', 'catarinense'
    ]
    if any(kw in l_name_low for kw in brazil_keywords):
        if l_name_low in ['serie a', 'serie b', 'serie c', 'serie d'] and l_id not in {71, 72, 75, 76}:
            return False
        return True

    return False

def calculate_poisson_under_cdf(xc: float, line: float) -> float:
    """
    Calcula a probabilidade acumulada de ocorrência de Under 'line' cartões (P(X <= k))
    assumindo Distribuição de Poisson com parâmetro lambda = xc.
    """
    if xc <= 0:
        return 100.0
    k_max = int(math.floor(line))
    prob_sum = 0.0
    for k in range(k_max + 1):
        prob_sum += (math.exp(-xc) * (xc ** k)) / math.factorial(k)
    return round(min(100.0, max(0.0, prob_sum * 100.0)), 2)

def extract_cards_under_suggestion(prediction_text: str):
    """
    Extrai a linha de cartões Under sugerida a partir do prediction_text de fixtures_trends.
    Retorna tupla: (line_float, palpite_str, status_gatekeeper, odd_justa, prob_poisson, ev_perc, exp_cards)
    """
    if not prediction_text:
        return None, None, 'NO_BET', None, None, None, None

    pred_low = prediction_text.lower()

    # 1. Trava de Abstenção / NO_BET expressa
    if any(term in pred_low for term in ['no_bet', 'sem entrada', 'abstenção', 'abstencao', 'bloqueada', 'indisponível', 'indisponivel']):
        return None, None, 'NO_BET', None, None, None, None

    # 2. Extrai expectativa matemática de cartões (xC)
    match_xc = re.search(r'xC(?::|\s+elevado)?\s*\(?(\d+\.\d+|\d+)', prediction_text, re.IGNORECASE)
    exp_cards = float(match_xc.group(1)) if match_xc else None

    # Se xC for muito alto (> 4.80), força NO_BET conforme regra estatística de segurança
    if exp_cards is not None and exp_cards > 4.80:
        return None, None, 'NO_BET', None, None, None, exp_cards

    # 3. Procura por sugestões do tipo: "1ª Opção: Under 5.5 (86.37% | Odd Justa: 1.16)"
    # Ou termos genéricos "Under X.5" ou "Menos de X.5"
    line_val = 5.5 # Default fallback safe line

    match_under_op1 = re.search(r'1ª\s*Opção:\s*Under\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)
    if match_under_op1:
        line_val = float(match_under_op1.group(1))
    else:
        match_under_gen = re.search(r'Under\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)
        if match_under_gen:
            line_val = float(match_under_gen.group(1))
        else:
            match_menos = re.search(r'menos\s+de\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)
            if match_menos:
                line_val = float(match_menos.group(1))

    # 4. Cálculo de Probabilidade Poisson & Odd Justa
    if exp_cards is None or exp_cards <= 0:
        exp_cards = 3.50 # baseline seguro

    prob_poisson = calculate_poisson_under_cdf(exp_cards, line_val)
    odd_justa = round(100.0 / prob_poisson, 2) if prob_poisson > 0 else 99.00

    # 5. Avaliação do Gatekeeper (Regra de Segurança Under)
    # xC <= 4.80 e Probabilidade Poisson >= 60%
    if exp_cards <= 4.80 and prob_poisson >= 60.0:
        status_gk = 'APROVADO'
    else:
        status_gk = 'NO_BET'

    palpite_str = f"Menos de {line_val} Cartões"

    return line_val, palpite_str, status_gk, odd_justa, prob_poisson, None, exp_cards

def criar_apostas_cartoes_diario(target_date_str=None):
    """
    Busca os jogos em aberto na janela pré-jogo (ou data especificada)
    e cria apostas no mercado 'Total de Cartões' (Estratégia Under) para todos os usuários.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    is_prematch_window = False

    if not target_date_str or target_date_str.lower() in ('prematch', 'pre-match'):
        is_prematch_window = True
        date_desc = "janela pré-jogo (30 a 45 minutos antes do início)"
    elif target_date_str.lower() == 'all':
        today_dt = datetime.now()
        tomorrow_dt = today_dt + timedelta(days=1)
        target_dates = [today_dt.strftime('%Y-%m-%d'), tomorrow_dt.strftime('%Y-%m-%d')]
        date_desc = f"todas as partidas em aberto das datas {target_dates[0]} e {target_dates[1]}"
    else:
        target_dates = [target_date_str]
        date_desc = f"data {target_date_str}"

    print(f"🚀 [DAG Criar Apostas Cartões Under] Iniciando verificação de jogos para {date_desc}...")

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
    apostas_abstencao = 0

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

        prediction_text = (fix.get('prediction_text') or '').strip()

        line_val, palpite_str, status_gk, odd_justa, prob_poisson, ev_perc, exp_cards = extract_cards_under_suggestion(prediction_text)

        if status_gk == 'NO_BET' or not palpite_str:
            print(f"🛡️ [Gatekeeper NO_BET / Abstenção] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Predição: '{prediction_text[:60]}...'. Entrada Under não recomendada.")
            apostas_abstencao += 1
            continue

        # Definição da Odd Real da Casa para a Aposta
        # Se odd_justa calculada estiver disponível, estimamos uma odd de mercado competitiva (+EV)
        # Ex: Odd Mercado 1.80 a 1.85 ou odd_justa com margem de segurança
        odd_val = 1.80
        if odd_justa and odd_justa > 1.0:
            # Garante que a odd oferecida atenda a margem financeira (+EV)
            odd_val = round(max(1.80, odd_justa * 1.15), 2)
        
        # Trava de Risco: Não criar aposta se a odd for inferior ao mínimo permitido (1.60)
        if odd_val < 1.60:
            print(f"🛡️ [Odd Baixa < 1.60] Partida {home_team} vs {away_team} -> Odd {odd_val:.2f} é inferior ao mínimo permitido. Aposta ignorada.")
            apostas_abstencao += 1
            continue

        # Calcula EV percentual final ((Prob * Odd) - 1) * 100
        if prob_poisson and prob_poisson > 0:
            ev_perc = round(((prob_poisson / 100.0) * odd_val - 1.0) * 100.0, 2)

        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

        # Inserir aposta para cada usuário cadastrado (com idempotência por fixture e mercado)
        for uid in user_ids:
            cursor.execute("""
                SELECT id FROM apostas 
                WHERE fixture_id = %s AND usuario_id = %s AND mercado = 'Total de Cartões'
            """, (fixture_id, uid))
            ja_existe = cursor.fetchone()

            if ja_existe:
                apostas_duplicadas += 1
                continue

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    odd_justa, probabilidade_poisson, ev_percentual, status_gatekeeper,
                    valor_aposta, ganhos_potenciais, status, data_hora_jogo, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Total de Cartões', %s, %s,
                    %s, %s, %s, 'APROVADO',
                    %s, %s, 'Pendente', %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, palpite_str, odd_val,
                odd_justa, prob_poisson, ev_perc,
                valor_aposta, ganhos_potenciais, fixture_date
            ))

            apostas_criadas += 1
            print(f"🟢 [Aposta Cartões Criada User #{uid}] ID #{cursor.lastrowid} | {home_team} vs {away_team} | Palpite: '{palpite_str}' @ Odd {odd_val:.2f} (Prob: {prob_poisson}%, EV: {ev_perc}%)")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE CRIAÇÃO DE APOSTAS CARTÕES UNDER CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🔄 Apostas Já Existentes (Ignoradas): {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/NO_BET: {apostas_abstencao}")
    print("=======================================================")

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    criar_apostas_cartoes_diario(target_date)
