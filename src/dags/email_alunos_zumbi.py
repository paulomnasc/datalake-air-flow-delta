from datetime import datetime, timedelta
import os
import time
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

import pymysql
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.hooks.base import BaseHook


default_args = {
    "depends_on_past": False,
    "email_on_failure": False,
    "email_on_retry": False,
    "retries": 1,
    "retry_delay": timedelta(minutes=5),
}




SMTP_CONFIG = {
    "host": "mail.estudotabela.com.br",
    "port": 587,
    "user": "admin@estudotabela.com.br",
    "password": "kJ#212394",
    "secure": "tls",
    "from_name": "MyDataFlow Lab",
}

EMAIL_SUBJECT = "Sentimos sua falta no MyDataFlow Lab 🚀"
DRY_RUN = os.environ.get("ZUMBI_EMAIL_DRY_RUN", "true").lower() in {"1", "true", "yes", "on"}


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


def buscar_alunos_zumbi(**context):

    query = """
    SELECT
        id,
        nome,
        email
    FROM usuario
    WHERE perfil_comportamental IN ('Zumbi', 'Oportunista (Pulou o S3 p/ ver preço)')
      AND email IS NOT NULL
      AND TRIM(email) <> ''
      AND email_confirmado = 1
    ORDER BY id;
    """

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

    print(f"Total de alunos Zumbi e Oportunistas encontrados: {len(alunos)}")
    context["ti"].xcom_push(key="alunos_zumbi", value=alunos)


def _build_html(nome: str) -> str:
    return f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #f9f9f9; padding: 30px; border-radius: 8px; border-top: 5px solid #0056b3;">
          <h2 style="color: #0056b3; margin-top: 0;">Olá, {nome}!</h2>
          <p>
            Percebemos que você está um pouco distante da plataforma.
            Preparamos conteúdos práticos para você retomar seus estudos com foco total no mercado.
          </p>
          <p>
            Acesse sua conta e continue de onde parou. Estamos com você nessa jornada.
          </p>
          <div style="text-align: center; margin: 35px 0;">
            <a href="https://myflow.estudotabela.com.br:28443/" style="background-color: #0056b3; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;">Ir para o MyDataFlow Lab</a>
          </div>
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


def enviar_emails_para_zumbi(**context):
    smtp_config = SMTP_CONFIG

    alunos = context["ti"].xcom_pull(task_ids="buscar_alunos_zumbi", key="alunos_zumbi") or []

    if not alunos:
        print("Nenhum aluno Zumbi ou Oportunista para notificar.")
        return

    if DRY_RUN:
        print("DRY-RUN habilitado: nenhum e-mail será enviado.")
        for aluno in alunos:
            print(f"[DRY-RUN] {aluno['email']} ({aluno['nome']})")
        return

    # --- BLOCO DE SEGURANÇA (Apenas envio para 176) ---
    # print("DRY-RUN desabilitado: limitando o envio real apenas para o aluno ID 176.")
    # alunos = [a for a in alunos if str(a.get("id")) == "176"]
    # 
    # if not alunos:
    #     print("Aluno ID 176 não encontrado entre os grupos-alvo. Nenhum e-mail será enviado.")
    #     return
    # ----------------------------------------------------

    smtp_host = smtp_config.get("host", "")
    smtp_user = smtp_config.get("user", "")
    smtp_from_name = smtp_config.get("from_name", "Equipe Smart Tables")
    smtp_from = smtp_user or "nao-responda@localhost"

    if not smtp_host:
        raise ValueError("SMTP não configurado no código da DAG.")

    enviados = 0
    falhas = 0

    smtp_client = None
    emails_nesta_sessao = 0
    LIMITE_POR_SESSAO = 20

    for aluno in alunos:
        destinatario = aluno["email"]
        nome = aluno["nome"]

        mensagem = MIMEMultipart("alternative")
        mensagem["Subject"] = EMAIL_SUBJECT
        mensagem["From"] = f"{smtp_from_name} <{smtp_from}>"
        mensagem["To"] = destinatario
        mensagem.attach(MIMEText(_build_html(nome), "html", "utf-8"))

        sucesso_neste_aluno = False
        tentativas = 0
        
        while not sucesso_neste_aluno and tentativas < 3:
            try:
                # Avalia se a conexão precisa ser recriada (nula ou atingiu o limite por sessão do provedor SMTP)
                if smtp_client is None or emails_nesta_sessao >= LIMITE_POR_SESSAO:
                    if smtp_client is not None:
                        try:
                            smtp_client.quit()
                        except:
                            pass
                    print("Processando nova conexão SMTP (Reset de Limite de Mensagens)...")
                    smtp_client = _create_smtp_client(smtp_config)
                    emails_nesta_sessao = 0

                smtp_client.sendmail(smtp_from, [destinatario], mensagem.as_string())
                enviados += 1
                emails_nesta_sessao += 1
                sucesso_neste_aluno = True
                print(f"E-mail enviado para {destinatario}")
                time.sleep(1.5) # Pequena pausa pro provedor SMTP não bloquear por SPAM rate
                
            except Exception as exc:
                tentativas += 1
                erro_str = str(exc).lower()
                print(f"Erro na tentativa {tentativas} para {destinatario}: {exc}")
                # Erros globais do provedor (conta bloqueada por limite de envios do plano)
                if "too many emails" in erro_str:
                    print(f"Limitação global do provedor! A conta de e-mail estourou seu limite por hora/dia: {exc}")
                    raise RuntimeError(f"Provedor SMTP bloqueou a conta por limite de envios: {exc}")
                
                # Erros comuns de "Corte" de conexão do servidor
                if "please run connect" in erro_str or "too many messages" in erro_str or "unexpected eof" in erro_str or "disconnected" in erro_str:
                    try:
                        smtp_client.quit()
                    except:
                        pass
                    smtp_client = None
                    time.sleep(3) # Aguarda antes de reconectar
                else:
                    # Assumimos que a falha é estrutural do endereço do aluno final (ex: email não existe mais). Desiste dele e marca falha.
                    falhas += 1
                    break 
                    
        if not sucesso_neste_aluno and tentativas >= 3:
            falhas += 1
            print(f"Abandonado e-mail {destinatario} após atingir limite de 3 tentativas de conexão com erro.")

    if smtp_client is not None:
        try:
            smtp_client.quit()
        except:
            pass

    print(f"Resumo de envio: enviados={enviados}, falhas={falhas}, total={len(alunos)}")
    if falhas > 0:
        raise RuntimeError(f"{falhas} envio(s) falharam.")


with DAG(
    dag_id="email_alunos_zumbi",
    default_args=default_args,
    description="Envia e-mails para alunos Zumbi e Oportunistas",
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
