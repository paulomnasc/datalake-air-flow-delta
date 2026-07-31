"""
DAG Airflow para extração de Odds (Betano, Bet365, Sportingbet),
cálculo de arbitragem (Surebet) para o Brasileirão Série A e Série B,
e upload obrigatório do relatório CSV no bucket S3 paulomnasc-558 na pasta /arbitrage/.
"""

from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import os
import logging
import pandas as pd

import io
import smtplib
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText

import pendulum

log = logging.getLogger(__name__)

local_tz = pendulum.timezone("America/Sao_Paulo")

# Argumentos Padrão da DAG
default_args = {
    'owner': 'paulomnasc-558',
    'depends_on_past': False,
    'start_date': datetime(2025, 1, 1, tzinfo=local_tz),
    'email_on_failure': False,
    'email_on_retry': False,
    'retries': 0,
}

def get_live_env_vars():
    """Lê diretamente o arquivo .env para refletir alterações instantaneamente sem reiniciar os containers."""
    env_vars = {}
    search_paths = [
        '/opt/airflow/.env',
        '/opt/airflow/dags/../../.env',
        '/opt/airflow/dags/../.env',
        '/root/datalake-air-flow-delta/.env',
        './.env',
        '../.env'
    ]
    for p in search_paths:
        if os.path.exists(p):
            try:
                with open(p, 'r', encoding='utf-8') as f:
                    for line in f:
                        line = line.strip()
                        if line and not line.startswith('#') and '=' in line:
                            k, v = line.split('=', 1)
                            env_vars[k.strip()] = v.strip().strip('"').strip("'")
            except Exception:
                pass
    return env_vars

def extract_and_calculate_arbitrage(**context):
    """
    Executa a extração das odds nas casas de apostas (Betnacional, Bet365, Betano, Sportingbet, etc.),
    aplica o algoritmo de Surebet e gera a string CSV com formatação decimal '%.2f'.
    """
    from lib.sports_arbitrage import process_arbitrage_report
    
    params = context.get('params', {})
    file_env = get_live_env_vars()
    
    # 1. Recupera valor padrão das variáveis de ambiente (do .env ou container)
    env_banca = file_env.get('ARBITRAGE_BANCA_TOTAL') or os.environ.get('ARBITRAGE_BANCA_TOTAL', '1000.0')
    env_casas = file_env.get('ARBITRAGE_CASAS_USUARIO') or os.environ.get('ARBITRAGE_CASAS_USUARIO', "Betnacional, Bet365, Betano, Sportingbet, Superbet, KTO, Novibet, EstrelaBet, Betfair, Betfair Sportsbook, Betfair Exchange, Pinnacle, Betsson")
    env_apenas = file_env.get('ARBITRAGE_APENAS_CASAS_USUARIO') or os.environ.get('ARBITRAGE_APENAS_CASAS_USUARIO', 'true')
    
    try:
        default_banca_val = float(env_banca)
    except ValueError:
        default_banca_val = 1000.0
        
    banca_param = params.get('banca_total')
    if banca_param is not None and float(banca_param) != 1000.0:
        banca_total = float(banca_param)
    else:
        banca_total = default_banca_val

    env_min_pre_match = file_env.get('ARBITRAGE_MIN_PRE_MATCH_MINUTES') or os.environ.get('ARBITRAGE_MIN_PRE_MATCH_MINUTES', '30')
    try:
        default_min_pre_match = int(env_min_pre_match)
    except ValueError:
        default_min_pre_match = 30

    min_pre_match_param = params.get('min_pre_match_minutes')
    if min_pre_match_param is not None:
        try:
            min_pre_match_minutes = int(min_pre_match_param)
        except ValueError:
            min_pre_match_minutes = default_min_pre_match
    else:
        min_pre_match_minutes = default_min_pre_match

    # Processa casas_usuario
    casas_param = params.get('casas_usuario')
    if casas_param:
        if isinstance(casas_param, str):
            casas_usuario = [c.strip() for c in casas_param.split(',') if c.strip()]
        elif isinstance(casas_param, list):
            casas_usuario = casas_param
        else:
            casas_usuario = [c.strip() for c in env_casas.split(',') if c.strip()]
    else:
        casas_usuario = [c.strip() for c in env_casas.split(',') if c.strip()]

    # Garante a remoção da 1xBet
    casas_usuario = [c for c in casas_usuario if c.lower() != '1xbet']

    # Processa apenas_casas_usuario
    apenas_param = params.get('apenas_casas_usuario')
    if apenas_param is not None:
        if isinstance(apenas_param, bool):
            apenas_casas_usuario = apenas_param
        else:
            apenas_casas_usuario = str(apenas_param).lower() in ('true', '1', 't', 'y', 'yes')
    else:
        apenas_casas_usuario = str(env_apenas).lower() in ('true', '1', 't', 'y', 'yes')
    
    log.info(f"[ARBITRAGEM] Iniciando extração com banca R$ {banca_total} | Casas: {casas_usuario} | Apenas Casas Usuário: {apenas_casas_usuario} | Mín. Pré-Jogo: {min_pre_match_minutes} min")
    
    df = process_arbitrage_report(
        banca_total=banca_total,
        casas_usuario=casas_usuario,
        apenas_casas_usuario=apenas_casas_usuario,
        min_pre_match_minutes=min_pre_match_minutes
    )
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    # float_format='%.2f' garante 2 casas decimais (ex: 4.40, 1.98, 3.75) no CSV final
    csv_string = df.to_csv(index=False, float_format='%.2f', encoding='utf-8-sig')
    
    log.info(f"[ARBITRAGEM] Relatório gerado com sucesso com 2 casas decimais nas odds!")
    log.info(f"[ARBITRAGEM] Total de jogos analisados: {len(df)}")
    
    surebets = df[df['Eh_Surebet'] == 'SIM'] if not df.empty and 'Eh_Surebet' in df.columns else pd.DataFrame()
    log.info(f"[ARBITRAGEM] Total de Surebets encontradas: {len(surebets)}")
    
    context['ti'].xcom_push(key='csv_content', value=csv_string)
    context['ti'].xcom_push(key='timestamp', value=timestamp)

