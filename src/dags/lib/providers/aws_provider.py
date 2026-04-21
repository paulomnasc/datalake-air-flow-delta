import logging
import os
import tempfile
from typing import Dict, Any, List
from .base_provider import BaseCloudProvider

log = logging.getLogger(__name__)

def map_pyarrow_to_hive_type(pa_type) -> str:
    """Mapeia os tipos sintáticos do PyArrow/Parquet para o equivalente compreendido pelo AWS Athena (Hive)."""
    import pyarrow as pa
    if pa.types.is_string(pa_type) or pa.types.is_large_string(pa_type): return 'string'
    if pa.types.is_int64(pa_type) or pa.types.is_uint64(pa_type): return 'bigint'
    if pa.types.is_int32(pa_type) or pa.types.is_uint32(pa_type): return 'int'
    if pa.types.is_int16(pa_type) or pa.types.is_uint16(pa_type) or pa.types.is_int8(pa_type): return 'int'
    if pa.types.is_float64(pa_type): return 'double'
    if pa.types.is_float32(pa_type): return 'float'
    if pa.types.is_boolean(pa_type): return 'boolean'
    if pa.types.is_timestamp(pa_type): return 'timestamp'
    if pa.types.is_date(pa_type): return 'date'
    if pa.types.is_decimal(pa_type): return f'decimal({pa_type.precision},{pa_type.scale})'
    # Fallback seguro para strings se o tipo for complexo demais
    return 'string'

