"""
COMPARAÇÃO: O QUE VOCÊ PRECISA ENTENDER

================================
❌ O QUE NÃO FUNCIONA
================================

from lib.medallion_pipeline import raw_to_medallion  # ← É uma FUNÇÃO, não classe!

class MeuValidador(raw_to_medallion):  # ❌ ERRO! Não pode herdar de função
    def validate_silver(self, df_silver, **context):
        pass

RESULTADO: TypeError: can't subclass built-in function
Motivo: raw_to_medallion é uma função, não uma classe.


================================
✅ O QUE FUNCIONA (WRAPPER PATTERN)
================================

from lib.medallion_pipeline import raw_to_medallion

class MeuValidador:  # ✅ Classe NORMAL (não herda de função)
    
    def __call__(self, source_filename, target_table_name, **context):
        # ETAPA 1: Executar pipeline padrão (chama a função)
        result = raw_to_medallion(source_filename, target_table_name, **context)
        #              ↓ Retorna {'bronze': path, 'silver': path, 'gold': path}
        
        # ETAPA 2: Aplicar suas customizações
        self.custom_validations(result, target_table_name, **context)
        
        # ETAPA 3: Retornar resultado
        return result
    
    def custom_validations(self, pipeline_result, target_table_name, **context):
        # Sua lógica aqui (validar, transformar, etc)
        pass


FLUXO VISUAL:
───────────────────────────────────────────────────────────────────────

Airflow Factory detecta:
  python_module_path = 'meu_validador.MeuValidador'
              ↓
Factory instancia:
  instance = MeuValidador()
              ↓
Factory chama como função:
  result = instance(source_filename=..., target_table_name=..., **context)
                   ↓
MeuValidador.__call__() executa:
              ├─ raw_to_medallion() ← Pipeline padrão
              │  ├─ Bronze: JSON/CSV → Parquet
              │  ├─ Silver: Limpeza + Qualidade (90+ colunas de qualidade)
              │  └─ Gold: Otimização + Índices
              │
              └─ self.custom_validations() ← Suas customizações
                 ├─ Validar CEP
                 ├─ Tratar nulos
                 ├─ Aplicar regras de negócio
                 └─ Re-salvar em Silver/Gold se necessário
                      ↓
                   Retorna resultado


COMPARAÇÃO LADO A LADO:
═══════════════════════════════════════════════════════════════════════

SEM CUSTOMIZAÇÕES:
  raw_to_medallion(...)  → Bronze → Silver → Gold ✅

COM CUSTOMIZAÇÕES (seu caso):
  MeuValidador()(...)    → Bronze → Silver → Gold → [CEP tratado, validações]
                            └─────────────────┬──────────────────┘
                                        ↑ Automático! ↑


DADOS DISPONÍVEIS NO CONTEXT:
═══════════════════════════════════════════════════════════════════════

def custom_validations(self, pipeline_result, target_table_name, **context):
    
    # pipeline_result: Dict retornado por raw_to_medallion
    bronze_path = pipeline_result.get('bronze')      # s3://bucket/bronze/...
    silver_path = pipeline_result.get('silver')      # s3://bucket/silver/...
    gold_path = pipeline_result.get('gold')          # s3://bucket/gold/...
    
    # context: Passado pelo Airflow
    bucket = context.get('bucket_name', 'lab01')     # Nome do bucket
    owner = context.get('owner', 'airflow')          # Dono da DAG
    ti = context.get('task_instance')                # Task instance do Airflow
    
    # Você pode usar isso para:
    # - Validar se arquivos existem no MinIO
    # - Carregar parquets e fazer transformações
    # - Enviar logs ao XCom
    # - Condicionar próximas tasks


REGRAS IMPORTANTES:
═══════════════════════════════════════════════════════════════════════

✅ DO (Obrigatório):
   - Classe com método __call__()
   - __call__ recebe: source_filename, target_table_name, **context
   - Chamar raw_to_medallion() no início
   - Retornar result

❌ DON'T:
   - Herança de raw_to_medallion (é função, não classe)
   - Não sobrescrever validate_*() (métodos não existem)
   - Não modificar direto no arquivo Bronze/Silver durante pipeline
   - Lançar exceção sem logger (Airflow precisa do contexto)


SEU CASO ESPECÍFICO (CEP):
═══════════════════════════════════════════════════════════════════════

O que você quer fazer:
  ✅ Executar pipeline padrão (Bronze → Silver → Gold)
  ✅ Validar/transformar CEP em Silver
  ✅ Re-salvar o arquivo Silver com CEP tratado

Solução:
  class MeuValidador:
      def __call__(self, source_filename, target_table_name, **context):
          # Pipeline padrão faz tudo
          result = raw_to_medallion(source_filename, target_table_name, **context)
          
          # Depois, você trata CEP na Silver
          self.treat_cep(result.get('silver'), **context)
          
          return result
      
      def treat_cep(self, silver_path, **context):
          # Carregar Silver
          df = pd.read_parquet(silver_path)
          
          # Tratar CEP
          df['billingpostalcode'] = df['billingpostalcode'].apply(
              lambda x: 'None' if pd.isna(x) or str(x).strip().lower() 
                              in ['nan', 'none', 'null', ''] 
              else str(x).strip()
          )
          
          # Re-salvar
          df.to_parquet(silver_path, index=False)


VER RESULTADO NO AIRFLOW:
═══════════════════════════════════════════════════════════════════════

1. Abra Airflow Web UI
2. Encontre sua DAG e clique "Trigger DAG"
3. Clique na execução (rodinha verde/vermelha)
4. Clique na task (ex: "etl_process_for_invoice")
5. Clique em "Logs"
6. Procure por:
   [MeuValidador] 🚀 Iniciando pipeline
   [MeuValidador] 📦 ETAPA 1: Executando pipeline padrão
   [MeuValidador] ✅ Pipeline padrão concluído!
   [CustomValidations] 🔍 Validando coluna 'billingpostalcode'
   [CustomValidations] ✅ CEP tratado: 1000 registros


ARQUIVO DE EXEMPLO:
═══════════════════════════════════════════════════════════════════════

Ver: MEU_VALIDADOR_CORRETO.py neste diretório

É uma implementação completa com:
- Classe MeuValidador com __call__()
- Chamada para raw_to_medallion()
- Validações customizadas
- Tratamento de CEP
- Logs detalhados
- Uso com XCom
- Tratamento de erros

Use como template para sua implementação!
"""

# Não execute este arquivo - é apenas documentação
if __name__ == '__main__':
    import sys
    print(__doc__)
    sys.exit(0)
