<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\ModuleModel;
use App\Models\VideoModel;
use App\Models\UcDefinitionModel;
use App\Models\VideoProgressModel;
use App\Models\UcProgressModel;

class CursoController extends BaseController
{

    

    public function modulo1()
    {
        return view('cursoModulo1');
    }

    public function progressMonitor()
    {
        return view('uc_progress_monitor');
    }

    // ========== STUDENT INTERFACE ==========

    /**
     * Lista todos os cursos ativos disponíveis para o aluno
     */
    public function index()
    {
        $courseModel = new CourseModel();
        $moduleModel = new ModuleModel();
        $videoModel = new VideoModel();
        $ucProgressModel = new UcProgressModel();

        $userId = $_SESSION['id_usuario_logado'] ?? null;
        $courses = $courseModel->getActiveCourses();
        foreach ($courses as &$course) {
            $modules = $moduleModel->where('course_id', $course['id'])->where('is_active', 1)->findAll();
            $course['module_count'] = count($modules);
            $videoCount = 0;
            foreach ($modules as $module) {
                $videoCount += $videoModel->where('module_id', $module['id'])->where('is_active', 1)->countAllResults();
            }
            $course['video_count'] = $videoCount;
            // XP acumulado por curso
            $course['total_xp'] = ($userId) ? $ucProgressModel->getCourseXp($userId, $course['id']) : 0;
        }
        $data['courses'] = $courses;
        return view('student/courses_list', $data);
    }

    /**
     * Exibe os módulos de um curso específico
     */
    public function course($courseId)
    {
        $courseModel = new CourseModel();
        $moduleModel = new ModuleModel();
        $videoModel = new VideoModel();

        $data['course'] = $courseModel->find($courseId);

        if (!$data['course'] || !$data['course']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Curso não encontrado ou não disponível.');
        }

        $modules = $moduleModel->getModulesByCourse($courseId);
        // Adiciona o campo video_count em cada módulo
        foreach ($modules as &$module) {
            $module['video_count'] = $videoModel->countByModule($module['id']);
        }
        $data['modules'] = $modules;

        return view('student/course_modules', $data);
    }

    /**
     * Exibe os vídeos de um módulo específico
     */
    public function module($moduleId)
    {
        $moduleModel = new ModuleModel();
        $videoModel = new VideoModel();
        $courseModel = new CourseModel();
        $ucModel = new UcDefinitionModel();
        $userId = $_SESSION['id_usuario_logado'] ?? null;

        $data['module'] = $moduleModel->find($moduleId);

        if (!$data['module'] || !$data['module']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Módulo não encontrado ou não disponível.');
        }
        $data['texto_periodicidade'] = ' taxa única';
        // Validação extra: bloqueia acesso ao módulo 2+ caso pagamento_inicial não esteja autorizado
        if ($userId && intval($data['module']['order'] ?? 1) > 1) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            if (empty($usuario->pagamento_inicial) || $usuario->pagamento_inicial != 1) {
                return redirect()->to('/subscription/initial-payment');
            }
        }

        $data['course'] = $courseModel->find($data['module']['course_id']);
        $data['videos'] = $videoModel->getVideosByModule($moduleId);

        // Buscar progresso do usuário se estiver logado
        if ($userId) {
            $progressModel = new VideoProgressModel();
            $ucProgressModel = new UcProgressModel();

            foreach ($data['videos'] as &$video) {
                // Progresso de vídeo
                $progress = $progressModel->getUserVideoProgress($userId, $video['id']);
                $video['completed'] = $progress ? $progress['completed'] : 0;
                $video['percent'] = $progress ? $progress['percent'] : 0;

                // Informações de tarefas do vídeo
                $ucs = $ucModel->getUCsByVideo($video['id']);
                $video['uc_count'] = count($ucs);

                // Total de XP disponível
                $video['total_xp'] = 0;
                foreach ($ucs as $uc) {
                    $video['total_xp'] += $uc['xp_points'];
                }

                // Tarefas concluídas e XP ganho
                $video['uc_completed'] = 0;
                $video['xp_earned'] = 0;

                foreach ($ucs as $uc) {
                    $ucProgress = $ucProgressModel->where([
                        'user_id' => $userId,
                        'uc_definition_id' => $uc['id']
                    ])->first();

                    if ($ucProgress && $ucProgress['completed']) {
                        $video['uc_completed']++;
                        $video['xp_earned'] += $uc['xp_points'];
                    }
                }
            }
        }

