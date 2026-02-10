<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UsuarioModel;

class PagamentoInicialAdminController extends BaseController
{
    protected function checkAdminAuth()
    {
        if (!isset($_SESSION['perfil_usuario_logado']) || $_SESSION['perfil_usuario_logado'] !== 'Admin') {
            return redirect()->to('/')->with('error', 'Acesso negado. Somente administradores podem acessar esta área.');
        }
        return null;
    }

    public function index()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $usuarioModel = new UsuarioModel();
        $usuarios = $usuarioModel->where('pagamento_inicial', 0)->findAll();
        return view('admin/pagamento_inicial', ['usuarios' => $usuarios]);
    }

    public function autorizar($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $usuarioModel = new UsuarioModel();
        $usuario = $usuarioModel->find($id);
        if (!$usuario) {
            return redirect()->back()->with('error', 'Usuário não encontrado.');
        }
        $usuarioModel->update($id, ['pagamento_inicial' => 1]);
        return redirect()->back()->with('success', 'Pagamento inicial autorizado para o usuário.');
    }
}
