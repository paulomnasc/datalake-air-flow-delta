<?php

namespace App\Models;

use CodeIgniter\Model;

class SprintModel extends Model
{
    protected $table            = 'agile_sprints';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_demanda',
        'meta',
        'data_inicio',
        'data_fim',
        'status'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
}
