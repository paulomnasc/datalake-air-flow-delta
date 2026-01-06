<?php

namespace App\Helpers;

class AirflowHelper
{
    /**
     * Retorna a URL base do Airflow (host:porta) a partir de variáveis de ambiente ou padrão.
     */
    private static function getAirflowBaseUrl(): string
    {
        $host = getenv('AIRFLOW_HOST') ?: 'airflow-webserver';
        $port = getenv('AIRFLOW_PORT') ?: '8080';
        log_message('debug', '[AirflowHelper] AIRFLOW_HOST=' . $host . ' AIRFLOW_PORT=' . $port);
        return "http://{$host}:{$port}";
    }

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
            log_message('info', '[AirflowHelper] Iniciando syncUserWithAirflow para userId=' . $userId . ', email=' . $email);
            // Username baseado no prefixo do email + id para unicidade
            $username = self::buildUsernameFromEmail($email, $userId);
            log_message('debug', '[AirflowHelper] Username gerado para Airflow: ' . $username);
            
            // Sanitizar entrada
            $firstName = substr(trim($firstName ?? ''), 0, 50) ?: "User";
            $lastName = substr(trim($lastName ?? ''), 0, 50) ?: "User";
            $email = filter_var($email, FILTER_SANITIZE_EMAIL) ?: "user-{$userId}@system.local";
            
            // Se não forneceu password, gerar uma aleatória
            if (empty($password)) {
                log_message('warning', "[AirflowHelper] Senha vazia recebida! Gerando senha aleatória.");
                $password = bin2hex(random_bytes(8));
            } else {
                log_message('info', "[AirflowHelper] Senha recebida com sucesso (tamanho: " . strlen($password) . " caracteres)");
            }
            
            log_message('debug', "[AirflowHelper] Sincronizando usuário via API: {$username}");

            $airflowUrl = self::getAirflowBaseUrl() . '/api/v1/users';
            log_message('debug', '[AirflowHelper] URL de API do Airflow: ' . $airflowUrl);

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

