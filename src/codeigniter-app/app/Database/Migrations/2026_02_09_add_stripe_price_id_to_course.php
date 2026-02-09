<?php
// src/codeigniter-app/app/Database/Migrations/2026_02_09_add_stripe_price_id_to_course.php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStripePriceIdToCourse extends Migration
{
    public function up()
    {
        $fields = [
            'stripe_price_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'created_by'
            ]
        ];
        $this->forge->addColumn('course', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('course', 'stripe_price_id');
    }
}
