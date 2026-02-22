<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ConfigModel;
use App\Models\PastaModel;
use App\Models\SourceTypeModel;
use CodeIgniter\CLI\Console;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use App\Helpers\SessionHelper;

class ConfigController extends BaseController
{

    protected $configModel;
    
    // Propriedades para o MinIO
    private $minioClient;
    private $bucketName;

    public function __construct()
    {
        // 1. Inicializa o Model (Exemplo)
        $this->configModel = new ConfigModel();
        
        // 2. Inicializa o cliente MinIO na construção da Controller
        $this->_initMinioClient();
    }

    /**
     * Centraliza a inicialização do S3Client (MinIO) usando variáveis do .env
     */
    private function _initMinioClient()
    {
        // Carrega as variáveis do .env (CodeIgniter usa getenv() nativamente)
        $endpoint = getenv('MINIO_ENDPOINT');
        $region = getenv('MINIO_REGION');
        $version = getenv('MINIO_VERSION');
        $usePathStyle = getenv('MINIO_USE_PATH_STYLE_ENDPOINT');
        $key = getenv('MINIO_ACCESS_KEY_ID');
        $secret = getenv('MINIO_SECRET_ACCESS_KEY');
        
        $this->bucketName = getenv('MINIO_BUCKET_RAW');
        
        log_message('info', '📋 Configuração do MinIO:');
        log_message('info', "  Endpoint: {$endpoint}");
        log_message('info', "  Region: {$region}");
        log_message('info', "  Version: {$version}");
        log_message('info', "  UsePathStyle: {$usePathStyle}");
        log_message('info', "  Key: " . (empty($key) ? 'NÃO DEFINIDO' : '***'));
        log_message('info', "  Secret: " . (empty($secret) ? 'NÃO DEFINIDO' : '***'));
        log_message('info', "  Bucket: {$this->bucketName}");
        
        // Validar que todas as configurações estão presentes
        if (empty($endpoint) || empty($region) || empty($key) || empty($secret) || empty($this->bucketName)) {
            log_message('error', '❌ Configuração incompleta do MinIO no .env');
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

        // Instancia o S3Client (MinIO)
        try {
            $this->minioClient = new S3Client($minioConfig);
            log_message('info', '✅ S3Client (MinIO) inicializado com sucesso');
        } catch (\Exception $e) {
            // Em caso de falha na inicialização (ex: credenciais ausentes)
            log_message('error', '❌ Falha ao inicializar S3Client (MinIO): ' . $e->getMessage());
            // Opcional: abortar ou permitir que a lógica de upload lide com o cliente nulo
            $this->minioClient = null; 
        }
    }

    public function index()
    {
        //

        $pastaModel = new PastaModel();
        $pastas = $pastaModel->listToCombo($_SESSION['id_usuario_logado']); 
        //var_dump($pastas); die();
        // Combine os dados em um único array
        //Esse set para nulo só deve acontecer se a rota anterior for listConfig
        $rotaAnterior = $this->previousRoute();
        if($rotaAnterior && $rotaAnterior == "listConfig")
            $_SESSION['id_pasta_selecionada'] = null;
        
        return view('listConfig',['pastas'=>$pastas]);
    }

    private function previousRoute()
    {
        // Obtém o referer (rota anterior)
        $referer = $this->request->getServer('HTTP_REFERER');

        if ($referer) {
            return $referer;
        } else {
            return null;
        }
    }

    // Função para verificar e aumentar o limite de quadros
    function podeCriarConfig()
    {
        $usuario_logado = ($_SESSION['usuario_logado'] == 1);

        if ($usuario_logado) {
            // Verificar limite no banco de dados para usuários logados
            $idUsuario = $_SESSION['id_usuario_logado'];
            $perfilUsuario = $_SESSION['perfil_usuario_logado'];

            if($perfilUsuario == 'Admin' || $perfilUsuario == 'Assinante') return true;

            $quantidadeConfigs = $this->contarConfigsPorUsuario($idUsuario);

            return ($quantidadeConfigs < 4);
        } 
        
    }

    // Função fictícia que simula a contagem de quadros no banco de dados
    function contarConfigsPorUsuario($idUsuario)
    {
        // Esta função deve contar os quadros criados pelo usuário no banco de dados
        //return 2; // Exemplo: 2 quadros criados

        $model = new ConfigModel();

        return $model->ObterTotalConfigs($idUsuario);

    }


    public function listConfigPasta()
    {
        $model = new ConfigModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'quadro.id_pasta = quadro.id');
        
        // Filtrar apenas configs das pastas do usuário logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $model->where('pasta.id_usuario', $_SESSION['id_usuario_logado']);
        }
        
