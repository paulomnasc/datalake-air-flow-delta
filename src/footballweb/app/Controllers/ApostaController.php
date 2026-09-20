<?php

namespace App\Controllers;

use App\Models\ApostaModel;
use App\Models\UsuarioModel;
use App\Models\ContaCorrenteModel;

class ApostaController extends BaseController
{
    protected ApostaModel $apostaModel;
    protected ContaCorrenteModel $contaCorrenteModel;

    public function __construct()
    {
        $this->apostaModel        = new ApostaModel();
        $this->contaCorrenteModel = new ContaCorrenteModel();
    }

    /**
     * Verifica se o usuário atual está autenticado e possui tokens de consulta.
     * Retorna array com [ 'authenticated' => bool, 'has_tokens' => bool, 'user_id' => int|null, 'user' => object|null, 'credits' => int ]
     */
    private function checkAccess(): array
    {
        $isLogged = (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1) 
                 || (session()->has('usuario_logado') && session()->get('usuario_logado') == 1);
        
        $userId = $_SESSION['id_usuario_logado'] ?? session()->get('id_usuario_logado') ?? null;

        if (!$isLogged || !$userId) {
            return [
                'authenticated' => false,
                'has_tokens'    => false,
                'user_id'       => null,
                'user'          => null,
                'credits'       => 0
            ];
        }

        $db = \Config\Database::connect();
        $userRow = $db->table('usuario')->where('id', $userId)->get()->getRow();

        if (!$userRow) {
            return [
                'authenticated' => true,
                'has_tokens'    => false,
                'user_id'       => $userId,
                'user'          => null,
                'credits'       => 0
            ];
        }

        $credits = (int)($userRow->grok_credits ?? 0);
        $hasTokens = ($credits > 0);

        return [
            'authenticated' => true,
            'has_tokens'    => $hasTokens,
            'user_id'       => (int)$userId,
            'user'          => $userRow,
            'credits'       => $credits
        ];
    }

    /**
     * Exibe o painel CRUD de Apostas do usuário
     */
    public function index()
    {
        $access = $this->checkAccess();

        // Se não estiver logado, redireciona para login com mensagem
        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar a gestão de simulações de apostas.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $hasTokens = $access['has_tokens'];
        $userCredits = $access['credits'];

        // Buscar lista de jogos disponíveis para associar (fixtures_trends)
        $db = \Config\Database::connect();
        $targetFixId = $this->request->getVar('fixture_id');

        $builderFix = $db->table('fixtures_trends')
            ->select('fixture_id, home_team, away_team, fixture_date, league_name, prediction_text, ah_suggestion, ah_confidence, xg_home, xg_away, home_rank, away_rank, home_ppg, away_ppg, home_zone, away_zone, standings_motivation_score');
        
        $fixtures = $builderFix->orderBy('fixture_date', 'DESC')
            ->limit(100)
            ->get()
            ->getResultObject();

        // Se fixture_id foi requisitada via URL mas nao esta na lista inicial, busca explicitamente
        if (!empty($targetFixId)) {
            $exists = false;
            foreach ($fixtures as $f) {
                if ((string)$f->fixture_id === (string)$targetFixId) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $targetFix = $db->table('fixtures_trends')
                    ->select('fixture_id, home_team, away_team, fixture_date, league_name, prediction_text, ah_suggestion, ah_confidence')
                    ->where('fixture_id', $targetFixId)
                    ->get()
                    ->getRow();
                if ($targetFix) {
                    array_unshift($fixtures, $targetFix);
                }
            }
        }

        foreach ($fixtures as $fix) {
            $suggestedCards = 'Menos de 5.5';
            if (!empty($fix->prediction_text) && preg_match('/Under\s*(\d+\.\d+|\d+)/i', $fix->prediction_text, $m)) {
                $suggestedCards = 'Menos de ' . $m[1];
            }
            $fix->suggested_palpite_cards = $suggestedCards;
            $ahSug = trim($fix->ah_suggestion ?? '');
            if (!empty($ahSug)) {
                $fix->suggested_palpite_ah = $ahSug;
            } else {
                $fix->suggested_palpite_ah = "{$fix->home_team} 0.0 (Empate Anula)";
            }
            $fix->suggested_palpite = $suggestedCards;

            $ahConf = floatval($fix->ah_confidence ?? 0);
            $fix->ah_confidence_val = $ahConf;
            $fix->is_max_ah_score = ($ahConf >= 78.0 || !empty($fix->ah_suggestion));
        }

        $apostas = [];
        $resumo  = [
            'total_apostas'  => 0,
            'total_apostado' => 0,
            'ganhos_totais'  => 0,
            'total_cashout'  => 0,
            'saldo_liquido'  => 0,
            'ganhas'         => 0,
            'perdidas'       => 0,
            'anuladas'       => 0,
            'pendentes'      => 0,
            'cashouts'       => 0
        ];

        // Apenas carrega apostas se o usuário possuir tokens
        if ($hasTokens) {
            helper('league');
            $db = \Config\Database::connect();
            $apostas = $db->query("
                SELECT 
                    a.*,
                    f.goals_home,
                    f.goals_away,
                    f.status as fixture_status,
                    f.league_name,
                    f.league_id,
                    f.odd_home,
                    f.odd_draw,
                    f.odd_away,
                    f.ah_suggestion,
                    f.ah_confidence,
                    f.ah_reasoning,
                    (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = 'DEBITO_APOSTA') as tem_debito
                FROM apostas a
                LEFT JOIN fixtures_trends f ON (a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id)
                WHERE a.usuario_id = ?
                ORDER BY CASE WHEN a.data_hora_jogo IS NOT NULL AND a.data_hora_jogo > '2000-01-01' THEN a.data_hora_jogo ELSE a.criado_em END ASC, a.criado_em ASC, a.id ASC
            ", [$userId])->getResultObject();

            $tzUtc = new \DateTimeZone('UTC');
            $tzBrt = new \DateTimeZone('America/Sao_Paulo');
            foreach ($apostas as &$ap) {
                if (!empty($ap->data_hora_jogo)) {
                    try {
                        $dt = new \DateTime($ap->data_hora_jogo, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia       = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $ap->data_hora_jogo;
                        $ap->data_brt_dia       = substr($ap->data_hora_jogo, 0, 10);
                    }
                } elseif (!empty($ap->criado_em)) {
                    try {
                        $dt = new \DateTime($ap->criado_em, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $ap->criado_em;
                        $ap->data_brt_dia = substr($ap->criado_em, 0, 10);
                    }
                } else {
                    $ap->data_hora_jogo_brt = date('Y-m-d H:i:s');
                    $ap->data_brt_dia = date('Y-m-d');
                }

                // Resolve o país e a bandeira emoji da liga referente ao jogo
                $leagueInfo = \App\Helpers\LeagueHelper::resolveCountryAndFlag($ap->league_id ?? null, $ap->league_name ?? null);
                $ap->league_country = $leagueInfo['country'];
                $ap->league_flag    = $leagueInfo['flag'];
            }
            unset($ap);

            $resumo  = $this->apostaModel->getResumoUsuario($userId);
        }

        $saldoContaCorrente = $this->contaCorrenteModel->getSaldo($userId);

        $data = [
            'title'              => 'Minhas Simulações de Apostas | Gestão de Riscos & Palpites',
            'hasTokens'          => $hasTokens,
            'userCredits'        => $userCredits,
            'saldoContaCorrente' => $saldoContaCorrente,
            'apostas'            => $apostas,
            'resumo'             => $resumo,
            'fixtures'           => $fixtures,
            'user'               => $access['user']
        ];

        return view('header', $data)
             . view('apostas/index', $data)
             . view('footer');
    }

    /**
     * Cadastra nova aposta (AJAX)
     */
    public function store()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta ativos para criar e gerenciar simulações de apostas.'
            ])->setStatusCode(403);
        }

        $userId = $access['user_id'];

        $timeCasa        = trim($this->request->getPost('time_casa') ?? '');
        $timeFora        = trim($this->request->getPost('time_fora') ?? '');
        $mercado         = trim($this->request->getPost('mercado') ?? 'Total de Cartões');
        $palpite         = trim($this->request->getPost('palpite') ?? '');
        $odd             = (float)str_replace(',', '.', (string)($this->request->getPost('odd') ?? '0'));
        $valorAposta     = (float)str_replace(',', '.', (string)($this->request->getPost('valor_aposta') ?? '0'));
        $fixtureId       = $this->request->getPost('fixture_id') ? (int)$this->request->getPost('fixture_id') : null;
        $dataHoraInput   = trim($this->request->getPost('data_hora_jogo') ?? '');
        $tipo            = trim($this->request->getPost('tipo') ?? 'Simples');
        $status          = trim($this->request->getPost('status') ?? 'Pendente');
        $cashOut         = $this->request->getPost('cash_out') !== null && $this->request->getPost('cash_out') !== '' 
                           ? (float)str_replace(',', '.', (string)$this->request->getPost('cash_out')) : null;

        if ($mercado === 'Handicap Asiático' || stripos($mercado, 'handicap') !== false) {
            $palpite = $this->formatHandicapPalpite($palpite, $timeCasa, $timeFora);
        }

