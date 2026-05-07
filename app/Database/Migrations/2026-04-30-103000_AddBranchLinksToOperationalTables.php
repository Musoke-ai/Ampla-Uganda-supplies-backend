<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBranchLinksToOperationalTables extends Migration
{
    public function up()
    {
        $tables = [
            'customers' => 'custId',
            'employees' => 'empID',
            'orders' => 'orderId',
            'inventory' => 'itemId',
            'stock' => 'stockId',
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
        foreach (['customers', 'employees', 'orders', 'inventory', 'stock'] as $table) {
            if ($this->db->fieldExists('branchId', $table)) {
                $this->forge->dropColumn($table, 'branchId');
            }
        }
    }
}
