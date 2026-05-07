<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRawMaterialsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'materialId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'materialCode' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'size' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'unitOfMeasure' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'pcs',
            ],
            'Quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,3',
                'default'    => 0,
            ],
            'unitPrice' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,2',
                'default'    => 0,
            ],
            'reorderLevel' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,3',
                'default'    => 0,
            ],
            'supplier' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'supplierContact' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'storageLocation' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'active',
            ],
            'note' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'expiry' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'rawMaterialDateCreated datetime not null default current_timestamp',
            'rawMaterialDateUpdated datetime not null default current_timestamp',
            'rawMaterialDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('materialId', true);
        $this->forge->createTable('raw_materials');
    }

    public function down()
    {
        $this->forge->dropTable('raw_materials');
    }
}
