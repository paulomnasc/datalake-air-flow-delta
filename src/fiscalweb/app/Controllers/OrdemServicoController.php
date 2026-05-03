<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\OrdemServicoModel;

class OrdemServicoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listOrdemServico', ['list' => $list]);
    }

    public function add()
    {
        $data = [];

        return view('addOrdemServico', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new OrdemServicoModel();
        $record = $model->find($id);

        $data = ['record' => $record];

        return view('updOrdemServico', $data);
    }

    public function list()  
    {
        $model = new OrdemServicoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'horas_alocadas' => $this->request->getPost('horas_alocadas'),
            'nup_sei' => $this->request->getPost('nup_sei'),
            'data_emissao' => $this->request->getPost('data_emissao'),
            'data_aceite' => $this->request->getPost('data_aceite')
        ];
        
        $model = new OrdemServicoModel();
        
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
        $model = new OrdemServicoModel();
        $id = $this->request->getPost('id');
        $data = [
            'horas_alocadas' => $this->request->getPost('horas_alocadas'),
            'nup_sei' => $this->request->getPost('nup_sei'),
            'data_emissao' => $this->request->getPost('data_emissao'),
            'data_aceite' => $this->request->getPost('data_aceite')
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
        $model = new OrdemServicoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
