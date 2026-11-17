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


def extract_cloudbet_bet_id(resultado_detalhado: str, aposta_id: int) -> str:
    """
    Extrai o identificador do bilhete (betId) da Cloudbet a partir do JSON de auditoria.
    """
    if resultado_detalhado and "|| CLOUDBET:" in resultado_detalhado:
        try:
            part = resultado_detalhado.split("|| CLOUDBET:")[1].split("||")[0].strip()
            data = json.loads(part)
            if "bet_id" in data and data["bet_id"]:
                return str(data["bet_id"])
        except Exception:
            pass
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

    # Consulta apostas ativas da Cloudbet com dados live de fixtures_trends
    cursor.execute("""
        SELECT a.id, a.usuario_id, a.fixture_id, a.palpite, a.mercado, a.odd,
               a.valor_aposta, a.moeda_codigo, a.ganhos_potenciais, a.resultado_detalhado,
               ft.status as fixture_status, ft.home_team as time_casa, ft.away_team as time_fora,
               ft.home_team_id, ft.away_team_id, ft.ah_reasoning,
               ft.goals_home, ft.goals_away, ft.elapsed,
               ft.yellow_cards_home, ft.yellow_cards_away,
               ft.red_cards_home, ft.red_cards_away,
               ft.corners_home, ft.corners_away, ft.fixture_date
        FROM apostas a
        INNER JOIN fixtures_trends ft ON a.fixture_id = ft.fixture_id
        WHERE a.casa_de_aposta = 'Cloudbet'
          AND a.status = 'Pendente'
          AND ft.status IN ('1H', '2H', 'HT', 'LIVE', 'IN_PLAY', 'PAUSED')
        ORDER BY ft.elapsed DESC
    """)
    active_bets = cursor.fetchall()
    total_abertas_cloudbet = len(active_bets)

    if not active_bets:
        print("ℹ️ Nenhuma aposta ativa da Cloudbet em jogos ao vivo no momento.")
        cursor.close()
        conn.close()
        return {
            "total_abertas": 0,
            "cashouts_executados": 0
        }

    print(f"🔍 Encontradas {total_abertas_cloudbet} aposta(s) aberta(s) em jogos ao vivo.")

    for bet in active_bets:
        aposta_id = bet['id']
        f_id = bet['fixture_id']
        home = bet['time_casa']
        away = bet['time_fora']
        elapsed = bet['elapsed']
        gh = bet['goals_home']
        ga = bet['goals_away']
        palpite = bet['palpite']
        stake = float(bet['valor_aposta'] or 0.0)
        cur = bet.get('moeda_codigo') or client.currency

        print(f"\n📊 Analisando Aposta #{aposta_id}: {home} {gh}x{ga} {away} ({elapsed}') | Palpite: {palpite}")

        should_cashout, motivo, fator_cashout = evaluate_live_cashout_trigger(bet)

        if not should_cashout:
            print(f"   🟢 Cenário Seguro / Controlado: {motivo}")
            continue

        print(f"   🚨 [GATILHO DE CASHOUT ATIVADO]: {motivo}")

        # 1. Extração do Bet ID
        bet_id = extract_cloudbet_bet_id(bet.get('resultado_detalhado', ''), aposta_id)

        # 2. Execução do Cash Out via API oficial da Cloudbet
        res_co = client.execute_cashout(bet_id)
        if not res_co.get("success") and not res_co.get("is_simulated"):
            print(f"   ❌ Falha ao processar Cash Out na API Cloudbet: {res_co}")
            total_erros += 1
            continue

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

        cursor.execute("""
            UPDATE apostas SET
                status = 'Cashout',
                cash_out = %s,
                resultado_detalhado = %s,
                updated_at = NOW()
            WHERE id = %s
        """, (valor_cashout, compound_r, aposta_id))
        conn.commit()

        print(f"   ✅ [Cash Out Concluído] Aposta #{aposta_id} atualizada para 'Cashout' no MySQL (Retorno: {valor_cashout:.2f} {cur}).")

        # 5. Notificação Mobile Push via ntfy
        tit = f"⚡ Cash Out Realizado: {home} vs {away}"
        msg = f"Aposta {palpite} encerrada pelo robô aos {elapsed}' ({gh}x{ga}). Retorno salvo: {valor_cashout:.2f} {cur}. Motivo: {motivo}"
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
        except Exception:
            pass

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
