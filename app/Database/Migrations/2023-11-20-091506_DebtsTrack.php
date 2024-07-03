<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DebtsTrack extends Migration
{
    public function up()
    {
               //add fields to debtsTrack table
               $this->forge->addField([
                'trackId' => [
                    'type' => 'int',
                    'constraint' => 11,
                    'unsigned' => false,
                    'auto_increment' => true,
                    'null' => false
                ],
                'debtId' => [
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
                'amountPaid' => [
                    'type' => 'varchar',
                    'constraint' => 11
                ],

                'datepaid datetime default current_timestamp',
                'debtTrackDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
                'debtTrackDateDeleted datetime default null'
            ]);
    
            $this->forge->addKey('trackId', true);
            // $this->forge->addForeignKey('debtId', 'indebt', 'indebtId', 'CASCADE', 'CASCADE');
            // $this->forge->addForeignKey('indebtOwner', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('debtsTrack');
    }

    public function down()
    {
            //drop debtsTrack table
            $this->forge->dropTable('debtsTrack');
    }
}
