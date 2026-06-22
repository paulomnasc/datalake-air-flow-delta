<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table            = 'usuario';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    //protected $returnType       = 'array';
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'nome',
        'email',
        'senha',
        'email_confirmado',
        'data_ultimo_pagamento',
        'data_vencimento_assinatura',
        'status_assinatura',
        'data_inicio_trial',
        'google_id',
        'google_token',
        'google_refresh_token',
        'auth_provider',
        'auth_updated_at',
           'pagamento_inicial',
    ];
    

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = ['handleStatusChange'];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Monitora alterações no status da assinatura para suspender/reativar acesso
     */
    protected function handleStatusChange(array $data)
    {
        if (isset($data['data']['status_assinatura'])) {
            $status = $data['data']['status_assinatura'];
            $userIds = (array) $data['id'];

            foreach ($userIds as $userId) {
                if (in_array($status, ['expired', 'cancelled'])) {
                    $this->suspendTenant($userId);
                } else {
                    $this->reactivateTenant($userId);
                }
            }
        }
        return $data;
    }

    /**
     * Bloqueia login no PostgreSQL e desativa no Metabase
     */
    private function suspendTenant(int $userId)
    {
        try {
            $dsn = "pgsql:host=postgres-bi;port=5432;dbname=datalake_bi";
            $pdo = new \PDO($dsn, 'pbi_user', 'pbi_password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            
            // Verifica se a role existe antes de rodar ALTER ROLE
            $stmt = $pdo->prepare("SELECT 1 FROM pg_roles WHERE rolname = :role");
            $stmt->execute(['role' => "user_aluno_{$userId}"]);
            if ($stmt->fetch()) {
                $pdo->exec("ALTER ROLE user_aluno_{$userId} NOLOGIN;");
                log_message('info', "UsuarioModel: Acesso PostgreSQL do aluno {$userId} suspenso (NOLOGIN).");
            }
        } catch (\Exception $e) {
            log_message('warning', "UsuarioModel: Erro ao suspender login PostgreSQL do aluno {$userId}: " . $e->getMessage());
        }

        try {
            $user = $this->find($userId);
            if ($user && !empty($user->email)) {
                $metabaseHelper = new \App\Helpers\MetabaseHelper();
                $metabaseHelper->deactivateUser($user->email);
            }
        } catch (\Exception $e) {
            log_message('error', "UsuarioModel: Erro ao desativar usuário {$userId} no Metabase: " . $e->getMessage());
        }
    }

    /**
     * Libera login no PostgreSQL e reativa no Metabase
     */
    private function reactivateTenant(int $userId)
    {
        try {
            $dsn = "pgsql:host=postgres-bi;port=5432;dbname=datalake_bi";
            $pdo = new \PDO($dsn, 'pbi_user', 'pbi_password', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            
            // Verifica se a role existe antes de rodar ALTER ROLE
            $stmt = $pdo->prepare("SELECT 1 FROM pg_roles WHERE rolname = :role");
            $stmt->execute(['role' => "user_aluno_{$userId}"]);
            if ($stmt->fetch()) {
                $pdo->exec("ALTER ROLE user_aluno_{$userId} LOGIN;");
                log_message('info', "UsuarioModel: Acesso PostgreSQL do aluno {$userId} reativado (LOGIN).");
            }
        } catch (\Exception $e) {
            log_message('warning', "UsuarioModel: Erro ao reativar login PostgreSQL do aluno {$userId}: " . $e->getMessage());
        }

        try {
            $user = $this->find($userId);
            if ($user && !empty($user->email)) {
                $metabaseHelper = new \App\Helpers\MetabaseHelper();
                $metabaseHelper->reactivateUser($user->email);
            }
        } catch (\Exception $e) {
            log_message('error', "UsuarioModel: Erro ao reativar usuário {$userId} no Metabase: " . $e->getMessage());
        }
    }


    public function listToCombo()
    {
        //$this->select('id, nome');
        return $this->select('id, nome')->findAll();
        
        return  $data;
    }

}
