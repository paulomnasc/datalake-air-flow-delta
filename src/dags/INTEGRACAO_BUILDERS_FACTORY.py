"""
INSTRUÇÕES DE INTEGRAÇÃO: Como integrar o sistema de builders na factory_master.py

Este arquivo fornece os trechos de código necessários para integrar
o sistema de extensibilidade baseado em herança na factory_master.py existente.

Duas estratégias são apresentadas:
1. GRADUAL: Integração passo a passo (recomendado)
2. FULL: Refatoração completa (se quiser refatorar tudo de uma vez)
"""

# ==============================================================================
# ESTRATÉGIA 1: INTEGRAÇÃO GRADUAL (RECOMENDADO)
# ==============================================================================

"""
Benefícios:
✅ Risco mínimo - não quebra DAGs existentes
✅ Pode ser feito incrementalmente
✅ Fácil reverter se necessário
✅ Permite testar com nova DAG antes de migrar tudo

Passos:
1. Adicionar import do registry
2. Modificar query para buscar builder_type (com fallback)
3. Usar registry apenas para DAGs que têm builder_type definido
4. DAGs sem builder_type continuam usando função original
"""

# PASSO 1: Adicionar imports no topo de factory_master.py
# ───────────────────────────────────────────────────────────

import_addition = """
# 🆕 NOVO: Sistema de extensibilidade com builders
from dag_builder_examples import DAGBuilderRegistry, DefaultDAGBuilder

# Se import falhar, continuar sem (compatibilidade retroativa)
try:
    registry = DAGBuilderRegistry()
except ImportError:
    registry = None
    log.warning("⚠️ dag_builder_examples não encontrado - usando factory padrão")
"""

# PASSO 2: Modificar a query SQL (adicionar COALESCE para segurança)
# ───────────────────────────────────────────────────────────────────

sql_query_modification = """
# ANTES (linhas 74-91):
sql_query = f\"\"\"
SELECT
    id,
    dag_id, 
    schedule_interval,
    owner,
    description,
    source_filename,
    target_table_name,
    python_module_path,
    transform_args,
    start_date,
    is_multi_table,
    max_parallel_tasks,
    sql_connection_id,
    sql_host,
    sql_port,
    sql_database_name,
    sql_user,
    sql_password,
    user_bucket
FROM dag_configurations
WHERE is_active = 1 
ORDER BY id;
\"\"\"

# DEPOIS (adicionar coluna builder_type com fallback):
sql_query = f\"\"\"
SELECT
    id,
    dag_id, 
    schedule_interval,
    owner,
    description,
    source_filename,
    target_table_name,
    python_module_path,
    transform_args,
    start_date,
    is_multi_table,
    max_parallel_tasks,
    sql_connection_id,
    sql_host,
    sql_port,
    sql_database_name,
    sql_user,
    sql_password,
    user_bucket,
    COALESCE(builder_type, 'default') AS builder_type  # 🆕 NOVO
FROM dag_configurations
WHERE is_active = 1 
ORDER BY id;
\"\"\"
"""

# PASSO 3: Desempacotar coluna adicional (linha ~689)
# ────────────────────────────────────────────────────

unpack_modification = """
# ANTES:
id, dag_id_value, schedule_interval, owner, description, source_filename, \\
target_table_name, python_module_path, transform_args, start_date_db, \\
is_multi_table, max_parallel_tasks, sql_connection_id, sql_host, sql_port, \\
sql_database_name, sql_user, sql_password, user_bucket = record

# DEPOIS:
id, dag_id_value, schedule_interval, owner, description, source_filename, \\
target_table_name, python_module_path, transform_args, start_date_db, \\
is_multi_table, max_parallel_tasks, sql_connection_id, sql_host, sql_port, \\
sql_database_name, sql_user, sql_password, user_bucket, \\
builder_type = record  # 🆕 NOVO
"""

# PASSO 4: Usar o registry ao criar DAG (linha ~720)
# ──────────────────────────────────────────────────

