<?php

namespace App\Models;

use CodeIgniter\Model;

class AvaliacaoQualidadeSlaModel extends Model
{
    protected $table            = 'avaliacao_qualidade_sla';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_documento_recebimento', 'nota_ins1_pontualidade', 'nota_ins2_qualidade', 'percentual_glosa'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, nota_ins1_pontualidade')->findAll();
        return $data;
    }
}
