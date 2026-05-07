<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'branchId',
        'userId',
        'action',
        'entityType',
        'entityId',
        'beforeData',
        'afterData',
        'metadata',
        'ipAddress',
        'userAgent',
        'auditDateCreated',
    ];
}
