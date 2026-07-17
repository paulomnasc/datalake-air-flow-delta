<?php
namespace App\Models;

use CodeIgniter\Model;

class VideoProgressModel extends Model
{
    protected $table = 'video_progress';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'user_id',
        'video_id',
        'percent',
        'watched_seconds',
        'total_seconds',
        'completed',
        'last_position_seconds'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|max_length[255]',
        'video_id' => 'required|integer|is_not_unique[video.id]',
        'percent' => 'permit_empty|decimal',
        'watched_seconds' => 'permit_empty|integer',
        'total_seconds' => 'permit_empty|integer',
        'completed' => 'in_list[0,1]',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'O ID do usuário é obrigatório'
        ],
        'video_id' => [
            'required' => 'O ID do vídeo é obrigatório',
            'is_not_unique' => 'O vídeo selecionado não existe'
        ]
    ];

    /**
     * Insere ou atualiza progresso do vídeo
     */
    public function upsertProgress(array $data)
    {
        $existing = $this->where([
            'user_id' => $data['user_id'],
            'video_id' => $data['video_id']
        ])->first();

        if ($existing) {
            // Atualizar apenas se o progresso for maior
            if ($data['percent'] > $existing['percent'] || $data['completed']) {
                return $this->update($existing['id'], $data);
            }
            return true;
        }

        return $this->insert($data);
    }

    /**
     * Busca progresso de um usuário em um vídeo específico
     */
    public function getUserVideoProgress(string $userId, string $videoId)
    {
        return $this->where([
            'user_id' => $userId,
            'video_id' => $videoId
        ])->first();
    }

    /**
     * Busca todos os vídeos concluídos de um usuário
     */
    public function getCompletedVideos(string $userId)
    {
        return $this->where('user_id', $userId)
                    ->where('completed', 1)
                    ->findAll();
    }

    /**
     * Busca progresso de vídeos de um módulo
     */
    public function getModuleProgress(string $userId, int $moduleId)
    {
        $videoModel = new \App\Models\VideoModel();
        $videos = $videoModel->where('module_id', $moduleId)->findAll();
        
        $videoIds = array_column($videos, 'id');
        
        if (empty($videoIds)) {
            return [];
        }
        
        return $this->where('user_id', $userId)
                    ->whereIn('video_id', $videoIds)
                    ->findAll();
    }

    /**
     * Atualiza posição atual do vídeo (para resume)
     */
    public function updatePosition(string $userId, int $videoId, int $seconds)
    {
        $existing = $this->getUserVideoProgress($userId, $videoId);
        
        $data = [
            'last_position_seconds' => $seconds,
            'watched_seconds' => $seconds
        ];
        
        if ($existing) {
            return $this->update($existing['id'], $data);
        }
        
        $data['user_id'] = $userId;
        $data['video_id'] = $videoId;
        return $this->insert($data);
    }
}
