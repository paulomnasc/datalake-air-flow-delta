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
            $db = \Config\Database::connect();
            $apostas = $db->query("
                SELECT 
                    a.*,
                    f.goals_home,
                    f.goals_away,
                    f.status as fixture_status
                FROM apostas a
                LEFT JOIN fixtures_trends f ON (a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id)
                WHERE a.usuario_id = ?
                ORDER BY a.criado_em DESC
            ", [$userId])->getResultObject();

            $tzUtc = new \DateTimeZone('UTC');
            $tzBrt = new \DateTimeZone('America/Sao_Paulo');

            foreach ($apostas as &$ap) {
                $dateToConvert = !empty($ap->data_hora_jogo) ? $ap->data_hora_jogo : ($ap->criado_em ?? null);
                if (!empty($dateToConvert)) {
                    try {
                        $dt = new \DateTime($dateToConvert, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $dateToConvert;
                        $ap->data_brt_dia = substr($dateToConvert, 0, 10);
                    }
                } else {
                    $ap->data_hora_jogo_brt = date('Y-m-d H:i:s');
                    $ap->data_brt_dia = date('Y-m-d');
                }
            }
            unset($ap);

            $resumo  = $this->apostaModel->getResumoUsuario($userId);
        }

        $data = [
            'title'       => 'Minhas Simulações de Apostas | Gestão de Riscos & Palpites',
            'hasTokens'   => $hasTokens,
            'userCredits' => $userCredits,
            'apostas'     => $apostas,
            'resumo'      => $resumo,
            'fixtures'    => $fixtures,
            'user'        => $access['user']
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
        $odd             = (float)$this->request->getPost('odd');
        $valorAposta     = (float)$this->request->getPost('valor_aposta');
        $fixtureId       = $this->request->getPost('fixture_id') ? (int)$this->request->getPost('fixture_id') : null;
        $dataHoraInput   = trim($this->request->getPost('data_hora_jogo') ?? '');
        $tipo            = trim($this->request->getPost('tipo') ?? 'Simples');
        $status          = trim($this->request->getPost('status') ?? 'Pendente');
        $cashOut         = $this->request->getPost('cash_out') !== null && $this->request->getPost('cash_out') !== '' 
                           ? (float)$this->request->getPost('cash_out') : null;

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
        } elseif ($statusGatekeeper === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Simulação de aposta recusada pelo Gatekeeper! ' . $gatekeeperMsg
            ]);
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
            'data_hora_jogo'        => $dataHoraJogo,
            'valor_aposta'          => $valorAposta,
            'ganhos_potenciais'     => $ganhosPotenciais,
            'cash_out'              => $cashOut,
            'tipo'                  => $tipo,
            'status'                => $status,
            'confirmada'            => $confirmarDebitar ? 1 : 0,
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
     * Reavalia o Gatekeeper para uma aposta (+EV, Odd Justa, Poisson e Teto Dinâmico de Segurança)
     */
    private function evaluateGatekeeper(?int $fixtureId, string $timeCasa, string $timeFora, string $mercado, string $palpite, float $odd): array
    {
        $oddJusta = null;
        $probPoisson = null;
        $evPercentual = null;
        $statusGatekeeper = 'NAO_ANALISADO';
        $gatekeeperMsg = 'Simulação de aposta sem análise de estatísticas.';

        $isOver = (stripos($palpite, 'over') !== false || stripos($palpite, 'mais') !== false);
        $isCartoes = (stripos($mercado, 'cartõ') !== false || stripos($mercado, 'card') !== false);

        // AVISO DE RISCO GATEKEEPER (Estratégia Exclusiva Under / Anti-Over)
        if ($isOver || ($isCartoes && $isOver)) {
            $statusGatekeeper = 'AVISO_RISCO_OVER';
            $gatekeeperMsg = "Alerta de Risco Gatekeeper (Estratégia Exclusiva Under): Simulações de apostas no mercado 'Over / Mais de' possuem elevado risco de perda e volatilidade estatística. Apenas apostas 'Under / Menos de' são recomendadas pelo modelo. Deseja prosseguir mesmo com o risco apontado?";
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
        }

        if (!$isCartoes) {
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
        }

        // TRAVA RIGOROSA DE SEGURANÇA POR LINHA MÍNIMA (Trava de Segurança Linha Mínima de 1.15)
        preg_match('/(\d+\.\d+|\d+)/', $palpite, $matchesLineCheck);
        $lineCheck = !empty($matchesLineCheck[1]) ? (float)$matchesLineCheck[1] : 5.5;

        if ($lineCheck < 1.15) {
            $statusGatekeeper = 'NO_BET';
            $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (Trava de Segurança Linha Mínima): Simulações de apostas com linhas inferiores a 1.15 são bloqueadas pelo modelo por elevado risco.";
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
        }

        $db = \Config\Database::connect();

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

        if ($fixture && !empty($fixture->prediction_text)) {
            preg_match('/xC(?::|\s+elevado)?\s*\(?(\d+\.\d+|\d+)/i', $fixture->prediction_text, $matchesXc);
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

                // 2. MATRIZ DINÂMICA DE RISCO (Odd vs Probabilidade Mínima Poisson + Margem EV)
                $isUnknownRef = false;
                if (!empty($fixture->referee_name) && (stripos($fixture->referee_name, 'Não Informado') !== false || stripos($fixture->referee_name, 'Desconhecido') !== false)) {
                    $isUnknownRef = true;
                }

                // Definição dos limiares da Matriz Dinâmica
                if ($odd <= 1.55) {
                    $minProbExigida = 50.0;
                    $minEvExigido   = 0.0;
                    $faixaRisco     = "Conservadora (Odd <= 1.55)";
                } elseif ($odd <= 1.75) {
                    $minProbExigida = 60.0;
                    $minEvExigido   = 5.0;
                    $faixaRisco     = "Intermediária (Odd 1.56 - 1.75)";
                } else {
                    $minProbExigida = 65.0;
                    $minEvExigido   = 10.0;
                    $faixaRisco     = "Agressiva (Odd > 1.75)";
                }

                // Ajuste de trava se Árbitro não estiver cadastrado na API-Football (+5% prob exigida)
                if ($isUnknownRef) {
                    $minProbExigida += 5.0;
                    $minEvExigido   += 3.0;
                }

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
                if ($lineCheck < 5.5 && ($xc === null || $xc > 3.30 || $probPoisson < 75.0)) {
                    $statusGatekeeper = 'NO_BET';
                    $xcFormatted = ($xc !== null) ? $xc : 'N/A';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Entrada na linha Under 4.5 exige Expectativa (xC) <= 3.30 cartões (Atual: {$xcFormatted}) e Probabilidade Poisson >= 75.0% (Atual: {$probPoisson}%).{$duplicidadeMsg}";
                } elseif ($odd > $maxAllowedOdd) {
                    $statusGatekeeper = 'NO_BET';
                    $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Odd da casa ({$odd}) excede o teto dinâmico de segurança ({$maxAllowedOdd}) derivado da média histórica de vitórias ({$avgWinningOdd}).{$duplicidadeMsg}";
                } elseif ($evPercentual !== null && $evPercentual >= $minEvExigido && $probPoisson >= $minProbExigida) {
                    $statusGatekeeper = 'APROVADO';
                    $refMsg = $isUnknownRef ? " | Árbitro: Genérico (+5% Rigor Exigido)" : "";
                    $gatekeeperMsg = "Gatekeeper Green Light (+EV): Faixa {$faixaRisco} | Odd Real ({$odd}) >= Odd Justa ({$oddJusta}) | EV: +{$evPercentual}% (Mínimo: +{$minEvExigido}%) | Prob. Poisson: {$probPoisson}% (Mínimo: {$minProbExigida}%){$refMsg} | Teto: {$maxAllowedOdd}.{$duplicidadeMsg}";
                } else {
                    $statusGatekeeper = 'NO_BET';
                    if ($evPercentual !== null && $evPercentual < $minEvExigido) {
                        $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Faixa {$faixaRisco} exige EV mínimo de +{$minEvExigido}% (EV Atual: {$evPercentual}%).{$duplicidadeMsg}";
                    } else {
                        $refMsg = $isUnknownRef ? " (Árbitro não informado na API exige +5% de margem)" : "";
                        $gatekeeperMsg = "Aviso Gatekeeper (NO_BET): Probabilidade Poisson ({$probPoisson}%) abaixo do mínimo exigido ({$minProbExigida}%) para a Faixa {$faixaRisco}{$refMsg}.{$duplicidadeMsg}";
                    }
                }
            }
        }

        return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
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
        $odd       = ($postOdd !== null && $postOdd !== '') ? (float)$postOdd : (float)$aposta->odd;
        $valorAposta = ($postValor !== null && $postValor !== '') ? (float)$postValor : (float)$aposta->valor_aposta;
        $status    = ($postStatus   !== null && trim($postStatus) !== '')   ? trim($postStatus)   : $aposta->status;
        $tipo      = ($postTipo     !== null && trim($postTipo) !== '')     ? trim($postTipo)     : $aposta->tipo;

        $cashOut   = ($postCashOut !== null && trim((string)$postCashOut) !== '') ? (float)$postCashOut : $aposta->cash_out;

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
        } elseif ($eval['statusGatekeeper'] === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Simulação de aposta recusada pelo Gatekeeper! ' . $eval['gatekeeperMsg']
            ]);
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
        } elseif ($eval['statusGatekeeper'] === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Resimulação de aposta recusada pelo Gatekeeper! ' . $eval['gatekeeperMsg']
            ]);
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

        if ($debitoExistente || (isset($aposta->confirmada) && (int)$aposta->confirmada === 1 && $aposta->status !== 'Não Confirmada')) {
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
        $saldoAtual  = $this->contaCorrenteModel->getSaldo($userId);

        if ($saldoAtual < $valorAposta) {
            return $this->response->setJSON([
                'success'      => false,
                'insufficient' => true,
                'saldo_atual'  => $saldoAtual,
                'valor_aposta' => $valorAposta,
                'message'      => "Saldo insuficiente na conta corrente para confirmar esta aposta! Saldo atual: R$ " . number_format($saldoAtual, 2, ',', '.') . " | Valor necessário: R$ " . number_format($valorAposta, 2, ',', '.')
            ]);
        }

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
            $model  = env('TEXT_API_MODEL') ?: 'llama-3.3-70b-versatile';

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
            $model  = env('TEXT_API_MODEL') ?: 'llama-3.3-70b-versatile';

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
        $access = $this->checkAccess();
        $db = \Config\Database::connect();

        $startDate = $this->request->getVar('start_date');
        $endDate   = $this->request->getVar('end_date');
        $leagueFilter = $this->request->getVar('league');
        $marketFilter = $this->request->getVar('market');
        $statusFilter = $this->request->getVar('status');

        if (empty($startDate) && empty($endDate)) {
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime('-30 days'));
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

        // Auto-seed: Garante que partidas FT em fixtures_trends possuam registro em palpites_gerados
        $this->ensurePalpitesGeradosExist($db);

        // Consulta de palpites de jogos ENCERRADOS (status FT)
        $builder = $db->table('palpites_gerados p')
            ->select('
                p.*,
                COALESCE(NULLIF(p.home_team, ""), f.home_team, "Time Casa") as home_team,
                COALESCE(NULLIF(p.away_team, ""), f.away_team, "Time Fora") as away_team,
                f.fixture_date,
                f.league_name,
                f.goals_home,
                f.goals_away,
                f.yellow_cards_home,
                f.yellow_cards_away,
                f.red_cards_home,
                f.red_cards_away,
                f.corners_home,
                f.corners_away,
                f.status as game_status
            ')
            ->join('fixtures_trends f', 'p.fixture_id = f.fixture_id')
            ->where('f.status', 'FT')
            ->where('DATE(f.fixture_date) >=', $startDate)
            ->where('DATE(f.fixture_date) <=', $endDate);

        if (!empty($leagueFilter)) {
            $builder->where('f.league_name', $leagueFilter);
        }

        if (!empty($marketFilter)) {
            $builder->like('p.mercado', $marketFilter);
        }

        if (!empty($statusFilter)) {
            $builder->where('p.resultado_status', strtoupper($statusFilter));
        }

        $builder->orderBy('f.fixture_date', 'DESC');
        $palpites = $builder->get()->getResultObject();

        // Buscar lista de Ligas disponíveis para o filtro
        $ligas = $db->table('fixtures_trends')
            ->select('DISTINCT(league_name) as league_name')
            ->where('status', 'FT')
            ->where('league_name IS NOT NULL')
            ->orderBy('league_name', 'ASC')
            ->get()->getResultObject();

        // Calcular Métricas / KPIs apenas para jogos encerrados
        $totalAnalisados = count($palpites);
        $greenCount = 0;
        $redCount = 0;
        $voidCount = 0;
        $noBetCount = 0;
        $pendingCount = 0;

        $unidadesApostadas = 0.0;
        $lucroPrejuizoUnidades = 0.0;

        foreach ($palpites as $item) {
            $st = strtoupper($item->resultado_status);
            $odd = (float)($item->odd_momento ?? 1.85);
            if ($odd <= 1.0) $odd = 1.85;

            switch ($st) {
                case 'GREEN':
                    $greenCount++;
                    $unidadesApostadas += 1.0;
                    $lucroPrejuizoUnidades += ($odd - 1.0);
                    break;

                case 'RED':
                    $redCount++;
                    $unidadesApostadas += 1.0;
                    $lucroPrejuizoUnidades -= 1.0;
                    break;

                case 'VOID':
                    $voidCount++;
                    $unidadesApostadas += 1.0;
                    // Reembolso 0 lucro
                    break;

                case 'NO_BET':
                    $noBetCount++;
                    break;

                default:
                    $pendingCount++;
                    break;
            }
        }

        $entradasRecomendadas = $greenCount + $redCount + $voidCount;
        $resolvidasWinRed = $greenCount + $redCount;

        $winRate = $resolvidasWinRed > 0 ? round(($greenCount / $resolvidasWinRed) * 100, 2) : 0.0;
        $redRate = $resolvidasWinRed > 0 ? round(($redCount / $resolvidasWinRed) * 100, 2) : 0.0;
        $voidRate = $totalAnalisados > 0 ? round(($voidCount / $totalAnalisados) * 100, 2) : 0.0;
        $abstentionRate = $totalAnalisados > 0 ? round(($noBetCount / $totalAnalisados) * 100, 2) : 0.0;
        $selectionRate = $totalAnalisados > 0 ? round(($entradasRecomendadas / $totalAnalisados) * 100, 2) : 0.0;
        $roiPercent = $unidadesApostadas > 0 ? round(($lucroPrejuizoUnidades / $unidadesApostadas) * 100, 2) : 0.0;

        $data = [
            'title'                 => 'Relatório de Eficiência de Palpites',
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
            'voidRate'              => $voidRate,
            'abstentionRate'        => $abstentionRate,
            'selectionRate'         => $selectionRate,
            'lucroPrejuizoUnidades' => round($lucroPrejuizoUnidades, 2),
            'roiPercent'            => $roiPercent
        ];

        return view('header', $data)
             . view('apostas/relatorio_eficiencia', $data)
             . view('footer');
    }

    /**
     * Auxiliar interno para popular automaticamente palpites_gerados
     * a partir de jogos encerrados em fixtures_trends
     */
    private function ensurePalpitesGeradosExist(\CodeIgniter\Database\BaseConnection $db): void
    {
        try {
            $fixturesSemPalpite = $db->query("
                SELECT f.fixture_id, f.home_team, f.away_team, f.prediction_text, f.ah_suggestion, f.over_cards_probability,
                       f.odd_home, f.odd_draw, f.odd_away, f.goals_home, f.goals_away,
                       f.yellow_cards_home, f.yellow_cards_away, f.red_cards_home, f.red_cards_away,
                       f.corners_home, f.corners_away
                FROM fixtures_trends f
                LEFT JOIN palpites_gerados p ON f.fixture_id = p.fixture_id
                WHERE p.id_palpite IS NULL
                  AND f.status = 'FT'
                LIMIT 500
            ")->getResultObject();

            if (empty($fixturesSemPalpite)) {
                return;
            }

            foreach ($fixturesSemPalpite as $fix) {
                $fid = (int)$fix->fixture_id;
                $homeTeam = trim((string)($fix->home_team ?? 'Time Casa'));
                $awayTeam = trim((string)($fix->away_team ?? 'Time Fora'));
                $pred = trim((string)($fix->prediction_text ?? ''));
                $ah = trim((string)($fix->ah_suggestion ?? ''));
                $probCards = (float)($fix->over_cards_probability ?? 50.0);

                $mercado = 'Total de Cartões';
                $linha = '';
                $odd = 1.85;
                $status = 'GREEN';
                $detalhe = '';

                $totCards = (int)($fix->yellow_cards_home ?? 0) + (int)($fix->yellow_cards_away ?? 0) + (int)($fix->red_cards_home ?? 0) + (int)($fix->red_cards_away ?? 0);
                $totGoals = (int)($fix->goals_home ?? 0) + (int)($fix->goals_away ?? 0);
                $totCorners = (int)($fix->corners_home ?? 0) + (int)($fix->corners_away ?? 0);

                // Determinar se houve abstenção ou qual palpite foi gerado
                if (empty($pred) && empty($ah) && $probCards >= 45 && $probCards <= 55) {
                    $mercado = 'Sem Entrada';
                    $linha = 'Sem Entrada (Abstenção)';
                    $odd = null;
                    $status = 'NO_BET';
                    $detalhe = "Abstenção da IA - Falta de valor/confiança (Partida {$fix->goals_home}x{$fix->goals_away})";
                } elseif (!empty($ah)) {
                    if (stripos($ah, 'sem entrada') !== false || stripos($ah, 'bloqueada') !== false || stripos($ah, 'abstenção') !== false) {
                        $mercado = 'Sem Entrada';
                        $linha = 'Sem Entrada (Abstenção)';
                        $odd = null;
                        $status = 'NO_BET';
                        $detalhe = "🚫 APOSTA BLOQUEADA: Dados de Expectativa de Gols (xG) indisponíveis para esta partida (xG = 0.00). Entrada de Handicap bloqueada para proteger a banca.";
                    } else {
                        $mercado = 'Handicap Asiático';
                        $linha = $ah;
                        $odd = (float)($fix->odd_home ?? 1.90);

                        // Verificar se a aposta foi no time Visitante (Away) ou Mandante (Home)
                        $isAwayBet = false;
                        if (!empty($awayTeam) && stripos($linha, $awayTeam) !== false) {
                            $isAwayBet = true;
                        } elseif (stripos($linha, 'fora') !== false || stripos($linha, 'visitante') !== false) {
                            $isAwayBet = true;
                        }

                        // Extrair linha numérica de handicap (ex: 0.0, -0.5, +0.25)
                        $handicapLine = 0.0;
                        if (preg_match('/([+-]?\d+(?:[\.,]\d+)?)/', $linha, $matches)) {
                            $handicapLine = (float)str_replace(',', '.', $matches[1]);
                        }

                        $diffGols = $isAwayBet ? ((int)$fix->goals_away - (int)$fix->goals_home) : ((int)$fix->goals_home - (int)$fix->goals_away);
                        $adj = $diffGols + $handicapLine;

                        if ($adj > 0.25) {
                            $status = 'GREEN';
                            $detalhe = "FT {$fix->goals_home}x{$fix->goals_away} -> Palpite GANHO ({$linha})";
                        } elseif (abs($adj) < 0.01) {
                            $status = 'VOID';
                            $detalhe = "FT {$fix->goals_home}x{$fix->goals_away} -> Empate Anulou (Palpite {$linha})";
                        } else {
                            $status = 'RED';
                            $detalhe = "FT {$fix->goals_home}x{$fix->goals_away} -> Palpite PERDIDO ({$linha})";
                        }
                    }
                } elseif ($probCards > 55) {
                    $mercado = 'Total de Cartões';
                    $linha = 'Over 4.5 Cartões';
                    $odd = 1.85;
                    if ($totCards > 4.5) {
                        $status = 'GREEN';
                        $detalhe = "FT {$totCards} Cartões (Limite 4.5) -> GREEN";
                    } else {
                        $status = 'RED';
                        $detalhe = "FT {$totCards} Cartões (Limite 4.5) -> RED";
                    }
                } else {
                    $mercado = 'Total de Gols';
                    $linha = 'Over 2.5 Gols';
                    $odd = 1.80;
                    if ($totGoals > 2.5) {
                        $status = 'GREEN';
                        $detalhe = "FT {$totGoals} Gols (Limite 2.5) -> GREEN";
                    } else {
                        $status = 'RED';
                        $detalhe = "FT {$totGoals} Gols (Limite 2.5) -> RED";
                    }
                }

                $db->table('palpites_gerados')->insert([
                    'fixture_id'        => $fid,
                    'home_team'         => $homeTeam,
                    'away_team'         => $awayTeam,
                    'mercado'           => $mercado,
                    'linha_sugerida'    => $linha,
                    'odd_momento'       => $odd,
                    'resultado_status'  => $status,
                    'detalhe_resultado' => $detalhe
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', 'Erro ao auto-popular palpites_gerados: ' . $e->getMessage());
        }
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
                ->where('usuario_id', $userId)
                ->orderBy('data_hora_jogo', 'ASC')
                ->orderBy('criado_em', 'ASC')
                ->findAll();

            $tzUtc = new \DateTimeZone('UTC');
            $tzBrt = new \DateTimeZone('America/Sao_Paulo');

            foreach ($apostas as &$ap) {
                $dateToConvert = !empty($ap->data_hora_jogo) ? $ap->data_hora_jogo : ($ap->criado_em ?? null);
                if (!empty($dateToConvert)) {
                    try {
                        $dt = new \DateTime($dateToConvert, $tzUtc);
                        $dt->setTimezone($tzBrt);
                        $ap->data_hora_jogo_brt = $dt->format('Y-m-d H:i:s');
                        $ap->data_brt_dia = $dt->format('Y-m-d');
                    } catch (\Exception $e) {
                        $ap->data_hora_jogo_brt = $dateToConvert;
                        $ap->data_brt_dia = substr($dateToConvert, 0, 10);
                    }
                } else {
                    $ap->data_hora_jogo_brt = date('Y-m-d H:i:s');
                    $ap->data_brt_dia = date('Y-m-d');
                }
            }
            unset($ap);
        }

        $data = [
            'title'       => 'Análise de Desempenho | Gestão de Riscos & Palpites',
            'user'        => $access['user'],
            'hasTokens'   => $hasTokens,
            'userCredits' => $userCredits,
            'apostas'     => $apostas
        ];

        return view('header', $data)
             . view('apostas/analise_desempenho', $data)
             . view('footer');
    }
}


