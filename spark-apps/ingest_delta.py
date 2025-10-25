import sys
import re
import boto3
from pyspark.sql import SparkSession
from delta.tables import DeltaTable
from pyspark.sql.functions import lit
from datetime import datetime

# Permite importar boto3 instalado em /tmp/libs
sys.path.insert(0, "/tmp/libs")

# Nome da tabela como argumento
if len(sys.argv) < 2:
    raise ValueError("Informe o nome da tabela como argumento: customers, products ou orders")

tabela = sys.argv[1]

# Inicializa SparkSession com Delta Lake e suporte a S3
spark = SparkSession.builder \
    .appName(f"Ingest Delta {tabela.capitalize()}") \
    .config("spark.jars", ",".join([
        "/opt/spark/jars/delta-core_2.12-2.4.0.jar",
        "/opt/spark/jars/hadoop-aws-3.3.2.jar",
        "/opt/spark/jars/aws-java-sdk-bundle-1.11.1026.jar"
    ])) \
    .config("spark.sql.extensions", "io.delta.sql.DeltaSparkSessionExtension") \
    .config("spark.sql.catalog.spark_catalog", "org.apache.spark.sql.delta.catalog.DeltaCatalog") \
    .getOrCreate()

# Configura acesso ao MinIO via s3a
hadoop_conf = spark._jsc.hadoopConfiguration()
hadoop_conf.set("fs.s3a.endpoint", "http://minio:9000")
hadoop_conf.set("fs.s3a.access.key", "admin")
hadoop_conf.set("fs.s3a.secret.key", "admin123")
hadoop_conf.set("fs.s3a.path.style.access", "true")
hadoop_conf.set("fs.s3a.impl", "org.apache.hadoop.fs.s3a.S3AFileSystem")

# Busca o arquivo mais recente no bucket
s3 = boto3.client(
    's3',
    endpoint_url='http://minio:9000',
    aws_access_key_id='admin',
    aws_secret_access_key='admin123'
)

prefixo = f'processed/refined/{tabela}_'
regex = rf'processed/refined/{tabela}_\d{{8}}_\d{{6}}\.parquet'

response = s3.list_objects_v2(Bucket='lab01', Prefix=prefixo)
arquivos = [
    obj['Key'] for obj in response.get('Contents', [])
    if re.match(regex, obj['Key'])
]

if not arquivos:
    raise FileNotFoundError(f"Nenhum arquivo Parquet encontrado para {tabela}.")

arquivo_mais_recente = sorted(arquivos)[-1]
input_path = f"s3a://lab01/{arquivo_mais_recente}"

# Extrai data para partição
match = re.search(rf'{tabela}_(\d{{8}})_\d{{6}}\.parquet', arquivo_mais_recente)
data_ref = match.group(1) if match else datetime.today().strftime("%Y%m%d")

# Lê os dados e adiciona partição
df = spark.read.parquet(input_path).limit(1000).repartition(1).withColumn("partition_date", lit(data_ref))

# Caminho Delta
delta_path = f"s3a://lab01/delta/{tabela}"

# Aplica merge ou cria tabela Delta
if DeltaTable.isDeltaTable(spark, delta_path):
    DeltaTable.forPath(spark, delta_path).alias("tgt").merge(
        df.alias("src"),
        "tgt.partition_date = src.partition_date"
    ).whenMatchedUpdateAll().whenNotMatchedInsertAll().execute()
else:
    df.write.format("delta").partitionBy("partition_date").mode("overwrite").save(delta_path)

spark.stop()
