<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\OrdemServicoModel;
use App\Models\ServicoModel;
use App\Models\ItemOsModel;
use App\Models\OsItemOsModel;
use App\Models\CatalogoServicosModel;

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
        $data['servicos_list'] = (new ServicoModel())->findAll();
        $data['catalogos_list'] = (new CatalogoServicosModel())->findAll();
        return view('addOrdemServico', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new OrdemServicoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['servicos_list'] = (new ServicoModel())->findAll();
        $data['catalogos_list'] = (new CatalogoServicosModel())->findAll();
        
        // Buscar itens existentes
        $db = \Config\Database::connect();
        $builder = $db->table('os_item_os oio');
        $builder->select('io.id as id_item_os, io.quantidade_horas, io.profissional_alocado, io.id_servico, s.numero_item, s.descricao, s.sla_dias, s.remuneracao');
        $builder->join('item_os io', 'io.id = oio.id_item_os');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->where('oio.id_os', $id);
        $data['items_json'] = json_encode($builder->get()->getResult());

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
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite'))
        ];
        
        $model = new OrdemServicoModel();
        $itemOsModel = new ItemOsModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            $idOs = $model->insert($data);
            
            $itemsJson = $this->request->getPost('items');
            if ($itemsJson) {
                $items = json_decode($itemsJson, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $itemData = [
                            'quantidade_horas' => $item['quantidade_horas'],
                            'profissional_alocado' => $item['profissional_alocado'],
                            'id_servico' => $item['id_servico']
                        ];
                        $idItemOs = $itemOsModel->insert($itemData);
                        $db->table('os_item_os')->insert([
                            'id_os' => $idOs,
                            'id_item_os' => $idItemOs
                        ]);
                    }
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Erro na transação.');
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro inserido com sucesso!'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update() 
    {
        $model = new OrdemServicoModel();
        $itemOsModel = new ItemOsModel();
        $id = $this->request->getPost('id');
        $data = [
            'horas_alocadas' => $this->post('horas_alocadas'),
            'nup_sei' => $this->post('nup_sei'),
            'data_emissao' => $this->normalizeDatetime($this->post('data_emissao')),
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite'))
        ];
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            $model->update($id, $data);
            
            // Buscar ids antigos para excluir fisicamente os itens da tabela item_os
            $oldRelations = $db->table('os_item_os')->where('id_os', $id)->get()->getResult();
            
            // Excluir os relacionamentos
            $db->table('os_item_os')->where('id_os', $id)->delete();
            
            // Excluir os itens fisicamente
            foreach ($oldRelations as $rel) {
                $itemOsModel->delete($rel->id_item_os);
            }
            
            $itemsJson = $this->request->getPost('items');
            if ($itemsJson) {
                $items = json_decode($itemsJson, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        // Insert new or updated items
                        $itemData = [
                            'quantidade_horas' => $item['quantidade_horas'],
                            'profissional_alocado' => $item['profissional_alocado'],
                            'id_servico' => $item['id_servico']
                        ];
                        $idItemOs = $itemOsModel->insert($itemData);
                        $db->table('os_item_os')->insert([
                            'id_os' => $id,
                            'id_item_os' => $idItemOs
                        ]);
                    }
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Erro na transação.');
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Registro atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
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
