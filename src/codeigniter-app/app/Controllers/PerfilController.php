<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PerfilModel;

class PerfilController extends BaseController
{
    public function index()
    {
        //

        
        $list = $this->list();        
        return view('listPerfil',['list'=>$list]);


    }

    


    public function add()
    {

        return view('addPerfil');

    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        
        $model = new PerfilModel();
        $perfil = $model->find($id);

        $data = [
            'id' => $perfil->id,
            'descricao' => $perfil->descricao
        ];

        return view('updPerfil',$data);

    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $perfis = array(); function list()  {
        
        $model = new PerfilModel();
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id )  {
        
        $model = new PerfilModel();
        $perfil = $model->find($id);
        return $perfil;
    }

    public function insert() {
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        $model = new PerfilModel();
        $inserted = $model->insert($data);
    
        return $this->response->setJSON([
            'status' => $inserted ? 'success' : 'warning',
            'mensagem' => $inserted ? 'Registro inserido com sucesso!' : 'Falha ao inserir o registro. Tente novamente.'
        ]);
    }
    
    public function update() {
        $model = new PerfilModel();
        $id = $this->request->getPost('id');
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        $updated = $model->update($id, $data);
    
        return $this->response->setJSON([
            'status' => $updated ? 'success' : 'warning',
            'mensagem' => $updated ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
        ]);
        
    }
    
    public function delete($id)  {
        
        $model = new PerfilModel();
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
