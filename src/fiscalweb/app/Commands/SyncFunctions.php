#!/usr/bin/env php
<?php
/**
 * Script para sincronizar funções Python de todos os usuários
 * Execute dentro do container: php spark sync:functions
 */

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncFunctions extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'sync:functions';
    protected $description = 'Sincroniza funções Python padrão para todos os usuários';

    public function run(array $params)
    {
        $usuarioModel = new \App\Models\UsuarioModel();
        $usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();

        CLI::write('=== Sincronização de Funções Python para Usuários ===', 'yellow');
        CLI::newLine();

        $usuarios = $usuarioModel->findAll();

        if (empty($usuarios)) {
            CLI::write('Nenhum usuário encontrado.', 'red');
            return;
        }

        CLI::write('Total de usuários encontrados: ' . count($usuarios), 'cyan');
        CLI::newLine();

        $sucessos = 0;
        $falhas = 0;

        foreach ($usuarios as $usuario) {
            $countFuncoes = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
            
            CLI::write("Usuário #{$usuario->id} ({$usuario->email}): ", 'white', false);
            
            if ($countFuncoes > 0) {
                CLI::write("já tem {$countFuncoes} funções configuradas. Pulando...", 'blue');
                $sucessos++;
                continue;
            }
            
            try {
                $result = $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
                
                if ($result) {
                    $novoCount = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
                    CLI::write("✓ Sincronizado com sucesso! ({$novoCount} funções)", 'green');
                    $sucessos++;
                } else {
                    CLI::write('✗ Falha na sincronização', 'red');
                    $falhas++;
                }
            } catch (\Exception $e) {
                CLI::write('✗ Erro: ' . $e->getMessage(), 'red');
                $falhas++;
            }
        }

        CLI::newLine();
        CLI::write('=== Resumo ===', 'yellow');
        CLI::write("Sucessos: {$sucessos}", 'green');
        CLI::write("Falhas: {$falhas}", ($falhas > 0 ? 'red' : 'white'));
        CLI::write("Total processado: " . count($usuarios), 'cyan');
    }
}
