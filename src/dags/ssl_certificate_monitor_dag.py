"""
DAG de Monitoramento de Certificados SSL
Verifica diariamente a expiração dos certificados HTTPS/SSL e envia alerta via e-mail (Brevo SMTP)
quando a expiração for menor ou igual a 5 dias (ou configurável via env SSL_ALERT_THRESHOLD_DAYS).
"""

from datetime import datetime, timedelta, timezone
import os
import socket
import ssl
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

from airflow import DAG
from airflow.operators.python import PythonOperator

# --------------------------------------------------------------------
# CONFIGURAÇÕES DE MONITORAMENTO E SMTP
# --------------------------------------------------------------------
DEFAULT_RECIPIENT = os.environ.get("SSL_ALERT_RECIPIENT", "admin@estudotabela.com.br")
ALERT_THRESHOLD_DAYS = int(os.environ.get("SSL_ALERT_THRESHOLD_DAYS", "5"))

# Domínios monitorados
DOMAINS_TO_CHECK = [
    "myflow.estudotabela.com.br",
    "vitalvitrine.com.br",
    "cristalbet.com.br",
]

SMTP_CONFIG = {
    "host": os.environ.get("SMTP_HOST", "smtp-relay.brevo.com"),
    "port": int(os.environ.get("SMTP_PORT", 587)),
    "user": os.environ.get("SMTP_USER", ""),
    "password": os.environ.get("SMTP_PASSWORD", ""),
    "from_name": os.environ.get("SMTP_FROM_NAME", "MyDataFlow SSL Monitor"),
}


def _check_domain_ssl(domain: str, port: int = 443, timeout: float = 10.0):
    """
    Conecta via TLS ao domínio e retorna a data de expiração UTC e os dias restantes.
    """
    try:
        ctx = ssl.create_default_context()
        with socket.create_connection((domain, port), timeout=timeout) as sock:
            with ctx.wrap_socket(sock, server_hostname=domain) as ssock:
                cert = ssock.getpeercert()
                not_after_str = cert.get("notAfter")
                if not not_after_str:
                    return None, None, f"Data de expiração não encontrada no certificado para {domain}"
                
                # ssl.cert_time_to_seconds converte a string RFC 2822 para timestamp epoch
                epoch_sec = ssl.cert_time_to_seconds(not_after_str)
                expiry_dt = datetime.fromtimestamp(epoch_sec, tz=timezone.utc)
                now_utc = datetime.now(timezone.utc)
                
                days_left = (expiry_dt - now_utc).days
                return expiry_dt, days_left, None
    except Exception as exc:
        return None, None, str(exc)


def _send_email_alert(recipient: str, alert_domains: list, all_results: list):
    """
    Dispara o e-mail HTML via SMTP Brevo com alertas de expiração de certificado.
    """
    host = SMTP_CONFIG["host"]
    port = SMTP_CONFIG["port"]
    user = SMTP_CONFIG["user"]
    password = SMTP_CONFIG["password"]
    from_name = SMTP_CONFIG["from_name"]

    if not user or not password:
        raise ValueError("SMTP_USER e SMTP_PASSWORD não estão configurados no ambiente.")

    subject = f"⚠️ [ALERTA URGENTE] Certificado SSL Expirando ({len(alert_domains)} domínio(s))"

    # Monta tabela HTML com o status de todos os domínios
    table_rows = ""
    for r in all_results:
        domain = r["domain"]
        days = r["days_left"]
        expiry = r["expiry_str"]
        error = r["error"]

        if error:
            status_badge = '<span style="background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold;">ERRO DE CONEXÃO</span>'
            days_str = "N/A"
        elif days is not None and days <= ALERT_THRESHOLD_DAYS:
            status_badge = f'<span style="background-color: #ffc107; color: #212529; padding: 4px 8px; border-radius: 4px; font-weight: bold;">EXPIRA EM {days} DIA(S)</span>'
            days_str = f"<b>{days} dias</b>"
        else:
            status_badge = f'<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px;">OK ({days} dias)</span>'
            days_str = f"{days} dias"

        table_rows += f"""
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;"><b>{domain}</b></td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">{expiry}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">{days_str}</td>
            <td style="padding: 10px; border-bottom: 1px solid #ddd;">{status_badge}</td>
        </tr>
        """

    html_content = f"""
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
    </head>
    <body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
        <div style="max-width: 650px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h2 style="color: #d9534f; margin-top: 0;">⚠️ Alerta de Vencimento de Certificado SSL</h2>
            <p>Atenção Admin,</p>
            <p>O monitor de segurança detectou certificado(s) SSL com validade inferior a <b>{ALERT_THRESHOLD_DAYS} dias</b> no seu ambiente:</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f1f1f1; text-align: left;">
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Domínio</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Data Expiração (UTC)</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Dias Restantes</th>
                        <th style="padding: 10px; border-bottom: 2px solid #ccc;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    {table_rows}
                </tbody>
            </table>

            <div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin-top: 20px;">
                <h4 style="margin: 0 0 10px 0; color: #007bff;">🛠️ Ação Recomendada para Renovação:</h4>
                <p style="margin: 0 0 5px 0;">Acesse o servidor via SSH e execute o comando de renovação:</p>
                <code style="background: #e9ecef; padding: 6px 10px; display: block; font-family: monospace; border-radius: 4px; margin-top: 5px;">
                    certbot renew --cert-name myflow.estudotabela.com.br --force-renewal && docker exec nginx-gateway nginx -s reload
                </code>
            </div>

            <hr style="margin-top: 30px; border: 0; border-top: 1px solid #eee;">
            <p style="font-size: 12px; color: #888; text-align: center;">E-mail automático enviado pela DAG <b>ssl_certificate_monitor_dag</b> do Apache Airflow.</p>
        </div>
    </body>
    </html>
    """

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{from_name} <{user}>"
    msg["To"] = recipient
    msg.attach(MIMEText(html_content, "html", "utf-8"))

    print(f"Conectando ao servidor SMTP {host}:{port} para enviar alerta a {recipient}...")
    with smtplib.SMTP(host, port, timeout=30) as server:
        server.starttls()
        server.login(user, password)
        server.sendmail(user, [recipient], msg.as_string())
    print(f"E-mail de alerta SSL enviado com sucesso para {recipient}!")


