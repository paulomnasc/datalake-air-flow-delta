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
        $fixtures = $db->table('fixtures_trends')
            ->select('fixture_id, home_team, away_team, fixture_date, league_name')
            ->orderBy('fixture_date', 'DESC')
            ->limit(50)
            ->get()
            ->getResultObject();

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

        $newId = $this->apostaModel->insert([
            'usuario_id'        => $userId,
            'fixture_id'        => $fixtureId,
            'time_casa'         => $timeCasa,
            'time_fora'         => $timeFora,
            'mercado'           => $mercado,
            'palpite'           => $palpite,
            'odd'               => $odd,
            'data_hora_jogo'    => $dataHoraJogo,
            'valor_aposta'      => $valorAposta,
            'ganhos_potenciais' => $ganhosPotenciais,
            'cash_out'          => $cashOut,
            'tipo'              => $tipo,
            'status'            => $status,
            'criado_em'         => date('Y-m-d H:i:s')
        ]);

        if ($newId) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Aposta registrada com sucesso!',
                'id'      => $newId
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Erro ao salvar aposta no banco de dados.'
        ]);
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
        $aposta = $this->apostaModel->find($apostaId);

        if (!$aposta || (int)$aposta->usuario_id !== $access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada ou acesso negado.'
            ])->setStatusCode(404);
        }

        $timeCasa        = trim($this->request->getPost('time_casa') ?? $aposta->time_casa);
        $timeFora        = trim($this->request->getPost('time_fora') ?? $aposta->time_fora);
        $mercado         = trim($this->request->getPost('mercado') ?? $aposta->mercado);
        $palpite         = trim($this->request->getPost('palpite') ?? $aposta->palpite);
        $odd             = $this->request->getPost('odd') !== null ? (float)$this->request->getPost('odd') : (float)$aposta->odd;
        $valorAposta     = $this->request->getPost('valor_aposta') !== null ? (float)$this->request->getPost('valor_aposta') : (float)$aposta->valor_aposta;
        $status          = trim($this->request->getPost('status') ?? $aposta->status);
        $tipo            = trim($this->request->getPost('tipo') ?? $aposta->tipo);
        $cashOut         = $this->request->getPost('cash_out') !== null && $this->request->getPost('cash_out') !== ''
                           ? (float)$this->request->getPost('cash_out') : $aposta->cash_out;

        $ganhosPotenciais = round($odd * $valorAposta, 2);

        $dataUpdate = [
            'time_casa'         => $timeCasa,
            'time_fora'         => $timeFora,
            'mercado'           => $mercado,
            'palpite'           => $palpite,
            'odd'               => $odd,
            'valor_aposta'      => $valorAposta,
            'ganhos_potenciais' => $ganhosPotenciais,
            'cash_out'          => $cashOut,
            'tipo'              => $tipo,
            'status'            => $status,
            'updated_at'        => date('Y-m-d H:i:s')
        ];

        $this->apostaModel->update($apostaId, $dataUpdate);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Aposta atualizada com sucesso!'
        ]);
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

        if (!$aposta || (int)$aposta->usuario_id !== $access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada.'
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
     * Duplica/Reaposta uma aposta existente (AJAX)
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

        if (!$aposta || (int)$aposta->usuario_id !== $access['user_id']) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Aposta não encontrada.'
            ]);
        }

        $novoId = $this->apostaModel->insert([
            'usuario_id'        => $access['user_id'],
            'fixture_id'        => $aposta->fixture_id,
            'time_casa'         => $aposta->time_casa,
            'time_fora'         => $aposta->time_fora,
            'mercado'           => $aposta->mercado,
            'palpite'           => $aposta->palpite,
            'odd'               => $aposta->odd,
            'data_hora_jogo'    => date('Y-m-d H:i:s'),
            'valor_aposta'      => $aposta->valor_aposta,
            'ganhos_potenciais' => $aposta->ganhos_potenciais,
            'cash_out'          => $aposta->cash_out,
            'tipo'              => $aposta->tipo,
            'status'            => 'Pendente',
            'criado_em'         => date('Y-m-d H:i:s')
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Reaposta realizada com sucesso!',
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

        if (!$aposta || (int)$aposta->usuario_id !== $access['user_id']) {
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
}
