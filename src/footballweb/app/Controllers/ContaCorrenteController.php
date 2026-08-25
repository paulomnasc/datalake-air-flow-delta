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
        $graficoData = $this->contaCorrenteModel->getEvolucaoFinanceira($userId);

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

        $graficoData = $this->contaCorrenteModel->getEvolucaoFinanceira($access['user_id']);

        return $this->response->setJSON([
            'success' => true,
            'grafico' => $graficoData
        ]);
    }
}