            // 2) Se já existe (409), tenta buscar por email e atualizar
            if (str_contains($create['error'], 'already exists') || str_contains($create['error'], 'already taken') || str_contains($create['error'], '409')) {
                log_message('info', "[AirflowHelper] Email já existe no Airflow. Buscando usuário existente...");
                
                // Busca o usuário existente pelo email
                $existingUser = self::getUserByEmail($email);
                if ($existingUser) {
                    $existingUsername = $existingUser['username'];
                    log_message('info', "[AirflowHelper] Usuário encontrado: {$existingUsername}. Atualizando senha e roles...");
                    
                    // Atualiza o usuário existente com a nova senha
                    $update = self::apiCall('PATCH', "{$airflowUrl}/{$existingUsername}", $userData);
                    if ($update['success']) {
                        log_message('info', "[AirflowHelper] ✅ Usuário atualizado no Airflow: {$existingUsername} (senha sincronizada)");
                        if (!empty($ownerRole)) {
                            self::ensureOwnerRoleAndAttach($existingUsername, $ownerRole);
                        }
                        return [
                            'success'  => true,
                            'message'  => "Usuário {$existingUsername} atualizado com sucesso no Airflow (senha sincronizada)",
                            'username' => $existingUsername,
                            'action'   => 'updated'
                        ];
                    }
                }
                
                // Se não encontrou por email, tenta atualizar com o username gerado
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
            $ch = curl_init(self::getAirflowBaseUrl() . '/health');
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
     * Ativa ou desativa um usuário no Airflow
     * 
     * @param int $userId ID do usuário
     * @param string $email Email do usuário
     * @param bool $active True para ativar, False para desativar
     * @return array ['success' => bool, 'message' => string]
     */
    public static function setUserActiveStatus(int $userId, string $email, bool $active): array
    {
        try {
            if (!self::isAirflowAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Airflow não disponível'
                ];
            }

            $username = self::buildUsernameFromEmail($email, $userId);
            $airflowUrl = self::getAirflowBaseUrl() . "/api/v1/users/{$username}";
            
            // Verificar se usuário existe
            $getUserResult = self::apiCall('GET', $airflowUrl);
            if (!$getUserResult['success']) {
                return [
                    'success' => false,
                    'message' => "Usuário {$username} não encontrado no Airflow"
                ];
            }
            
            // Atualizar status active
            $updateData = ['active' => $active];
            $result = self::apiCall('PATCH', $airflowUrl, $updateData);
            
            if ($result['success']) {
                $status = $active ? 'ativado' : 'desativado';
                log_message('info', "[AIRFLOW] Usuário {$username} {$status} com sucesso");
                return [
                    'success' => true,
                    'message' => "Usuário {$status} com sucesso no Airflow"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Falha ao atualizar status do usuário: " . ($result['error'] ?? 'erro desconhecido')
                ];
            }
        } catch (\Exception $e) {
            log_message('error', "[AIRFLOW] Erro ao alterar status do usuário: {$e->getMessage()}");
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Busca um usuário no Airflow pelo email
     * 
     * @param string $email Email do usuário
     * @return array|null Retorna os dados do usuário ou null se não encontrado
     */
    private static function getUserByEmail(string $email): ?array
    {
        try {
            $airflowUrl = self::getAirflowBaseUrl() . '/api/v1/users';
            $result = self::apiCall('GET', $airflowUrl);
            
            if ($result['success'] && isset($result['response']['users'])) {
                foreach ($result['response']['users'] as $user) {
                    if (isset($user['email']) && $user['email'] === $email) {
                        log_message('debug', "[AirflowHelper] Usuário encontrado por email: " . json_encode($user));
                        return $user;
                    }
                }
            }
            
            log_message('debug', "[AirflowHelper] Nenhum usuário encontrado com email: {$email}");
            return null;
        } catch (\Exception $e) {
            log_message('error', "[AirflowHelper] Erro ao buscar usuário por email: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Chamada REST ao Airflow com basic auth (admin:admin)
     */
    private static function apiCall(string $method, string $url, array $data = []): array
    {
        try {
            log_message('debug', "[AirflowHelper] apiCall: {$method} {$url} data=" . json_encode($data));
            $ch = curl_init($url);
            $auth = base64_encode('admin:kJ#212394');

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
                log_message('info', "[AirflowHelper] Sucesso na chamada {$method} para {$url}");
                return ['success' => true, 'error' => '', 'response' => json_decode($response, true)];
            }

            $err = $curlError ?: ($response ?: 'Erro desconhecido');
            log_message('error', "[AirflowHelper] Erro na chamada {$method} para {$url}: {$err}");
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
        $roleUrl = self::getAirflowBaseUrl() . '/api/v1/roles/' . urlencode($roleName);
        $get = self::apiCall('GET', $roleUrl);
        return $get['success'];
    }

    /**
     * Obtém a lista de actions de um role existente (para clonar permissões).
     */
    private static function getRoleActions(string $roleName): array
    {
        $roleUrl = self::getAirflowBaseUrl() . '/api/v1/roles/' . urlencode($roleName);
        $resp = self::apiCall('GET', $roleUrl);
        if ($resp['success'] && !empty($resp['response']['actions'])) {
            return $resp['response']['actions'];
        }
        return [];
    }

    /**
     * Cria role via API com corpo mínimo. Retorna true em sucesso ou conflito (já existe).
     */
    private static function createRole(string $roleName): bool
    {
        $rolesUrl = self::getAirflowBaseUrl() . '/api/v1/roles';
        // API 2.9 espera a chave "actions"; se ausente gera 500 (KeyError)
        // Copiamos as actions do role "User" (permissão padrão de execução/visualização)
        $templateActions = self::getRoleActions('User');

        $payloads = [];
        if (!empty($templateActions)) {
            $payloads[] = ['name' => $roleName, 'actions' => $templateActions];
        }
        // Fallback: actions vazio
        $payloads[] = ['name' => $roleName, 'actions' => []];
        // Fallback final: apenas name
        $payloads[] = ['name' => $roleName];

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

        // Garante que a role tenha ações mínimas (clonadas de User) para executar/visualizar DAG
        self::ensureRoleHasActions($ownerRole);

        // Tenta anexar se agora existir
        if (self::roleExists($ownerRole)) {
            $attached = self::addExistingRoleToUser($username, $ownerRole);
            if (!$attached) {
                log_message('warning', "[AirflowHelper] Role {$ownerRole} não anexada ao usuário {$username}.");
            }
        }
    }

    /**
     * Se a role existir mas estiver sem actions, clona as actions do role "User" e aplica via PATCH.
     */
    private static function ensureRoleHasActions(string $roleName): void
    {
        $roleUrl = self::getAirflowBaseUrl() . '/api/v1/roles/' . urlencode($roleName);
        $current = self::apiCall('GET', $roleUrl);
        if (!$current['success']) {
            return;
        }

        $currentActions = $current['response']['actions'] ?? [];
        if (!empty($currentActions)) {
            return; // já possui permissões
        }

        $templateActions = self::getRoleActions('User');
        if (empty($templateActions)) {
            return;
        }

        $patch = self::apiCall('PATCH', $roleUrl, ['actions' => $templateActions]);
        if ($patch['success']) {
            log_message('info', "[AirflowHelper] Actions clonadas para role {$roleName} a partir de User.");
        } else {
            log_message('warning', "[AirflowHelper] Falha ao aplicar actions na role {$roleName}: {$patch['error']}");
        }
    }

    /**
     * Adiciona uma role existente ao usuário (não cria roles novas)
     */
    public static function addExistingRoleToUser(string $username, string $roleName): bool
    {
        // Busca usuário atual para não sobrescrever outras configurações
        $userUrl = self::getAirflowBaseUrl() . '/api/v1/users/' . urlencode($username);
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
