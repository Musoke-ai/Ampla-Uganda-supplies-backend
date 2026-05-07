<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'orderId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'custId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'prodId' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'customSize' => [
                'type'       => 'VARCHAR',
                'constraint' => 11,
                'null'       => true,
            ],
            'layers' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
            'quantity' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'totalCost' => [
                'type' => 'FLOAT',
            ],
            'amountPaid' => [
                'type' => 'FLOAT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'quantityProduced' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'orderDateCreated datetime not null default current_timestamp',
            'orderDateUpdated datetime not null default current_timestamp',
            'orderDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('orderId', true);
        $this->forge->createTable('orders');
    }

    public function down()
    {
        $this->forge->dropTable('orders');
    }
}
