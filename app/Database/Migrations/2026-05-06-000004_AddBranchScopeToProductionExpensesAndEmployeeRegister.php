<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBranchScopeToProductionExpensesAndEmployeeRegister extends Migration
{
    public function up()
    {
        $tables = [
            'expenses' => 'id',
            'daily_employees_register' => 'ID',
        ];

        foreach ($tables as $table => $afterField) {
            if (!$this->db->fieldExists('branchId', $table)) {
                $this->forge->addColumn($table, [
                    'branchId' => [
                        'type'       => 'INT',
                        'constraint' => 11,
                        'unsigned'   => true,
                        'null'       => true,
                        'after'      => $afterField,
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        foreach (['expenses', 'daily_employees_register'] as $table) {
            if ($this->db->fieldExists('branchId', $table)) {
                $this->forge->dropColumn($table, 'branchId');
            }
        }
    }
}
