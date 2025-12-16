<?php

namespace App\Models;

use CodeIgniter\Model;

class TableSelectionModel extends Model
{
    protected $table            = 'dag_table_selections';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'id_dag_config',
        'table_name',
        'is_selected',
        'row_count',
        'last_sync',
        'created_at',
        'updated_at'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Busca todas as tabelas selecionadas para uma DAG específica
     */
    public function getSelectedTables(int $dagConfigId): array
    {
        return $this->where('id_dag_config', $dagConfigId)
                    ->where('is_selected', 1)
                    ->orderBy('table_name', 'ASC')
                    ->findAll();
    }

    /**
     * Busca todas as tabelas (selecionadas e não) para uma DAG
     */
    public function getAllTablesForDag(int $dagConfigId): array
    {
        return $this->where('id_dag_config', $dagConfigId)
                    ->orderBy('table_name', 'ASC')
                    ->findAll();
    }

    /**
     * Salva seleções de tabelas em batch
     */
    public function saveTableSelections(int $dagConfigId, array $tableSelections): bool
    {
        $this->db->transStart();

        // Remove seleções antigas
        $this->where('id_dag_config', $dagConfigId)->delete();

        // Insere novas seleções
        foreach ($tableSelections as $selection) {
            $data = [
                'id_dag_config' => $dagConfigId,
                'table_name'    => $selection['table_name'],
                'is_selected'   => $selection['is_selected'] ?? true,
                'row_count'     => $selection['row_count'] ?? null
            ];
            $this->insert($data);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Atualiza o status de seleção de uma tabela específica
     */
    public function toggleSelection(int $dagConfigId, string $tableName, bool $isSelected): bool
    {
        return $this->where('id_dag_config', $dagConfigId)
                    ->where('table_name', $tableName)
                    ->set('is_selected', $isSelected)
                    ->update();
    }

    /**
     * Conta quantas tabelas estão selecionadas para uma DAG
     */
    public function countSelectedTables(int $dagConfigId): int
    {
        return $this->where('id_dag_config', $dagConfigId)
                    ->where('is_selected', 1)
                    ->countAllResults();
    }
}
