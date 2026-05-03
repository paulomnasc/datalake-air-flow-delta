<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ItemOsModel;

class ItemOsController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listItemOs', ['list' => $list]);
    }

    public function add()
    {
        $data = [];

        return view('addItemOs', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ItemOsModel();
        $record = $model->find($id);

        $data = ['record' => $record];

        return view('updItemOs', $data);
    }

    public function list()  
    {
        $model = new ItemOsModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'quantidade_horas' => $this->request->getPost('quantidade_horas'),
            'profissional_alocado' => $this->request->getPost('profissional_alocado')
        ];
        
        $model = new ItemOsModel();
        
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
        $model = new ItemOsModel();
        $id = $this->request->getPost('id');
        $data = [
            'quantidade_horas' => $this->request->getPost('quantidade_horas'),
            'profissional_alocado' => $this->request->getPost('profissional_alocado')
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
        $model = new ItemOsModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
