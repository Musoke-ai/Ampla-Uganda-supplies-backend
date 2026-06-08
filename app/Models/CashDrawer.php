<?php

namespace App\Models;

use CodeIgniter\Model;

class CashDrawer extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'cash_drawers';
    protected $primaryKey       = 'drawerId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'branchId',
        'openedBy',
        'closedBy',
        'status',
        'openingFloat',
        'cashSalesTotal',
        'cashInTotal',
        'cashOutTotal',
        'expectedCash',
        'countedCash',
        'variance',
        'openingNote',
        'closingNote',
        'openedAt',
        'closedAt',
    ];
}
