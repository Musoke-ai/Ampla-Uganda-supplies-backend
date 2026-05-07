<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAppSettingsToBusinessProfile extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('appSettings', 'businessprofile')) {
            $this->forge->addColumn('businessprofile', [
                'appSettings' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'after' => 'busLogo',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('appSettings', 'businessprofile')) {
            $this->forge->dropColumn('businessprofile', 'appSettings');
        }
    }
}
