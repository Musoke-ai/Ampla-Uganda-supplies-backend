<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDailyExpenseRegisterTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'Description' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'amount' => [
                'type' => 'FLOAT',
            ],
            'payee' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'expenseDateCreated datetime not null default current_timestamp',
            'expenseDateUpdated datetime not null default current_timestamp',
            'expenseDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('daily_expense_register');
    }

    public function down()
    {
        $this->forge->dropTable('daily_expense_register');
    }
}
