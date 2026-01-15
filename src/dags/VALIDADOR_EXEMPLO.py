"""
EXEMPLO PRÁTICO DE VALIDADOR CUSTOMIZADO (PADRÃO CORRETO)

Este arquivo mostra como criar um validador usando um WRAPPER que
chama o pipeline padrão (raw_to_medallion) e aplica validações customizadas.

Uso:
    1. Salve este arquivo no seu repositório como: meu_validador.py
    2. Faça commit & push
    3. Registre em dag_configurations:
       UPDATE dag_configurations 
       SET python_module_path = 'meu_validador.ValidadorVendas'
       WHERE dag_id = 'sua_dag_id';
"""

from lib.medallion_pipeline import raw_to_medallion
import pandas as pd
import logging
from datetime import datetime
from airflow.providers.amazon.aws.hooks.s3 import S3Hook

log = logging.getLogger(__name__)


class ValidadorVendas:
    """
    Validador customizado (WRAPPER) para dados de vendas.
    
    Fluxo:
    1. Executa Bronze → Silver → Gold (pipeline padrão via raw_to_medallion)
    2. Aplica validações customizadas na Silver
    3. Regrava Silver no MinIO com ajustes
    """

    def __call__(self, source_filename: str, target_table_name: str, **context):
        log.info(f"\n{'='*80}")
        log.info(f"[ValidadorVendas] 🚀 Iniciando pipeline para {target_table_name}")
        log.info(f"[ValidadorVendas] Arquivo: {source_filename}")
        log.info(f"{'='*80}\n")

        # 1) Executar pipeline padrão
        log.info("[ValidadorVendas] 📦 Executando pipeline padrão...")
        result = raw_to_medallion(
            source_filename=source_filename,
            target_table_name=target_table_name,
            **context
        )
        log.info("[ValidadorVendas] ✅ Pipeline padrão concluído")

        # 2) Validar Silver
        bucket = context.get('bucket_name', 'lab01')
        hook = S3Hook(aws_conn_id='minio_conn')
        silver_key = result.get('silver')

        if not silver_key:
            log.warning("[ValidadorVendas] ⚠️ Silver key não encontrada, pulando validações")
            return result

        try:
            local = hook.download_file(key=silver_key, bucket_name=bucket, preserve_file_name=True)
            df = pd.read_parquet(local)

            # Validação 1: Valores negativos
            if 'valor' in df.columns:
                neg = df[df['valor'] < 0]
                if len(neg) > 0:
                    log.warning(f"⚠️ Silver: {len(neg)} valores negativos encontrados")
                    df = df[df['valor'] >= 0]

            # Validação 2: Datas futuras
            if 'data_venda' in df.columns:
                df['data_venda'] = pd.to_datetime(df['data_venda'])
                today = datetime.now().date()
                fut = df[df['data_venda'].dt.date > today]
                if len(fut) > 0:
                    log.warning(f"⚠️ Silver: {len(fut)} datas futuras (removendo)")
                    df = df[df['data_venda'].dt.date <= today]

            # Validação 3: Quantidade realista
            if 'quantidade' in df.columns:
                invalid = df[(df['quantidade'] <= 0) | (df['quantidade'] > 10000)]
                if len(invalid) > 0:
                    log.warning(f"⚠️ Silver: {len(invalid)} quantidades fora do intervalo [1, 10000]")
                    df = df[(df['quantidade'] > 0) & (df['quantidade'] <= 10000)]

            # Quality
            quality = (df.notna().sum().sum() / df.size) * 100
            log.info(f"✅ Silver: Quality Score = {quality:.2f}%")
            if 'task_instance' in context:
                ti = context['task_instance']
                ti.xcom_push(key='silver_row_count', value=len(df))
                ti.xcom_push(key='silver_quality_score', value=float(quality))

            # Regravar Silver
            tmpfile = '/tmp/silver_validated.parquet'
            df.to_parquet(tmpfile, index=False)
            hook.load_file(filename=tmpfile, key=silver_key, bucket_name=bucket, replace=True)
            log.info("[ValidadorVendas] ✅ Silver atualizada com validações")

        except Exception as e:
            log.error(f"[ValidadorVendas] ❌ Erro na validação Silver: {e}", exc_info=True)
            raise

        log.info(f"\n{'='*80}")
        log.info("[ValidadorVendas] ✅ Pipeline + validações concluídos")
        log.info(f"{'='*80}\n")
        return result


# Compatibilidade com a interface web: função dummy
def validate(df):
    log.info("[validate] ℹ️ Função dummy (use ValidadorVendas classe)")
    return df


if __name__ == '__main__':
    print("Validador de exemplo (padrão correto). Use via factory_master.")
