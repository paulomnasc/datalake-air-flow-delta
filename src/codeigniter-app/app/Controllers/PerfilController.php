<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PerfilModel;
use App\Models\FuncionalidadeModel;
use App\Models\PerfilFuncionalidadeModel;

class PerfilController extends BaseController
{
    public function index()
    {
        //

        
        $list = $this->list();        
        return view('listPerfil',['list'=>$list]);


    }

    


    public function add()
    {
        $funcionalidadeModel = new FuncionalidadeModel();
        $data['funcionalidades'] = $funcionalidadeModel->listToCombo();
        return view('addPerfil', $data);
    }

    public function upd()
    {

        $id = $this->request->getPost('id');
        
        $model = new PerfilModel();
        $perfil = $model->find($id);

        $funcionalidadeModel = new FuncionalidadeModel();
        $data['funcionalidades'] = $funcionalidadeModel->listToCombo();
        
        // Buscar as funcionalidades associadas ao perfil
        $perfilFuncionalidadeModel = new PerfilFuncionalidadeModel();
        $funcionalidadesPerfil = $perfilFuncionalidadeModel->getFuncionalidadesPerfil($id);
        $funcionalidadesSelecionadas = [];
        foreach ($funcionalidadesPerfil as $func) {
            $funcionalidadesSelecionadas[] = $func->id_funcionalidade;
        }
        
        $data['funcionalidades_selecionadas'] = $funcionalidadesSelecionadas;
        $data['id'] = $perfil->id;
        $data['descricao'] = $perfil->descricao;

        return view('updPerfil',$data);

    }

    public function del($id)
    {

        $this->delete($id);
        

    }


    public $perfis = array(); function list()  {
        
        $model = new PerfilModel();
        $list = $model->findAll();
        return $list;
    }

    public function findById(int $id )  {
        
        $model = new PerfilModel();
        $perfil = $model->find($id);
        return $perfil;
    }

    public function insert() {
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $funcionalidades = $this->request->getPost('id_funcionalidade');
        
        $model = new PerfilModel();
        $perfilFuncionalidadeModel = new PerfilFuncionalidadeModel();
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        try {
            $idPerfil = $model->insert($data);
            
            if ($idPerfil && !empty($funcionalidades)) {
                $perfilFuncionalidadeModel->saveFuncionalidadesPerfil($idPerfil, $funcionalidades);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                $error = $db->error();
                $lastQuery = method_exists($db, 'getLastQuery') ? (string) $db->getLastQuery() : '';
                throw new \Exception('Falha na transação ao inserir perfil e funcionalidades. Detalhe: ' . ($error['code'] ?? '0') . ' - ' . ($error['message'] ?? 'sem mensagem') . ' | Query: ' . $lastQuery);
            }
        
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Perfil inserido com sucesso!'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o perfil: ' . $e->getMessage()
            ]);
        }
    }
    
    public function update() {
        $model = new PerfilModel();
        $perfilFuncionalidadeModel = new PerfilFuncionalidadeModel();
        $id = $this->request->getPost('id');
        $data = [
            'descricao' => $this->request->getPost('descricao')
        ];
        
        $funcionalidades = $this->request->getPost('id_funcionalidade');
        
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $updated = $model->update($id, $data);
            
            if ($updated && !empty($funcionalidades)) {
                $perfilFuncionalidadeModel->saveFuncionalidadesPerfil($id, $funcionalidades);
            } elseif ($updated) {
                // Remove todas as funcionalidades se não houver selecionadas
                $perfilFuncionalidadeModel->deleteFuncionalidadesPerfil($id);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                $error = $db->error();
                $lastQuery = method_exists($db, 'getLastQuery') ? (string) $db->getLastQuery() : '';
                throw new \Exception('Falha na transação ao atualizar perfil e funcionalidades. Detalhe: ' . ($error['code'] ?? '0') . ' - ' . ($error['message'] ?? 'sem mensagem') . ' | Query: ' . $lastQuery);
            }
        
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Perfil atualizado com sucesso!'
            ]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o perfil: ' . $e->getMessage()
            ]);
        }
        
    }
    
    public function delete($id)  {
        
        $model = new PerfilModel();
        $deleted = $model->delete($id);
        

        if($deleted) 
        {
            return $this->response->setJSON([
                'status' => $deleted ? 'success' : 'warning',
                'mensagem' => $deleted ? 'Registro atualizado com sucesso!' : 'Falha ao atualizar o registro. Tente novamente.'
            ]);

        }
    }
}
