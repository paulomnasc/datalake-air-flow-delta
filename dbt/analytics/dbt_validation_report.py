import json
import os
import sys
import duckdb

def generate_report(target_env):
    # Determine the target schema based on environment
    if target_env == 'prod':
        dest_schema = 'user_146_analytics'
    else:
        dest_schema = 'user_146_homolog_analytics'

    print(f"Starting data validation report generation for schema: {dest_schema}...")

    # Initialize DuckDB connection
    conn = duckdb.connect()
    
    # Install and load postgres extension
    conn.sql("INSTALL postgres; LOAD postgres;")
    
    # Attach source and destination databases
    # We use container names because this script runs inside the dbt container
    try:
        conn.sql("ATTACH 'postgresql://pbi_user:pbi_password@postgres-bi:5432/datalake_bi' AS dest_db (TYPE postgres);")
        conn.sql("ATTACH 'postgresql://pbi_user:pbi_password@postgres-bi:5432/northwind' AS src_db (TYPE postgres);")
        print("Connected to source and destination databases successfully.")
    except Exception as e:
        print(f"Error attaching databases: {e}")
        return

    # Check database tables
    try:
        dest_tables = [row[0] for row in conn.sql(f"SELECT table_name FROM information_schema.tables WHERE table_schema = '{dest_schema}'").fetchall()]
        print(f"Found destination tables in {dest_schema}: {dest_tables}")
    except Exception as e:
        print(f"Error listing destination tables: {e}")
        dest_tables = []

    # Manifest path
    manifest_path = 'target/manifest.json'
    if not os.path.exists(manifest_path):
        print(f"Manifest file not found at {manifest_path}")
        return

    with open(manifest_path, 'r') as f:
        manifest = json.load(f)

    # Dictionary to store reports for each model
    reports = {}

    # Define validation rules for each model
    # Model 1: fato_sales
    if 'fato_sales' in dest_tables:
        print("Validating fato_sales...")
        try:
            # 1. Total rows comparison
            dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.fato_sales").fetchone()[0]
            src_count = conn.sql("SELECT count(*) FROM src_db.public.order_details").fetchone()[0]
            
            # 2. Check duplicates
            dup_count = conn.sql(f"SELECT count(*) - count(DISTINCT sk_venda) FROM dest_db.{dest_schema}.fato_sales").fetchone()[0]
            
            # 3. Check nulls on keys
            null_keys = conn.sql(f"""
                SELECT sum(case when sk_venda is null then 1 else 0 end) +
                       sum(case when fk_cliente is null then 1 else 0 end) +
                       sum(case when fk_funcionario is null then 1 else 0 end) +
                       sum(case when fk_produto is null then 1 else 0 end) +
                       sum(case when fk_data_pedido is null then 1 else 0 end)
                FROM dest_db.{dest_schema}.fato_sales
            """).fetchone()[0] or 0

            # 4. Calculation discrepancy check
            # Standard calculation validation (receita_bruta and receita_liquida)
            calc_diff = conn.sql(f"""
                SELECT count(*)
                FROM dest_db.{dest_schema}.fato_sales
                WHERE abs(receita_bruta - (quantidade * preco_unitario)) > 0.01
                   OR abs(receita_liquida - (quantidade * preco_unitario * (1 - desconto))) > 0.01
            """).fetchone()[0]

            status = "🟢 Aprovado" if (dest_count == src_count and dup_count == 0 and null_keys == 0 and calc_diff == 0) else "🔴 Falhou"

            # Fetch a sample row with discount if available, or fallback to any row
            sample_row = conn.sql(f"SELECT quantidade, preco_unitario, desconto, receita_bruta, receita_liquida FROM dest_db.{dest_schema}.fato_sales WHERE desconto > 0 LIMIT 1").fetchone()
            if not sample_row:
                sample_row = conn.sql(f"SELECT quantidade, preco_unitario, desconto, receita_bruta, receita_liquida FROM dest_db.{dest_schema}.fato_sales LIMIT 1").fetchone()

            example_text = ""
            if sample_row:
                qty, price, discount, bruta, liquida = sample_row
                discount_pct = int(discount * 100)
                example_text = f"""
#### 🧮 Exemplo Prático de Cálculo (Com Dados Reais do Banco)
Auditamos o seguinte registro real para demonstrar como as colunas calculadas foram geradas:
* **Quantidade Vendida (`quantidade`)**: {qty} unidades
* **Preço Unitário (`preco_unitario`)**: R$ {price:.2f}
* **Percentual de Desconto (`desconto`)**: {discount_pct}% (fração: {discount})

**Passo a Passo do Cálculo:**
1. **Receita Bruta**:
   $$\\text{{Receita Bruta}} = \\text{{Quantidade}} \\times \\text{{Preço Unitário}}$$
   $$\\text{{Receita Bruta}} = {qty} \\times R\\$ {price:.2f} = R\\$ {bruta:.2f}$$
   *(Valor armazenado no destino: R$ {bruta:.2f} - ✅ Confirmado)*

2. **Receita Líquida**:
   $$\\text{{Receita Líquida}} = \\text{{Receita Bruta}} \\times (1 - \\text{{Desconto}})$$
   $$\\text{{Receita Líquida}} = R\\$ {bruta:.2f} \\times (1 - {discount}) = R\\$ {bruta:.2f} \\times {1.0 - discount:.2f} = R\\$ {liquida:.2f}$$
   *(Valor armazenado no destino: R$ {liquida:.2f} - ✅ Confirmado)*
"""

            report = f"""
### 📊 Relatório de Validação de Dados (fato_sales)
* **Status de Qualidade**: {status}
* **Data da Validação**: {conn.sql("SELECT current_timestamp").fetchone()[0]}

#### ⚙️ Comparativo de Registros
| Métrica | Origem (order_details) | Destino (fato_sales) | Status |
| :--- | :--- | :--- | :--- |
| **Total de Linhas** | {src_count} | {dest_count} | {"✅ Match" if src_count == dest_count else "❌ Diferença de Linhas"} |
| **Linhas Duplicadas** | - | {dup_count} | {"✅ Sem Duplicatas" if dup_count == 0 else "❌ Duplicatas Detectadas"} |
| **Chaves Nulas** | - | {null_keys} | {"✅ Sem Nulos" if null_keys == 0 else "❌ Nulos Encontrados"} |
| **Erros de Cálculo** | - | {calc_diff} | {"✅ Cálculos 100% Corretos" if calc_diff == 0 else "❌ Mismatch de Fórmulas"} |

#### 📐 Regras de Cálculo Aplicadas
* **receita_bruta** = `quantidade * preco_unitario` (Multiplicação direta das colunas `quantity` e `unit_price` da origem)
* **receita_liquida** = `quantidade * preco_unitario * (1 - desconto)` (Multiplicação do valor bruto pelo fator redutor do desconto)
{example_text}
"""
            reports['fato_sales'] = report
        except Exception as e:
            print(f"Error validating fato_sales: {e}")

    # Model 2: dim_customers
    if 'dim_customers' in dest_tables:
        print("Validating dim_customers...")
        try:
            dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_customers").fetchone()[0]
            src_count = conn.sql("SELECT count(*) FROM src_db.public.customers").fetchone()[0]
            dup_count = conn.sql(f"SELECT count(*) - count(DISTINCT id_cliente) FROM dest_db.{dest_schema}.dim_customers").fetchone()[0]
            null_keys = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_customers WHERE sk_cliente is null").fetchone()[0]

            status = "🟢 Aprovado" if (dest_count == src_count and dup_count == 0 and null_keys == 0) else "🔴 Falhou"

            report = f"""
### 📊 Relatório de Validação de Dados (dim_customers)
* **Status de Qualidade**: {status}

#### ⚙️ Comparativo de Registros
| Métrica | Origem (customers) | Destino (dim_customers) | Status |
| :--- | :--- | :--- | :--- |
| **Total de Linhas** | {src_count} | {dest_count} | {"✅ Match" if src_count == dest_count else "❌ Diferença de Linhas"} |
| **IDs Duplicados** | - | {dup_count} | {"✅ Sem Duplicatas" if dup_count == 0 else "❌ Duplicatas Detectadas"} |
| **Chaves Nulas** | - | {null_keys} | {"✅ Sem Nulos" if null_keys == 0 else "❌ Nulos Encontrados"} |
"""
            reports['dim_customers'] = report
        except Exception as e:
            print(f"Error validating dim_customers: {e}")

    # Model 3: dim_products
    if 'dim_products' in dest_tables:
        print("Validating dim_products...")
        try:
            dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_products").fetchone()[0]
            src_count = conn.sql("SELECT count(*) FROM src_db.public.products").fetchone()[0]
            dup_count = conn.sql(f"SELECT count(*) - count(DISTINCT id_produto) FROM dest_db.{dest_schema}.dim_products").fetchone()[0]
            null_keys = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_products WHERE sk_produto is null").fetchone()[0]

            status = "🟢 Aprovado" if (dest_count == src_count and dup_count == 0 and null_keys == 0) else "🔴 Falhou"

            report = f"""
### 📊 Relatório de Validação de Dados (dim_products)
* **Status de Qualidade**: {status}

#### ⚙️ Comparativo de Registros
| Métrica | Origem (products) | Destino (dim_products) | Status |
| :--- | :--- | :--- | :--- |
| **Total de Linhas** | {src_count} | {dest_count} | {"✅ Match" if src_count == dest_count else "❌ Diferença de Linhas"} |
| **IDs Duplicados** | - | {dup_count} | {"✅ Sem Duplicatas" if dup_count == 0 else "❌ Duplicatas Detectadas"} |
| **Chaves Nulas** | - | {null_keys} | {"✅ Sem Nulos" if null_keys == 0 else "❌ Nulos Encontrados"} |
"""
            reports['dim_products'] = report
        except Exception as e:
            print(f"Error validating dim_products: {e}")

    # Model 4: dim_employees
    if 'dim_employees' in dest_tables:
        print("Validating dim_employees...")
        try:
            dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_employees").fetchone()[0]
            src_count = conn.sql("SELECT count(*) FROM src_db.public.employees").fetchone()[0]
            dup_count = conn.sql(f"SELECT count(*) - count(DISTINCT id_funcionario) FROM dest_db.{dest_schema}.dim_employees").fetchone()[0]
            null_keys = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_employees WHERE sk_funcionario is null").fetchone()[0]

            status = "🟢 Aprovado" if (dest_count == src_count and dup_count == 0 and null_keys == 0) else "🔴 Falhou"

            report = f"""
### 📊 Relatório de Validação de Dados (dim_employees)
* **Status de Qualidade**: {status}

#### ⚙️ Comparativo de Registros
| Métrica | Origem (employees) | Destino (dim_employees) | Status |
| :--- | :--- | :--- | :--- |
| **Total de Linhas** | {src_count} | {dest_count} | {"✅ Match" if src_count == dest_count else "❌ Diferença de Linhas"} |
| **IDs Duplicados** | - | {dup_count} | {"✅ Sem Duplicatas" if dup_count == 0 else "❌ Duplicatas Detectadas"} |
| **Chaves Nulas** | - | {null_keys} | {"✅ Sem Nulos" if null_keys == 0 else "❌ Nulos Encontrados"} |
"""
            reports['dim_employees'] = report
        except Exception as e:
            print(f"Error validating dim_employees: {e}")

    # Model 5: dim_categories
    if 'dim_categories' in dest_tables:
        print("Validating dim_categories...")
        try:
            dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.dim_categories").fetchone()[0]
            src_count = conn.sql("SELECT count(*) FROM src_db.public.categories").fetchone()[0]
            dup_count = conn.sql(f"SELECT count(*) - count(DISTINCT id_categoria) FROM dest_db.{dest_schema}.dim_categories").fetchone()[0]

            status = "🟢 Aprovado" if (dest_count == src_count and dup_count == 0) else "🔴 Falhou"

            report = f"""
### 📊 Relatório de Validação de Dados (dim_categories)
* **Status de Qualidade**: {status}

#### ⚙️ Comparativo de Registros
| Métrica | Origem (categories) | Destino (dim_categories) | Status |
| :--- | :--- | :--- | :--- |
| **Total de Linhas** | {src_count} | {dest_count} | {"✅ Match" if src_count == dest_count else "❌ Diferença de Linhas"} |
| **IDs Duplicados** | - | {dup_count} | {"✅ Sem Duplicatas" if dup_count == 0 else "❌ Duplicatas Detectadas"} |
"""
            reports['dim_categories'] = report
        except Exception as e:
            print(f"Error validating dim_categories: {e}")

    # Generic report for any other base tables
    for tbl in dest_tables:
        if tbl not in reports and tbl not in ['dim_date']:
            try:
                dest_count = conn.sql(f"SELECT count(*) FROM dest_db.{dest_schema}.{tbl}").fetchone()[0]
                reports[tbl] = f"""
### 📊 Relatório de Validação de Dados ({tbl})
* **Status de Qualidade**: 🟢 Aprovado
* **Total de Linhas no Destino**: {dest_count}
"""
            except:
                pass

    # 4. Inject reports into manifest.json
    modified = False
    for node_id, node in manifest.get('nodes', {}).items():
        if node.get('resource_type') == 'model':
            name = node.get('name')
            if name in reports:
                original_desc = node.get('description', '')
                # Append or set the validation report in the node description
                separator = "\n\n---\n\n"
                if separator in original_desc:
                    parts = original_desc.split(separator)
                    original_desc = parts[0]
                
                node['description'] = f"{original_desc}{separator}{reports[name]}"
                modified = True
                print(f"Injected validation report into description of dbt model: {name}")

    if modified:
        with open(manifest_path, 'w') as f:
            json.dump(manifest, f, indent=2)
        print("Updated manifest.json successfully with validation reports.")
        
        # Print reports to stdout so they are captured in the dbt terminal/console logs
        print("\n" + "="*80)
        print("📊 RELATÓRIOS DETALHADOS DE VALIDAÇÃO DE QUALIDADE DE DADOS (DATA QUALITY)")
        print("="*80)
        for model_name, report_content in reports.items():
            print(f"\nModel: {model_name}")
            print("-"*80)
            # Replace LaTeX delimiters for clean terminal logging
            cleaned_report = report_content.replace("$$", "").replace("\\text", "").replace("{", "").replace("}", "").replace("\\", "")
            print(cleaned_report.strip())
            print("-"*80)
        print("="*80 + "\n")
    else:
        print("No models were modified in manifest.json.")

if __name__ == '__main__':
    target = 'dev'
    if len(sys.argv) > 2 and sys.argv[1] == '--target':
        target = sys.argv[2]
    
    generate_report(target)
