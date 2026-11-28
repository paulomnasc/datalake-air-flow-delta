#!/usr/bin/env python3
"""
scripts/radar_reversao_nobet_sweetspot.py

Módulo do Radar de Reversão de NO_BET por Flutuação de Odds no Sweet Spot.
FootballWeb Pipeline - Motor Ofensivo de Repescagem de Valor (+EV).

Objetivo:
1. Varre partidas monitoradas em fixtures_trends que receberam 'NO_BET' (Abstenção)
   pela manhã por estarem fora do Sweet Spot (ex: odd < 1.60 ou odd > 1.95) ou por
   margem temporária insuficiente;
2. Consulta as cotações atualizadas da Betano (Bookmaker ID 32 / Cache MySQL);
3. Reavalia a partida nos motores canônicos de Handicap Asiático (asian_handicap_engine)
   e Gols Asiáticos (asian_total_engine);
4. Se a nova cotação atender INTEGRALMENTE aos critérios de Alta Assertividade:
   - Odd dentro do Sweet Spot seguro: 1.60 a 1.95;
   - Probabilidade efetiva de acerto >= 60.0%;
   - Valor Esperado Positivo (+EV >= 5.0%);
5. Reverte dinamicamente o status de NO_BET para APROVADO / Sinal Verde:
   - Atualiza fixtures_trends preservando 100% da integridade do payload U5J (Regra 16);
   - Insere a aposta de valor na tabela apostas para os usuários elegíveis;
   - Dispara notificação push mobile imediata (ntfy no canal footbalweb_sentinela);
   - Registra notificação in-app no Sininho (notificacoes_usuario).

Diretrizes e Regras do Repositório:
- Regra 1: Cache-First no Banco de Dados (MySQL).
- Regra 2: Surebets permanentemente desativadas.
- Regra 8: Inicialização prévia mandatória de variáveis numéricas locais.
- Regra 9: Proibição absoluta de fallbacks artificiais.
- Regra 15: Sem supressão silenciosa de exceções de banco de dados.
- Regra 16: Preservação obrigatória de U5J via compose_compound_ah_reasoning.
- Regra 18: Zero credenciais no código (leitura via db_config.py).
"""

import os
import sys
import argparse
from datetime import datetime, timedelta, timezone

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars
from leagues_config import is_allowed_league

from asian_handicap_engine import (
    calculate_unified_handicap_recommendation,
    sync_fixture_and_bet_handicap,
    compose_compound_ah_reasoning,
    determine_gatekeeper_category,
    BetTier
)

from asian_total_engine import (
    calculate_unified_goals_recommendation,
    sync_fixture_and_bet_goals
)

try:
    from lifecycle_logger import log_lifecycle_event
except ImportError:
    log_lifecycle_event = None


def get_eligible_user_ids(cursor) -> list:
    """
    Retorna a lista de IDs de usuários elegíveis para receber apostas automatizadas.
    """
    cursor.execute("""
        SELECT id FROM usuario 
        WHERE (grok_credits >= 0.5 OR status_assinatura = 'active' OR id = 558)
        ORDER BY id ASC
    """)
    rows = cursor.fetchall()
    if rows:
        return [r['id'] if isinstance(r, dict) else r[0] for r in rows]
    return [558]


