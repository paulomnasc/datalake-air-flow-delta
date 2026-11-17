#!/usr/bin/env python3
"""
scripts/cloudbet_live_cashout.py

Mecanismo Canônico de Cash Out Autônomo In-Play da Cloudbet.
FootballWeb Pipeline - Automação 100% Robotizada.

Responsabilidades:
1. Monitorar em tempo real todas as apostas ativas da Cloudbet (casa_de_aposta = 'Cloudbet' e status = 'Pendente') em partidas ao vivo (1H, 2H, HT, LIVE);
2. Avaliar o velocímetro de risco e cenários críticos de preservação de banca ou meta de lucro batida;
3. Executar o Cash Out programático via Trading API (cloudbet_client.execute_cashout);
4. Encerrar a aposta no MySQL (status = 'Cashout') preservando o payload || U5J_DATA:;
5. Disparar notificação push imediata via ntfy para o smartphone.
"""

import os
import sys
import re
import json
import time
import argparse
import requests
from datetime import datetime
from typing import Dict, Any, Optional, List, Tuple

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection, get_live_env_vars
from cloudbet_client import CloudbetClient
from asian_handicap_engine import compose_compound_ah_reasoning


def enviar_push_cashout(
    titulo: str,
    mensagem: str,
    fixture_id: Optional[int] = None,
    aposta_id: Optional[int] = None,
    prioridade: int = 5
) -> bool:
    """
    Dispara notificação push instantânea para o smartphone Android via ntfy.sh (Alta Prioridade).
    """
    try:
        env_vars = get_live_env_vars()
        enabled = os.environ.get("NTFY_ENABLED") or env_vars.get("NTFY_ENABLED", "true")
        if str(enabled).strip().lower() not in ("true", "1", "yes", "on"):
            return False

        topic = os.environ.get("NTFY_TOPIC") or env_vars.get("NTFY_TOPIC", "footbalweb_sentinela")
        if not topic:
            return False

        server = (os.environ.get("NTFY_SERVER") or env_vars.get("NTFY_SERVER", "https://ntfy.sh")).rstrip("/")
        base_url = (os.environ.get("NTFY_BASE_URL") or env_vars.get("NTFY_BASE_URL", "https://cristalbet.com.br")).rstrip("/")
        click_url = f"{base_url}/apostas?fixture_id={fixture_id}" if fixture_id else f"{base_url}/apostas"

        payload = {
            "topic": topic,
            "title": titulo,
            "message": mensagem,
            "priority": prioridade,
            "tags": ["zap", "moneybag", "soccer"],
            "click": click_url
        }

        resp = requests.post(
            f"{server}",
            json=payload,
            headers={"Content-Type": "application/json; charset=utf-8"},
            timeout=8
        )
        return resp.status_code in (200, 201)
    except Exception as e:
        print(f"⚠️ [Push Mobile Erro]: {e}")
        return False


def extract_cloudbet_metadata(resultado_detalhado: str) -> Dict[str, Any]:
    """
    Extrai os metadados da Cloudbet (bet_id, event_id, market_url, is_simulated) gravados em resultado_detalhado.
    """
    if resultado_detalhado and "|| CLOUDBET:" in resultado_detalhado:
        try:
            part = resultado_detalhado.split("|| CLOUDBET:")[1].split("||")[0].strip()
            return json.loads(part)
        except Exception:
            pass
    return {}


def derive_opposite_market_url(market_url: str) -> Optional[str]:
    """
    Determina o marketUrl canônico da ponta oposta para realização de Hedge in-play.
    Exemplos:
      - Over vira Under: soccer.total_goals/over?total=2.5 -> soccer.total_goals/under?total=2.5
      - Home vira Away: soccer.asian_handicap/home?handicap=-0.25 -> soccer.asian_handicap/away?handicap=-0.25
    """
    if not market_url:
        return None
    if "/over?" in market_url:
        return market_url.replace("/over?", "/under?")
    elif "/under?" in market_url:
        return market_url.replace("/under?", "/over?")
    elif "/home?" in market_url:
        return market_url.replace("/home?", "/away?")
    elif "/away?" in market_url:
        return market_url.replace("/away?", "/home?")
    elif market_url.endswith("/yes"):
        return market_url[:-4] + "/no"
    elif market_url.endswith("/no"):
        return market_url[:-3] + "/yes"
    return None


