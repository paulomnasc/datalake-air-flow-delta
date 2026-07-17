<?php

namespace App\Models;

use CodeIgniter\Model;

class PerfilFuncionalidadeModel extends Model
{
    protected $table            = 'perfil_funcionalidade';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_perfil', 'id_funcionalidade'];

    // A tabela perfil_funcionalidade não existe no fiscalweb atual.
    // Os métodos abaixo foram convertidos em fallbacks para evitar exceções.

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
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
     * Retorna funcionalidades de um perfil específico com detalhes
     */
    public function getFuncionalidadesPerfil($idPerfil)
    {
        // A tabela não existe no fiscalweb. Retornamos vazio para evitar queries.
        return [];
    }

    /**
     * Remove todas as funcionalidades de um perfil
     */
    public function deleteFuncionalidadesPerfil($idPerfil)
    {
        // Operação no model desativada porque a entidade não existe.
        return true;
    }

    /**
     * Salva múltiplas funcionalidades para um perfil
     */
    public function saveFuncionalidadesPerfil($idPerfil, $funcionalidades)
    {
        // Operação desativada: sem tabela de associação, não há persistência.
        return true;
    }
}
