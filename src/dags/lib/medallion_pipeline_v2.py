"""
🏗️ PIPELINE MEDALLION COM ARQUITETURA DE HERANÇA
================================================

Template Method Pattern para sincronização 100% garantida.

Estrutura:
- RawToMedallionPipeline: Classe base com fluxo completo sincronizado
- Métodos hookáveis: silver_layer_transform(), gold_layer_transform()
- Subclasses customizadas herdam e override apenas o necessário

SINCRONIZAÇÃO GARANTIDA:
- Classe base gerencia todo o sequenciamento
- Subclasses não podem causarrace conditions
- Cada etapa espera a anterior terminar (garantido estruturalmente)

COMPATIBILIDADE:
- Função raw_to_medallion() ainda funciona (wrapper)
- Código antigo continua operacional
- Gradualmente migrar para uso de classes
"""

import logging
import os
import tempfile
import json
from typing import Dict, Optional, Tuple
import pandas as pd

log = logging.getLogger(__name__)


class RawToMedallionPipeline:
    """
    ✅ NOVA: Classe base com Template Method Pattern
    
    Fluxo sincronizado:
    1. __call__() → orquestra todo o fluxo
    2. _setup() → inicializa (tmpdir, hooks, atlas)
    3. _process_bronze() → cria camada Bronze
    4. _process_silver() → cria camada Silver + CHAMA HOOK CUSTOMIZADO
    5. _process_gold() → cria camada Gold + CHAMA HOOK CUSTOMIZADO
    6. _process_delta() → cria camada Delta
    7. _cleanup() → limpa recursos
    
    Cada etapa espera a anterior → SINCRONIZAÇÃO GARANTIDA
    
    USAR: Subclassifique e override silver_layer_transform() ou gold_layer_transform()
    """
    
    def __init__(self):
        """Inicializa estado interno do pipeline"""
        self.tmpdir = None
        self.hook = None
        self.atlas = None
        self.results = {}
        self.context = {}
        self.bucket = 'lab01'
        self.dag_id = 'default'
        self.target_table_name = None
        self.source_filename = None
        
    def __call__(self, source_filename: str, target_table_name: str, **kwargs) -> Dict:
        """
        Entry point: Executa TODA a pipeline sincronizada.
        
        Sequência garantida:
        1. Setup
        2. Bronze
        3. Silver (+ transformações customizadas)
        4. Gold (+ transformações customizadas)
        5. Delta
        6. Cleanup
        
        Cada etapa termina antes da próxima começar.
        """
        try:
            self.target_table_name = target_table_name
            self.source_filename = source_filename
            self.context = kwargs
            
            log.info(f"\n{'='*80}")
            log.info(f"[PIPELINE] 🚀 Iniciando {self.__class__.__name__}")
            log.info(f"[PIPELINE] Tabela: {target_table_name}")
            log.info(f"[PIPELINE] Arquivo: {source_filename}")
            log.info(f"{'='*80}\n")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 1: SETUP (tmpdir, hooks, atlas)
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [1/6] 🔧 Setup...")
            self._setup(**kwargs)
            log.info("[PIPELINE] ✅ Setup completo")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 2: CAMADA BRONZE
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [2/6] 📦 Bronze...")
            self._process_bronze()
            log.info("[PIPELINE] ✅ Bronze completo")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 3: CAMADA SILVER + TRANSFORMAÇÕES CUSTOMIZADAS
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [3/6] 🥈 Silver...")
            self._process_silver()
            log.info("[PIPELINE] ✅ Silver completo")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 4: CAMADA GOLD + TRANSFORMAÇÕES CUSTOMIZADAS
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [4/6] 🥇 Gold...")
            self._process_gold()
            log.info("[PIPELINE] ✅ Gold completo")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 5: CAMADA DELTA
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [5/6] Δ Delta...")
            self._process_delta()
            log.info("[PIPELINE] ✅ Delta completo")
            
            # ══════════════════════════════════════════════════════
            # ETAPA 6: CLEANUP
            # ══════════════════════════════════════════════════════
            log.info("[PIPELINE] [6/6] 🧹 Limpeza...")
            self._cleanup()
            log.info("[PIPELINE] ✅ Cleanup completo")
            
            log.info(f"\n{'='*80}")
            log.info(f"[PIPELINE] ✅ {self.__class__.__name__} FINALIZADO COM SUCESSO!")
            log.info(f"{'='*80}\n")
            
            return self.results
            
        except Exception as e:
            log.error(f"[PIPELINE] ❌ ERRO: {e}", exc_info=True)
            self._cleanup()
            raise
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 1: SETUP
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _setup(self, **kwargs):
        """Inicializa tmpdir, S3 hook, Atlas, etc"""
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        
        # Cria diretório temporário único para armazenar arquivos intermediários do pipeline (downloads, conversões, etc.)
        self.tmpdir = tempfile.mkdtemp()
        self.hook = S3Hook(aws_conn_id='minio_conn')
        self.bucket = kwargs.get('bucket_name') or os.environ.get("MINIO_BUCKET", "lab01")
        self.dag_id = kwargs.get('dag_id') or 'default'
        
        # ✅ CRITICAL: Atualizar context com valores validados para garantir propagação
        self.context['dag_id'] = self.dag_id
        self.context['bucket_name'] = self.bucket
        
        log.info(f"[SETUP] Tmpdir: {self.tmpdir}")
        log.info(f"[SETUP] Bucket: {self.bucket}")
        log.info(f"[SETUP] DAG ID: {self.dag_id}")
        
        # Inicializar Atlas se habilitado
        if os.getenv("ENABLE_ATLAS", "false").lower() == "true":
            try:
                from .atlas_client import AtlasClient
                self.atlas = AtlasClient()
                self.atlas.wait_until_ready(timeout_seconds=90)
                db_name = os.getenv("ATLAS_HIVE_DB", "medallion")
                self.atlas.ensure_hive_db(db_name)
                log.info("[SETUP] ✅ Atlas inicializado")
            except Exception as e:
                log.warning(f"[SETUP] ⚠️ Atlas falhou: {e}")
                self.atlas = None
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 2: CAMADA BRONZE
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _process_bronze(self):
        """Cria camada Bronze (conversão para Parquet)"""
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        
        src_key = self.source_filename.lstrip('/')
        basename = os.path.basename(src_key)
        basename_no_ext = os.path.splitext(basename)[0]
        
        # Bronze: estrutura bronze/{target_table_name}/{timestamp_hash}.parquet
        bronze_key = f"bronze/{self.target_table_name}/{basename_no_ext}.parquet"
        
        log.info(f"[BRONZE] Baixando: s3://{self.bucket}/{src_key}")
        
        # Download
        local_file = self.hook.download_file(
            key=src_key, 
            bucket_name=self.bucket, 
            local_path=self.tmpdir, 
            preserve_file_name=True
        )
        
        # Ler arquivo
        file_ext = os.path.splitext(local_file)[1].lower()
        df_bronze = self._read_file(local_file, file_ext)
        
        # Salvar como Parquet
        local_parquet = os.path.join(self.tmpdir, f"{basename_no_ext}_bronze.parquet")
        df_bronze.to_parquet(
            local_parquet,  # Caminho absoluto onde o arquivo Parquet será salvo localmente
            index=False,  # Não inclui o índice do DataFrame no arquivo (economiza espaço)
            compression='snappy',  # Algoritmo de compressão (Snappy = bom balanço entre velocidade e taxa de compressão)
            engine='pyarrow'  # Engine PyArrow para escrita (mais rápido que Fastparquet, suporta mais tipos)
        )
        
        # Upload
        self.hook.load_file(
            filename=local_parquet, 
            key=bronze_key, 
            bucket_name=self.bucket, 
            replace=True
        )
        
        self.results['bronze'] = bronze_key
        log.info(f"[BRONZE] ✅ Salvo: s3://{self.bucket}/{bronze_key}")
    
    def _read_file(self, local_file: str, file_ext: str) -> pd.DataFrame:
        """Lê arquivo (CSV, JSON, Parquet) para DataFrame"""
        if file_ext == '.csv':
            return pd.read_csv(local_file)
        elif file_ext == '.json':
            return self._read_json_robust(local_file)
        elif file_ext == '.parquet':
            return pd.read_parquet(local_file)
        else:
            log.warning(f"[READ] Extensão desconhecida '{file_ext}', tentando CSV...")
            return pd.read_csv(local_file)
    
    def _read_json_robust(self, path: str) -> pd.DataFrame:
        """Lê JSON robusto (NDJSON, lista, objeto)"""
        try:
            df = pd.read_json(path, lines=True)
            if not df.empty:
                return df
        except Exception:
            pass
        
        try:
            df = pd.read_json(path)
            if not df.empty:
                if len(df.columns) == 1 and df.dtypes.iloc[0] == 'object':
                    col = df.columns[0]
                    try:
                        normalized = pd.json_normalize(
                            df[col].apply(lambda x: json.loads(x) if isinstance(x, str) else x)
                        )
                        if not normalized.empty:
                            return normalized
                    except Exception:
                        pass
                return df
        except Exception:
            pass
        
        with open(path, 'r') as f:
            payload = json.load(f)
        
        if isinstance(payload, list):
            return pd.json_normalize(payload)
        if isinstance(payload, dict):
            return pd.json_normalize(payload)
        
        raise ValueError("Formato JSON não suportado")
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 3: CAMADA SILVER + HOOK CUSTOMIZADO
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _process_silver(self):
        """
        Cria camada Silver e CHAMA HOOK CUSTOMIZADO.
        
        Fluxo:
        1. Chamar bronze_to_silver() padrão
        2. Chamar silver_layer_transform() para customizações
        3. Re-salvar resultado
        """
        from lib.silver_layer import bronze_to_silver
        
        bronze_key = self.results.get('bronze')
        
        log.info(f"[SILVER] Processando: {bronze_key}")
        log.info(f"[SILVER] DAG ID no contexto: {self.context.get('dag_id')}")
        
        # Chamar Silver padrão da lib
        silver_result = bronze_to_silver(
            bronze_key, 
            self.target_table_name, 
            dag_id=self.dag_id,
            **self.context
        )
        
        # bronze_to_silver retorna {"layer": "silver", "keys": [...]} (plural)
        silver_keys = silver_result.get('keys', [])
        if not silver_keys:
            raise ValueError("Silver retornou nenhuma chave")
        
        # Pegar primeira chave (normalmente há apenas uma)
        silver_key = silver_keys[0] if isinstance(silver_keys, list) else silver_keys
        
        log.info(f"[SILVER] ✅ Silver padrão: {silver_key}")
        
        # ════════════════════════════════════════════════════
        # HOOK: Transformações customizadas na Silver
        # ════════════════════════════════════════════════════
        # Subclasses podem override este método
        
        silver_key = self.silver_layer_transform(silver_key)
        
        self.results['silver'] = silver_key
        log.info(f"[SILVER] ✅ Salvo com transformações: {silver_key}")
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """
        🔧 HOOK: Override este método para customizações na Silver
        
        Subclasses herdam este método e podem aplicar transformações.
        
        IMPORTANTE: 
        - Sincronização garantida (roda DEPOIS de _process_silver padrão)
        - Não há race conditions
        - Você tem acesso a self.hook, self.bucket, self.tmpdir
        
        Args:
            silver_key: Chave S3 do arquivo Silver gerado
        
        Returns:
            silver_key: Chave S3 do arquivo Silver final (pode ser modificada)
        
        Exemplo de override:
        
        class MeuValidador(RawToMedallionPipeline):
            def silver_layer_transform(self, silver_key: str) -> str:
                # Baixar Silver
                local_file = self.hook.download_file(
                    key=silver_key,
                    bucket_name=self.bucket,
                    local_path=self.tmpdir,
                    preserve_file_name=True
                )
                
                # Ler e transformar
                df = pd.read_parquet(local_file)
                df['column'] = self._custom_logic(df['column'])
                
                # Re-salvar
                df.to_parquet(local_file, index=False, compression='snappy')
                
                # Upload (replace=True, SEGURO pois Silver já está finalizada)
                self.hook.load_file(
                    filename=local_file,
                    key=silver_key,
                    bucket_name=self.bucket,
                    replace=True
                )
                
                return silver_key
        """
        # Implementação padrão: não faz nada (apenas retorna)
        log.info("[SILVER-TRANSFORM] ℹ️ Nenhuma transformação customizada")
        return silver_key
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 4: CAMADA GOLD + HOOK CUSTOMIZADO
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _process_gold(self):
        """
        Cria camada Gold e CHAMA HOOK CUSTOMIZADO.
        
        Fluxo:
        1. Chamar silver_to_gold() padrão
        2. Chamar gold_layer_transform() para customizações
        3. Re-salvar resultado
        """
        from lib.gold_layer import silver_to_gold
        
        silver_key = self.results.get('silver')
        
        log.info(f"[GOLD] Processando: {silver_key}")
        log.info(f"[GOLD] DAG ID no contexto: {self.context.get('dag_id')}")
        
        # Chamar Gold padrão da lib
        gold_result = silver_to_gold(
            source_filename=silver_key,
            target_table_name=self.target_table_name,
            dag_id=self.dag_id,
            **self.context
        )
        
        # silver_to_gold retorna {"layer": "gold", "keys": [...]} (plural)
        gold_keys = gold_result.get('keys', [])
        if not gold_keys:
            raise ValueError("Gold retornou nenhuma chave")
        
        # Pegar primeira chave (normalmente há apenas uma)
        gold_key = gold_keys[0] if isinstance(gold_keys, list) else gold_keys
        
        log.info(f"[GOLD] ✅ Gold padrão: {gold_key}")
        
        # ════════════════════════════════════════════════════
        # HOOK: Transformações customizadas na Gold
        # ════════════════════════════════════════════════════
        # Subclasses podem override este método
        
        gold_key = self.gold_layer_transform(gold_key)
        
        self.results['gold'] = gold_key
        self.results['gold_format'] = 'parquet'
        log.info(f"[GOLD] ✅ Salvo com transformações: {gold_key}")
    
    def gold_layer_transform(self, gold_key: str) -> str:
        """
        🔧 HOOK: Override este método para customizações na Gold
        
        Subclasses herdam este método e podem aplicar transformações.
        
        IMPORTANTE:
        - Sincronização garantida (roda DEPOIS de _process_gold padrão)
        - Não há race conditions
        - SEGURO sobrescrever arquivo (Silver já finalizada, Gold não afeta Delta)
        
        Args:
            gold_key: Chave S3 do arquivo Gold gerado
        
        Returns:
            gold_key: Chave S3 do arquivo Gold final (pode ser modificada)
        
        Exemplo de override:
        
        class MeuValidador(RawToMedallionPipeline):
            def gold_layer_transform(self, gold_key: str) -> str:
                # Baixar Gold
                local_file = self.hook.download_file(
                    key=gold_key,
                    bucket_name=self.bucket,
                    local_path=self.tmpdir,
                    preserve_file_name=True
                )
                
                # Aplicar agregações customizadas
                df = pd.read_parquet(local_file)
                df = self._apply_business_logic(df)
                
                # Re-salvar
                df.to_parquet(local_file, index=False, compression='snappy')
                
                # Upload
                self.hook.load_file(
                    filename=local_file,
                    key=gold_key,
                    bucket_name=self.bucket,
                    replace=True
                )
                
                return gold_key
        """
        # Implementação padrão: não faz nada (apenas retorna)
        log.info("[GOLD-TRANSFORM] ℹ️ Nenhuma transformação customizada")
        return gold_key
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 5: CAMADA DELTA
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _process_delta(self):
        """Cria camada Delta (para Thrift Server)"""
        try:
            from lib.gold_delta_layer import gold_to_delta
            
            gold_key = self.results.get('gold')
            
            log.info(f"[DELTA] Processando: {gold_key}")
            log.info(f"[DELTA] DAG ID no contexto: {self.context.get('dag_id')}")
            
            delta_result = gold_to_delta(
                source_filename=gold_key,
                target_table_name=self.target_table_name,
                dag_id=self.dag_id,
                **self.context
            )
            
            self.results['delta'] = delta_result.get('delta')
            self.results['delta_format'] = 'delta'
            self.results['delta_version'] = delta_result.get('version', 0)
            
            log.info(f"[DELTA] ✅ Delta: {self.results['delta']}")
            
        except Exception as e:
            log.warning(f"[DELTA] ⚠️ Delta falhou, usando fallback: {e}")
            # Fallback não-Delta (opcional)
            self.results['delta_format'] = 'none'
    
    # ═══════════════════════════════════════════════════════════════════════════
    # ETAPA 6: CLEANUP
    # ═══════════════════════════════════════════════════════════════════════════
    
    def _cleanup(self):
        """Remove tmpdir e recursos"""
        if self.tmpdir and os.path.exists(self.tmpdir):
            import shutil
            try:
                shutil.rmtree(self.tmpdir)
                log.info(f"[CLEANUP] ✅ Tmpdir removido: {self.tmpdir}")
            except Exception as e:
                log.warning(f"[CLEANUP] ⚠️ Erro ao remover tmpdir: {e}")