        return view('student/module_videos', $data);
    }

    /**
     * Player de vídeo com as tarefas/UCs
     */
    public function video($videoId)
    {
        $videoModel = new VideoModel();
        $moduleModel = new ModuleModel();
        $courseModel = new CourseModel();
        $ucModel = new UcDefinitionModel();
        
        $data['video'] = $videoModel->find($videoId);
        
        if (!$data['video'] || !$data['video']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Vídeo não encontrado ou não disponível.');
        }
        
        $data['module'] = $moduleModel->find($data['video']['module_id']);
        $data['course'] = $courseModel->find($data['module']['course_id']);
        $data['ucs'] = $ucModel->getUCsByVideo($videoId);
        
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        
        // --- Validação de Pagamento (Segurança) ---
        // Bloqueia acesso ao vídeo caso o módulo seja 2+ e pagamento_inicial não esteja autorizado
        if ($userId && intval($data['module']['order'] ?? 1) > 1) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            if (empty($usuario->pagamento_inicial) || $usuario->pagamento_inicial != 1) {
                return redirect()->to('/subscription/initial-payment');
            }
        }
        // ------------------------------------------

        // --- Cálculo da Navegação Sequencial (Próxima Aula) ---
        $nextVideo = null;
        
        // 1. Tentar buscar o próximo vídeo dentro do mesmo módulo
        $moduleVideos = $videoModel->getVideosByModule($data['module']['id']);
        $currentIndex = null;
        foreach ($moduleVideos as $idx => $v) {
            if ($v['id'] == $videoId) {
                $currentIndex = $idx;
                break;
            }
        }
        
        if ($currentIndex !== null && isset($moduleVideos[$currentIndex + 1])) {
            // Existe próximo vídeo no mesmo módulo
            $nextVideo = $moduleVideos[$currentIndex + 1];
        } else {
            // É o último vídeo do módulo, tentar o primeiro vídeo do próximo módulo
            $modules = $moduleModel->getModulesByCourse($data['course']['id']);
            $currentModuleIndex = null;
            foreach ($modules as $idx => $m) {
                if ($m['id'] == $data['module']['id']) {
                    $currentModuleIndex = $idx;
                    break;
                }
            }
            
            if ($currentModuleIndex !== null && isset($modules[$currentModuleIndex + 1])) {
                $nextModule = $modules[$currentModuleIndex + 1];
                $nextModuleVideos = $videoModel->getVideosByModule($nextModule['id']);
                if (!empty($nextModuleVideos)) {
                    $nextVideo = $nextModuleVideos[0];
                }
            }
        }
        $data['next_video'] = $nextVideo;
        // ------------------------------------------------------
        
        // Buscar progresso do usuário se estiver logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $userId = $_SESSION['id_usuario_logado'];
            
            // Progresso do vídeo
            $videoProgressModel = new VideoProgressModel();
            $data['video_progress'] = $videoProgressModel->getUserVideoProgress($userId, $videoId);
            
            // Progresso das UCs
            $ucProgressModel = new UcProgressModel();
            foreach ($data['ucs'] as &$uc) {
                $ucProgress = $ucProgressModel->where([
                    'user_id' => $userId,
                    'uc_definition_id' => $uc['id']
                ])->first();
                
                $uc['completed'] = $ucProgress ? $ucProgress['completed'] : 0;
            }
        }
        
        return view('student/video_player', $data);
    }
}
