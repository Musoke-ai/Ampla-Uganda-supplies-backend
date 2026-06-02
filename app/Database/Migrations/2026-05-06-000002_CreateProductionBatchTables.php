<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductionBatchTables extends Migration
{
    public function up()
    {
        $this->addDailyRegisterLinks();

        if (!$this->db->tableExists('production_batches')) {
            $this->forge->addField([
                'batchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'batchNo' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                ],
                'orderId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'productId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'supervisorId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'quantityPlanned' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'quantityProduced' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'wastageQuantity' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'planned',
                ],
                'startDate' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'endDate' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'qualityStatus' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'pending',
                ],
                'qualityCheckedBy' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'qualityCheckedAt' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'qualityNotes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'createdBy' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'createdAt datetime not null default current_timestamp',
                'updatedAt datetime not null default current_timestamp on update current_timestamp',
            ]);

            $this->forge->addKey('batchId', true);
            $this->forge->addUniqueKey('batchNo');
            $this->forge->createTable('production_batches');
        }

        if (!$this->db->tableExists('production_batch_materials')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'batchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'materialId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'quantity' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'unitCost' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,2',
                    'default' => 0,
                ],
                'totalCost' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,2',
                    'default' => 0,
                ],
                'dailyRawMaterialRegisterId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'notes' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                ],
                'createdAt datetime not null default current_timestamp',
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('batchId');
            $this->forge->createTable('production_batch_materials');
        }

        if (!$this->db->tableExists('production_batch_outputs')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'batchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'productId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'quantity' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'wastageQuantity' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,3',
                    'default' => 0,
                ],
                'unitCost' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,2',
                    'null' => true,
                ],
                'dailyProductRegisterId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'notes' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                ],
                'createdAt datetime not null default current_timestamp',
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('batchId');
            $this->forge->createTable('production_batch_outputs');
        }

        if (!$this->db->tableExists('production_batch_labor')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'batchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'employeeId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'role' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'null' => true,
                ],
                'hoursWorked' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,2',
                    'default' => 0,
                ],
                'laborCost' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,2',
                    'default' => 0,
                ],
                'notes' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                ],
                'createdAt datetime not null default current_timestamp',
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('batchId');
            $this->forge->createTable('production_batch_labor');
        }

        if (!$this->db->tableExists('production_batch_expenses')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'batchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
                'category' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                ],
                'description' => [
                    'type' => 'VARCHAR',
                    'constraint' => 500,
                    'null' => true,
                ],
                'amount' => [
                    'type' => 'DECIMAL',
                    'constraint' => '14,2',
                    'default' => 0,
                ],
                'createdAt datetime not null default current_timestamp',
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('batchId');
            $this->forge->createTable('production_batch_expenses');
        }
    }

    public function down()
    {
        foreach ([
            'production_batch_expenses',
            'production_batch_labor',
            'production_batch_outputs',
            'production_batch_materials',
            'production_batches',
        ] as $table) {
            $this->forge->dropTable($table, true);
        }

        foreach (['daily_products_register', 'daily_rawmaterials_register'] as $table) {
            foreach (['batchId', 'orderId'] as $field) {
                if ($this->db->tableExists($table) && $this->db->fieldExists($field, $table)) {
                    $this->forge->dropColumn($table, $field);
                }
            }
        }
    }

    private function addDailyRegisterLinks(): void
    {
        foreach (['daily_products_register', 'daily_rawmaterials_register'] as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            if (!$this->columnExists($table, 'branchId')) {
                $this->forge->addColumn($table, [
                    'branchId' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => true,
                        'null' => true,
                        'after' => 'id',
                    ],
                ]);
            }

            if (!$this->columnExists($table, 'batchId')) {
                $this->forge->addColumn($table, [
                    'batchId' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'unsigned' => true,
                        'null' => true,
                        'after' => 'branchId',
                    ],
                ]);
            }

            if (!$this->columnExists($table, 'orderId')) {
                $this->forge->addColumn($table, [
                    'orderId' => [
                        'type' => 'INT',
                        'constraint' => 11,
                        'null' => true,
                        'after' => 'batchId',
                    ],
                ]);
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $safeTable = '`' . str_replace('`', '``', $table) . '`';

        return $this->db
            ->query('SHOW COLUMNS FROM ' . $safeTable . ' LIKE ' . $this->db->escape($column))
            ->getNumRows() > 0;
    }
}
