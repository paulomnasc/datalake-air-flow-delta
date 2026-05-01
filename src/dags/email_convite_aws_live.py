from datetime import datetime, timedelta
import os
import time
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

import pendulum
import pymysql
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.hooks.base import BaseHook

local_tz = pendulum.timezone("America/Sao_Paulo")

default_args = {
    "owner": "Paulo Nascimento",
    "start_date": datetime(2026, 4, 1, tzinfo=local_tz),
    "depends_on_past": False,
    "email_on_failure": False,
    "email_on_retry": False,
    "retries": 1,
    "retry_delay": timedelta(minutes=5),
}

SMTP_CONFIG = {
    "host": os.environ.get("SMTP_HOST", "smtp-relay.brevo.com"),
    "port": int(os.environ.get("SMTP_PORT", 587)),
    "user": os.environ.get("SMTP_USER", "SEU_LOGIN_BREVO"),
    "password": os.environ.get("SMTP_PASSWORD", "SUA_SENHA_SMTP_BREVO"),
    "secure": "tls",
    "from_name": "MyDataFlow Lab",
}

EMAIL_SUBJECT = "Sessão ao vivo na AWS"
DRY_RUN = os.environ.get("AWS_LIVE_EMAIL_DRY_RUN", "true").lower() in {"1", "true", "yes", "on"}


def _get_db_connection(conn_id: str):
    conn = BaseHook.get_connection(conn_id)
    return pymysql.connect(
        host=conn.host,
        user=conn.login,
        password=conn.password,
        database=conn.schema,
        port=conn.port or 3306,
        cursorclass=pymysql.cursors.DictCursor,
        charset="utf8mb4",
    )


def buscar_alunos_aws_live(**context):
    query = """
    SELECT
        u.id,
        u.nome,
        u.email
    FROM usuario u
    WHERE u.email IS NOT NULL
      AND u.perfil_comportamental IN ('Interessados')
      AND TRIM(u.email) <> ''
      AND u.pagamento_inicial = 0
    """

    # query = """
    # SELECT
    #     u.id,
    #     u.nome,
    #     u.email,
    #     MAX(al.created_at) as ultima_atividade
    # FROM usuario u
    # JOIN activity_logs al ON u.id = al.user_id
    # WHERE u.email IS NOT NULL
    #  AND (u.perfil_comportamental NOT IN ('Power User') OR u.perfil_comportamental IS NULL)
    #   AND TRIM(u.email) <> ''
    #   AND u.pagamento_inicial = 0
    #   AND u.id NOT IN (146, 238, 422)
    #   AND u.criado_em < DATE_SUB(NOW(), INTERVAL 14 DAY)
    #   AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    # GROUP BY u.id, u.nome, u.email
    # ORDER BY ultima_atividade DESC;
    # """

    print("Conectando ao banco via connection ID: mydataflow-conn")
    connection = _get_db_connection("mydataflow-conn")
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

    print(f"Total de alunos encontrados (ativos nos últimos 7 dias): {len(alunos)}")
    context["ti"].xcom_push(key="alunos_aws_live", value=alunos)


def _build_html(nome: str = None) -> str:
    saudacao = f"Olá, {nome}!" if nome else "Olá!"
    return f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://myflow.estudotabela.com.br:28443/assets/img/carcara-logo.png" alt="MyDataFlow Logo" style="max-height: 80px; width: auto;">
        </div>
        <div style="background-color: #f9f9f9; padding: 30px; border-radius: 8px; border-top: 5px solid #0056b3;">
          <h2 style="color: #0056b3; margin-top: 0;">{saudacao}</h2>
          <p>
            Estou abrindo uma sessão ao vivo na próxima semana para destravar o seu setup na AWS. Não é palestra, é mão na massa. Vou abrir a tela e vamos resolver o erro que está travando o seu lab. Quem estiver lá, eu ajudo pessoalmente.
          </p>
          <p>
            Responda este email com um <strong>SIM</strong> caso deseje participar e quais dias a partir às 20:00 hs você teria disponibilidade.
          </p>
          <p style="margin-bottom: 0;">Abraços,<br/><strong>Equipe MyDataFlow Lab</strong></p>
        </div>
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


