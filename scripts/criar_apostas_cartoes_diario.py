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
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from datetime import datetime, timedelta
import requests
import time


def get_live_env_vars():
    """
    Lê diretamente o arquivo .env para obter credenciais SMTP e outras configurações.
    """
    env_vars = {}
    search_paths = [
        '/opt/airflow/.env',
        '/opt/airflow/dags/../../.env',
        '/opt/airflow/dags/../.env',
        '/root/datalake-air-flow-delta/.env',
        './.env',
        '../.env'
    ]
    for p in search_paths:
        if os.path.exists(p):
            try:
                with open(p, 'r', encoding='utf-8') as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith('#') and '=' in line:
                            k, v = line.split('=', 1)
                            env_vars[k.strip()] = v.strip().strip('"').strip("'")
            except Exception:
                pass
    return env_vars

def send_created_bets_email(novas_apostas, recipient="paulomnasc@gmail.com"):
    """
    Envia e-mail formatado em HTML com a lista das novas apostas de cartões criadas.
    """
    if not novas_apostas:
        return

    env = get_live_env_vars()
    smtp_host = env.get("SMTP_HOST") or os.environ.get("SMTP_HOST", "smtp-relay.brevo.com")
    smtp_port = int(env.get("SMTP_PORT") or os.environ.get("SMTP_PORT", 587))
    smtp_user = env.get("SMTP_USER") or os.environ.get("SMTP_USER", "")
    smtp_pass = env.get("SMTP_PASSWORD") or os.environ.get("SMTP_PASSWORD", "")
    smtp_from = env.get("SMTP_FROM_EMAIL") or os.environ.get("SMTP_FROM_EMAIL", "admin@estudotabela.com.br")
    smtp_from_name = env.get("SMTP_FROM_NAME") or os.environ.get("SMTP_FROM_NAME", "MyDataFlow Cartões")

    agora_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    total_apostas = len(novas_apostas)
    subject = f"🟨 [Apostas Cartões] {total_apostas} Nova(s) Aposta(s) Criada(s)! - {agora_str}"

    rows_html = ""
    for aposta in novas_apostas:
        tc = aposta.get('time_casa', '-')
        tv = aposta.get('time_fora', '-')
        data_j = aposta.get('data_hora_jogo', '-')
        if isinstance(data_j, datetime):
            data_j = data_j.strftime("%d/%m/%Y %H:%M")
        elif not data_j:
            data_j = '-'
        
        palpite = aposta.get('palpite', '-')
        odd = aposta.get('odd', 0.0)
        odd_justa = aposta.get('odd_justa')
        odd_justa_str = f"{odd_justa:.2f}" if isinstance(odd_justa, (float, int)) and odd_justa else "-"
        prob = aposta.get('probabilidade_poisson', 0.0)
        ev = aposta.get('ev_percentual', 0.0)
        valor = aposta.get('valor_aposta', 10.0)
        ganhos = aposta.get('ganhos_potenciais', 0.0)

        rows_html += f"""
        <tr style="border-bottom: 1px solid #e0e0e0;">
            <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}<br><span style="color: #666; font-weight: normal; font-size: 11px;">{data_j}</span></td>
            <td style="padding: 10px; font-size: 13px; color: #856404; font-weight: bold; background-color: #fff3cd; text-align: center;">{palpite}</td>
            <td style="padding: 10px; font-size: 13px; text-align: center;"><strong>{odd:.2f}</strong> <span style="font-size: 11px; color: #666;">(Justa: {odd_justa_str})</span></td>
            <td style="padding: 10px; font-size: 13px; color: #28a745; font-weight: bold; text-align: center;">{prob}% <br><span style="font-size: 11px; color: #17a2b8;">EV: +{ev}%</span></td>
            <td style="padding: 10px; font-size: 13px; text-align: center;">R$ {valor:.2f}</td>
            <td style="padding: 10px; font-size: 13px; color: #28a745; font-weight: bold; text-align: center;">R$ {ganhos:.2f}</td>
        </tr>
        """

    html_content = f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 800px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://myflow.estudotabela.com.br:28443/assets/img/carcara-logo.png" alt="MyDataFlow Logo" style="max-height: 70px; width: auto;">
            <h2 style="color: #d39e00; margin: 10px 0 0 0;">MyDataFlow - Mercado de Cartões Under</h2>
        </div>
        
        <div style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 15px;">
            <strong>🚀 NOVAS APOSTAS GERADAS!</strong> Foram identificadas e cadastradas <strong>{total_apostas}</strong> nova(s) aposta(s) no mercado de Total de Cartões (Estratégia Under).
        </div>

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #ffc107; font-size: 13px; margin-bottom: 20px;">
            <strong>Data da Execução:</strong> {agora_str}<br>
            <strong>Destinatário:</strong> {recipient}<br>
            <strong>Estratégia:</strong> Gatekeeper Exclusivo Under Cartões (Poisson & EV+)
        </div>

        <div style="margin-top: 20px; overflow-x: auto;">
            <h3 style="color: #856404; margin-bottom: 10px;">📋 Lista de Apostas Criadas</h3>
            <table style="width: 100%; border-collapse: collapse; background-color: #ffffff; border: 1px solid #dee2e6; font-family: Arial, sans-serif;">
                <thead>
                    <tr style="background-color: #ffc107; color: #212529; text-align: left; font-size: 13px;">
                        <th style="padding: 10px;">Partida / Horário</th>
                        <th style="padding: 10px; text-align: center;">Palpite</th>
                        <th style="padding: 10px; text-align: center;">Odd Casa (Justa)</th>
                        <th style="padding: 10px; text-align: center;">Prob. / EV</th>
                        <th style="padding: 10px; text-align: center;">Stake (R$)</th>
                        <th style="padding: 10px; text-align: center;">Retorno Est.</th>
                    </tr>
                </thead>
                <tbody>
                    {rows_html}
                </tbody>
            </table>
        </div>

        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #777777; text-align: center;">
            Este e-mail foi gerado automaticamente pelo pipeline do Airflow (<code>criar_apostas_cartoes_dag</code>).<br>
            <strong>MyDataFlow Platform</strong> &bull; <a href="https://myflow.estudotabela.com.br:28443" style="color: #d39e00; text-decoration: none;">Acessar Painel</a>
        </div>
      </body>
    </html>
    """

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{smtp_from_name} <{smtp_from}>"
    msg["To"] = recipient

    html_part = MIMEText(html_content, "html", "utf-8")
    msg.attach(html_part)

    print(f"📧 [E-mail Apostas Cartões] Conectando ao servidor SMTP {smtp_host}:{smtp_port} para enviar a {recipient}...")
    try:
        smtp_client = smtplib.SMTP(smtp_host, smtp_port, timeout=30)
        smtp_client.starttls()
        if smtp_user and smtp_pass:
            smtp_client.login(smtp_user, smtp_pass)

        smtp_client.sendmail(smtp_from, [recipient], msg.as_string())
        smtp_client.quit()
        print(f"✅ [E-mail Apostas Cartões] E-mail com {total_apostas} aposta(s) enviado com sucesso para {recipient}!")
    except Exception as err:
        print(f"❌ [E-mail Apostas Cartões] Falha ao enviar e-mail via SMTP: {err}")

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

def is_allowed_league(league_id, league_name: str) -> bool:
    """
    Filtra o escopo de atuação do script de criação de apostas:
    - Campeonatos do Brasil (Série A, Série B, Copa do Brasil, Paulistão, etc. - Série C e Série D excluídas)
    - Internacional CONMEBOL: Libertadores e Sul-Americana
    - Ligas Internacionais Permitidas:
      - Allsvenskan (ID 113)
      - Eredivisie (ID 88)
      - La Liga - Espanha (ID 140)
      - Liga MX (ID 262)
      - Liga Profesional Argentina (ID 128)
      - Ligue 1 - França (ID 61)
      - Major League Soccer - MLS (ID 253)
      - Premier League - Inglaterra (ID 39)
      - Primeira Liga - Portugal (ID 94)
      - Serie A Italiana (ID 135)
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

    # IDs Conhecidos da API-Football (Brasil, Libertadores/Sudamericana e Ligas de Elite Internacionais/Americanas)
    ALLOWED_LEAGUE_IDS = {
        71,   # Brasil Série A
        72,   # Brasil Série B
        73,   # Copa do Brasil
        13,   # CONMEBOL Libertadores
        11,   # CONMEBOL Sudamericana
        39,   # Premier League (Inglaterra)
        140,  # La Liga (Espanha)
        135,  # Serie A Italiana (Itália)
        61,   # Ligue 1 (França)
        94,   # Primeira Liga (Portugal)
        88,   # Eredivisie (Holanda)
        113,  # Allsvenskan (Suécia)
        253,  # Major League Soccer (EUA)
        262,  # Liga MX (México)
        128,  # Liga Profesional (Argentina)
    }
    if l_id in ALLOWED_LEAGUE_IDS:
        return True

    # Checagem por Nome de Liga Internacional Permitida (CONMEBOL + 10 novas ligas)
    allowed_int_keywords = [
        'libertadores', 'sudamericana', 'sul-americana', 'sul americana',
        'allsvenskan',
        'eredivisie',
        'la liga', 'laliga',
        'liga mx',
        'liga profesional', 'primera division (argentina)', 'primera división (argentina)', 'liga profesional argentina',
        'ligue 1',
        'major league soccer', 'mls',
        'premier league',
        'primeira liga', 'liga portugal',
        'serie a (italia)', 'serie a (italy)', 'serie a italia', 'serie a italiana'
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
        if l_name_low in ['serie a', 'serie b'] and l_id not in {71, 72}:
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

def extract_all_cards_suggestions(prediction_text: str):
    """
    Extrai todas as linhas de cartões sugeridas (1ª Opção, 2ª Opção, etc.) a partir do prediction_text de fixtures_trends.
    Retorna lista de tuplas: [(line_float, palpite_str, status_gatekeeper, odd_justa, prob_poisson, ev_perc, exp_cards), ...]
    """
    if not prediction_text:
        return []

    pred_low = prediction_text.lower()

    # 1. Trava de Abstenção / NO_BET expressa
    if any(term in pred_low for term in ['no_bet', 'no bet', 'sem entrada', 'abstenção', 'abstencao', 'bloqueada', 'indisponível', 'indisponivel', 'xc: 0.0', 'xc: 0.00']):
        return []

    # 2. Extrai expectativa matemática de cartões (xC / Expectativa)
    match_xc = re.search(r'(?:xC|Expectativa(?:\s+de\s+[Cc]artões)?(?::|\s+elevad[ao])?)\s*\(?(\d+\.\d+|\d+)', prediction_text, re.IGNORECASE)
    exp_cards = float(match_xc.group(1)) if match_xc else None

    if exp_cards is None or exp_cards <= 0:
        return []

    # 3. Busca opções explícitas em prediction_text ("1ª Opção: Over 3.5 | 2ª Opção: Over 4.5", etc.)
    raw_options = re.findall(r'(?:1ª|2ª|3ª)?\s*Opção:\s*(Over|Under)\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)

    candidates = []
    if raw_options:
        for op_type, line_str in raw_options:
            is_over = (op_type.lower() == 'over')
            line_val = float(line_str)
            if (is_over, line_val) not in candidates:
                candidates.append((is_over, line_val))

    if not candidates:
        if 'estratégia over' in pred_low or 'over' in pred_low:
            match_over = re.search(r'Over\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE) or re.search(r'mais\s+de\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)
            if match_over:
                candidates.append((True, float(match_over.group(1))))
        else:
            match_under = re.search(r'Under\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE) or re.search(r'menos\s+de\s*(\d+(?:\.\d+)?)', prediction_text, re.IGNORECASE)
            if match_under:
                candidates.append((False, float(match_under.group(1))))

    # Se a estratégia for Over/Under, adiciona também linhas alternativas padrão para avaliação
    is_over_strategy = 'estratégia over' in pred_low or 'over' in pred_low
    if is_over_strategy:
        for alt_line in [3.5, 4.5, 5.5]:
            if (True, alt_line) not in candidates:
                candidates.append((True, alt_line))
    else:
        for alt_line in [5.5, 4.5, 6.5]:
            if (False, alt_line) not in candidates:
                candidates.append((False, alt_line))

    suggestions = []
    for is_over, line_val in candidates:
        prob_under = calculate_poisson_under_cdf(exp_cards, line_val)

        if is_over:
            prob_poisson = round(100.0 - prob_under, 2)
            odd_justa = round(100.0 / prob_poisson, 2) if prob_poisson > 0 else 99.00
            # Regra do Gatekeeper para Over (Mínimo 60.0% Poisson e exp_cards >= 5.0)
            if prob_poisson >= 60.0 and exp_cards >= 5.0:
                status_gk = 'APROVADO'
            else:
                status_gk = 'NO_BET'
            palpite_str = f"Mais de {line_val} Cartões"
        else:
            prob_poisson = prob_under
            odd_justa = round(100.0 / prob_poisson, 2) if prob_poisson > 0 else 99.00

            if line_val < 4.5:
                status_gk = 'NO_BET'
            elif line_val < 5.5:
                if exp_cards <= 3.30 and prob_poisson >= 75.0:
                    status_gk = 'APROVADO'
                else:
                    status_gk = 'NO_BET'
            elif exp_cards <= 6.50 and prob_poisson >= 60.0:
                status_gk = 'APROVADO'
            else:
                status_gk = 'NO_BET'
            palpite_str = f"Menos de {line_val} Cartões"

        suggestions.append((line_val, palpite_str, status_gk, odd_justa, prob_poisson, None, exp_cards))

    return suggestions

def extract_cards_under_suggestion(prediction_text: str):
    """
    Função de compatibilidade: retorna a primeira sugestão de extract_all_cards_suggestions.
    """
    suggestions = extract_all_cards_suggestions(prediction_text)
    if suggestions:
        return suggestions[0]
    return None, None, 'NO_BET', None, None, None, None

_betano_cards_odds_cache = {}

def fetch_betano_real_card_odds(fixture_id: int, palpite_str: str, line_val: float):
    """
    Busca na API-Sports a odd REAL do mercado de cartões oferecida exclusivamente pela Betano (Bookmaker ID 32).
    Retorna tupla: (odd_float, 'BETANO') se encontrada, ou (None, None) se o mercado não estiver à venda na Betano.
    """
    if not fixture_id:
        return None, None

    cache_key = f"{fixture_id}_{palpite_str}_{line_val}"
    if cache_key in _betano_cards_odds_cache:
        return _betano_cards_odds_cache[cache_key]

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    is_under = 'menos' in (palpite_str or '').lower() or 'under' in (palpite_str or '').lower()
    target_type = 'under' if is_under else 'over'

    urls = [
        f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32&bet=80",
        f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bet=80",
        f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"
    ]

    for url in urls:
        try:
            resp = requests.get(url, headers=headers, timeout=10).json()
            items = resp.get('response', [])
            for item in items:
                for bm in item.get('bookmakers', []):
                    bm_name = str(bm.get('name', '')).strip().upper()
                    bm_id = bm.get('id')
                    if 'BETANO' not in bm_name and bm_id != 32:
                        continue

                    for bet in bm.get('bets', []):
                        b_id = bet.get('id')
                        b_name = str(bet.get('name', '')).lower()

                        if b_id in [80, 81, 82, 83, 204, 299, 335] or 'cards' in b_name or 'cartões' in b_name:
                            for val in bet.get('values', []):
                                v_str = str(val.get('value', '')).strip().lower()
                                try:
                                    v_odd = float(val.get('odd', 0))
                                except (ValueError, TypeError):
                                    continue

                                if target_type in v_str and str(line_val) in v_str:
                                    if v_odd > 1.0:
                                        res = (v_odd, 'BETANO')
                                        _betano_cards_odds_cache[cache_key] = res
                                        return res
        except Exception as e:
            print(f"⚠️ [API Betano Cards] Erro ao buscar odd para fixture #{fixture_id}: {e}")

    _betano_cards_odds_cache[cache_key] = (None, None)
    return None, None

def criar_apostas_cartoes_diario(target_date_str=None):
    """
    Busca os jogos em aberto na janela pré-jogo (ou data especificada)
    e cria apostas no mercado 'Total de Cartões' (Estratégia Under) para todos os usuários.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    is_all_open = False

    if not target_date_str or target_date_str.lower() in ('prematch', 'pre-match', 'all'):
        is_all_open = True
        date_desc = "todas as partidas em aberto futuras (sem limitação de janela pré-jogo)"
    else:
        target_dates = [target_date_str]
        date_desc = f"data {target_date_str}"

    print(f"🚀 [DAG Criar Apostas Cartões Under] Iniciando verificação de jogos para {date_desc}...")

    user_ids = get_all_user_ids(cursor)
    print(f"👥 Usuários identificados: {user_ids}")

    # 1. Buscar partidas em aberto
    if is_all_open:
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE fixture_date >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
            ORDER BY fixture_date ASC
        """)
    else:
        placeholders = ', '.join(['%s'] * len(target_dates))
        cursor.execute(f"""
            SELECT * FROM fixtures_trends
            WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) IN ({placeholders})
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
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
    novas_apostas_detalhes = []

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']
        league_id = fix.get('league_id')
        league_name = fix.get('league_name') or ''

        # Filtro Estrito de Escopo: Brasil, CONMEBOL e Ligas de Elite Selecionadas
        if not is_allowed_league(league_id, league_name):
            print(f"🌍 [Fora do Escopo] Partida {home_team} vs {away_team} ({league_name} ID #{league_id}) ignorada. Liga fora do escopo permitido.")
            continue

        # Trava Obrigatória do Gatekeeper: Não criar apostas em jogos sem árbitro definido (65% de peso no modelo)
        referee_name = (fix.get('referee_name') or '').strip()
        ref_low = referee_name.lower()
        if not referee_name or any(un in ref_low for un in ['árbitro não informado', 'arbitro nao informado', 'não informado', 'nao informado', 'unassigned', 'n/a', 'tbd', 'sem arbitro']):
            print(f"🛡️ [Gatekeeper NO_BET / Sem Árbitro] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Árbitro não definido ('{referee_name or 'Nulo'}'). Entrada ignorada por segurança.")
            apostas_abstencao += 1
            continue

        prediction_text = (fix.get('prediction_text') or '').strip()

        suggestions = extract_all_cards_suggestions(prediction_text)

        if not suggestions:
            print(f"🛡️ [Gatekeeper NO_BET / Abstenção] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Predição sem amostragem estatística suficiente.")
            apostas_abstencao += 1
            continue

        selected_suggestion = None
        for s_line_val, s_palpite_str, s_status_gk, s_odd_justa, s_prob_poisson, s_ev, s_exp_cards in suggestions:
            if s_status_gk == 'NO_BET' or not s_palpite_str:
                continue

            real_odd_betano, odd_source = fetch_betano_real_card_odds(fixture_id, s_palpite_str, s_line_val)

            if not real_odd_betano or real_odd_betano <= 1.0:
                print(f"ℹ️ [Linha Indisponível Betano] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Mercado '{s_palpite_str}' indisponível na Betano. Testando próxima sugestão...")
                continue

            if real_odd_betano < 1.50:
                print(f"ℹ️ [Odd Baixa < 1.50] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Odd Betano ({real_odd_betano:.2f}) para '{s_palpite_str}' é inferior ao mínimo (1.50). Testando próxima opção...")
                continue

            # Opção válida encontrada na Betano e aprovada pelo Gatekeeper!
            selected_suggestion = (s_line_val, s_palpite_str, s_status_gk, s_odd_justa, s_prob_poisson, real_odd_betano, s_exp_cards)
            break

        if not selected_suggestion:
            print(f"🛡️ [Gatekeeper NO_BET / Sem Odd Betano] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Nenhuma linha recomendada está disponível na Betano com odd >= 1.50.")
            apostas_abstencao += 1
            continue

        line_val, palpite_str, status_gk, odd_justa, prob_poisson, odd_val, exp_cards = selected_suggestion

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
            novas_apostas_detalhes.append({
                'usuario_id': uid,
                'fixture_id': fixture_id,
                'time_casa': home_team,
                'time_fora': away_team,
                'palpite': palpite_str,
                'odd': odd_val,
                'odd_justa': odd_justa,
                'probabilidade_poisson': prob_poisson,
                'ev_percentual': ev_perc,
                'valor_aposta': valor_aposta,
                'ganhos_potenciais': ganhos_potenciais,
                'data_hora_jogo': fixture_date
            })
            print(f"🟢 [Aposta Cartões Criada User #{uid}] ID #{cursor.lastrowid} | {home_team} vs {away_team} | Palpite: '{palpite_str}' @ Odd {odd_val:.2f} (Prob: {prob_poisson}%, EV: {ev_perc}%)")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE CRIAÇÃO DE APOSTAS CARTÕES UNDER CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🔄 Apostas Já Existentes (Ignoradas): {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/NO_BET: {apostas_abstencao}")
    print("=======================================================")

    if apostas_criadas > 0:
        recipient = os.environ.get("CART_BETS_EMAIL_RECIPIENT", "paulomnasc@gmail.com")
        send_created_bets_email(novas_apostas_detalhes, recipient=recipient)

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    criar_apostas_cartoes_diario(target_date)
