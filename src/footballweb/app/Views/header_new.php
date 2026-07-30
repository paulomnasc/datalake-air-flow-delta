<?php
// Carrega funcionalidades do usuário se não foram passadas pela view
// Isso garante compatibilidade com controllers que usam view() direto
if (!isset($userHasBucketsAccess) || !isset($userHasPipelinesAccess)) {
    // Importa o helper
    if (!function_exists('loadUserFunctionalities')) {
        require_once APPPATH . 'Helpers/FunctionalityHelper.php';
    }
    
    // Carrega as funcionalidades
    loadUserFunctionalities();
    
    // Obtém os valores globais
    $userHasBucketsAccess = isset($GLOBALS['userHasBucketsAccess']) ? $GLOBALS['userHasBucketsAccess'] : false;
    $userHasPipelinesAccess = isset($GLOBALS['userHasPipelinesAccess']) ? $GLOBALS['userHasPipelinesAccess'] : false;
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">