<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class CustomerReportService
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
        $debts = $this->debts($filters);
        $totalDebt = array_sum(array_column($debts, 'outstandingBalance'));

        return [
            'summary' => [
                'customersWithDebt' => count($debts),
                'totalCustomerDebt' => $totalDebt,
            ],
            'topDebtors' => array_slice($debts, 0, 10),
            'accuracyNotes' => [
                'Customer debt uses indebt.totalAmount - indebt.initialDeposit.',
                'Expected payment dates use indebt.endDate from the debt sale.',
                'A customer_ledger table is still required for full reconciliation and aging.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return [
            'columns' => ['customerId', 'customer', 'contact', 'totalDebt', 'paid', 'outstandingBalance', 'nextDueDate', 'latestDueDate', 'overdueBalance'],
            'rows' => $this->debts($filters, true),
        ];
    }

    private function debts(array $filters, bool $paged = false): array
    {
        $builder = $this->db->table('indebt d')
            ->select("
                d.custId AS customerId,
                c.custName AS customer,
                c.custContact AS contact,
                SUM(d.totalAmount) AS totalDebt,
                SUM(d.initialDeposit) AS paid,
                SUM(d.totalAmount - d.initialDeposit) AS outstandingBalance,
                MIN(d.endDate) AS nextDueDate,
                MAX(d.endDate) AS latestDueDate,
                SUM(
                    CASE
                        WHEN d.endDate IS NOT NULL
                            AND d.endDate < CURRENT_DATE()
                        THEN d.totalAmount - d.initialDeposit
                        ELSE 0
                    END
                ) AS overdueBalance
            ", false)
            ->join('customers c', 'c.custId = d.custId', 'left')
            ->groupBy('d.custId, c.custName, c.custContact')
            ->having('outstandingBalance > 0', null, false)
            ->orderBy('outstandingBalance', 'DESC');

        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where('d.branchId', $branchId);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('c.custName', $filters['search'])
                ->orLike('c.custContact', $filters['search'])
                ->groupEnd();
        }

        if ($paged) {
            $offset = ($filters['page'] - 1) * $filters['perPage'];
            $builder->limit($filters['perPage'], $offset);
        }

        return $builder->get()->getResultArray();
    }
}
