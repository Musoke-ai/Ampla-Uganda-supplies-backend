<?php

namespace App\Controllers;

use App\Models\Expense;
use App\Controllers\Traits\SecuresInput;
use App\Services\BranchContextService;
use CodeIgniter\RESTful\ResourceController;

class ExpensesController extends ResourceController
{
    use SecuresInput;

    private Expense $expenseModel;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->expenseModel = new Expense();
        $this->branchContext = service('branchContext');
    }

    public function noExpensesData()
    {
        return $this->respond([
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the expense table. Add a new expense and try again.',
        ]);
    }

    public function index()
    {
        $expenses = $this->branchContext
            ->scopeBuilder($this->expenseModel->orderBy('expenseDateCreated', 'DESC'))
            ->findAll();

        if (empty($expenses)) {
            return $this->noExpensesData();
        }

        return $this->respond($expenses);
    }

    public function addExpense()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not POST. Set it to POST and try again.',
            ], 405);
        }

        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond([
                'status' => false,
                'error' => 'MissingBranch',
                'message' => 'Select a current branch before recording an expense.',
            ], 422);
        }

        $data = [
            'branchId' => $branchId,
            'category' => $this->secureText($this->request->getVar('category'), 200),
            'description' => $this->secureText($this->request->getVar('description'), 200),
            'amount' => $this->secureNonNegativeDecimal($this->request->getVar('amount'), 0),
            'givenTo' => $this->secureText($this->request->getVar('givenTo'), 200),
            'remarks' => $this->secureText($this->request->getVar('remarks'), 250),
        ];

        if (!$this->expenseModel->insert($data)) {
            return $this->respond([
                'status' => false,
                'error' => 'ExpenseFail',
                'message' => 'Expense was not added. Check all fields and try again.',
            ], 422);
        }

        $id = $this->expenseModel->getInsertID();
        get_pusher()->trigger('expense-channel', 'expense-created', [
            'expId' => $id,
            'branchId' => $branchId,
            'message' => 'Expense created',
        ]);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Expense successfully added.',
            'data' => ['id' => $id, 'branchId' => $branchId],
        ]);
    }

    public function edit($id = null)
    {
        $data = $this->expenseModel->find($id);

        if (!$data || !$this->recordIsInScope($data)) {
            return $this->noExpensesData();
        }

        return $this->respond($data);
    }

    public function update($id = null)
    {
        $id = trim((string) $this->request->getVar('id'));
        $expense = $id ? $this->expenseModel->find($id) : null;

        if (!$expense) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing expense ID.',
            ], 404);
        }

        if (!$this->recordIsInScope($expense)) {
            return $this->respond([
                'status' => false,
                'error' => 'branchScope',
                'message' => 'This expense is outside your current branch scope.',
            ], 403);
        }

        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'))
            ?? (int) ($expense['branchId'] ?? 0);

        if ($branchId === null || $branchId <= 0) {
            return $this->respond([
                'status' => false,
                'error' => 'MissingBranch',
                'message' => 'Select a current branch before updating this expense.',
            ], 422);
        }

        $expenseUpdateData = [
            'branchId' => $branchId,
            'category' => $this->secureText($this->request->getVar('category'), 200),
            'description' => $this->secureText($this->request->getVar('description'), 200),
            'amount' => $this->secureNonNegativeDecimal($this->request->getVar('amount'), 0),
            'givenTo' => $this->secureText($this->request->getVar('givenTo'), 200),
            'remarks' => $this->secureText($this->request->getVar('remarks'), 250),
        ];

        if (!$this->expenseModel->update($id, $expenseUpdateData)) {
            return $this->respond([
                'status' => false,
                'error' => 'expenseUpdateFail',
                'message' => 'Expense update failed. Please try again later.',
            ], 422);
        }

        get_pusher()->trigger('expense-channel', 'expense-updated', [
            'expId' => $id,
            'branchId' => $branchId,
            'message' => 'Expense updated',
        ]);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Expense has been updated.',
        ]);
    }

    public function delete($id = null)
    {
        $id = trim((string) $this->request->getVar('id'));
        $expense = $id ? $this->expenseModel->find($id) : null;

        if (!$expense) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing expense ID.',
            ], 404);
        }

        if (!$this->recordIsInScope($expense)) {
            return $this->respond([
                'status' => false,
                'error' => 'branchScope',
                'message' => 'This expense is outside your current branch scope.',
            ], 403);
        }

        if (!$this->expenseModel->delete($id)) {
            return $this->respond([
                'status' => false,
                'error' => 'deleteFail',
                'message' => 'Expense has not been deleted. Please try again later.',
            ], 422);
        }

        get_pusher()->trigger('expense-channel', 'expense-deleted', [
            'expId' => $id,
            'branchId' => $expense['branchId'] ?? null,
            'message' => 'Expense deleted',
        ]);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Selected expense has been deleted.',
        ]);
    }

    private function recordIsInScope(array $record): bool
    {
        $effectiveBranchId = $this->branchContext->getEffectiveBranchId();

        if ($effectiveBranchId === null) {
            return $this->branchContext->canUserSwitchBranches();
        }

        return (int) ($record['branchId'] ?? 0) === $effectiveBranchId;
    }
}
