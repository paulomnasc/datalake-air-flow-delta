import pandas as pd
import io
from airflow.providers.amazon.aws.hooks.s3 import S3Hook
import pyarrow as pa
import pyarrow.parquet as pq


def ler_parquet_s3(bucket, key, aws_conn_id='minio_conn'):
    s3_hook = S3Hook(aws_conn_id=aws_conn_id)
    s3_client = s3_hook.get_conn()
    obj = s3_client.get_object(Bucket=bucket, Key=key)
    return pd.read_parquet(io.BytesIO(obj['Body'].read()))

def salvar_parquet_s3(df, bucket, key, aws_conn_id='minio_conn'):
    # Converte o DataFrame Pandas para Arrow Table
    table = pa.Table.from_pandas(df, preserve_index=False)

    # Escreve Parquet em memória com timestamps em microssegundos
    buffer = io.BytesIO()
    pq.write_table(table, buffer, coerce_timestamps="us")

    # Envia para o S3 (MinIO)
    buffer.seek(0)
    s3_hook = S3Hook(aws_conn_id=aws_conn_id)
    s3_client = s3_hook.get_conn()
    s3_client.put_object(Bucket=bucket, Key=key, Body=buffer.getvalue())