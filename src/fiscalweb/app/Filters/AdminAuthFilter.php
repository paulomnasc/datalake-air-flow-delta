<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Verifica se o usuário está logado e se é Admin
        $session = session();
        $perfil = $session->get('perfil_usuario_logado') ?? ($_SESSION['perfil_usuario_logado'] ?? null);
        $usuarioLogado = $session->get('usuario_logado') ?? ($_SESSION['usuario_logado'] ?? 0);

        // Se não está logado ou não é Admin, redireciona
        if (empty($usuarioLogado) || $perfil !== 'Admin') {
            // Define mensagem de erro na sessão
            $session->setFlashdata('error', 'Acesso negado. Apenas administradores podem acessar esta área.');
            
            // Redireciona para a home ou login
            return redirect()->to('/');
        }

        // Usuário é Admin, permite acesso
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Não precisa fazer nada após a execução
        return $response;
    }
}
