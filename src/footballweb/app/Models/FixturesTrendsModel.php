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
        'home_team_id',
        'away_team_id',
        'referee_name',
        'prediction_text',
        'over_cards_probability',
        'status',
        'goals_home',
        'goals_away',
        'elapsed',
        'futbol24_tip',
        'futbol24_analysis',
        'futbol24_url',
        'home_rank',
        'away_rank',
        'home_ppg',
        'away_ppg',
        'home_zone',
        'away_zone',
        'standings_motivation_score'
    ];
}
