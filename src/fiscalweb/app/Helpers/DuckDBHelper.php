<?php

namespace App\Helpers;

use CodeIgniter\HTTP\CURLRequest;

/**
 * DuckDBHelper
 * 
 * Facilita consultas SQL em Parquet via API DuckDB
 * 
 * Uso:
 * 
 * $result = DuckDBHelper::query(
 *     "SELECT * FROM read_parquet('s3://lab01/bronze/customers.parquet') LIMIT 10"
 * );
 * 
 * if ($result['success']) {
 *     print_r($result['data']);
 * } else {
 *     echo "Erro: " . $result['error'];
 * }
 */
class DuckDBHelper
{
    private static $duckdbApi = 'http://duckdb-api:5000';
    
    /**
     * Executa uma query SQL contra Parquet no MinIO
     * 
     * @param string $sql Query SQL
     * @param int $limit Limite de resultados (default 1000)
     * @return array Resultado com ['success', 'data', 'columns', 'rows_affected', 'error']
     */
    public static function query(string $sql, int $limit = 1000): array
    {
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->post(self::$duckdbApi . '/query', [
                'json' => [
                    'sql' => $sql,
                    'limit' => $limit
                ],
                'timeout' => 30,
                'connect_timeout' => 5,
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            
            // Se DuckDB retornou erro
            if ($statusCode >= 400 || (isset($body['success']) && !$body['success'])) {
                $errorMsg = $body['error'] ?? $body['message'] ?? "DuckDB error (HTTP {$statusCode})";
                log_message('warning', "⚠️ DuckDB Query Warning: " . substr($sql, 0, 100) . " -> " . $errorMsg);
                
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
            
            log_message('info', "✅ DuckDB Query Executed: " . substr($sql, 0, 100));
            
            return $body ?? [
                'success' => false,
                'error' => 'Invalid response from DuckDB API'
            ];
            
        } catch (\Throwable $e) {
            log_message('error', "❌ DuckDB Query Error: " . $e->getMessage());
            
            // Detectar timeouts
            if (stripos($e->getMessage(), 'timeout') !== false || stripos($e->getMessage(), 'timed out') !== false) {
                return [
                    'success' => false,
                    'error' => 'Query timeout: A requisição demorou muito ou o arquivo não é acessível. Verifique se o caminho S3 está correto.'
                ];
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lista todas as tabelas/views disponíveis no DuckDB
     * 
     * @return array Lista de tabelas
     */
    public static function listTables(): array
    {
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->post(self::$duckdbApi . '/query/tables', [
                'timeout' => 10,
                'http_errors' => false
            ]);
            
            $body = json_decode($response->getBody(), true);
            
            return $body['tables'] ?? [];
            
        } catch (\Exception $e) {
            log_message('error', "❌ DuckDB List Tables Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Lista arquivos Parquet disponíveis no S3/MinIO
     * 
     * @param string $path S3 path (ex: s3://eng-147 para listar todas as camadas)
     * @return array Lista de arquivos
     */
    public static function listParquetFiles(string $path = null): array
    {
        // Se path não fornecido, usa bucket raiz do usuário para listar todas as camadas
        if (empty($path)) {
            $userBucket = \App\Helpers\SessionHelper::getUserBucket();
            $path = $userBucket ? "s3://{$userBucket}" : 's3://lab01';
        }
        
        // DEBUG: Log para diagnóstico (opcional - pode não estar disponível em teste)
        if (function_exists('log_message')) {
            log_message('debug', '[DuckDBHelper] listParquetFiles chamado com path: ' . ($path ?: 'null'));
        }
        
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->post(self::$duckdbApi . '/query/parquet-files', [
                'json' => ['path' => $path],
                'timeout' => 10,
                'http_errors' => false
            ]);
            
            $body = json_decode($response->getBody(), true);
            
            // DEBUG: Log resposta completa do DuckDB
            log_message('debug', '[DuckDBHelper] DuckDB Response: ' . json_encode($body));
            log_message('debug', '[DuckDBHelper] DuckDB retornou ' . count($body['files'] ?? []) . ' arquivos para path: ' . $path);
            if (!empty($body['files'])) {
                log_message('debug', '[DuckDBHelper] Primeiro arquivo: ' . ($body['files'][0][0] ?? 'N/A'));
            }
            
            return $body['files'] ?? [];
            
        } catch (\Exception $e) {
            log_message('error', "❌ DuckDB List Parquet Files Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém schema de um arquivo Parquet
     * 
     * @param string $path S3 path (ex: s3://lab01/bronze/customers)
     * @return array Estrutura com nomes e tipos das colunas
     */
    public static function getSchema(string $path = 's3://lab01'): array
    {
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->post(self::$duckdbApi . '/query/schema', [
                'json' => ['path' => $path],
                'timeout' => 10,
                'http_errors' => false
            ]);
            
            $body = json_decode($response->getBody(), true);
            
            return $body['columns'] ?? [];
            
        } catch (\Exception $e) {
            log_message('error', "❌ DuckDB Get Schema Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verifica se um arquivo existe no S3/MinIO
     * 
     * @param string $s3Path Caminho S3 (ex: s3://admin-146/bronze/arquivo.parquet)
     * @return bool True se arquivo existe
     */
    public static function fileExists(string $s3Path): bool
    {
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->post(self::$duckdbApi . '/query', [
                'json' => [
                    'sql' => "SELECT COUNT(*) FROM read_parquet('{$s3Path}') LIMIT 1",
                    'limit' => 1
                ],
                'timeout' => 10,
                'connect_timeout' => 5,
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody(), true);
            
            // Se conseguiu ler o arquivo, ele existe
            return ($statusCode === 200 && ($body['success'] ?? false));
            
        } catch (\Throwable $e) {
            // Se houver erro, arquivo não existe ou não é acessível
            return false;
        }
    }
    
    /**
     * Verifica saúde da API DuckDB
     * 
     * @return bool True se API está respondendo
     */
    public static function healthCheck(): bool
    {
        try {
            $client = \Config\Services::curlrequest();
            
            $response = $client->get(self::$duckdbApi . '/health', [
                'timeout' => 5,
                'http_errors' => false
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
