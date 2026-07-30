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
    protected $allowedFields    = ['gestor_substituto', 'Numero_Contrato', 'Objeto', 'Total_Horas_Contratadas', 'Saldo_Horas', 'Data_Inicio', 'Data_Fim', 'id_contrato', 'id_metrica'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, Objeto as descricao')->findAll();
        return $data;
    }
}