def enviar_emails_aws_live(**context):
    smtp_config = SMTP_CONFIG

    alunos = context["ti"].xcom_pull(task_ids="buscar_alunos_aws_live", key="alunos_aws_live") or []

    if not alunos:
        print("Nenhum aluno encontrado para notificar.")
        return

    if DRY_RUN:
        print("DRY-RUN habilitado: nenhum e-mail será enviado.")
        for aluno in alunos:
            print(f"[DRY-RUN] {aluno['email']} ({aluno['nome']})")
        return

    # --- DESCOMENTAR PARA TESTES ESSE LOCO DE SEGURANÇA (Apenas envio para 176) ---
    # print("DRY-RUN desabilitado: limitando o envio real apenas para o aluno ID 176.")
    # alunos = [a for a in alunos if str(a.get("id")) == "176"]
    
    #if not alunos:
    #    print("Aluno ID 176 não encontrado entre os grupos-alvo. Nenhum e-mail será enviado.")
    #    return
    #----------------------------------------------------


    smtp_host = smtp_config.get("host", "")
    smtp_user = smtp_config.get("user", "")
    smtp_from_name = smtp_config.get("from_name", "Equipe Smart Tables")
    smtp_from = os.environ.get("SMTP_FROM_EMAIL", "admin@estudotabela.com.br")

    if not smtp_host:
        raise ValueError("SMTP não configurado no código da DAG.")

    enviados_lotes = 0
    falhas_lotes = 0

    smtp_client = None

    TAMANHO_LOTE = 50
    todos_emails = [aluno["email"] for aluno in alunos]
    lotes = [todos_emails[i:i + TAMANHO_LOTE] for i in range(0, len(todos_emails), TAMANHO_LOTE)]

    print(f"Total de alunos: {len(todos_emails)}. Divididos em {len(lotes)} lote(s) de envio (BCC).")

    for idx, lote_emails in enumerate(lotes):
        mensagem = MIMEMultipart("alternative")
        mensagem["Subject"] = EMAIL_SUBJECT
        mensagem["From"] = f"{smtp_from_name} <{smtp_from}>"
        mensagem["To"] = smtp_from 
        mensagem["Bcc"] = ", ".join(lote_emails)
        
        mensagem.attach(MIMEText(_build_html(), "html", "utf-8"))

        sucesso_neste_lote = False
        tentativas = 0
        
        while not sucesso_neste_lote and tentativas < 3:
            try:
                if smtp_client is None:
                    print(f"Conectando ao SMTP para o lote {idx+1}/{len(lotes)}...")
                    smtp_client = _create_smtp_client(smtp_config)

                destinatarios_reais = [smtp_from] + lote_emails
                smtp_client.sendmail(smtp_from, destinatarios_reais, mensagem.as_string())
                
                enviados_lotes += 1
                sucesso_neste_lote = True
                print(f"Lote {idx+1}/{len(lotes)} enviado com sucesso! ({len(lote_emails)} destinatários em cópia oculta)")
                time.sleep(2) 
                
            except Exception as exc:
                tentativas += 1
                erro_str = str(exc).lower()
                print(f"Erro na tentativa {tentativas} para lote {idx+1}: {exc}")
                
                if "too many emails" in erro_str:
                    print(f"Limitação global do provedor! A conta de e-mail estourou seu limite: {exc}")
                    raise RuntimeError(f"Provedor SMTP bloqueou a conta por limite de envios: {exc}")
                
                if any(x in erro_str for x in ["please run connect", "too many messages", "unexpected eof", "disconnected", "connection", "closed"]):
                    try:
                        smtp_client.quit()
                    except:
                        pass
                    smtp_client = None
                    time.sleep(3)
                else:
                    falhas_lotes += 1
                    break 
                    
        if not sucesso_neste_lote and tentativas >= 3:
            falhas_lotes += 1
            print(f"Abandonado lote {idx+1} após atingir limite de 3 tentativas de conexão com erro.")

    if smtp_client is not None:
        try:
            smtp_client.quit()
        except:
            pass

    print(f"Resumo de envio (Lotes): enviados={enviados_lotes}, falhas={falhas_lotes}, total_lotes={len(lotes)}")
    if falhas_lotes > 0:
        raise RuntimeError(f"{falhas_lotes} lote(s) falharam no envio.")


with DAG(
    dag_id="email_convite_aws_live",
    default_args=default_args,
    description="Convite para sessão ao vivo na AWS para alunos ativos",
    schedule_interval=None,
    catchup=False,
    max_active_runs=1,
    tags=["engajamento", "email", "aws", "live"],
) as dag:
    task_buscar_alunos = PythonOperator(
        task_id="buscar_alunos_aws_live",
        python_callable=buscar_alunos_aws_live,
    )

    task_enviar_emails = PythonOperator(
        task_id="enviar_emails_aws_live",
        python_callable=enviar_emails_aws_live,
    )

    _ = task_buscar_alunos >> task_enviar_emails
