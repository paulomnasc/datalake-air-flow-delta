<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ItemOsModel;
use App\Models\ServicoModel;
use App\Models\OrdemServicoModel;
use App\Models\AtividadeMacroModel;
use App\Models\OsItemOsModel;

class ItemOsController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        $data = [
            'list' => $list,
            'id_servico_list' => (new ServicoModel())->listToCombo(),
            'id_os_list' => (new OrdemServicoModel())->listToCombo(),
            'id_atividade_macro_list' => (new AtividadeMacroModel())->listToCombo()
        ];
        return view('listItemOs', $data);
    }

    public function add()
    {
        $data = [];
        $data['id_servico_list'] = (new ServicoModel())->listToCombo();
        $data['id_os_list'] = (new OrdemServicoModel())->listToCombo();
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

        return view('addItemOs', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ItemOsModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_servico_list'] = (new ServicoModel())->listToCombo();
        $data['id_os_list'] = (new OrdemServicoModel())->listToCombo();
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

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
            'id_os' => $this->request->getPost('id_os'),
            'id_servico' => $this->request->getPost('id_servico'),
            'quantidade_horas' => $this->request->getPost('quantidade_horas'),
            'profissional_alocado' => $this->request->getPost('profissional_alocado')
        ];
        
        $model = new ItemOsModel();
        $osItemOsModel = new OsItemOsModel();
        
        try {
            $itemOsId = $model->insert($data);
            if ($itemOsId) {
                $osItemOsModel->insert([
                    'id_os' => $data['id_os'],
                    'id_item_os' => $itemOsId
                ]);
            }
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
        $osItemOsModel = new OsItemOsModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_os' => $this->request->getPost('id_os'),
            'id_servico' => $this->request->getPost('id_servico'),
            'quantidade_horas' => $this->request->getPost('quantidade_horas'),
            'profissional_alocado' => $this->request->getPost('profissional_alocado')
        ];
        
        try {
            $model->update($id, $data);
            // Remove existing associations
            $osItemOsModel->where('id_item_os', $id)->delete();
            // Add new association
            $osItemOsModel->insert([
                'id_os' => $data['id_os'],
                'id_item_os' => $id
            ]);
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
