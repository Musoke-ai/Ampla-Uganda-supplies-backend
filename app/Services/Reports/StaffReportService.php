<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class StaffReportService
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
        $employees = $this->employeeRows($filters);
        $payments = $this->paymentRows($filters);

        return [
            'summary' => [
                'employeeCount' => count($employees),
                'activeEmployees' => count(array_filter($employees, static fn ($row): bool => in_array(strtolower((string) $row['status']), ['1', 'active', 'enabled'], true))),
                'monthlySalaryEstimate' => round(array_sum(array_column($employees, 'salary')), 2),
                'workerPaymentEntries' => count($payments),
                'workerPayments' => round(array_sum(array_column($payments, 'amount_paid')), 2),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column($this->paymentsByRole($payments), 'label'),
                'datasets' => [
                    [
                        'label' => 'Worker Payments',
                        'data' => array_map(static fn ($row) => (float) $row['value'], $this->paymentsByRole($payments)),
                    ],
                ],
            ],
            'table' => $this->paginate($payments, $filters),
            'employees' => $employees,
            'insights' => $this->insights($employees),
            'accuracyNotes' => [
                'Staff payments use daily_employees_register joined to employees.',
                'Daily worker records are not yet linked to production batches, orders, or finished output.',
                'Payroll-grade reporting needs attendance, approval, and payroll period structures.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return $this->paginate($this->paymentRows($filters), $filters);
    }

    private function employeeRows(array $filters): array
    {
        $builder = $this->db->table('employees e')
            ->select('e.empID AS employee_id, e.branchId AS branch_id, e.empName AS employee, e.empRole AS role, e.empSalary AS salary, e.empStatus AS status, e.empContact AS contact, e.startDate, e.endDate', false)
            ->orderBy('e.empName', 'ASC');

        $this->scope($builder, 'e.branchId', 'employees');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('e.empName', $filters['search'])
                ->orLike('e.empRole', $filters['search'])
                ->orLike('e.empContact', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            return [
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'employee' => $row['employee'] ?: 'Unknown employee',
                'role' => $row['role'] ?: 'Unspecified',
                'salary' => (float) ($row['salary'] ?? 0),
                'status' => $row['status'] ?: 'Unspecified',
                'contact' => $row['contact'] ?? '',
                'startDate' => $row['startDate'] ?? null,
                'endDate' => $row['endDate'] ?? null,
            ];
        }, $builder->get()->getResultArray());
    }

    private function paymentRows(array $filters): array
    {
        if (!$this->db->tableExists('daily_employees_register')) {
            return [];
        }

        $builder = $this->db->table('daily_employees_register der')
            ->select('der.ID AS payment_id, der.dailyEmployeeDateCreated AS date, der.empID AS employee_id, e.empName AS employee, der.role, der.payment, der.amountPaid AS amount_paid', false)
            ->join('employees e', 'e.empID = der.empID', 'left')
            ->where('der.dailyEmployeeDateCreated >=', $filters['from'])
            ->where('der.dailyEmployeeDateCreated <=', $filters['to'])
            ->orderBy('der.dailyEmployeeDateCreated', 'DESC');

        $this->scope($builder, 'e.branchId', 'employees');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('e.empName', $filters['search'])
                ->orLike('der.role', $filters['search'])
                ->orLike('der.payment', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            return [
                'payment_id' => (int) ($row['payment_id'] ?? 0),
                'date' => $row['date'] ?? null,
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'employee' => $row['employee'] ?: 'Unknown employee',
                'role' => $row['role'] ?: 'Unspecified',
                'payment' => $row['payment'] ?: 'Unspecified',
                'amount_paid' => round((float) ($row['amount_paid'] ?? 0), 2),
            ];
        }, $builder->get()->getResultArray());
    }

    private function paymentsByRole(array $payments): array
    {
        $roles = [];

        foreach ($payments as $payment) {
            $role = $payment['role'] ?: 'Unspecified';
            $roles[$role] = ($roles[$role] ?? 0) + (float) ($payment['amount_paid'] ?? 0);
        }

        arsort($roles);

        return array_map(
            static fn ($role, $value): array => ['label' => $role, 'value' => round((float) $value, 2)],
            array_keys($roles),
            array_values($roles)
        );
    }

    private function paginate(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['payment_id', 'date', 'employee', 'role', 'payment', 'amount_paid'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function insights(array $employees): array
    {
        $insights = [];

        foreach ($employees as $employee) {
            if (strtolower((string) $employee['status']) !== 'active') {
                $insights[] = [
                    'severity' => 'info',
                    'message' => $employee['employee'] . ' is marked as ' . $employee['status'] . '.',
                    'suggested_action' => 'Review staff status before planning payroll or production schedules.',
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
