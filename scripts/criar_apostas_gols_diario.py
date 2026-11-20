#!/usr/bin/env python3
"""
Script de Criação Diária de Apostas em Gols Asiáticos (Airflow DAG Worker / Web Service)
Executado periodicamente para verificar jogos em aberto (fuso horário local Brasil -03:00),
verificar a disponibilidade e valor das linhas na Betano (Bookmaker ID 32 e Betano Direct API),
aplicar o Gatekeeper estatístico de Poisson (+EV >= 3.0%, Sweet Spot 1.65 - 2.25),
criar apostas na tabela 'apostas' (mercado = 'Gols Asiáticos') para os usuários elegíveis,
e enviar notificações por e-mail com as movimentações realizadas.
"""

import sys
import os
import re
import math
import requests
import pymysql
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from datetime import datetime, timedelta

# Importar configurações de ligas e banco de dados
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from leagues_config import ALLOWED_LEAGUES, ALLOWED_LEAGUE_IDS, ALLOWED_LEAGUE_NAMES, is_allowed_league
except Exception:
    ALLOWED_LEAGUE_IDS = set()
    ALLOWED_LEAGUE_NAMES = []
    def is_allowed_league(league_id, league_name: str = "", fixture_date=None) -> bool:
        return True

from db_config import get_db_connection, get_live_env_vars
from asian_total_engine import (
    calculate_unified_goals_recommendation,
    sync_fixture_and_bet_goals,
    fetch_betano_goals_lines
)

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


def format_game_date_brt(data_j):
    if not data_j:
        return "-"
    if isinstance(data_j, str):
        try:
            data_j = datetime.fromisoformat(data_j.replace('Z', '+00:00'))
        except Exception:
            return data_j
    return data_j.strftime("%d/%m/%Y %H:%M")


