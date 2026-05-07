<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'empID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'empName' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'empEmail' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'empLocation' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'empContact' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'empRole' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'empSalary' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'empStatus' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'startDate' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'endDate' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'empDateCreated datetime not null default current_timestamp',
            'empDateUpdated datetime not null default current_timestamp',
            'empDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('empID', true);
        $this->forge->createTable('employees');
    }

    public function down()
    {
        $this->forge->dropTable('employees');
    }
}
