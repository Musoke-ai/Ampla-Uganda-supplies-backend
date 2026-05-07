<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class InventoryReportService
{
    private BaseConnection $db;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->db = db_connect();
        $this->branchContext = service('branchContext');
    }

    public function build(array $filters): array
    {
        $stock = $this->stockSummary();
        $lowStock = $this->lowStock($filters['lowStockThreshold']);

        return [
            'summary' => [
                'activeProducts' => (int) ($stock['activeProducts'] ?? 0),
                'totalQuantity' => (float) ($stock['totalQuantity'] ?? 0),
                'stockCostValue' => (float) ($stock['stockCostValue'] ?? 0),
                'stockSellingValue' => (float) ($stock['stockSellingValue'] ?? 0),
                'expectedProfit' => max(0, (float) ($stock['stockSellingValue'] ?? 0) - (float) ($stock['stockCostValue'] ?? 0)),
                'lowStockProducts' => count($lowStock),
                'outOfStockProducts' => $this->outOfStockCount(),
            ],
            'lowStock' => $lowStock,
            'movements' => $this->stockMovements($filters),
            'accuracyNotes' => [
                'Stock valuation uses current product cost and selling prices.',
                'Stock movement reporting requires the stock_movements migration and all stock operations writing ledger entries.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        $builder = $this->db->table('inventory i')
            ->select('i.itemId AS productId, i.branchId, i.itemName AS product, i.itemQuantity AS quantity, i.itemStockPrice AS costPrice, i.itemLeastPrice AS sellingPrice, (i.itemQuantity * i.itemStockPrice) AS costValue, (i.itemQuantity * i.itemLeastPrice) AS sellingValue', false)
            ->orderBy('i.itemName', 'ASC');

        $this->scopeInventory($builder, 'i.branchId');

        if ($filters['search'] !== '') {
            $builder->like('i.itemName', $filters['search']);
        }

        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['productId', 'product', 'quantity', 'costPrice', 'sellingPrice', 'costValue', 'sellingValue'],
            'rows' => $builder->limit($filters['perPage'], $offset)->get()->getResultArray(),
        ];
    }

    public function movementReport(array $filters): array
    {
        $rows = $this->stockMovements($filters, false);
        $quantityIn = array_sum(array_column($rows, 'quantityIn'));
        $quantityOut = array_sum(array_column($rows, 'quantityOut'));
        $byType = $this->movementsByType($rows);

        return [
            'summary' => [
                'movementCount' => count($rows),
                'quantityIn' => round($quantityIn, 3),
                'quantityOut' => round($quantityOut, 3),
                'netQuantityMovement' => round($quantityIn - $quantityOut, 3),
                'movementTypeCount' => count($byType),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column($byType, 'label'),
                'datasets' => [
                    [
                        'label' => 'Movements',
                        'data' => array_map(static fn ($row) => (float) $row['value'], $byType),
                    ],
                ],
            ],
            'table' => $this->movementTable($rows, $filters),
            'insights' => $this->movementInsights($rows),
            'accuracyNotes' => [
                'Stock movements are read from the stock_movements ledger.',
                'Movement accuracy depends on every stock-in, stock-out, reversal, and raw material operation writing ledger entries.',
            ],
        ];
    }

    private function stockSummary(): array
    {
        $builder = $this->db->table('inventory i')
            ->select('COUNT(*) AS activeProducts, SUM(i.itemQuantity) AS totalQuantity, SUM(i.itemQuantity * i.itemStockPrice) AS stockCostValue, SUM(i.itemQuantity * i.itemLeastPrice) AS stockSellingValue', false);

        $this->scopeInventory($builder, 'i.branchId');

        return $builder->get()->getRowArray() ?? [];
    }

    private function lowStock(int $threshold): array
    {
        $builder = $this->db->table('inventory i')
            ->select('i.itemId, i.itemName, i.itemQuantity, i.itemStockPrice, i.itemLeastPrice')
            ->where('i.itemQuantity <=', $threshold)
            ->orderBy('i.itemQuantity', 'ASC')
            ->limit(25);

        $this->scopeInventory($builder, 'i.branchId');

        return $builder->get()->getResultArray();
    }

    private function outOfStockCount(): int
    {
        $builder = $this->db->table('inventory i')->where('i.itemQuantity <=', 0);
        $this->scopeInventory($builder, 'i.branchId');

        return $builder->countAllResults();
    }

    private function stockMovements(array $filters, bool $limit = true): array
    {
        if (!$this->db->tableExists('stock_movements')) {
            return [];
        }

        $builder = $this->db->table('stock_movements sm')
            ->select('sm.id, sm.branchId, sm.movementType, sm.quantityIn, sm.quantityOut, sm.balanceAfter, sm.unitCost, sm.referenceType, sm.referenceId, sm.userId, sm.movementDateCreated, i.itemName, rm.name AS rawMaterial')
            ->join('inventory i', 'i.itemId = sm.productId', 'left')
            ->join('raw_materials rm', 'rm.materialId = sm.rawMaterialId', 'left')
            ->where('sm.movementDateCreated >=', $filters['from'])
            ->where('sm.movementDateCreated <=', $filters['to'])
            ->orderBy('sm.movementDateCreated', 'DESC');

        $this->scopeInventory($builder, 'sm.branchId');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('i.itemName', $filters['search'])
                ->orLike('rm.name', $filters['search'])
                ->orLike('sm.movementType', $filters['search'])
                ->orLike('sm.referenceType', $filters['search'])
                ->groupEnd();
        }

        if ($limit) {
            $builder->limit(25);
        }

        return $builder->get()->getResultArray();
    }

    private function movementTable(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['id', 'movementDateCreated', 'itemName', 'rawMaterial', 'movementType', 'quantityIn', 'quantityOut', 'balanceAfter', 'unitCost', 'referenceType', 'referenceId', 'userId'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function movementsByType(array $rows): array
    {
        $types = [];

        foreach ($rows as $row) {
            $type = $row['movementType'] ?: 'Unspecified';
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        arsort($types);

        return array_map(
            static fn ($type, $count): array => ['label' => $type, 'value' => $count],
            array_keys($types),
            array_values($types)
        );
    }

    private function movementInsights(array $rows): array
    {
        if (!$this->db->tableExists('stock_movements')) {
            return [
                [
                    'severity' => 'warning',
                    'message' => 'The stock_movements table is not available.',
                    'suggested_action' => 'Run the stock movement migration before relying on movement reports.',
                ],
            ];
        }

        return count($rows) === 0 ? [
            [
                'severity' => 'info',
                'message' => 'No stock movements were found for this period.',
                'suggested_action' => 'Confirm the date range and that stock operations are writing ledger entries.',
            ],
        ] : [];
    }

    private function scopeInventory($builder, string $column): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where($column, $branchId);
        }
    }
}
