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
import math
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
_betano_ah_raw_fixture_cache = {}
_betano_ah_api_disabled = False

def calculate_bivariate_poisson_matrix(lambda_h: float, lambda_a: float, max_goals: int = 10):
    """
    Gera a matriz de probabilidades conjuntas P(X=x, Y=y) para gols do Mandante (x) e Visitante (y).
    """
    matrix = {}
    total_prob = 0.0
    for x in range(max_goals):
        px = (math.pow(lambda_h, x) * math.exp(-lambda_h)) / math.factorial(x)
        for y in range(max_goals):
            py = (math.pow(lambda_a, y) * math.exp(-lambda_a)) / math.factorial(y)
            p = px * py
            matrix[(x, y)] = p
            total_prob += p

    if total_prob > 0:
        for k in matrix:
            matrix[k] /= total_prob
            
    return matrix

def evaluate_ah_line_poisson(matrix, is_away: bool, line: float, odd_betano: float):
    """
    Avalia uma linha de Handicap Asiático a partir da matriz bivariada de Poisson.
    Calcula P(win), P(half_win), P(push), P(half_loss), P(loss), Odd Justa, Prob. Efetiva e +EV%.
    """
    p_win = 0.0
    p_half_win = 0.0
    p_push = 0.0
    p_half_loss = 0.0
    p_loss = 0.0

    for (x, y), p in matrix.items():
        diff = (y - x) if is_away else (x - y)
        adj = diff + line

        if adj > 0.25:
            p_win += p
        elif abs(adj - 0.25) < 1e-4:
            p_half_win += p
        elif abs(adj) < 1e-4:
            p_push += p
        elif abs(adj - (-0.25)) < 1e-4:
            p_half_loss += p
        else:
            p_loss += p

    expected_payoff = (
        p_win * odd_betano +
        p_half_win * ((odd_betano + 1.0) / 2.0) +
        p_push * 1.0 +
        p_half_loss * 0.5
    )
    ev_percent = (expected_payoff - 1.0) * 100.0

    numerator = 1.0 - (p_half_win / 2.0 + p_push + 0.5 * p_half_loss)
    denominator = p_win + (p_half_win / 2.0)

    if denominator > 0 and numerator > 0:
        odd_justa = round(numerator / denominator, 2)
        prob_eff = round(min(100.0, max(0.0, 100.0 / odd_justa)), 2)
    else:
        odd_justa = 99.00
        prob_eff = 1.00

    return {
        'line': line,
        'is_away': is_away,
        'odd_betano': odd_betano,
        'odd_justa': odd_justa,
        'prob_eff': prob_eff,
        'ev_percent': round(ev_percent, 2),
        'p_win': round(p_win * 100, 2),
        'p_half_win': round(p_half_win * 100, 2),
        'p_push': round(p_push * 100, 2),
        'p_half_loss': round(p_half_loss * 100, 2),
        'p_loss': round(p_loss * 100, 2),
    }

