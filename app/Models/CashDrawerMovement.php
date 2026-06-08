<?php

namespace App\Models;

use CodeIgniter\Model;

class CashDrawerMovement extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cash_drawer_movements';
    protected $primaryKey       = 'movementId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'drawerId',
        'branchId',
        'userId',
        'receiptId',
        'movementType',
        'amount',
        'reason',
        'movementDateCreated',
    ];
}
