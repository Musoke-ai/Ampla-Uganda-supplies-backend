<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockMovementsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'auto_increment' => true,
            ],
            'branchId' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'productId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'rawMaterialId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'movementType' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'quantityIn' => [
                'type' => 'DECIMAL',
                'constraint' => '15,3',
                'default' => '0.000',
            ],
            'quantityOut' => [
                'type' => 'DECIMAL',
                'constraint' => '15,3',
                'default' => '0.000',
            ],
            'balanceAfter' => [
                'type' => 'DECIMAL',
                'constraint' => '15,3',
                'default' => '0.000',
            ],
            'unitCost' => [
                'type' => 'DECIMAL',
                'constraint' => '15,2',
                'null' => true,
            ],
            'referenceType' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'referenceId' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'referenceNo' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'userId' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'movementDateCreated datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['branchId', 'productId', 'movementDateCreated']);
        $this->forge->addKey(['branchId', 'rawMaterialId', 'movementDateCreated']);
        $this->forge->addKey(['referenceType', 'referenceId']);
        $this->forge->createTable('stock_movements');
    }

    public function down()
    {
        $this->forge->dropTable('stock_movements');
    }
}
