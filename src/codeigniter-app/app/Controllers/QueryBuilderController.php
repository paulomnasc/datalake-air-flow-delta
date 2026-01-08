<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Helpers\DuckDBHelper;
use App\Helpers\SessionHelper;

/**
 * QueryBuilderController
 * 
 * Interface para execução de queries SQL em Parquet via DuckDB
 * 
 * Rotas:
 * - GET  /query-builder              → Exibe interface web
 * - POST /query-builder/execute      → Executa query
 * - POST /query-builder/tables       → Lista tabelas
 * - POST /query-builder/schema       → Obtém schema
 */
class QueryBuilderController extends BaseController
{
    /**
     * Exibe interface web para Query Builder
     */
    public function index()
    {
        // Verifica saúde da API DuckDB
        $duckdbStatus = DuckDBHelper::healthCheck();
        
        // Obtém bucket do usuário logado
        $userBucket = SessionHelper::getUserBucket();
        // Aponta para o bucket raiz (sem camada específica)
        // A API DuckDB listará todas as camadas: raw, bronze, silver, gold
        $userS3Path = SessionHelper::getUserS3Path('');
        
        // Lista arquivos Parquet do bucket do usuário (todas as camadas)
        $parquetFiles = [];
        if ($userBucket) {
            $parquetFiles = DuckDBHelper::listParquetFiles($userS3Path);
        }
        
        return view('query_builder/index', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
            'userBucket' => $userBucket,
            'userS3Path' => $userS3Path
        ]);
    }
    
    /**
     * Executa uma query SQL
     * 
     * POST /query-builder/execute
     * 
     * Body:
     * {
     *     "sql": "SELECT * FROM read_parquet('s3://lab01/bronze/files.parquet') LIMIT 10",
     *     "limit": 1000
     * }
     */
    public function execute()
    {
        $sql = trim($this->request->getJSON()->sql ?? '');
        $limit = intval($this->request->getJSON()->limit ?? 1000);
        
        if (empty($sql)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error' => 'SQL query cannot be empty'
                ]);
        }
        
        // Segurança: verificar se query tenta acessar buckets de outros usuários
        $userBucket = SessionHelper::getUserBucket();
        if ($userBucket) {
            // Procura por qualquer referência a s3:// bucket que não seja do usuário
            if (preg_match_all('/s3:\\/\\/([a-zA-Z0-9._-]+)/', $sql, $matches)) {
                foreach ($matches[1] as $bucketInQuery) {
                    if ($bucketInQuery !== $userBucket && $bucketInQuery !== 'lab01') {
                        // lab01 é permitido como fallback (compatibilidade com queries antigas)
                        return $this->response
                            ->setStatusCode(403)
                            ->setJSON([
                                'success' => false,
                                'error' => "Acesso negado: você não pode consultar o bucket '{$bucketInQuery}'. Seu bucket é '{$userBucket}'"
                            ]);
                    }
                }
            }
        }
        
        // Segurança: validações básicas
        $sql = $this->_sanitizeSql($sql);
        
        $result = DuckDBHelper::query($sql, min($limit, 10000));
        
        return $this->response->setJSON($result);
    }
    
    /**
     * Lista tabelas/views disponíveis
     * 
     * POST /query-builder/tables
     */
    public function listTables()
    {
        $tables = DuckDBHelper::listTables();
        
        return $this->response->setJSON([
            'success' => true,
            'tables' => $tables
        ]);
    }
    
    /**
     * Obtém schema de um Parquet
     * 
     * POST /query-builder/schema
     * 
     * Body:
     * {
     *     "path": "s3://lab01/bronze/customers"
     * }
     */
    public function getSchema()
    {
        $path = trim($this->request->getJSON()->path ?? '');
        
        if (empty($path)) {
            // Se path vazio, usa bucket do usuário
            $path = SessionHelper::getUserS3Path();
            if (!$path) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Path cannot be empty'
                    ]);
            }
        }
        
        // Segurança: validar se path pertence ao usuário
        if (!SessionHelper::validateUserS3Path($path)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'error' => 'Acesso negado: path inválido para este usuário'
                ]);
        }
        
        $schema = DuckDBHelper::getSchema($path);
        
        return $this->response->setJSON([
            'success' => true,
            'path' => $path,
            'columns' => $schema
        ]);
    }
    
    /**
     * Status da API DuckDB
     * 
     * GET /query-builder/status
     */
    public function status()
    {
        $isHealthy = DuckDBHelper::healthCheck();
        
        return $this->response->setJSON([
            'healthy' => $isHealthy,
            'service' => 'DuckDB Query API',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Lista arquivos Parquet disponíveis no S3/MinIO
     * 
     * POST /query-builder/parquet-files
     * 
     * Body (opcional):
     * {
     *     "path": "s3://lab01/bronze"
     * }
     */
    public function listParquetFiles()
    {
        $json = $this->request->getJSON(true);
        $path = $json['path'] ?? null;
        
        // DEBUG: Log para diagnóstico
        log_message('debug', '[QueryBuilder] listParquetFiles recebeu path: ' . var_export($path, true));
        log_message('debug', '[QueryBuilder] Session user ID: ' . ($_SESSION['id_usuario_logado'] ?? 'NOT SET'));
        
        // Se path não fornecido, usa bucket do usuário na camada raw (arquivos brutos)
        if (empty($path)) {
            $path = SessionHelper::getUserS3Path('/raw');
            log_message('debug', '[QueryBuilder] Path vazio, usando getUserS3Path(\'/raw\'): ' . var_export($path, true));
        }
        
        // Segurança: validar se path pertence ao usuário
        if (!SessionHelper::validateUserS3Path($path)) {
            log_message('warning', '[QueryBuilder] Path rejeitado por segurança: ' . $path);
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'error' => 'Acesso negado: path inválido para este usuário',
                    'files' => []
                ]);
        }
        
        $files = DuckDBHelper::listParquetFiles($path);
        
        return $this->response->setJSON([
            'success' => true,
            'files' => $files,
            'path' => $path
        ]);
    }
    
    /**
     * Sanitiza SQL para evitar injeção
     * 
     * ⚠️ Esta é uma sanitização BÁSICA. Para produção, considere usar prepared statements
     */
    private function _sanitizeSql(string $sql): string
    {
        // Remove comentários perigosos
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        $sql = preg_replace('/--.*/m', '', $sql);
        
        // Limita comprimento
        if (strlen($sql) > 5000) {
            throw new \Exception('Query too long (max 5000 chars)');
        }
        
        return $sql;
    }
    
    /**
     * Debug endpoint para verificar configuração do usuário
     * GET /query-builder/debug
     */
    public function debug()
    {
        $userData = SessionHelper::getUserData();
        $duckdbHealth = DuckDBHelper::healthCheck();
        
        // Testa listagem de arquivos
        $parquetFiles = [];
        if ($userData['bucket'] ?? false) {
            $parquetFiles = DuckDBHelper::listParquetFiles($userData['s3_path']);
        }
        
        return $this->response->setJSON([
            'user' => $userData,
            'duckdb' => $duckdbHealth,
            'files_count' => count($parquetFiles),
            'sample_files' => array_slice($parquetFiles, 0, 5)
        ]);
    }
}
