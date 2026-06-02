<?php

namespace App\Models;

use CodeIgniter\Model;

class ImportBatch extends Model
{
    protected $table = 'import_batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'branchId',
        'userId',
        'importType',
        'fileName',
        'status',
        'totalRows',
        'validRows',
        'warningRows',
        'errorRows',
        'skippedRows',
        'importedRows',
        'headers',
        'mapping',
        'options',
        'summary',
        'confirmedAt',
    ];
}
