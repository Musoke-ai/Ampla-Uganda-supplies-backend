<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPayrollFieldsToProductionBatchLabor extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('production_batch_labor')) {
            return;
        }

        $fields = [
            'amountPaid' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
                'after' => 'laborCost',
            ],
            'paymentStatus' => [
                'type' => 'VARCHAR',
                'constraint' => 40,
                'default' => 'unpaid',
                'after' => 'amountPaid',
            ],
            'paymentDate' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'paymentStatus',
            ],
        ];

        foreach ($fields as $field => $definition) {
            if (!$this->db->fieldExists($field, 'production_batch_labor')) {
                $this->forge->addColumn('production_batch_labor', [$field => $definition]);
            }
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('production_batch_labor')) {
            return;
        }

        foreach (['paymentDate', 'paymentStatus', 'amountPaid'] as $field) {
            if ($this->db->fieldExists($field, 'production_batch_labor')) {
                $this->forge->dropColumn('production_batch_labor', $field);
            }
        }
    }
}