dag_creation_modification = """
# ANTES (linha ~720):
try:
    dag_obj = create_multi_table_dag(dag_config) if is_multi_table else create_dynamic_dag(dag_config)
    globals()[config_name] = dag_obj
    log.info(f"✅ DAG registrada: {config_name}")

# DEPOIS:
try:
    # 🆕 NOVO: Usar o sistema de builders se disponível
    if registry and builder_type != 'default':
        log.info(f"🔨 [BUILDER] Usando builder customizado: {builder_type}")
        dag_builder = registry.get_builder(builder_type, dag_config)
        dag_obj = dag_builder.create_dag()
    else:
        # Fallback: usar função original (compatibilidade)
        log.debug(f"📌 [FACTORY] Usando factory padrão para DAG: {dag_id_value}")
        dag_obj = create_multi_table_dag(dag_config) if is_multi_table else create_dynamic_dag(dag_config)
    
    globals()[config_name] = dag_obj
    log.info(f"✅ DAG registrada: {config_name}")
"""

# ==============================================================================
# ESTRATÉGIA 2: REFATORAÇÃO COMPLETA (Full Migration)
# ==============================================================================

"""
Se quiser refatorar tudo de uma vez para usar o novo sistema:

1. Mover lógica de create_dynamic_dag() para DefaultDAGBuilder
2. Mover lógica de create_multi_table_dag() para uma subclasse
3. Simplificar factory_master.py ao máximo
4. Tudo passa pelo registry
"""

code_simplification = """
# A factory_master.py fica muito mais simples:

def create_dynamic_dag_legacy(dag_config: Dict[str, Any]) -> DAG:
    '''Factory padrão - mantida para compatibilidade.'''
    builder = DefaultDAGBuilder(dag_config)
    return builder.create_dag()

def create_multi_table_dag_legacy(dag_config: Dict[str, Any]) -> DAG:
    '''Factory multi-table - mantida para compatibilidade.'''
    from dag_builder_examples import MultiTableDAGBuilder
    builder = MultiTableDAGBuilder(dag_config)
    return builder.create_dag()

# Loop simplificado:
for record in dag_records:
    try:
        # ... desempacotamento ...
        dag_config = {...}
        
        # Tudo passa pelo registry
        builder_type = builder_type or ('multi_table' if is_multi_table else 'default')
        dag_builder = registry.get_builder(builder_type, dag_config)
        dag_obj = dag_builder.create_dag()
        globals()[config_name] = dag_obj
        
    except Exception as e:
        log.error(f"Erro criando DAG: {e}")
"""

# ==============================================================================
# EXEMPLO PRÁTICO DE INTEGRAÇÃO GRADUAL
# ==============================================================================

example_integration = """
# 1. No seu MySQL, adicionar coluna ao schema:

ALTER TABLE dag_configurations 
ADD COLUMN builder_type VARCHAR(50) DEFAULT 'default'
AFTER user_bucket;

# 2. Registrar builders customizados para seus dados:

UPDATE dag_configurations 
SET builder_type = 'monitored'
WHERE dag_id = 'pipeline_vendas';

UPDATE dag_configurations 
SET builder_type = 'ecommerce'
WHERE owner = 'vendas_team';

# 3. Adicionar código em factory_master.py:

# No início do arquivo:
from dag_builder_examples import DAGBuilderRegistry
registry = DAGBuilderRegistry()

# Na query SQL (linha ~90):
COALESCE(builder_type, 'default') AS builder_type

# No loop de criação (linha ~720):
if registry:
    dag_builder = registry.get_builder(builder_type, dag_config)
    dag_obj = dag_builder.create_dag()
else:
    dag_obj = create_multi_table_dag(dag_config) if is_multi_table else create_dynamic_dag(dag_config)

globals()[config_name] = dag_obj

# 4. Criar suas customizações:

from dag_builder_base import DAGBuilder

class MeuDAGBuilder(DAGBuilder):
    def customize_silver_transformation(self):
        def minha_silver(**context):
            # Minha lógica
            pass
        return minha_silver

# Registrar:
from dag_builder_examples import DAGBuilderRegistry
registry = DAGBuilderRegistry()
registry.register_builder('meu-tipo', MeuDAGBuilder)
"""

