<?php

namespace App\Controllers;

use App\Models\ContaCorrenteModel;
use App\Models\ApostaModel;
use App\Helpers\SessionHelper;

class ContaCorrenteController extends BaseController
{
    protected ContaCorrenteModel $contaCorrenteModel;
    protected ApostaModel $apostaModel;

    public function __construct()
    {
        $this->contaCorrenteModel = new ContaCorrenteModel();
        $this->apostaModel        = new ApostaModel();
    }

    /**
     * Verifica autenticação e permissão do usuário (apenas Paulo Nascimento)
     */
    private function checkAccess(): array
    {
        $isLogged = (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] == 1) 
                 || (session()->has('usuario_logado') && session()->get('usuario_logado') == 1);
        
        $userId = $_SESSION['id_usuario_logado'] ?? session()->get('id_usuario_logado') ?? null;

        if (!$isLogged || !$userId) {
            return [
                'authenticated' => false,
                'is_paulo'      => false,
                'user_id'       => null,
                'user'          => null
            ];
        }

        $db = \Config\Database::connect();
        $userRow = $db->table('usuario')->where('id', $userId)->get()->getRow();

        $userName = $userRow->nome ?? $_SESSION['nome_usuario_logado'] ?? session()->get('nome_usuario_logado') ?? '';
        $isPaulo = SessionHelper::isPauloNascimento($userName);

