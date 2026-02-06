<?php
namespace App\Models;

use CodeIgniter\Model;

class UcProgressModel extends Model
{
    protected $table = 'uc_progress';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'user_id',
        'course_id',
        'module_id',
        'active_task',
        'progress',
        'points',
        'is_completed',
        'completed_task_ids',
        'timestamp'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|max_length[100]',
        'course_id' => 'required|max_length[50]',
        'module_id' => 'required|max_length[50]',
        'active_task' => 'required|integer',
        'progress' => 'required|decimal',
        'points' => 'required|integer',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'O ID do usuário é obrigatório'
        ],
        'module_id' => [
            'required' => 'O ID do módulo é obrigatório'
        ]
    ];

    /**
     * Insere ou atualiza progresso do UC
     */
    public function upsertProgress(array $data)
    {
        // Converter completed_task_ids para JSON se for array
        if (isset($data['completed_task_ids']) && is_array($data['completed_task_ids'])) {
            $data['completed_task_ids'] = json_encode($data['completed_task_ids']);
        }

        $existing = $this->where([
            'user_id' => $data['user_id'],
            'module_id' => $data['module_id']
        ])->first();

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Busca progresso de um usuário em um módulo específico
     */
    public function getUserModuleProgress(string $userId, string $moduleId)
    {
        $result = $this->where([
            'user_id' => $userId,
            'module_id' => $moduleId
        ])->first();

        if ($result && isset($result['completed_task_ids'])) {
            $result['completed_task_ids'] = json_decode($result['completed_task_ids'], true);
        }

        return $result;
    }

    /**
     * Busca todos os módulos concluídos de um usuário
     */
    public function getCompletedModules(string $userId, string $courseId = null)
    {
        $builder = $this->where('user_id', $userId)
                        ->where('is_completed', 1);

        if ($courseId) {
            $builder->where('course_id', $courseId);
        }

        return $builder->findAll();
    }

    /**
     * Calcula progresso geral do curso baseado em UCs
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
            'is_completed' => 1
        ])->countAllResults();

        $avgProgress = $this->selectAvg('progress', 'avg')
                            ->where([
                                'user_id' => $userId,
                                'course_id' => $courseId
                            ])
                            ->first();

        return [
            'total_modules' => $total,
            'completed_modules' => $completed,
            'avg_progress' => round($avgProgress['avg'] ?? 0, 2),
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0
        ];
    }

    /**
     * Retorna ranking de pontos
     */
    public function getLeaderboard(string $courseId, int $limit = 10)
    {
        return $this->select('user_id, SUM(points) as total_points')
                    ->where('course_id', $courseId)
                    ->groupBy('user_id')
                    ->orderBy('total_points', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }
}
