from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging

log = logging.getLogger(__name__)

class MeuRawToMedallionPipeline(RawToMedallionPipeline):
    
    
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
        log.info(f"[DEBUG] source_filename: {getattr(self, 'source_filename', None)}")
    
        import numpy as np
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

        # Tenta carregar manualmente
        try:
            with open(path, 'r') as f:
                payload = json.load(f)
            if isinstance(payload, list):
                normalized = pd.json_normalize(payload)
                for colname in normalized.columns:
                    if normalized[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                        normalized[colname] = normalized[colname].apply(lambda x: json.dumps(x, ensure_ascii=False) if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x)
                return normalized
            if isinstance(payload, dict):
                normalized = pd.json_normalize(payload)
                for colname in normalized.columns:
                    if normalized[colname].apply(lambda x: isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray))).any():
                        normalized[colname] = normalized[colname].apply(lambda x: json.dumps(x, ensure_ascii=False) if isinstance(x, (list, dict, tuple, set, pd.Series, np.ndarray)) else x)
                return normalized
        except Exception as e:
            log.error(f"[READ_JSON_ROBUST] Falha ao carregar JSON manualmente: {e}")

        raise ValueError("Formato JSON não suportado ou erro de typecast/conversão")
