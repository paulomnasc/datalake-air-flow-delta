<?php

namespace App\Models;

use CodeIgniter\Model;

class CrawlerUrlModel extends Model
{
    protected $table            = 'crawler_urls';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['categoria_id', 'url'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'categoria_id' => 'required|integer',
        'url'          => 'required|valid_url|max_length[500]',
    ];
    protected $validationMessages   = [
        'categoria_id' => [
            'required' => 'A categoria é obrigatória.',
            'integer'  => 'A categoria selecionada é inválida.'
        ],
        'url' => [
            'required' => 'A URL é obrigatória.',
            'valid_url' => 'Insira uma URL válida (ex: https://exemplo.com).'
        ]
    ];
    protected $skipValidation       = false;
}
