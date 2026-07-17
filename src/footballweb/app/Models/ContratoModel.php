<?php

namespace App\Models;

use CodeIgniter\Model;

class ContratoModel extends Model
{
    protected $table            = 'contrato';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['descricao', 'empresa', 'data_inicio_vigencia', 'data_fim_vigencia', 'qtd_meses_total'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        return $this->select('id, descricao')->findAll();
    }
}
