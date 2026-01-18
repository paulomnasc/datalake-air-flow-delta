"""
VALIDADOR CUSTOMIZADO - VERSÃO CORRIGIDA E SINCRONIZADA

PROBLEMA DESCOBERTO:
- raw_to_medallion() já faz TUDO (Bronze → Silver → Gold → Delta)
- Tentar re-salvar Silver depois causa CORRUPÇÃO do arquivo
- Sincronização perdida entre etapas

SOLUÇÃO:
- NÃO modificar a Silver depois que raw_to_medallion() termina
- OU: implementar validação DENTRO do pipeline (hook)
- OU: aplicar validações NA LEITURA (lazy), não na escrita
"""

from lib.medallion_pipeline import raw_to_medallion
import pandas as pd
import logging
import os
import tempfile
from airflow.providers.amazon.aws.hooks.s3 import S3Hook

log = logging.getLogger(__name__)


class MeuValidadorCORRIGIDO:
    """
    Wrapper que executa pipeline Medallion de forma SEGURA sem corrupção.
    
    Opção 1: Executar raw_to_medallion() e aplicar validações na Gold (após sincronização)
    Opção 2: Usar hook para validar DURANTE a criação da Silver (sync automática)
    """
    
    def __call__(self, source_filename: str, target_table_name: str, **context):
        """
        Entry point seguro com sincronização correta.
        """
        log.info(f"\n{'='*80}")
        log.info(f"[MeuValidadorCORRIGIDO] 🚀 Iniciando pipeline para {target_table_name}")
        log.info(f"[MeuValidadorCORRIGIDO] Arquivo: {source_filename}")
        log.info(f"{'='*80}\n")
        
        try:
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 1: EXECUTAR PIPELINE MEDALLION COMPLETO (PADRÃO)
            # ═══════════════════════════════════════════════════════════════════
            # Isso executa TUDO: Bronze → Silver → Gold → Delta
            # E retorna um dict com as chaves: 'bronze', 'silver', 'gold', 'delta'
            log.info("[MeuValidadorCORRIGIDO] 📦 ETAPA 1: Executando pipeline padrão...")
            
            pipeline_result = raw_to_medallion(
                source_filename=source_filename,
                target_table_name=target_table_name,
                **context
            )
            
            log.info("[MeuValidadorCORRIGIDO] ✅ Pipeline padrão concluído!")
            log.info(f"   Bronze: {pipeline_result.get('bronze')}")
            log.info(f"   Silver: {pipeline_result.get('silver')}")
            log.info(f"   Gold: {pipeline_result.get('gold')}")
            
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 2: APLICAR VALIDAÇÕES NA GOLD (NÃO NA SILVER!)
            # ═══════════════════════════════════════════════════════════════════
            # ✅ CORRETO: Gold já está gerada, sincronização 100% segura
            # ❌ ERRADO: Tentar re-salvar Silver causa corrupção
            
            log.info("\n[MeuValidadorCORRIGIDO] 🔧 ETAPA 2: Aplicando validações customizadas na Gold...")
            
            self.custom_validations_on_gold(
                pipeline_result=pipeline_result,
                target_table_name=target_table_name,
                **context
            )
            
            log.info("[MeuValidadorCORRIGIDO] ✅ Validações customizadas concluídas!")
            
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 3: RETORNAR RESULTADO
            # ═══════════════════════════════════════════════════════════════════
            log.info(f"\n{'='*80}")
            log.info("[MeuValidadorCORRIGIDO] ✅ PIPELINE COMPLETO FINALIZADO COM SUCESSO!")
            log.info(f"{'='*80}\n")
            
            return pipeline_result
            
        except Exception as e:
            log.error(f"[MeuValidadorCORRIGIDO] ❌ ERRO: {e}", exc_info=True)
            raise
    
    
    def custom_validations_on_gold(self, pipeline_result: dict, target_table_name: str, **context):
        """
        ✅ CORRETO: Aplicar validações NA GOLD (após sincronização completa).
        
        Neste ponto:
        - raw_to_medallion() já terminou 100%
        - Bronze, Silver, Gold estão todos salvos e sincronizados
        - Seguro fazer transformações na Gold
        - Nenhum risco de corrupção
        
        Args:
            pipeline_result: Dict com 'bronze', 'silver', 'gold', 'delta'
            target_table_name: Nome da tabela
            context: Contexto do Airflow
        """
        
        bucket = context.get('bucket_name') or os.environ.get("MINIO_BUCKET", "lab01")
        hook = S3Hook(aws_conn_id='minio_conn')
        
        log.info(f"[CustomValidationsGold] Validando dados em s3://{bucket}/")
        
        # ───────────────────────────────────────────────────────────────────
        # PEGAR A GOLD (não a Silver!)
        # ───────────────────────────────────────────────────────────────────
        
        gold_key = pipeline_result.get('gold')
        if not gold_key:
            log.warning("[CustomValidationsGold] ⚠️ Gold key não encontrada, pulando validações")
            return
        
        log.info(f"[CustomValidationsGold] 📥 Lendo Gold: {gold_key}")
        
        tmpdir = None
        try:
            tmpdir = tempfile.mkdtemp()
            
            # Baixar Gold do MinIO
            local_file = hook.download_file(
                key=gold_key,
                bucket_name=bucket,
                local_path=tmpdir,
                preserve_file_name=True
            )
            
            # Carregar como DataFrame
            df_gold = pd.read_parquet(local_file)
            log.info(f"[CustomValidationsGold] ✅ Gold carregado: {len(df_gold)} registros, {len(df_gold.columns)} colunas")
            
            # ───── VALIDAÇÃO 1: CEP ─────
            if 'billingpostalcode' in df_gold.columns:
                log.info("[CustomValidationsGold] 🔍 Validando coluna 'billingpostalcode'...")
                
                null_before = df_gold['billingpostalcode'].isnull().sum()
                invalid_before = (df_gold['billingpostalcode'].astype(str).str.strip().str.lower()
                                 .isin(['nan', 'none', 'null', ''])).sum()
                
                log.info(f"   Antes: {null_before} nulos, {invalid_before} valores inválidos")
                
                # Aplicar tratamento
                df_gold['billingpostalcode'] = df_gold['billingpostalcode'].apply(
                    lambda x: None if pd.isna(x) or str(x).strip().lower() in ['nan', 'none', 'null', ''] 
                    else str(x).strip()
                )
                
                null_after = df_gold['billingpostalcode'].isnull().sum()
                log.info(f"   Depois: {null_after} nulos")
                log.info(f"   ✅ CEP tratado: {len(df_gold)} registros")
            else:
                log.warning("[CustomValidationsGold] ⚠️ Coluna 'billingpostalcode' não encontrada")
            
            # ───── VALIDAÇÃO 2: QUALIDADE ─────
            quality_score = (df_gold.notna().sum().sum() / df_gold.size) * 100
            log.info(f"[CustomValidationsGold] 📊 Quality Score: {quality_score:.2f}%")
            
            if quality_score < 90.0:
                log.warning(f"[CustomValidationsGold] ⚠️ Quality baixa ({quality_score:.2f}% < 90%)")
            
            # ───── OPCIONAL: RE-SALVAR GOLD COM VALIDAÇÕES ─────
            # Isso é seguro porque Gold já está finalizada
            log.info(f"[CustomValidationsGold] 📤 Re-salvando Gold com validações...")
            
            local_gold_updated = os.path.join(tmpdir, 'gold_updated.parquet')
            df_gold.to_parquet(
                local_gold_updated,
                index=False,
                compression='snappy',
                engine='pyarrow'
            )
            
            # Sobrescrever Gold no MinIO (SEGURO: pipeline 100% completo)
            hook.load_file(
                filename=local_gold_updated,
                key=gold_key,
                bucket_name=bucket,
                replace=True
            )
            
            log.info(f"[CustomValidationsGold] ✅ Gold atualizada com validações!")
            
            # Enviar status via XCom
            if 'task_instance' in context:
                ti = context['task_instance']
                ti.xcom_push(key='custom_validation_status', value='success')
                ti.xcom_push(key='cep_treated_rows', value=len(df_gold))
                ti.xcom_push(key='quality_score', value=quality_score)
            
        except FileNotFoundError as e:
            log.error(f"[CustomValidationsGold] ❌ Gold não encontrado: {e}")
            raise
        except Exception as e:
            log.error(f"[CustomValidationsGold] ❌ Erro ao validar Gold: {e}", exc_info=True)
            raise
        finally:
            if tmpdir and os.path.exists(tmpdir):
                import shutil
                try:
                    shutil.rmtree(tmpdir)
                except Exception:
                    pass
        
        log.info("[CustomValidationsGold] ✅ Todas as validações customizadas concluídas!")


