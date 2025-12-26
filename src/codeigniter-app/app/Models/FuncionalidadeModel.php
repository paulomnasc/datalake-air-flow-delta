<?php

namespace App\Models;

use CodeIgniter\Model;

class FuncionalidadeModel extends Model
{
    protected $table            = 'funcionalidade';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['descricao'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
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
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Retorna todas as funcionalidades formatadas para combo/select
     */
    public function listToCombo()
    {
        return $this->select('id, descricao')->orderBy('descricao', 'ASC')->findAll();
    }

    /**
     * Retorna funcionalidades de um perfil específico
     */
    public function getFuncionalidadesPerfil($idPerfil)
    {
        return $this->select('funcionalidade.*')
                    ->join('perfil_funcionalidade', 'perfil_funcionalidade.id_funcionalidade = funcionalidade.id')
                    ->where('perfil_funcionalidade.id_perfil', $idPerfil)
                    ->orderBy('funcionalidade.descricao', 'ASC')
                    ->findAll();
    }
}
