<?php

namespace App\Controllers;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class TestMinioController extends BaseController
{
    private $minioClient;
    private $bucketName;

    public function __construct()
    {
        $this->_initMinioClient();
    }

    private function _initMinioClient()
    {
        $endpoint = getenv('MINIO_ENDPOINT');
        $region = getenv('MINIO_REGION');
        $version = getenv('MINIO_VERSION');
        $usePathStyle = getenv('MINIO_USE_PATH_STYLE_ENDPOINT');
        $key = getenv('MINIO_ACCESS_KEY_ID');
        $secret = getenv('MINIO_SECRET_ACCESS_KEY');
        
        $this->bucketName = getenv('MINIO_BUCKET_RAW');
        
        echo "📋 Configuração do MinIO:<br>";
        echo "Endpoint: {$endpoint}<br>";
        echo "Region: {$region}<br>";
        echo "Version: {$version}<br>";
        echo "UsePathStyle: {$usePathStyle}<br>";
        echo "Key: " . (empty($key) ? 'NÃO DEFINIDO' : '***') . "<br>";
        echo "Secret: " . (empty($secret) ? 'NÃO DEFINIDO' : '***') . "<br>";
        echo "Bucket: {$this->bucketName}<br><br>";
        
        if (empty($endpoint) || empty($region) || empty($key) || empty($secret) || empty($this->bucketName)) {
            echo "❌ Configuração incompleta do MinIO no .env<br>";
            return;
        }
        
        $minioConfig = [
            'endpoint' => $endpoint, 
            'region' => $region,
            'version' => $version,
            'use_path_style_endpoint' => filter_var($usePathStyle, FILTER_VALIDATE_BOOLEAN), 
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ];

        try {
            $this->minioClient = new S3Client($minioConfig);
            echo "✅ S3Client (MinIO) inicializado com sucesso<br><br>";
        } catch (\Exception $e) {
            echo "❌ Falha ao inicializar S3Client: " . $e->getMessage() . "<br>";
            $this->minioClient = null; 
        }
    }

    public function testConnection()
    {
        if (!$this->minioClient) {
            return "❌ MinIO não está inicializado";
        }

        try {
            echo "Testando conexão com MinIO...<br>";
            
            // Listar buckets
            $result = $this->minioClient->listBuckets();
            echo "✅ Conexão com MinIO funciona!<br>";
            echo "Buckets disponíveis:<br>";
            foreach ($result['Buckets'] as $bucket) {
                echo "  - " . $bucket['Name'] . "<br>";
            }
        } catch (AwsException $e) {
            echo "❌ Erro AWS: " . $e->getAwsErrorMessage() . "<br>";
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
    }

    public function testUpload()
    {
        if (!$this->minioClient) {
            return "❌ MinIO não está inicializado";
        }

        try {
            echo "Testando upload para MinIO...<br>";
            
            // Criar um arquivo temporário
            $testFile = tempnam(sys_get_temp_dir(), 'test_');
            file_put_contents($testFile, "Teste de upload - " . date('Y-m-d H:i:s'));
            
            echo "Arquivo temp: {$testFile}<br>";
            echo "Tamanho: " . filesize($testFile) . " bytes<br>";
            
            // Fazer upload
            $result = $this->minioClient->putObject([
                'Bucket' => $this->bucketName,
                'Key'    => 'test/teste_upload_' . time() . '.txt',
                'Body'   => fopen($testFile, 'rb'),
            ]);
            
            echo "✅ Upload bem-sucedido!<br>";
            echo "ETag: " . $result['ETag'] . "<br>";
            
            // Limpar
            unlink($testFile);
            
        } catch (AwsException $e) {
            echo "❌ Erro AWS: " . $e->getAwsErrorMessage() . "<br>";
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "<br>";
        }
    }
}
