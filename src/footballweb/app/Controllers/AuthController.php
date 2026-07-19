<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UsuarioModel;
use App\Models\UsuarioPerfilModel;
use App\Helpers\GoogleAuthHelper;
use App\Helpers\MinioHelper;
use App\Helpers\AirflowHelper;
use App\Models\ActivityLogModel;

/**
 * AuthController
 * Gerencia autenticação social (Google OAuth2)
 */
class AuthController extends BaseController
{
    /**
     * Redireciona para login do Google
     */
    public function googleLoginRedirect()
    {
        try {
            $authUrl = GoogleAuthHelper::getAuthUrl();
            return redirect()->to($authUrl);
        } catch (\Exception $e) {
            log_message('error', '[GOOGLE_AUTH] Erro ao gerar URL de autenticação: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro ao iniciar autenticação Google'
            ]);
        }
    }

    /**
     * Callback após autenticação Google
     */
    public function googleCallback()
    {
        $code = $this->request->getGet('code');
        $state = $this->request->getGet('state');
        $error = $this->request->getGet('error');

        if ($error) {
            log_message('warning', '[GOOGLE_AUTH] Erro do Google: ' . $error);
            $_SESSION['error_message'] = 'Autenticação Google cancelada ou com erro.';
            return redirect()->to('/loginUsuario');
        }

        if (!$code) {
            $_SESSION['error_message'] = 'Código de autorização não recebido.';
            return redirect()->to('/loginUsuario');
        }

        try {
            // Processa callback e obtém dados do usuário
            $result = GoogleAuthHelper::handleCallback($code);
            
            if (!$result['success']) {
                log_message('error', '[GOOGLE_AUTH] ' . $result['message']);
                $_SESSION['error_message'] = $result['message'];
                return redirect()->to('/loginUsuario');
            }

            // Procura ou cria usuário
            $usuario = GoogleAuthHelper::findOrCreateUser([
                'google_id' => $result['google_id'],
                'email' => $result['email'],
                'nome' => $result['nome'],
                'picture' => $result['picture'],
            ]);

            if (!$usuario) {
                $_SESSION['error_message'] = 'Erro ao processar usuário.';
                return redirect()->to('/loginUsuario');
            }

            // Garante que o usuário tenha a pasta padrão
            $pastaModel = new \App\Models\PastaModel();
            $pastaExistente = $pastaModel->where('id_usuario', $usuario->id)
                                         ->where('descricao', 'pasta-padrao')
                                         ->first();
            if (!$pastaExistente) {
                $pastaModel->insert([
                    'descricao' => 'pasta-padrao',
                    'id_usuario' => $usuario->id
                ]);
            }

            // Salva token
            GoogleAuthHelper::saveTokenData($usuario->id, $result['token']);

            // Registra na sessão
            $_SESSION['id_usuario_logado'] = $usuario->id;
            $_SESSION['nome_usuario_logado'] = $usuario->nome;
            $_SESSION['email_usuario_logado'] = $usuario->email;
            $_SESSION['usuario_logado'] = 1;

            // Busca perfis do usuário
            $usuarioPerfilModel = new UsuarioPerfilModel();
            $perfis = $usuarioPerfilModel->getPerfisUsuario($usuario->id);
            $perfilDescricao = $perfis[0]->perfil_descricao ?? 'Teste';
            $_SESSION['perfil_usuario_logado'] = $perfilDescricao;

            // Registra login no activity log (desativado para fiscalweb)
            /*
            if (empty($_SESSION['is_admin'])) {
                try {
                    $logModel = new ActivityLogModel();
                    $logModel->insert([
                        'user_id'    => (int) $usuario->id,
                        'method'     => 'GET',
                        'uri'        => '/auth/google-callback',
                        'controller' => 'AuthController',
                        'action'     => 'googleCallback',
                        'route_alias'=> 'auth.google.callback',
                        'ip_address' => $this->request->getIPAddress(),
                        'user_agent' => ($this->request->getUserAgent() ? (method_exists($this->request->getUserAgent(), 'getAgent') ? $this->request->getUserAgent()->getAgent() : (string) $this->request->getUserAgent()) : ($_SERVER['HTTP_USER_AGENT'] ?? null)),
                        'session_id' => (function_exists('session_id') ? session_id() : null),
                    ]);
                } catch (\Throwable $e) {
                    log_message('warning', '[ActivityLog] Falha ao registrar login: ' . $e->getMessage());
                }
            }
            */

            // Garante bucket no MinIO; falha bloqueia o login via Google
            $bucketResult = MinioHelper::createUserBucket($usuario->id, $usuario->email ?? '');
            if ($bucketResult['success']) {
                log_message('info', "Bucket do usuário {$usuario->id}: {$bucketResult['message']}");
            } else {
                log_message('error', "Falha ao criar bucket do usuário {$usuario->id}: {$bucketResult['message']}");
                $_SESSION['usuario_logado'] = 0;
                $_SESSION['error_message'] = 'Não foi possível provisionar seu bucket de trabalho. Tente novamente em instantes ou contate o suporte.';
                return redirect()->to('/loginUsuario');
            }

            // Sincronização de funções Python desativada para fiscalweb
            /*
            try {
                $usuarioFuncionModel = new \App\Models\UsuarioFuncionConfigurationModel();
                $countFuncoes = $usuarioFuncionModel->contarFuncoesDoUsuario($usuario->id);
                
                if ($countFuncoes == 0) {
                    // Se não tem funções configuradas, sincroniza com padrão
                    $syncResult = $usuarioFuncionModel->sincronizarComPadrao($usuario->id);
                    if ($syncResult) {
                        log_message('info', "Funções Python sincronizadas para novo usuário Google Auth: {$usuario->id}");
                    } else {
                        log_message('warning', "Falha ao sincronizar funções Python para usuário Google Auth: {$usuario->id}");
                    }
                }
            } catch (\Exception $e) {
                log_message('warning', "Erro ao sincronizar funções no Google Auth: " . $e->getMessage());
            }
            */

            // Sincroniza com Airflow usando a senha do banco
            if (AirflowHelper::isAirflowAvailable()) {
                $airflowResult = AirflowHelper::syncUserWithAirflow(
                    $usuario->id,
                    $usuario->email ?? "",
                    explode(' ', $usuario->nome)[0] ?? 'User',
                    (count(explode(' ', $usuario->nome)) > 1) ? implode(' ', array_slice(explode(' ', $usuario->nome), 1)) : $usuario->id,
                    $usuario->senha
                );
                if ($airflowResult['success']) {
                    log_message('info', "[AIRFLOW_GOOGLE] {$airflowResult['message']}");
                } else {
                    log_message('warning', "[AIRFLOW_GOOGLE] {$airflowResult['message']}");
                }
            }

            log_message('info', "[GOOGLE_AUTH] Usuário {$usuario->id} ({$usuario->email}) autenticado com sucesso");
            
            // Registra evento de login para GA4
            $_SESSION['ga4_login_event'] = [
                'method' => 'Google',
                'user_id' => $usuario->id,
                'email' => $usuario->email
            ];
            
            return redirect()->to('/');
        } catch (\Exception $e) {
            log_message('error', '[GOOGLE_AUTH] Exception: ' . $e->getMessage());
            $_SESSION['error_message'] = 'Erro inesperado durante autenticação: ' . $e->getMessage();
            return redirect()->to('/loginUsuario');
        }
    }

    /**
     * Método legado: googleLogin (para compatibilidade com token JWT do frontend)
     */
    public function googleLogin()
    {
        // Recebe o token JWT enviado pelo frontend
        $token = $this->request->getPost('token');
        
        // Substitua pelo seu Client ID gerado no Google Cloud Console
        $CLIENT_ID = '88249765816-a2bvvo2l4qtjsv1dj4lqmfniknodli0h.apps.googleusercontent.com';
        
        // Configura o cliente do Google com o ID do cliente para validação
        $client = new \Google\Client(['client_id' => $CLIENT_ID]);
        
        try {
            // Verifica o token recebido
            $payload = $client->verifyIdToken($token);

            if ($payload) {
                // Token válido, extrai informações do usuário
                $userId = $payload['sub'];       // ID único do usuário Google
                $email = $payload['email'];      // Email do usuário
                $name = $payload['name'];        // Nome do usuário
                
                // Aqui, adicione lógica para criar ou autenticar o usuário na sua plataforma
                // - Se o usuário já existir no banco de dados, inicie a sessão para ele.
                // - Se o usuário não existir, insira os dados no banco e inicie a sessão.
                
                // IMPORTANTE: Após criar/autenticar o usuário no banco, obtenha o ID numérico
                // e crie o bucket do usuário
                // Exemplo (assumindo que você tem o ID do usuário no banco):
                // $dbUserId = ...; // ID numérico do usuário no banco de dados
                // $bucketResult = MinioHelper::createUserBucket($dbUserId);

                // Exemplo básico de resposta JSON com os dados do usuário
                return $this->response->setJSON([
                    'success' => true,
                    'user' => [
                        'name' => $name,
                        'email' => $email
                    ]
                ]);
            } else {
                // Caso o token seja inválido
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Token inválido.'
                ]);
            }
        } catch (\Exception $e) {
            // Erro ao verificar o token (exemplo: token expirado ou inválido)
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro de autenticação: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Simula login social do Google para testes locais
     */
    public function simulateGoogleLogin()
    {
        $usuarioModel = new \App\Models\UsuarioModel();
        
        // Pega o primeiro usuário para simular (ou cria um se vazio)
        $usuario = $usuarioModel->first();
        if (!$usuario) {
            // Cria um usuário teste de emergência
            $senha = bin2hex(random_bytes(16));
            $usuarioId = $usuarioModel->insert([
                'nome' => 'Usuário Teste Google',
                'email' => 'teste.google@estudotabela.com.br',
                'senha' => $senha,
                'email_confirmado' => 1,
                'google_id' => '123456789_simulated',
                'auth_provider' => 'google',
                'auth_updated_at' => date('Y-m-d H:i:s'),
                'data_inicio_trial' => date('Y-m-d'),
                'data_vencimento_assinatura' => date('Y-m-d', strtotime('+30 days')),
                'status_assinatura' => 'trial',
                'grok_credits' => 20
            ]);
            $usuario = $usuarioModel->find($usuarioId);
        } else {
            // Garante que o usuário possua google_id setado e créditos
            $usuarioModel->update($usuario->id, [
                'google_id' => '123456789_simulated',
                'auth_provider' => 'google',
                'grok_credits' => max((int)($usuario->grok_credits ?? 0), 20)
            ]);
            // Recarrega os dados atualizados
            $usuario = $usuarioModel->find($usuario->id);
        }

        // Inicia a sessão
        $_SESSION['usuario_logado'] = 1;
        $_SESSION['id_usuario_logado'] = $usuario->id;
        $_SESSION['nome_usuario_logado'] = $usuario->nome;
        $_SESSION['email_usuario_logado'] = $usuario->email;

        return redirect()->to('/football-trends')->with('success', 'Simulado login social Google com sucesso! 20 créditos disponíveis.');
    }
}
