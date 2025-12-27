<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Helpers\DuckDBHelper;

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
        
        // Lista arquivos Parquet disponíveis
        $parquetFiles = DuckDBHelper::listParquetFiles('s3://lab01/bronze');
        
        return view('query_builder/index', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
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
        $path = trim($this->request->getJSON()->path ?? 's3://lab01');
        
        if (empty($path)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error' => 'Path cannot be empty'
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
        $path = $json['path'] ?? 's3://lab01/bronze';
        
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
}
