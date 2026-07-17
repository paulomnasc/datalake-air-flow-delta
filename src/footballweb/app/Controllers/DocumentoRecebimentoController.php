<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\DocumentoRecebimentoModel;
use App\Models\OrdemServicoModel;
use App\Models\TipoDocumentoModel;
use App\Models\UsuarioModel;
use App\Models\ItemDocumentoRecebimentoModel;

class DocumentoRecebimentoController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        $data = [
            'list' => $list,
            'id_os_list' => (new OrdemServicoModel())->listToCombo(),
            'id_tipo_documento_list' => (new TipoDocumentoModel())->listToCombo(),
            'id_usuario_fiscal_tecnico_list' => (new UsuarioModel())->listToCombo(),
            'id_usuario_fiscal_requisitante_list' => (new UsuarioModel())->listToCombo(),
            'id_usuario_gestor_list' => (new UsuarioModel())->listToCombo()
        ];
        return view('listDocumentoRecebimento', $data);
    }

    public function add()
    {
        $data = [];
        $data['id_os_list'] = (new OrdemServicoModel())->listToCombo();
        $data['id_tipo_documento_list'] = (new TipoDocumentoModel())->listToCombo();
        $data['id_usuario_fiscal_tecnico_list'] = (new UsuarioModel())->listToCombo();
        $data['id_usuario_fiscal_requisitante_list'] = (new UsuarioModel())->listToCombo();
        $data['id_usuario_gestor_list'] = (new UsuarioModel())->listToCombo();
        $data['checklist_options'] = (new \App\Models\ListaVerificacaoModel())->findAll();

        return view('addDocumentoRecebimento', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new DocumentoRecebimentoModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_os_list'] = (new OrdemServicoModel())->listToCombo();
        $data['id_tipo_documento_list'] = (new TipoDocumentoModel())->listToCombo();
        $data['id_usuario_fiscal_tecnico_list'] = (new UsuarioModel())->listToCombo();
        $data['id_usuario_fiscal_requisitante_list'] = (new UsuarioModel())->listToCombo();
        $data['id_usuario_gestor_list'] = (new UsuarioModel())->listToCombo();
        $data['checklist_options'] = (new \App\Models\ListaVerificacaoModel())->findAll();

        $idOs = $record->id_os;
        $demandaModel = new \App\Models\DemandaModel();
        $data['demanda_list'] = $demandaModel->where('id_ordem_servico', $idOs)->findAll();
        $selectedDemanda = $demandaModel->find($record->id_demanda);
        if ($selectedDemanda) {
            $data['selected_demanda_title'] = "Demanda #{$selectedDemanda->id} - {$selectedDemanda->titulo}";
        }

        // Buscar itens existentes do documento
        $builder = $db->table('item_documento_recebimento idr');
        $builder->select('
            idr.id, 
            idr.id_item_os, 
            idr.quantidade_entregue, 
            idr.glosa_horas, 
            idr.observacoes, 
            s.numero_item, 
            s.descricao, 
            s.remuneracao,
            s.base_horas_complexidade,
            s.sla_dias,
            io.id_servico,
            io.Profissional_Alocado as profissional_alocado,
            mc.sigla as sigla_metrica,
            (SELECT valor_item_contrato 
             FROM reajuste_item_contrato 
             WHERE id_item_contrato = cs.id_item_contrato 
             AND data_reajuste_item_contrato <= os.Data_Emissao 
             ORDER BY data_reajuste_item_contrato DESC LIMIT 1) as valor_item_contrato
        ');
        $builder->join('documento_recebimento dr', 'dr.id = idr.id_documento_recebimento', 'left');
        $builder->join('ordem_servico os', 'os.id = dr.id_os', 'left');
        $builder->join('item_os io', 'io.id = idr.id_item_os', 'left');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->join('atividade_macro am', 'am.id = s.id_atividade_macro', 'left');
        $builder->join('area_atuacao aa', 'aa.id = am.id_area_atuacao', 'left');
        $builder->join('catalogo_servicos cs', 'cs.id = aa.id_catalogo_servicos', 'left');
        $builder->join('item_contrato ic', 'ic.id = cs.id_item_contrato', 'left');
        $builder->join('metrica_contrato mc', 'mc.id = ic.id_metrica', 'left');
        $builder->where('idr.id_documento_recebimento', $id);
        $itens = $builder->get()->getResult();
        
        foreach($itens as &$item) {
            $valContrato = isset($item->valor_item_contrato) ? (float)$item->valor_item_contrato : 0;
            $remun = isset($item->remuneracao) ? (float)$item->remuneracao : 0;
            $baseHoras = isset($item->base_horas_complexidade) ? (float)$item->base_horas_complexidade : 0;
            $qtd = isset($item->quantidade_entregue) ? (float)$item->quantidade_entregue : 0;
            $glosa = isset($item->glosa_horas) ? (float)$item->glosa_horas : 0;
            
            $sigla = isset($item->sigla_metrica) ? strtoupper($item->sigla_metrica) : 'H';
            if ($sigla === 'PROF') {
                $item->valor_remuneracao_item = ($qtd - $glosa) * $baseHoras;
            } elseif ($sigla === 'PF') {
                $item->valor_remuneracao_item = ($qtd - $glosa) * $valContrato;
            } else {
                $item->valor_remuneracao_item = ($qtd - $glosa) * $remun * $valContrato;
            }
            
            $item->desc_servico = $item->descricao ? "Item {$item->numero_item} - {$item->descricao}" : "Item OS #{$item->id_item_os}";
            $item->profissional = $item->profissional_alocado;

            // Buscar checklists cadastrados para o item
            $checklists = $db->table('item_doc_rec_lista_ver')->where('id_item_doc_origem', $item->id)->get()->getResult();
            $item->checklist = $checklists;
        }
        $data['items_json'] = json_encode($itens);

        return view('updDocumentoRecebimento', $data);
    }

    public function list()  
    {
        $model = new DocumentoRecebimentoModel();
        return $model->select('documento_recebimento.*, 
                               ordem_servico.nup_sei as os_nup_sei, 
                               contrato.descricao as Numero_Contrato,
                               tipo_documento.descricao as tipo_documento_descricao,
                               u_tecnico.nome as fiscal_tecnico_nome,
                               u_requisitante.nome as fiscal_requisitante_nome,
                               u_gestor.nome as gestor_nome')
                     ->join('ordem_servico', 'ordem_servico.id = documento_recebimento.id_os', 'left')
                     ->join('contrato', 'contrato.id = ordem_servico.id_contrato', 'left')
                     ->join('tipo_documento', 'tipo_documento.id = documento_recebimento.id_tipo_documento', 'left')
                     ->join('usuario u_tecnico', 'u_tecnico.id = documento_recebimento.id_usuario_fiscal_tecnico', 'left')
                     ->join('usuario u_requisitante', 'u_requisitante.id = documento_recebimento.id_usuario_fiscal_requisitante', 'left')
                     ->join('usuario u_gestor', 'u_gestor.id = documento_recebimento.id_usuario_gestor', 'left')
                     ->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_os' => $this->request->getPost('id_os'),
            'id_demanda' => $this->request->getPost('id_demanda'),
            'data_assinatura' => $this->request->getPost('data_assinatura'),
            'nup_sei' => $this->request->getPost('nup_sei'),
            'id_tipo_documento' => $this->request->getPost('id_tipo_documento'),
            'id_usuario_fiscal_tecnico' => $this->request->getPost('id_usuario_fiscal_tecnico'),
            'id_usuario_fiscal_requisitante' => $this->request->getPost('id_usuario_fiscal_requisitante'),
            'id_usuario_gestor' => $this->request->getPost('id_usuario_gestor')
        ];
        
        $model = new DocumentoRecebimentoModel();
        $itemModel = new ItemDocumentoRecebimentoModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            // Validar transição de status da OS de acordo com o tipo de documento de recebimento
            $osModel = new \App\Models\OrdemServicoModel();
            $os = $osModel->find($data['id_os']);
            
            if (!$os) {
                throw new \Exception('Ordem de serviço não encontrada.');
            }

            if ($data['id_tipo_documento'] == 1) { // TRP
                if ($os->status !== 'Execução') {
                    throw new \Exception("A Ordem de Serviço deve estar no status 'Execução' para cadastrar um documento TRP.");
                }
                $osModel->update($data['id_os'], ['status' => 'Recebido Provisorio']);
            } elseif ($data['id_tipo_documento'] == 2) { // TRD
                if ($os->status !== 'Recebido Provisorio') {
                    throw new \Exception("A Ordem de Serviço deve estar no status 'Recebido Provisorio' para cadastrar um documento TRD.");
                }
                $osModel->update($data['id_os'], ['status' => 'Recebido definitivo']);
            }

            $idDoc = $model->insert($data);
            
            $itemsJson = $this->request->getPost('items');
            if ($itemsJson) {
                $items = json_decode($itemsJson, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $itemData = [
                            'id_documento_recebimento' => $idDoc,
                            'id_item_os' => $item['id_item_os'],
                            'quantidade_entregue' => $item['quantidade_entregue'],
                            'glosa_horas' => $item['glosa_horas'] ?? 0,
                            'observacoes' => $item['observacoes'] ?? ''
                        ];
                        $idItemDoc = $itemModel->insert($itemData);
                        
                        if (!empty($item['checklist']) && is_array($item['checklist'])) {
                            foreach ($item['checklist'] as $chk) {
                                $db->table('item_doc_rec_lista_ver')->insert([
                                    'id_lista_verificacao' => $chk['id_lista_verificacao'],
                                    'id_item_doc_origem' => $idItemDoc,
                                    'conforme' => $chk['conforme'] ? 1 : 0
                                ]);
                            }
                        }
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
        $model = new DocumentoRecebimentoModel();
        $itemModel = new ItemDocumentoRecebimentoModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_os' => $this->request->getPost('id_os'),
            'id_demanda' => $this->request->getPost('id_demanda'),
            'data_assinatura' => $this->request->getPost('data_assinatura'),
            'nup_sei' => $this->request->getPost('nup_sei'),
            'id_tipo_documento' => $this->request->getPost('id_tipo_documento'),
            'id_usuario_fiscal_tecnico' => $this->request->getPost('id_usuario_fiscal_tecnico'),
            'id_usuario_fiscal_requisitante' => $this->request->getPost('id_usuario_fiscal_requisitante'),
            'id_usuario_gestor' => $this->request->getPost('id_usuario_gestor')
        ];
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            // Se o OS ou tipo do documento mudou, ou se for a primeira atribuição
            $oldDoc = $model->find($id);
            if ($oldDoc) {
                $osChanged = ($oldDoc->id_os != $data['id_os']);
                $typeChanged = ($oldDoc->id_tipo_documento != $data['id_tipo_documento']);
                
                if ($osChanged || $typeChanged) {
                    $osModel = new \App\Models\OrdemServicoModel();
                    $os = $osModel->find($data['id_os']);
                    if (!$os) {
                        throw new \Exception('Ordem de serviço não encontrada.');
                    }

                    if ($data['id_tipo_documento'] == 1) { // TRP
                        if ($os->status !== 'Execução') {
                            throw new \Exception("A Ordem de Serviço deve estar no status 'Execução' para associar um documento TRP.");
                        }
                        $osModel->update($data['id_os'], ['status' => 'Recebido Provisorio']);
                    } elseif ($data['id_tipo_documento'] == 2) { // TRD
                        if ($os->status !== 'Recebido Provisorio') {
                            throw new \Exception("A Ordem de Serviço deve estar no status 'Recebido Provisorio' para associar um documento TRD.");
                        }
                        $osModel->update($data['id_os'], ['status' => 'Recebido definitivo']);
                    }
                }
            }

            $model->update($id, $data);


            // Delete old items
            $db->table('item_documento_recebimento')->where('id_documento_recebimento', $id)->delete();
            
            $itemsJson = $this->request->getPost('items');
            if ($itemsJson) {
                $items = json_decode($itemsJson, true);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $itemData = [
                            'id_documento_recebimento' => $id,
                            'id_item_os' => $item['id_item_os'],
                            'quantidade_entregue' => $item['quantidade_entregue'],
                            'glosa_horas' => $item['glosa_horas'] ?? 0,
                            'observacoes' => $item['observacoes'] ?? ''
                        ];
                        $idItemDoc = $itemModel->insert($itemData);
                        
                        if (!empty($item['checklist']) && is_array($item['checklist'])) {
                            foreach ($item['checklist'] as $chk) {
                                $db->table('item_doc_rec_lista_ver')->insert([
                                    'id_lista_verificacao' => $chk['id_lista_verificacao'],
                                    'id_item_doc_origem' => $idItemDoc,
                                    'conforme' => $chk['conforme'] ? 1 : 0
                                ]);
                            }
                        }
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
    
    public function delete($id)  
    {
        $model = new DocumentoRecebimentoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