def upload_csv_to_s3(**context):
    """
    Envia o relatório CSV exclusivamente para o bucket 'paulomnasc-558' na pasta 'arbitrage/'.
    """
    ti = context['ti']
    csv_content = ti.xcom_pull(task_ids='extract_and_calculate_arbitrage_task', key='csv_content')
    timestamp = ti.xcom_pull(task_ids='extract_and_calculate_arbitrage_task', key='timestamp') or datetime.now().strftime("%Y%m%d_%H%M%S")
    
    if not csv_content:
        raise ValueError("[S3-UPLOAD] Nenhum conteúdo CSV foi gerado pela tarefa anterior.")
        
    s3_bucket = "paulomnasc-558"
    s3_folder = "arbitrage"
    s3_key_history = f"{s3_folder}/brasileirao_arbitrage_{timestamp}.csv"
    s3_key_latest = f"{s3_folder}/brasileirao_arbitrage_latest.csv"
    
    log.info(f"[S3-UPLOAD] Conectando ao MinIO/S3 para salvar em: s3://{s3_bucket}/{s3_folder}/")
    
    import boto3
    
    # 1. Tenta conexões de infraestrutura via S3Hook
    uploaded = False
    for conn_id in ['minio_conn', 'aws_default', 'minio_s3_conn']:
        try:
            from airflow.providers.amazon.aws.hooks.s3 import S3Hook
            h = S3Hook(aws_conn_id=conn_id)
            if not h.check_for_bucket(s3_bucket):
                h.create_bucket(s3_bucket)
            h.load_string(string_data=csv_content, key=s3_key_history, bucket_name=s3_bucket, replace=True)
            h.load_string(string_data=csv_content, key=s3_key_latest, bucket_name=s3_bucket, replace=True)
            log.info(f"[S3-UPLOAD] Upload via S3Hook '{conn_id}' realizado com sucesso!")
            uploaded = True
            break
        except Exception as err:
            log.debug(f"[S3-UPLOAD] Tentativa via '{conn_id}' não concluída: {err}")
            
    # 2. Conexão direta via boto3 com as credenciais administrativas do MinIO (admin / admin123)
    if not uploaded:
        log.info("[S3-UPLOAD] Executando upload direto via boto3 no MinIO (admin/admin123)...")
        endpoints = ['http://minio:9000', 'http://localhost:29002', 'http://127.0.0.1:29002']
        
        for ep in endpoints:
            try:
                s3_client = boto3.client('s3', endpoint_url=ep, aws_access_key_id='admin', aws_secret_access_key='admin123')
                buckets = [b['Name'] for b in s3_client.list_buckets().get('Buckets', [])]
                if s3_bucket not in buckets:
                    s3_client.create_bucket(Bucket=s3_bucket)
                    
                csv_bytes = csv_content.encode('utf-8-sig')
                s3_client.put_object(Bucket=s3_bucket, Key=s3_key_history, Body=csv_bytes)
                s3_client.put_object(Bucket=s3_bucket, Key=s3_key_latest, Body=csv_bytes)
                log.info(f"[S3-UPLOAD] 🎉 UPLOAD CONCLUÍDO COM SUCESSO NO BUCKET S3 VIA {ep}!")
                uploaded = True
                break
            except Exception as e_boto:
                log.warning(f"[S3-UPLOAD] Tentativa boto3 em {ep} falhou: {e_boto}")
                
    if uploaded:
        log.info(f" -> s3://{s3_bucket}/{s3_key_history}")
        log.info(f" -> s3://{s3_bucket}/{s3_key_latest}")
    else:
        raise RuntimeError("[S3-UPLOAD] Não foi possível fazer upload para o S3/MinIO em nenhum dos endpoints.")

