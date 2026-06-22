<?php
// Set up CodeIgniter paths
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(__DIR__);
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();

define('APPPATH', realpath(rtrim($paths->appDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath(APPPATH . '../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath(rtrim($paths->systemDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath(rtrim($paths->writableDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('TESTPATH', realpath(rtrim($paths->testsDirectory, '\\/ ')) . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('ENVIRONMENT', 'production');

require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::bootTest($paths);

echo "==================================================\n";
echo "🧪 TESTE DE INTEGRAÇÃO METABASE MULTI-TENANT\n";
echo "==================================================\n";

$helper = new \App\Helpers\MetabaseHelper();

// 1. Validar Geração da Senha Determinística
$email = "admin@gmail.com";
$name = "Admin";
echo "[1] Gerando senha determinística para o usuário...\n";
$pwd = $helper->getTenantPassword($email);
echo "    -> Senha gerada: " . $pwd . "\n\n";

// 2. Testar Autenticação Administrativa
echo "[2] Efetuando login de Administrador no Metabase...\n";
$sessionToken = $helper->authenticate();
if ($sessionToken) {
    echo "    -> Sucesso! Token de Sessão: " . $sessionToken . "\n\n";
    
    // 3. Testar fluxo de provisionamento do inquilino para o usuário admin (ID 3 no Metabase)
    // Vamos usar o ID 146 para simular o banco
    echo "[3] Executando fluxo completo de provisionamento (Tenant ID 146)...\n";
    $success = $helper->provisionTenant(146, 'admin@gmail.com', 'Admin Sobrenome');
    echo "    -> Status do Provisionamento: " . ($success ? "✅ SUCESSO" : "❌ FALHA") . "\n\n";
} else {
    echo "    -> ❌ FALHA: Não foi possível autenticar. Verifique o status do Metabase e suas credenciais no .env.\n\n";
}

echo "==================================================\n";
echo "Fim dos testes.\n";
echo "==================================================\n";
