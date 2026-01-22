<?php

namespace App\Helpers;

use GuzzleHttp\Client as HttpClient;

/**
 * GoogleAuthHelper
 * Gerencia autenticação OAuth2 com Google (sem google/apiclient)
 */
class GoogleAuthHelper
{
    private static $client = null;
    private const GOOGLE_AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const GOOGLE_USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * Gera URL de autorização do Google
     */
    public static function getAuthUrl()
    {
        $clientId = getenv('GOOGLE_CLIENT_ID') ?: $_ENV['GOOGLE_CLIENT_ID'] ?? null;
        
        if (empty($clientId)) {
            throw new \Exception('GOOGLE_CLIENT_ID não configurado no .env');
        }
        
        $params = [
            'client_id'     => $clientId,
            'redirect_uri'  => base_url('auth/google-callback'),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'offline',
        ];
        
        return self::GOOGLE_AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Trata o callback do Google e retorna dados do usuário
     */
    public static function handleCallback($code)
    {
        try {
            $clientId = getenv('GOOGLE_CLIENT_ID') ?: $_ENV['GOOGLE_CLIENT_ID'] ?? null;
            $clientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: $_ENV['GOOGLE_CLIENT_SECRET'] ?? null;
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('Google credentials não configurados');
            }
            
            // Usa GuzzleHttp para requisição HTTP
            $httpClient = new HttpClient();
            
            // Troca o código por token
            $response = $httpClient->post(self::GOOGLE_TOKEN_URL, [
                'form_params' => [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'code'          => $code,
                    'redirect_uri'  => base_url('auth/google-callback'),
                    'grant_type'    => 'authorization_code',
                ],
            ]);
            
            $tokenData = json_decode($response->getBody(), true);
            
            if (empty($tokenData['access_token'])) {
                throw new \Exception('Token de acesso não recebido do Google');
            }
            
            // Usa o token para obter informações do usuário
            $userResponse = $httpClient->get(self::GOOGLE_USERINFO_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokenData['access_token'],
                ],
            ]);
            
            $userInfo = json_decode($userResponse->getBody(), true);
            
            return [
                'success' => true,
                'google_id' => $userInfo['id'] ?? null,
                'email' => $userInfo['email'] ?? null,
                'nome' => $userInfo['name'] ?? 'User',
                'picture' => $userInfo['picture'] ?? null,
                'token' => $tokenData,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao autenticar com Google: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Procura ou cria usuário no banco baseado em dados do Google
     */
    public static function findOrCreateUser($googleData)
    {
        $usuarioModel = new \App\Models\UsuarioModel();
        
        // Procura por google_id
        $usuario = $usuarioModel->where('google_id', $googleData['google_id'])->first();
        
        if (!$usuario) {
            // Procura por email
            $usuario = $usuarioModel->where('email', $googleData['email'])->first();
        }
        
        if ($usuario) {
            // Atualiza dados do Google se ainda não estão salvos
            if (empty($usuario->google_id)) {
                $usuarioModel->update($usuario->id, [
                    'google_id' => $googleData['google_id'],
                    'auth_provider' => 'google',
                    'auth_updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return $usuario;
        }
        
        // Cria novo usuário
        $nomePartes = explode(' ', $googleData['nome']);
        $senha = bin2hex(random_bytes(16)); // Senha aleatória (usuário não usa senha)
        
        $novoUsuario = [
            'nome' => $googleData['nome'],
            'email' => $googleData['email'],
            'senha' => $senha,
            'email_confirmado' => 1,
            'google_id' => $googleData['google_id'],
            'auth_provider' => 'google',
            'auth_updated_at' => date('Y-m-d H:i:s'),
            'data_inicio_trial' => date('Y-m-d'),
            'data_vencimento_assinatura' => date('Y-m-d', strtotime('+30 days')),
            'status_assinatura' => 'trial',
        ];
        
        $usuarioId = $usuarioModel->insert($novoUsuario);
        
        // Associa ao perfil "Teste" (ou outro padrão)
        $usuarioPerfilModel = new \App\Models\UsuarioPerfilModel();
        $perfilTeste = (new \App\Models\PerfilModel())->where('descricao', 'Teste')->first();
        
        if ($perfilTeste) {
            $usuarioPerfilModel->insert([
                'id_usuario' => $usuarioId,
                'id_perfil' => $perfilTeste->id,
            ]);
        }
        
        return $usuarioModel->find($usuarioId);
    }

    /**
     * Salva informações criptografadas do token
     */
    public static function saveTokenData($usuarioId, $tokenData)
    {
        $usuarioModel = new \App\Models\UsuarioModel();
        
        $updateData = [
            'google_token' => json_encode($tokenData),
            'auth_updated_at' => date('Y-m-d H:i:s'),
        ];
        
        if (isset($tokenData['refresh_token'])) {
            $updateData['google_refresh_token'] = $tokenData['refresh_token'];
        }
        
        $usuarioModel->update($usuarioId, $updateData);
    }
}
