with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'r') as f:
    content = f.read()

if 'getOsDetails' not in content:
    content += "\n$routes->get('api/os_details/(:num)', 'ApiController::getOsDetails/$1');\n"
    with open('/root/datalake-air-flow-delta/src/fiscalweb/app/Config/Routes.php', 'w') as f:
        f.write(content)
