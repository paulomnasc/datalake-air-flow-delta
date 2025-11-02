<?php

namespace App\Controllers;

use Google\Client as GoogleClient;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    public function googleLogin()
    {
        // Recebe o token JWT enviado pelo frontend
        $token = $this->request->getPost('token');
        
        // Substitua pelo seu Client ID gerado no Google Cloud Console
        $CLIENT_ID = '88249765816-a2bvvo2l4qtjsv1dj4lqmfniknodli0h.apps.googleusercontent.com';
        
        // Configura o cliente do Google com o ID do cliente para validação
        $client = new GoogleClient(['client_id' => $CLIENT_ID]);
        
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
}
