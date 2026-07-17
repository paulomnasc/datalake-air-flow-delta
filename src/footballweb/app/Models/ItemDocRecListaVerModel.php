<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemDocRecListaVerModel extends Model
{
    protected $table            = 'item_doc_rec_lista_ver';
    protected $primaryKey       = 'id_lista_verificacao'; // Composite key, but we define one to satisfy CI4 requirement
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_lista_verificacao',
        'id_item_doc_origem',
        'conforme'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;
}
