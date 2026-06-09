<?php

namespace App\Helpers;

use Config\Services;

class MetabaseHelper
{
    private $apiUrl;
    private $siteUrl;
    private $adminUser;
    private $adminPassword;
    private $jwtSecret;

    public function __construct()
    {
        $this->apiUrl = rtrim(getenv('METABASE_API_URL') ?: 'http://metabase:3000', '/');
        $this->siteUrl = rtrim(getenv('METABASE_SITE_URL') ?: 'http://myflow.estudotabela.com.br:28300', '/');
        $this->adminUser = getenv('METABASE_ADMIN_USER') ?: 'admin@estudotabela.com.br';
        $this->adminPassword = getenv('METABASE_ADMIN_PASSWORD') ?: 'kJ#212394';
        $this->jwtSecret = getenv('METABASE_JWT_SHARED_SECRET') ?: 'myflow_metabase_secret_key_sso_123456';
    }

    /**
     * Efetua uma requisição HTTP cURL para a API do Metabase
     */
    private function request(string $method, string $path, ?array $payload = null, ?string $sessionToken = null)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if ($sessionToken) {
            $headers['X-Metabase-Session'] = $sessionToken;
        }

        $client = Services::curlrequest([
            'http_errors' => false,
            'timeout'     => 10,
        ]);

        $options = [
            'headers' => $headers,
        ];

        if ($payload !== null) {
            $options['json'] = $payload;
        }

