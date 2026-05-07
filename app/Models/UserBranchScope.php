<?php

namespace App\Models;

use CodeIgniter\Model;

class UserBranchScope extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'user_branch_scopes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'assigned_branch_id',
        'active_branch_id',
        'created_by',
        'can_switch_branches',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';
}
