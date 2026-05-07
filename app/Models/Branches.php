<?php

namespace App\Models;

use CodeIgniter\Model;

class Branches extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'branches';
    protected $primaryKey       = 'branchId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'branchName',
        'branchCode',
        'branchLocation',
        'branchContact',
        'branchEmail',
        'branchManager',
        'branchStatus',
        'branchDescription',
        'allowDebtSales',
    ];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
