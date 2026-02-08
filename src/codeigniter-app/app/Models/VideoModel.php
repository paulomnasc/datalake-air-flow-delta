<?php

namespace App\Models;

use CodeIgniter\Model;

class VideoModel extends Model
{
    protected $table            = 'video';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'video_id',
        'module_id',
        'youtube_id',
        'title',
        'description',
        'thumbnail_url',
        'duration_seconds',
        'video_order',
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
        'video_id' => 'required|max_length[100]|is_unique[video.video_id,id,{id}]',
        'module_id' => 'required|integer|is_not_unique[module.id]',
        'youtube_id' => 'required|max_length[100]',
        'title' => 'required|max_length[255]',
        'duration_seconds' => 'permit_empty|integer',
        'video_order' => 'permit_empty|integer',
        'is_active' => 'in_list[0,1]'
    ];

    protected $validationMessages = [
        'video_id' => [
            'required' => 'O ID do vídeo é obrigatório',
            'is_unique' => 'Este ID de vídeo já existe'
        ],
        'module_id' => [
            'required' => 'O ID do módulo é obrigatório',
            'is_not_unique' => 'O módulo selecionado não existe'
        ],
        'youtube_id' => [
            'required' => 'O ID do YouTube é obrigatório'
        ],
        'title' => [
            'required' => 'O título do vídeo é obrigatório'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Busca vídeo com UCs/tarefas associadas
     */
    public function getVideoWithUCs(int $videoId)
    {
        $video = $this->find($videoId);
        if (!$video) {
            return null;
        }

        $ucModel = new \App\Models\UcDefinitionModel();
        $video['ucs'] = $ucModel->where('video_id', $videoId)
            ->where('is_active', 1)
            ->orderBy('task_number', 'ASC')
            ->findAll();

        return $video;
    }

    /**
     * Busca vídeos por módulo
     */
    public function getVideosByModule(int $moduleId)
    {
        return $this->where('module_id', $moduleId)
            ->where('is_active', 1)
            ->orderBy('video_order', 'ASC')
            ->findAll();
    }

    /**
     * Busca vídeo por video_id (string identifier)
     */
    public function getByVideoId(string $videoId)
    {
        return $this->where('video_id', $videoId)->first();
    }

    /**
     * Busca vídeo por YouTube ID
     */
    public function getByYoutubeId(string $youtubeId)
    {
        return $this->where('youtube_id', $youtubeId)->first();
    }

    /**
     * Conta total de vídeos de um módulo
     */
    public function countByModule(int $moduleId): int
    {
        return $this->where('module_id', $moduleId)->countAllResults();
    }
}
