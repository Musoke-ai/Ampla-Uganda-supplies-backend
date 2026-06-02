<?php

namespace App\Models;

use CodeIgniter\Model;

class ImportRow extends Model
{
    protected $table = 'import_rows';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'importBatchId',
        'rowNumber',
        'rawData',
        'normalizedData',
        'status',
        'errors',
        'warnings',
        'createdEntityType',
        'createdEntityId',
    ];
}
