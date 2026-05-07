<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTransactionIdsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'TID' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'auto_increment' => true,
            ],
            'TOwner' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'TID_slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
            ],
            'T_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'tDateCreated datetime not null default current_timestamp',
            'tDateUpdated datetime not null default current_timestamp',
            'tDateDeleted datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('TID', true);
        $this->forge->createTable('transaction_ids');
    }

    public function down()
    {
        $this->forge->dropTable('transaction_ids');
    }
}
