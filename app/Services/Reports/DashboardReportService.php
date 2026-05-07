<?php

namespace App\Services\Reports;

use CodeIgniter\Database\BaseConnection;

class DashboardReportService
{
    private BaseConnection $db;
    private SalesReportService $salesReport;
    private InventoryReportService $inventoryReport;
    private ExpenseReportService $expenseReport;
    private CustomerReportService $customerReport;
    private AlertInsightService $alertInsight;

    public function __construct()
    {
        $this->db = db_connect();
        $this->salesReport = new SalesReportService();
        $this->inventoryReport = new InventoryReportService();
        $this->expenseReport = new ExpenseReportService();
        $this->customerReport = new CustomerReportService();
        $this->alertInsight = new AlertInsightService();
    }

    public function build(array $filters): array
    {
        $sales = $this->salesReport->build($filters);
        $inventory = $this->inventoryReport->build($filters);
        $expenses = $this->expenseReport->build($filters);
        $customers = $this->customerReport->build($filters);
        $alerts = $this->alertInsight->build($filters);

        $grossProfitEstimate = max(0, ($sales['summary']['netSales'] ?? 0) - $this->estimatedCogs($filters));
        $netProfitEstimate = $grossProfitEstimate - ($expenses['summary']['totalExpenses'] ?? 0);

        return [
            'kpis' => [
                $this->kpi('total_sales', 'Total Sales', $sales['summary']['netSales'] ?? 0, 'currency'),
                $this->kpi('cash_received', 'Cash Received', $sales['summary']['amountPaid'] ?? 0, 'currency'),
                $this->kpi('total_expenses', 'Total Expenses', $expenses['summary']['totalExpenses'] ?? 0, 'currency'),
                $this->kpi('gross_profit_estimate', 'Gross Profit Estimate', $grossProfitEstimate, 'currency'),
                $this->kpi('net_profit_estimate', 'Net Profit Estimate', $netProfitEstimate, 'currency'),
                $this->kpi('stock_value', 'Stock Cost Value', $inventory['summary']['stockCostValue'] ?? 0, 'currency'),
                $this->kpi('customer_debt', 'Customer Debt', $customers['summary']['totalCustomerDebt'] ?? 0, 'currency'),
                $this->kpi('active_products', 'Active Products', $inventory['summary']['activeProducts'] ?? 0, 'number'),
                $this->kpi('low_stock_products', 'Low Stock Products', $inventory['summary']['lowStockProducts'] ?? 0, 'number'),
                $this->kpi('alerts', 'Open Alerts', count($alerts['items'] ?? []), 'number'),
            ],
            'charts' => [
                [
                    'key' => 'sales_trend',
                    'type' => 'line',
                    'title' => 'Sales Trend',
                    'data' => $sales['trend'],
                ],
                [
                    'key' => 'expense_trend',
                    'type' => 'line',
                    'title' => 'Expense Trend',
                    'data' => $expenses['trend'],
                ],
                [
                    'key' => 'top_products',
                    'type' => 'bar',
                    'title' => 'Top Selling Products',
                    'data' => $sales['topProducts'],
                ],
            ],
            'insights' => array_slice($alerts['items'] ?? [], 0, 8),
            'accuracyNotes' => array_values(array_unique(array_merge(
                $sales['accuracyNotes'] ?? [],
                $inventory['accuracyNotes'] ?? [],
                $expenses['accuracyNotes'] ?? [],
                ['Gross and net profit remain estimates for historical sales without cost snapshots and until financial ledger support is added.']
            ))),
        ];
    }

    private function estimatedCogs(array $filters): float
    {
        $builder = $this->db->table('sales s')
            ->select('SUM(COALESCE(s.lineCostAtSale, s.saleQuantity * COALESCE(i.itemStockPrice, 0))) AS cogs', false)
            ->join('receipt r', 'r.SR_ID = s.SR_ID', 'left')
            ->join('inventory i', 'i.itemId = s.saleItemId', 'left')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupStart()
                ->where('s.saleStatus <>', 'cancelled')
                ->orWhere('s.saleStatus IS NULL', null, false)
            ->groupEnd()
            ->groupStart()
                ->where('r.receiptStatus <>', 'cancelled')
                ->orWhere('r.receiptStatus IS NULL', null, false)
            ->groupEnd();

        $branchId = service('branchContext')->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where('s.branchId', $branchId);
        }

        $row = $builder->get()->getRowArray() ?? [];

        return (float) ($row['cogs'] ?? 0);
    }

    private function kpi(string $key, string $label, $value, string $format): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => is_numeric($value) ? (float) $value : $value,
            'format' => $format,
        ];
    }
}
