<?php

namespace App\Models;

use CodeIgniter\Model;

class ImportMapping extends Model
{
    protected $table = 'import_mappings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'userId',
        'importType',
        'name',
        'headers',
        'mapping',
        'options',
    ];
}
