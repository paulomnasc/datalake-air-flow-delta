<?php

namespace App\Models;

use CodeIgniter\Model;

class CerimoniaModel extends Model
{
    protected $table            = 'agile_cerimonias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id_demanda',
        'tipo_cerimonia',
        'data_hora_agendada',
        'data_hora_realizada',
        'participantes_presentes',
        'ata_descritiva',
        'link_gravacao'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'criado_em';
    protected $updatedField  = 'atualizado_em';
}