# ═══════════════════════════════════════════════════════════════════════════════
# WRAPPER PARA BACKWARD COMPATIBILITY
# ═══════════════════════════════════════════════════════════════════════════════

def raw_to_medallion(source_filename: str, target_table_name: str, **kwargs) -> Dict:
    """
    ✅ COMPATIBILIDADE: Função wrapper que mantém a interface antiga.
    
    Usa a nova classe RawToMedallionPipeline internamente.
    
    Código antigo continua funcionando:
    
    result = raw_to_medallion(
        source_filename='raw/dados/arquivo.csv',
        target_table_name='minha_tabela',
        bucket_name='lab01'
    )
    """
    log.info("[RAW_TO_MEDALLION] Usando função wrapper (nova implementação)")
    
    pipeline = RawToMedallionPipeline()
    return pipeline(source_filename=source_filename, target_table_name=target_table_name, **kwargs)


def batch_raw_to_medallion(batch_id: str, files: list, max_parallel: int = 4, **context) -> Dict:
    """
    ✅ COMPATIBILIDADE: Função wrapper para batch.
    
    Usa a nova classe RawToMedallionPipeline internamente.
    """
    from concurrent.futures import ThreadPoolExecutor, as_completed
    
    log.info(f"[BATCH] Iniciando processamento: {batch_id}")
    log.info(f"[BATCH] Total de arquivos: {len(files)}")
    
    results = []
    errors = []
    
    def process_file(file_info):
        try:
            pipeline = RawToMedallionPipeline()
            result = pipeline(
                source_filename=file_info['source_path'],
                target_table_name=os.path.splitext(file_info['file_name'])[0],
                **context
            )
            return {'status': 'success', 'file': file_info['file_name'], 'result': result}
        except Exception as e:
            return {'status': 'error', 'file': file_info['file_name'], 'error': str(e)}
    
    with ThreadPoolExecutor(max_workers=max_parallel) as executor:
        futures = [executor.submit(process_file, f) for f in files]
        for future in as_completed(futures):
            result = future.result()
            if result['status'] == 'success':
                results.append(result)
            else:
                errors.append(result)
    
    return {
        'batch_id': batch_id,
        'total_files': len(files),
        'successful': len(results),
        'failed': len(errors),
        'results': results,
        'errors': errors
    }