class AWSCloudProvider(BaseCloudProvider):
    """
    Estratégia para enviar dados locais (MinIO) para o AWS S3.
    Incorpora o 'Data Discovery Automático' utilizando o Boto3 Glide Client 
    para registrar a tabela instantaneamente no AWS Glue Data Catalog,
    viabilizando consultas no Athena com bypass ao custo/delay do Glue Crawler.
    """
    
    def sync_files(self, local_hook: Any, bucket: str, files: List[str], config: Dict[str, Any]) -> Dict[str, Any]:
        import boto3
        import pyarrow.parquet as pq
        
        aws_access_key = config.get('aws_access_key')
        aws_secret_key = config.get('aws_secret_key')
        aws_bucket = config.get('aws_bucket')
        aws_region = config.get('aws_region', 'us-east-1')
        target_table_name = config.get('target_table_name', config.get('targetTableName'))
        
        if not all([aws_access_key, aws_secret_key, aws_bucket, target_table_name]):
            raise ValueError("[CLOUD_SYNC][AWS] Faltam credenciais exclusivas de destino (access_key, secret_key, bucket ou target_table_name).")
            
        # 1. Subida ao S3
        s3_client = boto3.client(
            's3',
            aws_access_key_id=aws_access_key,
            aws_secret_access_key=aws_secret_key,
            region_name=aws_region
        )
        
        synced = 0
        extracted_schema = None  # Guardará metadata de colunas da PyArrow
        s3_data_location = None  # Para injetar no Catalog: 's3://meubucket/gold/tabela/'
        
        with tempfile.TemporaryDirectory() as tmpdir:
            for file_key in files:
                log.info(f"[CLOUD_SYNC][AWS] Sincronizando: {file_key}")
                if file_key.endswith('/'):
                    continue
                    
                local_path = local_hook.download_file(
                    key=file_key,
                    bucket_name=bucket,
                    local_path=tmpdir,
                    preserve_file_name=True
                )
                
                # Se ainda não mapeou o schema, fazemos isso lendo o header do primeiro parquet
                if extracted_schema is None and local_path.endswith('.parquet'):
                    try:
                        parquet_file = pq.ParquetFile(local_path)
                        extracted_schema = parquet_file.schema_arrow
                        # Deduzimos o prefixo original da pasta (Ex: gold/tabela/) para injetar na localização da tabela externa
                        parent_path = os.path.dirname(file_key)
                        s3_data_location = f"s3://{aws_bucket}/{parent_path}/"
                        log.info(f"[CLOUD_SYNC][GLUE] Schema inferido localmente do Parquet '{file_key}'")
                    except Exception as e:
                        log.warning(f"[CLOUD_SYNC][GLUE] Não foi possível extrair schema pyarrow do arquivo {local_path}: {e}")

                # Subida do dado (mantém a mesma árvore de diretórios do Lake local)
                s3_client.upload_file(local_path, aws_bucket, file_key)
                synced += 1
                os.remove(local_path)
        
        log.info(f"[CLOUD_SYNC][AWS] ✅ Sucesso! {synced} arquivos encaminhados para S3 ({aws_bucket}).")
        
        # 2. Bypass AWS Glue Data Catalog
        glue_registered = False
        if extracted_schema and s3_data_location:
            glue_registered = self._register_in_glue_catalog(
                access_key=aws_access_key, 
                secret_key=aws_secret_key, 
                region=aws_region, 
                database_name="medataflow_db", # DB Fixo padronizado
                table_name=target_table_name,
                s3_location=s3_data_location,
                schema=extracted_schema
            )
            
        return {
            'status': 'success', 
            'synced': synced, 
            'target': 'aws', 
            'bucket': aws_bucket,
            'glue_catalogued': glue_registered
        }

    def _register_in_glue_catalog(self, access_key: str, secret_key: str, region: str, database_name: str, table_name: str, s3_location: str, schema: Any) -> bool:
        """Utilizando o schema do PyArrow, avisa a AWS das métricas dos arquivos recém processados"""
        import boto3
        from botocore.exceptions import ClientError
        
        log.info(f"[GLUE_CATALOG] Iniciando governança e check-in. AWS Database: {database_name} | Table: {table_name}")
        glue_client = boto3.client(
            'glue',
            aws_access_key_id=access_key,
            aws_secret_access_key=secret_key,
            region_name=region
        )
        
        # 1. Garantir que Database Exista
        try:
            glue_client.get_database(Name=database_name)
        except ClientError as e:
            if e.response['Error']['Code'] == 'EntityNotFoundException':
                log.info(f"[GLUE_CATALOG] Banco '{database_name}' não existe. Criando...")
                try:
                    glue_client.create_database(DatabaseInput={'Name': database_name, 'Description': "Orquestrado via MyDataFlow automation"})
                except Exception as ce:
                    log.error(f"[GLUE_CATALOG] Erro de permissão IAM ao tentar criar DB: {ce}")
                    return False
            else:
                log.error(f"[GLUE_CATALOG] Falha na verificação de DB: {e}")
                return False

        # 2. Gerar Colunagem (Hive compatible wrapper)
        hive_columns = []
        for field in schema:
            hive_columns.append({
                "Name": field.name.lower().replace(" ", "_"), # Glue é sensível e encoraja low_cases sem espaços
                "Type": map_pyarrow_to_hive_type(field.type)
            })

        # 3. Registrar ou Atualizar Tabela
        table_input = {
            'Name': table_name,
            'Description': 'Tabela Gold/Silver sincronizada automaticamente.',
            'TableType': 'EXTERNAL_TABLE',
            'Parameters': {
                'classification': 'parquet',
                'EXTERNAL': 'TRUE'
            },
            'StorageDescriptor': {
                'Columns': hive_columns,
                'Location': s3_location,
                'InputFormat': 'org.apache.hadoop.hive.ql.io.parquet.MapredParquetInputFormat',
                'OutputFormat': 'org.apache.hadoop.hive.ql.io.parquet.MapredParquetOutputFormat',
                'SerdeInfo': {
                    'SerializationLibrary': 'org.apache.hadoop.hive.ql.io.parquet.serde.ParquetHiveSerDe',
                    'Parameters': {'serialization.format': '1'}
                }
            }
        }
        
        try:
            glue_client.get_table(DatabaseName=database_name, Name=table_name)
            log.info(f"[GLUE_CATALOG] Tabela '{table_name}' já documentada. Efetuando Update (Schema Evolution)...")
            glue_client.update_table(
                DatabaseName=database_name, 
                TableInput=table_input
            )
            return True
        except ClientError as e:
            if e.response['Error']['Code'] == 'EntityNotFoundException':
                log.info(f"[GLUE_CATALOG] Declarando inédita tabela '{table_name}'...")
                try:
                    glue_client.create_table(DatabaseName=database_name, TableInput=table_input)
                    log.info(f"[GLUE_CATALOG] ✅ Data Discovery estabelecido! Athena já consegue consultar 'SELECT * FROM {database_name}.{table_name}'")
                    return True
                except Exception as ce:
                    log.error(f"[GLUE_CATALOG] Falta de permissões credenciais IAM para glue:CreateTable. Abortado schema: {ce}")
                    return False
            else:
                log.error(f"[GLUE_CATALOG] Erro desconhecido Boto3 Glue: {e}")
                return False
