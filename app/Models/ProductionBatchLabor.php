<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBatchLabor extends Model
{
    protected $table = 'production_batch_labor';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'batchId',
        'branchId',
        'employeeId',
        'role',
        'hoursWorked',
        'laborCost',
        'notes',
    ];
}
