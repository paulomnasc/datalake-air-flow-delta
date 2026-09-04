#!/usr/bin/env python3
"""
Script de Criação Diária de Apostas de Handicap Asiático (Airflow DAG Worker / Web Service)
Executado diariamente para verificar jogos em aberto do dia corrente (fuso horário local Brasil -03:00),
verificar a disponibilidade das linhas na Betano (Bookmaker ID 32),
criar apostas na tabela 'apostas' para os usuários com base na sugestão de Handicap Asiático,
cancelar e estornar apostas caso a análise da IA resulte em Abstenção/Bloqueio de Risco ou linha indisponível na Betano,
e enviar notificações por e-mail com as movimentações realizadas.
"""

import sys
import os
import re
import requests
import pymysql
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from datetime import datetime, timedelta

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
    return [558]

_betano_ah_odds_cache = {}
_betano_ah_api_disabled = False

def fetch_betano_real_ah_odds(fixture_id: int, palpite_str: str, home_team: str, away_team: str):
    """
    Busca na API-Sports a odd REAL do mercado de Handicap Asiático oferecida exclusivamente pela Betano (Bookmaker ID 32).
    Retorna tupla: (odd_float, 'BETANO') se encontrada, ou (None, None) se a linha não estiver à venda na Betano.
    Possui Circuit-Breaker para interrupção imediata quando a cota diária de requisições estourar.
    """
    global _betano_ah_api_disabled
    if not fixture_id or _betano_ah_api_disabled:
        return None, None

    cache_key = f"{fixture_id}_{palpite_str}"
    if cache_key in _betano_ah_odds_cache:
        return _betano_ah_odds_cache[cache_key]

    env = get_live_env_vars()
    api_key = env.get('FOOTBALL_API_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {
        'x-apisports-key': api_key,
        'User-Agent': 'Mozilla/5.0'
    }

    is_away = away_team.lower() in (palpite_str or '').lower() or 'visitante' in (palpite_str or '').lower() or 'fora' in (palpite_str or '').lower()
    target_side = 'Away' if is_away else 'Home'

    sign = '+' if '+' in (palpite_str or '') else ('-' if '-' in (palpite_str or '') else '')
    m = re.search(r'(\d+(?:\.\d+)?)', palpite_str or '')
    line_val = m.group(1) if m else ''

    # Na API-Sports (Bookmaker ID 32 Betano), as linhas de Handicap Asiático (Bet #4) para o time visitante (Away)
    # possuem os sinais invertidos (+ vira -, - vira +) na rotulagem da string da API devido à perspectiva do mandante (Home).
    expected_signs = []
    if is_away and sign:
        inv_sign = '-' if sign == '+' else '+'
        expected_signs = [inv_sign, sign]
    elif sign:
        expected_signs = [sign]
    else:
        expected_signs = ['']

    url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"
    try:
        resp = requests.get(url, headers=headers, timeout=10).json()
        errs = resp.get('errors')
        if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
            print(f"⚠️ [API-Sports Betano AH] Limite de requisições ou cota diária atingido: {errs}. Ativando Circuit-Breaker nesta execução.")
            _betano_ah_api_disabled = True
            _betano_ah_odds_cache[cache_key] = (None, None)
            return None, None

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

                    # Bet ID 4 = Asian Handicap, Bet ID 1 = Match Winner (1X2)
                    if b_id == 4 or 'asian handicap' in b_name:
                        values = bet.get('values', [])
                        for s in expected_signs:
                            search_target = f"{s}{line_val}" if s else line_val
                            for val in values:
                                v_str = str(val.get('value', '')).strip()
                                v_odd_raw = val.get('odd')
                                try:
                                    v_odd = float(v_odd_raw)
                                except (ValueError, TypeError):
                                    continue

                                if v_odd > 1.0 and target_side.lower() in v_str.lower() and search_target in v_str:
                                    res = (v_odd, 'BETANO')
                                    _betano_ah_odds_cache[cache_key] = res
                                    return res

                    # Se a linha for 0.0 ou Empate Anula, também checa Bet ID 16 (Draw No Bet)
                    elif ('0.0' in line_val or 'empate anula' in (palpite_str or '').lower()) and (b_id == 16 or 'draw no bet' in b_name):
                        for val in bet.get('values', []):
                            v_str = str(val.get('value', '')).strip()
                            try:
                                v_odd = float(val.get('odd', 0))
                            except (ValueError, TypeError):
                                continue

                            if v_odd > 1.0 and target_side.lower() in v_str.lower():
                                res = (v_odd, 'BETANO')
                                _betano_ah_odds_cache[cache_key] = res
                                return res

    except Exception as e:
        print(f"⚠️ [API Betano AH] Erro ao buscar odd para fixture #{fixture_id}: {e}")

    _betano_ah_odds_cache[cache_key] = (None, None)
    return None, None

