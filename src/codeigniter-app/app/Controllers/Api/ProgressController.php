<?php
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class ProgressController extends ResourceController
{
    use ResponseTrait;

    /**
     * Recebe e persiste progresso de vídeo
     * POST /api/video-progress
     */
    public function videoProgress()
    {
        $data = $this->request->getJSON(true);
        
        // Validação básica
        $validation = \Config\Services::validation();
        $validation->setRules([
            'user_id'   => 'required',
            'course_id' => 'required',
            'lesson_id' => 'required',
            'video_id'  => 'required',
            'percent'   => 'required|numeric',
            'completed' => 'required|in_list[0,1,true,false]',
        ]);

        if (!$validation->run($data)) {
            return $this->failValidationErrors($validation->getErrors());
        }

        // TODO: Persistir no banco de dados
        // Exemplo:
        // $progressModel = new \App\Models\VideoProgressModel();
        // $progressModel->save([
        //     'user_id'    => $data['user_id'],
        //     'course_id'  => $data['course_id'],
        //     'lesson_id'  => $data['lesson_id'],
        //     'video_id'   => $data['video_id'],
        //     'percent'    => $data['percent'],
        //     'completed'  => filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN),
        //     'timestamp'  => $data['timestamp'] ?? date('Y-m-d H:i:s')
        // ]);

        log_message('info', '[VideoProgress] User: ' . $data['user_id'] . ' | Video: ' . $data['video_id'] . ' | Progress: ' . $data['percent'] . '%');

        return $this->respondCreated([
            'success' => true,
            'message' => 'Progresso salvo com sucesso',
            'data' => $data
        ]);
    }

    /**
     * Recebe e persiste progresso de UC (tasks)
     * POST /api/uc-progress
     */
    public function ucProgress()
    {
        $data = $this->request->getJSON(true);
        
        $validation = \Config\Services::validation();
        $validation->setRules([
            'userId'    => 'required',
            'courseId'  => 'required',
            'moduleId'  => 'required',
            'activeTask' => 'required|numeric',
            'progress'  => 'required|numeric',
            'points'    => 'required|numeric',
        ]);

        if (!$validation->run($data)) {
            return $this->failValidationErrors($validation->getErrors());
        }

        // TODO: Persistir no banco
        // $ucProgressModel = new \App\Models\UcProgressModel();
        // $ucProgressModel->save($data);

        log_message('info', '[UCProgress] User: ' . $data['userId'] . ' | Module: ' . $data['moduleId'] . ' | Task: ' . $data['activeTask']);

        return $this->respondCreated([
            'success' => true,
            'message' => 'Progresso UC salvo com sucesso',
            'data' => $data
        ]);
    }

    /**
     * Busca progresso de vídeo do usuário
     * GET /api/video-progress/{userId}/{videoId}
     */
    public function getVideoProgress($userId = null, $videoId = null)
    {
        if (!$userId || !$videoId) {
            return $this->failValidationErrors('userId e videoId são obrigatórios');
        }

        // TODO: Buscar do banco
        // $progressModel = new \App\Models\VideoProgressModel();
        // $progress = $progressModel->where(['user_id' => $userId, 'video_id' => $videoId])->first();

        return $this->respond([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'video_id' => $videoId,
                'percent' => 0,
                'completed' => false
            ]
        ]);
    }
}
