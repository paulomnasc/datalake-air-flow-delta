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


    def read_pasta_s3(self, subdir, file_ext=".csv"):
        """
        Lê todos os arquivos de uma pasta no S3/MinIO (subdir) com a extensão especificada,
        concatena em um único DataFrame.
        Suporta: .csv, .json, .parquet
        """
        files = self.list_raw_files(subdir=subdir)
        dfs = []
        for key in files:
            if key.endswith(file_ext):
                local_path = self.hook.download_file(key, self.tmpdir)
                if file_ext == ".csv":
                    dfs.append(pd.read_csv(local_path))
                elif file_ext == ".json":
                    dfs.append(self._read_json_robust(local_path))
                elif file_ext == ".parquet":
                    dfs.append(pd.read_parquet(local_path))
        if dfs:
            return pd.concat(dfs, ignore_index=True)
        return pd.DataFrame()

    def list_raw_files(self, subdir=None, **kwargs):
        """
        Lista todos os arquivos no bucket do usuário na subpasta 'raw/{subdir}/' se fornecido, senão apenas 'raw/'.
        Retorna uma lista de keys encontrados.
        Garante que self.hook está inicializado.
        """
        if self.hook is None:
            log.info("[LIST_RAW] Inicializando hook via _setup...")
            self._setup(**kwargs)
        prefix = 'raw/'
        if subdir:
            prefix = f"raw/{subdir.strip('/')}/"
        log.info(f"[LIST_RAW] Listando arquivos em s3://{self.bucket}/{prefix}")
        files = self.hook.list_keys(bucket_name=self.bucket, prefix=prefix)
        if not files:
            log.warning(f"[LIST_RAW] Nenhum arquivo encontrado em s3://{self.bucket}/{prefix}")
            return []
        log.info(f"[LIST_RAW] {len(files)} arquivos encontrados: {files}")
        return files
        
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
        """Inicializa tmpdir, S3 hook, Atlas, etc

        ATENÇÃO: A lógica de obtenção do bucket do usuário (por sessão) deve permanecer EXCLUSIVAMENTE no backend PHP (ConfigController/SessionHelper).
        O pipeline Python NUNCA deve tentar deduzir, buscar ou resolver o bucket do usuário. Nunca use bucket_name de kwargs.
        Sempre use apenas o valor da variável de owner dag como identificador do bucket (propagada pelo backend PHP por sessão). Nunca tente buscar por usuário/sessão aqui!
        """
        from airflow.providers.amazon.aws.hooks.s3 import S3Hook
        self.tmpdir = tempfile.mkdtemp()
        self.hook = S3Hook(aws_conn_id='minio_conn')
        # bucket deve ser sempre igual ao Owner da DAG (campo 'owner' do contexto/kwargs)
        # Nunca usar bucket_name de kwargs nem variável de ambiente!
        if 'owner' not in kwargs or not kwargs['owner']:
            raise ValueError("O parâmetro 'owner' deve ser informado para determinar o bucket do usuário.")
        self.bucket = kwargs['owner']
        # Métodos das camadas ficam fora de _setup

    def _process_silver(self):
        """Cria camada Silver (transformação customizada)"""
        log.info(f"[SILVER] Bucket efetivo: {self.bucket}")
        # ...existing code...

    def _process_gold(self):
        """Cria camada Gold (transformação customizada)"""
        log.info(f"[GOLD] Bucket efetivo: {self.bucket}")
        # ...existing code...

    def _process_delta(self):
        """Cria camada Delta (transformação customizada)"""
        log.info(f"[DELTA] Bucket efetivo: {self.bucket}")
        # ...existing code...
        
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
        import json
        import re
        
        src_files = []
        
        # 1. Tentar extrair do source_filename (caso seja um array de arquivos multi-table)
        if self.source_filename:
            try:
                parsed_source = json.loads(self.source_filename)
                if isinstance(parsed_source, list):
                    for f in parsed_source:
                        if f.endswith(f"_{self.target_table_name}.csv") or f.endswith(f"/{self.target_table_name}.csv"):
                            src_files.append(f)
            except Exception:
                pass
            
            # 2. Se for uma string direta do arquivo (ex: .csv)
            if not src_files and isinstance(self.source_filename, str) and not self.source_filename.startswith('['):
                if self.source_filename.endswith('.csv') or self.source_filename.endswith('.json') or self.source_filename.endswith('.parquet'):
                    src_files = [self.source_filename]

        # 3. Fall-back: Busca na pasta padrão
        if not src_files:
            is_pasta_s3 = (self.context.get('file_ext') == 'Pasta_S3') or (str(self.context.get('source_type')).lower() == 'pasta_s3')
            if is_pasta_s3 and self.source_filename:
                subdir_to_list = self.source_filename
                if subdir_to_list.startswith('raw/'):
                    subdir_to_list = subdir_to_list[4:]
                src_files = self.list_raw_files(subdir=subdir_to_list)
            else:
                src_files = self.list_raw_files(subdir=self.target_table_name)
            
        # 4. Fall-back 2: Busca na pasta base da DAG (ex: raw/pipe-northwind/)
        # Pois CodeIgniter MinioHelper salva multi-tables em raw/{base_dag_id}/
        if not src_files:
            dag_id = self.context.get('dag_id')
            if not dag_id and 'dag' in self.context:
                dag_id = self.context['dag'].dag_id
                
            if dag_id:
                base_dag_id = re.sub(r'\d+$', '', dag_id)
                dag_files = self.list_raw_files(subdir=base_dag_id)
                for f in dag_files:
                    if f.endswith(f"_{self.target_table_name}.csv") or f.endswith(f"/{self.target_table_name}.csv"):
                        src_files.append(f)

        log.info(f"[BRONZE][AUDIT] Total de arquivos a serem processados: {len(src_files)}")
        if not src_files:
            log.warning(f"[BRONZE] Nenhum arquivo econtrado para a tabela '{self.target_table_name}'")
            self.results['bronze'] = None
            return

        bronze_keys_all = []
        bronze_key = None
        for idx, src_key in enumerate(src_files):
            basename = os.path.basename(src_key)
            basename_no_ext = os.path.splitext(basename)[0]
            # Obtém dag_id limpo
            clean_dag_id = self.target_table_name
            if hasattr(self, 'context'):
                dag_id_raw = self.context.get('dag_id')
                if not dag_id_raw and 'dag' in self.context:
                    dag_id_raw = self.context['dag'].dag_id
                if dag_id_raw:
                    import re
                    clean_dag_id = re.sub(r'\d+$', '', dag_id_raw)

            bronze_key = f"bronze/{clean_dag_id}/{basename_no_ext}.parquet"
            log.info(f"[BRONZE][AUDIT] ({idx+1}/{len(src_files)}) Processo: bronze | Arquivo: {src_key}")
            local_file = self.hook.download_file(
                key=src_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            file_ext = os.path.splitext(local_file)[1].lower()
            df_bronze = self._read_file(local_file, file_ext)
            if df_bronze is None or df_bronze.empty:
                log.warning(f"[BRONZE] ⚠️ Arquivo {src_key} não contém registros. Pulando.")
                continue
            log.info(f"[BRONZE][AUDIT] Arquivo original: {src_key} | Registros originais: {len(df_bronze)}")
            local_parquet = os.path.join(self.tmpdir, f"{basename_no_ext}_bronze.parquet")
            df_bronze.to_parquet(
                local_parquet,
                index=False,
                compression='snappy',
                engine='pyarrow'
            )
            self.hook.load_file(
                filename=local_parquet,
                key=bronze_key,
                bucket_name=self.bucket,
                replace=True
            )
            log.info(f"[BRONZE][AUDIT] ✅ Finalizado: {bronze_key} | Registros processados: {len(df_bronze)}")
            bronze_keys_all.append(bronze_key)

        self.results['bronze'] = bronze_keys_all[-1] if bronze_keys_all else None
        self.results['_bronze_all'] = bronze_keys_all
    
    def _read_file(self, local_file: str, file_ext: str) -> pd.DataFrame:
        """Lê arquivo (CSV, JSON, Parquet) para DataFrame ou múltiplos arquivos se Pasta_S3"""
        # Suporte ao novo tipo Pasta_S3
        if file_ext == 'Pasta_S3':
            ext = getattr(self, 'pasta_s3_ext', '.csv')
            return self.read_pasta_s3(local_file, ext)
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
        """
        Lê JSON robusto (NDJSON, lista, objeto)

        🔧 HOOK: Override este método para customizações na leitura de JSON.
        Subclasses podem sobrescrever para adaptar parsing conforme o formato da API.

        Args:
            path: Caminho do arquivo JSON local
        Returns:
            DataFrame pandas
        """
        import numpy as np
        # 1) Tenta carregar como JSON padrão para inspecionar estrutura aninhada (objeto com lista de produtos)
        try:
            with open(path, 'r', encoding='utf-8') as f:
                payload = json.load(f)
                
            if isinstance(payload, dict):
                # Procura por uma chave cujo valor seja uma lista de registros (produtos)
                # Prioriza listas não-vazias para evitar que chaves como 'parameters' (vazias) ocultem 'response'
                record_key = None
                lists = {k: v for k, v in payload.items() if isinstance(v, list)}
                if lists:
                    non_empty_lists = {k: v for k, v in lists.items() if len(v) > 0}
                    if non_empty_lists:
                        # Escolhe a lista com mais registros (ou a primeira não-vazia)
                        record_key = max(non_empty_lists.keys(), key=lambda k: len(non_empty_lists[k]))
                    else:
                        # Se todas as listas forem vazias, escolhe a primeira para poder retornar DataFrame vazio
                        record_key = list(lists.keys())[0]
                
                if record_key:
                    if len(payload[record_key]) > 0:
                        # Extrai chaves simples de metadados para preencher colunas em todas as linhas (ex: site)
                        meta_keys = [
                            k for k in payload.keys() 
                            if k != record_key and not isinstance(payload[k], (dict, list))
                        ]
                        df = pd.json_normalize(payload, record_path=record_key, meta=meta_keys)
                        
                        # Converte colunas com arrays/objetos para string
                        for colname in df.columns:
                            if df[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                                df[colname] = df[colname].apply(
                                    lambda x: json.dumps(x, ensure_ascii=False) 
                                    if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x
                                )
                        return df
                    else:
                        # Lista de registros vazia -> retorna DataFrame vazio
                        log.info(f"[READ_JSON_ROBUST] Chave de registros '{record_key}' vazia em {path}")
                        return pd.DataFrame()
                        
            if isinstance(payload, list):
                if not payload:
                    return pd.DataFrame()
                df = pd.json_normalize(payload)
                for colname in df.columns:
                    if df[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                        df[colname] = df[colname].apply(
                            lambda x: json.dumps(x, ensure_ascii=False) 
                            if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x
                        )
                return df
        except Exception as e:
            log.warning(f"[READ_JSON_ROBUST] Leitura estruturada falhou, tentando fallbacks: {e}")

        try:
            df = pd.read_json(path, lines=True)
            if not df.empty:
                return df
        except Exception as e:
            log.warning(f"[READ_JSON_ROBUST] NDJSON falhou: {e}")

        try:
            df = pd.read_json(path)
            if not df.empty:
                # Caso coluna única tipo object
                if len(df.columns) == 1 and df.dtypes.iloc[0] == 'object':
                    col = df.columns[0]
                    def safe_json_load(x):
                        try:
                            return json.loads(x) if isinstance(x, str) else x
                        except Exception as e:
                            log.warning(f"[READ_JSON_ROBUST] Erro ao converter valor para JSON: {x} ({e})")
                            return None
                    try:
                        # Filtra apenas dicts válidos
                        normalized_data = [safe_json_load(x) for x in df[col]]
                        normalized_data = [item for item in normalized_data if isinstance(item, dict)]
                        if not normalized_data:
                            log.warning("[READ_JSON_ROBUST] Nenhum dict válido para normalizar.")
                            return df
                        normalized = pd.json_normalize(normalized_data)
                        if not normalized.empty:
                            # Converte colunas com arrays/objetos para string
                            for colname in normalized.columns:
                                if normalized[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                                    normalized[colname] = normalized[colname].apply(lambda x: json.dumps(x, ensure_ascii=False) if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x)
                            return normalized
                    except Exception as e:
                        log.warning(f"[READ_JSON_ROBUST] Normalização falhou: {e}")
                # Também garante para DataFrames simples
                for colname in df.columns:
                    if df[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                        df[colname] = df[colname].apply(lambda x: json.dumps(x, ensure_ascii=False) if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x)
                return df
        except Exception as e:
            log.warning(f"[READ_JSON_ROBUST] JSON padrão falhou: {e}")

        raise ValueError("Formato JSON não suportado ou erro de typecast/conversão")
    
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

        bronze_keys = self.results.get('_bronze_all') or self.results.get('bronze')
        log.info(f"[SILVER][AUDIT] Total de arquivos a serem processados: {len(bronze_keys) if bronze_keys else 0}")
        if not bronze_keys:
            log.warning("[SILVER] Nenhum bronze_key encontrado para processar.")
            self.results['silver'] = None
            return

        if isinstance(bronze_keys, str):
            bronze_keys = [bronze_keys]

        silver_keys_all = []
        silver_key_final = None
        for idx, bronze_key in enumerate(bronze_keys):
            log.info(f"[SILVER][AUDIT] ({idx+1}/{len(bronze_keys)}) Processo: silver | Arquivo: {bronze_key}")
            # Baixar bronze para contar registros
            local_file = self.hook.download_file(
                key=bronze_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            df_bronze = pd.read_parquet(local_file)
            log.info(f"[SILVER][AUDIT] Arquivo original: {bronze_key} | Registros originais: {len(df_bronze)}")
            silver_result = bronze_to_silver(
                bronze_key,
                self.target_table_name,
                bucket=self.bucket,
                **self.context
            )
            silver_keys = silver_result.get('keys', [])
            if not silver_keys:
                log.warning(f"[SILVER] Nenhuma chave retornada para bronze {bronze_key}")
                continue
            for silver_key in silver_keys:
                log.info(f"[SILVER][AUDIT] ✅ Silver padrão: {silver_key}")
                # Baixar silver para contar registros
                local_silver = self.hook.download_file(
                    key=silver_key,
                    bucket_name=self.bucket,
                    local_path=self.tmpdir,
                    preserve_file_name=True
                )
                df_silver = pd.read_parquet(local_silver)
                log.info(f"[SILVER][AUDIT] ✅ Finalizado: {silver_key} | Registros processados: {len(df_silver)}")
                silver_key_final = self.silver_layer_transform(silver_key)
                silver_keys_all.append(silver_key_final)

        self.results['silver'] = silver_key_final
        self.results['_silver_all'] = silver_keys_all
    
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

        silver_keys = self.results.get('_silver_all') or self.results.get('silver')
        log.info(f"[GOLD][AUDIT] Total de arquivos a serem processados: {len(silver_keys) if silver_keys else 0}")
        if not silver_keys:
            log.warning("[GOLD] Nenhum silver_key encontrado para processar.")
            self.results['gold'] = None
            return

        if isinstance(silver_keys, str):
            silver_keys = [silver_keys]

        gold_keys_all = []
        gold_key_final = None
        for idx, silver_key in enumerate(silver_keys):
            log.info(f"[GOLD][AUDIT] ({idx+1}/{len(silver_keys)}) Processo: gold | Arquivo: {silver_key}")
            # Baixar silver para contar registros
            local_file = self.hook.download_file(
                key=silver_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            df_silver = pd.read_parquet(local_file)
            log.info(f"[GOLD][AUDIT] Arquivo original: {silver_key} | Registros originais: {len(df_silver)}")
            gold_result = silver_to_gold(
                source_filename=silver_key,
                target_table_name=self.target_table_name,
                bucket=self.bucket,
                **self.context
            )
            gold_keys = gold_result.get('keys', [])
            if not gold_keys:
                log.warning(f"[GOLD] Nenhuma chave retornada para silver {silver_key}")
                continue
            for gold_key in gold_keys:
                log.info(f"[GOLD][AUDIT] ✅ Gold padrão: {gold_key}")
                # Baixar gold para contar registros
                local_gold = self.hook.download_file(
                    key=gold_key,
                    bucket_name=self.bucket,
                    local_path=self.tmpdir,
                    preserve_file_name=True
                )
                df_gold = pd.read_parquet(local_gold)
                log.info(f"[GOLD][AUDIT] ✅ Finalizado: {gold_key} | Registros processados: {len(df_gold)}")
                gold_key_final = self.gold_layer_transform(gold_key)
                gold_keys_all.append(gold_key_final)

        self.results['gold'] = gold_key_final
        self.results['gold_format'] = 'parquet'
        self.results['_gold_all'] = gold_keys_all
    
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

            gold_keys = self.results.get('_gold_all') or self.results.get('gold')
            if not gold_keys:
                log.warning("[DELTA] Nenhum gold_key encontrado para processar.")
                self.results['delta'] = None
                self.results['delta_format'] = 'none'
                self.results['_delta_all'] = []
                return

            if isinstance(gold_keys, str):
                gold_keys = [gold_keys]

            delta_keys_all = []
            delta_key_final = None
            processed_files = set()
            log.info(f"[DELTA][AUDIT] Total de arquivos a serem processados: {len(gold_keys) if gold_keys else 0}")
            for idx, gold_key in enumerate(gold_keys):
                log.info(f"[DELTA][AUDIT] ({idx+1}/{len(gold_keys)}) Processo: delta | Arquivo: {gold_key}")
                if gold_key in processed_files:
                    log.warning(f"[DELTA] Arquivo já processado: {gold_key}")
                    continue
                processed_files.add(gold_key)
                # Extrair nome base da tabela/parquet
                import os
                table_name = os.path.splitext(os.path.basename(gold_key))[0]
                # Baixar gold para contar registros
                local_gold = self.hook.download_file(
                    key=gold_key,
                    bucket_name=self.bucket,
                    local_path=self.tmpdir,
                    preserve_file_name=True
                )
                import pandas as pd
                df_gold = pd.read_parquet(local_gold)
                log.info(f"[DELTA][AUDIT] Arquivo original: {gold_key} | Registros originais: {len(df_gold)}")
                delta_result = gold_to_delta(
                    gold_key,
                    table_name,
                    bucket=self.bucket,
                    **self.context
                )
                delta_path = delta_result.get('gold_delta') or delta_result.get('delta_path') or delta_result.get('delta_key')
                if not delta_path:
                    log.warning(f"[DELTA] Nenhum caminho Delta retornado para {gold_key}")
                    continue
                # Após escrita Delta, tentar ler quantidade de registros Delta
                try:
                    from deltalake import DeltaTable
                    storage_options = {
                        "AWS_ACCESS_KEY_ID": "minioadmin",
                        "AWS_SECRET_ACCESS_KEY": "minioadmin",
                        "AWS_ENDPOINT_URL": "http://minio:9000",
                        "AWS_REGION": "us-east-1"
                    }
                    dt = DeltaTable(delta_path, storage_options=storage_options)
                    df_delta = dt.to_pandas()
                    log.info(f"[DELTA][AUDIT] ✅ Finalizado: {delta_path} | Registros processados (total Delta): {len(df_delta)}")
                except Exception as e:
                    log.warning(f"[DELTA][AUDIT] Não foi possível ler Delta para auditoria: {e}")
                delta_keys_all.append(delta_path)
                delta_key_final = delta_path

            self.results['delta'] = delta_key_final
            self.results['delta_format'] = 'delta'
            self.results['_delta_all'] = delta_keys_all
        except Exception as e:
            log.warning(f"[DELTA] ⚠️ Delta falhou, usando fallback: {e}")
            self.results['delta'] = None
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
    log.info(f"[RAW_TO_MEDALLION] kwargs recebidos: {kwargs}")
    if not kwargs:
        log.warning("[RAW_TO_MEDALLION] Nenhum parâmetro recebido em kwargs! Verifique passagem de transform_args na DAG.")
    else:
        for k, v in kwargs.items():
            log.info(f"[RAW_TO_MEDALLION] Param: {k} = {v}")

    # Logar decisão de roteamento
    # bucket_name deve ser resolvido no backend PHP e passado via kwargs. Nunca deduzir bucket do usuário aqui!
    if kwargs.get('api_endpoint'):
        log.info("[RAW_TO_MEDALLION] Detecção: pipeline API REST (api_endpoint presente)")
    elif kwargs.get('mysql_conn_id') or kwargs.get('sql_connection_id'):
        log.info("[RAW_TO_MEDALLION] Detecção: pipeline MySQL (mysql_conn_id/sql_connection_id presente)")
    elif source_filename and (str(source_filename).endswith('.csv') or str(source_filename).endswith('.json') or str(source_filename).endswith('.parquet')):
        log.info("[RAW_TO_MEDALLION] Detecção: pipeline arquivo (extensão conhecida)")
    elif source_filename is None and kwargs.get('api_endpoint'):
        log.info("[RAW_TO_MEDALLION] Detecção: pipeline API REST (source_filename None, api_endpoint presente)")
    else:
        log.warning("[RAW_TO_MEDALLION] Não foi possível determinar o tipo de pipeline a partir dos parâmetros.")
    # 🛍️ Suporte para Ingestão Dinâmica da Shopee via Parâmetros de UI Console
    target_table = str(target_table_name or '').lower()
    dag_identifier = str(kwargs.get('dag_id', '')).lower()

    if 'shopee' in target_table or 'shopee' in dag_identifier:
        params_dict = kwargs.get('params') or {}
        if not isinstance(params_dict, dict):
            params_dict = {}

        kw_val = kwargs.get('keyword') or params_dict.get('keyword')
        lim_val = kwargs.get('limit') or params_dict.get('limit') or 50

        if kw_val:
            log.info(f"[RAW_TO_MEDALLION] Executando extrator Shopee para keyword='{kw_val}', limit={lim_val}...")
            try:
                import subprocess
                import sys
                script_paths = [
                    '/usr/local/bin/scripts/shopee_ingest_offers.py',
                    '/root/datalake-air-flow-delta/scripts/shopee_ingest_offers.py'
                ]
                selected_script = None
                for sp in script_paths:
                    if os.path.exists(sp):
                        selected_script = sp
                        break
                if selected_script:
                    cmd = [sys.executable, selected_script, str(kw_val), str(lim_val)]
                    res = subprocess.run(cmd, capture_output=True, text=True)
                    log.info(f"[RAW_TO_MEDALLION] Extrator Shopee concluído: STDOUT={res.stdout}")
                    if res.stderr:
                        log.warning(f"[RAW_TO_MEDALLION] Extrator Shopee STDERR={res.stderr}")
            except Exception as e:
                log.warning(f"[RAW_TO_MEDALLION] Erro ao executar extrator Shopee: {e}")

    # Detecta tipo de fonte
    source_type = kwargs.get('source_type') or kwargs.get('fonte') or kwargs.get('tipo_fonte')

    # Detecta tipo de fonte de forma robusta
    if not source_type:
        if kwargs.get('api_endpoint'):
            source_type = 'api'
        elif kwargs.get('mysql_conn_id') or kwargs.get('sql_connection_id'):
            source_type = 'mysql'
        elif source_filename and (str(source_filename).endswith('.csv') or str(source_filename).endswith('.json') or str(source_filename).endswith('.parquet')):
            source_type = 'arquivo'
        elif source_filename is None and kwargs.get('api_endpoint'):
            # Caso especial: source_filename None mas api_endpoint presente
            source_type = 'api'
        else:
            source_type = 'arquivo'  # fallback
    source_type = str(source_type).lower()

    # Novo tipo: Pasta_S3
    if source_type == 'pasta_s3':
        subdir = kwargs.get('subdir')
        file_ext = kwargs.get('file_ext')
        
        # Tenta extrair a partir do JSON string de source_filename gerado pelo PHP webapp
        if not subdir and source_filename:
            try:
                parsed = json.loads(source_filename)
                if isinstance(parsed, dict):
                    subdir = parsed.get('subdir')
                    if not file_ext:
                        file_ext = parsed.get('file_ext', '.csv')
            except Exception:
                pass
                
        subdir = subdir or source_filename or ''
        file_ext = file_ext or '.csv'
        pipeline = RawToMedallionPipeline()
        pipeline.pasta_s3_ext = file_ext
        return pipeline(source_filename=subdir, target_table_name=target_table_name, file_ext='Pasta_S3', **kwargs)

    if source_type in ['api', 'api rest', 'rest', 'rest api']:
        log.info("[RAW_TO_MEDALLION] Delegando para ingest_api_to_raw (API REST)")
        from lib.api_ingestion import ingest_api_to_raw
        api_endpoint = kwargs.get('api_endpoint')
        api_method = kwargs.get('api_method', 'GET')
        api_headers = kwargs.get('api_headers') or {}
        api_params = kwargs.get('api_params') or {}
        api_payload = kwargs.get('api_payload') or {}
        dag_id = kwargs.get('dag_id') or 'default'

        # Correção automática do endpoint e parâmetros obrigatórios
        if api_endpoint and 'amadeus.com' in api_endpoint:
            if '/v2/' in api_endpoint or '/v2' in api_endpoint:
                log.warning("[RAW_TO_MEDALLION] Corrigindo endpoint Amadeus de /v2 para /v1.")
                api_endpoint = api_endpoint.replace('/v2/', '/v1/').replace('/v2', '/v1')
            #if not api_params.get('origin'):
             #   log.warning("[RAW_TO_MEDALLION] Parâmetro 'origin' ausente. Adicionando valor padrão 'PAR'.")
             #   api_params['origin'] = 'PAR'  # Valor padrão, pode ser ajustado conforme necessidade

        if not api_endpoint:
            raise ValueError("[RAW_TO_MEDALLION] Parâmetro obrigatório 'api_endpoint' ausente para fonte API REST.")

        log.info(f"[RAW_TO_MEDALLION] Parâmetros API: endpoint={api_endpoint}, method={api_method}, headers={api_headers}, params={api_params}, payload={api_payload}, dag_id={dag_id}")
        # Remove todos os argumentos já passados explicitamente
        kwargs_clean = dict(kwargs)
        for k in [
            'api_endpoint', 'api_method', 'api_headers', 'api_params', 'api_payload',
            'target_table_name', 'dag_id'
        ]:
            kwargs_clean.pop(k, None)
        log.info("[RAW_TO_MEDALLION] Chamando ingest_api_to_raw...")
        ingest_result = ingest_api_to_raw(
            api_endpoint=api_endpoint,
            api_method=api_method,
            api_headers=api_headers,
            api_params=api_params,
            api_payload=api_payload,
            target_table_name=target_table_name,
            dag_id=dag_id,
            **kwargs_clean
        )
        log.info(f"[RAW_TO_MEDALLION] Retorno ingest_api_to_raw: {ingest_result}")
        # Pega o caminho do arquivo salvo na camada raw
        source_filename_api = ingest_result.get('key')
        log.info(f"[RAW_TO_MEDALLION] Caminho arquivo gerado: {source_filename_api}")
        if not source_filename_api:
            raise ValueError("[RAW_TO_MEDALLION] ingest_api_to_raw não retornou o campo 'key' com o caminho do arquivo raw gerado.")
        # Chama pipeline padrão com o arquivo gerado
        pipeline = RawToMedallionPipeline()
        log.info(f"[RAW_TO_MEDALLION] Chamando pipeline padrão com source_filename={source_filename_api}")
        return pipeline(source_filename=source_filename_api, target_table_name=target_table_name, **kwargs)
    #elif source_type in ['mysql', 'sql', 'banco', 'database']:
        # log.info("[RAW_TO_MEDALLION] Delegando para ingest_mysql_to_raw (MySQL)")
        # from lib.mysql_ingestion import ingest_mysql_to_raw
        # mysql_conn_id = kwargs.get('mysql_conn_id') or kwargs.get('sql_connection_id')
        # if not mysql_conn_id:
        #     raise ValueError("[RAW_TO_MEDALLION] Parâmetro obrigatório 'mysql_conn_id' ou 'sql_connection_id' ausente para fonte MySQL.")
        # table_name = kwargs.get('table_name') or target_table_name
        # query = kwargs.get('query') or None
        # dag_id = kwargs.get('dag_id') or 'default'
        # return ingest_mysql_to_raw(
        #     mysql_conn_id=mysql_conn_id,
        #     table_name=table_name,
        #     query=query,
        #     target_table_name=target_table_name,
        #     dag_id=dag_id,
        #     **kwargs
        # )
    else:
        log.info("[RAW_TO_MEDALLION] Delegando para pipeline padrão (arquivos)")
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
