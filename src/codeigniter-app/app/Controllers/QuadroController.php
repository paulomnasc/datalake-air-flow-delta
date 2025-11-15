<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\QuadroModel;
use App\Models\PastaModel;
use CodeIgniter\CLI\Console;

class QuadroController extends BaseController
{
    public function index()
    {
        //

        $pastaModel = new PastaModel();
        $pastas = $pastaModel->listToCombo($_SESSION['id_usuario_logado']); 
        //var_dump($pastas); die();
        // Combine os dados em um único array
        //Esse set para nulo só deve acontecer se a rota anterior for listQuadro
        $rotaAnterior = $this->previousRoute();
        if($rotaAnterior && $rotaAnterior == "listQuadro")
            $_SESSION['id_pasta_selecionada'] = null;
        
        return view('listQuadro',['pastas'=>$pastas]);
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
    function podeCriarQuadro()
    {
        $usuario_logado = ($_SESSION['usuario_logado'] == 1);

        if ($usuario_logado) {
            // Verificar limite no banco de dados para usuários logados
            $idUsuario = $_SESSION['id_usuario_logado'];
            $perfilUsuario = $_SESSION['perfil_usuario_logado'];

            if($perfilUsuario == 'Admin' || $perfilUsuario == 'Assinante') return true;

            $quantidadeQuadros = $this->contarQuadrosPorUsuario($idUsuario);

            return ($quantidadeQuadros < 4);
        } 
        
    }

    // Função fictícia que simula a contagem de quadros no banco de dados
    function contarQuadrosPorUsuario($idUsuario)
    {
        // Esta função deve contar os quadros criados pelo usuário no banco de dados
        //return 2; // Exemplo: 2 quadros criados

        $model = new QuadroModel();

        return $model->ObterTotalQuadros($idUsuario);

    }


    public function listQuadroPasta()
    {
        $model = new QuadroModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'quadro.id_pasta = quadro.id');
        $list = $model->findAll();
        return $list;
    }

    public function listQuadroPastaPorId($id_pasta)
    {
        $model = new QuadroModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'quadro.id_pasta = quadro.id');
        $model->where('quadro.id_pasta', $id_pasta);
        $list = $model->findAll();
        return $list;
    }

    public function listarQuadro()
    {


        //if(!isset($_SESSION['id_pasta_selecionada'])){
            $id_pasta = $this->request->getGet('id_pasta'); // Obtém o ID da pasta da requisição
            $_SESSION['id_pasta_selecionada'] = $id_pasta;
        /* }    
        else
        {
            $id_pasta = $_SESSION['id_pasta_selecionada'];
            
        } */

        
        $model = new QuadroModel();
        $model->select('quadro.*, pasta.descricao as pasta_descricao');
        $model->join('pasta', 'pasta.id = quadro.id_pasta');
        $model->where('quadro.id_pasta', $id_pasta);
        $list = $model->findAll();
    
        
        return $this->response->setJSON($list); 
        
    }




    public function add()
    {


        /* if (!$this->podeCriarQuadro()) {
        
            session()->setFlashdata('limit-message', 'Você atingiu o limite de quadros gratuitos. 
            Por favor, ajude a manter esse site fazendo pix para a chave CPF: 024.253.747-28.');
        
            // Redireciona para a view desejada
            //return redirect()->to('listQuadro');
                           
        } */


        $pastaModel = new PastaModel();
        $data['pastas'] = $pastaModel->listToCombo($_SESSION['id_usuario_logado']);
        $data['conteudo_csv_json'] = null;
        $_SESSION['conteudo_arquivo'] = null;
        return view('addQuadro',$data);


    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        $model = new QuadroModel();
        $Quadro = $model->find($id);

        $pastaModel = new PastaModel();
        $data['pastas'] = $pastaModel->listToCombo($_SESSION['id_usuario_logado']);
        $data['id_pasta_selecionado'] = $Quadro->id_pasta;
        $data['id'] = $Quadro->id;
        $data['descricao'] = $Quadro->descricao;
        $data['arquivo'] = $Quadro->arquivo;
        $data['nome_arquivo'] = $Quadro->nome_arquivo;
        
        //$conteudo_csv = str_replace(["\r\n", "\r", "\n"], "\\n", base64_decode($Quadro->conteudo_arquivo));
        $conteudo_csv = base64_decode($Quadro->conteudo_arquivo);

        $_SESSION['conteudo_arquivo'] = $conteudo_csv;
        
        $data['conteudo_arquivo'] = $conteudo_csv;


        //$data['conteudo_csv_json'] = json_encode($conteudo_csv);

        $data['conteudo_csv_json'] =  json_encode($conteudo_csv, JSON_UNESCAPED_UNICODE);

        return view('updQuadro', $data);

    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $Quadros = array(); function list()  {
        
        $model = new QuadroModel();
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id )  {
        
        $model = new QuadroModel();
        $Quadro = $model->find($id);
        return $Quadro;
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
    public function insert() {

        
        try {

            $data = [
                'descricao' => $this->request->getPost('descricao'),
                'id_pasta' => $this->request->getPost('id_pasta'),
                'arquivo' => $_SESSION['caminho_formatado'],
                'nome_arquivo' => $this->request->getPost('nome_arquivo'),
                'conteudo_arquivo' => base64_encode($_SESSION['conteudo_arquivo'])
            ];

            $model = new QuadroModel();
        
            $model->insert($data);
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
                    
                
            
            $model = new QuadroModel();
        
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
        
        $model = new QuadroModel();
        $deleted = $model->delete($id);
        

        if($deleted) 
        {
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);

        }
    }

    public function playPorId($idQuadro)
    {

        try{

            $model = new QuadroModel();
            $quadro = $model->find($idQuadro);
            
            // Converter o array em JSON
            $quadroJSON = json_encode($quadro);

            // Passar o JSON para a view
            return view('playQuadro', 
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

            $idQuadro = $this->request->getPost('id');
            $model = new QuadroModel();
            $quadro = $model->find($idQuadro);
            
            // Converter o array em JSON
            $quadroJSON = json_encode($quadro);

            // Passar o JSON para a view
            return view('playQuadro', 
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
