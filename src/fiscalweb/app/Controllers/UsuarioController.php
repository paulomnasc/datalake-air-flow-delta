<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;
use App\Models\PerfilModel;

class UsuarioController extends BaseController
{
    public function index()
    {
        $model = new UsuarioModel();
        $usuarios = $model->findAll();
        
        return view('listUsuario', ['list' => $usuarios]);
    }

    public function add()
    {
        return view('addUsuario');
    }

    public function insert()
    {
        $data = [
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email'),
            'senha' => $this->request->getPost('senha'), // Idealmente hash a senha, mas mantendo a lógica atual
            'perfil_comportamental' => 'Desinteressado',
            'email_confirmado' => 1
        ];

        $model = new UsuarioModel();
        
        try {
            if ($model->insert($data)) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => 'Usuário inserido com sucesso!'
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Erro de validação: ' . implode(', ', $model->errors())
                ]);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao inserir o registro: ' . $e->getMessage()
            ]);
        }
    }

    public function upd($id)
    {
        $model = new UsuarioModel();
        $usuario = $model->find($id);

        if (!$usuario) {
            return redirect()->to('/usuario');
        }

        return view('updUsuario', ['usuario' => $usuario]);
    }

    public function update($id)
    {
        $data = [
            'nome' => $this->request->getPost('nome'),
            'email' => $this->request->getPost('email')
        ];

        // Só atualiza a senha se for preenchida
        $senha = $this->request->getPost('senha');
        if (!empty($senha)) {
            $data['senha'] = $senha;
        }

        $model = new UsuarioModel();

        try {
            if ($model->update($id, $data)) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => 'Usuário atualizado com sucesso!'
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Erro de validação: ' . implode(', ', $model->errors())
                ]);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Falha ao atualizar o registro: ' . $e->getMessage()
            ]);
        }
    }

    public function del($id)
    {
        $model = new UsuarioModel();
        
        try {
            if ($model->delete($id)) {
                return $this->response->setJSON([
                    'status' => 'success',
                    'mensagem' => 'Usuário removido com sucesso!'
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Não foi possível remover o usuário.'
                ]);
            }
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro: ' . $e->getMessage()
            ]);
        }
    }
}
