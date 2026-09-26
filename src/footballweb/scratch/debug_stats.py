import sys
import os
sys.path.insert(0, os.path.abspath(os.path.join(os.path.dirname(__file__), "../../../scripts")))
from db_config import get_db_connection

conn = get_db_connection()
cursor = conn.cursor()

# Árbitro stats
cursor.execute("SELECT * FROM referee_stats WHERE name LIKE '%Horn%'")
ref = cursor.fetchall()
print("Árbitro Stats:", ref)

# Equipes
cursor.execute("SELECT name, average_yellow_cards, average_red_cards FROM team_card_stats WHERE name LIKE '%Oper%rio%' OR name LIKE '%Vila%Nova%'")
teams = cursor.fetchall()
print("Team Card Stats:", teams)

conn.close()
