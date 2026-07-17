<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\FuncionalidadeModel;

class FuncionalidadeController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listFuncionalidade', ['list' => $list]);
    }

    public function add()
    {
        return view('addFuncionalidade');
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        
        $model = new FuncionalidadeModel();
        $funcionalidade = $model->find($id);

        $data = [
            'id' => $funcionalidade->id,
            'descricao' => $funcionalidade->descricao
        ];

        return view('updFuncionalidade', $data);
    }

    public function del($id)
    {
        $this->delete($id);
    }

    public function list()
    {
        $model = new FuncionalidadeModel();
        $list = $model->orderBy('descricao', 'ASC')->findAll();
        return $list;
    }

    public function findById(int $id)
    {
        $model = new FuncionalidadeModel();
        $funcionalidade = $model->find($id);
        return $funcionalidade;
    }

    public function insert()
    {
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        $model = new FuncionalidadeModel();
        
        try {
            $inserted = $model->insert($data);
        
            return $this->response->setJSON([
                'status' => $inserted ? 'success' : 'warning',
                'mensagem' => $inserted ? 'Funcionalidade inserida com sucesso!' : 'Falha ao inserir a funcionalidade. Tente novamente.'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir a funcionalidade: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update()
    {
        $model = new FuncionalidadeModel();
        $id = $this->request->getPost('id');
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        
        try {
            $updated = $model->update($id, $data);
        
            return $this->response->setJSON([
                'status' => $updated ? 'success' : 'warning',
                'mensagem' => $updated ? 'Funcionalidade atualizada com sucesso!' : 'Falha ao atualizar a funcionalidade. Tente novamente.'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar a funcionalidade: ' . $e->getMessage()
            ]);
        }
    }
    
    public function delete($id)
    {
        $model = new FuncionalidadeModel();
        
        try {
            $deleted = $model->delete($id);

            if ($deleted) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => 'Funcionalidade excluída com sucesso!'
                ]);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao excluir a funcionalidade: ' . $e->getMessage()
            ]);
        }
    }
}
