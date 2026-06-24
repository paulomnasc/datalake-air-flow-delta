<?php

namespace App\Models;

use CodeIgniter\Model;

class ListaVerificacaoModel extends Model
{
    protected $table            = 'lista_verificacao';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'descricao'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        return $this->select('id, descricao')->findAll();
    }
}
