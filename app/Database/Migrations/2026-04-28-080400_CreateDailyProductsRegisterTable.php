<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyProductsRegisterTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'prodId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'Quantity' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'initials' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'dailyProductionDateCreated datetime not null default current_timestamp',
            'dailyProductionDateUpdated datetime not null default current_timestamp',
            'dailyProductionDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('daily_products_register');
    }

    public function down()
    {
        $this->forge->dropTable('daily_products_register');
    }
}
