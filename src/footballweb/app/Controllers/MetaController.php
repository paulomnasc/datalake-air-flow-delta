<?php

namespace App\Controllers;

use App\Models\MetaDiariaModel;
use App\Models\ApostaModel;
use App\Helpers\SessionHelper;

class MetaController extends BaseController
{
    protected MetaDiariaModel $metaDiariaModel;
    protected ApostaModel $apostaModel;

    public function __construct()
    {
        $this->metaDiariaModel = new MetaDiariaModel();
        $this->apostaModel     = new ApostaModel();
    }

    /**
     * Validação de acesso do usuário
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
     * Exibe o Painel Principal de Metas Diárias e Ciclos Rotativos
     */
    public function index()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar as metas diárias.');
            return redirect()->to('/loginUsuario');
        }

        $userId = $access['user_id'];
        $dataRef = trim((string)$this->request->getGet('data'));
        if (empty($dataRef) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
            $dataRef = date('Y-m-d');
        }

        $forceRecalc = (bool)$this->request->getGet('recalc');
        $cicloParam  = $this->request->getGet('ciclo');
        $numeroCiclo = (!empty($cicloParam) && is_numeric($cicloParam)) ? (int)$cicloParam : null;

        // Filtro de data para o Histórico (afeta abas Diário e Blocos/Ciclos)
        $histInicio = trim((string)$this->request->getGet('hist_inicio'));
        $histFim    = trim((string)$this->request->getGet('hist_fim'));
        $tabAtiva   = trim((string)$this->request->getGet('tab'));

        if (!in_array($tabAtiva, ['dias', 'ciclos'])) {
            $tabAtiva = (!empty($cicloParam)) ? 'ciclos' : 'dias';
        }

        if (!empty($histInicio) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $histInicio)) {
            $histInicio = '';
        }
        if (!empty($histFim) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $histFim)) {
            $histFim = '';
        }

        // Identifica preset ativo e rótulo do período
        $presetAtivo = 'custom';
        if (empty($histInicio) && empty($histFim)) {
            $presetAtivo = '30days';
            $textoPeriodo = 'Últimos 30 Dias';
        } else {
            if (!empty($histInicio) && !empty($histFim)) {
                $textoPeriodo = date('d/m/Y', strtotime($histInicio)) . ' a ' . date('d/m/Y', strtotime($histFim));
                if ($histInicio === date('Y-m-d') && $histFim === date('Y-m-d')) {
                    $presetAtivo = 'today';
                } elseif ($histInicio === date('Y-m-d', strtotime('-1 day')) && $histFim === date('Y-m-d', strtotime('-1 day'))) {
                    $presetAtivo = 'yesterday';
                } elseif ($histInicio === date('Y-m-d', strtotime('-6 days')) && $histFim === date('Y-m-d')) {
                    $presetAtivo = '7days';
                } elseif ($histInicio === date('Y-m-d', strtotime('-14 days')) && $histFim === date('Y-m-d')) {
                    $presetAtivo = '15days';
                } elseif ($histInicio === date('Y-m-d', strtotime('-29 days')) && $histFim === date('Y-m-d')) {
                    $presetAtivo = '30days';
                } elseif ($histInicio === date('Y-m-01') && $histFim === date('Y-m-t')) {
                    $presetAtivo = 'this_month';
                }
            } elseif (!empty($histInicio)) {
                $textoPeriodo = 'A partir de ' . date('d/m/Y', strtotime($histInicio));
            } else {
                $textoPeriodo = 'Até ' . date('d/m/Y', strtotime($histFim));
            }
        }

        $metaConfig      = $this->metaDiariaModel->getMetaAtiva($userId);
        $progressoHoje   = $this->metaDiariaModel->getProgressoDiario($userId, $dataRef, $forceRecalc);
        $cicloAtivo      = $this->metaDiariaModel->getCicloSequencial($userId, (int)($metaConfig->total_apostas_alvo ?? 10), $numeroCiclo, $dataRef);

        $limiteCiclos = (!empty($histInicio) || !empty($histFim)) ? 100 : 15;
        $limiteDias   = (!empty($histInicio) || !empty($histFim)) ? 365 : 30;

        $historicoCiclos = $this->metaDiariaModel->getHistoricoCiclos(
            $userId, 
            $limiteCiclos, 
            (int)($metaConfig->total_apostas_alvo ?? 10),
            $histInicio ?: null,
            $histFim ?: null
        );
        $historicoDias   = $this->metaDiariaModel->getHistoricoDias(
            $userId, 
            $limiteDias,
            $histInicio ?: null,
            $histFim ?: null
        );

        $data = [
            'title'            => 'Gestão de Metas Diárias & Ciclos Rotativos | Smart Betting',
            'user'             => $access['user'],
            'metaConfig'       => $metaConfig,
            'progressoHoje'    => $progressoHoje,
            'cicloAtivo'       => $cicloAtivo,
            'historicoCiclos'  => $historicoCiclos,
            'historicoDias'    => $historicoDias,
            'dataRef'          => $dataRef,
            'numeroCiclo'      => $numeroCiclo,
            'histInicio'       => $histInicio,
            'histFim'          => $histFim,
            'tabAtiva'         => $tabAtiva,
            'presetAtivo'      => $presetAtivo,
            'textoPeriodo'     => $textoPeriodo,
            'isHoje'           => ($dataRef === date('Y-m-d'))
        ];

        return view('header', $data)
             . view('metas/index', $data)
             . view('footer');
    }

    /**
     * Salva ou atualiza os parâmetros da meta ativa do usuário (AJAX / POST)
     */
    public function salvarConfig()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Sessão expirada. Faça login novamente.'
            ])->setStatusCode(401);
        }

        $userId = $access['user_id'];

        $titulo           = trim((string)$this->request->getPost('titulo'));
        $stakePadrao      = (float)$this->request->getPost('stake_padrao');
        $totalApostasAlvo = (int)$this->request->getPost('total_apostas_alvo');
        $oddMediaAlvo     = (float)$this->request->getPost('odd_media_alvo');
        $targetGreens     = (int)$this->request->getPost('target_greens');
        $targetPushes     = (int)$this->request->getPost('target_pushes');
        $maxReds          = (int)$this->request->getPost('max_reds');
        $lucroAlvo        = (float)$this->request->getPost('lucro_alvo');
        $stopLossDiario   = (float)$this->request->getPost('stop_loss_diario');

        if ($totalApostasAlvo <= 0) {
            $totalApostasAlvo = 10;
        }
        if ($stakePadrao <= 0) {
            $stakePadrao = 10.00;
        }

        $metaAtual = $this->metaDiariaModel->getMetaAtiva($userId);
        $stakeAntiga = (float)($metaAtual->stake_padrao ?? 10.00);
        if ($stakeAntiga <= 0) {
            $stakeAntiga = 10.00;
        }

        // Se lucro_alvo ou stop_loss não foram informados explicitamente, escala proporcionalmente à stake
        if ($lucroAlvo <= 0) {
            $lucroBase = (float)($metaAtual->lucro_alvo ?? 7.50);
            $lucroAlvo = round($lucroBase * ($stakePadrao / $stakeAntiga), 2);
        }
        if ($stopLossDiario == 0) {
            $stopBase = (float)($metaAtual->stop_loss_diario ?? -30.00);
            $stopLossDiario = round($stopBase * ($stakePadrao / $stakeAntiga), 2);
        }

        $dados = [
            'titulo'             => !empty($titulo) ? $titulo : 'Meta Diária - Ciclo 10 Apostas',
            'stake_padrao'       => $stakePadrao,
            'total_apostas_alvo' => $totalApostasAlvo,
            'odd_media_alvo'     => $oddMediaAlvo > 0 ? $oddMediaAlvo : 1.75,
            'target_greens'      => $targetGreens > 0 ? $targetGreens : 5,
            'target_pushes'      => $targetPushes >= 0 ? $targetPushes : 2,
            'max_reds'           => $maxReds > 0 ? $maxReds : 3,
            'lucro_alvo'         => $lucroAlvo > 0 ? $lucroAlvo : 7.50,
            'stop_loss_diario'   => $stopLossDiario != 0 ? $stopLossDiario : -30.00,
        ];

        $ok = $this->metaDiariaModel->salvarConfig($userId, $dados);
        if ($ok) {
            // Recalcula o cache de hoje com a nova meta
            $this->metaDiariaModel->recalcularCacheDiario($userId, date('Y-m-d'));
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Configurações de meta atualizadas com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Erro ao salvar configurações de meta.'
        ])->setStatusCode(500);
    }

    /**
     * Força o recálculo do cache de uma data específica (AJAX / POST)
     */
    public function recalcularDia()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Não autenticado'])->setStatusCode(401);
        }

        $dataRef = trim((string)$this->request->getPost('data'));
        if (empty($dataRef) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
            $dataRef = date('Y-m-d');
        }

        $cache = $this->metaDiariaModel->recalcularCacheDiario($access['user_id'], $dataRef);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Cache recalculado com sucesso!',
            'data'    => $cache
        ]);
    }
}
