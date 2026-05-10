<?php

namespace App\Models;

use CodeIgniter\Model;

class StatusRecebimentoModel extends Model
{
    protected $table            = 'status_recebimento';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['descricao'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, descricao')->findAll();
        return $data;
    }
}