        $list = $model->findAll();
        return $list;
    }

    public function listConfigPastaPorId($id_pasta)
    {
        $model = new ConfigModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'quadro.id_pasta = quadro.id');
        $model->where('quadro.id_pasta', $id_pasta);
        
        // Filtrar apenas configs das pastas do usuário logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $model->where('pasta.id_usuario', $_SESSION['id_usuario_logado']);
        }
        
        $list = $model->findAll();
        return $list;
    }

    public function listarConfig()
    {


        //if(!isset($_SESSION['id_pasta_selecionada'])){
            $id_pasta = $this->request->getGet('id_pasta'); // Obtém o ID da pasta da requisição
            $_SESSION['id_pasta_selecionada'] = $id_pasta;
        /* }    
        else
        {
            $id_pasta = $_SESSION['id_pasta_selecionada'];
            
        } */

        
        $model = new ConfigModel();
        $model->select('dag_configurations.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'pasta.id = dag_configurations.id_pasta');
        $model->where('dag_configurations.id_pasta', $id_pasta);
        
        // Filtrar apenas configs das pastas do usuário logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $model->where('pasta.id_usuario', $_SESSION['id_usuario_logado']);
        }
        
        $list = $model->findAll();
    
        
        return $this->response->setJSON($list); 
        
    }




    public function add()
    {


        /* if (!$this->podeCriarConfig()) {
        
            session()->setFlashdata('limit-message', 'Você atingiu o limite de quadros gratuitos. 
            Por favor, ajude a manter esse site fazendo pix para a chave CPF: 024.253.747-28.');
        
            // Redireciona para a view desejada
            //return redirect()->to('listConfig');
                           
        } */


        $pastaModel = new PastaModel();
        $sourceTypeModel = new SourceTypeModel();
        $data['pastas'] = $pastaModel->listToCombo($_SESSION['id_usuario_logado']);
        $data['source_types'] = $sourceTypeModel->listToCombo();
        # $data['conteudo_csv_json'] = null;
        #$_SESSION['conteudo_arquivo'] = null;
        return view('addConfig',$data);


    }

    public function upd() // 🛑 Renomeado para 'upd' 🛑
    {
        $id = $this->request->getPost('id'); // 🛑 ID obtido via POST 🛑
        
        $configModel = new ConfigModel();
        $sourceTypeModel = new SourceTypeModel();
        $pastaModel = new PastaModel(); 

        // 🛑 1. Busca a tupla de dados
        $Config = $configModel->find($id); // Objeto/Array com os dados da dag_configurations

        if (!$Config) {
            return redirect()->to(route_to('Config.index'))->with('error', 'Configuração não encontrada.');
        }

        // 2. Prepara Listas de Suporte
        $data['pastas'] = $pastaModel->findAll(); // Traz todas as pastas
        $data['source_types'] = $sourceTypeModel->listToCombo(); // Traz todos os tipos de fonte

        // 🛑 3. CARREGA DADOS INDIVIDUAIS (Alinhado com a view) 🛑
        
        // Metadados da DAG
        $data['id'] = $id;
        $data['id_pasta_selecionado'] = $Config->id_pasta; // ID da Pasta selecionada
        $data['dag_id'] = $Config->dag_id;
        $data['owner'] = $Config->owner;
        $data['schedule_interval'] = $Config->schedule_interval;
        $data['description'] = $Config->description;
        $data['is_active'] = $Config->is_active;

        // Configuração de Pipeline/Source
        $data['id_source_type_selecionado'] = $Config->id_source_type; // ID do Tipo de Fonte selecionado
        
        // Caminho da Fonte (usado para MinIO Path, Upload Original ou URI de DB)
        $data['source_filename'] = $Config->source_filename; 
        $data['target_table_name'] = $Config->target_table_name;
        
        // Lógica de Transformação
        $data['python_module_path'] = $Config->python_module_path;
        $data['transform_args'] = $Config->transform_args;

        // Campos SQL estruturados
        $data['sql_connection_id'] = $Config->sql_connection_id ?? null;
        $data['sql_host'] = $Config->sql_host ?? null;
        $data['sql_port'] = $Config->sql_port ?? 3306;
        $data['sql_database_name'] = $Config->sql_database_name ?? null;
        $data['sql_user'] = $Config->sql_user ?? null;
        $data['sql_password'] = $Config->sql_password ?? null;

        // Campos SSH TUNNELING
        $data['ssh_host'] = $Config->ssh_host;
        $data['ssh_port'] = $Config->ssh_port;
        $data['ssh_user'] = $Config->ssh_user;
        $data['ssh_key_path'] = $Config->ssh_key_path;
        $data['ssh_local_port'] = $Config->ssh_local_port;

        // 4. Retorna a View
        // 5. Retorna a View
        return view('updConfig', $data);
        
    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $Configs = array(); function list()  {
        
        $model = new ConfigModel();
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id)
    {
        // Instancia os Models
        $model = new ConfigModel();
        $sourceTypeModel = new SourceTypeModel();
        $postData = $this->request->getPost();

        $sourceTypeID = (int)($postData['id_source_type'] ?? 0);
        $pastaID = (int)($postData['id_pasta'] ?? 0);
        $idSourceType = $postData['id_source_type'] ?? null;
        $sourceFilenameDB = $this->request->getPost('source_filename');

        try {
            if (!$sourceTypeID) {
                throw new \Exception('O tipo de fonte de dados é obrigatório.');
            }

            $sourceTypeConfig = $sourceTypeModel->find($sourceTypeID);
            if (!$sourceTypeConfig) {
                throw new \Exception('Tipo de fonte de dados não encontrado.');
            }

            $sourceTypeDescription = strtolower($sourceTypeConfig['description']);
            $sourceLocation = null;
            $isMultiUpload = $this->request->getPost('enable_multi_upload') === '1';

            // Se for upload múltiplo
            if ($isMultiUpload && (str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json'))) {
                return $this->uploadMultipleFiles();
            } elseif (str_contains($sourceTypeDescription, 'sql')) {
                $dagId = $postData['dag_id'] ?? 'default_dag';
                $bucket = SessionHelper::getUserBucket() ?: ($this->bucketName ?: 'lab01');
                $ownerUsername = \App\Helpers\AirflowHelper::buildUsernameFromEmail(
                    \App\Helpers\SessionHelper::getUserEmail(),
                    (int)\App\Helpers\SessionHelper::getUserId()
                );

                $bucketCheck = \App\Helpers\MinioHelper::ensureBucketExists($bucket);
                if (!$bucketCheck['success']) {
                    log_message('error', "Falha ao criar/verificar bucket: {$bucketCheck['message']}");
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => "Erro ao preparar armazenamento: {$bucketCheck['message']}"
                    ]);
                }

                // Recebe lista de tabelas selecionadas
                $selectedTables = $postData['selected_tables'] ?? [];
                if (!is_array($selectedTables) || count($selectedTables) === 0) {
                    throw new \Exception('Nenhuma tabela SQL selecionada para extração.');
                }

                $uploadedFiles = [];
                foreach ($selectedTables as $tableName) {
                    $sqlHost = $postData['sql_host'] ?? '';
                    $sqlPort = $postData['sql_port'] ?? 3306;
                    $sqlDatabase = $postData['sql_database_name'] ?? '';
                    $sqlUser = $postData['sql_user'] ?? '';
                    $sqlPassword = $postData['sql_password'] ?? '';

                    $dsn = "mysql:host={$sqlHost};port={$sqlPort};dbname={$sqlDatabase}";

                    try {
                        $pdo = new \PDO($dsn, $sqlUser, $sqlPassword);
                        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                        $stmt = $pdo->query("SELECT * FROM `{$tableName}`");
                        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                        if (count($rows) === 0) {
                            continue; // Tabela vazia
                        }

                        // Gerar CSV temporário
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

                        // Upload para MinIO
                        $targetMinioPath = "raw/{$dagId}/{$csvName}";
                        if (!$this->minioClient) {
                            throw new \Exception('Cliente MinIO não inicializado. Verifique configurações.');
                        }

                        $this->minioClient->putObject([
                            'Bucket' => $bucket,
                            'Key' => $targetMinioPath,
                            'SourceFile' => $tmpCsvPath,
                            'ContentType' => 'text/csv',
                        ]);

                        log_message('info', "Upload SQL CSV: {$tableName} para {$targetMinioPath}");
                        $uploadedFiles[] = $targetMinioPath;

                        unlink($tmpCsvPath);
                    } catch (\Exception $e) {
                        log_message('error', "Erro ao extrair/upload tabela {$tableName}: " . $e->getMessage());
                    }
                }

                if (count($uploadedFiles) === 0) {
                    throw new \Exception('Falha ao extrair/uploadar tabelas SQL.');
                }

                $sourceLocation = json_encode($uploadedFiles);
            }

            // Aqui entra o processamento do arquivo
            $filePath = FCPATH . $folder . $file->getName();
            $caminho_formatado = str_replace('\\', '/', $filePath);

            $_SESSION['caminho_formatado'] = $caminho_formatado;
            $_SESSION['conteudo_arquivo'] = file_get_contents($caminho_formatado);

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'O arquivo ' . $file->getName() . ' foi enviado com sucesso.',
                'uploadedFile' => base_url($folder . $file->getName())
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o arquivo: ' . $e->getMessage()
            ]);
        }
    }
    
    // O insert agora será chamado após o processo de upload no novo subform
    public function insert()
    {
        // Instancia os Models
        $model = new ConfigModel();
        $sourceTypeModel = new SourceTypeModel();
        // $minioService = new App\Services\MinIOService(); // Exemplo: seu serviço MinIO

        $postData = $this->request->getPost();
        
        // 🛑 COLETA CORRIGIDA: Coleta o ID numérico da FK 🛑
        $sourceTypeID = (int)($postData['id_source_type'] ?? 0); 
        $pastaID = (int)($postData['id_pasta'] ?? 0); 

        // Valor original do formulário (usado para comparações com strings, ex: 'upload')
        $idSourceType = $postData['id_source_type'] ?? null;


        // O valor inicial (URI, PATH ou o valor que viria do file input)
        $sourceFilenameDB = $this->request->getPost('source_filename'); 

        try {
            if (!$sourceTypeID) {
                throw new \Exception('O tipo de fonte de dados é obrigatório.');
            }

            // 1. BUSCAR A DESCRIÇÃO NO BANCO PARA A LÓGICA CONDICIONAL
            $sourceTypeConfig = $sourceTypeModel->find($sourceTypeID);

            if (!$sourceTypeConfig) {
                 throw new \Exception('Tipo de fonte de dados não encontrado.');
            }
            
            // Pega a descrição em minúsculas para a lógica
            $sourceTypeDescription = strtolower($sourceTypeConfig['description']);

            // Variável que irá conter a string a ser salva em dag_configurations.source_filename
            $sourceLocation = null;
            
            // Verificar se é upload múltiplo
            $isMultiUpload = $this->request->getPost('enable_multi_upload') === '1';
            
            // Se for upload múltiplo, redirecionar para o método específico
            if ($isMultiUpload && (str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json'))) {
                return $this->uploadMultipleFiles();
            }
            
            // 2. Lógica Condicional de Upload/Caminho (usando a descrição textual)
            if (str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json')) {
                
                // O campo 'name' no input de arquivo é 'source_filename' (devido à lógica JS)
                $uploadedFile = $this->request->getFile('source_filename');
                
                if (!$uploadedFile || !$uploadedFile->isValid() || $uploadedFile->hasMoved()) {
                    throw new \Exception('O arquivo de upload é obrigatório ou inválido.');
                }
                
                // --- INÍCIO DA LÓGICA DE UPLOAD PARA MINIO (implementação real) ---

                $dagId = $postData['dag_id'] ?? 'default_dag';
                
                // Extrair target_table_name do nome do arquivo original (sem extensão)
                $originalFileName = $uploadedFile->getClientName();
                $targetTableName = $postData['target_table_name'] ?? pathinfo($originalFileName, PATHINFO_FILENAME);
                
                // Prioriza bucket do usuário logado (alinhado com username), depois config, depois fallback
                $bucket = SessionHelper::getUserBucket() ?: ($this->bucketName ?: 'lab01');
                
                // Alinhar owner com username do Airflow para access_control na DAG
                $ownerUsername = \App\Helpers\AirflowHelper::buildUsernameFromEmail(
                    \App\Helpers\SessionHelper::getUserEmail(),
                    (int) \App\Helpers\SessionHelper::getUserId()
                );

                // GARANTIR QUE O BUCKET DO USUÁRIO EXISTE
                $bucketCheck = \App\Helpers\MinioHelper::ensureBucketExists($bucket);
                
                if (!$bucketCheck['success']) {
                    log_message('error', "Falha ao criar/verificar bucket: {$bucketCheck['message']}");
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => "Erro ao preparar armazenamento: {$bucketCheck['message']}"
                    ]);
                }
                
                if ($bucketCheck['created']) {
                    log_message('info', "Bucket '{$bucket}' criado automaticamente para novo usuário.");
                }

                // VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO
                $fileSize = $uploadedFile->getSize();
                $storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucket, $fileSize);
                
                if (!$storageCheck['allowed']) {
                    log_message('warning', "Upload bloqueado por limite de armazenamento: {$storageCheck['message']}");
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => $storageCheck['message']
                    ]);
                }
                
                log_message('info', "Verificação de armazenamento OK: {$storageCheck['message']}");

                // Preserva o nome original do arquivo no MinIO para rastreabilidade
                $originalFileName = $uploadedFile->getClientName();
                // Opcional: adicionar timestamp/hash antes do nome original para evitar colisão
                $timestamp = date('YmdHis');
                $hash = substr(md5($originalFileName . microtime()), 0, 8);
                $newName = "{$timestamp}_{$hash}_{$originalFileName}";
                $targetMinioPath = "raw/{$targetTableName}/{$newName}";

                // Realiza o upload usando o S3Client inicializado em __construct
                if (!$this->minioClient) {
                    throw new \Exception('Cliente MinIO não inicializado. Verifique configurações.');
                }

                try {
                    $this->minioClient->putObject([
                        'Bucket' => $bucket,
                        'Key' => $targetMinioPath,
                        'SourceFile' => $uploadedFile->getTempName(),
                        'ContentType' => $uploadedFile->getClientMimeType(),
                    ]);

                    log_message('info', "📦 Upload bem-sucedido para bucket: {$bucket}, path: {$targetMinioPath}, owner: {$ownerUsername}");

                    // Se o upload ocorreu, salve a chave no BD
                    $sourceLocation = $targetMinioPath;
                } catch (AwsException $e) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => 'Erro MinIO: Falha no upload. ' . $e->getAwsErrorMessage()
                    ]);
                } catch (\Exception $e) {
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => 'Erro interno de upload: ' . $e->getMessage()
                    ]);
                }

                // --- FIM DA LÓGICA DE UPLOAD PARA MINIO ---
                
            } else if (str_contains($sourceTypeDescription, 'parquet')) {
                // Parquet: usa caminho direto
                $sourceLocation = $postData['source_filename'] ?? null;
                if (empty($sourceLocation)) {
                    throw new \Exception('O Caminho do arquivo Parquet é obrigatório');
                }
                
            } else if (str_contains($sourceTypeDescription, 'sql') || 
                       str_contains($sourceTypeDescription, 'mysql') || 
                       str_contains($sourceTypeDescription, 'postgresql')) {
                
                // SQL: usa campos estruturados (sql_host, sql_connection_id, sql_database_name)
                $sqlHost = $postData['sql_host'] ?? null;
                $sqlConnectionId = $postData['sql_connection_id'] ?? null;
                $sqlDatabaseName = $postData['sql_database_name'] ?? null;
                
                if (empty($sqlHost)) {
                    throw new \Exception('O Host do banco de dados é obrigatório para fontes SQL');
                }
                
                // Extrai o tipo de datasource da descrição (ex: "MySQL", "PostgreSQL")
                $datasourceType = ucfirst(trim(explode('-', $sourceTypeConfig['description'])[0]));
                $datasourceType = str_replace(' ', '', $datasourceType); // Remove espaços
                
                // Formata como "tipo_datasource.host" para source_filename
                $sourceLocation = "{$datasourceType}.{$sqlHost}";
                
                // Armazena os campos SQL separadamente (não em transform_args)
                $postData['sql_connection_id'] = $sqlConnectionId;
                $postData['sql_host'] = $sqlHost;
                $postData['sql_port'] = $postData['sql_port'] ?? 3306;
                $postData['sql_database_name'] = $sqlDatabaseName;
                $postData['sql_user'] = $postData['sql_user'] ?? null;
                $postData['sql_password'] = $postData['sql_password'] ?? null;
                
            } else if (str_contains($sourceTypeDescription, 'api')) {
                // API REST: não exige upload nem campos SQL, apenas salva o endpoint/config no transform_args
                // O campo source_filename pode ser usado para identificar a fonte, mas não é obrigatório
                $sourceLocation = $postData['source_filename'] ?? null;
                // Nenhuma validação extra obrigatória aqui, pois os campos relevantes vão em transform_args
                // (endpoint, headers, params, etc.)
            } else {
                 throw new \Exception('Lógica de processamento de dados não implementada para o tipo: ' . $sourceTypeDescription);
            }

            // Validar se dag_id já existe
            $dagId = $postData['dag_id'] ?? null;
            if (empty($dagId)) {
                throw new \Exception('O nome do pipeline (dag_id) é obrigatório');
            }
            
            $existingDag = $model->where('dag_id', $dagId)->first();
            if ($existingDag) {
                throw new \Exception('Já existe um pipeline com o nome "' . $dagId . '". Por favor, escolha outro nome.');
            }

            // 3. Preparação dos Dados para Inserção (Mapeamento total)
            $dataToInsert = [
                'id_pasta'        => (int)($postData['id_pasta'] ?? 0), // Garante INT
                'id_source_type'  => (int)($postData['id_source_type'] ?? 0), // Garante INT
                'dag_id'                => $dagId,   
                'is_active'             => $postData['is_active'] ?? 1,
                'owner'                 => $ownerUsername ?? ($postData['owner'] ?? 'airflow'),
                'schedule_interval'     => $postData['schedule_interval'] ?? '0 0 * * *',
                'description'           => $postData['description'] ?? null,
                
                // Novos campos para multi-table
                'is_multi_table'        => isset($postData['is_multi_table']) ? (bool)$postData['is_multi_table'] : false,
                'max_parallel_tasks'    => (int)($postData['max_parallel_tasks'] ?? 16),
                
                'source_filename'       => $sourceLocation, // Caminho MinIO ou URI final
                'target_table_name'     => $postData['target_table_name'] ?? null, // NULL para multi-table
                'python_module_path'    => $postData['python_module_path'],
                'transform_args'        => $postData['transform_args'] ?? '{}', 
                
                // Campos SQL estruturados
                'sql_connection_id'     => $postData['sql_connection_id'] ?? null,
                'sql_host'              => $postData['sql_host'] ?? null,
                'sql_port'              => $postData['sql_port'] ?? 3306,
                'sql_database_name'     => $postData['sql_database_name'] ?? null,
                'sql_user'              => $postData['sql_user'] ?? null,
                'sql_password'          => $postData['sql_password'] ?? null,
                
                // CAMPOS PARA SSH TUNNELING
                'ssh_host'              => $postData['ssh_host'] ?? null,
                'ssh_port'              => $postData['ssh_port'] ?? 22, 
                'ssh_user'              => $postData['ssh_user'] ?? null,
                'ssh_key_path'          => $postData['ssh_key_path'] ?? null, 
                'ssh_local_port'        => $postData['ssh_local_port'] ?? 13306,
            ];

            // 4. Inserção no Banco de Dados
            $insertedId = $model->insert($dataToInsert);
            
            // 5. Se for multi-table, salvar seleções de tabelas
            if ($dataToInsert['is_multi_table'] && isset($postData['selected_tables'])) {
                $tableSelectionModel = new \App\Models\TableSelectionModel();
                $selections = [];
                
                foreach ($postData['selected_tables'] as $tableName) {
                    $selections[] = [
                        'table_name' => $tableName,
                        'is_selected' => true
                    ];
                }
                
                $tableSelectionModel->saveTableSelections($model->getInsertID(), $selections);
            }
            
            // 6. Resposta
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Configuração da DAG e fonte de dados salvas com sucesso! (ID: ' . $model->getInsertID() . ')',
                'id' => $model->getInsertID()
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao salvar configuração de DAG: ' . $e->getMessage());
            
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao salvar a configuração. Detalhes: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update() {

        // Instancia os Models
        $model = new ConfigModel();
        $sourceTypeModel = new SourceTypeModel();
        
        // Pega o ID para a atualização e os dados completos
        $id = $this->request->getPost('id');
        $postData = $this->request->getPost();

        // O valor inicial (URI, PATH ou o valor que viria do file input)
        $sourceFilenameDB = $this->request->getPost('source_filename');
        
        // NOTE: upload handling is performed below after validation of source type
        
        // Busca o registro existente para ter o nome da DAG e o caminho original
        $existingConfig = $model->find($id);

        if (!$existingConfig) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Configuração de DAG não encontrada.'
            ]);
        }
        
        // 🛑 COLETA CORRIGIDA: Coleta o ID numérico da FK 🛑
        $sourceTypeID = (int)($postData['id_source_type'] ?? 0); 
        $pastaID = (int)($postData['id_pasta'] ?? 0); 

        try {
            if (!$sourceTypeID) {
                throw new \Exception('O tipo de fonte de dados é obrigatório.');
            }

            // 1. BUSCAR A DESCRIÇÃO NO BANCO PARA A LÓGICA CONDICIONAL
            $sourceTypeConfig = $sourceTypeModel->find($sourceTypeID);

            if (!$sourceTypeConfig) {
                 throw new \Exception('Tipo de fonte de dados não encontrado.');
            }
            
            // Pega a descrição em minúsculas para a lógica
            $sourceTypeDescription = strtolower($sourceTypeConfig['description']);
            
            // Verificar se é upload múltiplo
            $isMultiUpload = $this->request->getPost('enable_multi_upload') === '1';
            
            // Se for upload múltiplo, redirecionar para o método específico
            if ($isMultiUpload && (str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json'))) {
                return $this->updateMultipleFiles($id);
            }

            // Simplicidade: se chegou aqui, temos um ID e vamos atualizar o registro existente.
            // Não criar novo registro nem tentar substituir por criação/deleção.

            $uploadedFile = $this->request->getFile('source_filename');

            // Determina dag_id a partir do POST (permite alterar) ou mantém existente
            $dagId = $postData['dag_id'] ?? $existingConfig->dag_id;

            // Determina source_location inicial como o valor existente
            $sourceLocation = $existingConfig->source_filename;

            // Se um novo arquivo foi enviado, faz upload para MinIO usando o dag_id informado
            if ($uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()) {
                if (!$this->minioClient) {
                    throw new \Exception('Cliente MinIO não inicializado.');
                }
                
                // Prioriza bucket do usuário logado
                $bucket = SessionHelper::getUserBucket() ?: ($this->bucketName ?: 'lab01');
                
                // GARANTIR QUE O BUCKET DO USUÁRIO EXISTE
                $bucketCheck = \App\Helpers\MinioHelper::ensureBucketExists($bucket);
                
                if (!$bucketCheck['success']) {
                    throw new \Exception("Erro ao preparar armazenamento: {$bucketCheck['message']}");
                }
                
                if ($bucketCheck['created']) {
                    log_message('info', "Bucket '{$bucket}' criado automaticamente.");
                }
                
                // VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO
                $fileSize = $uploadedFile->getSize();
                $storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucket, $fileSize);
                
                if (!$storageCheck['allowed']) {
                    log_message('warning', "Upload bloqueado por limite de armazenamento: {$storageCheck['message']}");
                    return $this->response->setJSON([
                        'status' => 'error',
                        'mensagem' => $storageCheck['message']
                    ]);
                }
                
                log_message('info', "Verificação de armazenamento OK: {$storageCheck['message']}");
                
                // Extrair target_table_name do nome do arquivo original (sem extensão)
                $originalFileName = $uploadedFile->getClientName();
                $targetTableName = $postData['target_table_name'] ?? pathinfo($originalFileName, PATHINFO_FILENAME);

                $newName = $uploadedFile->getRandomName();
                $targetMinioPath = "raw/{$targetTableName}/{$newName}";

                try {
                    $this->minioClient->putObject([
                        'Bucket' => $bucket,
                        'Key' => $targetMinioPath,
                        'SourceFile' => $uploadedFile->getTempName(),
                        'ContentType' => $uploadedFile->getClientMimeType(),
                    ]);

                    $sourceLocation = $targetMinioPath;
                } catch (AwsException $e) {
                    throw new \Exception('Erro MinIO: ' . $e->getAwsErrorMessage());
                }
            } else {
                // Se não enviou arquivo, prioriza campo oculto source_filename_original ou o post comum
                $sourceLocation = $postData['source_filename_original'] ?? $postData['source_filename'] ?? $existingConfig->source_filename;
            }

            $dataToUpdate = [
                'id_pasta'            => (int)($postData['id_pasta'] ?? $existingConfig->id_pasta),
                'id_source_type'      => (int)($postData['id_source_type'] ?? $existingConfig->id_source_type),
                'dag_id'              => $dagId,
                'is_active'           => $postData['is_active'] ?? $existingConfig->is_active ?? 1,
                'owner'               => $ownerUsername ?? ($postData['owner'] ?? $existingConfig->owner ?? 'airflow'),
                'schedule_interval'   => $postData['schedule_interval'] ?? $existingConfig->schedule_interval ?? '0 0 * * *',
                'description'         => $postData['description'] ?? $existingConfig->description ?? null,
                'source_filename'     => $sourceLocation,
                'target_table_name'   => $postData['target_table_name'] ?? $existingConfig->target_table_name ?? null,
                'python_module_path'  => $postData['python_module_path'] ?? $existingConfig->python_module_path ?? null,
                'transform_args'      => $postData['transform_args'] ?? $existingConfig->transform_args ?? '{}',
                'sql_connection_id'   => $postData['sql_connection_id'] ?? $existingConfig->sql_connection_id ?? null,
                'sql_host'            => $postData['sql_host'] ?? $existingConfig->sql_host ?? null,
                'sql_port'            => $postData['sql_port'] ?? $existingConfig->sql_port ?? 3306,
                'sql_database_name'   => $postData['sql_database_name'] ?? $existingConfig->sql_database_name ?? null,
                'sql_user'            => $postData['sql_user'] ?? $existingConfig->sql_user ?? null,
                'sql_password'        => $postData['sql_password'] ?? $existingConfig->sql_password ?? null,
                'ssh_host'            => $postData['ssh_host'] ?? $existingConfig->ssh_host ?? null,
                'ssh_port'            => $postData['ssh_port'] ?? $existingConfig->ssh_port ?? 22,
                'ssh_user'            => $postData['ssh_user'] ?? $existingConfig->ssh_user ?? null,
                'ssh_key_path'        => $postData['ssh_key_path'] ?? $existingConfig->ssh_key_path ?? null,
                'ssh_local_port'      => $postData['ssh_local_port'] ?? $existingConfig->ssh_local_port ?? 13306,
            ];

            $updated = $model->update((int)$id, $dataToUpdate);

            if ($updated === false) {
                $errors = $model->errors();
                $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Falha ao atualizar o registro. Tente novamente.';
                throw new \Exception($errorMessage);
            }

            // 🔄 Reserializar DAG após atualizar config para refletir mudanças de datasource
            $this->reserializeDAG($dagId);

            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso! DAG será recarregada.'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Erro ao salvar configuração de DAG: ' . $e->getMessage());
            
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao salvar a configuração. Detalhes: ' . $e->getMessage()
            ]);
        }
    }

    // Função auxiliar para garantir que todos os elementos sejam convertidos para strings
    function convertToString($element) {
        if (is_array($element)) {
            return implode('|', array_map('convertToString', $element));
        }
        return strval($element);
    }

    public function transformaJsonToArrayCSV($dataIn)
    {
        try
        {
            // Inicializar a string CSV

            // Suponha que $data é um array de objetos
            $dataArray = json_decode(json_encode($dataIn), true); // Converte para um array associativo
            
            // Verifique se existe a chave 'data' no array
            if (isset($dataArray['data']) && is_array($dataArray['data'])) {
                $dataContent = $dataArray['data'];
            
                // Conta o número de linhas
                $numLinhas = count($dataContent);
            
                // Conta o número de colunas no primeiro elemento se existir
                if (!empty($dataContent) && isset($dataContent[0])) {
                    $numColunas = count($dataContent[0]);
                    echo "Número de linhas: $numLinhas\n";
                    echo "Número de colunas: $numColunas";
                } else {
                    echo "O conteúdo de 'data' está vazio ou não está no formato esperado.";
                }
            } else {
                echo "A chave 'data' não existe ou não está no formato esperado.";
            }
            
            $csvString = '';
            
            // Iterar sobre os dados e adicionar as linhas
            //$numRows = count($data);
            foreach ($dataContent as $rowIndex => $row) {
                
                    for ($index = 0; $index < $numColunas; $index++) {
                        // Adiciona a célula à string CSV, garantindo que esteja convertida para string
                        $csvString .= strval($row[$index]) . ",";
                    }
                    // Remove a última vírgula
                    $csvString = rtrim($csvString, ',');
                    // Adiciona uma quebra de linha, exceto na última linha
                    if ($rowIndex < $numLinhas - 1) {
                        $csvString .= "\n";
                    }
              }
            

            return $csvString;
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    
       
    public function salvarTabela()
    {
        try
        {
            $request = \Config\Services::request();
            //Aqui voltou as células como array
            //$jsonHeaderData = $request->getJSON();
            $data = $request->getJSON();
            
            $csvTextoData = $this->transformaJsonToArrayCSV($data);

            $_SESSION['conteudo_csv_jason'] = $data;
            $_SESSION['conteudo_arquivo'] = $csvTextoData;
            
            
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Sua planilha foi salva com sucesso!'
                
            ]); 

    } catch (\Exception $e) {
        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Falha ao atualizar a planilha : '. $e->getMessage()
        ]);
    }
        

    }
    
    
    public function delete($id)  {
        
        $model = new ConfigModel();
        $deleted = $model->delete($id);
        

        if($deleted) 
        {
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);

        }
    }

    public function playPorId($idConfig)
    {

        try{

            $model = new ConfigModel();
            $quadro = $model->find($idConfig);
            
            // Converter o array em JSON
            $quadroJSON = json_encode($quadro);

            // Passar o JSON para a view
            return view('playConfig', 
                ['quadro' => $quadroJSON, 
                'nome_arquivo' => $quadro->nome_arquivo, 
                'conteudo_arquivo' => $quadro->conteudo_arquivo,
                'descricao' => $quadro->descricao
                ]
            );

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao recuperar o quadro: ' . $e->getMessage()
            ]);
        }
    
    }

    /**
     * Lista tabelas disponíveis de uma fonte MySQL via AJAX
     */
    public function getAvailableTables()
    {
        try {
            log_message('info', 'getAvailableTables chamado - POST data: ' . json_encode($this->request->getPost()));
            
            $connectionId = $this->request->getPost('connection_id');
            $databaseName = $this->request->getPost('database_name');
            $host = $this->request->getPost('host');
            $port = $this->request->getPost('port') ?? 3306;
            $user = $this->request->getPost('user');
            $password = $this->request->getPost('password') ?? '';
            
            // Validação
            if (!$connectionId || !$databaseName || !$host || !$user) {
                $msg = 'Parâmetros obrigatórios faltando: ';
                $missing = [];
                if (!$connectionId) $missing[] = 'connection_id';
                if (!$databaseName) $missing[] = 'database_name';
                if (!$host) $missing[] = 'host';
                if (!$user) $missing[] = 'user';
                
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => $msg . implode(', ', $missing)
                ]);
            }
            
            log_message('info', "Tentando conectar em $host:$port/$databaseName com usuário $user");
            
            // Corrigir host para Docker: localhost -> mysql (nome do serviço)
            $actualHost = ($host === 'localhost' || $host === '127.0.0.1') ? 'mysql' : $host;
            log_message('info', "Host traduzido para Docker: $actualHost");
            
            // Conecta ao MySQL usando as credenciais fornecidas
            $mysqli = new \mysqli($actualHost, $user, $password, $databaseName, $port);
            
            if ($mysqli->connect_error) {
                log_message('error', "Erro de conexão MySQL: " . $mysqli->connect_error);
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => "Falha ao conectar ao MySQL: " . $mysqli->connect_error
                ]);
            }
            
            log_message('info', 'Conexão MySQL estabelecida com sucesso');
            
            // Busca tabelas do information_schema
            $query = "SELECT 
                        TABLE_NAME as table_name,
                        TABLE_ROWS as row_count,
                        ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as table_size_mb
                      FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = ?
                      AND TABLE_TYPE = 'BASE TABLE'
                      ORDER BY TABLE_NAME";
            
            $stmt = $mysqli->prepare($query);
            if (!$stmt) {
                $mysqli->close();
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Erro ao preparar query: ' . $mysqli->error
                ]);
            }
            
            $stmt->bind_param('s', $databaseName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tables = [];
            while ($row = $result->fetch_assoc()) {
                $tables[] = $row;
            }
            
            $stmt->close();
            $mysqli->close();
            
            log_message('info', count($tables) . ' tabelas encontradas');
            
            // Opcionalmente salva no cache (apenas se modelo existir)
            if (count($tables) > 0 && class_exists('\App\Models\AvailableSourceTableModel')) {
                try {
                    $db = \Config\Database::connect();
                    foreach ($tables as $table) {
                        // Usar REPLACE INTO ou INSERT ... ON DUPLICATE KEY UPDATE
                        $sql = "INSERT INTO available_source_tables 
                                (connection_id, database_name, table_name, row_count, table_size_mb, last_updated)
                                VALUES (?, ?, ?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE 
                                    row_count = VALUES(row_count),
                                    table_size_mb = VALUES(table_size_mb),
                                    last_updated = NOW()";
                        
                        $db->query($sql, [
                            $connectionId,
                            $databaseName,
                            $table['table_name'],
                            $table['row_count'],
                            $table['table_size_mb']
                        ]);
                    }
                    log_message('info', 'Cache de tabelas atualizado com sucesso');
                } catch (\Exception $e) {
                    log_message('warning', 'Erro ao salvar cache de tabelas: ' . $e->getMessage());
                }
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'tables' => $tables,
                'count' => count($tables)
            ]);
            
        } catch (\Throwable $e) {
            log_message('error', 'Erro fatal em getAvailableTables: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Salva seleções de múltiplas tabelas para uma DAG
     */
    public function saveTableSelections()
    {
        $dagConfigId = $this->request->getPost('id_dag_config');
        $selectedTables = $this->request->getPost('selected_tables'); // Array de nomes de tabelas
        
        if (!$dagConfigId || !is_array($selectedTables)) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Dados inválidos'
            ]);
        }
        
        try {
            $tableSelectionModel = new \App\Models\TableSelectionModel();
            
            // Prepara array de seleções
            $selections = [];
            foreach ($selectedTables as $tableName) {
                $selections[] = [
                    'table_name' => $tableName,
                    'is_selected' => true
                ];
            }
            
            $success = $tableSelectionModel->saveTableSelections($dagConfigId, $selections);
            
            if ($success) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => count($selections) . ' tabelas selecionadas salvas'
                ]);
            } else {
                throw new \Exception('Falha ao salvar seleções');
            }
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro ao salvar seleções: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Retorna tabelas já selecionadas para uma DAG
     */
    public function getSelectedTables($dagConfigId)
    {
        try {
            $tableSelectionModel = new \App\Models\TableSelectionModel();
            $selected = $tableSelectionModel->getSelectedTables($dagConfigId);
            
            return $this->response->setJSON([
                'status' => 'success',
                'tables' => $selected
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => $e->getMessage()
            ]);
        }
    }

    public function play()
    {

        try{

            $idConfig = $this->request->getPost('id');
            $model = new ConfigModel();
            $quadro = $model->find($idConfig);
            
            // Converter o array em JSON
            $quadroJSON = json_encode($quadro);

            // Passar o JSON para a view
            return view('playConfig', 
                ['quadro' => $quadroJSON, 
                'nome_arquivo' => $quadro->nome_arquivo, 
                'conteudo_arquivo' => $quadro->conteudo_arquivo,
                'descricao' => $quadro->descricao
                ]
            );

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao recuperar o quadro: ' . $e->getMessage()
            ]);
        }
    
    }

    /**
     * Processar upload múltiplo de arquivos para batch processing
     */
    public function uploadMultipleFiles()
    {
        try {
            // Log de entrada para debug
            error_log('=== Upload Múltiplo Iniciado ===');
            error_log('POST data: ' . json_encode($this->request->getPost()));
            error_log('target_table_name recebido: ' . ($this->request->getPost('target_table_name') ?? 'NULL/VAZIO'));
            
            // Validar dados básicos
            $dagId = $this->request->getPost('dag_id');
            $targetTableName = $this->request->getPost('target_table_name');
            $batchMode = $this->request->getPost('batch_mode') ?? 'parallel';
            $maxParallel = (int)($this->request->getPost('max_parallel_files') ?? 4);
            
            log_message('info', "DAG ID: {$dagId}, Target Table: {$targetTableName}, Batch Mode: {$batchMode}, Max Parallel: {$maxParallel}");
            
            if (!$dagId) {
                throw new \Exception('dag_id é obrigatório');
            }
            
            if (!$targetTableName) {
                throw new \Exception('target_table_name é obrigatório para organizar os arquivos nas camadas do Data Lake');
            }
            
            // Verificar se MinIO está inicializado
            if (!$this->minioClient) {
                log_message('error', 'Cliente MinIO não inicializado!');
                throw new \Exception('Cliente MinIO não está inicializado. Verifique configurações do .env');
            }
            
            // Determinar bucket do usuário (isolamento por usuário)
            $userBucket = \App\Helpers\SessionHelper::getUserBucket();
            $bucketInUse = $userBucket ?: $this->bucketName;
            log_message('info', "MinIO inicializado. Bucket em uso: {$bucketInUse}");
            
            // Garantir que o bucket do usuário existe
            $ensureResult = \App\Helpers\MinioHelper::ensureBucketExists($bucketInUse);
            if (!$ensureResult['success']) {
                log_message('error', "Falha ao garantir bucket '{$bucketInUse}': " . $ensureResult['message']);
                throw new \Exception("Falha ao preparar bucket de armazenamento: " . $ensureResult['message']);
            }
            log_message('info', "Bucket '{$bucketInUse}' verificado/criado com sucesso");
            
            // Obter arquivos múltiplos
            $files = $this->request->getFileMultiple('multiple_files') ?? [];
            $selectFolder = $this->request->getPost('select_folder') == '1';

            log_message('info', 'Arquivos recebidos: ' . count($files));
            log_message('info', 'Select folder: ' . ($selectFolder ? 'SIM' : 'NÃO'));

            // Validar que arquivos foram enviados
            if (empty($files) || !isset($files[0]) || !$files[0]->isValid()) {
                log_message('error', 'Nenhum arquivo válido enviado');
                throw new \Exception('Nenhum arquivo válido foi enviado. Certifique-se de selecionar arquivos ou uma pasta.');
            }

            // Validar extensões dos arquivos
            $this->validateFileExtensions($files);

            // VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO PARA UPLOAD MÚLTIPLO
            // Calcular tamanho total de todos os arquivos
            $totalFilesSize = 0;
            foreach ($files as $file) {
                $totalFilesSize += $file->getSize();
            }
            
            $storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucketInUse, $totalFilesSize);
            
            if (!$storageCheck['allowed']) {
                log_message('warning', "Upload múltiplo bloqueado por limite de armazenamento: {$storageCheck['message']}");
                throw new \Exception(
                    "Limite de armazenamento excedido! " . 
                    "Tamanho total dos arquivos: " . \App\Helpers\MinioHelper::formatBytes($totalFilesSize) . ". " .
                    $storageCheck['message']
                );
            }
            
            log_message('info', "Verificação de armazenamento para upload múltiplo OK: {$storageCheck['message']}");

            // Gerar timestamp único para o batch
            $batchId = uniqid('batch_', true);
            $timestamp = date('YmdHis');
            
            $uploadedFiles = [];
            $errors = [];
            
            // Upload de cada arquivo para MinIO
            foreach ($files as $index => $file) {
                try {
                    $fileName = $file->getName(); // Nome original do arquivo
                    // Gerar nome único para evitar sobrescrita, mas PRESERVAR nome original
                    $fileTimestamp = date('YmdHis');
                    $hash = substr(md5($fileName . microtime()), 0, 8);
                    // Novo padrão: {timestamp}_{hash}_{originalFileName}
                    $uniqueFileName = "{$fileTimestamp}_{$hash}_{$fileName}";
                    // Estrutura: raw/{target_table_name}/{timestamp}_{hash}_{originalFileName}
                    $s3Key = "raw/{$targetTableName}/{$uniqueFileName}";

                    log_message('info', "Fazendo upload do arquivo: {$fileName} para {$s3Key}");
                    log_message('info', "Arquivo temp: {$file->getTempName()}, Tamanho: {$file->getSize()}, MIME: {$file->getMimeType()}");

                    // Validar que o arquivo temp existe
                    if (!file_exists($file->getTempName())) {
                        throw new \Exception("Arquivo temporário não encontrado: " . $file->getTempName());
                    }

                    // Regras adicionais por tipo
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $sourceForUpload = $file->getTempName();
                    $contentType = $file->getMimeType();

                    // Bloquear arquivos vazios
                    if ((int)$file->getSize() <= 0) {
                        throw new \Exception('Arquivo vazio (0 bytes)');
                    }

                    if ($ext === 'json') {
                        $jsonCheck = $this->validateAndPrepareJson($file->getTempName());
                        if (!$jsonCheck['ok']) {
                            throw new \Exception('JSON inválido: ' . $jsonCheck['error']);
                        }
                        if (!empty($jsonCheck['path'])) {
                            $sourceForUpload = $jsonCheck['path'];
                        }
                        $contentType = 'application/json';
                    }

                    // Upload para MinIO
                    $putObjectResult = $this->minioClient->putObject([
                        'Bucket' => $bucketInUse,
                        'Key'    => $s3Key,
                        'Body'   => fopen($sourceForUpload, 'rb'),
                        'ContentType' => $contentType
                    ]);

                    log_message('info', "Resposta putObject: " . json_encode($putObjectResult));

                    $uploadedFiles[] = [
                        'name' => $fileName,
                        's3_key' => $s3Key,
                        'size' => $file->getSize()
                    ];

                    log_message('info', "✅ Upload bem-sucedido: {$fileName}");

                } catch (AwsException $e) {
                    log_message('error', "❌ Erro AWS ao fazer upload de {$file->getName()}: {$e->getAwsErrorMessage()}");
                    $errors[] = [
                        'file' => $file->getName(),
                        'error' => 'Erro MinIO: ' . $e->getAwsErrorMessage()
                    ];
                } catch (\Exception $e) {
                    log_message('error', "❌ Erro ao fazer upload de {$file->getName()}: {$e->getMessage()}");
                    $errors[] = [
                        'file' => $file->getName(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Salvar UMA configuração no banco para processar TODOS os arquivos
            $model = new ConfigModel();
            $sourceTypeModel = new SourceTypeModel();
            
            // Obter dados do formulário
            $postData = $this->request->getPost();
            $sourceTypeId = $postData['id_source_type'];
            
            // Buscar informações do tipo de fonte
            $sourceTypeConfig = $sourceTypeModel->find($sourceTypeId);
            if (!$sourceTypeConfig) {
                throw new \Exception('Tipo de fonte de dados não encontrado');
            }
            
            // Criar lista de pastas únicas para batch processing
            // Todos os arquivos vão para a mesma pasta: raw/{target_table_name}/
            $sourceFilenameValue = "raw/{$targetTableName}/";
            
            // Criar UMA configuração que processa TODOS os arquivos das pastas
            // Alinhar owner com username do Airflow para access_control
            $ownerUsername = \App\Helpers\AirflowHelper::buildUsernameFromEmail(
                \App\Helpers\SessionHelper::getUserEmail(), 
                (int) \App\Helpers\SessionHelper::getUserId()
            );

            $dataToInsert = [
                'dag_id' => $dagId,
                'description' => $postData['description'] ?? "Batch processing - " . count($uploadedFiles) . " arquivo(s) → {$targetTableName}",
                'schedule_interval' => $postData['schedule_interval'] ?? '@daily',
                'owner' => $ownerUsername ?: ($postData['owner'] ?? 'airflow'),
                'start_date' => $postData['start_date'] ?? date('Y-m-d'),
                'id_source_type' => $sourceTypeId,
                'source_filename' => $sourceFilenameValue, // PATH DA(S) PASTA(S)!
                'target_table_name' => $postData['target_table_name'] ?? $dagId,
                'python_module_path' => $postData['python_module_path'] ?? 'spark.medallion_pipeline',
                'transform_args' => $postData['transform_args'] ?? null,
                'is_multi_table' => 0,
                'id_pasta' => $postData['id_pasta'] ?? null,
                'is_active' => 1,
                // Limpar campos SQL/SSH para fontes de arquivo
                'sql_connection_id' => null,
                'sql_host' => null,
                'sql_port' => null,
                'sql_database_name' => null,
                'sql_user' => null,
                'sql_password' => null,
                'ssh_host' => null,
                'ssh_port' => null,
                'ssh_user' => null,
                'ssh_key_path' => null,
                'ssh_local_port' => null,
                'max_parallel_tasks' => $maxParallel
            ];
            
            log_message('info', 'Dados para inserir: ' . json_encode($dataToInsert));
            
            $insertedId = $model->insert($dataToInsert);
            
            if (!$insertedId) {
                $errors_db = $model->errors();
                log_message('error', 'Erro ao inserir no banco: ' . json_encode($errors_db));
                throw new \Exception('Falha ao salvar configuração no banco de dados');
            }
            
            log_message('info', "Configuração salva com ID: {$insertedId}");

            // Gerar e salvar YAML de batch para a DAG criada (facilitar listagem e processamento)
            try {
                $batchYaml = $this->generateBatchYAML($dagId, $batchId, $uploadedFiles, $batchMode, $maxParallel);
                // Nome único por batch para não sobrescrever
                $yamlName = $dagId . '_' . $batchId;
                $this->saveYAMLConfig($yamlName, $batchYaml);
                log_message('info', "Batch YAML gerado e salvo: {$yamlName}");
            } catch (\Exception $e) {
                // Logar mas não falhar o fluxo principal
                log_message('error', 'Falha ao gerar/salvar YAML do batch: ' . $e->getMessage());
            }
            
            // Verificar se houve algum arquivo enviado com sucesso
            if (empty($uploadedFiles) && !empty($errors)) {
                log_message('error', 'Todos os arquivos falharam no upload');
                throw new \Exception('Todos os arquivos falharam no upload. Verifique os logs para mais detalhes.');
            }
            
            // Inicializa $uniqueFolders e $folderPath para evitar erro de variável indefinida
            $uniqueFolders = isset($uniqueFolders) ? $uniqueFolders : [];
            $folderPath = isset($uniqueFolders[0]) ? $uniqueFolders[0] : $sourceFilenameValue;
            return $this->response->setJSON([
                'status' => count($errors) > 0 ? 'partial' : 'success',
                'mensagem' => sprintf(
                    'DAG criada com sucesso! %d arquivo(s) enviado(s) para %s e serão processados em modo %s%s',
                    count($uploadedFiles),
                    count($uniqueFolders) > 1 ? count($uniqueFolders) . " pasta(s) raw" : $folderPath,
                    $batchMode === 'parallel' ? 'paralelo' : 'sequencial',
                    count($errors) > 0 ? sprintf(' (%d arquivo(s) falharam)', count($errors)) : ''
                ),
                'id' => $insertedId,
                'batch_id' => $batchId,
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'batch_mode' => $batchMode,
                'dag_id' => $dagId,
                'folder_path' => $folderPath
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Upload múltiplo falhou: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro no upload: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }
    
    /**
     * Validar extensões de arquivos
     */
    private function validateFileExtensions(array $files): void
    {
        $allowedExtensions = ['csv', 'json'];
        $extensions = [];
        
        foreach ($files as $file) {
            // Usar pathinfo do nome do arquivo em vez de getExtension() que usa MIME type
            $fileName = $file->getName();
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowedExtensions)) {
                throw new \Exception(
                    "Extensão '{$ext}' não permitida no arquivo '{$fileName}'. " .
                    "Apenas CSV e JSON são aceitos."
                );
            }
            
            $extensions[] = $ext;
        }
        
        // Verificar se todos têm a mesma extensão
        $uniqueExtensions = array_unique($extensions);
        if (count($uniqueExtensions) > 1) {
            throw new \Exception(
                'Todos os arquivos devem ter o mesmo formato. ' .
                'Detectados: ' . implode(', ', $uniqueExtensions)
            );
        }
    }

    /**
     * Valida o conteúdo JSON. Suporta JSON (objeto/array) e NDJSON (um JSON por linha).
     * Remove BOM se presente e, quando necessário, grava uma cópia sanitizada
     * temporária para upload. Retorna array com:
     *  - ok: bool
     *  - path: caminho do arquivo sanitizado (opcional)
     *  - mode: 'json' | 'ndjson' (opcional)
     *  - error: mensagem de erro (quando ok=false)
     */
    private function validateAndPrepareJson(string $tempPath): array
    {
        try {
            if (!is_readable($tempPath)) {
                return ['ok' => false, 'error' => 'Arquivo temporário não legível'];
            }
            $content = file_get_contents($tempPath);
            if ($content === false) {
                return ['ok' => false, 'error' => 'Falha ao ler arquivo'];
            }
            if ($content === '' || strlen($content) === 0) {
                return ['ok' => false, 'error' => 'Arquivo vazio'];
            }

            // Remover BOM (UTF-8) se presente
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $content = substr($content, 3);
            }
            $trimmed = ltrim($content);

            // Tentar JSON canônico (objeto/array)
            $isJsonCandidate = strlen($trimmed) > 0 && ($trimmed[0] === '{' || $trimmed[0] === '[');
            if ($isJsonCandidate) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Se conteúdo foi alterado (ex: removeu BOM), salvar cópia
                    if ($content !== file_get_contents($tempPath)) {
                        $tmpSan = tempnam(sys_get_temp_dir(), 'jsonfix_');
                        file_put_contents($tmpSan, $content);
                        return ['ok' => true, 'path' => $tmpSan, 'mode' => 'json'];
                    }
                    return ['ok' => true, 'path' => null, 'mode' => 'json'];
                }
            }

            // Tentar NDJSON (JSON por linha)
            $lines = preg_split("/(\r\n|\n|\r)/", $content);
            $total = 0; $ok = 0; $fail = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') { continue; }
                $total++;
                json_decode($line, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $ok++;
                } else {
                    $fail++;
                }
            }
            if ($total > 0 && $fail === 0) {
                // Válido como NDJSON (mantém como está, apenas remove BOM se foi removido)
                if ($content !== file_get_contents($tempPath)) {
                    $tmpSan = tempnam(sys_get_temp_dir(), 'jsonfix_');
                    file_put_contents($tmpSan, $content);
                    return ['ok' => true, 'path' => $tmpSan, 'mode' => 'ndjson'];
                }
                return ['ok' => true, 'path' => null, 'mode' => 'ndjson'];
            }

            return ['ok' => false, 'error' => 'Conteúdo não é JSON válido nem NDJSON'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Gerar configuração YAML para processamento em batch
     */
    private function generateBatchYAML(
        string $dagId, 
        string $batchId, 
        array $files, 
        string $batchMode, 
        int $maxParallel
    ): array {
        return [
            'dag_id' => $dagId,
            'batch_id' => $batchId,
            'batch_mode' => $batchMode,
            'max_parallel_tasks' => $maxParallel,
            'total_files' => count($files),
            'files' => array_map(function($file) {
                return [
                    'source_path' => $file['s3_key'],
                    'file_name' => $file['name'],
                    'size_bytes' => $file['size']
                ];
            }, $files),
            'pipeline_function' => 'lib.medallion_pipeline.batch_raw_to_medallion',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Salvar configuração YAML
     */
    private function saveYAMLConfig(string $dagId, array $config): void
    {
        // Caminho correto: usa writable/configs (montado no docker-compose)
        $yamlPath = WRITEPATH . 'configs/' . $dagId . '.yml';
        
        // Criar diretório se não existir
        $dir = dirname($yamlPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \Exception("Falha ao criar diretório: {$dir}. Verifique permissões.");
            }
        }
        
        // Verificar se o diretório é gravável
        if (!is_writable($dir)) {
            throw new \Exception("Diretório não tem permissão de escrita: {$dir}");
        }
        
        // Converter para YAML
        $yamlContent = $this->arrayToYaml($config);
        
        if (file_put_contents($yamlPath, $yamlContent) === false) {
            throw new \Exception("Falha ao escrever arquivo YAML: {$yamlPath}");
        }
        
        log_message('info', "YAML config salvo em: {$yamlPath}");
    }
    
    /**
     * Converter array para YAML (simples)
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $prefix . $key . ":\n";
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Array numérico
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $prefix . "  -\n";
                            $yaml .= $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $prefix . "  - " . $this->yamlValue($item) . "\n";
                        }
                    }
                } else {
                    // Array associativo
                    $yaml .= $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $prefix . $key . ": " . $this->yamlValue($value) . "\n";
            }
        }
        
        return $yaml;
    }
    
    /**
     * Formatar valor para YAML
     */
    private function yamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        // String - adicionar aspas se contiver caracteres especiais
        if (preg_match('/[:\{\}\[\],&*#?|\-<>=!%@`]/', $value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        return $value;
    }

    /**
     * Processar upload múltiplo de arquivos para atualizar uma DAG existente
     */
    public function updateMultipleFiles($dagConfigId = null)
    {
        try {
            // Log de entrada para debug
            error_log('=== Update Múltiplo Iniciado ===');
            error_log('POST data: ' . json_encode($this->request->getPost()));
            
            // Usar o ID passado como parâmetro ou obter do POST
            if (!$dagConfigId) {
                $dagConfigId = $this->request->getPost('id');
            }
            
            // Validar que o ID foi fornecido
            if (!$dagConfigId) {
                throw new \Exception('ID da configuração é obrigatório');
            }
            
            // Buscar a configuração existente
            $model = new ConfigModel();
            $existingConfig = $model->find($dagConfigId);
            
            if (!$existingConfig) {
                throw new \Exception('Configuração de DAG não encontrada');
            }
            
            $dagId = $existingConfig->dag_id;
            $targetTableName = $existingConfig->target_table_name ?? $existingConfig->dag_id;
            $postData = $this->request->getPost();
            $batchMode = $postData['batch_mode'] ?? 'parallel';
            $maxParallel = (int)($postData['max_parallel_files'] ?? 4);
            
            log_message('info', "Update DAG ID: {$dagId}, Target Table: {$targetTableName}, Batch Mode: {$batchMode}, Max Parallel: {$maxParallel}");
            
            // Verificar se MinIO está inicializado
            if (!$this->minioClient) {
                log_message('error', 'Cliente MinIO não inicializado!');
                throw new \Exception('Cliente MinIO não está inicializado. Verifique configurações do .env');
            }
            
            // Determinar bucket do usuário (isolamento por usuário)
            $userBucket = \App\Helpers\SessionHelper::getUserBucket();
            $bucketInUse = $userBucket ?: $this->bucketName;
            log_message('info', "MinIO inicializado. Bucket em uso: {$bucketInUse}");
            
            // Garantir que o bucket do usuário existe
            $ensureResult = \App\Helpers\MinioHelper::ensureBucketExists($bucketInUse);
            if (!$ensureResult['success']) {
                log_message('error', "Falha ao garantir bucket '{$bucketInUse}': " . $ensureResult['message']);
                throw new \Exception("Falha ao preparar bucket de armazenamento: " . $ensureResult['message']);
            }
            log_message('info', "Bucket '{$bucketInUse}' verificado/criado com sucesso");
            
            // Obter arquivos múltiplos
            $files = $this->request->getFileMultiple('multiple_files') ?? [];
            $selectFolder = $this->request->getPost('select_folder') == '1';

            log_message('info', 'Arquivos recebidos: ' . count($files));
            log_message('info', 'Select folder: ' . ($selectFolder ? 'SIM' : 'NÃO'));

            // Validar que arquivos foram enviados
            if (empty($files) || !isset($files[0]) || !$files[0]->isValid()) {
                log_message('error', 'Nenhum arquivo válido enviado');
                throw new \Exception('Nenhum arquivo válido foi enviado. Certifique-se de selecionar arquivos ou uma pasta.');
            }

            // Validar extensões dos arquivos
            $this->validateFileExtensions($files);

            // VERIFICAÇÃO DE LIMITE DE ARMAZENAMENTO PARA UPDATE MÚLTIPLO
            // Calcular tamanho total de todos os novos arquivos
            $totalFilesSize = 0;
            foreach ($files as $file) {
                $totalFilesSize += $file->getSize();
            }
            
            $storageCheck = \App\Helpers\MinioHelper::checkStorageLimit($bucketInUse, $totalFilesSize);
            
            if (!$storageCheck['allowed']) {
                log_message('warning', "Update múltiplo bloqueado por limite de armazenamento: {$storageCheck['message']}");
                throw new \Exception(
                    "Limite de armazenamento excedido! " . 
                    "Tamanho total dos novos arquivos: " . \App\Helpers\MinioHelper::formatBytes($totalFilesSize) . ". " .
                    $storageCheck['message']
                );
            }
            
            log_message('info', "Verificação de armazenamento para update múltiplo OK: {$storageCheck['message']}");

            // Gerar timestamp único para o batch
            $batchId = uniqid('batch_', true);
            $timestamp = date('YmdHis');
            
            $uploadedFiles = [];
            $errors = [];
            
            // Upload de cada arquivo para MinIO
            foreach ($files as $index => $file) {
                try {
                    $fileName = $file->getName(); // Nome original do arquivo
                    
                    // Gerar nome único para evitar sobrescrita
                    $fileTimestamp = date('YmdHis');
                    $hash = substr(md5($fileName . microtime()), 0, 8);
                    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                    $uniqueFileName = "{$fileTimestamp}_{$hash}.{$extension}";
                    
                    // Estrutura: raw/{target_table_name}/{timestamp}_{hash}.ext
                    $s3Key = "raw/{$targetTableName}/{$uniqueFileName}";
                    
                    log_message('info', "Fazendo upload do arquivo: {$fileName} para {$s3Key}");
                    log_message('info', "Arquivo temp: {$file->getTempName()}, Tamanho: {$file->getSize()}, MIME: {$file->getMimeType()}");

                    // Validar que o arquivo temp existe
                    if (!file_exists($file->getTempName())) {
                        throw new \Exception("Arquivo temporário não encontrado: " . $file->getTempName());
                    }

                    // Regras adicionais por tipo
                    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    $sourceForUpload = $file->getTempName();
                    $contentType = $file->getMimeType();

                    // Bloquear arquivos vazios
                    if ((int)$file->getSize() <= 0) {
                        throw new \Exception('Arquivo vazio (0 bytes)');
                    }

                    if ($ext === 'json') {
                        $jsonCheck = $this->validateAndPrepareJson($file->getTempName());
                        if (!$jsonCheck['ok']) {
                            throw new \Exception('JSON inválido: ' . $jsonCheck['error']);
                        }
                        if (!empty($jsonCheck['path'])) {
                            $sourceForUpload = $jsonCheck['path'];
                        }
                        $contentType = 'application/json';
                    }

                    // Upload para MinIO
                    $putObjectResult = $this->minioClient->putObject([
                        'Bucket' => $bucketInUse,
                        'Key'    => $s3Key,
                        'Body'   => fopen($sourceForUpload, 'rb'),
                        'ContentType' => $contentType
                    ]);

                    log_message('info', "Resposta putObject: " . json_encode($putObjectResult));

                    $uploadedFiles[] = [
                        'name' => $fileName,
                        's3_key' => $s3Key,
                        'size' => $file->getSize()
                    ];
                    
                    log_message('info', "✅ Upload bem-sucedido: {$fileName}");

                } catch (AwsException $e) {
                    log_message('error', "❌ Erro AWS ao fazer upload de {$file->getName()}: {$e->getAwsErrorMessage()}");
                    $errors[] = [
                        'file' => $file->getName(),
                        'error' => 'Erro MinIO: ' . $e->getAwsErrorMessage()
                    ];
                } catch (\Exception $e) {
                    log_message('error', "❌ Erro ao fazer upload de {$file->getName()}: {$e->getMessage()}");
                    $errors[] = [
                        'file' => $file->getName(),
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Criar lista de pastas únicas para batch processing
            // Como agora cada arquivo vai para sua própria pasta (raw/nome_arquivo/),
            // precisamos salvar todas as pastas envolvidas
            $uniqueFolders = [];
            foreach ($uploadedFiles as $fileInfo) {
                $fileName = basename($fileInfo['s3_key']);
                $fileNameNoExt = pathinfo($fileName, PATHINFO_FILENAME);
                $folderPath = "raw/{$fileNameNoExt}/";
                if (!in_array($folderPath, $uniqueFolders)) {
                    $uniqueFolders[] = $folderPath;
                }
            }
            
            // Se houver múltiplas pastas, salvar como JSON; se apenas uma, usar string simples
            $sourceFilenameValue = count($uniqueFolders) === 1 ? $uniqueFolders[0] : json_encode($uniqueFolders);
            
            // Dados para atualização
            $dataToUpdate = [
                'source_filename' => $sourceFilenameValue, // Atualizar com path(s) da(s) pasta(s)
                'max_parallel_tasks' => $maxParallel
            ];
            
            log_message('info', 'Dados para atualizar: ' . json_encode($dataToUpdate));
            
            $updated = $model->update((int)$dagConfigId, $dataToUpdate);
            
            if (!$updated && $updated !== false) { // Se retorna NULL, pode ter sucesso mesmo assim
                // Verificar se realmente houve erro
                $errors_db = $model->errors();
                if (!empty($errors_db)) {
                    log_message('error', 'Erro ao atualizar no banco: ' . json_encode($errors_db));
                    throw new \Exception('Falha ao atualizar configuração no banco de dados');
                }
            }
            
            log_message('info', "Configuração atualizada com sucesso");

            // Gerar e salvar YAML de batch para a DAG criada (facilitar listagem e processamento)
            try {
                $batchYaml = $this->generateBatchYAML($dagId, $batchId, $uploadedFiles, $batchMode, $maxParallel);
                // Nome único por batch para não sobrescrever
                $yamlName = $dagId . '_' . $batchId;
                $this->saveYAMLConfig($yamlName, $batchYaml);
                log_message('info', "Batch YAML gerado e salvo: {$yamlName}");
            } catch (\Exception $e) {
                // Logar mas não falhar o fluxo principal
                log_message('error', 'Falha ao gerar/salvar YAML do batch: ' . $e->getMessage());
            }
            
            // Verificar se houve algum arquivo enviado com sucesso
            if (empty($uploadedFiles) && !empty($errors)) {
                log_message('error', 'Todos os arquivos falharam no upload');
                throw new \Exception('Todos os arquivos falharam no upload. Verifique os logs para mais detalhes.');
            }
            
            if (!isset($uniqueFolders)) {
                $uniqueFolders = [];
            }
            return $this->response->setJSON([
                'status' => count($errors) > 0 ? 'partial' : 'success',
                'mensagem' => sprintf(
                    'DAG atualizada com sucesso! %d arquivo(s) enviado(s) para %s e serão processados em modo %s%s',
                    count($uploadedFiles),
                    count($uniqueFolders) > 1 ? count($uniqueFolders) . " pasta(s) raw" : (isset($uniqueFolders[0]) ? $uniqueFolders[0] : 'raw/'),
                    $batchMode === 'parallel' ? 'paralelo' : 'sequencial',
                    count($errors) > 0 ? sprintf(' (%d arquivo(s) falharam)', count($errors)) : ''
                ),
                'id' => $dagConfigId,
                'batch_id' => $batchId,
                'uploaded_files' => $uploadedFiles,
                'errors' => $errors,
                'batch_mode' => $batchMode,
                'dag_id' => $dagId,
                'folder_paths' => $uniqueFolders
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Update múltiplo falhou: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro no upload: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * Reserializa uma DAG no Airflow após atualizar a configuração.
     * Executa: airflow dags reserialize --dag-id=<dag_id>
     * 
     * @param string $dagId O ID da DAG a reserializar
     */
    private function reserializeDAG(string $dagId): void
    {
        try {
            // Comando para reserializar a DAG específica no Airflow
            $command = "docker exec airflow-scheduler airflow dags reserialize --dag-id={$dagId}";
            
            log_message('info', "[ConfigController] Reserializando DAG: {$dagId}");
            
            // Executa o comando (best-effort, não bloqueia se falhar)
            $output = shell_exec("{$command} 2>&1");
            
            if (strpos($output, 'error') !== false || strpos($output, 'Error') !== false) {
                log_message('warning', "[ConfigController] Possível erro ao reserializar {$dagId}: {$output}");
            } else {
                log_message('info', "[ConfigController] ✅ DAG {$dagId} reserializada com sucesso.");
            }
        } catch (\Exception $e) {
            log_message('error', "[ConfigController] Falha ao reserializar DAG {$dagId}: " . $e->getMessage());
            // Não lança exceção; permite que a atualização seja bem-sucedida mesmo se reserializar falhar
        }
    }

}

