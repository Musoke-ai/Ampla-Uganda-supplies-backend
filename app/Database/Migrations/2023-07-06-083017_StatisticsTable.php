<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class StatisticsTable extends Migration
{
    public function up()
    {
        //create statistics table
        $this->forge->addField([
            'statId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true
            ],
            'statItemId'=>[
                'type' => 'INT',
                'constraint' => 11
            ],
            'busId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'statItemStock' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statItemStockWorth' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statItemSales' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statItemSalesWorth' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statItemIndebt' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statItemIndebtWorth' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0
            ],
            'statDateCreated datetime default current_timestamp',
            'statDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'statDateDeleted datetime default null'
        ]);

        $this->forge->addKey('statId', true);
        // $this->forge->addForeignKey('statItemId', 'inventory', 'itemId', 'CASCADE', 'CASCADE');
        // $this->forge->addForeignKey('busId', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('statistics');
    }

    public function down()
    {
        //delete statistics table
        $this->forge->dropTable('statistics');
    }
}
