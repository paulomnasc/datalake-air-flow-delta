#!/bin/bash
# ==============================================================================
# Script de Backup no Host (Host Fallback Monitor)
# Verifica se o disco atinge limite crítico ou se a webapp está inativa.
# Se detectado problema, envia alerta por e-mail diretamente via SMTP do Brevo.
# ==============================================================================

ENV_FILE="/root/datalake-air-flow-delta/.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -E '^SMTP_' "$ENV_FILE" | xargs)
fi

RECIPIENT="${SSL_ALERT_RECIPIENT:-admin@estudotabela.com.br}"
SMTP_HOST="${SMTP_HOST:-smtp-relay.brevo.com}"
SMTP_PORT="${SMTP_PORT:-587}"
SMTP_USER="${SMTP_USER:-}"
SMTP_PASS="${SMTP_PASSWORD:-}"
DISK_LIMIT=85

# Check Disk Usage
USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
FREE_GB=$(df -h / | awk 'NR==2 {print $4}')

HAS_ALERT=0
ALERT_MSG=""

if [ "$USAGE" -ge "$DISK_LIMIT" ]; then
    HAS_ALERT=1
    ALERT_MSG="⚠️ ALERTA DE ESPAÇO EM DISCO: Partição / atingiu ${USAGE}% de uso (${FREE_GB} livres)!"
    
    # Auto-limpeza preventiva se acima de 90%
    if [ "$USAGE" -ge 90 ]; then
        docker builder prune -a -f > /dev/null 2>&1
        docker image prune -f > /dev/null 2>&1
    fi
fi

# Check HTTP status of FootballWeb
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -m 10 http://localhost:28091/ || echo "000")
if [ "$HTTP_STATUS" -ne 200 ]; then
    HAS_ALERT=1
    ALERT_MSG="${ALERT_MSG}\n⚠️ ALERTA DE SERVIÇO: FootballWeb respondeu HTTP ${HTTP_STATUS} (Esperado 200 OK)!"
fi

if [ "$HAS_ALERT" -eq 1 ] && [ -n "$SMTP_USER" ] && [ -n "$SMTP_PASS" ]; then
    SUBJECT="⚠️ [EMERGÊNCIA INFRA] Alerta de Recursos no Servidor Host"
    
    python3 -c "
import smtplib
from email.mime.text import MIMEText

msg = MIMEText('''${ALERT_MSG}''')
msg['Subject'] = '${SUBJECT}'
msg['From'] = 'MyDataFlow Host Monitor <${SMTP_USER}>'
msg['To'] = '${RECIPIENT}'

try:
    with smtplib.SMTP('${SMTP_HOST}', ${SMTP_PORT}, timeout=20) as server:
        server.starttls()
        server.login('${SMTP_USER}', '${SMTP_PASS}')
        server.sendmail('${SMTP_USER}', ['${RECIPIENT}'], msg.as_string())
    print('Alerta do host enviado com sucesso!')
except Exception as e:
    print('Erro ao enviar e-mail no host:', e)
"
fi
