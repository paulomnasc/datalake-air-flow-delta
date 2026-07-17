import os
import re

ddl_path = '/root/datalake-air-flow-delta/src/fiscalweb/app/Database/script_ddl/ddl-v2.sql'

def parse_ddl(file_path):
    tables = {}
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # regex updated to capture type as well. We assume standard layout:
    # `column_name` TYPE [OPTIONS]
    blocks = re.findall(r'CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`? \((.*?)\)(?: ENGINE=|\s*;)', content, re.DOTALL | re.IGNORECASE)
    for table_name, body in blocks:
        table_name = table_name.lower()
        lines = body.split('\n')
        columns = []
        fks = []
        for line in lines:
            line = line.strip()
            if not line: continue
            
            # Check for FKs
            if line.upper().startswith('FOREIGN KEY'):
                col_match = re.search(r'FOREIGN KEY\s*\(`?(\w+)`?\)', line, re.IGNORECASE)
                ref_match = re.search(r'REFERENCES\s*`?(\w+)`?', line, re.IGNORECASE)
                if col_match and ref_match:
                    fks.append({'col': col_match.group(1), 'ref': ref_match.group(1).lower()})
            elif line.upper().startswith('PRIMARY KEY') or line.upper().startswith('UNIQUE KEY'):
                continue
            else:
                # It's a column definition
                # Match `col` or col TYPE
                col_match = re.search(r'^`?(\w+)`?\s+(\w+)', line)
                if col_match:
                    col_name = col_match.group(1)
                    col_type = col_match.group(2).upper()
                    columns.append({'name': col_name, 'type': col_type})

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

def get_input_type(col_type):
    if 'DATETIME' in col_type:
        return 'datetime-local'
    elif 'DATE' in col_type:
        return 'date'
    elif 'FLOAT' in col_type or 'DECIMAL' in col_type or 'DOUBLE' in col_type:
        return 'number" step="0.01'
    elif 'INT' in col_type:
        return 'number'
    return 'text'

def generate_list_view(table_name, columns_info, fks, pascal_name):
    columns = [c['name'] for c in columns_info]
    disp_cols = [c for c in columns if c != 'id']
    headers = "".join([f"<th>{to_pascal_case(c)}</th>" for c in disp_cols])
    
    tds = ""
    for c in disp_cols:
        fk_match = next((fk for fk in fks if fk['col'] == c), None)
        if fk_match:
            tds += f"""
            <td>
                <select name="{c}" id="{c}-<?php echo $item->id ?>">
                    <option value="">Selecione...</option>
                    <?php if(isset(${c}_list)): foreach(${c}_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php if($opt->id == $item->{c}) echo 'selected'; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </td>
"""
        else:
            tds += f"<td> <?php echo $item->{c} ?> </td>"
    
    first_col = disp_cols[0] if disp_cols else 'id'

    code = f"""<?php
if (! defined('VIEWPATH')) {{
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}}
require VIEWPATH.'/header.php';
?>
<div id="content">        
    <div class="container">
        <h4 style="text-align: center;">Listagem de {pascal_name}</h4>
        
        <input type="text" id="filtro-{first_col}" placeholder="Filtrar">
        <img src="../assets/img/lupa.jpg" >
        
        <form action="<?php echo site_url('add{pascal_name}'); ?>" method="post">
            <button type="submit" class="add-button">Incluir</button>
        </form>

        <table class="data-table" id="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    {headers}
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($list as $item): ?>
                <tr id="row-<?php echo $item->id ?>">
                    <td> <?php echo $item->id ?> </td>
                    {tds}
                    <td> 
                        <div class="sidebyside-container">
                            <form action="<?php echo site_url('upd{pascal_name}'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo $item->id ?>">
                                <button class="edit-button" type="submit">✏️</button>
                            </form>
                            <form id="deleteForm-<?php echo $item->id; ?>">
                                <button class="delete-button" type="button" onclick="confirmDelete('<?php echo $item->id; ?>', '<?php echo site_url('delete{pascal_name}/' . $item->id); ?>', 'deleteForm-<?php echo $item->id; ?>')">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            function confirmDelete(id, deleteUrl, formId) {{
                if (confirm("Você tem certeza que deseja deletar este registro?")) {{
                    $.ajax({{
                        url: deleteUrl,
                        type: 'POST',
                        data: {{ _method: 'DELETE' }},
                        success: function(result) {{
                            if (result.status === 'success') {{
                                $('#row-' + id).remove();
                                $('#success-message').html(result.mensagem).show().delay(6000).fadeOut();
                            }} else {{
                                $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                            }}
                        }},
                        error: function(err) {{
                            $('#error-message').html('Erro ao excluir o registro.').show().delay(6000).fadeOut();
                            console.log(err);
                        }}
                    }});
                }}
            }}

            $(document).ready(function() {{
                var table = $('#data-table').DataTable({{
                    dom: 'lrtip',
                    columnDefs: [{{ targets: [0], visible: false }}],
                    language: {{ "sEmptyTable": "Nenhum registro encontrado" }}
                }});

                $('#filtro-{first_col}').on('keyup', function() {{
                    table.search(this.value).draw();
                }});
            }});
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
"""
    return code

