<?php

namespace App\Services\Reports;

use CodeIgniter\Database\BaseConnection;

class ExpenseReportService
{
    private BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    public function build(array $filters): array
    {
        return [
            'summary' => $this->summary($filters),
            'byCategory' => $this->byCategory($filters),
            'trend' => $this->trend($filters),
            'accuracyNotes' => [
                'Expense reports use expenses.expenseDateCreated.',
                'Expenses are not branch-scoped until expenses.branchId is added.',
                'Production expense reporting needs expenses.production_batch_id or production_expenses.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        $builder = $this->db->table('expenses e')
            ->select('e.id, e.expenseDateCreated AS date, e.category, e.description, e.amount, e.givenTo, e.remarks')
            ->where('e.expenseDateCreated >=', $filters['from'])
            ->where('e.expenseDateCreated <=', $filters['to'])
            ->orderBy('e.expenseDateCreated', 'DESC');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('e.category', $filters['search'])
                ->orLike('e.description', $filters['search'])
                ->orLike('e.givenTo', $filters['search'])
                ->groupEnd();
        }

        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['id', 'date', 'category', 'description', 'amount', 'givenTo', 'remarks'],
            'rows' => $builder->limit($filters['perPage'], $offset)->get()->getResultArray(),
        ];
    }

    private function summary(array $filters): array
    {
        $row = $this->db->table('expenses e')
            ->select('COUNT(*) AS expenseCount, SUM(e.amount) AS totalExpenses, AVG(e.amount) AS averageExpense', false)
            ->where('e.expenseDateCreated >=', $filters['from'])
            ->where('e.expenseDateCreated <=', $filters['to'])
            ->get()
            ->getRowArray() ?? [];

        return [
            'expenseCount' => (int) ($row['expenseCount'] ?? 0),
            'totalExpenses' => (float) ($row['totalExpenses'] ?? 0),
            'averageExpense' => round((float) ($row['averageExpense'] ?? 0), 2),
        ];
    }

    private function byCategory(array $filters): array
    {
        return $this->db->table('expenses e')
            ->select("COALESCE(NULLIF(e.category, ''), 'Uncategorized') AS label, SUM(e.amount) AS value, COUNT(*) AS count", false)
            ->where('e.expenseDateCreated >=', $filters['from'])
            ->where('e.expenseDateCreated <=', $filters['to'])
            ->groupBy('e.category')
            ->orderBy('value', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function trend(array $filters): array
    {
        return $this->db->table('expenses e')
            ->select('DATE(e.expenseDateCreated) AS label, SUM(e.amount) AS value', false)
            ->where('e.expenseDateCreated >=', $filters['from'])
            ->where('e.expenseDateCreated <=', $filters['to'])
            ->groupBy('DATE(e.expenseDateCreated)')
            ->orderBy('label', 'ASC')
            ->get()
            ->getResultArray();
    }
}
