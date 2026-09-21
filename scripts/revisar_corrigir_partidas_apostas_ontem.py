#!/usr/bin/env python3
"""
Script de Revisão, Saneamento e Reliquidação das Partidas e Apostas de 20/09/2026.
1. Varre partidas encerradas de 20/09/2026 e obtém estatísticas oficiais da API-Sports;
2. Persiste ambos os times em 'match_statistics_cache' e atualiza 'fixtures_trends';
3. Revisa todas as apostas no mercado de Cartões aplicando a regra: 1 Cartão Vermelho = 2 Cartões;
4. Retifica apostas indevidamente dadas como Ganhas para Perdidas;
5. Realiza o estorno financeiro em 'conta_corrente' e reconcilia o 'saldo_conta_corrente';
6. Recalcula os indicadores do sistema de metas diárias em 'metas_diarias_cache'.
"""

import os
import sys
import re
import time
import requests
import pymysql
from datetime import datetime

def get_db_connection():
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
            print(f"✅ Conectado ao MySQL ({host}:{port})")
            return conn
        except Exception:
            continue

    print("❌ Falha ao conectar no MySQL.")
    sys.exit(1)

def fetch_and_update_fixture_stats(cursor, fixture_id, home_team_id, headers):
    """
    Busca estatísticas oficiais na API-Sports e salva em match_statistics_cache e fixtures_trends.
    """
    url_st = f"https://v3.football.api-sports.io/fixtures/statistics?fixture={fixture_id}"
    try:
        res = requests.get(url_st, headers=headers, timeout=12)
        if res.status_code != 200:
            print(f"⚠️ API retornou status {res.status_code} para fixture #{fixture_id}")
            return False

        st_json = res.json()
        st_data = st_json.get("response", [])
        if not st_data:
            print(f"ℹ️ Estatísticas ainda não disponíveis para fixture #{fixture_id}")
            return False

        team_stats = {}
        for idx, team_st in enumerate(st_data):
            t_id = team_st.get("team", {}).get("id")
            is_home = (t_id == home_team_id) if home_team_id else (idx == 0)
            t_key = 'home' if is_home else 'away'
            team_stats[t_key] = {
                'team_id': t_id,
                'yc': 0,
                'rc': 0,
                'ck': 0,
                'sg': 0,
                'st': 0,
                'xg': 0.0
            }
            for s in team_st.get("statistics", []):
                s_type = (s.get("type") or "").strip()
                s_val = s.get("value")
                if s_val is not None:
                    if s_type == "Yellow Cards":
                        team_stats[t_key]['yc'] = int(s_val)
                    elif s_type == "Red Cards":
                        team_stats[t_key]['rc'] = int(s_val)
                    elif s_type == "Corner Kicks":
                        team_stats[t_key]['ck'] = int(s_val)
                    elif s_type in ["Shots on Goal", "Shots on Target"]:
                        team_stats[t_key]['sg'] = int(s_val)
                    elif s_type in ["Total Shots", "Shots"]:
                        team_stats[t_key]['st'] = int(s_val)
                    elif s_type.lower().replace("_", " ").strip() in ["expected goals", "xg", "expectedgoals"]:
                        try:
                            team_stats[t_key]['xg'] = float(s_val)
                        except Exception:
                            pass

        h_info = team_stats.get('home', {})
        a_info = team_stats.get('away', {})

        yh = h_info.get('yc', 0)
        ya = a_info.get('yc', 0)
        rh = h_info.get('rc', 0)
        ra = a_info.get('rc', 0)
        ck_h = h_info.get('ck', 0)
        ck_a = a_info.get('ck', 0)
        sg_h = h_info.get('sg', 0)
        sg_a = a_info.get('sg', 0)
        st_h = h_info.get('st', 0)
        st_a = a_info.get('st', 0)
        xg_h = h_info.get('xg', 0.0)
        xg_a = a_info.get('xg', 0.0)

        # Salva mandante e visitante em match_statistics_cache
        for t_key in ['home', 'away']:
            t_data = team_stats.get(t_key)
            if t_data and t_data.get('team_id'):
                cursor.execute("""
                    INSERT INTO match_statistics_cache (fixture_id, team_id, corners, yellow_cards, red_cards)
                    VALUES (%s, %s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                        corners = VALUES(corners),
                        yellow_cards = VALUES(yellow_cards),
                        red_cards = VALUES(red_cards)
                """, (fixture_id, t_data['team_id'], t_data['ck'], t_data['yc'], t_data['rc']))

        # Atualiza fixtures_trends com estatísticas oficiais reais
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

        print(f"  ⚡ Fixture #{fixture_id}: Atualizado -> Y: {yh}x{ya} | R: {rh}x{ra} | Cantos: {ck_h}x{ck_a} | xG: {xg_h}x{xg_a}")
        return True
    except Exception as e:
        print(f"❌ Erro ao atualizar estatísticas da fixture #{fixture_id}: {e}")
        return False

