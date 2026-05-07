<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRawMaterialCategoriesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('raw_material_categories')) {
            return;
        }

        $this->forge->addField([
            'categoryId' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branchId' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'categoryName' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'description' => [
                'type'       => 'VARCHAR',
                'constraint' => 250,
                'null'       => true,
            ],
            'isActive' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'rawMaterialCategoryDateCreated datetime not null default current_timestamp',
            'rawMaterialCategoryDateUpdated datetime not null default current_timestamp',
        ]);

        $this->forge->addKey('categoryId', true);
        $this->forge->addKey('branchId');
        $this->forge->addKey('categoryName');
        $this->forge->createTable('raw_material_categories');
    }

    public function down()
    {
        $this->forge->dropTable('raw_material_categories', true);
    }
}
