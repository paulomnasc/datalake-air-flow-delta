"""
EXEMPLO PRÁTICO DE VALIDADOR CUSTOMIZADO

Este arquivo mostra como criar um validador que herda de raw_to_medallion.
Copie este código para seu repositório Git e customize conforme necessário.

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

log = logging.getLogger(__name__)


class ValidadorVendas(raw_to_medallion):
    """
    Validador customizado para dados de vendas.
    
    Estende o pipeline Medallion padrão com:
    - Validações de regras de negócio
    - Mascaramento de dados sensíveis
    - Cálculos de qualidade
    """
    
    def validate_bronze(self, df_bronze, **context):
        """
        Validações na camada Bronze (dados brutos convertidos em Parquet).
        
        Nesta fase, dados ainda não foram limpos/transformados.
        Use para validar estrutura básica e detectar problemas na fonte.
        """
        log.info("[ValidadorVendas] Validando Bronze...")
        
        # Chamar validação padrão da classe base
        df_bronze = super().validate_bronze(df_bronze, **context)
        
        # Validação 1: Colunas obrigatórias
        required_cols = ['id_venda', 'data_venda', 'valor', 'quantidade']
        missing_cols = [c for c in required_cols if c not in df_bronze.columns]
        
        if missing_cols:
            log.error(f"❌ Bronze: Colunas obrigatórias faltando: {missing_cols}")
            raise ValueError(f"Colunas faltando em Bronze: {missing_cols}")
        
        log.info(f"✅ Bronze: {len(df_bronze)} registros, {len(df_bronze.columns)} colunas")
        
        # Informar via XCom para próximas tasks
        if 'task_instance' in context:
            context['task_instance'].xcom_push(
                key='bronze_row_count', 
                value=len(df_bronze)
            )
        
        return df_bronze
    
    def validate_silver(self, df_silver, **context):
        """
        Validações na camada Silver (dados limpos e normalizados).
        
        Nesta fase, dados já foram transformados.
        Use para validar regras de qualidade e conformidade.
        """
        log.info("[ValidadorVendas] Validando Silver...")
        
        # Chamar validação padrão da classe base
        df_silver = super().validate_silver(df_silver, **context)
        
        # Validação 1: Valores negativos
        if 'valor' in df_silver.columns:
            negative_values = df_silver[df_silver['valor'] < 0]
            if len(negative_values) > 0:
                log.warning(f"⚠️ Silver: {len(negative_values)} valores negativos encontrados")
                df_silver = df_silver[df_silver['valor'] >= 0]
        
        # Validação 2: Datas não podem ser futuras
        if 'data_venda' in df_silver.columns:
            df_silver['data_venda'] = pd.to_datetime(df_silver['data_venda'])
            today = datetime.now().date()
            future_dates = df_silver[df_silver['data_venda'].dt.date > today]
            
            if len(future_dates) > 0:
                log.warning(f"⚠️ Silver: {len(future_dates)} datas futuras (removendo)")
                df_silver = df_silver[df_silver['data_venda'].dt.date <= today]
        
        # Validação 3: Quantidade sempre positiva e realista
        if 'quantidade' in df_silver.columns:
            invalid_qty = df_silver[
                (df_silver['quantidade'] <= 0) | 
                (df_silver['quantidade'] > 10000)
            ]
            if len(invalid_qty) > 0:
                log.warning(f"⚠️ Silver: {len(invalid_qty)} quantidades fora do intervalo [1, 10000]")
                df_silver = df_silver[
                    (df_silver['quantidade'] > 0) & 
                    (df_silver['quantidade'] <= 10000)
                ]
        
        # Validação 4: Qualidade de dados
        quality_score = (df_silver.notna().sum().sum() / df_silver.size) * 100
        log.info(f"✅ Silver: Quality Score = {quality_score:.2f}%")
        
        if quality_score < 90.0:
            log.warning(f"⚠️ Silver: Quality baixa ({quality_score:.2f}% < 90%)")
        
        # Informar via XCom
        if 'task_instance' in context:
            ti = context['task_instance']
            ti.xcom_push(key='silver_row_count', value=len(df_silver))
            ti.xcom_push(key='silver_quality_score', value=quality_score)
        
        log.info(f"✅ Silver: {len(df_silver)} registros após validação")
        
        return df_silver
    
    def validate_gold(self, df_gold, **context):
        """
        Validações na camada Gold (dados prontos para análise).
        
        Nesta fase, dados já foram agregados/otimizados.
        Use para validações finais antes de expor para BI/Analytics.
        """
        log.info("[ValidadorVendas] Validando Gold...")
        
        # Chamar validação padrão da classe base
        df_gold = super().validate_gold(df_gold, **context)
        
        # Validação 1: Não deve conter dados pessoais
        sensitive_cols = ['email', 'telefone', 'cpf', 'endereco']
        found_sensitive = [c for c in sensitive_cols if c in df_gold.columns]
        
        if found_sensitive:
            log.error(f"❌ Gold: Colunas sensíveis encontradas (violação LGPD): {found_sensitive}")
            raise Exception(f"Dados pessoais não devem estar em Gold: {found_sensitive}")
        
        # Validação 2: Total de vendas faz sentido?
        if 'valor' in df_gold.columns:
            total_vendas = df_gold['valor'].sum()
            media_venda = df_gold['valor'].mean()
            
            log.info(f"💰 Gold: Total de vendas = R$ {total_vendas:,.2f}")
            log.info(f"💰 Gold: Média por venda = R$ {media_venda:,.2f}")
            
            # Sanidade: total muito alto?
            if total_vendas > 1_000_000_000:  # > R$ 1 bilhão
                log.warning(f"⚠️ Gold: Total de vendas muito alto (R$ {total_vendas:,.2f})")
        
        # Validação 3: Número de registros
        num_records = len(df_gold)
        if num_records == 0:
            log.error("❌ Gold: Nenhum registro após processamento!")
            raise Exception("Gold layer resultou em 0 registros")
        
        log.info(f"✅ Gold: {num_records} registros, pronto para análise")
        
        # Informar via XCom
        if 'task_instance' in context:
            ti = context['task_instance']
            ti.xcom_push(key='gold_row_count', value=num_records)
            ti.xcom_push(key='gold_total_sales', value=float(df_gold['valor'].sum()) if 'valor' in df_gold.columns else 0)
        
        return df_gold


class ValidadorLGPD(raw_to_medallion):
    """
    Validador especializado em conformidade LGPD.
    
    Garante que dados pessoais sejam mascarados em cada camada.
    """
    
    SENSITIVE_FIELDS = ['cpf', 'rg', 'email', 'telefone', 'endereco', 'data_nascimento']
    
    def mask_field(self, df, field):
        """Mascara um campo de dados sensível."""
        if field not in df.columns:
            return df
        
        def _mask(value):
            if pd.isna(value):
                return None
            s = str(value)
            if len(s) <= 2:
                return '****'
            return s[:2] + '*' * (len(s) - 4) + s[-2:]
        
        df[field] = df[field].apply(_mask)
        return df
    
    def validate_silver(self, df_silver, **context):
        """Mascarar dados sensíveis em Silver."""
        log.info("[ValidadorLGPD] Maskando dados sensíveis em Silver...")
        
        df_silver = super().validate_silver(df_silver, **context)
        
        # Mascarar cada campo sensível
        for field in self.SENSITIVE_FIELDS:
            if field in df_silver.columns:
                df_silver = self.mask_field(df_silver, field)
                log.info(f"✅ LGPD: Campo '{field}' mascarado")
        
        return df_silver
    
    def validate_gold(self, df_gold, **context):
        """Garantir que Gold não tem dados sensíveis."""
        log.info("[ValidadorLGPD] Validando conformidade LGPD em Gold...")
        
        df_gold = super().validate_gold(df_gold, **context)
        
        # Verificar se ainda existe algum campo sensível
        found = [c for c in self.SENSITIVE_FIELDS if c in df_gold.columns]
        
        if found:
            log.error(f"❌ LGPD VIOLATION: Dados sensíveis em Gold: {found}")
            raise Exception(f"LGPD: Dados pessoais não devem estar em Gold: {found}")
        
        log.info("✅ LGPD: Gold está conforme (sem dados sensíveis)")
        return df_gold


class ValidadorComQualityScore(raw_to_medallion):
    """
    Validador que calcula quality score detalhado por coluna.
    
    Útil para monitorar qualidade de dados por campo.
    """
    
    def validate_silver(self, df_silver, **context):
        """Calcular quality score por coluna."""
        log.info("[ValidadorQuality] Calculando quality score...")
        
        df_silver = super().validate_silver(df_silver, **context)
        
        # Calcular qualidade por coluna
        quality_report = {}
        for col in df_silver.columns:
            filled = df_silver[col].notna().sum()
            quality_report[col] = {
                'total': len(df_silver),
                'filled': int(filled),
                'null_count': int(len(df_silver) - filled),
                'quality_pct': round((filled / len(df_silver)) * 100, 2)
            }
        
        # Logar colunas com qualidade baixa
        poor_quality = {c: q for c, q in quality_report.items() if q['quality_pct'] < 90}
        
        if poor_quality:
            log.warning(f"⚠️ Colunas com qualidade < 90%:")
            for col, metrics in poor_quality.items():
                log.warning(f"   - {col}: {metrics['quality_pct']}% preenchido")
        
        # Salvar relatório em XCom
        if 'task_instance' in context:
            context['task_instance'].xcom_push(
                key='quality_report',
                value=quality_report
            )
        
        # Média geral
        avg_quality = sum(q['quality_pct'] for q in quality_report.values()) / len(quality_report)
        log.info(f"✅ Quality Score Médio: {avg_quality:.2f}%")
        
        return df_silver


# ==============================================================================
# COMO USAR ESTE ARQUIVO
# ==============================================================================
# 
# 1. Salve como: seu_validador.py
#    Coloque em: seu repositório GitHub
# 
# 2. Faça commit & push:
#    git add seu_validador.py
#    git commit -m "Add custom validator"
#    git push
# 
# 3. Configure em dag_configurations (MySQL):
#    UPDATE dag_configurations 
#    SET python_module_path = 'seu_validador.ValidadorVendas'
#    WHERE dag_id = 'sua_dag_id';
# 
# 4. Execute sua DAG normalmente:
#    - Factory detectará python_module_path
#    - Carregará ValidadorVendas
#    - Chamará validate_bronze(), validate_silver(), validate_gold()
# 
# 5. Monitore via Airflow:
#    - Logs mostram [ValidadorVendas] prefixo
#    - XCom contém métricas (bronze_row_count, silver_quality_score, etc)
#    - Erros em validação causam task failure
# ==============================================================================

if __name__ == '__main__':
    # Para testar localmente (opcional)
    print("Este é um arquivo de validador customizado.")
    print("Use com a interface web ou execute via factory_master.py")
    print()
    print("Exemplo de uso:")
    print("  UPDATE dag_configurations")
    print("  SET python_module_path = 'seu_validador.ValidadorVendas'")
    print("  WHERE dag_id = 'sua_dag_id';")
