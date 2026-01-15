"""
EXEMPLO CORRETO DE VALIDADOR CUSTOMIZADO COM PIPELINE COMPLETO

Este é o padrão CORRETO para criar um validador que:
1. Executa o pipeline Medallion COMPLETO (Bronze → Silver → Gold)
2. MAIS aplica validações/transformações customizadas

A classe MeuValidador é um WRAPPER (invólucro) que:
- Chama raw_to_medallion() para executar todo o pipeline padrão
- Depois aplica suas customizações específicas

Uso:
    1. Salve como: meu_validador.py
    2. Configure em dag_configurations:
       UPDATE dag_configurations 
       SET python_module_path = 'meu_validador.MeuValidador'
       WHERE dag_id = 'sua_dag_id';
    3. Execute DAG normalmente
"""

from lib.medallion_pipeline import raw_to_medallion
import pandas as pd
import logging
from airflow.providers.amazon.aws.hooks.s3 import S3Hook

log = logging.getLogger(__name__)


class MeuValidador:
    """
    Wrapper que executa pipeline Medallion padrão MAIS validações customizadas.
    
    Fluxo:
    1. raw_to_medallion() → Bronze → Silver → Gold (pipeline padrão)
    2. custom_validations() → Validações customizadas (suas regras)
    
    Resultado: dados processados + validados + com regras de negócio aplicadas
    """
    
    def __call__(self, source_filename: str, target_table_name: str, **context):
        """
        Entry point chamado pelo Airflow/Factory.
        
        Executará SEMPRE o pipeline completo, depois suas customizações.
        """
        log.info(f"\n{'='*80}")
        log.info(f"[MeuValidador] 🚀 Iniciando pipeline para {target_table_name}")
        log.info(f"[MeuValidador] Arquivo: {source_filename}")
        log.info(f"{'='*80}\n")
        
        try:
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 1: EXECUTAR PIPELINE MEDALLION COMPLETO (PADRÃO)
            # ═══════════════════════════════════════════════════════════════════
            # Isso faz Bronze → Silver → Gold com toda a lógica padrão
            log.info("[MeuValidador] 📦 ETAPA 1: Executando pipeline padrão...")
            
            pipeline_result = raw_to_medallion(
                source_filename=source_filename,
                target_table_name=target_table_name,
                **context
            )
            
            log.info("[MeuValidador] ✅ Pipeline padrão concluído!")
            log.info(f"   Bronze: {pipeline_result.get('bronze')}")
            log.info(f"   Silver: {pipeline_result.get('silver')}")
            log.info(f"   Gold: {pipeline_result.get('gold')}")
            
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 2: APLICAR VALIDAÇÕES/TRANSFORMAÇÕES CUSTOMIZADAS
            # ═══════════════════════════════════════════════════════════════════
            log.info("\n[MeuValidador] 🔧 ETAPA 2: Aplicando validações customizadas...")
            
            self.custom_validations(
                pipeline_result=pipeline_result,
                target_table_name=target_table_name,
                **context
            )
            
            log.info("[MeuValidador] ✅ Validações customizadas concluídas!")
            
            # ═══════════════════════════════════════════════════════════════════
            # ETAPA 3: RETORNAR RESULTADO
            # ═══════════════════════════════════════════════════════════════════
            log.info(f"\n{'='*80}")
            log.info("[MeuValidador] ✅ PIPELINE COMPLETO FINALIZADO COM SUCESSO!")
            log.info(f"{'='*80}\n")
            
            return pipeline_result
            
        except Exception as e:
            log.error(f"[MeuValidador] ❌ ERRO: {e}", exc_info=True)
            raise
    
    
    def custom_validations(self, pipeline_result: dict, target_table_name: str, **context):
        """
        Aplicar validações/transformações customizadas APÓS o pipeline padrão.
        
        Args:
            pipeline_result: Dict com chaves 'bronze', 'silver', 'gold' (caminhos S3)
            target_table_name: Nome da tabela
            context: Contexto do Airflow (contém bucket_name, owner, etc)
        """
        
        bucket = context.get('bucket_name', 'lab01')
        hook = S3Hook(aws_conn_id='minio_conn')
        
        log.info(f"[CustomValidations] Validando dados em s3://{bucket}/")
        
        # ───────────────────────────────────────────────────────────────────
        # CUSTOMIZAÇÃO 1: VALIDAR E TRATAR CEP NA SILVER
        # ───────────────────────────────────────────────────────────────────
        
        silver_key = pipeline_result.get('silver')
        if not silver_key:
            log.warning("[CustomValidations] ⚠️  Silver key não encontrada, pulando validação de CEP")
            return
        
        log.info(f"[CustomValidations] 📥 Baixando Silver: {silver_key}")
        
        try:
            # Baixar arquivo Silver do MinIO
            local_file = hook.download_file(
                key=silver_key,
                bucket_name=bucket,
                preserve_file_name=True
            )
            
            # Carregar como DataFrame
            df_silver = pd.read_parquet(local_file)
            log.info(f"[CustomValidations] ✅ Silver carregado: {len(df_silver)} registros")
            
            # ───── VALIDAÇÃO DE CEP ─────
            if 'billingpostalcode' in df_silver.columns:
                log.info("[CustomValidations] 🔍 Validando coluna 'billingpostalcode'...")
                
                # Contar valores antes
                null_before = df_silver['billingpostalcode'].isnull().sum()
                invalid_before = (df_silver['billingpostalcode'].astype(str).str.strip().str.lower()
                                 .isin(['nan', 'none', 'null', ''])).sum()
                
                log.info(f"   Antes: {null_before} nulos, {invalid_before} valores 'nan/none/null'")
                
                # Aplicar tratamento
                df_silver['billingpostalcode'] = df_silver['billingpostalcode'].apply(
                    lambda x: 'None' if pd.isna(x) or str(x).strip().lower() in ['nan', 'none', 'null', ''] 
                    else str(x).strip()
                )
                
                # Contar valores depois
                null_after = df_silver['billingpostalcode'].isnull().sum()
                log.info(f"   Depois: {null_after} nulos")
                log.info(f"   ✅ CEP tratado: {len(df_silver)} registros processados")
            else:
                log.warning("[CustomValidations] ⚠️  Coluna 'billingpostalcode' não encontrada")
            
            # ───── VALIDAÇÃO DE QUALIDADE ─────
            quality_score = (df_silver.notna().sum().sum() / df_silver.size) * 100
            log.info(f"[CustomValidations] 📊 Quality Score: {quality_score:.2f}%")
            
            if quality_score < 90.0:
                log.warning(f"[CustomValidations] ⚠️  Quality baixa ({quality_score:.2f}% < 90%)")
            
            # ───── RE-SALVAR SILVER COM VALIDAÇÕES APLICADAS ─────
            log.info(f"[CustomValidations] 📤 Re-salvando Silver com validações...")
            
            local_parquet = '/tmp/silver_validated.parquet'
            df_silver.to_parquet(
                local_parquet,
                index=False,
                compression='snappy',
                engine='pyarrow'
            )
            
            # Enviar de volta para MinIO (replace=True sobrescreve)
            hook.load_file(
                filename=local_parquet,
                key=silver_key,
                bucket_name=bucket,
                replace=True
            )
            
            log.info(f"[CustomValidations] ✅ Silver atualizado com validações!")
            
            # Informar via XCom para próximas tasks
            if 'task_instance' in context:
                ti = context['task_instance']
                ti.xcom_push(key='custom_validation_status', value='success')
                ti.xcom_push(key='cep_treated_rows', value=len(df_silver))
                ti.xcom_push(key='quality_score', value=quality_score)
            
        except FileNotFoundError:
            log.error(f"[CustomValidations] ❌ Silver não encontrado em {silver_key}")
            raise
        except Exception as e:
            log.error(f"[CustomValidations] ❌ Erro ao validar Silver: {e}", exc_info=True)
            raise
        
        
        # ───────────────────────────────────────────────────────────────────
        # CUSTOMIZAÇÃO 2: VALIDAÇÕES ADICIONAIS (EXEMPLO)
        # ───────────────────────────────────────────────────────────────────
        # Você pode adicionar mais validações aqui...
        # Por exemplo: validar Gold, aplicar mascaramento, etc
        
        log.info("[CustomValidations] ✅ Todas as validações customizadas concluídas!")


