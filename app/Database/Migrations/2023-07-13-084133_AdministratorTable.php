<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AdministratorTable extends Migration
{
    public function up()
    {
        //create administrators table
        $this->forge->addField([
            'adId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true
            ],
            'adFirstName' => [
                'type' => 'varchar',
                'constraint' => 20,
                'default' => 'John/Jane'
            ],
            'adMiddleName' => [
                'type' => 'varchar',
                'constraint' => 20
            ],
            'adLastName' => [
                'type' => 'varchar',
                'constraint' => 20,
                'default' => 'Doe'
            ],
            'adEmail' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'johnjanedoe@hsms.com'
            ],
            'adPhoto' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'hsms_admin_photo.png'
            ],
            'adPassword' => [
                'type' => 'TEXT'
            ],
            'adDateCreated datetime default current_timestamp',
            'adDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'adDateDeleted datetime default null'
        ]);

        $this->forge->addKey('adId', true);
        $this->forge->createTable('administrators');
    }

    public function down()
    {
        //delete administrators table
        $this->forge->addField('administrators');
    }
}
