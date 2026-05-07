<?php

namespace App\Models;

use CodeIgniter\Model;

class StockMovement extends Model
{
    protected $table = 'stock_movements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'branchId',
        'productId',
        'rawMaterialId',
        'movementType',
        'quantityIn',
        'quantityOut',
        'balanceAfter',
        'unitCost',
        'referenceType',
        'referenceId',
        'referenceNo',
        'userId',
        'movementDateCreated',
    ];
}
