<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemOsModel extends Model
{
    protected $table            = 'item_os';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['quantidade_horas', 'profissional_alocado'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, quantidade_horas')->findAll();
        return $data;
    }
}
