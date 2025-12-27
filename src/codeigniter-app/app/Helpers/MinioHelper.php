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
     * Cria o bucket do usuário com base no ID
     * Formato: user-{id}
     * 
     * @param int $userId ID do usuário
     * @return array ['success' => bool, 'message' => string, 'bucket_name' => string]
     */
    public static function createUserBucket(int $userId): array
    {
        $bucketName = "user-" . $userId;
        $result = self::ensureBucketExists($bucketName);
        
        return array_merge($result, ['bucket_name' => $bucketName]);
    }
}
