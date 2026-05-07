<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDebtSaleSettings extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('allowDebtSales', 'branches')) {
            $this->forge->addColumn('branches', [
                'allowDebtSales' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'null' => true,
                    'default' => null,
                    'after' => 'branchDescription',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('allowDebtSales', 'branches')) {
            $this->forge->dropColumn('branches', 'allowDebtSales');
        }
    }
}
