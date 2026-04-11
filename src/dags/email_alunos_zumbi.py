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
    "host": os.environ.get("SMTP_HOST", "smtp-relay.brevo.com"),
    "port": int(os.environ.get("SMTP_PORT", 587)),
    "user": os.environ.get("SMTP_USER", "SEU_LOGIN_BREVO"),
    "password": os.environ.get("SMTP_PASSWORD", "SUA_SENHA_SMTP_BREVO"),
    "secure": "tls",
    "from_name": "MyDataFlow Lab",
}

EMAIL_SUBJECT = "DESAFIO: De Analista de BI a Engenheiro: O divisor de águas é o Vídeo 5 🚀"
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
        u.id,
        u.nome,
        u.email
    FROM usuario u
    WHERE u.email IS NOT NULL
      AND (u.perfil_comportamental NOT IN ('Power User') OR u.perfil_comportamental IS NULL)
      AND TRIM(u.email) <> ''
      AND u.email_confirmado = 1
      AND u.pagamento_inicial = 0
      AND id <> 146
    ORDER BY u.id;
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


def _build_html(nome: str = None) -> str:
    saudacao = f"Olá, {nome}!" if nome else "Olá!"
    return f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background-color: #f9f9f9; padding: 30px; border-radius: 8px; border-top: 5px solid #0056b3;">
          <h2 style="color: #0056b3; margin-top: 0;">{saudacao}</h2>
          <p>
            Saber SQL e Power BI é o básico. O que separa os grandes salários da Engenharia de Dados é a capacidade de gerir 
            infraestrutura e automação.
           
            No Vídeo 9 (Gratuito), eu mostro exatamente como configurar pipeline ELT sem nenhuma codificação. 
            
            Vi que você ainda não validou essa etapa no seu Dashboard. 
            
            Valide seu lab hoje !!!

          </p>
          <p>
            Acesse sua conta e continue de onde parou. Estamos com você nessa jornada.
          </p>
          <div style="text-align: center; margin: 35px 0;">
            <a href="https://myflow.estudotabela.com.br:28443/video/9" style="background-color: #0056b3; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 16px;">Ir para o MyDataFlow Lab</a>
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

    # --- DESCOMENTAR PARA TESTES ESSE LOCO DE SEGURANÇA (Apenas envio para 176) ---
    # print("DRY-RUN desabilitado: limitando o envio real apenas para o aluno ID 176.")
    # alunos = [a for a in alunos if str(a.get("id")) == "176"]
     
    # if not alunos:
    #    print("Aluno ID 176 não encontrado entre os grupos-alvo. Nenhum e-mail será enviado.")
    #    return
    #----------------------------------------------------

    smtp_host = smtp_config.get("host", "")
    smtp_user = smtp_config.get("user", "")
    smtp_from_name = smtp_config.get("from_name", "Equipe Smart Tables")
    smtp_from = smtp_user or "nao-responda@localhost"

    if not smtp_host:
        raise ValueError("SMTP não configurado no código da DAG.")

    enviados_lotes = 0
    falhas_lotes = 0

    smtp_client = None

    # Agrupa os e-mails em lotes de 50 para não estourar limite de destinatários por mensagem (limites comuns de provedores)
    TAMANHO_LOTE = 50
    todos_emails = [aluno["email"] for aluno in alunos]
    lotes = [todos_emails[i:i + TAMANHO_LOTE] for i in range(0, len(todos_emails), TAMANHO_LOTE)]

    print(f"Total de alunos: {len(todos_emails)}. Divididos em {len(lotes)} lote(s) de envio (BCC).")

    for idx, lote_emails in enumerate(lotes):
        mensagem = MIMEMultipart("alternative")
        mensagem["Subject"] = EMAIL_SUBJECT
        mensagem["From"] = f"{smtp_from_name} <{smtp_from}>"
        mensagem["To"] = smtp_from  # O To fica como o próprio remetente (não expõe listas)
        # O Bcc recebe a lista separada por vírgula dos e-mails no lote
        mensagem["Bcc"] = ", ".join(lote_emails)
        
        # O nome foi removido do loop para ser genérico e ir igual a todos no BCC
        mensagem.attach(MIMEText(_build_html(), "html", "utf-8"))

        sucesso_neste_lote = False
        tentativas = 0
        
        while not sucesso_neste_lote and tentativas < 3:
            try:
                if smtp_client is None:
                    print(f"Conectando ao SMTP para o lote {idx+1}/{len(lotes)}...")
                    smtp_client = _create_smtp_client(smtp_config)

                # O sendmail requer a concatenação da lista de todos que receberão o pacote de dados (To + Bcc)
                destinatarios_reais = [smtp_from] + lote_emails
                smtp_client.sendmail(smtp_from, destinatarios_reais, mensagem.as_string())
                
                enviados_lotes += 1
                sucesso_neste_lote = True
                print(f"Lote {idx+1}/{len(lotes)} enviado com sucesso! ({len(lote_emails)} destinatários em cópia oculta)")
                time.sleep(2) # Pequena pausa entre lotes caso haja limitador
                
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
    dag_id="email_alunos_zumbi",
    default_args=default_args,
    description="Envia e-mails para alunos Zumbi e Oportunistas",
    schedule_interval="0 9 * * 1-5",
    start_date=datetime(2025, 1, 1),
    catchup=False,
    max_active_runs=1,
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
