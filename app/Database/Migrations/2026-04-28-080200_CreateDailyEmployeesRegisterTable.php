<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyEmployeesRegisterTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'ID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'empID' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'payment' => [
                'type' => 'FLOAT',
            ],
            'amountPaid' => [
                'type' => 'FLOAT',
            ],
            'dailyEmployeeDateCreated datetime not null default current_timestamp',
            'dailyEmployeeDateUpdated datetime not null default current_timestamp',
            'dailyEmployeeDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('ID', true);
        $this->forge->createTable('daily_employees_register');
    }

    public function down()
    {
        $this->forge->dropTable('daily_employees_register');
    }
}
