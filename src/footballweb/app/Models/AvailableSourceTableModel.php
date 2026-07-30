<?php

namespace App\Models;

use CodeIgniter\Model;

class AvailableSourceTableModel extends Model
{
    protected $table            = 'available_source_tables';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'connection_id',
        'database_name',
        'table_name',
        'table_schema',
        'row_count',
        'table_size_mb',
        'last_updated'
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    /**
     * Busca tabelas disponíveis para uma conexão específica
     */
    public function getTablesForConnection(string $connectionId, string $databaseName): array
    {
        return $this->where('connection_id', $connectionId)
                    ->where('database_name', $databaseName)
                    ->orderBy('table_name', 'ASC')
                    ->findAll();
    }

    /**
     * Atualiza cache de tabelas de uma fonte MySQL
     */
    public function refreshMySQLTables(string $connectionId, string $databaseName, \mysqli $connection): int
    {
        // Query para obter metadados das tabelas
        $query = "
            SELECT 
                TABLE_NAME as table_name,
                TABLE_SCHEMA as table_schema,
                TABLE_ROWS as row_count,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS table_size_mb
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = '$databaseName'
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ";

        $result = $connection->query($query);
        
        if (!$result) {
            return 0;
        }

        $this->db->transStart();

        // Remove entradas antigas desta conexão
        $this->where('connection_id', $connectionId)
             ->where('database_name', $databaseName)
             ->delete();

        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $this->insert([
                'connection_id'  => $connectionId,
                'database_name'  => $databaseName,
                'table_name'     => $row['table_name'],
                'table_schema'   => $row['table_schema'],
                'row_count'      => $row['row_count'],
                'table_size_mb'  => $row['table_size_mb']
            ]);
            $count++;
        }

        $this->db->transComplete();

        return $count;
    }

    /**
     * Verifica se o cache precisa ser atualizado (mais de 1 hora)
     */
    public function needsRefresh(string $connectionId, string $databaseName): bool
    {
        $lastUpdate = $this->selectMax('last_updated')
                           ->where('connection_id', $connectionId)
                           ->where('database_name', $databaseName)
                           ->first();

        if (!$lastUpdate || !$lastUpdate->last_updated) {
            return true;
        }

        $lastUpdateTime = strtotime($lastUpdate->last_updated);
        $hourAgo = time() - 3600;

        return $lastUpdateTime < $hourAgo;
    }
}
