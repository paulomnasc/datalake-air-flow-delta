<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\MetricaModel;

class MetricaController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listMetrica', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        return view('addMetrica', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new MetricaModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        return view('updMetrica', $data);
    }

    public function list()  
    {
        $model = new MetricaModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'nome' => $this->request->getPost('nome'),
            'sigla' => $this->request->getPost('sigla'),
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $model = new MetricaModel();
        
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
        $model = new MetricaModel();
        $id = $this->request->getPost('id');
        $data = [
            'nome' => $this->request->getPost('nome'),
            'sigla' => $this->request->getPost('sigla'),
            'descricao' => $this->request->getPost('descricao')
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
        $model = new MetricaModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
