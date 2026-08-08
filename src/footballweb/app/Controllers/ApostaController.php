<?php

namespace App\Controllers;

use App\Models\ApostaModel;
use App\Models\UsuarioModel;

class ApostaController extends BaseController
{
    protected ApostaModel $apostaModel;

    public function __construct()
    {
        $this->apostaModel = new ApostaModel();
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
            session()->setFlashdata('error', 'Você precisa estar logado para acessar a gestão de apostas.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $hasTokens = $access['has_tokens'];
        $userCredits = $access['credits'];

        // Buscar lista de jogos disponíveis para associar (fixtures_trends)
        $db = \Config\Database::connect();
        $targetFixId = $this->request->getVar('fixture_id');

        $builderFix = $db->table('fixtures_trends')
            ->select('fixture_id, home_team, away_team, fixture_date, league_name, prediction_text, ah_suggestion');
        
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
                    ->select('fixture_id, home_team, away_team, fixture_date, league_name, prediction_text, ah_suggestion')
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
            $ahSug = $fix->ah_suggestion ?? '';
            if (empty($ahSug) || $ahSug === 'Handicap 0.0 (Empate Anula)') {
                $ahSug = "{$fix->home_team} 0.0 (Empate Anula)";
            }
            $fix->suggested_palpite_ah = $ahSug;
            $fix->suggested_palpite = $suggestedCards;
        }

        $apostas = [];
        $resumo  = [
            'total_apostas'  => 0,
            'total_apostado' => 0,
            'ganhos_totais'  => 0,
            'total_cashout'  => 0,
            'ganhas'         => 0,
            'perdidas'       => 0,
            'pendentes'      => 0,
            'cashouts'       => 0
        ];

        // Apenas carrega apostas se o usuário possuir tokens
        if ($hasTokens) {
            $apostas = $this->apostaModel->where('usuario_id', $userId)->orderBy('criado_em', 'DESC')->findAll();
            $resumo  = $this->apostaModel->getResumoUsuario($userId);
        }

        $data = [
            'title'       => 'Minhas Apostas | Gestão de Gestão de Riscos & Palpites',
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
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta ativos para criar e gerenciar apostas.'
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
        $dataHoraJogo    = $this->request->getPost('data_hora_jogo') ?: date('Y-m-d H:i:s');
        $tipo            = trim($this->request->getPost('tipo') ?? 'Simples');
        $status          = trim($this->request->getPost('status') ?? 'Pendente');
        $cashOut         = $this->request->getPost('cash_out') !== null && $this->request->getPost('cash_out') !== '' 
                           ? (float)$this->request->getPost('cash_out') : null;

        if (empty($timeCasa) || empty($timeFora) || empty($palpite) || $odd <= 0 || $valorAposta <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, preencha corretamente os campos obrigatórios (Times, Palpite, Odd e Valor da Aposta).'
            ]);
        }

        $ganhosPotenciais = round($odd * $valorAposta, 2);

        // Validação do Gatekeeper
        $eval = $this->evaluateGatekeeper($fixtureId, $timeCasa, $timeFora, $mercado, $palpite, $odd);
        $fixtureId        = $eval['fixtureId'];
        $oddJusta         = $eval['oddJusta'];
        $probPoisson      = $eval['probPoisson'];
        $evPercentual     = $eval['evPercentual'];
        $statusGatekeeper = $eval['statusGatekeeper'];
        $gatekeeperMsg    = $eval['gatekeeperMsg'];

