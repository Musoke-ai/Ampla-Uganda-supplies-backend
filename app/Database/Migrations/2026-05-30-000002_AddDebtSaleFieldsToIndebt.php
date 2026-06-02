<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDebtSaleFieldsToIndebt extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('indebt')) {
            return;
        }

        $this->addColumnIfMissing('custId', [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
            'after' => 'indebtOwner',
        ], 'indebtOwner');

        $this->addColumnIfMissing('quantityDebted', [
            'type' => 'DECIMAL',
            'constraint' => '15,3',
            'default' => 0,
            'after' => 'custId',
        ], 'custId');

        $this->addColumnIfMissing('atPrice', [
            'type' => 'DECIMAL',
            'constraint' => '15,2',
            'default' => 0,
            'after' => 'quantityDebted',
        ], 'quantityDebted');

        $this->addColumnIfMissing('initialDeposit', [
            'type' => 'DECIMAL',
            'constraint' => '15,2',
            'default' => 0,
            'after' => 'atPrice',
        ], 'atPrice');

        $this->addColumnIfMissing('totalAmount', [
            'type' => 'DECIMAL',
            'constraint' => '15,2',
            'default' => 0,
            'after' => 'initialDeposit',
        ], 'initialDeposit');

        $this->addColumnIfMissing('endDate', [
            'type' => 'DATE',
            'null' => true,
            'after' => 'totalAmount',
        ], 'totalAmount');

        $this->addColumnIfMissing('SR_ID', [
            'type' => 'INT',
            'constraint' => 11,
            'null' => true,
            'after' => 'endDate',
        ], 'endDate');

        $this->backfillLegacyDebtAmounts();
    }

    public function down()
    {
        if (!$this->db->tableExists('indebt')) {
            return;
        }

        foreach (['SR_ID', 'endDate', 'totalAmount', 'initialDeposit', 'atPrice', 'quantityDebted', 'custId'] as $column) {
            if ($this->columnExists('indebt', $column)) {
                $this->forge->dropColumn('indebt', $column);
            }
        }
    }

    private function addColumnIfMissing(string $column, array $attributes, ?string $after = null): void
    {
        if ($this->columnExists('indebt', $column)) {
            return;
        }

        if ($after !== null && !$this->columnExists('indebt', $after)) {
            unset($attributes['after']);
        }

        $this->forge->addColumn('indebt', [
            $column => $attributes,
        ]);
    }

    private function backfillLegacyDebtAmounts(): void
    {
        if (!$this->columnExists('indebt', 'indebtToday') || !$this->columnExists('indebt', 'indebtTodayAmount')) {
            return;
        }

        $this->db->query(
            'UPDATE ' . $this->db->protectIdentifiers('indebt') . '
             SET quantityDebted = COALESCE(NULLIF(quantityDebted, 0), indebtToday),
                 totalAmount = COALESCE(NULLIF(totalAmount, 0), indebtTodayAmount),
                 atPrice = CASE
                     WHEN COALESCE(atPrice, 0) = 0 AND COALESCE(indebtToday, 0) > 0
                     THEN indebtTodayAmount / indebtToday
                     ELSE atPrice
                 END
             WHERE COALESCE(totalAmount, 0) = 0'
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->db->query(
            'SHOW COLUMNS FROM ' . $this->db->protectIdentifiers($table) . ' LIKE ?',
            [$column]
        )->getRowArray();

        return $row !== null;
    }
}
