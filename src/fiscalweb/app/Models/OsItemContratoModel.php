<?php

namespace App\Models;

use CodeIgniter\Model;

class OsItemContratoModel extends Model
{
    protected $table            = 'os_item_contrato';
    protected $primaryKey       = 'id_item_contrato';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_item_contrato', 'id_os'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id_item_contrato, id_item_contrato')->findAll();
        return $data;
    }

    public function getAssocByTarget($target_id, $target_col)
    {
        return $this->where($target_col, $target_id)->findAll();
    }
}
