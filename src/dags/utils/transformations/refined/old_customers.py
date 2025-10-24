import pandas as pd
import io
from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
from datetime import datetime

# ====================================================================
# VARIÁVEIS DE CONFIGURAÇÃO
# ====================================================================
BUCKET = 'lab01'
TRUSTED_PREFIX = 'processed/trusted/customers_'
REFINED_PREFIX = 'processed/refined/customers_'

# ====================================================================
# FUNÇÃO DE REFINAMENTO
# ====================================================================
def refinar_customers():
    s3_hook = S3Hook(aws_conn_id='minio_conn')
    s3_client = s3_hook.get_conn()

    # 🔹 1. Listar arquivos trusted e refined
    trusted_objs = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=TRUSTED_PREFIX).get('Contents', [])
    refined_objs = s3_client.list_objects_v2(Bucket=BUCKET, Prefix=REFINED_PREFIX).get('Contents', [])

    trusted_keys = [obj['Key'] for obj in trusted_objs if obj['Key'].endswith('.parquet')]
    refined_keys = [obj['Key'] for obj in refined_objs if obj['Key'].endswith('.parquet')]

    # 🔹 2. Identificar arquivos pendentes
    pendentes = [key for key in trusted_keys if key.replace('trusted', 'refined') not in refined_keys]

    if not pendentes:
        print("✅ Nenhum arquivo pendente para refinamento.")
        return

    for trusted_key in pendentes:
        print(f"🔍 Refinando: {trusted_key}")
        obj = s3_client.get_object(Bucket=BUCKET, Key=trusted_key)
        df = pd.read_parquet(io.BytesIO(obj['Body'].read()))

        # 🔹 3. Preenchimento de dados faltantes
        df['creditLimit'] = df['creditlimit'].fillna(0)
        df['state'] = df['state'].replace('', pd.NA)
        df['state'] = df['state'].fillna('N/A')
        df['salesrepemployeenumber'] = df['salesrepemployeenumber'].fillna(0)

        # 🔹 4. Colunas derivadas
        df['nome_completo'] = df['contactfirstname'].str.strip() + ' ' + df['contactlastname'].str.strip()
        df['valor_cliente'] = df['creditLimit']

        # 🔹 5. Segmentação por faixa de crédito
        df['faixa_credito'] = pd.cut(
            df['valor_cliente'],
            bins=[-1, 50000, 100000, 150000, float('inf')],
            labels=['Baixo', 'Médio', 'Alto', 'Premium']
        )

        # 🔹 6. Enriquecimento com taxa de câmbio simulada
        taxas = {
            'USA': 5.0, 'France': 5.3, 'Germany': 5.4, 'UK': 6.2,
            'Japan': 0.035, 'Canada': 3.8, 'Australia': 3.2, 'Spain': 5.1
        }
        df['taxa_cambio'] = df['country'].str.strip().map(taxas)
        df['credito_brl'] = df['valor_cliente'] * df['taxa_cambio']

        # 🔹 7. Curadoria final
        df_refinada = df[
            ['customernumber', 'nome_completo', 'country', 'state', 'valor_cliente',
             'faixa_credito', 'credito_brl']
        ]

        # 🔹 8. Exportar para zona refined
        buffer = io.BytesIO()
        df_refinada.to_parquet(buffer, index=False)

        refined_key = trusted_key.replace('trusted', 'refined')
        s3_client.put_object(
            Bucket=BUCKET,
            Key=refined_key,
            Body=buffer.getvalue()
        )

        print(f"✅ Refinado e salvo: {refined_key}")

# ====================================================================
# DEFINIÇÃO DA DAG
# ====================================================================
default_args = {
    'start_date': datetime(2023, 1, 1),
    'catchup': False
}

with DAG(
    dag_id='refinar_customers',
    schedule_interval='@daily',
    default_args=default_args,
    description='Refina dados da tabela customers da zona trusted',
) as dag:

    refinar_customers_task = PythonOperator(
        task_id='refinar_customers',
        python_callable=refinar_customers,
        dag=dag
    )