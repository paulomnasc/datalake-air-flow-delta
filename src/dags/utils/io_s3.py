import pandas as pd
import io
from airflow.providers.amazon.aws.hooks.s3 import S3Hook

def ler_parquet_s3(bucket, key, aws_conn_id='minio_conn'):
    s3_hook = S3Hook(aws_conn_id=aws_conn_id)
    s3_client = s3_hook.get_conn()
    obj = s3_client.get_object(Bucket=bucket, Key=key)
    return pd.read_parquet(io.BytesIO(obj['Body'].read()))

def salvar_parquet_s3(df, bucket, key, aws_conn_id='minio_conn'):
    buffer = io.BytesIO()
    df.to_parquet(buffer, index=False)
    s3_hook = S3Hook(aws_conn_id=aws_conn_id)
    s3_client = s3_hook.get_conn()
    s3_client.put_object(Bucket=bucket, Key=key, Body=buffer.getvalue())
