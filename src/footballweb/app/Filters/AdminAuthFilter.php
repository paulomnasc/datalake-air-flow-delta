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
        $perfil = trim((string) ($session->get('perfil_usuario_logado') ?? ($_SESSION['perfil_usuario_logado'] ?? '')));
        $usuarioLogado = $session->get('usuario_logado') ?? ($_SESSION['usuario_logado'] ?? 0);
        $userEmail = trim((string) ($session->get('email_usuario_logado') ?? ($_SESSION['email_usuario_logado'] ?? '')));
        $userId = $session->get('id_usuario_logado') ?? ($_SESSION['id_usuario_logado'] ?? null);

        // Fallback de resolução do e-mail do usuário se ausente na sessão ativa
        if (empty($userEmail) && !empty($userId)) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $u = $usuarioModel->find($userId);
            if ($u && !empty($u->email)) {
                $userEmail = trim((string) $u->email);
                $_SESSION['email_usuario_logado'] = $userEmail;
                $session->set('email_usuario_logado', $userEmail);
            }
        }

        $uri = $request->getUri()->getPath();

        $isPaulo = (strcasecmp($userEmail, 'paulomnasc@gmail.com') === 0) || ((int) $userId === 558);

        // Rota /terminal/stake (e sub-rotas) é de acesso ESTRITAMENTE EXCLUSIVO de paulomnasc@gmail.com
        if (strpos($uri, 'terminal/stake') !== false) {
            if ((empty($usuarioLogado) && empty($userId)) || !$isPaulo) {
                if ($request->isAJAX()) {
                    return service('response')->setStatusCode(403)->setJSON([
                        'success' => false,
                        'message' => 'Acesso negado. A rota /terminal/stake é de acesso exclusivo de paulomnasc@gmail.com.'
                    ]);
                }
                $session->setFlashdata('error', 'Acesso negado. A rota /terminal/stake é de acesso exclusivo de paulomnasc@gmail.com.');
                return redirect()->to('/');
            }
            return null;
        }

        $isAdmin = !empty($session->get('is_admin'))
            || !empty($_SESSION['is_admin'])
            || in_array(strtolower($perfil), ['admin', 'administrador'], true)
            || strcasecmp($userEmail, 'paulomnasc@gmail.com') === 0;

        // Se não está logado ou não é Admin, redireciona
        if (empty($usuarioLogado) || !$isAdmin) {
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
