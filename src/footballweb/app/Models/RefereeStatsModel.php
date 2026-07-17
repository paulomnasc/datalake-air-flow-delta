<?php

namespace App\Models;

use CodeIgniter\Model;

class RefereeStatsModel extends Model
{
    protected $table            = 'referee_stats';
    protected $primaryKey       = 'name';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'name',
        'average_yellow_cards',
        'average_red_cards',
        'average_fouls',
        'total_games',
        'rigor_level'
    ];
}
