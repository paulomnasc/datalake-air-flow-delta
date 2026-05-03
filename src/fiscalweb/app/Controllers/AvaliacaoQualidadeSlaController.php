<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\AvaliacaoQualidadeSlaModel;\nuse App\Models\DocumentoRecebimentoModel;

class AvaliacaoQualidadeSlaController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        return view('listAvaliacaoQualidadeSla', ['list' => $list]);
    }

    public function add()
    {
        $data = [];
        $data['id_documento_recebimento_list'] = (new DocumentoRecebimentoModel())->listToCombo();

        return view('addAvaliacaoQualidadeSla', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new AvaliacaoQualidadeSlaModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_documento_recebimento_list'] = (new DocumentoRecebimentoModel())->listToCombo();

        return view('updAvaliacaoQualidadeSla', $data);
    }

    public function list()  
    {
        $model = new AvaliacaoQualidadeSlaModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_documento_recebimento' => $this->request->getPost('id_documento_recebimento'),
            'nota_ins1_pontualidade' => $this->request->getPost('nota_ins1_pontualidade'),
            'nota_ins2_qualidade' => $this->request->getPost('nota_ins2_qualidade'),
            'percentual_glosa' => $this->request->getPost('percentual_glosa')
        ];
        
        $model = new AvaliacaoQualidadeSlaModel();
        
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
        $model = new AvaliacaoQualidadeSlaModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_documento_recebimento' => $this->request->getPost('id_documento_recebimento'),
            'nota_ins1_pontualidade' => $this->request->getPost('nota_ins1_pontualidade'),
            'nota_ins2_qualidade' => $this->request->getPost('nota_ins2_qualidade'),
            'percentual_glosa' => $this->request->getPost('percentual_glosa')
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
        $model = new AvaliacaoQualidadeSlaModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}
