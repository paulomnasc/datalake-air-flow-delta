<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Helpers\DuckDBHelper;
use App\Helpers\SessionHelper;

/**
 * CodeEditorController
 * 
 * Interface avançada com Monaco Editor para execução de queries SQL em Parquet via DuckDB
 * 
 * Rotas:
 * - GET  /code-editor              → Exibe interface web com Monaco Editor
 * - POST /code-editor/execute      → Executa query
 * - POST /code-editor/tables       → Lista tabelas
 * - POST /code-editor/schema       → Obtém schema
 * - POST /code-editor/files        → Lista arquivos Parquet
 */
class CodeEditorController extends BaseController
{
    /**
     * Exibe interface web para Code Editor com Monaco
     */
    public function index()
    {
        // Verifica saúde da API DuckDB
        $duckdbStatus = DuckDBHelper::healthCheck();
        
        // Obtém bucket do usuário logado
        $userBucket = SessionHelper::getUserBucket();
        // Aponta para o bucket raiz (sem camada específica)
        $userS3Path = SessionHelper::getUserS3Path('');
        
        // Lista arquivos Parquet do bucket do usuário (todas as camadas)
        $parquetFiles = [];
        if ($userBucket) {
            $parquetFiles = DuckDBHelper::listParquetFiles($userS3Path);
        }
        
        return view('code_editor/index', [
            'duckdbStatus' => $duckdbStatus,
            'parquetFiles' => $parquetFiles,
            'userBucket' => $userBucket,
            'userS3Path' => $userS3Path
        ]);
    }
    
    /**
     * Executa uma query SQL
     * 
     * POST /code-editor/execute
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
        if ($userBucket && preg_match('/s3:\\/\\/user-(\\d+)/', $sql, $matches)) {
            $queryBucket = "user-{$matches[1]}";
            if ($queryBucket !== $userBucket) {
                return $this->response
                    ->setStatusCode(403)
                    ->setJSON([
                        'success' => false,
                        'error' => 'Acesso negado: você não pode consultar dados de outros usuários'
                    ]);
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
     * POST /code-editor/tables
     */
    public function listTables()
    {
        $result = DuckDBHelper::query("SHOW TABLES");
        return $this->response->setJSON($result);
    }
    
    /**
     * Obtém schema de uma tabela
     * 
     * POST /code-editor/schema
     * 
     * Body:
     * {
     *     "table": "my_table"
     * }
     */
    public function getSchema()
    {
        $table = $this->request->getJSON()->table ?? '';
        
        if (empty($table)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'success' => false,
                    'error' => 'Table name cannot be empty'
                ]);
        }
        
        $sql = "DESCRIBE " . preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $result = DuckDBHelper::query($sql);
        
        return $this->response->setJSON($result);
    }
    
    /**
     * Lista arquivos Parquet disponíveis para o usuário
     * 
     * POST /code-editor/files
     */
    public function listParquetFiles()
    {
        $path = $this->request->getJSON()->path ?? '';
        
        log_message('debug', '[CodeEditor] listParquetFiles recebeu path: ' . var_export($path, true));
        log_message('debug', '[CodeEditor] Session user ID: ' . ($_SESSION['id_usuario_logado'] ?? 'NOT SET'));
        
        // Se path vazio, usa path do usuário logado
        if (empty($path)) {
            $path = SessionHelper::getUserS3Path('/raw');
            log_message('debug', '[CodeEditor] Path vazio, usando getUserS3Path(\'/raw\'): ' . var_export($path, true));
        }
        
        // Validação de segurança: impedir acesso a buckets de outros usuários
        if (!$this->_isPathAllowed($path)) {
            log_message('warning', '[CodeEditor] Path rejeitado por segurança: ' . $path);
            return $this->response
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'error' => 'Acesso negado a este caminho'
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
     * Valida se um path SQL é seguro
     */
    private function _sanitizeSql(string $sql): string
    {
        // Remove comandos potencialmente perigosos
        $dangerous = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE'];
        
        foreach ($dangerous as $cmd) {
            if (stripos($sql, $cmd) !== false) {
                throw new \RuntimeException("Comando {$cmd} não permitido");
            }
        }
        
        return $sql;
    }
    
    /**
     * Verifica se um path S3 é permitido para o usuário logado
     */
    private function _isPathAllowed(string $path): bool
    {
        $userBucket = SessionHelper::getUserBucket();
        
        // Se não tiver bucket do usuário, permite (modo admin)
        if (!$userBucket) {
            return true;
        }
        
        // Verifica se o path começa com o bucket do usuário
        return str_starts_with($path, "s3://{$userBucket}/");
    }
}
