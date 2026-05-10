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

        // Buscar itens existentes do documento
        $db = \Config\Database::connect();
        $builder = $db->table('item_documento_recebimento idr');
        $builder->select('idr.id, idr.id_item_os, idr.quantidade_entregue, idr.glosa_horas, idr.observacoes, s.numero_item, s.descricao, io.Profissional_Alocado as profissional_alocado');
        $builder->join('item_os io', 'io.id = idr.id_item_os', 'left');
        $builder->join('servico s', 's.id = io.id_servico', 'left');
        $builder->where('idr.id_documento_recebimento', $id);
        $data['items_json'] = json_encode($builder->get()->getResult());

        return view('updDocumentoRecebimento', $data);
    }

    public function list()  
    {
        $model = new DocumentoRecebimentoModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_os' => $this->request->getPost('id_os'),
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
                        $itemModel->insert($itemData);
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
                        $itemModel->insert($itemData);
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