        return $client->request($method, $this->apiUrl . $path, $options);
    }

    /**
     * Efetua login na API do Metabase e retorna o Token de Sessão
     */
    public function authenticate(): ?string
    {
        $response = $this->request('POST', '/api/session', [
            'username' => $this->adminUser,
            'password' => $this->adminPassword,
        ]);

        $statusCode = $response->getStatusCode();
        $body = json_decode($response->getBody(), true);

        if ($statusCode === 200 && isset($body['id'])) {
            return $body['id'];
        }

        log_message('error', "MetabaseHelper: Falha na autenticação (Código {$statusCode}). Resposta: " . json_encode($body));
        return null;
    }

    /**
     * Provisiona todo o ecossistema do inquilino (database, usuário, grupo, permissões)
     */
    public function provisionTenant(int $userId, string $email, string $name): bool
    {
        log_message('info', "MetabaseHelper: Iniciando provisionamento para o usuário {$userId} ({$email}).");

        $sessionToken = $this->authenticate();
        if (!$sessionToken) {
            log_message('error', "MetabaseHelper: Cancelando provisionamento por falta de autenticação.");
            return false;
        }

        // 1. Criar/atualizar a conexão de banco de dados restrita
        $dbId = $this->getOrCreateDatabase($sessionToken, $userId);
        if (!$dbId) {
            return false;
        }

        // 2. Criar/ativar o usuário no Metabase
        $metabaseUserId = $this->getOrCreateUser($sessionToken, $email, $name);
        if (!$metabaseUserId) {
            return false;
        }

        // 3. Criar/obter o grupo de segurança exclusivo
        $groupId = $this->getOrCreateGroup($sessionToken, $userId);
        if (!$groupId) {
            return false;
        }

        // 4. Associar o usuário ao grupo
        $this->addUserToGroup($sessionToken, $groupId, $metabaseUserId);

        // 5. Configurar o Grafo de Permissões:
        //    - Dar acesso total ao grupo do aluno para esta DB
        //    - Bloquear acesso do grupo "All Users" (ID 1) para esta DB
        $this->configurePermissions($sessionToken, $dbId, $groupId);

        log_message('info', "MetabaseHelper: Provisionamento concluído com sucesso para o usuário {$userId}.");
        return true;
    }

    /**
     * Busca ou cria a conexão da base de dados do inquilino no Metabase
     */
    private function getOrCreateDatabase(string $sessionToken, int $userId): ?int
    {
        $dbName = "datalake_bi_user_{$userId}";

        // Buscar bancos existentes
        $response = $this->request('GET', '/api/database', null, $sessionToken);
        if ($response->getStatusCode() === 200) {
            $databases = json_decode($response->getBody(), true);
            $dbList = $databases['data'] ?? $databases;
            if (is_array($dbList)) {
                foreach ($dbList as $db) {
                    if (isset($db['name']) && $db['name'] === $dbName) {
                        log_message('info', "MetabaseHelper: Banco de dados {$dbName} já cadastrado (ID {$db['id']}).");
                        return (int) $db['id'];
                    }
                }
            }
        }

        // Se não existir, criar novo
        $dbSalt = $this->jwtSecret;
        $dbPassword = 'pwd_' . substr(hash_hmac('sha256', (string)$userId, $dbSalt), 0, 16);

        log_message('info', "MetabaseHelper: Cadastrando nova conexão de banco no Metabase: {$dbName}.");
        
        $payload = [
            'name'    => $dbName,
            'engine'  => 'postgres',
            'details' => [
                'host'     => 'postgres-bi',
                'port'     => 5432,
                'dbname'   => 'datalake_bi',
                'user'     => "user_aluno_{$userId}",
                'password' => $dbPassword,
                'ssl'      => false,
            ]
        ];

        $response = $this->request('POST', '/api/database', $payload, $sessionToken);
        $statusCode = $response->getStatusCode();
        $rawBody = $response->getBody();
        $body = json_decode($rawBody, true);

        if ($statusCode === 200 && isset($body['id'])) {
            return (int) $body['id'];
        }

        log_message('error', "MetabaseHelper: Erro ao cadastrar banco de dados (Código {$statusCode}). Resposta: " . $rawBody);
        return null;
    }

    /**
     * Busca ou cria o usuário no Metabase
     */
    private function getOrCreateUser(string $sessionToken, string $email, string $name): ?int
    {
        // Buscar usuários
        $response = $this->request('GET', '/api/user', null, $sessionToken);
        if ($response->getStatusCode() === 200) {
            $users = json_decode($response->getBody(), true);
            $userList = $users['data'] ?? $users;
            foreach ($userList as $u) {
                if (strtolower($u['email']) === strtolower($email)) {
                    log_message('info', "MetabaseHelper: Usuário {$email} já existe no Metabase (ID {$u['id']}).");
                    
                    // Se o usuário estiver inativo, reativa-o
                    if (isset($u['is_active']) && !$u['is_active']) {
                        $this->request('PUT', "/api/user/{$u['id']}", ['is_active' => true], $sessionToken);
                        log_message('info', "MetabaseHelper: Usuário ID {$u['id']} reativado.");
                    }
                    
                    return (int) $u['id'];
                }
            }
        }

        // Criar usuário se não existir
        log_message('info', "MetabaseHelper: Criando novo usuário no Metabase: {$email}.");
        $parts = explode(' ', trim($name));
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts) ?: 'Sobrenome';

        $payload = [
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'password'   => bin2hex(random_bytes(10)) . 'A1!', // senha inicial randômica e forte
        ];

        $response = $this->request('POST', '/api/user', $payload, $sessionToken);
        $body = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200 && isset($body['id'])) {
            return (int) $body['id'];
        }

        log_message('error', "MetabaseHelper: Erro ao criar usuário {$email}: " . json_encode($body));
        return null;
    }

    /**
     * Busca ou cria o grupo de segurança exclusivo
     */
    private function getOrCreateGroup(string $sessionToken, int $userId): ?int
    {
        $groupName = "group_aluno_{$userId}";

        $response = $this->request('GET', '/api/permissions/group', null, $sessionToken);
        if ($response->getStatusCode() === 200) {
            $groups = json_decode($response->getBody(), true);
            foreach ($groups as $g) {
                if ($g['name'] === $groupName) {
                    log_message('info', "MetabaseHelper: Grupo {$groupName} já existe (ID {$g['id']}).");
                    return (int) $g['id'];
                }
            }
        }

        log_message('info', "MetabaseHelper: Criando novo grupo de segurança {$groupName}.");
        $response = $this->request('POST', '/api/permissions/group', ['name' => $groupName], $sessionToken);
        $body = json_decode($response->getBody(), true);

        if ($response->getStatusCode() === 200 && isset($body['id'])) {
            return (int) $body['id'];
        }

        log_message('error', "MetabaseHelper: Erro ao criar grupo de segurança: " . json_encode($body));
        return null;
    }

    /**
     * Associa o usuário ao grupo de segurança correspondente
     */
    private function addUserToGroup(string $sessionToken, int $groupId, int $metabaseUserId): bool
    {
        $payload = [
            'group_id' => $groupId,
            'user_id'  => $metabaseUserId,
        ];

        $response = $this->request('POST', '/api/permissions/membership', $payload, $sessionToken);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            log_message('info', "MetabaseHelper: Usuário {$metabaseUserId} adicionado ao grupo {$groupId} com sucesso.");
            return true;
        }

        log_message('debug', "MetabaseHelper: Membership já existe ou ocorreu aviso (Status {$statusCode}).");
        return false;
    }

    /**
     * Configura o grafo de permissões no Metabase para isolar a base de dados
     */
    private function configurePermissions(string $sessionToken, int $dbId, int $groupId): bool
    {
        // 1. Obter Grafo de Permissões Atual
        $response = $this->request('GET', '/api/permissions/graph', null, $sessionToken);
        if ($response->getStatusCode() !== 200) {
            log_message('error', "MetabaseHelper: Falha ao carregar grafo de permissões.");
            return false;
        }

        $graph = json_decode($response->getBody(), true);
        $revision = $graph['revision'] ?? 1;

        // 2. Modificar o Grafo de Permissões:
        //    - Grupo 1 ("All Users") -> Sem Acesso a essa DB
        $graph['groups']['1'][(string) $dbId] = [
            'data' => [
                'schemas' => 'none',
                'native'  => 'none'
            ]
        ];

        //    - Grupo do Aluno -> Acesso Total de Leitura/Escrita nessa DB
        $graph['groups'][(string) $groupId][(string) $dbId] = [
            'data' => [
                'schemas' => 'all',
                'native'  => 'write'
            ]
        ];

        // 3. Atualizar o Grafo na API
        $payload = [
            'revision' => $revision,
            'groups'   => $graph['groups']
        ];

        $response = $this->request('PUT', '/api/permissions/graph', $payload, $sessionToken);
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            log_message('info', "MetabaseHelper: Grafo de permissões atualizado com sucesso para DB {$dbId} e Grupo {$groupId}.");
            return true;
        }

        log_message('error', "MetabaseHelper: Erro ao salvar grafo de permissões (Status {$statusCode}). Resposta: " . $response->getBody());
        return false;
    }

    /**
     * Desativa a conta do usuário no Metabase (por exemplo, após cancelamento de assinatura)
     */
    public function deactivateUser(string $email): bool
    {
        $sessionToken = $this->authenticate();
        if (!$sessionToken) {
            return false;
        }

        // Buscar usuário pelo e-mail
        $response = $this->request('GET', '/api/user', null, $sessionToken);
        if ($response->getStatusCode() === 200) {
            $users = json_decode($response->getBody(), true);
            $userList = $users['data'] ?? $users;
            foreach ($userList as $u) {
                if (strtolower($u['email']) === strtolower($email)) {
                    // Desativar usuário
                    $res = $this->request('PUT', "/api/user/{$u['id']}", ['is_active' => false], $sessionToken);
                    if ($res->getStatusCode() === 200) {
                        log_message('info', "MetabaseHelper: Usuário {$email} (ID {$u['id']}) foi desativado no Metabase.");
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Reativa a conta do usuário no Metabase
     */
    public function reactivateUser(string $email): bool
    {
        $sessionToken = $this->authenticate();
        if (!$sessionToken) {
            return false;
        }

        $response = $this->request('GET', '/api/user', null, $sessionToken);
        if ($response->getStatusCode() === 200) {
            $users = json_decode($response->getBody(), true);
            $userList = $users['data'] ?? $users;
            foreach ($userList as $u) {
                if (strtolower($u['email']) === strtolower($email)) {
                    $res = $this->request('PUT', "/api/user/{$u['id']}", ['is_active' => true], $sessionToken);
                    if ($res->getStatusCode() === 200) {
                        log_message('info', "MetabaseHelper: Usuário {$email} (ID {$u['id']}) foi reativado no Metabase.");
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Gera a URL do Single Sign-On (SSO) com JWT
     */
    public function generateSSOLink(string $email, string $name): string
    {
        $parts = explode(' ', trim($name));
        $firstName = array_shift($parts);
        $lastName = implode(' ', $parts) ?: 'Sobrenome';

        $payload = [
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'exp'        => time() + 300, // Token expira em 5 minutos
        ];

        $jwt = $this->signJWT($payload, $this->jwtSecret);

        return $this->siteUrl . "/auth/sso?jwt=" . $jwt;
    }

    /**
     * Assina o payload no formato HS256 JWT
     */
    private function signJWT(array $payload, string $secret): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
