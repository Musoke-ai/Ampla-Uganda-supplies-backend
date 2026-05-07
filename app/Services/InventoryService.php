<?php

namespace App\Services;

use App\Models\Inventory;
use CodeIgniter\Database\BaseConnection;

class InventoryService
{
    protected Inventory $productModel;
    protected BranchContextService $branchContext;
    protected BaseConnection $db;

    public function __construct()
    {
        $this->productModel = new Inventory();
        $this->branchContext = service('branchContext');
        $this->db = db_connect();
    }

    public function getLowStockProducts(int $limit = 25): array
    {
        $builder = $this->productModel
            ->select('itemId, itemName, itemQuantity, itemReorderLevel, itemStockPrice, itemLeastPrice')
            ->groupStart()
                ->where('itemQuantity <= COALESCE(NULLIF(itemReorderLevel, 0), 11)', null, false)
            ->groupEnd()
            ->orderBy('itemQuantity', 'ASC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function searchProductStock(string $productName): array
    {
        $builder = $this->productModel
            ->select('itemId, itemName, itemModel, itemSku, itemBarcode, itemBrand, itemQuantity, itemReorderLevel, itemUnit, itemSupplier, itemStockPrice, itemLeastPrice')
            ->groupStart()
                ->like('itemName', $productName)
                ->orLike('itemModel', $productName)
                ->orLike('itemSku', $productName)
                ->orLike('itemBarcode', $productName)
            ->groupEnd()
            ->orderBy('itemName', 'ASC')
            ->limit(20);

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function getInventoryValue(): array
    {
        $builder = $this->productModel
            ->select('
                itemId,
                itemName,
                itemQuantity,
                itemStockPrice,
                itemLeastPrice,
                (itemQuantity * itemStockPrice) AS cost_value,
                (itemQuantity * itemLeastPrice) AS selling_value
            ')
            ->orderBy('selling_value', 'DESC');

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function getOutOfStockProducts(int $limit = 25): array
    {
        $builder = $this->productModel
            ->select('itemId, itemName, itemModel, itemSku, itemQuantity, itemReorderLevel, itemSupplier, itemStockPrice, itemLeastPrice')
            ->where('itemQuantity <=', 0)
            ->orderBy('itemName', 'ASC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function getInventoryHealthSummary(): array
    {
        $builder = $this->productModel
            ->select('
                COUNT(*) AS product_count,
                SUM(CASE WHEN itemQuantity <= 0 THEN 1 ELSE 0 END) AS out_of_stock_count,
                SUM(CASE WHEN itemQuantity > 0 AND itemQuantity <= COALESCE(NULLIF(itemReorderLevel, 0), 11) THEN 1 ELSE 0 END) AS low_stock_count,
                SUM(COALESCE(itemQuantity, 0)) AS total_units,
                SUM(COALESCE(itemQuantity, 0) * COALESCE(itemStockPrice, 0)) AS cost_value,
                SUM(COALESCE(itemQuantity, 0) * COALESCE(itemLeastPrice, 0)) AS selling_value
            ', false);

        $summary = $this->branchContext->scopeBuilder($builder)->first() ?? [];
        $summary['expected_profit'] = max(0, (float) ($summary['selling_value'] ?? 0) - (float) ($summary['cost_value'] ?? 0));

        return [$summary];
    }

    public function getReorderSuggestions(int $limit = 25): array
    {
        $builder = $this->productModel
            ->select('
                itemId,
                itemName,
                itemModel,
                itemSku,
                itemSupplier,
                itemQuantity,
                COALESCE(NULLIF(itemReorderLevel, 0), 11) AS reorder_level,
                GREATEST(COALESCE(NULLIF(itemReorderLevel, 0), 11) - COALESCE(itemQuantity, 0), 0) AS suggested_quantity
            ', false)
            ->where('itemQuantity <= COALESCE(NULLIF(itemReorderLevel, 0), 11)', null, false)
            ->orderBy('suggested_quantity', 'DESC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder)->findAll();
    }

    public function getSlowMovingProducts(int $days = 90, int $limit = 25): array
    {
        $days = max(1, min($days, 365));
        $limit = max(1, $limit);
        $cutoff = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));

        $builder = $this->db->table('inventory i')
            ->select('
                i.itemId,
                i.itemName,
                i.itemModel,
                i.itemSku,
                i.itemQuantity,
                i.itemStockPrice,
                i.itemLeastPrice,
                MAX(s.saleDateCreated) AS last_sale_at,
                COALESCE(SUM(CASE WHEN s.saleDateCreated >= ' . $this->db->escape($cutoff) . ' THEN s.saleQuantity ELSE 0 END), 0) AS units_sold_in_period
            ', false)
            ->join('sales s', "s.saleItemId = i.itemId AND (s.saleStatus <> 'cancelled' OR s.saleStatus IS NULL)", 'left')
            ->where('i.itemQuantity >', 0)
            ->groupBy('i.itemId, i.itemName, i.itemModel, i.itemSku, i.itemQuantity, i.itemStockPrice, i.itemLeastPrice')
            ->having('units_sold_in_period <=', 0)
            ->orderBy('i.itemQuantity', 'DESC')
            ->limit($limit);

        return $this->branchContext->scopeBuilder($builder, 'i.branchId')->get()->getResultArray();
    }

    public function getOverstockedProducts(int $days = 90, int $limit = 25): array
    {
        $days = max(1, min($days, 365));
        $limit = max(1, $limit);
        $cutoff = date('Y-m-d 00:00:00', strtotime('-' . $days . ' days'));

        $builder = $this->db->table('inventory i')
            ->select('
                i.itemId,
                i.itemName,
                i.itemModel,
                i.itemSku,
                i.itemQuantity,
                i.itemReorderLevel,
                i.itemStockPrice,
                i.itemLeastPrice,
                COALESCE(SUM(CASE WHEN s.saleDateCreated >= ' . $this->db->escape($cutoff) . ' THEN s.saleQuantity ELSE 0 END), 0) AS units_sold_in_period,
                CASE
                    WHEN COALESCE(SUM(CASE WHEN s.saleDateCreated >= ' . $this->db->escape($cutoff) . ' THEN s.saleQuantity ELSE 0 END), 0) <= 0 THEN NULL
                    ELSE ROUND(i.itemQuantity / (COALESCE(SUM(CASE WHEN s.saleDateCreated >= ' . $this->db->escape($cutoff) . ' THEN s.saleQuantity ELSE 0 END), 0) / ' . $days . '), 1)
                END AS estimated_days_of_stock
            ', false)
            ->join('sales s', "s.saleItemId = i.itemId AND (s.saleStatus <> 'cancelled' OR s.saleStatus IS NULL)", 'left')
            ->where('i.itemQuantity >', 0)
            ->groupBy('i.itemId, i.itemName, i.itemModel, i.itemSku, i.itemQuantity, i.itemReorderLevel, i.itemStockPrice, i.itemLeastPrice')
            ->having('units_sold_in_period <= 0 OR estimated_days_of_stock > ' . (int) ($days * 2), null, false)
            ->orderBy('i.itemQuantity', 'DESC')
            ->limit($limit);

        return $this->branchContext->scopeBuilder($builder, 'i.branchId')->get()->getResultArray();
    }
}
