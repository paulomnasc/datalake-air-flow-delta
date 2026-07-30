<?php

namespace App\Models;

use CodeIgniter\Model;

class ReleaseModel extends Model
{
    protected $table            = 'agile_releases';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_demanda',
        'ticket_rdm',
        'metadados'
    ];

    // Dates
    protected $useTimestamps = false; // Apenas criado_em no DDL
}
