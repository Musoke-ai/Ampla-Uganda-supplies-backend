<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CategoriesTable extends Migration
{
    public function up()
    {
        //create categories table

        $this->forge->addField([
            'categoryId' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => false,
                'auto_increment' => true
            ],
            'categoryName' => [
                'type' => 'varchar',
                'constraint' => 50,
                'default' => 'Others'
            ],
            'categoryDateCreated datetime default current_timestamp',
            'categoryDateUpdated datetime default current_timestamp on UPDATE current_timestamp',
            'categoryDateDeleted datetime default null'
        ]);

        $this->forge->addKey('categoryId', 'true');
        $this->forge->createTable('categories');
    }

    public function down()
    {
        //delete categories table
        $this->forge->dropTable('categories');
    }
}
