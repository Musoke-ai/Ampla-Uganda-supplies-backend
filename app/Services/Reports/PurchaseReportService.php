<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class PurchaseReportService
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
        $rows = $this->rows($filters);
        $totalCost = array_sum(array_column($rows, 'purchase_cost'));
        $sellingValue = array_sum(array_column($rows, 'expected_selling_value'));
        $totalQuantity = array_sum(array_column($rows, 'quantity'));

        return [
            'summary' => [
                'purchaseIntakeCount' => count($rows),
                'totalQuantityPurchased' => round($totalQuantity, 3),
                'purchaseCost' => round($totalCost, 2),
                'expectedSellingValue' => round($sellingValue, 2),
                'expectedMargin' => round($sellingValue - $totalCost, 2),
                'supplierCount' => count(array_unique(array_filter(array_column($rows, 'supplier')))),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column(array_slice($this->bySupplier($rows), 0, 10), 'label'),
                'datasets' => [
                    [
                        'label' => 'Purchase Cost',
                        'data' => array_map(static fn ($row) => (float) $row['value'], array_slice($this->bySupplier($rows), 0, 10)),
                    ],
                ],
            ],
            'table' => $this->paginate($rows, $filters),
            'insights' => $this->insights($rows),
            'accuracyNotes' => [
                'Purchase reporting uses stock intake records from the stock table.',
                'Supplier balances and payment status need suppliers, purchases, purchase_items, and supplier_payments tables.',
                'This report treats stock.stockItemPrice as unit purchase cost and itemSellingPrice as expected unit selling price.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return $this->paginate($this->rows($filters), $filters);
    }

    private function rows(array $filters): array
    {
        $dateColumn = $this->stockDateColumn();
        $oldStockSelect = $this->db->fieldExists('oldStock', 'stock') ? 'st.oldStock' : 'NULL';
        $builder = $this->db->table('stock st')
            ->select("st.stockId AS stock_id, st.branchId AS branch_id, {$dateColumn} AS date, st.stockItem AS product_id, i.itemName AS product, st.stockItemQuantity AS quantity, {$oldStockSelect} AS old_stock, st.stockItemPrice AS unit_cost, st.itemSellingPrice AS unit_selling_price, st.itemSupplier AS supplier", false)
            ->join('inventory i', 'i.itemId = st.stockItem', 'left')
            ->where("{$dateColumn} >=", $filters['from'])
            ->where("{$dateColumn} <=", $filters['to'])
            ->orderBy($dateColumn, 'DESC');

        $this->scope($builder, 'st.branchId');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('i.itemName', $filters['search'])
                ->orLike('st.itemSupplier', $filters['search'])
                ->groupEnd();
        }

        $rows = $builder->get()->getResultArray();

        return array_map(static function (array $row): array {
            $quantity = (float) ($row['quantity'] ?? 0);
            $unitCost = (float) ($row['unit_cost'] ?? 0);
            $unitSellingPrice = (float) ($row['unit_selling_price'] ?? 0);

            return [
                'stock_id' => (int) ($row['stock_id'] ?? 0),
                'date' => $row['date'] ?? null,
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product' => $row['product'] ?: 'Unknown product',
                'quantity' => $quantity,
                'old_stock' => (float) ($row['old_stock'] ?? 0),
                'unit_cost' => $unitCost,
                'unit_selling_price' => $unitSellingPrice,
                'purchase_cost' => round($quantity * $unitCost, 2),
                'expected_selling_value' => round($quantity * $unitSellingPrice, 2),
                'supplier' => trim((string) ($row['supplier'] ?? '')) ?: 'Unspecified',
            ];
        }, $rows);
    }

    private function bySupplier(array $rows): array
    {
        $suppliers = [];

        foreach ($rows as $row) {
            $supplier = $row['supplier'] ?: 'Unspecified';
            $suppliers[$supplier] = ($suppliers[$supplier] ?? 0) + (float) ($row['purchase_cost'] ?? 0);
        }

        arsort($suppliers);

        return array_map(
            static fn ($supplier, $value): array => ['label' => $supplier, 'value' => round((float) $value, 2)],
            array_keys($suppliers),
            array_values($suppliers)
        );
    }

    private function paginate(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['stock_id', 'date', 'product', 'supplier', 'quantity', 'old_stock', 'unit_cost', 'unit_selling_price', 'purchase_cost', 'expected_selling_value'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function insights(array $rows): array
    {
        $insights = [];

        foreach ($rows as $row) {
            if ((float) $row['unit_selling_price'] < (float) $row['unit_cost']) {
                $insights[] = [
                    'severity' => 'warning',
                    'message' => $row['product'] . ' was stocked with selling price below purchase cost.',
                    'suggested_action' => 'Review the product selling price before selling this batch.',
                ];
            }

            if ($row['supplier'] === 'Unspecified') {
                $insights[] = [
                    'severity' => 'info',
                    'message' => $row['product'] . ' has no supplier name on its stock intake.',
                    'suggested_action' => 'Add supplier details to improve supplier and purchase reporting.',
                ];
            }

            if (count($insights) >= 8) {
                break;
            }
        }

        return $insights;
    }

    private function stockDateColumn(): string
    {
        if ($this->db->fieldExists('stockCreated', 'stock')) {
            return 'st.stockCreated';
        }

        return 'st.stockId';
    }

    private function scope($builder, string $column): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null && $this->db->fieldExists('branchId', 'stock')) {
            $builder->where($column, $branchId);
        }
    }
}
