<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ConfigModel;
use App\Models\PastaModel;
use CodeIgniter\CLI\Console;

class ConfigController extends BaseController
{
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
        $model->join('pasta', 'pasta.id = dag_configurations.pasta_id');
        $model->where('dag_configurations.pasta_id', $id_pasta);
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
        $data['pastas'] = $pastaModel->listToCombo($_SESSION['id_usuario_logado']);
        $data['conteudo_csv_json'] = null;
        $_SESSION['conteudo_arquivo'] = null;
        return view('addConfig',$data);


    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        $model = new ConfigModel();
        $Config = $model->find($id);

        $pastaModel = new PastaModel();
        $data['pastas'] = $pastaModel->listToCombo($_SESSION['id_usuario_logado']);
        $data['id_pasta_selecionado'] = $Config->id_pasta;
        $data['id'] = $Config->id;
        $data['descricao'] = $Config->descricao;
        $data['arquivo'] = $Config->arquivo;
        $data['nome_arquivo'] = $Config->nome_arquivo;
        
        //$conteudo_csv = str_replace(["\r\n", "\r", "\n"], "\\n", base64_decode($Config->conteudo_arquivo));
        $conteudo_csv = base64_decode($Config->conteudo_arquivo);

        $_SESSION['conteudo_arquivo'] = $conteudo_csv;
        
        $data['conteudo_arquivo'] = $conteudo_csv;


        //$data['conteudo_csv_json'] = json_encode($conteudo_csv);

        $data['conteudo_csv_json'] =  json_encode($conteudo_csv, JSON_UNESCAPED_UNICODE);

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
        // Instancia o Model (e o Service MinIO se for o caso)
        $model = new ConfigModel();
        // $minioService = new App\Services\MinIOService(); // Exemplo: seu serviço MinIO
        
        $postData = $this->request->getPost();
        $sourceType = $postData['source_type'] ?? null;
        
        try {
            // Variável que irá conter a string a ser salva em dag_configurations.source_filename
            $sourceLocation = null;
            
            // 1. Lógica Condicional de Upload/Caminho
            if ($sourceType === 'csv' || $sourceType === 'json') {
                
                // O campo 'name' no input de arquivo é 'source_filename' (devido à lógica JS)
                $uploadedFile = $this->request->getFile('source_filename');
                
                if (!$uploadedFile || !$uploadedFile->isValid() || $uploadedFile->hasMoved()) {
                     throw new \Exception('O arquivo para ' . strtoupper($sourceType) . ' é obrigatório ou inválido.');
                }
                
                // --- INÍCIO DA LÓGICA DE UPLOAD PARA MINIO (SUBSTITUA ESTE BLOCO) ---
                
                $dagId = $postData['dag_id'] ?? 'default_dag';
                $bucket = 'raw';
                
                // Exemplo: Salvar com um nome único dentro de uma subpasta específica da DAG
                $newName = $uploadedFile->getRandomName(); // CI gera um nome único
                $targetMinioPath = "{$bucket}/{$dagId}/{$newName}";
                
                // SIMULAÇÃO: No ambiente de produção, você faria o upload aqui:
                // $minioService->upload($uploadedFile->getTempName(), $targetMinioPath);

                // O valor salvo no banco de dados é o caminho MinIO (chave S3)
                $sourceLocation = $targetMinioPath; 
                
                // --- FIM DA LÓGICA DE UPLOAD PARA MINIO ---
                
            } else if ($sourceType === 'parquet' || $sourceType === 'database') {
                
                // Se não é upload, o valor de texto (URI/Caminho) já está no $_POST['source_filename']
                $sourceLocation = $postData['source_filename'] ?? null;
                if (empty($sourceLocation)) {
                    throw new \Exception('O Caminho/URI de conexão é obrigatório para o tipo ' . strtoupper($sourceType));
                }
                
            } else {
                throw new \Exception('Tipo de fonte de dados não selecionado.');
            }

            // 2. Preparação dos Dados para Inserção (Mapeamento total)
            $dataToInsert = [
                'pasta_id'             => $postData['pasta_id'], // Novo campo FK
                'dag_id'               => $postData['dag_id'],   // Campo DAG ID
                'is_active'            => $postData['is_active'] ?? 1,
                'owner'                => $postData['owner'] ?? 'webapp_user',
                'schedule_interval'    => $postData['schedule_interval'] ?? '0 0 * * *',
                'description'          => $postData['description'] ?? null,
                'source_type'          => $sourceType,
                'source_filename'      => $sourceLocation, // Caminho MinIO ou URI final
                'target_table_name'    => $postData['target_table_name'],
                'python_module_path'   => $postData['python_module_path'],
                // Garantir que transform_args seja JSON válido (ou string vazia/null)
                'transform_args'       => $postData['transform_args'] ?? '{}', 
                
                // CAMPOS PARA SSH TUNNELING
                'ssh_host'             => $postData['ssh_host'] ?? null,
                'ssh_port'             => $postData['ssh_port'] ?? 22, // Usa 22 como padrão se não for fornecido
                'ssh_user'             => $postData['ssh_user'] ?? null,
                
                // O campo da View é 'ssh_key_path_value' (o campo hidden que guarda o path)
                'ssh_key_path'         => $postData['ssh_key_path'] ?? null, 
                
                'ssh_local_port'       => $postData['ssh_local_port'] ?? 13306, // Usa 13306 como padrão
            ];


            // 3. Inserção no Banco de Dados
            $model->insert($dataToInsert);
            
            // 4. Resposta
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Configuração da DAG e fonte de dados salvas com sucesso! (ID: ' . $model->getInsertID() . ')'
            ]);
            
        } catch (\Exception $e) {
            // Logs são essenciais para debugar falhas de upload/conexão
            log_message('error', 'Erro ao salvar configuração de DAG: ' . $e->getMessage());
            
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao salvar a configuração. Detalhes: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update() {

        try {

            
            $id = $this->request->getPost('id');



            $data = [
                'id' => $id,
                'descricao' => $this->request->getPost('descricao'),
                'id_pasta' => $this->request->getPost('id_pasta'),
                'nome_arquivo' => $this->request->getPost('nome_arquivo'),
                'conteudo_arquivo' => base64_encode($_SESSION['conteudo_arquivo'])
            ];

            
            if(isset($_SESSION['caminho_formatado']))
            {
                $data = array_merge($data, [
                    'arquivo' => $_SESSION['caminho_formatado']
                ]);
            }
                    
                
            
            $model = new ConfigModel();
        
            $model->update($id, $data);
            $_SESSION['conteudo_arquivo'] = null;
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro inserido com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
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
