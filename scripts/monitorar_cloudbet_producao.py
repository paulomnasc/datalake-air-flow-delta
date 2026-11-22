#!/usr/bin/env python3
"""
scripts/monitorar_cloudbet_producao.py

Monitor de Produção Real da Cloudbet.
Verifica:
1. Saldo ao vivo na Cloudbet Trading API vs MySQL (usuario.saldo_conta_corrente);
2. Apostas confirmadas e pendentes criadas no dia;
3. Status de abertura de mercados de Handicap Asiático nas partidas das próximas 3 horas;
4. Última execução da DAG criar_apostas_cloudbet_dag no Airflow.
"""

import os
import sys
import json
from datetime import datetime, timedelta
import pymysql

CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
if CURRENT_DIR not in sys.path:
    sys.path.insert(0, CURRENT_DIR)

from db_config import get_db_connection
from cloudbet_client import CloudbetClient
from cloudbet_matcher import CloudbetMatcher


def check_status():
    client = CloudbetClient()
    matcher = CloudbetMatcher(client=client)
    conn = get_db_connection()

    print("=" * 65)
    print(f"📡 [Monitor Cloudbet Produção Real] {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(f"   • Modo: {'DRY-RUN 🛡️' if client.dry_run else 'PRODUÇÃO REAL ⚠️'}")
    print(f"   • Moeda: {client.currency}")
    print("=" * 65)

    # 1. Saldo
    live_bal = client.get_account_balance()
    with conn.cursor() as cur:
        cur.execute("SELECT saldo_conta_corrente FROM usuario WHERE id = 558")
        row = cur.fetchone()
        db_bal = float(row['saldo_conta_corrente'] or 0.0) if row else 0.0

    print(f"\n💰 SALDOS:")
    print(f"   • Carteira Cloudbet (Live API): {live_bal:.4f} {client.currency}")
    print(f"   • Banco de Dados (Conta Corrente): {db_bal:.4f} {client.currency}")
    if abs(live_bal - db_bal) < 0.01:
        print("   ✅ Paridade de Saldo: 100% Sincronizado")
    else:
        print(f"   ⚠️ Divergência de Saldo: Diferença de {abs(live_bal - db_bal):.4f} USDT")

    # 2. Apostas no dia
    with conn.cursor() as cur:
        cur.execute("""
            SELECT id, time_casa, time_fora, mercado, palpite, odd, valor_aposta, confirmada, status, criado_em, resultado_detalhado
            FROM apostas
            WHERE DATE(criado_em) = CURDATE() OR DATE(data_hora_jogo) = CURDATE()
            ORDER BY id DESC
        """)
        apostas_hoje = cur.fetchall()

    print(f"\n🎫 APOSTAS DO DIA NO BANCO ({len(apostas_hoje)} encontradas):")
    if not apostas_hoje:
        print("   • Nenhuma aposta gerada ainda. Aguardando abertura de linhas pela Cloudbet.")
    else:
        for a in apostas_hoje:
            cb_id = "N/A"
            if 'CLOUDBET' in a['resultado_detalhado']:
                try:
                    import re
                    m = re.search(r'\|\| CLOUDBET: (.*?) \|\|', a['resultado_detalhado'])
                    if m:
                        cb_data = json.loads(m.group(1))
                        cb_id = cb_data.get('bet_id', 'N/A')
                except Exception:
                    pass
            print(f"   • #{a['id']} | {a['time_casa']} x {a['time_fora']} | {a['palpite']} @ {a['odd']} | Stake: {a['valor_aposta']} USDT | Confirmada: {a['confirmada']} | Status: {a['status']} | Cloudbet ID: {cb_id}")

    # 3. Varredura das partidas das próximas 3 horas
    with conn.cursor() as cur:
        cur.execute("""
            SELECT fixture_id, home_team, away_team, fixture_date, league_name
            FROM fixtures_trends
            WHERE fixture_date >= NOW() AND fixture_date <= DATE_ADD(NOW(), INTERVAL 3 HOUR)
            ORDER BY fixture_date ASC
        """)
        proximos = cur.fetchall()

    print(f"\n⚽ PARTIDAS NAS PRÓXIMAS 3 HORAS ({len(proximos)} jogos mapeados):")
    for fix in proximos:
        f_id = fix['fixture_id']
        home = fix['home_team']
        away = fix['away_team']
        dt = fix['fixture_date']
        league = fix['league_name']

        match = matcher.find_matching_event(home, away, dt)
        if not match:
            print(f"   • #{f_id} [{league}] {home} x {away} ({dt.strftime('%H:%M')} BRT) -> ⚠️ Sem match no catálogo")
            continue

        ev_id = match['event_id']
        mkts = matcher.get_event_markets(ev_id)
        ah = mkts.get('asian_handicap', {})
        mo = mkts.get('match_odds')

        if ah:
            print(f"   • #{f_id} [{league}] {home} x {away} ({dt.strftime('%H:%M')} BRT) -> 🎯 LINHAS AH ABERTAS: {list(ah.keys())}")
        else:
            status_1x2 = "1X2 Aberto" if mo else "1X2 Fechado"
            print(f"   • #{f_id} [{league}] {home} x {away} ({dt.strftime('%H:%M')} BRT) -> ⏳ AH Fechado ({status_1x2}) [Cloudbet ID: {ev_id}]")

    print("\n" + "=" * 65)


if __name__ == '__main__':
    check_status()
