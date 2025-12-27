<?php

namespace App\Helpers;

class AirflowHelper
{
    /**
     * Sincroniza usuário com Airflow (cria ou atualiza)
     * 
     * @param int $userId ID do usuário no sistema
     * @param string $email Email do usuário
     * @param string $firstName Primeiro nome
     * @param string $lastName Último nome
     * @param string $password Senha (usa a mesma do sistema)
     * @param string $role Role no Airflow (default: Viewer)
     * 
     * @return array ['success' => bool, 'message' => string, 'username' => string]
     */
    public static function syncUserWithAirflow(
        int $userId,
        string $email,
        string $firstName = '',
        string $lastName = '',
        string $password = '',
        string $role = 'Viewer'
    ): array {
        try {
            // Username baseado no prefixo do email + id para unicidade
            $username = self::buildUsernameFromEmail($email, $userId);
            
            // Sanitizar entrada
            $firstName = substr(trim($firstName ?? ''), 0, 50) ?: "User";
            $lastName = substr(trim($lastName ?? ''), 0, 50) ?: "User";
            $email = filter_var($email, FILTER_SANITIZE_EMAIL) ?: "user-{$userId}@system.local";
            
            // Se não forneceu password, gerar uma aleatória
            if (empty($password)) {
                $password = bin2hex(random_bytes(8));
            }
            
            log_message('debug', "[AirflowHelper] Sincronizando usuário via API: {$username}");

            $airflowUrl = 'http://airflow-webserver:8080/api/v1/users';

            $roleName = $role ?: 'Viewer';

            $userData = [
                'username'   => $username,
                'email'      => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'password'   => $password,
                // Airflow espera objetos em roles: [{"name": "Viewer"}]
                'roles'      => [['name' => $roleName]]
            ];

            // 1) Tenta criar
            $create = self::apiCall('POST', $airflowUrl, $userData);
            if ($create['success']) {
                log_message('info', "[AirflowHelper] ✅ Usuário criado no Airflow: {$username}");
                return [
                    'success'  => true,
                    'message'  => "Usuário {$username} criado com sucesso no Airflow",
                    'username' => $username,
                    'action'   => 'created'
                ];
            }

            // 2) Se já existe, tenta atualizar
            if (str_contains($create['error'], 'already exists') || str_contains($create['error'], '409')) {
                $update = self::apiCall('PATCH', "{$airflowUrl}/{$username}", $userData);
                if ($update['success']) {
                    log_message('info', "[AirflowHelper] ✅ Usuário atualizado no Airflow: {$username}");
                    return [
                        'success'  => true,
                        'message'  => "Usuário {$username} atualizado com sucesso no Airflow",
                        'username' => $username,
                        'action'   => 'updated'
                    ];
                }

                log_message('warning', "[AirflowHelper] ❌ Falha ao atualizar usuário: {$update['error']}");
                return [
                    'success'  => false,
                    'message'  => "Falha ao atualizar usuário no Airflow: {$update['error']}",
                    'username' => $username
                ];
            }

            log_message('warning', "[AirflowHelper] ❌ Falha ao criar usuário no Airflow: {$create['error']}");
            return [
                'success'  => false,
                'message'  => "Falha ao sincronizar usuário com Airflow: {$create['error']}",
                'username' => $username
            ];
            
        } catch (\Exception $e) {
            log_message('error', "[AirflowHelper] Exceção ao sincronizar com Airflow: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Erro ao sincronizar com Airflow: {$e->getMessage()}",
                'username' => "unknown"
            ];
        }
    }
    
    /**
     * Verifica se o Airflow está disponível
     * 
     * @return bool
     */
    public static function isAirflowAvailable(): bool
    {
        try {
            $ch = curl_init('http://airflow-webserver:8080/health');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 5
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // Considera disponível se HTTP 200 e resposta não vazia
            return $httpCode === 200 && !empty($response);
        } catch (\Exception $e) {
            log_message('warning', "[AirflowHelper] Airflow não disponível: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Chamada REST ao Airflow com basic auth (admin:admin)
     */
    private static function apiCall(string $method, string $url, array $data = []): array
    {
        try {
            $ch = curl_init($url);
            $auth = base64_encode('admin:admin');

            $headers = [
                "Authorization: Basic {$auth}",
                'Content-Type: application/json'
            ];

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
            ];

            if (!empty($data) && in_array($method, ['POST', 'PATCH', 'PUT'])) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            }

            curl_setopt_array($ch, $options);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            log_message('debug', "[AirflowAPI] {$method} {$url} HTTP {$httpCode} resp={$response}");

            if ($httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'error' => '', 'response' => json_decode($response, true)];
            }

            $err = $curlError ?: ($response ?: 'Erro desconhecido');
            return ['success' => false, 'error' => "HTTP {$httpCode}: {$err}", 'response' => []];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'response' => []];
        }
    }

    /**
     * Gera username baseado no prefixo do email e no id para garantir unicidade
     */
    public static function buildUsernameFromEmail(?string $email, int $userId): string
    {
        $prefix = '';
        if (!empty($email) && str_contains($email, '@')) {
            $prefix = strtolower(explode('@', $email)[0]);
            // normaliza caracteres
            $prefix = preg_replace('/[^a-z0-9\-]+/', '-', $prefix);
            $prefix = trim($prefix, '-');
        }

        if (empty($prefix)) {
            $prefix = 'user';
        }

        // limita tamanho e garante unicidade com id
        $prefix = substr($prefix, 0, 30);
        return "{$prefix}-{$userId}";
    }
}
