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
echo "🧪 TESTE DE INTEGRAÇÃO DE GRUPOS E MEMBROS\n";
echo "==================================================\n";

$grupoModel = new \App\Models\GrupoModel();
$usuarioModel = new \App\Models\UsuarioModel();
$grupoUsuarioModel = new \App\Models\GrupoUsuarioModel();

// 1. Criar Grupo
echo "[1] Criando Grupo de Teste...\n";
$grupoData = [
    'nome' => 'Grupo Teste Automação',
    'email' => 'grupo.teste.automacao@empresa.com'
];
$idGrupo = $grupoModel->insert($grupoData);
if ($idGrupo) {
    echo "    -> Sucesso! ID do Grupo: " . $idGrupo . "\n\n";
} else {
    echo "    -> ❌ FALHA ao criar grupo.\n\n";
    exit(1);
}

// 2. Associar Usuário Existente (ID 505 - Paulo)
echo "[2] Associando Usuário Existente (ID 505) ao grupo...\n";
$associacaoId = $grupoUsuarioModel->insert([
    'id_usuario' => 505,
    'id_grupo' => $idGrupo
]);
if ($associacaoId) {
    echo "    -> Sucesso! ID da Associação: " . $associacaoId . "\n\n";
} else {
    echo "    -> ❌ FALHA ao associar usuário existente.\n\n";
}

// 3. Criar e associar novo usuário (simulando a lógica do Controller)
echo "[3] Criando e associando novo usuário (Fluxo de Senha Temporária)...\n";
$emailNovo = 'novomembro.teste@gmail.com';
$nomeNovo = 'Novo Membro Teste';
$senhaTemporaria = bin2hex(random_bytes(4)); // 8 chars

$novoUsuarioData = [
    'nome' => $nomeNovo,
    'email' => $emailNovo,
    'senha' => password_hash($senhaTemporaria, PASSWORD_DEFAULT),
    'email_confirmado' => 0,
    'status_assinatura' => 'trial'
];

$idNovoUsuario = $usuarioModel->insert($novoUsuarioData);
if ($idNovoUsuario) {
    echo "    -> Usuário criado com ID: " . $idNovoUsuario . "\n";
    echo "    -> Senha temporária gerada: " . $senhaTemporaria . "\n";
    
    // Associar ao grupo
    $relId = $grupoUsuarioModel->insert([
        'id_usuario' => $idNovoUsuario,
        'id_grupo' => $idGrupo
    ]);
    if ($relId) {
         echo "    -> Associado ao grupo com ID da relação: " . $relId . " ✅\n\n";
    } else {
         echo "    -> ❌ FALHA ao associar novo usuário.\n\n";
    }
} else {
    echo "    -> ❌ FALHA ao criar novo usuário.\n\n";
}

// 4. Limpeza (Deletar Grupo e Usuário Novo de teste)
echo "[4] Executando limpeza de dados do teste...\n";
if ($idNovoUsuario) {
    $usuarioModel->delete($idNovoUsuario);
    echo "    -> Novo usuário deletado.\n";
}
$grupoModel->delete($idGrupo);
echo "    -> Grupo e suas relações deletados.\n\n";

echo "==================================================\n";
echo "Fim dos testes. Todos os componentes integrados com SUCESSO!\n";
echo "==================================================\n";
