<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ActivityLogModel;

class ActivityLogFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Logging será feito no after para capturar rota resolvida
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Verifica sessão do usuário: só loga se nome_usuario_logado estiver preenchido
        $session = session();
        $nomeUsuario = $session->get('nome_usuario_logado') ?? ($_SESSION['nome_usuario_logado'] ?? null);
        $userId = $session->get('id_usuario_logado') ?? ($_SESSION['id_usuario_logado'] ?? null);

        if (empty($nomeUsuario)) {
            return;
        }

        // Evita log de alguns assets comuns
        $path = $request->getUri()->getPath();
        if (preg_match('#\.(css|js|png|jpg|jpeg|gif|svg|ico)$#i', $path)) {
            return;
        }

        // Obtém dados de rota
        $router = service('router');
        $controller = method_exists($router, 'getControllerName') ? $router->getControllerName() : null;
        $method = method_exists($router, 'getMethodName') ? $router->getMethodName() : null;
        $options = method_exists($router, 'getMatchedRouteOptions') ? ($router->getMatchedRouteOptions() ?? []) : [];
        $alias = $options['as'] ?? null;

        // User Agent
        $ua = $request->getUserAgent();
        $agentString = null;
        if ($ua) {
            $agentString = method_exists($ua, 'getAgent') ? $ua->getAgent() : (string) $ua;
        } else {
            $agentString = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        // Monta registro
        $data = [
            'user_id'    => (int) $userId,
            'method'     => strtoupper($request->getMethod()),
            'uri'        => $path,
            'controller' => $controller,
            'action'     => $method,
            'route_alias'=> $alias,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $agentString,
            'session_id' => (function_exists('session_id') ? session_id() : null),
        ];

        try {
            $model = new ActivityLogModel();
            $model->insert($data);
        } catch (\Throwable $e) {
            // Evita quebrar fluxo por erro de log
            log_message('warning', '[ActivityLog] Falha ao registrar log: ' . $e->getMessage());
        }
    }
}
