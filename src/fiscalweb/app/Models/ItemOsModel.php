<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemOsModel extends Model
{
    protected $table            = 'item_os';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['Quantidade_Horas', 'Profissional_Alocado', 'id_servico'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id, profissional_alocado as descricao')->findAll();
        return $data;
    }

    public function findAllWithOS()
    {
        $db = \Config\Database::connect();
        $builder = $db->table($this->table . ' io')
            ->select('io.id, io.Quantidade_Horas, io.Profissional_Alocado, io.id_servico, os.nup_sei, os.id as id_os')
            ->join('os_item_os oio', 'oio.id_item_os = io.id', 'left')
            ->join('ordem_servico os', 'os.id = oio.id_os', 'left')
            ->orderBy('io.id', 'DESC');
        
        return $builder->get()->getResult();
    }

}
