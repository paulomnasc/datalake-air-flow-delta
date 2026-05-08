<?php

namespace App\Models;

use CodeIgniter\Model;

class AreaAtuacaoModel extends Model
{
    protected $table            = 'area_atuacao';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_catalogo_servicos', 'descricao'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, descricao')->findAll();
        return $data;
    }
}
