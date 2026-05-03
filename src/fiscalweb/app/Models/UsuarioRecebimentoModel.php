<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioRecebimentoModel extends Model
{
    protected $table            = 'usuario_recebimento';
    protected $primaryKey       = 'id_recebimento';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_recebimento', 'id_usuario'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id_recebimento, id_recebimento')->findAll();
        return $data;
    }

    public function getAssocByTarget($target_id, $target_col)
    {
        return $this->where($target_col, $target_id)->findAll();
    }
}
