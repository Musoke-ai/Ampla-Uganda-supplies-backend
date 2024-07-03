<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class inventoryTable extends Migration
{
    public function up()
    {
        //creating the stock table
        $this->forge->addField([
            'itemId' => [
                'type' => 'INT',
                'unsigned' => false,
                'constraint' => 11,
                'auto_increment' => true
            ],
            'itemName' => [
                'type' => 'varchar',
                'constraint' => 255,
                'default' => 'Make sure you enter an item name here'
            ],
            'itemCategoryId' => [
                'type' => 'INT',
                'constraint' => 11
            ],
            'itemModel' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'Generic',
            ],
            'itemQuality' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'Original'
            ],
            'itemQuantity' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'itemCondition' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'New'
            ],
            'itemSize' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'Variable'
            ],
            'itemLeastPrice' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'itemNotes' => [
                'type' => 'varchar',
                'constraint' => 1500,
                'default' => 'none'
            ],
            'itemOwner' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'itemDateCreated datetime default current_timestamp',
            'itemDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'itemDateDeleted datetime default null'
        ]);

        $this->forge->addKey('itemId', true);
        // $this->forge->addForeignKey('itemOwner', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('inventory');
    }

    public function down()
    {
        //delete audio table
        $this->forge->dropTable('inventory');
    }
}
