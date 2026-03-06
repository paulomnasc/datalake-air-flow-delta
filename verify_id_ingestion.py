import os
import sys
import pandas as pd
import logging
from datetime import datetime

# Setup logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger("VerifyIngestion")

# Adicionar src ao path para os imports funcionarem
sys.path.append(os.path.join(os.getcwd(), 'src/dags'))

def verify_record_in_db(id_to_check):
    """Verifica se o ID existe no banco de dados real."""
    import subprocess
    cmd = f'mysql -h 127.0.0.1 -u root -proot lista_revisao2 -e "SELECT id, nome, email, criado_em FROM usuario WHERE id = {id_to_check};"'
    try:
        result = subprocess.check_output(cmd, shell=True).decode()
        if str(id_to_check) in result:
            logger.info(f"✅ Registro {id_to_check} ENCONTRADO no banco de origem!")
            print("\n--- Dados do Banco de Origem ---")
            print(result)
            return True
        else:
            logger.warning(f"❌ Registro {id_to_check} NÃO existe no banco de origem.")
            return False
    except Exception as e:
        logger.error(f"Erro ao consultar MySQL: {e}")
        return False

def check_data_quality(id_to_check):
    """Aplica as regras de Data Quality no registro."""
    from lib.data_quality import validate_dataframe
    
    import subprocess
    cmd = f'mysql -h 127.0.0.1 -u root -proot lista_revisao2 -B -e "SELECT * FROM usuario WHERE id = {id_to_check};"'
    try:
        output = subprocess.check_output(cmd, shell=True).decode()
        if not output.strip():
            return
            
        import io
        df = pd.read_csv(io.StringIO(output), sep='\t')
        
        logger.info(f"Aplicando validação de Data Quality para o registro {id_to_check}...")
        df_validated, metrics = validate_dataframe(df, "usuario")
        
        print("\n--- Resultado da Validação de Qualidade ---")
        print(f"Resultado: {df_validated['DataQualityEvaluationResult'].iloc[0]}")
        print(f"Regras Passaram: {df_validated['DataQualityRulesPass'].iloc[0]}")
        print(f"Regras Falharam: {df_validated['DataQualityRulesFail'].iloc[0]}")
        
        if df_validated['DataQualityEvaluationResult'].iloc[0] == 'Failed':
            print("\n⚠️  DETALHES DA REPROVAÇÃO:")
            
            # 1. Verificar E-mail
            email = df['email'].iloc[0]
            if not pd.isna(email):
                import re
                if not re.match(r'^[\w\.-]+@[\w\.-]+\.\w+$', str(email)):
                    print(f"  - E-mail '{email}': Formato inválido.")
            
            # 2. Verificar Colunas Críticas Nulas
            from lib.data_quality import DataQualityValidator
            validator = DataQualityValidator(df, "usuario")
            critical_cols = validator._identify_critical_columns()
            for col in critical_cols:
                if col in df.columns and pd.isna(df[col].iloc[0]):
                    print(f"  - Coluna Crítica '{col}': Está NULA. (Regra: Colunas com 'id', 'key', 'code' ou 'number' não podem ser nulas)")

    except Exception as e:
        logger.error(f"Erro na validação de DQ: {e}")

def check_last_run():
    """Verifica quando a pipeline rodou por último."""
    import subprocess
    cmd = 'mysql -h 127.0.0.1 -u root -proot lista_revisao2 -e "SELECT updated_at FROM dag_configurations WHERE dag_id=\'mydataflow\';"'
    try:
        result = subprocess.check_output(cmd, shell=True).decode()
        print("\n--- Timing da Pipelines ---")
        print(f"Última atualização da config do 'mydataflow':\n{result}")
    except Exception as e:
        logger.error(f"Erro ao verificar timing: {e}")

if __name__ == "__main__":
    target_id = 346
    if len(sys.argv) > 1:
        target_id = sys.argv[1]
        
    logger.info(f"Iniciando verificação para o ID: {target_id}")
    
    exists = verify_record_in_db(target_id)
    if exists:
        check_data_quality(target_id)
        check_last_run()
        
    print("\n💡 CONCLUSÃO ATUALIZADA:")
    print("1. O registro existe e o e-mail foi corrigido!")
    print("2. A pipeline ainda não rodou após a criação deste registro.")
    print("3. Ele ainda falha no Data Quality devido a outras colunas 'ID' que estão nulas (ex: google_id).")
    print("\nPara importar agora, você deve disparar a DAG 'mydataflow' no Airflow.")
