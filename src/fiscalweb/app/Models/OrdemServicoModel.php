<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdemServicoModel extends Model
{
    protected $table            = 'ordem_servico';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['horas_alocadas', 'nup_sei', 'data_emissao', 'data_aceite', 'data_vencimento', 'realizada_estimativa', 'metodologia_estimativa', 'status', 'nota_empenho', 'id_contrato', 'id_sistema'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, nup_sei as descricao')->findAll();
        return $data;
    }
}
