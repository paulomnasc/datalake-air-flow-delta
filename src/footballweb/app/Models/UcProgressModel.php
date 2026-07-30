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
        'uc_definition_id',
        'completed',
        'completed_at',
        'progress_percent',
        'attempts'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'user_id' => 'required|max_length[255]',
        'uc_definition_id' => 'required|integer|is_not_unique[uc_definition.id]',
        'completed' => 'in_list[0,1]',
        'progress_percent' => 'permit_empty|decimal',
        'attempts' => 'permit_empty|integer',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'O ID do usuário é obrigatório'
        ],
        'uc_definition_id' => [
            'required' => 'O ID da UC é obrigatório',
            'is_not_unique' => 'A UC selecionada não existe'
        ]
    ];

    /**
     * Calcula XP ganho por um usuário em um curso
     */
    public function getCourseXp($userId, $courseId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('uc_progress up');
        $result = $builder->select('SUM(ud.xp_points) as total_xp')
            ->join('uc_definition ud', 'up.uc_definition_id = ud.id')
            ->join('video v', 'ud.video_id = v.id')
            ->join('module m', 'v.module_id = m.id')
            ->where('m.course_id', $courseId)
            ->where('up.user_id', $userId)
            ->where('up.completed', 1)
            ->get()
            ->getRowArray();
        return $result['total_xp'] ?? 0;
    }

    /**
     * Insere ou atualiza progresso do UC
     */
    public function upsertProgress(array $data)
    {
        $existing = $this->where([
            'user_id' => $data['user_id'],
            'uc_definition_id' => $data['uc_definition_id']
        ])->first();

        if ($existing) {
            return $this->update($existing['id'], $data);
        }

        return $this->insert($data);
    }

    /**
     * Busca progresso de um usuário em uma UC específica
     */
    public function getUserUcProgress(string $userId, int $ucDefinitionId)
    {
        return $this->where([
            'user_id' => $userId,
            'uc_definition_id' => $ucDefinitionId
        ])->first();
    }

    /**
     * Marca UC como completa
     */
    public function completeUc(string $userId, int $ucDefinitionId)
    {
        $data = [
            'user_id' => $userId,
            'uc_definition_id' => $ucDefinitionId,
            'completed' => 1,
            'completed_at' => date('Y-m-d H:i:s'),
            'progress_percent' => 100
        ];
        
        $existing = $this->getUserUcProgress($userId, $ucDefinitionId);
        
        if ($existing) {
            $data['attempts'] = ($existing['attempts'] ?? 0) + 1;
            return $this->update($existing['id'], $data);
        }
        
        $data['attempts'] = 1;
        return $this->insert($data);
    }

    /**
     * Busca todas as UCs completadas por um usuário em um vídeo
     */
    public function getCompletedUcsByVideo(string $userId, int $videoId)
    {
        $ucDefModel = new \App\Models\UcDefinitionModel();
        $definitions = $ucDefModel->where('video_id', $videoId)->findAll();
        
        $ucDefIds = array_column($definitions, 'id');
        
        if (empty($ucDefIds)) {
            return [];
        }
        
        return $this->where('user_id', $userId)
                    ->where('completed', 1)
                    ->whereIn('uc_definition_id', $ucDefIds)
                    ->findAll();
    }

    /**
     * Calcula total de XP ganho por um usuário
     */
    public function getTotalXp(string $userId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('uc_progress up');
        
        $result = $builder->select('SUM(ud.xp_points) as total_xp')
            ->join('uc_definition ud', 'up.uc_definition_id = ud.id')
            ->where('up.user_id', $userId)
            ->where('up.completed', 1)
            ->get()
            ->getRowArray();
        
        return $result['total_xp'] ?? 0;
    }

    /**
     * Busca progresso de um usuário em um curso
     */
    public function getCourseProgress(string $userId, int $courseId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('uc_progress up');
        
        // Total de UCs no curso
        $totalBuilder = clone $builder;
        $total = $totalBuilder->select('COUNT(DISTINCT ud.id) as total')
            ->join('uc_definition ud', 'up.uc_definition_id = ud.id')
            ->join('video v', 'ud.video_id = v.id')
            ->join('module m', 'v.module_id = m.id')
            ->where('m.course_id', $courseId)
            ->get()
            ->getRowArray();
        
        // UCs completadas
        $completed = $builder->select('COUNT(DISTINCT ud.id) as completed')
            ->join('uc_definition ud', 'up.uc_definition_id = ud.id')
            ->join('video v', 'ud.video_id = v.id')
            ->join('module m', 'v.module_id = m.id')
            ->where('m.course_id', $courseId)
            ->where('up.user_id', $userId)
            ->where('up.completed', 1)
            ->get()
            ->getRowArray();
        
        $totalCount = $total['total'] ?? 0;
        $completedCount = $completed['completed'] ?? 0;
        
        return [
            'total_ucs' => $totalCount,
            'completed_ucs' => $completedCount,
            'completion_rate' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0
        ];
    }
}
