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

# Importar módulo global de ligas
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from leagues_config import ALLOWED_LEAGUES, ALLOWED_LEAGUE_IDS, ALLOWED_LEAGUE_NAMES, is_allowed_league
except Exception:
    ALLOWED_LEAGUE_IDS = set()
    ALLOWED_LEAGUE_NAMES = []
    def is_allowed_league(league_id, league_name: str = "", fixture_date=None) -> bool:
        return True


from db_config import get_db_connection, get_live_env_vars

def get_all_user_ids(cursor):
    """
    Retorna lista de IDs de usuários elegíveis para receber apostas automatizadas:
    - Usuários com créditos suficientes (grok_credits >= 0.5)
    - Usuários com assinatura ativa (status_assinatura = 'active')
    - Administrador Paulo (id = 558)
    """
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE (grok_credits >= 0.5 OR status_assinatura = 'active' OR id = 558)
        ORDER BY id ASC
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] for r in rows]
    return [558]

from asian_handicap_engine import (
    calculate_bivariate_poisson_matrix,
    evaluate_ah_line_poisson,
    fetch_all_betano_ah_lines,
    build_fallback_lines_from_odds,
    evaluate_and_select_best_ah_candidate,
    calculate_unified_handicap_recommendation,
    sync_fixture_and_bet_handicap,
    compose_compound_ah_reasoning,
    determine_bet_side,
    analisar_mudanca_odd_cancelamento,
    determine_gatekeeper_category,
    GATEKEEPER_DIDACTIC_MAP,
    BetTier
)

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

def format_game_date_brt(data_j):
    """
    Formata data do jogo para exibição em e-mails no horário de Brasília (UTC-3).
    Suporta objetos datetime e strings ISO / SQL.
    """
    if not data_j:
        return '-'
    dt = None
    if isinstance(data_j, datetime):
        dt = data_j
    elif isinstance(data_j, str) and data_j.strip() not in ('', '-'):
        try:
            dt = datetime.fromisoformat(data_j.strip().replace('Z', ''))
        except Exception:
            return data_j

    if dt:
        if dt.tzinfo is None:
            from datetime import timezone
            dt_utc = dt.replace(tzinfo=timezone.utc)
        else:
            dt_utc = dt

        try:
            from zoneinfo import ZoneInfo
            dt_brt = dt_utc.astimezone(ZoneInfo("America/Sao_Paulo"))
        except Exception:
            dt_brt = dt_utc - timedelta(hours=3)

        return dt_brt.strftime("%d/%m/%Y %H:%M")

    return str(data_j)

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
        subject_parts.append(f"🟢 {num_novas} Nova(s) Sugestão(ões)")
    if num_canc > 0:
        subject_parts.append(f"🚫 {num_canc} Cancelada(s)/Estornada(s)")
    
    subject = f"🛡️ [Sugestões Handicap Betano] {' | '.join(subject_parts)} - {agora_str}"

    rows_novas_html = ""
    for aposta in novas_apostas:
        tc = aposta.get('time_casa', '-')
        tv = aposta.get('time_fora', '-')
        data_j = format_game_date_brt(aposta.get('data_hora_jogo'))
        
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
        data_j = format_game_date_brt(aposta.get('data_hora_jogo'))
        palpite = aposta.get('palpite', '-')
        valor = float(aposta.get('valor_aposta', 10.0))
        motivo = aposta.get('motivo', 'Abstenção da IA')
        estornado = aposta.get('estornado', False)
        saldo_post = aposta.get('saldo_posterior')

        estorno_badge = f"""<span style="background-color: #d1e7dd; color: #0f5132; padding: 3px 6px; border-radius: 4px; font-weight: bold; font-size: 11px;">💰 Estornado R$ {valor:.2f} (Novo Saldo: R$ {saldo_post:.2f})</span>""" if estornado and saldo_post is not None else """<span style="background-color: #f8d7da; color: #842029; padding: 3px 6px; border-radius: 4px; font-size: 11px;">Sem débito prévio (Simulação)</span>"""

        rows_canc_html += f"""
        <tr style="border-bottom: 1px solid #e0e0e0; background-color: #fff5f5;">
            <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}<br><span style="color: #666; font-weight: normal; font-size: 11px;">{data_j}</span></td>
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
                    <th style="padding: 10px;">Partida / Horário</th>
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

def registrar_notificacao_usuario(cursor, usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link):
    """
    Insere notificação na tabela notificacoes_usuario para exibição em tempo real (Sino + Toast Pop-up).
    TRAVA DE DATA: Somente emite alertas se a partida ocorrer na data de HOJE (horário BRT).
    """
    try:
        if fixture_id:
            cursor.execute("""
                SELECT DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) as game_date,
                       DATE(CONVERT_TZ(NOW(), '+00:00', '-03:00')) as today_date
                FROM fixtures_trends
                WHERE fixture_id = %s
                LIMIT 1
            """, (fixture_id,))
            row_date = cursor.fetchone()
            if row_date and row_date.get('game_date') and row_date.get('today_date'):
                if str(row_date['game_date']) != str(row_date['today_date']):
                    print(f"ℹ️ [Alerta Suprimido / Data Futura] Partida #{fixture_id} ocorre em {row_date['game_date']}. Alertas permitidos apenas para a data de hoje ({row_date['today_date']}).")
                    return False

        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, criado_em
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, 0, NOW())
        """, (usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link))
        print(f"🔔 [Notificação Registrada User #{usuario_id}] {titulo}")
        return True
    except Exception as e:
        print(f"⚠️ [Notificação] Falha ao registrar notificação para user #{usuario_id}: {e}")
        return False

