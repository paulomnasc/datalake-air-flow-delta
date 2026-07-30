<?php

namespace App\Models;

use CodeIgniter\Model;

class ReajusteItemContratoModel extends Model
{
    protected $table            = 'reajuste_item_contrato';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_item_contrato', 'data_reajuste_item_contrato', 'valor_item_contrato'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
}
