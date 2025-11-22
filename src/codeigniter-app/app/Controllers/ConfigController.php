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
        $minioConfig = [
            'endpoint' => getenv('MINIO_ENDPOINT'), 
            'region' => getenv('MINIO_REGION'),
            'version' => getenv('MINIO_VERSION'),
            'use_path_style_endpoint' => filter_var(getenv('MINIO_USE_PATH_STYLE_ENDPOINT'), FILTER_VALIDATE_BOOLEAN), 
            'credentials' => [
                'key' => getenv('MINIO_ACCESS_KEY_ID'),
                'secret' => getenv('MINIO_SECRET_ACCESS_KEY'),
            ],
        ];
        
        $this->bucketName = getenv('MINIO_BUCKET_RAW');

        // Instancia o S3Client (MinIO)
        try {
            $this->minioClient = new S3Client($minioConfig);
        } catch (\Exception $e) {
            // Em caso de falha na inicialização (ex: credenciais ausentes)
            log_message('error', 'Falha ao inicializar S3Client (MinIO): ' . $e->getMessage());
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
        $list = $model->findAll();
        return $list;
    }

    public function listConfigPastaPorId($id_pasta)
    {
        $model = new ConfigModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'quadro.id_pasta = quadro.id');
        $model->where('quadro.id_pasta', $id_pasta);
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

    public function findById(int $id )  {
        
        $model = new ConfigModel();
        $Config = $model->find($id);
        return $Config;
    }

    
    public function upload()
    {
        
        try {

            $file = $this->request->getFile('arquivo');

            if ($file && $file->isValid() && !$file->hasMoved()) {

                $folder = 'uploads/' . $_SESSION['id_usuario_logado'] . '/';

                $file->move(FCPATH  . $folder);

                // Obtém o caminho completo do arquivo movido
                $filePath = FCPATH  . $folder . $file->getName();

                $caminho_formatado = str_replace('\\', '/', $filePath);

                $_SESSION['caminho_formatado'] = $caminho_formatado;
                $conteudo = file_get_contents($caminho_formatado);
                $_SESSION['conteudo_arquivo'] = $conteudo;        
                
            } else {
                // Trate o erro do arquivo aqui, caso não seja válido ou já tenha sido movido
                return redirect()->back()->with('error', 'Erro ao processar o arquivo.');
            }
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'O arquivo ' . $file->getName() . ' foi enviado com sucesso.',
                'uploadedFile' => base_url($folder . $file->getName()) // Isso deve estar presente
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
            
            // 2. Lógica Condicional de Upload/Caminho (usando a descrição textual)
            if (str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json')) {
                
                // O campo 'name' no input de arquivo é 'source_filename' (devido à lógica JS)
                $uploadedFile = $this->request->getFile('source_filename');
                
                if (!$uploadedFile || !$uploadedFile->isValid() || $uploadedFile->hasMoved()) {
                    throw new \Exception('O arquivo de upload é obrigatório ou inválido.');
                }
                
                // --- INÍCIO DA LÓGICA DE UPLOAD PARA MINIO (implementação real) ---

                $dagId = $postData['dag_id'] ?? 'default_dag';
                $bucket = $this->bucketName ?: 'lab01';

                // Gera nome único para o arquivo no MinIO
                $newName = $uploadedFile->getRandomName(); // CI gera um nome único
                $targetMinioPath = "raw/{$dagId}/{$newName}";

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
                
            } else if (str_contains($sourceTypeDescription, 'parquet') || 
                       str_contains($sourceTypeDescription, 'database') || 
                       str_contains($sourceTypeDescription, 'postgresql') || 
                       str_contains($sourceTypeDescription, 'api')) {
                
                // Se não é upload, o valor de texto (URI/Caminho) já está no $_POST['source_filename']
                $sourceLocation = $postData['source_filename'] ?? null;
                if (empty($sourceLocation)) {
                    throw new \Exception('O Caminho/URI de conexão é obrigatório para o tipo ' . strtoupper($sourceTypeDescription));
                }
                
            } else {
                 throw new \Exception('Lógica de processamento de dados não implementada para o tipo: ' . $sourceTypeDescription);
            }

            // 3. Preparação dos Dados para Inserção (Mapeamento total)
            $dataToInsert = [
                'id_pasta'        => (int)($postData['id_pasta'] ?? 0), // Garante INT
                'id_source_type'  => (int)($postData['id_source_type'] ?? 0), // Garante INT
                'dag_id'                => $postData['dag_id'],   
                'is_active'             => $postData['is_active'] ?? 1,
                'owner'                 => $postData['owner'] ?? 'webapp_user',
                'schedule_interval'     => $postData['schedule_interval'] ?? '0 0 * * *',
                'description'           => $postData['description'] ?? null,
                
                // 🛑 CORREÇÃO CRUCIAL: Salvar a FK (ID) no novo campo 'id_source_type'
                 
                
                'source_filename'       => $sourceLocation, // Caminho MinIO ou URI final
                'target_table_name'     => $postData['target_table_name'],
                'python_module_path'    => $postData['python_module_path'],
                'transform_args'        => $postData['transform_args'] ?? '{}', 
                
                // CAMPOS PARA SSH TUNNELING
                'ssh_host'              => $postData['ssh_host'] ?? null,
                'ssh_port'              => $postData['ssh_port'] ?? 22, 
                'ssh_user'              => $postData['ssh_user'] ?? null,
                'ssh_key_path'          => $postData['ssh_key_path'] ?? null, 
                'ssh_local_port'        => $postData['ssh_local_port'] ?? 13306,
            ];

            // 4. Inserção no Banco de Dados
            $model->insert($dataToInsert);
            
            // 5. Resposta
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Configuração da DAG e fonte de dados salvas com sucesso! (ID: ' . $model->getInsertID() . ')'
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

            // Variável que irá conter a string a ser salva em dag_configurations.source_filename
            $sourceLocation = $existingConfig->source_filename; // Inicializa com o valor atual do banco
            
            // 2. Lógica Condicional de Upload/Caminho (usando a descrição textual)
            $fileRequired = str_contains($sourceTypeDescription, 'csv') || str_contains($sourceTypeDescription, 'json');
            
            $uploadedFile = $this->request->getFile('source_file_upload');

            if ($fileRequired) {
                if ($uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()) {
                    // Cenário A: Novo arquivo enviado e válido (Fazendo upload)
                    $dagId = $existingConfig->dag_id; // Mantém o DAG ID original para o caminho
                    $bucket = 'raw';

                    $newName = $uploadedFile->getRandomName();
                    $targetMinioPath = "{$bucket}/{$dagId}/{$newName}";

                    // Faz o upload real do arquivo para MinIO
                    if (!$this->minioClient) {
                        throw new \Exception('Cliente MinIO não inicializado.');
                    }

                    try {
                        $this->minioClient->putObject([
                            'Bucket' => $this->bucketName ?: 'lab01',
                            'Key' => $targetMinioPath,
                            'SourceFile' => $uploadedFile->getTempName(),
                            'ContentType' => $uploadedFile->getClientMimeType(),
                        ]);

                        $sourceLocation = $targetMinioPath;
                    } catch (AwsException $e) {
                        throw new \Exception('Erro MinIO: ' . $e->getAwsErrorMessage());
                    }
                } else {
                    // Cenário B: Não há novo upload, usa o caminho original.
                    // Prioriza o caminho original que veio via campo oculto, senão usa o valor do banco
                    $sourceLocation = $postData['source_filename_original'] ?? $existingConfig->source_filename;
                
                    // Se o arquivo for requerido, mas não houver upload NEM caminho original, é um erro.
                    if (empty($sourceLocation)) {
                        throw new \Exception('O arquivo de origem é obrigatório para este tipo de fonte, e nenhum caminho anterior foi encontrado.');
                    }
                }
            } else {
                // 🛑 CORREÇÃO CRUCIAL APLICADA AQUI:
                // Se o tipo de fonte NÃO requer arquivo (Ex: SQL, Parquet), o campo ativo na view 
                // (URI ou Path) foi renomeado pelo JS para 'source_filename'.
                // Usamos este valor do POST como a nova localização/URI.
                $sourceLocation = $postData['source_filename'] ?? $existingConfig->source_filename;
            }

            // 3. Preparação dos Dados para Inserção (Mapeamento total)
            $dataToUpdate = [
                'id'                  => $id,
                'id_pasta'            => $pastaID, // Já é INT
                'id_source_type'      => $sourceTypeID, // Já é INT
                'dag_id'              => $existingConfig->dag_id, // Mantém o ID original
                'is_active'           => $postData['is_active'] ?? 1,
                'owner'               => $postData['owner'] ?? 'webapp_user',
                'schedule_interval'   => $postData['schedule_interval'] ?? '0 0 * * *',
                'description'         => $postData['description'] ?? null,
                
                // 🛑 VALOR CORRIGIDO: Agora $sourceLocation contém a URI ou o Path 🛑
                'source_filename'     => $sourceLocation, 
                'target_table_name'   => $postData['target_table_name'],
                'python_module_path'  => $postData['python_module_path'],
                'transform_args'      => $postData['transform_args'] ?? '{}', 
                
                // CAMPOS PARA SSH TUNNELING
                'ssh_host'            => $postData['ssh_host'] ?? null,
                'ssh_port'            => $postData['ssh_port'] ?? 22, 
                'ssh_user'            => $postData['ssh_user'] ?? null,
                'ssh_key_path'        => $postData['ssh_key_path'] ?? null, 
                'ssh_local_port'      => $postData['ssh_local_port'] ?? 13306,
            ];

            // 4. Inserção no Banco de Dados
            $updated = $model->save($dataToUpdate);
            
            if (!$updated) {
                 // Captura erros da Model/Validação, se existirem
                 $errors = $model->errors();
                 $errorMessage = !empty($errors) ? implode(', ', $errors) : 'Falha ao atualizar o registro. Tente novamente.';
                 throw new \Exception($errorMessage);
            }

             return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso!'
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

}