def cancelar_e_estornar_aposta_handicap(cursor, fixture_id, motivo="Abstenção da IA / Gestão de Risco"):
    """
    Cancelamento automático desativado conforme diretriz operacional (Diário de Bordo 22/10/2026).
    Preserva apostas existentes (confirmadas ou não confirmadas) como 'Pendente' sem estorno ou cancelamento forçado.
    """
    print(f"🔒 [Aposta Preservada / Cancelamento Automático Desativado] Partida #{fixture_id}: cancelamento automático ignorado. Apostas mantidas intactas.")
    return []

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
# Catálogo de ligas e validador is_allowed_league unificados globalmente em leagues_config.py


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

    # Sincronização sistêmica de remarcações: reconcilia datas de apostas pendentes com fixtures_trends
    try:
        cursor.execute("""
            UPDATE apostas a
            INNER JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
            SET a.data_hora_jogo = ft.fixture_date
            WHERE a.status = 'Pendente'
              AND a.data_hora_jogo != ft.fixture_date
        """)
        reconciled_cnt = cursor.rowcount
        if reconciled_cnt > 0:
            print(f"🔄 [Sincronização Sistêmica] Reconciliada a data de {reconciled_cnt} aposta(s) pendente(s) com fixtures_trends.")
    except Exception as e_reconcile:
        print(f"Aviso ao reconciliar datas de apostas pendentes: {e_reconcile}")

    if is_prematch_window:
        cursor.execute("""
            SELECT * FROM fixtures_trends
            WHERE fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)
              AND fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 45 MINUTE)
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
            ORDER BY fixture_date ASC
        """)
    else:
        placeholders = ', '.join(['%s'] * len(target_dates))
        cursor.execute(f"""
            SELECT * FROM fixtures_trends
            WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) IN ({placeholders})
              AND fixture_date >= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
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
    novas_candidatas_aprovadas = []

    for fix in fixtures:
        fixture_id = fix['fixture_id']
        home_team = fix['home_team'].strip()
        away_team = fix['away_team'].strip()
        fixture_date = fix['fixture_date']
        league_id = fix.get('league_id')
        league_name = fix.get('league_name') or ''

        if not is_allowed_league(league_id, league_name, fixture_date):
            print(f"🌍 [Fora do Escopo Global de Ligas] Partida {home_team} vs {away_team} ({league_name} ID #{league_id}) ignorada.")
            # Preserva apostas existentes sem cancelamento automático via DAG
            continue

        # Chamada unificada do Asian Handicap Engine (Single Source of Truth)
        status_gk, selected_palpite, conf_val, detalhe_calculo, best_cand, approved_cands = calculate_unified_handicap_recommendation(
            fix, allow_api_fetch=True, cursor=cursor
        )

        if status_gk == 'NO_BET' or not best_cand:
            print(f"🛡️ [Gatekeeper AH NO_BET / Abstenção] Partida {home_team} vs {away_team} (ID #{fixture_id}) -> {detalhe_calculo}")
            is_api_missing = any(k.lower() in (detalhe_calculo or '').lower() for k in [
                'sem odd betano', 'indisponível ou fechado na betano', 'limite de requisições', 'circuit-breaker',
                'ausência de linhas reais', 'cotações oficiais de handicap asiático indisponíveis', 'linhas reais', 'ausência de cotações'
            ]) and 'odds 1x2 ausentes' not in (detalhe_calculo or '').lower()

            # Se a partida já possui card aprovado em fixtures_trends ou odds gravadas, preserva sem cancelar!
            cur_sug_db = fix.get('ah_suggestion') or ''
            has_approved_card_in_db = cur_sug_db and not any(k in cur_sug_db.lower() for k in ['sem entrada', 'abstenção', 'abstencao', 'no_bet', 'bloqueada'])
            if is_api_missing and has_approved_card_in_db:
                print(f"🔒 [Card e Aposta Preservados / Falha Temporária de API] Partida #{fixture_id} ({home_team} vs {away_team}) possui dados gravados ({cur_sug_db}). Mantendo registro existente.")
                continue

            apostas_abstenção += 1
            # Preserva apostas existentes sem cancelamento automático via DAG
            # Atualiza fixtures_trends para abstenção se não houver aposta confirmada ou pendente preservada por falha de API
            cursor.execute("""
                SELECT id, palpite, odd, confirmada, status FROM apostas 
                WHERE fixture_id = %s 
                  AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
                  AND status NOT IN ('Não Confirmada', 'Cancelada')
                ORDER BY (status IN ('Ganha', 'Perdida', 'Meio Ganha', 'Meio Perdida', 'ANULADA')) DESC, confirmada DESC, id DESC
                LIMIT 1
            """, (fixture_id,))
            has_pending_bet = cursor.fetchone()
            if has_pending_bet:
                is_settled_blindado = has_pending_bet.get('status') in ('Ganha', 'Perdida', 'Meio Ganha', 'Meio Perdida', 'ANULADA')
                if is_settled_blindado:
                    print(f"🔒 [Aposta Liquidada] Partida #{fixture_id} já liquidada ({has_pending_bet.get('status')}). Mantida intacta.")
                    continue

                if has_pending_bet.get('confirmada') == 1:
                    print(f"🔒 [Aposta Confirmada Blindada] Partida #{fixture_id} ({home_team} vs {away_team}) já confirmada pelo usuário (#{has_pending_bet.get('id')}). Mantida intacta (auditoria de drift delegada exclusivamente à Sentinela).")
                    continue

                if is_api_missing:
                    print(f"🔒 [Card AH Preservado / Falha Temporária de API] Partida #{fixture_id} possui aposta ativa ({has_pending_bet.get('palpite')}). Mantendo registro existente.")
                    continue

                from asian_handicap_engine import determine_gatekeeper_category
                cat_desc = determine_gatekeeper_category('NO_BET', 'Sem Entrada (Abstenção)', detalhe_calculo)

                # Preserva a aposta como 'Pendente' / 'Não Confirmada' conforme diretriz operacional do Diário de Bordo (22/10/2026),
                # atualizando apenas a sinalização de risco do Gatekeeper e disparando o alerta consultivo no Sininho.
                cursor.execute("""
                    UPDATE apostas SET
                        status_gatekeeper = 'NO_BET',
                        gatekeeper_category = %s,
                        auditoria_ia_status = 'ALERTA_RISCO',
                        updated_at = NOW()
                    WHERE fixture_id = %s AND status IN ('Pendente', 'Não Confirmada') AND confirmada = 0
                """, (cat_desc, fixture_id))

                palpite_antigo = has_pending_bet.get('palpite') or ''
                odd_antiga = float(has_pending_bet.get('odd') or 0.0)
                motivo_especifico = ""
                try:
                    dyn_diag = analisar_mudanca_odd_cancelamento(
                        palpite_anterior=palpite_antigo,
                        odd_anterior=odd_antiga,
                        motivo_calculo=detalhe_calculo,
                        odd_home=float(fix.get('odd_home') or 0.0),
                        odd_draw=float(fix.get('odd_draw') or 0.0),
                        odd_away=float(fix.get('odd_away') or 0.0),
                        home_team=home_team,
                        away_team=away_team
                    )
                    motivo_especifico = dyn_diag.get('diagnostico_curto') or dyn_diag.get('nova_odd_curta') or ""
                except Exception:
                    pass

                if not motivo_especifico:
                    motivo_especifico = f"A linha {palpite_antigo} (@ {odd_antiga:.2f}) não atingiu valor esperado positivo (+EV) na Betano"

                msg_notif = (
                    f"A aposta {palpite_antigo} (@ {odd_antiga:.2f}) foi reclassificada para NO_BET ({cat_desc}). "
                    f"Motivo: {motivo_especifico}. Recomenda-se saída preventiva ou cancelamento."
                )

                # Dispara notificação no Sininho (notificacoes_usuario) para os usuários
                for uid in user_ids:
                    notif_id = registrar_notificacao_usuario(
                        cursor, uid, has_pending_bet['id'], fixture_id,
                        tipo="ALERTA_GATEKEEPER_NO_BET",
                        titulo=f"🚨 Alerta Gatekeeper: NO_BET em {home_team} vs {away_team}",
                        mensagem=msg_notif,
                        link=f"/apostas?fixture_id={fixture_id}"
                    )
                    if notif_id:
                        try:
                            from lifecycle_logger import log_lifecycle_event
                            log_lifecycle_event(
                                cursor=cursor,
                                fixture_id=fixture_id,
                                aposta_id=has_pending_bet['id'],
                                notificacao_id=notif_id,
                                notificacao_criada_em=datetime.now(),
                                componente='CRIADOR_APOSTAS_AH',
                                tipo_evento='RECLASSIFICACAO_NO_BET',
                                estado_anterior=f"{palpite_antigo} @ {odd_antiga:.2f}",
                                estado_novo='NO_BET',
                                delta={'palpite': palpite_antigo, 'odd': odd_antiga, 'categoria': cat_desc, 'motivo': motivo_especifico},
                                motivo=msg_notif
                            )
                        except Exception as e_log:
                            pass
                conn.commit()
                print(f"🚨 [Alerta NO_BET Notificado] Partida #{fixture_id} ({home_team} vs {away_team}): aposta pendente atualizada para NO_BET e usuário notificado.")
                continue

            cursor.execute("SELECT ah_reasoning, home_team_id, away_team_id FROM fixtures_trends WHERE fixture_id = %s", (fixture_id,))
            cur_f = cursor.fetchone()
            existing_r = cur_f.get("ah_reasoning") if cur_f else None
            h_tid = cur_f.get("home_team_id") if cur_f else None
            a_tid = cur_f.get("away_team_id") if cur_f else None

            compound_reasoning = compose_compound_ah_reasoning(
                cursor=cursor,
                fixture_id=fixture_id,
                main_calc=detalhe_calculo,
                suggestion='Sem Entrada (Abstenção)',
                home_team=home_team,
                away_team=away_team,
                home_team_id=h_tid,
                away_team_id=a_tid,
                existing_reasoning=existing_r
            )

            from asian_handicap_engine import determine_gatekeeper_category
            cat_desc = determine_gatekeeper_category('NO_BET', 'Sem Entrada (Abstenção)', detalhe_calculo or compound_reasoning)

            # =========================================================================
            # SUGESTÃO DE RADAR PARA OUTRAS CASAS (STAKE / PINNACLE / BET365)
            # REGRA ESTRITA: Apenas partidas que cumpram INTEGRALMENTE os requisitos do
            # Prisma PREMIUM ou do Prisma QUASE PREMIUM podem gerar sugestão de aposta.
            # =========================================================================
            if cat_desc == 'Falta de Valor Esperado (+EV)':
                cursor.execute("""
                    SELECT id, status FROM apostas 
                    WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
                    LIMIT 1
                """, (fixture_id,))
                ja_existe_aposta = cursor.fetchone()

                if not ja_existe_aposta:
                    try:
                        from checar_odds_ah_fixture import build_ah_reference_fair_lines
                        from asian_handicap_engine import get_team_u5j_from_db

                        o_h = float(fix.get('odd_home') or 0.0)
                        o_a = float(fix.get('odd_away') or 0.0)
                        fav_is_away = (o_a > 0 and o_h > 0 and o_a < o_h)
                        fav_team = away_team if fav_is_away else home_team
                        fav_odd = o_a if fav_is_away else o_h

                        h_l5 = get_team_u5j_from_db(cursor, fix.get('home_team_id'), home_team)
                        a_l5 = get_team_u5j_from_db(cursor, fix.get('away_team_id'), away_team)
                        cand_l5 = a_l5 if fav_is_away else h_l5
                        opp_l5 = h_l5 if fav_is_away else a_l5

                        cand_eff = float((cand_l5 or {}).get('pts_efficiency') or 0.0)
                        opp_eff = float((opp_l5 or {}).get('pts_efficiency') or 0.0)

                        # Validação contextual de visitante
                        is_away_dependent = False
                        if fav_is_away:
                            a_away_matches = [m for m in ((a_l5 or {}).get('matches') or []) if isinstance(m, dict) and m.get('is_home') is False]
                            a_away_wins = sum(1 for m in a_away_matches if m.get('result') == 'V')
                            a_away_pts = sum(3 if m.get('result') == 'V' else (1 if m.get('result') == 'E' else 0) for m in a_away_matches)
                            is_away_dependent = (bool(a_away_matches) and a_away_wins == 0 and a_away_pts <= 1)

                        # 1. PRISMA PREMIUM: Eficiência >= 24.0 pts E adversário frágil <= 10.0 pts E odd 1X2 <= 1.60
                        is_prisma_premium = (
                            cand_eff >= 24.0 and
                            opp_eff <= 10.0 and
                            fav_odd <= 1.60 and
                            not is_away_dependent
                        )

                        # 2. PRISMA QUASE PREMIUM: Eficiência >= 12.0 pts E superior ao adversário E odd 1X2 <= 2.20
                        is_prisma_quase_premium = (
                            not is_prisma_premium and
                            cand_eff >= 12.0 and
                            cand_eff > opp_eff and
                            fav_odd <= 2.20 and
                            not is_away_dependent
                        )

                        if is_prisma_premium or is_prisma_quase_premium:
                            m_xg = re.search(r'xG\s+[^0-9]*([0-9]+(?:\.[0-9]+)?)\s*x\s*([0-9]+(?:\.[0-9]+)?)', detalhe_calculo or '')
                            xg_h = float(m_xg.group(1)) if m_xg else 1.20
                            xg_a = float(m_xg.group(2)) if m_xg else 1.20
                            p_matrix = calculate_bivariate_poisson_matrix(xg_h, xg_a)

                            ref_lines = build_ah_reference_fair_lines(fix, p_matrix, home_team, away_team, o_h, o_a)

                            # Inicialização prévia de variáveis de controle de equilíbrio (Regra de Ouro 8)
                            is_confronto_equilibrado = False
                            diff_eff_pct = 0.0
                            dnb_cand = None

                            # Filtra as linhas conforme a regra específica de cada Prisma:
                            if is_prisma_premium:
                                # Premium autoriza linhas de dominância (-0.5, -0.75 AH)
                                cand_ref = [r for r in ref_lines if r['is_away'] == fav_is_away and r['line'] in (-0.75, -0.5)]
                                tier = BetTier.PREMIUM
                            else:
                                # Quase Premium:
                                # Em confrontos equilibrados (odd do favorito no 1X2 >= 1.90 OU vantagem de U5J <= 20% com oponente forte >= 9.0 pts),
                                # a linha recomendada pelo Radar DEVE SER ESTRITAMENTE a linha segura: DNB (0.0 AH)
                                diff_eff_pct = ((cand_eff - opp_eff) / max(cand_eff, 1.0)) if cand_eff > 0 else 0.0
                                is_confronto_equilibrado = (
                                    fav_odd >= 1.90 or
                                    (diff_eff_pct <= 0.20 and opp_eff >= 9.0)
                                )

                                if is_confronto_equilibrado:
                                    # Restringe estritamente à linha segura 0.0 AH (DNB) para evitar exposição a meio-red em clássicos
                                    cand_ref = [r for r in ref_lines if r['is_away'] == fav_is_away and r['line'] == 0.0]
                                    if not cand_ref:
                                        cand_ref = [r for r in ref_lines if r['is_away'] == fav_is_away and r['line'] in (-0.25, 0.0)]
                                else:
                                    # Confronto com dominância clara: autoriza -0.25 AH ou 0.0 AH
                                    cand_ref = [r for r in ref_lines if r['is_away'] == fav_is_away and r['line'] in (-0.25, 0.0)]

                                tier = BetTier.QUASE_PREMIUM

                            destaque_val = tier.value
                            badge_str = tier.label
                            cat_name = f"{'Oportunidade PREMIUM' if tier == BetTier.PREMIUM else 'Aposta Quase Premium'} (Sugestão Radar)"

                            if cand_ref:
                                # Prioriza linhas com odd mínima na faixa viável da banca (>= 1.50)
                                valid_ref = [r for r in cand_ref if r['odd_minima'] >= 1.50]
                                if not valid_ref and is_confronto_equilibrado:
                                    # Em clássicos/jogos equilibrados, ajusta a odd mínima de entrada para o piso da banca (1.50)
                                    # preservando a linha segura 0.0 AH com a sugestão de odd justa pelo radar
                                    dnb_cand = next((r for r in cand_ref if r['line'] == 0.0), cand_ref[0])
                                    dnb_cand['odd_minima'] = max(1.50, dnb_cand['odd_minima'])
                                    valid_ref = [dnb_cand]
                                elif not valid_ref:
                                    valid_ref = [cand_ref[0]]

                                if valid_ref:
                                    best_ref = valid_ref[0]
                                    sug_palpite = best_ref['linha']
                                    sug_odd_justa = float(best_ref['odd_justa'])
                                    sug_odd_minima = float(best_ref['odd_minima'])
                                    sug_prob = float(best_ref['prob_cobertura'])

                                    sug_msg = (
                                        f"💡 [{badge_str} / Radar de Oportunidade] A Betano não abriu cotações viáveis com +EV. "
                                        f"Linha teórica de valor recomendada: {sug_palpite}. "
                                        f"Odd Justa: {sug_odd_justa:.2f} | Odd Mínima Aceitável: @ {sug_odd_minima:.2f} "
                                        f"(Cobertura de {sug_prob:.1f}%). Busque esta cotação na Stake, Pinnacle ou Bet365."
                                    )

                                    for uid in user_ids:
                                        cursor.execute("""
                                            INSERT INTO apostas (
                                                usuario_id, fixture_id, time_casa, time_fora, mercado, casa_de_aposta, palpite,
                                                odd, odd_justa, probabilidade_poisson, ev_percentual,
                                                status_gatekeeper, gatekeeper_category, data_hora_jogo,
                                                valor_aposta, ganhos_potenciais, tipo, status, confirmada, destaque,
                                                resultado_detalhado, criado_em, updated_at
                                            ) VALUES (
                                                %s, %s, %s, %s, 'Handicap Asiático', 'Sugestão Radar (Stake / Outras)', %s,
                                                %s, %s, %s, 5.0,
                                                'SUGESTAO_RADAR', %s, %s,
                                                10.00, %s, 'Sugestão', 'Não Confirmada', 0, %s,
                                                %s, NOW(), NOW()
                                            )
                                        """, (
                                            uid, fixture_id, home_team, away_team, sug_palpite,
                                            sug_odd_minima, sug_odd_justa, sug_prob,
                                            cat_name, fix.get('fixture_date'), round(10.0 * sug_odd_minima, 2),
                                            destaque_val, sug_msg
                                        ))
                                        new_aposta_id = cursor.lastrowid
                                        # Log transversal de ciclo de vida com rastreamento completo
                                        try:
                                            from lifecycle_logger import log_lifecycle_event
                                            log_lifecycle_event(
                                                cursor=cursor,
                                                fixture_id=fixture_id,
                                                aposta_id=new_aposta_id,
                                                notificacao_id=None,
                                                notificacao_criada_em=None,
                                                componente='CRIADOR_APOSTAS_AH',
                                                tipo_evento='SUGESTAO_RADAR_CRIADA',
                                                estado_anterior='NO_BET (Betano)',
                                                estado_novo=f"{sug_palpite} (Não Confirmada)",
                                                delta={
                                                    'linha': sug_palpite,
                                                    'odd_minima': sug_odd_minima,
                                                    'odd_justa': sug_odd_justa,
                                                    'cobertura': sug_prob,
                                                    'prisma': 'PREMIUM' if is_prisma_premium else 'QUASE_PREMIUM'
                                                },
                                                motivo=sug_msg
                                            )
                                        except Exception:
                                            pass
                                    conn.commit()
                                    print(f"📡 [{badge_str} Gerada] #{fixture_id} {home_team} vs {away_team} -> {sug_palpite} (Odd Justa: {sug_odd_justa:.2f} | Odd Mínima: @ {sug_odd_minima:.2f}) [Status: Não Confirmada]")
                    except Exception as e_sug:
                        print(f"Aviso ao gerar sugestão de radar para #{fixture_id}: {e_sug}")

            from asian_handicap_engine import get_cached_betano_1x2
            b1x2 = get_cached_betano_1x2(fixture_id)
            if b1x2 and b1x2.get('casa') and b1x2.get('empate') and b1x2.get('visitante'):
                bm_label = b1x2.get('bookmaker', 'Betano').capitalize()
                cursor.execute("""
                    UPDATE fixtures_trends SET
                        ah_suggestion = 'Sem Entrada (Abstenção)',
                        ah_confidence = 50.00,
                        ah_reasoning = %s,
                        gatekeeper_category = %s,
                        odd_home = %s,
                        casa_odd_home = %s,
                        odd_draw = %s,
                        casa_odd_draw = %s,
                        odd_away = %s,
                        casa_odd_away = %s,
                        updated_at = NOW()
                    WHERE fixture_id = %s
                """, (compound_reasoning, cat_desc, b1x2['casa'], bm_label, b1x2['empate'], bm_label, b1x2['visitante'], bm_label, fixture_id))
            else:
                cursor.execute("""
                    UPDATE fixtures_trends SET
                        ah_suggestion = 'Sem Entrada (Abstenção)',
                        ah_confidence = 50.00,
                        ah_reasoning = %s,
                        gatekeeper_category = %s,
                        updated_at = NOW()
                    WHERE fixture_id = %s
                """, (compound_reasoning, cat_desc, fixture_id))
            continue

        eval_res = best_cand['eval']
        odd_val = best_cand['odd']
        odd_justa = eval_res['odd_justa']
        prob_poisson = eval_res['prob_eff']
        ev_perc = eval_res['ev_percent']
        ref_uid = user_ids[0] if user_ids else 558
        cursor.execute("SELECT stake_padrao FROM metas_diarias_config WHERE usuario_id = %s AND is_ativa = 1 ORDER BY id DESC LIMIT 1", (ref_uid,))
        m_row = cursor.fetchone()
        valor_aposta = float(m_row['stake_padrao']) if (m_row and m_row.get('stake_padrao')) else 10.00
        if valor_aposta <= 0.0:
            valor_aposta = 10.00
        ganhos_potenciais = round(valor_aposta * odd_val, 2)

        # Checa se já existe aposta ativa ou cancelada pelo usuário para esta partida no banco
        cursor.execute("""
            SELECT id, palpite, confirmada, status, status_gatekeeper, resultado_detalhado FROM apostas 
            WHERE fixture_id = %s 
              AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
            ORDER BY id DESC LIMIT 1
        """, (fixture_id,))
        ja_tem_aposta = cursor.fetchone()

        # Se a aposta existente foi cancelada pelo usuário, preserva o cancelamento e ignora nova candidatura
        if ja_tem_aposta and ja_tem_aposta.get('status') == 'Cancelada':
            is_user_cancel = (
                ja_tem_aposta.get('status_gatekeeper') == 'CANCELADA_USUARIO' or
                'cancelada pelo usuário' in str(ja_tem_aposta.get('resultado_detalhado') or '').lower() or
                'indisponível' in str(ja_tem_aposta.get('resultado_detalhado') or '').lower()
            )
            if is_user_cancel:
                print(f"🛑 [Trava Cancelamento Usuário] Partida #{fixture_id} possui aposta #{ja_tem_aposta['id']} cancelada manualmente pelo usuário. Ignorando nova candidatura.")
                continue

        if ja_tem_aposta and ja_tem_aposta.get('status') not in ('Não Confirmada', 'Cancelada'):
            # Já existe aposta ativa: sincroniza imediatamente (não consome nova vaga diária)
            c_cnt, u_cnt, s_cnt = sync_fixture_and_bet_handicap(
                cursor=cursor,
                fixture_id=fixture_id,
                home_team=home_team,
                away_team=away_team,
                fixture_date=fixture_date,
                selected_palpite=selected_palpite,
                odd_val=odd_val,
                odd_justa=odd_justa,
                prob_poisson=prob_poisson,
                ev_perc=ev_perc,
                detalhe_calculo=detalhe_calculo,
                user_ids=user_ids,
                confirmada_val=confirmada_val,
                destaque_val=int(best_cand.get('destaque', 0)),
                best_cand=best_cand
            )
            apostas_criadas += c_cnt
            apostas_duplicadas += (u_cnt + s_cnt)

            if c_cnt > 0:
                novas_apostas_detalhes.append({
                    'usuario_id': user_ids[0] if user_ids else 558,
                    'time_casa': home_team,
                    'time_fora': away_team,
                    'palpite': selected_palpite,
                    'odd': odd_val,
                    'casa_de_aposta': (best_cand.get('bookmaker') or best_cand.get('source') or 'Betano'),
                    'valor_aposta': valor_aposta,
                    'ganhos_potenciais': ganhos_potenciais,
                    'data_hora_jogo': fixture_date
                })
        else:
            # Nova candidata: armazena para triagem diária e priorização de Top EV
            dt_jogo_str = ""
            if isinstance(fixture_date, datetime):
                try:
                    dt_local = fixture_date - timedelta(hours=3)
                    dt_jogo_str = dt_local.strftime('%Y-%m-%d')
                except Exception:
                    dt_jogo_str = str(fixture_date)[:10]
            elif isinstance(fixture_date, str) and len(fixture_date) >= 10:
                dt_jogo_str = fixture_date[:10]

            novas_candidatas_aprovadas.append({
                'fix': fix,
                'fixture_id': fixture_id,
                'home_team': home_team,
                'away_team': away_team,
                'fixture_date': fixture_date,
                'dt_jogo_str': dt_jogo_str,
                'selected_palpite': selected_palpite,
                'odd_val': odd_val,
                'odd_justa': odd_justa,
                'prob_poisson': prob_poisson,
                'ev_perc': ev_perc,
                'detalhe_calculo': detalhe_calculo,
                'valor_aposta': valor_aposta,
                'ganhos_potenciais': ganhos_potenciais,
                'best_cand': best_cand
            })

    # Triagem das novas candidatas respeitando a Trava Diária de 10 Apostas (Priorização Top EV)
    if novas_candidatas_aprovadas:
        print(f"\n🎯 [Triagem Top EV] Avaliando {len(novas_candidatas_aprovadas)} nova(s) candidata(s) de AH sob a trava diária de até 10 apostas.")
        cands_por_data = {}
        for cand in novas_candidatas_aprovadas:
            d_key = cand['dt_jogo_str'] or 'sem_data'
            cands_por_data.setdefault(d_key, []).append(cand)

        for d_key, cands_lista in cands_por_data.items():
            cursor.execute("""
                SELECT COUNT(DISTINCT fixture_id) as total_dia
                FROM apostas
                WHERE DATE(CONVERT_TZ(data_hora_jogo, '+00:00', '-03:00')) = %s
                  AND status NOT IN ('Cancelada', 'Não Confirmada')
            """, (d_key,))
            r_cnt = cursor.fetchone()
            apostas_ativas_dia = int(r_cnt.get('total_dia', 0)) if r_cnt else 0
            vagas_restantes = max(0, 10 - apostas_ativas_dia)

            print(f"📅 Data {d_key} | Apostas ativas hoje: {apostas_ativas_dia}/10 | Vagas disponíveis: {vagas_restantes} | Candidatas: {len(cands_lista)}")

            # Ordena por maior EV decrescente e desempate por probabilidade efetiva
            cands_lista.sort(key=lambda c: (float(c['ev_perc'] or 0), float(c['prob_poisson'] or 0)), reverse=True)

            cands_aceitas = cands_lista[:vagas_restantes]
            cands_excedentes = cands_lista[vagas_restantes:]

            for cand in cands_aceitas:
                c_cnt, u_cnt, s_cnt = sync_fixture_and_bet_handicap(
                    cursor=cursor,
                    fixture_id=cand['fixture_id'],
                    home_team=cand['home_team'],
                    away_team=cand['away_team'],
                    fixture_date=cand['fixture_date'],
                    selected_palpite=cand['selected_palpite'],
                    odd_val=cand['odd_val'],
                    odd_justa=cand['odd_justa'],
                    prob_poisson=cand['prob_poisson'],
                    ev_perc=cand['ev_perc'],
                    detalhe_calculo=cand['detalhe_calculo'],
                    user_ids=user_ids,
                    confirmada_val=confirmada_val,
                    destaque_val=int(cand['best_cand'].get('destaque', 0)),
                    best_cand=cand['best_cand']
                )
                apostas_criadas += c_cnt
                apostas_duplicadas += (u_cnt + s_cnt)
                if c_cnt > 0:
                    novas_apostas_detalhes.append({
                        'usuario_id': user_ids[0] if user_ids else 558,
                        'time_casa': cand['home_team'],
                        'time_fora': cand['away_team'],
                        'palpite': cand['selected_palpite'],
                        'odd': cand['odd_val'],
                        'casa_de_aposta': (cand['best_cand'].get('bookmaker') or cand['best_cand'].get('source') or 'Betano'),
                        'valor_aposta': cand['valor_aposta'],
                        'ganhos_potenciais': cand['ganhos_potenciais'],
                        'data_hora_jogo': cand['fixture_date']
                    })
                bm_name_log = cand['best_cand'].get('bookmaker') or cand['best_cand'].get('source') or 'Betano'
                print(f"🟢 [Top 10 AH Aposta Criada] #{cand['fixture_id']} {cand['home_team']} vs {cand['away_team']} | {cand['selected_palpite']} @ {cand['odd_val']} ({bm_name_log}) | EV: +{cand['ev_perc']:.1f}%")

            for cand in cands_excedentes:
                h_tid = cand['fix'].get('home_team_id')
                a_tid = cand['fix'].get('away_team_id')
                existing_r = cand['fix'].get('ah_reasoning')
                msg_limite = f"{cand['detalhe_calculo']} || GESTÃO DE RISCO: Limite diário de 10 apostas atingido. Entrada qualificada preservada no card sem aposta financeira emitida."
                compound_reasoning = compose_compound_ah_reasoning(
                    cursor=cursor,
                    fixture_id=cand['fixture_id'],
                    main_calc=msg_limite,
                    suggestion=cand['selected_palpite'],
                    home_team=cand['home_team'],
                    away_team=cand['away_team'],
                    home_team_id=h_tid,
                    away_team_id=a_tid,
                    existing_reasoning=existing_r
                )
                from asian_handicap_engine import determine_gatekeeper_category
                cat_desc = determine_gatekeeper_category('APROVADO', cand['selected_palpite'], cand['detalhe_calculo'], cand['best_cand'])
                cursor.execute("""
                    UPDATE fixtures_trends SET
                        ah_suggestion = %s,
                        ah_confidence = %s,
                        ah_reasoning = %s,
                        gatekeeper_category = %s,
                        updated_at = NOW()
                    WHERE fixture_id = %s
                """, (cand['selected_palpite'], cand['prob_poisson'], compound_reasoning, cat_desc, cand['fixture_id']))
                print(f"🛑 [Trava Diária Top 10] Partida #{cand['fixture_id']} ({cand['home_team']} vs {cand['away_team']}) com EV +{cand['ev_perc']:.1f}% excedeu o limite diário de 10 apostas. Aposta financeira não criada.")

    print("\n=======================================================")
    print(f"✅ PROCESSAMENTO DE APOSTAS AH BETANO CONCLUÍDO!")
    print(f"📊 Novas Apostas Criadas: {apostas_criadas}")
    print(f"🚫 Apostas Canceladas / Estornadas: {apostas_canceladas}")
    print(f"🔄 Apostas Já Existentes Mantidas: {apostas_duplicadas}")
    print(f"🛡️ Jogos com Abstenção/Bloqueio: {apostas_abstenção}")
    print("=======================================================")

    if novas_apostas_detalhes or apostas_canceladas_detalhes:
        recipient = os.environ.get("AH_BETS_EMAIL_RECIPIENT", "paulomnasc@gmail.com")
        # send_handicap_bets_email(novas_apostas_detalhes, apostas_canceladas_detalhes, recipient=recipient)

    conn.close()

if __name__ == '__main__':
    target_date = sys.argv[1] if len(sys.argv) > 1 else None
    confirmada_arg = int(sys.argv[2]) if len(sys.argv) > 2 else 0
    criar_apostas_handicap_diario(target_date, confirmada_arg)
