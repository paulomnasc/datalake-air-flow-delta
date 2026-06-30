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
        $os = $db->table('ordem_servico')->where('id', $id)->get()->getRow();
        $dataEmissao = $os ? $os->Data_Emissao : date('Y-m-d');

        // Buscar se existem TRP e TRD
        $trpExists = $db->table('documento_recebimento')
                        ->where('id_os', $id)
                        ->where('id_tipo_documento', 1)
                        ->countAllResults() > 0;
        $trdExists = $db->table('documento_recebimento')
                        ->where('id_os', $id)
                        ->where('id_tipo_documento', 2)
                        ->countAllResults() > 0;
        $data['trp_exists'] = $trpExists;
        $data['trd_exists'] = $trdExists;

        $builder = $db->table('os_item_os oio');
        $builder->select('
            io.id as id_item_os, 
            io.Quantidade_Horas as quantidade_horas, 
            io.Profissional_Alocado as profissional_alocado, 
            io.id_servico, 
            s.numero_item, 
            s.descricao, 
            s.sla_dias, 
            s.remuneracao, 
            s.base_horas_complexidade,
            s.id_atividade_macro as id_macro, 
            am.id_area_atuacao as id_area, 
            aa.id_catalogo_servicos as id_catalogo,
            cs.id_item_contrato,
            mc.sigla as sigla_metrica,
            (SELECT valor_item_contrato 
             FROM reajuste_item_contrato 
             WHERE id_item_contrato = cs.id_item_contrato 
             AND data_reajuste_item_contrato <= ' . $db->escape($dataEmissao) . ' 
             ORDER BY data_reajuste_item_contrato DESC LIMIT 1) as valor_item_contrato
        ');
        $builder->join('item_os io', 'io.id = oio.id_item_os');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->join('atividade_macro am', 'am.id = s.id_atividade_macro', 'left');
        $builder->join('area_atuacao aa', 'aa.id = am.id_area_atuacao', 'left');
        $builder->join('catalogo_servicos cs', 'cs.id = aa.id_catalogo_servicos', 'left');
        $builder->join('item_contrato ic', 'ic.id = cs.id_item_contrato', 'left');
        $builder->join('metrica_contrato mc', 'mc.id = ic.id_metrica', 'left');
        $builder->where('oio.id_os', $id);
        $itens = $builder->get()->getResult();
        
        foreach($itens as &$item) {
            $valContrato = isset($item->valor_item_contrato) ? (float)$item->valor_item_contrato : 0;
            $remun = isset($item->remuneracao) ? (float)$item->remuneracao : 0;
            $baseHoras = isset($item->base_horas_complexidade) ? (float)$item->base_horas_complexidade : 0;
            $qtd = isset($item->quantidade_horas) ? (float)$item->quantidade_horas : 0;
            
            $sigla = isset($item->sigla_metrica) ? strtoupper($item->sigla_metrica) : 'H';
            if ($sigla === 'PROF') {
                $item->valor_remuneracao_item = $qtd * $baseHoras;
            } elseif ($sigla === 'PF') {
                $item->valor_remuneracao_item = $qtd * $valContrato;
            } else {
                $item->valor_remuneracao_item = $qtd * $remun * $valContrato;
            }
        }
        $data['items_json'] = json_encode($itens);

        return view('updOrdemServico', $data);
    }

    public function list()  
    {
        $model = new OrdemServicoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $notaEmpenho = $this->post('nota_empenho');
        if ($notaEmpenho !== null) {
            $notaEmpenho = trim($notaEmpenho);
        }

        if (!empty($notaEmpenho) && !preg_match('/^[a-zA-Z0-9]+$/', $notaEmpenho)) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'A nota de empenho deve ser alfanumérica.'
            ]);
        }

        $status = 'Rascunho';
        if (!empty($notaEmpenho)) {
            $status = 'Aguardando assinatura';
        }

        $data = [
            'horas_alocadas' => $this->post('horas_alocadas'),
            'nup_sei' => $this->post('nup_sei'),
            'data_emissao' => $this->normalizeDatetime($this->post('data_emissao')),
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite')),
            'realizada_estimativa' => $this->post('realizada_estimativa'),
            'metodologia_estimativa' => $this->post('metodologia_estimativa'),
            'status' => $status,
            'nota_empenho' => $notaEmpenho ?: null
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
                            'Quantidade_Horas' => $item['quantidade_horas'],
                            'Profissional_Alocado' => $item['profissional_alocado'],
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

        $record = $model->find($id);
        if (!$record) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Ordem de serviço não encontrada.'
            ]);
        }

        $currentStatus = $record->status ?? 'Rascunho';
        $newStatus = $this->post('status') ?: $currentStatus;
        $notaEmpenho = $this->post('nota_empenho');

        if ($notaEmpenho !== null) {
            $notaEmpenho = trim($notaEmpenho);
        }

        // Validação da nota de empenho: se informada, deve ser alfanumérica
        if (!empty($notaEmpenho) && !preg_match('/^[a-zA-Z0-9]+$/', $notaEmpenho)) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'A nota de empenho deve ser alfanumérica.'
            ]);
        }

        // Se saiu do Rascunho, ou se tenta atualizar, nota_empenho é obrigatória se status for Aguardando assinatura ou superior
        if ($currentStatus !== 'Rascunho' && empty($notaEmpenho)) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'A nota de empenho é obrigatória após a Ordem de Serviço sair do status Rascunho.'
            ]);
        }

        // Regra: Rascunho -> Aguardando assinatura
        if ($currentStatus === 'Rascunho') {
            if (!empty($notaEmpenho)) {
                $newStatus = 'Aguardando assinatura';
            } else {
                $newStatus = 'Rascunho';
            }
        }

        // Regra: Aguardando assinatura -> Execução (manual)
        if ($newStatus === 'Execução' && $currentStatus === 'Aguardando assinatura') {
            if (empty($notaEmpenho)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'É necessário uma Nota de Empenho para colocar a Ordem de Serviço em Execução.'
                ]);
            }
        }

        // Garantir que a transição seja válida e sequencial (não retroceder nem saltar estados manualmente)
        $allowedManualTransitions = [
            'Rascunho' => ['Rascunho', 'Aguardando assinatura'],
            'Aguardando assinatura' => ['Aguardando assinatura', 'Execução'],
            'Execução' => ['Execução'],
            'Recebido Provisorio' => ['Recebido Provisorio'],
            'Recebido definitivo' => ['Recebido definitivo'],
            'Concluido' => ['Concluido']
        ];

        if (!in_array($newStatus, $allowedManualTransitions[$currentStatus])) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => "Transição de status inválida de '{$currentStatus}' para '{$newStatus}'."
            ]);
        }

        $data = [
            'horas_alocadas' => $this->post('horas_alocadas'),
            'nup_sei' => $this->post('nup_sei'),
            'data_emissao' => $this->normalizeDatetime($this->post('data_emissao')),
            'data_aceite' => $this->normalizeDatetime($this->post('data_aceite')),
            'realizada_estimativa' => $this->post('realizada_estimativa'),
            'metodologia_estimativa' => $this->post('metodologia_estimativa'),
            'status' => $newStatus,
            'nota_empenho' => $notaEmpenho ?: null
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
                            'Quantidade_Horas' => $item['quantidade_horas'],
                            'Profissional_Alocado' => $item['profissional_alocado'],
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
    
    public function concluir($id)
    {
        $model = new OrdemServicoModel();
        $record = $model->find($id);
        if (!$record) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Ordem de serviço não encontrada.'
            ]);
        }

        if ($record->status !== 'Recebido definitivo') {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'A ordem de serviço deve estar no status Recebido definitivo para ser concluída.'
            ]);
        }

        $db = \Config\Database::connect();
        $trpExists = $db->table('documento_recebimento')
                        ->where('id_os', $id)
                        ->where('id_tipo_documento', 1)
                        ->countAllResults() > 0;
        $trdExists = $db->table('documento_recebimento')
                        ->where('id_os', $id)
                        ->where('id_tipo_documento', 2)
                        ->countAllResults() > 0;

        if (!$trpExists || !$trdExists) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'A ordem de serviço deve possuir pelo menos um documento TRP e um TRD cadastrados para ser concluída.'
            ]);
        }

        $model->update($id, ['status' => 'Concluido']);

        return $this->response->setJSON([
            'status' => 'success',
            'mensagem' => 'Ordem de serviço concluída com sucesso!'
        ]);
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