def estornar_retorno_aposta(cursor, usuario_id, aposta_id, valor_retorno, descricao):
    """
    Realiza o estorno de um retorno creditado indevidamente no extrato da conta corrente.
    """
    cursor.execute("SELECT id FROM conta_corrente WHERE usuario_id = %s AND aposta_id = %s AND tipo = 'ESTORNO_APOSTA'", (usuario_id, aposta_id))
    if cursor.fetchone():
        print(f"  ℹ️ [Estorno Ignorado] Aposta #{aposta_id} já possui estorno lançado anteriormente.")
        return

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
    print(f"  💸 [Estorno Conta Corrente] Aposta #{aposta_id}: Lançado {valor_estorno:.2f} | Saldo: R$ {saldo_anterior:.2f} -> R$ {saldo_posterior:.2f}")
    return saldo_posterior

def recalcular_metas_diarias_db(cursor, usuario_id, data_referencia):
    """
    Recalcula a consolidação estatística de metas_diarias_cache para o dia informado.
    """
    print(f"\n🎯 Recalculando Metas Diárias para Usuário #{usuario_id} na data {data_referencia}...")
    
    # 1. Busca configurações da meta ativa do usuário
    cursor.execute("SELECT * FROM metas_diarias_config WHERE usuario_id = %s AND is_ativa = 1 ORDER BY id DESC LIMIT 1", (usuario_id,))
    config = cursor.fetchone()
    if not config:
        print("  ⚠️ Nenhuma configuração de meta ativa encontrada para o usuário.")
        return

    meta_config_id = config['id']
    meta_lucro_diario = float(config.get('lucro_alvo') or 7.50)
    limite_perda_diario = float(config.get('stop_loss_diario') or -30.0)
    teto_reds = int(config.get('max_reds') or 3)
    total_apostas_alvo = int(config.get('total_apostas_alvo') or 10)

    # 2. Agregação das apostas do dia
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

    # Determinação do status do dia
    if lucro_liquido <= limite_perda_diario or (reds >= teto_reds and lucro_liquido < 0):
        status_dia = 'STOP_LOSS_ATINGIDO'
    elif lucro_liquido >= meta_lucro_diario:
        status_dia = 'META_BATIDA'
    elif pendentes == 0 and total_liquidadas > 0:
        if lucro_liquido > 0:
            status_dia = 'SUPERAVITARIO'
        elif lucro_liquido == 0:
            status_dia = 'NEUTRO'
        else:
            status_dia = 'DEFICITARIO'
    elif total_cadastradas == 0:
        status_dia = 'SEM_APOSTAS'
    else:
        status_dia = 'EM_ANDAMENTO'

    cursor.execute("""
        INSERT INTO metas_diarias_cache (
            usuario_id, meta_config_id, data_referencia,
            total_apostas_cadastradas, total_apostas_liquidadas,
            total_apostado, odd_media_real,
            greens_count, pushes_count, reds_count, pendentes_count,
            ganhos_totais, lucro_liquido, roi_pct,
            progresso_apostas_pct, progresso_lucro_pct,
            status_dia, updated_at
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
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
        total_cadastradas, total_liquidadas,
        total_apostado, odd_media,
        greens, pushes, reds, pendentes,
        ganhos_totais, lucro_liquido, roi_pct,
        progresso_apostas_pct, progresso_lucro_pct,
        status_dia
    ))

    print(f"  ✅ [Cache Metas Diárias Atualizado] Greens: {greens} | Reds: {reds} | Pushes: {pushes} | Lucro Líq: R$ {lucro_liquido:.2f} | Status: {status_dia}")

def main():
    print("=" * 80)
    print("🚀 INICIANDO REVISÃO E SANEAMENTO GLOBAL DE PARTIDAS E APOSTAS (20/09/2026)")
    print("=" * 80)

    conn = get_db_connection()
    cursor = conn.cursor()

    api_key = os.environ.get('FOOTBALL_API_KEY') or "0327019c6fab54df2ea46009b5f0844b"
    headers = {'x-apisports-key': api_key, 'User-Agent': 'Mozilla/5.0'}

    # 1. Buscar partidas FT de ontem associadas a apostas ou com cartões zerados
    cursor.execute("""
        SELECT DISTINCT f.fixture_id, f.home_team, f.away_team, f.home_team_id, f.away_team_id,
                        f.yellow_cards_home, f.yellow_cards_away, f.red_cards_home, f.red_cards_away
        FROM fixtures_trends f
        WHERE DATE(f.fixture_date) = '2026-09-20' 
          AND f.status = 'FT'
          AND (
            (f.yellow_cards_home = 0 AND f.yellow_cards_away = 0)
            OR f.fixture_id IN (SELECT DISTINCT fixture_id FROM apostas WHERE fixture_id IS NOT NULL)
          )
        ORDER BY f.fixture_id ASC
    """)
    fixtures = cursor.fetchall()
    print(f"\n📋 Encontradas {len(fixtures)} partidas de 20/09 para verificação/atualização de estatísticas oficiais...")

    for fix in fixtures:
        fid = fix['fixture_id']
        htid = fix.get('home_team_id')
        h_name = fix['home_team']
        a_name = fix['away_team']
        print(f"\n🔍 Verificando Fixture #{fid} [{h_name} vs {a_name}]...")
        fetch_and_update_fixture_stats(cursor, fid, htid, headers)
        time.sleep(0.3)

    # 2. Revisar e Reliquidar todas as apostas de cartões de 20/09/2026
    print("\n" + "=" * 80)
    print("🔍 REVISANDO E RELIQUIDANDO APOSTAS DE CARTÕES DE 20/09/2026...")
    print("=" * 80)

    cursor.execute("""
        SELECT a.id, a.usuario_id, a.fixture_id, a.time_casa, a.time_fora, a.mercado, a.palpite,
               a.odd, a.valor_aposta, a.ganhos_potenciais, a.status, a.confirmada, a.resultado_detalhado,
               f.yellow_cards_home, f.yellow_cards_away, f.red_cards_home, f.red_cards_away
        FROM apostas a
        JOIN fixtures_trends f ON a.fixture_id = f.fixture_id
        WHERE DATE(DATE_SUB(COALESCE(a.data_hora_jogo, a.criado_em), INTERVAL 3 HOUR)) = '2026-09-20'
          AND a.mercado LIKE '%Cart%'
          AND a.status NOT IN ('Cashout', 'Cancelada', 'CANCELADA')
        ORDER BY a.id ASC
    """)
    apostas_cartoes = cursor.fetchall()
    print(f"📊 Total de apostas de cartões analisadas: {len(apostas_cartoes)}")

    reliquidadas_count = 0

    for ap in apostas_cartoes:
        ap_id = ap['id']
        u_id = ap['usuario_id']
        fid = ap['fixture_id']
        t_casa = ap['time_casa']
        t_fora = ap['time_fora']
        palpite = ap['palpite']
        status_anterior = ap['status']
        valor_aposta = float(ap['valor_aposta'] or 10.0)
        odd = float(ap['odd'] or 1.0)
        ganhos_pot = float(ap['ganhos_potenciais'] or (valor_aposta * odd))

        yh = ap.get('yellow_cards_home') or 0
        ya = ap.get('yellow_cards_away') or 0
        rh = ap.get('red_cards_home') or 0
        ra = ap.get('red_cards_away') or 0

        # Regra Oficial: 1 Cartão Vermelho conta como 2 Cartões para liquidação
        total_cards = yh + ya + ((rh + ra) * 2)
        cards_home = yh + (rh * 2)
        cards_away = ya + (ra * 2)

        match_thresh = re.search(r'(\d+(?:\.\d+)?)', palpite)
        threshold = float(match_thresh.group(1)) if match_thresh else 5.5

        palpite_lower = palpite.lower()
        tc_lower = (t_casa or '').lower()
        tf_lower = (t_fora or '').lower()

        is_home_target = (tc_lower and tc_lower in palpite_lower) or 'time casa' in palpite_lower
        is_away_target = (tf_lower and tf_lower in palpite_lower) or 'time fora' in palpite_lower

        if is_home_target:
            actual_cards = cards_home
            target_name = f"Cartões Time Casa ({t_casa})"
            breakdown = f"{yh} Amarelos + {rh} Vermelhos [peso 2]" if rh > 0 else f"{yh} Amarelos"
        elif is_away_target:
            actual_cards = cards_away
            target_name = f"Cartões Time Fora ({t_fora})"
            breakdown = f"{ya} Amarelos + {ra} Vermelhos [peso 2]" if ra > 0 else f"{ya} Amarelos"
        else:
            actual_cards = total_cards
            target_name = "Total Cartões Jogo"
            tot_y = yh + ya
            tot_r = rh + ra
            breakdown = f"{tot_y} Amarelos + {tot_r} Vermelhos [peso 2]" if tot_r > 0 else f"{tot_y} Amarelos"

        is_over = ('mais' in palpite_lower or 'acima' in palpite_lower or 'over' in palpite_lower)
        if is_over:
            won = (actual_cards > threshold)
            comp = ">" if won else "<="
        else:
            won = (actual_cards < threshold)
            comp = "<" if won else ">="

        novo_status = 'Ganha' if won else 'Perdida'
        novo_detalhe = f"FT | {target_name}: {actual_cards} ({breakdown}) {comp} Limite {threshold} -> Aposta {novo_status.upper()}"

        print(f"\n🎯 Avaliando Aposta #{ap_id} [{t_casa} vs {t_fora}] - Palpite: '{palpite}':")
        print(f"   Amarelos: {yh}x{ya} | Vermelhos: {rh}x{ra} -> Total Avaliado: {actual_cards} ({breakdown})")
        print(f"   Status Anterior: {status_anterior} -> Novo Status Oficial: {novo_status}")

        if status_anterior == 'Ganha' and novo_status == 'Perdida':
            # Houve crédito indevido! Estornar retorno da aposta
            print(f"   ⚠️ RETIFICAÇÃO NECESSÁRIA: Aposta era GANHA e na verdade foi PERDIDA!")
            desc_estorno = f"Estorno Retorno Aposta #{ap_id} ({t_casa} x {t_fora} - Retificação Súmula Oficial: 1 Vermelho = 2 Cartões)"
            estornar_retorno_aposta(cursor, u_id, ap_id, ganhos_pot, desc_estorno)

            cursor.execute("""
                UPDATE apostas 
                SET status = 'Perdida', 
                    resultado_detalhado = %s, 
                    updated_at = NOW() 
                WHERE id = %s
            """, (novo_detalhe, ap_id))
            reliquidadas_count += 1

        elif status_anterior != novo_status:
            cursor.execute("""
                UPDATE apostas 
                SET status = %s, 
                    resultado_detalhado = %s, 
                    updated_at = NOW() 
                WHERE id = %s
            """, (novo_status, novo_detalhe, ap_id))
            reliquidadas_count += 1
        else:
            # Status permanece o mesmo, mas atualiza o detalhe com a memória de cálculo exata
            cursor.execute("""
                UPDATE apostas 
                SET resultado_detalhado = %s, 
                    updated_at = NOW() 
                WHERE id = %s
            """, (novo_detalhe, ap_id))

    # 3. Recalcular o Cache de Metas Diárias de 20/09/2026
    recalcular_metas_diarias_db(cursor, 558, '2026-09-20')

    print("\n" + "=" * 80)
    print(f"✅ SANEAMENTO CONCLUÍDO COM SUCESSO! {reliquidadas_count} apostas retificadas.")
    print("=" * 80)

    cursor.close()
    conn.close()

if __name__ == "__main__":
    main()
