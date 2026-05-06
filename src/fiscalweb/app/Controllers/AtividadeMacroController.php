<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\AtividadeMacroModel;
use App\Models\ServicoModel;

class AtividadeMacroController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listAtividadeMacro', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        $data['id_servico_list'] = (new ServicoModel())->listToCombo();

        return view('addAtividadeMacro', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new AtividadeMacroModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_servico_list'] = (new ServicoModel())->listToCombo();

        return view('updAtividadeMacro', $data);
    }

    public function list()  
    {
        $model = new AtividadeMacroModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_servico' => $this->request->getPost('id_servico'),
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $model = new AtividadeMacroModel();
        
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
        $model = new AtividadeMacroModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_servico' => $this->request->getPost('id_servico'),
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
        $model = new AtividadeMacroModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
