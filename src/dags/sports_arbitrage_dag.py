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

log = logging.getLogger(__name__)

# Argumentos Padrão da DAG
default_args = {
    'owner': 'paulomnasc-558',
    'depends_on_past': False,
    'start_date': datetime(2025, 1, 1),
    'email_on_failure': False,
    'email_on_retry': False,
    'retries': 0,
}

def extract_and_calculate_arbitrage(**context):
    """
    Executa a extração das odds nas 3 casas de apostas (Betano, Bet365, Sportingbet),
    aplica o algoritmo de Surebet e gera a string CSV.
    """
    from lib.sports_arbitrage import process_arbitrage_report
    
    params = context.get('params', {})
    banca_total = float(params.get('banca_total', 1000.0))
    
    log.info(f"[ARBITRAGEM] Iniciando extração e cálculo de arbitragem com banca total de R$ {banca_total}")
    
    df = process_arbitrage_report(banca_total=banca_total)
    
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    csv_string = df.to_csv(index=False, encoding='utf-8-sig')
    
    log.info(f"[ARBITRAGEM] Relatório gerado com sucesso!")
    log.info(f"[ARBITRAGEM] Total de jogos analisados: {len(df)}")
    
    surebets = df[df['Eh_Surebet'] == 'SIM'] if not df.empty and 'Eh_Surebet' in df.columns else pd.DataFrame()
    log.info(f"[ARBITRAGEM] Total de Surebets encontradas: {len(surebets)}")
    
    context['ti'].xcom_push(key='csv_content', value=csv_string)
    context['ti'].xcom_push(key='timestamp', value=timestamp)

def upload_csv_to_s3(**context):
    """
    Envia o relatório CSV exclusivamente para o bucket 'paulomnasc-558' na pasta 'arbitrage/'.
    Conexão padrão do projeto: 'minio_conn'.
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
    
    log.info(f"[S3-UPLOAD] Conectando ao S3/MinIO para salvar em: s3://{s3_bucket}/{s3_folder}/")
    
    from airflow.providers.amazon.aws.hooks.s3 import S3Hook
    
    # Tenta utilizar a conexão padronizada do projeto ('minio_conn') ou fallback para 'aws_default' / 'minio_s3_conn'
    hook = None
    for conn_id in ['minio_conn', 'aws_default', 'minio_s3_conn']:
        try:
            h = S3Hook(aws_conn_id=conn_id)
            h.get_conn()
            hook = h
            log.info(f"[S3-UPLOAD] Conexão S3 estabelecida com sucesso usando '{conn_id}'")
            break
        except Exception as err:
            log.debug(f"[S3-UPLOAD] Conexão '{conn_id}' não disponível: {err}")
            
    if not hook:
        hook = S3Hook(aws_conn_id='minio_conn')
        
    # Garante que o bucket paulomnasc-558 exista no S3
    try:
        if not hook.check_for_bucket(s3_bucket):
            log.info(f"[S3-UPLOAD] Criando o bucket '{s3_bucket}'...")
            hook.create_bucket(s3_bucket)
    except Exception as e_bkt:
        log.warning(f"[S3-UPLOAD] Aviso ao verificar/criar bucket '{s3_bucket}': {e_bkt}")
        
    # Realiza o envio dos arquivos
    log.info(f"[S3-UPLOAD] Enviando {s3_key_history}...")
    hook.load_string(
        string_data=csv_content,
        key=s3_key_history,
        bucket_name=s3_bucket,
        replace=True
    )
    
    log.info(f"[S3-UPLOAD] Enviando {s3_key_latest}...")
    hook.load_string(
        string_data=csv_content,
        key=s3_key_latest,
        bucket_name=s3_bucket,
        replace=True
    )
    
    log.info(f"[S3-UPLOAD] 🎉 UPLOAD CONCLUÍDO COM SUCESSO NO BUCKET S3!")
    log.info(f" -> s3://{s3_bucket}/{s3_key_history}")
    log.info(f" -> s3://{s3_bucket}/{s3_key_latest}")

# Definição da DAG
with DAG(
    'sports_arbitrage_dag',
    default_args=default_args,
    description='Scraping e Cálculo de Arbitragem (Surebets) para o Brasileirão Série A/B nas casas Betano, Bet365 e Sportingbet',
    schedule_interval='0 */2 * * *',
    catchup=False,
    max_active_runs=1,
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

    task_extract_calculate >> task_upload_s3
