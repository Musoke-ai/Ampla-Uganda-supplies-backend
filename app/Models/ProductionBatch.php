<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductionBatch extends Model
{
    protected $table = 'production_batches';
    protected $primaryKey = 'batchId';
    protected $returnType = 'array';
    protected $allowedFields = [
        'branchId',
        'batchNo',
        'orderId',
        'productId',
        'supervisorId',
        'quantityPlanned',
        'quantityProduced',
        'wastageQuantity',
        'status',
        'startDate',
        'endDate',
        'qualityStatus',
        'qualityCheckedBy',
        'qualityCheckedAt',
        'qualityNotes',
        'notes',
        'createdBy',
    ];
}
