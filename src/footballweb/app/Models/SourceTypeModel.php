<?php namespace App\Models;

use CodeIgniter\Model;

class SourceTypeModel extends Model
{
    protected $table = 'source_types';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['description'];

    /**
     * Retorna todos os tipos de fonte para um dropdown.
     */
    public function listToCombo()
    {
        return $this->findAll();
    }
}