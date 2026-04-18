<?php

namespace App\Helpers;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioHelper
{
    /**
     * Realiza upload de objeto para o MinIO e loga bucket, key e endpoint
     *
     * @param string $bucketName Nome do bucket
     * @param string $key Caminho/Key do objeto
     * @param mixed $body Conteúdo do arquivo
     * @param array $options Opções adicionais para putObject
     * @return array ['success' => bool, 'result' => mixed, 'error' => string]
     */
    public static function uploadObject(string $bucketName, string $key, $body, array $options = []): array
    {
        try {
            $client = self::getClient();
            $endpoint = getenv('MINIO_ENDPOINT') ?: 'http://minio:9000';
            log_message('debug', "[MinioHelper] putObject: Bucket={$bucketName}, Key={$key}, Endpoint={$endpoint}");
            $result = $client->putObject(array_merge([
                'Bucket' => $bucketName,
                'Key' => $key,
                'Body' => $body
            ], $options));
            log_message('debug', "[MinioHelper] putObject: Resultado=" . json_encode($result));
            return ['success' => true, 'result' => $result, 'error' => ''];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] putObject: Exception: " . $e->getMessage());
            return ['success' => false, 'result' => null, 'error' => $e->getMessage()];
        }
    }
    
    private static $client = null;

    /**
     * Inicializa o cliente MinIO S3
     * 
     * @return S3Client
     */
    private static function getClient()
    {
        if (self::$client === null) {
            try {
                $region = getenv('MINIO_REGION') ?: 'us-east-1';
                $endpoint = getenv('MINIO_ENDPOINT') ?: 'http://minio:9000';
                $key = getenv('MINIO_ACCESS_KEY_ID') ?: 'admin';
                $secret = getenv('MINIO_SECRET_ACCESS_KEY') ?: 'admin123';
                log_message('debug', '[MinioHelper] Inicializando S3Client com endpoint=' . $endpoint . ', region=' . $region . ', key=' . $key);
                self::$client = new S3Client([
                    'version' => 'latest',
                    'region' => $region,
                    'endpoint' => $endpoint,
                    'use_path_style_endpoint' => true,
                    'credentials' => [
                        'key' => $key,
                        'secret' => $secret,
                    ],
                ]);
            } catch (\Exception $e) {
                log_message('error', '[MinioHelper] getClient: Exception ao inicializar S3Client: ' . $e->getMessage());
                throw $e;
                
            }
        }
        return self::$client;
    }

    /**
     * Verifica se um bucket existe
     * 
     * @param string $bucketName Nome do bucket
     * @return bool True se existe, False caso contrário
     */
    public static function bucketExists(string $bucketName): bool
    {
        try {
            $client = self::getClient();
            try {
                $client->headBucket(['Bucket' => $bucketName]);
                log_message('debug', "[MinioHelper] bucketExists: Bucket '{$bucketName}' existe.");
                return true;
            } catch (AwsException $e) {
                log_message('error', "[MinioHelper] bucketExists: Exception: " . $e->getMessage());
                return false;
            }
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] bucketExists: Exception (getClient): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo bucket no MinIO
     * 
     * @param string $bucketName Nome do bucket a ser criado
     * @return array ['success' => bool, 'message' => string]
     */
    public static function createBucket(string $bucketName): array
    {
        try {
            $client = self::getClient();
            try {
                // Valida nome do bucket (S3 rules)
                if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $bucketName)) {
                    log_message('debug', "[MinioHelper] createBucket: Nome de bucket inválido: {$bucketName}");
                    return [
                        'success' => false,
                        'message' => 'Nome de bucket inválido. Use apenas letras minúsculas, números e hífens.'
                    ];
                }
                if (strlen($bucketName) < 3 || strlen($bucketName) > 63) {
                    log_message('debug', "[MinioHelper] createBucket: Nome do bucket fora do tamanho permitido: {$bucketName}");
                    return [
                        'success' => false,
                        'message' => 'Nome do bucket deve ter entre 3 e 63 caracteres.'
                    ];
                }
                $client->createBucket([
                    'Bucket' => $bucketName,
                ]);
                log_message('debug', "[MinioHelper] createBucket: Bucket '{$bucketName}' criado com sucesso.");
                return [
                    'success' => true,
                    'message' => "Bucket '{$bucketName}' criado com sucesso."
                ];
            } catch (AwsException $e) {
                log_message('error', "[MinioHelper] createBucket: Exception: " . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Erro ao criar bucket: ' . $e->getMessage()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] createBucket: Exception (getClient): " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao inicializar cliente MinIO: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Garante que um bucket existe (cria se não existir)
     * 
     * @param string $bucketName Nome do bucket
     * @return array ['success' => bool, 'message' => string, 'created' => bool]
     */
    public static function ensureBucketExists(string $bucketName): array
    {
        try {
            if (self::bucketExists($bucketName)) {
                log_message('debug', "[MinioHelper] ensureBucketExists: Bucket '{$bucketName}' já existe.");
                return [
                    'success' => true,
                    'message' => "Bucket '{$bucketName}' já existe.",
                    'created' => false
                ];
            }
            $result = self::createBucket($bucketName);
            log_message('debug', "[MinioHelper] ensureBucketExists: Resultado ao criar bucket '{$bucketName}': " . json_encode($result));
            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'created' => $result['success']
            ];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] ensureBucketExists: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao garantir bucket: ' . $e->getMessage(),
                'created' => false
            ];
        }
    }

    /**
     * Lista todos os buckets disponíveis
     * 
     * @return array Lista de nomes de buckets
     */
    public static function listBuckets(): array
    {
        try {
            $client = self::getClient();
            try {
                $result = $client->listBuckets();
                $buckets = [];
                foreach ($result['Buckets'] as $bucket) {
                    $buckets[] = $bucket['Name'];
                }
                log_message('debug', '[MinioHelper] listBuckets: Buckets encontrados: ' . implode(', ', $buckets));
                return $buckets;
            } catch (AwsException $e) {
                log_message('error', '[MinioHelper] listBuckets: Exception: ' . $e->getMessage());
                return [];
            }
        } catch (\Exception $e) {
            log_message('error', '[MinioHelper] listBuckets: Exception (getClient): ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Cria o bucket do usuário com base no ID e email
     * Formato: {username-prefix}-{id} (alinhado com AirflowHelper::buildUsernameFromEmail)
     * Exemplo: eng-147, joao-89, etc.
     * 
     * @param int $userId ID do usuário
     * @param string $email Email do usuário (para extrair prefixo)
     * @return array ['success' => bool, 'message' => string, 'bucket_name' => string]
     */
    public static function createUserBucket(int $userId, string $email = ''): array
    {
        // Usar o mesmo padrão do Airflow username
        try {
            $bucketName = \App\Helpers\AirflowHelper::buildUsernameFromEmail($email, $userId);
            $result = self::ensureBucketExists($bucketName);
            log_message('debug', "[MinioHelper] createUserBucket: Resultado para bucket '{$bucketName}': " . json_encode($result));
            return array_merge($result, ['bucket_name' => $bucketName]);
        } catch (\Exception $e) {
            log_message('error', '[MinioHelper] createUserBucket: Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar bucket de usuário: ' . $e->getMessage(),
                'bucket_name' => ''
            ];
        }
    }

    /**
     * Calcula o tamanho total de armazenamento usado por um bucket específico
     * 
     * @param string $bucketName Nome do bucket
     * @return int Tamanho total em bytes
     */
    public static function getBucketStorageUsage(string $bucketName): int
    {
        try {
            $client = self::getClient();
            try {
                $totalSize = 0;
                if (!self::bucketExists($bucketName)) {
                    log_message('debug', "[MinioHelper] getBucketStorageUsage: Bucket '{$bucketName}' não existe. Retornando tamanho 0.");
                    return 0;
                }
                $paginator = $client->getPaginator('ListObjects', [
                    'Bucket' => $bucketName
                ]);
                foreach ($paginator as $result) {
                    if (isset($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            $totalSize += $object['Size'];
                        }
                    }
                }
                log_message('debug', "[MinioHelper] getBucketStorageUsage: Bucket '{$bucketName}' possui {$totalSize} bytes de armazenamento usado.");
                return $totalSize;
            } catch (AwsException $e) {
                log_message('error', "[MinioHelper] getBucketStorageUsage: Exception: " . $e->getMessage());
                return 0;
            }
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] getBucketStorageUsage: Exception (getClient): " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verifica se o usuário ainda tem espaço disponível para upload
     * 
     * @param string $bucketName Nome do bucket do usuário
     * @param int $newFileSize Tamanho do(s) novo(s) arquivo(s) em bytes
     * @return array ['allowed' => bool, 'current_usage' => int, 'limit' => int, 'available' => int, 'message' => string]
     */
    public static function checkStorageLimit(string $bucketName, int $newFileSize = 0): array
    {
        try {
            $storageLimit = (int) (getenv('MINIO_USER_STORAGE_LIMIT') ?: 1073741824);
            $currentUsage = self::getBucketStorageUsage($bucketName);
            $futureUsage = $currentUsage + $newFileSize;
            $available = $storageLimit - $currentUsage;
            $allowed = $futureUsage <= $storageLimit;
            if ($allowed) {
                $message = sprintf(
                    'Upload permitido. Uso atual: %s / %s (%.1f%%). Espaço disponível: %s',
                    self::formatBytes($currentUsage),
                    self::formatBytes($storageLimit),
                    ($currentUsage / $storageLimit) * 100,
                    self::formatBytes($available)
                );
            } else {
                $message = sprintf(
                    'Limite de armazenamento excedido! Uso atual: %s / %s (%.1f%%). Espaço necessário: %s, mas apenas %s disponível.',
                    self::formatBytes($currentUsage),
                    self::formatBytes($storageLimit),
                    ($currentUsage / $storageLimit) * 100,
                    self::formatBytes($newFileSize),
                    self::formatBytes($available)
                );
            }
            $result = [
                'allowed' => $allowed,
                'current_usage' => $currentUsage,
                'limit' => $storageLimit,
                'available' => $available,
                'new_file_size' => $newFileSize,
                'future_usage' => $futureUsage,
                'message' => $message
            ];
            log_message('debug', '[MinioHelper] checkStorageLimit: Resultado para bucket ' . $bucketName . ': ' . json_encode($result));
            return $result;
        } catch (\Exception $e) {
            log_message('error', '[MinioHelper] checkStorageLimit: Exception: ' . $e->getMessage());
            return [
                'allowed' => false,
                'current_usage' => 0,
                'limit' => 0,
                'available' => 0,
                'new_file_size' => $newFileSize,
                'future_usage' => 0,
                'message' => 'Erro ao verificar limite de armazenamento: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Formata bytes para formato legível (KB, MB, GB)
     * 
     * @param int $bytes Tamanho em bytes
     * @param int $precision Precisão decimal
     * @return string Tamanho formatado
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        try {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            $formatted = round($bytes, $precision) . ' ' . $units[$i];
            log_message('debug', '[MinioHelper] formatBytes: ' . $formatted);
            return $formatted;
        } catch (\Exception $e) {
            log_message('error', '[MinioHelper] formatBytes: Exception: ' . $e->getMessage());
            return '0 B';
        }
    }

    /**
     * Deleta um único objeto do MinIO
     * 
     * @param string $bucketName Nome do bucket
     * @param string $key Caminho/Key do objeto
     * @return array ['success' => bool, 'message' => string]
     */
    public static function deleteObject(string $bucketName, string $key): array
    {
        try {
            $client = self::getClient();
            log_message('debug', "[MinioHelper] deleteObject: Bucket={$bucketName}, Key={$key}");
            
            $client->deleteObject([
                'Bucket' => $bucketName,
                'Key' => $key
            ]);
            
            log_message('debug', "[MinioHelper] deleteObject: Objeto '{$key}' deletado com sucesso.");
            return [
                'success' => true,
                'message' => "Objeto '{$key}' deletado com sucesso."
            ];
        } catch (AwsException $e) {
            log_message('error', "[MinioHelper] deleteObject: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar objeto: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] deleteObject: Exception (getClient): " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao inicializar cliente MinIO: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Deleta múltiplos objetos do MinIO (batch delete)
     * 
     * @param string $bucketName Nome do bucket
     * @param array $keys Array de caminhos/keys dos objetos
     * @return array ['success' => bool, 'message' => string, 'deleted_count' => int, 'errors' => array]
     */
    public static function deleteObjects(string $bucketName, array $keys): array
    {
        try {
            if (empty($keys)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum objeto especificado para deletar.',
                    'deleted_count' => 0,
                    'errors' => []
                ];
            }
            
            $client = self::getClient();
            log_message('debug', "[MinioHelper] deleteObjects: Bucket={$bucketName}, Keys=" . json_encode($keys));
            
            // Formata keys para o formato esperado pela AWS SDK
            $objects = array_map(function($key) {
                return ['Key' => $key];
            }, $keys);
            
            $result = $client->deleteObjects([
                'Bucket' => $bucketName,
                'Delete' => [
                    'Objects' => $objects
                ]
            ]);
            
            $deleted = $result['Deleted'] ?? [];
            $errors = $result['Errors'] ?? [];
            $deletedCount = count($deleted);
            
            log_message('debug', "[MinioHelper] deleteObjects: {$deletedCount} objetos deletados.");
            
            if (count($errors) > 0) {
                log_message('warning', "[MinioHelper] deleteObjects: Erros encontrados: " . json_encode($errors));
            }
            
            return [
                'success' => count($errors) === 0,
                'message' => "{$deletedCount} objeto(s) deletado(s) com sucesso." . 
                            (count($errors) > 0 ? " {count($errors)} erro(s) encontrado(s)." : ""),
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ];
        } catch (AwsException $e) {
            log_message('error', "[MinioHelper] deleteObjects: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar objetos: ' . $e->getMessage(),
                'deleted_count' => 0,
                'errors' => []
            ];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] deleteObjects: Exception (getClient): " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao inicializar cliente MinIO: ' . $e->getMessage(),
                'deleted_count' => 0,
                'errors' => []
            ];
        }
    }

    /**
     * Deleta uma pasta inteira (prefix) do MinIO
     * Lista todos os objetos com o prefix e deleta em batch
     * 
     * @param string $bucketName Nome do bucket
     * @param string $prefix Prefixo/pasta a deletar (ex: 'bronze/dados/')
     * @return array ['success' => bool, 'message' => string, 'deleted_count' => int]
     */
    public static function deleteFolder(string $bucketName, string $prefix): array
    {
        try {
            $client = self::getClient();
            
            // Garante que o prefix termine com /
            if (!empty($prefix) && substr($prefix, -1) !== '/') {
                $prefix .= '/';
            }
            
            log_message('debug', "[MinioHelper] deleteFolder: Bucket={$bucketName}, Prefix={$prefix}");
            
            // Lista todos os objetos com o prefix
            $objects = [];
            $paginator = $client->getPaginator('ListObjects', [
                'Bucket' => $bucketName,
                'Prefix' => $prefix
            ]);
            
            foreach ($paginator as $result) {
                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        $objects[] = $object['Key'];
                    }
                }
            }
            
            if (empty($objects)) {
                log_message('debug', "[MinioHelper] deleteFolder: Nenhum objeto encontrado com prefix '{$prefix}'.");
                return [
                    'success' => true,
                    'message' => "Pasta '{$prefix}' não contém objetos ou não existe.",
                    'deleted_count' => 0
                ];
            }
            
            log_message('debug', "[MinioHelper] deleteFolder: Encontrados " . count($objects) . " objetos para deletar.");
            
            // Deleta em batch
            $deleteResult = self::deleteObjects($bucketName, $objects);
            
            return [
                'success' => $deleteResult['success'],
                'message' => "Pasta '{$prefix}' deletada. " . $deleteResult['message'],
                'deleted_count' => $deleteResult['deleted_count'],
                'errors' => $deleteResult['errors'] ?? []
            ];
        } catch (AwsException $e) {
            log_message('error', "[MinioHelper] deleteFolder: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar pasta: ' . $e->getMessage(),
                'deleted_count' => 0
            ];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] deleteFolder: Exception (getClient): " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao inicializar cliente MinIO: ' . $e->getMessage(),
                'deleted_count' => 0
            ];
        }
    }


        /**
     * Exporta uma tabela SQL para CSV e faz upload para o MinIO
     *
     * @param string $dsn DSN de conexão PDO
     * @param string $user Usuário do banco
     * @param string $password Senha do banco
     * @param string $tableName Nome da tabela a exportar
     * @param string $bucket Nome do bucket MinIO
     * @param string $dagId Identificador da DAG (para path)
     * @param object|null $minioClient Cliente MinIO (opcional, se não usar helper)
     * @return array ['success' => bool, 'minio_path' => string, 'error' => string]
     */
    public static function exportSqlTableToCsvAndUpload($dsn, $user, $password, $tableName, $bucket, $dagId, $minioClient = null)
    {
        try {
            if (empty($user) || empty($password)) {
                log_message('error', "[MinioHelper] exportSqlTableToCsvAndUpload: Usuário ou senha do banco não informados!");
                return ['success' => false, 'minio_path' => '', 'error' => 'Usuário ou senha do banco não informados'];
            }
            $pdo = new \PDO($dsn, $user, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $driver = explode(':', $dsn)[0];
            $quote = ($driver === 'pgsql') ? '"' : '`';
            $stmt = $pdo->query("SELECT * FROM {$quote}{$tableName}{$quote}");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows) === 0) {
                log_message('warning', "[MinioHelper] Tabela '{$tableName}' está vazia. Nenhum CSV será gerado.");
                return ['success' => false, 'minio_path' => '', 'error' => 'Tabela vazia'];
            }
            $timestamp = date('YmdHis');
            $hash = substr(md5($tableName . microtime()), 0, 8);
            $csvName = "{$timestamp}_{$hash}_{$tableName}.csv";
            $tmpCsvPath = sys_get_temp_dir() . "/" . $csvName;
            $fp = fopen($tmpCsvPath, 'w');
            fputcsv($fp, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
            $targetMinioPath = "raw/{$dagId}/{$csvName}";
            if (!$minioClient) {
                $minioClient = self::getClient();
            }
            log_message('info', "[MinioHelper] Enviando CSV '{$csvName}' para bucket '{$bucket}' em '{$targetMinioPath}'");
            $minioClient->putObject([
                'Bucket' => $bucket,
                'Key' => $targetMinioPath,
                'SourceFile' => $tmpCsvPath,
                'ContentType' => 'text/csv',
            ]);
            unlink($tmpCsvPath);
            log_message('info', "[MinioHelper] Upload concluído: tabela '{$tableName}' → '{$targetMinioPath}'");
            return ['success' => true, 'minio_path' => $targetMinioPath, 'error' => ''];
        } catch (\Exception $e) {
            log_message('error', "[MinioHelper] Falha ao exportar/upload tabela '{$tableName}' para bucket '{$bucket}': " . $e->getMessage());
            return ['success' => false, 'minio_path' => '', 'error' => $e->getMessage()];
        }
    }

}
