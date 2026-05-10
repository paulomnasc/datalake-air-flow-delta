import re
with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'r') as f:
    content = f.read()

if 'ApiController' not in content:
    routes = """
$routes->get('api/areas/(:num)', 'ApiController::getAreasByCatalogo/$1');
$routes->get('api/atividades/(:num)', 'ApiController::getAtividadesByArea/$1');
$routes->get('api/servicos/(:num)', 'ApiController::getServicosByAtividade/$1');
"""
    content += routes
    with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'w') as f:
        f.write(content)