# ==============================================================================
# EXEMPLO DE USO
# ==============================================================================
#
# 1. Salve este arquivo: meu_validador.py
#    Coloque em: seu repositório GitHub ou /opt/airflow/dags/
#
# 2. Configure em dag_configurations (MySQL):
#    UPDATE dag_configurations 
#    SET python_module_path = 'meu_validador.MeuValidador'
#    WHERE dag_id = 'sua_dag_id';
#
# 3. Execute sua DAG:
#    airflow dags trigger sua_dag_id
#
# 4. Monitore nos logs do Airflow:
#    [MeuValidador] 🚀 Iniciando pipeline para seu_table_name
#    [MeuValidador] 📦 ETAPA 1: Executando pipeline padrão...
#    [MeuValidador] ✅ Pipeline padrão concluído!
#    [MeuValidador] 🔧 ETAPA 2: Aplicando validações customizadas...
#    [CustomValidations] 🔍 Validando coluna 'billingpostalcode'...
#    [CustomValidations] ✅ Silver atualizado com validações!
#    [MeuValidador] ✅ PIPELINE COMPLETO FINALIZADO COM SUCESSO!
#
# ==============================================================================

# ─────────────────────────────────────────────────────────────────────────────
# FUNÇÃO PARA COMPATIBILIDADE COM WEBAPP
# ─────────────────────────────────────────────────────────────────────────────
# A interface web (validation-rules-editor.php) procura por uma função validate()
# Esta função é apenas para passar na validação da interface.
# A execução real usa MeuValidador classe.

def validate(df):
    """
    Função dummy para compatibilidade com webapp.
    
    O webapp testa se arquivo tem 'def validate(df)' antes de salvar.
    Esta função existe apenas para passar nessa validação.
    
    Execução real:
    - Use class MeuValidador para factory_master.py
    - Configure python_module_path = 'arquivo.MeuValidador'
    """
    log.info("[validate] ℹ️  Função dummy chamada (use MeuValidador classe instead)")
    return df


if __name__ == '__main__':
    print("Este é um arquivo de validador customizado.")
    print("Use com a factory_master.py ou via interface web.")
    print()
    print("Padrão correto:")
    print("  class MeuValidador:")
    print("      def __call__(self, source_filename, target_table_name, **context):")
    print("          # 1. Chamar raw_to_medallion() para pipeline completo")
    print("          # 2. Aplicar suas customizações")
    print("          # 3. Retornar resultado")

