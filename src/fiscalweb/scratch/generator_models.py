import os
import re

ddl_path = '/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql'

def parse_ddl(file_path):
    tables = {}
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Simple regex to find CREATE TABLE blocks
    blocks = re.findall(r'CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*?)\) ENGINE=', content, re.DOTALL)
    for table_name, body in blocks:
        lines = body.split('\n')
        columns = []
        fks = []
        pk = 'id'
        for line in lines:
            line = line.strip()
            if not line: continue
            if line.startswith('`'):
                col_name = line.split('`')[1]
                columns.append(col_name)
            elif line.startswith('PRIMARY KEY'):
                pk_match = re.search(r'\(`([^`]+)`', line)
                if pk_match:
                    pk = pk_match.group(1)
            elif line.startswith('CONSTRAINT') and 'FOREIGN KEY' in line:
                # e.g., CONSTRAINT `fk_...` FOREIGN KEY (`id_servico`) REFERENCES `servico` (`id`)
                col_match = re.search(r'FOREIGN KEY \(`([^`]+)`\)', line)
                ref_match = re.search(r'REFERENCES `([^`]+)`', line)
                if col_match and ref_match:
                    fks.append({'col': col_match.group(1), 'ref': ref_match.group(1)})
        tables[table_name] = {'columns': columns, 'pk': pk, 'fks': fks}
    return tables

tables = parse_ddl(ddl_path)

# Filter the 17 target tables we care about
target_tables = [
    'tipo_documento', 'status', 'status_recebimento', 'ordem_servico', 'item_os', 'servico',
    'atividade_macro', 'area_atuacao', 'catalogo_servicos', 'item_contrato', 'documento_recebimento',
    'avaliacao_qualidade_sla',
    'os_item_contrato', 'os_item_os', 'os_status_recebimento', 'usuario_os', 'usuario_recebimento'
]

main_tables = target_tables[:12]
assoc_tables = target_tables[12:]

def to_camel_case(snake_str):
    components = snake_str.split('_')
    return components[0] + ''.join(x.title() for x in components[1:])

def to_pascal_case(snake_str):
    return ''.join(x.title() for x in snake_str.split('_'))

def get_allowed_fields(cols):
    cols = [c for c in cols if c != 'id']
    return ", ".join([f"'{c}'" for c in cols])

def first_string_field(cols):
    if 'descricao' in cols: return 'descricao'
    if 'nome' in cols: return 'nome'
    for c in cols:
        if c != 'id' and not c.startswith('id_'): return c
    return cols[0] if cols else 'id'

def generate_model(table_name, t_data):
    pascal = to_pascal_case(table_name)
    allowed = get_allowed_fields(t_data['columns'])
    pk = t_data['pk']
    desc_field = first_string_field(t_data['columns'])
    
    code = f"""<?php

namespace App\Models;

use CodeIgniter\\Model;

class {pascal}Model extends Model
{{
    protected $table            = '{table_name}';
    protected $primaryKey       = '{pk}';
    protected $useAutoIncrement = { 'true' if pk == 'id' else 'false' };
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [{allowed}];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {{
        $data = $this->select('{pk}, {desc_field}')->findAll();
        return $data;
    }}
"""
    
    if table_name in assoc_tables:
        # Example: function getOsByContrato
        code += f"""
    public function getAssocByTarget($target_id, $target_col)
    {{
        return $this->where($target_col, $target_id)->findAll();
    }}
"""
    code += "}\n"
    
    out_path = f"/root/datalake-air-flow-delta/src/fiscalweb/app/Models/{pascal}Model.php"
    with open(out_path, 'w') as f:
        f.write(code)

for t in target_tables:
    if t in tables:
        generate_model(t, tables[t])

print("Models generated!")
