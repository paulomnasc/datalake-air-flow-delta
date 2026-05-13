<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ServicoModel;
use App\Models\AtividadeMacroModel;

class ServicoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        $data = [
            'list' => $list,
            'id_atividade_macro_list' => (new AtividadeMacroModel())->listToCombo()
        ];
        return view('listServico', $data);
    }

    public function add()
    {
        $data = [];
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

        return view('addServico', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new ServicoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_atividade_macro_list'] = (new AtividadeMacroModel())->listToCombo();

        return view('updServico', $data);
    }

    public function list()  
    {
        $db = \Config\Database::connect();
        $builder = $db->table('servico s');
        $builder->select('s.*, am.descricao as desc_macro');
        $builder->join('atividade_macro am', 'am.id = s.id_atividade_macro', 'left');
        return $builder->get()->getResult();
    }

    public function insert() 
    {
        $data = [
            'id_atividade_macro' => $this->request->getPost('id_atividade_macro'),
            'numero_item' => $this->request->getPost('numero_item'),
            'descricao' => $this->request->getPost('descricao'),
            'entregaveis' => $this->request->getPost('entregaveis'),
            'remuneracao' => $this->normalizeDecimal($this->request->getPost('remuneracao')),
            'base_horas_mes' => $this->normalizeDecimal($this->request->getPost('base_horas_mes')),
            'base_horas_complexidade' => $this->normalizeDecimal($this->request->getPost('base_horas_complexidade')),
            'sla_dias' => $this->normalizeDecimal($this->request->getPost('sla_dias')),
            'estim_max_ano' => $this->normalizeDecimal($this->request->getPost('estim_max_ano')),
            'saldo_horas' => $this->normalizeDecimal($this->request->getPost('saldo_horas'))
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
                'mensagem' => 'Falha ao inserir o registro: ' . $this->buildExceptionMessage($e)
            ]);
        }
    }
    
    public function update() 
    {
        $model = new ServicoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_atividade_macro' => $this->request->getPost('id_atividade_macro'),
            'numero_item' => $this->request->getPost('numero_item'),
            'descricao' => $this->request->getPost('descricao'),
            'entregaveis' => $this->request->getPost('entregaveis'),
            'remuneracao' => $this->normalizeDecimal($this->request->getPost('remuneracao')),
            'base_horas_mes' => $this->normalizeDecimal($this->request->getPost('base_horas_mes')),
            'base_horas_complexidade' => $this->normalizeDecimal($this->request->getPost('base_horas_complexidade')),
            'sla_dias' => $this->normalizeDecimal($this->request->getPost('sla_dias')),
            'estim_max_ano' => $this->normalizeDecimal($this->request->getPost('estim_max_ano')),
            'saldo_horas' => $this->normalizeDecimal($this->request->getPost('saldo_horas'))
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
                'mensagem' => 'Falha ao atualizar o registro: ' . $this->buildExceptionMessage($e)
            ]);
        }
    }
    
    private function normalizeDecimal($value)
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        return str_replace(',', '.', $value);
    }

    public function getByMacro($macro_id)
    {
        $model = new ServicoModel();
        $data = $model->where('id_atividade_macro', $macro_id)->select('id, descricao')->findAll();
        return $this->response->setJSON($data);
    }

    public function getMacroByServico($servico_id)
    {
        $model = new ServicoModel();
        $servico = $model->find($servico_id);
        return $this->response->setJSON(['id_atividade_macro' => $servico ? $servico->id_atividade_macro : null]);
    }
}
