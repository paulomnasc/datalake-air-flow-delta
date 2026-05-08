<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\DocumentoRecebimentoModel;
use App\Models\OrdemServicoModel;
use App\Models\TipoDocumentoModel;
use App\Models\UsuarioModel;

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
        $model = new DocumentoRecebimentoModel();
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
        $model = new DocumentoRecebimentoModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
