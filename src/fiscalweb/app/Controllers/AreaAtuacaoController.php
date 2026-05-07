<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use FiscalWeb\App\Models\AreaAtuacaoModel;
use FiscalWeb\App\Models\AtividadeMacroModel;

class AreaAtuacaoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listAreaAtuacao', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

        return view('addAreaAtuacao', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new AreaAtuacaoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

        return view('updAreaAtuacao', $data);
    }

    public function list()  
    {
        $model = new AreaAtuacaoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_atividade_macro' => $this->request->getPost('id_atividade_macro'),
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $model = new AreaAtuacaoModel();
        
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
        $model = new AreaAtuacaoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_atividade_macro' => $this->request->getPost('id_atividade_macro'),
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
        $model = new AreaAtuacaoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
