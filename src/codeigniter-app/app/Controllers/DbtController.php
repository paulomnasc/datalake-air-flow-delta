<?php

namespace App\Controllers;

use App\Helpers\SessionHelper;
use Aws\S3\S3Client;

class DbtController extends BaseController
{
    private $s3Client;
    private $minioEndpoint;

    public function __construct()
    {
        // Garante que a sessão está inicializada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->minioEndpoint = getenv('MINIO_ENDPOINT') ?: 'http://minio:9000';

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
    }

    /**
     * Executa comandos do dbt via Docker transiente
     * 
     * POST /code-editor/dbt-execute
     */
    public function execute()
    {
        if (!SessionHelper::isLoggedIn()) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error' => 'Acesso negado: Usuário não autenticado.'
            ]);
        }

        $json = $this->request->getJSON(true);
        $action = $json['action'] ?? 'run'; // run | test | docs
        $env = $json['env'] ?? 'dev'; // dev | prod
        $owner = $json['owner'] ?? null;
        $repo = $json['repo'] ?? null;

        if (!in_array($action, ['run', 'test', 'docs'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Ação inválida. Escolha entre run, test ou docs.'
            ]);
        }

        if (!in_array($env, ['dev', 'prod'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error' => 'Ambiente inválido. Escolha entre dev ou prod.'
            ]);
        }

        $userId = SessionHelper::getUserId();
        $userBucket = SessionHelper::getUserBucket();

        // 1. Obter caminho do host de forma dinâmica e detectar a rede (Docker-in-Docker)
        $containerId = trim(@file_get_contents('/etc/hostname'));
        $hostPath = '';
        $network = 'airflow_net'; // default fallback
        if (!empty($containerId)) {
            $cmd = "docker inspect " . escapeshellarg($containerId) . " --format " . escapeshellarg('{{ range .Mounts }}{{ if eq .Destination "/datalake-root" }}{{ .Source }}{{ end }}{{ end }}');
            $hostPath = trim(@shell_exec($cmd));
            
            $netCmd = "docker inspect " . escapeshellarg($containerId) . " --format " . escapeshellarg('{{ range $net, $val := .NetworkSettings.Networks }}{{ $net }}{{ end }}');
            $detectedNetwork = trim(@shell_exec($netCmd));
            if (!empty($detectedNetwork)) {
                $network = $detectedNetwork;
            }
        }
        if (empty($hostPath)) {
            $hostPath = realpath(APPPATH . '../../');
        }

        // Caminhos de execução
        $tempDir = '/datalake-root/writable/dbt-tmp/user_' . $userId;
        $hostTempDir = rtrim($hostPath, '\\/') . '/writable/dbt-tmp/user_' . $userId;

        // Limpar diretório se existir e recriar
        if (is_dir($tempDir)) {
            $this->_removeDirectory($tempDir);
        }
        @mkdir($tempDir, 0777, true);

        // 2. Baixar arquivos do MinIO se conectado, senão copiar templates do host
        $downloaded = false;
        if (!empty($owner) && !empty($repo)) {
            $downloaded = $this->downloadRepoFromMinio($userBucket, $owner, $repo, $tempDir);
        }

        // Encontrar onde dbt_project.yml está no repositório baixado
        $projectDir = $this->findDbtProjectDir($tempDir);

        // Fallback para os templates se não foi conectado/baixado ou dbt_project.yml não existe
        if (!$downloaded || $projectDir === null) {
            $this->_copyDir('/datalake-root/dbt/analytics', $tempDir);
            $projectDir = $tempDir;
        }

        // 3. Garantir pre-criação dos schemas no PostgreSQL
        $schemaDev = "user_{$userId}_homolog_analytics";
        $schemaProd = "user_{$userId}_analytics";
        $schemaTarget = ($env === 'dev') ? $schemaDev : $schemaProd;

        try {
            $dsn = "pgsql:host=postgres-bi;port=5432;dbname=datalake_bi";
            $pdo = new \PDO($dsn, 'pbi_user', 'pbi_password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE SCHEMA IF NOT EXISTS " . $schemaDev . ";");
            $pdo->exec("CREATE SCHEMA IF NOT EXISTS " . $schemaProd . ";");
            $pdo->exec("GRANT ALL PRIVILEGES ON SCHEMA " . $schemaDev . " TO pbi_user;");
            $pdo->exec("GRANT ALL PRIVILEGES ON SCHEMA " . $schemaProd . " TO pbi_user;");
        } catch (\Exception $e) {
            log_message('warning', 'DbtController: Falha ao pré-criar schemas: ' . $e->getMessage());
        }

        // 4. Gerar profiles.yml dinâmico com tenant isolado
        $profilesContent = <<<YAML
analytics:
  target: dev
  outputs:
    dev:
      type: postgres
      host: postgres-bi
      port: 5432
      user: pbi_user
      password: pbi_password
      dbname: datalake_bi
      schema: {$schemaDev}
      threads: 4
      keepalives_idle: 0
    prod:
      type: postgres
      host: postgres-bi
      port: 5432
      user: pbi_user
      password: pbi_password
      dbname: datalake_bi
      schema: {$schemaProd}
      threads: 4
      keepalives_idle: 0
YAML;

        file_put_contents($projectDir . '/profiles.yml', $profilesContent);

        // 5. Montar comando dbt
        $dbtCmd = '';
        if ($action === 'run') {
            $dbtCmd = "run --profiles-dir . --target " . $env;
        } elseif ($action === 'test') {
            $dbtCmd = "test --profiles-dir . --target " . $env;
        } elseif ($action === 'docs') {
            $dbtCmd = "docs generate --profiles-dir . --target " . $env;
        }

        // Determinar o diretório de trabalho correto no container com base no caminho relativo
        $relativePath = ltrim(str_replace($tempDir, '', $projectDir), '\\/');
        $workDir = '/usr/app';
        if (!empty($relativePath)) {
            $workDir = '/usr/app/' . str_replace('\\', '/', $relativePath);
        }

        // Comando Docker com limite de recurso
        $dockerCmd = "docker run --rm --network=" . escapeshellarg($network) . " --memory=\"512m\" -v " . escapeshellarg($hostTempDir) . ":/usr/app -w " . escapeshellarg($workDir) . " ghcr.io/dbt-labs/dbt-postgres:1.5.0 " . $dbtCmd . " 2>&1";

        log_message('info', 'DbtController: Executando comando: ' . $dockerCmd);
        
        $outputLines = [];
        $returnCode = 0;
        exec($dockerCmd, $outputLines, $returnCode);
        $outputText = implode("\n", $outputLines);

        return $this->response->setJSON([
            'success' => ($returnCode === 0),
            'output'  => $outputText,
            'action'  => $action,
            'env'     => $env,
            'schema'  => $schemaTarget
        ]);
    }

    /**
     * Serve a documentação estática dbt compilada por usuário
     * 
     * GET /code-editor/dbt-docs-serve
     * GET /code-editor/dbt-docs-serve/(:any)
     */
    public function serveDocs($file = 'index.html')
    {
        if (!SessionHelper::isLoggedIn()) {
            return $this->response->setStatusCode(403)->setBody('Acesso negado.');
        }

        // Sanitização básica para evitar path traversal
        $file = str_replace(['..', '\\'], '', $file);
        $file = ltrim($file, '/');

        if (empty($file)) {
            $file = 'index.html';
        }

        $userId = SessionHelper::getUserId();
        $tempDir = '/datalake-root/writable/dbt-tmp/user_' . $userId;
        $projectDir = $this->findDbtProjectDir($tempDir);
        if ($projectDir === null) {
            $projectDir = $tempDir;
        }
        $path = $projectDir . '/target/' . $file;

        if (!file_exists($path)) {
            return $this->response->setStatusCode(404)->setBody('A documentação do dbt ainda não foi gerada para este usuário. Por favor, execute "dbt Docs" primeiro.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = [
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon'
        ];
        $mimeType = $mimeMap[$ext] ?? 'text/plain';

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody(file_get_contents($path));
    }

    /**
     * Baixa os arquivos de modelos do dbt do MinIO
     */
    private function downloadRepoFromMinio($userBucket, $owner, $repo, $tempDir)
    {
        $s3Prefix = "scripts/{$owner}/{$repo}/";
        try {
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $userBucket,
                'Prefix' => $s3Prefix
            ]);

            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $s3Key = $object['Key'];
                    $relativePath = str_replace($s3Prefix, '', $s3Key);
                    
                    if (empty($relativePath) || substr($relativePath, -1) === '/') {
                        continue;
                    }
                    
                    // Ignora arquivos internos do git
                    if (strpos($relativePath, '.git/') !== false) {
                        continue;
                    }
                    
                    $s3Object = $this->s3Client->getObject([
                        'Bucket' => $userBucket,
                        'Key'    => $s3Key
                    ]);
                    
                    $content = (string) $s3Object['Body'];
                    $localFilePath = $tempDir . '/' . $relativePath;
                    $localDir = dirname($localFilePath);
                    
                    if (!is_dir($localDir)) {
                        @mkdir($localDir, 0777, true);
                    }
                    
                    file_put_contents($localFilePath, $content);
                }
                return true;
            }
        } catch (\Exception $e) {
            log_message('error', 'DbtController: Erro ao baixar arquivos do MinIO: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Cópia recursiva de diretórios
     */
    private function _copyDir($src, $dst)
    {
        if (!is_dir($src)) {
            return;
        }
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->_copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Remoção recursiva de diretórios
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
     * Encontra recursivamente a pasta contendo dbt_project.yml
     */
    private function findDbtProjectDir($dir)
    {
        if (file_exists($dir . '/dbt_project.yml')) {
            return $dir;
        }

        $items = @scandir($dir);
        if ($items === false) {
            return null;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.git') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $found = $this->findDbtProjectDir($path);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
