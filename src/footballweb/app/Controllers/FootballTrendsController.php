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

    /**
     * Obtém e valida o fuso horário ativo para a requisição/sessão do usuário
     */
    protected function getUserTimezone(): string
    {
        $tz = $_SESSION['user_timezone']
            ?? $this->request->getCookie('user_timezone')
            ?? 'America/Sao_Paulo';

        if (!in_array($tz, \DateTimeZone::listIdentifiers())) {
            $tz = 'America/Sao_Paulo';
        }

        return $tz;
    }

    /**
     * Retorna o offset UTC formatado (ex: '-03:00', '+01:00') para uso em queries SQL CONVERT_TZ
     */
    protected function getTimezoneSqlOffset(string $timezone): string
    {
        $dtZone = new \DateTimeZone($timezone);
        $dtNow = new \DateTime('now', $dtZone);
        $offsetSeconds = $dtZone->getOffset($dtNow);
        $hours = intdiv($offsetSeconds, 3600);
        $minutes = abs($offsetSeconds % 3600) / 60;
        return sprintf('%+03d:%02d', $hours, $minutes);
    }

    public function index()
    {
        // Define timezone dinâmico da sessão/usuário (default America/Sao_Paulo)
        $userTimezone = $this->getUserTimezone();
        date_default_timezone_set($userTimezone);
        $sqlOffset = $this->getTimezoneSqlOffset($userTimezone);

        // Recebe as datas de filtro (suporta data única 'date' ou intervalo 'start_date' & 'end_date')
        $today = date('Y-m-d');
        $startDate = $this->request->getVar('start_date');
        $endDate = $this->request->getVar('end_date');
        $targetDate = $this->request->getVar('date');

        if (empty($startDate) && empty($endDate)) {
            $startDate = !empty($targetDate) ? $targetDate : $today;
            $endDate = $startDate;
        } elseif (empty($startDate)) {
            $startDate = $endDate;
        } elseif (empty($endDate)) {
            $endDate = $startDate;
        }

        if ($startDate > $endDate) {
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
        }
        $targetDate = $startDate;

        // Filtro de busca por time ou árbitro
        $search = $this->request->getVar('search');

        // Filtro para mostrar ou ocultar jogos encerrados
        $showFinishedParam = $this->request->getVar('show_finished');
        if ($showFinishedParam === null) {
            $showFinished = ($startDate < $today);
        } else {
            $showFinished = ($showFinishedParam === '1' || $showFinishedParam === 'true' || $showFinishedParam === 'sim');
        }

        // Filtro para mostrar ou ocultar jogos adiados (PST) - Padrão: Não (false)
        $showPostponedParam = $this->request->getVar('show_postponed');
        $showPostponed = ($showPostponedParam === '1' || $showPostponedParam === 'true' || $showPostponedParam === 'sim');

        // Filtro para exibir apenas apostas seguras (Under com alta confiança)
        $onlySafeParam = $this->request->getVar('only_safe');
        $onlySafe = ($onlySafeParam === '1' || $onlySafeParam === 'true' || $onlySafeParam === 'sim');

        // Filtro para exibir apenas Surebets (oportunidades de arbitragem)
        $onlySurebetParam = $this->request->getVar('only_surebet');
        $onlySurebet = ($onlySurebetParam === '1' || $onlySurebetParam === 'true' || $onlySurebetParam === 'sim');

        // Conecta ao banco para realizar a query com join
        $db = \Config\Database::connect();
        $builder = $db->table('fixtures_trends ft');
        $builder->select('ft.*, rs.average_yellow_cards, rs.average_red_cards, rs.average_fouls, rs.total_games, rs.rigor_level,
                          th.avg_goals_scored as home_avg_goals_scored, th.avg_goals_conceded as home_avg_goals_conceded, th.clean_sheets_pct as home_clean_sheets_pct, th.avg_corners as home_avg_corners, th.avg_cards as home_avg_cards,
                          ta.avg_goals_scored as away_avg_goals_scored, ta.avg_goals_conceded as away_avg_goals_conceded, ta.clean_sheets_pct as away_clean_sheets_pct, ta.avg_corners as away_avg_corners, ta.avg_cards as away_avg_cards');
        $builder->join('referee_stats rs', 'ft.referee_name = rs.name', 'left');
        $builder->join('team_moving_averages th', 'ft.home_team_id = th.team_id AND th.venue_type = "home"', 'left');
        $builder->join('team_moving_averages ta', 'ft.away_team_id = ta.team_id AND ta.venue_type = "away"', 'left');
        
        if ($startDate === $endDate) {
            $builder->where("DATE(CONVERT_TZ(ft.fixture_date, '+00:00', '{$sqlOffset}'))", $startDate);
        } else {
            $builder->where("DATE(CONVERT_TZ(ft.fixture_date, '+00:00', '{$sqlOffset}')) >=", $startDate);
            $builder->where("DATE(CONVERT_TZ(ft.fixture_date, '+00:00', '{$sqlOffset}')) <=", $endDate);
        }

        // Nota: A filtragem de Surebets e Apostas Seguras e tratada dinamicamente via JS na View (dashboard.php)
        // para que o usuario possa alternar os toggles instantaneamente sem perder as partidas carregadas.

        // Se showFinished for falso (default), exclui jogos encerrados
        if (!$showFinished) {
            $builder->groupStart()
                ->whereNotIn('ft.status', ['FT', 'AET', 'PEN', '120', '90'])
                ->where('DATE_ADD(ft.fixture_date, INTERVAL 120 MINUTE) >= UTC_TIMESTAMP()')
            ->groupEnd();
        }

        // Se showPostponed for falso (default: Não), exclui jogos com status PST / CANCELLED / POSTPONED
        if (!$showPostponed) {
            $builder->whereNotIn('ft.status', ['PST', 'CANCELLED', 'POSTPONED']);
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

        // Se não houver partidas no banco para a data solicitada, dispara a ingestão (API ou Fallback) e recarrega
        if (empty($fixtures)) {
            $scriptPath = '/root/datalake-air-flow-delta/scripts/football_ingest_trends.py';
            if (file_exists($scriptPath)) {
                @exec("python3 {$scriptPath} " . escapeshellarg($targetDate));
                $fixtures = $builder->get()->getResultObject();
            }
        }

        // Extrai ligas únicas para filtro em abas na View
        $leagues = [];
        $needsGoalsUpdate = false;
        foreach ($fixtures as $fix) {
            if (!empty($fix->league_name) && !in_array($fix->league_name, $leagues)) {
                $leagues[] = $fix->league_name;
            }
            if ($fix->status !== 'NS' && $fix->goals_home === null) {
                $needsGoalsUpdate = true;
            }
        }

        // Se houver partidas iniciadas/encerradas sem placar no banco, dispara atualização em segundo plano
        if ($needsGoalsUpdate) {
            $scriptPath = '/root/datalake-air-flow-delta/scripts/football_ingest_trends.py';
            if (file_exists($scriptPath)) {
                @exec("python3 {$scriptPath} " . escapeshellarg($targetDate) . " > /dev/null 2>&1 &");
            }
        }

        $seo = new \App\Libraries\SeoHelper();
        $seo->setFootballTrendsDefaults($targetDate, count($fixtures), $leagues);

        // Consulta apostas cadastradas para identificar partidas com palpite/aposta
        $userBetFixtureIds = [];
        $allBetFixtureIds  = [];
        if ($db->tableExists('apostas')) {
            $userId = $_SESSION['id_usuario_logado'] ?? session()->get('id_usuario_logado') ?? null;
            if (!empty($userId)) {
                $userBets = $db->table('apostas')
                    ->select('fixture_id')
                    ->where('usuario_id', $userId)
                    ->where('fixture_id IS NOT NULL')
                    ->get()
                    ->getResultArray();
                $userBetFixtureIds = array_map('intval', array_column($userBets, 'fixture_id'));
            }

            $allBets = $db->table('apostas')
                ->select('fixture_id')
                ->where('fixture_id IS NOT NULL')
                ->get()
                ->getResultArray();
            $allBetFixtureIds = array_map('intval', array_column($allBets, 'fixture_id'));
        }

        // Prepara dados para a view
        $data = [
            'targetDate'        => $targetDate,
            'startDate'         => $startDate,
            'endDate'           => $endDate,
            'userTimezone'      => $userTimezone,
            'search'            => $search,
            'showFinished'      => $showFinished,
            'showPostponed'     => $showPostponed,
            'onlySafe'          => $onlySafe,
            'onlySurebet'       => $onlySurebet,
            'userBetFixtureIds' => $userBetFixtureIds,
            'allBetFixtureIds'  => $allBetFixtureIds,
            'fixtures'          => $fixtures,
            'leagues'           => $leagues,
            'title'             => 'Tendências de Futebol Hoje & Estatísticas de Cartões | CristalBet',
            'metaTags'          => $seo->generateMetaTags()
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

        // Estatísticas detalhadas passadas no POST
        $homeAvgGoalsScored = $this->request->getPost('home_avg_goals_scored');
        $homeAvgGoalsConceded = $this->request->getPost('home_avg_goals_conceded');
        $homeCleanSheetsPct = $this->request->getPost('home_clean_sheets_pct');
        $homeAvgCorners = $this->request->getPost('home_avg_corners');
        $homeAvgCards = $this->request->getPost('home_avg_cards');

        $awayAvgGoalsScored = $this->request->getPost('away_avg_goals_scored');
        $awayAvgGoalsConceded = $this->request->getPost('away_avg_goals_conceded');
        $awayCleanSheetsPct = $this->request->getPost('away_clean_sheets_pct');
        $awayAvgCorners = $this->request->getPost('away_avg_corners');
        $awayAvgCards = $this->request->getPost('away_avg_cards');

        $refereeRigor = $this->request->getPost('referee_rigor') ?: 'Moderado';
        $refereeYellows = $this->request->getPost('referee_yellows');
        $refereeReds = $this->request->getPost('referee_reds');
        $refereeFouls = $this->request->getPost('referee_fouls');
        $refereeGames = $this->request->getPost('referee_games');
        
        // Verificar se o usuário está logado
        if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] != 1) {
            return $this->response->setJSON([
                'success' => false,
                'is_locked' => true,
                'message' => 'Você precisa estar logado para consultar o Grok AI.'
            ]);
        }

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if (!$userId) {
            return $this->response->setJSON([
                'success' => false,
                'is_locked' => true,
                'message' => 'ID de usuário não encontrado.'
            ]);
        }

        // Buscar créditos de Grok no banco
        $db = \Config\Database::connect();
        $userRow = $db->table('usuario')->where('id', $userId)->get()->getRow();
        if (!$userRow) {
            return $this->response->setJSON([
                'success' => false,
                'is_locked' => true,
                'message' => 'Usuário não encontrado no banco de dados.'
            ]);
        }

        // Verificar se o cadastro foi realizado com login social Google
        if (empty($userRow->google_id)) {
            return $this->response->setJSON([
                'success' => false,
                'is_locked' => true,
                'is_google_required' => true,
                'message' => 'Para usar o sistema de cotas e o Grok AI, você deve se cadastrar com seu login social do Google.'
            ]);
        }

        $credits = (int)($userRow->grok_credits ?? 0);

        if ($credits <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'is_locked' => true,
                'message' => 'Você não possui créditos suficientes. Recarregue seu saldo (R$ 10,00 = 20 consultas) para continuar usando o Grok AI e liberar as estatísticas.'
            ]);
        }

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

        // Monta o panorama de estatísticas de cada time e do árbitro
        $statsContent = "\n\nDados Estatísticos Detalhados dos Times:\n"
            . "- {$homeTeam} (Mandante):\n"
            . "  * Média de Gols Marcados: " . ($homeAvgGoalsScored !== '' && $homeAvgGoalsScored !== null ? number_format($homeAvgGoalsScored, 1) : 'N/A') . "\n"
            . "  * Média de Gols Sofridos: " . ($homeAvgGoalsConceded !== '' && $homeAvgGoalsConceded !== null ? number_format($homeAvgGoalsConceded, 1) : 'N/A') . "\n"
            . "  * Clean Sheets (Jogos sem sofrer gols): " . ($homeCleanSheetsPct !== '' && $homeCleanSheetsPct !== null ? round($homeCleanSheetsPct) . '%' : 'N/A') . "\n"
            . "  * Média de Escanteios a favor: " . ($homeAvgCorners !== '' && $homeAvgCorners !== null ? number_format($homeAvgCorners, 1) : 'N/A') . "\n"
            . "  * Média de Cartões recebidos: " . ($homeAvgCards !== '' && $homeAvgCards !== null ? number_format($homeAvgCards, 1) : 'N/A') . "\n"
            . "- {$awayTeam} (Visitante):\n"
            . "  * Média de Gols Marcados: " . ($awayAvgGoalsScored !== '' && $awayAvgGoalsScored !== null ? number_format($awayAvgGoalsScored, 1) : 'N/A') . "\n"
            . "  * Média de Gols Sofridos: " . ($awayAvgGoalsConceded !== '' && $awayAvgGoalsConceded !== null ? number_format($awayAvgGoalsConceded, 1) : 'N/A') . "\n"
            . "  * Clean Sheets (Jogos sem sofrer gols): " . ($awayCleanSheetsPct !== '' && $awayCleanSheetsPct !== null ? round($awayCleanSheetsPct) . '%' : 'N/A') . "\n"
            . "  * Média de Escanteios a favor: " . ($awayAvgCorners !== '' && $awayAvgCorners !== null ? number_format($awayAvgCorners, 1) : 'N/A') . "\n"
            . "  * Média de Cartões recebidos: " . ($awayAvgCards !== '' && $awayAvgCards !== null ? number_format($awayAvgCards, 1) : 'N/A') . "\n";

        if (!empty($refereeName)) {
            $statsContent .= "\nDados Estatísticos Detalhados do Árbitro ({$refereeName}):\n"
                . "- Rigor da Arbitragem: {$refereeRigor}\n"
                . "- Média de Amarelos por partida: " . ($refereeYellows !== '' && $refereeYellows !== null ? number_format($refereeYellows, 2) : 'N/A') . "\n"
                . "- Média de Vermelhos por partida: " . ($refereeReds !== '' && $refereeReds !== null ? number_format($refereeReds, 2) : 'N/A') . "\n"
                . "- Média de Faltas por partida: " . ($refereeFouls !== '' && $refereeFouls !== null ? number_format($refereeFouls, 2) : 'N/A') . "\n"
                . "- Total de jogos registrados: " . ($refereeGames !== '' && $refereeGames !== null ? $refereeGames : 'N/A') . "\n";
        }

        // Prompt de Sistema detalhado com base nas orientações fornecidas
        $systemContent = "Você é o Grok, um assistente inteligente especialista em apostas esportivas e análise estatística de futebol na plataforma MyFlow Trends. "
            . "O usuário está analisando a partida: {$homeTeam} vs {$awayTeam} pela liga '{$leagueName}'.\n\n"
            . "Dados gerais do confronto:\n"
            . "- Árbitro escalado: " . ($refereeName ?: 'Sem árbitro escalado ainda') . "\n"
            . "- Análise pré-gerada: {$predictionText}\n"
            . "- Probabilidade calculada de Over 4.5 Cartões: {$prob}%\n"
            . $statsContent . "\n"
            . "DIRETRIZES DE RESPOSTA:\n"
            . "1. Seja conversador, direto, amigável e use gírias ou jargão saudável do meio de apostas em português.\n"
            . "2. Faça uma análise AMPLA, aproveitando todos os dados estatísticos fornecidos acima. Não se limite apenas a cartões. Se o usuário perguntar ou se fizer sentido, explore e cruze dados para indicar outros mercados de apostas inteligentes:\n"
            . "   - Mercado de Gols (Over/Under gols, Ambas Marcam / BTTS): Avalie as médias de gols marcados/sofridos e os índices de Clean Sheets das duas equipes. Se ambos marcam muito e sofrem muito, recomende 'Ambas Marcam' ou 'Over 2.5 Gols'.\n"
            . "   - Mercado de Escanteios (Cantos): Utilize as médias de escanteios de cada equipe para fundamentar projeções de Over/Under escanteios ou o mercado de 'Quem terá mais escanteios'.\n"
            . "   - Mercado de Cartões por Equipe / Individuais: Indique qual time costuma receber mais cartões com base na média individual de cartões e na postura do árbitro.\n"
            . "   - Mercados Híbridos/Alternativos para Cartões (ex: 'Ambas as equipes receberão 2 ou mais cartões') caso a linha direta esteja esticada ou indisponível em ligas Tier 2 na Betano/Superbet.\n"
            . "3. Use a nossa análise pré-gerada e o rigor do árbitro para fundamentar a sua resposta técnica. Responda de forma concisa e evite textos excessivamente longos.";

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
            $client = \Config\Services::curlrequest([
                'http_errors' => false,
            ]);
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
                $bodyText = $response->getBody();

                if ($statusCode === 429 || $statusCode === 402) {
                    $this->notifyAdminQuotaExceeded($statusCode, $bodyText);
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'O assistente de IA atingiu temporariamente o limite de consultas por minuto/dia do servidor. O administrador foi notificado para expansão do plano.'
                    ]);
                }

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

            // Decrementa o crédito do usuário após resposta com sucesso
            $db->table('usuario')->where('id', $userId)->update([
                'grok_credits' => $credits - 1
            ]);

            return $this->response->setJSON([
                'success' => true,
                'response' => $aiResponse,
                'remaining_credits' => $credits - 1
            ]);

        } catch (\Exception $e) {
            $msg = $e->getMessage();

            if (strpos($msg, '429') !== false || strpos($msg, '402') !== false || stripos($msg, 'rate limit') !== false) {
                $this->notifyAdminQuotaExceeded(429, $msg);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'O assistente de IA atingiu temporariamente o limite de consultas por minuto/dia do servidor. O administrador foi notificado para expansão do plano.'
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro interno ao se comunicar com o Groq: ' . $msg
            ]);
        }
    }

    /**
     * Notifica o administrador quando a cota/rate limit da Groq API for excedida (HTTP 429 / 402)
     */
    private function notifyAdminQuotaExceeded(int $statusCode, string $errorMessage = '')
    {
        $cacheKey = 'groq_api_quota_alert_sent';
        $cache = \Config\Services::cache();

        // Evita flood de notificações: envia no máximo 1 notificação a cada 15 minutos (900s)
        if ($cache->get($cacheKey)) {
            return;
        }

        // 1. Log Crítico no Servidor
        log_message('critical', "[GROQ API ALERT] Limite de cota/rate limit atingido (HTTP {$statusCode}). Detalhes: {$errorMessage}");

        $now = date('Y-m-d H:i:s');
        $webhookUrl = env('GROQ_ALERT_WEBHOOK_URL');
        $adminEmail = env('ADMIN_ALERT_EMAIL') ?: 'admin@estudotabela.com.br';

        // 2. Notificação via Webhook (Discord / Telegram / n8n / Slack)
        if (!empty($webhookUrl)) {
            try {
                $client = \Config\Services::curlrequest(['timeout' => 5]);
                $client->post($webhookUrl, [
                    'json' => [
                        'event' => 'GROQ_API_QUOTA_EXCEEDED',
                        'status_code' => $statusCode,
                        'message' => "🚨 ALERTA GROQ API: Limite de cota/rate limit atingido (HTTP {$statusCode}). É necessário fazer upgrade para o plano pago na Groq Cloud (api.groq.com).",
                        'timestamp' => $now,
                        'details' => $errorMessage
                    ]
                ]);
            } catch (\Exception $e) {
                log_message('error', "[GROQ ALERT WEBHOOK FAIL] Erro ao enviar webhook: " . $e->getMessage());
            }
        }

        // 3. Notificação via E-mail
        if (!empty($adminEmail)) {
            try {
                $emailService = \Config\Services::email();
                $emailService->setTo($adminEmail);
                $emailService->setSubject("🚨 [ALERTA FOOTBALLWEB] Limite da Groq API Excedido (HTTP {$statusCode})");
                $emailService->setMessage(
                    "Atenção Administrador,\n\n" .
                    "A chave da API Groq (utilizada pelo Grok AI) atingiu o limite do plano gratuito ou estourou a cota de requisições por minuto/dia.\n\n" .
                    "Data/Hora: {$now}\n" .
                    "Status Code: {$statusCode}\n" .
                    "Detalhes: {$errorMessage}\n\n" .
                    "Ação Necessária: Acesse https://console.groq.com/ e faça o upgrade da sua conta para o plano pago (Pay-as-you-go).\n\n" .
                    "Atenciosamente,\nFootballWeb System"
                );
                $emailService->send(false);
            } catch (\Exception $e) {
                log_message('error', "[GROQ ALERT EMAIL FAIL] Erro ao enviar email: " . $e->getMessage());
            }
        }

        // Define a trava de 15 minutos (900 segundos) para evitar spam de alertas
        $cache->save($cacheKey, true, 900);
    }

    /**
     * Exibe a página dinâmica do jogo com Meta Tags de SEO e Schema.org JSON-LD (SportsEvent)
     */
    public function matchDetail($slug = null)
    {
        $userTimezone = $this->getUserTimezone();
        date_default_timezone_set($userTimezone);

        $db = \Config\Database::connect();

        $fixture = null;
        $homeTeam = 'Time Casa';
        $awayTeam = 'Time Fora';
        $refereeName = 'Árbitro';
        $fixtureDate = date('Y-m-d');

        if (!empty($slug)) {
            // Tenta extrair partes do slug: ex. 2026-07-20-fluminense-x-bragantino
            $parts = explode('-x-', $slug);
            if (count($parts) === 2) {
                // Parte 1 pode conter a data e time_casa (ex: 2026-07-20-fluminense)
                $firstPart = explode('-', $parts[0]);
                if (count($firstPart) >= 4) {
                    $fixtureDate = implode('-', array_slice($firstPart, 0, 3));
                    $homeSlug = implode('-', array_slice($firstPart, 3));
                } else {
                    $homeSlug = $parts[0];
                }
                $awaySlug = $parts[1];

                // Busca fixture compatível no banco
                $builder = $db->table('fixtures_trends ft');
                $builder->select('ft.*, rs.average_yellow_cards, rs.average_red_cards, rs.average_fouls, rs.total_games, rs.rigor_level');
                $builder->join('referee_stats rs', 'ft.referee_name = rs.name', 'left');
                $builder->like('ft.home_team', str_replace('-', ' ', $homeSlug));
                $builder->like('ft.away_team', str_replace('-', ' ', $awaySlug));
                $fixture = $builder->get()->getRow();

                if ($fixture) {
                    $homeTeam = $fixture->home_team;
                    $awayTeam = $fixture->away_team;
                    $refereeName = $fixture->referee_name ?? 'Não informado';
                    $fixtureDate = $fixture->fixture_date ?? $fixtureDate;
                } else {
                    $homeTeam = ucwords(str_replace('-', ' ', $homeSlug));
                    $awayTeam = ucwords(str_replace('-', ' ', $awaySlug));
                }
            }
        }

        $canonicalUrl = base_url("jogos/{$slug}");

        $seo = new \App\Libraries\SeoHelper();
        $seo->setMatchData($homeTeam, $awayTeam, $refereeName, $fixtureDate, $canonicalUrl);

        $data = [
            'targetDate'   => date('Y-m-d', strtotime($fixtureDate)),
            'userTimezone' => $userTimezone,
            'search'       => "{$homeTeam} {$awayTeam}",
            'showFinished' => true,
            'fixtures'     => $fixture ? [$fixture] : [],
            'leagues'      => $fixture ? [$fixture->league_name] : [],
            'title'        => "Estatísticas {$homeTeam} x {$awayTeam} | CristalBet",
            'metaTags'     => $seo->generateMetaTags()
        ];

        return $this->loadView('football/dashboard', $data);
    }

    /**
     * Retorna JSON com os placares e minutos atualizados das partidas em tempo real
     */
    public function liveScores()
    {
        $userTimezone = $this->getUserTimezone();
        $sqlOffset = $this->getTimezoneSqlOffset($userTimezone);
        $today = date('Y-m-d');
        $targetDate = $this->request->getVar('date') ?: $today;

        // Dispara uma sincronização dos placares da data via script Python
        if ($targetDate === $today) {
            $scriptPath = '/root/datalake-air-flow-delta/scripts/football_ingest_trends.py';
            if (file_exists($scriptPath)) {
                @exec("python3 {$scriptPath} --live > /dev/null 2>&1 &");
            }
        }

        $db = \Config\Database::connect();
        $builder = $db->table('fixtures_trends');
        $builder->select('fixture_id, status, elapsed, goals_home, goals_away, yellow_cards_home, yellow_cards_away, red_cards_home, red_cards_away, corners_home, corners_away, shots_home, shots_away, xg_home, xg_away, goal_scorers, last_event, home_team, away_team, updated_at');
        $builder->where("DATE(CONVERT_TZ(fixture_date, '+00:00', '{$sqlOffset}'))", $targetDate);
        $fixtures = $builder->get()->getResultArray();

        return $this->response->setJSON([
            'status'    => 'success',
            'timestamp' => date('Y-m-d H:i:s'),
            'count'     => count($fixtures),
            'fixtures'  => $fixtures
        ]);
    }

    /**
     * Proxy seguro e com cache local para os escudos dos times (evita bloqueio por AdBlockers/CORS)
     */
    public function teamLogo($teamId = null)
    {
        $teamId = (int)$teamId;
        if ($teamId <= 0) {
            return $this->response->setStatusCode(404);
        }

        $cache = \Config\Services::cache();
        $cacheKey = "team_logo_{$teamId}";
        $imageData = $cache->get($cacheKey);

        if (!$imageData) {
            $url = "https://media.api-sports.io/football/teams/{$teamId}.png";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && !empty($imageData)) {
                $cache->save($cacheKey, $imageData, 604800); // 7 dias
            } else {
                return $this->response->setStatusCode(404);
            }
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'public, max-age=604800')
            ->setBody($imageData);
    }
}


