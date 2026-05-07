<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfessionalProductFields extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inventory')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('itemSku', 'inventory')) {
            $fields['itemSku'] = [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
                'after' => 'itemModel',
            ];
        }

        if (!$this->db->fieldExists('itemBarcode', 'inventory')) {
            $fields['itemBarcode'] = [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'itemSku',
            ];
        }

        if (!$this->db->fieldExists('itemBrand', 'inventory')) {
            $fields['itemBrand'] = [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'itemBarcode',
            ];
        }

        if (!$this->db->fieldExists('itemProductType', 'inventory')) {
            $fields['itemProductType'] = [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'purchased',
                'after' => 'itemBrand',
            ];
        }

        if (!$this->db->fieldExists('itemUnit', 'inventory')) {
            $fields['itemUnit'] = [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'pcs',
                'after' => 'itemProductType',
            ];
        }

        if (!$this->db->fieldExists('itemSupplier', 'inventory')) {
            $fields['itemSupplier'] = [
                'type' => 'VARCHAR',
                'constraint' => 150,
                'null' => true,
                'after' => 'itemUnit',
            ];
        }

        if (!$this->db->fieldExists('itemReorderLevel', 'inventory')) {
            $fields['itemReorderLevel'] = [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
                'after' => 'itemSupplier',
            ];
        }

        if (!$this->db->fieldExists('itemWholesalePrice', 'inventory')) {
            $fields['itemWholesalePrice'] = [
                'type' => 'DECIMAL',
                'constraint' => '14,2',
                'null' => true,
                'after' => 'itemLeastPrice',
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('inventory', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('inventory')) {
            return;
        }

        foreach ([
            'itemWholesalePrice',
            'itemReorderLevel',
            'itemSupplier',
            'itemUnit',
            'itemProductType',
            'itemBrand',
            'itemBarcode',
            'itemSku',
        ] as $field) {
            if ($this->db->fieldExists($field, 'inventory')) {
                $this->forge->dropColumn('inventory', $field);
            }
        }
    }
}
