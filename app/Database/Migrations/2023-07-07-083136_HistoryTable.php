<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HistoryTable extends Migration
{
    public function up()
    {
        //create the history table

        $this->forge->addField([
            'historyId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true
            ],
            'historyItemId' => [
                'type' => 'INT',
                'constraint' => 11,
            ],
            'busId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'historyAction' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'Click'
            ],
            'historyDetails' => [
                'type' => 'varchar',
                'constraint' => 250,
                'default' => 'none'
            ],
            'historyDateCreated datetime default current_timestamp',
            'historyDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'historyDateDeleted datetime default null'
        ]);

        $this->forge->addKey('historyId', true);
        // $this->forge->addForeignKey('historyItemId', 'inventory', 'itemId', 'CASCADE', 'CASCADE');
        // $this->forge->addForeignKey('busId', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('history');
    }

    public function down()
    {
        // delete history table
        $this->forge->dropTable('history');
    }
}
