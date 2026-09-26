import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../scripts")))
from db_config import get_db_connection

conn = get_db_connection()
cursor = conn.cursor()

sql = """
    SELECT fixture_id, home_team, away_team, fixture_date, status, referee_name, prediction_text 
    FROM fixtures_trends 
    WHERE (home_team LIKE '%Santos%' OR away_team LIKE '%Santos%')
      AND (home_team LIKE '%Mirassol%' OR away_team LIKE '%Mirassol%')
    ORDER BY fixture_date DESC
    LIMIT 5
"""
cursor.execute(sql)
rows = cursor.fetchall()
print(f"Encontradas {len(rows)} partidas para Santos x Mirassol:\n")
for r in rows:
    print(f"ID: {r['fixture_id']} | Data: {r['fixture_date']} | Status: {r['status']}")
    print(f"Mandante: {r['home_team']} | Visitante: {r['away_team']}")
    print(f"Árbitro: {r['referee_name']}")
    print(f"Prediction Text:\n{r['prediction_text']}\n" + "-"*50)

conn.close()