def get_live_opposite_quote(client: CloudbetClient, event_id: str, opposite_market_url: str) -> Optional[Dict[str, Any]]:
    """
    Consulta o book de ofertas ao vivo da Cloudbet para localizar a cotação e liquidez da ponta de cobertura.
    """
    try:
        odds_raw = client.get_event_odds(event_id)
        markets = odds_raw.get("markets", {}) if isinstance(odds_raw, dict) else {}
        for mk, mv in markets.items():
            for subk, subv in mv.get("submarkets", {}).items():
                for sel in subv.get("selections", []):
                    if sel.get("marketUrl") == opposite_market_url:
                        price = float(sel.get("price") or 0.0)
                        status = sel.get("status")
                        if status == "SELECTION_ENABLED" and price > 1.0:
                            return {
                                "market_url": opposite_market_url,
                                "price": price,
                                "min_stake": float(sel.get("minStake") or 0.10),
                                "max_stake": float(sel.get("maxStake") or 0.0)
                            }
    except Exception as e:
        print(f"⚠️ [Hedge Quote Error] Erro ao buscar cotação in-play de hedge: {e}")
    return None


def extract_cloudbet_bet_id(resultado_detalhado: str, aposta_id: int) -> str:
    """
    Extrai o identificador do bilhete (betId) da Cloudbet a partir do JSON de auditoria.
    """
    meta = extract_cloudbet_metadata(resultado_detalhado)
    if "bet_id" in meta and meta["bet_id"]:
        return str(meta["bet_id"])
    return f"sim_cb_{aposta_id}"


def evaluate_live_cashout_trigger(bet_row: Dict[str, Any]) -> Tuple[bool, str, float]:
    """
    Avalia se uma aposta em andamento atingiu as condições contratuais de Cash Out.
    Retorna: (is_cashout_moment, motivo, fator_estimado_cashout)
    """
    # Inicialização prévia mandatória de variáveis numéricas locais (Regra 8)
    diff_gols = 0
    fator_cashout = 0.80
    elapsed = int(bet_row.get('elapsed') or 0)
    gh = int(bet_row.get('goals_home') or 0)
    ga = int(bet_row.get('goals_away') or 0)
    total_gols = gh + ga

    mercado = str(bet_row.get('mercado') or '').strip().lower()
    palpite = str(bet_row.get('palpite') or '').strip()
    home_team = str(bet_row.get('time_casa') or '').strip()
    away_team = str(bet_row.get('time_fora') or '').strip()

    status = str(bet_row.get('fixture_status') or '').strip().upper()
    if status not in ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED'):
        return False, "Partida não está em andamento ao vivo", 0.0

    # =========================================================================
    # 1. CENÁRIOS DE HANDICAP ASIÁTICO
    # =========================================================================
    if 'handicap' in mercado or 'ah' in mercado.lower():
        is_away = (away_team.lower() in palpite.lower())
        team_name = away_team if is_away else home_team
        
        # Diferença de gols do ponto de vista do time apostado
        diff_gols = (ga - gh) if is_away else (gh - ga)

        # Extrai a linha do palpite (ex: -0.25, 0.0, +0.5)
        m_line = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite)
        line_val = float(m_line.group(1)) if m_line else 0.0

        # Cenário A: Desvantagem Crítica no 2º Tempo (Reta Final 76'+)
        # Se estiver perdendo por 1 gol aos 76'+ minutos e a linha for negativa ou zero (0.0 / -0.25 / -0.5)
        if diff_gols < 0 and elapsed >= 76:
            if line_val <= 0.0:
                fator_cashout = 0.25 if diff_gols <= -2 else 0.40
                motivo = f"Preservação de Banca: {team_name} em desvantagem ({gh}x{ga}) aos {elapsed}'. Tempo escasso para reversão no 2º tempo."
                return True, motivo, fator_cashout

        # Cenário B: Desvantagem de 2 ou mais gols no 2º tempo (46'+)
        if diff_gols <= -2 and elapsed >= 50:
            fator_cashout = 0.20
            motivo = f"Preservação de Banca: Desvantagem crítica de 2+ gols ({gh}x{ga}) aos {elapsed}'."
            return True, motivo, fator_cashout

        # Cenário C: Expulsão Crítica (Cartão Vermelho) do time apostado no 2º tempo em empate ou desvantagem
        rc_home = int(bet_row.get('red_cards_home') or 0)
        rc_away = int(bet_row.get('red_cards_away') or 0)
        has_red = rc_away > 0 if is_away else rc_home > 0
        if has_red and elapsed >= 46 and diff_gols <= 0:
            fator_cashout = 0.50
            motivo = f"Gestão de Risco: {team_name} com jogador expulso e sem vantagem no placar ({gh}x{ga}) aos {elapsed}'."
            return True, motivo, fator_cashout

    # =========================================================================
    # 2. CENÁRIOS DE TOTAL DE GOLS (OVER / UNDER)
    # =========================================================================
    elif 'gol' in mercado or 'over' in palpite.lower() or 'under' in palpite.lower():
        is_over = 'over' in palpite.lower() or 'mais' in palpite.lower()
        m_tot = re.search(r'(\d+(?:\.\d+)?)', palpite)
        line_total = float(m_tot.group(1)) if m_tot else 2.5

        if is_over:
            # Over: Seca severa de gols aos 72'+ minutos com 0x0
            if total_gols == 0 and elapsed >= 72:
                fator_cashout = 0.15
                motivo = f"Seca Prolongada: Placar 0x0 aos {elapsed}' em aposta {palpite}. Saída antecipada para mitigar perda total."
                return True, motivo, fator_cashout
        else:
            # Under: Placar perigosamente colado na linha no 2º tempo
            # Exemplo: Under 2.5 com 2 gols já marcados antes dos 75'
            if total_gols == int(line_total) and elapsed <= 75:
                fator_cashout = 0.55
                motivo = f"Atenção ao Under: Apenas 1 gol restante para o Red ({gh}x{ga}) aos {elapsed}'. Salvaguarda de lucro/banca."
                return True, motivo, fator_cashout

    return False, "Nenhum gatilho de risco acionado", 0.0