def send_arbitrage_email(**context):
    """
    Envia e-mail de notificação a cada execução informando se há Surebet no CSV.
    Se sim, inclui a tabela com os detalhes da oportunidade de arbitragem.
    """
    ti = context['ti']
    csv_content = ti.xcom_pull(task_ids='extract_and_calculate_arbitrage_task', key='csv_content')
    
    if not csv_content:
        log.warning("[EMAIL-ARBITRAGEM] Nenhum CSV encontrado para processamento de e-mail.")
        return

    try:
        df = pd.read_csv(io.StringIO(csv_content))
    except Exception as e:
        log.error(f"[EMAIL-ARBITRAGEM] Erro ao ler CSV: {e}")
        return

    surebets_df = df[df['Eh_Surebet'] == 'SIM'] if not df.empty and 'Eh_Surebet' in df.columns else pd.DataFrame()
    tem_surebet = not surebets_df.empty
    total_jogos = len(df) if not df.empty else 0
    total_surebets = len(surebets_df)
    
    agora_str = datetime.now().strftime("%d/%m/%Y %H:%M:%S")

    # Configuração de Destinatários e Assunto
    to_email = "admin@estudotabela.com.br"
    cc_email = "paulomnasc@gmail.com"
    recipients = [to_email, cc_email]

    if tem_surebet:
        subject = f"🚨 [SUREBET ALERTA] {total_surebets} Oportunidade(s) Encontrada(s)! - {agora_str}"
        status_banner_bg = "#d4edda"
        status_banner_color = "#155724"
        status_banner_border = "#c3e6cb"
        status_text = f"<strong>SUREBET ENCONTRADA!</strong> {total_surebets} oportunidade(s) com lucro garantido sem risco."
    else:
        subject = f"ℹ️ [MyDataFlow Sports] Varredura de Arbitragem Concluída - {agora_str}"
        status_banner_bg = "#e2e3e5"
        status_banner_color = "#383d41"
        status_banner_border = "#d6d8db"
        status_text = "<strong>Varredura Concluída:</strong> Nenhum Surebet foi identificado nesta execução."

    # Construção das linhas da tabela se houver Surebet
    table_html = ""
    if tem_surebet:
        rows_html = ""
        for _, row in surebets_df.iterrows():
            camp = row.get('Campeonato', '-')
            data_j = row.get('Data_Jogo', '-')
            tc = row.get('Time_Casa', '-')
            tv = row.get('Time_Visitante', '-')
            
            c1 = row.get('Casa_Odd_1', '-')
            o1 = row.get('Odd_1', '-')
            s1 = row.get('Stake_Odd_1_R$', '-')

            cx = row.get('Casa_Odd_X', '-')
            ox = row.get('Odd_X', '-')
            sx = row.get('Stake_Odd_X_R$', '-')

            c2 = row.get('Casa_Odd_2', '-')
            o2 = row.get('Odd_2', '-')
            s2 = row.get('Stake_Odd_2_R$', '-')

            lucro_pct = row.get('Lucro_Percentual_%', '-')
            lucro_rs = row.get('Lucro_Estimado_R$', '-')
            banca = row.get('Banca_Total_R$', '-')

            rows_html += f"""
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 10px; font-size: 13px; font-weight: bold; color: #0056b3;">{camp}<br><span style="color: #666; font-weight: normal;">{data_j}</span></td>
                <td style="padding: 10px; font-size: 13px; font-weight: bold;">{tc} <span style="color: #888;">vs</span> {tv}</td>
                <td style="padding: 10px; font-size: 12px; background-color: #f8f9fa;">
                    <strong>1 ({tc}):</strong> {c1} @ <strong>{o1}</strong> (Apostar: R$ {s1})<br>
                    <strong>X (Empate):</strong> {cx} @ <strong>{ox}</strong> (Apostar: R$ {sx})<br>
                    <strong>2 ({tv}):</strong> {c2} @ <strong>{o2}</strong> (Apostar: R$ {s2})
                </td>
                <td style="padding: 10px; font-size: 13px; color: #28a745; font-weight: bold; text-align: center;">+{lucro_pct}%</td>
                <td style="padding: 10px; font-size: 13px; color: #28a745; font-weight: bold; text-align: center;">R$ {lucro_rs}</td>
                <td style="padding: 10px; font-size: 12px; text-align: center; color: #555;">R$ {banca}</td>
            </tr>
            """

        table_html = f"""
        <div style="margin-top: 20px; overflow-x: auto;">
            <h3 style="color: #155724; margin-bottom: 10px;">📋 Oportunidades Detalhadas</h3>
            <table style="width: 100%; border-collapse: collapse; background-color: #ffffff; border: 1px solid #dee2e6; font-family: Arial, sans-serif;">
                <thead>
                    <tr style="background-color: #0056b3; color: #ffffff; text-align: left; font-size: 13px;">
                        <th style="padding: 10px;">Campeonato / Data</th>
                        <th style="padding: 10px;">Partida</th>
                        <th style="padding: 10px;">Casas, Odds & Stakes (R$)</th>
                        <th style="padding: 10px; text-align: center;">Lucro %</th>
                        <th style="padding: 10px; text-align: center;">Lucro Est.</th>
                        <th style="padding: 10px; text-align: center;">Banca Total</th>
                    </tr>
                </thead>
                <tbody>
                    {rows_html}
                </tbody>
            </table>
        </div>
        """

    html_content = f"""
    <html>
      <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 800px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://myflow.estudotabela.com.br:28443/assets/img/carcara-logo.png" alt="MyDataFlow Logo" style="max-height: 70px; width: auto;">
            <h2 style="color: #0056b3; margin: 10px 0 0 0;">MyDataFlow - Sports Arbitrage</h2>
        </div>
        
        <div style="background-color: {status_banner_bg}; color: {status_banner_color}; border: 1px solid {status_banner_border}; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 15px;">
            {status_text}
        </div>

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #0056b3; font-size: 13px; margin-bottom: 20px;">
            <strong>Data da Execução:</strong> {agora_str}<br>
            <strong>Filtro de Segurança:</strong> Apenas Pré-Jogo (mín. 30 min antes da partida)<br>
            <strong>Total de Partidas Analisadas:</strong> {total_jogos}<br>
            <strong>Surebets Encontradas:</strong> {total_surebets}
        </div>

        {table_html}

        <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #eeeeee; font-size: 12px; color: #777777; text-align: center;">
            Este e-mail foi gerado automaticamente pelo pipeline do Airflow (<code>sports_arbitrage_dag</code>).<br>
            <strong>MyDataFlow Platform</strong> &bull; <a href="https://myflow.estudotabela.com.br:28443" style="color: #0056b3; text-decoration: none;">Acessar Painel</a>
        </div>
      </body>
    </html>
    """

    # Obter credenciais SMTP do .env / ambiente
    env = get_live_env_vars()
    smtp_host = env.get("SMTP_HOST") or os.environ.get("SMTP_HOST", "smtp-relay.brevo.com")
    smtp_port = int(env.get("SMTP_PORT") or os.environ.get("SMTP_PORT", 587))
    smtp_user = env.get("SMTP_USER") or os.environ.get("SMTP_USER", "")
    smtp_pass = env.get("SMTP_PASSWORD") or os.environ.get("SMTP_PASSWORD", "")
    smtp_from = env.get("SMTP_FROM_EMAIL") or os.environ.get("SMTP_FROM_EMAIL", "admin@estudotabela.com.br")
    smtp_from_name = env.get("SMTP_FROM_NAME") or os.environ.get("SMTP_FROM_NAME", "MyDataFlow Arbitrage")

    log.info(f"[EMAIL-ARBITRAGEM] Preparando envio de e-mail SMTP via {smtp_host}:{smtp_port} para {to_email} (CC: {cc_email})...")

    msg = MIMEMultipart("alternative")
    msg["Subject"] = subject
    msg["From"] = f"{smtp_from_name} <{smtp_from}>"
    msg["To"] = to_email
    msg["Cc"] = cc_email

    msg.attach(MIMEText(html_content, "html", "utf-8"))

    try:
        smtp_client = smtplib.SMTP(smtp_host, smtp_port, timeout=30)
        smtp_client.starttls()
        if smtp_user and smtp_pass:
            smtp_client.login(smtp_user, smtp_pass)

        smtp_client.sendmail(smtp_from, recipients, msg.as_string())
        smtp_client.quit()
        log.info(f"[EMAIL-ARBITRAGEM] E-mail enviado com sucesso para {recipients}!")
    except Exception as err:
        log.error(f"[EMAIL-ARBITRAGEM] Falha ao enviar e-mail via SMTP: {err}")
        raise

