import logging
import pandas as pd
from typing import Dict, List, Tuple

log = logging.getLogger(__name__)

class DataQualityValidator:
    """
    Valida qualidade de dados e gera métricas por linha.
    
    Adiciona colunas de auditoria:
    - DataQualityRulesPass: Quantidade de regras que passaram
    - DataQualityRulesFail: Quantidade de regras que falharam
    - DataQualityRulesSkip: Quantidade de regras puladas
    - DataQualityEvaluationResult: "Passed" ou "Failed"
    """
    
    def __init__(self, df: pd.DataFrame, table_name: str):
        self.df = df.copy()
        self.table_name = table_name
        self.rules_results = []
        
    def add_quality_columns(self) -> pd.DataFrame:
        """
        Adiciona 4 colunas de qualidade ao DataFrame.
        
        Returns:
            DataFrame com colunas de qualidade adicionadas
        """
        log.info(f"[QUALITY] Iniciando validação de qualidade para: {self.table_name}")
        
        # Inicializar colunas de contagem
        self.df['DataQualityRulesPass'] = 0
        self.df['DataQualityRulesFail'] = 0
        self.df['DataQualityRulesSkip'] = 0
        self.df['DataQualityEvaluationResult'] = 'Passed'
        
        # Executar regras de qualidade
        self._check_null_values()
        self._check_data_types()
        self._check_duplicates()
        self._check_numeric_ranges()
        self._check_string_patterns()
        
        # Calcular resultado final
        self.df['DataQualityEvaluationResult'] = self.df.apply(
            lambda row: 'Failed' if row['DataQualityRulesFail'] > 0 else 'Passed',
            axis=1
        )
        
        # Estatísticas gerais
        total_rows = len(self.df)
        passed_rows = (self.df['DataQualityEvaluationResult'] == 'Passed').sum()
        failed_rows = total_rows - passed_rows
        
        log.info(f"[QUALITY] ✓ Validação concluída:")
        log.info(f"[QUALITY]   - Total de linhas: {total_rows}")
        log.info(f"[QUALITY]   - Linhas aprovadas: {passed_rows} ({passed_rows/total_rows*100:.1f}%)")
        log.info(f"[QUALITY]   - Linhas reprovadas: {failed_rows} ({failed_rows/total_rows*100:.1f}%)")
        
        return self.df
    
    def _check_null_values(self):
        """Valida campos obrigatórios não podem ser nulos."""
        log.info("[QUALITY] Regra 1: Verificando valores nulos...")
        
        # Identifica colunas críticas (que não deveriam ser nulas)
        critical_cols = self._identify_critical_columns()
        
        if not critical_cols:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma coluna crítica identificada (pulada)")
            return
        
        for col in critical_cols:
            if col in self.df.columns:
                # Linha passa se campo não é nulo
                mask_pass = self.df[col].notna()
                self.df.loc[mask_pass, 'DataQualityRulesPass'] += 1
                
                # Linha falha se campo é nulo
                mask_fail = self.df[col].isna()
                self.df.loc[mask_fail, 'DataQualityRulesFail'] += 1
        
        log.info(f"[QUALITY]   ✓ Verificadas {len(critical_cols)} colunas críticas")
    
    def _check_data_types(self):
        """Valida se tipos de dados são consistentes."""
        log.info("[QUALITY] Regra 2: Verificando tipos de dados...")
        
        # Para colunas numéricas, verifica se valores são válidos
        numeric_cols = self.df.select_dtypes(include=['number']).columns
        
        if len(numeric_cols) == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma coluna numérica (pulada)")
            return
        
        for col in numeric_cols:
            # Passa se não é infinito ou NaN
            mask_pass = ~(self.df[col].isin([float('inf'), float('-inf')]) | self.df[col].isna())
            self.df.loc[mask_pass, 'DataQualityRulesPass'] += 1
            
            # Falha se é infinito
            mask_fail = self.df[col].isin([float('inf'), float('-inf')])
            self.df.loc[mask_fail, 'DataQualityRulesFail'] += 1
        
        log.info(f"[QUALITY]   ✓ Verificadas {len(numeric_cols)} colunas numéricas")
    
    def _check_duplicates(self):
        """Identifica linhas duplicadas."""
        log.info("[QUALITY] Regra 3: Verificando duplicatas...")
        
        # Identifica duplicatas (excluindo colunas de qualidade)
        cols_to_check = [col for col in self.df.columns 
                        if not col.startswith('DataQuality')]
        
        if len(cols_to_check) == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma coluna para verificar (pulada)")
            return
        
        # Marca duplicatas
        is_duplicate = self.df[cols_to_check].duplicated(keep=False)
        
        # Passa se não é duplicata
        self.df.loc[~is_duplicate, 'DataQualityRulesPass'] += 1
        
        # Falha se é duplicata
        self.df.loc[is_duplicate, 'DataQualityRulesFail'] += 1
        
        dup_count = is_duplicate.sum()
        log.info(f"[QUALITY]   {'✓' if dup_count == 0 else '⚠'} {dup_count} duplicatas encontradas")
    
    def _check_numeric_ranges(self):
        """Valida se valores numéricos estão em ranges aceitáveis."""
        log.info("[QUALITY] Regra 4: Verificando ranges numéricos...")
        
        numeric_cols = self.df.select_dtypes(include=['number']).columns
        numeric_cols = [col for col in numeric_cols if not col.startswith('DataQuality')]
        
        if len(numeric_cols) == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma coluna numérica (pulada)")
            return
        
        rules_applied = 0
        for col in numeric_cols:
            # Detecta outliers extremos (além de 3 desvios padrão)
            if self.df[col].std() > 0:
                mean = self.df[col].mean()
                std = self.df[col].std()
                lower_bound = mean - (3 * std)
                upper_bound = mean + (3 * std)
                
                # Passa se está dentro do range
                mask_pass = (self.df[col] >= lower_bound) & (self.df[col] <= upper_bound)
                self.df.loc[mask_pass, 'DataQualityRulesPass'] += 1
                
                # Falha se está fora do range
                mask_fail = (self.df[col] < lower_bound) | (self.df[col] > upper_bound)
                self.df.loc[mask_fail, 'DataQualityRulesFail'] += 1
                
                rules_applied += 1
        
        if rules_applied == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma regra aplicável (pulada)")
        else:
            log.info(f"[QUALITY]   ✓ Verificadas {rules_applied} colunas numéricas")
    
    def _check_string_patterns(self):
        """Valida padrões de strings (emails, telefones, etc)."""
        log.info("[QUALITY] Regra 5: Verificando padrões de string...")
        
        string_cols = self.df.select_dtypes(include=['object']).columns
        string_cols = [col for col in string_cols if not col.startswith('DataQuality')]
        
        if len(string_cols) == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma coluna de texto (pulada)")
            return
        
        rules_applied = 0
        for col in string_cols:
            col_lower = col.lower()
            
            # Validação de email
            if 'email' in col_lower or 'mail' in col_lower:
                mask_valid = self.df[col].str.contains(r'^[\w\.-]+@[\w\.-]+\.\w+$', 
                                                       regex=True, na=False)
                self.df.loc[mask_valid, 'DataQualityRulesPass'] += 1
                self.df.loc[~mask_valid & self.df[col].notna(), 'DataQualityRulesFail'] += 1
                rules_applied += 1
            
            # Validação de telefone (básica)
            elif 'phone' in col_lower or 'tel' in col_lower or 'fone' in col_lower:
                mask_valid = self.df[col].str.contains(r'\d{8,}', regex=True, na=False)
                self.df.loc[mask_valid, 'DataQualityRulesPass'] += 1
                self.df.loc[~mask_valid & self.df[col].notna(), 'DataQualityRulesFail'] += 1
                rules_applied += 1
        
        if rules_applied == 0:
            self.df['DataQualityRulesSkip'] += 1
            log.info("[QUALITY]   ⊘ Nenhuma regra aplicável (pulada)")
        else:
            log.info(f"[QUALITY]   ✓ Verificadas {rules_applied} colunas de texto")
    
    def _identify_critical_columns(self) -> List[str]:
        """
        Identifica colunas críticas que não devem ser nulas.
        
        Critérios:
        - Colunas com 'id' no nome
        - Colunas com 'key' no nome
        - Primeira coluna (geralmente PK)
        """
        critical = []
        
        for col in self.df.columns:
            col_lower = col.lower()
            if any(keyword in col_lower for keyword in ['id', 'key', 'code', 'number']):
                critical.append(col)
        
        # Se não encontrou, usa primeira coluna
        if not critical and len(self.df.columns) > 0:
            critical.append(self.df.columns[0])
        
        return critical
    
    def get_summary_metrics(self) -> Dict:
        """
        Retorna métricas agregadas de qualidade.
        
        Returns:
            Dicionário com estatísticas de qualidade
        """
        total_rows = len(self.df)
        
        metrics = {
            'table_name': self.table_name,
            'total_rows': total_rows,
            'rows_passed': (self.df['DataQualityEvaluationResult'] == 'Passed').sum(),
            'rows_failed': (self.df['DataQualityEvaluationResult'] == 'Failed').sum(),
            'pass_rate': (self.df['DataQualityEvaluationResult'] == 'Passed').sum() / total_rows * 100,
            'avg_rules_pass': self.df['DataQualityRulesPass'].mean(),
            'avg_rules_fail': self.df['DataQualityRulesFail'].mean(),
            'avg_rules_skip': self.df['DataQualityRulesSkip'].mean(),
            'total_rules_executed': (
                self.df['DataQualityRulesPass'].sum() + 
                self.df['DataQualityRulesFail'].sum()
            )
        }
        
        return metrics


def validate_dataframe(df: pd.DataFrame, table_name: str) -> Tuple[pd.DataFrame, Dict]:
    """
    Função helper para validar DataFrame e adicionar colunas de qualidade.
    
    Args:
        df: DataFrame a ser validado
        table_name: Nome da tabela para logs
        
    Returns:
        Tuple com (DataFrame validado, métricas de qualidade)
    """
    validator = DataQualityValidator(df, table_name)
    df_with_quality = validator.add_quality_columns()
    metrics = validator.get_summary_metrics()
    
    return df_with_quality, metrics
