<?php

namespace App\Models;

use CodeIgniter\Model;

class CatalogoServicosModel extends Model
{
    protected $table            = 'catalogo_servicos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_item_contrato', 'descricao'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, descricao')->findAll();
        return $data;
    }
}
