<?php

namespace App\Models;

use CodeIgniter\Model;

class RawMaterials extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'raw_materials';
    protected $primaryKey       = 'materialId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'branchId',
        'name',
        'materialCode',
        'category',
        'size',
        'unitOfMeasure',
        'Quantity',
        'unitPrice',
        'reorderLevel',
        'supplier',
        'supplierContact',
        'storageLocation',
        'status',
        'note',
        'expiry',
        'rawMaterialDateCreated',
        'rawMaterialDateUpdated',
        'rawMaterialDateDeleted',
    ];

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
