<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfigModel extends Model
{
    protected $table            = 'dag_configurations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    //protected $returnType       = 'array';
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields = ['id', 
                                'pasta_id', 
                                'dag_id', 
                                'is_active', 
                                'owner', 
                                'schedule_interval', 
                                'description', 
                                'source_type', 
                                'source_filename', 
                                'target_table_name', 
                                'python_module_path', 
                                'transform_args',
                                // NOVOS CAMPOS PARA SSH TUNNELING
                                'ssh_host',
                                'ssh_port',
                                'ssh_user',
                                'ssh_key_path',
                                'ssh_local_port',
                            ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];


    /*
    public function ObterTotalQuadros($idUsuario)
    {
        // Usa a função count com alias para contagem de quadros diretamente
        $this->select('COUNT(DISTINCT q.id) as total_quadros');
        $this->from('pasta p');
        $this->join('usuario u', 'u.id = p.id_usuario');
        $this->join('quadro q', 'q.id_pasta = p.id');
        $this->where('u.id', $idUsuario);

        // Obter o total de quadros
        $query = $this->get();
        $result = $query->getRow();

        return $result->total_quadros ?? 0;
    }
    */




}
