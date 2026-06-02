<?php

namespace App\Models;

use CodeIgniter\Model;

class Inventory extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'inventory';
    protected $primaryKey       = 'itemId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'branchId',
        'itemName',
        'itemCategoryId',
        'itemModel',
        'itemQuality',
        'itemQuantity',
        'itemNumber',
        'itemSku',
        'itemBarcode',
        'itemImage',
        'itemBrand',
        'itemProductType',
        'itemUnit',
        'itemSupplier',
        'itemReorderLevel',
        'itemWholesalePrice',
        'itemCondition',
        'itemSize',
        'itemStockPrice',
        'itemLeastPrice',
        'itemNotes',
        'itemOwner',
        'itemDateCreated'
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
