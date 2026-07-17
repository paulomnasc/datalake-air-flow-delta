<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUcProgressTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'course_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'module_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'active_task' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'progress' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
            ],
            'points' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'is_completed' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'completed_task_ids' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'timestamp' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'module_id'], 'unique_uc');
        $this->forge->addKey('user_id');
        
        $this->forge->createTable('uc_progress');
    }

    public function down()
    {
        $this->forge->dropTable('uc_progress');
    }
}
