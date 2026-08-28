import pymysql
import re

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
            print(f"✅ Conectado ao MySQL em {host}:{port}")
            return conn
        except Exception:
            continue
    return None

conn = get_db_connection()
if conn:
    cursor = conn.cursor()
    sql = """
        SELECT fixture_id, home_team, away_team, fixture_date, status, referee_name, prediction_text 
        FROM fixtures_trends 
        WHERE (home_team LIKE '%Oper%rio%' OR away_team LIKE '%Oper%rio%')
          AND (home_team LIKE '%Vila%Nova%' OR away_team LIKE '%Vila%Nova%')
        ORDER BY fixture_date DESC
        LIMIT 5
    """
    cursor.execute(sql)
    rows = cursor.fetchall()
    print(f"\nEncontradas {len(rows)} partidas para Operário x Vila Nova:\n")
    for r in rows:
        print(f"ID: {r['fixture_id']} | Data: {r['fixture_date']} | Status: {r['status']}")
        print(f"Mandante: {r['home_team']} | Visitante: {r['away_team']}")
        print(f"Árbitro: {r['referee_name']}")
        print(f"Prediction Text:\n{r['prediction_text']}\n" + "-"*50)
    conn.close()
