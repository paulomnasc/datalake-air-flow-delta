#!/usr/bin/env python3
"""
Script de Revisão, Saneamento e Reliquidação Histórica de Partidas e Apostas (Datas <= 19/09/2026).
1. Identifica todas as partidas associadas a apostas de cartões no período <= 19/09/2026;
2. Consulta API-Sports para obter estatísticas oficiais de cartões e escanteios para fixtures pendentes;
3. Persiste ambos os times em 'match_statistics_cache' e atualiza 'fixtures_trends';
4. Reliquida todas as apostas de cartões aplicando a regra canônica: 1 Cartão Vermelho = 2 Cartões;
5. Trata partidas adiadas/canceladas (PST/CANC) como ANULADA e preserva apostas com status Cashout;
6. Reconcilia o extrato da 'conta_corrente' (lançamento de ESTORNO_APOSTA ou CREDITO_RETORNO_APOSTA);
7. Reconcilia o saldo do usuário em 'usuario.saldo_conta_corrente';
8. Recalcula as metas diárias em 'metas_diarias_cache' para todas as datas afetadas.
"""

import os
import sys
import re
import time
import json
import urllib.request
import pymysql
from datetime import datetime

API_KEY = os.getenv("API_SPORTS_KEY", "0327019c6fab54df2ea46009b5f0844b")

def get_db_connection():
    hosts_ports = [
        ("127.0.0.1", 23306),
        ("localhost", 3306),
        ("mysql", 3306)
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
                connect_timeout=3,
                autocommit=True
            )
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ Falha ao conectar no MySQL.")
    sys.exit(1)

