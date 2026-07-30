<?php

namespace App\Models;

use CodeIgniter\Model;

class ParecerHomologacaoModel extends Model
{
    protected $table            = 'agile_pareceres_homologacao';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_demanda',
        'id_usuario_po',
        'parecer',
        'observacoes'
    ];

    // Dates
    protected $useTimestamps = false; // Apenas criado_em automático no DDL
}
