<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBatchMaterial extends Model
{
    protected $table = 'production_batch_materials';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'batchId',
        'branchId',
        'materialId',
        'quantity',
        'unitCost',
        'totalCost',
        'dailyRawMaterialRegisterId',
        'notes',
    ];
}
