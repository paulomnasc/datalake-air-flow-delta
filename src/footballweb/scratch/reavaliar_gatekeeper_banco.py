import math
import re
import pymysql

# Conexão MySQL no port 23306
conn = pymysql.connect(
    host='127.0.0.1',
    port=23306,
    user='root',
    password='YM11rMrT32xH0E6N',
    database='footballweb',
    cursorclass=pymysql.cursors.DictCursor
)

def factorial(n):
    return math.factorial(n)

try:
    with conn.cursor() as cursor:
        # 1. Média Histórica de Odds Vencedoras (Under Cartões)
        sql_avg = """
            SELECT AVG(odd) as avg_odd, COUNT(*) as total_vitorias 
            FROM apostas 
            WHERE status = 'Ganha' 
              AND (mercado LIKE '%cartõ%' OR mercado LIKE '%card%') 
              AND (palpite LIKE '%Menos%' OR palpite LIKE '%under%')
        """
        cursor.execute(sql_avg)
        row_avg = cursor.fetchone()
        
        avg_winning_odd = round(float(row_avg['avg_odd']), 2) if row_avg and row_avg['avg_odd'] and row_avg['total_vitorias'] > 0 else 1.60
        max_allowed_odd = round(max(2.00, avg_winning_odd + 0.35), 2)
        
        print("=== GESTÃO DO GATEKEEPER DINÂMICO ===")
        print(f"Média Histórica de Odds Vencedoras (Under Cartões): {avg_winning_odd}")
        print(f"Teto Dinâmico de Segurança Calculado: {max_allowed_odd}\n")

        # 2. Buscar apostas
        cursor.execute("SELECT * FROM apostas ORDER BY id ASC")
        apostas = cursor.fetchall()

        aprovados_count = 0
        no_bet_count = 0
        nao_analisado_count = 0

        update_sql = """
            UPDATE apostas 
            SET status_gatekeeper = %s,
                odd_justa = %s,
                probabilidade_poisson = %s,
                ev_percentual = %s
            WHERE id = %s
        """

        for aposta in apostas:
            aposta_id = aposta['id']
            palpite = aposta['palpite'] or ''
            mercado = aposta['mercado'] or ''
            odd = float(aposta['odd'])
            fixture_id = aposta['fixture_id']
            time_casa = aposta['time_casa'] or ''
            time_fora = aposta['time_fora'] or ''

            is_over = ('over' in palpite.lower() or 'mais' in palpite.lower())
            is_cartoes = ('cartõ' in mercado.lower() or 'card' in mercado.lower())

            odd_justa = None
            prob_poisson = None
            ev_percentual = None
            match_line_chk = re.search(r'(\d+\.\d+|\d+)', palpite)
            line_chk = float(match_line_chk.group(1)) if match_line_chk else 5.5

            if is_over or (is_cartoes and is_over) or (is_cartoes and line_chk < 7.5):
                status_gatekeeper = 'NO_BET'
            elif is_cartoes:
                fixture = None
                if fixture_id:
                    cursor.execute("SELECT prediction_text FROM fixtures_trends WHERE fixture_id = %s LIMIT 1", (fixture_id,))
                    fixture = cursor.fetchone()

                if not fixture and time_casa and time_fora:
                    sql_fix = """
                        SELECT prediction_text 
                        FROM fixtures_trends 
                        WHERE (home_team LIKE %s OR away_team LIKE %s)
                          AND (home_team LIKE %s OR away_team LIKE %s)
                        ORDER BY fixture_date DESC 
                        LIMIT 1
                    """
                    cursor.execute(sql_fix, (f"%{time_casa}%", f"%{time_casa}%", f"%{time_fora}%", f"%{time_fora}%"))
                    fixture = cursor.fetchone()

                if fixture and fixture.get('prediction_text'):
                    pred_text = fixture['prediction_text']
                    match_xc = re.search(r'xC(?::|\s+elevado)?\s*\(?(\d+\.\d+|\d+)', pred_text, re.IGNORECASE)
                    xc = float(match_xc.group(1)) if match_xc else None

                    if xc and xc > 0:
                        match_line = re.search(r'(\d+\.\d+|\d+)', palpite)
                        line = float(match_line.group(1)) if match_line else 5.5

                        k_max = int(math.floor(line))
                        prob_under_cdf = 0.0
                        for k in range(k_max + 1):
                            prob_under_cdf += (math.exp(-xc) * (xc ** k)) / factorial(k)

                        prob_poisson = round(min(100.0, max(0.0, prob_under_cdf * 100.0)), 2)

                        if prob_poisson > 0:
                            odd_justa = round(100.0 / prob_poisson, 2)
                            ev_percentual = round((((prob_poisson / 100.0) * odd) - 1.0) * 100.0, 2)

                        if odd > max_allowed_odd:
                            status_gatekeeper = 'NO_BET'
                        elif ev_percentual is not None and ev_percentual >= 0 and prob_poisson >= 50.0:
                            status_gatekeeper = 'APROVADO'
                        else:
                            status_gatekeeper = 'NO_BET'

            if status_gatekeeper == 'APROVADO':
                aprovados_count += 1
            elif status_gatekeeper == 'NO_BET':
                no_bet_count += 1
            else:
                nao_analisado_count += 1

            cursor.execute(update_sql, (status_gatekeeper, odd_justa, prob_poisson, ev_percentual, aposta_id))

        conn.commit()

        print("Reavaliação concluída com sucesso!")
        print(f"Total de Apostas Processadas: {len(apostas)}")
        print(f"Status APROVADO (+EV): {aprovados_count}")
        print(f"Status NO_BET: {no_bet_count}")
        print(f"Status NAO_ANALISADO: {nao_analisado_count}")

finally:
    conn.close()
