<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyRawmaterialsRegisterTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'materialId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'quantity' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'totalCost' => [
                'type' => 'FLOAT',
            ],
            'initials' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'dailyRawmaterialsDateCreated datetime not null default current_timestamp',
            'dailyRawmaterialsDateUpdated datetime not null default current_timestamp',
            'dailyRawmaterialsDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('daily_rawmaterials_register');
    }

    public function down()
    {
        $this->forge->dropTable('daily_rawmaterials_register');
    }
}