        return [
            'authenticated' => true,
            'is_paulo'      => $isPaulo,
            'user_id'       => (int)$userId,
            'user'          => $userRow
        ];
    }

    /**
     * Exibe o Relatório em Tela do Extrato da Conta Corrente e o Gráfico de Evolução Financeira
     */
    public function extrato()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar o extrato da conta corrente.');
            return redirect()->to('/loginUsuario');
        }

        if (!$access['is_paulo']) {
            session()->setFlashdata('error', 'Acesso negado: O extrato de conta corrente está disponível apenas para o usuário Paulo Nascimento.');
            return redirect()->to('/apostas');
        }

        $userId = $access['user_id'];

        $dataInicio = trim((string)$this->request->getGet('data_inicio'));
        $dataFim    = trim((string)$this->request->getGet('data_fim'));
        $tipo       = trim((string)$this->request->getGet('tipo'));

        $extratoData = $this->contaCorrenteModel->getExtrato($userId, $dataInicio, $dataFim, $tipo);
        $graficoData = $this->contaCorrenteModel->getEvolucaoFinanceira($userId, $dataInicio, $dataFim);

        $data = [
            'title'        => 'Extrato da Conta Corrente & Evolução Financeira | Smart Betting',
            'user'         => $access['user'],
            'extrato'      => $extratoData,
            'grafico'      => $graficoData,
            'data_inicio'  => $dataInicio,
            'data_fim'     => $dataFim,
            'tipo_filtro'  => $tipo
        ];

        return view('header', $data)
             . view('apostas/extrato', $data)
             . view('footer');
    }

    /**
     * Adiciona crédito monetário na conta corrente (AJAX)
     */
    public function adicionarCredito()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sessão expirada. Faça login novamente.'
            ])->setStatusCode(401);
        }

        if (!$access['is_paulo']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado: Funcionalidade disponível apenas para o usuário Paulo Nascimento.'
            ])->setStatusCode(403);
        }

        $userId = $access['user_id'];
        $valor = (float)$this->request->getPost('valor');
        $descricaoInput = trim((string)$this->request->getPost('descricao'));

        if ($valor <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, informe um valor válido maior que zero.'
            ]);
        }

        $descricao = !empty($descricaoInput) 
            ? "Aporte Manual: " . $descricaoInput 
            : "Depósito / Crédito Adicionado na Conta Corrente";

        $result = $this->contaCorrenteModel->adicionarCredito($userId, $valor, $descricao);

        return $this->response->setJSON($result);
    }

    /**
     * Resgata crédito da conta corrente (AJAX)
     */
    public function resgatarCredito()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sessão expirada. Faça login novamente.'
            ])->setStatusCode(401);
        }

        if (!$access['is_paulo']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado: Funcionalidade disponível apenas para o usuário Paulo Nascimento.'
            ])->setStatusCode(403);
        }

        $userId = $access['user_id'];
        $valor = (float)$this->request->getPost('valor');
        $descricaoInput = trim((string)$this->request->getPost('descricao'));

        if ($valor <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, informe um valor de resgate válido maior que zero.'
            ]);
        }

        $saldoAtual = $this->contaCorrenteModel->getSaldo($userId);
        if ($valor > $saldoAtual) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'O valor do resgate (R$ ' . number_format($valor, 2, ',', '.') . ') excede o saldo disponível na conta corrente (R$ ' . number_format($saldoAtual, 2, ',', '.') . ').'
            ]);
        }

        $descricao = !empty($descricaoInput) 
            ? "Resgate: " . $descricaoInput 
            : "Resgate de Crédito da Conta Corrente";

        $result = $this->contaCorrenteModel->resgatarCredito($userId, $valor, $descricao);

        return $this->response->setJSON($result);
    }

    /**
     * Retorna dados em JSON para o gráfico de evolução financeira (AJAX)
     */
    public function getGraficoDados()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Não autorizado.'
            ])->setStatusCode(401);
        }

        if (!$access['is_paulo']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado: Funcionalidade disponível apenas para o usuário Paulo Nascimento.'
            ])->setStatusCode(403);
        }

        $dataInicio = trim((string)$this->request->getGet('data_inicio'));
        $dataFim    = trim((string)$this->request->getGet('data_fim'));

        $graficoData = $this->contaCorrenteModel->getEvolucaoFinanceira($access['user_id'], $dataInicio, $dataFim);

        return $this->response->setJSON([
            'success' => true,
            'grafico' => $graficoData
        ]);
    }

    /**
     * Retorna os dados essenciais da aposta (sem detalhamento do Gatekeeper) para exibição em popup (AJAX)
     */
    public function getApostaDetalhes($apostaId = null)
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sessão expirada. Faça login novamente.'
            ])->setStatusCode(401);
        }

        if (!$access['is_paulo']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Acesso negado: Funcionalidade disponível apenas para o usuário Paulo Nascimento.'
            ])->setStatusCode(403);
        }

        $apostaId = (int)$apostaId;
        if ($apostaId <= 0) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Identificador de aposta inválido.'
            ])->setStatusCode(400);
        }

        $db = \Config\Database::connect();
        $row = $db->table('apostas a')
            ->select('
                a.id,
                a.fixture_id,
                a.time_casa,
                a.time_fora,
                a.mercado,
                a.casa_de_aposta,
                a.palpite,
                a.odd,
                a.data_hora_jogo,
                a.valor_aposta,
                a.ganhos_potenciais,
                a.cash_out,
                a.tipo,
                a.status,
                a.confirmada,
                a.resultado_detalhado,
                a.criado_em,
                a.processado_em,
                f.goals_home,
                f.goals_away,
                f.status as fixture_status,
                f.league_name,
                f.league_id
            ')
            ->join('fixtures_trends f', 'a.fixture_id IS NOT NULL AND a.fixture_id = f.fixture_id', 'left')
            ->where('a.id', $apostaId)
            ->where('a.usuario_id', $access['user_id'])
            ->get()
            ->getRow();

        if (!$row) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta #' . $apostaId . ' não encontrada no seu histórico.'
            ])->setStatusCode(404);
        }

        // Formatação de fuso horário (UTC -> Brasília)
        $tzUtc = new \DateTimeZone('UTC');
        $tzBrt = new \DateTimeZone('America/Sao_Paulo');

        $dataHoraJogoFormatada = '-';
        if (!empty($row->data_hora_jogo)) {
            try {
                $dt = new \DateTime($row->data_hora_jogo, $tzUtc);
                $dt->setTimezone($tzBrt);
                $dataHoraJogoFormatada = $dt->format('d/m/Y H:i');
            } catch (\Exception $e) {
                $dataHoraJogoFormatada = date('d/m/Y H:i', strtotime($row->data_hora_jogo));
            }
        }

        $criadoEmFormatado = !empty($row->criado_em) ? date('d/m/Y H:i:s', strtotime($row->criado_em)) : '-';
        $processadoEmFormatado = !empty($row->processado_em) ? date('d/m/Y H:i:s', strtotime($row->processado_em)) : null;

        $placar = null;
        if ($row->goals_home !== null && $row->goals_away !== null) {
            $placar = $row->goals_home . ' x ' . $row->goals_away;
        }

        $valorAposta = (float)$row->valor_aposta;
        $ganhosPotenciais = (float)$row->ganhos_potenciais;
        $cashOut = $row->cash_out !== null ? (float)$row->cash_out : null;

        $retornoObtido = null;
        $lucroLiquido = null;
        if ($row->status === 'Ganha') {
            $retornoObtido = $ganhosPotenciais > 0 ? $ganhosPotenciais : ($valorAposta * (float)$row->odd);
            $lucroLiquido = $retornoObtido - $valorAposta;
        } elseif ($row->status === 'Meio Ganha') {
            $totalBruto = $ganhosPotenciais > 0 ? $ganhosPotenciais : ($valorAposta * (float)$row->odd);
            $retornoObtido = $valorAposta + (($totalBruto - $valorAposta) / 2);
            $lucroLiquido = $retornoObtido - $valorAposta;
        } elseif ($row->status === 'ANULADA') {
            $retornoObtido = $valorAposta;
            $lucroLiquido = 0.00;
        } elseif ($row->status === 'Meio Perdida') {
            $retornoObtido = $valorAposta * 0.5;
            $lucroLiquido = $retornoObtido - $valorAposta;
        } elseif ($row->status === 'Perdida') {
            $retornoObtido = 0.00;
            $lucroLiquido = -$valorAposta;
        } elseif ($row->status === 'Cashout') {
            $retornoObtido = $cashOut !== null ? $cashOut : $valorAposta;
            $lucroLiquido = $retornoObtido - $valorAposta;
        }

        // Higienização estrita: remove todos os dados e memórias técnicas do Gatekeeper
        $resultadoLimpo = $row->resultado_detalhado ?? '';
        if (!empty($resultadoLimpo)) {
            $resultadoLimpo = preg_replace('/\s*\|\|\s*MEM[ÓO]RIA DE C[ÁA]LCULO.*$/isu', '', $resultadoLimpo);
            $resultadoLimpo = preg_replace('/\s*\|\|\s*U5J_DATA:.*$/isu', '', $resultadoLimpo);
            $resultadoLimpo = preg_replace('/🎯\s*GATEKEEPER.*?(?=\|\|\s*[A-Z_]+:|\n|$)/isu', '', $resultadoLimpo);
            $resultadoLimpo = preg_replace('/🎯\s*GATEKEEPER.*$/isu', '', $resultadoLimpo);
            $resultadoLimpo = preg_replace('/🛡️\s*\[Gatekeeper[^\]]*\]/isu', '', $resultadoLimpo);
            $resultadoLimpo = preg_replace('/🚫\s*APOSTA CANCELADA POR ABSTENÇÃO DA IA:\s*/isu', '', $resultadoLimpo);
            $resultadoLimpo = trim(preg_replace('/\s*\|\|\s*/u', "\n\n", $resultadoLimpo));
            $resultadoLimpo = rtrim($resultadoLimpo, " |\n\r");
        }

        // Resolução de país e bandeira da liga via LeagueHelper
        $leagueCountry = '';
        $leagueFlag = '';
        if (!empty($row->league_name) || !empty($row->league_id)) {
            if (class_exists('\App\Helpers\LeagueHelper')) {
                $leagueInfo = \App\Helpers\LeagueHelper::resolveCountryAndFlag($row->league_id ?? null, $row->league_name ?? null);
                $leagueCountry = $leagueInfo['country'] ?? '';
                $leagueFlag = $leagueInfo['flag'] ?? '';
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'aposta'  => [
                'id'                     => (int)$row->id,
                'fixture_id'             => !empty($row->fixture_id) ? (int)$row->fixture_id : null,
                'time_casa'              => $row->time_casa,
                'time_fora'              => $row->time_fora,
                'league_name'            => $row->league_name ?? null,
                'country'                => !empty($leagueCountry) ? $leagueCountry : null,
                'league_flag'            => $leagueFlag,
                'data_hora_jogo'         => $dataHoraJogoFormatada,
                'mercado'                => $row->mercado,
                'palpite'                => $row->palpite,
                'odd'                    => number_format((float)$row->odd, 2, '.', ''),
                'valor_aposta'           => number_format($valorAposta, 2, ',', '.'),
                'ganhos_potenciais'      => number_format($ganhosPotenciais, 2, ',', '.'),
                'cash_out'               => $cashOut !== null ? number_format($cashOut, 2, ',', '.') : null,
                'retorno_obtido'         => $retornoObtido !== null ? number_format($retornoObtido, 2, ',', '.') : null,
                'lucro_liquido'          => $lucroLiquido !== null ? number_format($lucroLiquido, 2, ',', '.') : null,
                'lucro_liquido_positivo' => $lucroLiquido !== null ? ($lucroLiquido >= 0) : null,
                'tipo'                   => $row->tipo,
                'status'                 => $row->status,
                'placar'                 => $placar,
                'resultado_detalhado'    => !empty($resultadoLimpo) ? $resultadoLimpo : null,
                'criado_em'              => $criadoEmFormatado,
                'processado_em'          => $processadoEmFormatado
            ]
        ]);
    }
}