def monitor_ssl_certificates(**context):
    """
    Task principal do Airflow que verifica os certificados e decide se envia o e-mail de alerta.
    """
    print(f"Iniciando verificação de certificados SSL (Limite de alerta: {ALERT_THRESHOLD_DAYS} dias)...")
    print(f"Destinatário do alerta: {DEFAULT_RECIPIENT}")

    all_results = []
    alert_domains = []

    for domain in DOMAINS_TO_CHECK:
        expiry_dt, days_left, error = _check_domain_ssl(domain)

        if error:
            print(f"❌ [ERRO] {domain}: {error}")
            all_results.append({
                "domain": domain,
                "days_left": None,
                "expiry_str": "Erro na verificação",
                "error": error,
            })
            alert_domains.append(domain)
        else:
            expiry_str = expiry_dt.strftime("%Y-%m-%d %H:%M:%S UTC")
            print(f"✔️ {domain}: expira em {expiry_str} ({days_left} dias restantes)")
            all_results.append({
                "domain": domain,
                "days_left": days_left,
                "expiry_str": expiry_str,
                "error": None,
            })

            if days_left <= ALERT_THRESHOLD_DAYS:
                print(f"⚠️ [ALERTA] Domínio {domain} expira em {days_left} dias! (Menor/Igual a {ALERT_THRESHOLD_DAYS} dias)")
                alert_domains.append(domain)

    if alert_domains:
        print(f"\nAlerta acionado! {len(alert_domains)} domínio(s) necessita(m) de atenção: {alert_domains}")
        _send_email_alert(DEFAULT_RECIPIENT, alert_domains, all_results)
    else:
        print(f"\nTodos os certificados SSL estão saudáveis (validade > {ALERT_THRESHOLD_DAYS} dias). Nenhum e-mail enviado.")


# --------------------------------------------------------------------
# DEFINIÇÃO DA DAG AIRFLOW
# --------------------------------------------------------------------
default_args = {
    "owner": "airflow",
    "depends_on_past": False,
    "email_on_failure": False,
    "email_on_retry": False,
    "retries": 1,
    "retry_delay": timedelta(minutes=10),
}

with DAG(
    dag_id="ssl_certificate_monitor_dag",
    default_args=default_args,
    description="Monitora a expiração de certificados SSL/HTTPS e alerta admin@estudotabela.com.br 5 dias antes de vencer",
    schedule_interval="0 8 * * *",  # Executa diariamente às 08:00 AM UTC
    start_date=datetime(2026, 1, 1),
    catchup=False,
    tags=["security", "ssl", "monitoring", "email"],
) as dag:

    task_monitor_ssl = PythonOperator(
        task_id="check_ssl_expiration",
        python_callable=monitor_ssl_certificates,
    )
