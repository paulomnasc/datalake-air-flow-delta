<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CatalogoServicosModel;
use App\Models\ItemContratoModel;

class CatalogoServicosController extends BaseController
{
    public function index()
    {
        $list = $this->list();        
        $data = [
            'list' => $list,
            'id_item_contrato_list' => (new ItemContratoModel())->listToCombo()
        ];
        return view('listCatalogoServicos', $data);
    }

    public function add()
    {
        $data = [];
        $data['id_item_contrato_list'] = (new ItemContratoModel())->listToCombo();
        $data['catalogo_servicos_list'] = (new CatalogoServicosModel())->listToArray();

        return view('addCatalogoServicos', $data);
    }

    public function upd()
    {
        $id = $this->request->getPost('id');
        $model = new CatalogoServicosModel();
        $record = $model->find($id);

        $data = ['record' => $record];
        $data['id_item_contrato_list'] = (new ItemContratoModel())->listToCombo();
        $data['catalogo_servicos_list'] = (new CatalogoServicosModel())->listToArray();

        return view('updCatalogoServicos', $data);
    }

    public function list()  
    {
        $model = new CatalogoServicosModel();
        return $model->findAll();
    }

    public function insert() 
    {
        $data = [
            'id_item_contrato' => $this->request->getPost('id_item_contrato'),
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $model = new CatalogoServicosModel();
        
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
        $model = new CatalogoServicosModel();
        $id = $this->request->getPost('id');
        $data = [
            'id_item_contrato' => $this->request->getPost('id_item_contrato'),
            'descricao' => $this->request->getPost('descricao')
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
        $model = new CatalogoServicosModel();
        $deleted = $model->delete($id);

        return $this->response->setJSON([
            'status' => $deleted ? 'success' : 'warning',
            'mensagem' => $deleted ? 'Registro deletado com sucesso!' : 'Falha ao deletar o registro. Tente novamente.'
        ]);
    }
}

