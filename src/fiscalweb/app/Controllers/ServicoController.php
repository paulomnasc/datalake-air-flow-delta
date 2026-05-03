<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ServicoModel;\nuse App\Models\ItemOsModel;

class ServicoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listServico', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        $data['id_item_os_list'] = (new ItemOsModel())->listToCombo();

        return view('addServico', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ServicoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_item_os_list'] = (new ItemOsModel())->listToCombo();

        return view('updServico', $data);
    }

    public function list()  
    {
        $model = new ServicoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_item_os' => $this->request->getPost('id_item_os'),
            'descricao' => $this->request->getPost('descricao'),
            'remuneracao' => $this->request->getPost('remuneracao'),
            'base_horas_mes' => $this->request->getPost('base_horas_mes'),
            'base_horas_complexidade' => $this->request->getPost('base_horas_complexidade'),
            'sla_dias' => $this->request->getPost('sla_dias'),
            'estim_max_ano' => $this->request->getPost('estim_max_ano')
        ];
        
        $model = new ServicoModel();
        
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
        $model = new ServicoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_item_os' => $this->request->getPost('id_item_os'),
            'descricao' => $this->request->getPost('descricao'),
            'remuneracao' => $this->request->getPost('remuneracao'),
            'base_horas_mes' => $this->request->getPost('base_horas_mes'),
            'base_horas_complexidade' => $this->request->getPost('base_horas_complexidade'),
            'sla_dias' => $this->request->getPost('sla_dias'),
            'estim_max_ano' => $this->request->getPost('estim_max_ano')
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
        $model = new ServicoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
