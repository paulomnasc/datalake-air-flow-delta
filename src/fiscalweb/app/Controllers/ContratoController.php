<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ContratoModel;

class ContratoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listContrato', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        return view('addContrato', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ContratoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        return view('updContrato', $data);
    }

    public function list()  
    {
        $model = new ContratoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'descricao' => $this->request->getPost('descricao'),
            'empresa' => $this->request->getPost('empresa')
        ];
        
        $model = new ContratoModel();
        
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
        $model = new ContratoModel();
        $id = $this->request->getPost('id');
        $data = [
            'descricao' => $this->request->getPost('descricao'),
            'empresa' => $this->request->getPost('empresa')
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
        $model = new ContratoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
