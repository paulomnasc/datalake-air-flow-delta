<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemDocumentoRecebimentoModel extends Model
{
    protected $table            = 'item_documento_recebimento';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_documento_recebimento', 
        'id_item_os', 
        'quantidade_entregue', 
        'glosa_horas', 
        'observacoes'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
}
