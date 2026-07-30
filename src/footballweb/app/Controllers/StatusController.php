<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\StatusModel;

class StatusController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listStatus', ['list' => $list]);
    }

    public function add()
    {
        $data = [];

        return view('addStatus', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new StatusModel();
        $record = $model->find($id);

        $data = ['record' => $record];

        return view('updStatus', $data);
    }

    public function list()  
    {
        $model = new StatusModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'status' => $this->request->getPost('status')
        ];
        
        $model = new StatusModel();
        
        try {
            $model->insert($data);
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
    
    public function update() 
    {
        $model = new StatusModel();
        $id = $this->request->getPost('id');
        $data = [
            'status' => $this->request->getPost('status')
        ];
        
        try {
            $model->update($id, $data);
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o registro: ' . $e->getMessage()
            ]);
        }
    }
    
    public function delete($id)  
    {
        $model = new StatusModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
