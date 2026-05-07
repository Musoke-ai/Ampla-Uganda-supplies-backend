<?php

namespace App\Models;

use CodeIgniter\Model;

class CopilotActionDraft extends Model
{
    protected $table = 'copilot_action_drafts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'draftKey',
        'userId',
        'branchId',
        'actionType',
        'status',
        'risk',
        'title',
        'summary',
        'sourceTool',
        'payload',
        'decisionBy',
        'decisionNote',
        'decisionAt',
        'executionStatus',
        'executionResult',
        'executedBy',
        'executedAt',
        'expiresAt',
        'createdAt',
        'updatedAt',
    ];
}
