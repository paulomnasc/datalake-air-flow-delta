<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ReajusteItemContratoModel;

class ReajusteItemContratoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listReajusteItemContrato', ['list' => $list]);
    }

    public function add()
    {
        $contratoModel = new \App\Models\ContratoModel();
        $itemContratoModel = new \App\Models\ItemContratoModel();

        $data = [
            'contrato_list' => $contratoModel->findAll(),
            'item_contrato_list' => $itemContratoModel->listWithContrato()
        ];

        return view('addReajusteItemContrato', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ReajusteItemContratoModel();
        $record = $model->find($id);

        $contratoModel = new \App\Models\ContratoModel();
        $itemContratoModel = new \App\Models\ItemContratoModel();

        $itemRecord = null;
        if ($record && !empty($record->id_item_contrato)) {
            $itemRecord = $itemContratoModel->find($record->id_item_contrato);
        }

        $data = [
            'record' => $record,
            'selected_id_contrato' => $itemRecord ? $itemRecord->id_contrato : null,
            'contrato_list' => $contratoModel->findAll(),
            'item_contrato_list' => $itemContratoModel->listWithContrato()
        ];

        return view('updReajusteItemContrato', $data);
    }

    public function list()  
    {
        $model = new ReajusteItemContratoModel();
        return $model->select('reajuste_item_contrato.*, contrato.empresa, metrica_contrato.sigla as metrica_sigla')
                     ->join('item_contrato', 'item_contrato.id = reajuste_item_contrato.id_item_contrato', 'left')
                     ->join('contrato', 'contrato.id = item_contrato.id_contrato', 'left')
                     ->join('metrica_contrato', 'metrica_contrato.id = item_contrato.id_metrica', 'left')
                     ->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_item_contrato' => $this->request->getPost('id_item_contrato'),
            'data_reajuste_item_contrato' => $this->request->getPost('data_reajuste_item_contrato'),
            'valor_item_contrato' => $this->request->getPost('valor_item_contrato')
        ];
        
        $model = new ReajusteItemContratoModel();
        
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
        $model = new ReajusteItemContratoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_item_contrato' => $this->request->getPost('id_item_contrato'),
            'data_reajuste_item_contrato' => $this->request->getPost('data_reajuste_item_contrato'),
            'valor_item_contrato' => $this->request->getPost('valor_item_contrato')
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
        $model = new ReajusteItemContratoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
