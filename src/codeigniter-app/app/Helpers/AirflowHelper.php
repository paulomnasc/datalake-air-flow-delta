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

            // Força perfil básico a ser Viewer (nunca usar role global 'User' para evitar acesso amplo)
            $roleName = ($role === 'User' || empty($role)) ? 'Viewer' : $role;
            $ownerRole = self::buildUserRoleName($username);

            // Começa com Viewer e só inclui a role de dono se ela EXISTIR de fato
            $roles = [['name' => $roleName]];
            $ownerRoleExists = false;
            if (!empty($ownerRole)) {
                // Tenta criar se não existir, antes de montar payload
                if (!self::roleExists($ownerRole)) {
                    $created = self::createRole($ownerRole);
                    if ($created) {
                        log_message('info', "[AirflowHelper] Role {$ownerRole} criado via API (pré-criação de usuário).");
                    } else {
                        log_message('warning', "[AirflowHelper] Falha ao criar role {$ownerRole} via API (pré-criação de usuário).");
                    }
                }

                // Recheca existência para decidir incluir no payload
                $ownerRoleExists = self::roleExists($ownerRole);
                if ($ownerRoleExists) {
                    $roles[] = ['name' => $ownerRole];
                } else {
                    log_message('warning', "[AirflowHelper] Role do dono ainda não existe: {$ownerRole}. Prosseguindo com Viewer apenas.");
                }
            }

            $userData = [
                'username'   => $username,
                'email'      => $email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'password'   => $password,
                // Airflow espera objetos em roles: [{"name": "Viewer"}]
                'roles'      => $roles
            ];

            // 1) Tenta criar
            $create = self::apiCall('POST', $airflowUrl, $userData);
            if ($create['success']) {
                log_message('info', "[AirflowHelper] ✅ Usuário criado no Airflow: {$username}");
                // Se ainda não anexou a role do dono, tenta novamente criar e anexar pós-criação
                if (!empty($ownerRole)) {
                    self::ensureOwnerRoleAndAttach($username, $ownerRole);
                }
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
                    if (!empty($ownerRole)) {
                        self::ensureOwnerRoleAndAttach($username, $ownerRole);
                    }
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

            // 3) Se falhou por causa de role desconhecida, tentar novamente com apenas Viewer
            if (str_contains(strtolower($create['error']), 'unknown roles')) {
                log_message('warning', "[AirflowHelper] Unknown roles na criação. Reenviando apenas com Viewer.");
                $userDataViewerOnly = $userData;
                $userDataViewerOnly['roles'] = [['name' => 'Viewer']];
                $createFallback = self::apiCall('POST', $airflowUrl, $userDataViewerOnly);
                if ($createFallback['success']) {
                    log_message('info', "[AirflowHelper] ✅ Usuário criado no Airflow (fallback Viewer): {$username}");
                    // Depois do fallback, tentar novamente criar/anexar a role do dono
                    if (!empty($ownerRole)) {
                        self::ensureOwnerRoleAndAttach($username, $ownerRole);
                    }
                    return [
                        'success'  => true,
                        'message'  => "Usuário {$username} criado com Viewer (role dono inexistente)",
                        'username' => $username,
                        'action'   => 'created-viewer-only'
                    ];
                }
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
     * Cria ou verifica a existência de um role customizado para o dono da DAG
     */
    private static function roleExists(string $roleName): bool
    {
        $roleUrl = 'http://airflow-webserver:8080/api/v1/roles/' . urlencode($roleName);
        $get = self::apiCall('GET', $roleUrl);
        return $get['success'];
    }

    /**
     * Cria role via API com corpo mínimo. Retorna true em sucesso ou conflito (já existe).
     */
    private static function createRole(string $roleName): bool
    {
        $rolesUrl = 'http://airflow-webserver:8080/api/v1/roles';
        // Corpo mínimo; algumas versões exigem 'permissions' vazio
        $payloads = [
            ['name' => $roleName],
            ['name' => $roleName, 'permissions' => []]
        ];

        foreach ($payloads as $body) {
            $resp = self::apiCall('POST', $rolesUrl, $body);
            if ($resp['success']) {
                return true;
            }
            $err = strtolower($resp['error'] ?? '');
            if (str_contains($err, 'already exists') || str_contains($err, '409')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Garante role criada e anexada ao usuário (best-effort).
     */
    private static function ensureOwnerRoleAndAttach(string $username, string $ownerRole): void
    {
        // Cria se não existir
        if (!self::roleExists($ownerRole)) {
            $created = self::createRole($ownerRole);
            if ($created) {
                log_message('info', "[AirflowHelper] Role {$ownerRole} criado (pós-criação de usuário).");
            } else {
                log_message('warning', "[AirflowHelper] Falha ao criar role {$ownerRole} (pós-criação de usuário).");
            }
        }

        // Tenta anexar se agora existir
        if (self::roleExists($ownerRole)) {
            $attached = self::addExistingRoleToUser($username, $ownerRole);
            if (!$attached) {
                log_message('warning', "[AirflowHelper] Role {$ownerRole} não anexada ao usuário {$username}.");
            }
        }
    }

    /**
     * Adiciona uma role existente ao usuário (não cria roles novas)
     */
    public static function addExistingRoleToUser(string $username, string $roleName): bool
    {
        // Busca usuário atual para não sobrescrever outras configurações
        $userUrl = 'http://airflow-webserver:8080/api/v1/users/' . urlencode($username);
        $getUser = self::apiCall('GET', $userUrl);
        if (!$getUser['success']) {
            log_message('warning', "[AirflowHelper] Não foi possível obter usuário {$username} para adicionar role: {$getUser['error']}");
            return false;
        }

        $userData = $getUser['response'] ?? [];
        $currentRoles = $userData['roles'] ?? [];

        // Se já tem a role, nada a fazer
        foreach ($currentRoles as $r) {
            if (($r['name'] ?? '') === $roleName) {
                return true;
            }
        }

        // Acrescenta a nova role existente
        $currentRoles[] = ['name' => $roleName];
        $patchData = [
            'first_name' => $userData['first_name'] ?? 'User',
            'last_name'  => $userData['last_name'] ?? 'User',
            'email'      => $userData['email'] ?? ($username.'@system.local'),
            'roles'      => $currentRoles
        ];

        $patch = self::apiCall('PATCH', $userUrl, $patchData);
        if ($patch['success']) {
            log_message('info', "[AirflowHelper] Role {$roleName} adicionada ao usuário {$username}.");
            return true;
        }

        log_message('warning', "[AirflowHelper] Falha ao adicionar role {$roleName} ao usuário {$username}: {$patch['error']}");
        return false;
    }

    /**
     * Constrói nome de role por usuário, alinhado ao username
     */
    private static function buildUserRoleName(string $username): string
    {
        // role precisa ser simples e consistente com access_control nas DAGs
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $username) ?? '';
        return trim($sanitized, '-') ?: '';
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