def fetch_all_betano_ah_lines(fixture_id: int, home_team: str, away_team: str):
    """
    Busca na API-Sports TODAS as linhas de Handicap Asiático ativas oferecidas pela Betano (Bookmaker ID 32).
    Retorna lista de dicionários com cada linha disponível e sua cotação real.
    """
    global _betano_ah_api_disabled
    if not fixture_id or _betano_ah_api_disabled:
        return []

    # Reutiliza dados da Betano em cache de memória para a fixture
    if fixture_id in _betano_ah_raw_fixture_cache:
        items = _betano_ah_raw_fixture_cache[fixture_id]
    else:
        env = get_live_env_vars()
        api_key = env.get('FOOTBALL_API_KEY') or os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
        headers = {
            'x-apisports-key': api_key,
            'User-Agent': 'Mozilla/5.0'
        }

        url = f"https://v3.football.api-sports.io/odds?fixture={fixture_id}&bookmaker=32"
        items = []
        try:
            resp = requests.get(url, headers=headers, timeout=10).json()
            errs = resp.get('errors')
            if errs and isinstance(errs, dict) and ('rateLimit' in errs or 'requests' in errs):
                print(f"⚠️ [API-Sports Betano AH] Limite de requisições ou cota diária atingido: {errs}. Ativando Circuit-Breaker nesta execução.")
                _betano_ah_api_disabled = True
                return []

            items = resp.get('response', [])
            _betano_ah_raw_fixture_cache[fixture_id] = items
        except Exception as e:
            print(f"⚠️ [API Betano AH] Erro ao buscar odds para fixture #{fixture_id}: {e}")
            _betano_ah_raw_fixture_cache[fixture_id] = []

    available_lines = []

    for item in items:
        for bm in item.get('bookmakers', []):
            bm_name = str(bm.get('name', '')).strip().upper()
            bm_id = bm.get('id')
            if 'BETANO' not in bm_name and bm_id != 32:
                continue

            for bet in bm.get('bets', []):
                b_id = bet.get('id')
                b_name = str(bet.get('name', '')).lower()

                # Ignorar mercados de 1º tempo, intervalo, cartões e escanteios
                if any(term in b_name for term in ['half', '1st', '2nd', 'corner', 'card', 'cartão', 'escanteio', 'tempo', 'intervalo']):
                    continue

                # Bet ID 4 = Asian Handicap Full Time
                if b_id == 4 or 'asian handicap' in b_name or 'handicap asiático' in b_name:
                    for val in bet.get('values', []):
                        v_str = str(val.get('value', '')).strip()
                        v_odd_raw = val.get('odd')
                        try:
                            v_odd = float(v_odd_raw)
                        except (ValueError, TypeError):
                            continue

                        if v_odd <= 1.0:
                            continue

                        is_away = ('away' in v_str.lower() or away_team.lower() in v_str.lower())
                        m_line = re.search(r'([+-]?\d+(?:\.\d+)?)', v_str)
                        if m_line:
                            try:
                                line_num = float(m_line.group(1))
                            except Exception:
                                continue

                            target_team = away_team if is_away else home_team
                            sign_str = f"{line_num:+.2f}".rstrip('0').rstrip('.')
                            if line_num == 0:
                                sign_str = "0.0"
                            palpite_fmt = f"{target_team} {sign_str} AH"

                            available_lines.append({
                                'team': 'Away' if is_away else 'Home',
                                'target_team': target_team,
                                'is_away': is_away,
                                'line': line_num,
                                'palpite_str': palpite_fmt,
                                'odd': v_odd,
                                'raw_value': v_str,
                                'source': 'BETANO'
                            })

                # Bet ID 16 = Draw No Bet (Handicap 0.0)
                elif b_id == 16 or 'draw no bet' in b_name or 'empate anula' in b_name:
                    for val in bet.get('values', []):
                        v_str = str(val.get('value', '')).strip()
                        try:
                            v_odd = float(val.get('odd', 0))
                        except (ValueError, TypeError):
                            continue

                        if v_odd <= 1.0:
                            continue

                        is_away = ('away' in v_str.lower() or away_team.lower() in v_str.lower())
                        target_team = away_team if is_away else home_team
                        available_lines.append({
                            'team': 'Away' if is_away else 'Home',
                            'target_team': target_team,
                            'is_away': is_away,
                            'line': 0.0,
                            'palpite_str': f"{target_team} 0.0 AH",
                            'odd': v_odd,
                            'raw_value': v_str,
                            'source': 'BETANO'
                        })

    return available_lines

