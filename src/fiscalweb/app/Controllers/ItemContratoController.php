<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ItemContratoModel;\nuse App\Models\CatalogoServicosModel;

class ItemContratoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listItemContrato', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        $data['id_catalogo_servicos_list'] = (new CatalogoServicosModel())->listToCombo();

        return view('addItemContrato', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ItemContratoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_catalogo_servicos_list'] = (new CatalogoServicosModel())->listToCombo();

        return view('updItemContrato', $data);
    }

    public function list()  
    {
        $model = new ItemContratoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_catalogo_servicos' => $this->request->getPost('id_catalogo_servicos'),
            'gestor_titular' => $this->request->getPost('gestor_titular'),
            'gestor_substituto' => $this->request->getPost('gestor_substituto'),
            'numero_contrato' => $this->request->getPost('numero_contrato'),
            'objeto' => $this->request->getPost('objeto'),
            'total_horas_contratadas' => $this->request->getPost('total_horas_contratadas'),
            'saldo_horas' => $this->request->getPost('saldo_horas'),
            'data_inicio' => $this->request->getPost('data_inicio'),
            'data_fim' => $this->request->getPost('data_fim')
        ];
        
        $model = new ItemContratoModel();
        
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
        $model = new ItemContratoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_catalogo_servicos' => $this->request->getPost('id_catalogo_servicos'),
            'gestor_titular' => $this->request->getPost('gestor_titular'),
            'gestor_substituto' => $this->request->getPost('gestor_substituto'),
            'numero_contrato' => $this->request->getPost('numero_contrato'),
            'objeto' => $this->request->getPost('objeto'),
            'total_horas_contratadas' => $this->request->getPost('total_horas_contratadas'),
            'saldo_horas' => $this->request->getPost('saldo_horas'),
            'data_inicio' => $this->request->getPost('data_inicio'),
            'data_fim' => $this->request->getPost('data_fim')
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
        $model = new ItemContratoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
