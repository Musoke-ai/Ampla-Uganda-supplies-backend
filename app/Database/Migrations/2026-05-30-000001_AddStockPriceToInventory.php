<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStockPriceToInventory extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inventory') || $this->columnExists('inventory', 'itemStockPrice')) {
            return;
        }

        $this->forge->addColumn('inventory', [
            'itemStockPrice' => [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => 0,
                'after' => 'itemSize',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('inventory') && $this->columnExists('inventory', 'itemStockPrice')) {
            $this->forge->dropColumn('inventory', 'itemStockPrice');
        }
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
