<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCashDrawerTables extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('cash_drawers')) {
            $this->forge->addField([
                'drawerId' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'branchId' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'openedBy' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'closedBy' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'open',
                ],
                'openingFloat' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'cashSalesTotal' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'cashInTotal' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'cashOutTotal' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'expectedCash' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'countedCash' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'null'       => true,
                ],
                'variance' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'null'       => true,
                ],
                'openingNote' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'closingNote' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'openedAt datetime not null default current_timestamp',
                'closedAt datetime null',
            ]);
            $this->forge->addKey('drawerId', true);
            $this->forge->addKey(['branchId', 'status']);
            $this->forge->addKey(['openedBy', 'openedAt']);
            $this->forge->createTable('cash_drawers');
        }

        if (!$this->db->tableExists('cash_drawer_movements')) {
            $this->forge->addField([
                'movementId' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'drawerId' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'branchId' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'userId' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'receiptId' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'movementType' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                ],
                'amount' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '15,2',
                    'default'    => 0,
                ],
                'reason' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'movementDateCreated datetime not null default current_timestamp',
            ]);
            $this->forge->addKey('movementId', true);
            $this->forge->addKey(['drawerId', 'movementDateCreated']);
            $this->forge->addKey(['branchId', 'movementType']);
            $this->forge->createTable('cash_drawer_movements');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('cash_drawer_movements')) {
            $this->forge->dropTable('cash_drawer_movements');
        }

        if ($this->db->tableExists('cash_drawers')) {
            $this->forge->dropTable('cash_drawers');
        }
    }
}
