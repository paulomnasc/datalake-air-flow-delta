<?php

namespace App\Models;

use CodeIgniter\Model;

class VideoFeedbackModel extends Model
{
    protected $table = 'video_feedback';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    
    protected $allowedFields = [
        'user_id',
        'video_id',
        'lab_status',
        'value_perception',
        'open_feedback'
    ];
    
    protected $useTimestamps = false;
    
    /**
     * Salva o feedback do usuário
     */
    public function saveFeedback($userId, $videoId, $labStatus, $valuePerception, $openFeedback = null)
    {
        // Verifica se já existe feedback para este usuário e vídeo
        $existing = $this->where('user_id', $userId)
                         ->where('video_id', $videoId)
                         ->first();
        
        $data = [
            'user_id' => $userId,
            'video_id' => $videoId,
            'lab_status' => $labStatus,
            'value_perception' => $valuePerception,
            'open_feedback' => $openFeedback
        ];
        
        if ($existing) {
            // Atualiza se já existe
            return $this->update($existing['id'], $data);
        } else {
            // Insere novo registro
            return $this->insert($data);
        }
    }
    
    /**
     * Obter feedback de um usuário para um vídeo
     */
    public function getUserVideoFeedback($userId, $videoId)
    {
        return $this->where('user_id', $userId)
                    ->where('video_id', $videoId)
                    ->first();
    }
}
