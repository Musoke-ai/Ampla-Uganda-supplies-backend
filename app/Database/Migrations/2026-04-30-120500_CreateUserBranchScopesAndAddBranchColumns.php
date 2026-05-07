<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserBranchScopesAndAddBranchColumns extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('user_branch_scopes')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'assigned_branch_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'active_branch_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'can_switch_branches' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'created_at datetime null',
                'updated_at datetime null',
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('user_id');
            $this->forge->createTable('user_branch_scopes');
        }

        $tables = [
            'raw_materials' => 'materialId',
            'daily_rawmaterials_register' => 'id',
            'sales' => 'saleId',
            'indebt' => 'indebtId',
            'history' => 'historyId',
            'statistics' => 'statId',
        ];

        foreach ($tables as $table => $afterField) {
            if (!$this->db->fieldExists('branchId', $table)) {
                $this->forge->addColumn($table, [
                    'branchId' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => true,
                        'null' => true,
                        'after' => $afterField,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        foreach (['raw_materials', 'daily_rawmaterials_register', 'sales', 'indebt', 'history', 'statistics'] as $table) {
            if ($this->db->fieldExists('branchId', $table)) {
                $this->forge->dropColumn($table, 'branchId');
            }
        }

        $this->forge->dropTable('user_branch_scopes', true);
    }
}
