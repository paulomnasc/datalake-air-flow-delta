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
    Executa a extração das odds nas casas de apostas (Betnacional, Bet365, Betano, Sportingbet, etc.),
    aplica o algoritmo de Surebet e gera a string CSV com formatação decimal '%.2f'.
    """
    from lib.sports_arbitrage import process_arbitrage_report
    
    params = context.get('params', {})
    banca_total = float(params.get('banca_total', 1000.0))
    casas_usuario = params.get('casas_usuario', "Betnacional, Bet365, Betano, Sportingbet")
    apenas_casas_usuario = bool(params.get('apenas_casas_usuario', True))
    
    log.info(f"[ARBITRAGEM] Iniciando extração com banca R$ {banca_total} | Casas: {casas_usuario} | Apenas Casas Usuário: {apenas_casas_usuario}")
    
    df = process_arbitrage_report(
        banca_total=banca_total,
        casas_usuario=casas_usuario,
        apenas_casas_usuario=apenas_casas_usuario
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

# Definição da DAG
with DAG(
    'sports_arbitrage_dag',
    default_args=default_args,
    description='Scraping e Cálculo de Arbitragem (Surebets) para o Brasileirão Série A/B com suporte a casas personalizadas (Betnacional, Bet365, Betano, etc.)',
    schedule_interval='0 */2 * * *',
    catchup=False,
    max_active_runs=1,
    params={
        'banca_total': 1000.0,
        'casas_usuario': "Betnacional, Bet365, Betano, Sportingbet",
        'apenas_casas_usuario': True,
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

    task_extract_calculate >> task_upload_s3