        if (empty($timeCasa) || empty($timeFora) || empty($palpite) || $odd <= 0 || $valorAposta <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, preencha corretamente os campos obrigatórios (Times, Palpite, Odd e Valor da Simulação de Aposta).'
            ]);
        }

        if ($status === 'ANULADA') {
            $ganhosPotenciais = $valorAposta;
        } elseif ($status === 'Meio Ganha') {
            $ganhosPotenciais = round($valorAposta * (($odd + 1) / 2), 2);
        } elseif ($status === 'Meio Perdida') {
            $ganhosPotenciais = round($valorAposta * 0.5, 2);
        } elseif ($status === 'Perdida') {
            $ganhosPotenciais = 0.00;
        } else {
            $ganhosPotenciais = round($odd * $valorAposta, 2);
        }

        // Validação do Gatekeeper
        $eval = $this->evaluateGatekeeper($fixtureId, $timeCasa, $timeFora, $mercado, $palpite, $odd);
        $fixtureId        = $eval['fixtureId'];
        $oddJusta         = $eval['oddJusta'];
        $probPoisson      = $eval['probPoisson'];
        $evPercentual     = $eval['evPercentual'];
        $statusGatekeeper = $eval['statusGatekeeper'];
        $gatekeeperMsg    = $eval['gatekeeperMsg'];

        $confirmarRisco = filter_var($this->request->getPost('confirmar_risco') ?? $this->request->getPost('confirm_warning') ?? $this->request->getPost('confirm'), FILTER_VALIDATE_BOOLEAN)
                          || in_array(strtolower((string)($this->request->getPost('confirmar_risco') ?? '')), ['1', 'true', 'sim', 'yes'])
                          || in_array(strtolower((string)($this->request->getPost('confirm') ?? '')), ['1', 'true', 'sim', 'yes']);

        if ($statusGatekeeper === 'AVISO_RISCO_OVER') {
            if (!$confirmarRisco) {
                return $this->response->setJSON([
                    'success'              => false,
                    'require_confirmation' => true,
                    'is_warning'           => true,
                    'status_gatekeeper'    => 'AVISO_RISCO_OVER',
                    'message'              => '⚠️ ' . $gatekeeperMsg
                ]);
            }
            $statusGatekeeper = 'ALERTA_RISCO_OVER';
        }


        // Trava anti-duplicidade de requisições em paralelo (janela de 10 segundos)
        $dbCheck = \Config\Database::connect();
        $recentDuplicate = $dbCheck->table('apostas')
            ->where('usuario_id', $userId)
            ->where('time_casa', $timeCasa)
            ->where('time_fora', $timeFora)
            ->where('mercado', $mercado)
            ->where('palpite', $palpite)
            ->where('valor_aposta', $valorAposta)
            ->where('criado_em >=', date('Y-m-d H:i:s', time() - 10))
            ->get()->getRow();

        if ($recentDuplicate) {
            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Simulação de aposta já registrada anteriormente! ' . $gatekeeperMsg,
                'id'                => $recentDuplicate->id,
                'status_gatekeeper' => $statusGatekeeper,
                'odd_justa'         => $oddJusta,
                'ev_percentual'     => $evPercentual,
                'gatekeeper_msg'    => $gatekeeperMsg
            ]);
        }

        // Definição da data_hora_jogo (fuso horário America/Sao_Paulo)
        $nowBr = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        if (!empty($dataHoraInput)) {
            $dataHoraJogo = $dataHoraInput;
        } elseif ($fixtureId) {
            $dbFix = \Config\Database::connect();
            $fixRow = $dbFix->table('fixtures_trends')->select('fixture_date')->where('fixture_id', $fixtureId)->get()->getRow();
            $dataHoraJogo = (!empty($fixRow) && !empty($fixRow->fixture_date)) ? $fixRow->fixture_date : $nowBr;
        } else {
            // Caso não haja fixture_id e nem data informada, grava com a data de hoje em America/Sao_Paulo
            $dataHoraJogo = $nowBr;
        }

        $confirmarDebitar = $this->request->getPost('confirmar_debitar') !== null 
                            ? filter_var($this->request->getPost('confirmar_debitar'), FILTER_VALIDATE_BOOLEAN) 
                            : true;

        if (!$confirmarDebitar && $status === 'Pendente') {
            $status = 'Não Confirmada';
        }

        $newId = $this->apostaModel->insert([
            'usuario_id'            => $userId,
            'fixture_id'            => $fixtureId,
            'time_casa'             => $timeCasa,
            'time_fora'             => $timeFora,
            'mercado'               => $mercado,
            'palpite'               => $palpite,
            'odd'                   => $odd,
            'odd_justa'             => $oddJusta,
            'probabilidade_poisson' => $probPoisson,
            'ev_percentual'         => $evPercentual,
            'status_gatekeeper'     => $statusGatekeeper,
            'gatekeeper_category'   => $eval['gatekeeperCategory'] ?? null,
            'data_hora_jogo'        => $dataHoraJogo,
            'valor_aposta'          => $valorAposta,
            'ganhos_potenciais'     => $ganhosPotenciais,
            'cash_out'              => $cashOut,
            'tipo'                  => $tipo,
            'status'                => $status,
            'confirmada'            => $confirmarDebitar ? 1 : 0,
            'destaque'              => !empty($eval['destaque']) ? 1 : 0,
            'criado_em'             => $nowBr
        ]);

        if ($newId) {
            if ($confirmarDebitar) {
                // Débito do valor da aposta na Conta Corrente se confirmado
                $this->contaCorrenteModel->debitarAposta(
                    $userId,
                    (int)$newId,
                    $valorAposta,
                    "Aposta #{$newId} ({$timeCasa} x {$timeFora} - {$palpite})"
                );

                // Se o status da aposta já for de encerramento/retorno, credita a conta corrente
                if (in_array($status, ['Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Cashout'])) {
                    $retorno = ($status === 'Cashout' && $cashOut !== null) ? $cashOut : $ganhosPotenciais;
                    $this->contaCorrenteModel->creditarRetornoAposta(
                        $userId,
                        (int)$newId,
                        (float)$retorno,
                        "Retorno Aposta #{$newId} ({$status})"
                    );
                }
            }

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Simulação de aposta registrada! ' . $gatekeeperMsg,
                'id'                => $newId,
                'status_gatekeeper' => $statusGatekeeper,
                'odd_justa'         => $oddJusta,
                'ev_percentual'     => $evPercentual,
                'gatekeeper_msg'    => $gatekeeperMsg
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Erro ao salvar simulação de aposta no banco de dados.'
        ]);
    }

    /**
     * Verifica se o clube pertence ao grupo Tier 1 de Elite Mundial/Continental.
     * Delega para o Helper central canônico (App\Helpers\LeagueHelper::isTier1EliteClub).
     */
    private function isTier1EliteClub(?int $teamId = null, ?string $teamName = null): bool
    {
        return \App\Helpers\LeagueHelper::isTier1EliteClub($teamId, $teamName);
    }

    /**
     * Reavalia o Gatekeeper para uma aposta (+EV, Odd Justa, Poisson e Teto Dinâmico de Segurança)
     */
    private function evaluateGatekeeper(?int $fixtureId, string $timeCasa, string $timeFora, string $mercado, string $palpite, float $odd): array
    {
        $oddJusta = null;
        $probPoisson = null;
        $evPercentual = null;
        $statusGatekeeper = 'NAO_ANALISADO';
        $gatekeeperCategory = null;
        $gatekeeperMsg = 'Simulação de aposta sem análise de estatísticas.';
        $destaque = 0;

        $isOver = (stripos($palpite, 'over') !== false || stripos($palpite, 'mais') !== false);
        $isCartoes = (stripos($mercado, 'cartõ') !== false || stripos($mercado, 'card') !== false);
        $isHandicap = (stripos($mercado, 'handicap') !== false || stripos($palpite, 'ah') !== false);

        // AVISO DE RISCO GATEKEEPER (Estratégia Exclusiva Under / Anti-Over para Cartões)
        if ($isCartoes && ($isOver || stripos($palpite, 'mais') !== false)) {
            $statusGatekeeper = 'AVISO_RISCO_OVER';
            $gatekeeperCategory = 'Aviso de Risco Over';
            $gatekeeperMsg = "Alerta de Risco Gatekeeper (Estratégia Exclusiva Under): Simulações de apostas no mercado 'Over / Mais de' possuem elevado risco de perda e volatilidade estatística. Apenas apostas 'Under / Menos de' são recomendadas pelo modelo. Deseja prosseguir mesmo com o risco apontado?";
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
        }

        if (!$isCartoes && !$isHandicap) {
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
        }

        $db = \Config\Database::connect();
        $fixture = null;

        if ($fixtureId) {
            $fixture = $db->table('fixtures_trends')->where('fixture_id', $fixtureId)->get()->getRow();
        }

        if (!$fixture && !empty($timeCasa) && !empty($timeFora)) {
            $fixture = $db->table('fixtures_trends')
                ->groupStart()
                    ->like('home_team', $timeCasa)
                    ->orLike('away_team', $timeCasa)
                ->groupEnd()
                ->groupStart()
                    ->like('home_team', $timeFora)
                    ->orLike('away_team', $timeFora)
                ->groupEnd()
                ->orderBy('fixture_date', 'DESC')
                ->get()
                ->getRow();
            if ($fixture) {
                $fixtureId = (int)$fixture->fixture_id;
            }
        }

        if ($fixture) {
            $hId = !empty($fixture->home_team_id) ? (int)$fixture->home_team_id : null;
            $aId = !empty($fixture->away_team_id) ? (int)$fixture->away_team_id : null;
            $hName = $fixture->home_team ?? $timeCasa;
            $aName = $fixture->away_team ?? $timeFora;

            $isAwayFav = (stripos($palpite, $aName) !== false || stripos($palpite, 'away') !== false || stripos($palpite, 'fora') !== false || stripos($palpite, 'visitante') !== false);
            $candId = $isAwayFav ? $aId : $hId;
            $candName = $isAwayFav ? $aName : $hName;
            $oppId = $isAwayFav ? $hId : $aId;
            $oppName = $isAwayFav ? $hName : $aName;

            $isCandT1 = $this->isTier1EliteClub($candId, $candName);
            $isOppT1 = $this->isTier1EliteClub($oppId, $oppName);

            if ($isCandT1) {
                $oppKey = $isAwayFav ? 'home' : 'away';
                $candKey = $isAwayFav ? 'away' : 'home';
                if (!empty($fixture->ah_reasoning) && preg_match('/U5J_DATA:\s*(\{.*?\})\s*(?:\|\||$)/s', $fixture->ah_reasoning, $mU5)) {
                    $u5Arr = json_decode($mU5[1], true);
                    if (!empty($u5Arr[$oppKey])) {
                        $oppPts = (int)($u5Arr[$oppKey]['pts'] ?? 0);
                        $oppV = (int)($u5Arr[$oppKey]['v'] ?? 0);
                        $candPts = (int)($u5Arr[$candKey]['pts'] ?? 0);
                        $oddCand = $isAwayFav ? (float)($fixture->odd_away ?? 99) : (float)($fixture->odd_home ?? 99);
                        $isCrisisOpp = ($oppPts <= 5 || $oppV === 0);

                        if (!$isOppT1 && $isCrisisOpp) {
                            $destaque = 1;
                        } elseif ($isOppT1 && $isCrisisOpp && ($candPts >= 8 || $oddCand <= 1.60 || $candPts >= $oppPts + 4)) {
                            $destaque = 1;
                        }
                    }
                }

                if (stripos($fixture->ah_reasoning ?? '', 'Tier 1 Dominante') !== false) {
                    $destaque = 1;
                }
            }
        }

        // =========================================================================
        // RAMO 1: GATEKEEPER PARA HANDICAP ASIÁTICO (DELEGAÇÃO CANÔNICA AO PYTHON)
        // =========================================================================
        if ($isHandicap) {
            $evalPy = $this->evaluateHandicapGatekeeperPython($fixtureId, $timeCasa, $timeFora, $palpite, $odd);
            if ($evalPy !== null) {
                return $evalPy;
            }
        }

        // =========================================================================
        // RAMO 2: GATEKEEPER PARA TOTAL DE CARTÕES (UNDER)
        // =========================================================================

        // =========================================================================
        // RAMO 2: GATEKEEPER PARA TOTAL DE CARTÕES (UNDER)
        // =========================================================================

        // TRAVA RIGOROSA DE SEGURANÇA POR LINHA (Apenas Under 5.5 e Under 6.5)
        preg_match('/(\d+\.\d+|\d+)/', $palpite, $matchesLineCheck);
        $lineCheck = !empty($matchesLineCheck[1]) ? (float)$matchesLineCheck[1] : 5.5;

        if (abs($lineCheck - 5.5) > 0.01 && abs($lineCheck - 6.5) > 0.01) {
            $statusGatekeeper = 'NO_BET';
            $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): Apenas as linhas Under 5.5 e Under 6.5 são autorizadas para operação no mercado de cartões.";
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg', 'destaque');
        }

        // 1. Média Histórica Dinâmica de Odds Vencedoras (Under Cartões) e Teto Dinâmico de Segurança
        $rowAvg = $db->query("
            SELECT AVG(odd) as avg_odd, COUNT(*) as total_vitorias 
            FROM apostas 
            WHERE status = 'Ganha' 
              AND (mercado LIKE '%cartõ%' OR mercado LIKE '%card%') 
              AND (palpite LIKE '%Menos%' OR palpite LIKE '%under%')
        ")->getRow();

        $avgWinningOdd = ($rowAvg && $rowAvg->avg_odd && (int)$rowAvg->total_vitorias > 0) 
            ? round((float)$rowAvg->avg_odd, 2) 
            : 1.50;

        // Teto dinâmico flexível: Média + 0.35 com piso mínimo de 2.00 (evita auto-afunilamento e bloqueia apenas distorções irreais)
        $maxAllowedOdd = round(max(2.00, $avgWinningOdd + 0.35), 2);

        if ($fixture && !empty($fixture->prediction_text)) {
            preg_match('/(?:xC|Expectativa)(?::|\s+elevado)?\s*\(?(\d+\.\d+|\d+)/i', $fixture->prediction_text, $matchesXc);
            $xc = !empty($matchesXc[1]) ? (float)$matchesXc[1] : null;

            if ($xc !== null && $xc > 0) {
                preg_match('/(\d+\.\d+|\d+)/', $palpite, $matchesLine);
                $line = !empty($matchesLine[1]) ? (float)$matchesLine[1] : 5.5;

                $kMax = (int)floor($line);
                $probUnderCdf = 0.0;
                for ($k = 0; $k <= $kMax; $k++) {
                    $probUnderCdf += (exp(-$xc) * pow($xc, $k)) / $this->factorial($k);
                }

                $probPoisson = round(min(100.0, max(0.0, $probUnderCdf * 100.0)), 2);

                if ($probPoisson > 0) {
                    $oddJusta = round(100.0 / $probPoisson, 2);
                    $evPercentual = round((($probPoisson / 100.0) * $odd - 1.0) * 100.0, 2);
                }

                // 2. MATRIZ DE RISCO E TRAVAS DE OURO DO GATEKEEPER
                $refName = !empty($fixture->referee_name) ? trim($fixture->referee_name) : '';
                $isUnknownRef = empty($refName) 
                    || stripos($refName, 'Não Informado') !== false 
                    || stripos($refName, 'Desconhecido') !== false 
                    || stripos($refName, 'unassigned') !== false 
                    || stripos($refName, 'tbd') !== false
                    || stripos($refName, 'sem arbitro') !== false;

                // Regra Canônica: Sem juiz = NO_BET
                if ($isUnknownRef) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Sem Árbitro Confirmado';
                    $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): Partida sem árbitro oficial confirmado na escala. Entrada em Under Cartões bloqueada por segurança (Sem juiz = NO_BET).";
                    return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
                }

                // Regra Canônica Exclusiva: Apenas Under 5.5 e Under 6.5
                if (abs($line - 5.5) > 0.01 && abs($line - 6.5) > 0.01) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Linha de Cartões Não Autorizada';
                    $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): Apenas as linhas Under 5.5 e Under 6.5 são autorizadas para operação no mercado de cartões.";
                    return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
                }

                // Metadados contextuais da partida para avaliação de partidas conflituosas (Regras AH -> Cartões)
                $ftRow = null;
                if ($fixtureId) {
                    $ftRow = $db->table('fixtures_trends')
                        ->select('odd_home, odd_away, home_rank, away_rank, home_zone, home_ppg, standings_motivation_score')
                        ->where('fixture_id', $fixtureId)
                        ->get()
                        ->getRowArray();
                }

                $ftOddH = (float)($ftRow['odd_home'] ?? 0.0);
                $ftOddA = (float)($ftRow['odd_away'] ?? 0.0);
                $ftHRank = isset($ftRow['home_rank']) ? (int)$ftRow['home_rank'] : 0;
                $ftARank = isset($ftRow['away_rank']) ? (int)$ftRow['away_rank'] : 0;
                $ftHZone = strtolower($ftRow['home_zone'] ?? '');
                $ftHPpg = (float)($ftRow['home_ppg'] ?? 0.0);
                $ftStandMot = (float)($ftRow['standings_motivation_score'] ?? 0.0);

                $isHRel = (strpos($ftHZone, 'relegat') !== false || strpos($ftHZone, 'play out') !== false || strpos($ftHZone, 'rebaixamento') !== false);
                $isHUnderThreat = (
                    $isHRel ||
                    ($ftHRank >= 12 && $ftHPpg > 0.0 && $ftHPpg <= 1.25) ||
                    ($ftHRank >= 12 && $ftStandMot >= 3.0) ||
                    ($ftHRank >= 12 && $ftARank > 0 && ($ftHRank - $ftARank >= 6 || $ftARank <= 6))
                );

                // 1. Caldeirão da Degola / Sobrevivência
                if ($isHUnderThreat) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Caldeirão da Degola (Sobrevivência)';
                    $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): O mandante está na zona de rebaixamento ou ameaçado pela degola, jogando a vida em seus domínios. A urgência de sobrevivência e tensão de caldeirão elevam o risco de faltas táticas e indisciplina. Abstenção mandatória para Under.";
                    return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
                }

                // 2. Disparidade Técnica Extrema / Massacre (Veto Under 5.5)
                $isExtremeDisparity = false;
                if ($ftOddH > 1.0 && $ftOddA > 1.0) {
                    $minO = min($ftOddH, $ftOddA);
                    $maxO = max($ftOddH, $ftOddA);
                    $ratio = ($minO > 0) ? ($maxO / $minO) : 1.0;
                    if (($minO <= 1.45 && $maxO >= 4.00) || $ratio >= 4.0) {
                        $isExtremeDisparity = true;
                    }
                }

                if ($isExtremeDisparity && abs($line - 5.5) < 0.01) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Disparidade Técnica Extrema';
                    $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): Partida com acentuado desnível técnico (odds {$ftOddH} vs {$ftOddA}). A equipe em desvantagem técnica tende a cometer faltas de contenção tática repetidas, tornando a linha Under 5.5 vulnerável a estouro.";
                    return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
                }

                // 3. Divergência de Mando / Conflito de Mercado (Veto Under 5.5)
                if ($ftOddH > 1.0 && $ftOddA > 1.0 && ($ftOddA - $ftOddH) >= 0.15 && $ftHRank > 0 && $ftARank > 0 && $ftHRank > $ftARank && abs($line - 5.5) < 0.01) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Conflito de Mando (Mercado)';
                    $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (NO_BET): Choque entre a melhor tabela do visitante e o favoritismo de mercado do mandante. Disputa física acirrada no meio-campo incompatível com a linha Under 5.5.";
                    return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
                }

                // Critérios específicos por linha (Under 5.5 e Under 6.5)
                $minOddReq  = (abs($line - 5.5) < 0.01) ? 1.65 : 1.50;
                $maxXcReq   = (abs($line - 5.5) < 0.01) ? 4.50 : 5.50;
                $minProbReq = 60.0;
                $minEvReq   = 0.0;

                // 3. Verificação de Duplicidade / Exposição por Evento
                $duplicateCount = 0;
                if ($fixtureId) {
                    $duplicateCount = (int)$db->table('apostas')
                        ->where('fixture_id', $fixtureId)
                        ->groupStart()
                            ->where('status', 'Pendente')
                            ->orWhere('status', 'Ganha')
                        ->groupEnd()
                        ->countAllResults();
                }

                $duplicidadeMsg = ($duplicateCount > 0) 
                    ? " ⚠️ [ALERTA DE GESTÃO DE RISCO: Já existe(m) {$duplicateCount} aposta(s) aberta(s) nesta partida]."
                    : "";

                // Avaliação final do Gatekeeper
                if ($odd < $minOddReq) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Odd Abaixo do Piso Mínimo';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Odd da casa ({$odd}) abaixo do piso mínimo de segurança ({$minOddReq}) para a linha Under {$line}.{$duplicidadeMsg}";
                } elseif ($xc > $maxXcReq) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Expectativa Excessiva de Cartões';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Expectativa de cartões ({$xc}) excede o teto de segurança ({$maxXcReq}) para a linha Under {$line}.{$duplicidadeMsg}";
                } elseif ($probPoisson < $minProbReq) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Probabilidade Insuficiente';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Probabilidade Poisson ({$probPoisson}%) abaixo do mínimo exigido ({$minProbReq}%) para a linha Under {$line}.{$duplicidadeMsg}";
                } elseif ($odd > $maxAllowedOdd) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Odd Excessiva (Risco)';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Odd da casa ({$odd}) excede o teto dinâmico de segurança ({$maxAllowedOdd}) derivado da média histórica de vitórias ({$avgWinningOdd}).{$duplicidadeMsg}";
                } elseif ($evPercentual !== null && $evPercentual >= $minEvReq) {
                    $statusGatekeeper = 'APROVADO';
                    $gatekeeperCategory = 'Valor Esperado Positivo (+EV)';
                    $gatekeeperMsg = "Gatekeeper Green Light (+EV): Linha Under {$line} | Odd Real ({$odd}) >= Odd Justa ({$oddJusta}) | EV: +{$evPercentual}% | Prob. Poisson: {$probPoisson}% (Mínimo: 60.0%) | xC: {$xc} (Teto: {$maxXcReq}) | Árbitro Oficial Confirmado.{$duplicidadeMsg}";
                } else {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperCategory = 'Falta de Valor Esperado (+EV)';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Valor esperado (+EV: {$evPercentual}%) insuficiente para aprovação na linha Under {$line}.{$duplicidadeMsg}";
                }
            }
        }

        return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperCategory', 'gatekeeperMsg', 'destaque');
    }

    /**
     * Invoca o motor canônico em Python (asian_handicap_engine.py) como Fonte Única da Verdade (SSOT).
     */
    private function evaluateHandicapGatekeeperPython(?int $fixtureId, string $timeCasa, string $timeFora, string $palpite, float $odd): ?array
    {
        $scriptPath = '/datalake-root/scripts/asian_handicap_engine.py';
        if (!file_exists($scriptPath)) {
            $scriptPath = '/root/datalake-air-flow-delta/scripts/asian_handicap_engine.py';
        }
        if (!file_exists($scriptPath)) {
            return null;
        }

        $cmd = "python3 " . escapeshellarg($scriptPath) . " --eval_bet"
             . " --fixture_id=" . escapeshellarg((string)($fixtureId ?? 0))
             . " --home_team=" . escapeshellarg($timeCasa)
             . " --away_team=" . escapeshellarg($timeFora)
             . " --palpite=" . escapeshellarg($palpite)
             . " --odd=" . escapeshellarg((string)$odd)
             . " 2>/dev/null";

        $output = shell_exec($cmd);
        if (empty($output)) {
            return null;
        }

        $res = json_decode(trim($output), true);
        if (!$res || !isset($res['statusGatekeeper'])) {
            return null;
        }

        return [
            'fixtureId'          => $res['fixtureId'] ?? $fixtureId,
            'oddJusta'           => $res['oddJusta'] ?? null,
            'probPoisson'        => $res['probPoisson'] ?? null,
            'evPercentual'       => $res['evPercentual'] ?? null,
            'statusGatekeeper'   => $res['statusGatekeeper'],
            'gatekeeperCategory' => $res['gatekeeperCategory'] ?? null,
            'gatekeeperMsg'      => $res['gatekeeperMsg'] ?? '',
            'destaque'           => (int)($res['destaque'] ?? 0)
        ];
    }

    /**
     * Atualiza dados de uma aposta (AJAX)
     */
    public function update($id = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta para atualizar simulações de apostas.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        if ($apostaId <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID de simulação de aposta inválido.'
            ])->setStatusCode(400);
        }

        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada.'
            ])->setStatusCode(404);
        }

        // Permite atualização se for o dono da aposta ou se for admin (ID 146)
        if ((int)$aposta->usuario_id !== (int)$access['user_id'] && (int)$access['user_id'] !== 146) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada ou acesso negado.'
            ])->setStatusCode(403);
        }

        $postTimeCasa  = $this->request->getPost('time_casa');
        $postTimeFora  = $this->request->getPost('time_fora');
        $postMercado   = $this->request->getPost('mercado');
        $postPalpite   = $this->request->getPost('palpite');
        $postOdd       = $this->request->getPost('odd');
        $postValor     = $this->request->getPost('valor_aposta');
        $postStatus    = $this->request->getPost('status');
        $postTipo      = $this->request->getPost('tipo');
        $postCashOut   = $this->request->getPost('cash_out');

        $timeCasa  = ($postTimeCasa !== null && trim($postTimeCasa) !== '') ? trim($postTimeCasa) : $aposta->time_casa;
        $timeFora  = ($postTimeFora !== null && trim($postTimeFora) !== '') ? trim($postTimeFora) : $aposta->time_fora;
        $mercado   = ($postMercado  !== null && trim($postMercado) !== '')  ? trim($postMercado)  : $aposta->mercado;
        $palpite   = ($postPalpite  !== null && trim($postPalpite) !== '')  ? trim($postPalpite)  : $aposta->palpite;
        $odd       = ($postOdd !== null && $postOdd !== '') ? (float)str_replace(',', '.', (string)$postOdd) : (float)$aposta->odd;
        $valorAposta = ($postValor !== null && $postValor !== '') ? (float)str_replace(',', '.', (string)$postValor) : (float)$aposta->valor_aposta;
        $status    = ($postStatus   !== null && trim($postStatus) !== '')   ? trim($postStatus)   : $aposta->status;
        $tipo      = ($postTipo     !== null && trim($postTipo) !== '')     ? trim($postTipo)     : $aposta->tipo;

        $cashOut   = ($postCashOut !== null && trim((string)$postCashOut) !== '') ? (float)str_replace(',', '.', (string)$postCashOut) : $aposta->cash_out;

        if ($mercado === 'Handicap Asiático' || stripos($mercado, 'handicap') !== false) {
            $palpite = $this->formatHandicapPalpite($palpite, $timeCasa, $timeFora);
        }

        if (empty($timeCasa) || empty($timeFora) || empty($palpite) || $odd <= 0 || $valorAposta <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, preencha corretamente os campos obrigatórios (Times, Palpite, Odd e Valor da Simulação de Aposta).'
            ]);
        }

        if ($status === 'ANULADA') {
            $ganhosPotenciais = $valorAposta;
        } elseif ($status === 'Meio Ganha') {
            $ganhosPotenciais = round($valorAposta * (($odd + 1) / 2), 2);
        } elseif ($status === 'Meio Perdida') {
            $ganhosPotenciais = round($valorAposta * 0.5, 2);
        } elseif ($status === 'Perdida') {
            $ganhosPotenciais = 0.00;
        } else {
            $ganhosPotenciais = round($odd * $valorAposta, 2);
        }

        // Reavalia o Gatekeeper ao editar a aposta
        $fixtureId = $aposta->fixture_id ? (int)$aposta->fixture_id : null;
        $eval = $this->evaluateGatekeeper($fixtureId, $timeCasa, $timeFora, $mercado, $palpite, $odd);

        $confirmarRisco = filter_var($this->request->getPost('confirmar_risco') ?? $this->request->getPost('confirm_warning') ?? $this->request->getPost('confirm'), FILTER_VALIDATE_BOOLEAN)
                          || in_array(strtolower((string)($this->request->getPost('confirmar_risco') ?? '')), ['1', 'true', 'sim', 'yes'])
                          || in_array(strtolower((string)($this->request->getPost('confirm') ?? '')), ['1', 'true', 'sim', 'yes']);

        if ($eval['statusGatekeeper'] === 'AVISO_RISCO_OVER') {
            if (!$confirmarRisco) {
                return $this->response->setJSON([
                    'success'              => false,
                    'require_confirmation' => true,
                    'is_warning'           => true,
                    'status_gatekeeper'    => 'AVISO_RISCO_OVER',
                    'message'              => '⚠️ ' . $eval['gatekeeperMsg']
                ]);
            }
            $eval['statusGatekeeper'] = 'ALERTA_RISCO_OVER';
        }


        $dataUpdate = [
            'fixture_id'            => $eval['fixtureId'],
            'time_casa'             => $timeCasa,
            'time_fora'             => $timeFora,
            'mercado'               => $mercado,
            'palpite'               => $palpite,
            'odd'                   => $odd,
            'odd_justa'             => $eval['oddJusta'],
            'probabilidade_poisson' => $eval['probPoisson'],
            'ev_percentual'         => $eval['evPercentual'],
            'status_gatekeeper'     => $eval['statusGatekeeper'],
            'gatekeeper_category'   => $eval['gatekeeperCategory'] ?? null,
            'valor_aposta'          => $valorAposta,
            'ganhos_potenciais'     => $ganhosPotenciais,
            'cash_out'              => $cashOut,
            'tipo'                  => $tipo,
            'status'                => $status,
            'updated_at'            => date('Y-m-d H:i:s')
        ];

        try {
            $updated = $this->apostaModel->update($apostaId, $dataUpdate);
            if ($updated === false) {
                $errors = implode(', ', $this->apostaModel->errors() ?: ['Erro ao atualizar registro.']);
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Erro ao salvar no banco: ' . $errors
                ]);
            }

            // Credita o retorno na Conta Corrente caso a aposta tenha sido resolvida/ganha/cashout
            if (in_array($status, ['Ganha', 'Meio Ganha', 'ANULADA', 'Meio Perdida', 'Cashout'])) {
                $retorno = ($status === 'Cashout' && $cashOut !== null) ? $cashOut : $ganhosPotenciais;
                $this->contaCorrenteModel->creditarRetornoAposta(
                    (int)$aposta->usuario_id,
                    $apostaId,
                    (float)$retorno,
                    "Retorno Aposta #{$apostaId} ({$status})"
                );
            }

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Simulação de aposta atualizada com sucesso! ' . $eval['gatekeeperMsg'],
                'status_gatekeeper' => $eval['statusGatekeeper'],
                'odd_justa'         => $eval['oddJusta'],
                'ev_percentual'     => $eval['evPercentual']
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro no banco de dados: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Executa Cash Out na aposta (AJAX)
     */
    public function cashout($id = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: Requer tokens de consulta ativos.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta || ((int)$aposta->usuario_id !== (int)$access['user_id'] && (int)$access['user_id'] !== 146)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada ou acesso negado.'
            ]);
        }

        $valorCashout = $this->request->getPost('valor_cashout') !== null 
                        ? (float)$this->request->getPost('valor_cashout') 
                        : ($aposta->cash_out ?? $aposta->valor_aposta);

        $this->apostaModel->update($apostaId, [
            'status'     => 'Cashout',
            'cash_out'   => $valorCashout,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Credita valor do cashout na Conta Corrente
        $this->contaCorrenteModel->creditarRetornoAposta(
            (int)$aposta->usuario_id,
            $apostaId,
            (float)$valorCashout,
            "Cashout Aposta #{$apostaId}"
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Cash out realizado com sucesso! Valor resgatado: R$ ' . number_format($valorCashout, 2, ',', '.')
        ]);
    }

    /**
     * Duplica/Reapostar uma aposta existente (AJAX)
     */
    public function reapostar($id = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: Requer tokens de consulta ativos.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta || ((int)$aposta->usuario_id !== (int)$access['user_id'] && (int)$access['user_id'] !== 146)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada.'
            ]);
        }

        $fixtureId = $aposta->fixture_id ? (int)$aposta->fixture_id : null;
        $eval = $this->evaluateGatekeeper($fixtureId, $aposta->time_casa, $aposta->time_fora, $aposta->mercado, $aposta->palpite, (float)$aposta->odd);

        $confirmarRisco = filter_var($this->request->getPost('confirmar_risco') ?? $this->request->getPost('confirm_warning') ?? $this->request->getPost('confirm'), FILTER_VALIDATE_BOOLEAN)
                          || in_array(strtolower((string)($this->request->getPost('confirmar_risco') ?? '')), ['1', 'true', 'sim', 'yes'])
                          || in_array(strtolower((string)($this->request->getPost('confirm') ?? '')), ['1', 'true', 'sim', 'yes']);

        if ($eval['statusGatekeeper'] === 'AVISO_RISCO_OVER') {
            if (!$confirmarRisco) {
                return $this->response->setJSON([
                    'success'              => false,
                    'require_confirmation' => true,
                    'is_warning'           => true,
                    'status_gatekeeper'    => 'AVISO_RISCO_OVER',
                    'message'              => '⚠️ ' . $eval['gatekeeperMsg']
                ]);
            }
            $eval['statusGatekeeper'] = 'ALERTA_RISCO_OVER';
        }


        // Trava anti-duplicidade de reapostas em paralelo (janela de 10 segundos)
        $dbCheck = \Config\Database::connect();
        $recentDuplicate = $dbCheck->table('apostas')
            ->where('usuario_id', $access['user_id'])
            ->where('time_casa', $aposta->time_casa)
            ->where('time_fora', $aposta->time_fora)
            ->where('mercado', $aposta->mercado)
            ->where('palpite', $aposta->palpite)
            ->where('valor_aposta', $aposta->valor_aposta)
            ->where('criado_em >=', date('Y-m-d H:i:s', time() - 10))
            ->get()->getRow();

        if ($recentDuplicate) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Resimulação de aposta já realizada anteriormente! ' . $eval['gatekeeperMsg'],
                'id'      => $recentDuplicate->id
            ]);
        }

        $nowBr = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');
        $dataHoraJogo = $aposta->data_hora_jogo;
        if (!empty($eval['fixtureId'])) {
            $dbFix = \Config\Database::connect();
            $fixRow = $dbFix->table('fixtures_trends')->select('fixture_date')->where('fixture_id', $eval['fixtureId'])->get()->getRow();
            if (!empty($fixRow) && !empty($fixRow->fixture_date)) {
                $dataHoraJogo = $fixRow->fixture_date;
            }
        }
        if (empty($dataHoraJogo)) {
            $dataHoraJogo = $nowBr;
        }

        $novoId = $this->apostaModel->insert([
            'usuario_id'            => $access['user_id'],
            'fixture_id'            => $eval['fixtureId'],
            'time_casa'             => $aposta->time_casa,
            'time_fora'             => $aposta->time_fora,
            'mercado'               => $aposta->mercado,
            'palpite'               => $aposta->palpite,
            'odd'                   => $aposta->odd,
            'odd_justa'             => $eval['oddJusta'],
            'probabilidade_poisson' => $eval['probPoisson'],
            'ev_percentual'         => $eval['evPercentual'],
            'status_gatekeeper'     => $eval['statusGatekeeper'],
            'gatekeeper_category'   => $eval['gatekeeperCategory'] ?? null,
            'data_hora_jogo'        => $dataHoraJogo,
            'valor_aposta'          => $aposta->valor_aposta,
            'ganhos_potenciais'     => $aposta->ganhos_potenciais,
            'cash_out'              => $aposta->cash_out,
            'tipo'                  => $aposta->tipo,
            'status'                => 'Pendente',
            'criado_em'             => $nowBr
        ]);

        if ($novoId) {
            $this->contaCorrenteModel->debitarAposta(
                (int)$access['user_id'],
                (int)$novoId,
                (float)$aposta->valor_aposta,
                "Reaposta #{$novoId} ({$aposta->time_casa} x {$aposta->time_fora})"
            );
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Resimulação de aposta realizada com sucesso! ' . $eval['gatekeeperMsg'],
            'id'      => $novoId
        ]);
    }

    /**
     * Exclui aposta (AJAX)
     */
    public function delete($id = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: Requer tokens de consulta ativos.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta || ((int)$aposta->usuario_id !== (int)$access['user_id'] && (int)$access['user_id'] !== 146)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada ou acesso negado.'
            ]);
        }

        if ($aposta && $aposta->status === 'Pendente') {
            $this->contaCorrenteModel->estornarAposta(
                (int)$aposta->usuario_id,
                $apostaId,
                (float)$aposta->valor_aposta,
                "Estorno Exclusão Aposta #{$apostaId}"
            );
        }

        $this->apostaModel->delete($apostaId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Simulação de aposta removida com sucesso.'
        ]);
    }

    /**
     * Confirma uma aposta e realiza o débito do valor na conta corrente (AJAX)
     */
    public function confirmar($id = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta ativos para confirmar simulações de apostas.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        if ($apostaId <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID de simulação de aposta inválido.'
            ])->setStatusCode(400);
        }

        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada.'
            ])->setStatusCode(404);
        }

        if (!empty($aposta->status) && strtolower($aposta->status) === 'cancelada') {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Esta simulação de aposta foi cancelada e não pode ser confirmada.'
            ])->setStatusCode(400);
        }

        $userId = (int)$access['user_id'];
        if ((int)$aposta->usuario_id !== $userId && $userId !== 146) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada ou acesso negado.'
            ])->setStatusCode(403);
        }

        $db = \Config\Database::connect();
        $qExists = $db->table('conta_corrente')
            ->where('usuario_id', $userId)
            ->where('aposta_id', $apostaId)
            ->where('tipo', 'DEBITO_APOSTA')
            ->get();

        $debitoExistente = $qExists ? $qExists->getRow() : null;

        if ($debitoExistente) {
            $this->apostaModel->update($apostaId, ['confirmada' => 1]);
            $saldoAtual = $this->contaCorrenteModel->getSaldo($userId);
            return $this->response->setJSON([
                'success'           => true,
                'already_confirmed' => true,
                'message'           => "Aposta #{$apostaId} já foi confirmada e debitada anteriormente.",
                'novo_saldo'        => $saldoAtual,
                'id'                => $apostaId
            ]);
        }

        $valorAposta = (float)$aposta->valor_aposta;

        $desc = "Débito Aposta #{$apostaId} ({$aposta->time_casa} x {$aposta->time_fora} - {$aposta->palpite})";
        $resDebito = $this->contaCorrenteModel->debitarAposta($userId, $apostaId, $valorAposta, $desc);

        if (!$resDebito['success']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao processar débito na conta corrente: ' . ($resDebito['message'] ?? 'Falha de transação.')
            ]);
        }

        $novoStatus = ($aposta->status === 'Não Confirmada') ? 'Pendente' : $aposta->status;
        $this->apostaModel->update($apostaId, [
            'confirmada' => 1,
            'status'     => $novoStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $novoSaldo = $resDebito['saldo_posterior'] ?? $this->contaCorrenteModel->getSaldo($userId);

        return $this->response->setJSON([
            'success'     => true,
            'message'     => "Aposta #{$apostaId} confirmada com sucesso! R$ " . number_format($valorAposta, 2, ',', '.') . " debitado da conta corrente.",
            'novo_saldo'  => $novoSaldo,
            'id'          => $apostaId,
            'novo_status' => $novoStatus
        ]);
    }

    /**
     * Confirma um lote de apostas e realiza os débitos correspondentes na conta corrente (AJAX)
     */
    public function confirmarLote()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta ativos para confirmar simulações de apostas.'
            ])->setStatusCode(403);
        }

        $userId = (int)$access['user_id'];
        
        $ids = $this->request->getPost('ids');
        if (empty($ids)) {
            $jsonInput = $this->request->getJSON(true);
            if (!empty($jsonInput) && isset($jsonInput['ids'])) {
                $ids = $jsonInput['ids'];
            }
        }

        if (!is_array($ids) || empty($ids)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Nenhuma aposta selecionada para confirmação em lote.'
            ])->setStatusCode(400);
        }

        $cleanIds = array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0));
        if (empty($cleanIds)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'IDs de apostas inválidos para confirmação.'
            ])->setStatusCode(400);
        }

        $apostas = $this->apostaModel->whereIn('id', $cleanIds)->findAll();
        if (empty($apostas)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Nenhuma das simulações de aposta solicitadas foi encontrada.'
            ])->setStatusCode(404);
        }

        $db = \Config\Database::connect();
        $confirmedCount = 0;
        $totalDebitado = 0.00;
        $now = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');

        foreach ($apostas as $aposta) {
            // Valida permissão de acesso à aposta
            if ((int)$aposta->usuario_id !== $userId && $userId !== 146) {
                continue;
            }

            // Aposta cancelada não pode ser confirmada
            if (!empty($aposta->status) && strtolower($aposta->status) === 'cancelada') {
                continue;
            }

            // Apenas status elegíveis: 'Pendente' ou 'Não Confirmada'
            if ($aposta->status !== 'Pendente' && $aposta->status !== 'Não Confirmada') {
                continue;
            }

            $apostaId = (int)$aposta->id;
            $valorAposta = (float)$aposta->valor_aposta;

            // Verifica anti-duplicidade de débito
            $qExists = $db->table('conta_corrente')
                ->where('usuario_id', $userId)
                ->where('aposta_id', $apostaId)
                ->where('tipo', 'DEBITO_APOSTA')
                ->get();
            $debitoExistente = $qExists ? $qExists->getRow() : null;

            if (!$debitoExistente && $valorAposta > 0) {
                $desc = "Débito Aposta #{$apostaId} ({$aposta->time_casa} x {$aposta->time_fora} - {$aposta->palpite})";
                $resDebito = $this->contaCorrenteModel->debitarAposta($userId, $apostaId, $valorAposta, $desc);
                if ($resDebito['success']) {
                    $totalDebitado += $valorAposta;
                }
            }

            $novoStatus = ($aposta->status === 'Não Confirmada') ? 'Pendente' : $aposta->status;
            $this->apostaModel->update($apostaId, [
                'confirmada' => 1,
                'status'     => $novoStatus,
                'updated_at' => $now
            ]);

            $confirmedCount++;
        }

        $novoSaldo = $this->contaCorrenteModel->getSaldo($userId);

        if ($confirmedCount === 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Nenhuma aposta elegível (pendente e não confirmada) foi processada.'
            ]);
        }

        $strDebitado = number_format($totalDebitado, 2, ',', '.');
        $pluralApostas = ($confirmedCount === 1) ? 'aposta confirmada' : 'apostas confirmadas';

        return $this->response->setJSON([
            'success'         => true,
            'confirmed_count' => $confirmedCount,
            'total_debitado'  => $totalDebitado,
            'novo_saldo'      => $novoSaldo,
            'message'         => "{$confirmedCount} {$pluralApostas} com sucesso! R$ {$strDebitado} debitado da conta corrente."
        ]);
    }

    /**
     * Processa jogos encerrados do dia (Simula/dispara verificação das 23:00 hs via DAG)
     */
    public function processar()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['has_tokens']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso restrito: Requer tokens de consulta ativos.'
            ])->setStatusCode(403);
        }

        $scriptPath = '/root/datalake-air-flow-delta/scripts/processar_apostas_encerradas.py';
        if (file_exists($scriptPath)) {
            $cmd = "python3 " . escapeshellarg($scriptPath) . " 2>&1";
            $output = shell_exec($cmd);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Verificação das 23:00 hs executada com sucesso!',
                'output'  => $output
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Script de processamento de simulações de apostas não encontrado no servidor.'
        ]);
    }

    /**
     * Exibe o Relatório Rank Top 5 Mercado + Palpite Vencedores (Abre em nova aba)
     */
    public function relatorioTop5()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para visualizar o relatório.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $db = \Config\Database::connect();

        // Filtro de datas enviado via GET
        $dataInicio = trim((string)$this->request->getGet('data_inicio'));
        $dataFim    = trim((string)$this->request->getGet('data_fim'));

        $dateExpr = "(CASE WHEN data_hora_jogo IS NOT NULL AND data_hora_jogo > '2000-01-01' THEN data_hora_jogo ELSE criado_em END)";

        $whereDateUser = "";
        $whereDateGeral = "";
        $whereDateSummary = "";
        $whereDateGk = "";
        $paramsUser = [$userId];
        $paramsGeral = [];
        $paramsSummary = [$userId];
        $paramsGk = [];

        if (!empty($dataInicio)) {
            $whereDateUser .= " AND {$dateExpr} >= ?";
            $whereDateGeral .= " AND {$dateExpr} >= ?";
            $whereDateSummary .= " AND {$dateExpr} >= ?";
            $whereDateGk .= " AND {$dateExpr} >= ?";
            $paramsUser[] = $dataInicio . ' 00:00:00';
            $paramsGeral[] = $dataInicio . ' 00:00:00';
            $paramsSummary[] = $dataInicio . ' 00:00:00';
            $paramsGk[] = $dataInicio . ' 00:00:00';
        }

        if (!empty($dataFim)) {
            $whereDateUser .= " AND {$dateExpr} <= ?";
            $whereDateGeral .= " AND {$dateExpr} <= ?";
            $whereDateSummary .= " AND {$dateExpr} <= ?";
            $whereDateGk .= " AND {$dateExpr} <= ?";
            $paramsUser[] = $dataFim . ' 23:59:59';
            $paramsGeral[] = $dataFim . ' 23:59:59';
            $paramsSummary[] = $dataFim . ' 23:59:59';
            $paramsGk[] = $dataFim . ' 23:59:59';
        }

        $cleanPalpiteExpr = "
            TRIM(
                CASE
                    WHEN time_casa IS NOT NULL AND TRIM(time_casa) != '' AND palpite LIKE CONCAT(TRIM(time_casa), ' %') 
                        THEN SUBSTRING(palpite, CHAR_LENGTH(TRIM(time_casa)) + 2)
                    WHEN time_fora IS NOT NULL AND TRIM(time_fora) != '' AND palpite LIKE CONCAT(TRIM(time_fora), ' %') 
                        THEN SUBSTRING(palpite, CHAR_LENGTH(TRIM(time_fora)) + 2)
                    WHEN palpite REGEXP '^[A-Za-z0-9à-úÀ-Ú\\\\.\\\\-\\\\s]+\\\\s+(\\\\+?0\\\\.0.*|\\\\+?00.*|\\\\-?\\\\d+\\\\.\\\\d+.*)$'
                         AND palpite NOT LIKE 'Menos de %' AND palpite NOT LIKE 'Mais de %' AND palpite NOT LIKE 'Over %' AND palpite NOT LIKE 'Under %'
                        THEN REGEXP_REPLACE(palpite, '^[A-Za-z0-9à-úÀ-Ú\\\\.\\\\-\\\\s]+\\\\s+(\\\\+?0\\\\.0.*|\\\\+?00.*|\\\\-?\\\\d+\\\\.\\\\d+.*)$', '$1')
                    ELSE palpite
                END
            )
        ";

        // Top 5 Combinações (Mercado + Palpite) com mais vitórias do Usuário
        $queryUser = $db->query("
            SELECT 
                mercado,
                {$cleanPalpiteExpr} as palpite,
                COUNT(*) as total_vitorias,
                SUM(valor_aposta) as total_apostado,
                SUM(ganhos_potenciais) as retorno_total,
                (SUM(ganhos_potenciais) - SUM(valor_aposta)) as lucro_liquido,
                AVG(odd) as odd_media
            FROM apostas
            WHERE usuario_id = ? AND status IN ('Ganha', 'Meio Ganha') {$whereDateUser}
            GROUP BY mercado, 2
            ORDER BY total_vitorias DESC, lucro_liquido DESC
            LIMIT 5
        ", $paramsUser);

        $top5Usuario = $queryUser->getResultArray();

        // Top 5 Combinações da Plataforma (Geral)
        $sqlGeral = "
            SELECT 
                mercado,
                {$cleanPalpiteExpr} as palpite,
                COUNT(*) as total_vitorias,
                SUM(valor_aposta) as total_apostado,
                SUM(ganhos_potenciais) as retorno_total,
                (SUM(ganhos_potenciais) - SUM(valor_aposta)) as lucro_liquido,
                AVG(odd) as odd_media
            FROM apostas
            WHERE status IN ('Ganha', 'Meio Ganha') {$whereDateGeral}
            GROUP BY mercado, 2
            ORDER BY total_vitorias DESC, lucro_liquido DESC
            LIMIT 5
        ";
        $queryGeral = !empty($paramsGeral) ? $db->query($sqlGeral, $paramsGeral) : $db->query($sqlGeral);

        $top5Geral = $queryGeral->getResultArray();

        // Resumo estatístico e Métricas de Performance do Usuário
        $rawSummary = $db->query("
            SELECT 
                COUNT(*) as total_apostas,
                SUM(CASE WHEN status IN ('Ganha', 'Meio Ganha', 'Meio Perdida', 'Perdida', 'ANULADA') THEN 1 ELSE 0 END) as total_encerradas,
                SUM(CASE WHEN status = 'Ganha' THEN 1 ELSE 0 END) as count_ganha_pura,
                SUM(CASE WHEN status = 'Meio Ganha' THEN 1 ELSE 0 END) as count_meio_ganha,
                SUM(CASE WHEN status = 'Meio Perdida' THEN 1 ELSE 0 END) as count_meio_perdida,
                SUM(CASE WHEN status = 'Perdida' THEN 1 ELSE 0 END) as count_perdida_pura,
                SUM(CASE WHEN status = 'ANULADA' THEN 1 ELSE 0 END) as total_anuladas,
                COALESCE(SUM(CASE 
                    WHEN status IN ('Ganha', 'Meio Ganha', 'Meio Perdida', 'ANULADA') THEN ganhos_potenciais 
                    ELSE 0 
                END), 0) as retorno_ganhas,
                COALESCE(SUM(valor_aposta), 0) as total_investido,
                COALESCE(SUM(CASE WHEN status IN ('Ganha', 'Meio Ganha', 'Meio Perdida', 'Perdida', 'ANULADA') THEN valor_aposta ELSE 0 END), 0) as total_investido_encerradas,
                COALESCE(SUM(CASE 
                    WHEN status IN ('Ganha', 'Meio Ganha', 'Meio Perdida', 'Perdida', 'ANULADA') THEN (odd * valor_aposta) 
                    ELSE 0 
                END), 0) as soma_odd_ponderada
            FROM apostas
            WHERE usuario_id = ? {$whereDateSummary}
        ", $paramsSummary)->getRowArray();

        $totApostas    = (int)($rawSummary['total_apostas'] ?? 0);
        $totEncerradas = (int)($rawSummary['total_encerradas'] ?? 0);
        $cntGanhaPura  = (int)($rawSummary['count_ganha_pura'] ?? 0);
        $cntMeioGanha  = (int)($rawSummary['count_meio_ganha'] ?? 0);
        $cntMeioPerdida= (int)($rawSummary['count_meio_perdida'] ?? 0);
        $cntPerdidaPura= (int)($rawSummary['count_perdida_pura'] ?? 0);
        $totAnuladas   = (int)($rawSummary['total_anuladas'] ?? 0);

        $totGanhas     = $cntGanhaPura + $cntMeioGanha;
        $totPerdidas   = $cntPerdidaPura + $cntMeioPerdida;
        $retornoGanhas = (float)($rawSummary['retorno_ganhas'] ?? 0.0);
        $totInvestido  = (float)($rawSummary['total_investido'] ?? 0.0);
        $totInvestEnc  = (float)($rawSummary['total_investido_encerradas'] ?? 0.0);
        $somaOddPond   = (float)($rawSummary['soma_odd_ponderada'] ?? 0.0);

        $baseInvestida = ($totInvestEnc > 0) ? $totInvestEnc : $totInvestido;
        $lucroLiquido  = $retornoGanhas - $baseInvestida;
        $roiPercentual = ($baseInvestida > 0) ? round(($lucroLiquido / $baseInvestida) * 100, 2) : 0.0;
        
        $totDecididas  = $cntGanhaPura + $cntMeioGanha + $cntMeioPerdida + $cntPerdidaPura;
        // Abordagem Fracionada: Ganha=1.0, Meio Ganha=0.75, Meio Perdida=0.25 (stake salva), Perdida=0.0
        $pontosVitorias= ($cntGanhaPura * 1.0) + ($cntMeioGanha * 0.75) + ($cntMeioPerdida * 0.25);
        $winRate       = ($totDecididas > 0) ? round(($pontosVitorias / $totDecididas) * 100, 2) : 0.0;

        $oddMedia      = ($totInvestEnc > 0) ? round($somaOddPond / $totInvestEnc, 2) : 1.0;
        $breakEvenRate = ($oddMedia > 0) ? round((1.0 / $oddMedia) * 100, 2) : 0.0;
        $edgePercentual= round($winRate - $breakEvenRate, 2);
        $stakeMedia    = ($totEncerradas > 0) ? round($baseInvestida / $totEncerradas, 2) : 0.0;

        // Projeções Futuras de Longo Prazo (+EV Projeção)
        $lucroEsperadoPorAposta = $stakeMedia * ($roiPercentual / 100.0);
        $projecao100  = round(100 * $lucroEsperadoPorAposta, 2);
        $projecao500  = round(500 * $lucroEsperadoPorAposta, 2);
        $projecao1000 = round(1000 * $lucroEsperadoPorAposta, 2);

        // Métricas de Range Ideal de Odd do Gatekeeper (+EV Geral para todas as modalidades)
        $sqlGk = "
            SELECT AVG(odd) as avg_odd, COUNT(*) as total_vitorias 
            FROM apostas 
            WHERE status IN ('Ganha', 'Meio Ganha') {$whereDateGk}
        ";
        $rowGkAvg = !empty($paramsGk) ? $db->query($sqlGk, $paramsGk)->getRow() : $db->query($sqlGk)->getRow();

        $gkOddMediaVencedora = ($rowGkAvg && $rowGkAvg->avg_odd && (int)$rowGkAvg->total_vitorias > 0) 
            ? round((float)$rowGkAvg->avg_odd, 2) 
            : 1.69;

        $gkTetoMaximo = round(max(2.00, $gkOddMediaVencedora + 0.35), 2);
        $gkOddMinima  = 1.25;

        $statSummary = [
            'total_apostas'          => $totApostas,
            'total_encerradas'       => $totEncerradas,
            'total_decididas'        => $totDecididas,
            'total_ganhas'           => $totGanhas,
            'total_perdidas'         => $totPerdidas,
            'total_anuladas'         => $totAnuladas,
            'retorno_ganhas'         => $retornoGanhas,
            'total_investido'        => $totInvestido,
            'lucro_liquido'          => $lucroLiquido,
            'roi_percentual'         => $roiPercentual,
            'win_rate'               => $winRate,
            'odd_media'              => $oddMedia,
            'break_even_rate'        => $breakEvenRate,
            'edge_percentual'        => $edgePercentual,
            'stake_media'            => $stakeMedia,
            'projecao_100'           => $projecao100,
            'projecao_500'           => $projecao500,
            'projecao_1000'          => $projecao1000,
            'gk_odd_media_vencedora' => $gkOddMediaVencedora,
            'gk_teto_maximo'         => $gkTetoMaximo,
            'gk_odd_minima'          => $gkOddMinima,
        ];

        $data = [
            'title'       => 'Relatório Rank Top 5 | Mercados & Palpites Vencedores',
            'user'        => $access['user'],
            'top5Usuario' => $top5Usuario,
            'top5Geral'   => $top5Geral,
            'statSummary' => $statSummary,
            'dataInicio'  => $dataInicio,
            'dataFim'     => $dataFim
        ];

        return view('header', $data)
             . view('apostas/relatorio_top5', $data)
             . view('footer');
    }

    /**
     * Exibe o novo Relatório de Diagnóstico de Apostas Perdidas com Groq AI
     */
    public function relatorioIaPerdas()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar o relatório de perdas.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $db = \Config\Database::connect();

        $startDate = $this->request->getVar('start_date');
        $endDate   = $this->request->getVar('end_date');

        if (empty($startDate) && empty($endDate)) {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-14 days'));
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

        // Buscar apostas perdidas ou meio perdidas no período
        $sql = "
            SELECT 
                a.*,
                f.league_name,
                f.prediction_text,
                f.ah_suggestion,
                f.ah_confidence,
                f.ah_reasoning,
                f.over_cards_probability,
                f.referee_name,
                f.goals_home as ft_goals_home,
                f.goals_away as ft_goals_away,
                f.yellow_cards_home,
                f.yellow_cards_away,
                f.red_cards_home,
                f.red_cards_away,
                f.corners_home,
                f.corners_away,
                f.shots_home,
                f.shots_away,
                f.xg_home,
                f.xg_away,
                f.futbol24_tip,
                f.futbol24_analysis,
                rs.average_yellow_cards,
                rs.average_red_cards,
                rs.average_fouls,
                rs.total_games as referee_total_games,
                rs.rigor_level as referee_rigor_level,
                th.avg_goals_scored as home_avg_goals_scored,
                th.avg_goals_conceded as home_avg_goals_conceded,
                th.clean_sheets_pct as home_clean_sheets_pct,
                th.avg_corners as home_avg_corners,
                th.avg_cards as home_avg_cards,
                ta.avg_goals_scored as away_avg_goals_scored,
                ta.avg_goals_conceded as away_avg_goals_conceded,
                ta.clean_sheets_pct as away_clean_sheets_pct,
                ta.avg_corners as away_avg_corners,
                ta.avg_cards as away_avg_cards
            FROM apostas a
            LEFT JOIN fixtures_trends f ON (a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id)
            LEFT JOIN referee_stats rs ON (f.referee_name IS NOT NULL AND f.referee_name = rs.name)
            LEFT JOIN team_moving_averages th ON (f.home_team_id IS NOT NULL AND f.home_team_id = th.team_id AND th.venue_type = 'home')
            LEFT JOIN team_moving_averages ta ON (f.away_team_id IS NOT NULL AND f.away_team_id = ta.team_id AND ta.venue_type = 'away')
            WHERE a.usuario_id = ?
              AND a.status IN ('Perdida', 'Meio Perdida')
              AND DATE(a.data_hora_jogo) BETWEEN ? AND ?
            ORDER BY a.data_hora_jogo DESC
        ";

        $apostasPerdidas = $db->query($sql, [$userId, $startDate, $endDate])->getResultObject();

        // Calcular sumários do período
        $totPerdidas = count($apostasPerdidas);
        $totInvestidoPerdas = 0.0;
        $prejuizoTotal = 0.0;
        $mercadosBreakdown = [
            'cartoes' => 0,
            'handicap' => 0,
            'gols' => 0,
            'outros' => 0
        ];

        foreach ($apostasPerdidas as $ap) {
            $val = (float)($ap->valor_aposta ?? 0);
            $totInvestidoPerdas += $val;

            if ($ap->status === 'Meio Perdida') {
                $prejuizoTotal += ($val * 0.5);
            } else {
                $prejuizoTotal += $val;
            }

            $merc = strtolower(($ap->mercado ?? '') . ' ' . ($ap->palpite ?? ''));
            if (strpos($merc, 'cart') !== false || strpos($merc, 'card') !== false || strpos($merc, 'amarelo') !== false) {
                $mercadosBreakdown['cartoes']++;
            } elseif (strpos($merc, 'handicap') !== false || strpos($merc, 'ah') !== false) {
                $mercadosBreakdown['handicap']++;
            } elseif (strpos($merc, 'gol') !== false || strpos($merc, 'goal') !== false || strpos($merc, 'ambas') !== false || strpos($merc, 'btts') !== false) {
                $mercadosBreakdown['gols']++;
            } else {
                $mercadosBreakdown['outros']++;
            }
        }

        $data = [
            'title'              => 'Relatório de Diagnóstico de Apostas Perdidas | Groq AI',
            'user'               => $access['user'],
            'credits'            => $access['credits'],
            'apostasPerdidas'    => $apostasPerdidas,
            'startDate'          => $startDate,
            'endDate'            => $endDate,
            'totPerdidas'        => $totPerdidas,
            'totInvestidoPerdas' => $totInvestidoPerdas,
            'prejuizoTotal'      => $prejuizoTotal,
            'mercadosBreakdown'  => $mercadosBreakdown
        ];

        return view('header', $data)
             . view('apostas/relatorio_ia_perdas', $data)
             . view('footer');
    }

    /**
     * Endpoint AJAX para Análise Individual de Aposta Perdida via Groq AI
     */
    public function analisarPerdaIa(): \CodeIgniter\HTTP\ResponseInterface
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você precisa estar logado para utilizar a análise de IA.'
            ]);
        }

        $userId = $access['user_id'];
        $apostaId = $this->request->getPost('aposta_id');

        if (empty($apostaId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID da simulação de aposta não informado.'
            ]);
        }

        $db = \Config\Database::connect();
        $userRow = $access['user'];

        if (empty($userRow->google_id)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você deve estar autenticado via conta Google para utilizar os créditos do Groq AI.'
            ]);
        }

        $credits = (int)($userRow->grok_credits ?? 0);

        // Buscar aposta com dados completos do card e estatísticas
        $sql = "
            SELECT 
                a.*,
                f.league_name,
                f.prediction_text,
                f.ah_suggestion,
                f.ah_confidence,
                f.ah_reasoning,
                f.over_cards_probability,
                f.referee_name,
                f.goals_home as ft_goals_home,
                f.goals_away as ft_goals_away,
                f.yellow_cards_home,
                f.yellow_cards_away,
                f.red_cards_home,
                f.red_cards_away,
                f.corners_home,
                f.corners_away,
                f.shots_home,
                f.shots_away,
                f.xg_home,
                f.xg_away,
                f.futbol24_tip,
                f.futbol24_analysis,
                rs.average_yellow_cards,
                rs.average_red_cards,
                rs.average_fouls,
                rs.total_games as referee_total_games,
                rs.rigor_level as referee_rigor_level,
                th.avg_goals_scored as home_avg_goals_scored,
                th.avg_goals_conceded as home_avg_goals_conceded,
                th.clean_sheets_pct as home_clean_sheets_pct,
                th.avg_corners as home_avg_corners,
                th.avg_cards as home_avg_cards,
                ta.avg_goals_scored as away_avg_goals_scored,
                ta.avg_goals_conceded as away_avg_goals_conceded,
                ta.clean_sheets_pct as away_clean_sheets_pct,
                ta.avg_corners as away_avg_corners,
                ta.avg_cards as away_avg_cards
            FROM apostas a
            LEFT JOIN fixtures_trends f ON (a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id)
            LEFT JOIN referee_stats rs ON (f.referee_name IS NOT NULL AND f.referee_name = rs.name)
            LEFT JOIN team_moving_averages th ON (f.home_team_id IS NOT NULL AND f.home_team_id = th.team_id AND th.venue_type = 'home')
            LEFT JOIN team_moving_averages ta ON (f.away_team_id IS NOT NULL AND f.away_team_id = ta.team_id AND ta.venue_type = 'away')
            WHERE a.id = ? AND a.usuario_id = ?
        ";
        $aposta = $db->query($sql, [$apostaId, $userId])->getRow();

        if (!$aposta) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Simulação de aposta não encontrada ou não pertence ao seu usuário.'
            ]);
        }

        // Se já possui análise salva no banco e não foi forçada reanálise, retorna direto sem gastar crédito
        $forceReload = $this->request->getPost('force') === '1';
        if (!empty($aposta->analise_ia_perda) && !$forceReload) {
            return $this->response->setJSON([
                'success' => true,
                'analise' => $aposta->analise_ia_perda,
                'cached'  => true,
                'credits_left' => $credits
            ]);
        }

        if ($credits <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você não possui saldo de créditos Groq suficientes para esta análise.'
            ]);
        }

        $apiKey = env('VISION_API_KEY');
        if (empty($apiKey)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Chave da API Groq não configurada no servidor.'
            ]);
        }

        // Identificar o tipo de palpite/mercado
        $mercadoFull = strtolower(($aposta->mercado ?? '') . ' ' . ($aposta->palpite ?? ''));
        $categoriaSecao = 'outros';

        if (strpos($mercadoFull, 'cart') !== false || strpos($mercadoFull, 'card') !== false || strpos($mercadoFull, 'amarelo') !== false) {
            $categoriaSecao = 'cartoes';
        } elseif (strpos($mercadoFull, 'handicap') !== false || strpos($mercadoFull, 'ah') !== false || strpos($mercadoFull, 'empate anula') !== false || strpos($mercadoFull, 'dnb') !== false) {
            $categoriaSecao = 'handicap';
        } elseif (strpos($mercadoFull, 'gol') !== false || strpos($mercadoFull, 'goal') !== false || strpos($mercadoFull, 'ambas') !== false || strpos($mercadoFull, 'btts') !== false || strpos($mercadoFull, 'over') !== false || strpos($mercadoFull, 'under') !== false) {
            $categoriaSecao = 'gols';
        }

        // Construir contexto da seção temática do Card correspondente
        $secaoCardInfo = "";
        if ($categoriaSecao === 'cartoes') {
            $secaoCardInfo = "📌 SEÇÃO DO CARD CORRESPONDENTE (MERCADO DE CARTÕES & ÁRBITRO):\n"
                . "- Expectativa Calculada de Cartões (xC): " . ($aposta->prediction_text ?? 'N/A') . "\n"
                . "- Probabilidade de Poisson Over/Under: " . ($aposta->over_cards_probability ?? 'N/A') . "%\n"
                . "- Média de Cartões Recebidos (Mandante/Visitante): " . ($aposta->home_avg_cards ?? 'N/A') . " / " . ($aposta->away_avg_cards ?? 'N/A') . "\n"
                . "- Árbitro Escalado: " . ($aposta->referee_name ?? 'Não Informado') . "\n"
                . "- Média de Amarelos do Árbitro: " . ($aposta->average_yellow_cards ?? 'N/A') . " | Faltas: " . ($aposta->average_fouls ?? 'N/A') . "\n"
                . "- Realidade da Partida (Placar de Cartões): " . ($aposta->yellow_cards_home ?? 0) . " amarelos (Casa), " . ($aposta->yellow_cards_away ?? 0) . " amarelos (Fora), " . ($aposta->red_cards_home ?? 0) . " vermelhos (Casa), " . ($aposta->red_cards_away ?? 0) . " vermelhos (Fora).\n";
        } elseif ($categoriaSecao === 'handicap' || $categoriaSecao === 'gols') {
            $secaoCardInfo = "📌 SEÇÃO DO CARD CORRESPONDENTE (MERCADO DE GOLS & HANDICAP ASIÁTICO):\n"
                . "- Sugestão de Handicap do Card: " . ($aposta->ah_suggestion ?? 'N/A') . " (Confiança: " . ($aposta->ah_confidence ?? 'N/A') . "%)\n"
                . "- Raciocínio / Memória AH: " . ($aposta->ah_reasoning ?? 'N/A') . "\n"
                . "- Médias de Gols Marcados/Sofridos (Casa): " . ($aposta->home_avg_goals_scored ?? 'N/A') . " / " . ($aposta->home_avg_goals_conceded ?? 'N/A') . "\n"
                . "- Médias de Gols Marcados/Sofridos (Fora): " . ($aposta->away_avg_goals_scored ?? 'N/A') . " / " . ($aposta->away_avg_goals_conceded ?? 'N/A') . "\n"
                . "- Clean Sheets % (Casa / Fora): " . ($aposta->home_clean_sheets_pct ?? 'N/A') . "% / " . ($aposta->away_clean_sheets_pct ?? 'N/A') . "%\n"
                . "- Realidade da Partida (Placar Final): " . ($aposta->time_casa ?? 'Casa') . " " . ($aposta->ft_goals_home ?? $aposta->goals_home ?? 0) . " x " . ($aposta->ft_goals_away ?? $aposta->goals_away ?? 0) . " " . ($aposta->time_fora ?? 'Visitante') . "\n"
                . "- Métrica Expected Goals (xG): " . ($aposta->xg_home ?? 0.0) . " (Casa) x " . ($aposta->xg_away ?? 0.0) . " (Fora).\n";
        } else {
            $secaoCardInfo = "📌 SEÇÃO DO CARD CORRESPONDENTE (RESENHA EDITORIAL & ESTATÍSTICAS GERAIS):\n"
                . "- Dica Futbol24: " . ($aposta->futbol24_tip ?? 'N/A') . "\n"
                . "- Análise Editorial Futbol24: " . ($aposta->futbol24_analysis ?? 'N/A') . "\n"
                . "- Escanteios na Partida: " . ($aposta->corners_home ?? 0) . " (Casa) - " . ($aposta->corners_away ?? 0) . " (Fora)\n"
                . "- Chutes Totais na Partida: " . ($aposta->shots_home ?? 0) . " (Casa) - " . ($aposta->shots_away ?? 0) . " (Fora)\n"
                . "- Placar Final Real: " . ($aposta->goals_home ?? 0) . " x " . ($aposta->goals_away ?? 0) . "\n";
        }

        $systemPrompt = "Você é o Grok AI, um analista sênior de inteligência esportiva e gestão de risco em apostas da plataforma FootballWeb. "
            . "Sua missão é realizar um EXAME CRÍTICO focado no motivo da perda da aposta em confronto direto entre o PALPITE EFETUADO e os dados da SEÇÃO TEMÁTICA DO CARD do jogo.\n\n"
            . "DADOS DA APOSTA REGISTRADA:\n"
            . "- Partida: {$aposta->time_casa} vs {$aposta->time_fora}\n"
            . "- Data do Jogo: {$aposta->data_hora_jogo}\n"
            . "- Mercado: {$aposta->mercado}\n"
            . "- Palpite Apostado: {$aposta->palpite}\n"
            . "- Odd Apostada: {$aposta->odd}\n"
            . "- Valor Apostado: R$ {$aposta->valor_aposta}\n"
            . "- Status Final: {$aposta->status}\n"
            . "- Resultado Detalhado Registrado: {$aposta->resultado_detalhado}\n\n"
            . $secaoCardInfo . "\n"
            . "INSTRUÇÕES OBRIGATÓRIAS PARA SUA RESPOSTA (FORMATO MARKDOWN ESTRUTURADO):\n"
            . "1. **🔍 Diagnóstico Crítico (Palpite vs Seção do Card):** Compare diretamente o palpite apostado ('{$aposta->palpite}') com as projeções contidas na seção temática do Card informada acima. Aponte exatamente onde ocorreu a divergência entre a projeção pré-jogo e a realidade do jogo.\n"
            . "2. **⚡ Motivo Provável do Red:** Explique a causa técnica principal da perda (ex: desvio estatístico de Poisson, arbitragem atípica, expulsão prematura, falta de eficiência de gols/xG, variação em clássico, etc.).\n"
            . "3. **🎯 Ajustes Recomendados nos Critérios para Próximos Jogos:** Forneça de 2 a 3 recomendações práticas, quantitativas e objetivas de ajustes de parâmetros (ex: aumentar margem de segurança no Under cartões quando o árbitro tiver média X, evitar linha Y em handicap fora de casa, recalibrar tolerância em jogos decisivos).";

        try {
            $client = \Config\Services::curlrequest(['http_errors' => false]);
            $apiUrl = env('VISION_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions';
            $model  = env('TEXT_API_MODEL') ?: 'openai/gpt-oss-120b';

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Faça a análise crítica detalhada da perda da aposta #{$aposta->id} ({$aposta->time_casa} x {$aposta->time_fora} - Palpite: {$aposta->palpite})."]
                    ],
                ],
                'timeout' => 35,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "Erro na API do Groq (HTTP {$statusCode})."
                ]);
            }

            $body = json_decode($response->getBody(), true);
            $aiResponse = $body['choices'][0]['message']['content'] ?? '';

            if (empty($aiResponse)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Resposta da IA retornou vazia.'
                ]);
            }

            // Debitar 1 crédito e salvar no banco
            $newCredits = max(0, $credits - 1);
            $db->table('usuario')->where('id', $userId)->update(['grok_credits' => $newCredits]);
            $db->table('apostas')->where('id', $apostaId)->update([
                'analise_ia_perda' => $aiResponse,
                'analise_ia_data' => date('Y-m-d H:i:s')
            ]);

            return $this->response->setJSON([
                'success' => true,
                'analise' => $aiResponse,
                'credits_left' => $newCredits
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao processar chamada para o Groq: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint AJAX para Análise Consolidada das Apostas Perdidas do Período via Groq AI
     */
    public function analisarPerdasConsolidadoIa(): \CodeIgniter\HTTP\ResponseInterface
    {
        $access = $this->checkAccess();

        if (!$access['authenticated'] || !$access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você precisa estar logado para utilizar a análise de IA.'
            ]);
        }

        $userId = $access['user_id'];
        $startDate = $this->request->getPost('start_date');
        $endDate   = $this->request->getPost('end_date');

        if (empty($startDate) || empty($endDate)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Datas de início e fim do período são obrigatórias.'
            ]);
        }

        $db = \Config\Database::connect();
        $userRow = $access['user'];

        if (empty($userRow->google_id)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você deve estar autenticado via conta Google para utilizar os créditos do Groq AI.'
            ]);
        }

        $credits = (int)($userRow->grok_credits ?? 0);
        if ($credits <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você não possui saldo de créditos Groq suficientes.'
            ]);
        }

        $apiKey = env('VISION_API_KEY');
        if (empty($apiKey)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Chave da API Groq não configurada no servidor.'
            ]);
        }

        // Buscar perdas do período com dados estatísticos completos
        $sql = "
            SELECT 
                a.*,
                f.prediction_text,
                f.ah_suggestion,
                f.referee_name,
                f.goals_home,
                f.goals_away,
                f.yellow_cards_home,
                f.yellow_cards_away,
                f.red_cards_home,
                f.red_cards_away,
                rs.average_yellow_cards,
                rs.average_red_cards,
                rs.average_fouls,
                th.avg_cards as home_avg_cards,
                ta.avg_cards as away_avg_cards
            FROM apostas a
            LEFT JOIN fixtures_trends f ON (a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id)
            LEFT JOIN referee_stats rs ON (f.referee_name IS NOT NULL AND f.referee_name = rs.name)
            LEFT JOIN team_moving_averages th ON (f.home_team_id IS NOT NULL AND f.home_team_id = th.team_id AND th.venue_type = 'home')
            LEFT JOIN team_moving_averages ta ON (f.away_team_id IS NOT NULL AND f.away_team_id = ta.team_id AND ta.venue_type = 'away')
            WHERE a.usuario_id = ?
              AND a.status IN ('Perdida', 'Meio Perdida')
              AND DATE(a.data_hora_jogo) BETWEEN ? AND ?
            ORDER BY a.data_hora_jogo ASC
        ";
        $perdas = $db->query($sql, [$userId, $startDate, $endDate])->getResultObject();

        if (empty($perdas)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Nenhuma simulação de aposta perdida encontrada no período selecionado.'
            ]);
        }

        $summaryText = "PANORAMA DE APOSTAS PERDIDAS NO PERÍODO ({$startDate} a {$endDate}):\n";
        $count = 1;
        foreach ($perdas as $p) {
            $summaryText .= "{$count}. [{$p->time_casa} x {$p->time_fora}] - Mercado: {$p->mercado} | Palpite: {$p->palpite} | Odd: {$p->odd} | Stake: R$ {$p->valor_aposta} | Status: {$p->status}\n"
                . "   - Card Projeção: " . ($p->prediction_text ?: $p->ah_suggestion ?: 'N/A') . "\n"
                . "   - Placar Real: " . ($p->goals_home ?? 0) . "x" . ($p->goals_away ?? 0) . " | Cartões Reais: " . (($p->yellow_cards_home ?? 0) + ($p->yellow_cards_away ?? 0)) . " amarelos, " . (($p->red_cards_home ?? 0) + ($p->red_cards_away ?? 0)) . " vermelhos\n";
            $count++;
        }

        $systemPrompt = "Você é o Grok AI, diretor de inteligência estatística e controle de risco da FootballWeb. "
            . "Abaixo está a lista consolidada de apostas que resultaram em perda ('Perdida' e 'Meio Perdida') no período de {$startDate} a {$endDate}.\n\n"
            . $summaryText . "\n\n"
            . "DIRETRIZES DE RESPOSTA (FORMATO MARKDOWN EXECUTIVO):\n"
            . "1. **📊 Diagnóstico Geral dos Padrões de Perda:** Avalie em quais mercados ou tipos de palpite concentraram-se os reds no período.\n"
            . "2. **⚖️ Análise Crítica dos Modelos do Card:** Identifique se houve falha sistemática nos modelos (ex: superestimativa de cartões Under, falha em linhas de Handicap asiático em times visitantes, distorção por zebras).\n"
            . "3. **🛠️ Plano de Ação & Recalibragem de Critérios:** Apresente 3 a 5 regras claras e reajustes de parâmetros para que os próximos palpites no sistema minimizem reds semelhantes.";

        try {
            $client = \Config\Services::curlrequest(['http_errors' => false]);
            $apiUrl = env('VISION_API_URL') ?: 'https://api.groq.com/openai/v1/chat/completions';
            $model  = env('TEXT_API_MODEL') ?: 'openai/gpt-oss-120b';

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'    => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => "Gere o relatório de diagnóstico consolidado para as " . count($perdas) . " apostas perdidas no período de {$startDate} a {$endDate}."]
                    ],
                ],
                'timeout' => 45,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => "Erro na API do Groq (HTTP {$statusCode})."
                ]);
            }

            $body = json_decode($response->getBody(), true);
            $aiResponse = $body['choices'][0]['message']['content'] ?? '';

            if (empty($aiResponse)) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Resposta da IA retornou vazia.'
                ]);
            }

            $newCredits = max(0, $credits - 1);
            $db->table('usuario')->where('id', $userId)->update(['grok_credits' => $newCredits]);

            return $this->response->setJSON([
                'success' => true,
                'analise' => $aiResponse,
                'credits_left' => $newCredits
            ]);

        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao processar chamada de consolidação no Groq: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Calcula o fatorial de um número inteiro (auxiliar para Distribuição de Poisson)
     */
    private function factorial(int $n): float
    {
        if ($n <= 1) return 1.0;
        $res = 1.0;
        for ($i = 2; $i <= $n; $i++) {
            $res *= $i;
        }
        return $res;
    }

    /**
     * Formata e garante que palpites de Handicap Asiático válidos sejam mantidos ou formatados
     */
    private function formatHandicapPalpite(string $palpite, string $timeCasa, string $timeFora): string
    {
        if (empty($palpite)) {
            return (!empty($timeCasa) ? $timeCasa : 'Handicap') . " 0.0 (Empate Anula)";
        }
        if (preg_match('/[+-]?\d+(?:[\.,]\d+)?/i', $palpite)) {
            return $palpite;
        }
        if (stripos($palpite, '0.0 (Empate Anula)') !== false || stripos($palpite, '0,0 (Empate Anula)') !== false) {
            return $palpite;
        }
        if (!empty($timeFora) && stripos($palpite, $timeFora) !== false) {
            return "{$timeFora} 0.0 (Empate Anula)";
        }
        if (!empty($timeCasa) && stripos($palpite, $timeCasa) !== false) {
            return "{$timeCasa} 0.0 (Empate Anula)";
        }
        return $palpite;
    }

    /**
     * Relatório de Eficiência de Palpites (KPIs Win Rate, Red Rate, Void Rate, Abstenção, ROI)
     * Restrito exclusivamente a partidas encerradas (FT).
     */
    public function relatorioEficiencia()
    {
        ini_set('memory_limit', '512M');
        $access = $this->checkAccess();
        $db = \Config\Database::connect();

        $startDate = $this->request->getVar('start_date');
        $endDate   = $this->request->getVar('end_date');
        $leagueFilter = $this->request->getVar('league');
        $marketFilter = $this->request->getVar('market');
        $statusFilter = $this->request->getVar('status');
        $confirmedFilter = $this->request->getVar('confirmed') ?? 'all';

        if (empty($startDate) && empty($endDate)) {
            // Se foi enviada uma requisição de filtro (market/league/status/confirmed), permite buscar Todo o Período
            if ($this->request->getVar('market') !== null || $this->request->getVar('league') !== null || $this->request->getVar('status') !== null || $this->request->getVar('confirmed') !== null) {
                $startDate = null;
                $endDate = null;
            } else {
                $tzBrt = new \DateTimeZone('America/Sao_Paulo');
                $nowBrt = new \DateTime('now', $tzBrt);
                $endDate = $nowBrt->format('Y-m-d');
                $startDt = clone $nowBrt;
                $startDt->modify('-6 days');
                $startDate = $startDt->format('Y-m-d');
            }
        } elseif (empty($startDate)) {
            $startDate = $endDate;
        } elseif (empty($endDate)) {
            $endDate = $startDate;
        }

        if (!empty($startDate) && !empty($endDate) && $startDate > $endDate) {
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
        }

        // 1. Reconciliação Contábil da Conta Corrente (Banca Real)
        $contaCorrenteStats = [
            'saldo_atual'     => 0.0,
            'total_depositos' => 0.0,
            'total_resgates'  => 0.0,
            'lucro_apostas'   => 0.0
        ];

        $userId = $access['user_id'] ?? null;
        $userCcWhere = (!empty($userId) && (int)$userId !== 146) ? "WHERE usuario_id = " . (int)$userId : "";
        $rowSaldo = $db->query("SELECT saldo_posterior FROM conta_corrente {$userCcWhere} ORDER BY id DESC LIMIT 1")->getRow();
        if ($rowSaldo) {
            $contaCorrenteStats['saldo_atual'] = (float)$rowSaldo->saldo_posterior;
        }

        $rowTotals = $db->query("
            SELECT 
                SUM(CASE WHEN tipo = 'CREDITO_ADICIONADO' THEN valor ELSE 0 END) as total_depositos,
                SUM(CASE WHEN tipo = 'RESGATE_CREDITO' THEN valor ELSE 0 END) as total_resgates,
                SUM(CASE WHEN tipo IN ('CREDITO_RETORNO_APOSTA', 'ESTORNO_APOSTA', 'DEBITO_APOSTA') THEN valor ELSE 0 END) as lucro_apostas
            FROM conta_corrente
            {$userCcWhere}
        ")->getRow();
        if ($rowTotals) {
            $contaCorrenteStats['total_depositos'] = (float)$rowTotals->total_depositos;
            $contaCorrenteStats['total_resgates']  = abs((float)$rowTotals->total_resgates);
            $contaCorrenteStats['lucro_apostas']   = (float)$rowTotals->lucro_apostas;
        }

        // 2. Consulta de Apostas da Carteira
        $builder = $db->table('apostas a')
            ->select('
                a.id as aposta_id,
                a.usuario_id,
                a.fixture_id,
                a.time_casa,
                a.time_fora,
                a.mercado,
                a.palpite,
                a.odd,
                a.odd_justa,
                a.probabilidade_poisson,
                a.valor_aposta,
                a.ganhos_potenciais,
                a.cash_out,
                a.status as aposta_status,
                a.status_gatekeeper,
                a.resultado_detalhado,
                a.data_hora_jogo,
                a.criado_em,
                a.confirmada,
                (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = "DEBITO_APOSTA") as tem_debito,
                COALESCE(NULLIF(f.league_name, ""), "Outras Ligas") as league_name,
                f.league_id,
                f.fixture_date,
                f.goals_home,
                f.goals_away,
                f.yellow_cards_home,
                f.yellow_cards_away,
                f.red_cards_home,
                f.red_cards_away,
                f.corners_home,
                f.corners_away,
                f.ah_suggestion,
                f.ah_confidence,
                f.over_cards_probability,
                f.status as game_status
            ')
            ->join('fixtures_trends f', 'a.fixture_id = f.fixture_id', 'left');

        if (!empty($userId) && (int)$userId !== 146) {
            $builder->where('a.usuario_id', (int)$userId);
        }

        // Filtro de Confirmação: Sim (padrão), Não ou Todas
        if ($confirmedFilter === '1') {
            $builder->where('(a.confirmada = 1 OR (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = "DEBITO_APOSTA") > 0)');
        } elseif ($confirmedFilter === '0') {
            $builder->where('((a.confirmada = 0 OR a.confirmada IS NULL) AND (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = "DEBITO_APOSTA") = 0)');
        }

        // Apenas apostas encerradas e resolvidas (exclui pendentes/abertas)
        $builder->where("a.status NOT IN ('Pendente', 'Aberta', 'Em Andamento')");

        if (!empty($startDate)) {
            $builder->where("(DATE(CONVERT_TZ(COALESCE(a.data_hora_jogo, a.criado_em), '+00:00', '-03:00')) >= '{$startDate}')", null, false);
        }
        if (!empty($endDate)) {
            $builder->where("(DATE(CONVERT_TZ(COALESCE(a.data_hora_jogo, a.criado_em), '+00:00', '-03:00')) <= '{$endDate}')", null, false);
        }

        if (!empty($leagueFilter)) {
            $builder->where('f.league_name', $leagueFilter);
        }

        if (!empty($marketFilter)) {
            $mFilterUpper = strtoupper(trim($marketFilter));
            if ($mFilterUpper === 'OVER') {
                $builder->where("(LOWER(a.mercado) LIKE '%over%' OR LOWER(a.palpite) LIKE '%mais%' OR LOWER(a.palpite) LIKE '%over%')");
            } elseif ($mFilterUpper === 'UNDER') {
                $builder->where("(LOWER(a.mercado) LIKE '%under%' OR LOWER(a.palpite) LIKE '%menos%' OR LOWER(a.palpite) LIKE '%under%')");
            } elseif ($mFilterUpper === 'AH_DEFENSIVE') {
                $builder->where("((LOWER(a.mercado) LIKE '%handicap%' OR LOWER(a.palpite) LIKE '%ah%') AND (a.palpite LIKE '%+%' OR a.palpite LIKE '%0.0%' OR a.palpite LIKE '% 0 %' OR a.palpite LIKE '% 0 AH%' OR a.palpite REGEXP '\\\+[0-9]|0(\\\\.0)? AH| 0.0'))");
            } elseif ($mFilterUpper === 'AH_AGGRESSIVE' || $mFilterUpper === 'AH_MINUS' || $mFilterUpper === '-AH') {
                $builder->where("((LOWER(a.mercado) LIKE '%handicap%' OR LOWER(a.palpite) LIKE '%ah%') AND (a.palpite LIKE '%-%' OR a.palpite REGEXP '-[0-9]'))");
            } elseif ($mFilterUpper === 'AH_MINUS_025') {
                $builder->where("((LOWER(a.mercado) LIKE '%handicap%' OR LOWER(a.palpite) LIKE '%ah%') AND (a.palpite LIKE '%-0.25%' OR a.palpite LIKE '%-0,25%'))");
            } elseif ($mFilterUpper === 'AH_DNB') {
                $builder->where("((LOWER(a.mercado) LIKE '%handicap%' OR LOWER(a.palpite) LIKE '%ah%') AND (a.palpite LIKE '%0.0%' OR a.palpite LIKE '% 0 %' OR a.palpite LIKE '% 0 AH%' OR a.palpite REGEXP ' 0(\\\\.0)? AH'))");
            } elseif ($mFilterUpper === 'AH_PLUS' || $mFilterUpper === '+AH') {
                $builder->where("((LOWER(a.mercado) LIKE '%handicap%' OR LOWER(a.palpite) LIKE '%ah%') AND (a.palpite LIKE '%+%' OR a.palpite REGEXP '\\\+[0-9]'))");
            } else {
                $escaped = $db->escapeLikeString($marketFilter);
                $builder->where("(LOWER(a.mercado) LIKE LOWER('%" . $escaped . "%') OR LOWER(a.palpite) LIKE LOWER('%" . $escaped . "%'))");
            }
        }

        if (!empty($statusFilter)) {
            $sf = strtoupper(trim($statusFilter));
            if ($sf === 'GREEN' || $sf === 'GANHA') {
                $builder->whereIn('a.status', ['Ganha', 'Meio Ganha', 'GREEN']);
            } elseif ($sf === 'RED' || $sf === 'PERDIDA') {
                $builder->whereIn('a.status', ['Perdida', 'Meio Perdida', 'RED']);
            } elseif ($sf === 'VOID' || $sf === 'ANULADA') {
                $builder->whereIn('a.status', ['Anulada', 'ANULADA', 'VOID']);
            } elseif ($sf === 'NO_BET') {
                $builder->where('a.status_gatekeeper', 'NO_BET');
            }
        }

        $builder->orderBy('a.data_hora_jogo', 'DESC');
        $builder->orderBy('a.id', 'DESC');
        $rawApostas = $builder->get(1000)->getResultObject();

        // Buscar lista de Ligas distintas para o filtro
        $ligasBuilder = $db->table('apostas a')
            ->select('DISTINCT(COALESCE(NULLIF(f.league_name, ""), "Outras Ligas")) as league_name')
            ->join('fixtures_trends f', 'a.fixture_id = f.fixture_id', 'left');
        if (!empty($userId) && (int)$userId !== 146) {
            $ligasBuilder->where('a.usuario_id', (int)$userId);
        }
        if ($confirmedFilter === '1') {
            $ligasBuilder->where('(a.confirmada = 1 OR (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = "DEBITO_APOSTA") > 0)');
        } elseif ($confirmedFilter === '0') {
            $ligasBuilder->where('((a.confirmada = 0 OR a.confirmada IS NULL) AND (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = a.id AND cc.tipo = "DEBITO_APOSTA") = 0)');
        }
        $ligas = $ligasBuilder
            ->orderBy('league_name', 'ASC')
            ->get()->getResultObject();

        // Data de hoje no fuso horário BRT (São Paulo)
        $todayBrt = (new \DateTime('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
        $isSingleDayToday = (!empty($startDate) && $startDate === $todayBrt && !empty($endDate) && $endDate === $todayBrt);

        // Inicialização Obrigatória de Variáveis Numéricas (Regra 8)
        $totalAnalisados = count($rawApostas);
        $greenCount = 0;
        $redCount = 0;
        $voidCount = 0;
        $noBetCount = 0;
        $pendingCount = 0;

        $unidadesApostadas = 0.0;
        $lucroPrejuizoUnidades = 0.0;
        $totalApostadoReal = 0.0;
        $lucroLiquidoReal = 0.0;

        $allGreenCount = 0;
        $allRedCount = 0;
        $allVoidCount = 0;
        $allWinWeight = 0.0;
        $allDecidedCount = 0;
        $allUnidades = 0.0;
        $allUnidadesDelta = 0.0;
        $allApostado = 0.0;
        $allLucro = 0.0;

        $closedGreenCount = 0;
        $closedRedCount = 0;
        $closedVoidCount = 0;
        $closedWinWeight = 0.0;
        $closedDecidedCount = 0;
        $closedUnidades = 0.0;
        $closedUnidadesDelta = 0.0;
        $closedApostado = 0.0;
        $closedLucro = 0.0;
        $closedBetsCount = 0;

        $openApostado = 0.0;
        $openPartialLucro = 0.0;
        $openBetsCount = 0;

        $somaProbProjetada = 0.0;
        $countProbValida = 0;

        // Estrutura de Segmentação de Risco
        $segmentacao = [
            'defensivo' => [
                'label'      => 'Handicap Defensivo (+AH / 0.0)',
                'icon'       => 'bi-shield-check',
                'color'      => '#38bdf8',
                'badge'      => 'bg-info text-dark',
                'total'      => 0,
                'green'      => 0,
                'red'        => 0,
                'void'       => 0,
                'unidades'   => 0.0,
                'lucro'      => 0.0,
                'winRate'    => 0.0,
                'redRate'    => 0.0,
                'cobertura'  => 0.0,
                'roi'        => 0.0
            ],
            'agressivo' => [
                'label'      => 'Handicap Agressivo (-AH)',
                'icon'       => 'bi-lightning-charge-fill',
                'color'      => '#f59e0b',
                'badge'      => 'bg-warning text-dark',
                'total'      => 0,
                'green'      => 0,
                'red'        => 0,
                'void'       => 0,
                'unidades'   => 0.0,
                'lucro'      => 0.0,
                'winRate'    => 0.0,
                'redRate'    => 0.0,
                'cobertura'  => 0.0,
                'roi'        => 0.0
            ],
            'cartoes'   => [
                'label'      => 'Total de Cartões (Over/Under)',
                'icon'       => 'bi-card-text',
                'color'      => '#a855f7',
                'badge'      => 'bg-secondary text-white',
                'total'      => 0,
                'green'      => 0,
                'red'        => 0,
                'void'       => 0,
                'unidades'   => 0.0,
                'lucro'      => 0.0,
                'winRate'    => 0.0,
                'redRate'    => 0.0,
                'cobertura'  => 0.0,
                'roi'        => 0.0
            ]
        ];

        $palpites = [];

        foreach ($rawApostas as $ap) {
            $statusRaw = trim((string)$ap->aposta_status);
            $odd = (float)($ap->odd ?? 1.85);
            if ($odd <= 1.0) $odd = 1.85;

            $stake = (float)($ap->valor_aposta ?? 10.0);
            if ($stake <= 0.0) $stake = 10.0;

            $statusNorm = 'PENDING';
            $lucroAposta = 0.0;
            $unidadeDelta = 0.0;
            $winWeight = 0.0;
            $isDecided = false;
            $category = 'PENDING';
            $ganho = (float)($ap->ganhos_potenciais ?? 0.0);
            $cashout = (float)($ap->cash_out ?? 0.0);

            $isConfirmedOrDebited = (isset($ap->confirmada) && (int)$ap->confirmada === 1) || (!empty($ap->tem_debito) && (int)$ap->tem_debito > 0);

            // 1. Contabilidade Financeira Real da Carteira (Mantém ROI e Lucro Líquido intactos)
            if (in_array($statusRaw, ['Ganha', 'GREEN'], true)) {
                $ret = ($ganho > 0.0) ? $ganho : ($stake * $odd);
                $lucroAposta = $ret - $stake;
                $unidadeDelta = ($stake > 0.0) ? ($lucroAposta / $stake) : ($odd - 1.0);
            } elseif ($statusRaw === 'Meio Ganha') {
                $fullRet = ($ganho > 0.0) ? $ganho : ($stake * $odd);
                $lucroAposta = ($fullRet - $stake) / 2.0;
                $unidadeDelta = ($stake > 0.0) ? ($lucroAposta / $stake) : (($odd - 1.0) / 2.0);
            } elseif (in_array($statusRaw, ['Perdida', 'RED'], true)) {
                $lucroAposta = -$stake;
                $unidadeDelta = -1.0;
            } elseif ($statusRaw === 'Meio Perdida') {
                $lucroAposta = -($stake / 2.0);
                $unidadeDelta = -0.5;
            } elseif (in_array($statusRaw, ['Anulada', 'ANULADA', 'VOID', 'Cancelada', 'CANCELADA'], true)) {
                $lucroAposta = 0.0;
                $unidadeDelta = 0.0;
            } elseif ($statusRaw === 'Cashout') {
                $cashVal = ($cashout > 0.0) ? $cashout : (($ganho > 0.0) ? $ganho : ($stake * $odd));
                $lucroAposta = $cashVal - $stake;
                $unidadeDelta = ($stake > 0.0) ? ($lucroAposta / $stake) : 0.0;
            }

            // 2. Avaliação Esportiva do Palpite (Acurácia de Campo da IA para Win Rate, Red Rate, Cobertura e Badges)
            $sportingStatus = null;
            $sportingWinWeight = null;
            $sportingIsDecided = false;

            if ($ap->goals_home !== null && $ap->goals_away !== null) {
                $gHome = (int)$ap->goals_home;
                $gAway = (int)$ap->goals_away;
                $palpiteLower = mb_strtolower(trim((string)$ap->palpite), 'UTF-8');
                $mercadoLower = mb_strtolower(trim((string)$ap->mercado), 'UTF-8');
                $timeCasaLower = mb_strtolower(trim((string)$ap->time_casa), 'UTF-8');
                $timeForaLower = mb_strtolower(trim((string)$ap->time_fora), 'UTF-8');

                // A. Handicap Asiático / DNB
                if (strpos($mercadoLower, 'handicap') !== false || strpos($palpiteLower, 'ah') !== false || strpos($palpiteLower, '0.0') !== false || strpos($palpiteLower, '0,0') !== false) {
                    $isAwayBet = false;
                    if (!empty($timeForaLower) && strpos($palpiteLower, $timeForaLower) !== false) {
                        $isAwayBet = true;
                    } elseif (strpos($palpiteLower, 'fora') !== false || strpos($palpiteLower, 'visitante') !== false || strpos($palpiteLower, ' 2 ') !== false) {
                        $isAwayBet = true;
                    }

                    $line = 0.0;
                    if (preg_match('/([+-]?\d+(?:[\.,]\d+)?)/', (string)$ap->palpite, $mLine)) {
                        $line = (float)str_replace(',', '.', $mLine[1]);
                    }

                    $diffGols = $isAwayBet ? ($gAway - $gHome) : ($gHome - $gAway);
                    $adj = $diffGols + $line;

                    if ($adj > 0.25) {
                        $sportingStatus = 'GREEN';
                        $sportingWinWeight = 1.0;
                        $sportingIsDecided = true;
                    } elseif (abs($adj - 0.25) < 0.01) {
                        $sportingStatus = 'MEIO_GREEN';
                        $sportingWinWeight = 0.75;
                        $sportingIsDecided = true;
                    } elseif (abs($adj) < 0.01) {
                        $sportingStatus = 'VOID';
                        $sportingWinWeight = 0.0;
                        $sportingIsDecided = false;
                    } elseif (abs($adj - (-0.25)) < 0.01) {
                        $sportingStatus = 'MEIO_RED';
                        $sportingWinWeight = 0.25;
                        $sportingIsDecided = true;
                    } else {
                        $sportingStatus = 'RED';
                        $sportingWinWeight = 0.0;
                        $sportingIsDecided = true;
                    }
                } elseif (strpos($mercadoLower, 'cart') !== false || strpos($palpiteLower, 'cart') !== false) {
                    if ($ap->yellow_cards_home !== null && $ap->yellow_cards_away !== null) {
                        $totCards = (int)$ap->yellow_cards_home + (int)$ap->yellow_cards_away + (int)($ap->red_cards_home ?? 0) + (int)($ap->red_cards_away ?? 0);
                        $thresh = 4.5;
                        if (preg_match('/(\d+(?:[\.,]\d+)?)/', (string)$ap->palpite, $mThresh)) {
                            $thresh = (float)str_replace(',', '.', $mThresh[1]);
                        }
                        $isUnder = (strpos($palpiteLower, 'menos') !== false || strpos($palpiteLower, 'under') !== false);
                        $won = $isUnder ? ($totCards < $thresh) : ($totCards > $thresh);
                        $sportingStatus = $won ? 'GREEN' : 'RED';
                        $sportingWinWeight = $won ? 1.0 : 0.0;
                        $sportingIsDecided = true;
                    }
                } elseif (strpos($mercadoLower, 'gol') !== false || strpos($palpiteLower, 'gol') !== false) {
                    $totGoals = $gHome + $gAway;
                    $thresh = 2.5;
                    if (preg_match('/(\d+(?:[\.,]\d+)?)/', (string)$ap->palpite, $mThresh)) {
                        $thresh = (float)str_replace(',', '.', $mThresh[1]);
                    }
                    $isUnder = (strpos($palpiteLower, 'menos') !== false || strpos($palpiteLower, 'under') !== false);
                    $won = $isUnder ? ($totGoals < $thresh) : ($totGoals > $thresh);
                    $sportingStatus = $won ? 'GREEN' : 'RED';
                    $sportingWinWeight = $won ? 1.0 : 0.0;
                    $sportingIsDecided = true;
                }
            }

            // Se obtivemos a avaliação esportiva do campo, ela prevalece para a eficiência do palpite
            if ($sportingStatus !== null) {
                $statusNorm = $sportingStatus;
                $winWeight = $sportingWinWeight;
                $isDecided = $sportingIsDecided;
                if ($sportingStatus === 'GREEN' || $sportingStatus === 'MEIO_GREEN') {
                    $category = 'GREEN';
                } elseif ($sportingStatus === 'RED' || $sportingStatus === 'MEIO_RED') {
                    $category = 'RED';
                } elseif ($sportingStatus === 'VOID') {
                    $category = 'VOID';
                } else {
                    $category = 'PENDING';
                }
            } else {
                // Fallback quando não há dados de placar oficial ainda
                if (in_array($statusRaw, ['Ganha', 'GREEN'], true)) {
                    $statusNorm = 'GREEN';
                    $winWeight = 1.0;
                    $isDecided = true;
                    $category = 'GREEN';
                } elseif ($statusRaw === 'Meio Ganha') {
                    $statusNorm = 'MEIO_GREEN';
                    $winWeight = 0.75;
                    $isDecided = true;
                    $category = 'GREEN';
                } elseif (in_array($statusRaw, ['Perdida', 'RED'], true)) {
                    $statusNorm = 'RED';
                    $winWeight = 0.0;
                    $isDecided = true;
                    $category = 'RED';
                } elseif ($statusRaw === 'Meio Perdida') {
                    $statusNorm = 'MEIO_RED';
                    $winWeight = 0.25;
                    $isDecided = true;
                    $category = 'RED';
                } elseif (in_array($statusRaw, ['Anulada', 'ANULADA', 'VOID', 'Cancelada', 'CANCELADA'], true)) {
                    $statusNorm = 'VOID';
                    $winWeight = 0.0;
                    $isDecided = false;
                    $category = 'VOID';
                } elseif ($statusRaw === 'Cashout') {
                    if ($lucroAposta > 0.01) {
                        $statusNorm = 'GREEN';
                        $winWeight = 1.0;
                        $isDecided = true;
                        $category = 'GREEN';
                    } elseif ($lucroAposta < -0.01) {
                        $statusNorm = 'RED';
                        $winWeight = 0.0;
                        $isDecided = true;
                        $category = 'RED';
                    } else {
                        $statusNorm = 'CASHOUT';
                        $winWeight = 0.0;
                        $isDecided = false;
                        $category = 'VOID';
                    }
                } else {
                    if ($ap->status_gatekeeper === 'NO_BET' && !$isConfirmedOrDebited) {
                        $statusNorm = 'NO_BET';
                        $category = 'NO_BET';
                    } else {
                        $statusNorm = 'PENDING';
                        $category = 'PENDING';
                    }
                }
            }

            // Converte data do jogo para fuso horário de São Paulo (BRT)
            $rawDate = $ap->data_hora_jogo ?? $ap->criado_em ?? $ap->fixture_date ?? '';
            $dtBrtFormatted = $rawDate;
            $dtBrtDay = '';
            if (!empty($rawDate)) {
                try {
                    $dtObj = new \DateTime($rawDate, new \DateTimeZone('UTC'));
                    $dtObj->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                    $dtBrtFormatted = $dtObj->format('Y-m-d H:i');
                    $dtBrtDay = $dtObj->format('Y-m-d');
                } catch (\Exception $e) {
                    $dtBrtFormatted = $rawDate;
                    $dtBrtDay = substr((string)$rawDate, 0, 10);
                }
            }

            $isBetToday = ($dtBrtDay === $todayBrt);

            if ($category === 'NO_BET') {
                $noBetCount++;
            } elseif ($category === 'PENDING') {
                $pendingCount++;
            } elseif ($category === 'GREEN') {
                $allGreenCount++;
            } elseif ($category === 'RED') {
                $allRedCount++;
            } elseif ($category === 'VOID') {
                $allVoidCount++;
            }

            if (in_array($category, ['GREEN', 'RED', 'VOID'], true)) {
                $allApostado += $stake;
                $allLucro += $lucroAposta;
                $allUnidades += 1.0;
                $allUnidadesDelta += $unidadeDelta;
                if ($isDecided) {
                    $allWinWeight += $winWeight;
                    $allDecidedCount++;
                }

                if ($isBetToday) {
                    $openApostado += $stake;
                    $openPartialLucro += $lucroAposta;
                    $openBetsCount++;
                } else {
                    $closedApostado += $stake;
                    $closedLucro += $lucroAposta;
                    $closedBetsCount++;
                    $closedUnidades += 1.0;
                    $closedUnidadesDelta += $unidadeDelta;
                    if ($category === 'GREEN') $closedGreenCount++;
                    elseif ($category === 'RED') $closedRedCount++;
                    elseif ($category === 'VOID') $closedVoidCount++;
                    if ($isDecided) {
                        $closedWinWeight += $winWeight;
                        $closedDecidedCount++;
                    }
                }
            }

            // Probabilidade projetada por Poisson
            $probProj = null;
            if (!empty($ap->probabilidade_poisson) && (float)$ap->probabilidade_poisson > 0) {
                $probProj = (float)$ap->probabilidade_poisson;
            } elseif (!empty($ap->ah_confidence) && (float)$ap->ah_confidence > 0) {
                $probProj = (float)$ap->ah_confidence;
            } elseif (!empty($ap->over_cards_probability) && (float)$ap->over_cards_probability > 0) {
                $ov = (float)$ap->over_cards_probability;
                $probProj = (stripos($ap->palpite, 'mais') !== false || stripos($ap->palpite, 'over') !== false) ? $ov : (100.0 - $ov);
            }

            if ($probProj !== null && $probProj > 0 && in_array($category, ['GREEN', 'RED', 'VOID'], true)) {
                $somaProbProjetada += $probProj;
                $countProbValida++;
            }

            // Segmentação de Risco
            $segKey = null;
            if (stripos($ap->mercado, 'handicap') !== false || stripos($ap->palpite, 'ah') !== false) {
                if (stripos($ap->palpite, '-') !== false || preg_match('/-[0-9]/', $ap->palpite)) {
                    $segKey = 'agressivo';
                } else {
                    $segKey = 'defensivo';
                }
            } elseif (stripos($ap->mercado, 'cart') !== false || stripos($ap->palpite, 'cartão') !== false || stripos($ap->palpite, 'cartao') !== false || stripos($ap->palpite, 'cartões') !== false || stripos($ap->palpite, 'cartoes') !== false || stripos($ap->palpite, 'card') !== false) {
                $segKey = 'cartoes';
            }

            if ($segKey && in_array($category, ['GREEN', 'RED', 'VOID'], true)) {
                $segmentacao[$segKey]['total']++;
                $segmentacao[$segKey]['unidades'] += 1.0;
                $segmentacao[$segKey]['lucro'] += $unidadeDelta;
                if ($category === 'GREEN') $segmentacao[$segKey]['green']++;
                elseif ($category === 'RED') $segmentacao[$segKey]['red']++;
                elseif ($category === 'VOID') $segmentacao[$segKey]['void']++;
            }

            // Objeto formatado para compatibilidade total com a view
            $itemObj = new \stdClass();
            $itemObj->id_palpite        = $ap->aposta_id;
            $itemObj->fixture_id        = $ap->fixture_id;
            $itemObj->fixture_date      = $dtBrtFormatted;
            $itemObj->home_team         = $ap->time_casa;
            $itemObj->away_team         = $ap->time_fora;
            $itemObj->league_name       = $ap->league_name;
            $itemObj->mercado           = $ap->mercado;
            $itemObj->linha_sugerida    = $ap->palpite;
            $itemObj->odd_momento       = $odd;
            $itemObj->valor_aposta      = $stake;
            $itemObj->lucro_real        = $lucroAposta;
            $itemObj->resultado_status  = $statusNorm;
            $itemObj->is_cashout        = ($statusRaw === 'Cashout');
            $itemObj->aposta_status     = $statusRaw;
            $itemObj->detalhe_resultado = $ap->resultado_detalhado ?? '';
            $itemObj->prob_projetada    = $probProj ? round($probProj, 1) : null;
            $itemObj->goals_home        = $ap->goals_home;
            $itemObj->goals_away        = $ap->goals_away;
            $itemObj->yellow_cards_home = $ap->yellow_cards_home;
            $itemObj->yellow_cards_away = $ap->yellow_cards_away;
            $itemObj->red_cards_home    = $ap->red_cards_home;
            $itemObj->red_cards_away    = $ap->red_cards_away;

            $palpites[] = $itemObj;
        }

        // Se visualização geral ou multi-dias, consolida estritamente os dias fechados (alinhado com analiseDesempenho)
        $useClosedOnly = (!$isSingleDayToday && $closedBetsCount > 0);

        $totalApostadoReal     = $useClosedOnly ? $closedApostado : $allApostado;
        $lucroLiquidoReal      = $useClosedOnly ? $closedLucro : $allLucro;
        $lucroPrejuizoUnidades = $useClosedOnly ? $closedUnidadesDelta : $allUnidadesDelta;
        $unidadesApostadas     = $useClosedOnly ? $closedUnidades : $allUnidades;
        $totalAnalisados       = $useClosedOnly ? $closedBetsCount : count($rawApostas);
        $greenCount            = $useClosedOnly ? $closedGreenCount : $allGreenCount;
        $redCount              = $useClosedOnly ? $closedRedCount : $allRedCount;
        $voidCount             = $useClosedOnly ? $closedVoidCount : $allVoidCount;
        $decidedCount          = $useClosedOnly ? $closedDecidedCount : $allDecidedCount;
        $winWeight             = $useClosedOnly ? $closedWinWeight : $allWinWeight;

        $entradasRecomendadas = $greenCount + $redCount + $voidCount;

        $winRate = $decidedCount > 0 ? round(($winWeight / $decidedCount) * 100, 1) : 0.0;
        $redRate = $decidedCount > 0 ? round((($decidedCount - $winWeight) / $decidedCount) * 100, 1) : 0.0;
        $redRateTotal = $entradasRecomendadas > 0 ? round(($redCount / $entradasRecomendadas) * 100, 1) : 0.0;
        $voidRate = $totalAnalisados > 0 ? round(($voidCount / $totalAnalisados) * 100, 1) : 0.0;
        $abstentionRate = $totalAnalisados > 0 ? round(($noBetCount / $totalAnalisados) * 100, 1) : 0.0;
        $selectionRate = $totalAnalisados > 0 ? round(($entradasRecomendadas / $totalAnalisados) * 100, 1) : 0.0;

        // ROI Real sobre o Capital Total Efetivamente Apostado
        $roiPercent = $totalApostadoReal > 0 ? round(($lucroLiquidoReal / $totalApostadoReal) * 100, 1) : 0.0;

        // Cobertura Real (Greens + Voids sobre Recomendadas)
        $coberturaReal = $entradasRecomendadas > 0 ? round((($greenCount + $voidCount) / $entradasRecomendadas) * 100, 1) : 0.0;
        // Cobertura Projetada Poisson (Média das Probabilidades Pré-Jogo)
        $coberturaProjetada = $countProbValida > 0 ? round($somaProbProjetada / $countProbValida, 2) : 0.0;
        $gapCobertura = round($coberturaReal - $coberturaProjetada, 2);

        // Status de Validação da Regra 7 (.agents/AGENTS.md)
        $regra7Status = 'DENTRO_META';
        if ($redRateTotal > 20.0) {
            $regra7Status = 'ALERTA_RISCO';
        } elseif ($redRateTotal < 10.0 && $entradasRecomendadas >= 5) {
            $regra7Status = 'EXCELENTE';
        }

        // Finalizar cálculos da segmentação
        foreach ($segmentacao as $k => &$seg) {
            $dec = $seg['green'] + $seg['red'];
            $seg['winRate'] = $dec > 0 ? round(($seg['green'] / $dec) * 100, 1) : 0.0;
            $seg['redRate'] = $dec > 0 ? round(($seg['red'] / $dec) * 100, 1) : 0.0;
            $seg['cobertura'] = $seg['total'] > 0 ? round((($seg['green'] + $seg['void']) / $seg['total']) * 100, 1) : 0.0;
            $seg['roi'] = $seg['unidades'] > 0 ? round(($seg['lucro'] / $seg['unidades']) * 100, 1) : 0.0;
        }
        unset($seg);

        $data = [
            'title'                 => 'Relatório de Eficiência de Palpites & Carteira Real',
            'user'                  => $access['user'],
            'credits'               => $access['credits'],
            'palpites'              => $palpites,
            'ligas'                 => $ligas,
            'startDate'             => $startDate,
            'endDate'               => $endDate,
            'leagueFilter'          => $leagueFilter,
            'marketFilter'          => $marketFilter,
            'statusFilter'          => $statusFilter,
            'totalAnalisados'       => $totalAnalisados,
            'entradasRecomendadas'  => $entradasRecomendadas,
            'greenCount'            => $greenCount,
            'redCount'              => $redCount,
            'voidCount'             => $voidCount,
            'noBetCount'            => $noBetCount,
            'winRate'               => $winRate,
            'redRate'               => $redRate,
            'redRateTotal'          => $redRateTotal,
            'voidRate'              => $voidRate,
            'abstentionRate'        => $abstentionRate,
            'selectionRate'         => $selectionRate,
            'coberturaReal'         => $coberturaReal,
            'coberturaProjetada'    => $coberturaProjetada,
            'gapCobertura'          => $gapCobertura,
            'regra7Status'          => $regra7Status,
            'segmentacao'           => $segmentacao,
            'lucroPrejuizoUnidades' => round($lucroPrejuizoUnidades, 2),
            'roiPercent'            => $roiPercent,
            'totalApostadoReal'     => round($totalApostadoReal, 2),
            'lucroLiquidoReal'      => round($lucroLiquidoReal, 2),
            'openApostado'          => round($openApostado, 2),
            'openPartialLucro'      => round($openPartialLucro, 2),
            'openBetsCount'         => $openBetsCount,
            'useClosedOnly'         => $useClosedOnly,
            'contaCorrenteStats'    => $contaCorrenteStats,
            'confirmedFilter'       => $confirmedFilter
        ];

        return view('header', $data)
             . view('apostas/relatorio_eficiencia', $data)
             . view('footer');
    }

    /**
     * Auxiliar interno desativado conforme Regra 12 (.agents/AGENTS.md).
     * Proibição absoluta de geração de dados sintéticos e odds fictícias retroativas pós-jogo.
     */
    private function ensurePalpitesGeradosExist(\CodeIgniter\Database\BaseConnection $db): void
    {
        // Desativado por conformidade com a Regra 12.
        return;
    }

    /**
     * Exibe o relatório de Análise de Desempenho com gráfico acumulado de valor apostado bruto e lucro líquido real.
     */
    public function analiseDesempenho()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar a análise de desempenho.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $hasTokens = $access['has_tokens'];
        $userCredits = $access['credits'];

        $apostas = [];
        if ($hasTokens) {
            $apostas = $this->apostaModel
                ->select('apostas.*, COALESCE(fixtures_trends.league_name, "Outras Ligas") as league_name, fixtures_trends.league_id, (SELECT COUNT(*) FROM conta_corrente cc WHERE cc.aposta_id = apostas.id AND cc.tipo = "DEBITO_APOSTA") as tem_debito')
                ->join('fixtures_trends', 'apostas.fixture_id = fixtures_trends.fixture_id', 'left')
                ->where('apostas.usuario_id', $userId)
                ->orderBy('apostas.data_hora_jogo', 'ASC')
                ->orderBy('apostas.criado_em', 'ASC')
                ->findAll();

            $tzUtc = new \DateTimeZone('UTC');
            $tzBrt = new \DateTimeZone('America/Sao_Paulo');

            foreach ($apostas as &$ap) {
                if (!empty($ap->data_hora_jogo)) {
                    try {
                        $dt = new \DateTime($ap->data_hora_jogo, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia       = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $ap->data_hora_jogo;
                        $ap->data_brt_dia       = substr($ap->data_hora_jogo, 0, 10);
                    }
                } elseif (!empty($ap->criado_em)) {
                    try {
                        $dt = new \DateTime($ap->criado_em, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $ap->criado_em;
                        $ap->data_brt_dia = substr($ap->criado_em, 0, 10);
                    }
                } else {
                    $ap->data_hora_jogo_brt = date('Y-m-d H:i:s');
                    $ap->data_brt_dia = date('Y-m-d');
                }

                // Resolve país e formata nome de exibição: (País ou Internacional) + Nome da Liga
                $leagueInfo = \App\Helpers\LeagueHelper::resolveCountryAndFlag($ap->league_id ?? null, $ap->league_name ?? null);
                $country = ($leagueInfo['country'] === 'INTERNACIONAL') ? 'Internacional' : $leagueInfo['country'];
                $ap->league_country = $country;
                $ap->league_flag    = $leagueInfo['flag'];

                $rawLeague = trim($ap->league_name ?? 'Outras Ligas');
                if (!empty($country) && $country !== 'Outro') {
                    if (stripos($rawLeague, "({$country})") === false) {
                        $ap->display_league_name = "({$country}) {$rawLeague}";
                    } else {
                        $ap->display_league_name = $rawLeague;
                    }
                } else {
                    $ap->display_league_name = $rawLeague;
                }
            }
            unset($ap);
        }

        $gatekeeperStats = $this->computeGatekeeperCategoryStats();

        $data = [
            'title'           => 'Análise de Desempenho | Gestão de Riscos & Palpites',
            'user'            => $access['user'],
            'hasTokens'       => $hasTokens,
            'userCredits'     => $userCredits,
            'apostas'         => $apostas,
            'gatekeeperStats' => $gatekeeperStats
        ];

        return view('header', $data)
             . view('apostas/analise_desempenho', $data)
             . view('footer');
    }

    /**
     * Calcula as métricas consolidadas de cada categoria do Gatekeeper (BET vs NO_BET),
     * incluindo volume de ocorrência, taxa de Green/Red reais nas apostas ativas e Green/Red
     * reprimidos nas partidas bloqueadas pelo Gatekeeper.
     */
    private function computeGatekeeperCategoryStats(): array
    {
        $db = \Config\Database::connect('default');
        try {
            $db->setDatabase('footballweb');
        } catch (\Throwable $e) {
            // Mantém base atual se setDatabase não for suportado
        }
        $builder = $db->table('fixtures_trends')
            ->select('fixture_id, home_team, away_team, status, goals_home, goals_away, ah_suggestion, ah_reasoning, gatekeeper_category')
            ->where('gatekeeper_category IS NOT NULL')
            ->where('gatekeeper_category !=', '');
        
        $rows = $builder->get()->getResultArray();
        $totalAll = count($rows);

        $categories = [];

        foreach ($rows as $row) {
            $cat = trim($row['gatekeeper_category'] ?? '');
            if ($cat === '') {
                continue;
            }

            $sug = trim($row['ah_suggestion'] ?? '');
            $isNoBet = (stripos($sug, 'Abstenção') !== false || stripos($sug, 'Sem Entrada') !== false || stripos($sug, 'NO_BET') !== false);
            $tipo = $isNoBet ? 'NO_BET' : 'BET';

            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'categoria'   => $cat,
                    'tipo'        => $tipo,
                    'total'       => 0,
                    'ft'          => 0,
                    'greens'      => 0,
                    'reds'        => 0,
                    'voids'       => 0,
                    'sem_palpite' => 0
                ];
            }

            $categories[$cat]['total']++;

            $status = strtoupper(trim($row['status'] ?? ''));
            if ($status === 'FT' && $row['goals_home'] !== null && $row['goals_away'] !== null) {
                $categories[$cat]['ft']++;
                $gh = (int)$row['goals_home'];
                $ga = (int)$row['goals_away'];
                $homeTeam = trim($row['home_team'] ?? '');
                $awayTeam = trim($row['away_team'] ?? '');
                $reasoning = $row['ah_reasoning'] ?? '';

                $targetTeam = null;
                $line = 0.0;

                if (!$isNoBet) {
                    // Palpite BET Oficial
                    if (preg_match('/([+-]?\d+(?:[\.,]\d+)?)/', $sug, $mLine)) {
                        $line = (float)str_replace(',', '.', $mLine[1]);
                    }
                    if ($awayTeam !== '' && stripos($sug, $awayTeam) !== false) {
                        $targetTeam = $awayTeam;
                    } else {
                        $targetTeam = $homeTeam;
                    }
                } else {
                    // NO_BET: Extração do palpite reprimido que a IA avaliou
                    if (preg_match('/Vitória do ([^:\n\r]+):/u', $reasoning, $mExp)) {
                        $candName = trim($mExp[1]);
                        if ($awayTeam !== '' && stripos($candName, $awayTeam) !== false) {
                            $targetTeam = $awayTeam;
                        } elseif ($homeTeam !== '' && stripos($candName, $homeTeam) !== false) {
                            $targetTeam = $homeTeam;
                        } else {
                            $targetTeam = $candName;
                        }
                    }

                    // Linha reprimida associada
                    if (preg_match('/\(.*?\s+([+-]?\d+(?:\.\d+)?)\s*AH/i', $reasoning, $mDnb)) {
                        $line = (float)$mDnb[1];
                    } elseif (stripos($reasoning, '0.0 AH') !== false || stripos($reasoning, 'DNB') !== false) {
                        $line = 0.0;
                    } elseif (stripos($reasoning, '-0.25 AH') !== false) {
                        $line = -0.25;
                    } elseif (stripos($reasoning, '+0.25 AH') !== false) {
                        $line = 0.25;
                    } else {
                        $line = 0.0;
                    }
                }

                if (!$targetTeam) {
                    $categories[$cat]['sem_palpite']++;
                    continue;
                }

                $isAway = ($targetTeam === $awayTeam);
                $diff = $isAway ? ($ga - $gh) : ($gh - $ga);
                $adj = $diff + $line;

                if ($adj > 0.25) {
                    $categories[$cat]['greens']++;
                } elseif (abs($adj - 0.25) < 0.01) {
                    $categories[$cat]['greens']++; // Meio green considerado vitória
                } elseif (abs($adj) < 0.01) {
                    $categories[$cat]['voids']++;
                } elseif (abs($adj - (-0.25)) < 0.01) {
                    $categories[$cat]['reds']++; // Meio red considerado perda
                } else {
                    $categories[$cat]['reds']++;
                }
            }
        }

        // Ordena categorias pelo volume total de ocorrências
        uasort($categories, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        // Formata percentuais finais
        $formatted = [];
        $totalBets = 0;
        $totalNoBets = 0;
        $totalGreens = 0;
        $totalReds = 0;
        $totalVoids = 0;
        $totalReprimidosGreen = 0;
        $totalReprimidosRed = 0;
        $totalReprimidosVoid = 0;

        foreach ($categories as $k => $c) {
            $pctOcc = ($totalAll > 0) ? round(($c['total'] / $totalAll) * 100, 1) : 0.0;
            $validFt = $c['ft'] - $c['sem_palpite'];
            $pctGreen = ($validFt > 0) ? round(($c['greens'] / $validFt) * 100, 1) : 0.0;
            $pctRed   = ($validFt > 0) ? round(($c['reds'] / $validFt) * 100, 1) : 0.0;
            $pctVoid  = ($validFt > 0) ? round(($c['voids'] / $validFt) * 100, 1) : 0.0;

            if ($c['tipo'] === 'BET') {
                $totalBets += $c['total'];
                $totalGreens += $c['greens'];
                $totalReds += $c['reds'];
                $totalVoids += $c['voids'];
            } else {
                $totalNoBets += $c['total'];
                $totalReprimidosGreen += $c['greens'];
                $totalReprimidosRed += $c['reds'];
                $totalReprimidosVoid += $c['voids'];
            }

            $formatted[] = array_merge($c, [
                'pct_ocorrencia' => $pctOcc,
                'jogos_validos'  => $validFt,
                'pct_green'      => $pctGreen,
                'pct_red'        => $pctRed,
                'pct_void'       => $pctVoid
            ]);
        }

        return [
            'total_partidas'          => $totalAll,
            'total_bets'              => $totalBets,
            'total_no_bets'           => $totalNoBets,
            'total_greens'            => $totalGreens,
            'total_reds'              => $totalReds,
            'total_voids'             => $totalVoids,
            'total_reprimidos_green'  => $totalReprimidosGreen,
            'total_reprimidos_red'    => $totalReprimidosRed,
            'total_reprimidos_void'   => $totalReprimidosVoid,
            'categorias'              => $formatted
        ];
    }

    /**
     * Endpoint AJAX para auditar e checar odds de Handicap Asiático em tempo real via API Football.
     */
    public function checarOddsAh()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Você precisa estar logado para auditar odds em tempo real.'
            ])->setStatusCode(401);
        }

        $fixtureId = $this->request->getPost('fixture_id');
        $apostaId  = $this->request->getPost('aposta_id');

        if (empty($fixtureId)) {
            $jsonInput = $this->request->getJSON(true);
            if (!empty($jsonInput['fixture_id'])) {
                $fixtureId = $jsonInput['fixture_id'];
                $apostaId  = $jsonInput['aposta_id'] ?? null;
            }
        }

        if (empty($fixtureId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Identificador da partida (fixture_id) não informado.'
            ])->setStatusCode(400);
        }

        $fixtureId = (int)$fixtureId;
        $scriptPath = '/datalake-root/scripts/checar_odds_ah_fixture.py';
        if (!file_exists($scriptPath)) {
            $scriptPath = '/root/datalake-air-flow-delta/scripts/checar_odds_ah_fixture.py';
        }

        $cmd = "python3 " . escapeshellarg($scriptPath) . " --fixture_id={$fixtureId}";
        if (!empty($apostaId)) {
            $apostaId = (int)$apostaId;
            $cmd .= " --aposta_id={$apostaId}";
        }
        $cmd .= " 2>&1";

        $output = shell_exec($cmd);
        $result = json_decode($output, true);

        if (!$result) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Erro ao processar auditoria de odds: ' . $output
            ]);
        }

        return $this->response->setJSON($result);
    }

    /**
     * Retorna a lista de notificações não lidas e recentes do usuário logado.
     */
    public function getNotificacoesNaoLidas()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated'] || !$access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'total_nao_lidas' => 0,
                'notificacoes' => []
            ]);
        }

        $userId = $access['user_id'];
        $db = \Config\Database::connect();

        // Contar total de não lidas para o usuário
        $totalNaoLidas = $db->table('notificacoes_usuario n')
            ->where('n.usuario_id', $userId)
            ->where('n.lida', 0)
            ->countAllResults();

        // Buscar as últimas 15 notificações (não lidas primeiro, depois por data mais recente)
        $notificacoes = $db->table('notificacoes_usuario n')
            ->select('n.*')
            ->where('n.usuario_id', $userId)
            ->orderBy('n.lida', 'ASC')
            ->orderBy('n.criado_em', 'DESC')
            ->limit(15)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'total_nao_lidas' => (int)$totalNaoLidas,
            'notificacoes' => $notificacoes
        ]);
    }

    /**
     * Marca uma notificação individual como lida.
     */
    public function marcarNotificacaoLida($id = null)
    {
        $access = $this->checkAccess();
        if (!$access['authenticated'] || !$access['user_id']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        $userId = $access['user_id'];
        $id = (int)$id;

        $db = \Config\Database::connect();
        $db->table('notificacoes_usuario')
            ->where('id', $id)
            ->where('usuario_id', $userId)
            ->update(['lida' => 1]);

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Marca todas as notificações do usuário como lidas.
     */
    public function marcarTodasNotificacoesLidas()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated'] || !$access['user_id']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        $userId = $access['user_id'];
        $db = \Config\Database::connect();
        $db->table('notificacoes_usuario')
            ->where('usuario_id', $userId)
            ->where('lida', 0)
            ->update(['lida' => 1]);

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Relatório Analítico de Abstenções (NO_BET)
     * Consolida dados do Gatekeeper para Handicap Asiático e Cartões Under
     */
    public function relatorioAbstencoes()
    {
        ini_set('memory_limit', '512M');
        $access = $this->checkAccess();
        $db = \Config\Database::connect();

        $modalidade = strtolower(trim((string)($this->request->getVar('modalidade') ?? 'todos')));
        if (!in_array($modalidade, ['todos', 'ah', 'cartao'])) {
            $modalidade = 'todos';
        }

        $periodo = strtolower(trim((string)($this->request->getVar('periodo') ?? '14d')));
        $startDate = $this->request->getVar('start_date');
        $endDate   = $this->request->getVar('end_date');
        $ligaFilter = $this->request->getVar('liga');

        if ($periodo === '7d') {
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate   = date('Y-m-d');
        } elseif ($periodo === '30d') {
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate   = date('Y-m-d');
        } elseif ($periodo === 'custom' && !empty($startDate) && !empty($endDate)) {
            // Mantém datas enviadas
        } else {
            // Default 14d
            $periodo   = '14d';
            $startDate = date('Y-m-d', strtotime('-14 days'));
            $endDate   = date('Y-m-d');
        }

        if (!empty($startDate) && !empty($endDate) && $startDate > $endDate) {
            $temp = $startDate;
            $startDate = $endDate;
            $endDate = $temp;
        }

        // Busca lista de Ligas disponíveis para o filtro
        $ligas = $db->table('fixtures_trends')
            ->select('DISTINCT(league_name) as league_name')
            ->where('fixture_date >=', $startDate . ' 00:00:00')
            ->where('fixture_date <=', $endDate . ' 23:59:59')
            ->where('league_name IS NOT NULL')
            ->orderBy('league_name', 'ASC')
            ->get()->getResultObject();

        // Consulta partidas no período
        $builder = $db->table('fixtures_trends')
            ->select('fixture_id, fixture_date, league_name, home_team, away_team, ah_suggestion, ah_confidence, ah_reasoning, prediction_text, over_cards_probability, status')
            ->where('fixture_date >=', $startDate . ' 00:00:00')
            ->where('fixture_date <=', $endDate . ' 23:59:59');

        if (!empty($ligaFilter)) {
            $builder->where('league_name', $ligaFilter);
        }

        $builder->orderBy('fixture_date', 'DESC');
        $fixtures = $builder->get()->getResultObject();

        // Inicialização prévia de variáveis numéricas conforme Regra 8 do repositório
        $totalJogosAnalisados = 0;
        $totalAprovados = 0;
        $totalAbstencoes = 0;
        
        $totalAhAvaliados = 0;
        $totalAhAprovados = 0;
        $totalAhAbstencoes = 0;

        $totalCardsAvaliados = 0;
        $totalCardsAprovados = 0;
        $totalCardsAbstencoes = 0;

        $motivosCount = [];
        $timelineData = [];
        $partidasLista = [];

        foreach ($fixtures as $f) {
            $fDateStr = !empty($f->fixture_date) ? substr($f->fixture_date, 0, 10) : date('Y-m-d');
            if (!isset($timelineData[$fDateStr])) {
                $timelineData[$fDateStr] = [
                    'data'      => $fDateStr,
                    'total'     => 0,
                    'nobet'     => 0,
                    'aprovado'  => 0,
                ];
            }

            // 1. Processamento de Handicap Asiático
            if ($modalidade === 'todos' || $modalidade === 'ah') {
                $sug = trim((string)($f->ah_suggestion ?? ''));
                $reason = trim((string)($f->ah_reasoning ?? ''));
                $fullAh = $reason . ' ' . $sug;
                $isAhEvaluated = (!empty($sug) || !empty($reason));

                if ($isAhEvaluated) {
                    $totalAhAvaliados++;
                    $totalJogosAnalisados++;
                    $timelineData[$fDateStr]['total']++;

                    $isAhNoBet = (
                        stripos($sug, 'absten') !== false ||
                        stripos($sug, 'sem entrada') !== false ||
                        stripos($sug, 'no_bet') !== false ||
                        stripos($sug, 'bloquead') !== false ||
                        stripos($reason, 'NO_BET') !== false
                    );

                    if ($isAhNoBet) {
                        $totalAhAbstencoes++;
                        $totalAbstencoes++;
                        $timelineData[$fDateStr]['nobet']++;

                        // Identificar motivo
                        $cat = 'Gestão de Risco Preventiva da IA';
                        $regra = 'Travas de prudência por equilíbrio excessivo ou volatilidade projetada';
                        $badgeColor = '#64748b';

                        if (preg_match('/\[Gatekeeper AH NO_BET\s*\/\s*([^\]]+)\]/i', $reason, $m)) {
                            $tag = trim($m[1]);
                            if (stripos($tag, 'Linhas Reais') !== false) {
                                $cat = 'Ausência de Linhas Reais nas Casas';
                                $regra = 'Regra 12: Proibição de odds sintéticas; sem linhas oficiais na Betano/The Odds API';
                                $badgeColor = '#ef4444';
                            } elseif (stripos($tag, 'Amostragem') !== false) {
                                $cat = 'Amostragem Recente Insuficiente (U5J < 5)';
                                $regra = 'Regra 9: Proibição de fallbacks artificiais sem 5 jogos consolidados para modelar xG';
                                $badgeColor = '#f59e0b';
                            } elseif (stripos($tag, 'Sem EV') !== false) {
                                $cat = 'Sem EV+ Mínimo (+EV < 5.0% ou Prob. Insuficiente)';
                                $regra = 'Poisson e odds de mercado não atingiram o limiar mínimo de +EV >= 5.0%';
                                $badgeColor = '#3b82f6';
                            } elseif (stripos($tag, 'Piso') !== false) {
                                $cat = 'Odd de Mercado Abaixo do Piso (< 1.50)';
                                $regra = 'Cotação líquida inferior ao piso mínimo operacional de segurança';
                                $badgeColor = '#ec4899';
                            } elseif (stripos($tag, 'Odds 1X2') !== false) {
                                $cat = 'Cotações 1X2 Ausentes de Mercado';
                                $regra = 'Partida sem cotações 1X2 de abertura precificadas pelas bookmakers';
                                $badgeColor = '#8b5cf6';
                            } elseif (stripos($tag, 'Crises') !== false) {
                                $cat = 'Duelo de Crises Severas';
                                $regra = 'Ambas as equipes em jejum/crise severa com alta imprevisibilidade técnica';
                                $badgeColor = '#d97706';
                            } elseif (stripos($tag, 'Rendimento') !== false) {
                                $cat = 'Queda Recente de Rendimento e Eficiência';
                                $regra = 'Deterioração drástica na conversão ofensiva/defensiva recente dos times';
                                $badgeColor = '#a855f7';
                            } else {
                                $cat = 'AH: ' . $tag;
                            }
                        } elseif (stripos($fullAh, 'Ausência de Linhas Reais') !== false || stripos($fullAh, 'Cotações oficiais de Handicap Asiático indisponíveis') !== false || stripos($fullAh, 'Odds de mercado indisponíveis') !== false) {
                            $cat = 'Ausência de Linhas Reais nas Casas';
                            $regra = 'Regra 12: Proibição de odds sintéticas; sem linhas oficiais na Betano/The Odds API';
                            $badgeColor = '#ef4444';
                        } elseif (stripos($fullAh, 'Amostragem Insuficiente') !== false || stripos($fullAh, 'Histórico recente incompleto') !== false || stripos($fullAh, 'Histórico U5J insuficiente') !== false) {
                            $cat = 'Amostragem Recente Insuficiente (U5J < 5)';
                            $regra = 'Regra 9: Proibição de fallbacks artificiais sem 5 jogos consolidados para modelar xG';
                            $badgeColor = '#f59e0b';
                        } elseif (stripos($fullAh, 'odd nominal esmagada') !== false || stripos($fullAh, 'odd nominal deprimida') !== false || stripos($fullAh, 'linhas agressivas') !== false) {
                            $cat = 'Odd Esmagada / Linha Agressiva Bloqueada';
                            $regra = 'Superfavorito com odd esmagada (@ 1.20-1.40); linhas esticadas bloqueadas para evitar perdas no empate';
                            $badgeColor = '#ea580c';
                        } elseif (stripos($fullAh, 'Sem EV+') !== false || stripos($fullAh, '+EV >=') !== false || stripos($fullAh, 'Falta de valor') !== false) {
                            $cat = 'Sem EV+ Mínimo (+EV < 5.0% ou Prob. Insuficiente)';
                            $regra = 'Poisson e odds de mercado não atingiram o limiar mínimo de +EV >= 5.0%';
                            $badgeColor = '#3b82f6';
                        } elseif (stripos($fullAh, 'Odds 1X2 Ausentes') !== false || stripos($fullAh, 'cotações 1X2 de mercado no banco') !== false) {
                            $cat = 'Cotações 1X2 Ausentes de Mercado';
                            $regra = 'Partida sem cotações 1X2 de abertura precificadas pelas bookmakers';
                            $badgeColor = '#8b5cf6';
                        } elseif (stripos($fullAh, 'Odd Abaixo do Piso') !== false || stripos($fullAh, 'abaixo do piso') !== false) {
                            $cat = 'Odd de Mercado Abaixo do Piso (< 1.50)';
                            $regra = 'Cotação líquida inferior ao piso mínimo operacional de segurança';
                            $badgeColor = '#ec4899';
                        } elseif (stripos($fullAh, 'Alerta de Copa') !== false || stripos($fullAh, 'ALERTA DE COPA') !== false) {
                            $cat = 'Alerta de Copa (Risco de Rodízio)';
                            $regra = 'Partida de copa eliminatória com elevado risco de time alternativo/misto';
                            $badgeColor = '#e11d48';
                        } elseif (stripos($fullAh, 'Divergência Crítica') !== false) {
                            $cat = 'Divergência Crítica (Mercado vs xG)';
                            $regra = 'Divergência severa entre a precificação da casa de apostas e as métricas de campo';
                            $badgeColor = '#0284c7';
                        }

                        $chaveMotivo = 'AH: ' . $cat;
                        if (!isset($motivosCount[$chaveMotivo])) {
                            $motivosCount[$chaveMotivo] = [
                                'nome'        => $cat,
                                'modalidade'  => 'Handicap Asiático',
                                'modalidade_key' => 'ah',
                                'count'       => 0,
                                'regra_ouro'  => $regra,
                                'color'       => $badgeColor,
                            ];
                        }
                        $motivosCount[$chaveMotivo]['count']++;

                        if (count($partidasLista) < 600) {
                            $partidasLista[] = (object)[
                                'fixture_id'   => $f->fixture_id,
                                'data'         => $f->fixture_date,
                                'liga'         => $f->league_name ?? 'Não informada',
                                'times'        => ($f->home_team ?? 'Time Casa') . ' x ' . ($f->away_team ?? 'Time Fora'),
                                'home_team'    => $f->home_team ?? 'Time Casa',
                                'away_team'    => $f->away_team ?? 'Time Fora',
                                'modalidade'   => 'Handicap Asiático',
                                'modalidade_key' => 'ah',
                                'motivo_nome'  => $cat,
                                'motivo_texto' => $reason ?: $sug,
                                'badge_color'  => $badgeColor,
                                'status'       => $f->status ?? 'NS'
                            ];
                        }
                    } else {
                        $totalAhAprovados++;
                        $totalAprovados++;
                        $timelineData[$fDateStr]['aprovado']++;
                    }
                }
            }

            // 2. Processamento de Cartões Under
            if ($modalidade === 'todos' || $modalidade === 'cartao') {
                $ptext = trim((string)($f->prediction_text ?? ''));
                $isCardEvaluated = !empty($ptext);

                if ($isCardEvaluated) {
                    $totalCardsAvaliados++;
                    if ($modalidade === 'cartao') {
                        $totalJogosAnalisados++;
                        $timelineData[$fDateStr]['total']++;
                    }

                    $isCardNoBet = (
                        stripos($ptext, 'no_bet') !== false ||
                        stripos($ptext, 'sem entrada') !== false ||
                        stripos($ptext, 'absten') !== false ||
                        stripos($ptext, 'bloquead') !== false
                    );

                    if ($isCardNoBet) {
                        $totalCardsAbstencoes++;
                        if ($modalidade === 'cartao') {
                            $totalAbstencoes++;
                            $timelineData[$fDateStr]['nobet']++;
                        }

                        $cat = 'Outro Bloqueio Preventivo de Cartões';
                        $regra = 'Travas de segurança preventiva do Gatekeeper Disciplinar';
                        $badgeColor = '#64748b';

                        if (stripos($ptext, 'zerados ou indisponíveis') !== false) {
                            $cat = 'Dados de Cartões Zerados ou Indisponíveis';
                            $regra = 'Regra 9: Proibição de fallbacks artificiais; ausência de histórico consolidado no cache';
                            $badgeColor = '#ef4444';
                        } elseif (stripos($ptext, 'Início de campeonato') !== false || stripos($ptext, '< 5 jogos com dados') !== false) {
                            $cat = 'Início de Temporada / Amostragem < 5 Jogos';
                            $regra = 'Regra 9: Menos de 5 partidas registradas na base para calcular médias móveis confiáveis';
                            $badgeColor = '#f59e0b';
                        } elseif (stripos($ptext, 'Trava de Árbitro') !== false || stripos($ptext, 'Rigor do árbitro') !== false) {
                            $cat = 'Trava de Rigor do Árbitro';
                            $regra = 'Árbitro escalado com histórico rigoroso (> 4.80 c/j), incompatível com Under';
                            $badgeColor = '#dc2626';
                        } elseif (stripos($ptext, 'Atrito Disciplinar') !== false) {
                            $cat = 'Risco de Atrito Disciplinar U5J';
                            $regra = 'Ambas as equipes em momento adverso (U5J <= 3 pts), com elevada propensão a faltas';
                            $badgeColor = '#ea580c';
                        } elseif (stripos($ptext, 'Média de cartões por time') !== false || stripos($ptext, 'inferior) com amostragem') !== false) {
                            $cat = 'Média Disciplinar Anômala (<= 1.0 c/j)';
                            $regra = 'Histórico disciplinar estatisticamente suspeito ou distorcido';
                            $badgeColor = '#8b5cf6';
                        } elseif (stripos($ptext, 'Mata-Mata') !== false || stripos($ptext, 'eliminatór') !== false) {
                            $cat = 'Mata-Mata / Confronto Eliminatório';
                            $regra = 'Partida eliminatória com alta carga emocional e risco disciplinar elevado';
                            $badgeColor = '#e11d48';
                        } elseif (stripos($ptext, 'Sem Margem') !== false) {
                            $cat = 'Sem Margem Estatística para Under';
                            $regra = 'Expectativa de cartões superior às linhas Under disponíveis no mercado';
                            $badgeColor = '#0284c7';
                        } elseif (stripos($ptext, 'Sem Árbitro') !== false || stripos($ptext, 'Árbitro não definido') !== false) {
                            $cat = 'Árbitro Não Definido a < 48h';
                            $regra = 'Confronto próximo sem confirmação da escala oficial de arbitragem';
                            $badgeColor = '#64748b';
                        }

                        $chaveMotivo = 'Cartões: ' . $cat;
                        if (!isset($motivosCount[$chaveMotivo])) {
                            $motivosCount[$chaveMotivo] = [
                                'nome'        => $cat,
                                'modalidade'  => 'Cartões Under',
                                'modalidade_key' => 'cartao',
                                'count'       => 0,
                                'regra_ouro'  => $regra,
                                'color'       => $badgeColor,
                            ];
                        }
                        $motivosCount[$chaveMotivo]['count']++;

                        if (count($partidasLista) < 600) {
                            $partidasLista[] = (object)[
                                'fixture_id'   => $f->fixture_id,
                                'data'         => $f->fixture_date,
                                'liga'         => $f->league_name ?? 'Não informada',
                                'times'        => ($f->home_team ?? 'Time Casa') . ' x ' . ($f->away_team ?? 'Time Fora'),
                                'home_team'    => $f->home_team ?? 'Time Casa',
                                'away_team'    => $f->away_team ?? 'Time Fora',
                                'modalidade'   => 'Cartões Under',
                                'modalidade_key' => 'cartao',
                                'motivo_nome'  => $cat,
                                'motivo_texto' => $ptext,
                                'badge_color'  => $badgeColor,
                                'status'       => $f->status ?? 'NS'
                            ];
                        }
                    } else {
                        $totalCardsAprovados++;
                        if ($modalidade === 'cartao') {
                            $totalAprovados++;
                            $timelineData[$fDateStr]['aprovado']++;
                        }
                    }
                }
            }
        }

        // Ordenar motivos por contagem decrescente
        uasort($motivosCount, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Calcular percentuais na tabela agregada
        $denominadorNb = ($totalAbstencoes > 0) ? $totalAbstencoes : 1;
        $denominadorTotal = ($totalJogosAnalisados > 0) ? $totalJogosAnalisados : 1;
        $motivosTabela = [];

        foreach ($motivosCount as $k => $item) {
            $pctNb = round(($item['count'] / $denominadorNb) * 100, 2);
            $pctTot = round(($item['count'] / $denominadorTotal) * 100, 2);
            $motivosTabela[] = (object)[
                'nome'        => $item['nome'],
                'modalidade'  => $item['modalidade'],
                'modalidade_key' => $item['modalidade_key'],
                'count'       => $item['count'],
                'pct_nb'      => $pctNb,
                'pct_total'   => $pctTot,
                'regra_ouro'  => $item['regra_ouro'],
                'color'       => $item['color']
            ];
        }

        // Taxas Globais
        $taxaAbstencaoGlobal = ($totalJogosAnalisados > 0) ? round(($totalAbstencoes / $totalJogosAnalisados) * 100, 2) : 0.0;
        $taxaAprovacaoGlobal = ($totalJogosAnalisados > 0) ? round(($totalAprovados / $totalJogosAnalisados) * 100, 2) : 0.0;

        $taxaAhAbstencao = ($totalAhAvaliados > 0) ? round(($totalAhAbstencoes / $totalAhAvaliados) * 100, 2) : 0.0;
        $taxaCardsAbstencao = ($totalCardsAvaliados > 0) ? round(($totalCardsAbstencoes / $totalCardsAvaliados) * 100, 2) : 0.0;

        $principalGargalo = !empty($motivosTabela) ? $motivosTabela[0] : null;

        // Montar dados para os gráficos Chart.js
        $chartLabels = [];
        $chartCounts = [];
        $chartColors = [];
        $chartPcts   = [];

        foreach (array_slice($motivosTabela, 0, 8) as $m) {
            $chartLabels[] = (strlen($m->nome) > 28) ? substr($m->nome, 0, 25) . '...' : $m->nome;
            $chartCounts[] = $m->count;
            $chartColors[] = $m->color;
            $chartPcts[]   = $m->pct_nb;
        }

        // Se houver mais de 8 motivos, agrupa o restante em "Outros"
        if (count($motivosTabela) > 8) {
            $somaOutros = 0;
            $somaOutrosPct = 0.0;
            foreach (array_slice($motivosTabela, 8) as $m) {
                $somaOutros += $m->count;
                $somaOutrosPct += $m->pct_nb;
            }
            if ($somaOutros > 0) {
                $chartLabels[] = 'Demais Motivos Combinados';
                $chartCounts[] = $somaOutros;
                $chartColors[] = '#94a3b8';
                $chartPcts[]   = round($somaOutrosPct, 2);
            }
        }

        // Ordenar timeline por data cronológica crescente
        ksort($timelineData);
        $timelineDates = [];
        $timelineNb    = [];
        $timelineAp    = [];

        foreach ($timelineData as $tDate => $tVals) {
            $timelineDates[] = date('d/m', strtotime($tDate));
            $timelineNb[]    = $tVals['nobet'];
            $timelineAp[]    = $tVals['aprovado'];
        }

        $data = [
            'modalidade'           => $modalidade,
            'periodo'              => $periodo,
            'startDate'            => $startDate,
            'endDate'              => $endDate,
            'ligaFilter'           => $ligaFilter,
            'ligas'                => $ligas,
            'totalJogosAnalisados' => $totalJogosAnalisados,
            'totalAbstencoes'      => $totalAbstencoes,
            'totalAprovados'       => $totalAprovados,
            'taxaAbstencaoGlobal'  => $taxaAbstencaoGlobal,
            'taxaAprovacaoGlobal'  => $taxaAprovacaoGlobal,
            'totalAhAvaliados'     => $totalAhAvaliados,
            'totalAhAbstencoes'    => $totalAhAbstencoes,
            'totalAhAprovados'     => $totalAhAprovados,
            'taxaAhAbstencao'      => $taxaAhAbstencao,
            'totalCardsAvaliados'  => $totalCardsAvaliados,
            'totalCardsAbstencoes' => $totalCardsAbstencoes,
            'totalCardsAprovados'  => $totalCardsAprovados,
            'taxaCardsAbstencao'   => $taxaCardsAbstencao,
            'principalGargalo'     => $principalGargalo,
            'motivosTabela'        => $motivosTabela,
            'partidasLista'        => $partidasLista,
            'chartLabelsJson'      => json_encode($chartLabels),
            'chartCountsJson'      => json_encode($chartCounts),
            'chartColorsJson'      => json_encode($chartColors),
            'chartPctsJson'        => json_encode($chartPcts),
            'timelineDatesJson'    => json_encode($timelineDates),
            'timelineNbJson'       => json_encode($timelineNb),
            'timelineApJson'       => json_encode($timelineAp),
        ];

        return view('header', $data)
             . view('apostas/relatorio_abstencoes', $data)
             . view('footer');
    }
}


