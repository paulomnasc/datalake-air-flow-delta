<?php

namespace App\Controllers;

use App\Models\MoedaConfigModel;
use App\Helpers\SessionHelper;

class MoedaController extends BaseController
{
    protected MoedaConfigModel $moedaModel;

    public function __construct()
    {
        $this->moedaModel = new MoedaConfigModel();
    }

    /**
     * Validação de autenticação e permissões de acesso
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
     * Tela Principal de Gestão de Criptomoedas e Moedas Operacionais
     */
    public function index()
    {
        $access = $this->checkAccess();

        if (!$access['authenticated']) {
            session()->setFlashdata('error', 'Você precisa estar logado para acessar as configurações de moedas.');
            return redirect()->to('/loginUsuario');
        }

        $moedas = $this->moedaModel->getAllMoedas(false);
        $moedaDefault = $this->moedaModel->getDefaultMoeda();

        $data = [
            'title'        => 'Configuração de Criptomoedas & Taxas Operacionais | FootballWeb',
            'user'         => $access['user'],
            'moedas'       => $moedas,
            'moedaDefault' => $moedaDefault,
            'isPaulo'      => $access['is_paulo']
        ];

        return view('header', $data)
             . view('config/moedas', $data)
             . view('footer');
    }

    /**
     * Define a moeda padrão do sistema (via POST / AJAX)
     */
    public function setDefault()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado.'])->setStatusCode(401);
        }

        $codigo = strtoupper(trim((string)$this->request->getPost('codigo')));
        if (empty($codigo)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Código da moeda inválido.'])->setStatusCode(400);
        }

        $ok = $this->moedaModel->setDefaultMoeda($codigo);
        if ($ok) {
            return $this->response->setJSON([
                'success' => true,
                'message' => "Moeda {$codigo} configurada com sucesso como padrão para criação de apostas!"
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Falha ao atualizar moeda padrão.'])->setStatusCode(500);
    }

    /**
     * Salva ou atualiza os parâmetros de uma moeda (taxas, saque mínimo, cotação)
     */
    public function salvar()
    {
        $access = $this->checkAccess();
        if (!$access['authenticated']) {
            return $this->response->setJSON(['success' => false, 'message' => 'Acesso negado.'])->setStatusCode(401);
        }

        $id = $this->request->getPost('id');
        $codigo = strtoupper(trim((string)$this->request->getPost('codigo')));
        $nome = trim((string)$this->request->getPost('nome'));
        $simbolo = trim((string)$this->request->getPost('simbolo'));
        $tipo = strtoupper(trim((string)$this->request->getPost('tipo')));
        $casasDecimais = (int)$this->request->getPost('casas_decimais');
        $saqueMinimo = (float)$this->request->getPost('saque_minimo');
        $taxaSaqueFixa = (float)$this->request->getPost('taxa_saque_fixa');
        $taxaRedeEstimada = (float)$this->request->getPost('taxa_rede_estimada');
        $taxaSpotPercentual = (float)$this->request->getPost('taxa_spot_percentual');
        $cotacaoBrl = (float)$this->request->getPost('cotacao_brl');
        $isDefault = (int)$this->request->getPost('is_default');
        $ativo = (int)$this->request->getPost('ativo');

        if (empty($codigo) || empty($nome) || empty($simbolo)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.'])->setStatusCode(400);
        }

        $payload = [
            'codigo'               => $codigo,
            'nome'                 => $nome,
            'simbolo'              => $simbolo,
            'tipo'                 => in_array($tipo, ['FIAT', 'CRYPTO']) ? $tipo : 'CRYPTO',
            'casas_decimais'       => max(0, min(8, $casasDecimais)),
            'saque_minimo'         => $saqueMinimo,
            'taxa_saque_fixa'      => $taxaSaqueFixa,
            'taxa_rede_estimada'   => $taxaRedeEstimada,
            'taxa_spot_percentual' => $taxaSpotPercentual,
            'cotacao_brl'          => max(0.0001, $cotacaoBrl),
            'cotacao_atualizada_em'=> date('Y-m-d H:i:s'),
            'ativo'                => $ativo ? 1 : 0
        ];

        if (!empty($id)) {
            $this->moedaModel->update((int)$id, $payload);
            if ($isDefault) {
                $this->moedaModel->setDefaultMoeda($codigo);
            }
            return $this->response->setJSON(['success' => true, 'message' => 'Moeda atualizada com sucesso!']);
        } else {
            $payload['is_default'] = $isDefault ? 1 : 0;
            $novoId = $this->moedaModel->insert($payload);
            if ($isDefault) {
                $this->moedaModel->setDefaultMoeda($codigo);
            }
            return $this->response->setJSON(['success' => true, 'message' => 'Nova moeda cadastrada com sucesso!', 'id' => $novoId]);
        }
    }
}
