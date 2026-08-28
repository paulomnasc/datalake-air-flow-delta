import pymysql

conn = pymysql.connect(
    host="127.0.0.1", port=23306, user="root", password="YM11rMrT32xH0E6N", database="footballweb", cursorclass=pymysql.cursors.DictCursor
)
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
