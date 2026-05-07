<?php

namespace App\Services;

use App\Models\CopilotActionDraft;
use App\Models\Inventory;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;

class CopilotDraftActionService
{
    private CopilotActionDraft $draftModel;
    private Inventory $inventoryModel;
    private InventoryService $inventoryService;
    private CustomerService $customerService;
    private BranchContextService $branchContext;
    private StockLedgerService $stockLedger;
    private BaseConnection $db;

    public function __construct()
    {
        $this->draftModel = new CopilotActionDraft();
        $this->inventoryModel = new Inventory();
        $this->inventoryService = new InventoryService();
        $this->customerService = new CustomerService();
        $this->branchContext = service('branchContext');
        $this->stockLedger = new StockLedgerService();
        $this->db = db_connect();
    }

    public function draftReorderList(int $limit = 25): array
    {
        $items = $this->inventoryService->getReorderSuggestions($limit);

        if (empty($items)) {
            throw new InvalidArgumentException('No products currently need reorder suggestions.');
        }

        $payload = [
            'items' => array_map(static function (array $item): array {
                return [
                    'product_id' => (int) ($item['itemId'] ?? 0),
                    'product' => $item['itemName'] ?? null,
                    'sku' => $item['itemSku'] ?? null,
                    'supplier' => $item['itemSupplier'] ?? null,
                    'current_quantity' => (float) ($item['itemQuantity'] ?? 0),
                    'reorder_level' => (float) ($item['reorder_level'] ?? 0),
                    'suggested_quantity' => (float) ($item['suggested_quantity'] ?? 0),
                ];
            }, $items),
        ];

        return $this->storeDraft(
            'reorder_list',
            'Draft reorder list',
            'Prepared a reorder list for ' . count($items) . ' low-stock product(s).',
            $payload,
            'draft_reorder_list'
        );
    }

    public function draftStockAdjustment(string $productName, float $targetQuantity, string $reason = ''): array
    {
        $product = $this->firstProduct($productName);
        $currentQuantity = (float) ($product['itemQuantity'] ?? 0);

        $payload = [
            'product_id' => (int) ($product['itemId'] ?? 0),
            'product' => $product['itemName'] ?? $productName,
            'sku' => $product['itemSku'] ?? null,
            'current_quantity' => $currentQuantity,
            'target_quantity' => $targetQuantity,
            'quantity_difference' => $targetQuantity - $currentQuantity,
            'reason' => $reason,
            'note' => 'This is only a draft. Inventory is not changed until a future confirmation action executes it.',
        ];

        return $this->storeDraft(
            'stock_adjustment',
            'Draft stock adjustment for ' . ($payload['product'] ?? $productName),
            'Prepared a stock adjustment from ' . $currentQuantity . ' to ' . $targetQuantity . '.',
            $payload,
            'draft_stock_adjustment'
        );
    }

    public function draftInvoice(string $customerName, string $productName, float $quantity): array
    {
        $product = $this->firstProduct($productName);
        $customers = $this->customerService->searchCustomers($customerName);
        $customer = $customers[0] ?? null;

        if (!$customer) {
            throw new InvalidArgumentException('No matching customer was found for the draft invoice.');
        }

        $unitPrice = (float) ($product['itemLeastPrice'] ?? 0);
        $lineTotal = $quantity * $unitPrice;
        $availableQuantity = (float) ($product['itemQuantity'] ?? 0);

        $payload = [
            'customer' => [
                'customer_id' => (int) ($customer['custId'] ?? 0),
                'name' => $customer['custName'] ?? $customerName,
                'contact' => $customer['custContact'] ?? null,
                'email' => $customer['custEmail'] ?? null,
            ],
            'items' => [
                [
                    'product_id' => (int) ($product['itemId'] ?? 0),
                    'product' => $product['itemName'] ?? $productName,
                    'sku' => $product['itemSku'] ?? null,
                    'quantity' => $quantity,
                    'available_quantity' => $availableQuantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'stock_warning' => $quantity > $availableQuantity ? 'Requested quantity is above current stock.' : null,
                ],
            ],
            'total' => $lineTotal,
            'currency' => 'UGX',
            'note' => 'This is only a draft. No invoice, sale, or inventory movement has been posted.',
        ];

        return $this->storeDraft(
            'invoice',
            'Draft invoice for ' . ($customer['custName'] ?? $customerName),
            'Prepared a draft invoice with 1 line item totaling UGX ' . number_format($lineTotal),
            $payload,
            'draft_invoice'
        );
    }

