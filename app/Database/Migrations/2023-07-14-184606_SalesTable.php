<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SalesTable extends Migration
{
    public function up()
    {
        //add fields to sales table
        $this->forge->addField([
            'saleId' => [
                'type' => 'int',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true,
                'null' => false
            ],
            'saleItemId' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0,
                'null' => false
            ],
            'saleOwner' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'custContacts' => [
                'type' => 'varchar',
                'constraint' => 150,
                'null' => false,
                // 'default' => 'none'
            ],
            'custName' => [
                'type' => 'varchar',
                'constraint' => 150,
                'null' => false,
                // 'default' => 'none'
            ],
            'salePrice' => [
                'type' => 'varchar',
                'constraint' => 150,
                'null' => false,
                // 'default' => 'none'
            ],
            'saleQuantity' => [
                'type' => 'int',
                'constraint' => 11,
                'null' => false,
                // 'default' => 'none'
            ],
            'saleToday' => [
                'type' => 'int',
                'constraint' => 11
            ],
            'saleTodayAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0,
            ],
            'saleMonth' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'saleMonthAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'saleYear' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'saleYearAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'saleDateCreated datetime default current_timestamp',
            'saleDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'saleDateDeleted datetime default null'
        ]);

        $this->forge->addKey('saleId', true);
        // $this->forge->addForeignKey('saleItemId', 'inventory', 'itemId', 'CASCADE', 'CASCADE');
        // $this->forge->addForeignKey('saleOwner', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sales');
    }

    public function down()
    {
        //drop sales table
        $this->forge->dropTable('sales');
    }
}
