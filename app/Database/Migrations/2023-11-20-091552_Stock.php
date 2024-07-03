<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Stock extends Migration
{
    public function up()
    {        //add fields to stock table
        $this->forge->addField([
         'stockId' => [
             'type' => 'int',
             'constraint' => 11,
             'unsigned' => false,
             'auto_increment' => true,
             'null' => false
         ],
         'stockItem' => [
             'type' => 'int',
             'constraint' => 11,
             'default' => 0,
             'null' => false
         ],
         'stockOwner' => [
            'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
         ],
         'stockItemQuantity' => [
             'type' => 'int',
             'constraint' => 11
         ],
         'stockItemPrice' => [
             'type' => 'varchar',
             'constraint' => 150,
             'default' => 0,
         ],
         'itemSellingPrice' => [
             'type' => 'varchar',
             'constraint' => 150,
             'default' => 0
         ],
         'itemSupplier' => [
             'type' => 'varchar',
             'constraint' => 150
         ],
         'stockCreated datetime default current_timestamp',
         'stockDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
         'stockDateDeleted datetime default null'
     ]);

     $this->forge->addKey('stockId', true);
    //  $this->forge->addForeignKey('stockItem', 'inventory', 'itemId', 'CASCADE', 'CASCADE');
    //  $this->forge->addForeignKey('stockOwner', 'users', 'id', 'CASCADE', 'CASCADE');
     $this->forge->createTable('stock');
    }

    public function down()
    {
      //drop stock table
      $this->forge->dropTable('stock');
    }
}
