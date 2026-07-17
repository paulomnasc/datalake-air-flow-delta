<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVideoProgressTable extends Migration
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
            'lesson_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'video_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'percent' => [
                'type' => 'DECIMAL',
                'constraint' => '5,2',
            ],
            'completed' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
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
        $this->forge->addUniqueKey(['user_id', 'video_id'], 'unique_progress');
        $this->forge->addKey('user_id');
        $this->forge->addKey('course_id');
        
        $this->forge->createTable('video_progress');
    }

    public function down()
    {
        $this->forge->dropTable('video_progress');
    }
}