def send_goals_bets_email(novas_apostas, apostas_canceladas=None, recipient="paulomnasc@gmail.com"):
    """
    Envia e-mail formatado em HTML com as apostas criadas no mercado de Gols Asiáticos.
    """
    if not novas_apostas and not apostas_canceladas:
        return

    apostas_canceladas = apostas_canceladas or []
    env = get_live_env_vars()
    smtp_host = env.get("SMTP_HOST") or os.environ.get("SMTP_HOST", "smtp-relay.brevo.com")
    smtp_port = int(env.get("SMTP_PORT") or os.environ.get("SMTP_PORT", 587))
    smtp_user = env.get("SMTP_USER") or os.environ.get("SMTP_USER", "")
    smtp_pass = env.get("SMTP_PASSWORD") or os.environ.get("SMTP_PASSWORD", "")
    smtp_from = env.get("SMTP_FROM_EMAIL") or os.environ.get("SMTP_FROM_EMAIL", "admin@estudotabela.com.br")
    smtp_from_name = env.get("SMTP_FROM_NAME") or os.environ.get("SMTP_FROM_NAME", "MyDataFlow Gols Asiáticos")

    agora_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")
    num_novas = len(novas_apostas)
    num_canc = len(apostas_canceladas)

    subject_parts = []
    if num_novas > 0:
        subject_parts.append(f"🟢 {num_novas} Nova(s) Sugestão(ões)")
    if num_canc > 0:
        subject_parts.append(f"🚫 {num_canc} Cancelada(s)")
    
    subject = f"⚽ [Gols Asiáticos Betano] {' | '.join(subject_parts)} - {agora_str}"

    rows_novas_html = ""
    for aposta in novas_apostas:
        tc = aposta.get('time_casa', '-')
        tv = aposta.get('time_fora', '-')
        data_j = format_game_date_brt(aposta.get('data_hora_jogo'))
        palpite = aposta.get('palpite', '-')
        odd = float(aposta.get('odd', 0.0))
        valor = float(aposta.get('valor_aposta', 10.0))
        ganhos = float(aposta.get('ganhos_potenciais', 0.0))
        moeda_cod = aposta.get('moeda_codigo', 'USDT')

        rows_novas_html += f"""
        <tr style="border-bottom: 1px solid #e0e0e0;">
            <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}<br><span style="color: #666; font-weight: normal; font-size: 11px;">{data_j}</span></td>
            <td style="padding: 10px; font-size: 13px; color: #16a34a; font-weight: bold;">{palpite}</td>
            <td style="padding: 10px; font-size: 13px; font-weight: bold; text-align: center;">{odd:.2f}</td>
            <td style="padding: 10px; font-size: 13px; text-align: right;">{moeda_cod} {valor:.2f}</td>
            <td style="padding: 10px; font-size: 13px; font-weight: bold; color: #0284c7; text-align: right;">{moeda_cod} {ganhos:.2f}</td>
        </tr>
        """

    html = f"""
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"></head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px;">
        <div style="max-width: 650px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            <div style="background-color: #0f172a; padding: 20px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 18px; letter-spacing: 0.5px;">⚽ Esteira Autônoma de Gols Asiáticos (Betano)</h1>
                <p style="color: #94a3b8; margin: 5px 0 0 0; font-size: 12px;">Relatório de Entradas com Valor Esperado (+EV) e Projeção de Poisson</p>
            </div>
            <div style="padding: 20px;">
                <p style="font-size: 13px; color: #334155;">Olá, foram identificadas e criadas <b>{num_novas}</b> nova(s) entrada(s) elegíveis pelo Gatekeeper:</p>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr style="background-color: #f1f5f9; text-align: left; font-size: 11px; text-transform: uppercase; color: #475569;">
                            <th style="padding: 8px 10px;">Partida</th>
                            <th style="padding: 8px 10px;">Palpite</th>
                            <th style="padding: 8px 10px; text-align: center;">Odd</th>
                            <th style="padding: 8px 10px; text-align: right;">Stake</th>
                            <th style="padding: 8px 10px; text-align: right;">Retorno</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows_novas_html}
                    </tbody>
                </table>
            </div>
            <div style="background-color: #f8fafc; padding: 15px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
                Pipeline MyDataFlow • Modelo Bivariado de Poisson • Gestão de Risco Automatizada
            </div>
        </div>
    </body>
    </html>
    """

    try:
        msg = MIMEMultipart("alternative")
        msg["Subject"] = subject
        msg["From"] = f"{smtp_from_name} <{smtp_from}>"
        msg["To"] = recipient
        msg.attach(MIMEText(html, "html"))

        with smtplib.SMTP(smtp_host, smtp_port, timeout=15) as server:
            if smtp_port == 587:
                server.starttls()
            if smtp_user and smtp_pass:
                server.login(smtp_user, smtp_pass)
            server.sendmail(smtp_from, [recipient], msg.as_string())
        print(f"📧 [E-mail Gols Asiáticos] Relatório enviado com sucesso para {recipient}")
    except Exception as e_mail:
        print(f"⚠️ [E-mail Gols Asiáticos] Falha ao enviar relatório por e-mail: {e_mail}", file=sys.stderr)


