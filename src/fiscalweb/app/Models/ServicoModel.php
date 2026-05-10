<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicoModel extends Model
{
    protected $table            = 'servico';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['numero_item', 'descricao', 'entregaveis', 'remuneracao', 'base_horas_mes', 'base_horas_complexidade', 'sla_dias', 'estim_max_ano', 'id_atividade_macro'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, descricao')->findAll();
        return $data;
    }
}
