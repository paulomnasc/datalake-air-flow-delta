"""
🏃 COMO USAR: Exemplos Práticos em DAGs

Demonstra como usar a nova arquitetura de herança em DAGs reais.
"""

# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 1: DAG SIMPLES COM VALIDADOR CUSTOMIZADO
# ═══════════════════════════════════════════════════════════════════════════════

from airflow import DAG
from airflow.operators.python import PythonOperator
from datetime import datetime, timedelta
import logging

# Importar o validador customizado
from lib.validadores.exemplos_heranca import CustomerValidador

log = logging.getLogger(__name__)

DEFAULT_ARGS = {
    'owner': 'airflow',
    'start_date': datetime(2026, 1, 1),
    'retries': 1,
}

# Criar a DAG
with DAG(
    'customer_pipeline_v2',
    default_args=DEFAULT_ARGS,
    schedule_interval='0 2 * * *',  # Diariamente às 2 AM
    catchup=False,
    tags=['medallion', 'herança', 'v2']
) as dag:
    
    # Task que usa o validador customizado
    def process_customer_data(**context):
        """
        Executa o pipeline Medallion com validações customizadas.
        
        Context vem do Airflow:
        - dag_id
        - task_id
        - execution_date
        - bucket_name (se configurado)
        etc
        """
        
        # Instanciar o validador
        pipeline = CustomerValidador()
        
        # Executar pipeline
        result = pipeline(
            source_filename='raw/dados/Customer.csv',
            target_table_name='Customer',
            **context  # Passa contexto do Airflow
        )
        
        # Resultado contém:
        # {
        #     'bronze': 's3://bucket/bronze/customer_pipeline_v2/Customer/Customer.parquet',
        #     'silver': 's3://bucket/silver/customer_pipeline_v2/Customer/Customer.parquet',
        #     'gold': 's3://bucket/gold/customer_pipeline_v2/Customer/Customer.parquet',
        #     'delta': 's3://bucket/delta/customer_pipeline_v2/Customer/...',
        #     'delta_format': 'delta',
        #     'delta_version': 0
        # }
        
        log.info(f"✅ Pipeline concluído!")
        log.info(f"   Bronze: {result['bronze']}")
        log.info(f"   Silver: {result['silver']}")
        log.info(f"   Gold: {result['gold']}")
        
        return result
    
    # PythonOperator que executa a função
    process_customer = PythonOperator(
        task_id='process_customer_data',
        python_callable=process_customer_data,
        provide_context=True,
    )

# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 2: DAG COM MÚLTIPLAS TABELAS
# ═══════════════════════════════════════════════════════════════════════════════

from airflow.operators.python import PythonOperator
from lib.validadores.exemplos_heranca import (
    CustomerValidador,
    InvoiceAgregador,
    TrackValidadorEAgregador
)

with DAG(
    'multi_table_pipeline_v2',
    default_args=DEFAULT_ARGS,
    schedule_interval='0 2 * * *',
    catchup=False,
    tags=['medallion', 'herança', 'v2', 'multi-table']
) as dag:
    
    # Task 1: Process Customer
    def process_customer(**context):
        pipeline = CustomerValidador()
        return pipeline(
            source_filename='raw/dados/Customer.csv',
            target_table_name='Customer',
            **context
        )
    
    # Task 2: Process Invoice
    def process_invoice(**context):
        pipeline = InvoiceAgregador()
        return pipeline(
            source_filename='raw/dados/Invoice.csv',
            target_table_name='Invoice',
            **context
        )
    
    # Task 3: Process Track
    def process_track(**context):
        pipeline = TrackValidadorEAgregador()
        return pipeline(
            source_filename='raw/dados/Track.csv',
            target_table_name='Track',
            **context
        )
    
    # Criar tasks
    process_customer_task = PythonOperator(
        task_id='process_customer',
        python_callable=process_customer,
        provide_context=True,
    )
    
    process_invoice_task = PythonOperator(
        task_id='process_invoice',
        python_callable=process_invoice,
        provide_context=True,
    )
    
    process_track_task = PythonOperator(
        task_id='process_track',
        python_callable=process_track,
        provide_context=True,
    )
    
    # Definir dependências (podem rodar em paralelo)
    [process_customer_task, process_invoice_task, process_track_task]

# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 3: DAG COM FUNÇÃO wrapper (compatibilidade)
# ═══════════════════════════════════════════════════════════════════════════════

from lib.medallion_pipeline import raw_to_medallion  # Função wrapper

with DAG(
    'legacy_pipeline',
    default_args=DEFAULT_ARGS,
    schedule_interval='0 2 * * *',
    catchup=False,
    tags=['medallion', 'legacy', 'compatibilidade']
) as dag:
    
    def process_data(**context):
        """
        Usa função wrapper raw_to_medallion().
        
        Internamente, usa a nova classe RawToMedallionPipeline.
        Compatibilidade 100% mantida.
        """
        
        result = raw_to_medallion(
            source_filename='raw/dados/Produto.csv',
            target_table_name='Produto',
            **context
        )
        
        return result
    
    task = PythonOperator(
        task_id='process_produto',
        python_callable=process_data,
        provide_context=True,
    )

# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 4: CRIAR UM VALIDADOR CUSTOMIZADO (Seu próprio!)
# ═══════════════════════════════════════════════════════════════════════════════

from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd

class ProdutoValidador(RawToMedallionPipeline):
    """
    Validador customizado para tabela Produto.
    
    Aplicar validações na Silver:
    - Remover duplicatas de SKU
    - Validar preço
    - Normalizar nomes
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """Override para validações de Produto na Silver"""
        
        log.info("[ProdutoValidador] 🛍️ Validando Produto...")
        
        # Baixar Silver
        local_file = self.hook.download_file(
            key=silver_key,
            bucket_name=self.bucket,
            local_path=self.tmpdir,
            preserve_file_name=True
        )
        
        df = pd.read_parquet(local_file)
        
        # ─────────────────────────────────────────────────
        # VALIDAÇÃO 1: SKU deve ser único
        # ─────────────────────────────────────────────────
        if 'sku' in df.columns:
            duplicates = df['sku'].duplicated().sum()
            if duplicates > 0:
                log.warning(f"[ProdutoValidador] ⚠️ {duplicates} SKUs duplicados encontrados")
                df = df.drop_duplicates(subset=['sku'], keep='first')
                log.info(f"[ProdutoValidador] ✅ Duplicatas removidas")
        
        # ─────────────────────────────────────────────────
        # VALIDAÇÃO 2: Preço deve ser > 0
        # ─────────────────────────────────────────────────
        if 'price' in df.columns:
            df['price'] = pd.to_numeric(df['price'], errors='coerce')
            invalid_price = (df['price'] <= 0) | (df['price'].isnull())
            invalid_count = invalid_price.sum()
            if invalid_count > 0:
                log.warning(f"[ProdutoValidador] ⚠️ {invalid_count} preços inválidos")
                df.loc[invalid_price, 'price'] = None
        
        # ─────────────────────────────────────────────────
        # VALIDAÇÃO 3: Nome normalizado
        # ─────────────────────────────────────────────────
        if 'name' in df.columns:
            df['name'] = df['name'].astype(str).str.strip().str.title()
        
        # Re-salvar
        df.to_parquet(local_file, index=False, compression='snappy')
        self.hook.load_file(
            filename=local_file,
            key=silver_key,
            bucket_name=self.bucket,
            replace=True
        )
        
        log.info("[ProdutoValidador] ✅ Produto validado")
        return silver_key

# Usar na DAG
with DAG(
    'produto_pipeline_v2',
    default_args=DEFAULT_ARGS,
    schedule_interval='0 2 * * *',
    catchup=False,
    tags=['medallion', 'herança', 'v2', 'custom']
) as dag:
    
    def process_produto(**context):
        pipeline = ProdutoValidador()
        return pipeline(
            source_filename='raw/dados/Produto.csv',
            target_table_name='Produto',
            **context
        )
    
    task = PythonOperator(
        task_id='process_produto',
        python_callable=process_produto,
        provide_context=True,
    )

# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 5: USAR VIA FACTORY MASTER (MySQL Config)
# ═══════════════════════════════════════════════════════════════════════════════

# ⚠️ Este exemplo mostra como CONFIGURAR via SQL (não é Python)
# O factory_master.py detectará e usará automaticamente!

"""
SQL para configurar uma DAG customizada com validador:

INSERT INTO dag_configurations (
    dag_id,
    schedule_interval,
    owner,
    description,
    source_filename,
    target_table_name,
    python_module_path,
    is_active,
    created_at,
    updated_at
) VALUES (
    'customer_pipeline_from_mysql',
    '0 2 * * *',
    'airflow',
    'Customer pipeline com validações',
    'raw/dados/Customer.csv',
    'Customer',
    'lib.validadores.exemplos_heranca.CustomerValidador',  # ← Classe customizada!
    1,
    NOW(),
    NOW()
);

Agora o factory_master.py:
1. Detecta que é uma classe (não função)
2. Importa 'lib.validadores.exemplos_heranca.CustomerValidador'
3. Instancia: CustomerValidador()
4. Executa: instancia(source_filename=..., target_table_name=..., **context)
5. DAG funciona com validações automáticas!
"""

# ═══════════════════════════════════════════════════════════════════════════════
# DICAS IMPORTANTES
# ═══════════════════════════════════════════════════════════════════════════════

"""
✅ BEST PRACTICES:

1. Sempre herdar de RawToMedallionPipeline se precisar customizar
   
   ✓ class MeuValidador(RawToMedallionPipeline):
   ✗ class MeuValidador:

2. Override apenas silver_layer_transform() OU gold_layer_transform()
   
   ✓ def silver_layer_transform(self, silver_key: str) -> str:
   ✗ def _process_silver(self):  (use hooks, não métodos internos)

3. Sempre usar self.hook, self.bucket, self.tmpdir
   
   ✓ local_file = self.hook.download_file(...)
   ✗ hook = S3Hook(...)  (pode quebrar se contexto mudar)

4. Retornar a chave (pode ser mesma ou diferente)
   
   ✓ return silver_key
   ✗ return resultado  (precisa ser string com caminho S3)

5. Usar logging para debug
   
   ✓ log.info("[MeuValidador] Fazendo algo...")
   ✗ print("...")  (Airflow não captura)

6. Sempre usar try/except para erros
   
   ✓ try:
   ✗ Deixar exceção vazar (Airflow falha silenciosamente)

⚠️ NÃO FAZER:

❌ Chamar raw_to_medallion() ou RawToMedallionPipeline() dentro de hook
   - Causaria pipeline duplicado!

❌ Tentar sobrescrever _setup() ou _process_bronze()
   - Use os hooks: silver_layer_transform() e gold_layer_transform()

❌ Modificar self.results dentro do hook
   - Somente retorne a chave

❌ Usar time.sleep() ou locks
   - Sincronização já é garantida pela classe base

🎁 O QUE VOCÊ GANHA:

✅ Sincronização automática (impossível race conditions)
✅ Sem corrupção de parquet
✅ Código mais simples (20 linhas vs 100+)
✅ Fácil de estender
✅ Compatível com código antigo
"""
