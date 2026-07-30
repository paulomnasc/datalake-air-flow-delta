<?php

namespace App\Models;

use CodeIgniter\Model;

class OsStatusRecebimentoModel extends Model
{
    protected $table            = 'os_status_recebimento';
    protected $primaryKey       = 'id_os';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_os', 'id_status_recebimento'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id_os, id_os')->findAll();
        return $data;
    }

    public function getAssocByTarget($target_id, $target_col)
    {
        return $this->where($target_col, $target_id)->findAll();
    }
}
