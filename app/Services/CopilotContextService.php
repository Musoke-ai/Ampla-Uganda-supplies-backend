<?php

namespace App\Services;

use App\Models\CopilotSession;
use CodeIgniter\Database\BaseConnection;

class CopilotContextService
{
    private CopilotSession $sessionModel;
    private BranchContextService $branchContext;
    private BaseConnection $db;

    public function __construct()
    {
        $this->sessionModel = new CopilotSession();
        $this->branchContext = service('branchContext');
        $this->db = db_connect();
    }

    public function load($sessionKey = null): array
    {
        $sessionKey = $this->normalizeSessionKey($sessionKey);
        $userId = auth()->id() ? (int) auth()->id() : null;
        $branchId = $this->branchContext->getEffectiveBranchId($userId);

        if (!$this->db->tableExists('copilot_sessions')) {
            return [
                'session_key' => $sessionKey,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'row' => null,
                'context' => [],
                'enabled' => false,
            ];
        }

        $row = $this->findSession($sessionKey, $userId);

        return [
            'session_key' => $sessionKey,
            'user_id' => $userId,
            'branch_id' => $branchId,
            'row' => $row,
            'context' => $this->contextFromRow($row),
            'enabled' => true,
        ];
    }

    public function enrichMessage(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $hints = [];

        foreach ([
            'last_tool' => 'last tool',
            'last_product' => 'last product',
            'last_customer' => 'last customer',
            'last_period' => 'last period',
            'last_search' => 'last search',
        ] as $key => $label) {
            if (!empty($context[$key])) {
                $hints[] = $label . ': ' . $context[$key];
            }
        }

        if (!empty($context['last_result_summary']) && is_array($context['last_result_summary'])) {
            $hints[] = 'last result summary: ' . json_encode($context['last_result_summary']);
        }

        if (empty($hints)) {
            return $message;
        }

        return "Conversation context:\n" . implode("\n", $hints) . "\n\nUser message:\n" . $message;
    }

    public function applyContextToToolChoice(array $toolChoice, array $context): array
    {
        if (empty($toolChoice['allowed'])) {
            return $toolChoice;
        }

        $tool = (string) ($toolChoice['tool'] ?? '');
        $arguments = is_array($toolChoice['arguments'] ?? null) ? $toolChoice['arguments'] : [];

        if ($tool === 'search_product_stock' || $tool === 'search_sales_by_product') {
            if (empty($arguments['product_name']) && !empty($context['last_product'])) {
                $arguments['product_name'] = $context['last_product'];
            }
        }

        if ($tool === 'search_customers') {
            if (empty($arguments['customer_name']) && !empty($context['last_customer'])) {
                $arguments['customer_name'] = $context['last_customer'];
            }
        }

        if (str_starts_with($tool, 'get_') && str_ends_with($tool, '_report')) {
            if (empty($arguments['period']) && !empty($context['last_period'])) {
                $arguments['period'] = $context['last_period'];
            }
        }

        $toolChoice['arguments'] = $arguments;

        return $toolChoice;
    }

    public function fallbackFollowUpChoice(string $message, array $context): ?array
    {
        $normalized = strtolower(trim($message));

        if (empty($context['last_tool'])) {
            return null;
        }

        $arguments = [];

        if ($this->asksForExport($normalized)) {
            $tool = $this->exportToolForLastTool((string) $context['last_tool']);

            if ($tool === null) {
                return null;
            }

            if (!empty($context['last_period'])) {
                $arguments['period'] = $context['last_period'];
            }

            $arguments['export_format'] = $this->exportFormatFromMessage($normalized) ?? 'pdf';

            return [
                'allowed' => true,
                'tool' => $tool,
                'arguments' => $arguments,
            ];
        }

        if (!$this->looksLikeFollowUp($normalized)) {
            return null;
        }

        if (!empty($context['last_product']) && in_array($context['last_tool'], ['search_product_stock', 'search_sales_by_product'], true)) {
            $arguments['product_name'] = $context['last_product'];
        }

        if (!empty($context['last_customer']) && $context['last_tool'] === 'search_customers') {
            $arguments['customer_name'] = $context['last_customer'];
        }

        if (!empty($context['last_period'])) {
            $arguments['period'] = $context['last_period'];
        }

        return [
            'allowed' => true,
            'tool' => $context['last_tool'],
            'arguments' => $arguments,
        ];
    }

