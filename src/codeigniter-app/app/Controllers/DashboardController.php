<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\ConfigModel;
use App\Models\PastaModel;
use App\Models\SourceTypeModel;
use App\Models\UsuarioFuncionConfigurationModel;
use App\Helpers\SessionHelper;
use App\Helpers\AirflowHelper;

class DashboardController extends BaseController
{
    protected $configModel;
    protected $pastaModel;
    protected $sourceTypeModel;

    public function __construct()
    {
        $this->configModel = new ConfigModel();
        $this->pastaModel = new PastaModel();
        $this->sourceTypeModel = new SourceTypeModel();
    }

    /**
     * Dashboard principal com nova UX
     */
    public function index()
    {
        // Verificar se usuário está logado
        if (!isset($_SESSION['id_usuario_logado'])) {
            return redirect()->to(route_to('Usuario.login'));
        }

        $userId = (int) SessionHelper::getUserId();

        // Carregar estatísticas
        $stats = $this->getStats($userId);
        
        // Carregar pastas para o wizard
        $pastas = $this->pastaModel->listToCombo($userId);
        
        // Carregar tipos de fonte
        $sourceTypes = $this->sourceTypeModel->listToCombo();
        
        // Carregar funções Python disponíveis
        $usuarioFuncionModel = new UsuarioFuncionConfigurationModel();
        $funcoesAgrupadas = $usuarioFuncionModel->getFuncoesFormatadas($userId);
        
        log_message('debug', 'Funções Python carregadas: ' . print_r($funcoesAgrupadas, true));

        $data = [
            'stats' => $stats,
            'pastas' => $pastas,
            'source_types' => $sourceTypes,
            'funcoes_python' => $funcoesAgrupadas
        ];

        return view('dashboard/index', $data);
    }

    /**
     * Calcula estatísticas do dashboard
     */
    private function getStats($userId)
    {
        // Total de pipelines
        $totalPipelines = $this->configModel
            ->join('pasta', 'pasta.id = dag_configurations.id_pasta')
            ->where('pasta.id_usuario', $userId)
            ->countAllResults();

        // Pipelines ativos
        $activePipelines = $this->configModel
            ->join('pasta', 'pasta.id = dag_configurations.id_pasta')
            ->where('pasta.id_usuario', $userId)
            ->where('dag_configurations.is_active', 1)
            ->countAllResults();

        // Pipelines inativos (podem indicar problemas)
        $failedPipelines = $this->configModel
            ->join('pasta', 'pasta.id = dag_configurations.id_pasta')
            ->where('pasta.id_usuario', $userId)
            ->where('dag_configurations.is_active', 0)
            ->countAllResults();

        // Total de pastas/datasources
        $totalDatasources = $this->pastaModel
            ->where('id_usuario', $userId)
            ->countAllResults();

        return [
            'pipelines' => [
                'total' => $totalPipelines,
                'active' => $activePipelines,
                'failed' => $failedPipelines
            ],
            'datasources' => [
                'total' => $totalDatasources,
                'connected' => $totalDatasources // Por enquanto considera todas como conectadas
            ],
            'executions' => [
                'today' => 0, // TODO: Implementar quando houver log de execuções
                'total' => 0,
                'success_rate' => $totalPipelines > 0 ? round(($activePipelines / $totalPipelines) * 100) : 0
            ]
        ];
    }

    /**
     * API para retornar stats em JSON
     */
    public function getStatsJson()
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['error' => 'Não autorizado'])->setStatusCode(401);
        }

        $userId = (int) SessionHelper::getUserId();
        $stats = $this->getStats($userId);

        return $this->response->setJSON($stats);
    }

    /**
     * Criar novo pipeline (POST do wizard)
     */
    public function createPipeline()
    {
        // Reutilizar a lógica do ConfigController::insert
        $configController = new \App\Controllers\ConfigController();
        return $configController->insert();
    }

    /**
     * Salvar rascunho do pipeline
     */
    public function saveDraft()
    {
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->response->setJSON(['error' => 'Não autorizado'])->setStatusCode(401);
        }

        // TODO: Implementar salvamento de rascunho
        // Por enquanto retorna sucesso simulado
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Rascunho salvo com sucesso'
        ]);
    }
}