def enviar_push_ntfy_reversao(titulo: str, mensagem: str, tags: str = "arrows_counterclockwise,dart,moneybag", fixture_id: int = None):
    """
    Dispara notificação push mobile via ntfy.sh no tópico canônico footbalweb_sentinela.
    Publica via payload JSON (UTF-8 nativo) garantindo legibilidade perfeita de acentos e emojis.
    """
    import requests
    
    env_vars = get_live_env_vars()
    lineups_only = os.environ.get("NTFY_LINEUPS_ONLY") or env_vars.get("NTFY_LINEUPS_ONLY", "false")
    if str(lineups_only).strip().lower() in ("true", "1", "yes", "on"):
        return False
    topic = os.environ.get("NTFY_TOPIC") or env_vars.get("NTFY_TOPIC", "footbalweb_sentinela")
    server = (os.environ.get("NTFY_SERVER") or env_vars.get("NTFY_SERVER", "https://ntfy.sh")).rstrip("/")
    base_url = (os.environ.get("NTFY_BASE_URL") or env_vars.get("NTFY_BASE_URL", "https://cristalbet.com.br")).rstrip("/")
    click_url = f"{base_url}/apostas?fixture_id={fixture_id}" if fixture_id else f"{base_url}/apostas"
    
    tags_list = [t.strip() for t in tags.split(",") if t.strip()] if isinstance(tags, str) else (tags or [])

    payload = {
        "topic": topic,
        "title": titulo,
        "message": mensagem,
        "priority": "high",
        "tags": tags_list,
        "click": click_url
    }

    try:
        resp = requests.post(
            f"{server}",
            json=payload,
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=10
        )
        if resp.status_code in (200, 201):
            print(f"📲 [Push ntfy Enviado] {titulo}")
            return True
        else:
            print(f"⚠️ [Push ntfy Falha] Status code {resp.status_code}: {resp.text}")
    except Exception as e_ntfy:
        print(f"⚠️ [Push ntfy Falha] Não foi possível entregar push: {e_ntfy}")
    return False


def registrar_notificacao_sininho(cursor, usuario_id: int, fixture_id: int, aposta_id: int, titulo: str, mensagem: str, link: str):
    """
    Registra notificação in-app na tabela notificacoes_usuario para exibição no Sininho.
    """
    try:
        cursor.execute("""
            INSERT INTO notificacoes_usuario (
                usuario_id, aposta_id, fixture_id, tipo, titulo, mensagem, link, lida, pinada, criado_em
            ) VALUES (%s, %s, %s, 'OPORTUNIDADE_REVERSAO', %s, %s, %s, 0, 1, NOW())
        """, (usuario_id, aposta_id, fixture_id, titulo, mensagem, link))
        return True
    except Exception as e_notif:
        print(f"⚠️ [Notificação Sininho Erro] {e_notif}")
        return False


