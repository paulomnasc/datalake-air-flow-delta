#!/usr/bin/env php
<?php
/**
 * Script para sincronizar funções Python de todos os usuários
 * Execute: php sync_user_functions.php
 */

// Define o caminho para o CodeIgniter
define('ROOTPATH', __DIR__ . '/src/codeigniter-app/');
define('FCPATH', ROOTPATH . 'public/');
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system/');
define('APPPATH', ROOTPATH . 'app/');
define('WRITEPATH', ROOTPATH . 'writable/');

// Carrega o ambiente
require ROOTPATH . 'vendor/autoload.php';

// Boot the CodeIgniter application
$app = \Config\Services::codeigniter();
$app->initialize();

// Carrega os models necessários
$usuarioModel = new \App\Models\UsuarioModel();
$usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();

echo "=== Sincronização de Funções Python para Usuários ===\n\n";

// Busca todos os usuários
$usuarios = $usuarioModel->findAll();

if (empty($usuarios)) {
    echo "Nenhum usuário encontrado.\n";
    exit(0);
}

echo "Total de usuários encontrados: " . count($usuarios) . "\n\n";

$sucessos = 0;
$falhas = 0;

foreach ($usuarios as $usuario) {
    // Verifica quantas funções o usuário já tem
    $countFuncoes = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
    
    echo "Usuário #{$usuario->id} ({$usuario->email}): ";
    
    if ($countFuncoes > 0) {
        echo "já tem {$countFuncoes} funções configuradas. Pulando...\n";
        $sucessos++;
        continue;
    }
    
    // Sincroniza com padrão
    try {
        $result = $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
        
        if ($result) {
            $novoCount = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
            echo "✓ Sincronizado com sucesso! ({$novoCount} funções)\n";
            $sucessos++;
        } else {
            echo "✗ Falha na sincronização\n";
            $falhas++;
        }
    } catch (\Exception $e) {
        echo "✗ Erro: " . $e->getMessage() . "\n";
        $falhas++;
    }
}

echo "\n=== Resumo ===\n";
echo "Sucessos: {$sucessos}\n";
echo "Falhas: {$falhas}\n";
echo "Total processado: " . count($usuarios) . "\n";
