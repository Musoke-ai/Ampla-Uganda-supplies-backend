<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoiceProductsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'INVItems_ID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'itemId' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'INV_ID' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'invoiceDateCreated datetime not null default current_timestamp',
            'invoiceDateUpdated datetime not null default current_timestamp',
            'invoiceDateDeleted datetime default null',
        ]);

        $this->forge->addKey('INVItems_ID', true);
        $this->forge->createTable('invoiceproducts');
    }

    public function down()
    {
        $this->forge->dropTable('invoiceproducts');
    }
}
