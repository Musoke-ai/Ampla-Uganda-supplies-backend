<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCostAndStatusToSalesReports extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('unitCostAtSale', 'sales')) {
            $this->forge->addColumn('sales', [
                'unitCostAtSale' => [
                    'type' => 'DECIMAL',
                    'constraint' => '15,2',
                    'null' => true,
                    'after' => 'salePrice',
                ],
            ]);
        }

        if (!$this->db->fieldExists('lineCostAtSale', 'sales')) {
            $this->forge->addColumn('sales', [
                'lineCostAtSale' => [
                    'type' => 'DECIMAL',
                    'constraint' => '15,2',
                    'null' => true,
                    'after' => 'unitCostAtSale',
                ],
            ]);
        }

        if (!$this->db->fieldExists('saleStatus', 'sales')) {
            $this->forge->addColumn('sales', [
                'saleStatus' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'completed',
                    'after' => 'custId',
                ],
            ]);
        }

        if (!$this->db->fieldExists('cancelledAt', 'sales')) {
            $this->forge->addColumn('sales', [
                'cancelledAt' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'saleStatus',
                ],
            ]);
        }

        if (!$this->db->fieldExists('cancelledBy', 'sales')) {
            $this->forge->addColumn('sales', [
                'cancelledBy' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'cancelledAt',
                ],
            ]);
        }

        if (!$this->db->fieldExists('branchId', 'receipt')) {
            $this->forge->addColumn('receipt', [
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'SR_ID',
                ],
            ]);
        }

        if (!$this->db->fieldExists('createdBy', 'receipt')) {
            $this->forge->addColumn('receipt', [
                'createdBy' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'branchId',
                ],
            ]);
        }

        if (!$this->db->fieldExists('receiptStatus', 'receipt')) {
            $this->forge->addColumn('receipt', [
                'receiptStatus' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'completed',
                    'after' => 'amountPaid',
                ],
            ]);
        }

        if (!$this->db->fieldExists('cancelledAt', 'receipt')) {
            $this->forge->addColumn('receipt', [
                'cancelledAt' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'receiptStatus',
                ],
            ]);
        }

        if (!$this->db->fieldExists('cancelledBy', 'receipt')) {
            $this->forge->addColumn('receipt', [
                'cancelledBy' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'cancelledAt',
                ],
            ]);
        }

        $this->forge->addKey(['branchId', 'saleStatus', 'saleDateCreated']);
        $this->forge->addKey(['SR_ID', 'saleStatus']);
        $this->forge->processIndexes('sales');

        $this->forge->addKey(['branchId', 'receiptStatus', 'srDateCreated']);
        $this->forge->processIndexes('receipt');
    }

    public function down()
    {
        foreach (['unitCostAtSale', 'lineCostAtSale', 'saleStatus', 'cancelledAt', 'cancelledBy'] as $column) {
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