    public function draftCustomerFollowUp(string $customerName, string $message = ''): array
    {
        $customers = $this->customerService->searchCustomers($customerName);
        $customer = $customers[0] ?? null;

        if (!$customer) {
            throw new InvalidArgumentException('No matching customer was found for the follow-up draft.');
        }

        $payload = [
            'customer_id' => (int) ($customer['custId'] ?? 0),
            'customer' => $customer['custName'] ?? $customerName,
            'contact' => $customer['custContact'] ?? null,
            'email' => $customer['custEmail'] ?? null,
            'message' => $message !== '' ? $message : 'Please follow up with this customer about their account.',
            'note' => 'This is only a draft follow-up note. No SMS, email, or notification has been sent.',
        ];

        return $this->storeDraft(
            'customer_follow_up',
            'Draft customer follow-up for ' . ($payload['customer'] ?? $customerName),
            'Prepared a customer follow-up draft.',
            $payload,
            'draft_customer_follow_up'
        );
    }

    public function getDraft(string $draftKey): array
    {
        $draft = $this->findDraft($draftKey);

        return $this->formatDraft($draft);
    }

    public function listDrafts(string $status = 'active', int $limit = 20): array
    {
        if (!$this->db->tableExists('copilot_action_drafts')) {
            return [];
        }

        $status = strtolower(trim($status));
        $limit = max(1, min($limit, 50));
        $builder = $this->draftModel->orderBy('createdAt', 'DESC')->limit($limit);
        $this->scopeDraftBuilder($builder);

        if ($status === 'active') {
            $builder->whereIn('status', ['draft', 'confirmed']);
        } elseif ($status !== '' && $status !== 'all') {
            $builder->where('status', $status);
        }

        return array_map(
            fn (array $draft): array => $this->formatDraft($draft),
            $builder->findAll()
        );
    }

