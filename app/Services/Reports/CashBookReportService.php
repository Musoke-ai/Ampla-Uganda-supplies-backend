<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class CashBookReportService
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
        $salesRows = $this->salesReceipts($filters);
        $expenseRows = $this->expensePayments($filters);
        $rows = array_merge($salesRows, $expenseRows);

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));

        $runningNet = 0.0;
        foreach ($rows as &$row) {
            $runningNet += (float) ($row['cash_in'] ?? 0) - (float) ($row['cash_out'] ?? 0);
            $row['running_net'] = round($runningNet, 2);
        }
        unset($row);

        $cashIn = array_sum(array_column($rows, 'cash_in'));
        $cashOut = array_sum(array_column($rows, 'cash_out'));
        $creditOutstanding = array_sum(array_column($rows, 'credit_outstanding'));

        return [
            'summary' => [
                'cashIn' => round($cashIn, 2),
                'cashOut' => round($cashOut, 2),
                'netCashMovement' => round($cashIn - $cashOut, 2),
                'creditOutstanding' => round($creditOutstanding, 2),
                'salesReceiptCount' => count($salesRows),
                'expensePaymentCount' => count($expenseRows),
                'transactionCount' => count($rows),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => ['Cash In', 'Cash Out', 'Net Cash', 'Credit Outstanding'],
                'datasets' => [
                    [
                        'label' => 'UGX',
                        'data' => [
                            round($cashIn, 2),
                            round($cashOut, 2),
                            round($cashIn - $cashOut, 2),
                            round($creditOutstanding, 2),
                        ],
                    ],
                ],
            ],
            'table' => $this->paginateRows($rows, $filters),
            'insights' => $this->insights($cashIn, $cashOut, $creditOutstanding),
            'accuracyNotes' => [
                'Cash book uses receipt.amountPaid as cash in and expenses.amount as cash out.',
                'This is a daily cash movement report, not a full bank reconciliation ledger.',
                'Opening and closing till balances need a cash drawer/bank account ledger to be exact.',
                'Cancelled receipts are excluded when receiptStatus is cancelled.',
            ],
        ];
    }

    private function salesReceipts(array $filters): array
    {
        $builder = $this->db->table('receipt r')
            ->select("r.srDateCreated AS date, r.SR_ID AS source_id, r.timeStamp AS reference, r.paymentMethod AS payment_method, COALESCE(r.amountPaid, 0) AS cash_in, 0 AS cash_out, COALESCE(r.dueAmount, 0) AS credit_outstanding, COALESCE(r.discount, 0) AS discount, 'Sale Receipt' AS type, CONCAT('Receipt #', r.SR_ID) AS description", false)
            ->where('r.srDateCreated >=', $filters['from'])
            ->where('r.srDateCreated <=', $filters['to'])
            ->groupStart()
                ->where('r.receiptStatus <>', 'cancelled')
                ->orWhere('r.receiptStatus IS NULL', null, false)
            ->groupEnd();

        $this->applyBranchScope($builder, 'r.branchId', $filters);

        if (!empty($filters['payment_method'])) {
            $builder->where('r.paymentMethod', $filters['payment_method']);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('r.SR_ID', $filters['search'])
                ->orLike('r.timeStamp', $filters['search'])
                ->orLike('r.paymentMethod', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            return [
                'date' => $row['date'] ?? null,
                'type' => $row['type'] ?? 'Sale Receipt',
                'reference' => $row['reference'] ?: ('Receipt #' . ($row['source_id'] ?? '')),
                'description' => $row['description'] ?? '',
                'payment_method' => $row['payment_method'] ?: 'Cash',
                'cash_in' => round((float) ($row['cash_in'] ?? 0), 2),
                'cash_out' => 0.0,
                'credit_outstanding' => round((float) ($row['credit_outstanding'] ?? 0), 2),
                'discount' => round((float) ($row['discount'] ?? 0), 2),
            ];
        }, $builder->get()->getResultArray());
    }

    private function expensePayments(array $filters): array
    {
        $builder = $this->db->table('expenses e')
            ->select("e.expenseDateCreated AS date, e.id AS source_id, CONCAT('EXP-', e.id) AS reference, e.category, e.description, e.givenTo, 0 AS cash_in, COALESCE(e.amount, 0) AS cash_out, 0 AS credit_outstanding, 0 AS discount, 'Expense Payment' AS type", false)
            ->where('e.expenseDateCreated >=', $filters['from'])
            ->where('e.expenseDateCreated <=', $filters['to']);

        if ($this->db->fieldExists('branchId', 'expenses')) {
            $this->applyBranchScope($builder, 'e.branchId', $filters);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('e.category', $filters['search'])
                ->orLike('e.description', $filters['search'])
                ->orLike('e.givenTo', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            $description = trim((string) ($row['description'] ?? ''));
            $givenTo = trim((string) ($row['givenTo'] ?? ''));

            return [
                'date' => $row['date'] ?? null,
                'type' => $row['type'] ?? 'Expense Payment',
                'reference' => $row['reference'] ?? '',
                'description' => $description !== '' ? $description : ($row['category'] ?? 'Expense'),
                'payment_method' => $givenTo !== '' ? 'Paid to: ' . $givenTo : 'Cash',
                'cash_in' => 0.0,
                'cash_out' => round((float) ($row['cash_out'] ?? 0), 2),
                'credit_outstanding' => 0.0,
                'discount' => 0.0,
            ];
        }, $builder->get()->getResultArray());
    }

    private function paginateRows(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => [
                'date',
                'type',
                'reference',
                'description',
                'payment_method',
                'cash_in',
                'cash_out',
                'credit_outstanding',
                'discount',
                'running_net',
            ],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function insights(float $cashIn, float $cashOut, float $creditOutstanding): array
    {
        $items = [];

        if ($cashOut > $cashIn && $cashIn > 0) {
            $items[] = [
                'severity' => 'warning',
                'message' => 'Cash out is higher than cash received for the selected period.',
                'suggested_action' => 'Review expense approvals and confirm all sales receipts were posted.',
            ];
        }

        if ($creditOutstanding > 0) {
            $items[] = [
                'severity' => 'info',
                'message' => 'The period includes outstanding customer credit of ' . number_format($creditOutstanding) . '.',
                'suggested_action' => 'Use the paid-vs-credit report for follow-up collections.',
            ];
        }

        return $items;
    }

    private function applyBranchScope($builder, string $column, array $filters): void
    {
        $effectiveBranchId = $this->branchContext->getEffectiveBranchId();
        $branchId = $effectiveBranchId ?? ($filters['branch_id'] ?? null);

        if ($branchId !== null) {
            $builder->where($column, $branchId);
        }
    }
}