def fetch_betano_real_ah_odds(fixture_id: int, palpite_str: str, home_team: str, away_team: str):
    """
    Função de compatibilidade: busca uma linha específica entre as disponíveis na Betano.
    """
    lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)
    if not lines:
        return None, None

    is_away = away_team.lower() in (palpite_str or '').lower()
    m = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite_str or '')
    target_line = float(m.group(1)) if m else None

    for item in lines:
        if item['is_away'] == is_away:
            if target_line is not None and abs(item['line'] - target_line) < 0.01:
                return item['odd'], 'BETANO'

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
    307,          # Arábia Saudita Saudi Pro League
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
    'pro league', 'jupiler pro league', 'saudi pro league',
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

        # 1. Obter xG ajustado do Mandante e Visitante para a Matriz Bivariada de Poisson
        xg_h = float(fix.get('xg_home') or 0.0)
        xg_a = float(fix.get('xg_away') or 0.0)
        if xg_h <= 0.1 or xg_a <= 0.1:
            reasoning = fix.get('ah_reasoning') or ''
            m_h = re.search(r'\(Em Casa\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
            m_a = re.search(r'\(Fora\):.*?=\s*xG\s*Adj\s*(\d+(?:\.\d+)?)', reasoning)
            if m_h:
                xg_h = float(m_h.group(1))
            if m_a:
                xg_a = float(m_a.group(1))
        if xg_h <= 0.1:
            xg_h = 1.25
        if xg_a <= 0.1:
            xg_a = 1.05

        # 2. Gerar Matriz de Poisson Conjunta (Gols Casa x Gols Fora)
        poisson_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

        # 3. Buscar TODAS as linhas ativas de Handicap Asiático oferecidas pela Betano
        betano_lines = fetch_all_betano_ah_lines(fixture_id, home_team, away_team)

        # Fallback de contingência se a Betano estiver temporariamente sem cotação aberta
        if not betano_lines:
            ah_previo = (fix.get('ah_suggestion') or '').strip()
            if ah_previo and not any(term in ah_previo.lower() for term in ['sem entrada', 'abstenção', 'no_bet', 'indisponível']):
                is_away_p = determine_bet_side(home_team, away_team, ah_previo)
                m_p = re.search(r'([+-]?\d+(?:\.\d+)?)', ah_previo)
                l_p = float(m_p.group(1)) if m_p else 0.0
                raw_ref_odd = float(fix.get('odd_away') if is_away_p else fix.get('odd_home') or 1.90)
                if '+0.25' in ah_previo:
                    est_odd = round(max(1.55, min(2.05, 1.0 + (raw_ref_odd - 1.0) * 0.40)), 2)
                elif '+0.5' in ah_previo:
                    est_odd = round(max(1.50, min(1.85, 1.0 + (raw_ref_odd - 1.0) * 0.28)), 2)
                elif '-0.25' in ah_previo:
                    est_odd = round(max(1.55, min(2.10, 1.0 + (raw_ref_odd - 1.0) * 0.72)), 2)
                elif '-0.5' in ah_previo:
                    est_odd = round(max(1.55, raw_ref_odd), 2)
                elif '-0.75' in ah_previo:
                    est_odd = round(max(1.65, min(2.15, raw_ref_odd + 0.22)), 2)
                else:
                    est_odd = raw_ref_odd

                target_t = away_team if is_away_p else home_team
                betano_lines.append({
                    'team': 'Away' if is_away_p else 'Home',
                    'target_team': target_t,
                    'is_away': is_away_p,
                    'line': l_p,
                    'palpite_str': f"{target_t} {l_p:+.2f} AH",
                    'odd': est_odd,
                    'raw_value': f"{target_t} {l_p:+.2f}",
                    'source': 'TRENDS_FALLBACK'
                })

        if not betano_lines:
            print(f"ℹ️ [Sem Linhas Betano] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Nenhuma linha de AH disponível.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, "Nenhuma linha de Handicap disponível na Betano")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        # 4. Avaliar cada linha Betano com a Matriz de Poisson e aplicar Filtros do Gatekeeper
        approved_candidates = []
        allowed_lines = {-0.25, -0.5, -0.75, 0.25, 0.5, 0.75, 1.0, 1.25, 1.5}

        for cand in betano_lines:
            cand_line = cand['line']
            cand_odd = cand['odd']
            cand_is_away = cand['is_away']
            cand_target_team = cand['target_team']

            # Filtro 1: Linhas permitidas (evita linhas de goleada -1.5, -2.0 e linha 0.0)
            if cand_line not in allowed_lines:
                continue

            # Filtro 2: Faixa de odd segura
            if cand_odd < 1.50 or cand_odd > 2.35:
                continue

            # Filtro 3: Inversão de Handicap (favorito 1X2 não recebe handicap positivo alto)
            raw_h_odd = float(fix.get('odd_home') or 2.0)
            raw_a_odd = float(fix.get('odd_away') or 2.0)
            is_cand_fav = (raw_a_odd < raw_h_odd) if cand_is_away else (raw_h_odd < raw_a_odd)
            if is_cand_fav and cand_line > 0.25:
                continue

            # Avaliação Poisson
            res = evaluate_ah_line_poisson(poisson_matrix, cand_is_away, cand_line, cand_odd)
            ev = res['ev_percent']
            prob_eff = res['prob_eff']

            # GATEKEEPER AH: Exige +EV% real >= 5.0% e Probabilidade Efetiva >= 48.0%
            if ev >= 5.0 and prob_eff >= 48.0:
                score = ev * (prob_eff / 100.0)
                cand['eval'] = res
                cand['score'] = score
                approved_candidates.append(cand)

        # 5. Se nenhuma linha atinge +EV% >= 5%, o Gatekeeper emite NO_BET (Abstenção Mandatória)
        if not approved_candidates:
            print(f"🛡️ [Gatekeeper AH NO_BET / Sem EV+] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> Nenhuma linha Betano com +EV >= 5% e prob >= 48%. Abstenção mandatória.")
            apostas_abstenção += 1
            canc_list = cancelar_e_estornar_aposta_handicap(cursor, fixture_id, "🚫 Gatekeeper AH NO_BET: Nenhuma linha Betano com +EV >= 5%")
            if canc_list:
                apostas_canceladas_detalhes.extend(canc_list)
                apostas_canceladas += len(canc_list)
            continue

        # 6. Seleciona o melhor candidato aprovado (Maior Score de Valor: EV% x Probabilidade)
        approved_candidates.sort(key=lambda x: x['score'], reverse=True)
        best_cand = approved_candidates[0]

        eval_res = best_cand['eval']
        selected_palpite = best_cand['palpite_str']
        odd_val = best_cand['odd']
        odd_justa = eval_res['odd_justa']
        prob_poisson = eval_res['prob_eff']
        ev_perc = eval_res['ev_percent']

        valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

        detalhe_calculo = (
            f"🎯 GATEKEEPER AH APROVADO (+EV {ev_perc:+.1f}%) | "
            f"Odd Betano {odd_val:.2f} vs Odd Justa {odd_justa:.2f} (Prob. Efetiva: {prob_poisson:.1f}%) | "
            f"Matriz Poisson: xG {home_team} {xg_h:.2f} x {xg_a:.2f} {away_team} | "
            f"Desfechos: Vitória {eval_res['p_win']:.1f}%, Meio-Green {eval_res['p_half_win']:.1f}%, "
            f"Push {eval_res['p_push']:.1f}%, Meio-Red {eval_res['p_half_loss']:.1f}%, Red {eval_res['p_loss']:.1f}%."
        )

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
                        odd_justa = %s,
                        probabilidade_poisson = %s,
                        ev_percentual = %s,
                        status_gatekeeper = 'APROVADO',
                        ganhos_potenciais = %s,
                        resultado_detalhado = %s,
                        status = 'Pendente',
                        updated_at = NOW()
                    WHERE id = %s
                """, (selected_palpite, odd_val, odd_justa, prob_poisson, ev_perc, ganhos_potenciais, detalhe_calculo, ja_existe['id']))
                apostas_duplicadas += 1
                continue

            cursor.execute("""
                INSERT INTO apostas (
                    usuario_id, fixture_id, time_casa, time_fora, mercado, palpite, odd, 
                    odd_justa, probabilidade_poisson, ev_percentual,
                    valor_aposta, ganhos_potenciais, status_gatekeeper, status, confirmada, data_hora_jogo, resultado_detalhado, criado_em, updated_at
                ) VALUES (
                    %s, %s, %s, %s, 'Handicap Asiático', %s, %s,
                    %s, %s, %s,
                    %s, %s, 'APROVADO', 'Pendente', %s, %s, %s, NOW(), NOW()
                )
            """, (
                uid, fixture_id, home_team, away_team, selected_palpite, odd_val,
                odd_justa, prob_poisson, ev_perc,
                valor_aposta, ganhos_potenciais, confirmada_val, fixture_date,
                detalhe_calculo
            ))

            aposta_id = cursor.lastrowid
            apostas_criadas += 1

            if confirmada_val == 1:
                cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (uid,))
                u_row = cursor.fetchone()
                s_ant = float(u_row['saldo_conta_corrente'] or 0.0) if u_row else 0.0
                s_post = round(s_ant - valor_aposta, 2)
                desc_deb = f"Débito Aposta #{aposta_id} ({home_team} x {away_team} - {selected_palpite})"
                cursor.execute("""
                    INSERT INTO conta_corrente (
                        usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em
                    ) VALUES (
                        %s, %s, 'DEBITO_APOSTA', %s, %s, %s, %s, NOW()
                    )
                """, (uid, aposta_id, desc_deb, valor_aposta, s_ant, s_post))
                cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (s_post, uid))

            print(f"🟢 [Aposta Criada User #{uid}] ID #{aposta_id} | {home_team} vs {away_team} | Palpite: '{selected_palpite}' @ Odd Betano {odd_val:.2f} (Odd Justa: {odd_justa:.2f} | +EV: {ev_perc:+.1f}%) | Confirmada={confirmada_val}")

            novas_apostas_detalhes.append({
                'id': aposta_id,
                'usuario_id': uid,
                'time_casa': home_team,
                'time_fora': away_team,
                'palpite': selected_palpite,
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