        if ($statusGatekeeper === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Aposta recusada pelo Gatekeeper! ' . $gatekeeperMsg
            ]);
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
            'criado_em'             => date('Y-m-d H:i:s')
        ]);

        if ($newId) {
            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Aposta registrada! ' . $gatekeeperMsg,
                'id'                => $newId,
                'status_gatekeeper' => $statusGatekeeper,
                'odd_justa'         => $oddJusta,
                'ev_percentual'     => $evPercentual,
                'gatekeeper_msg'    => $gatekeeperMsg
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Erro ao salvar aposta no banco de dados.'
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
        $gatekeeperMsg = 'Aposta sem análise de estatísticas.';

        $isOver = (stripos($palpite, 'over') !== false || stripos($palpite, 'mais') !== false);
        $isCartoes = (stripos($mercado, 'cartõ') !== false || stripos($mercado, 'card') !== false);

        // REGRA DE BLOQUEIO ABSOLUTO (Estratégia Exclusiva Under / Anti-Over)
        if ($isOver || ($isCartoes && $isOver)) {
            $statusGatekeeper = 'NO_BET';
            $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (Estratégia Exclusiva Under): Apostas no mercado 'Over / Mais de' são proibidas pelo sistema devido ao alto risco de perda e volatilidade estatística. Apenas apostas 'Under / Menos de' são permitidas.";
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
        }

        if (!$isCartoes) {
            return compact('fixtureId', 'oddJusta', 'probPoisson', 'evPercentual', 'statusGatekeeper', 'gatekeeperMsg');
        }

        // TRAVA RIGOROSA DE SEGURANÇA POR LINHA MÍNIMA (Estratégia Exclusiva Under 7.5+)
        preg_match('/(\d+\.\d+|\d+)/', $palpite, $matchesLineCheck);
        $lineCheck = !empty($matchesLineCheck[1]) ? (float)$matchesLineCheck[1] : 5.5;

        if ($lineCheck < 7.5) {
            $statusGatekeeper = 'NO_BET';
            $gatekeeperMsg = "Regra de Bloqueio Gatekeeper (Trava de Segurança Linha Mínima): Apostas no mercado 'Total de Cartões' com linhas inferiores a 7.5 (ex: Under 6.5, 5.5, 4.5, 3.5) são bloqueadas pelo modelo devido ao elevado risco de perda histórico. Apenas linhas de Under 7.5 ou superior possuem margem de segurança aprovada.";
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
            : 1.60;

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
                if ($odd > $maxAllowedOdd) {
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
                'message' => 'Acesso restrito: É necessário possuir tokens de consulta para atualizar apostas.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)($id ?? $this->request->getPost('id'));
        if ($apostaId <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID de aposta inválido.'
            ])->setStatusCode(400);
        }

        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada.'
            ])->setStatusCode(404);
        }

        // Permite atualização se for o dono da aposta ou se for admin (ID 146)
        if ((int)$aposta->usuario_id !== (int)$access['user_id'] && (int)$access['user_id'] !== 146) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada ou acesso negado.'
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

        if (empty($timeCasa) || empty($timeFora) || empty($palpite) || $odd <= 0 || $valorAposta <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, preencha corretamente os campos obrigatórios (Times, Palpite, Odd e Valor da Aposta).'
            ]);
        }

        $ganhosPotenciais = round($odd * $valorAposta, 2);

        // Reavalia o Gatekeeper ao editar a aposta
        $fixtureId = $aposta->fixture_id ? (int)$aposta->fixture_id : null;
        $eval = $this->evaluateGatekeeper($fixtureId, $timeCasa, $timeFora, $mercado, $palpite, $odd);

        if ($eval['statusGatekeeper'] === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Aposta recusada pelo Gatekeeper! ' . $eval['gatekeeperMsg']
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

            return $this->response->setJSON([
                'success'           => true,
                'message'           => 'Aposta atualizada com sucesso! ' . $eval['gatekeeperMsg'],
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

        if ($eval['statusGatekeeper'] === 'NO_BET') {
            return $this->response->setJSON([
                'success' => false,
                'message' => '🚫 Reaposta recusada pelo Gatekeeper! ' . $eval['gatekeeperMsg']
            ]);
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
            'data_hora_jogo'        => date('Y-m-d H:i:s'),
            'valor_aposta'          => $aposta->valor_aposta,
            'ganhos_potenciais'     => $aposta->ganhos_potenciais,
            'cash_out'              => $aposta->cash_out,
            'tipo'                  => $aposta->tipo,
            'status'                => 'Pendente',
            'criado_em'             => date('Y-m-d H:i:s')
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Reaposta realizada com sucesso! ' . $eval['gatekeeperMsg'],
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
                'message' => 'Aposta não encontrada ou acesso negado.'
            ]);
        }

        $this->apostaModel->delete($apostaId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Aposta removida com sucesso.'
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
            'message' => 'Script de processamento de apostas não encontrado no servidor.'
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

        // Top 5 Combinações (Mercado + Palpite) com mais vitórias do Usuário
        $queryUser = $db->query("
            SELECT 
                mercado,
                palpite,
                COUNT(*) as total_vitorias,
                SUM(valor_aposta) as total_apostado,
                SUM(ganhos_potenciais) as retorno_total,
                (SUM(ganhos_potenciais) - SUM(valor_aposta)) as lucro_liquido,
                AVG(odd) as odd_media
            FROM apostas
            WHERE usuario_id = ? AND status = 'Ganha' {$whereDateUser}
            GROUP BY mercado, palpite
            ORDER BY total_vitorias DESC, lucro_liquido DESC
            LIMIT 5
        ", $paramsUser);

        $top5Usuario = $queryUser->getResultArray();

        // Top 5 Combinações da Plataforma (Geral)
        $sqlGeral = "
            SELECT 
                mercado,
                palpite,
                COUNT(*) as total_vitorias,
                SUM(valor_aposta) as total_apostado,
                SUM(ganhos_potenciais) as retorno_total,
                (SUM(ganhos_potenciais) - SUM(valor_aposta)) as lucro_liquido,
                AVG(odd) as odd_media
            FROM apostas
            WHERE status = 'Ganha' {$whereDateGeral}
            GROUP BY mercado, palpite
            ORDER BY total_vitorias DESC, lucro_liquido DESC
            LIMIT 5
        ";
        $queryGeral = !empty($paramsGeral) ? $db->query($sqlGeral, $paramsGeral) : $db->query($sqlGeral);

        $top5Geral = $queryGeral->getResultArray();

        // Resumo estatístico e Métricas de Performance do Usuário
        $rawSummary = $db->query("
            SELECT 
                COUNT(*) as total_apostas,
                SUM(CASE WHEN status IN ('Ganha', 'Perdida') THEN 1 ELSE 0 END) as total_encerradas,
                SUM(CASE WHEN status = 'Ganha' THEN 1 ELSE 0 END) as total_ganhas,
                SUM(CASE WHEN status = 'Perdida' THEN 1 ELSE 0 END) as total_perdidas,
                COALESCE(SUM(CASE WHEN status = 'Ganha' THEN ganhos_potenciais ELSE 0 END), 0) as retorno_ganhas,
                COALESCE(SUM(valor_aposta), 0) as total_investido,
                COALESCE(SUM(CASE WHEN status IN ('Ganha', 'Perdida') THEN valor_aposta ELSE 0 END), 0) as total_investido_encerradas,
                COALESCE(SUM(CASE WHEN status IN ('Ganha', 'Perdida') THEN odd * valor_aposta ELSE 0 END), 0) as soma_odd_ponderada
            FROM apostas
            WHERE usuario_id = ? {$whereDateSummary}
        ", $paramsSummary)->getRowArray();

        $totApostas   = (int)($rawSummary['total_apostas'] ?? 0);
        $totEncerradas= (int)($rawSummary['total_encerradas'] ?? 0);
        $totGanhas    = (int)($rawSummary['total_ganhas'] ?? 0);
        $totPerdidas  = (int)($rawSummary['total_perdidas'] ?? 0);
        $retornoGanhas= (float)($rawSummary['retorno_ganhas'] ?? 0.0);
        $totInvestido = (float)($rawSummary['total_investido'] ?? 0.0);
        $totInvestEnc = (float)($rawSummary['total_investido_encerradas'] ?? 0.0);
        $somaOddPond  = (float)($rawSummary['soma_odd_ponderada'] ?? 0.0);

        $baseInvestida = ($totInvestEnc > 0) ? $totInvestEnc : $totInvestido;
        $lucroLiquido  = $retornoGanhas - $baseInvestida;
        $roiPercentual = ($baseInvestida > 0) ? round(($lucroLiquido / $baseInvestida) * 100, 2) : 0.0;
        
        $winRate       = ($totEncerradas > 0) ? round(($totGanhas / $totEncerradas) * 100, 2) : 0.0;
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
            WHERE status = 'Ganha' {$whereDateGk}
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
            'total_ganhas'           => $totGanhas,
            'total_perdidas'         => $totPerdidas,
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
}

