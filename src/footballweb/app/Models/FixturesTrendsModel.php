<?php

namespace App\Models;

use CodeIgniter\Model;

class FixturesTrendsModel extends Model
{
    protected $table            = 'fixtures_trends';
    protected $primaryKey       = 'fixture_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $allowedFields    = [
        'fixture_id',
        'fixture_date',
        'league_id',
        'league_name',
        'home_team',
        'away_team',
        'referee_name',
        'prediction_text',
        'over_cards_probability',
        'status'
    ];
}
