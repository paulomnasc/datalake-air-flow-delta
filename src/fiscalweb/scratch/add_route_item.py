with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'r') as f:
    content = f.read()

if 'getItensByOs' not in content:
    content += "\n$routes->get('api/itens_os/(:num)', 'ApiController::getItensByOs/$1');\n"
    with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'w') as f:
        f.write(content)
