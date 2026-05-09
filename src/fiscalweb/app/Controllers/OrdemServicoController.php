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
            'horas_alocadas' => $this->post('horas_alocadas'),
            'nup_sei' => $this->post('nup_sei'),
            'data_emissao' => $this->normalizeDatetime($this->post('data_emissao')),
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite')),
            'data_vencimento' => $this->normalizeDatetime($this->post('data_vencimento'))
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
            'horas_alocadas' => $this->post('horas_alocadas'),
            'nup_sei' => $this->post('nup_sei'),
            'data_emissao' => $this->normalizeDatetime($this->post('data_emissao')),
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite')),
            'data_vencimento' => $this->normalizeDatetime($this->post('data_vencimento'))
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

    private function post(string $key)
    {
        $value = $this->request->getPost($key);
        if ($value !== null) {
            return $value;
        }

        $parts = explode('_', $key);
        $altKey = implode('_', array_map('ucfirst', $parts));
        return $this->request->getPost($altKey);
    }

    private function normalizeDatetime($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Normalize browser datetime-local values (2025-12-30T10:30) to SQL DATETIME
        if (strpos($value, 'T') !== false) {
            $value = str_replace('T', ' ', $value);
        }

        // Convert dd/mm/YYYY HH:MM if provided by other clients
        $date = date_create_from_format('d/m/Y H:i', $value);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }

        return $value;
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
