<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
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
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'entityType' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'entityId' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'beforeData' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'afterData' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'metadata' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ipAddress' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'userAgent' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'auditDateCreated datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['branchId', 'auditDateCreated']);
        $this->forge->addKey(['entityType', 'entityId']);
        $this->forge->createTable('audit_logs');
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs');
    }
}
