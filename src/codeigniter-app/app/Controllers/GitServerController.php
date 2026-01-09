<?php

namespace App\Controllers;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class GitServerController extends BaseController
{
    private $s3Client;
    private $minioEndpoint;
    private $minioBucket;
    private $tempClonePath = '/tmp/git-clone';

    public function __construct()
    {
        // Configuração MinIO
        $this->minioEndpoint = getenv('MINIO_ENDPOINT') ?: 'http://minio:9000';
        $this->minioBucket = getenv('MINIO_BUCKET') ?: 'lab01';

        // Inicializar S3 client para MinIO
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region'  => 'us-east-1',
            'endpoint' => $this->minioEndpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => getenv('MINIO_ACCESS_KEY_ID') ?: 'admin',
                'secret' => getenv('MINIO_SECRET_ACCESS_KEY') ?: 'admin123',
            ],
        ]);

        // Garantir que diretório temporário existe
        if (!is_dir($this->tempClonePath)) {
            @mkdir($this->tempClonePath, 0755, true);
        }
    }

    public function cloneRepository()
    {
        $method = strtoupper($this->request->getMethod());
        if ($method !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        // Auth opcional no editor: permitir sem sessão

        $input = $this->request->getJSON();
        $token = $input->token ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $branch = $input->branch ?? 'main';

        if (!$owner || !$repo) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: owner, repo'
            ]);
        }

        // Caminho temporário para clone
        $tempRepoPath = "{$this->tempClonePath}/{$owner}/{$repo}";
        
        // Limpar se já existe
        if (is_dir($tempRepoPath)) {
            $this->_removeDirectory($tempRepoPath);
        }
        @mkdir($tempRepoPath, 0755, true);

        // Clone do repositório (suporta público sem token)
        $cloneUrl = $token
            ? "https://{$owner}:{$token}@github.com/{$owner}/{$repo}.git"
            : "https://github.com/{$owner}/{$repo}.git";
        $cloneCommand = "git clone --depth 1 --branch {$branch} {$cloneUrl} {$tempRepoPath} 2>&1";
        
        $output = [];
        $returnCode = 0;
        exec($cloneCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            // Detectar erro comum de autenticação para mensagem amigável
            $friendly = (stripos($errorMsg, 'Authentication failed') !== false || stripos($errorMsg, 'Invalid username or token') !== false)
                ? 'GitHub authentication failed: invalid token or permissions.'
                : null;
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Git clone failed',
                'message' => $friendly,
                'details' => $errorMsg
            ]);
        }

        // Listar todos os arquivos clonados (excluir .git)
        $files = $this->_getAllFiles($tempRepoPath);
        if (empty($files)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Repository cloned but no files found'
            ]);
        }

        // Fazer upload de CADA arquivo para MinIO (hierarquia: scripts/{owner}/{repo})
        $s3Path = "scripts/{$owner}/{$repo}";
        $uploadedCount = 0;
        $uploadErrors = [];

        foreach ($files as $filePath) {
            try {
                $relativePath = str_replace($tempRepoPath . '/', '', $filePath);
                $s3Key = "{$s3Path}/{$relativePath}";
                
                $this->s3Client->putObject([
                    'Bucket' => $this->minioBucket,
                    'Key'    => $s3Key,
                    'Body'   => fopen($filePath, 'r'),
                    'ACL'    => 'private'
                ]);
                $uploadedCount++;
            } catch (AwsException $e) {
                $uploadErrors[] = "File: {$relativePath} - Error: " . $e->getMessage();
            }
        }

        // Limpeza do diretório temporário
        $this->_removeDirectory($tempRepoPath);

        return $this->response->setStatusCode(200)->setJSON([
            'success' => true,
            'filesCount' => count($files),
            'uploadedCount' => $uploadedCount,
            's3Path' => "s3://{$this->minioBucket}/{$s3Path}",
            'errors' => $uploadErrors ?: null
        ]);
    }

    /**
     * Listar arquivos do repositório clonado no MinIO
     * GET /api/git-files?owner=...&repo=...
     */
    public function listFiles()
    {
        $method = strtoupper($this->request->getMethod());
        
        if ($method !== 'GET') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        // Auth opcional no editor: permitir sem sessão

        $owner = $this->request->getGet('owner');
        $repo = $this->request->getGet('repo');

        if (!$owner || !$repo) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required parameters: owner, repo'
            ]);
        }
        
        try {
            $s3Path = "scripts/{$owner}/{$repo}";
            
            // Listar objetos no MinIO
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->minioBucket,
                'Prefix' => $s3Path . '/'
            ]);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $key = $object['Key'];
                    // Remover prefixo s3Path/ e .git files
                        $relativePath = str_replace($s3Path . '/', '', $key);
                    
                    if (strpos($relativePath, '.git') === false && !empty($relativePath)) {
                        $files[] = [
                            'name' => basename($relativePath),
                            'path' => $relativePath,
                            's3Key' => $key,
                            'size' => $object['Size'] ?? 0,
                            'lastModified' => $object['LastModified'] ?? null
                        ];
                    }
                }
            }

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'count' => count($files),
                'files' => $files,
                's3Path' => "s3://{$this->minioBucket}/{$s3Path}"
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to list files from MinIO',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obter conteúdo de um arquivo específico
     * GET /api/git-file-content?owner=...&repo=...&file=...
     */
    public function getFileContent()
    {
        $method = strtoupper($this->request->getMethod());
        
        if ($method !== 'GET') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        // Auth opcional no editor: permitir sem sessão

        $owner = $this->request->getGet('owner');
        $repo = $this->request->getGet('repo');
        $file = $this->request->getGet('file');

        if (!$owner || !$repo || !$file) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required parameters: owner, repo, file'
            ]);
        }

        // Validação de path traversal
        if (strpos($file, '..') !== false || strpos($file, './') === 0) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Invalid file path'
            ]);
        }

        try {
            $s3Key = "scripts/{$owner}/{$repo}/{$file}";
            
            // Obter objeto do MinIO
            $result = $this->s3Client->getObject([
                'Bucket' => $this->minioBucket,
                'Key'    => $s3Key
            ]);

            $content = (string)$result['Body'];

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'filename' => basename($file),
                'path' => $file,
                'content' => $content,
                'size' => strlen($content)
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'File not found in MinIO',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Salvar/atualizar conteúdo de um arquivo no MinIO
     * POST /api/git-file-save
     */
    public function saveFileContent()
    {
        $method = strtoupper($this->request->getMethod());
        
        if ($method !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        // Auth opcional no editor: permitir sem sessão

        $input = $this->request->getJSON();
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $file = $input->file ?? null;
        $content = $input->content ?? null;

        if (!$owner || !$repo || !$file || $content === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: owner, repo, file, content'
            ]);
        }

        // Validação de path traversal
        if (strpos($file, '..') !== false || strpos($file, './') === 0) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Invalid file path'
            ]);
        }

        try {
            $s3Key = "scripts/{$owner}/{$repo}/{$file}";
            
            // Fazer upload do arquivo atualizado
            $this->s3Client->putObject([
                'Bucket' => $this->minioBucket,
                'Key'    => $s3Key,
                'Body'   => $content,
                'ACL'    => 'private'
            ]);

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'message' => 'File saved successfully',
                's3Key' => $s3Key
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to save file to MinIO',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obter todos os arquivos recursivamente (excluindo .git)
     */
    private function _getAllFiles($directory)
    {
        $files = [];
        $items = @scandir($directory);

        if ($items === false) {
            return $files;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->_getAllFiles($path));
            } else {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Remover diretório recursivamente
     */
    private function _removeDirectory($directory)
    {
        if (!is_dir($directory)) {
            return true;
        }

        $items = @scandir($directory);
        
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->_removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($directory);
    }
}
