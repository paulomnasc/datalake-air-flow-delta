<?php
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\VideoProgressModel;
use App\Models\UcProgressModel;

class ProgressController extends ResourceController
{
    use ResponseTrait;

    /**
     * Recebe e persiste progresso de vídeo
     * POST /api/video-progress
     */
    public function videoProgress()
    {
        // Verificar se usuário está logado
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->failUnauthorized('Usuário não autenticado');
        }

        $userId = $_SESSION['id_usuario_logado'];
        $data = $this->request->getPost();
        
        // Validação básica
        if (!isset($data['video_id'])) {
            return $this->failValidationErrors(['video_id' => 'video_id é obrigatório']);
        }

        $videoData = [
            'user_id' => $userId,
            'video_id' => intval($data['video_id']),
            'watched_seconds' => intval($data['watched_seconds'] ?? 0),
            'total_seconds' => intval($data['total_seconds'] ?? 0),
            'percent' => floatval($data['percent'] ?? 0),
            'completed' => intval($data['completed'] ?? 0),
            'last_position_seconds' => intval($data['watched_seconds'] ?? 0)
        ];

        try {
            $progressModel = new VideoProgressModel();
            $progressModel->upsertProgress($videoData);

            log_message('info', '[VideoProgress] User: ' . $userId . ' | Video: ' . $videoData['video_id'] . ' | Progress: ' . $videoData['percent'] . '%');

            return $this->respondCreated([
                'status' => 'success',
                'message' => 'Progresso salvo com sucesso',
                'data' => $videoData
            ]);
        } catch (\Exception $e) {
            log_message('error', '[VideoProgress Error] ' . $e->getMessage());
            return $this->failServerError('Erro ao salvar progresso: ' . $e->getMessage());
        }
    }

    /**
     * Recebe e persiste progresso de UC (tasks)
     * POST /api/uc-progress
     */
    public function ucProgress()
    {
        try {
            // Verificar se usuário está logado
            if (!isset($_SESSION['id_usuario_logado'])) {
                return $this->failUnauthorized('Usuário não autenticado');
            }

            $userId = $_SESSION['id_usuario_logado'];
            $data = $this->request->getPost();

            // Debug logging
            log_message('debug', '[UCProgress Request] Data: ' . json_encode($data) . ' | User: ' . $userId);

            // Validação básica
            if (!isset($data['uc_definition_id'])) {
                log_message('error', '[UCProgress Error] Falta uc_definition_id');
                return $this->failValidationErrors(['uc_definition_id' => 'uc_definition_id é obrigatório']);
            }

            $ucData = [
                'user_id' => $userId,
                'uc_definition_id' => intval($data['uc_definition_id']),
                'completed' => intval($data['completed'] ?? 0),
                'completed_at' => intval($data['completed'] ?? 0) ? date('Y-m-d H:i:s') : null,
                'progress_percent' => 100,
                'attempts' => 1
            ];

            log_message('debug', '[UCProgress Process] Data to save: ' . json_encode($ucData));

            $ucProgressModel = new UcProgressModel();
            
            // Buscar se já existe
            $existing = $ucProgressModel->where([
                'user_id' => $userId,
                'uc_definition_id' => $ucData['uc_definition_id']
            ])->first();

            if ($existing) {
                // Atualizar
                $result = $ucProgressModel->update($existing['id'], $ucData);
                log_message('info', '[UCProgress Updated] ID: ' . $existing['id'] . ' | Result: ' . ($result ? 'success' : 'failed'));
            } else {
                // Criar novo
                $result = $ucProgressModel->insert($ucData);
                log_message('info', '[UCProgress Inserted] ID: ' . ($result ? 'generated' : 'failed'));
            }

            if (!$result) {
                $errors = $ucProgressModel->errors();
                log_message('error', '[UCProgress Model Errors] ' . json_encode($errors));
                return $this->failServerError('Erro ao salvar progresso: ' . json_encode($errors));
            }

            log_message('info', '[UCProgress Success] User: ' . $userId . ' | UC: ' . $ucData['uc_definition_id'] . ' | Completed: ' . $ucData['completed']);

            return $this->respond([
                'status' => 'success',
                'message' => 'Progresso da UC/Tarefa salvo com sucesso',
                'data' => $ucData
            ], 200);

        } catch (\Exception $e) {
            log_message('error', '[UCProgress Exception] ' . $e->getMessage() . ' | Line: ' . $e->getLine());
            return $this->failServerError('Erro ao salvar progresso: ' . $e->getMessage());
        }
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

        try {
            $progressModel = new VideoProgressModel();
            $progress = $progressModel->where([
                'user_id' => $userId, 
                'video_id' => intval($videoId)
            ])->first();

            if ($progress) {
                return $this->respond([
                    'status' => 'success',
                    'data' => $progress
                ]);
            }

            return $this->respond([
                'status' => 'success',
                'data' => null
            ]);
        } catch (\Exception $e) {
            log_message('error', '[GetVideoProgress Error] ' . $e->getMessage());
            return $this->failServerError('Erro ao buscar progresso: ' . $e->getMessage());
        }
    }

    /**
     * Salva feedback do usuário quando atinge 80% do vídeo
     * POST /api/video-feedback
     */
    public function saveVideoFeedback()
    {
        // Verificar se usuário está logado
        if (!isset($_SESSION['id_usuario_logado'])) {
            return $this->failUnauthorized('Usuário não autenticado');
        }

        $userId = $_SESSION['id_usuario_logado'];
        $data = $this->request->getPost();
        
        // Validação básica
        if (!isset($data['video_id']) || !isset($data['lab_status']) || !isset($data['value_perception'])) {
            return $this->failValidationErrors([
                'video_id' => 'video_id é obrigatório',
                'lab_status' => 'lab_status é obrigatório',
                'value_perception' => 'value_perception é obrigatório'
            ]);
        }

        $feedbackData = [
            'user_id' => $userId,
            'video_id' => intval($data['video_id']),
            'lab_status' => $data['lab_status'],
            'value_perception' => $data['value_perception'],
            'open_feedback' => $data['open_feedback'] ?? null
        ];

        try {
            $feedbackModel = new \App\Models\VideoFeedbackModel();
            $feedbackModel->saveFeedback(
                $feedbackData['user_id'],
                $feedbackData['video_id'],
                $feedbackData['lab_status'],
                $feedbackData['value_perception'],
                $feedbackData['open_feedback']
            );

            log_message('info', '[VideoFeedback] User: ' . $userId . ' | Video: ' . $feedbackData['video_id'] . ' | Lab Status: ' . $feedbackData['lab_status']);

            return $this->respond([
                'status' => 'success',
                'message' => 'Feedback salvo com sucesso',
                'data' => $feedbackData
            ], 200);

        } catch (\Exception $e) {
            log_message('error', '[VideoFeedback Exception] ' . $e->getMessage() . ' | Line: ' . $e->getLine());
            return $this->failServerError('Erro ao salvar feedback: ' . $e->getMessage());
        }
    }
}

