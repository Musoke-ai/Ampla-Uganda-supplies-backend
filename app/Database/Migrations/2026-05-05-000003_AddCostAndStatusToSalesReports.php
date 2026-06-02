<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCostAndStatusToSalesReports extends Migration
{
    protected function addColumnIfMissing(string $table, string $column, array $attributes, ?string $after = null): void
    {
        if ($this->db->fieldExists($column, $table)) {
            return;
        }

        if ($after !== null && !$this->db->fieldExists($after, $table)) {
            unset($attributes['after']);
        }

        $this->forge->addColumn($table, [
            $column => $attributes,
        ]);
    }

    protected function addKeyIfColumnsExist(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if (!$this->db->fieldExists($column, $table)) {
                return;
            }
        }

        $this->forge->addKey($columns);
    }

    public function up()
    {
        $this->addColumnIfMissing('sales', 'unitCostAtSale', [
            'type' => 'DECIMAL',
            'constraint' => '15,2',
            'null' => true,
            'after' => 'salePrice',
        ], 'salePrice');

        $this->addColumnIfMissing('sales', 'lineCostAtSale', [
            'type' => 'DECIMAL',
            'constraint' => '15,2',
            'null' => true,
            'after' => 'unitCostAtSale',
        ], 'unitCostAtSale');

        $this->addColumnIfMissing('sales', 'custId', [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
            'after' => 'saleOwner',
        ], 'saleOwner');

        $this->addColumnIfMissing('sales', 'SR_ID', [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
            'after' => 'custId',
        ], 'custId');

        $this->addColumnIfMissing('sales', 'saleStatus', [
            'type' => 'VARCHAR',
            'constraint' => 30,
            'default' => 'completed',
            'after' => 'custId',
        ], 'custId');

        $this->addColumnIfMissing('sales', 'cancelledAt', [
            'type' => 'DATETIME',
            'null' => true,
            'after' => 'saleStatus',
        ], 'saleStatus');

        $this->addColumnIfMissing('sales', 'cancelledBy', [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'null' => true,
            'after' => 'cancelledAt',
        ], 'cancelledAt');

        $this->addColumnIfMissing('receipt', 'branchId', [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'null' => true,
            'after' => 'SR_ID',
        ], 'SR_ID');

        $this->addColumnIfMissing('receipt', 'createdBy', [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'null' => true,
            'after' => 'branchId',
        ], 'branchId');

        $this->addColumnIfMissing('receipt', 'receiptStatus', [
            'type' => 'VARCHAR',
            'constraint' => 30,
            'default' => 'completed',
            'after' => 'amountPaid',
        ], 'amountPaid');

        $this->addColumnIfMissing('receipt', 'cancelledAt', [
            'type' => 'DATETIME',
            'null' => true,
            'after' => 'receiptStatus',
        ], 'receiptStatus');

        $this->addColumnIfMissing('receipt', 'cancelledBy', [
            'type' => 'INT',
            'constraint' => 11,
            'unsigned' => true,
            'null' => true,
            'after' => 'cancelledAt',
        ], 'cancelledAt');

        $this->addKeyIfColumnsExist('sales', ['branchId', 'saleStatus', 'saleDateCreated']);
        $this->addKeyIfColumnsExist('sales', ['SR_ID', 'saleStatus']);
        $this->forge->processIndexes('sales');

        $this->addKeyIfColumnsExist('receipt', ['branchId', 'receiptStatus', 'srDateCreated']);
        $this->forge->processIndexes('receipt');
    }

    public function down()
    {
        foreach (['unitCostAtSale', 'lineCostAtSale', 'saleStatus', 'cancelledAt', 'cancelledBy', 'custId', 'SR_ID'] as $column) {
            if ($this->db->fieldExists($column, 'sales')) {
                $this->forge->dropColumn('sales', $column);
            }
        }

        foreach (['branchId', 'createdBy', 'receiptStatus', 'cancelledAt', 'cancelledBy'] as $column) {
            if ($this->db->fieldExists($column, 'receipt')) {
                $this->forge->dropColumn('receipt', $column);
            }
        }
    }
}
