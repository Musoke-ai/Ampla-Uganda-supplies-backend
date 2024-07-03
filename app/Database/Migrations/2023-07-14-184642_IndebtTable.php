<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class IndebtTable extends Migration
{
   public function up()
    {
        //add fields to sales table
        $this->forge->addField([
            'indebtId' => [
                'type' => 'int',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true,
                'null' => false
            ],
            'indebtItemId' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0,
                'null' => false
            ],
            'indebtOwner' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'indebtToday' => [
                'type' => 'int',
                'constraint' => 11
            ],
            'indebtTodayAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0,
            ],
            'indebtMonth' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'IndebtMonthAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'indebtYear' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'indebtYearAmount' => [
                'type' => 'int',
                'constraint' => 11,
                'default' => 0
            ],
            'indebtDateCreated datetime default current_timestamp',
            'indebtDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'indebtDateDeleted datetime default null'
        ]);

        $this->forge->addKey('indebtId', true);
        // $this->forge->addForeignKey('indebtItemId', 'inventory', 'itemId', 'CASCADE', 'CASCADE');
        // $this->forge->addForeignKey('indebtOwner', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('indebt');
    }

    public function down()
    {
        //drop sales table
        $this->forge->dropTable('indebt');
    }
}
