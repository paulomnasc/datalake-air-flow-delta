import os
import re

ddl_path = '/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl.sql'

def parse_ddl(file_path):
    tables = {}
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    blocks = re.findall(r'CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*?)\) ENGINE=', content, re.DOTALL)
    for table_name, body in blocks:
        lines = body.split('\n')
        columns = []
        fks = []
        for line in lines:
            line = line.strip()
            if not line: continue
            if line.startswith('`'):
                columns.append(line.split('`')[1])
            elif line.startswith('CONSTRAINT') and 'FOREIGN KEY' in line:
                col_match = re.search(r'FOREIGN KEY \(`([^`]+)`\)', line)
                ref_match = re.search(r'REFERENCES `([^`]+)`', line)
                if col_match and ref_match:
                    fks.append({'col': col_match.group(1), 'ref': ref_match.group(1)})
        tables[table_name] = {'columns': columns, 'fks': fks}
    return tables

tables = parse_ddl(ddl_path)
main_tables = [
    'tipo_documento', 'status', 'status_recebimento', 'ordem_servico', 'item_os', 'servico',
    'atividade_macro', 'area_atuacao', 'catalogo_servicos', 'item_contrato', 'documento_recebimento',
    'avaliacao_qualidade_sla'
]

def to_camel_case(snake_str):
    components = snake_str.split('_')
    return components[0] + ''.join(x.title() for x in components[1:])

def to_pascal_case(snake_str):
    return ''.join(x.title() for x in snake_str.split('_'))

def generate_controller(table_name, t_data):
    pascal = to_pascal_case(table_name)
    camel = to_camel_case(table_name)
    model_name = f"{pascal}Model"
    
    # dependencies (FKs)
    use_stmts = [f"use App\\Models\\{model_name};"]
    combos_add = ""
    combos_upd = ""
    for fk in t_data['fks']:
        ref_table = fk['ref']
        if ref_table == 'usuario':
            continue # specific handling if needed, or we might need UsuarioModel
        ref_model = to_pascal_case(ref_table) + "Model"
        if f"use App\\Models\\{ref_model};" not in use_stmts:
            use_stmts.append(f"use App\\Models\\{ref_model};")
        
        combos_add += f"        $data['{fk['col']}_list'] = (new {ref_model}())->listToCombo();\n"
        combos_upd += f"        $data['{fk['col']}_list'] = (new {ref_model}())->listToCombo();\n"
    
    # insert fields
    insert_fields = []
    update_fields = []
    for c in t_data['columns']:
        if c == 'id': continue
        insert_fields.append(f"            '{c}' => $this->request->getPost('{c}')")
        update_fields.append(f"            '{c}' => $this->request->getPost('{c}')")
    
    fields_str = ",\n".join(insert_fields)
    
    code = f"""<?php

namespace App\\Controllers;

use App\\Controllers\\BaseController;
use CodeIgniter\\HTTP\\ResponseInterface;
{"\\n".join(use_stmts)}

class {pascal}Controller extends BaseController
{{
    public function index()
    {{
        $list = $this->list();        
        return view('list{pascal}', ['list' => $list]);
    }}

    public function add()
    {{
        $data = [];
{combos_add}
        return view('add{pascal}', $data);
    }}

    public function upd()
    {{
        $id = $this->request->getPost('id');
        $model = new {model_name}();
        $record = $model->find($id);

        $data = ['record' => $record];
{combos_upd}
        return view('upd{pascal}', $data);
    }}

    public function list()  
    {{
        $model = new {model_name}();
        return $model->findAll();
    }}

    public function insert() 
    {{
        $data = [
{fields_str}
        ];
        
        $model = new {model_name}();
        
        try {{
            $model->insert($data);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro inserido com sucesso!'
            ]);
        }} catch (\\Exception $e) {{
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }}
    }}
    
    public function update() 
    {{
        $model = new {model_name}();
        $id = $this->request->getPost('id');
        $data = [
{fields_str}
        ];
        
        try {{
            $model->update($id, $data);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso!'
            ]);
        }} catch (\\Exception $e) {{
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o registro: ' . $e->getMessage()
            ]);
        }}
    }}
    
    public function delete($id)  
    {{
        $model = new {model_name}();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }}
}}
"""
    out_path = f"/root/datalake-air-flow-delta/src/fiscalweb/app/Controllers/{pascal}Controller.php"
    with open(out_path, 'w') as f:
        f.write(code)

for t in main_tables:
    if t in tables:
        generate_controller(t, tables[t])

print("Controllers generated!")
