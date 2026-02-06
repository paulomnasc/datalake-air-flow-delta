<?php

namespace App\Models;

use CodeIgniter\Model;

class UcDefinitionModel extends Model
{
    protected $table            = 'uc_definition';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'uc_id',
        'video_id',
        'task_number',
        'task_title',
        'task_description',
        'video_checkpoint',
        'xp_points',
        'is_active',
        'order',
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
        'uc_id' => 'required|max_length[100]|is_unique[uc_definition.uc_id,id,{id}]',
        'video_id' => 'required|integer|is_not_unique[video.id]',
        'task_number' => 'required|integer',
        'task_title' => 'required|max_length[255]',
        'video_checkpoint' => 'permit_empty|max_length[20]',
        'xp_points' => 'permit_empty|integer',
        'is_active' => 'in_list[0,1]',
        'order' => 'permit_empty|integer'
    ];

    protected $validationMessages = [
        'uc_id' => [
            'required' => 'O ID da UC é obrigatório',
            'is_unique' => 'Este ID de UC já existe'
        ],
        'video_id' => [
            'required' => 'O ID do vídeo é obrigatório',
            'is_not_unique' => 'O vídeo selecionado não existe'
        ],
        'task_number' => [
            'required' => 'O número da tarefa é obrigatório'
        ],
        'task_title' => [
            'required' => 'O título da tarefa é obrigatório'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Busca UCs por vídeo
     */
    public function getUCsByVideo(int $videoId)
    {
        return $this->where('video_id', $videoId)
            ->where('is_active', 1)
            ->orderBy('task_number', 'ASC')
            ->findAll();
    }

    /**
     * Busca UC por uc_id (string identifier)
     */
    public function getByUcId(string $ucId)
    {
        return $this->where('uc_id', $ucId)->first();
    }

    /**
     * Conta total de UCs de um vídeo
     */
    public function countByVideo(int $videoId): int
    {
        return $this->where('video_id', $videoId)->countAllResults();
    }

    /**
     * Calcula total de XP disponível em um vídeo
     */
    public function getTotalXpByVideo(int $videoId): int
    {
        $result = $this->selectSum('xp_points')
            ->where('video_id', $videoId)
            ->where('is_active', 1)
            ->first();

        return $result['xp_points'] ?? 0;
    }

    /**
     * Busca próxima tarefa disponível (por task_number) em um vídeo
     */
    public function getNextTask(int $videoId, int $currentTaskNumber)
    {
        return $this->where('video_id', $videoId)
            ->where('task_number >', $currentTaskNumber)
            ->where('is_active', 1)
            ->orderBy('task_number', 'ASC')
            ->first();
    }
}
