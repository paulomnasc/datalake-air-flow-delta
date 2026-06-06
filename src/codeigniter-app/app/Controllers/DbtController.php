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

        // Fallback para os templates se não foi conectado/baixado
        if (!$downloaded) {
            $this->_copyDir('/datalake-root/dbt/analytics', $tempDir);
            $projectDir = $tempDir;
        } elseif ($projectDir === null) {
            // Se foi baixado, mas não tem dbt_project.yml, copiamos apenas a estrutura básica de suporte
            // (dbt_project.yml e macros) SEM copiar os modelos SQL ou arquivos de configuração do template.
            $projectDir = $tempDir;
            
            if (!file_exists($projectDir . '/dbt_project.yml')) {
                copy('/datalake-root/dbt/analytics/dbt_project.yml', $projectDir . '/dbt_project.yml');
            }
            
            if (!is_dir($projectDir . '/macros')) {
                @mkdir($projectDir . '/macros', 0777, true);
                $this->_copyDir('/datalake-root/dbt/analytics/macros', $projectDir . '/macros');
            }
        } else {
            // Se o repositório possui seu próprio dbt_project.yml, garantimos que a macro
            // generate_schema_name.sql esteja presente na pasta macros/ para respeitar
            // o schema dinâmico e evitar o comportamento padrão do dbt (concatenar target_schema + custom_schema).
            $macrosDir = $projectDir . '/macros';
            if (!is_dir($macrosDir)) {
                @mkdir($macrosDir, 0777, true);
            }
            if (!file_exists($macrosDir . '/generate_schema_name.sql')) {
                copy('/datalake-root/dbt/analytics/macros/generate_schema_name.sql', $macrosDir . '/generate_schema_name.sql');
            }
        }

        // Gerar dinamicamente os modelos ephemerais intermediários (raw, bronze, silver, gold) para todas as tabelas ativas do MySQL do usuário
        $generationDebug = $this->generateDynamicEphemeralModels($projectDir, $userId);

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

        if (!empty($generationDebug)) {
            $outputText = $generationDebug . "\n\n" . $outputText;
        }

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

        $content = file_get_contents($path);
        if ($file === 'index.html' || $ext === 'html') {
            // Injeta a tag <base href="/code-editor/dbt-docs-serve/"> no header do HTML
            // para garantir que requisições relativas a assets (catalog.json, manifest.json)
            // sejam resolvidas na URL base correta, contornando o comportamento de redirecionamento
            // de trailing slash do .htaccess.
            if (stripos($content, '<head>') !== false) {
                $content = str_ireplace('<head>', '<head><base href="/code-editor/dbt-docs-serve/">', $content);
            }
        }

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setBody($content);
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

    /**
     * Gera dinamicamente os modelos ephemerais intermediários do pipeline Medallion (raw, bronze, silver, gold)
     * a partir das tabelas configuradas no MySQL (dag_configurations) do usuário ativo.
     */
    private function generateDynamicEphemeralModels($projectDir, $userId)
    {
        $debug = [];
        $debug[] = "=========================================================================";
        $debug[] = "⚙️ [DEBUG] SISTEMA DE GERAÇÃO DINÂMICA DE MODELOS EPHEMERAIS";
        $debug[] = "- Usuário ID: " . $userId;
        $debug[] = "- Pasta do Projeto: " . $projectDir;

        $modelsDir = $projectDir . '/models';
        if (!is_dir($modelsDir)) {
            @mkdir($modelsDir, 0777, true);
        }

        try {
            $declaredTables = []; // Mapeamento clean_name => exact_declared_name

            // Procura em todos os arquivos .yml ou .yaml dentro da pasta models/ de forma robusta
            if (is_dir($modelsDir)) {
                $directory = new \RecursiveDirectoryIterator($modelsDir);
                $iterator = new \RecursiveIteratorIterator($directory);
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['yml', 'yaml'])) {
                        $content = file_get_contents($file->getPathname());
                        $lines = explode("\n", $content);
                        foreach ($lines as $line) {
                            // Ignora comentários
                            if (preg_match('/^\s*#/', $line)) {
                                continue;
                            }
                            // Captura qualquer entrada no formato '- name: tabela'
                            if (preg_match('/^\s*-\s*name\s*:\s*[\'"]?([a-zA-Z0-9_-]+)[\'"]?/', $line, $matches)) {
                                $exactName = trim($matches[1]);
                                // Remove sufixos comuns de camadas (ex: 'customers_gold' -> 'customers')
                                $cleanName = strtolower(str_replace(['_gold', '_silver', '_bronze', '_raw'], '', $exactName));
                                $declaredTables[$cleanName] = $exactName;
                            }
                        }
                    }
                }
            }

            $debug[] = "- Tabelas declaradas no dbt (arquivos YML): " . json_encode(array_values($declaredTables));

            // Se nenhuma tabela estiver declarada nos arquivos .yml, não há o que gerar
            if (empty($declaredTables)) {
                $debug[] = "⚠️ [AVISO] Nenhuma tabela declarada encontrada nos arquivos YML.";
                $debug[] = "=========================================================================";
                return implode("\n", $debug);
            }

            $db = \Config\Database::connect();
            // Busca todas as configurações de pipeline (ativas e inativas) do usuário para depuração
            $configs = $db->query("
                SELECT dc.dag_id, dc.source_filename, dc.target_table_name, dc.is_active, p.descricao as pasta_nome
                FROM dag_configurations dc
                INNER JOIN pasta p ON p.id = dc.id_pasta
                WHERE p.id_usuario = ?
            ", [$userId])->getResultArray();

            $debug[] = "- Total de pipelines no MySQL para este usuário: " . count($configs);

            $generatedFiles = [];
            
            // Para cada tabela declarada no dbt, tentamos encontrar o pipeline correspondente no MySQL
            foreach ($declaredTables as $cleanName => $exactDbtName) {
                // Pulamos o próprio nome do schema/source que às vezes é capturado (ex: 'public', 'raw_lakehouse')
                if (in_array($cleanName, ['public', 'raw_lakehouse'])) {
                    continue;
                }

                $matchedConfig = null;
                $specificFile = null;
                $matchType = "";

                // Loop para tentar encontrar match nas configurações de pipeline
                foreach ($configs as $config) {
                    $rawTargetTable = trim($config['target_table_name']);
                    $isActive = (int)$config['is_active'];

                    if ($isActive !== 1) {
                        continue;
                    }

                    if (empty($rawTargetTable)) {
                        continue;
                    }

                    // 1. Match exato pelo target_table_name (Single-table)
                    $mysqlCleanName = strtolower(str_replace(['_gold', '_silver', '_bronze', '_raw'], '', $rawTargetTable));
                    if ($mysqlCleanName === $cleanName) {
                        $matchedConfig = $config;
                        $specificFile = $config['source_filename']; // Usado como fallback
                        $matchType = "Exato (Single-Table)";
                        break;
                    }

                    // 2. Match pelo contéudo do source_filename (Multi-table)
                    $sourceFilename = trim($config['source_filename']);
                    
                    // Verifica se o cleanName aparece na lista de arquivos/JSON (ex: "customers.csv" ou "customers")
                    if (stripos($sourceFilename, $cleanName) !== false) {
                        $matchedConfig = $config;
                        
                        // Tenta localizar o arquivo específico correspondente à tabela no array/lista
                        $decoded = json_decode($sourceFilename, true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $file) {
                                if (stripos($file, $cleanName) !== false) {
                                    $specificFile = $file;
                                    break;
                                }
                            }
                        } else {
                            $files = explode(',', $sourceFilename);
                            foreach ($files as $file) {
                                if (stripos(trim($file), $cleanName) !== false) {
                                    $specificFile = trim($file);
                                    break;
                                }
                            }
                        }

                        if (empty($specificFile)) {
                            $specificFile = $sourceFilename;
                        }

                        $matchType = "Arquivo (Multi-Table)";
                        break;
                    }
                }

                if ($matchedConfig === null) {
                    $debug[] = "  * Tabela '{$cleanName}' (declarada como '{$exactDbtName}'): ↳ [PULADO] Nenhuma pipeline ativa encontrada no MySQL.";
                    continue;
                }

                $debug[] = "  * Tabela '{$cleanName}' (declarada como '{$exactDbtName}'): ↳ Match tipo: {$matchType} com pipeline/DAG '{$matchedConfig['dag_id']}' | Arquivo: '{$specificFile}'";

                // Caminhos dos arquivos SQL
                $tableName = $cleanName;
                $rawFile = $modelsDir . '/raw_' . $tableName . '.sql';
                $bronzeFile = $modelsDir . '/bronze_' . $tableName . '.sql';
                $silverFile = $modelsDir . '/silver_' . $tableName . '.sql';
                $goldFile = $modelsDir . '/gold_' . $tableName . '.sql';

                // 1. Gerar raw_{table}.sql (somente se não existir fisicamente no repositório do aluno)
                if (!file_exists($rawFile)) {
                    $rawSql = "{{ config(materialized='ephemeral') }}\n\n" .
                               "-- Camada Raw: Arquivo de origem original carregado no MinIO\n" .
                               "-- Caminho da Origem MySQL: " . $specificFile . " (DAG: " . $matchedConfig['dag_id'] . ")\n" .
                               "select * from {{ source('raw_lakehouse', '" . $exactDbtName . "') }}";
                    if (file_put_contents($rawFile, $rawSql) !== false) {
                        $generatedFiles[] = "raw_{$tableName}.sql";
                    }
                } else {
                    $generatedFiles[] = "raw_{$tableName}.sql (já existia)";
                }

                // 2. Gerar bronze_{table}.sql
                if (!file_exists($bronzeFile)) {
                    $bronzeSql = "{{ config(materialized='ephemeral') }}\n\n" .
                                 "-- Camada Bronze: Conversão do arquivo original em formato Parquet\n" .
                                 "-- Localização MinIO: bronze/" . $tableName . "/*.parquet\n" .
                                 "select * from {{ ref('raw_" . $tableName . "') }}";
                    if (file_put_contents($bronzeFile, $bronzeSql) !== false) {
                        $generatedFiles[] = "bronze_{$tableName}.sql";
                    }
                } else {
                    $generatedFiles[] = "bronze_{$tableName}.sql (já existia)";
                }

                // 3. Gerar silver_{table}.sql
                if (!file_exists($silverFile)) {
                    $silverSql = "{{ config(materialized='ephemeral') }}\n\n" .
                                 "-- Camada Silver: Limpeza dos dados brutos (remover duplicatas e nulos)\n" .
                                 "-- Localização MinIO: silver/" . $tableName . "/*.parquet\n" .
                                 "select * from {{ ref('bronze_" . $tableName . "') }}";
                    if (file_put_contents($silverFile, $silverSql) !== false) {
                        $generatedFiles[] = "silver_{$tableName}.sql";
                    }
                } else {
                    $generatedFiles[] = "silver_{$tableName}.sql (já existia)";
                }

                // 4. Gerar gold_{table}.sql
                if (!file_exists($goldFile)) {
                    $goldSql = "{{ config(materialized='ephemeral') }}\n\n" .
                               "-- Camada Gold: Tabela final consolidada para consumo analítico (Delta Lake)\n" .
                               "-- Localização MinIO: gold/" . $tableName . "_delta/*.parquet\n" .
                               "select * from {{ ref('silver_" . $tableName . "') }}";
                    if (file_put_contents($goldFile, $goldSql) !== false) {
                        $generatedFiles[] = "gold_{$tableName}.sql";
                    }
                } else {
                    $generatedFiles[] = "gold_{$tableName}.sql (já existia)";
                }
            }

            $debug[] = "- Arquivos ephemerais gerados nesta execução: " . json_encode($generatedFiles);
        } catch (\Exception $e) {
            $debug[] = "❌ Erro ao processar modelos: " . $e->getMessage();
            log_message('error', 'DbtController: Erro ao gerar modelos ephemerais dinâmicos: ' . $e->getMessage());
        }

        $debug[] = "=========================================================================";
        return implode("\n", $debug);
    }
}
