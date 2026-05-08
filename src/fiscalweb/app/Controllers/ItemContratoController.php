<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ItemContratoModel;

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

        return view('addItemContrato', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ItemContratoModel();
        $record = $model->find($id);

        $data = ['record' => $record];

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
            'gestor_substituto' => $this->request->getPost('gestor_substituto'),
            'Numero_Contrato' => $this->request->getPost('Numero_Contrato'),
            'Objeto' => $this->request->getPost('Objeto'),
            'Total_Horas_Contratadas' => $this->request->getPost('Total_Horas_Contratadas'),
            'Saldo_Horas' => $this->request->getPost('Saldo_Horas'),
            'Data_Inicio' => $this->request->getPost('Data_Inicio'),
            'Data_Fim' => $this->request->getPost('Data_Fim')
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
            'gestor_substituto' => $this->request->getPost('gestor_substituto'),
            'Numero_Contrato' => $this->request->getPost('Numero_Contrato'),
            'Objeto' => $this->request->getPost('Objeto'),
            'Total_Horas_Contratadas' => $this->request->getPost('Total_Horas_Contratadas'),
            'Saldo_Horas' => $this->request->getPost('Saldo_Horas'),
            'Data_Inicio' => $this->request->getPost('Data_Inicio'),
            'Data_Fim' => $this->request->getPost('Data_Fim')
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
