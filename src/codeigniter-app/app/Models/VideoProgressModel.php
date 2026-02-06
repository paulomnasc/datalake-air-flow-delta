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
        'course_id',
        'lesson_id',
        'video_id',
        'percent',
        'completed',
        'timestamp'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|max_length[100]',
        'course_id' => 'required|max_length[50]',
        'lesson_id' => 'required|max_length[50]',
        'video_id' => 'required|max_length[50]',
        'percent' => 'required|decimal',
        'completed' => 'required|in_list[0,1]',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'O ID do usuário é obrigatório'
        ],
        'video_id' => [
            'required' => 'O ID do vídeo é obrigatório'
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
    public function getCompletedVideos(string $userId, string $courseId = null)
    {
        $builder = $this->where('user_id', $userId)
                        ->where('completed', 1);

        if ($courseId) {
            $builder->where('course_id', $courseId);
        }

        return $builder->findAll();
    }

    /**
     * Calcula progresso geral do curso
     */
    public function getCourseProgress(string $userId, string $courseId)
    {
        $total = $this->where([
            'user_id' => $userId,
            'course_id' => $courseId
        ])->countAllResults();

        $completed = $this->where([
            'user_id' => $userId,
            'course_id' => $courseId,
            'completed' => 1
        ])->countAllResults();

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }
}
