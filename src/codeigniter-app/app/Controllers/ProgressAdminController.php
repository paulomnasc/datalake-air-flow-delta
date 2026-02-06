<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CourseModel;
use App\Models\ModuleModel;
use App\Models\VideoModel;
use App\Models\UcDefinitionModel;

class ProgressAdminController extends BaseController
{
    protected function checkAdminAuth()
    {
        if (!isset($_SESSION['perfil_usuario_logado']) || $_SESSION['perfil_usuario_logado'] !== 'Admin') {
            return redirect()->to('/')->with('error', 'Acesso negado. Somente administradores podem acessar esta área.');
        }
        return null;
    }

    // ========== COURSE CRUD ==========

    public function indexCourses()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $model = new CourseModel();
        $data['courses'] = $model->orderBy('order', 'ASC')->findAll();
        return view('admin/courses/index', $data);
    }

    public function addCourse()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        return view('admin/courses/add');
    }

    public function insertCourse()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $data = [
            'course_id' => $this->request->getPost('course_id'),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'icon_url' => $this->request->getPost('icon_url'),
            'color' => $this->request->getPost('color'),
            'order' => $this->request->getPost('order') ?? 0,
            'is_active' => $this->request->getPost('is_active') ?? 1,
            'created_by' => $_SESSION['id_usuario_logado'] ?? null
        ];

        $model = new CourseModel();
        
        if ($model->insert($data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Curso criado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao criar curso.',
            'errors' => $model->errors()
        ]);
    }

    public function editCourse()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $model = new CourseModel();
        $data['course'] = $model->find($id);

        if (!$data['course']) {
            return redirect()->to('/admin/courses')->with('error', 'Curso não encontrado.');
        }

        return view('admin/courses/edit', $data);
    }

    public function updateCourse()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $data = [
            'course_id' => $this->request->getPost('course_id'),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'icon_url' => $this->request->getPost('icon_url'),
            'color' => $this->request->getPost('color'),
            'order' => $this->request->getPost('order'),
            'is_active' => $this->request->getPost('is_active')
        ];

        $model = new CourseModel();

        if ($model->update($id, $data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Curso atualizado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao atualizar curso.',
            'errors' => $model->errors()
        ]);
    }

    public function deleteCourse($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $model = new CourseModel();

        if ($model->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Curso deletado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao deletar curso.'
        ]);
    }

    // ========== MODULE CRUD ==========

    public function indexModules($courseId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $moduleModel = new ModuleModel();
        
        if ($courseId) {
            $data['modules'] = $moduleModel->getModulesByCourse($courseId);
            $courseModel = new CourseModel();
            $data['course'] = $courseModel->find($courseId);
        } else {
            $data['modules'] = $moduleModel->orderBy('module_number', 'ASC')->findAll();
            $data['course'] = null;
        }

        $courseModel = new CourseModel();
        $data['courses'] = $courseModel->getActiveCourses();

        return view('admin/modules/index', $data);
    }

    public function addModule($courseId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $courseModel = new CourseModel();
        $data['courses'] = $courseModel->getActiveCourses();
        $data['selected_course_id'] = $courseId;

        return view('admin/modules/add', $data);
    }

    public function insertModule()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $data = [
            'module_id' => $this->request->getPost('module_id'),
            'course_id' => $this->request->getPost('course_id'),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'module_number' => $this->request->getPost('module_number'),
            'order' => $this->request->getPost('order') ?? 0,
            'estimated_hours' => $this->request->getPost('estimated_hours'),
            'is_active' => $this->request->getPost('is_active') ?? 1,
            'created_by' => $_SESSION['id_usuario_logado'] ?? null
        ];

        $model = new ModuleModel();
        
        if ($model->insert($data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Módulo criado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao criar módulo.',
            'errors' => $model->errors()
        ]);
    }

    public function editModule()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $moduleModel = new ModuleModel();
        $data['module'] = $moduleModel->find($id);

        if (!$data['module']) {
            return redirect()->to('/admin/modules')->with('error', 'Módulo não encontrado.');
        }

        $courseModel = new CourseModel();
        $data['courses'] = $courseModel->getActiveCourses();

        return view('admin/modules/edit', $data);
    }

    public function updateModule()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $data = [
            'module_id' => $this->request->getPost('module_id'),
            'course_id' => $this->request->getPost('course_id'),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'module_number' => $this->request->getPost('module_number'),
            'order' => $this->request->getPost('order'),
            'estimated_hours' => $this->request->getPost('estimated_hours'),
            'is_active' => $this->request->getPost('is_active')
        ];

        $model = new ModuleModel();

        if ($model->update($id, $data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Módulo atualizado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao atualizar módulo.',
            'errors' => $model->errors()
        ]);
    }

    public function deleteModule($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $model = new ModuleModel();

        if ($model->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Módulo deletado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao deletar módulo.'
        ]);
    }

    // ========== VIDEO CRUD ==========

    public function indexVideos($moduleId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $videoModel = new VideoModel();
        
        if ($moduleId) {
            $data['videos'] = $videoModel->getVideosByModule($moduleId);
            $moduleModel = new ModuleModel();
            $data['module'] = $moduleModel->find($moduleId);
        } else {
            $data['videos'] = $videoModel->orderBy('video_order', 'ASC')->findAll();
            $data['module'] = null;
        }

        $moduleModel = new ModuleModel();
        $data['modules'] = $moduleModel->where('is_active', 1)->findAll();

        return view('admin/videos/index', $data);
    }

    public function addVideo($moduleId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $moduleModel = new ModuleModel();
        $data['modules'] = $moduleModel->where('is_active', 1)->findAll();
        $data['selected_module_id'] = $moduleId;

        return view('admin/videos/add', $data);
    }

    public function insertVideo()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $data = [
            'video_id' => $this->request->getPost('video_id'),
            'module_id' => $this->request->getPost('module_id'),
            'youtube_id' => $this->request->getPost('youtube_id'),
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'thumbnail_url' => $this->request->getPost('thumbnail_url'),
            'duration_seconds' => $this->request->getPost('duration_seconds'),
            'video_order' => $this->request->getPost('video_order') ?? 0,
            'is_active' => $this->request->getPost('is_active') ?? 1,
            'created_by' => $_SESSION['id_usuario_logado'] ?? null
        ];

        $model = new VideoModel();
        
        if ($model->insert($data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Vídeo criado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao criar vídeo.',
            'errors' => $model->errors()
        ]);
    }

    public function editVideo()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $videoModel = new VideoModel();
        $data['video'] = $videoModel->find($id);

        if (!$data['video']) {
            return redirect()->to('/admin/videos')->with('error', 'Vídeo não encontrado.');
        }

        $moduleModel = new ModuleModel();
        $data['modules'] = $moduleModel->where('is_active', 1)->findAll();

        return view('admin/videos/edit', $data);
    }

    public function updateVideo()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $data = [
            'video_id' => $this->request->getPost('video_id'),
            'module_id' => $this->request->getPost('module_id'),
            'youtube_id' => $this->request->getPost('youtube_id'),
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'thumbnail_url' => $this->request->getPost('thumbnail_url'),
            'duration_seconds' => $this->request->getPost('duration_seconds'),
            'video_order' => $this->request->getPost('video_order'),
            'is_active' => $this->request->getPost('is_active')
        ];

        $model = new VideoModel();

        if ($model->update($id, $data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Vídeo atualizado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao atualizar vídeo.',
            'errors' => $model->errors()
        ]);
    }

    public function deleteVideo($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $model = new VideoModel();

        if ($model->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'Vídeo deletado com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao deletar vídeo.'
        ]);
    }

    // ========== UC DEFINITION CRUD ==========

    public function indexUCs($videoId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $ucModel = new UcDefinitionModel();
        
        if ($videoId) {
            $data['ucs'] = $ucModel->getUCsByVideo($videoId);
            $videoModel = new VideoModel();
            $data['video'] = $videoModel->find($videoId);
        } else {
            $data['ucs'] = $ucModel->orderBy('task_number', 'ASC')->findAll();
            $data['video'] = null;
        }

        $videoModel = new VideoModel();
        $data['videos'] = $videoModel->where('is_active', 1)->findAll();

        return view('admin/ucs/index', $data);
    }

    public function addUC($videoId = null)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $videoModel = new VideoModel();
        $data['videos'] = $videoModel->where('is_active', 1)->findAll();
        $data['selected_video_id'] = $videoId;

        return view('admin/ucs/add', $data);
    }

    public function insertUC()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $data = [
            'uc_id' => $this->request->getPost('uc_id'),
            'video_id' => $this->request->getPost('video_id'),
            'task_number' => $this->request->getPost('task_number'),
            'task_title' => $this->request->getPost('task_title'),
            'task_description' => $this->request->getPost('task_description'),
            'video_checkpoint' => $this->request->getPost('video_checkpoint'),
            'xp_points' => $this->request->getPost('xp_points') ?? 100,
            'is_active' => $this->request->getPost('is_active') ?? 1,
            'order' => $this->request->getPost('order') ?? 0,
            'created_by' => $_SESSION['id_usuario_logado'] ?? null
        ];

        $model = new UcDefinitionModel();
        
        if ($model->insert($data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'UC/Tarefa criada com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao criar UC/Tarefa.',
            'errors' => $model->errors()
        ]);
    }

    public function editUC()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $ucModel = new UcDefinitionModel();
        $data['uc'] = $ucModel->find($id);

        if (!$data['uc']) {
            return redirect()->to('/admin/ucs')->with('error', 'UC não encontrada.');
        }

        $videoModel = new VideoModel();
        $data['videos'] = $videoModel->where('is_active', 1)->findAll();

        return view('admin/ucs/edit', $data);
    }

    public function updateUC()
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $id = $this->request->getPost('id');
        $data = [
            'uc_id' => $this->request->getPost('uc_id'),
            'video_id' => $this->request->getPost('video_id'),
            'task_number' => $this->request->getPost('task_number'),
            'task_title' => $this->request->getPost('task_title'),
            'task_description' => $this->request->getPost('task_description'),
            'video_checkpoint' => $this->request->getPost('video_checkpoint'),
            'xp_points' => $this->request->getPost('xp_points'),
            'is_active' => $this->request->getPost('is_active'),
            'order' => $this->request->getPost('order')
        ];

        $model = new UcDefinitionModel();

        if ($model->update($id, $data)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'UC/Tarefa atualizada com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao atualizar UC/Tarefa.',
            'errors' => $model->errors()
        ]);
    }

    public function deleteUC($id)
    {
        $check = $this->checkAdminAuth();
        if ($check) return $check;

        $model = new UcDefinitionModel();

        if ($model->delete($id)) {
            return $this->response->setJSON([
                'status' => 'success',
                'mensagem' => 'UC/Tarefa deletada com sucesso!'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'mensagem' => 'Erro ao deletar UC/Tarefa.'
        ]);
    }
}