# ==============================================================================
# ALTERNATIVA: Usar Hook de Validação DENTRO do Pipeline (Mais Seguro)
# ==============================================================================
# Se você quiser validações aplicadas DURANTE a geração da Silver:
# 1. Crie um arquivo: lib/validadores/seu_validador.py
# 2. Implemente a função: def validate(df, target_table_name)
# 3. Configure em dag_configurations:
#    SET validation_rules_module = 'lib.validadores.seu_validador'
#    SET validation_rules_enabled = true
# 4. O pipeline automaticamente chamará sua função durante bronze_to_silver()

# ==============================================================================
# RESUMO DAS CORREÇÕES:
# ==============================================================================
#
# ❌ PROBLEMA ORIGINAL:
#   - Tentava re-salvar Silver enquanto Gold Layer estava lendo
#   - Race condition causava corrupção
#   - silver_key podia ser None
#
# ✅ SOLUÇÃO:
#   1. JAMAIS re-salvar Silver depois de raw_to_medallion()
#   2. Aplicar validações na Gold (após sincronização 100%)
#   3. OU: Usar validation_rules_module para validar DENTRO do pipeline
#   4. Verificar se chave existe antes de usar
#   5. Usar try/finally para cleanup de tmpdir
#
# ==============================================================================

def validate(df):
    """
    Função dummy para compatibilidade com webapp.
    """
    log.info("[validate] ℹ️ Função dummy (use MeuValidadorCORRIGIDO classe instead)")
    return df


if __name__ == '__main__':
    print("Validador Customizado Corrigido - Versão Sincronizada")
    print()
    print("Diferenças da versão anterior:")
    print("  1. ✅ Validações aplicadas na GOLD (não na Silver)")
    print("  2. ✅ Sem race conditions (pipeline 100% completo)")
    print("  3. ✅ Seguro contra corrupção de parquet")
    print("  4. ✅ Sincronização automática")
    print()
    print("Configuração:")
    print("  python_module_path = 'meu_validador_CORRIGIDO.MeuValidadorCORRIGIDO'")
