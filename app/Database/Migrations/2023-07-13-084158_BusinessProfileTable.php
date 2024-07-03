<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BusinessProfileTable extends Migration
{
   public function up()
    {
        //create business profiles table
        $this->forge->addField([
            'profileId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true
            ],
            'busId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'null' => false
            ],
            'busLocation' => [
                'type' => 'varchar',
                'constraint' => 50,
            ],
            'busBuilding' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'various'
            ],
            'busName' => [
                'type' => 'varchar',
                'constraint' => 50,
            ],
            'busContactOne' => [
                'type' => 'varchar',
                'constraint' => 14,
            ],
            'busContactTwo' => [
                'type' => 'varchar',
                'constraint' => 14,
            ],
            'busEmail' => [
                'type' => 'varchar',
                'constraint' => 50,
            ],
            'busNumberShop' => [
                'type' => 'varchar',
                'constraint' => 50,
            ],
            'busOwner' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => false,
                'attributes' => 'unsigned',
                // 'default' => 'none'
            ],
            'busLogo' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'hsms_business_logo.png'
            ],
            'busPassword' => [
                'type' => 'TEXT'
            ],
            'busDateCreated datetime default current_timestamp',
            'busDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'busDateDeleted datetime default null'
        ]);

        $this->forge->addKey('profileId', true);
        // $this->forge->addForeignKey('busOwner', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('businessprofile');
    }

    public function down()
    {
        //delete table businessprofile
        $this->forge->dropTable('businessprofile');
    }
}
