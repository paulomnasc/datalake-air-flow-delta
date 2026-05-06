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
        // Não existe a entidade perfil_funcionalidade neste sistema fiscalweb,
        // então liberamos acesso básico por padrão para usuários logados.
        // Se for necessário, ajuste aqui para basear em perfil ou em outra lógica de autorização.
        $userHasBucketsAccess = true;
        $userHasPipelinesAccess = true;
    }

    // Cacheia em globals
    $GLOBALS['userHasBucketsAccess'] = $userHasBucketsAccess;
    $GLOBALS['userHasPipelinesAccess'] = $userHasPipelinesAccess;
}
