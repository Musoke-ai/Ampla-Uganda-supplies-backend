<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBatchOutput extends Model
{
    protected $table = 'production_batch_outputs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'batchId',
        'branchId',
        'productId',
        'quantity',
        'wastageQuantity',
        'unitCost',
        'dailyProductRegisterId',
        'notes',
    ];
}
