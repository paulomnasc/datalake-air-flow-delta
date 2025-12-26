<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioPerfilModel extends Model
{
    protected $table            = 'usuario_perfil';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_usuario', 'id_perfil'];

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
     * Retorna os perfis de um usuário específico
     */
    public function getPerfisUsuario($idUsuario)
    {
        return $this->select('usuario_perfil.*, perfil.descricao as perfil_descricao')
                    ->join('perfil', 'usuario_perfil.id_perfil = perfil.id')
                    ->where('usuario_perfil.id_usuario', $idUsuario)
                    ->findAll();
    }

    /**
     * Remove todos os perfis de um usuário
     */
    public function deletePerfisUsuario($idUsuario)
    {
        return $this->where('id_usuario', $idUsuario)->delete();
    }

    /**
     * Salva múltiplos perfis para um usuário
     */
    public function savePerfisUsuario($idUsuario, $perfis)
    {
        // Remove perfis antigos
        $this->deletePerfisUsuario($idUsuario);

        // Insere novos perfis
        $data = [];
        foreach ($perfis as $idPerfil) {
            $data[] = [
                'id_usuario' => $idUsuario,
                'id_perfil' => $idPerfil
            ];
        }

        if (!empty($data)) {
            return $this->insertBatch($data);
        }

        return true;
    }
}
