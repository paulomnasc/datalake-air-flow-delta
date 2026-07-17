<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\FixturesTrendsModel;
use App\Models\RefereeStatsModel;
use App\Helpers\AirflowHelper;
use CodeIgniter\HTTP\ResponseInterface;

class FootballTrendsController extends BaseController
{
    protected $fixturesTrendsModel;
    protected $refereeStatsModel;

    public function __construct()
    {
        $this->fixturesTrendsModel = new FixturesTrendsModel();
        $this->refereeStatsModel = new RefereeStatsModel();
    }

    public function index()
    {
        // Define timezone para America/Sao_Paulo
        date_default_timezone_set('America/Sao_Paulo');

        // Recebe a data de filtro (default: hoje)
        $targetDate = $this->request->getVar('date');
        if (empty($targetDate)) {
            $targetDate = date('Y-m-d');
        }

        // Filtro de busca por time ou árbitro
        $search = $this->request->getVar('search');

        // Conecta ao banco para realizar a query com join
        $db = \Config\Database::connect();
        $builder = $db->table('fixtures_trends ft');
        $builder->select('ft.*, rs.average_yellow_cards, rs.average_red_cards, rs.average_fouls, rs.total_games, rs.rigor_level');
        $builder->join('referee_stats rs', 'ft.referee_name = rs.name', 'left');
        $builder->where('DATE(ft.fixture_date)', $targetDate);

        if (!empty($search)) {
            $builder->groupStart()
                ->like('ft.home_team', $search)
                ->orLike('ft.away_team', $search)
                ->orLike('ft.referee_name', $search)
                ->orLike('ft.league_name', $search)
            ->groupEnd();
        }

        $builder->orderBy('ft.fixture_date', 'ASC');
        $fixtures = $builder->get()->getResultObject();

        // Extrai ligas únicas para filtro em abas na View
        $leagues = [];
        foreach ($fixtures as $fix) {
            if (!empty($fix->league_name) && !in_array($fix->league_name, $leagues)) {
                $leagues[] = $fix->league_name;
            }
        }

        // Prepara dados para a view
        $data = [
            'targetDate' => $targetDate,
            'search'     => $search,
            'fixtures'   => $fixtures,
            'leagues'    => $leagues,
            'title'      => 'Football Trends - Mercado de Cartões'
        ];

        return $this->loadView('football/dashboard', $data);
    }

    /**
     * Aciona o trigger de ingestão de dados via API do Airflow
     */
    public function triggerIngest(): ResponseInterface
    {
        $targetDate = $this->request->getPost('date');
        if (empty($targetDate)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data inválida para a operação.'
            ]);
        }

        // Triga a DAG no Airflow com a data alvo nas configurações
        $result = AirflowHelper::triggerDag('football_trends_ingestion_dag', [
            'target_date' => $targetDate
        ]);

        return $this->response->setJSON($result);
    }
}
