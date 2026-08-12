# Implementação de SMTP Externo Profissional (Brevo)

Este documento registra a mudança da nossa arquitetura de disparos de e-mail marketing (realizados via Apache Airflow) de um provedor baseado em hospedagem compartilhada para uma infraestrutura SMTP transacional profissional (via Brevo).

## 1. O Problema
Nas antigas configurações, a DAG do Airflow `email_alunos_zumbi.py` se conectava diretamente ao `mail.estudotabela.com.br`. Como o serviço roda em provedor de e-mail de hospedagem compartilhada, as contas sofrem limitações severas de spam:
- Proteções contra Spam (Limite de 200 mensagens em lote enviando via Bcc).
- Risco de IP sujo e bloqueios temporários no envio.
- Entregabilidade (muitos e-mails de marketing indo para o lixo eletrônico dos destinatários).

## 2. A Solução (Brevo SMTP)
Definimos separar as "Caixas de E-mail padrão" do "Motor de Envio de Campanhas da Plataforma".
Fizemos a migração da lógica do script para apontar para um provedor externo que oferece plano massivo gratuíto sem limitação quantitativa artificial num único lote, o **Brevo** (300/dia grátis) ou AWS SES ($0.10/1000).

Para blindar o repositório contra exposição de senhas (`Hardcoded credentials`), movemos as informações sensíveis e chaves de APIs para as variáveis de ambiente base do Docker.

---

## 3. Alterações Realizadas nos Arquivos

### A. `.env` (Raiz do Projeto)
Foram criadas novas propriedades para abrigar a credencial Master API (chave de SMTP) dos provedores sem commitar no repositório:
```env
#--------------------------------------------------------------------
# SMTP / EMAIL CONFIG (API do Brevo / Sendinblue / AWS SES)
#--------------------------------------------------------------------
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USER=coloque_aqui_seu_email_login_brevo
SMTP_PASSWORD=coloque_aqui_sua_senha_master_brevo
```

### B. `docker-compose.yml`
Injetamos as variáveis provenientes do arquivo `.env` dentro as instâncias baseadas no Airflow:
`airflow-webserver`, `airflow-scheduler` e `airflow-worker`.
```yaml
      - SMTP_HOST=${SMTP_HOST:-smtp-relay.brevo.com}
      - SMTP_PORT=${SMTP_PORT:-587}
      - SMTP_USER=${SMTP_USER:-}
      - SMTP_PASSWORD=${SMTP_PASSWORD:-}
```

### C. `src/dags/email_alunos_zumbi.py`
Substituímos o bloco `SMTP_CONFIG` que continha credenciais engessadas, alterando o motor para importar a configuração segura através do SO (`os.environ.get`), fornecida pelo container docker em execução.
```python
SMTP_CONFIG = {
    "host": os.environ.get("SMTP_HOST", "smtp-relay.brevo.com"),
    "port": int(os.environ.get("SMTP_PORT", 587)),
    "user": os.environ.get("SMTP_USER", "SEU_LOGIN_BREVO"),
    "password": os.environ.get("SMTP_PASSWORD", "SUA_SENHA_SMTP_BREVO"),
    "secure": "tls",
    "from_name": "MyDataFlow Lab",
}
```

## 4. Testando / Como Validar a Mudança
1. Atualizar credenciais no seu `.env`.
2. Executar novamente o build/reload dos containers atrelados via docker (como Nginx template) utilizando comando: `docker-compose up -d`.
3. Disparar a DAG no painel do Airflow, confirmando a saída de mensagens de SUCESSO no console para pacotes superiores à 200 destinações.
