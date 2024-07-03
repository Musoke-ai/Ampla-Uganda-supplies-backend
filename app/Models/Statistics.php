<?php

namespace App\Models;

use CodeIgniter\Model;

class Statistics extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 'statistics';
    protected $primaryKey       = 'statId';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['statItemId','busId', 'statItemStock', 'statItemStockWorth', 'statItemSales', 'statItemSalesWorth', 'statItemIndebt', 'statItemIndebtWorth', 'statItemIndebtToday', 'statItemIndebtTodayWorth', 'statItem'];

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
