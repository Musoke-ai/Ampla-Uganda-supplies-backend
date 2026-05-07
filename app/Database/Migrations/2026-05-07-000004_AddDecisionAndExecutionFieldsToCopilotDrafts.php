<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDecisionAndExecutionFieldsToCopilotDrafts extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('copilot_action_drafts')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('decisionBy', 'copilot_action_drafts')) {
            $fields['decisionBy'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'payload',
            ];
        }

        if (!$this->db->fieldExists('decisionNote', 'copilot_action_drafts')) {
            $fields['decisionNote'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'decisionBy',
            ];
        }

        if (!$this->db->fieldExists('decisionAt', 'copilot_action_drafts')) {
            $fields['decisionAt'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'decisionNote',
            ];
        }

        if (!$this->db->fieldExists('executionStatus', 'copilot_action_drafts')) {
            $fields['executionStatus'] = [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'null' => true,
                'after' => 'decisionAt',
            ];
        }

        if (!$this->db->fieldExists('executionResult', 'copilot_action_drafts')) {
            $fields['executionResult'] = [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'executionStatus',
            ];
        }

        if (!$this->db->fieldExists('executedBy', 'copilot_action_drafts')) {
            $fields['executedBy'] = [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'after' => 'executionResult',
            ];
        }

        if (!$this->db->fieldExists('executedAt', 'copilot_action_drafts')) {
            $fields['executedAt'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'executedBy',
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('copilot_action_drafts', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('copilot_action_drafts')) {
            return;
        }

        foreach ([
            'executedAt',
            'executedBy',
            'executionResult',
            'executionStatus',
            'decisionAt',
            'decisionNote',
            'decisionBy',
        ] as $field) {
            if ($this->db->fieldExists($field, 'copilot_action_drafts')) {
                $this->forge->dropColumn('copilot_action_drafts', $field);
            }
        }
    }
}
