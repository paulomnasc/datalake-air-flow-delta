import sys
import re
import boto3
from pyspark.sql import SparkSession
from delta.tables import DeltaTable
from pyspark.sql.functions import lit

# Permite importar boto3 instalado em /tmp/libs
sys.path.insert(0, "/tmp/libs")

# Inicializa SparkSession com Delta Lake e suporte a S3
spark = SparkSession.builder \
    .appName("Ingest Delta Clientes") \
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

response = s3.list_objects_v2(Bucket='lab01', Prefix='processed/refined/')
arquivos = [
    obj['Key'] for obj in response.get('Contents', [])
    if re.match(r'processed/refined/customers_\d{8}_\d{6}\.parquet', obj['Key'])
]

if not arquivos:
    raise FileNotFoundError("Nenhum arquivo Parquet encontrado em processed/refined/.")

arquivo_mais_recente = sorted(arquivos)[-1]
input_path = f"s3a://lab01/{arquivo_mais_recente}"

# Extrai data para partição
match = re.search(r'customers_(\d{8})_\d{6}\.parquet', arquivo_mais_recente)
data_ref = match.group(1) if match else "00000000"

# Lê os dados e adiciona partição
df = spark.read.parquet(input_path).withColumn("partition_date", lit(data_ref))

# Caminho Delta
delta_path = "s3a://lab01/delta/clientes"

# Aplica merge ou cria tabela Delta
if DeltaTable.isDeltaTable(spark, delta_path):
    DeltaTable.forPath(spark, delta_path).alias("tgt").merge(
        df.alias("src"),
        "tgt.customernumber = src.customernumber AND tgt.partition_date = src.partition_date"
    ).whenMatchedUpdateAll().whenNotMatchedInsertAll().execute()
else:
    df.write.format("delta").partitionBy("partition_date").mode("overwrite").save(delta_path)

spark.stop()
