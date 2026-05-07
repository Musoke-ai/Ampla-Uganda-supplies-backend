<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBranchesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'branchId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branchName' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'branchCode' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'branchLocation' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'branchContact' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'branchEmail' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'branchManager' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
            ],
            'branchStatus' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'branchDescription' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'branchDateCreated datetime not null default current_timestamp',
            'branchDateUpdated datetime not null default current_timestamp',
            'branchDateDeleted datetime null',
        ]);

        $this->forge->addKey('branchId', true);
        $this->forge->addUniqueKey('branchCode');
        $this->forge->createTable('branches');
    }

    public function down()
    {
        $this->forge->dropTable('branches');
    }
}
