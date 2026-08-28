"""
DAG de Monitoramento Unificado de Infraestrutura (Recursos do Sistema, Docker & Certificados SSL)
Verifica a cada 4 horas:
1. Espaço em Disco (Partição /): Alerta se uso > 80%; Executa auto-limpeza do Docker se uso > 88%.
2. Saúde dos Contêineres Docker: Alerta se contêineres críticos (postgres-bi, mysql, football-app, etc.) estiverem caídos ou reiniciando.
3. Validade dos Certificados SSL: Alerta via e-mail se expiração for inferior a 20 dias.

Envio de e-mail automático via Brevo SMTP para admin@estudotabela.com.br.
"""

from datetime import datetime, timedelta, timezone
import os
import shutil
import socket
import ssl
import subprocess
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

from airflow import DAG
from airflow.operators.python import PythonOperator

# --------------------------------------------------------------------
# CONFIGURAÇÕES DE MONITORAMENTO E SMTP
# --------------------------------------------------------------------
DEFAULT_RECIPIENT = os.environ.get("SSL_ALERT_RECIPIENT", "admin@estudotabela.com.br")
ALERT_THRESHOLD_DAYS = int(os.environ.get("SSL_ALERT_THRESHOLD_DAYS", "20"))
DISK_ALERT_PERCENT = int(os.environ.get("DISK_ALERT_PERCENT", "80"))
DISK_AUTO_PRUNE_PERCENT = int(os.environ.get("DISK_AUTO_PRUNE_PERCENT", "88"))

# Contêineres críticos monitorados
CRITICAL_CONTAINERS = [
    "postgres-bi",
    "postgres",
    "mysql",
    "football-app",
    "nginx-gateway",
    "codeigniter-app",
    "fiscalweb",
    "airflow-webserver",
    "airflow-scheduler",
]

# Domínios monitorados para SSL
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
    "from_name": os.environ.get("SMTP_FROM_NAME", "MyDataFlow Monitor"),
}


def _check_disk_usage():
    """
    Verifica o uso de disco da partição raiz (/).
    Retorna total_gb, used_gb, free_gb, percent_used.
    """
    stat = shutil.disk_usage("/")
    total_gb = stat.total / (1024 ** 3)
    used_gb = stat.used / (1024 ** 3)
    free_gb = stat.free / (1024 ** 3)
    percent_used = (stat.used / stat.total) * 100
    return total_gb, used_gb, free_gb, percent_used


def _check_docker_containers():
    """
    Verifica o status dos contêineres Docker usando 'docker ps'.
    Retorna dict com status de cada contêiner crítico e lista de contêineres com problema.
    """
    container_statuses = {}
    failing_containers = []

    try:
        cmd = ["docker", "ps", "-a", "--format", "{{.Names}}|{{.Status}}"]
        res = subprocess.run(cmd, capture_output=True, text=True, check=True)
        lines = res.stdout.strip().splitlines()

        running_dict = {}
        for line in lines:
            if "|" in line:
                name, status = line.split("|", 1)
                running_dict[name] = status

        for container_name in CRITICAL_CONTAINERS:
            matched = False
            for actual_name, status in running_dict.items():
                if container_name in actual_name:
                    matched = True
                    is_healthy = "Up" in status and "Restarting" not in status
                    container_statuses[actual_name] = {
                        "status": status,
                        "healthy": is_healthy
                    }
                    if not is_healthy:
                        failing_containers.append((actual_name, status))
                    break
            
            if not matched:
                container_statuses[container_name] = {
                    "status": "Não Encontrado / Parado",
                    "healthy": False
                }
                failing_containers.append((container_name, "Não Encontrado / Parado"))

    except Exception as exc:
        print(f"Erro ao verificar contêineres Docker: {exc}")
        failing_containers.append(("Docker Service", str(exc)))

    return container_statuses, failing_containers


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
                
                epoch_sec = ssl.cert_time_to_seconds(not_after_str)
                expiry_dt = datetime.fromtimestamp(epoch_sec, tz=timezone.utc)
                now_utc = datetime.now(timezone.utc)
                
                days_left = (expiry_dt - now_utc).days
                return expiry_dt, days_left, None
    except Exception as exc:
        return None, None, str(exc)


def _auto_prune_docker_cache():
    """
    Executa limpeza automática do cache do Docker quando o uso de disco ultrapassa limite de risco.
    """
    print("⚠️ EXECUTANDO AUTO-LIMPEZA DO DOCKER DEVIDO A USO CRÍTICO DE DISCO...")
    try:
        subprocess.run(["docker", "builder", "prune", "-a", "-f"], check=False)
        subprocess.run(["docker", "image", "prune", "-f"], check=False)
        print("✔️ Auto-limpeza do Docker concluída com sucesso.")
    except Exception as exc:
        print(f"Erro na auto-limpeza do Docker: {exc}")


