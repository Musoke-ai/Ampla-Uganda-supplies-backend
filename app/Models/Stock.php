<?php

namespace App\Models;

use CodeIgniter\Model;

class Stock extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'stock';
    protected $primaryKey       = 'stockId';
    protected $useAutoIncrement = true;
    // protected $returnType       = 'object';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['branchId','stockItem','stockOwner', 'stockItemQuantity', 'oldStock', 'stockItemPrice', 'itemSellingPrice', 'itemSupplier'];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
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
