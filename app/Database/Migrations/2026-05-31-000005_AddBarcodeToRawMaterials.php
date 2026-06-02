<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBarcodeToRawMaterials extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('raw_materials')) {
            return;
        }

        if (!$this->db->fieldExists('rawMaterialBarcode', 'raw_materials')) {
            $this->forge->addColumn('raw_materials', [
                'rawMaterialBarcode' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'null' => true,
                    'after' => 'materialCode',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('raw_materials') && $this->db->fieldExists('rawMaterialBarcode', 'raw_materials')) {
            $this->forge->dropColumn('raw_materials', 'rawMaterialBarcode');
        }
    }
}