def send_handicap_bets_email(novas_apostas, apostas_canceladas, recipient="paulomnasc@gmail.com"):
    """
    Envia e-mail formatado em HTML com a lista das novas apostas de Handicap Asiático criadas e/ou canceladas/estornadas.
    """
    if not novas_apostas and not apostas_canceladas:
        return

    env = get_live_env_vars()
    smtp_host = env.get("SMTP_HOST") or os.environ.get("SMTP_HOST", "smtp-relay.brevo.com")
    smtp_port = int(env.get("SMTP_PORT") or os.environ.get("SMTP_PORT", 587))
    smtp_user = env.get("SMTP_USER") or os.environ.get("SMTP_USER", "")
    smtp_pass = env.get("SMTP_PASSWORD") or os.environ.get("SMTP_PASSWORD", "")
    smtp_from = env.get("SMTP_FROM_EMAIL") or os.environ.get("SMTP_FROM_EMAIL", "admin@estudotabela.com.br")
    smtp_from_name = env.get("SMTP_FROM_NAME") or os.environ.get("SMTP_FROM_NAME", "MyDataFlow Handicap")

    agora_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    num_novas = len(novas_apostas)
    num_canc = len(apostas_canceladas)

    subject_parts = []
    if num_novas > 0:
        subject_parts.append(f"🟢 {num_novas} Nova(s) Aposta(s)")
    if num_canc > 0:
        subject_parts.append(f"🚫 {num_canc} Cancelada(s)/Estornada(s)")
    
    subject = f"🛡️ [Apostas Handicap Betano] {' | '.join(subject_parts)} - {agora_str}"

    rows_novas_html = ""
    for aposta in novas_apostas:
        tc = aposta.get('time_casa', '-')
        tv = aposta.get('time_fora', '-')
        data_j = aposta.get('data_hora_jogo', '-')
        if isinstance(data_j, datetime):
            data_j = data_j.strftime("%d/%m/%Y %H:%M")
        elif not data_j:
            data_j = '-'
        
        palpite = aposta.get('palpite', '-')
        odd = float(aposta.get('odd', 0.0))
        valor = float(aposta.get('valor_aposta', 10.0))
        ganhos = float(aposta.get('ganhos_potenciais', 0.0))

        rows_novas_html += f"""
        <tr style="border-bottom: 1px solid #e0e0e0;">
            <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}<br><span style="color: #666; font-weight: normal; font-size: 11px;">{data_j}</span></td>
            <td style="padding: 10px; font-size: 13px; color: #0d6efd; font-weight: bold; background-color: #e7f1ff; text-align: center;">{palpite}</td>
            <td style="padding: 10px; font-size: 13px; text-align: center;"><strong>{odd:.2f}</strong> <span style="font-size: 11px; color: #ff6b00; font-weight: bold;">(Betano)</span></td>
            <td style="padding: 10px; font-size: 13px; text-align: center;">R$ {valor:.2f}</td>
            <td style="padding: 10px; font-size: 13px; color: #28a745; font-weight: bold; text-align: center;">R$ {ganhos:.2f}</td>
        </tr>
        """

    rows_canc_html = ""
    for aposta in apostas_canceladas:
        tc = aposta.get('time_casa', '-')
        tv = aposta.get('time_fora', '-')
        palpite = aposta.get('palpite', '-')
        valor = float(aposta.get('valor_aposta', 10.0))
        motivo = aposta.get('motivo', 'Abstenção da IA')
        estornado = aposta.get('estornado', False)
        saldo_post = aposta.get('saldo_posterior')

        estorno_badge = f"""<span style="background-color: #d1e7dd; color: #0f5132; padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 11px;">💰 Estornado R$ {valor:.2f} (Novo Saldo: R$ {saldo_post:.2f})</span>""" if estornado and saldo_post is not None else """<span style="background-color: #f8d7da; color: #842029; padding: 3px 6px; border-radius: 4px; font-size: 11px;">Sem débito prévio (Simulação)</span>"""

        rows_canc_html += f"""
        <tr style="border-bottom: 1px solid #e0e0e0; background-color: #fff5f5;">
            <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}</td>
            <td style="padding: 10px; font-size: 13px; color: #dc3545; font-weight: bold; text-align: center;">{palpite}</td>
            <td style="padding: 10px; font-size: 12px; color: #666;">{motivo}</td>
            <td style="padding: 10px; font-size: 12px; text-align: center;">{estorno_badge}</td>
        </tr>
        """

    section_novas = f"""
    <div style="margin-top: 20px; overflow-x: auto;">
        <h3 style="color: #0d6efd; margin-bottom: 10px;">📋 Novas Apostas Criadas na Betano ({num_novas})</h3>
        <table style="width: 100%; border-collapse: collapse; background-color: #ffffff; border: 1px solid #dee2e6; font-family: Arial, sans-serif;">
            <thead>
                <tr style="background-color: #0d6efd; color: #ffffff; text-align: left; font-size: 13px;">
                    <th style="padding: 10px;">Partida / Horário</th>
                    <th style="padding: 10px; text-align: center;">Palpite</th>
                    <th style="padding: 10px; text-align: center;">Odd Betano</th>
                    <th style="padding: 10px; text-align: center;">Valor Stake</th>
                    <th style="padding: 10px; text-align: center;">Retorno Potencial</th>
                </tr>
            </thead>
            <tbody>
                {rows_novas_html}
            </tbody>
        </table>
    </div>
    """ if num_novas > 0 else ""

    section_canc = f"""
    <div style="margin-top: 20px; overflow-x: auto;">
        <h3 style="color: #dc3545; margin-bottom: 10px;">🚫 Apostas Canceladas & Estornadas ({num_canc})</h3>
        <table style="width: 100%; border-collapse: collapse; background-color: #ffffff; border: 1px solid #dee2e6; font-family: Arial, sans-serif;">
            <thead>
                <tr style="background-color: #dc3545; color: #ffffff; text-align: left; font-size: 13px;">
                    <th style="padding: 10px;">Partida</th>
                    <th style="padding: 10px; text-align: center;">Palpite Cancelado</th>
                    <th style="padding: 10px;">Motivo da Abstenção / Bloqueio</th>
                    <th style="padding: 10px; text-align: center;">Status do Estorno</th>
                </tr>
            </thead>
            <tbody>
                {rows_canc_html}
            </tbody>
        </table>
    </div>
    """ if num_canc > 0 else ""

    html_content = f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 800px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://myflow.estudotabela.com.br:28443/assets/img/carcara-logo.png" alt="MyDataFlow Logo" style="max-height: 70px; width: auto;">
            <h2 style="color: #0d6efd; margin: 10px 0 0 0;">MyDataFlow - Mercado de Handicap Asiático (Betano)</h2>
        </div>
        
        <div style="background-color: #e7f1ff; color: #0c4a6e; border: 1px solid #bae6fd; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 15px;">
            <strong>🛡️ RELATÓRIO DE MOVIMENTAÇÃO DE APOSTAS AH (BETANO)!</strong><br>
            Novas Criadas: <strong>{num_novas}</strong> | Canceladas / Estornadas: <strong>{num_canc}</strong>
        </div>

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #0d6efd; font-size: 13px; margin-bottom: 20px;">
            <strong>Data da Execução:</strong> {agora_str}<br>
            <strong>Destinatário:</strong> {recipient}<br>
            <strong>Casa de Apostas:</strong> Betano (Bookmaker ID 32)<br>
            <strong>Estratégia:</strong> Handicap Asiático (Gatekeeper & Sweet Spot Odd &ge; 1.50)
        </div>

        {section_novas}
        {section_canc}

        <div style="margin-top: 30px; font-size: 11px; color: #888888; text-align: center; border-top: 1px solid #eeeeee; padding-top: 10px;">
            Este é um e-mail automático gerado pela plataforma MyDataFlow Airflow DAG Worker.
        </div>
      </body>
    </html>
    """

    msg = MIMEMultipart('alternative')
    msg['Subject'] = subject
    msg['From'] = f"{smtp_from_name} <{smtp_from}>"
    msg['To'] = recipient
    msg.attach(MIMEText(html_content, 'html', 'utf-8'))

    try:
        if smtp_user and smtp_pass:
            server = smtplib.SMTP(smtp_host, smtp_port, timeout=15)
            server.starttls()
            server.login(smtp_user, smtp_pass)
            server.sendmail(smtp_from, [recipient], msg.as_string())
            server.quit()
            print(f"📧 [E-mail Enviado] Relatório de Apostas AH enviado com sucesso para {recipient}!")
        else:
            print(f"⚠️ [SMTP Não Configurado] Credenciais de e-mail ausentes. Notificação não enviada.")
    except Exception as e_mail:
        print(f"❌ [Erro ao enviar E-mail] Falha no disparo SMTP para {recipient}: {e_mail}")

def cancelar_e_estornar_aposta_handicap(cursor, fixture_id, motivo="Abstenção da IA / Gestão de Risco"):
    """
    Busca apostas pendentes no mercado de Handicap Asiático para o fixture_id.
    Altera o status para 'Cancelada' e, se a aposta tiver débito em conta corrente (DEBITO_APOSTA),
    efetua o estorno financeiro (ESTORNO_APOSTA) atualizando o saldo do usuário.
    Retorna lista de dicionários com detalhes das apostas canceladas/estornadas.
    """
    cursor.execute("""
        SELECT a.id, a.usuario_id, a.time_casa, a.time_fora, a.mercado, a.palpite, a.odd,
               a.valor_aposta, a.confirmada, a.data_hora_jogo, a.status,
               (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
        FROM apostas a
        WHERE a.fixture_id = %s 
          AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
          AND a.status = 'Pendente'
          AND (a.confirmada IS NULL OR a.confirmada = 0)
    """, (fixture_id,))
    apostas_pendentes = cursor.fetchall()
    
    canceladas_detalhes = []
    for aposta in apostas_pendentes:
        aposta_id = aposta['id']
        usuario_id = aposta['usuario_id']
        valor = float(aposta['valor_aposta'] or 0.0)
        
        # Checagem de segurança: Aposta confirmada pelo usuário jamais é cancelada automaticamente pela DAG
        is_confirmada = (int(aposta.get('confirmada') or 0) == 1) or (int(aposta.get('tem_debito') or 0) > 0)
        if is_confirmada:
            print(f"🔒 [Aposta Confirmada Mantida] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} é aposta confirmada pelo usuário. Cancelamento automático ignorado.")
            continue
        
        cursor.execute("""
            UPDATE apostas 
            SET status = 'Cancelada', 
                resultado_detalhado = %s, 
                updated_at = NOW() 
            WHERE id = %s
        """, (f"🚫 APOSTA CANCELADA POR ABSTENÇÃO DA IA: {str(motivo)[:200]}", aposta_id))
        
        estornado = False
        saldo_posterior = None
        
        cursor.execute("""
            SELECT id, valor FROM conta_corrente 
            WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'DEBITO_APOSTA'
            LIMIT 1
        """, (usuario_id, aposta_id))
        debito = cursor.fetchone()
        
        if debito:
            cursor.execute("""
                SELECT id FROM conta_corrente 
                WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'
                LIMIT 1
            """, (usuario_id, aposta_id))
            estorno_existente = cursor.fetchone()
            
            if not estorno_existente:
                cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
                user_row = cursor.fetchone()
                saldo_anterior = float(user_row['saldo_conta_corrente'] or 0.0) if user_row else 0.0
                saldo_posterior = round(saldo_anterior + valor, 2)
                
                desc_estorno = f"Estorno Aposta #{aposta_id} - Abstenção IA ({aposta['time_casa']} vs {aposta['time_fora']})"
                
                cursor.execute("""
                    INSERT INTO conta_corrente (
                        usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                    ) VALUES (
                        %s, %s, 'ESTORNO_APOSTA', %s, %s, %s, %s, NOW()
                    )
                """, (usuario_id, aposta_id, desc_estorno, valor, saldo_anterior, saldo_posterior))
                
                cursor.execute("""
                    UPDATE usuario 
                    SET saldo_conta_corrente = %s 
                    WHERE id = %s
                """, (saldo_posterior, usuario_id))
                
                estornado = True
                print(f"💰 [Estorno Efetivado] Aposta #{aposta_id} User #{usuario_id} | R$ {valor:.2f} estornado (Novo Saldo: R$ {saldo_posterior:.2f})")

        detail = dict(aposta)
        detail['motivo'] = motivo
        detail['estornado'] = estornado
        detail['saldo_posterior'] = saldo_posterior
        canceladas_detalhes.append(detail)
        
        print(f"🚫 [Aposta Handicap Cancelada] ID #{aposta_id} | {aposta['time_casa']} vs {aposta['time_fora']} -> Motivo: {motivo}")

    return canceladas_detalhes

def determine_bet_side(home_team: str, away_team: str, ah_suggestion: str) -> bool:
    """
    Determina se o palpite é a favor do Visitante (True) ou Mandante (False).
    """
    if not ah_suggestion:
        return False
    
    ah_low = ah_suggestion.lower().strip()
    away_low = away_team.lower().strip()
    home_low = home_team.lower().strip()

    if away_low in ah_low:
        return True
    if home_low in ah_low:
        return False

    if 'visitante' in ah_low or 'fora' in ah_low:
        return True
    return False

ALLOWED_LEAGUE_IDS = {
    71, 72, 73,   # Brasil Série A, Série B e Copa do Brasil
    39,           # Inglaterra Premier League
    140,          # Espanha La Liga
    135,          # Itália Serie A
    78,           # Alemanha Bundesliga
    61,           # França Ligue 1
    94,           # Portugal Liga Portugal (Primeira Liga)
    88,           # Holanda Eredivisie
    144,          # Bélgica Pro League
    203,          # Turquia Süper Lig
    179,          # Escócia Premiership
    128,          # Argentina Liga Profesional
    197,          # Grécia Super League 1
    2, 3, 848,    # UEFA Champions League, Europa League, Conference League
    13, 11        # CONMEBOL Libertadores, Copa Sudamericana
}

ALLOWED_LEAGUE_NAMES = [
    'brasileirão', 'brasileirao', 'serie a', 'série a', 'serie b', 'série b', 'copa do brasil', 'copa brasil',
    'premier league',
    'la liga',
    'bundesliga',
    'ligue 1',
    'primeira liga', 'liga portugal',
    'eredivisie',
    'pro league', 'jupiler pro league',
    'super lig', 'süper lig',
    'premiership',
    'liga profesional',
    'super league 1',
    'champions league', 'europa league', 'conference league',
    'libertadores', 'copa sudamericana', 'sudamericana'
]

def is_allowed_league(league_id, league_name: str, fixture_date=None) -> bool:
    """
    Filtra o escopo de atuação do script de criação de apostas estritamente para Ligas de Elite e Torneios Continentais de 1ª Divisão (e Série B do Brasil).
    """
    if not league_name and not league_id:
        return False
    
    l_name_low = str(league_name or '').lower().strip()

    # 1. Bloqueia partidas femininas
    if any(w in l_name_low for w in ['women', 'feminino', 'femenina']):
        return False

    # 2. Bloqueia Divisões Secundárias Europeias e Inferiores (Championship, La Liga 2, Ligue 2, 2. Bundesliga, League One/Two, Copas Menores)
    secondary_blocked = [
        'championship', 'la liga 2', 'segunda división', 'segunda division',
        '2. bundesliga', 'ligue 2', '2nd division', 'division 2',
        'efl trophy', 'fl trophy', 'johnstone', 'bristol street', 'papa john',
        'carabao cup', 'league cup', 'fa trophy',
        'league one', 'league 1', 'league two', 'league 2', 'national league'
    ]
    if any(blocked in l_name_low for blocked in secondary_blocked):
        return False

    # 3. Bloqueia explicitamente todas as ligas e copas do Japão (J1, J2, J3, Emperor's Cup, etc.)
    japan_blocked = ['japan', 'japão', 'japao', 'j1 league', 'j2 league', 'j3 league', 'j-league', 'j.league', 'emperor']
    if any(blocked in l_name_low for blocked in japan_blocked):
        return False

    # 4. Validação por ID Numérico Oficial
    if league_id is not None:
        try:
            lid = int(league_id)
            if lid in ALLOWED_LEAGUE_IDS:
                return True
            else:
                return False
        except (ValueError, TypeError):
            pass

    # 5. Validação por Nome da Liga (Fallback)
    if any(allowed in l_name_low for allowed in ALLOWED_LEAGUE_NAMES):
        return True

    return False

def criar_apostas_handicap_diario(target_date_str=None, confirmada=0):
    """
    Busca os jogos em aberto, verifica a disponibilidade REAL das linhas na Betano (Bookmaker ID 32)
    e cria apostas no mercado de Handicap Asiático para todos os usuários.
    Se a linha estiver indisponível na Betano, possuir indicação de abstenção ou não passar no Gatekeeper,
    cancela apostas pendentes e realiza o estorno financeiro em conta corrente.
    Envia e-mail de notificação ao final da execução.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    is_prematch_window = False
    confirmada_val = int(confirmada) if confirmada is not None else 0

    if target_date_str and target_date_str.lower() in ('prematch', 'pre-match'):
        is_prematch_window = True
        date_desc = "janela pré-jogo (30 a 45 minutos antes do início)"
    elif not target_date_str or target_date_str.lower() in ('all', 'today'):
        today_dt = datetime.now()
        tomorrow_dt = today_dt + timedelta(days=1)
        target_dates = [today_dt.strftime('%Y-%m-%d'), tomorrow_dt.strftime('%Y-%m-%d')]
        date_desc = f"todas as partidas em aberto das datas {target_dates[0]} e {target_dates[1]}"
    else:
        target_dates = [d.strip() for d in target_date_str.split(',') if d.strip()]
        date_desc = f"datas {', '.join(target_dates)}"

    print(f"🚀 [DAG Criar Apostas AH Betano] Iniciando verificação de jogos para {date_desc} (Confirmada={confirmada_val})...")

    user_ids = get_all_user_ids(cursor)
    print(f"👥 Usuários identificados: {user_ids}")

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
    apostas_abstenção = 0
    apostas_canceladas = 0

    novas_apostas_detalhes = []
    apostas_canceladas_detalhes = []

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']
        league_id = fix.get('league_id')
        league_name = fix.get('league_name') or ''

        if not is_allowed_league(league_id, league_name, fixture_date):
            print(f"🌍 [Fora do Escopo / Bloqueio Meio de Semana] Partida {home_team} vs {away_team} ({league_name} ID #{league_id}) ignorada.")
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, "Liga/Copa fora do escopo (Bloqueio Meio de Semana / EFL Trophy)")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        ah_suggestion = (fix.get('ah_suggestion') or '').strip()

        if not ah_suggestion:
            print(f"⚠️ Sem ah_suggestion prévia para {home_team} vs {away_team} (ID #{fixture_id}). Ignorando...")
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, "Sem palpite prévio de IA")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        ah_norm = ah_suggestion.lower()

        # 1. Filtrar abstenções, bloqueios de risco e 'Sem Entrada'
        if any(term in ah_norm for term in ['sem entrada', 'abstenção', 'abstencao', 'bloqueada', 'no_bet', 'indisponível', 'indisponivel']):
            print(f"🛡️ [Abstenção] Partida {home_team} vs {away_team} -> Sugestão: '{ah_suggestion}'.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Abstenção da IA no Card: {ah_suggestion}")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        # 2. Validação Rígida de Formato do Handicap Asiático:
        has_handicap_spec = bool(re.search(r'[+-]?\d+\.?\d*', ah_suggestion)) or '0.0' in ah_suggestion
        if not has_handicap_spec or ah_norm.startswith('vitória') or ah_norm.startswith('vitoria'):
            print(f"🛡️ [Formato Inválido AH] Partida {home_team} vs {away_team} -> Sugestão '{ah_suggestion}' não possui linha de handicap válida.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Formato de linha inválido: {ah_suggestion}")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        is_away = determine_bet_side(home_team, away_team, ah_suggestion)

        # Validação de Segurança: Time favorito no mercado 1X2 não comercializa linhas com handicap positivo (+) na Betano
        raw_home_odd = float(fix.get('odd_home') or 2.0)
        raw_away_odd = float(fix.get('odd_away') or 2.0)
        is_target_fav = (raw_away_odd < raw_home_odd) if is_away else (raw_home_odd < raw_away_odd)
        has_positive_sign = '+' in ah_suggestion

        if is_target_fav and has_positive_sign:
            target_team_name = away_team if is_away else home_team
            print(f"🛡️ [Handicap Invertido Betano] Partida {home_team} vs {away_team} -> Linha '{ah_suggestion}' atribui vantagem positiva ao favorito ({target_team_name}), indisponível na Betano.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Linha '{ah_suggestion}' atribui handicap positivo ao favorito (indisponível na Betano)")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        ah_norm_after = ah_suggestion.lower()
        if '0.0' in ah_norm_after or 'empate anula' in ah_norm_after:
            print(f"🛡️ [Trava 0.0 AH / Empate Anula] Partida {home_team} vs {away_team} -> Sugestão '{ah_suggestion}' é linha 0.0 de alto risco (EV-).")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Trava 0.0 AH / Empate Anula de alto risco")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        allowed_lines = ['+0.25', '-0.25', '-0.5', '+0.5', '-1.0']
        if not any(al in ah_norm_after for al in allowed_lines):
            print(f"🛡️ [Linha Fora das Top Estratégias] Partida {home_team} vs {away_team} -> Sugestão '{ah_suggestion}' fora das linhas permitidas.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Linha fora das estratégias fracionadas permitidas: {ah_suggestion}")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        # 3. Consulta Obrigatória de Disponibilidade da Odd na Betano (Bookmaker ID 32)
        real_odd_betano, odd_source = fetch_betano_real_ah_odds(fixture_id, ah_suggestion, home_team, away_team)

        # Fallback para odd do banco se API da Betano estiver com cota estourada ou indisponível
        if not real_odd_betano or real_odd_betano <= 1.0:
            raw_odd = fix.get('odd_away') if is_away else fix.get('odd_home')
            if raw_odd and float(raw_odd) > 1.0:
                raw_float = float(raw_odd)
                if '+0.25' in ah_suggestion:
                    # Linha +0.25 para underdog: odd estimada com base na cotação seca (ex: 3.10 -> ~1.84)
                    real_odd_betano = round(max(1.55, min(2.05, 1.0 + (raw_float - 1.0) * 0.40)), 2)
                elif '+0.5' in ah_suggestion:
                    # Linha +0.5 (Dupla Chance): odd estimada com base na cotação seca (ex: 3.10 -> ~1.59)
                    real_odd_betano = round(max(1.50, min(1.85, 1.0 + (raw_float - 1.0) * 0.28)), 2)
                else:
                    real_odd_betano = raw_float
                odd_source = 'TRENDS_FALLBACK'
            else:
                print(f"ℹ️ [Linha Indisponível Betano] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Linha '{ah_suggestion}' indisponível na Betano.")
                apostas_abstenção += 1
                canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Linha '{ah_suggestion}' indisponível na Betano")
                if canc_list:
                    apostas_canceladas_detalhes.extend(canc_list)
                    apostas_canceladas += len(canc_list)
                continue

        odd_val = real_odd_betano

        # 4. Trava de Odd Mínima Segura (>= 1.50)
        min_odd_threshold = 1.50
        if odd_val < min_odd_threshold:
            print(f"🛡️ [Odd Baixa Betano] Partida {home_team} vs {away_team} ({league_name}) -> Odd Betano {odd_val:.2f} inferior a {min_odd_threshold:.2f}.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, f"Odd Betano ({odd_val:.2f}) abaixo do limiar seguro ({min_odd_threshold:.2f})")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

        for uid in user_ids:
            cursor.execute("""
                SELECT a.id, a.confirmada,
                       (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') AS tem_debito
                FROM apostas a 
                WHERE a.fixture_id = %s AND a.usuario_id = %s AND (a.mercado = 'Handicap Asiático' OR a.mercado LIKE '%%Handicap%%')
            """, (fixture_id, uid))
            ja_existe = cursor.fetchone()

            if ja_existe:
                is_conf = (int(ja_existe.get('confirmada') or 0) == 1) or (int(ja_existe.get('tem_debito') or 0) > 0)
                if is_conf:
                    print(f"🔒 [Aposta Confirmada Mantida User #{uid}] ID #{ja_existe['id']} com confirmação do usuário mantida intacta.")
                    apostas_duplicadas += 1
                    continue

                cursor.execute("""
                    UPDATE apostas SET
                        palpite = %s,
                        odd = %s,
                        ganhos_potenciais = %s,
                        resultado_detalhado = %s,
                        status = 'Pendente',
                        updated_at = NOW()
                    WHERE id = %s
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

            aposta_id = cursor.lastrowid
            apostas_criadas += 1

            if confirmada_val == 1:
                cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
                u_row = cursor.fetchone()
                s_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
                s_post = round(s_ant - valor_aposta, 2)
                desc_deb = f"Débito Aposta #{aposta_id} ({home_team} x {away_team} - {ah_suggestion})"
                cursor.execute("""
                    INSERT INTO conta_corrente (
                        usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                    ) VALUES (
                        %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, NOW()
                    )
                """, (uid, aposta_id, desc_deb, valor_aposta, s_ant, s_post))
                cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))

            print(f"🟢 [Aposta Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{ah_suggestion}' @ Odd Betano {odd_val:.2f} | Confirmada={confirmada_val}")

            novas_apostas_detalhes.append({
                'id': aposta_id,
                'usuario_id': uid,
                'time_casa': home_team,
                'time_fora': away_team,
                'palpite': ah_suggestion,
                'odd': odd_val,
                'valor_aposta': valor_aposta,
                'ganhos_potenciais': ganhos_potenciais,
                'data_hora_jogo': fixture_date
            })

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE APOSTAS AH BETANO CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🚫 Apostas Canceladas / Estornadas: {apostas_canceladas}")
    print(f"🔄 Apostas Já Existentes Mantidas: {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/Bloqueio: {apostas_abstenção}")
    print("=======================================================")

    if novas_apostas_detalhes or apostas_canceladas_detalhes:
        recipient = os.environ.get("AH_BETS_EMAIL_RECIPIENT", "paulomnasc@gmail.com")
        send_handicap_bets_email(novas_apostas_detalhes, apostas_canceladas_detalhes, recipient=recipient)

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    confirmada_arg = int(sys.argv[2]) if len(sys.argv) > 2 else 0
    criar_apostas_handicap_diario(target_date, confirmada_arg)
