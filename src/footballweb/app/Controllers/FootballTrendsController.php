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

        // Filtro para mostrar ou ocultar jogos encerrados (default: não)
        $showFinishedParam = $this->request->getVar('show_finished');
        $showFinished = ($showFinishedParam === '1' || $showFinishedParam === 'true' || $showFinishedParam === 'sim');

        // Conecta ao banco para realizar a query com join
        $db = \Config\Database::connect();
        $builder = $db->table('fixtures_trends ft');
        $builder->select('ft.*, rs.average_yellow_cards, rs.average_red_cards, rs.average_fouls, rs.total_games, rs.rigor_level');
        $builder->join('referee_stats rs', 'ft.referee_name = rs.name', 'left');
        $builder->where('DATE(DATE_SUB(ft.fixture_date, INTERVAL 3 HOUR))', $targetDate);

        // Se showFinished for falso (default), exclui jogos encerrados
        if (!$showFinished) {
            $builder->groupStart()
                ->whereNotIn('ft.status', ['FT', 'AET', 'PEN', '120', '90'])
                ->where('DATE_ADD(ft.fixture_date, INTERVAL 120 MINUTE) >= UTC_TIMESTAMP()')
            ->groupEnd();
        }

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
            'targetDate'   => $targetDate,
            'search'       => $search,
            'showFinished' => $showFinished,
            'fixtures'     => $fixtures,
            'leagues'      => $leagues,
            'title'        => 'Football Trends - Mercado de Cartões'
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

    /**
     * Endpoint do Assistente de IA (Chatbot Groq)
     */
    public function askAi(): ResponseInterface
    {
        $homeTeam = $this->request->getPost('home_team');
        $awayTeam = $this->request->getPost('away_team');
        $leagueName = $this->request->getPost('league_name');
        $refereeName = $this->request->getPost('referee_name');
        $predictionText = $this->request->getPost('prediction_text');
        $prob = $this->request->getPost('over_cards_probability');
        $userMessage = $this->request->getPost('message');
        $historyJson = $this->request->getPost('history'); // Array JSON de histórico de mensagens
        
        if (empty($userMessage)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A mensagem do usuário não pode estar vazia.'
            ]);
        }

        $apiKey = env('VISION_API_KEY');
        if (empty($apiKey)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Chave da API de IA não configurada no servidor.'
            ]);
        }

        // Reconstrói mensagens para a API Groq
        $messages = [];

        // Prompt de Sistema detalhado com base nas orientações fornecidas
        $systemContent = "Você é o Grok, um assistente inteligente especialista em apostas esportivas e análise estatística de cartões na plataforma MyFlow Trends. "
            . "O usuário está analisando a partida: {$homeTeam} vs {$awayTeam} pela liga '{$leagueName}'.\n\n"
            . "Dados atuais do confronto:\n"
            . "- Árbitro escalado: " . ($refereeName ?: 'Sem árbitro escalado ainda') . "\n"
            . "- Análise pré-gerada: {$predictionText}\n"
            . "- Probabilidade calculada de Over 4.5 Cartões: {$prob}%\n\n"
            . "DIRETRIZES DE RESPOSTA:\n"
            . "1. Seja conversador, direto, amigável e use gírias ou jargão saudável do meio de apostas em português.\n"
            . "2. Explique ao usuário de forma pragmática como traduzir a nossa probabilidade estatística para o que ele vê na tela das casas de apostas (Betano/Superbet):\n"
            . "   - Se a liga for de 'Tier 2' ou secundária (como a Liga MX do México), explique que a Betano costuma limitar as linhas para evitar prejuízo, por isso o mercado direto de 'Total de Cartões (Mais de 4.5)' pode não aparecer.\n"
            . "   - Nesses casos, sugira mercados híbridos inteligentes como:\n"
            . "     * 'Ambas as equipes receberão 2 ou mais cartões' (se a nossa probabilidade for alta, ex: >= 70%, o jogo costuma ser truncado para os dois lados, exigindo ao menos 4 cartões no total, sendo 2 para cada, o que é um mercado seguro);\n"
            . "     * 'Total de Cartões por Equipe' (ex: apostar individualmente que determinado time terá Mais de 1.5 ou 2.5 cartões);\n"
            . "     * 'Total de Cartões no 1º Tempo' ou cartões vermelhos.\n"
            . "   - Destaque que em grandes ligas (Brasileirão Série A, Champions League, ligas europeias principais), a Betano sempre abre o mercado padrão de 'Total de Cartões'.\n"
            . "3. Use a nossa dica pré-gerada e o rigor do árbitro para fundamentar a sua resposta técnica. Responda de forma concisa e evite textos excessivamente longos.";

        $messages[] = ['role' => 'system', 'content' => $systemContent];

        // Adiciona histórico de chat se existir
        if (!empty($historyJson)) {
            $history = json_decode($historyJson, true);
            if (is_array($history)) {
                foreach ($history as $msg) {
                    if (isset($msg['role']) && isset($msg['content'])) {
                        $messages[] = [
                            'role' => $msg['role'],
                            'content' => $msg['content']
                        ];
                    }
                }
            }
        }

        // Adiciona mensagem atual
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $client = \Config\Services::curlrequest();
            $apiUrl = env('VISION_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions';
            $model = env('TEXT_API_MODEL') ?: 'llama-3.3-70b-versatile';

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => $model,
                    'messages' => $messages,
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "Erro na chamada da API de IA (HTTP {$statusCode})."
                ]);
            }

            $body = json_decode($response->getBody(), true);
            $aiResponse = $body['choices'][0]['message']['content'] ?? '';

            if (empty($aiResponse)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Nenhuma resposta retornada pela IA.'
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'response' => $aiResponse
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro interno ao se comunicar com o Groq: ' . $e->getMessage()
            ]);
        }
    }
}
