<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Sales;

class SalesService
{
    protected Sales $salesModel;
    protected Inventory $inventoryModel;
    protected BranchContextService $branchContext;

    public function __construct()
    {
        $this->salesModel = new Sales();
        $this->inventoryModel = new Inventory();
        $this->branchContext = service('branchContext');
    }

    public function getSalesSummary(string $period = 'today'): array
    {
        $builder = $this->salesModel
            ->select('
                COUNT(saleId) AS sale_count,
                SUM(COALESCE(saleQuantity, 0)) AS total_units_sold,
                SUM(COALESCE(saleQuantity, 0) * COALESCE(salePrice, 0)) AS total_sales_value,
                MIN(saleDateCreated) AS first_sale_at,
                MAX(saleDateCreated) AS last_sale_at
            ');

        if ($period === 'today') {
            $builder->where('DATE(saleDateCreated)', date('Y-m-d'));
        }

        $builder
            ->groupStart()
                ->where('saleStatus <>', 'cancelled')
                ->orWhere('saleStatus IS NULL', null, false)
            ->groupEnd();

        $this->branchContext->scopeBuilder($builder);
        $result = $builder->first();

        return $result ? [$result] : [];
    }

    public function searchSalesByProduct(string $productName, int $limit = 20): array
    {
        $builder = $this->salesModel
            ->select('
                sales.saleId,
                sales.saleItemId,
                inventory.itemName,
                inventory.itemModel,
                sales.saleQuantity,
                sales.salePrice,
                (COALESCE(sales.saleQuantity, 0) * COALESCE(sales.salePrice, 0)) AS line_total,
                sales.custId,
                sales.saleDateCreated
            ')
            ->join('inventory', 'inventory.itemId = sales.saleItemId', 'left')
            ->groupStart()
                ->like('inventory.itemName', $productName)
                ->orLike('inventory.itemModel', $productName)
            ->groupEnd()
            ->groupStart()
                ->where('sales.saleStatus <>', 'cancelled')
                ->orWhere('sales.saleStatus IS NULL', null, false)
            ->groupEnd()
            ->orderBy('sales.saleDateCreated', 'DESC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder, 'sales.branchId')->findAll();
    }
}
