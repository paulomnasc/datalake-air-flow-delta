from datetime import datetime, timedelta
import os
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

import pymysql
from airflow import DAG
from airflow.operators.python import PythonOperator


default_args = {
    "depends_on_past": False,
    "email_on_failure": False,
    "email_on_retry": False,
    "retries": 1,
    "retry_delay": timedelta(minutes=5),
}


DB_CONFIG = {
    "hostname": "mysql",
    "username": "root",
    "password": "root",
    "database": "lista_revisao2",
    "port": 3306,
}

SMTP_CONFIG = {
    "host": "mail.estudotabela.com.br",
    "port": 587,
    "user": "admin@estudotabela.com.br",
    "password": "kJ#212394",
    "secure": "tls",
    "from_name": "Smart_Tables_TEST",
}

EMAIL_SUBJECT = "Sentimos sua falta no MyDataFlow Lab 🚀"
DRY_RUN = os.environ.get("ZUMBI_EMAIL_DRY_RUN", "true").lower() in {"1", "true", "yes", "on"}


def _get_db_connection(db_config: dict):
    return pymysql.connect(
        host=db_config.get("hostname", "127.0.0.1"),
        user=db_config.get("username", "root"),
        password=db_config.get("password", ""),
        database=db_config.get("database", ""),
        port=int(db_config.get("port", 3306)),
        cursorclass=pymysql.cursors.DictCursor,
        charset="utf8mb4",
    )


def buscar_alunos_zumbi(**context):
    db_config = DB_CONFIG

    query = """
    SELECT
        id,
        nome,
        email
    FROM usuario
    WHERE perfil_comportamental = 'Zumbi'
      AND email IS NOT NULL
      AND TRIM(email) <> ''
      AND email_confirmado = 1
    ORDER BY id;
    """

    print(
        "Conectando ao banco: "
        f"{db_config.get('hostname')}:{db_config.get('port')}/{db_config.get('database')}"
    )
    connection = _get_db_connection(db_config)
    cursor = connection.cursor()
    cursor.execute("SET NAMES utf8mb4;")
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    connection.close()

    alunos_lidos = [
        {
            "id": row["id"],
            "nome": (row.get("nome") or "Aluno").strip(),
            "email": row["email"].strip(),
        }
        for row in rows
        if row.get("email")
    ]

    alunos = []
    emails_ja_incluidos = set()
    for aluno in alunos_lidos:
        email = aluno["email"].lower()
        if email in emails_ja_incluidos:
            continue
        emails_ja_incluidos.add(email)
        alunos.append(aluno)

    print(f"Total de alunos Zumbi encontrados: {len(alunos)}")
    context["ti"].xcom_push(key="alunos_zumbi", value=alunos)


def _build_html(nome: str) -> str:
    return f"""
    <html>
      <body>
        <p>Olá, {nome}!</p>
        <p>
          Percebemos que você está um pouco distante da plataforma.
          Preparamos conteúdos práticos para você retomar seus estudos com foco total no mercado.
        </p>
        <p>
          Acesse sua conta e continue de onde parou. Estamos com você nessa jornada.
        </p>
        <p>Abraços,<br/>Equipe MyDataFlow Lab</p>
      </body>
    </html>
    """


def _create_smtp_client(smtp_config: dict):
    smtp_host = smtp_config.get("host", "")
    smtp_port = int(smtp_config.get("port", 587))
    smtp_secure = (smtp_config.get("secure", "tls") or "tls").lower()
    smtp_user = smtp_config.get("user", "")
    smtp_password = smtp_config.get("password", "")

    if smtp_secure == "ssl":
        smtp_client = smtplib.SMTP_SSL(smtp_host, smtp_port, timeout=30)
    else:
        smtp_client = smtplib.SMTP(smtp_host, smtp_port, timeout=30)
        if smtp_secure == "tls":
            smtp_client.starttls()

    if smtp_user:
        smtp_client.login(smtp_user, smtp_password)

    return smtp_client


def enviar_emails_para_zumbi(**context):
    smtp_config = SMTP_CONFIG

    alunos = context["ti"].xcom_pull(task_ids="buscar_alunos_zumbi", key="alunos_zumbi") or []

    if not alunos:
        print("Nenhum aluno Zumbi para notificar.")
        return

    if DRY_RUN:
        print("DRY-RUN habilitado: nenhum e-mail será enviado.")
        for aluno in alunos:
            print(f"[DRY-RUN] {aluno['email']} ({aluno['nome']})")
        return

    smtp_host = smtp_config.get("host", "")
    smtp_user = smtp_config.get("user", "")
    smtp_from_name = smtp_config.get("from_name", "Equipe Smart Tables")
    smtp_from = smtp_user or "nao-responda@localhost"

    if not smtp_host:
        raise ValueError("SMTP não configurado no código da DAG.")

    enviados = 0
    falhas = 0

    with _create_smtp_client(smtp_config) as smtp_client:
        for aluno in alunos:
            destinatario = aluno["email"]
            nome = aluno["nome"]

            mensagem = MIMEMultipart("alternative")
            mensagem["Subject"] = EMAIL_SUBJECT
            mensagem["From"] = f"{smtp_from_name} <{smtp_from}>"
            mensagem["To"] = destinatario
            mensagem.attach(MIMEText(_build_html(nome), "html", "utf-8"))

            try:
                smtp_client.sendmail(smtp_from, [destinatario], mensagem.as_string())
                enviados += 1
                print(f"E-mail enviado para {destinatario}")
            except Exception as exc:
                falhas += 1
                print(f"Falha ao enviar para {destinatario}: {exc}")

    print(f"Resumo de envio: enviados={enviados}, falhas={falhas}, total={len(alunos)}")
    if falhas > 0:
        raise RuntimeError(f"{falhas} envio(s) falharam.")


with DAG(
    dag_id="email_alunos_zumbi",
    default_args=default_args,
    description="Envia e-mails para alunos com perfil comportamental Zumbi",
    schedule_interval="0 9 * * 1-5",
    start_date=datetime(2025, 1, 1),
    catchup=False,
    tags=["engajamento", "email", "alunos"],
) as dag:
    task_buscar_alunos_zumbi = PythonOperator(
        task_id="buscar_alunos_zumbi",
        python_callable=buscar_alunos_zumbi,
    )

    task_enviar_emails = PythonOperator(
        task_id="enviar_emails_para_zumbi",
        python_callable=enviar_emails_para_zumbi,
    )

    _ = task_buscar_alunos_zumbi >> task_enviar_emails
