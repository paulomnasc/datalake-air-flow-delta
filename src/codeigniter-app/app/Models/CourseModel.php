<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table            = 'course';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'course_id',
        'name',
        'description',
        'icon_url',
        'color',
        'order',
        'is_active',
        'created_by',
        'stripe_price_id'
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
        'course_id' => 'required|max_length[50]', // Remove is_unique para update
        'name' => 'required|max_length[255]',
        'color' => 'permit_empty|max_length[7]',
        'order' => 'permit_empty|integer',
        'is_active' => 'in_list[0,1]',
        'stripe_price_id' => 'permit_empty|max_length[255]'
    ];

    protected $validationMessages = [
        'course_id' => [
            'required' => 'O ID do curso é obrigatório',
            'is_unique' => 'Este ID de curso já existe'
        ],
        'name' => [
            'required' => 'O nome do curso é obrigatório'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $beforeInsert = [];
    protected $afterInsert  = [];
    protected $beforeUpdate = [];
    protected $afterUpdate  = [];
    protected $beforeFind   = [];
    protected $afterFind    = [];
    protected $beforeDelete = [];
    protected $afterDelete  = [];

    /**
     * Busca curso com módulos associados
     */
    public function getCourseWithModules(int $courseId)
    {
        $course = $this->find($courseId);
        if (!$course) {
            return null;
        }

        $moduleModel = new \App\Models\ModuleModel();
        $course['modules'] = $moduleModel->where('course_id', $courseId)
            ->where('is_active', 1)
            ->orderBy('module_number', 'ASC')
            ->findAll();

        return $course;
    }

    /**
     * Busca todos os cursos ativos ordenados
     */
    public function getActiveCourses()
    {
        return $this->where('is_active', 1)
            ->orderBy('order', 'ASC')
            ->findAll();
    }

    /**
     * Busca curso por course_id (string identifier)
     */
    public function getByCourseId(string $courseId)
    {
        return $this->where('course_id', $courseId)->first();
    }
}