def fetch_fixture_statistics_api(fixture_id):
    """
    Busca estatísticas oficiais da partida na API-Sports via HTTP GET com timeout seguro.
    """
    url = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
    req = urllib.request.Request(url, headers={"x-apisports-key": API_KEY, "User-Agent": "FootballWeb/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=12) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                return data.get("response", [])
            else:
                print(f"⚠️ API retornou status HTTP {response.status} para fixture #{fixture_id}")
                return []
    except Exception as e:
        print(f"⚠️ Erro ao consultar API para fixture #{fixture_id}: {e}")
        return []

def fetch_fixture_status_api(fixture_id):
    """
    Busca metadados e status da partida na API-Sports (para casos PST/CANC/FT).
    """
    url = f"https://v3.football.api-sports.io/fixtures?id={fixture_id}"
    req = urllib.request.Request(url, headers={"x-apisports-key": API_KEY, "User-Agent": "FootballWeb/1.0"})
    try:
        with urllib.request.urlopen(req, timeout=12) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                resp_list = data.get("response", [])
                if resp_list:
                    return resp_list[0].get("fixture", {}).get("status", {}).get("short")
            return None
    except Exception as e:
        print(f"⚠️ Erro ao consultar status da fixture #{fixture_id}: {e}")
        return None

def save_fixture_stats_to_db(cursor, fixture_id, home_team_id, away_team_id, st_data):
    """
    Persiste ambos os times em match_statistics_cache e atualiza fixtures_trends.
    """
    try:
        yh, ya = 0, 0
        rh, ra = 0, 0
        ck_h, ck_a = 0, 0
        st_h, st_a = 0, 0
        sg_h, sg_a = 0, 0
        xg_h, xg_a = 0.0, 0.0

        for t_idx, team_block in enumerate(st_data):
            t_info = team_block.get("team", {})
            t_id = t_info.get("id")
            stats_list = team_block.get("statistics", [])
            s_map = {s.get("type"): s.get("value") for s in stats_list if s.get("type")}

            yc = int(s_map.get("Yellow Cards") or 0)
            rc = int(s_map.get("Red Cards") or 0)
            ck = int(s_map.get("Corner Kicks") or 0)
            st = int(s_map.get("Total Shots") or 0)
            sg = int(s_map.get("Shots on Goal") or 0)

            raw_xg = s_map.get("expected_goals")
            try:
                xg_val = float(raw_xg) if raw_xg is not None else 0.0
            except (ValueError, TypeError):
                xg_val = 0.0

            is_home = (t_id == home_team_id) if home_team_id else (t_idx == 0)

            if is_home:
                yh, rh, ck_h, st_h, sg_h, xg_h = yc, rc, ck, st, sg, xg_val
            else:
                ya, ra, ck_a, st_a, sg_a, xg_a = yc, rc, ck, st, sg, xg_val

            if t_id:
                cursor.execute("""
                    INSERT INTO match_statistics_cache (fixture_id, team_id, corners, yellow_cards, red_cards)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        corners = VALUES(corners),
                        yellow_cards = VALUES(yellow_cards),
                        red_cards = VALUES(red_cards)
                """, (fixture_id, t_id, ck, yc, rc))

        cursor.execute("""
            UPDATE fixtures_trends
            SET yellow_cards_home = %s,
                yellow_cards_away = %s,
                red_cards_home = %s,
                red_cards_away = %s,
                corners_home = %s,
                corners_away = %s,
                shots_home = %s,
                shots_away = %s,
                xg_home = %s,
                xg_away = %s,
                cards_api_checked_at = NOW(),
                cards_api_retry_count = 0,
                updated_at = NOW()
            WHERE fixture_id = %s
        """, (
            yh, ya, rh, ra,
            ck_h, ck_a,
            sg_h if sg_h > 0 else st_h,
            sg_a if sg_a > 0 else st_a,
            xg_h, xg_a,
            fixture_id
        ))

        return yh, ya, rh, ra
    except Exception as e:
        print(f"❌ Erro ao gravar estatísticas para fixture #{fixture_id}: {e}")
        return None

def estornar_retorno_aposta(cursor, usuario_id, aposta_id, valor_retorno, descricao):
    """
    Realiza o estorno de um retorno creditado indevidamente no extrato da conta corrente.
    """
    cursor.execute("""
        SELECT id FROM conta_corrente 
        WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'
    """, (usuario_id, aposta_id))
    if cursor.fetchone():
        print(f"  ℹ️ [Estorno Já Existente] Aposta #{aposta_id} já possui estorno lançado anteriormente.")
        return False

    valor_estorno = -abs(float(valor_retorno))
    cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
    u_row = cursor.fetchone()
    saldo_anterior = float(u_row['saldo_conta_corrente']) if u_row and u_row.get('saldo_conta_corrente') is not None else 0.0
    saldo_posterior = round(saldo_anterior + valor_estorno, 2)

    cursor.execute("""
        INSERT INTO conta_corrente (usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em)
        VALUES (%s, %s, 'ESTORNO_APOSTA', %s, %s, %s, %s, NOW())
    """, (usuario_id, aposta_id, descricao, valor_estorno, saldo_anterior, saldo_posterior))

    cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (saldo_posterior, usuario_id))
    print(f"  💸 [Estorno Conta Corrente] Aposta #{aposta_id}: Débito de R$ {valor_estorno:.2f} | Saldo: R$ {saldo_anterior:.2f} -> R$ {saldo_posterior:.2f}")
    return True

def creditar_retorno_aposta(cursor, usuario_id, aposta_id, valor_retorno, descricao):
    """
    Credita o retorno de uma aposta que se tornou Ganha.
    """
    cursor.execute("""
        SELECT id FROM conta_corrente 
        WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'CREDITO_RETORNO_APOSTA'
    """, (usuario_id, aposta_id))
    if cursor.fetchone():
        print(f"  ℹ️ [Crédito Já Existente] Aposta #{aposta_id} já possui crédito lançado anteriormente.")
        return False

    valor_credito = abs(float(valor_retorno))
    cursor.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = %s", (usuario_id,))
    u_row = cursor.fetchone()
    saldo_anterior = float(u_row['saldo_conta_corrente']) if u_row and u_row.get('saldo_conta_corrente') is not None else 0.0
    saldo_posterior = round(saldo_anterior + valor_credito, 2)

    cursor.execute("""
        INSERT INTO conta_corrente (usuario_id, aposta_id, tipo, descricao, valor, saldo_anterior, saldo_posterior, criado_em)
        VALUES (%s, %s, 'CREDITO_RETORNO_APOSTA', %s, %s, %s, %s, NOW())
    """, (usuario_id, aposta_id, descricao, valor_credito, saldo_anterior, saldo_posterior))

    cursor.execute("UPDATE usuario SET saldo_conta_corrente = %s WHERE id = %s", (saldo_posterior, usuario_id))
    print(f"  💰 [Crédito Conta Corrente] Aposta #{aposta_id}: Crédito de R$ {valor_credito:.2f} | Saldo: R$ {saldo_anterior:.2f} -> R$ {saldo_posterior:.2f}")
    return True

def evaluate_cards_bet(aposta, yh, ya, rh, ra, fixture_status):
    """
    Avalia a aposta no mercado de cartões seguindo as regras oficiais:
    1. 1 Cartão Vermelho conta como 2 Cartões;
    2. PST/CANC -> ANULADA;
    3. Cashout manual -> mantido inalterado;
    4. Over: actual > threshold | Under: actual < threshold.
    """
    current_status = aposta.get('status')
    if current_status == 'Cashout':
        return 'Cashout', float(aposta.get('cash_out') or aposta.get('valor_aposta') or 0.0), aposta.get('resultado_detalhado') or "Encerramento manual (Cashout)"

    # Partidas adiadas ou canceladas
    if fixture_status in ['PST', 'CANC', 'CANCELLED', 'POSTPONED', 'ABD']:
        valor_aposta = float(aposta.get('valor_aposta') or 10.0)
        return 'ANULADA', valor_aposta, f"{fixture_status} | Partida Cancelada/Adiada -> Aposta ANULADA (Reembolso R$ {valor_aposta:.2f})"

    palpite = (aposta.get('palpite') or '').strip()
    mercado = (aposta.get('mercado') or '').strip()
    time_casa = (aposta.get('time_casa') or '').strip()
    time_fora = (aposta.get('time_fora') or '').strip()
    valor_aposta = float(aposta.get('valor_aposta') or 10.0)
    odd = float(aposta.get('odd') or 1.80)

    # Limite numérico (ex: 5.5 em "Menos de 5.5 Cartões")
    match_line = re.search(r'(\d+(?:\.\d+)?)', palpite)
    threshold = float(match_line.group(1)) if match_line else 5.5

    # Regra Oficial: 1 Cartão Vermelho = 2 Cartões
    cards_home = yh + (rh * 2)
    cards_away = ya + (ra * 2)
    total_match = cards_home + cards_away

    palpite_lower = palpite.lower()
    mercado_lower = mercado.lower()
    tc_lower = time_casa.lower()
    tf_lower = time_fora.lower()

    is_home_target = False
    is_away_target = False

    if (tc_lower and tc_lower in palpite_lower) or 'time casa' in palpite_lower or 'time casa' in mercado_lower:
        is_home_target = True
    elif (tf_lower and tf_lower in palpite_lower) or 'time fora' in palpite_lower or 'time fora' in mercado_lower:
        is_away_target = True

    if is_home_target:
        actual_cards = cards_home
        target_name = f"Cartões Time Casa ({time_casa})"
        card_breakdown = f"{yh} Amarelos + {rh} Vermelhos [peso 2]" if rh > 0 else f"{yh} Amarelos"
    elif is_away_target:
        actual_cards = cards_away
        target_name = f"Cartões Time Fora ({time_fora})"
        card_breakdown = f"{ya} Amarelos + {ra} Vermelhos [peso 2]" if ra > 0 else f"{ya} Amarelos"
    else:
        actual_cards = total_match
        target_name = "Total Cartões Jogo"
        tot_y = yh + ya
        tot_r = rh + ra
        card_breakdown = f"{tot_y} Amarelos + {tot_r} Vermelhos [peso 2]" if tot_r > 0 else f"{tot_y} Amarelos"

    is_over = ('mais' in palpite_lower or 'acima' in palpite_lower or 'over' in palpite_lower)

    if is_over:
        won = (actual_cards > threshold)
        comp = ">"
    else:
        won = (actual_cards < threshold)
        comp = "<"

    if won:
        payout = round(valor_aposta * odd, 2)
        detalhe = f"FT | {target_name}: {actual_cards} ({card_breakdown}) {comp} Limite {threshold} -> GANHA (Retorno R$ {payout:.2f})"
        return 'Ganha', payout, detalhe
    else:
        detalhe = f"FT | {target_name}: {actual_cards} ({card_breakdown}) {comp} Limite {threshold} -> PERDIDA"
        return 'Perdida', 0.0, detalhe

def recalcular_metas_diarias_db(cursor, usuario_id, data_referencia):
    """
    Recalcula a consolidação estatística de metas_diarias_cache para a data de referência informada.
    """
    # 1. Configuração ativa
    cursor.execute("""
        SELECT * FROM metas_diarias_config 
        WHERE usuario_id = %s AND is_ativa = 1 
        ORDER BY id DESC LIMIT 1
    """, (usuario_id,))
    config = cursor.fetchone()
    if not config:
        return

    meta_config_id = config['id']
    meta_lucro_diario = float(config.get('lucro_alvo') or 7.50)
    limite_perda_diario = float(config.get('stop_loss_diario') or -30.0)
    teto_reds = int(config.get('max_reds') or 3)
    total_apostas_alvo = int(config.get('total_apostas_alvo') or 10)

    # 2. Agregação das apostas do dia considerando fuso horário BRT (UTC-3)
    cursor.execute("""
        SELECT 
            COUNT(*) as total_cadastradas,
            SUM(CASE WHEN status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN 1 ELSE 0 END) as total_liquidadas,
            SUM(CASE WHEN status = 'Pendente' THEN 1 ELSE 0 END) as pendentes,
            SUM(CASE WHEN status IN ('Ganha', 'Meio Ganha') THEN 1 ELSE 0 END) as greens,
            SUM(CASE WHEN status IN ('ANULADA', 'Anulada') THEN 1 ELSE 0 END) as pushes,
            SUM(CASE WHEN status IN ('Perdida', 'Meio Perdida') THEN 1 ELSE 0 END) as reds,
            COALESCE(AVG(odd), 0) as odd_media,
            COALESCE(SUM(valor_aposta), 0) as total_apostado,
            COALESCE(SUM(CASE WHEN status NOT IN ('Pendente', 'Cancelada', 'CANCELADA') THEN valor_aposta ELSE 0 END), 0) as total_liquidado,
            COALESCE(SUM(CASE 
                WHEN status = 'Ganha' THEN ganhos_potenciais
                WHEN status = 'Meio Ganha' THEN (valor_aposta * ((odd + 1.0) / 2.0))
                WHEN status IN ('ANULADA', 'Anulada') THEN valor_aposta
                WHEN status = 'Meio Perdida' THEN (valor_aposta * 0.5)
                ELSE 0 
            END), 0) as ganhos_totais
        FROM apostas
        WHERE usuario_id = %s
          AND status NOT IN ('Cancelada', 'CANCELADA')
          AND DATE(DATE_SUB(COALESCE(data_hora_jogo, criado_em), INTERVAL 3 HOUR)) = %s
    """, (usuario_id, data_referencia))
    row = cursor.fetchone()

    total_cadastradas = int(row['total_cadastradas'] or 0)
    if total_cadastradas == 0:
        return

    total_liquidadas = int(row['total_liquidadas'] or 0)
    pendentes = int(row['pendentes'] or 0)
    greens = int(row['greens'] or 0)
    pushes = int(row['pushes'] or 0)
    reds = int(row['reds'] or 0)
    odd_media = round(float(row['odd_media'] or 0.0), 2)
    total_apostado = round(float(row['total_apostado'] or 0.0), 2)
    total_liquidado = round(float(row['total_liquidado'] or 0.0), 2)
    ganhos_totais = round(float(row['ganhos_totais'] or 0.0), 2)
    lucro_liquido = round(ganhos_totais - total_liquidado, 2)

    roi_pct = round((lucro_liquido / total_liquidado * 100.0), 2) if total_liquidado > 0 else 0.0
    progresso_lucro_pct = round(max(0.0, min(100.0, (lucro_liquido / meta_lucro_diario * 100.0))), 2) if meta_lucro_diario > 0 else 0.0
    progresso_apostas_pct = round(min(100.0, (total_cadastradas / total_apostas_alvo * 100.0)), 1) if total_apostas_alvo > 0 else 0.0

    if lucro_liquido <= limite_perda_diario or (reds >= teto_reds and lucro_liquido < 0):
        status_dia = 'STOP_LOSS_ATINGIDO'
    elif lucro_liquido >= meta_lucro_diario:
        status_dia = 'META_BATIDA'
    elif pendentes == 0 and total_liquidadas > 0:
        if lucro_liquido > 0:
            status_dia = 'SUPERAVITARIO'
        else:
            status_dia = 'DEFICITARIO'
    else:
        status_dia = 'EM_ANDAMENTO'

    cursor.execute("""
        INSERT INTO metas_diarias_cache (
            usuario_id, meta_config_id, data_referencia,
            total_apostas_cadastradas, total_apostas_liquidadas, total_apostado,
            odd_media_real, greens_count, pushes_count, reds_count, pendentes_count,
            ganhos_totais, lucro_liquido, roi_pct,
            progresso_apostas_pct, progresso_lucro_pct, status_dia, updated_at
        ) VALUES (
            %s, %s, %s,
            %s, %s, %s,
            %s, %s, %s, %s, %s,
            %s, %s, %s,
            %s, %s, %s, NOW()
        )
        ON DUPLICATE KEY UPDATE
            meta_config_id = VALUES(meta_config_id),
            total_apostas_cadastradas = VALUES(total_apostas_cadastradas),
            total_apostas_liquidadas = VALUES(total_apostas_liquidadas),
            total_apostado = VALUES(total_apostado),
            odd_media_real = VALUES(odd_media_real),
            greens_count = VALUES(greens_count),
            pushes_count = VALUES(pushes_count),
            reds_count = VALUES(reds_count),
            pendentes_count = VALUES(pendentes_count),
            ganhos_totais = VALUES(ganhos_totais),
            lucro_liquido = VALUES(lucro_liquido),
            roi_pct = VALUES(roi_pct),
            progresso_apostas_pct = VALUES(progresso_apostas_pct),
            progresso_lucro_pct = VALUES(progresso_lucro_pct),
            status_dia = VALUES(status_dia),
            updated_at = NOW()
    """, (
        usuario_id, meta_config_id, data_referencia,
        total_cadastradas, total_liquidadas, total_apostado,
        odd_media, greens, pushes, reds, pendentes,
        ganhos_totais, lucro_liquido, roi_pct,
        progresso_apostas_pct, progresso_lucro_pct, status_dia
    ))
    print(f"  🎯 [Metas Diárias Cache] {data_referencia}: {greens}G / {reds}R / {pushes}P | Lucro: R$ {lucro_liquido:+.2f} | Status: {status_dia}")

def main():
    print("=========================================================================")
    print("🚀 SANEAMENTO HISTÓRICO DE TRENDS E APOSTAS (DATAS <= 19/09/2026)")
    print("=========================================================================\n")

    conn = get_db_connection()
    cursor = conn.cursor()

    # 1. Carregar apostas de cartões no período
    cursor.execute("""
        SELECT a.*,
               DATE(DATE_SUB(COALESCE(a.data_hora_jogo, a.criado_em), INTERVAL 3 HOUR)) as data_ref
        FROM apostas a
        WHERE DATE(COALESCE(a.data_hora_jogo, a.criado_em)) <= '2026-09-19'
          AND (a.mercado LIKE '%Cart%' OR a.palpite LIKE '%Cart%')
        ORDER BY a.id ASC
    """)
    apostas = cursor.fetchall()
    print(f"📋 Total de apostas de cartões carregadas: {len(apostas)}")

    # 2. Mapear todas as fixtures envolvidas
    fids = list(set(a['fixture_id'] for a in apostas if a['fixture_id']))
    print(f"🏟️ Total de fixtures distintas associadas: {len(fids)}")

    # Carregar metadados das fixtures
    cursor.execute("""
        SELECT fixture_id, status, home_team_id, away_team_id, home_team, away_team,
               yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away
        FROM fixtures_trends
        WHERE fixture_id IN %s
    """, (tuple(fids),))
    trends_map = {r['fixture_id']: r for r in cursor.fetchall()}

    # Carregar cache existente
    cursor.execute("""
        SELECT fixture_id, team_id, yellow_cards, red_cards
        FROM match_statistics_cache
        WHERE fixture_id IN %s
    """, (tuple(fids),))
    cache_by_fid = {}
    for r in cursor.fetchall():
        fid = r['fixture_id']
        if fid not in cache_by_fid:
            cache_by_fid[fid] = []
        cache_by_fid[fid].append(r)

    # Identificar fixtures que precisam de consulta à API
    fixtures_para_enriquecer = []
    for fid in fids:
        c_list = cache_by_fid.get(fid, [])
        trend = trends_map.get(fid)
        # Se não tem ambos os times em cache ou se cartões em trends estão zerados sem conferência
        if len(c_list) < 2:
            fixtures_para_enriquecer.append(fid)

    print(f"⚡ Fixtures com estatísticas completas em cache: {len(fids) - len(fixtures_para_enriquecer)}")
    print(f"🌐 Fixtures que requerem consulta à API-Sports: {len(fixtures_para_enriquecer)}\n")

    # 3. Enriquecer fixtures via API-Sports
    api_calls_count = 0
    fixture_stats_resolved = {}

    for idx, fid in enumerate(fixtures_para_enriquecer, 1):
        trend = trends_map.get(fid, {})
        h_id = trend.get('home_team_id')
        a_id = trend.get('away_team_id')
        h_name = trend.get('home_team', 'Time Casa')
        a_name = trend.get('away_team', 'Time Fora')
        status_fix = trend.get('status')

        print(f"[{idx}/{len(fixtures_para_enriquecer)}] Consultando API para #{fid} ({h_name} vs {a_name})...")
        st_data = fetch_fixture_statistics_api(fid)
        api_calls_count += 1
        time.sleep(0.15) # Respeita rate limit

        if st_data and len(st_data) > 0:
            res = save_fixture_stats_to_db(cursor, fid, h_id, a_id, st_data)
            if res:
                yh, ya, rh, ra = res
                fixture_stats_resolved[fid] = (yh, ya, rh, ra, status_fix)
                print(f"   ↳ OK: Y: {yh}x{ya} | R: {rh}x{ra}")
        else:
            # Se não retornou estatísticas, checar status real da partida
            real_status = fetch_fixture_status_api(fid)
            if real_status and real_status in ['PST', 'CANC', 'POSTPONED', 'CANCELLED', 'ABD']:
                cursor.execute("UPDATE fixtures_trends SET status = %s WHERE fixture_id = %s", (real_status, fid))
                fixture_stats_resolved[fid] = (0, 0, 0, 0, real_status)
                print(f"   ↳ Partida com status '{real_status}' na API.")
            else:
                print(f"   ⚠️ Nenhuma estatística disponível na API para fixture #{fid}.")

    print(f"\n✅ Concluído enriquecimento via API ({api_calls_count} requisições realizadas).\n")

    # 4. Consolidar estatísticas para todas as 174 fixtures
    for fid in fids:
        if fid in fixture_stats_resolved:
            continue
        # Buscar do cache de match_statistics_cache
        trend = trends_map.get(fid, {})
        h_id = trend.get('home_team_id')
        status_fix = trend.get('status')
        c_list = cache_by_fid.get(fid, [])

        yh, ya, rh, ra = 0, 0, 0, 0
        if len(c_list) >= 2:
            for c in c_list:
                if h_id and c['team_id'] == h_id:
                    yh = c['yellow_cards'] or 0
                    rh = c['red_cards'] or 0
                else:
                    ya = c['yellow_cards'] or 0
                    ra = c['red_cards'] or 0
        else:
            yh = trend.get('yellow_cards_home') or 0
            ya = trend.get('yellow_cards_away') or 0
            rh = trend.get('red_cards_home') or 0
            ra = trend.get('red_cards_away') or 0

        fixture_stats_resolved[fid] = (yh, ya, rh, ra, status_fix)

    # 5. Reliquidação de cada aposta
    print("=========================================================================")
    print("⚖️ RELIQUIDAÇÃO DE APOSTAS E CONCILIAÇÃO FINANCEIRA")
    print("=========================================================================\n")

    apostas_retificadas = 0
    estornos_realizados = 0
    creditos_realizados = 0
    datas_afetadas = set()

    for aposta in apostas:
        aposta_id = aposta['id']
        fid = aposta.get('fixture_id')
        status_anterior = aposta.get('status')
        usuario_id = aposta.get('usuario_id')
        is_confirmada = (int(aposta.get('confirmada') or 0) == 1)
        data_ref = str(aposta.get('data_ref'))

        stats_fix = fixture_stats_resolved.get(fid)
        if not stats_fix:
            print(f"⚠️ Fixture #{fid} sem estatísticas resolvidas para Aposta #{aposta_id}. Mantendo status.")
            continue

        yh, ya, rh, ra, fix_status = stats_fix

        novo_status, novo_ganho, novo_detalhe = evaluate_cards_bet(aposta, yh, ya, rh, ra, fix_status)

        # Se houve mudança de status ou de detalhamento
        mudou_status = (novo_status != status_anterior)

        if mudou_status:
            apostas_retificadas += 1
            datas_afetadas.add(data_ref)
            print(f"🔄 Aposta #{aposta_id} [{aposta['time_casa']} vs {aposta['time_fora']}] [{data_ref}]")
            print(f"   Status Anterior: {status_anterior} -> Novo: {novo_status}")
            print(f"   Palpite: {aposta['palpite']} | Y: {yh}x{ya} | R: {rh}x{ra}")
            print(f"   Detalhe: {novo_detalhe}")

            # Atualizar registro da aposta
            cursor.execute("""
                UPDATE apostas
                SET status = %s,
                    resultado_detalhado = %s,
                    ganhos_potenciais = %s,
                    processado_em = NOW(),
                    updated_at = NOW()
                WHERE id = %s
            """, (novo_status, novo_detalhe, novo_ganho, aposta_id))

            # Conciliação Financeira para apostas confirmadas (banca real)
            if is_confirmada:
                if status_anterior == 'Ganha' and novo_status == 'Perdida':
                    # Estorno do valor ganho anteriormente
                    valor_pago = float(aposta.get('ganhos_potenciais') or (float(aposta['valor_aposta']) * float(aposta['odd'])))
                    desc = f"Estorno Retorno Aposta #{aposta_id} - Correção Oficial Cartões (1 Vermelho = 2)"
                    if estornar_retorno_aposta(cursor, usuario_id, aposta_id, valor_pago, desc):
                        estornos_realizados += 1

                elif status_anterior in ['Perdida', 'Pendente', 'ANULADA'] and novo_status == 'Ganha':
                    desc = f"Crédito Retorno Aposta #{aposta_id} - Vitória Oficial Cartões (1 Vermelho = 2)"
                    if creditar_retorno_aposta(cursor, usuario_id, aposta_id, novo_ganho, desc):
                        creditos_realizados += 1
        else:
            # Apenas atualiza o resultado_detalhado com a auditoria precisa se estiver vazio ou desatualizado
            if novo_detalhe and novo_detalhe != aposta.get('resultado_detalhado'):
                cursor.execute("""
                    UPDATE apostas
                    SET resultado_detalhado = %s,
                        updated_at = NOW()
                    WHERE id = %s
                """, (novo_detalhe, aposta_id))

    print(f"\n📊 Total de apostas com status retificado: {apostas_retificadas}")
    print(f"💸 Estornos financeiros lançados: {estornos_realizados}")
    print(f"💰 Créditos financeiros lançados: {creditos_realizados}\n")

    # 6. Recalcular metas diárias para todas as datas afetadas
    print("=========================================================================")
    print("🎯 RECALCULANDO CACHE DE METAS DIÁRIAS")
    print("=========================================================================\n")

    # Também incluir todas as datas onde houve apostas confirmadas para garantir integridade total
    cursor.execute("""
        SELECT DISTINCT DATE(DATE_SUB(COALESCE(data_hora_jogo, criado_em), INTERVAL 3 HOUR)) as dt
        FROM apostas
        WHERE DATE(COALESCE(data_hora_jogo, criado_em)) <= '2026-09-19'
          AND confirmada = 1
        ORDER BY dt ASC
    """)
    all_confirmed_dates = [str(r['dt']) for r in cursor.fetchall() if r['dt']]
    datas_para_recalcular = sorted(list(datas_afetadas.union(set(all_confirmed_dates))))

    print(f"📅 Datas para recalcular metas diárias ({len(datas_para_recalcular)} datas): {datas_para_recalcular}")
    for dt in datas_para_recalcular:
        # Busca usuários com apostas nessa data
        cursor.execute("""
            SELECT DISTINCT usuario_id 
            FROM apostas 
            WHERE DATE(DATE_SUB(COALESCE(data_hora_jogo, criado_em), INTERVAL 3 HOUR)) = %s
        """, (dt,))
        u_ids = [r['usuario_id'] for r in cursor.fetchall()]
        for uid in u_ids:
            recalcular_metas_diarias_db(cursor, uid, dt)

    # 7. Auditoria Final do Saldo
    print("\n=========================================================================")
    print("🏦 AUDITORIA DO SALDO DA CONTA CORRENTE")
    print("=========================================================================\n")
    cursor.execute("""
        SELECT u.id, u.nome, u.saldo_conta_corrente,
               COALESCE(SUM(cc.valor), 0.0) as saldo_calculado_extrato
        FROM usuario u
        LEFT JOIN conta_corrente cc ON cc.usuario_id = u.id
        GROUP BY u.id
    """)
    for u in cursor.fetchall():
        saldo_db = float(u['saldo_conta_corrente'] or 0.0)
        saldo_extrato = float(u['saldo_calculado_extrato'] or 0.0)
        diff = round(saldo_db - saldo_extrato, 2)
        print(f"Usuário #{u['id']} ({u['nome']}): Saldo DB = R$ {saldo_db:.2f} | Saldo Extrato = R$ {saldo_extrato:.2f} | Divergência = R$ {diff:.2f}")

    conn.close()
    print("\n✨ Processamento de saneamento histórico concluído com sucesso!")

if __name__ == "__main__":
    main()
