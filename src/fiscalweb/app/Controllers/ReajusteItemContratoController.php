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
        $data = [];

        return view('addReajusteItemContrato', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ReajusteItemContratoModel();
        $record = $model->find($id);

        $data = ['record' => $record];

        return view('updReajusteItemContrato', $data);
    }

    public function list()  
    {
        $model = new ReajusteItemContratoModel();
        return $model->findAll();
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