def registrar_notificacao_usuario(cursor, usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link):
    """
    Registra notificação in-app na tabela notificacoes_usuario se existir.
    """
    try:
        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, criada_em
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, 0, NOW()
            )
        """, (usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link))
    except Exception:
        pass


def criar_apostas_gols_diario(target_date_str=None, confirmada=1):
    """
    Rotina Principal: Varre os jogos em aberto da data alvo (ou hoje/amanhã),
    consulta a Betano, avalia no Gatekeeper de Poisson e insere as apostas aprovadas.
    """
    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        now_dt = datetime.now()
        if not target_date_str:
            target_date_str = now_dt.strftime("%Y-%m-%d")

        print(f"🚀 [Gols Asiáticos] Iniciando verificação para a data {target_date_str} (Confirmada={confirmada})...")

        # Selecionar usuários elegíveis
        user_ids = get_all_user_ids(cursor)
        print(f"👥 Usuários elegíveis para receber apostas de Gols Asiáticos: {user_ids}")

        # Buscar partidas em aberto para a data selecionada (fuso BRT)
        cursor.execute("""
            SELECT fixture_id, home_team, away_team, league_id, league_name,
                   fixture_date, status, odd_home, odd_draw, odd_away,
                   home_team_id, away_team_id
            FROM fixtures_trends
            WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
              AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
            ORDER BY fixture_date ASC
        """, (target_date_str,))
        fixtures = cursor.fetchall()

        if not fixtures:
            print(f"ℹ️ [Gols Asiáticos] Nenhuma partida aberta encontrada para {target_date_str}.")
            return

        print(f"📋 Encontradas {len(fixtures)} partidas abertas para processamento em {target_date_str}.")

        novas_apostas_relatorio = []

        for f in fixtures:
            fid = f['fixture_id']
            home = f['home_team']
            away = f['away_team']
            lid = f.get('league_id')
            lname = f.get('league_name') or ''

            # Filtro de Ligas Permitidas
            if not is_allowed_league(lid, lname, f.get('fixture_date')):
                continue

            # Avaliar recomendação unificada de Gols Asiáticos
            status, palpite_sug, confianca, reasoning, best_cand, approved_list = calculate_unified_goals_recommendation(
                fixture_dict=f,
                cursor=cursor
            )

            if status != 'APPROVED' or not best_cand:
                print(f"⏩ [Abstenção] Partida #{fid} ({home} x {away}): {reasoning[:80]}...")
                continue

            print(f"🎯 [APROVADA GK] Partida #{fid} ({home} x {away}) -> Sugestão: {palpite_sug} @ {best_cand['odd']:.2f} (+EV {best_cand['ev_pct']:.1f}%)")

            # Persistir aposta para os usuários
            inserted = sync_fixture_and_bet_goals(
                cursor=cursor,
                fixture_dict=f,
                best_cand=best_cand,
                reasoning=reasoning,
                user_ids=user_ids,
                confirmada=confirmada
            )

            if inserted > 0:
                conn.commit()
                # Buscar aposta recém-criada para o relatório por e-mail com a moeda e stake reais
                cursor.execute("""
                    SELECT valor_aposta, ganhos_potenciais, moeda_codigo
                    FROM apostas
                    WHERE fixture_id = %s AND mercado = 'Gols Asiáticos'
                    ORDER BY id DESC LIMIT 1
                """, (fid,))
                b_info = cursor.fetchone()
                val_ap = float(b_info['valor_aposta']) if b_info else 10.0
                ganh_pot = float(b_info['ganhos_potenciais']) if b_info else round(val_ap * best_cand['odd'], 2)
                moeda_c = b_info['moeda_codigo'] if b_info else 'USDT'

                novas_apostas_relatorio.append({
                    'time_casa': home,
                    'time_fora': away,
                    'data_hora_jogo': f.get('fixture_date'),
                    'palpite': palpite_sug,
                    'odd': best_cand['odd'],
                    'valor_aposta': val_ap,
                    'ganhos_potenciais': ganh_pot,
                    'moeda_codigo': moeda_c
                })

        print(f"✅ [Gols Asiáticos] Concluído. Total de {len(novas_apostas_relatorio)} novas partidas com entradas geradas.")

        if novas_apostas_relatorio:
            send_goals_bets_email(novas_apostas_relatorio)

    except Exception as e:
        conn.rollback()
        print(f"❌ [Gols Asiáticos] Erro crítico durante processamento: {e}", file=sys.stderr)
        raise
    finally:
        cursor.close()
        conn.close()


if __name__ == '__main__':
    t_date = sys.argv[1] if len(sys.argv) > 1 else None
    if t_date in ('', 'None', 'none'):
        t_date = None
    conf = int(sys.argv[2]) if len(sys.argv) > 2 else 1
    criar_apostas_gols_diario(target_date_str=t_date, confirmada=conf)
