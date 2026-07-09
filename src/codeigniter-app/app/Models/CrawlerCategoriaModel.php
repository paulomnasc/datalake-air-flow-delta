<?php

namespace App\Models;

use CodeIgniter\Model;

class CrawlerCategoriaModel extends Model
{
    protected $table            = 'crawler_categorias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['nome'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'nome' => 'required|min_length[3]|max_length[255]|is_unique[crawler_categorias.nome,id,{id}]',
    ];
    protected $validationMessages   = [
        'nome' => [
            'required'  => 'O nome da categoria é obrigatório.',
            'is_unique' => 'Esta categoria já está cadastrada.',
            'min_length'=> 'O nome da categoria deve ter no mínimo 3 caracteres.'
        ]
    ];
    protected $skipValidation       = false;
}
