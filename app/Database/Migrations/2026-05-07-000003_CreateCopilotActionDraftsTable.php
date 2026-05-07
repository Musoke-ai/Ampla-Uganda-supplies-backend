<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCopilotActionDraftsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('copilot_action_drafts')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'auto_increment' => true,
            ],
            'draftKey' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
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
            'actionType' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'draft',
            ],
            'risk' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'draft',
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 180,
            ],
            'summary' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sourceTool' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'payload' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'decisionBy' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'decisionNote' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'decisionAt datetime null',
            'executionStatus' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
            ],
            'executionResult' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'executedBy' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
            ],
            'executedAt datetime null',
            'expiresAt datetime null',
            'createdAt datetime not null default current_timestamp',
            'updatedAt datetime not null default current_timestamp on update current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('draftKey');
        $this->forge->addKey(['userId', 'status']);
        $this->forge->addKey(['branchId', 'createdAt']);
        $this->forge->createTable('copilot_action_drafts');
    }

    public function down()
    {
        $this->forge->dropTable('copilot_action_drafts', true);
    }
}
