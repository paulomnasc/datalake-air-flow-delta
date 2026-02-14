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

        // Verificar se é modo edição
        $editId = $this->request->getGet('edit');
        $editData = null;
        
        if ($editId) {
            $config = $this->configModel->find($editId);
            if ($config) {
                // Converter para array se for objeto
                if (is_object($config)) {
                    $config = (array) $config;
                }
                
                // Verificar se a config pertence ao usuário
                $pasta = $this->pastaModel->find($config['id_pasta']);
                if (is_object($pasta)) {
                    $pasta = (array) $pasta;
                }
                
                if ($pasta && $pasta['id_usuario'] == $userId) {
                    $editData = $config;
                }
            }
        }

        $data = [
            'stats' => $stats,
            'pastas' => $pastas,
            'source_types' => $sourceTypes,
            'funcoes_python' => $funcoesAgrupadas,
            'edit_data' => $editData
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
        // Inicializar o ConfigController corretamente com request e response
        $configController = new \App\Controllers\ConfigController();
        $configController->initController($this->request, $this->response, service('logger'));
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

    /**
     * Download de arquivo template de exemplo
     */
    public function downloadTemplate($type = 'json', $filename = 'Invoice.json')
    {
        // Validar tipo
        $validTypes = ['json', 'csv'];
        if (!in_array($type, $validTypes)) {
            log_message('error', 'Tipo de template inválido: ' . $type);
            return $this->response->setStatusCode(400)->setBody('Tipo de template inválido');
        }

        // Validar filename (apenas nome do arquivo, sem path traversal)
        $filename = basename($filename);
        
        // Construir caminho do arquivo - usar FCPATH que aponta para public/
        $filePath = FCPATH . "../src/codeigniter-app/assets/templates/{$type}/{$filename}";
        $filePath = realpath($filePath); // Resolve o caminho absoluto
        
        log_message('info', 'Tentando fazer download de: ' . $filePath);
        
        // Verificar se arquivo existe
        if (!$filePath || !file_exists($filePath)) {
            log_message('error', 'Arquivo template não encontrado: ' . $filePath);
            return $this->response->setStatusCode(404)->setBody('Arquivo template não encontrado');
        }

        log_message('info', 'Arquivo encontrado, preparando download');

        // Definir headers para download
        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Content-Length', filesize($filePath))
            ->setHeader('Cache-Control', 'no-cache, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(file_get_contents($filePath));
    }

    /**
     * Dashboard Administrativo
     * Exibe estatísticas gerais do sistema para admins
     */
    public function admin()
    {
        $db = \Config\Database::connect();
        
        // ========== 1. TOTAL DE USUÁRIOS ==========
        $totalUsers = $db->table('usuario')
            ->countAllResults();
        
        // ========== 2. USUÁRIOS COM FLUXOS (DAG_CONFIGURATIONS) ==========
        // Busca usuários que têm pelo menos 1 dag_configuration
        $usersWithFlows = $db->query("
            SELECT COUNT(DISTINCT u.id) as total
            FROM usuario u
            INNER JOIN pasta p ON p.id_usuario = u.id
            INNER JOIN dag_configurations d ON d.id_pasta = p.id
        ")->getRow()->total;
        
        $percentUsersWithFlows = $totalUsers > 0 
            ? round(($usersWithFlows / $totalUsers) * 100, 2) 
            : 0;
        
        // ========== 3. PROGRESSO DOS ALUNOS NO CURSO ==========
            // ========== 3. PROGRESSO GERAL DOS ALUNOS ==========
            // Calcula a média de progresso dos alunos com status_assinatura 'trial' ou 'active'
            $progressQuery = $db->query("
                SELECT AVG(percentual) as media_progresso
                FROM (
                    SELECT 
                        u.id,
                        u.nome,
                        u.status_assinatura,
                        COUNT(DISTINCT uc.id) as total_tarefas,
                        COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) as tarefas_concluidas,
                        CASE 
                            WHEN COUNT(DISTINCT uc.id) = 0 THEN 0
                            ELSE ROUND((COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) / COUNT(DISTINCT uc.id)) * 100, 2)
                        END as percentual
                    FROM usuario u
                    LEFT JOIN course c ON 1=1
                    LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
                    LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
                    LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
                    LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
                    WHERE u.status_assinatura IN ('trial', 'active')
                    GROUP BY u.id
                ) as sub
            ");
            $courseProgressPercent = $progressQuery->getRow()->media_progresso ?? 0;
            $courseProgressPercent = round($courseProgressPercent, 2);
        
        // Estatísticas detalhadas de curso
        $courseStats = $db->query("
            SELECT 
                COUNT(DISTINCT u.id) as total_students,
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT m.id) as total_modules,
                COUNT(DISTINCT v.id) as total_videos,
                COUNT(DISTINCT uc.id) as total_tasks,
                COALESCE(SUM(uc.xp_points), 0) as total_xp_available,
                COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.id END) as completed_tasks_count,
                (
                    SELECT AVG(xp_ganho) FROM (
                        SELECT COALESCE(SUM(CASE WHEN up2.completed = 1 THEN uc2.xp_points ELSE 0 END), 0) as xp_ganho
                        FROM usuario u2
                        LEFT JOIN course c2 ON c2.is_active = 1
                        LEFT JOIN module m2 ON m2.course_id = c2.id AND m2.is_active = 1
                        LEFT JOIN video v2 ON v2.module_id = m2.id AND v2.is_active = 1
                        LEFT JOIN uc_definition uc2 ON uc2.video_id = v2.id AND uc2.is_active = 1
                        LEFT JOIN uc_progress up2 ON up2.user_id = u2.id AND up2.uc_definition_id = uc2.id
                        WHERE u2.status_assinatura IN ('trial', 'active')
                        GROUP BY u2.id
                    ) as sub
                ) as total_xp_earned
            FROM usuario u
            LEFT JOIN course c ON c.is_active = 1
            LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
            LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
            LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
            LEFT JOIN uc_progress up ON up.user_id = u.id AND up.uc_definition_id = uc.id
            WHERE u.status_assinatura IN ('trial', 'active')
        ")->getRow();
        
        // ========== 4. ALUNOS ATIVOS NOS ÚLTIMOS 7 DIAS ==========
        $recentActivities = $db->query("
            SELECT 
                u.id as user_id,
                u.nome as user_name,
                u.email,
                u.criado_em,
                COUNT(al.id) as activity_count,
                MAX(al.created_at) as last_activity
            FROM usuario u
            INNER JOIN activity_logs al ON al.user_id = u.id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY u.id
            ORDER BY last_activity DESC
            LIMIT 20
        ")->getResult();
        
        // Total de alunos ativos nos últimos 7 dias
        $activeUsersLast7Days = count($recentActivities);
        
        // ========== 5. RANKING DE ALUNOS POR XP ==========
                // ========== 4.1. ALUNOS QUE RETORNARAM APÓS CADASTRO ==========
                $returningStudents = $db->query("
                    SELECT 
                        u.id as user_id,
                        u.nome as user_name,
                        u.email,
                        u.criado_em,
                        COUNT(ac.id) as return_count,
                        MAX(ac.created_at) as last_return
                    FROM activity_logs ac
                    INNER JOIN usuario u ON u.id = ac.user_id
                    WHERE ac.user_id NOT IN (146, 176)
                        AND DATE_FORMAT(u.criado_em, '%Y-%m-%d') < DATE_FORMAT(ac.created_at, '%Y-%m-%d')
                    GROUP BY u.id
                    ORDER BY return_count DESC, last_return DESC
                ")->getResult();
        $topStudents = $db->query("
            SELECT 
                u.id,
                u.nome,
                u.email,
                COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as total_xp,
                COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.uc_definition_id END) as tasks_completed
            FROM usuario u
            LEFT JOIN uc_progress up ON up.user_id = u.id
            LEFT JOIN uc_definition uc ON uc.id = up.uc_definition_id
            GROUP BY u.id
            HAVING total_xp > 0
            ORDER BY total_xp DESC
            LIMIT 10
        ")->getResult();
        
        // ========== 6. CURSOS COM MAIS PROGRESSO ==========
        $coursesProgress = $db->query("
            SELECT 
                c.id,
                c.name as course_name,
                COUNT(DISTINCT m.id) as module_count,
                COUNT(DISTINCT v.id) as video_count,
                COUNT(DISTINCT uc.id) as task_count,
                COALESCE(SUM(uc.xp_points), 0) as total_xp,
                (
                    SELECT AVG(percentual) FROM (
                        SELECT 
                            u.id as aluno_id,
                            CASE WHEN COUNT(DISTINCT uc2.id) = 0 THEN 0
                                 ELSE ROUND((COUNT(DISTINCT CASE WHEN up2.completed = 1 THEN up2.uc_definition_id END) / COUNT(DISTINCT uc2.id)) * 100, 2)
                            END as percentual
                        FROM usuario u
                        LEFT JOIN module m2 ON m2.course_id = c.id AND m2.is_active = 1
                        LEFT JOIN video v2 ON v2.module_id = m2.id AND v2.is_active = 1
                        LEFT JOIN uc_definition uc2 ON uc2.video_id = v2.id AND uc2.is_active = 1
                        LEFT JOIN uc_progress up2 ON up2.user_id = u.id AND up2.uc_definition_id = uc2.id
                        WHERE u.status_assinatura IN ('trial', 'active')
                        GROUP BY u.id
                    ) as sub
                ) as media_progresso,
                COALESCE(SUM(CASE WHEN up.completed = 1 THEN uc.xp_points ELSE 0 END), 0) as earned_xp
            FROM course c
            LEFT JOIN module m ON m.course_id = c.id AND m.is_active = 1
            LEFT JOIN video v ON v.module_id = m.id AND v.is_active = 1
            LEFT JOIN uc_definition uc ON uc.video_id = v.id AND uc.is_active = 1
            LEFT JOIN uc_progress up ON up.uc_definition_id = uc.id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY media_progresso DESC
        ")->getResult();
        
        $data = [
            // Estatísticas gerais
            'total_users' => $totalUsers,
            'users_with_flows' => $usersWithFlows,
            'percent_users_with_flows' => $percentUsersWithFlows,
            'active_users_last_7_days' => $activeUsersLast7Days,
            
            // Estatísticas de curso
            'course_progress_percent' => $courseProgressPercent,
            'total_students' => $courseStats->total_students ?? 0,
            'total_courses' => $courseStats->total_courses ?? 0,
            'total_modules' => $courseStats->total_modules ?? 0,
            'total_videos' => $courseStats->total_videos ?? 0,
            'total_tasks' => $courseStats->total_tasks ?? 0,
            'total_xp_available' => $courseStats->total_xp_available ?? 0,
            'completed_tasks_count' => $courseStats->completed_tasks_count ?? 0,
            'total_xp_earned' => $courseStats->total_xp_earned ?? 0,
            
            // Dados detalhados
            'recent_activities' => $recentActivities,
            'returning_students' => $returningStudents,
            'top_students' => $topStudents,
            'courses_progress' => $coursesProgress,
        ];
        
        return view('admin/dashboard', $data);
    }

    // ...existing code...
    /**
     * Download CSV dos alunos que retornaram após cadastro
     */
    public function downloadReturningStudentsCsv()
    {
        $db = \Config\Database::connect();
        $returningStudents = $db->query("
            SELECT 
                u.id as user_id,
                u.nome as user_name,
                u.email,
                u.criado_em,
                COUNT(ac.id) as return_count,
                MAX(ac.created_at) as last_return
            FROM activity_logs ac
            INNER JOIN usuario u ON u.id = ac.user_id
            WHERE ac.user_id NOT IN (146, 176)
                AND DATE_FORMAT(u.criado_em, '%Y-%m-%d') < DATE_FORMAT(ac.created_at, '%Y-%m-%d')
            GROUP BY u.id
            ORDER BY return_count DESC, last_return DESC
        ")->getResult();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alunos_retornaram_apos_cadastro.csv"');

        $output = fopen('php://output', 'w');
        // Cabeçalho
        fputcsv($output, ['#', 'Aluno', 'Email', 'Retornos', 'Último Retorno', 'Criado em']);
        $rank = 1;
        foreach ($returningStudents as $student) {
            fputcsv($output, [
                $rank++, 
                $student->user_name, 
                $student->email, 
                $student->return_count, 
                $student->last_return, 
                $student->criado_em
            ]);
        }
        fclose($output);
        exit;
    }
}