def generate_add_view(table_name, columns_info, fks, pascal_name):
    inputs = ""
    for col in columns_info:
        c = col['name']
        ctype = col['type']
        if c == 'id': continue
        
        fk_match = next((fk for fk in fks if fk['col'] == c), None)
        if fk_match:
            inputs += f"""
            <div class="form-group">
                <label for="{c}">{to_pascal_case(c)}:</label>
                <select id="{c}" name="{c}" required>
                    <option value="">Selecione...</option>
                    <?php if(isset(${c}_list)): foreach(${c}_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>">
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
"""
        else:
            input_type = get_input_type(ctype)
            inputs += f"""
            <div class="form-group">
                <label for="{c}">{to_pascal_case(c)}:</label>
                <input type="{input_type}" id="{c}" name="{c}" required>
            </div>
"""

    code = f"""<?php
if (! defined('VIEWPATH')) {{
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Inclusão de {pascal_name}</h4>
        
        <form id="addForm">
            {inputs}
            <div class="button-group">
                <button class="add-button" type="submit">Salvar</button>
                <a href="<?php echo site_url('list{pascal_name}'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {{
                $('#addForm').on('submit', function(e) {{
                    e.preventDefault();
                    $.ajax({{
                        url: '<?php echo site_url('insert{pascal_name}'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {{
                            if (response.status === 'success') {{
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() {{ window.location.href = '<?php echo site_url('list{pascal_name}'); ?>'; }}, 1500);
                            }} else {{
                                $('#error-message').html(response.mensagem).show().delay(5000).fadeOut();
                            }}
                        }},
                        error: function() {{
                            $('#error-message').html('Ocorreu um erro ao salvar os dados.').show().delay(5000).fadeOut();
                        }}
                    }});
                }});
            }});
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
"""
    return code


def generate_upd_view(table_name, columns_info, fks, pascal_name):
    inputs = ""
    for col in columns_info:
        c = col['name']
        ctype = col['type']
        if c == 'id': continue
        
        fk_match = next((fk for fk in fks if fk['col'] == c), None)
        if fk_match:
            inputs += f"""
            <div class="form-group">
                <label for="{c}">{to_pascal_case(c)}:</label>
                <select id="{c}" name="{c}" required>
                    <option value="">Selecione...</option>
                    <?php if(isset(${c}_list)): foreach(${c}_list as $opt): ?>
                        <option value="<?php echo $opt->id; ?>" <?php echo (isset($record->{c}) && $record->{c} == $opt->id) ? 'selected' : ''; ?>>
                            <?php echo isset($opt->descricao) ? $opt->descricao : (isset($opt->nome) ? $opt->nome : $opt->id); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
"""
        else:
            input_type = get_input_type(ctype)
            inputs += f"""
            <div class="form-group">
                <label for="{c}">{to_pascal_case(c)}:</label>
                <input type="{input_type}" id="{c}" name="{c}" value="<?php echo isset($record->{c}) ? $record->{c} : ''; ?>" required>
            </div>
"""

    code = f"""<?php
if (! defined('VIEWPATH')) {{
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}}
require VIEWPATH.'/header.php';
?>
<div id="content">
    <div class="container-menor">
        <h4 style="text-align: center;">Edição de {pascal_name}</h4>
        
        <form id="updForm">
            <input type="hidden" name="id" value="<?php echo isset($record->id) ? $record->id : ''; ?>">
            {inputs}
            <div class="button-group">
                <button class="add-button" type="submit">Atualizar</button>
                <a href="<?php echo site_url('list{pascal_name}'); ?>" class="add-button" style="text-decoration: none; background-color: #6c757d;">Voltar</a>
            </div>
        </form>

        <script>
            $(document).ready(function() {{
                $('#updForm').on('submit', function(e) {{
                    e.preventDefault();
                    $.ajax({{
                        url: '<?php echo site_url('update{pascal_name}'); ?>',
                        type: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {{
                            if (response.status === 'success') {{
                                $('#success-message').html(response.mensagem).show().delay(3000).fadeOut();
                                setTimeout(function() {{ window.location.href = '<?php echo site_url('list{pascal_name}'); ?>'; }}, 1500);
                            }} else {{
                                $('#error-message').html(response.mensagem).show().delay(5000).fadeOut();
                            }}
                        }},
                        error: function() {{
                            $('#error-message').html('Ocorreu um erro ao salvar os dados.').show().delay(5000).fadeOut();
                        }}
                    }});
                }});
            }});
        </script>
    </div>
</div>
<?php require VIEWPATH.'/footer.php'; ?>
"""
    return code

for t in main_tables:
    if t in tables:
        pascal_name = to_pascal_case(t)
        list_code = generate_list_view(t, tables[t]['columns'], tables[t]['fks'], pascal_name)
        add_code = generate_add_view(t, tables[t]['columns'], tables[t]['fks'], pascal_name)
        upd_code = generate_upd_view(t, tables[t]['columns'], tables[t]['fks'], pascal_name)
        
        with open(f"/root/datalake-air-flow-delta/src/fiscalweb/app/Views/list{pascal_name}.php", 'w') as f: f.write(list_code)
        with open(f"/root/datalake-air-flow-delta/src/fiscalweb/app/Views/add{pascal_name}.php", 'w') as f: f.write(add_code)
        with open(f"/root/datalake-air-flow-delta/src/fiscalweb/app/Views/upd{pascal_name}.php", 'w') as f: f.write(upd_code)

print("Views generated!")
