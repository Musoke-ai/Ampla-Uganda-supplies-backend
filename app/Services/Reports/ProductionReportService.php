<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class ProductionReportService
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
        $orders = $this->orderRows($filters);
        $outputs = $this->outputRows($filters);
        $orderedQuantity = array_sum(array_column($orders, 'quantity_ordered'));
        $producedQuantity = array_sum(array_column($orders, 'quantity_produced'));
        $orderValue = array_sum(array_column($orders, 'order_value'));
        $amountPaid = array_sum(array_column($orders, 'amount_paid'));

        return [
            'summary' => [
                'orderCount' => count($orders),
                'quantityOrdered' => round($orderedQuantity, 3),
                'quantityProducedOnOrders' => round($producedQuantity, 3),
                'orderProgressPercent' => $orderedQuantity > 0 ? round(($producedQuantity / $orderedQuantity) * 100, 2) : 0,
                'orderValue' => round($orderValue, 2),
                'amountPaid' => round($amountPaid, 2),
                'outstandingOrderValue' => round(max(0, $orderValue - $amountPaid), 2),
                'finishedGoodsProduced' => round(array_sum(array_column($outputs, 'quantity_produced')), 3),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column($this->byStatus($orders), 'label'),
                'datasets' => [
                    [
                        'label' => 'Orders',
                        'data' => array_map(static fn ($row) => (float) $row['value'], $this->byStatus($orders)),
                    ],
                ],
            ],
            'table' => $this->paginate($orders, $filters),
            'outputs' => $outputs,
            'insights' => $this->insights($orders),
            'accuracyNotes' => [
                'Production order progress uses orders.quantity and orders.quantityProduced.',
                'Finished goods output uses daily_products_register when populated.',
                'Accurate batch profitability needs production batch, material usage, labour, output, and expense links.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return $this->paginate($this->orderRows($filters), $filters);
    }

    private function orderRows(array $filters): array
    {
        $builder = $this->db->table('orders o')
            ->select('o.orderId AS order_id, o.branchId AS branch_id, o.orderDateCreated AS date, o.custId AS customer_id, c.custName AS customer, o.prodId AS product_id, i.itemName AS product, o.quantity AS quantity_ordered, o.quantityProduced AS quantity_produced, o.totalCost AS order_value, o.amountPaid AS amount_paid, o.status, o.description', false)
            ->join('customers c', 'c.custId = o.custId', 'left')
            ->join('inventory i', 'i.itemId = o.prodId', 'left')
            ->where('o.orderDateCreated >=', $filters['from'])
            ->where('o.orderDateCreated <=', $filters['to'])
            ->orderBy('o.orderDateCreated', 'DESC');

        $this->scope($builder, 'o.branchId', 'orders');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('c.custName', $filters['search'])
                ->orLike('i.itemName', $filters['search'])
                ->orLike('o.status', $filters['search'])
                ->orLike('o.description', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            $ordered = (float) ($row['quantity_ordered'] ?? 0);
            $produced = (float) ($row['quantity_produced'] ?? 0);
            $orderValue = (float) ($row['order_value'] ?? 0);
            $amountPaid = (float) ($row['amount_paid'] ?? 0);

            return [
                'order_id' => (int) ($row['order_id'] ?? 0),
                'date' => $row['date'] ?? null,
                'customer' => $row['customer'] ?: 'Unspecified',
                'product' => $row['product'] ?: 'Custom / unspecified',
                'quantity_ordered' => $ordered,
                'quantity_produced' => $produced,
                'progress_percent' => $ordered > 0 ? round(($produced / $ordered) * 100, 2) : 0,
                'order_value' => round($orderValue, 2),
                'amount_paid' => round($amountPaid, 2),
                'outstanding_value' => round(max(0, $orderValue - $amountPaid), 2),
                'status' => $row['status'] ?: 'Unspecified',
                'description' => $row['description'] ?? '',
            ];
        }, $builder->get()->getResultArray());
    }

    private function outputRows(array $filters): array
    {
        if (!$this->db->tableExists('daily_products_register')) {
            return [];
        }

        $quantityColumn = $this->db->fieldExists('Quantity', 'daily_products_register') ? 'dpr.Quantity' : 'dpr.quantity';
        $builder = $this->db->table('daily_products_register dpr')
            ->select("dpr.prodId AS product_id, i.itemName AS product, SUM({$quantityColumn}) AS quantity_produced, COUNT(*) AS entries", false)
            ->join('inventory i', 'i.itemId = dpr.prodId', 'left')
            ->where('dpr.dailyProductionDateCreated >=', $filters['from'])
            ->where('dpr.dailyProductionDateCreated <=', $filters['to'])
            ->groupBy('dpr.prodId, i.itemName')
            ->orderBy('quantity_produced', 'DESC');

        return array_map(static function (array $row): array {
            return [
                'product_id' => (int) ($row['product_id'] ?? 0),
                'product' => $row['product'] ?: 'Unknown product',
                'quantity_produced' => (float) ($row['quantity_produced'] ?? 0),
                'entries' => (int) ($row['entries'] ?? 0),
            ];
        }, $builder->get()->getResultArray());
    }

    private function byStatus(array $rows): array
    {
        $statuses = [];

        foreach ($rows as $row) {
            $status = $row['status'] ?: 'Unspecified';
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }

        return array_map(
            static fn ($status, $value): array => ['label' => $status, 'value' => $value],
            array_keys($statuses),
            array_values($statuses)
        );
    }

    private function paginate(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['order_id', 'date', 'customer', 'product', 'quantity_ordered', 'quantity_produced', 'progress_percent', 'order_value', 'amount_paid', 'outstanding_value', 'status'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function insights(array $orders): array
    {
        $insights = [];

        foreach ($orders as $order) {
            if ((float) $order['progress_percent'] < 50 && strtolower((string) $order['status']) !== 'completed') {
                $insights[] = [
                    'severity' => 'info',
                    'message' => 'Order #' . $order['order_id'] . ' is ' . $order['progress_percent'] . '% produced.',
                    'suggested_action' => 'Review production schedule and raw material availability.',
                ];
            }

            if ((float) $order['outstanding_value'] > 0) {
                $insights[] = [
                    'severity' => 'warning',
                    'message' => 'Order #' . $order['order_id'] . ' has outstanding value of ' . number_format((float) $order['outstanding_value']) . '.',
                    'suggested_action' => 'Follow up customer payment before dispatch or completion.',
                ];
            }

            if (count($insights) >= 8) {
                break;
            }
        }

        return $insights;
    }

    private function scope($builder, string $column, string $table): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null && $this->db->fieldExists('branchId', $table)) {
            $builder->where($column, $branchId);
        }
    }
}
