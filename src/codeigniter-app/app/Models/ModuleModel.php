<?php

namespace App\Models;

use CodeIgniter\Model;

class ModuleModel extends Model
{
    protected $table            = 'module';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'module_id',
        'course_id',
        'name',
        'description',
        'module_number',
        'order',
        'estimated_hours',
        'is_active',
        'created_by'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'module_id' => 'required|max_length[50]',
        'course_id' => 'required|integer|is_not_unique[course.id]',
        'name' => 'required|max_length[255]',
        'module_number' => 'required|integer',
        'order' => 'permit_empty|integer',
        'estimated_hours' => 'permit_empty|decimal',
        'is_active' => 'in_list[0,1]'
    ];

    protected $validationMessages = [
        'module_id' => [
            'required' => 'O ID do módulo é obrigatório'
        ],
        'course_id' => [
            'required' => 'O ID do curso é obrigatório',
            'is_not_unique' => 'O curso selecionado não existe'
        ],
        'name' => [
            'required' => 'O nome do módulo é obrigatório'
        ],
        'module_number' => [
            'required' => 'O número do módulo é obrigatório'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Busca módulo com vídeos associados
     */
    public function getModuleWithVideos(int $moduleId)
    {
        $module = $this->find($moduleId);
        if (!$module) {
            return null;
        }

        $videoModel = new \App\Models\VideoModel();
        $module['videos'] = $videoModel->where('module_id', $moduleId)
            ->where('is_active', 1)
            ->orderBy('video_order', 'ASC')
            ->findAll();

        return $module;
    }

    /**
     * Busca módulos por curso
     */
    public function getModulesByCourse(int $courseId)
    {
        return $this->where('course_id', $courseId)
            ->where('is_active', 1)
            ->orderBy('module_number', 'ASC')
            ->findAll();
    }

    /**
     * Busca módulo por module_id (string identifier) e course_id
     */
    public function getByModuleId(string $moduleId, int $courseId)
    {
        return $this->where([
            'module_id' => $moduleId,
            'course_id' => $courseId
        ])->first();
    }

    /**
     * Conta total de módulos de um curso
     */
    public function countByCourse(int $courseId): int
    {
        return $this->where('course_id', $courseId)->countAllResults();
    }
}
