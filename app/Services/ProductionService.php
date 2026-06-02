<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Orders;
use App\Models\RawMaterials;

class ProductionService
{
    protected Orders $ordersModel;
    protected Inventory $inventoryModel;
    protected RawMaterials $rawMaterialsModel;
    protected BranchContextService $branchContext;

    public function __construct()
    {
        $this->ordersModel = new Orders();
        $this->inventoryModel = new Inventory();
        $this->rawMaterialsModel = new RawMaterials();
        $this->branchContext = service('branchContext');
    }

    public function getProductionOverview(): array
    {
        $orderBuilder = $this->ordersModel
            ->select('
                COUNT(orderId) AS total_orders,
                SUM(CASE WHEN COALESCE(quantityProduced, 0) < COALESCE(quantity, 0) THEN 1 ELSE 0 END) AS orders_in_progress,
                SUM(COALESCE(quantity, 0)) AS total_quantity_ordered,
                SUM(COALESCE(quantityProduced, 0)) AS total_quantity_produced,
                SUM(COALESCE(totalCost, 0)) AS total_order_value,
                SUM(COALESCE(amountPaid, 0)) AS total_amount_paid
            ');
        $orderStats = $this->branchContext->scopeBuilder($orderBuilder, 'branchId')->first();

        $rawMaterialBuilder = $this->rawMaterialsModel
            ->select('
                COUNT(materialId) AS total_raw_materials,
                SUM(CASE WHEN COALESCE(Quantity, 0) <= COALESCE(NULLIF(reorderLevel, 0), 10) THEN 1 ELSE 0 END) AS low_stock_raw_materials,
                SUM(COALESCE(Quantity, 0) * COALESCE(unitPrice, 0)) AS raw_material_stock_value
            ');
        $rawMaterialStats = $this->branchContext->scopeBuilder($rawMaterialBuilder, 'branchId')->first();

        return [[
            'total_orders' => (int)($orderStats['total_orders'] ?? 0),
            'orders_in_progress' => (int)($orderStats['orders_in_progress'] ?? 0),
            'total_quantity_ordered' => (float)($orderStats['total_quantity_ordered'] ?? 0),
            'total_quantity_produced' => (float)($orderStats['total_quantity_produced'] ?? 0),
            'total_order_value' => (float)($orderStats['total_order_value'] ?? 0),
            'total_amount_paid' => (float)($orderStats['total_amount_paid'] ?? 0),
            'total_raw_materials' => (int)($rawMaterialStats['total_raw_materials'] ?? 0),
            'low_stock_raw_materials' => (int)($rawMaterialStats['low_stock_raw_materials'] ?? 0),
            'raw_material_stock_value' => (float)($rawMaterialStats['raw_material_stock_value'] ?? 0),
        ]];
    }

    public function searchProductionOrders(string $keyword, int $limit = 20): array
    {
        $builder = $this->ordersModel
            ->select('
                orders.orderId,
                orders.custId,
                customers.custName,
                orders.prodId,
                inventory.itemName,
                orders.customSize,
                orders.quantity,
                orders.quantityProduced,
                orders.totalCost,
                orders.amountPaid,
                orders.status,
                orders.description,
                orders.orderDateCreated,
                orders.orderDateUpdated
            ')
            ->join('customers', 'customers.custId = orders.custId', 'left')
            ->join('inventory', 'inventory.itemId = orders.prodId', 'left')
            ->groupStart()
                ->like('customers.custName', $keyword)
                ->orLike('inventory.itemName', $keyword)
                ->orLike('orders.customSize', $keyword)
                ->orLike('orders.description', $keyword)
            ->groupEnd()
            ->orderBy('orders.orderDateUpdated', 'DESC')
            ->limit(max(1, $limit));

        return $this->branchContext->scopeBuilder($builder, 'orders.branchId')->findAll();
    }

    public function getLowStockRawMaterials(int $threshold = 10): array
    {
        $builder = $this->rawMaterialsModel
            ->select('materialId, materialCode, rawMaterialBarcode, name, category, size, unitOfMeasure, Quantity, unitPrice, reorderLevel, supplier, supplierContact, storageLocation, status, note, expiry')
            ->groupStart()
                ->where('Quantity <= COALESCE(NULLIF(reorderLevel, 0), ' . max(0, $threshold) . ')', null, false)
            ->groupEnd()
            ->orderBy('Quantity', 'ASC')
            ->limit(20);

        return $this->branchContext->scopeBuilder($builder, 'branchId')->findAll();
    }
}
