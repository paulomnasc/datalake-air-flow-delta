<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentoRecebimentoModel extends Model
{
    protected $table            = 'documento_recebimento';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_os', 'data_assinatura', 'nup_sei', 'id_tipo_documento', 'id_usuario_fiscal_tecnico', 'id_usuario_fiscal_requisitante', 'id_usuario_gestor'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, data_assinatura')->findAll();
        return $data;
    }
}
