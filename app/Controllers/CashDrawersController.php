<?php

namespace App\Controllers;

use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
use App\Models\Expense;
use App\Services\BranchContextService;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class CashDrawersController extends ResourceController
{
    private CashDrawer $drawerModel;
    private CashDrawerMovement $movementModel;
    private Expense $expenseModel;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->drawerModel = new CashDrawer();
        $this->movementModel = new CashDrawerMovement();
        $this->expenseModel = new Expense();
        $this->branchContext = new BranchContextService();
    }

    public function active()
    {
        $branchId = $this->resolveBranchId($this->request->getGet('branchId'));
        if ($branchId === null) {
            return $this->fail('Select a branch to view the cash drawer.', 422);
        }

        $drawer = $this->activeDrawer($branchId);

        return $this->respond([
            'status' => true,
            'data' => [
                'drawer' => $drawer ? $this->formatDrawer($drawer) : null,
            ],
        ]);
    }

    public function history()
    {
        $branchId = $this->resolveBranchId($this->request->getGet('branchId'));
        if ($branchId === null) {
            return $this->fail('Select a branch to view drawer history.', 422);
        }

        $drawers = $this->drawerModel
            ->where('branchId', $branchId)
            ->orderBy('openedAt', 'DESC')
            ->findAll(20);

        return $this->respond([
            'status' => true,
            'data' => array_map(fn($drawer) => $this->formatDrawer($drawer, false), $drawers),
        ]);
    }

    public function open()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $branchId = $this->resolveBranchId($payload['branchId'] ?? null);
        if ($branchId === null) {
            return $this->fail('Select a branch before opening the cash drawer.', 422);
        }

        if ($this->activeDrawer($branchId)) {
            return $this->fail('This branch already has an open cash drawer.', 409);
        }

        $userId = (int) auth()->id();
        $openingFloat = $this->money($payload['openingFloat'] ?? 0);
        $note = $this->cleanNote($payload['note'] ?? '');

        $drawerId = $this->drawerModel->insert([
            'branchId' => $branchId,
            'openedBy' => $userId,
            'status' => 'open',
            'openingFloat' => $openingFloat,
            'expectedCash' => $openingFloat,
            'openingNote' => $note,
        ], true);

        if (!$drawerId) {
            return $this->failServerError('Cash drawer could not be opened.');
        }

        $this->movementModel->insert([
            'drawerId' => $drawerId,
            'branchId' => $branchId,
            'userId' => $userId,
            'movementType' => 'opening_float',
            'amount' => $openingFloat,
            'reason' => $note ?: 'Opening float',
        ]);

        return $this->respond([
            'status' => true,
            'message' => 'Cash drawer opened successfully.',
            'data' => [
                'drawer' => $this->formatDrawer($this->drawerModel->find($drawerId)),
            ],
        ]);
    }

    public function movement()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $drawerId = (int) ($payload['drawerId'] ?? 0);
        $type = $payload['movementType'] ?? '';
        $allowed = ['cash_in', 'cash_out', 'adjustment'];

        if (!in_array($type, $allowed, true)) {
            return $this->fail('Movement type must be cash_in, cash_out, or adjustment.', 422);
        }

        $drawer = $this->drawerModel->find($drawerId);
        if (!$drawer || ($drawer['status'] ?? '') !== 'open') {
            return $this->fail('Select an open cash drawer before recording movement.', 422);
        }

        $branchId = $this->resolveBranchId($drawer['branchId'] ?? null);
        if ($branchId === null || (int) $drawer['branchId'] !== $branchId) {
            return $this->failForbidden('You cannot update this branch cash drawer.');
        }

        $amount = $this->money($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.', 422);
        }

        $signedAmount = $type === 'cash_out' ? -$amount : $amount;
        $reason = $this->cleanNote($payload['reason'] ?? '');
        if ($reason === '') {
            return $this->fail('A reason is required for drawer movements.', 422);
        }

        $this->movementModel->insert([
            'drawerId' => $drawerId,
            'branchId' => (int) $drawer['branchId'],
            'userId' => (int) auth()->id(),
            'movementType' => $type,
            'amount' => $signedAmount,
            'reason' => $reason,
        ]);

        $this->recalculateDrawer($drawerId);

        return $this->respond([
            'status' => true,
            'message' => 'Cash drawer movement recorded.',
            'data' => [
                'drawer' => $this->formatDrawer($this->drawerModel->find($drawerId)),
            ],
        ]);
    }

    public function expense()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $drawerId = (int) ($payload['drawerId'] ?? 0);
        $drawer = $this->drawerModel->find($drawerId);

        if (!$drawer || ($drawer['status'] ?? '') !== 'open') {
            return $this->fail('Select an open cash drawer before recording an expense.', 422);
        }

        $branchId = $this->resolveBranchId($drawer['branchId'] ?? null);
        if ($branchId === null || (int) $drawer['branchId'] !== $branchId) {
            return $this->failForbidden('You cannot update this branch cash drawer.');
        }

        $amount = $this->money($payload['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Expense amount must be greater than zero.', 422);
        }

        $category = $this->cleanText($payload['category'] ?? 'POS Expense');
        $description = $this->cleanText($payload['description'] ?? '');
        $givenTo = $this->cleanText($payload['givenTo'] ?? '');
        $remarks = $this->cleanText($payload['remarks'] ?? '', 250);

        if ($category === '' || $description === '') {
            return $this->fail('Expense category and description are required.', 422);
        }

        $db = Database::connect();
        $db->transBegin();

        $expenseId = $this->expenseModel->insert([
            'branchId' => $branchId,
            'category' => $category,
            'description' => $description,
            'amount' => $amount,
            'givenTo' => $givenTo,
            'remarks' => $remarks,
        ], true);

        if (!$expenseId) {
            $db->transRollback();
            return $this->fail('Expense could not be recorded. Check all fields and try again.', 422);
        }

        $reason = 'Expense: ' . $category . ' - ' . $description;
        if ($givenTo !== '') {
            $reason .= ' paid to ' . $givenTo;
        }

        $movementSaved = $this->movementModel->insert([
            'drawerId' => $drawerId,
            'branchId' => $branchId,
            'userId' => (int) auth()->id(),
            'movementType' => 'cash_out',
            'amount' => -$amount,
            'reason' => $this->cleanNote($reason),
        ]);

        if (!$movementSaved) {
            $db->transRollback();
            return $this->failServerError('Cash drawer movement could not be recorded for this expense.');
        }

        $this->recalculateDrawer($drawerId);
        $db->transCommit();

        get_pusher()->trigger('expense-channel', 'expense-created', [
            'expId' => $expenseId,
            'branchId' => $branchId,
            'message' => 'Expense created from cash drawer',
        ]);

        return $this->respond([
            'status' => true,
            'message' => 'Expense and cash drawer movement recorded.',
            'data' => [
                'expense' => ['id' => (int) $expenseId, 'branchId' => $branchId],
                'drawer' => $this->formatDrawer($this->drawerModel->find($drawerId)),
            ],
        ]);
    }

    public function close()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $drawerId = (int) ($payload['drawerId'] ?? 0);
        $drawer = $this->drawerModel->find($drawerId);

        if (!$drawer || ($drawer['status'] ?? '') !== 'open') {
            return $this->fail('Select an open cash drawer before closing.', 422);
        }

        $branchId = $this->resolveBranchId($drawer['branchId'] ?? null);
        if ($branchId === null || (int) $drawer['branchId'] !== $branchId) {
            return $this->failForbidden('You cannot close this branch cash drawer.');
        }

        $this->recalculateDrawer($drawerId);
        $drawer = $this->drawerModel->find($drawerId);
        $countedCash = $this->money($payload['countedCash'] ?? 0);
        $expectedCash = (float) ($drawer['expectedCash'] ?? 0);
        $variance = round($countedCash - $expectedCash, 2);

        $this->drawerModel->update($drawerId, [
            'closedBy' => (int) auth()->id(),
            'status' => 'closed',
            'countedCash' => $countedCash,
            'variance' => $variance,
            'closingNote' => $this->cleanNote($payload['note'] ?? ''),
            'closedAt' => date('Y-m-d H:i:s'),
        ]);

        return $this->respond([
            'status' => true,
            'message' => 'Cash drawer closed successfully.',
            'data' => [
                'drawer' => $this->formatDrawer($this->drawerModel->find($drawerId)),
            ],
        ]);
    }

    public function recordSale(int $branchId, int $receiptId, int $userId, float $amount): ?array
    {
        $drawer = $this->activeDrawer($branchId);
        if (!$drawer || $amount <= 0) {
            return null;
        }

        $this->movementModel->insert([
            'drawerId' => (int) $drawer['drawerId'],
            'branchId' => $branchId,
            'userId' => $userId,
            'receiptId' => $receiptId,
            'movementType' => 'sale',
            'amount' => round($amount, 2),
            'reason' => 'Cash sale receipt #' . $receiptId,
        ]);

        $this->recalculateDrawer((int) $drawer['drawerId']);

        return $this->formatDrawer($this->drawerModel->find($drawer['drawerId']));
    }

    private function resolveBranchId($branchId): ?int
    {
        return $this->branchContext->resolveWritableBranchId($branchId);
    }

    private function activeDrawer(int $branchId): ?array
    {
        return $this->drawerModel
            ->where('branchId', $branchId)
            ->where('status', 'open')
            ->orderBy('openedAt', 'DESC')
            ->first();
    }

    private function movements(int $drawerId): array
    {
        return $this->movementModel
            ->where('drawerId', $drawerId)
            ->orderBy('movementDateCreated', 'DESC')
            ->findAll(80);
    }

    private function recalculateDrawer(int $drawerId): void
    {
        $db = Database::connect();
        $row = $db->table('cash_drawer_movements')
            ->select("
                SUM(CASE WHEN movementType = 'sale' THEN amount ELSE 0 END) AS cashSalesTotal,
                SUM(CASE WHEN movementType IN ('cash_in', 'adjustment') AND amount > 0 THEN amount ELSE 0 END) AS cashInTotal,
                SUM(CASE WHEN movementType = 'cash_out' THEN ABS(amount) ELSE 0 END) AS cashOutTotal,
                SUM(amount) AS expectedCash
            ", false)
            ->where('drawerId', $drawerId)
            ->get()
            ->getRowArray() ?? [];

        $this->drawerModel->update($drawerId, [
            'cashSalesTotal' => round((float) ($row['cashSalesTotal'] ?? 0), 2),
            'cashInTotal' => round((float) ($row['cashInTotal'] ?? 0), 2),
            'cashOutTotal' => round((float) ($row['cashOutTotal'] ?? 0), 2),
            'expectedCash' => round((float) ($row['expectedCash'] ?? 0), 2),
        ]);
    }

    private function formatDrawer(?array $drawer, bool $includeMovements = true): ?array
    {
        if (!$drawer) {
            return null;
        }

        $drawer['drawerId'] = (int) $drawer['drawerId'];
        $drawer['branchId'] = (int) $drawer['branchId'];
        $drawer['openedBy'] = (int) $drawer['openedBy'];
        $drawer['closedBy'] = isset($drawer['closedBy']) ? (int) $drawer['closedBy'] : null;

        foreach (['openingFloat', 'cashSalesTotal', 'cashInTotal', 'cashOutTotal', 'expectedCash', 'countedCash', 'variance'] as $key) {
            $drawer[$key] = isset($drawer[$key]) ? round((float) $drawer[$key], 2) : null;
        }

        if ($includeMovements) {
            $drawer['movements'] = array_map(function ($movement) {
                $movement['movementId'] = (int) $movement['movementId'];
                $movement['drawerId'] = (int) $movement['drawerId'];
                $movement['branchId'] = (int) $movement['branchId'];
                $movement['userId'] = (int) $movement['userId'];
                $movement['receiptId'] = isset($movement['receiptId']) ? (int) $movement['receiptId'] : null;
                $movement['amount'] = round((float) $movement['amount'], 2);
                return $movement;
            }, $this->movements((int) $drawer['drawerId']));
            $drawer['paymentSummary'] = $this->paymentSummary($drawer);
        }

        return $drawer;
    }

    private function paymentSummary(array $drawer): array
    {
        $branchId = (int) ($drawer['branchId'] ?? 0);
        $openedAt = $drawer['openedAt'] ?? null;
        $closedAt = $drawer['closedAt'] ?? date('Y-m-d H:i:s');

        if ($branchId === 0 || !$openedAt) {
            return [
                'cash' => 0,
                'cashless' => 0,
                'creditDue' => 0,
                'totalReceipts' => 0,
                'methods' => [],
            ];
        }

        $rows = Database::connect()->table('receipt')
            ->select('paymentMethod, COALESCE(amountPaid, 0) AS amountPaid, COALESCE(dueAmount, 0) AS dueAmount', false)
            ->where('branchId', $branchId)
            ->where('srDateCreated >=', $openedAt)
            ->where('srDateCreated <=', $closedAt)
            ->groupStart()
                ->where('receiptStatus <>', 'cancelled')
                ->orWhere('receiptStatus IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $summary = [
            'cash' => 0,
            'cashless' => 0,
            'creditDue' => 0,
            'totalReceipts' => count($rows),
            'methods' => [],
        ];

        foreach ($rows as $row) {
            $method = trim((string) ($row['paymentMethod'] ?? 'Cash')) ?: 'Cash';
            $paid = round((float) ($row['amountPaid'] ?? 0), 2);
            $due = round((float) ($row['dueAmount'] ?? 0), 2);
            $isCash = str_contains(strtolower($method), 'cash');

            $summary[$isCash ? 'cash' : 'cashless'] += $paid;
            $summary['creditDue'] += $due;
            $summary['methods'][$method] = round(($summary['methods'][$method] ?? 0) + $paid, 2);
        }

        $summary['cash'] = round((float) $summary['cash'], 2);
        $summary['cashless'] = round((float) $summary['cashless'], 2);
        $summary['creditDue'] = round((float) $summary['creditDue'], 2);

        return $summary;
    }

    private function money($value): float
    {
        $number = is_numeric($value) ? (float) $value : 0;
        return round(max(0, $number), 2);
    }

    private function cleanNote($value): string
    {
        return mb_substr(trim((string) $value), 0, 500);
    }

    private function cleanText($value, int $limit = 200): string
    {
        return mb_substr(trim((string) $value), 0, $limit);
    }
}