def _send_email_report(recipient: str, subject: str, html_body: str):
    """
    Envia o relatório/alerta por e-mail via Brevo SMTP.
    """
    host = SMTP_CONFIG["host"]
    port = SMTP_CONFIG["port"]
    user = SMTP_CONFIG["user"]
    password = SMTP_CONFIG["password"]
    from_name = SMTP_CONFIG["from_name"]

    if not user or not password:
        print("⚠️ SMTP_USER ou SMTP_PASSWORD não configurados. E-mail não enviado.")
        return

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{from_name} <{user}>"
    msg["To"] = recipient
    msg.attach(MIMEText(html_body, "html", "utf-8"))

    print(f"Conectando ao servidor SMTP {host}:{port} para enviar alerta a {recipient}...")
    with smtplib.SMTP(host, port, timeout=30) as server:
        server.starttls()
        server.login(user, password)
        server.sendmail(user, [recipient], msg.as_string())
    print(f"E-mail de alerta enviado com sucesso para {recipient}!")


def monitor_infrastructure(**context):
    """
    Task principal do Airflow que executa todas as verificações de infraestrutura.
    """
    print("Iniciando Verificação Unificada de Infraestrutura...")

    has_alert = False
    alert_reasons = []

    # 1. Espaço em Disco
    total_gb, used_gb, free_gb, disk_pct = _check_disk_usage()
    print(f"📊 Disco (/): {used_gb:.1f}GB / {total_gb:.1f}GB em uso ({disk_pct:.1f}%) | {free_gb:.1f}GB livre")

    if disk_pct >= DISK_AUTO_PRUNE_PERCENT:
        has_alert = True
        alert_reasons.append(f"CRÍTICO: Disco em {disk_pct:.1f}%! Executando auto-limpeza...")
        _auto_prune_docker_cache()
        # Recalcula espaço pós-limpeza
        total_gb, used_gb, free_gb, disk_pct = _check_disk_usage()
    elif disk_pct >= DISK_ALERT_PERCENT:
        has_alert = True
        alert_reasons.append(f"ALERTA: Disco em {disk_pct:.1f}% ({free_gb:.1f}GB livres)")

    # 2. Contêineres Docker
    container_statuses, failing_containers = _check_docker_containers()
    if failing_containers:
        has_alert = True
        failing_names = ", ".join([name for name, _ in failing_containers])
        alert_reasons.append(f"Contêiner(es) com falha/queda: {failing_names}")

    # 3. Certificados SSL
    ssl_results = []
    failing_ssl = []

    for domain in DOMAINS_TO_CHECK:
        expiry_dt, days_left, error = _check_domain_ssl(domain)
        if error:
            ssl_results.append({
                "domain": domain,
                "days_left": None,
                "expiry_str": "Erro na Conexão",
                "error": error
            })
            failing_ssl.append(domain)
            has_alert = True
            alert_reasons.append(f"SSL erro de conexão no domínio {domain}")
        else:
            expiry_str = expiry_dt.strftime("%Y-%m-%d %H:%M UTC")
            ssl_results.append({
                "domain": domain,
                "days_left": days_left,
                "expiry_str": expiry_str,
                "error": None
            })
            if days_left <= ALERT_THRESHOLD_DAYS:
                failing_ssl.append(domain)
                has_alert = True
                alert_reasons.append(f"SSL no domínio {domain} expira em {days_left} dias")

    # Se houver alerta, monta e envia e-mail
    if has_alert:
        subject = f"⚠️ [ALERTA DE INFRAESTRUTURA] MyDataFlow ({', '.join(alert_reasons[:2])})"

        # Tabela Disco
        disk_badge_color = "#dc3545" if disk_pct >= DISK_ALERT_PERCENT else "#28a745"
        disk_html = f"""
        <h3>💾 Espaço em Disco (Partição /)</h3>
        <p><b>Uso Atual:</b> {disk_pct:.1f}% ({used_gb:.1f} GB de {total_gb:.1f} GB)</p>
        <p><b>Espaço Livre:</b> {free_gb:.1f} GB</p>
        <div style="background-color: #e9ecef; border-radius: 4px; overflow: hidden; height: 20px; width: 100%;">
            <div style="background-color: {disk_badge_color}; height: 100%; width: {min(disk_pct, 100)}%;"></div>
        </div>
        """

        # Tabela Contêineres
        container_rows = ""
        for name, data in container_statuses.items():
            status_text = data["status"]
            healthy = data["healthy"]
            badge = '<span style="background-color: #28a745; color: white; padding: 3px 8px; border-radius: 4px;">ONLINE</span>' if healthy else '<span style="background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-weight: bold;">FALHA / PARADO</span>'
            container_rows += f"""
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;"><b>{name}</b></td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">{status_text}</td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">{badge}</td>
            </tr>
            """

        containers_html = f"""
        <h3>🐳 Status dos Contêineres Docker Críticos</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f1f1f1; text-align: left;">
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Contêiner</th>
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Status</th>
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Estado</th>
                </tr>
            </thead>
            <tbody>
                {container_rows}
            </tbody>
        </table>
        """

        # Tabela SSL
        ssl_rows = ""
        for r in ssl_results:
            domain = r["domain"]
            days = r["days_left"]
            expiry = r["expiry_str"]
            err = r["error"]
            if err:
                badge = '<span style="background-color: #dc3545; color: white; padding: 3px 8px; border-radius: 4px;">ERRO DE CONEXÃO</span>'
            elif days is not None and days <= ALERT_THRESHOLD_DAYS:
                badge = f'<span style="background-color: #ffc107; color: #212529; padding: 3px 8px; border-radius: 4px; font-weight: bold;">EXPIRA EM {days} DIAS</span>'
            else:
                badge = f'<span style="background-color: #28a745; color: white; padding: 3px 8px; border-radius: 4px;">OK ({days} dias)</span>'

            ssl_rows += f"""
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;"><b>{domain}</b></td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">{expiry}</td>
                <td style="padding: 8px; border-bottom: 1px solid #ddd;">{badge}</td>
            </tr>
            """

        ssl_html = f"""
        <h3>🔒 Status dos Certificados SSL</h3>
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <thead>
                <tr style="background-color: #f1f1f1; text-align: left;">
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Domínio</th>
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Expiração (UTC)</th>
                    <th style="padding: 8px; border-bottom: 2px solid #ccc;">Status</th>
                </tr>
            </thead>
            <tbody>
                {ssl_rows}
            </tbody>
        </table>
        """

        html_body = f"""
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"></head>
        <body style="font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; color: #333;">
            <div style="max-width: 700px; margin: 0 auto; background: #ffffff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2 style="color: #d9534f; margin-top: 0;">⚠️ Relatório de Alerta de Infraestrutura</h2>
                <p>Atenção Administrador,</p>
                <p>O monitoramento automático identificou os seguintes pontos de atenção nos recursos do servidor:</p>
                
                {disk_html}
                {containers_html}
                {ssl_html}

                <div style="background-color: #f8f9fa; border-left: 4px solid #007bff; padding: 15px; margin-top: 20px;">
                    <h4 style="margin: 0 0 10px 0; color: #007bff;">🛠️ Ações Recomendadas:</h4>
                    <ul>
                        <li>Se o disco estiver acima de 80%, execute: <code>docker system prune -a -f</code></li>
                        <li>Se houver contêineres caídos, execute: <code>docker compose up -d</code></li>
                        <li>Se o SSL estiver prestes a expirar, execute: <code>certbot renew && docker exec nginx-gateway nginx -s reload</code></li>
                    </ul>
                </div>

                <hr style="margin-top: 30px; border: 0; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #888; text-align: center;">Enviado automaticamente por Airflow DAG <b>ssl_certificate_monitor_dag</b></p>
            </div>
        </body>
        </html>
        """

        _send_email_report(DEFAULT_RECIPIENT, subject, html_body)
    else:
        print("✔️ Todos os recursos (Disco, Docker e SSL) estão 100% saudáveis. Nenhum e-mail enviado.")


# --------------------------------------------------------------------
# DEFINIÇÃO DA DAG AIRFLOW
# --------------------------------------------------------------------
default_args = {
    "owner": "airflow",
    "depends_on_past": False,
    "email_on_failure": False,
    "email_on_retry": False,
    "retries": 1,
    "retry_delay": timedelta(minutes=5),
}

with DAG(
    dag_id="ssl_certificate_monitor_dag",
    default_args=default_args,
    description="Monitora Espaço em Disco, Contêineres Docker e Certificados SSL a cada 4 horas",
    schedule_interval="0 */4 * * *",  # Executa a cada 4 horas
    start_date=datetime(2026, 1, 1),
    catchup=False,
    tags=["security", "ssl", "monitoring", "docker", "disk"],
) as dag:

    task_monitor = PythonOperator(
        task_id="check_infrastructure_health",
        python_callable=monitor_infrastructure,
    )