    public function confirmDraft(string $draftKey, string $decisionNote = ''): array
    {
        $draft = $this->findDraft($draftKey);
        $this->assertDraftCanBeDecided($draft);

        $this->draftModel->update($draft['id'], [
            'status' => 'confirmed',
            'decisionBy' => auth()->id() ? (int) auth()->id() : null,
            'decisionNote' => $decisionNote !== '' ? $decisionNote : 'Confirmed from Ampla Copilot.',
            'decisionAt' => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->findDraft($draftKey);
        $formatted = $this->formatDraft($updated);
        $formatted['execution'] = [
            'status' => 'pending_execution',
            'note' => 'The draft is confirmed and ready for a future executor. No ERP records were posted by this confirmation.',
        ];

        return $formatted;
    }

    public function cancelDraft(string $draftKey, string $decisionNote = ''): array
    {
        $draft = $this->findDraft($draftKey);
        $this->assertDraftCanBeDecided($draft);

        $this->draftModel->update($draft['id'], [
            'status' => 'cancelled',
            'decisionBy' => auth()->id() ? (int) auth()->id() : null,
            'decisionNote' => $decisionNote !== '' ? $decisionNote : 'Cancelled from Ampla Copilot.',
            'decisionAt' => date('Y-m-d H:i:s'),
        ]);

        return $this->formatDraft($this->findDraft($draftKey));
    }

    public function executeDraft(string $draftKey): array
    {
        $this->assertCanExecute();

        $draft = $this->findDraft($draftKey);

        if (($draft['status'] ?? '') !== 'confirmed') {
            throw new InvalidArgumentException('Only confirmed Copilot drafts can be executed.');
        }

        if (!empty($draft['executionStatus']) && $draft['executionStatus'] === 'executed') {
            throw new InvalidArgumentException('This Copilot draft has already been executed.');
        }

        $payload = $this->decodePayload($draft);
        $result = match ($draft['actionType'] ?? '') {
            'stock_adjustment' => $this->executeStockAdjustment($draft, $payload),
            default => $this->manualExecutionResult($draft),
        };

        $this->draftModel->update($draft['id'], [
            'status' => $result['posted'] ? 'executed' : 'confirmed',
            'executionStatus' => $result['status'],
            'executionResult' => json_encode($result),
            'executedBy' => auth()->id() ? (int) auth()->id() : null,
            'executedAt' => date('Y-m-d H:i:s'),
        ]);

        $formatted = $this->formatDraft($this->findDraft($draftKey));
        $formatted['execution'] = $result;

        return $formatted;
    }

    private function firstProduct(string $productName): array
    {
        $products = $this->inventoryService->searchProductStock($productName);
        $product = $products[0] ?? null;

        if (!$product) {
            throw new InvalidArgumentException('No matching product was found for the draft action.');
        }

        return $product;
    }

    private function storeDraft(string $actionType, string $title, string $summary, array $payload, string $sourceTool): array
    {
        $draft = [
            'draftKey' => $this->newDraftKey(),
            'userId' => auth()->id() ? (int) auth()->id() : null,
            'branchId' => $this->branchContext->getEffectiveBranchId(),
            'actionType' => $actionType,
            'status' => 'draft',
            'risk' => 'draft',
            'title' => $title,
            'summary' => $summary,
            'sourceTool' => $sourceTool,
            'payload' => json_encode($payload),
            'expiresAt' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ];

        if ($this->db->tableExists('copilot_action_drafts')) {
            $this->draftModel->insert($draft);
        }

        return [
            'draft_key' => $draft['draftKey'],
            'action_type' => $actionType,
            'status' => 'draft',
            'risk' => 'draft',
            'title' => $title,
            'summary' => $summary,
            'expires_at' => $draft['expiresAt'],
            'requires_confirmation' => true,
            'confirmation_status' => 'pending_confirmation',
            'confirmation_note' => 'The draft is saved for review. Confirming it marks approval, but does not post ERP records yet.',
            'payload' => $payload,
            'persisted' => $this->db->tableExists('copilot_action_drafts'),
        ];
    }

    private function findDraft(string $draftKey): array
    {
        if (!$this->db->tableExists('copilot_action_drafts')) {
            throw new InvalidArgumentException('Copilot draft storage is not available. Run the latest migrations first.');
        }

        $draftKey = trim($draftKey);

        if ($draftKey === '') {
            throw new InvalidArgumentException('draft_key is required.');
        }

        $builder = $this->draftModel->where('draftKey', $draftKey);
        $this->scopeDraftBuilder($builder);

        $draft = $builder->first();

        if (!$draft) {
            throw new InvalidArgumentException('No matching Copilot draft was found.');
        }

        return $draft;
    }

    private function scopeDraftBuilder($builder): void
    {
        $userId = auth()->id() ? (int) auth()->id() : null;

        if ($userId !== null) {
            $builder->where('userId', $userId);
        } else {
            $builder->where('userId IS NULL', null, false);
        }

        $branchId = $this->branchContext->getEffectiveBranchId($userId);

        if ($branchId !== null) {
            $builder->where('branchId', $branchId);
        }
    }

    private function assertDraftCanBeDecided(array $draft): void
    {
        if (($draft['status'] ?? '') !== 'draft') {
            throw new InvalidArgumentException('Only drafts with draft status can be confirmed or cancelled.');
        }

        if (!empty($draft['expiresAt']) && strtotime((string) $draft['expiresAt']) < time()) {
            $this->draftModel->update($draft['id'], ['status' => 'expired']);

            throw new InvalidArgumentException('This Copilot draft has expired. Please create a new draft.');
        }
    }

    private function assertCanExecute(): void
    {
        $user = auth()->user();

        if (!$user) {
            throw new InvalidArgumentException('Authentication is required to execute Copilot drafts.');
        }

        $groups = method_exists($user, 'getGroups') ? $user->getGroups() : [];

        if (array_intersect($groups, ['superadmin', 'admin', 'developer'])) {
            return;
        }

        throw new InvalidArgumentException('You do not have permission to execute Copilot drafts.');
    }

    private function executeStockAdjustment(array $draft, array $payload): array
    {
        $productId = (int) ($payload['product_id'] ?? 0);
        $targetQuantity = (float) ($payload['target_quantity'] ?? -1);

        if ($productId <= 0 || $targetQuantity < 0) {
            throw new InvalidArgumentException('The stock adjustment draft is missing a valid product or target quantity.');
        }

        $userId = auth()->id() ? (int) auth()->id() : 0;
        $branchId = $this->branchContext->getEffectiveBranchId($userId);

        if ($branchId === null) {
            throw new InvalidArgumentException('A branch context is required to execute stock adjustments.');
        }

        $this->db->transStart();

        $product = $this->inventoryModel->find($productId);

        if (!$product || (int) ($product['branchId'] ?? 0) !== $branchId) {
            $this->db->transRollback();

            throw new InvalidArgumentException('The product is not available in the active branch.');
        }

        $beforeQuantity = (float) ($product['itemQuantity'] ?? 0);
        $difference = $targetQuantity - $beforeQuantity;

        $updated = $this->db->table('inventory')
            ->where('itemId', $productId)
            ->where('branchId', $branchId)
            ->update(['itemQuantity' => $targetQuantity]);

        if (!$updated) {
            $this->db->transRollback();

            throw new RuntimeException('Could not update product quantity.');
        }

        if ($this->db->tableExists('stock_movements')) {
            $recorded = $this->stockLedger->recordProductMovement(
                $branchId,
                $productId,
                $difference >= 0 ? 'copilot_stock_adjustment_in' : 'copilot_stock_adjustment_out',
                max($difference, 0),
                abs(min($difference, 0)),
                $targetQuantity,
                isset($product['itemStockPrice']) ? (float) $product['itemStockPrice'] : null,
                'copilot_action_draft',
                $draft['id'],
                (string) ($draft['draftKey'] ?? ''),
                $userId
            );

            if (!$recorded) {
                $this->db->transRollback();

                throw new RuntimeException('Could not record the stock movement.');
            }
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new RuntimeException('Could not complete the stock adjustment transaction.');
        }

        return [
            'status' => 'executed',
            'posted' => true,
            'message' => 'Stock adjustment posted successfully.',
            'product_id' => $productId,
            'product' => $product['itemName'] ?? ($payload['product'] ?? null),
            'before_quantity' => $beforeQuantity,
            'after_quantity' => $targetQuantity,
            'quantity_difference' => $difference,
            'stock_movement_recorded' => $this->db->tableExists('stock_movements'),
        ];
    }

    private function manualExecutionResult(array $draft): array
    {
        return [
            'status' => 'manual_action_required',
            'posted' => false,
            'message' => 'This draft type is confirmed but does not yet have an automatic ERP executor.',
            'action_type' => $draft['actionType'] ?? null,
        ];
    }

    private function decodePayload(array $draft): array
    {
        if (empty($draft['payload'])) {
            return [];
        }

        $payload = json_decode((string) $draft['payload'], true);

        return is_array($payload) ? $payload : [];
    }

    private function formatDraft(array $draft): array
    {
        $payload = $this->decodePayload($draft);
        $executionResult = [];

        if (!empty($draft['executionResult'])) {
            $decoded = json_decode((string) $draft['executionResult'], true);
            $executionResult = is_array($decoded) ? $decoded : [];
        }

        return [
            'draft_key' => $draft['draftKey'] ?? null,
            'action_type' => $draft['actionType'] ?? null,
            'status' => $draft['status'] ?? null,
            'risk' => $draft['risk'] ?? null,
            'title' => $draft['title'] ?? null,
            'summary' => $draft['summary'] ?? null,
            'source_tool' => $draft['sourceTool'] ?? null,
            'payload' => $payload,
            'decision' => [
                'by' => $draft['decisionBy'] ?? null,
                'note' => $draft['decisionNote'] ?? null,
                'at' => $draft['decisionAt'] ?? null,
            ],
            'execution' => [
                'status' => $draft['executionStatus'] ?? null,
                'result' => $executionResult,
                'by' => $draft['executedBy'] ?? null,
                'at' => $draft['executedAt'] ?? null,
            ],
            'expires_at' => $draft['expiresAt'] ?? null,
            'created_at' => $draft['createdAt'] ?? null,
            'updated_at' => $draft['updatedAt'] ?? null,
            'requires_confirmation' => ($draft['status'] ?? null) === 'draft',
        ];
    }

    private function newDraftKey(): string
    {
        return 'draft_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }
}
