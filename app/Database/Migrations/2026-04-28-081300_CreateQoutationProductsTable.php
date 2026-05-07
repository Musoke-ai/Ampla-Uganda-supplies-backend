<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQoutationProductsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'QTNItems_ID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'itemId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'QTNID' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'QTNItemDateCreated datetime not null default current_timestamp',
            'QTNItemDateUpdated datetime not null default current_timestamp',
            'QTNItemDateDeleted datetime default null',
        ]);

        $this->forge->addKey('QTNItems_ID', true);
        $this->forge->createTable('qoutationproducts');
    }

    public function down()
    {
        $this->forge->dropTable('qoutationproducts');
    }
}
