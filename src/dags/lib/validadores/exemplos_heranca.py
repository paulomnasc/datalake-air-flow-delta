"""
📚 EXEMPLOS DE VALIDADORES CUSTOMIZADOS COM HERANÇA

Padrão recomendado: Herdar de RawToMedallionPipeline e override hooks.

VANTAGENS:
✅ Sincronização 100% garantida (architecturally impossible race conditions)
✅ Herança oferece toda a estrutura do pipeline
✅ Override apenas o necessário (single responsibility)
✅ Fácil de estender
✅ Sem necessidade de re-salvar múltiplas vezes
"""

from lib.medallion_pipeline_v2 import RawToMedallionPipeline
import pandas as pd
import logging
import os

log = logging.getLogger(__name__)


# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 1: Validador de CEP (Silver Layer)
# ═══════════════════════════════════════════════════════════════════════════════

class CustomerValidador(RawToMedallionPipeline):
    """
    ✅ Valida e normaliza dados de Customer na camada Silver.
    
    Herança garante:
    - Sincronização completa (Silver já finalizada quando hook roda)
    - Acesso a self.hook, self.bucket, self.tmpdir
    - Nenhuma race condition possível
    
    Uso:
        pipeline = CustomerValidador()
        result = pipeline(
            source_filename='raw/dados/Customer.csv',
            target_table_name='Customer'
        )
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """
        Override do hook Silver: Validar e normalizar dados de Cliente
        
        Roda GARANTIDAMENTE após a Silver padrão estar salva.
        Seguro sobrescrever arquivo.
        """
        
        log.info("[CustomerValidador] 🔍 Iniciando validações de Cliente...")
        
        try:
            # Baixar Silver (já finalizada, seguro!)
            local_file = self.hook.download_file(
                key=silver_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            
            # Ler DataFrame
            df = pd.read_parquet(local_file)
            log.info(f"[CustomerValidador] ✅ Silver carregado: {len(df)} registros")
            
            # ─────────────────────────────────────────────────
            # VALIDAÇÃO 1: CEP (Billing Postal Code)
            # ─────────────────────────────────────────────────
            
            if 'billingpostalcode' in df.columns:
                log.info("[CustomerValidador] 🔧 Normalizando billingpostalcode...")
                
                invalid_mask = (
                    (df['billingpostalcode'].isnull()) |
                    (df['billingpostalcode'].astype(str).str.strip().str.lower()
                     .isin(['nan', 'none', 'null', '', 'undefined']))
                )
                
                invalid_count = invalid_mask.sum()
                if invalid_count > 0:
                    log.info(f"[CustomerValidador]   Corrigindo {invalid_count} valores inválidos")
                    df.loc[invalid_mask, 'billingpostalcode'] = None
            
            # ─────────────────────────────────────────────────
            # VALIDAÇÃO 2: Email
            # ─────────────────────────────────────────────────
            
            if 'email' in df.columns:
                log.info("[CustomerValidador] 📧 Validando emails...")
                
                # Remover espaços
                df['email'] = df['email'].astype(str).str.strip().str.lower()
                
                # Valores inválidos
                invalid_emails = ~df['email'].str.contains(
                    r'^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$|^nan$|^none$|^null$|^$',
                    na=False
                )
                
                if invalid_emails.any():
                    log.info(f"[CustomerValidador]   {invalid_emails.sum()} emails inválidos encontrados")
                    # Opção 1: Remover linhas
                    # df = df[~invalid_emails]
                    # Opção 2: Marcar como NULL
                    df.loc[invalid_emails, 'email'] = None
            
            # ─────────────────────────────────────────────────
            # VALIDAÇÃO 3: Telefone
            # ─────────────────────────────────────────────────
            
            if 'phone' in df.columns:
                log.info("[CustomerValidador] 📱 Padronizando telefones...")
                
                # Remover caracteres especiais, manter apenas dígitos
                df['phone'] = df['phone'].astype(str).str.replace(r'\D', '', regex=True)
                
                # Se vazio, NULL
                df.loc[df['phone'].isin(['', 'nan']), 'phone'] = None
            
            # ─────────────────────────────────────────────────
            # VALIDAÇÃO 4: Data Quality Score
            # ─────────────────────────────────────────────────
            
            quality_score = (df.notna().sum().sum() / df.size) * 100
            log.info(f"[CustomerValidador] 📊 Quality Score: {quality_score:.2f}%")
            
            if quality_score < 70:
                log.warning(f"[CustomerValidador] ⚠️ Quality Score baixo: {quality_score:.2f}%")
            
            # ─────────────────────────────────────────────────
            # RE-SALVAR SILVER COM VALIDAÇÕES
            # ─────────────────────────────────────────────────
            
            df.to_parquet(
                local_file,
                index=False,
                compression='snappy',
                engine='pyarrow'
            )
            
            # Upload (SEGURO: Silver já está finalizada)
            self.hook.load_file(
                filename=local_file,
                key=silver_key,
                bucket_name=self.bucket,
                replace=True
            )
            
            log.info(f"[CustomerValidador] ✅ Silver validada e salva!")
            
            return silver_key
            
        except Exception as e:
            log.error(f"[CustomerValidador] ❌ Erro: {e}", exc_info=True)
            raise


# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 2: Agregações de Negócio (Gold Layer)
# ═══════════════════════════════════════════════════════════════════════════════

class InvoiceAgregador(RawToMedallionPipeline):
    """
    ✅ Aplica agregações de negócio na camada Gold.
    
    Herança garante:
    - Sincronização completa (Gold já finalizada)
    - Acesso a estrutura do pipeline
    - Nenhuma interferência com Silver
    
    Uso:
        pipeline = InvoiceAgregador()
        result = pipeline(
            source_filename='raw/dados/Invoice.csv',
            target_table_name='Invoice'
        )
    """
    
    def gold_layer_transform(self, gold_key: str) -> str:
        """
        Override do hook Gold: Aplicar agregações de negócio
        
        Roda GARANTIDAMENTE após a Gold padrão estar salva.
        """
        
        log.info("[InvoiceAgregador] 💰 Aplicando agregações de negócio...")
        
        try:
            # Baixar Gold (já finalizada, seguro!)
            local_file = self.hook.download_file(
                key=gold_key,
                bucket_name=self.bucket,
                local_path=self.tmpdir,
                preserve_file_name=True
            )
            
            df = pd.read_parquet(local_file)
            log.info(f"[InvoiceAgregador] ✅ Gold carregado: {len(df)} registros")
            
            # ─────────────────────────────────────────────────
            # AGREGAÇÃO 1: Adicionar coluna de categoria de valor
            # ─────────────────────────────────────────────────
            
            if 'total' in df.columns:
                log.info("[InvoiceAgregador] 📊 Categorizando valores...")
                
                df['value_category'] = pd.cut(
                    df['total'],
                    bins=[0, 100, 500, 1000, float('inf')],
                    labels=['pequeno', 'médio', 'grande', 'muito_grande']
                )
            
            # ─────────────────────────────────────────────────
            # AGREGAÇÃO 2: Flag de atraso (se houver data)
            # ─────────────────────────────────────────────────
            
            if 'duedate' in df.columns and 'paidate' in df.columns:
                log.info("[InvoiceAgregador] ⏰ Identificando pagamentos atrasados...")
                
                df['duedate'] = pd.to_datetime(df['duedate'], errors='coerce')
                df['paidate'] = pd.to_datetime(df['paidate'], errors='coerce')
                
                df['is_overdue'] = (df['paidate'] > df['duedate']).astype(int)
                
                overdue_count = df['is_overdue'].sum()
                log.info(f"[InvoiceAgregador]   {overdue_count} pagamentos atrasados")
            
            # ─────────────────────────────────────────────────
            # AGREGAÇÃO 3: Adicionar métricas
            # ─────────────────────────────────────────────────
            
            log.info("[InvoiceAgregador] 📈 Adicionando métricas...")
            
            stats = {
                'total_registros': len(df),
                'valor_total': df['total'].sum() if 'total' in df.columns else 0,
                'valor_medio': df['total'].mean() if 'total' in df.columns else 0,
                'data_processamento': pd.Timestamp.now().isoformat()
            }
            
            for key, value in stats.items():
                log.info(f"[InvoiceAgregador]   {key}: {value}")
            
            # ─────────────────────────────────────────────────
            # RE-SALVAR GOLD COM AGREGAÇÕES
            # ─────────────────────────────────────────────────
            
            df.to_parquet(
                local_file,
                index=False,
                compression='snappy',
                engine='pyarrow'
            )
            
            # Upload (SEGURO: Gold já está finalizada)
            self.hook.load_file(
                filename=local_file,
                key=gold_key,
                bucket_name=self.bucket,
                replace=True
            )
            
            log.info(f"[InvoiceAgregador] ✅ Gold com agregações salvo!")
            
            return gold_key
            
        except Exception as e:
            log.error(f"[InvoiceAgregador] ❌ Erro: {e}", exc_info=True)
            raise


# ═══════════════════════════════════════════════════════════════════════════════
# EXEMPLO 3: Validações + Agregações (Override ambos hooks)
# ═══════════════════════════════════════════════════════════════════════════════

class TrackValidadorEAgregador(RawToMedallionPipeline):
    """
    ✅ Combina validações (Silver) + agregações (Gold)
    
    Override AMBOS os hooks para máxima customização.
    Sincronização garantida em todo fluxo.
    
    Uso:
        pipeline = TrackValidadorEAgregador()
        result = pipeline(
            source_filename='raw/dados/Track.csv',
            target_table_name='Track'
        )
    """
    
    def silver_layer_transform(self, silver_key: str) -> str:
        """Validações na Silver"""
        
        log.info("[TrackValidador] 🎵 Validando dados de Track...")
        
        local_file = self.hook.download_file(
            key=silver_key,
            bucket_name=self.bucket,
            local_path=self.tmpdir,
            preserve_file_name=True
        )
        
        df = pd.read_parquet(local_file)
        
        # Normalizar nome da música
        if 'name' in df.columns:
            df['name'] = df['name'].astype(str).str.strip()
            df.loc[df['name'].isin(['nan', 'none', '']), 'name'] = None
        
        # Validar duração
        if 'milliseconds' in df.columns:
            df['milliseconds'] = pd.to_numeric(df['milliseconds'], errors='coerce')
            df.loc[df['milliseconds'] <= 0, 'milliseconds'] = None
        
        df.to_parquet(local_file, index=False, compression='snappy', engine='pyarrow')
        self.hook.load_file(
            filename=local_file,
            key=silver_key,
            bucket_name=self.bucket,
            replace=True
        )
        
        log.info("[TrackValidador] ✅ Validações concluídas")
        return silver_key
    
    def gold_layer_transform(self, gold_key: str) -> str:
        """Agregações na Gold"""
        
        log.info("[TrackAgregador] 🎵 Agregando dados de Track...")
        
        local_file = self.hook.download_file(
            key=gold_key,
            bucket_name=self.bucket,
            local_path=self.tmpdir,
            preserve_file_name=True
        )
        
        df = pd.read_parquet(local_file)
        
        # Categorizar por duração
        if 'milliseconds' in df.columns:
            df['duration_category'] = pd.cut(
                df['milliseconds'],
                bins=[0, 180000, 300000, 600000, float('inf')],
                labels=['muito_curta', 'curta', 'média', 'longa']
            )
        
        # Contar músicas por gênero
        if 'genreid' in df.columns:
            genre_counts = df['genreid'].value_counts()
            log.info(f"[TrackAgregador] 🎸 Distribuição: {genre_counts.to_dict()}")
        
        df.to_parquet(local_file, index=False, compression='snappy', engine='pyarrow')
        self.hook.load_file(
            filename=local_file,
            key=gold_key,
            bucket_name=self.bucket,
            replace=True
        )
        
        log.info("[TrackAgregador] ✅ Agregações concluídas")
        return gold_key
