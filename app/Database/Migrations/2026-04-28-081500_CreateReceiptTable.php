<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReceiptTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'SR_ID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'timeStamp' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'discount' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'dueAmount' => [
                'type' => 'DOUBLE',
            ],
            'moreInfo' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'paymentMethod' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'amountPaid' => [
                'type' => 'DOUBLE',
            ],
            'srDateCreated datetime not null default current_timestamp',
            'srDateUpdated datetime not null default current_timestamp',
            'srDateDeleted datetime default null',
        ]);

        $this->forge->addKey('SR_ID', true);
        $this->forge->createTable('receipt');
    }

    public function down()
    {
        $this->forge->dropTable('receipt');
    }
}
