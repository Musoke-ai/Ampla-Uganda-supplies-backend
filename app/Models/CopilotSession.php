<?php

namespace App\Models;

use CodeIgniter\Model;

class CopilotSession extends Model
{
    protected $table = 'copilot_sessions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'sessionKey',
        'userId',
        'branchId',
        'lastIntent',
        'lastTool',
        'lastProduct',
        'lastCustomer',
        'lastPeriod',
        'lastSearch',
        'contextData',
        'messageCount',
        'createdAt',
        'updatedAt',
    ];
}