# Definição da DAG
_live_env = get_live_env_vars()
_raw_casas = _live_env.get('ARBITRAGE_CASAS_USUARIO') or os.environ.get('ARBITRAGE_CASAS_USUARIO', "Betnacional, Bet365, Betano, Sportingbet, Superbet, KTO, Novibet, EstrelaBet, Betfair, Betfair Sportsbook, Betfair Exchange, Pinnacle, Betsson")
_default_casas_filtered = ", ".join([c.strip() for c in _raw_casas.split(',') if c.strip() and c.strip().lower() != '1xbet'])
_schedule_interval = _live_env.get('ARBITRAGE_SCHEDULE_INTERVAL') or os.environ.get('ARBITRAGE_SCHEDULE_INTERVAL', '0 15,18,21 * * *')

with DAG(
    'sports_arbitrage_dag',
    default_args=default_args,
    description='Scraping e Cálculo de Arbitragem (Surebets) para o Brasileirão Série A/B com suporte a casas personalizadas (Betnacional, Bet365, Betano, etc.)',
    schedule_interval=_schedule_interval,
    catchup=False,
    max_active_runs=1,
    params={
        'banca_total': float(_live_env.get('ARBITRAGE_BANCA_TOTAL') or os.environ.get('ARBITRAGE_BANCA_TOTAL', 1000.0)),
        'casas_usuario': _default_casas_filtered,
        'apenas_casas_usuario': True,
        'min_pre_match_minutes': int(_live_env.get('ARBITRAGE_MIN_PRE_MATCH_MINUTES') or os.environ.get('ARBITRAGE_MIN_PRE_MATCH_MINUTES', 30)),
    },
    tags=['sports', 'arbitrage', 'surebet', 'brasileirao', 's3', 'paulomnasc-558']
) as dag:

    task_extract_calculate = PythonOperator(
        task_id='extract_and_calculate_arbitrage_task',
        python_callable=extract_and_calculate_arbitrage,
        provide_context=True,
    )

    task_upload_s3 = PythonOperator(
        task_id='upload_csv_to_s3_task',
        python_callable=upload_csv_to_s3,
        provide_context=True,
    )

    task_send_email = PythonOperator(
        task_id='send_arbitrage_email_task',
        python_callable=send_arbitrage_email,
        provide_context=True,
    )

    task_extract_calculate >> task_upload_s3 >> task_send_email

