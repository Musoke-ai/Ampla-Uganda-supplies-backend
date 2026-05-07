<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCopilotSessionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('copilot_sessions')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'auto_increment' => true,
            ],
            'sessionKey' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'userId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'branchId' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'lastIntent' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'lastTool' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'lastProduct' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'lastCustomer' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'lastPeriod' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'lastSearch' => [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
            ],
            'contextData' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'messageCount' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
            ],
            'createdAt datetime not null default current_timestamp',
            'updatedAt datetime not null default current_timestamp on update current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['userId', 'sessionKey']);
        $this->forge->addKey(['branchId', 'updatedAt']);
        $this->forge->createTable('copilot_sessions');
    }

    public function down()
    {
        $this->forge->dropTable('copilot_sessions', true);
    }
}
