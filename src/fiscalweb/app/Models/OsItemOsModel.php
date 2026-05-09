<?php

namespace App\Models;

use CodeIgniter\Model;
use CodeIgniter\Database\Exceptions\DatabaseException;

class OsItemOsModel extends Model
{
    protected $table            = 'os_item_os';
    protected $primaryKey       = 'id_os';
    protected $useAutoIncrement = false;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['id_os', 'id_item_os'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    public function listToCombo()
    {
        $data = $this->select('id_os, id_os')->findAll();
        return $data;
    }

    public function getAssocByTarget($target_id, $target_col)
    {
        return $this->where($target_col, $target_id)->findAll();
    }

    public function insertAssociation($id_os, $id_item_os)
    {
        try {
            $db = \Config\Database::connect();
            $db->table($this->table)->insert([
                'id_os' => $id_os,
                'id_item_os' => $id_item_os
            ]);
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage());
        }
    }

    public function updateAssociation($id_item_os, $id_os_new)
    {
        try {
            $db = \Config\Database::connect();
            $db->table($this->table)
                ->where('id_item_os', $id_item_os)
                ->set(['id_os' => $id_os_new])
                ->update();
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage());
        }
    }

    public function deleteAssociation($id_item_os)
    {
        try {
            $db = \Config\Database::connect();
            $db->table($this->table)
                ->where('id_item_os', $id_item_os)
                ->delete();
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage());
        }
    }
}
