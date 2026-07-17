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
        return $model->findAllWithOS();
    }

    public function insert() 
    {
        $id_os = $this->request->getPost('id_os');
        $data = [
            'id_servico' => $this->request->getPost('id_servico'),
            'Quantidade_Horas' => $this->request->getPost('Quantidade_Horas'),
            'Profissional_Alocado' => $this->request->getPost('Profissional_Alocado')
        ];
        
        // Validate required fields
        if (empty($id_os) || empty($data['id_servico'])) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Campos obrigatórios não preenchidos.'
            ]);
        }
        
        $model = new ItemOsModel();
        $osItemOsModel = new OsItemOsModel();
        
        try {
            $itemOsId = $model->insert($data);
            if ($itemOsId) {
                $osItemOsModel->insertAssociation($id_os, $itemOsId);
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
        $id_os = $this->request->getPost('id_os');
        $data = [
            'id_servico' => $this->request->getPost('id_servico'),
            'Quantidade_Horas' => $this->request->getPost('Quantidade_Horas'),
            'Profissional_Alocado' => $this->request->getPost('Profissional_Alocado')
        ];
        
        // Validate required fields
        if (empty($id_os) || empty($data['id_servico'])) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Campos obrigatórios não preenchidos.'
            ]);
        }
        
        try {
            $model->update($id, $data);
            // Upsert association
            $existing = $osItemOsModel->where('id_item_os', $id)->first();
            if ($existing) {
                $osItemOsModel->updateAssociation($id, $id_os);
            } else {
                $osItemOsModel->insertAssociation($id_os, $id);
            }
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
        $osItemOsModel = new OsItemOsModel();
        
        try {
            // Delete association first
            $osItemOsModel->deleteAssociation($id);
            // Then delete the item
            $deleted = $model->delete($id);
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao deletar o registro: ' . $e->getMessage()
            ]);
        }
    }
}
