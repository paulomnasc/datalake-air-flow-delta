<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ListaVerificacaoModel;

class ListaVerificacaoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listListaVerificacao', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        return view('addListaVerificacao', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ListaVerificacaoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        return view('updListaVerificacao', $data);
    }

    public function list()  
    {
        $model = new ListaVerificacaoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $model = new ListaVerificacaoModel();
        
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
        $model = new ListaVerificacaoModel();
        $id = $this->request->getPost('id');
        $data = [
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
        $model = new ListaVerificacaoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
