<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfessionalRawMaterialFields extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('raw_materials')) {
            return;
        }

        $fields = [
            'materialCode' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'after'      => 'name',
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'materialCode',
            ],
            'unitOfMeasure' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'default'    => 'pcs',
                'after'      => 'size',
            ],
            'reorderLevel' => [
                'type'       => 'DECIMAL',
                'constraint' => '12,3',
                'default'    => 0,
                'after'      => 'unitPrice',
            ],
            'supplierContact' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'supplier',
            ],
            'storageLocation' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
                'after'      => 'supplierContact',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'active',
                'after'      => 'storageLocation',
            ],
        ];

        foreach ($fields as $field => $definition) {
            if (!$this->db->fieldExists($field, 'raw_materials')) {
                $this->forge->addColumn('raw_materials', [$field => $definition]);
            }
        }

        if ($this->db->fieldExists('Quantity', 'raw_materials')) {
            $this->forge->modifyColumn('raw_materials', [
                'Quantity' => [
                    'name'       => 'Quantity',
                    'type'       => 'DECIMAL',
                    'constraint' => '12,3',
                    'default'    => 0,
                ],
            ]);
        }

        if ($this->db->fieldExists('unitPrice', 'raw_materials')) {
            $this->forge->modifyColumn('raw_materials', [
                'unitPrice' => [
                    'name'       => 'unitPrice',
                    'type'       => 'DECIMAL',
                    'constraint' => '12,2',
                    'default'    => 0,
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('raw_materials')) {
            return;
        }

        foreach (['materialCode', 'category', 'unitOfMeasure', 'reorderLevel', 'supplierContact', 'storageLocation', 'status'] as $field) {
            if ($this->db->fieldExists($field, 'raw_materials')) {
                $this->forge->dropColumn('raw_materials', $field);
            }
        }

        if ($this->db->fieldExists('Quantity', 'raw_materials')) {
            $this->forge->modifyColumn('raw_materials', [
                'Quantity' => [
                    'name'       => 'Quantity',
                    'type'       => 'INT',
                    'constraint' => 100,
                ],
            ]);
        }

        if ($this->db->fieldExists('unitPrice', 'raw_materials')) {
            $this->forge->modifyColumn('raw_materials', [
                'unitPrice' => [
                    'name' => 'unitPrice',
                    'type' => 'FLOAT',
                ],
            ]);
        }
    }
}
