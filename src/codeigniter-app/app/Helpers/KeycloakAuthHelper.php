<?php

namespace App\Helpers;

use Exception;

class KeycloakAuthHelper
{
    /**
     * Troca o código de autorização pelo token e retorna os dados do usuário
     */
    public static function authenticate($code, $redirectUri)
    {
        $clientId = getenv('KEYCLOAK_CLIENT_ID_CODEIGNITER') ?: 'codeigniter-app';
        $clientSecret = getenv('KEYCLOAK_CLIENT_SECRET_CODEIGNITER') ?: '';
        $tokenUrl = getenv('KEYCLOAK_PROVIDER_URL') . '/protocol/openid-connect/token';

        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Erro ao obter token do Keycloak: ' . $response);
        }

        $tokenData = json_decode($response, true);
        if (!isset($tokenData['id_token'])) {
            throw new Exception('Token ID não encontrado na resposta do Keycloak.');
        }

        // Decodifica o id_token (JWT)
        $jwtParts = explode('.', $tokenData['id_token']);
        if (count($jwtParts) !== 3) {
            throw new Exception('Formato do id_token inválido.');
        }
        $payload = json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true);
        if (!$payload) {
            throw new Exception('Não foi possível decodificar o payload do id_token.');
        }

        return [
            'tokenData' => $tokenData,
            'user' => $payload
        ];
    }
}
