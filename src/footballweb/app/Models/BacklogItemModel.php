<?php

namespace App\Models;

use CodeIgniter\Model;

class BacklogItemModel extends Model
{
    protected $table            = 'agile_backlog_itens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_demanda',
        'titulo',
        'criterios_aceite',
        'pontuacao',
        'ordem',
        'status_kanban'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
}
