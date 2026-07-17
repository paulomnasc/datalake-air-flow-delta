<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PastaModel;
use App\Models\UsuarioModel;

class PastaController extends BaseController
{
    public function index()
    {
        //

        
        $list = $this->listPastaByIdUsuario($_SESSION['id_usuario_logado']);        
        return view('listPasta',['list'=>$list]);


    }

    public function listPastaByIdUsuario($id_usuario)
    {
        $model = new PastaModel();
        $model->select('pasta.*');
        $model->join('usuario', 'usuario.id = pasta.id_usuario');
        $model->where('usuario.id', $id_usuario);
        
        $list = $model->findAll();
        return $list;
    }


    public function add()
    {
        
        return view('addPasta');

    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        
        $model = new PastaModel();
        $model = $model->find($id);
        $data['id_usuario_selecionado'] = $model->id_usuario;
        $data['id'] = $model->id;
        $data['descricao'] = $model->descricao;
        
        return view('updPasta',$data);

    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $Pastas = array(); function list()  {
        
        $model = new PastaModel();
        
        // Filtrar apenas pastas do usuário logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $model->where('id_usuario', $_SESSION['id_usuario_logado']);
        }
        
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id )  {
        
        $model = new PastaModel();
        $Pasta = $model->find($id);
        return $Pasta;
    }

    public function insert() {
        $data = [
            'descricao' => $this->request->getPost('descricao'),
            'id_usuario' => $_SESSION['id_usuario_logado']
        ];
        $model = new PastaModel();
        
        try {
            $inserted = $model->insert($data);
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

        $model = new PastaModel();
        $id = $this->request->getPost('id');
        $data = [
            'id' => $this->request->getPost('id'),
            'descricao' => $this->request->getPost('descricao'),
            'id_usuario' => $_SESSION['id_usuario_logado']
            
        ];

        try {
            $updated = $model->update($id, $data);
                
            return $this->response->setJSON([
                'status' => $updated ? 'success' : 'warning',
                'mensagem' => $updated ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
        ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }
        
    }
    
    public function delete($id)  {
        
        $model = new PastaModel();
        $deleted = $model->delete($id);
        

        if($deleted) 
        {
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);

        }
    }
}
