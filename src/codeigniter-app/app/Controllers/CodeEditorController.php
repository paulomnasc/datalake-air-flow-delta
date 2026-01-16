<?php

namespace App\Controllers;

use App\Controllers\QueryBuilderController;

/**
 * CodeEditorController
 * 
 * Interface avançada com Monaco Editor para execução de queries SQL
 * Herda toda a lógica funcional do QueryBuilderController (DuckDB, segurança, etc)
 * 
 * Git é gerenciado pelo frontend usando isomorphic-git (sem backend necessário)
 * 
 * Rotas:
 * - GET  /code-editor              → Exibe interface web com Monaco Editor
 * - POST /code-editor/execute      → Executa query (herdado de QueryBuilderController)
 * - POST /code-editor/tables       → Lista tabelas (herdado)
 * - POST /code-editor/schema       → Obtém schema (herdado)
 * - POST /code-editor/files        → Lista arquivos Parquet (herdado)
 */
class CodeEditorController extends QueryBuilderController
{
    /**
     * Exibe interface web para Code Editor com Monaco
     * Usa a mesma lógica de preparação de dados do QueryBuilder
     */
    public function index()
    {
        // Verifica saúde da API DuckDB
        $duckdbStatus = \App\Helpers\DuckDBHelper::healthCheck();
        
        // Obtém bucket do usuário logado
        $userBucket = \App\Helpers\SessionHelper::getUserBucket();
        // Aponta para o bucket raiz (sem camada específica)
        $userS3Path = \App\Helpers\SessionHelper::getUserS3Path('');
        
        // Lista arquivos Parquet do bucket do usuário (todas as camadas)
        $parquetFiles = [];
        if ($userBucket) {
            $parquetFiles = \App\Helpers\DuckDBHelper::listParquetFiles($userS3Path);
        }
        
        return view('code_editor/code-editor', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
            'userBucket' => $userBucket,
            'userS3Path' => $userS3Path
        ]);
    }
    
    /**
     * Sobrescreve listParquetFiles para retornar apenas camadas: bronze, silver, gold, delta
     * (não inclui raw como no QueryBuilder padrão)
     */
    public function listParquetFiles()
    {
        $json = $this->request->getJSON(true);
        $path = $json['path'] ?? null;
        
        // Camadas permitidas para Code Editor (não inclui raw)
        $layers = ['bronze', 'silver', 'gold', 'delta'];
        
        $allFiles = [];
        
        // Se path não fornecido, busca de todas as camadas permitidas
        if (empty($path)) {
            foreach ($layers as $layer) {
                $layerPath = \App\Helpers\SessionHelper::getUserS3Path('/' . $layer);
                
                // Validar se path pertence ao usuário
                if (\App\Helpers\SessionHelper::validateUserS3Path($layerPath)) {
                    $files = \App\Helpers\DuckDBHelper::listParquetFiles($layerPath);
                    if (is_array($files)) {
                        $allFiles = array_merge($allFiles, $files);
                    }
                }
            }
        } else {
            // Se path fornecido, validar se é uma das camadas permitidas
            $isAllowed = false;
            foreach ($layers as $layer) {
                if (strpos($path, '/' . $layer) !== false) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Acesso negado: apenas bronze, silver, gold e delta são permitidos',
                        'files' => []
                    ]);
            }
            
            // Validar se path pertence ao usuário
            if (!\App\Helpers\SessionHelper::validateUserS3Path($path)) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Acesso negado: path inválido para este usuário',
                        'files' => []
                    ]);
            }
            
            $allFiles = \App\Helpers\DuckDBHelper::listParquetFiles($path);
        }
        
        return $this->response->setJSON([
            'success' => true,
            'files' => $allFiles,
            'path' => $path ?? 'merged'
        ]);
    }
    
    /**
     * Página de teste do componente git-sidebar
     */
    public function testGitSidebar()
    {
        $userBucket = \App\Helpers\SessionHelper::getUserBucket();
        
        return view('code_editor/test-git-sidebar', [
            'userBucket' => $userBucket ?? 'lab01'
        ]);
    }
    
    // Todos os outros métodos (execute, listTables, getSchema, etc)
    // são herdados do QueryBuilderController
    // Nenhuma duplicação de código!
}

