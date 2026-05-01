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
        return $this->select('perfil_funcionalidade.*, funcionalidade.descricao as funcionalidade_descricao')
                    ->join('funcionalidade', 'perfil_funcionalidade.id_funcionalidade = funcionalidade.id')
                    ->where('perfil_funcionalidade.id_perfil', $idPerfil)
                    ->orderBy('funcionalidade.descricao', 'ASC')
                    ->findAll();
    }

    /**
     * Remove todas as funcionalidades de um perfil
     */
    public function deleteFuncionalidadesPerfil($idPerfil)
    {
        return $this->where('id_perfil', $idPerfil)->delete();
    }

    /**
     * Salva múltiplas funcionalidades para um perfil
     */
    public function saveFuncionalidadesPerfil($idPerfil, $funcionalidades)
    {
        // Normaliza entrada: garante array, remove duplicados e converte para int
        if (!is_array($funcionalidades)) {
            $funcionalidades = $funcionalidades !== null ? [$funcionalidades] : [];
        }
        $funcionalidades = array_unique(array_map('intval', $funcionalidades));

        // Remove funcionalidades antigas
        $this->deleteFuncionalidadesPerfil($idPerfil);

        // Insere novas funcionalidades
        if (!empty($funcionalidades)) {
            $data = [];
            foreach ($funcionalidades as $idFuncionalidade) {
                if ($idFuncionalidade > 0) {
                    $data[] = [
                        'id_perfil' => (int) $idPerfil,
                        'id_funcionalidade' => (int) $idFuncionalidade
                    ];
                }
            }

            if (!empty($data)) {
                return (bool) $this->insertBatch($data);
            }
        }

        return true;
    }
}