    public function answerFromMemory(string $message, array $context): ?array
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return null;
        }

        if ($this->asksForExport($normalized)) {
            return null;
        }

        $snapshot = is_array($context['last_result_snapshot'] ?? null)
            ? $context['last_result_snapshot']
            : [];

        if (empty($snapshot) && is_array($context['last_result_summary'] ?? null)) {
            $snapshot = [
                'tool' => $context['last_tool'] ?? null,
                'summary' => $context['last_result_summary'],
                'records' => $context['last_result_summary'],
                'record_count' => 1,
                'created_at' => null,
            ];
        }

        if (!$this->shouldUseMemoryAnswer($normalized, $context, $snapshot)) {
            return null;
        }

        return [
            'answer' => $this->debtDueAnswerFromMemory($normalized, $context, $snapshot),
            'memory_used' => true,
            'tool' => $snapshot['tool'] ?? ($context['last_tool'] ?? null),
            'arguments' => is_array($snapshot['arguments'] ?? null) ? $snapshot['arguments'] : [],
            'records' => $snapshot['records'] ?? ($snapshot['summary'] ?? []),
            'record_count' => (int) ($snapshot['record_count'] ?? 0),
            'source' => [
                'type' => 'memory',
                'label' => 'Previous Copilot result',
                'tool' => $snapshot['tool'] ?? ($context['last_tool'] ?? null),
                'created_at' => $snapshot['created_at'] ?? null,
            ],
        ];
    }

    public function remember(string $sessionKey, string $message, array $toolChoice, array $toolResult): array
    {
        $sessionKey = $this->normalizeSessionKey($sessionKey);
        $userId = auth()->id() ? (int) auth()->id() : null;
        $branchId = $this->branchContext->getEffectiveBranchId($userId);

        if (!$this->db->tableExists('copilot_sessions')) {
            return [];
        }

        $row = $this->findSession($sessionKey, $userId);
        $context = $this->contextFromRow($row);
        $arguments = is_array($toolResult['arguments'] ?? null)
            ? $toolResult['arguments']
            : (is_array($toolChoice['arguments'] ?? null) ? $toolChoice['arguments'] : []);
        $tool = (string) ($toolResult['tool'] ?? ($toolChoice['tool'] ?? ''));

        $context['last_message'] = $this->truncate($message, 500);
        $context['last_tool'] = $tool;
        $context['last_intent'] = $this->intentForTool($tool);

        if (!empty($arguments['period'])) {
            $context['last_period'] = (string) $arguments['period'];
        }

        if (!empty($arguments['search'])) {
            $context['last_search'] = (string) $arguments['search'];
        }

        if (!empty($arguments['product_name'])) {
            $context['last_product'] = (string) $arguments['product_name'];
        }

        if (!empty($arguments['customer_name'])) {
            $context['last_customer'] = (string) $arguments['customer_name'];
        }

        $records = $toolResult['records'] ?? [];
        if (($toolResult['status'] ?? false) && !empty($records)) {
            $context['last_result_summary'] = $this->summarizeToolResult($tool, $records);
            $context['last_result_snapshot'] = $this->buildResultSnapshot($toolResult);
        }

        $firstRecord = $this->firstRecordFromRecords($records);

        if (empty($context['last_product']) && is_array($firstRecord)) {
            $product = $this->recordValue($firstRecord, ['itemName', 'productName', 'product', 'item']);

            if ($product !== null) {
                $context['last_product'] = $product;
            }
        }

        if (empty($context['last_customer']) && is_array($firstRecord)) {
            $customer = $this->recordValue($firstRecord, ['custName', 'customer', 'customerName', 'name']);

            if ($customer !== null) {
                $context['last_customer'] = $customer;
            }
        }

        $data = [
            'sessionKey' => $sessionKey,
            'userId' => $userId,
            'branchId' => $branchId,
            'lastIntent' => $context['last_intent'] ?? null,
            'lastTool' => $context['last_tool'] ?? null,
            'lastProduct' => $this->truncate($context['last_product'] ?? null, 150),
            'lastCustomer' => $this->truncate($context['last_customer'] ?? null, 150),
            'lastPeriod' => $this->truncate($context['last_period'] ?? null, 50),
            'lastSearch' => $this->truncate($context['last_search'] ?? null, 150),
            'contextData' => json_encode($context),
            'messageCount' => ((int) ($row['messageCount'] ?? 0)) + 1,
        ];

        if ($row) {
            $this->sessionModel->update($row['id'], $data);
        } else {
            $this->sessionModel->insert($data);
        }

        return $context;
    }

    private function findSession(string $sessionKey, ?int $userId): ?array
    {
        $builder = $this->sessionModel->where('sessionKey', $sessionKey);

        if ($userId === null) {
            $builder->where('userId IS NULL', null, false);
        } else {
            $builder->where('userId', $userId);
        }

        return $builder->first();
    }

    private function contextFromRow(?array $row): array
    {
        if (!$row) {
            return [];
        }

        $context = [];

        if (!empty($row['contextData'])) {
            $decoded = json_decode((string) $row['contextData'], true);
            $context = is_array($decoded) ? $decoded : [];
        }

        foreach ([
            'lastIntent' => 'last_intent',
            'lastTool' => 'last_tool',
            'lastProduct' => 'last_product',
            'lastCustomer' => 'last_customer',
            'lastPeriod' => 'last_period',
            'lastSearch' => 'last_search',
        ] as $rowKey => $contextKey) {
            if (!empty($row[$rowKey])) {
                $context[$contextKey] = $row[$rowKey];
            }
        }

        return $context;
    }

    private function normalizeSessionKey($sessionKey): string
    {
        $sessionKey = trim((string) $sessionKey);

        if ($sessionKey === '') {
            return 'default';
        }

        $sessionKey = preg_replace('/[^A-Za-z0-9_.:-]/', '-', $sessionKey);

        return substr($sessionKey, 0, 100) ?: 'default';
    }

    private function looksLikeFollowUp(string $message): bool
    {
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'what about')
            || str_contains($message, 'how about')
            || str_contains($message, 'same')
            || str_contains($message, 'them')
            || str_contains($message, 'that')
            || str_contains($message, 'those')
            || str_contains($message, 'remaining')
            || str_contains($message, 'expire')
            || str_word_count($message) <= 4;
    }

    private function summarizeToolResult(string $tool, $records): array
    {
        if ($tool === 'get_customer_debt_report' && is_array($records)) {
            $debtors = is_array($records['topDebtors'] ?? null) ? $records['topDebtors'] : [];
            $first = $debtors[0] ?? [];

            if (is_array($first)) {
                return [
                    'customer' => $first['customer'] ?? null,
                    'contact' => $first['contact'] ?? null,
                    'outstandingBalance' => $first['outstandingBalance'] ?? null,
                    'nextDueDate' => $first['nextDueDate'] ?? null,
                    'latestDueDate' => $first['latestDueDate'] ?? null,
                    'overdueBalance' => $first['overdueBalance'] ?? null,
                ];
            }
        }

        if (is_array($records) && array_is_list($records)) {
            return [
                'record_count' => count($records),
                'first_record' => $records[0] ?? null,
            ];
        }

        return [
            'record_count' => is_array($records) ? count($records) : 0,
        ];
    }

    private function shouldUseMemoryAnswer(string $message, array $context, array $snapshot): bool
    {
        if (empty($snapshot)) {
            return false;
        }

        if ($this->containsAny($message, ['refresh', 'refetch', 'check again', 'latest', 'current list', 'new list', 'updated list'])) {
            return false;
        }

        if (!empty($context['last_customer'])
            && $this->messageMentionsValue($message, (string) $context['last_customer'])
            && $this->isContextualMemoryQuestion($message)
        ) {
            return true;
        }

        if (!empty($context['last_product'])
            && $this->messageMentionsValue($message, (string) $context['last_product'])
            && $this->isContextualMemoryQuestion($message)
        ) {
            return true;
        }

        if ($this->looksLikeFollowUp($message) && !$this->looksLikeNewBusinessQuestion($message)) {
            return true;
        }

        return $this->containsAny($message, [
            'from that',
            'in that result',
            'in the result',
            'previous result',
            'above result',
            'those records',
            'that customer',
            'that product',
            'that debt',
            'the same',
        ]);
    }

    private function isContextualMemoryQuestion(string $message): bool
    {
        if (!$this->looksLikeNewBusinessQuestion($message)) {
            return true;
        }

        return $this->containsAny($message, [
            'that',
            'same',
            'previous',
            'above',
            'result',
            'remaining',
            'left',
            'expire',
            'expires',
            'due',
            'expected',
            'pay',
            'contact',
            'phone',
            'balance',
            'amount',
        ]);
    }

    private function asksForExport(string $message): bool
    {
        return $this->containsAny($message, [
            'pdf',
            'csv',
            'download',
            'export',
            'file',
            'document',
        ]);
    }

    private function exportFormatFromMessage(string $message): ?string
    {
        if (preg_match('/\bpdf\b/i', $message)) {
            return 'pdf';
        }

        if (preg_match('/\bcsv\b/i', $message)) {
            return 'csv';
        }

        return null;
    }

    private function exportToolForLastTool(string $tool): ?string
    {
        $map = [
            'get_sales_summary' => 'get_sales_report',
            'search_sales_by_product' => 'get_sales_report',
            'get_sales_report' => 'get_sales_report',
            'get_sales_product_profit_report' => 'get_sales_product_profit_report',
            'get_sales_paid_vs_credit_report' => 'get_sales_paid_vs_credit_report',
            'get_inventory_value' => 'get_inventory_report',
            'get_inventory_health_summary' => 'get_inventory_report',
            'get_low_stock_products' => 'get_inventory_report',
            'get_out_of_stock_products' => 'get_inventory_report',
            'get_reorder_suggestions' => 'get_inventory_report',
            'get_slow_moving_products' => 'get_inventory_report',
            'get_overstocked_products' => 'get_inventory_report',
            'search_product_stock' => 'get_inventory_report',
            'get_inventory_report' => 'get_inventory_report',
            'get_stock_movement_report' => 'get_stock_movement_report',
            'get_customer_debt_report' => 'get_customer_debt_report',
            'get_top_customers_by_sales' => 'get_sales_report',
            'get_dashboard_report' => 'get_dashboard_report',
            'get_purchase_report' => 'get_purchase_report',
            'get_supplier_report' => 'get_supplier_report',
            'get_raw_material_report' => 'get_raw_material_report',
            'get_production_report' => 'get_production_report',
            'get_expense_report' => 'get_expense_report',
            'get_staff_report' => 'get_staff_report',
            'get_audit_report' => 'get_audit_report',
            'get_alert_insights' => 'get_alert_insights',
        ];

        return $map[$tool] ?? null;
    }

    private function looksLikeNewBusinessQuestion(string $message): bool
    {
        return $this->containsAny($message, [
            'sales',
            'sale',
            'stock',
            'inventory',
            'product',
            'products',
            'customer',
            'customers',
            'debt',
            'debts',
            'supplier',
            'suppliers',
            'raw material',
            'production',
            'expense',
            'expenses',
            'purchase',
            'purchases',
            'report',
            'dashboard',
            'audit',
        ]);
    }

    private function debtDueAnswerFromMemory(string $message, array $context, array $snapshot): ?string
    {
        if (!$this->containsAny($message, ['days', 'remaining', 'left', 'expire', 'expires', 'due', 'expected', 'pay'])) {
            return null;
        }

        $record = $this->findDebtRecordForMessage($message, $context, $snapshot);
        if (!$record) {
            return null;
        }

        $dueDate = $this->recordValue($record, ['nextDueDate', 'latestDueDate', 'endDate', 'dueDate']);
        if (!$dueDate) {
            return 'I still have the previous debt result in memory, but it did not include a due date for that customer.';
        }

        try {
            $today = new \DateTimeImmutable('today');
            $due = new \DateTimeImmutable(substr((string) $dueDate, 0, 10));
            $days = (int) $today->diff($due)->format('%r%a');
            $customer = $this->recordValue($record, ['customer', 'custName', 'customerName', 'name'])
                ?? ($context['last_customer'] ?? 'that customer');
            $balanceValue = $this->recordValue($record, ['outstandingBalance', 'balance', 'dueAmount', 'debtAmount']);
            $balance = is_numeric($balanceValue)
                ? ' with an outstanding balance of UGX ' . number_format((float) $balanceValue)
                : '';

            if ($days < 0) {
                return $customer . "'s debt" . $balance . ' was due on ' . $due->format('Y-m-d') . ', which is ' . abs($days) . ' day(s) overdue.';
            }

            if ($days === 0) {
                return $customer . "'s debt" . $balance . ' is due today, ' . $due->format('Y-m-d') . '.';
            }

            return $customer . "'s debt" . $balance . ' is due on ' . $due->format('Y-m-d') . ', so there are ' . $days . ' day(s) remaining.';
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function findDebtRecordForMessage(string $message, array $context, array $snapshot): ?array
    {
        $records = $this->debtRecordsFromSnapshot($snapshot);

        if (empty($records)) {
            $firstRecord = $this->firstRecordFromRecords($snapshot['records'] ?? []);
            return is_array($firstRecord) ? $firstRecord : null;
        }

        $customer = trim((string) ($context['last_customer'] ?? ''));

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $recordCustomer = $this->recordValue($record, ['customer', 'custName', 'customerName', 'name']);

            if ($recordCustomer && $this->messageMentionsValue($message, $recordCustomer)) {
                return $record;
            }
        }

        if ($customer !== '') {
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $recordCustomer = $this->recordValue($record, ['customer', 'custName', 'customerName', 'name']);

                if ($recordCustomer && strtolower($recordCustomer) === strtolower($customer)) {
                    return $record;
                }
            }
        }

        return count($records) === 1 && is_array($records[0]) ? $records[0] : null;
    }

    private function debtRecordsFromSnapshot(array $snapshot): array
    {
        $records = $snapshot['records'] ?? [];

        if (is_array($records) && is_array($records['topDebtors'] ?? null)) {
            return $records['topDebtors'];
        }

        if (is_array($records) && is_array($records['table']['rows'] ?? null)) {
            return $records['table']['rows'];
        }

        if (is_array($records) && array_is_list($records)) {
            foreach ($records as $toolResult) {
                if (!is_array($toolResult)) {
                    continue;
                }

                if (($toolResult['tool'] ?? null) === 'get_customer_debt_report') {
                    return $this->debtRecordsFromSnapshot($toolResult);
                }
            }

            return $records;
        }

        return [];
    }

    private function buildResultSnapshot(array $toolResult): array
    {
        return [
            'tool' => $toolResult['tool'] ?? null,
            'arguments' => is_array($toolResult['arguments'] ?? null) ? $toolResult['arguments'] : [],
            'source_label' => $toolResult['source_label'] ?? null,
            'record_count' => (int) ($toolResult['record_count'] ?? 0),
            'summary' => $this->summarizeToolResult((string) ($toolResult['tool'] ?? ''), $toolResult['records'] ?? []),
            'records' => $this->compactRecords($toolResult['records'] ?? []),
            'created_at' => date('c'),
        ];
    }

    private function compactRecords($records, int $limit = 12)
    {
        if (!is_array($records)) {
            return $records;
        }

        if (array_is_list($records)) {
            return array_map(
                fn ($record) => $this->compactRecord($record),
                array_slice($records, 0, $limit)
            );
        }

        $compact = [];

        foreach ($records as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $compact[$key] = array_map(
                        fn ($record) => $this->compactRecord($record),
                        array_slice($value, 0, $limit)
                    );
                    continue;
                }

                $compact[$key] = $this->compactRecords($value, $limit);
                continue;
            }

            $compact[$key] = $value;
        }

        return $compact;
    }

    private function compactRecord($record)
    {
        if (!is_array($record)) {
            return $record;
        }

        $compact = [];

        foreach ($record as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $compact[$key] = is_string($value) ? $this->truncate($value, 300) : $value;
        }

        return $compact;
    }

    private function firstRecordFromRecords($records): array
    {
        if (!is_array($records) || empty($records)) {
            return [];
        }

        if (array_is_list($records)) {
            foreach ($records as $record) {
                if (is_array($record)) {
                    if (!empty($record['records'])) {
                        $nested = $this->firstRecordFromRecords($record['records']);
                        if (!empty($nested)) {
                            return $nested;
                        }
                    }

                    return $record;
                }
            }
        }

        foreach (['topDebtors', 'rows'] as $key) {
            if (is_array($records[$key] ?? null) && !empty($records[$key][0]) && is_array($records[$key][0])) {
                return $records[$key][0];
            }
        }

        if (is_array($records['table']['rows'][0] ?? null)) {
            return $records['table']['rows'][0];
        }

        foreach ($records as $value) {
            if (is_array($value)) {
                $nested = $this->firstRecordFromRecords($value);
                if (!empty($nested)) {
                    return $nested;
                }
            }
        }

        return [];
    }

    private function recordValue(array $record, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $record) && trim((string) $record[$key]) !== '') {
                return trim((string) $record[$key]);
            }
        }

        return null;
    }

    private function messageMentionsValue(string $message, string $value): bool
    {
        $message = strtolower($message);
        $value = strtolower(trim($value));

        if ($value === '') {
            return false;
        }

        if (str_contains($message, $value)) {
            return true;
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $matches = 0;

        foreach ($parts as $part) {
            if (strlen($part) >= 3 && str_contains($message, $part)) {
                $matches++;
            }
        }

        return $matches > 0 && $matches >= min(2, count(array_filter($parts)));
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function intentForTool(string $tool): ?string
    {
        if ($tool === '') {
            return null;
        }

        if (str_contains($tool, 'report')) {
            return 'report';
        }

        if (str_contains($tool, 'summary') || str_contains($tool, 'suggestions') || str_contains($tool, 'insights')) {
            return 'analysis';
        }

        return 'query';
    }

    private function truncate($value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return strlen($value) > $length ? substr($value, 0, $length) : $value;
    }
}
