<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemContratoModel extends Model
{
    protected $table            = 'item_contrato';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_catalogo_servicos', 'gestor_titular', 'gestor_substituto', 'numero_contrato', 'objeto', 'total_horas_contratadas', 'saldo_horas', 'data_inicio', 'data_fim'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, gestor_titular')->findAll();
        return $data;
    }
}
