<?php

namespace App\Controllers;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use App\Helpers\SessionHelper;

class GitServerController extends BaseController
{
    private $s3Client;
    private $minioEndpoint;
    private $tempClonePath = '/tmp/git-clone';

    public function __construct()
    {
        // Garantir que sessão está inicializada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Configuração MinIO
        $this->minioEndpoint = getenv('MINIO_ENDPOINT') ?: 'http://minio:9000';

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

        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $token = $input->token ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $branch = $input->branch ?? 'main';

        if (!$userBucket || !$owner || !$repo) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo'
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
        // Bucket = userBucket (ex: admin-146)
        $s3Path = "scripts/{$owner}/{$repo}";
        $uploadedCount = 0;
        $uploadErrors = [];

        foreach ($files as $filePath) {
            try {
                $relativePath = str_replace($tempRepoPath . '/', '', $filePath);
                $s3Key = "{$s3Path}/{$relativePath}";
                
                $this->s3Client->putObject([
                    'Bucket' => $userBucket,
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
            's3Path' => "s3://{$userBucket}/{$s3Path}",
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

        $userBucket = $this->request->getGet('userBucket');
        $owner = $this->request->getGet('owner');
        $repo = $this->request->getGet('repo');

        if (!$userBucket || !$owner || !$repo) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required parameters: userBucket, owner, repo'
            ]);
        }
        
        try {
            $s3Path = "scripts/{$owner}/{$repo}";
            
            // Listar objetos no MinIO
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $userBucket,
                'Prefix' => $s3Path . '/'
            ]);

            $files = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $key = $object['Key'];
                    // Remover prefixo s3Path/ e .git files
                    $relativePath = str_replace($s3Path . '/', '', $key);
                    
                    $isGitInternal = preg_match('#(^|/)(\.git)(/|$)#', $relativePath);
                    if ($isGitInternal || empty($relativePath)) {
                        continue;
                    }

                    $lastModified = $object['LastModified'] ?? null;
                    if ($lastModified instanceof \DateTimeInterface) {
                        $lastModified = $lastModified->format('c');
                    } elseif (is_object($lastModified)) {
                        $lastModified = null;
                    }

                    $files[] = [
                        'name' => basename($relativePath),
                        'path' => $relativePath,
                        's3Key' => $key,
                        'size' => $object['Size'] ?? 0,
                        'lastModified' => $lastModified
                    ];
                }
            }

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'count' => count($files),
                'files' => $files,
                's3Path' => "s3://{$userBucket}/{$s3Path}",
                'bucket' => $userBucket,
                'userBucket' => $userBucket
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to list files from MinIO',
                'message' => $e->getMessage(),
                'bucket' => $userBucket,
                'userBucket' => $userBucket,
                'prefix' => isset($s3Path) ? ($s3Path . '/') : null
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

        $userBucket = $this->request->getGet('userBucket');
        $owner = $this->request->getGet('owner');
        $repo = $this->request->getGet('repo');
        $file = $this->request->getGet('file');

        if (!$userBucket || !$owner || !$repo || !$file) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required parameters: userBucket, owner, repo, file'
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
                'Bucket' => $userBucket,
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

        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $file = $input->file ?? null;
        $content = $input->content ?? null;

        if (!$userBucket || !$owner || !$repo || !$file || $content === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo, file, content'
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
                'Bucket' => $userBucket,
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
     * Criar pasta no MinIO (usa objeto .gitkeep)
     * POST /api/git-folder-create
     */
    public function createFolder()
    {
        $method = strtoupper($this->request->getMethod());
        if ($method !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $path = $input->path ?? null;

        $normalizedPath = trim(str_replace('\\', '/', $path ?? ''), '/');

        if (!$userBucket || !$owner || !$repo || !$normalizedPath) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo, path'
            ]);
        }

        if (strpos($normalizedPath, '..') !== false) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Invalid folder path'
            ]);
        }

        try {
            $s3Key = "scripts/{$owner}/{$repo}/{$normalizedPath}/.gitkeep";
            $this->s3Client->putObject([
                'Bucket' => $userBucket,
                'Key'    => $s3Key,
                'Body'   => '',
                'ACL'    => 'private'
            ]);

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'message' => 'Folder created successfully',
                'path' => $normalizedPath,
                's3Key' => $s3Key
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to create folder',
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

    private function _listObjectsByPrefix(string $bucket, string $prefix): array
    {
        $keys = [];
        $params = [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ];

        do {
            $result = $this->s3Client->listObjectsV2($params);
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $keys[] = $object['Key'];
                }
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while (!empty($params['ContinuationToken']));

        return $keys;
    }

    private function _formatCopySource(string $bucket, string $key): string
    {
        return $bucket . '/' . str_replace('%2F', '/', rawurlencode($key));
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

    /**
     * Deletar arquivo do MinIO
     * DELETE /api/git-file-delete
     */
    public function deleteFileContent()
    {
        $method = strtoupper($this->request->getMethod());
        
        if ($method !== 'DELETE' && $method !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $file = $input->file ?? null;

        if (!$userBucket || !$owner || !$repo || !$file) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo, file'
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
            
            // Deletar objeto do MinIO
            $this->s3Client->deleteObject([
                'Bucket' => $userBucket,
                'Key'    => $s3Key
            ]);

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'message' => 'File deleted successfully',
                's3Key' => $s3Key
            ]);

        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to delete file from MinIO',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Renomear arquivo ou pasta
     * POST /api/git-entry-rename
     */
    public function renameEntry()
    {
        $method = strtoupper($this->request->getMethod());
        if ($method !== 'POST') {
            return $this->response->setStatusCode(405)->setJSON(['error' => 'Method not allowed']);
        }

        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $oldPath = $input->oldPath ?? null;
        $newPath = $input->newPath ?? null;
        $isFile = filter_var($input->isFile ?? false, FILTER_VALIDATE_BOOLEAN);

        $oldPath = trim(str_replace('\\', '/', $oldPath ?? ''), '/');
        $newPath = trim(str_replace('\\', '/', $newPath ?? ''), '/');

        if (!$userBucket || !$owner || !$repo || !$oldPath || !$newPath) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo, oldPath, newPath'
            ]);
        }

        if ($oldPath === $newPath) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'New path must be different from old path'
            ]);
        }

        if (strpos($oldPath, '..') !== false || strpos($newPath, '..') !== false) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Invalid path'
            ]);
        }

        $basePrefix = "scripts/{$owner}/{$repo}/";

        try {
            if ($isFile) {
                $oldKey = $basePrefix . $oldPath;
                $newKey = $basePrefix . $newPath;

                $this->s3Client->copyObject([
                    'Bucket' => $userBucket,
                    'CopySource' => $this->_formatCopySource($userBucket, $oldKey),
                    'Key' => $newKey,
                    'ACL' => 'private'
                ]);

                $this->s3Client->deleteObject([
                    'Bucket' => $userBucket,
                    'Key' => $oldKey
                ]);

                return $this->response->setStatusCode(200)->setJSON([
                    'success' => true,
                    'message' => 'File renamed successfully',
                    'oldPath' => $oldPath,
                    'newPath' => $newPath
                ]);
            }

            $oldPrefix = rtrim($oldPath, '/') . '/';
            $newPrefix = rtrim($newPath, '/') . '/';
            $sourcePrefix = $basePrefix . $oldPrefix;
            $targetsPrefix = $basePrefix . $newPrefix;

            $objects = $this->_listObjectsByPrefix($userBucket, $sourcePrefix);
            if (empty($objects)) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => 'Folder not found or empty'
                ]);
            }

            $copied = 0;
            $deleteQueue = [];

            foreach ($objects as $objectKey) {
                $relative = substr($objectKey, strlen($sourcePrefix));
                $targetKey = $targetsPrefix . $relative;

                $this->s3Client->copyObject([
                    'Bucket' => $userBucket,
                    'CopySource' => $this->_formatCopySource($userBucket, $objectKey),
                    'Key' => $targetKey,
                    'ACL' => 'private'
                ]);

                $deleteQueue[] = ['Key' => $objectKey];
                $copied++;
            }

            if (!empty($deleteQueue)) {
                $this->s3Client->deleteObjects([
                    'Bucket' => $userBucket,
                    'Delete' => [
                        'Objects' => $deleteQueue,
                        'Quiet' => true
                    ]
                ]);
            }

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'message' => 'Folder renamed successfully',
                'oldPath' => $oldPath,
                'newPath' => $newPath,
                'movedObjects' => $copied
            ]);
        } catch (AwsException $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Failed to rename entry',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Commit e push alterações do MinIO para GitHub
     * POST /api/git-push
     */
    public function gitPush()
    {
        $input = $this->request->getJSON();
        $userBucket = $input->userBucket ?? null;
        $owner = $input->owner ?? null;
        $repo = $input->repo ?? null;
        $token = $input->token ?? null;
        $commitMsg = $input->commitMsg ?? 'Update files';

        if (!$userBucket || !$owner || !$repo || !$token) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Missing required fields: userBucket, owner, repo, token'
            ]);
        }

        try {
            // 1. Criar diretório temporário para clone
            $tempDir = "/tmp/git-push/{$owner}/{$repo}";
            
            if (is_dir($tempDir)) {
                $this->_removeDirectory($tempDir);
            }
            
            @mkdir($tempDir, 0755, true);

            // 2. Clonar repositório
            $repoUrl = "https://{$owner}:{$token}@github.com/{$owner}/{$repo}.git";
            $cloneCmd = "cd {$tempDir} && git clone {$repoUrl} . 2>&1";
            
            exec($cloneCmd, $cloneOutput, $cloneReturnCode);
            
            if ($cloneReturnCode !== 0) {
                throw new \Exception('Git clone failed: ' . implode("\n", $cloneOutput));
            }

            // 3. Baixar todos os arquivos do MinIO para o diretório clonado
            $s3Prefix = "scripts/{$owner}/{$repo}/";
            
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $userBucket,
                'Prefix' => $s3Prefix
            ]);

            $downloadedCount = 0;
            
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $s3Key = $object['Key'];
                    
                    // Remove o prefixo para obter o caminho relativo
                    $relativePath = str_replace($s3Prefix, '', $s3Key);
                    
                    if (empty($relativePath) || substr($relativePath, -1) === '/') {
                        continue; // Skip folders
                    }
                    
                    // Baixar conteúdo do MinIO
                    $s3Object = $this->s3Client->getObject([
                        'Bucket' => $userBucket,
                        'Key'    => $s3Key
                    ]);
                    
                    $content = (string) $s3Object['Body'];
                    
                    // Criar diretórios necessários
                    $localFilePath = $tempDir . '/' . $relativePath;
                    $localDir = dirname($localFilePath);
                    
                    if (!is_dir($localDir)) {
                        @mkdir($localDir, 0755, true);
                    }
                    
                    // Salvar arquivo
                    file_put_contents($localFilePath, $content);
                    $downloadedCount++;
                }
            }

            // 4. Configurar git user
            exec("cd {$tempDir} && git config user.name \"{$owner}\" 2>&1");
            exec("cd {$tempDir} && git config user.email \"{$owner}@users.noreply.github.com\" 2>&1");

            // 5. Add, commit e push
            exec("cd {$tempDir} && git add . 2>&1", $addOutput, $addReturnCode);
            
            $commitCmd = "cd {$tempDir} && git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
            exec($commitCmd, $commitOutput, $commitReturnCode);
            
            // Se não há mudanças, commit retorna código 1
            if ($commitReturnCode !== 0 && !stripos(implode(' ', $commitOutput), 'nothing to commit')) {
                throw new \Exception('Git commit failed: ' . implode("\n", $commitOutput));
            }
            
            $pushCmd = "cd {$tempDir} && git push origin main 2>&1";
            exec($pushCmd, $pushOutput, $pushReturnCode);
            
            if ($pushReturnCode !== 0) {
                // Tentar master se main falhar
                $pushCmd = "cd {$tempDir} && git push origin master 2>&1";
                exec($pushCmd, $pushOutput, $pushReturnCode);
                
                if ($pushReturnCode !== 0) {
                    throw new \Exception('Git push failed: ' . implode("\n", $pushOutput));
                }
            }

            // 6. Limpar diretório temporário
            $this->_removeDirectory($tempDir);

            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'message' => 'Changes pushed to GitHub successfully',
                'downloadedFiles' => $downloadedCount,
                'commitMsg' => $commitMsg
            ]);

        } catch (\Exception $e) {
            // Limpar em caso de erro
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->_removeDirectory($tempDir);
            }
            
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Git push failed',
                'message' => $e->getMessage()
            ]);
        }
    }
}
