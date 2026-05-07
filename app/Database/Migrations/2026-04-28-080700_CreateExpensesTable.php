<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExpensesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'amount' => [
                'type' => 'FLOAT',
            ],
            'givenTo' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'remarks' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'expenseDateCreated datetime not null default current_timestamp',
            'expenseDateUpdated datetime not null default current_timestamp',
            'expenseDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('expenses');
    }

    public function down()
    {
        $this->forge->dropTable('expenses');
    }
}
