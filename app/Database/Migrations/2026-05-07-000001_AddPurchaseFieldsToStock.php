<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPurchaseFieldsToStock extends Migration
{
    public function up()
    {
        $fields = [];

        if (!$this->db->fieldExists('stockItemPrice', 'stock')) {
            $fields['stockItemPrice'] = [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => '0.00',
                'after' => 'stockItemQuantity',
            ];
        }

        if (!$this->db->fieldExists('itemSellingPrice', 'stock')) {
            $fields['itemSellingPrice'] = [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'default' => '0.00',
                'after' => 'stockItemPrice',
            ];
        }

        if (!$this->db->fieldExists('itemSupplier', 'stock')) {
            $fields['itemSupplier'] = [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
                'default' => 'none',
                'after' => 'itemSellingPrice',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('stock', $fields);
        }
    }

    public function down()
    {
        // This is a repair migration for databases that missed fields from the
        // original stock schema, so rollback must not remove original columns.
    }
}
