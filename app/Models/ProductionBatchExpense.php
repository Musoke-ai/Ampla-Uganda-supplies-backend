<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBatchExpense extends Model
{
    protected $table = 'production_batch_expenses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'batchId',
        'branchId',
        'category',
        'description',
        'amount',
    ];
}