def check_antecipation_risk_goals(fix: dict, best_cand: dict) -> tuple:
    """
    Aplica a blindagem contextual para emissão de apostas em Gols Asiáticos com mais de 12 horas de antecedência.
    Retorna: (is_approved: bool, reject_reason: str)
    
    Regras de Antecipação no Radar de Reversão:
    1. Janela de Antecipação: aplica-se quando faltarem mais de 12 horas para o início da partida.
    2. Trava de Linha Inteira (Segurança de Capital): apenas linhas inteiras (2.0 e 3.0 Gols com Push integral) são permitidas.
    3. Trava de BetTier (Qualidade Máxima): apenas apostas PREMIUM (Win-Rate >= 68.0% e +EV >= 15.0%) são autorizadas em D+1.
    4. Trava Anti-Descompromisso / Fim de Temporada (Caso Chapecoense x Palmeiras):
       - Se rodada final (últimas 4 rodadas em pontos corridos ou rodadas >= 34) e houver time despromovido contra time garantido sem disputa de título.
    5. Trava Anti-Goleada e Superfavorito Extremo (Caso Sabah Baku x Barcelona):
       - Se odd 1X2 <= 1.20, veta Under sumariamente e veta Over sem escalação confirmada em T-40.
    6. Trava de Mata-Mata Eliminatório:
       - Fases de oitavas, quartas, semi ou final de copas eliminatórias requerem validação pré-jogo com chaveamento.
    """
    import re
    diff_secs = 0.0
    diff_hours = 0.0
    cand_line = 0.0
    round_num = 0
    h_rank = 0
    a_rank = 0
    odd_h = 0.0
    odd_a = 0.0
    min_odd = 0.0

    f_date = fix.get('fixture_date')
    if not f_date:
        return True, ""

    now_utc = datetime.now(timezone.utc).replace(tzinfo=None)
    diff_secs = (f_date - now_utc).total_seconds()
    diff_hours = diff_secs / 3600.0

    # Se faltarem 12 horas ou menos (D+0 / pré-jogo), segue o fluxo padrão sem restrição de D+1
    if diff_hours <= 12.0:
        return True, ""

    cand_line = float(best_cand.get('line') or 0.0)
    cand_type = str(best_cand.get('type') or 'Under')
    cand_tier = best_cand.get('tier')

    # 1. Trava de Linha Inteira (Segurança de Capital e Push integral)
    if cand_line not in (2.0, 3.0):
        return False, f"Linha fracionada ({cand_line}). Antecipação (>12h) autorizada exclusivamente para linhas inteiras de segurança (2.0 e 3.0 com Push integral)."

    # 2. Trava de BetTier (Apenas PREMIUM na antecipação)
    if cand_tier != BetTier.PREMIUM:
        tier_nome = cand_tier.label if hasattr(cand_tier, 'label') else str(cand_tier)
        return False, f"Classificação {tier_nome}. Antecipação (>12h) requer estritamente selo PREMIUM (+EV >= 15.0% e Probabilidade >= 68.0%)."

    # 3. Trava Anti-Descompromisso / Fim de Temporada (Caso Chapecoense x Palmeiras)
    l_round = str(fix.get('league_round') or '').lower()
    h_zone = str(fix.get('home_zone') or '').lower()
    a_zone = str(fix.get('away_zone') or '').lower()
    h_rank = int(fix.get('home_rank') or 0)
    a_rank = int(fix.get('away_rank') or 0)

    m_round = re.search(r'(\d+)', l_round)
    round_num = int(m_round.group(1)) if m_round else 0
    if round_num >= 34 or any(k in l_round for k in ('35', '36', '37', '38')):
        h_releg = any(k in h_zone for k in ('relegation', 'rebaixamento', 'serie b')) or h_rank >= 19
        a_releg = any(k in a_zone for k in ('relegation', 'rebaixamento', 'serie b')) or a_rank >= 19
        h_safe = any(k in h_zone for k in ('libertadores', 'champions', 'promotion')) or (1 <= h_rank <= 3)
        a_safe = any(k in a_zone for k in ('libertadores', 'champions', 'promotion')) or (1 <= a_rank <= 3)
        if (h_releg and a_safe) or (a_releg and h_safe):
            return False, f"Reta final de campeonato (rodada {round_num or l_round}) entre time despromovido e time sem disputa direta de título. Risco crítico de elenco misto e descompromisso tático."

    # 4. Trava Anti-Goleada e Superfavorito Extremo (Caso Sabah Baku x Barcelona)
    odd_h = float(fix.get('odd_home') or 0.0)
    odd_a = float(fix.get('odd_away') or 0.0)
    min_odd = min(odd_h, odd_a) if (odd_h > 0 and odd_a > 0) else 0.0
    if 0.0 < min_odd <= 1.20:
        if cand_type.lower() == 'under':
            return False, f"Superfavorito com cotação esmagada (@ {min_odd:.2f} <= 1.20). Trava Anti-Goleada proíbe Under na antecipação."
        else:
            return False, f"Superfavorito com cotação esmagada (@ {min_odd:.2f} <= 1.20). Entrada de Over antecipada vetada sem confirmação pré-jogo dos titulares (T-40)."

    # 5. Trava de Mata-Mata Eliminatório
    if any(k in l_round for k in ('round of 16', 'quarter', 'semi', 'final', 'oitavas', 'quartas', 'mata-mata')):
        return False, f"Fase eliminatória de copa ({l_round}). Dinâmica atípica de jogo único/eliminatória requer validação pré-jogo."

    return True, ""


