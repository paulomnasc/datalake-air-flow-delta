import pymysql

conn = pymysql.connect(
    host="127.0.0.1",
    user="root",
    password="YM11rMrT32xH0E6N",
    database="lista_revisao2",
    port=23306,
    cursorclass=pymysql.cursors.DictCursor
)
cursor = conn.cursor()
cursor.execute("""
    SELECT u.id, u.nome, u.email
    FROM usuario u
    WHERE u.email IS NOT NULL
      AND (u.perfil_comportamental NOT IN ('Power User') OR u.perfil_comportamental IS NULL)
      AND TRIM(u.email) <> ''
      AND u.email_confirmado = 1
      AND u.pagamento_inicial = 0
      AND id <> 146
    ORDER BY u.id;
""")
rows = cursor.fetchall()
found = [r for r in rows if r['id'] == 176]
print(f"Total returned: {len(rows)}")
print(f"ID 176 present: {bool(found)}")
if found:
    print(f"Row: {found[0]}")
