<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddImagesToProductsAndRawMaterials extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inventory') && !$this->db->fieldExists('itemImage', 'inventory')) {
            $this->forge->addColumn('inventory', [
                'itemImage' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'itemBarcode',
                ],
            ]);
        }

        if ($this->db->tableExists('raw_materials') && !$this->db->fieldExists('rawMaterialImage', 'raw_materials')) {
            $this->forge->addColumn('raw_materials', [
                'rawMaterialImage' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'rawMaterialBarcode',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('inventory') && $this->db->fieldExists('itemImage', 'inventory')) {
            $this->forge->dropColumn('inventory', 'itemImage');
        }

        if ($this->db->tableExists('raw_materials') && $this->db->fieldExists('rawMaterialImage', 'raw_materials')) {
            $this->forge->dropColumn('raw_materials', 'rawMaterialImage');
        }
    }
}
