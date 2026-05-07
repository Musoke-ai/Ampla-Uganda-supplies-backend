<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'customOrderId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'custId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'layerType' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'otherDesc' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'customDateCreated datetime not null default current_timestamp',
            'customDateUpdated datetime not null default current_timestamp',
            'custDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('customOrderId', true);
        $this->forge->createTable('customorders');
    }

    public function down()
    {
        $this->forge->dropTable('customorders');
    }
}
