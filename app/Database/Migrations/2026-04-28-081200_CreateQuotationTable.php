<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuotationTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'QTNID' => [
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
            'quotationDateCreated datetime not null default current_timestamp',
            'quotationDateUpdated datetime not null default current_timestamp',
            'quotationDateDeleted' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => true,
            ],
        ]);

        $this->forge->addKey('QTNID', true);
        $this->forge->createTable('quotation');
    }

    public function down()
    {
        $this->forge->dropTable('quotation');
    }
}
