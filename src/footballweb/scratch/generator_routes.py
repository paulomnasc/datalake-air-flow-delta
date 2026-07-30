import os
import re

main_tables = [
    'tipo_documento', 'status', 'status_recebimento', 'ordem_servico', 'item_os', 'servico',
    'atividade_macro', 'area_atuacao', 'catalogo_servicos', 'item_contrato', 'documento_recebimento',
    'avaliacao_qualidade_sla'
]

def to_pascal_case(snake_str):
    return ''.join(x.title() for x in snake_str.split('_'))

routes_to_add = "\n// --- ROTAS DO NOVO MÓDULO (Contratos e OS) ---\n"
for t in main_tables:
    pascal = to_pascal_case(t)
    ctrl = f"{pascal}Controller"
    routes_to_add += f"$routes->get('list{pascal}', '{ctrl}::index');\n"
    routes_to_add += f"$routes->post('add{pascal}', '{ctrl}::add');\n"
    routes_to_add += f"$routes->get('add{pascal}', '{ctrl}::add');\n"
    routes_to_add += f"$routes->post('upd{pascal}', '{ctrl}::upd');\n"
    routes_to_add += f"$routes->post('insert{pascal}', '{ctrl}::insert');\n"
    routes_to_add += f"$routes->post('update{pascal}', '{ctrl}::update');\n"
    routes_to_add += f"$routes->delete('delete{pascal}/(:num)', '{ctrl}::delete/$1');\n"

# Append to Routes.php if not already there
with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'r') as f:
    content = f.read()

if "ROTAS DO NOVO MÓDULO" not in content:
    with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'a') as f:
        f.write(routes_to_add)
    print("Routes added!")
else:
    print("Routes already exist.")
