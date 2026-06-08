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
        $buildDebug = "";
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

        // 1b. Garantir build da imagem Docker do dbt-duckdb local
        $checkImage = trim(@shell_exec("docker images -q dbt-duckdb-local:latest 2>&1"));
        $buildDebug .= "Docker Image Check (initially): '" . $checkImage . "'\n";
        if (empty($checkImage)) {
            log_message('info', 'DbtController: Compilando imagem dbt-duckdb-local:latest...');
            $buildDebug .= "Compilando imagem dbt-duckdb-local:latest...\n";
            // Usamos a entrada padrão (<) para enviar o conteúdo do Dockerfile, contornando incompatibilidades de caminhos absolutos do Docker-in-Docker
            $buildResult = @shell_exec("docker build -t dbt-duckdb-local:latest - < /datalake-root/dbt/Dockerfile.dbt-duckdb 2>&1");
            $buildDebug .= "Build Output:\n" . $buildResult . "\n";
            $checkImage = trim(@shell_exec("docker images -q dbt-duckdb-local:latest 2>&1"));
            $buildDebug .= "Docker Image Check (after build): '" . $checkImage . "'\n";
            if (empty($checkImage)) {
                log_message('error', 'DbtController: Falha crítica ao compilar imagem dbt-duckdb-local:latest.');
                $buildDebug .= "⚠️ Falha crítica: imagem dbt-duckdb-local:latest não foi criada.\n";
            }
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
        $generationDebug = $this->generateDynamicEphemeralModels($projectDir, $userId, $userBucket);

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

        // 4. Gerar profiles.yml dinâmico com tenant isolado usando DuckDB + Postgres
        $minioHost = str_replace(['http://', 'https://'], '', getenv('MINIO_ENDPOINT') ?: 'minio:9000');
        $minioKey = getenv('MINIO_ACCESS_KEY_ID') ?: 'admin';
        $minioSecret = getenv('MINIO_SECRET_ACCESS_KEY') ?: 'admin123';

        $profilesContent = <<<YAML
analytics:
  target: dev
  outputs:
    dev:
      type: duckdb
      path: /tmp/datalake.duckdb
      schema: '{$schemaDev}'
      extensions:
        - httpfs
        - postgres
      settings:
        s3_endpoint: '{$minioHost}'
        s3_access_key_id: '{$minioKey}'
        s3_secret_access_key: '{$minioSecret}'
        s3_use_ssl: false
        s3_url_style: 'path'
      attach:
        - path: "postgresql://pbi_user:pbi_password@postgres-bi:5432/datalake_bi"
          type: postgres
          alias: postgres_db
    prod:
      type: duckdb
      path: /tmp/datalake.duckdb
      schema: '{$schemaProd}'
      extensions:
        - httpfs
        - postgres
      settings:
        s3_endpoint: '{$minioHost}'
        s3_access_key_id: '{$minioKey}'
        s3_secret_access_key: '{$minioSecret}'
        s3_use_ssl: false
        s3_url_style: 'path'
      attach:
        - path: "postgresql://pbi_user:pbi_password@postgres-bi:5432/datalake_bi"
          type: postgres
          alias: postgres_db
YAML;

        file_put_contents($projectDir . '/profiles.yml', $profilesContent);

        // 4.1. Ajustar dbt_project.yml dinamicamente
        $dbtProjectFile = $projectDir . '/dbt_project.yml';
        if (file_exists($dbtProjectFile)) {
            $projectContent = file_get_contents($dbtProjectFile);
            
            // Garantir que exista o on-run-start para criar os schemas no DuckDB
            if (strpos($projectContent, 'on-run-start:') === false) {
                $projectContent .= "\n\non-run-start:\n  - \"create schema if not exists {{ target.schema }}\"\n";
            }
            
            // Redirecionamento para o banco Postgres attached
            if (strpos($projectContent, 'postgres_db') === false) {
                // Escaneia a pasta models para encontrar todos os modelos dim_ e fato_
                $foundModels = [];
                $modelsDir = $projectDir . '/models';
                if (is_dir($modelsDir)) {
                    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($modelsDir));
                    foreach ($iterator as $file) {
                        if ($file->isFile() && strtolower($file->getExtension()) === 'sql') {
                            $filename = $file->getBasename('.sql');
                            if (strpos($filename, 'dim_') === 0 || strpos($filename, 'fato_') === 0) {
                                $foundModels[] = $filename;
                            }
                        }
                    }
                }
                
                // Fallback padrão se não encontrar arquivos (para garantir retrocompatibilidade)
                $defaultModels = ['dim_customers', 'dim_cursos', 'dim_usuarios', 'fato_acessos', 'fato_vendas'];
                $foundModels = array_unique(array_merge($defaultModels, $foundModels));

                $redirectConfig = "\n    # Redirecionamento dinâmico para tabelas PostgreSQL\n";
                foreach ($foundModels as $model) {
                    $redirectConfig .= "    {$model}:\n      +database: postgres_db\n";
                }

                // Encontra onde está "analytics:" dentro de "models:"
                if (preg_match('/models\s*:\s*\n\s*analytics\s*:/', $projectContent)) {
                    $projectContent = preg_replace('/(models\s*:\s*\n\s*analytics\s*:)/', "$1" . $redirectConfig, $projectContent);
                } else {
                    $projectContent .= "\nmodels:\n  analytics:" . $redirectConfig;
                }
            }
            file_put_contents($dbtProjectFile, $projectContent);
        }

        // 5. Montar comando dbt
        $dbtCmd = '';
        if ($action === 'run') {
            // Roda o dbt run e, se obtiver sucesso, gera os docs para manter a linhagem e colunas atualizadas na interface
            $dbtCmd = "sh -c \"dbt run --profiles-dir . --target {$env} && dbt docs generate --profiles-dir . --target {$env}\"";
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

        // Comando Docker com limite de recurso usando a imagem local dbt-duckdb-local:latest
        $dockerCmd = "docker run --rm --network=" . escapeshellarg($network) . " --memory=\"512m\" -v " . escapeshellarg($hostTempDir) . ":/usr/app -w " . escapeshellarg($workDir) . " dbt-duckdb-local:latest ";
        if (strpos($dbtCmd, 'sh -c') === 0) {
            $dockerCmd .= $dbtCmd . " 2>&1";
        } else {
            $dockerCmd .= "dbt " . $dbtCmd . " 2>&1";
        }

        log_message('info', 'DbtController: Executando comando: ' . $dockerCmd);
        
        $outputLines = [];
        $returnCode = 0;
        exec($dockerCmd, $outputLines, $returnCode);
        $outputText = implode("\n", $outputLines);

        if (!empty($generationDebug)) {
            $outputText = $generationDebug . "\n\n" . $outputText;
        }

        if (!empty($buildDebug)) {
            $outputText = $buildDebug . "\n\n" . $outputText;
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
            if ($item === '.' || $item === '..' || $item === '.git' || $item === 'target' || $item === 'logs' || $item === 'dbt_packages') {
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
     * Gera dinamicamente os modelos intermediários do pipeline Medallion (raw, bronze, silver, gold)
     * a partir das tabelas configuradas no MySQL (dag_configurations) do usuário ativo,
     * lendo diretamente os arquivos físicos do MinIO S3.
     */
    private function generateDynamicEphemeralModels($projectDir, $userId, $userBucket)
    {
        $debug = [];
        $debug[] = "=========================================================================";
        $debug[] = "⚙️ [DEBUG] SISTEMA DE GERAÇÃO DINÂMICA DE MODELOS MEDALLION S3";
        $debug[] = "- Usuário ID: " . $userId;
        $debug[] = "- Pasta do Projeto: " . $projectDir;

        $modelsDir = $projectDir . '/models';
        if (!is_dir($modelsDir)) {
            @mkdir($modelsDir, 0777, true);
        }

        try {
            $declaredTables = []; // Mapeamento clean_name => ['exact_name' => ..., 'source_name' => ...]

            // Procura em todos os arquivos .yml ou .yaml dentro da pasta models/ de forma robusta
            if (is_dir($modelsDir)) {
                $directory = new \RecursiveDirectoryIterator($modelsDir);
                $iterator = new \RecursiveIteratorIterator($directory);
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['yml', 'yaml'])) {
                        $content = file_get_contents($file->getPathname());
                        $lines = explode("\n", $content);
                        $currentSource = 'raw_lakehouse';
                        $sourceIndent = -1;

                        foreach ($lines as $line) {
                            // Ignora comentários
                            if (preg_match('/^\s*#/', $line)) {
                                continue;
                            }
                            // Captura qualquer entrada no formato '- name: tabela' ou '- name: fonte'
                            if (preg_match('/^(\s*)-\s*name\s*:\s*[\'"]?([a-zA-Z0-9_-]+)[\'"]?/', $line, $matches)) {
                                $indent = strlen($matches[1]);
                                $name = trim($matches[2]);

                                if ($sourceIndent == -1 || $indent <= $sourceIndent) {
                                    // Novo source encontrado (menor ou igual indentação que o anterior)
                                    $currentSource = $name;
                                    $sourceIndent = $indent;
                                } else {
                                    // Tabela sob o source atual (maior indentação)
                                    $exactName = $name;
                                    $cleanName = strtolower(str_replace(['_gold', '_silver', '_bronze', '_raw'], '', $exactName));
                                    $declaredTables[$cleanName] = [
                                        'exact_name' => $exactName,
                                        'source_name' => $currentSource
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $displayTables = [];
            foreach ($declaredTables as $k => $v) {
                $displayTables[] = "source('{$v['source_name']}', '{$v['exact_name']}')";
            }
            $debug[] = "- Tabelas declaradas no dbt (arquivos YML): " . json_encode($displayTables);

            // Se nenhuma tabela estiver declarada nos arquivos .yml, não há o que gerar
            if (empty($declaredTables)) {
                $debug[] = "⚠️ [AVISO] Nenhuma tabela declarada encontrada nos arquivos YML.";
                $debug[] = "=========================================================================";
                return implode("\n", $debug);
            }

            $db = \Config\Database::connect();
            // Busca todas as configurações de pipeline (ativas e inativas) do usuário para depuração
            $configs = $db->query("
                SELECT dc.id, dc.dag_id, dc.source_filename, dc.target_table_name, dc.is_active, p.descricao as pasta_nome,
                       dc.sql_connection_id, dc.is_multi_table, dc.owner
                FROM dag_configurations dc
                INNER JOIN pasta p ON p.id = dc.id_pasta
                WHERE p.id_usuario = ?
            ", [$userId])->getResultArray();

            // Busca as tabelas selecionadas nas Dags multi-table
            $selections = $db->query("
                SELECT dts.id_dag_config, dts.table_name
                FROM dag_table_selections dts
                WHERE dts.is_selected = 1
            ")->getResultArray();

            $selectionsByConfig = [];
            foreach ($selections as $sel) {
                $selectionsByConfig[$sel['id_dag_config']][] = strtolower(trim($sel['table_name']));
            }

            $debug[] = "- Total de pipelines no MySQL para este usuário: " . count($configs);

            $generatedFiles = [];
            
            // Para cada tabela declarada no dbt, tentamos encontrar o pipeline correspondente no MySQL
            foreach ($declaredTables as $cleanName => $tableMeta) {
                $exactDbtName = $tableMeta['exact_name'];
                $sourceName = $tableMeta['source_name'];

                // Pulamos o próprio nome do schema/source que às vezes é capturado por falsa indentação
                if (in_array($cleanName, ['public', 'raw_lakehouse'])) {
                    continue;
                }

                $matchedConfig = null;
                $specificFile = null;
                $matchType = "";

                // Loop para tentar encontrar match nas configurações de pipeline
                foreach ($configs as $config) {
                    $isActive = (int)$config['is_active'];
                    if ($isActive !== 1) {
                        continue;
                    }

                    $isMultiTable = (int)($config['is_multi_table'] ?? 0);

                    // 1. Caso Multi-Table: busca nas seleções de tabela associadas
                    if ($isMultiTable === 1) {
                        $configId = $config['id'];
                        $configTables = $selectionsByConfig[$configId] ?? [];
                        
                        if (in_array($cleanName, $configTables)) {
                            $matchedConfig = $config;
                            
                            // Tenta encontrar o arquivo específico correspondente na lista do source_filename
                            $sourceFilename = trim($config['source_filename']);
                            $specificFile = $cleanName . ".csv"; // default fallback
                            
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
                                        $specificFile = $file;
                                        break;
                                    }
                                }
                            }
                            $matchType = "Multi-Table (dag_table_selections)";
                            break;
                        }
                    } 
                    // 2. Caso Single-Table: busca direta no target_table_name
                    else {
                        $rawTargetTable = trim($config['target_table_name']);
                        if (!empty($rawTargetTable)) {
                            $mysqlCleanName = strtolower(str_replace(['_gold', '_silver', '_bronze', '_raw'], '', $rawTargetTable));
                            if ($mysqlCleanName === $cleanName) {
                                $matchedConfig = $config;
                                $specificFile = $config['source_filename'];
                                $matchType = "Single-Table (target_table_name)";
                                break;
                            }
                        }
                    }
                }


                if ($matchedConfig === null) {
                    $debug[] = "  * Tabela '{$cleanName}' (declarada como '{$exactDbtName}' no source '{$sourceName}'): ↳ [PULADO] Nenhuma pipeline ativa encontrada no MySQL.";
                    continue;
                }

                $debug[] = "  * Tabela '{$cleanName}' (declarada como '{$exactDbtName}' no source '{$sourceName}'): ↳ Match tipo: {$matchType} com pipeline/DAG '{$matchedConfig['dag_id']}' | Arquivo: '{$specificFile}'";

                // Caminhos dos arquivos SQL
                $tableName = $cleanName;
                $cleanFile = stripslashes(trim($specificFile ?? '', '"\' []{} '));
                $cleanFile = ltrim($cleanFile, '/\\');
                $basename = basename($cleanFile);
                $basenameNoExt = pathinfo($basename, PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($cleanFile, PATHINFO_EXTENSION));

                $readFunc = "read_csv_auto";
                if ($ext === 'json') {
                    $readFunc = "read_json_auto";
                } elseif ($ext === 'parquet') {
                    $readFunc = "read_parquet";
                }

                $bucket = trim($matchedConfig['owner'] ?? $userBucket);
                if (empty($bucket)) {
                    $bucket = 'lab01'; // fallback
                }

                $cleanDagId = preg_replace('/\d+$/', '', $matchedConfig['dag_id']);
                
                // Caminhos corretos baseados na estrutura física observada no MinIO S3
                $bronzeS3SubDir = str_replace('raw/', 'bronze/', pathinfo($cleanFile, PATHINFO_DIRNAME));
                $bronzeS3Path = "s3://{$bucket}/{$bronzeS3SubDir}/{$basenameNoExt}.parquet";
                
                $silverS3Path = "s3://{$bucket}/silver/{$tableName}/{$basenameNoExt}.parquet";
                
                $goldS3Path = "s3://{$bucket}/gold/{$basenameNoExt}_gold/{$basenameNoExt}_gold_delta/*.parquet";

                $rawFile = $modelsDir . '/raw_' . $tableName . '.sql';
                $bronzeFile = $modelsDir . '/bronze_' . $tableName . '.sql';
                $silverFile = $modelsDir . '/silver_' . $tableName . '.sql';
                $goldFile = $modelsDir . '/gold_' . $tableName . '.sql';

                // 1. Gerar raw_{table}.sql
                $rawSql = "{{ config(materialized='view') }}\n\n" .
                           "-- Camada Raw: Arquivo de origem original carregado no MinIO\n" .
                           "-- Caminho da Origem MySQL: " . $cleanFile . " (DAG: " . $matchedConfig['dag_id'] . ")\n" .
                           "select * from " . $readFunc . "('s3://" . $bucket . "/" . $cleanFile . "')";
                if (file_put_contents($rawFile, $rawSql) !== false) {
                    $generatedFiles[] = "raw_{$tableName}.sql";
                }

                // 2. Gerar bronze_{table}.sql
                $bronzeSql = "{{ config(materialized='view') }}\n\n" .
                             "-- depends_on: {{ ref('raw_" . $tableName . "') }}\n\n" .
                             "-- Camada Bronze: Conversão do arquivo original em formato Parquet\n" .
                             "-- Localização MinIO: " . str_replace('raw/', 'bronze/', pathinfo($cleanFile, PATHINFO_DIRNAME)) . "/" . $basenameNoExt . ".parquet\n" .
                             "select * from read_parquet('" . $bronzeS3Path . "')";
                if (file_put_contents($bronzeFile, $bronzeSql) !== false) {
                    $generatedFiles[] = "bronze_{$tableName}.sql";
                }

                // 3. Gerar silver_{table}.sql
                $silverSql = "{{ config(materialized='view') }}\n\n" .
                             "-- depends_on: {{ ref('bronze_" . $tableName . "') }}\n\n" .
                             "-- Camada Silver: Limpeza dos dados brutos (remover duplicatas e nulos)\n" .
                             "-- Localização MinIO: silver/" . $tableName . "/" . $basenameNoExt . ".parquet\n" .
                             "select * from read_parquet('" . $silverS3Path . "')";
                if (file_put_contents($silverFile, $silverSql) !== false) {
                    $generatedFiles[] = "silver_{$tableName}.sql";
                }

                // 4. Gerar gold_{table}.sql
                $goldSql = "{{ config(materialized='view') }}\n\n" .
                           "-- depends_on: {{ ref('silver_" . $tableName . "') }}\n\n" .
                           "-- Camada Gold: Tabela final consolidada para consumo analítico (Delta Lake)\n" .
                           "-- Localização MinIO: gold/" . $basenameNoExt . "_gold/" . $basenameNoExt . "_gold_delta/*.parquet\n" .
                           "select * from read_parquet('" . $goldS3Path . "')";
                if (file_put_contents($goldFile, $goldSql) !== false) {
                    $generatedFiles[] = "gold_{$tableName}.sql";
                }
            }

            $debug[] = "- Arquivos medallion gerados fisicamente no S3: " . json_encode($generatedFiles);
        } catch (\Exception $e) {
            $debug[] = "❌ Erro ao processar modelos: " . $e->getMessage();
            log_message('error', 'DbtController: Erro ao gerar modelos ephemerais dinâmicos: ' . $e->getMessage());
        }

        $debug[] = "=========================================================================";
        return implode("\n", $debug);
    }
}
