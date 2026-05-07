<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class SalesReportService
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
        $receiptRows = $this->receiptRows($filters);
        $grossSales = array_sum(array_column($receiptRows, 'grossSales'));
        $discounts = array_sum(array_column($receiptRows, 'discount'));
        $amountPaid = array_sum(array_column($receiptRows, 'amountPaid'));
        $unpaidBalance = array_sum(array_column($receiptRows, 'dueAmount'));
        $totalCostAtSale = array_sum(array_column($receiptRows, 'totalCostAtSale'));
        $totalLineCount = array_sum(array_column($receiptRows, 'lineCount'));
        $costedLineCount = array_sum(array_column($receiptRows, 'costedLineCount'));
        $netSales = max(0, $grossSales - $discounts);
        $grossProfit = $netSales - $totalCostAtSale;

        $summary = [
            'grossSales' => $grossSales,
            'discounts' => $discounts,
            'netSales' => $netSales,
            'totalCostAtSale' => $totalCostAtSale,
            'grossProfit' => $grossProfit,
            'grossMarginPercent' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0,
            'profitCoveragePercent' => $totalLineCount > 0 ? round(($costedLineCount / $totalLineCount) * 100, 2) : 0,
            'amountPaid' => $amountPaid,
            'unpaidBalance' => $unpaidBalance,
            'receiptCount' => count($receiptRows),
            'averageSaleValue' => count($receiptRows) > 0 ? round($netSales / count($receiptRows), 2) : 0,
        ];

        return [
            'summary' => $summary,
            'trend' => $this->salesTrend($filters),
            'topProducts' => $this->topProducts($filters),
            'paymentMethods' => $this->paymentMethods($receiptRows),
            'accuracyNotes' => [
                'Sales totals are calculated on the backend from sales lines and receipt headers.',
                'New sales now store unitCostAtSale and lineCostAtSale for profit reporting.',
                'Historical sales created before the cost-at-sale migration may have empty cost snapshots.',
                'Cancelled sales are excluded when saleStatus or receiptStatus is cancelled.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        $builder = $this->db->table('sales s')
            ->select('s.saleId, s.SR_ID AS receiptId, s.branchId, s.saleQuantity AS quantity, s.salePrice AS unitPrice, s.unitCostAtSale, s.lineCostAtSale, (s.saleQuantity * s.salePrice) AS lineTotal, s.saleOwner, s.custId, r.srDateCreated AS date, r.timeStamp AS receiptCode, r.paymentMethod, i.itemName AS product, c.custName AS customer', false)
            ->join('receipt r', 'r.SR_ID = s.SR_ID', 'left')
            ->join('inventory i', 'i.itemId = s.saleItemId', 'left')
            ->join('customers c', 'c.custId = s.custId', 'left')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->orderBy('r.srDateCreated', 'DESC');

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('i.itemName', $filters['search'])
                ->orLike('c.custName', $filters['search'])
                ->orLike('s.SR_ID', $filters['search'])
                ->groupEnd();
        }

        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['saleId', 'receiptId', 'date', 'product', 'customer', 'quantity', 'unitPrice', 'unitCostAtSale', 'lineCostAtSale', 'lineTotal', 'paymentMethod'],
            'rows' => $builder->limit($filters['perPage'], $offset)->get()->getResultArray(),
        ];
    }

    public function productProfit(array $filters): array
    {
        $rows = $this->productProfitRows($filters);
        $summary = $this->productProfitSummary($rows);

        return [
            'summary' => $summary,
            'chart' => [
                'type' => 'bar',
                'labels' => array_column(array_slice($rows, 0, 10), 'product_name'),
                'datasets' => [
                    [
                        'label' => 'Gross Profit',
                        'data' => array_map(static fn ($row) => (float) $row['gross_profit'], array_slice($rows, 0, 10)),
                    ],
                    [
                        'label' => 'Net Sales',
                        'data' => array_map(static fn ($row) => (float) $row['net_sales'], array_slice($rows, 0, 10)),
                    ],
                ],
            ],
            'table' => $this->paginateProductProfitRows($rows, $filters),
            'insights' => $this->productProfitInsights($rows),
            'accuracyNotes' => [
                'Product profit uses sales.lineCostAtSale captured when the sale was created.',
                'Receipt-level discounts are allocated proportionally by each line gross sale value.',
                'Rows created before the cost-at-sale migration may have incomplete cost coverage.',
                'Cancelled sales are excluded when saleStatus or receiptStatus is cancelled.',
            ],
        ];
    }

    public function paidVsCredit(array $filters): array
    {
        $rows = $this->paidVsCreditRows($filters);
        $summary = $this->paidVsCreditSummary($rows);

        return [
            'summary' => $summary,
            'chart' => [
                'type' => 'bar',
                'labels' => ['Cash Collected', 'Outstanding Credit', 'Fully Paid Sales', 'Credit Sales'],
                'datasets' => [
                    [
                        'label' => 'Amount',
                        'data' => [
                            $summary['cash_collected'],
                            $summary['outstanding_credit'],
                            $summary['fully_paid_sales_value'],
                            $summary['credit_sales_value'],
                        ],
                    ],
                ],
            ],
            'table' => $this->paginatePaidVsCreditRows($rows, $filters),
            'insights' => $this->paidVsCreditInsights($summary, $rows),
            'accuracyNotes' => [
                'Paid vs credit is calculated at receipt level to avoid double-counting multi-line sales.',
                'Net sale value is sales lines minus receipt discount.',
                'Outstanding credit uses receipt.dueAmount, with net sale minus amount paid as a fallback.',
                'Cancelled sales are excluded when saleStatus or receiptStatus is cancelled.',
            ],
        ];
    }

    private function receiptRows(array $filters): array
    {
        $builder = $this->db->table('receipt r')
            ->select('r.SR_ID, r.paymentMethod, COALESCE(r.discount, 0) AS discount, COALESCE(r.amountPaid, 0) AS amountPaid, COALESCE(r.dueAmount, 0) AS dueAmount, SUM(s.saleQuantity * s.salePrice) AS grossSales, SUM(COALESCE(s.lineCostAtSale, 0)) AS totalCostAtSale, COUNT(s.saleId) AS lineCount, SUM(CASE WHEN s.lineCostAtSale IS NOT NULL THEN 1 ELSE 0 END) AS costedLineCount', false)
            ->join('sales s', 's.SR_ID = r.SR_ID')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupBy('r.SR_ID, r.paymentMethod, r.discount, r.amountPaid, r.dueAmount');

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        return $builder->get()->getResultArray();
    }

    private function salesTrend(array $filters): array
    {
        $builder = $this->db->table('receipt r')
            ->select('DATE(r.srDateCreated) AS label, SUM(s.saleQuantity * s.salePrice) AS value', false)
            ->join('sales s', 's.SR_ID = r.SR_ID')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupBy('DATE(r.srDateCreated)')
            ->orderBy('label', 'ASC');

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        return $builder->get()->getResultArray();
    }

    private function topProducts(array $filters): array
    {
        $builder = $this->db->table('sales s')
            ->select('s.saleItemId AS productId, i.itemName AS productName, SUM(s.saleQuantity) AS quantitySold, SUM(s.saleQuantity * s.salePrice) AS salesValue', false)
            ->join('receipt r', 'r.SR_ID = s.SR_ID', 'left')
            ->join('inventory i', 'i.itemId = s.saleItemId', 'left')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupBy('s.saleItemId, i.itemName')
            ->orderBy('quantitySold', 'DESC')
            ->limit(10);

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        return $builder->get()->getResultArray();
    }

    private function paymentMethods(array $receiptRows): array
    {
        $methods = [];

        foreach ($receiptRows as $row) {
            $label = trim((string) ($row['paymentMethod'] ?? '')) ?: 'Unknown';
            $methods[$label] = ($methods[$label] ?? 0) + (float) ($row['amountPaid'] ?? 0);
        }

        return array_map(
            static fn (string $label, float $value): array => ['label' => $label, 'value' => $value],
            array_keys($methods),
            array_values($methods)
        );
    }

    private function productProfitRows(array $filters): array
    {
        $lines = $this->productProfitLineRows($filters);
        $receiptGross = [];

        foreach ($lines as $line) {
            $receiptId = (string) ($line['receiptId'] ?? '');
            $receiptGross[$receiptId] = ($receiptGross[$receiptId] ?? 0) + $this->lineGross($line);
        }

        $products = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['productId'] ?? 0);
            $receiptId = (string) ($line['receiptId'] ?? '');
            $lineGross = $this->lineGross($line);
            $receiptGrossValue = (float) ($receiptGross[$receiptId] ?? 0);
            $receiptDiscount = (float) ($line['receiptDiscount'] ?? 0);
            $allocatedDiscount = $receiptGrossValue > 0 ? round(($lineGross / $receiptGrossValue) * $receiptDiscount, 2) : 0.0;
            $lineCost = $line['lineCostAtSale'] === null ? 0.0 : (float) $line['lineCostAtSale'];

            if (!isset($products[$productId])) {
                $products[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $line['productName'] ?? 'Unknown',
                    'category_id' => $line['categoryId'] ?? null,
                    'category_name' => $line['categoryName'] ?? 'Uncategorized',
                    'quantity_sold' => 0.0,
                    'gross_sales' => 0.0,
                    'discounts' => 0.0,
                    'net_sales' => 0.0,
                    'total_cost' => 0.0,
                    'gross_profit' => 0.0,
                    'gross_margin_percent' => 0.0,
                    'line_count' => 0,
                    'costed_line_count' => 0,
                    'cost_coverage_percent' => 0.0,
                ];
            }

            $products[$productId]['quantity_sold'] += (float) ($line['quantity'] ?? 0);
            $products[$productId]['gross_sales'] += $lineGross;
            $products[$productId]['discounts'] += $allocatedDiscount;
            $products[$productId]['total_cost'] += $lineCost;
            $products[$productId]['line_count']++;

            if ($line['lineCostAtSale'] !== null) {
                $products[$productId]['costed_line_count']++;
            }
        }

        foreach ($products as &$product) {
            $product['quantity_sold'] = round($product['quantity_sold'], 3);
            $product['gross_sales'] = round($product['gross_sales'], 2);
            $product['discounts'] = round($product['discounts'], 2);
            $product['net_sales'] = round(max(0, $product['gross_sales'] - $product['discounts']), 2);
            $product['total_cost'] = round($product['total_cost'], 2);
            $product['gross_profit'] = round($product['net_sales'] - $product['total_cost'], 2);
            $product['gross_margin_percent'] = $product['net_sales'] > 0
                ? round(($product['gross_profit'] / $product['net_sales']) * 100, 2)
                : 0.0;
            $product['cost_coverage_percent'] = $product['line_count'] > 0
                ? round(($product['costed_line_count'] / $product['line_count']) * 100, 2)
                : 0.0;
        }
        unset($product);

        $rows = array_values($products);
        $this->sortProductProfitRows($rows, $filters);

        return $rows;
    }

    private function paidVsCreditRows(array $filters): array
    {
        $builder = $this->db->table('receipt r')
            ->select('r.SR_ID AS receipt_id, r.timeStamp AS receipt_code, r.srDateCreated AS sale_date, r.paymentMethod AS payment_method, COALESCE(r.discount, 0) AS discount, COALESCE(r.amountPaid, 0) AS amount_paid, COALESCE(r.dueAmount, 0) AS due_amount, SUM(s.saleQuantity * s.salePrice) AS gross_sales, MAX(c.custName) AS customer_name, COUNT(s.saleId) AS line_count', false)
            ->join('sales s', 's.SR_ID = r.SR_ID')
            ->join('customers c', 'c.custId = s.custId', 'left')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupBy('r.SR_ID, r.timeStamp, r.srDateCreated, r.paymentMethod, r.discount, r.amountPaid, r.dueAmount')
            ->orderBy('r.srDateCreated', 'DESC');

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        if (!empty($filters['payment_method'])) {
            $builder->where('r.paymentMethod', $filters['payment_method']);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('r.SR_ID', $filters['search'])
                ->orLike('r.timeStamp', $filters['search'])
                ->orLike('r.paymentMethod', $filters['search'])
                ->orLike('c.custName', $filters['search'])
                ->groupEnd();
        }

        $rows = array_map(function (array $row): array {
            $grossSales = (float) ($row['gross_sales'] ?? 0);
            $discount = (float) ($row['discount'] ?? 0);
            $amountPaid = (float) ($row['amount_paid'] ?? 0);
            $dueAmount = (float) ($row['due_amount'] ?? 0);
            $netSales = round(max(0, $grossSales - $discount), 2);
            $outstanding = round(max(0, $dueAmount, $netSales - $amountPaid), 2);
            $saleType = $this->paidVsCreditSaleType($amountPaid, $outstanding);

            return [
                'receipt_id' => (int) ($row['receipt_id'] ?? 0),
                'receipt_code' => $row['receipt_code'] ?? '',
                'sale_date' => $row['sale_date'] ?? null,
                'customer_name' => $row['customer_name'] ?: 'Walk-in / unspecified',
                'payment_method' => $row['payment_method'] ?: 'Unknown',
                'sale_type' => $saleType,
                'gross_sales' => round($grossSales, 2),
                'discount' => round($discount, 2),
                'net_sales' => $netSales,
                'amount_paid' => round($amountPaid, 2),
                'outstanding_credit' => $outstanding,
                'line_count' => (int) ($row['line_count'] ?? 0),
            ];
        }, $builder->get()->getResultArray());

        if (!empty($filters['status'])) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['sale_type'] === $filters['status']));
        }

        return $rows;
    }

    private function paidVsCreditSaleType(float $amountPaid, float $outstanding): string
    {
        if ($outstanding <= 0) {
            return 'fully_paid';
        }

        return $amountPaid > 0 ? 'partial_credit' : 'unpaid_credit';
    }

    private function paidVsCreditSummary(array $rows): array
    {
        $creditRows = array_values(array_filter($rows, static fn (array $row): bool => $row['outstanding_credit'] > 0));
        $fullyPaidRows = array_values(array_filter($rows, static fn (array $row): bool => $row['outstanding_credit'] <= 0));
        $netSales = array_sum(array_column($rows, 'net_sales'));
        $outstandingCredit = array_sum(array_column($rows, 'outstanding_credit'));

        return [
            'receipt_count' => count($rows),
            'fully_paid_receipts' => count($fullyPaidRows),
            'credit_receipts' => count($creditRows),
            'fully_paid_sales_value' => round(array_sum(array_column($fullyPaidRows, 'net_sales')), 2),
            'credit_sales_value' => round(array_sum(array_column($creditRows, 'net_sales')), 2),
            'net_sales' => round($netSales, 2),
            'cash_collected' => round(array_sum(array_column($rows, 'amount_paid')), 2),
            'outstanding_credit' => round($outstandingCredit, 2),
            'collection_rate_percent' => $netSales > 0 ? round((($netSales - $outstandingCredit) / $netSales) * 100, 2) : 0,
            'credit_sales_percent' => $netSales > 0 ? round((array_sum(array_column($creditRows, 'net_sales')) / $netSales) * 100, 2) : 0,
        ];
    }

    private function paginatePaidVsCreditRows(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => [
                'receipt_id',
                'sale_date',
                'customer_name',
                'payment_method',
                'sale_type',
                'net_sales',
                'amount_paid',
                'outstanding_credit',
                'line_count',
            ],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function paidVsCreditInsights(array $summary, array $rows): array
    {
        $insights = [];

        if (($summary['outstanding_credit'] ?? 0) > 0) {
            $insights[] = [
                'type' => 'warning',
                'severity' => 'warning',
                'message' => 'Outstanding customer credit is ' . number_format((float) $summary['outstanding_credit']) . '.',
                'related_report' => 'sales.paid_vs_credit',
                'suggested_action' => 'Follow up credit customers and review credit limits before extending more credit.',
            ];
        }

        if (($summary['collection_rate_percent'] ?? 100) < 70 && ($summary['net_sales'] ?? 0) > 0) {
            $insights[] = [
                'type' => 'critical',
                'severity' => 'critical',
                'message' => 'Collection rate is only ' . $summary['collection_rate_percent'] . '% for this period.',
                'related_report' => 'sales.paid_vs_credit',
                'suggested_action' => 'Prioritize payment collection and review paid-vs-credit selling policy.',
            ];
        }

        foreach ($rows as $row) {
            if ((float) $row['outstanding_credit'] <= 0) {
                continue;
            }

            $insights[] = [
                'type' => 'info',
                'severity' => 'info',
                'message' => ($row['customer_name'] ?: 'Customer') . ' has outstanding credit of ' . number_format((float) $row['outstanding_credit']) . ' on receipt #' . $row['receipt_id'] . '.',
                'related_report' => 'sales.paid_vs_credit',
                'related_record' => ['receipt_id' => $row['receipt_id']],
                'suggested_action' => 'Check payment history and follow up if due.',
            ];

            if (count($insights) >= 8) {
                break;
            }
        }

        return $insights;
    }

    private function productProfitLineRows(array $filters): array
    {
        $builder = $this->db->table('sales s')
            ->select('s.SR_ID AS receiptId, s.saleItemId AS productId, i.itemName AS productName, i.itemCategoryId AS categoryId, cat.categoryName AS categoryName, s.saleQuantity AS quantity, s.salePrice AS unitPrice, s.lineCostAtSale, COALESCE(r.discount, 0) AS receiptDiscount', false)
            ->join('receipt r', 'r.SR_ID = s.SR_ID', 'left')
            ->join('inventory i', 'i.itemId = s.saleItemId', 'left')
            ->join('categories cat', 'cat.categoryId = i.itemCategoryId', 'left')
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to']);

        $this->activeOnly($builder);
        $this->scopeSales($builder);

        if (!empty($filters['product_id'])) {
            $builder->where('s.saleItemId', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $builder->where('i.itemCategoryId', $filters['category_id']);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('i.itemName', $filters['search'])
                ->orLike('cat.categoryName', $filters['search'])
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    private function productProfitSummary(array $rows): array
    {
        $lineCount = array_sum(array_column($rows, 'line_count'));
        $costedLineCount = array_sum(array_column($rows, 'costed_line_count'));
        $netSales = array_sum(array_column($rows, 'net_sales'));
        $grossProfit = array_sum(array_column($rows, 'gross_profit'));

        return [
            'total_products' => count($rows),
            'quantity_sold' => round(array_sum(array_column($rows, 'quantity_sold')), 3),
            'gross_sales' => round(array_sum(array_column($rows, 'gross_sales')), 2),
            'discounts' => round(array_sum(array_column($rows, 'discounts')), 2),
            'net_sales' => round($netSales, 2),
            'total_cost' => round(array_sum(array_column($rows, 'total_cost')), 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percent' => $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0,
            'cost_coverage_percent' => $lineCount > 0 ? round(($costedLineCount / $lineCount) * 100, 2) : 0,
        ];
    }

    private function paginateProductProfitRows(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => [
                'product_id',
                'product_name',
                'category_name',
                'quantity_sold',
                'gross_sales',
                'discounts',
                'net_sales',
                'total_cost',
                'gross_profit',
                'gross_margin_percent',
                'cost_coverage_percent',
            ],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function productProfitInsights(array $rows): array
    {
        $insights = [];

        foreach ($rows as $row) {
            if ((float) $row['cost_coverage_percent'] < 100) {
                $insights[] = [
                    'type' => 'warning',
                    'severity' => 'warning',
                    'message' => $row['product_name'] . ' has incomplete cost-at-sale coverage, so profit may be overstated.',
                    'related_report' => 'sales.product_profit',
                    'related_record' => ['product_id' => $row['product_id']],
                    'suggested_action' => 'Use this row with caution for historical periods before the cost-at-sale migration.',
                ];
            }

            if ((float) $row['gross_margin_percent'] < 10 && (float) $row['net_sales'] > 0) {
                $insights[] = [
                    'type' => 'warning',
                    'severity' => 'warning',
                    'message' => $row['product_name'] . ' has a low gross margin of ' . $row['gross_margin_percent'] . '%.',
                    'related_report' => 'sales.product_profit',
                    'related_record' => ['product_id' => $row['product_id']],
                    'suggested_action' => 'Review cost price, discounts, and selling price before restocking heavily.',
                ];
            }

            if ((float) $row['gross_profit'] < 0) {
                $insights[] = [
                    'type' => 'critical',
                    'severity' => 'critical',
                    'message' => $row['product_name'] . ' sold at a loss in this period.',
                    'related_report' => 'sales.product_profit',
                    'related_record' => ['product_id' => $row['product_id']],
                    'suggested_action' => 'Check selling price, discount use, and recorded cost immediately.',
                ];
            }

            if (count($insights) >= 8) {
                break;
            }
        }

        return $insights;
    }

    private function sortProductProfitRows(array &$rows, array $filters): void
    {
        $sortBy = $filters['sort_by'] ?: 'gross_profit';
        $sortDir = $filters['sort_by'] ? $filters['sort_dir'] : 'desc';
        $allowed = [
            'product_name',
            'category_name',
            'quantity_sold',
            'gross_sales',
            'net_sales',
            'total_cost',
            'gross_profit',
            'gross_margin_percent',
            'cost_coverage_percent',
        ];

        if (!in_array($sortBy, $allowed, true)) {
            $sortBy = 'gross_profit';
        }

        usort($rows, static function (array $a, array $b) use ($sortBy, $sortDir): int {
            $left = $a[$sortBy] ?? null;
            $right = $b[$sortBy] ?? null;
            $result = is_numeric($left) && is_numeric($right)
                ? ((float) $left <=> (float) $right)
                : strcmp((string) $left, (string) $right);

            return $sortDir === 'desc' ? -$result : $result;
        });
    }

    private function lineGross(array $line): float
    {
        return (float) ($line['quantity'] ?? 0) * (float) ($line['unitPrice'] ?? 0);
    }

    private function activeOnly($builder): void
    {
        $builder
            ->groupStart()
                ->where('s.saleStatus <>', 'cancelled')
                ->orWhere('s.saleStatus IS NULL', null, false)
            ->groupEnd()
            ->groupStart()
                ->where('r.receiptStatus <>', 'cancelled')
                ->orWhere('r.receiptStatus IS NULL', null, false)
            ->groupEnd();
    }

    private function scopeSales($builder): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where('s.branchId', $branchId);
        }
    }
}
