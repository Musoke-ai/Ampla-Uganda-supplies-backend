<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoiceTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'INV_ID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'timeStamp' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'fulfilled' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'invoiceDateCreated datetime not null default current_timestamp',
            'invoiceDateUpdated datetime not null default current_timestamp',
            'invoiceDateDeleted' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
        ]);

        $this->forge->addKey('INV_ID', true);
        $this->forge->createTable('invoice');
    }

    public function down()
    {
        $this->forge->dropTable('invoice');
    }
}