def check_and_execute_cloudbet_live_cashouts(dry_run_override: Optional[bool] = None) -> Dict[str, Any]:
    """
    Executa uma varredura nas apostas Cloudbet ativas e encerra aquelas com gatilho ativado.
    """
    # Inicialização prévia mandatória de variáveis numéricas locais (Regra 8)
    total_abertas_cloudbet = 0
    total_jogos_ao_vivo = 0
    total_cashouts_executados = 0
    total_erros = 0

    conn = get_db_connection()
    cursor = conn.cursor()

    client = CloudbetClient(dry_run=dry_run_override)

    print("=" * 70)
    print("⚡ [Cloudbet In-Play Live Cashout Monitor] Iniciando Varredura...")
    print(f"   • Modo Operacional: {'DRY-RUN / SIMULAÇÃO 🛡️' if client.dry_run else 'PRODUÇÃO REAL ⚠️'}")
    print("=" * 70)

    # Consulta apostas ativas (Cloudbet e outras casas monitoradas) com dados live de fixtures_trends
    cursor.execute("""
        SELECT a.id, a.usuario_id, a.fixture_id, a.palpite, a.mercado, a.odd,
               a.valor_aposta, a.moeda_codigo, a.ganhos_potenciais, a.resultado_detalhado,
               a.casa_de_aposta,
               ft.status as fixture_status, ft.home_team as time_casa, ft.away_team as time_fora,
               ft.home_team_id, ft.away_team_id, ft.ah_reasoning,
               ft.goals_home, ft.goals_away, ft.elapsed,
               ft.yellow_cards_home, ft.yellow_cards_away,
               ft.red_cards_home, ft.red_cards_away,
               ft.corners_home, ft.corners_away, ft.fixture_date
        FROM apostas a
        INNER JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
        WHERE a.status = 'Pendente'
          AND a.casa_de_aposta IN ('Cloudbet', 'Betano', 'Stake', 'Pinnacle', '1xBet')
          AND ft.status IN ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED')
        ORDER BY ft.elapsed DESC
    """)
    active_bets = cursor.fetchall()
    total_abertas_cloudbet = len(active_bets)

    if not active_bets:
        print("ℹ️ Nenhuma aposta ativa monitorada em jogos ao vivo no momento.")
        cursor.close()
        conn.close()
        return {
            "total_abertas": 0,
            "cashouts_executados": 0
        }

    print(f"🔍 Encontradas {total_abertas_cloudbet} aposta(s) aberta(s) em jogos ao vivo.")

    for bet in active_bets:
        aposta_id = bet['id']
        bet_id = str(aposta_id)
        f_id = bet['fixture_id']
        home = bet['time_casa']
        away = bet['time_fora']
        elapsed = bet['elapsed']
        gh = bet['goals_home']
        ga = bet['goals_away']
        palpite = bet['palpite']
        casa_nome = bet.get('casa_de_aposta') or 'Cloudbet'
        stake = float(bet['valor_aposta'] or 0.0)
        cur = bet.get('moeda_codigo') or client.currency

        print(f"\n📊 Analisando Aposta #{aposta_id} [{casa_nome}]: {home} {gh}x{ga} {away} ({elapsed}') | Palpite: {palpite}")

        should_cashout, motivo, fator_cashout = evaluate_live_cashout_trigger(bet)

        if not should_cashout:
            print(f"   🟢 Cenário Seguro / Controlado: {motivo}")
            continue

        print(f"   🚨 [GATILHO DE CASHOUT ATIVADO]: {motivo}")

        # 1. Se for Cloudbet, executa via HEDGE AUTOMÁTICO na API; se for Betano/outras, notifica para ação assistida
        hedge_info = {}
        if casa_nome == 'Cloudbet':
            cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
            bet_id = str(cb_meta.get('bet_id') or f"sim_cb_{aposta_id}")
            event_id = str(cb_meta.get('event_id') or '')
            market_url = str(cb_meta.get('market_url') or '')
            opposite_market_url = derive_opposite_market_url(market_url)

            if client.dry_run or bet_id.startswith("sim_"):
                print(f"   ⚡ [Cloudbet DRY-RUN] Simulando Cash Out / Hedge in-play...")
                res_co = client.execute_cashout(bet_id)
                hedge_info = {"mode": "dry_run_simulated", "bet_id": bet_id, "opposite_market_url": opposite_market_url}
            else:
                # MODO REAL: Executa o Hedge In-Play comprando a ponta contrária
                if not event_id or not opposite_market_url:
                    print(f"   ⚠️ [Hedge Cloudbet] Metadados de mercado ausentes para hedge em #{aposta_id}. Abortando.")
                    total_erros += 1
                    continue

                quote = get_live_opposite_quote(client, event_id, opposite_market_url)
                if not quote:
                    print(f"   ⚠️ [Hedge Cloudbet] Mercado oposto '{opposite_market_url}' suspenso/sem liquidez neste instante. Tentará no próximo ciclo.")
                    continue

                hedge_price = quote['price']
                min_stake = quote['min_stake']
                hedge_stake = max(min_stake, round(stake * fator_cashout, 2))

                print(f"   🚀 [Disparando Hedge Real via API] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
                res_co = client.execute_cashout(
                    bet_id=bet_id,
                    event_id=event_id,
                    market_url=opposite_market_url,
                    price=hedge_price,
                    stake=hedge_stake,
                    dry_run=False
                )

                if not res_co.get("success") and not res_co.get("referenceId"):
                    print(f"   ❌ Falha ao processar ordem de cobertura na API Cloudbet: {res_co}")
                    total_erros += 1
                    continue
                hedge_info = {"mode": "real_hedge", "hedge_ref": res_co.get("referenceId"), "market_url": opposite_market_url, "price": hedge_price, "stake": hedge_stake}
        else:
            print(f"   ℹ️ [{casa_nome}] Aposta requer confirmação de Cash Out no app da casa. Disparando alerta mobile imediato...")

        # 3. Cálculo do valor de retorno do Cash Out
        valor_cashout = round(stake * fator_cashout, 2)
        total_cashouts_executados += 1

        # 4. Atualização segura no banco de dados preservando o payload U5J
        compound_r = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=f_id,
            main_calc=f"⚡ [CASHOUT EXECUTADO] Aposta encerrada aos {elapsed}' ({gh}x{ga}). Motivo: {motivo}",
            suggestion=palpite,
            home_team=home,
            away_team=away,
            home_team_id=bet.get('home_team_id'),
            away_team_id=bet.get('away_team_id'),
            existing_reasoning=bet.get('resultado_detalhado') or bet.get('ah_reasoning')
        )
        final_r = f"|| HEDGE: {json.dumps(hedge_info)} || " + compound_r if hedge_info else compound_r

        cursor.execute("""
            UPDATE apostas SET
                status = 'Cashout',
                cash_out = %s,
                resultado_detalhado = %s,
                updated_at = NOW()
            WHERE id = %s
        """, (valor_cashout, final_r, aposta_id))
        conn.commit()

        print(f"   ✅ [Cash Out Concluído] Aposta #{aposta_id} atualizada para 'Cashout' no MySQL (Retorno: {valor_cashout:.2f} {cur}).")

        # 4.1 Liquidação Financeira Imediata em Conta Corrente
        try:
            from processar_apostas_encerradas import creditar_retorno_aposta
            uid = bet.get('usuario_id')
            desc_cc = f"Retorno Aposta #{aposta_id} ({home} x {away} - Cashout)"
            if creditar_retorno_aposta(cursor, uid, aposta_id, valor_cashout, 'Cashout', desc_cc):
                conn.commit()
                print(f"   💰 [Crédito Salvo] +{valor_cashout:.2f} {cur} adicionado ao saldo do Usuário #{uid}.")
        except Exception as e_cc:
            print(f"   ⚠️ [Erro Crédito Conta Corrente Cash Out #{aposta_id}]: {e_cc}")

        # 5. Notificação Mobile Push via ntfy
        if casa_nome == 'Cloudbet':
            tit = f"⚡ Cash Out Realizado (Cloudbet): {home} vs {away}"
            msg = f"Aposta {palpite} encerrada pelo robô aos {elapsed}' ({gh}x{ga}). Retorno salvo: {valor_cashout:.2f} {cur}. Motivo: {motivo}"
        else:
            tit = f"⚡ Alerta Cash Out ({casa_nome}): {home} vs {away}"
            msg = f"Aposta {palpite} aos {elapsed}' ({gh}x{ga}). Toque para abrir e encerrar na {casa_nome}. Motivo: {motivo}"
            
        enviar_push_cashout(tit, msg, fixture_id=f_id, aposta_id=aposta_id, prioridade=5)

        # 6. Rastreamento transversal de ciclo de vida
        try:
            from lifecycle_logger import log_lifecycle_event
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=f_id,
                aposta_id=aposta_id,
                notificacao_id=None,
                notificacao_criada_em=None,
                componente='CLOUDBET_LIVE_CASHOUT',
                tipo_evento='CASHOUT_EXECUTADO',
                estado_anterior='Pendente',
                estado_novo='Cashout',
                delta={'bet_id': bet_id, 'valor_cashout': valor_cashout, 'minuto': elapsed, 'placar': f"{gh}x{ga}"},
                motivo=motivo
            )
            conn.commit()
        except Exception as e_lf:
            print(f"   ⚠️ [Erro Lifecycle Logger Cash Out #{aposta_id}]: {e_lf}")

    cursor.close()
    conn.close()

    print("\n" + "=" * 70)
    print("🏁 Resumo do Cash Out Live:")
    print(f"   • Total de apostas analisadas: {total_abertas_cloudbet}")
    print(f"   • Cash outs executados com sucesso: {total_cashouts_executados}")
    print(f"   • Erros de execução: {total_erros}")
    print("=" * 70)

    return {
        "total_abertas": total_abertas_cloudbet,
        "cashouts_executados": total_cashouts_executados,
        "erros": total_erros
    }


