<?php

namespace App\Helpers;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class MinioHelper
{
    private static $client = null;

    /**
     * Inicializa o cliente MinIO S3
     * 
     * @return S3Client
     */
    private static function getClient()
    {
        if (self::$client === null) {
            self::$client = new S3Client([
                'version' => 'latest',
                'region' => getenv('MINIO_REGION') ?: 'us-east-1',
                'endpoint' => getenv('MINIO_ENDPOINT') ?: 'http://minio:9000',
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => getenv('MINIO_ACCESS_KEY_ID') ?: 'admin',
                    'secret' => getenv('MINIO_SECRET_ACCESS_KEY') ?: 'admin123',
                ],
            ]);
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
            $client->headBucket(['Bucket' => $bucketName]);
            return true;
        } catch (AwsException $e) {
            // Bucket não existe ou erro de acesso
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
            
            // Valida nome do bucket (S3 rules)
            if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $bucketName)) {
                return [
                    'success' => false,
                    'message' => 'Nome de bucket inválido. Use apenas letras minúsculas, números e hífens.'
                ];
            }

            if (strlen($bucketName) < 3 || strlen($bucketName) > 63) {
                return [
                    'success' => false,
                    'message' => 'Nome do bucket deve ter entre 3 e 63 caracteres.'
                ];
            }

            $client->createBucket([
                'Bucket' => $bucketName,
            ]);

            return [
                'success' => true,
                'message' => "Bucket '{$bucketName}' criado com sucesso."
            ];
        } catch (AwsException $e) {
            return [
                'success' => false,
                'message' => 'Erro ao criar bucket: ' . $e->getMessage()
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
        if (self::bucketExists($bucketName)) {
            return [
                'success' => true,
                'message' => "Bucket '{$bucketName}' já existe.",
                'created' => false
            ];
        }

        $result = self::createBucket($bucketName);
        
        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'created' => $result['success']
        ];
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
            $result = $client->listBuckets();
            
            $buckets = [];
            foreach ($result['Buckets'] as $bucket) {
                $buckets[] = $bucket['Name'];
            }
            
            return $buckets;
        } catch (AwsException $e) {
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
        $bucketName = \App\Helpers\AirflowHelper::buildUsernameFromEmail($email, $userId);
        $result = self::ensureBucketExists($bucketName);
        
        return array_merge($result, ['bucket_name' => $bucketName]);
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
            $totalSize = 0;
            
            // Verificar se o bucket existe antes de tentar listar objetos
            if (!self::bucketExists($bucketName)) {
                log_message('info', "Bucket '{$bucketName}' não existe. Retornando tamanho 0.");
                return 0;
            }
            
            // Listar todos os objetos do bucket
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
            
            log_message('info', "Bucket '{$bucketName}' possui {$totalSize} bytes de armazenamento usado.");
            return $totalSize;
            
        } catch (AwsException $e) {
            log_message('error', "Erro ao calcular uso de armazenamento do bucket '{$bucketName}': " . $e->getMessage());
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
        // Obter limite do .env (padrão: 1GB)
        $storageLimit = (int) (getenv('MINIO_USER_STORAGE_LIMIT') ?: 1073741824);
        
        // Calcular uso atual
        $currentUsage = self::getBucketStorageUsage($bucketName);
        
        // Calcular uso futuro se o upload for realizado
        $futureUsage = $currentUsage + $newFileSize;
        
        // Calcular espaço disponível
        $available = $storageLimit - $currentUsage;
        
        $allowed = $futureUsage <= $storageLimit;
        
        // Formatar mensagem
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
        
        return [
            'allowed' => $allowed,
            'current_usage' => $currentUsage,
            'limit' => $storageLimit,
            'available' => $available,
            'new_file_size' => $newFileSize,
            'future_usage' => $futureUsage,
            'message' => $message
        ];
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
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
