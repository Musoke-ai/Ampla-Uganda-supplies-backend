<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Services\AgentToolService;
use App\Services\AuditLogService;
use App\Services\CopilotContextService;
use App\Services\CopilotDraftActionService;
use App\Services\Reports\AlertInsightService;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\ReportFilterService;

class AgentController extends ResourceController
{
    private const AGENT_NAME = 'Ampla Copilot';

    public function chat()
    {
        $data = $this->request->getJSON(true);
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            return $this->failValidationErrors('Message is required');
        }

        try {
            $toolService = new AgentToolService();
            $contextService = new CopilotContextService();
            $session = $contextService->load($data['session_id'] ?? ($data['conversation_id'] ?? null));
            $sessionKey = $session['session_key'] ?? 'default';
            $context = $session['context'] ?? [];

            $memoryAnswer = $contextService->answerFromMemory($message, $context);
            if ($memoryAnswer) {
                $answer = $memoryAnswer['answer'] ?? $this->generateMemoryAnswer($message, $memoryAnswer);
                $auditLogged = $this->recordCopilotEvent($message, [
                    'status' => true,
                    'tool' => 'memory:' . ($memoryAnswer['tool'] ?? 'previous_result'),
                    'category' => 'memory',
                    'risk' => 'read',
                    'source_type' => 'memory',
                    'arguments' => $memoryAnswer['arguments'] ?? [],
                    'record_count' => $memoryAnswer['record_count'] ?? 0,
                ], $sessionKey);

                return $this->respond([
                    'status' => true,
                    'agent_name' => self::AGENT_NAME,
                    'session_id' => $sessionKey,
                    'tool' => $memoryAnswer['tool'] ?? null,
                    'arguments' => $memoryAnswer['arguments'] ?? [],
                    'risk' => 'read',
                    'source' => $memoryAnswer['source'] ?? [
                        'type' => 'memory',
                        'label' => 'Previous Copilot result',
                    ],
                    'record_count' => $memoryAnswer['record_count'] ?? 0,
                    'records' => $memoryAnswer['records'] ?? [],
                    'answer' => $answer,
                    'used_memory' => true,
                    'needs_confirmation' => false,
                    'confirmation' => null,
                    'audit_logged' => $auditLogged,
                    'context' => [
                        'last_tool' => $context['last_tool'] ?? null,
                        'last_product' => $context['last_product'] ?? null,
                        'last_customer' => $context['last_customer'] ?? null,
                        'last_period' => $context['last_period'] ?? null,
                    ],
                ]);
            }

            $routerMessage = $contextService->enrichMessage($message, $context);

            $toolChoice = $this->chooseTool($message, $toolService, $contextService, $context, $routerMessage);
            $toolChoice = $this->applyContextToToolPlan($toolChoice, $contextService, $context);

            if (!$toolChoice['allowed']) {
                return $this->respond([
                    'status' => true,
                    'agent_name' => self::AGENT_NAME,
                    'session_id' => $sessionKey,
                    'answer' => 'I can currently help with inventory, customers, sales, production orders, raw materials, and reports.',
                    'tool_choice' => $toolChoice,
                ]);
            }

            $isMultiTool = $this->isMultiToolChoice($toolChoice);
            $toolName = $isMultiTool ? 'multi_tool' : ($toolChoice['tool'] ?? null);
            $arguments = $isMultiTool ? ['tools' => $toolChoice['tools']] : ($toolChoice['arguments'] ?? []);

            if (empty($toolName)) {
                return $this->respond([
                    'status' => false,
                    'agent_name' => self::AGENT_NAME,
                    'session_id' => $sessionKey,
                    'message' => 'No valid tool was selected.',
                    'tool_choice' => $toolChoice,
                ], 400);
            }

            $toolResult = $isMultiTool
                ? $this->executeToolPlan($toolService, $toolChoice['tools'])
                : $toolService->executeTool($toolName, $arguments);
            $updatedContext = $contextService->remember($sessionKey, $message, $toolChoice, $toolResult);
            $auditLogged = $this->recordCopilotEvent($message, $toolResult, $sessionKey);

            if (!($toolResult['status'] ?? false)) {
                $statusCode = ($toolResult['error'] ?? '') === 'permissionDenied' ? 403 : 422;

                return $this->respond([
                    'status' => false,
                    'agent_name' => self::AGENT_NAME,
                    'session_id' => $sessionKey,
                    'message' => $toolResult['message'] ?? 'The selected Copilot tool could not run.',
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'error' => $toolResult['error'] ?? 'toolFailed',
                    'audit_logged' => $auditLogged,
                ], $statusCode);
            }

            $answer = $this->generateFinalAnswer(
                $message,
                $toolName,
                $toolResult['arguments'] ?? $arguments,
                $toolResult
            );

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'session_id' => $sessionKey,
                'tool' => $toolName,
                'arguments' => $toolResult['arguments'] ?? $arguments,
                'risk' => $toolResult['risk'] ?? 'read',
                'source' => [
                    'type' => $toolResult['source_type'] ?? null,
                    'label' => $toolResult['source_label'] ?? null,
                ],
                'record_count' => $toolResult['record_count'] ?? 0,
                'records' => $toolResult['records'] ?? [],
                'answer' => $answer,
                'needs_confirmation' => ($toolResult['risk'] ?? 'read') === 'draft',
                'confirmation' => $this->confirmationMetadata($toolResult),
                'audit_logged' => $auditLogged,
                'context' => [
                    'last_tool' => $updatedContext['last_tool'] ?? null,
                    'last_product' => $updatedContext['last_product'] ?? null,
                    'last_customer' => $updatedContext['last_customer'] ?? null,
                    'last_period' => $updatedContext['last_period'] ?? null,
                ],
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'Agent failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'message' => 'Agent failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function draft(string $draftKey = '')
    {
        try {
            $draft = (new CopilotDraftActionService())->getDraft($draftKey);

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'draft' => $draft,
                'needs_confirmation' => $draft['requires_confirmation'] ?? false,
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }

    public function briefing()
    {
        try {
            $filters = (new ReportFilterService())->resolve([
                'period' => $this->request->getGet('period') ?? 'today',
                'lowStockThreshold' => $this->request->getGet('lowStockThreshold') ?? 5,
            ]);
            $dashboard = (new DashboardReportService())->build($filters);
            $alerts = (new AlertInsightService())->build($filters);
            $drafts = (new CopilotDraftActionService())->listDrafts('active', 8);
            $toolCatalog = (new AgentToolService())->getAvailableToolDescriptions();

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'period' => $filters['period'],
                'kpis' => array_slice($dashboard['kpis'] ?? [], 0, 6),
                'insights' => array_slice($alerts['items'] ?? [], 0, 8),
                'active_drafts' => $drafts,
                'tool_catalog' => $toolCatalog,
                'summary' => [
                    'open_alerts' => count($alerts['items'] ?? []),
                    'active_drafts' => count($drafts),
                    'available_tools' => count($toolCatalog),
                    'accuracy_notes' => array_values(array_unique(array_merge(
                        $dashboard['accuracyNotes'] ?? [],
                        $alerts['accuracyNotes'] ?? []
                    ))),
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function tools()
    {
        try {
            $tools = (new AgentToolService())->getAvailableToolDescriptions();
            $categories = [];

            foreach ($tools as $tool) {
                $category = $tool['category'] ?? 'other';
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'tool_catalog' => $tools,
                'summary' => [
                    'available_tools' => count($tools),
                    'categories' => $categories,
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function drafts()
    {
        $status = (string) ($this->request->getGet('status') ?? 'active');
        $limit = (int) ($this->request->getGet('limit') ?? 20);

        try {
            $drafts = (new CopilotDraftActionService())->listDrafts($status, $limit);

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'drafts' => $drafts,
                'count' => count($drafts),
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function confirmDraft()
    {
        $data = $this->request->getJSON(true) ?? [];
        $draftKey = trim((string) ($data['draft_key'] ?? ($data['draftKey'] ?? '')));
        $decisionNote = trim((string) ($data['note'] ?? ($data['decision_note'] ?? '')));

        try {
            $draft = (new CopilotDraftActionService())->confirmDraft($draftKey, $decisionNote);
            $auditLogged = $this->recordDraftEvent('copilot.draft_confirm', $draft);

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'message' => 'Copilot draft confirmed. No ERP records were posted yet.',
                'draft' => $draft,
                'needs_confirmation' => false,
                'audit_logged' => $auditLogged,
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function cancelDraft()
    {
        $data = $this->request->getJSON(true) ?? [];
        $draftKey = trim((string) ($data['draft_key'] ?? ($data['draftKey'] ?? '')));
        $decisionNote = trim((string) ($data['reason'] ?? ($data['note'] ?? '')));

        try {
            $draft = (new CopilotDraftActionService())->cancelDraft($draftKey, $decisionNote);
            $auditLogged = $this->recordDraftEvent('copilot.draft_cancel', $draft);

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'message' => 'Copilot draft cancelled.',
                'draft' => $draft,
                'needs_confirmation' => false,
                'audit_logged' => $auditLogged,
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function executeDraft()
    {
        $data = $this->request->getJSON(true) ?? [];
        $draftKey = trim((string) ($data['draft_key'] ?? ($data['draftKey'] ?? '')));

        try {
            $draft = (new CopilotDraftActionService())->executeDraft($draftKey);
            $auditLogged = $this->recordDraftEvent('copilot.draft_execute', $draft);

            return $this->respond([
                'status' => true,
                'agent_name' => self::AGENT_NAME,
                'message' => ($draft['execution']['status'] ?? null) === 'executed'
                    ? 'Copilot draft executed successfully.'
                    : 'Copilot draft is confirmed, but this action still requires manual execution.',
                'draft' => $draft,
                'audit_logged' => $auditLogged,
            ]);
        } catch (\Throwable $exception) {
            return $this->respond([
                'status' => false,
                'agent_name' => self::AGENT_NAME,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function chooseTool(
        string $message,
        AgentToolService $toolService,
        CopilotContextService $contextService,
        array $context = [],
        ?string $routerMessage = null
    ): array
    {
        $tools = $toolService->getToolDescriptions();
        $routerMessage ??= $message;
        $prompt = "
You are an operations system tool router for " . self::AGENT_NAME . ".

Your job is to choose the correct tool for the user's question.
If the question needs data from more than one area, return a small tools plan with 2 or 3 read-only tools.
Do not use draft tools inside a multi-tool plan.

Available tools:
" . json_encode($tools, JSON_PRETTY_PRINT) . "

Return ONLY valid JSON.
Do not include markdown.
Do not include explanations outside JSON.
Only choose tools from the available tools list.

JSON format:
{
  \"allowed\": true,
  \"tool\": \"get_low_stock_products\",
  \"arguments\": {}
}

Multi-tool JSON format:
{
  \"allowed\": true,
  \"tools\": [
    {\"tool\": \"get_sales_report\", \"arguments\": {\"period\": \"this_month\"}},
    {\"tool\": \"get_customer_debt_report\", \"arguments\": {\"period\": \"this_month\"}}
  ]
}

If the user asks about a specific product:
{
  \"allowed\": true,
  \"tool\": \"search_product_stock\",
  \"arguments\": {
    \"product_name\": \"cement\"
  }
}

If the user asks about a specific customer:
{
  \"allowed\": true,
  \"tool\": \"search_customers\",
  \"arguments\": {
    \"customer_name\": \"Acme\"
  }
}

If the user asks about production or raw materials:
{
  \"allowed\": true,
  \"tool\": \"get_production_overview\",
  \"arguments\": {}
}

If the message is not about inventory, stock, products, customers, sales, production, orders, suppliers, raw materials, or reports:
{
  \"allowed\": false,
  \"tool\": null,
  \"arguments\": {}
}

User message:
{$routerMessage}
";

        try {
            $response = $this->askGroq(
                $prompt,
                'You are a strict JSON tool router. Return only valid JSON. Do not use markdown.'
            );

            $json = json_decode($this->cleanJson($response), true);

            if (!is_array($json)) {
                return $this->fallbackToolChoice($message, $contextService, $context);
            }

            if (($json['allowed'] ?? false) && is_array($json['tools'] ?? null)) {
                $plan = $this->normalizeRouterToolPlan($json['tools'], $toolService);

                if (count($plan) > 1) {
                    return [
                        'allowed' => true,
                        'tool' => 'multi_tool',
                        'arguments' => [],
                        'tools' => $plan,
                    ];
                }

                if (count($plan) === 1) {
                    return [
                        'allowed' => true,
                        'tool' => $plan[0]['tool'],
                        'arguments' => $plan[0]['arguments'],
                    ];
                }

                return $this->fallbackToolChoice($message, $contextService, $context);
            }

            $toolChoice = [
                'allowed' => (bool)($json['allowed'] ?? false),
                'tool' => $json['tool'] ?? null,
                'arguments' => is_array($json['arguments'] ?? null) ? $json['arguments'] : [],
            ];

            if (($toolChoice['allowed'] ?? false) && !empty($toolChoice['tool'])) {
                if (!$toolService->toolExists((string) $toolChoice['tool'])) {
                    return $this->fallbackToolChoice($message, $contextService, $context);
                }

                return $toolChoice;
            }

            return $this->fallbackToolChoice($message, $contextService, $context);

        } catch (\Throwable $e) {
            log_message('error', 'Groq tool choice failed: ' . $e->getMessage());

            return $this->fallbackToolChoice($message, $contextService, $context);
        }
    }

    private function applyContextToToolPlan(array $toolChoice, CopilotContextService $contextService, array $context): array
    {
        if (!$this->isMultiToolChoice($toolChoice)) {
            return $contextService->applyContextToToolChoice($toolChoice, $context);
        }

        $tools = [];

        foreach ($toolChoice['tools'] as $choice) {
            $tools[] = $contextService->applyContextToToolChoice([
                'allowed' => true,
                'tool' => $choice['tool'] ?? null,
                'arguments' => is_array($choice['arguments'] ?? null) ? $choice['arguments'] : [],
            ], $context);
        }

        $toolChoice['tools'] = $tools;

        return $toolChoice;
    }

    private function isMultiToolChoice(array $toolChoice): bool
    {
        return ($toolChoice['allowed'] ?? false)
            && ($toolChoice['tool'] ?? null) === 'multi_tool'
            && is_array($toolChoice['tools'] ?? null)
            && count($toolChoice['tools']) > 1;
    }

    private function normalizeRouterToolPlan(array $tools, AgentToolService $toolService): array
    {
        $plan = [];
        $seen = [];

        foreach ($tools as $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $toolName = (string) ($choice['tool'] ?? '');

            if ($toolName === '' || isset($seen[$toolName]) || !$toolService->toolExists($toolName)) {
                continue;
            }

            $definition = $toolService->getToolDefinition($toolName);

            if (($definition['risk'] ?? 'read') !== 'read') {
                continue;
            }

            $plan[] = [
                'allowed' => true,
                'tool' => $toolName,
                'arguments' => is_array($choice['arguments'] ?? null) ? $choice['arguments'] : [],
            ];
            $seen[$toolName] = true;

            if (count($plan) >= 3) {
                break;
            }
        }

        return $plan;
    }

    private function executeToolPlan(AgentToolService $toolService, array $tools): array
    {
        $results = [];
        $recordCount = 0;

        foreach ($tools as $choice) {
            $toolName = (string) ($choice['tool'] ?? '');
            $arguments = is_array($choice['arguments'] ?? null) ? $choice['arguments'] : [];
            $result = $toolService->executeTool($toolName, $arguments);

            if (!($result['status'] ?? false)) {
                $result['tool'] = $toolName;
                $result['arguments'] = $arguments;
                return $result;
            }

            $recordCount += (int) ($result['record_count'] ?? 0);
            $results[] = [
                'tool' => $result['tool'] ?? $toolName,
                'category' => $result['category'] ?? null,
                'risk' => $result['risk'] ?? 'read',
                'source_type' => $result['source_type'] ?? null,
                'source_label' => $result['source_label'] ?? null,
                'arguments' => $result['arguments'] ?? $arguments,
                'record_count' => $result['record_count'] ?? 0,
                'records' => $result['records'] ?? [],
            ];
        }

        return [
            'status' => true,
            'tool' => 'multi_tool',
            'category' => 'multi',
            'risk' => 'read',
            'permission' => null,
            'source_type' => 'multi_tool',
            'source_label' => 'Multiple Copilot tools',
            'arguments' => [
                'tools' => array_map(static fn (array $result): array => [
                    'tool' => $result['tool'] ?? null,
                    'arguments' => $result['arguments'] ?? [],
                ], $results),
            ],
            'records' => $results,
            'record_count' => $recordCount,
        ];
    }

    private function generateFinalAnswer(
        string $message,
        ?string $toolName,
        array $arguments,
        array $toolResult
    ): string {
        $prompt = "
You are " . self::AGENT_NAME . ", an operations assistant for Ampla Uganda Supplies.

User question:
{$message}

Tool used:
{$toolName}

Tool arguments:
" . json_encode($arguments, JSON_PRETTY_PRINT) . "

Database result:
" . json_encode($toolResult, JSON_PRETTY_PRINT) . "

Instructions:
- Answer clearly and practically.
- Use only the database result provided.
- Do not invent figures.
- Do not guess missing values.
- Use UGX where money is involved.
- If no records are found, say no matching records were found.
- Give useful business recommendations where possible.
- If the question is about customers, sales, production, or raw materials, answer in that business context.
- For customer debt reports, use nextDueDate, latestDueDate, and overdueBalance when present. If a due date is present, state when payment is expected. Only say the due date is missing when those fields are empty or null.
- If this is a draft action, clearly say it is only a draft and no ERP records, stock, sale, invoice, SMS, or email have been posted.
";

        try {
            return $this->askGroq(
                $prompt,
                'You are Ampla Copilot, a business assistant. Use only the provided database result. Do not invent figures.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Groq final answer failed: ' . $e->getMessage());

            if (($toolResult['record_count'] ?? 0) === 0) {
                return 'No matching records were found.';
            }

            return 'I found the records, but I failed to generate a detailed AI summary. Please review the returned records.';
        }
    }

    private function generateMemoryAnswer(string $message, array $memoryAnswer): string
    {
        $prompt = "
You are " . self::AGENT_NAME . ", an operations assistant for Ampla Uganda Supplies.

The user is asking a follow-up question. Answer using only the previous Copilot result below.

User follow-up:
{$message}

Previous tool:
" . ($memoryAnswer['tool'] ?? 'unknown') . "

Previous arguments:
" . json_encode($memoryAnswer['arguments'] ?? [], JSON_PRETTY_PRINT) . "

Previous result:
" . json_encode($memoryAnswer['records'] ?? [], JSON_PRETTY_PRINT) . "

Instructions:
- Answer clearly and practically.
- Use only the previous result.
- Do not invent figures, dates, names, or quantities.
- If the answer needs fresh or broader data that is not in the previous result, say that you need to check the system again.
- Mention that you are using the previous result only when that helps avoid confusion.
";

        try {
            return $this->askGroq(
                $prompt,
                'You are Ampla Copilot. Answer follow-up questions using only the supplied previous result.'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Groq memory answer failed: ' . $e->getMessage());

            return 'I can answer this from the previous Copilot result, but I failed to generate a detailed summary. Please review the returned previous records.';
        }
    }

    private function askGroq(string $prompt, string $systemPrompt = ''): string
    {
        $client = \Config\Services::curlrequest();

        $apiKey = env('GROQ_API_KEY');
        $model = env('GROQ_MODEL') ?: 'llama-3.1-8b-instant';

        if (!$apiKey) {
            throw new \RuntimeException('GROQ_API_KEY is missing in .env');
        }

        $messages = [];

        if ($systemPrompt !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        $response = $client->post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.2,
                'max_completion_tokens' => 1200,
            ],
            'timeout' => 120,
        ]);

        $result = json_decode($response->getBody(), true);

        return $result['choices'][0]['message']['content'] ?? '';
    }

    private function fallbackToolChoice(string $message, ?CopilotContextService $contextService = null, array $context = []): array
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return [
                'allowed' => false,
                'tool' => null,
                'arguments' => [],
            ];
        }

        if ($contextService) {
            $followUp = $contextService->fallbackFollowUpChoice($message, $context);

            if ($followUp) {
                $followUp['arguments'] = array_merge($followUp['arguments'] ?? [], $this->extractReportArguments($normalized));

                return $followUp;
            }
        }

        if ($this->containsAny($normalized, ['draft', 'prepare', 'make a', 'create a'])) {
            if ($this->containsAny($normalized, ['reorder list', 'restock list', 'purchase list', 'buying list'])) {
                return [
                    'allowed' => true,
                    'tool' => 'draft_reorder_list',
                    'arguments' => [],
                ];
            }

            if ($this->containsAny($normalized, ['stock adjustment', 'adjust stock', 'stock count', 'inventory adjustment', 'quantity change'])) {
                return [
                    'allowed' => true,
                    'tool' => 'draft_stock_adjustment',
                    'arguments' => [
                        'product_name' => $this->extractKeyword($message, ['draft', 'prepare', 'make', 'create', 'stock', 'adjustment', 'adjust', 'quantity', 'to']),
                        'target_quantity' => $this->extractFirstNumber($message),
                    ],
                ];
            }

            if ($this->containsAny($normalized, ['invoice', 'bill', 'sales document'])) {
                return [
                    'allowed' => true,
                    'tool' => 'draft_invoice',
                    'arguments' => [
                        'customer_name' => $this->extractNamedValue($message, 'customer'),
                        'product_name' => $this->extractNamedValue($message, 'product'),
                        'quantity' => $this->extractFirstNumber($message),
                    ],
                ];
            }

            if ($this->containsAny($normalized, ['follow up', 'follow-up', 'reminder', 'customer note', 'message'])) {
                return [
                    'allowed' => true,
                    'tool' => 'draft_customer_follow_up',
                    'arguments' => [
                        'customer_name' => $this->extractNamedValue($message, 'customer') ?: $this->extractKeyword($message, ['draft', 'prepare', 'make', 'create', 'follow', 'up', 'reminder', 'message', 'customer']),
                        'message' => $message,
                    ],
                ];
            }
        }

        if ($this->containsAny($normalized, ['available reports', 'report catalog', 'what reports', 'report list', 'reporting capabilities', 'what can you report'])) {
            return [
                'allowed' => true,
                'tool' => 'get_report_catalog',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['audit', 'activity log', 'user activity', 'who changed', 'who did', 'system activity'])) {
            return [
                'allowed' => true,
                'tool' => 'get_audit_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['expense', 'expenses', 'spending', 'spent', 'costs', 'cost report'])) {
            return [
                'allowed' => true,
                'tool' => 'get_expense_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['staff', 'employee', 'employees', 'worker', 'workers', 'payroll', 'wage', 'wages', 'salary', 'labour', 'labor'])) {
            return [
                'allowed' => true,
                'tool' => 'get_staff_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['purchase', 'purchases', 'stock intake', 'buying history', 'bought items', 'purchase cost'])) {
            return [
                'allowed' => true,
                'tool' => 'get_purchase_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['supplier', 'suppliers'])) {
            if ($this->containsAny($normalized, ['raw material', 'raw materials'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_raw_material_report',
                    'arguments' => $this->extractReportArguments($normalized),
                ];
            }

            return [
                'allowed' => true,
                'tool' => 'get_supplier_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['raw material', 'raw materials'])) {
            if ($this->containsAny($normalized, ['low', 'running out', 'restock', 'reorder'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_low_stock_raw_materials',
                    'arguments' => [],
                ];
            }

            return [
                'allowed' => true,
                'tool' => 'get_raw_material_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['alert', 'alerts', 'attention', 'risk', 'risks', 'warning', 'warnings', 'recommendation', 'recommendations', 'insight', 'insights'])) {
            return [
                'allowed' => true,
                'tool' => 'get_alert_insights',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['dashboard', 'overview', 'kpi', 'kpis', 'performance', 'management summary', 'business summary'])) {
            return [
                'allowed' => true,
                'tool' => 'get_dashboard_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['stock movement', 'stock movements', 'stock ledger', 'stock in', 'stock out', 'movement history'])) {
            return [
                'allowed' => true,
                'tool' => 'get_stock_movement_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['out of stock', 'zero stock', 'no stock', 'unavailable'])) {
            return [
                'allowed' => true,
                'tool' => 'get_out_of_stock_products',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['slow moving', 'slow-moving', 'dead stock', 'not selling', 'no recent sales'])) {
            return [
                'allowed' => true,
                'tool' => 'get_slow_moving_products',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['overstock', 'overstocked', 'excess stock', 'too much stock'])) {
            return [
                'allowed' => true,
                'tool' => 'get_overstocked_products',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['reorder suggestion', 'reorder suggestions', 'buying list', 'what to reorder', 'restock suggestions'])) {
            return [
                'allowed' => true,
                'tool' => 'get_reorder_suggestions',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['production', 'order', 'orders', 'factory'])) {
            return [
                'allowed' => true,
                'tool' => $this->containsAny($normalized, ['report', 'progress', 'output', 'finished goods', 'value'])
                    ? 'get_production_report'
                    : 'get_production_overview',
                'arguments' => $this->containsAny($normalized, ['report', 'progress', 'output', 'finished goods', 'value'])
                    ? $this->extractReportArguments($normalized)
                    : [],
            ];
        }

        if ($this->containsAny($normalized, ['top customer', 'best customer', 'biggest customer'])) {
            return [
                'allowed' => true,
                'tool' => 'get_top_customers_by_sales',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['debt', 'debts', 'debtor', 'debtors', 'owe', 'owes', 'owing', 'outstanding balance', 'credit customers'])) {
            return [
                'allowed' => true,
                'tool' => 'get_customer_debt_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['customer', 'client'])) {
            if ($this->containsAny($normalized, ['debt', 'debts', 'owe', 'owes', 'owing', 'balance', 'credit'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_customer_debt_report',
                    'arguments' => $this->extractReportArguments($normalized),
                ];
            }

            return [
                'allowed' => true,
                'tool' => 'search_customers',
                'arguments' => [
                    'customer_name' => $this->extractKeyword($message, ['customer', 'client']),
                ],
            ];
        }

        if ($this->containsAny($normalized, ['sale', 'sales', 'revenue', 'sold'])) {
            if ($this->containsAny($normalized, ['profit', 'margin', 'profitable', 'gross profit'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_sales_product_profit_report',
                    'arguments' => $this->extractReportArguments($normalized),
                ];
            }

            if ($this->containsAny($normalized, ['paid vs credit', 'paid versus credit', 'credit sales', 'paid sales', 'unpaid sales', 'cash vs credit'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_sales_paid_vs_credit_report',
                    'arguments' => $this->extractReportArguments($normalized),
                ];
            }

            if ($this->containsAny($normalized, ['report', 'trend', 'monthly', 'daily', 'weekly', 'this month', 'last month'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_sales_report',
                    'arguments' => $this->extractReportArguments($normalized),
                ];
            }

            if ($this->containsAny($normalized, ['product', 'item'])) {
                return [
                    'allowed' => true,
                    'tool' => 'search_sales_by_product',
                    'arguments' => [
                        'product_name' => $this->extractKeyword($message, ['product', 'item']),
                    ],
                ];
            }

            return [
                'allowed' => true,
                'tool' => 'get_sales_summary',
                'arguments' => [
                    'period' => $this->containsAny($normalized, ['today', 'todays', "today's"]) ? 'today' : 'all',
                ],
            ];
        }

        if ($this->containsAny($normalized, ['inventory report', 'stock report', 'current stock report', 'stock valuation report'])) {
            return [
                'allowed' => true,
                'tool' => 'get_inventory_report',
                'arguments' => $this->extractReportArguments($normalized),
            ];
        }

        if ($this->containsAny($normalized, ['inventory value', 'stock worth', 'stock value', 'selling value', 'buying value'])) {
            return [
                'allowed' => true,
                'tool' => 'get_inventory_value',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['low stock', 'restock', 'running out', 'reorder'])) {
            return [
                'allowed' => true,
                'tool' => 'get_low_stock_products',
                'arguments' => [],
            ];
        }

        if ($this->containsAny($normalized, ['product', 'stock', 'inventory', 'item'])) {
            if ($this->containsAny($normalized, ['summary', 'overview', 'health', 'status'])) {
                return [
                    'allowed' => true,
                    'tool' => 'get_inventory_health_summary',
                    'arguments' => [],
                ];
            }

            $productName = $this->extractKeyword($message, ['product', 'stock', 'inventory', 'item']);
            if ($productName === '') {
                return [
                    'allowed' => true,
                    'tool' => 'get_inventory_health_summary',
                    'arguments' => [],
                ];
            }

            return [
                'allowed' => true,
                'tool' => 'search_product_stock',
                'arguments' => [
                    'product_name' => $productName,
                ],
            ];
        }

        return [
            'allowed' => false,
            'tool' => null,
            'arguments' => [],
        ];
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

    private function extractKeyword(string $message, array $stopWords = []): string
    {
        $keyword = strtolower(trim($message));
        $keyword = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $keyword);

        foreach ($stopWords as $word) {
            $keyword = preg_replace('/\b' . preg_quote(strtolower($word), '/') . '\b/u', ' ', $keyword);
        }

        $keyword = preg_replace('/\b(what|which|show|find|check|for|the|a|an|is|are|of|me|about|tell|lookup|look up|search)\b/u', ' ', $keyword);
        $keyword = preg_replace('/\s+/', ' ', $keyword);

        return trim($keyword);
    }

    private function extractReportArguments(string $normalized): array
    {
        if ($this->containsAny($normalized, ['today', 'todays', "today's"])) {
            return ['period' => 'today'];
        }

        if (str_contains($normalized, 'yesterday')) {
            return ['period' => 'yesterday'];
        }

        if (str_contains($normalized, 'last week')) {
            return ['period' => 'last_week'];
        }

        if (str_contains($normalized, 'this week')) {
            return ['period' => 'this_week'];
        }

        if (str_contains($normalized, 'last month')) {
            return ['period' => 'last_month'];
        }

        if (str_contains($normalized, 'this year')) {
            return ['period' => 'this_year'];
        }

        if (str_contains($normalized, 'this quarter')) {
            return ['period' => 'this_quarter'];
        }

        if (str_contains($normalized, 'this month')) {
            return ['period' => 'this_month'];
        }

        return [];
    }

    private function extractFirstNumber(string $message)
    {
        if (preg_match('/\d+(?:\.\d+)?/', $message, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractNamedValue(string $message, string $label): string
    {
        if (preg_match('/\b' . preg_quote($label, '/') . '\s*[:=]\s*([^,;.]+)/i', $message, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function confirmationMetadata(array $toolResult): ?array
    {
        if (($toolResult['risk'] ?? 'read') !== 'draft') {
            return null;
        }

        $draft = $toolResult['records'] ?? [];

        return [
            'required' => true,
            'draft_key' => $draft['draft_key'] ?? null,
            'status' => $draft['confirmation_status'] ?? 'not_implemented',
            'note' => $draft['confirmation_note'] ?? 'Review this draft before any future execution step.',
        ];
    }

    private function recordCopilotEvent(string $message, array $toolResult, string $sessionKey = 'default'): bool
    {
        try {
            $audit = new AuditLogService();
            $branchId = service('branchContext')->getEffectiveBranchId();
            return $audit->record(
                'copilot.tool_run',
                'copilot',
                null,
                null,
                null,
                auth()->id() ? (int) auth()->id() : null,
                $branchId,
                [
                    'message' => $message,
                    'session_key' => $sessionKey,
                    'tool' => $toolResult['tool'] ?? null,
                    'category' => $toolResult['category'] ?? null,
                    'risk' => $toolResult['risk'] ?? null,
                    'permission' => $toolResult['permission'] ?? null,
                    'source_type' => $toolResult['source_type'] ?? null,
                    'status' => $toolResult['status'] ?? false,
                    'error' => $toolResult['error'] ?? null,
                    'arguments' => $toolResult['arguments'] ?? [],
                    'record_count' => $toolResult['record_count'] ?? 0,
                ]
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Copilot audit failed: ' . $exception->getMessage());

            return false;
        }
    }

    private function recordDraftEvent(string $action, array $draft): bool
    {
        try {
            return (new AuditLogService())->record(
                $action,
                'copilot_action_draft',
                $draft['draft_key'] ?? null,
                null,
                $draft,
                auth()->id() ? (int) auth()->id() : null,
                service('branchContext')->getEffectiveBranchId(),
                [
                    'draft_key' => $draft['draft_key'] ?? null,
                    'action_type' => $draft['action_type'] ?? null,
                    'status' => $draft['status'] ?? null,
                    'execution_status' => $draft['execution']['status'] ?? null,
                ]
            );
        } catch (\Throwable $exception) {
            log_message('error', 'Copilot draft audit failed: ' . $exception->getMessage());

            return false;
        }
    }

    private function cleanJson(string $text): string
    {
        $text = trim($text);

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }
}
