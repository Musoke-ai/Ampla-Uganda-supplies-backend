<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'custId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'custOwner' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'custName' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'custContact' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'custEmail' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'custLocation' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'custDateCreated datetime not null default current_timestamp',
            'custDateUpdated datetime not null default current_timestamp',
            'custDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('custId', true);
        $this->forge->createTable('customers');
    }

    public function down()
    {
        $this->forge->dropTable('customers');
    }
}
