
from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging

log = logging.getLogger(__name__)


class MeuValidador(RawToMedallionPipeline):
    """
    Validador customizado herdando de RawToMedallionPipeline.
    
    VANTAGENS:
    ✅ Sincronização 100% garantida (architecturally impossible race conditions)
    ✅ Acesso a self.hook, self.bucket, self.tmpdir
    ✅ Nenhuma race condition possível
    ✅ Simples de estender
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """
        Override do hook Silver: Validar e transformar dados de Cliente.
        
        ✅ Roda GARANTIDAMENTE após Silver estar 100% salva em S3.
        ✅ Seguro sobrescrever arquivo (nenhuma race condition).
        """
        try:
            log.info(f"🔍 [MeuValidador] Processando Silver: {silver_key}")
            
            # Download do arquivo Silver
            local_file = self.hook.download_file(
                key=silver_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            log.info(f"✅ [MeuValidador] Download concluído")
            
            # Ler como DataFrame
            df = pd.read_parquet(local_file)
            original_rows = len(df)
            original_cols = len(df.columns)
            log.info(f"📊 [MeuValidador] Entrada: {original_rows} registros, {original_cols} colunas")
            
            # Aplicar validações
            df = self._apply_validations(df)
            
            # Salvar de volta (sobrescreve o arquivo Silver)
            df.to_parquet(local_file, index=False)
            
            # Upload de volta para S3
            self.hook.load_file(
                filename=local_file,
                key=silver_key,
                bucket_name=self.bucket,
                replace=True
            )
            log.info(f"🚀 [MeuValidador] Silver validada e salva ✅")
            
            return silver_key
            
        except Exception as e:
            log.error(f"❌ [MeuValidador] ERRO: {e}", exc_info=True)
            raise
    
    def _apply_validations(self, df):
        """Aplicar todas as validações no DataFrame"""
        log.info(f"⚙️ [MeuValidador] Iniciando validações...")
        
        original_rows = len(df)
        original_cols = len(df.columns)
        
        # ═══════════════════════════════════════════════════════════════════
        # VALIDAÇÃO 1: CEP (Billing Postal Code)
        # ═══════════════════════════════════════════════════════════════════
        if 'BillingPostalCode' in df.columns:
            log.info("🔍 [MeuValidador] Validando coluna 'BillingPostalCode'...")
            
            invalid_mask = (
                (df['BillingPostalCode'].isnull()) |
                (df['BillingPostalCode'].astype(str).str.strip().str.lower()
                 .isin(['nan', 'none', 'null', '', 'undefined']))
            )
            invalid_count = invalid_mask.sum()
            
            if invalid_count > 0:
                log.info(f"   └─ {invalid_count} valores inválidos encontrados")
                df.loc[invalid_mask, 'BillingPostalCode'] = None
                log.info(f"   └─ CEP normalizado ✅")
            else:
                log.info(f"   └─ Nenhum inválido ✅")
        else:
            log.warning(f"⚠️ Coluna 'BillingPostalCode' não encontrada")
        
        # ═══════════════════════════════════════════════════════════════════
        # VALIDAÇÃO 2: Remover colunas 100% nulas
        # ═══════════════════════════════════════════════════════════════════
        cols_to_drop = [col for col in df.columns if df[col].isnull().all()]
        
        if cols_to_drop:
            log.info(f"🗑️ Removendo {len(cols_to_drop)} colunas 100% nulas")
            df = df.drop(columns=cols_to_drop)
        
        # ═══════════════════════════════════════════════════════════════════
        # VALIDAÇÃO 3: Normalizar nomes de colunas
        # ═══════════════════════════════════════════════════════════════════
        df.columns = (df.columns
                     .str.strip()
                     .str.lower()
                     .str.replace(' ', '_')
                     .str.replace('-', '_'))
        log.info(f"📝 Colunas normalizadas")
        
        # ═══════════════════════════════════════════════════════════════════
        # VALIDAÇÃO 4: Data Quality Score
        # ═══════════════════════════════════════════════════════════════════
        total_cells = df.size
        filled_cells = df.notna().sum().sum()
        quality_score = (filled_cells / total_cells * 100) if total_cells > 0 else 100
        
        log.info(f"📊 Quality Score: {quality_score:.2f}% ({filled_cells}/{total_cells})")
        
        if quality_score < 50:
            log.error(f"❌ Quality Score crítico ({quality_score:.2f}%)")
        elif quality_score < 80:
            log.warning(f"⚠️ Quality Score baixo ({quality_score:.2f}%)")
        else:
            log.info(f"✅ Quality Score aceitável")
        
        # ═══════════════════════════════════════════════════════════════════
        # VALIDAÇÃO 5: Resumo final
        # ═══════════════════════════════════════════════════════════════════
        log.info(f"📈 Resumo: {original_rows}→{len(df)} registros, {original_cols}→{len(df.columns)} colunas, {quality_score:.2f}% qualidade ✅")
        
        return df