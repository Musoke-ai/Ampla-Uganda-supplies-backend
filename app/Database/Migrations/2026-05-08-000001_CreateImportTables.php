<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateImportTables extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('import_batches')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'branchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'userId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'importType' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                ],
                'fileName' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'uploaded',
                ],
                'totalRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'validRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'warningRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'errorRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'skippedRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'importedRows' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'default' => 0,
                ],
                'headers' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'mapping' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'options' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'summary' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'confirmedAt' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'createdAt datetime default current_timestamp',
                'updatedAt datetime default current_timestamp on UPDATE current_timestamp',
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['importType', 'status']);
            $this->forge->addKey('branchId');
            $this->forge->createTable('import_batches');
        }

        if (!$this->db->tableExists('import_rows')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'importBatchId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                ],
                'rowNumber' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'rawData' => [
                    'type' => 'TEXT',
                ],
                'normalizedData' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'pending',
                ],
                'errors' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'warnings' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'createdEntityType' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                    'null' => true,
                ],
                'createdEntityId' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                    'null' => true,
                ],
                'createdAt datetime default current_timestamp',
                'updatedAt datetime default current_timestamp on UPDATE current_timestamp',
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('importBatchId');
            $this->forge->addKey(['status', 'rowNumber']);
            $this->forge->addForeignKey('importBatchId', 'import_batches', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('import_rows');
        }

        if (!$this->db->tableExists('import_mappings')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'userId' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'null' => true,
                ],
                'importType' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'default' => 'Default Mapping',
                ],
                'headers' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'mapping' => [
                    'type' => 'TEXT',
                ],
                'options' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'createdAt datetime default current_timestamp',
                'updatedAt datetime default current_timestamp on UPDATE current_timestamp',
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['userId', 'importType']);
            $this->forge->createTable('import_mappings');
        }
    }

    public function down()
    {
        $this->forge->dropTable('import_mappings', true);
        $this->forge->dropTable('import_rows', true);
        $this->forge->dropTable('import_batches', true);
    }
}
