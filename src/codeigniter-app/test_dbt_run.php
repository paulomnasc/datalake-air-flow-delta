<?php
// Set up CodeIgniter paths
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(__DIR__);
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();

// Define necessary path constants before bootTest
define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('TESTPATH', realpath(rtrim($paths->testsDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('ENVIRONMENT', 'production');

require $paths->systemDirectory . '/Boot.php';

// Bootstrap CI for testing (loads autoloader, services, helpers, etc.)
\CodeIgniter\Boot::bootTest($paths);

// Implement dummy session handler for MockSession
$driver = new class implements \SessionHandlerInterface {
    public function open($savePath, $sessionName): bool { return true; }
    public function close(): bool { return true; }
    public function read($id): string { return ''; }
    public function write($id, $data): bool { return true; }
    public function destroy($id): bool { return true; }
    public function gc($maxLifetime): int|bool { return true; }
};

// Inject MockSession to avoid session ini settings conflicts
$mockSession = new \CodeIgniter\Test\Mock\MockSession($driver, new \Config\Session());
\Config\Services::injectMock('session', $mockSession);

// Instantiate request, response and logger services
$request = service('request');
$response = service('response');
$logger = service('logger');

// Set JSON payload on request body
$rawInput = json_encode([
    'action' => 'run',
    'env' => 'dev',
    'owner' => 'paulomnasc',
    'repo' => 'sql-scripts'
]);
$request->setBody($rawInput);

// Instantiate the controller (Constructor runs session_start() natively)
$controller = new \App\Controllers\DbtController();

// Mock session values in $_SESSION directly
$_SESSION['usuario_logado'] = 1;
$_SESSION['id_usuario_logado'] = 146;
$_SESSION['email_usuario_logado'] = 'admin@gmail.com';
$_SESSION['nome_usuario_logado'] = 'Admin';
$_SESSION['perfil_usuario_logado'] = 'admin';

// Initialize the controller
$controller->initController($request, $response, $logger);

// Run execute
$res = $controller->execute();
if ($res instanceof \CodeIgniter\HTTP\ResponseInterface) {
    echo $res->getBody() . "\n";
} else {
    echo json_encode($res) . "\n";
}