def executar_hedge_prejogo_cloudbet(
    bet: Dict[str, Any],
    motivo_tatica: str,
    client: Optional[CloudbetClient] = None,
    cursor = None,
    conn = None
) -> Dict[str, Any]:
    """
    Executa o Hedge Autônomo Pré-Jogo para apostas na Cloudbet
    quando o Grok IA emite veredito de CASHOUT_IMEDIATO por sinistro na súmula oficial.
    Regra 8: inicialização prévia de variáveis numéricas locais.
    Regra 16: preservação obrigatória do bloco || U5J_DATA:.
    """
    total_erros = 0
    valor_cashout = 0.0

    aposta_id = bet.get('id') or bet.get('aposta_id')
    f_id = bet.get('fixture_id')
    home = bet.get('home_team') or bet.get('time_casa', 'Mandante')
    away = bet.get('away_team') or bet.get('time_fora', 'Visitante')
    palpite = bet.get('palpite', 'N/D')
    stake = float(bet.get('valor_aposta') or 0.0)

    if client is None:
        client = CloudbetClient()

    owns_conn = False
    if cursor is None:
        conn = get_db_connection()
        cursor = conn.cursor()
        owns_conn = True

    try:
        print(f"\n🛡️ [Iniciando Hedge Pré-Jogo Autônomo] Aposta #{aposta_id} ({home} vs {away}) | Palpite: {palpite}")
        print(f"   Motivo Grok IA: {motivo_tatica[:200]}...")

        cb_meta = extract_cloudbet_metadata(bet.get('resultado_detalhado', ''))
        bet_id = str(cb_meta.get('bet_id') or f"sim_cb_{aposta_id}")
        event_id = str(cb_meta.get('event_id') or '')
        market_url = str(cb_meta.get('market_url') or '')
        opposite_market_url = derive_opposite_market_url(market_url)

        hedge_info = {}
        if client.dry_run or bet_id.startswith("sim_"):
            print(f"   ⚡ [Cloudbet DRY-RUN] Simulando Hedge Pré-Jogo...")
            res_co = client.execute_cashout(bet_id)
            hedge_info = {"mode": "dry_run_pregame_hedge", "bet_id": bet_id, "opposite_market_url": opposite_market_url}
        else:
            if not event_id or not opposite_market_url:
                try:
                    from cloudbet_matcher import find_matching_event
                    matched = find_matching_event(client, home, away)
                    if matched:
                        event_id = matched.get("id", "")
                except Exception as e_m:
                    print(f"   ⚠️ [Matcher Event Error]: {e_m}")

            if not event_id or not opposite_market_url:
                print(f"   ⚠️ [Hedge Cloudbet Pré-Jogo] Metadados de mercado ausentes para hedge em #{aposta_id}.")
                return {"success": False, "motivo": "Metadados ausentes"}

            quote = get_live_opposite_quote(client, event_id, opposite_market_url)
            if not quote:
                print(f"   ⚠️ [Hedge Cloudbet Pré-Jogo] Mercado oposto '{opposite_market_url}' sem cotação pré-jogo disponível.")
                return {"success": False, "motivo": "Mercado oposto sem cotação"}

            hedge_price = quote['price']
            min_stake = quote['min_stake']
            hedge_stake = max(min_stake, round(stake, 2))

            print(f"   🚀 [Disparando Ordem Real Pré-Jogo via API] Comprando ponta oposta: {opposite_market_url} @ {hedge_price} (Stake: {hedge_stake} {client.currency})...")
            res_co = client.execute_cashout(
                bet_id=bet_id,
                event_id=event_id,
                market_url=opposite_market_url,
                price=hedge_price,
                stake=hedge_stake,
                dry_run=False
            )

            if not res_co.get("success") and not res_co.get("referenceId"):
                print(f"   ❌ Falha ao processar ordem de hedge pré-jogo na API Cloudbet: {res_co}")
                return {"success": False, "motivo": "Falha na API Cloudbet"}

            hedge_info = {
                "mode": "real_pregame_hedge",
                "hedge_ref": res_co.get("referenceId"),
                "market_url": opposite_market_url,
                "price": hedge_price,
                "stake": hedge_stake
            }

        valor_cashout = round(stake * 0.90, 2)
        motivo_clean = re.sub(r'[\r\n\t]+', ' ', motivo_tatica).strip()
        main_calc_text = f"🛡️ [HEDGE PRÉ-JOGO EXECUTADO] Aposta neutralizada na Cloudbet antes do início. Motivo Grok IA: {motivo_clean[:180]}"

        compound_r = compose_compound_ah_reasoning(
            cursor=cursor,
            fixture_id=f_id,
            main_calc=main_calc_text,
            suggestion=palpite,
            home_team=home,
            away_team=away,
            home_team_id=bet.get('home_team_id'),
            away_team_id=bet.get('away_team_id'),
            existing_reasoning=bet.get('resultado_detalhado') or bet.get('ah_reasoning')
        )
        final_r = f"|| HEDGE_PREJOGO: {json.dumps(hedge_info)} || " + compound_r if hedge_info else compound_r

        cursor.execute("""
            UPDATE apostas
            SET status = 'Cashout',
                cash_out = %s,
                resultado_detalhado = %s,
                updated_at = NOW()
            WHERE id = %s
        """, (valor_cashout, final_r, aposta_id))

        if conn:
            conn.commit()

        # Liquidação Financeira Imediata em Conta Corrente
        try:
            from processar_apostas_encerradas import creditar_retorno_aposta
            uid = bet.get('usuario_id')
            desc_cc = f"Retorno Aposta #{aposta_id} ({home} x {away} - Hedge Pré-Jogo)"
            if creditar_retorno_aposta(cursor, uid, aposta_id, valor_cashout, 'Cashout', desc_cc):
                if conn:
                    conn.commit()
                print(f"💰 [Crédito Salvo] +{valor_cashout:.2f} {client.currency} adicionado ao saldo do Usuário #{uid}.")
        except Exception as e_cc:
            print(f"⚠️ [Erro Crédito Conta Corrente Hedge Pré-Jogo #{aposta_id}]: {e_cc}")

        tit_push = f"🛡️ Hedge Pré-Jogo Executado: {home} vs {away}"
        msg_push = (
            f"O Grok IA identificou desequilíbrio crítico na súmula oficial de {home} x {away}. "
            f"Sua aposta #{aposta_id} ({palpite}) foi neutralizada na Cloudbet antes do apito inicial para proteger a banca!"
        )
        enviar_push_cashout(
            titulo=tit_push,
            mensagem=msg_push,
            fixture_id=f_id,
            aposta_id=aposta_id,
            prioridade=5
        )

        try:
            from lifecycle_logger import log_lifecycle_event
            log_lifecycle_event(
                cursor=cursor,
                fixture_id=f_id,
                aposta_id=aposta_id,
                notificacao_id=None,
                notificacao_criada_em=None,
                componente='GROK_SENTINEL_HEDGE',
                tipo_evento='HEDGE_PREJOGO_EXECUTADO',
                estado_anterior='Pendente',
                estado_novo='Cashout',
                delta={'bet_id': bet_id, 'valor_cashout': valor_cashout, 'hedge_info': hedge_info},
                motivo=motivo_tatica
            )
            if conn:
                conn.commit()
        except Exception as e_lf:
            print(f"⚠️ [Erro Lifecycle Logger Hedge Pré-Jogo #{aposta_id}]: {e_lf}")

        print(f"✅ [Hedge Pré-Jogo Concluído] Aposta #{aposta_id} encerrada e protegida com sucesso!")
        return {"success": True, "valor_cashout": valor_cashout, "hedge_info": hedge_info}

    except Exception as e_h:
        print(f"❌ [Erro Hedge Pré-Jogo]: {e_h}")
        return {"success": False, "motivo": str(e_h)}
    finally:
        if owns_conn and conn:
            cursor.close()
            conn.close()


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Monitor de Cash Out In-Play 100% Autônomo da Cloudbet")
    parser.add_argument("--loop", action="store_true", help="Executa em loop contínuo a cada 30 segundos")
    parser.add_argument("--real", action="store_true", help="Força a execução financeira real na API")
    args = parser.parse_args()

    dry_run_opt = False if args.real else None

    if args.loop:
        print("🔁 [Modo Daemon Contínuo] Monitorando cash outs a cada 30 segundos. Pressione CTRL+C para sair.")
        while True:
            try:
                check_and_execute_cloudbet_live_cashouts(dry_run_override=dry_run_opt)
                time.sleep(30)
            except KeyboardInterrupt:
                print("\n🛑 Monitor de cash out interrompido pelo usuário.")
                break
            except Exception as e:
                print(f"⚠️ Erro inesperado no loop de cash out: {e}")
                time.sleep(30)
    else:
        check_and_execute_cloudbet_live_cashouts(dry_run_override=dry_run_opt)