def executar_radar_reversao_sweetspot(target_date: str = None, dry_run: bool = False, allow_api: bool = False):
    """
    Rotina Principal do Radar de Reversão de NO_BET por Flutuação de Odds no Sweet Spot.
    """
    # Inicialização prévia mandatória de acumuladores locais (Regra 8)
    total_verificados = 0
    total_reversoes_ah = 0
    total_reversoes_gols = 0
    total_apostas_criadas = 0

    conn = get_db_connection()
    cursor = conn.cursor()

    try:
        now_dt = datetime.now()
        if target_date:
            date_desc = f"data específica {target_date}"
            query_fixtures = """
                SELECT fixture_id, home_team, away_team, league_id, league_name,
                       fixture_date, status, odd_home, odd_draw, odd_away,
                       home_team_id, away_team_id, ah_suggestion, ah_reasoning
                FROM fixtures_trends
                WHERE DATE(CONVERT_TZ(fixture_date, '+00:00', '-03:00')) = %s
                  AND fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                  AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
                ORDER BY fixture_date ASC
            """
            cursor.execute(query_fixtures, (target_date,))
        else:
            date_desc = "janela móvel contínua de 48 horas (D+0 e D+1)"
            query_fixtures = """
                SELECT fixture_id, home_team, away_team, league_id, league_name,
                       fixture_date, status, odd_home, odd_draw, odd_away,
                       home_team_id, away_team_id, ah_suggestion, ah_reasoning
                FROM fixtures_trends
                WHERE fixture_date >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)
                  AND fixture_date <= DATE_ADD(UTC_TIMESTAMP(), INTERVAL 48 HOUR)
                  AND status NOT IN ('FT', '1H', '2H', 'HT', 'AET', 'PEN', 'PST', 'CANCELLED', 'POSTPONED', 'IN_PLAY', 'FINISHED')
                ORDER BY fixture_date ASC
            """
            cursor.execute(query_fixtures)

        fixtures = cursor.fetchall()

        print(f"🚀 [Radar Reversão NO_BET] Iniciando varredura para {date_desc} (Dry-Run: {dry_run}, Allow-API: {allow_api})...")

        user_ids = get_eligible_user_ids(cursor)
        print(f"👥 [Usuários Elegíveis] {user_ids}")

        if not fixtures:
            print(f"ℹ️ [Radar Reversão] Nenhuma partida aberta encontrada para {date_desc}.")
            return

        print(f"📋 [Radar Reversão] {len(fixtures)} partidas abertas identificadas para auditoria de flutuação de odds.")

        for fix in fixtures:
            total_verificados += 1
            fid = fix['fixture_id']
            home = (fix.get('home_team') or '').strip()
            away = (fix.get('away_team') or '').strip()
            lid = fix.get('league_id')
            lname = fix.get('league_name') or ''
            fdate = fix.get('fixture_date')

            if not is_allowed_league(lid, lname, fdate):
                continue

            ah_atual = str(fix.get('ah_suggestion') or '').strip()
            is_ah_nobet = (
                not ah_atual or
                any(term in ah_atual.lower() for term in ['abstenção', 'abstencao', 'sem entrada', 'no_bet', 'bloquead'])
            )

            # Opção 1 (Respeitar Cancelamentos): Se já existe qualquer registro de aposta em Handicap (inclusive Cancelada), não reavalia
            cursor.execute("""
                SELECT id FROM apostas 
                WHERE fixture_id = %s AND (mercado = 'Handicap Asiático' OR mercado LIKE '%%Handicap%%')
                LIMIT 1
            """, (fid,))
            if cursor.fetchone():
                is_ah_nobet = False

            # Opção 1 (Respeitar Cancelamentos): Se já existe qualquer aposta em Gols Asiáticos (inclusive Cancelada), não reavalia
            cursor.execute("""
                SELECT id FROM apostas 
                WHERE fixture_id = %s AND mercado = 'Gols Asiáticos'
                LIMIT 1
            """, (fid,))
            existing_gols_bet = cursor.fetchone()
            is_gols_nobet = (existing_gols_bet is None)

            # =========================================================================
            # 1. REAVALIAÇÃO DE HANDICAP ASIÁTICO NO SWEET SPOT (1.60 A 1.95)
            # =========================================================================
            if is_ah_nobet:
                status_gk, palpite_sug, conf_val, detalhe_calc, best_cand, approved_cands = calculate_unified_handicap_recommendation(
                    fix, allow_api_fetch=allow_api, cursor=cursor
                )

                if status_gk == 'APPROVED' and best_cand:
                    cand_odd = float(best_cand.get('odd') or 0.0)
                    cand_prob = float(best_cand.get('eval', {}).get('prob_eff') or 0.0)
                    cand_ev = float(best_cand.get('eval', {}).get('ev_percent') or 0.0)

                    # Validação estrita do Sweet Spot Oficial (1.60 a 1.95) e Probabilidade >= 60.0%
                    if 1.60 <= cand_odd <= 1.95 and cand_prob >= 60.0:
                        total_reversoes_ah += 1
                        print(f"🎯 [REVERSÃO AH DETECTADA!] Partida #{fid} ({home} x {away})")
                        print(f"   ↳ Anterior: {ah_atual} ➔ Novo: {palpite_sug} @ {cand_odd:.2f} (Prob {cand_prob:.1f}%, +EV {cand_ev:.1f}%)")

                        if not dry_run:
                            # Recompõe reasoning preservando U5J (Regra 16)
                            cur_reasoning = fix.get('ah_reasoning')
                            reversal_tag = (
                                f"🔄 [Gatekeeper AH / Reversão no Sweet Spot] A cotação da linha '{palpite_sug}' "
                                f"oscilou para @ {cand_odd:.2f}, entrando perfeitamente no Sweet Spot seguro (1.60 a 1.95) "
                                f"com cobertura projetada de {cand_prob:.1f}% e +EV de +{cand_ev:.1f}%. "
                                f"Status revertido com sucesso de NO_BET para APROVADO.\n"
                            )
                            new_calc = reversal_tag + (detalhe_calc or '')
                            compound_reasoning = compose_compound_ah_reasoning(
                                cursor=cursor,
                                fixture_id=fid,
                                main_calc=new_calc,
                                suggestion=palpite_sug,
                                home_team=home,
                                away_team=away,
                                home_team_id=fix.get('home_team_id'),
                                away_team_id=fix.get('away_team_id'),
                                existing_reasoning=cur_reasoning
                            )

                            # Atualiza fixtures_trends
                            cursor.execute("""
                                UPDATE fixtures_trends SET
                                    ah_suggestion = %s,
                                    ah_confidence = %s,
                                    ah_reasoning = %s,
                                    gatekeeper_category = 'Reversão de Odds no Sweet Spot',
                                    updated_at = NOW()
                                WHERE fixture_id = %s
                            """, (palpite_sug, conf_val, compound_reasoning, fid))

                            # Cria aposta para os usuários elegíveis
                            inserted_cnt = sync_fixture_and_bet_handicap(
                                cursor=cursor,
                                fixture_dict=fix,
                                user_ids=user_ids,
                                best_cand=best_cand,
                                palpite_sug=palpite_sug,
                                conf_val=conf_val,
                                detalhe_calculo=compound_reasoning,
                                confirmada_val=1
                            )
                            total_apostas_criadas += inserted_cnt

                            # Dispara Push Mobile e Sininho estritamente se a aposta foi criada com sucesso
                            if inserted_cnt > 0:
                                push_title = f"🎯 REVERSÃO SWEET SPOT: {palpite_sug} @ {cand_odd:.2f}"
                                push_msg = f"{home} x {away}: A cotação entrou no Sweet Spot seguro (1.60 a 1.95) com {cand_prob:.1f}% de probabilidade. Entrada gerada!"
                                enviar_push_ntfy_reversao(push_title, push_msg, fixture_id=fid)

                                for uid in user_ids:
                                    registrar_notificacao_sininho(
                                        cursor=cursor,
                                        usuario_id=uid,
                                        fixture_id=fid,
                                        aposta_id=None,
                                        titulo=push_title,
                                        mensagem=push_msg,
                                        link=f"/apostas?fixture_id={fid}"
                                    )
                                conn.commit()

            # =========================================================================
            # 2. REAVALIAÇÃO DE GOLS ASIÁTICOS NO SWEET SPOT (1.60 A 1.95)
            # =========================================================================
            if is_gols_nobet:
                st_gols, palpite_gols, conf_gols, reas_gols, best_gols, app_gols = calculate_unified_goals_recommendation(
                    fixture_dict=fix,
                    cursor=cursor
                )

                if st_gols == 'APPROVED' and best_gols:
                    gols_odd = float(best_gols.get('odd') or 0.0)
                    gols_prob = float(best_gols.get('prob_efetiva') or 0.0)
                    gols_ev = float(best_gols.get('ev_pct') or 0.0)

                    # Validação estrita do Sweet Spot Oficial (1.60 a 1.95) e Probabilidade >= 60.0%
                    if 1.60 <= gols_odd <= 1.95 and gols_prob >= 60.0:
                        # Blindagem Contextual de Antecipação (> 12h)
                        is_ok_ant, motivo_veto = check_antecipation_risk_goals(fix, best_gols)
                        if not is_ok_ant:
                            print(f"   🛡️ [Antecipação Standby #{fid}] {home} vs {away}: {motivo_veto}")
                            continue

                        total_reversoes_gols += 1
                        print(f"⚽ [REVERSÃO GOLS DETECTADA!] Partida #{fid} ({home} x {away})")
                        print(f"   ↳ Palpite: {palpite_gols} @ {gols_odd:.2f} (Prob {gols_prob:.1f}%, +EV {gols_ev:.1f}%)")

                        if not dry_run:
                            # Persiste e cria apostas em Gols Asiáticos
                            inserted_g = sync_fixture_and_bet_goals(
                                cursor=cursor,
                                fixture_dict=fix,
                                best_cand=best_gols,
                                reasoning=reas_gols,
                                user_ids=user_ids,
                                confirmada=1
                            )
                            total_apostas_criadas += inserted_g

                            # Dispara Push Mobile e Sininho estritamente se a aposta foi criada com sucesso
                            if inserted_g > 0:
                                push_title_g = f"⚽ REVERSÃO GOLS: {palpite_gols} @ {gols_odd:.2f}"
                                push_msg_g = f"{home} x {away}: Linha de gols entrou no Sweet Spot seguro (1.60 a 1.95) com {gols_prob:.1f}% de chance."
                                enviar_push_ntfy_reversao(push_title_g, push_msg_g, tags="soccer,dart,chart_with_upwards_trend", fixture_id=fid)

                                for uid in user_ids:
                                    registrar_notificacao_sininho(
                                        cursor=cursor,
                                        usuario_id=uid,
                                        fixture_id=fid,
                                        aposta_id=None,
                                        titulo=push_title_g,
                                        mensagem=push_msg_g,
                                        link=f"/apostas?fixture_id={fid}"
                                    )
                                conn.commit()

        print(f"\n🏁 [Radar Reversão Concluído] Verificados: {total_verificados} | Reversões AH: {total_reversoes_ah} | Reversões Gols: {total_reversoes_gols} | Apostas Geradas: {total_apostas_criadas}")

    except Exception as e_master:
        print(f"❌ [Erro Crítico no Radar de Reversão] {e_master}", file=sys.stderr)
        conn.rollback()
        raise e_master
    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Radar de Reversão de NO_BET por Flutuação de Odds no Sweet Spot")
    parser.add_argument("--date", type=str, default=None, help="Data alvo no formato YYYY-MM-DD")
    parser.add_argument("--dry-run", action="store_true", help="Apenas simula a reavaliação sem alterar o banco de dados")
    parser.add_argument("--allow-api", action="store_true", help="Permite consulta à API externa de odds se necessário (padrão: Cache-First)")
    args = parser.parse_args()

    executar_radar_reversao_sweetspot(target_date=args.date, dry_run=args.dry_run, allow_api=args.allow_api)
