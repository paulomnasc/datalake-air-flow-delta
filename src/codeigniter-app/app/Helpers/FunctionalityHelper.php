<?php

/**
 * Functionality Helper - Fornece acesso às funcionalidades do usuário em qualquer lugar
 */

/**
 * Retorna se o usuário tem acesso a Buckets
 */
function userHasBucketsAccess()
{
    return isset($GLOBALS['userHasBucketsAccess']) ? $GLOBALS['userHasBucketsAccess'] : false;
}

/**
 * Retorna se o usuário tem acesso a Pipelines
 */
function userHasPipelinesAccess()
{
    return isset($GLOBALS['userHasPipelinesAccess']) ? $GLOBALS['userHasPipelinesAccess'] : false;
}

/**
 * Busca e cacheia as funcionalidades do usuário logado
 */
function loadUserFunctionalities()
{
    // Se já foi carregado, retorna cached
    if (isset($GLOBALS['userHasBucketsAccess']) && isset($GLOBALS['userHasPipelinesAccess'])) {
        return;
    }

    $userHasBucketsAccess = false;
    $userHasPipelinesAccess = false;

    if (isset($_SESSION['id_usuario_logado']) && !empty($_SESSION['id_usuario_logado'])) {
        try {
            $usuarioPerfilModel = new \App\Models\UsuarioPerfilModel();
            $perfilFuncionalidadeModel = new \App\Models\PerfilFuncionalidadeModel();

            // Buscar perfis do usuário
            $perfisUsuario = $usuarioPerfilModel->getPerfisUsuario($_SESSION['id_usuario_logado']);

            if (!empty($perfisUsuario)) {
                $funcionalidadesBuckets = ['Visualizar Buckets', 'Criar Buckets', 'Editar Buckets', 'Deletar Buckets'];
                $funcionalidadesPipelines = ['Operar Fluxos de Dados'];

                // Verificar funcionalidades para cada perfil do usuário
                foreach ($perfisUsuario as $perfil) {
                    $funcionalidadesPerfil = $perfilFuncionalidadeModel->getFuncionalidadesPerfil($perfil->id_perfil);

                    foreach ($funcionalidadesPerfil as $func) {
                        if (in_array($func->funcionalidade_descricao, $funcionalidadesBuckets)) {
                            $userHasBucketsAccess = true;
                        }
                        if (in_array($func->funcionalidade_descricao, $funcionalidadesPipelines)) {
                            $userHasPipelinesAccess = true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Erro ao buscar funcionalidades do usuário: ' . $e->getMessage());
        }
    }

    // Cacheia em globals
    $GLOBALS['userHasBucketsAccess'] = $userHasBucketsAccess;
    $GLOBALS['userHasPipelinesAccess'] = $userHasPipelinesAccess;
}