# ==============================================================================
# SCRIPT HELPER: Aplicar todas as mudanças automaticamente
# ==============================================================================

helper_script = '''
#!/usr/bin/env python3
"""
Script para integrar o sistema de builders na factory_master.py existente.

Uso: python integrate_builders.py /path/to/factory_master.py
"""

import sys
import re

def integrate_builders(factory_file_path):
    """Aplica as mudanças de integração automaticamente."""
    
    with open(factory_file_path, 'r') as f:
        content = f.read()
    
    # 1. Adicionar import após os imports existentes
    import_statement = """
# 🆕 Sistema de extensibilidade com builders
try:
    from dag_builder_examples import DAGBuilderRegistry
    registry = DAGBuilderRegistry()
except ImportError:
    registry = None
    log.warning("⚠️ dag_builder_examples não encontrado")
"""
    
    if 'DAGBuilderRegistry' not in content:
        # Encontrar linha com último import airflow
        match = re.search(r'(from airflow\..*?\n)', content[:500])
        if match:
            insert_pos = match.end()
            content = content[:insert_pos] + import_statement + '\\n' + content[insert_pos:]
            print("✅ Import adicionado")
    
    # 2. Modificar SQL query
    old_query = "sql_query = f\"\"\"\\s+SELECT.*?WHERE is_active = 1"
    new_query_ending = "COALESCE(builder_type, 'default') AS builder_type\\nFROM dag_configurations\\nWHERE is_active = 1"
    
    if 'builder_type' not in content:
        content = re.sub(
            r"(user_bucket\\s+FROM dag_configurations)",
            r"user_bucket,\\n    COALESCE(builder_type, 'default') AS builder_type\\nFROM dag_configurations",
            content
        )
        print("✅ Query SQL modificada")
    
    # 3. Modificar desempacotamento
    if 'builder_type = record' not in content:
        content = re.sub(
            r"(user_bucket = record\[)",
            r"builder_type = record[\\1",
            content
        )
        print("✅ Desempacotamento modificado")
    
    # 4. Modificar criação de DAG
    if 'registry.get_builder' not in content:
        old_dag_creation = r"dag_obj = create_multi_table_dag.*?\nglobals\[config_name\] = dag_obj"
        new_dag_creation = """if registry and builder_type != 'default':
                dag_builder = registry.get_builder(builder_type, dag_config)
                dag_obj = dag_builder.create_dag()
            else:
                dag_obj = create_multi_table_dag(dag_config) if is_multi_table else create_dynamic_dag(dag_config)
            
            globals()[config_name] = dag_obj"""
        
        content = re.sub(old_dag_creation, new_dag_creation, content, flags=re.DOTALL)
        print("✅ Criação de DAG modificada")
    
    # Salvar arquivo modificado
    with open(factory_file_path, 'w') as f:
        f.write(content)
    
    print(f"✅ Integração concluída! {factory_file_path} foi atualizado")

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Uso: python integrate_builders.py /path/to/factory_master.py")
        sys.exit(1)
    
    integrate_builders(sys.argv[1])
'''

print("=" * 80)
print("INTEGRAÇÃO DO SISTEMA DE BUILDERS")
print("=" * 80)
print()
print("📖 Este arquivo documenta como integrar o novo sistema de builders")
print("   na factory_master.py existente.")
print()
print("Duas estratégias:")
print()
print("1️⃣  ESTRATÉGIA GRADUAL (Recomendado)")
print("   - Risco mínimo")
print("   - Compatibilidade retroativa")
print("   - Pode ser feito incrementalmente")
print()
print("2️⃣  ESTRATÉGIA FULL (Refatoração completa)")
print("   - Código mais limpo")
print("   - Mas requer mais testes")
print()
print("Consulte este arquivo para trechos de código específicos.")
print("=" * 80)
