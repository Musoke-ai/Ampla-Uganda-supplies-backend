<?php

namespace App\Services;

use App\Services\Reports\AlertInsightService;
use App\Services\Reports\AuditReportService;
use App\Services\Reports\CustomerReportService;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\ProductionReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\RawMaterialReportService;
use App\Services\Reports\ReportCatalogService;
use App\Services\Reports\ReportFilterService;
use App\Services\Reports\ReportPermissionService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\SupplierReportService;
use InvalidArgumentException;

class AgentToolService
{
    protected InventoryService $inventoryService;
    protected CustomerService $customerService;
    protected SalesService $salesService;
    protected ProductionService $productionService;
    protected CopilotDraftActionService $draftActionService;
    protected ReportFilterService $reportFilters;
    protected ReportPermissionService $permissions;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
        $this->customerService = new CustomerService();
        $this->salesService = new SalesService();
        $this->productionService = new ProductionService();
        $this->draftActionService = new CopilotDraftActionService();
        $this->reportFilters = new ReportFilterService();
        $this->permissions = new ReportPermissionService();
    }

    public function getToolDescriptions(): array
    {
        return array_map(static function (array $tool): array {
            return [
                'name' => $tool['name'],
                'category' => $tool['category'],
                'description' => $tool['description'],
                'arguments' => $tool['arguments'],
                'required_arguments' => $tool['required_arguments'],
                'risk' => $tool['risk'],
                'permission' => $tool['permission'],
            ];
        }, $this->getToolRegistry());
    }

    public function getAvailableToolDescriptions(): array
    {
        $availableTools = [];

        foreach ($this->getToolRegistry() as $tool) {
            if (!$this->canRunTool($tool['name'])) {
                continue;
            }

            $availableTools[] = [
                'name' => $tool['name'],
                'category' => $tool['category'],
                'description' => $tool['description'],
                'arguments' => $tool['arguments'],
                'required_arguments' => $tool['required_arguments'],
                'risk' => $tool['risk'],
                'permission' => $tool['permission'],
                'source_label' => $tool['source_label'],
            ];
        }

        return $availableTools;
    }

    public function getToolDefinition(string $toolName): ?array
    {
        foreach ($this->getToolRegistry() as $tool) {
            if ($tool['name'] === $toolName) {
                return $tool;
            }
        }

        return null;
    }

    public function toolExists(string $toolName): bool
    {
        return $this->getToolDefinition($toolName) !== null;
    }

    public function canRunTool(string $toolName): bool
    {
        $tool = $this->getToolDefinition($toolName);

        if (!$tool || empty($tool['permission'])) {
            return true;
        }

        try {
            return $this->permissions->can($tool['permission']);
        } catch (\Throwable $exception) {
            log_message('error', 'Copilot permission check failed: ' . $exception->getMessage());

            return false;
        }
    }

    public function executeTool(string $toolName, array $arguments = []): array
    {
        $tool = $this->getToolDefinition($toolName);

        if (!$tool) {
            return $this->errorResult($toolName, 'unknownTool', 'The selected Copilot tool is not registered.');
        }

        if (!$this->canRunTool($toolName)) {
            return $this->errorResult($toolName, 'permissionDenied', 'You do not have permission to use this Copilot tool.', $tool);
        }

        $validation = $this->validateArguments($tool, $arguments);
        if (!$validation['valid']) {
            return $this->errorResult($toolName, 'invalidArguments', implode(' ', $validation['errors']), $tool);
        }

        try {
            $records = $this->dispatch($toolName, $validation['arguments']);

            return [
                'status' => true,
                'tool' => $toolName,
                'category' => $tool['category'],
                'risk' => $tool['risk'],
                'permission' => $tool['permission'],
                'source_type' => $tool['source_type'],
                'source_label' => $tool['source_label'],
                'arguments' => $validation['arguments'],
                'records' => $records,
                'record_count' => $this->countRecords($records),
            ];
        } catch (InvalidArgumentException $exception) {
            return $this->errorResult($toolName, 'invalidArguments', $exception->getMessage(), $tool);
        } catch (\Throwable $exception) {
            log_message('error', 'Copilot tool failed: ' . $toolName . ' - ' . $exception->getMessage());

            return $this->errorResult($toolName, 'toolFailed', 'The selected Copilot tool failed to run.', $tool);
        }
    }

    public function runTool(string $toolName, array $arguments = []): array
    {
        $result = $this->executeTool($toolName, $arguments);

        return $result['records'] ?? [];
    }

    private function getToolRegistry(): array
    {
        return [
            $this->tool('get_inventory_health_summary', 'inventory', 'Use when the user asks for an overall inventory health, stock status, stock summary, valuation summary, or inventory overview.', [], [], 'reports.inventory.view', 'read', 'report', 'Inventory health summary'),
            $this->tool('get_low_stock_products', 'inventory', 'Use when the user asks about low stock, restocking, reorder, products running out, or items below minimum stock.', [
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'read', 'inventory', 'Inventory products'),
            $this->tool('get_out_of_stock_products', 'inventory', 'Use when the user asks about out of stock products, zero stock, unavailable products, or products with no remaining quantity.', [
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'read', 'inventory', 'Inventory products'),
            $this->tool('get_reorder_suggestions', 'inventory', 'Use when the user asks what to reorder, restock suggestions, buying list, or suggested reorder quantities.', [
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'read', 'inventory', 'Inventory reorder suggestions'),
            $this->tool('get_slow_moving_products', 'inventory', 'Use when the user asks about slow-moving stock, dead stock, products not selling, or items with no recent sales.', [
                'days' => 'integer optional, default 90',
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'read', 'inventory_and_sales', 'Inventory and sales records'),
            $this->tool('get_overstocked_products', 'inventory', 'Use when the user asks about overstocked products, excess stock, too much stock, or products with high stock compared to demand.', [
                'days' => 'integer optional, default 90',
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'read', 'inventory_and_sales', 'Inventory and sales records'),
            $this->tool('search_product_stock', 'inventory', 'Use when the user asks about the stock quantity or details of a specific product.', [
                'product_name' => 'string required',
            ], ['product_name'], 'reports.inventory.view', 'read', 'inventory', 'Inventory products'),
            $this->tool('get_inventory_value', 'inventory', 'Use when the user asks about total stock value, inventory value, stock worth, buying value, or selling value.', [], [], 'reports.inventory.view', 'read', 'inventory', 'Inventory valuation'),

            $this->tool('search_customers', 'customers', 'Use when the user asks about a specific customer, customer contact, customer email, or customer location.', [
                'customer_name' => 'string required',
            ], ['customer_name'], 'reports.customers.view', 'read', 'customers', 'Customer records'),
            $this->tool('get_top_customers_by_sales', 'customers', 'Use when the user asks about top customers, biggest customers, best buyers, or who has bought the most.', [
                'limit' => 'integer optional, default 10',
            ], [], 'reports.customers.view', 'read', 'sales_and_customers', 'Sales and customer records'),

            $this->tool('get_sales_summary', 'sales', 'Use when the user asks about total sales, today sales, sales performance, revenue, or sales summary.', [
                'period' => 'string optional: today or all',
            ], [], 'reports.sales.view', 'read', 'sales', 'Sales records'),
            $this->tool('search_sales_by_product', 'sales', 'Use when the user asks about sales of a specific product or which sales involved a certain item.', [
                'product_name' => 'string required',
            ], ['product_name'], 'reports.sales.view', 'read', 'sales_and_inventory', 'Sales and inventory records'),

            $this->tool('get_production_overview', 'production', 'Use when the user asks about production status, production summary, orders in progress, or raw materials summary.', [], [], 'reports.production.view', 'read', 'production', 'Production records'),
            $this->tool('search_production_orders', 'production', 'Use when the user asks about a specific production order, a customer order, or orders for a given product.', [
                'keyword' => 'string required',
            ], ['keyword'], 'reports.production.view', 'read', 'production', 'Production orders'),
            $this->tool('get_low_stock_raw_materials', 'production', 'Use when the user asks about raw materials running low, raw material restocking, or low stock raw materials.', [
                'threshold' => 'integer optional, default 10',
            ], [], 'reports.production.view', 'read', 'raw_materials', 'Raw material records'),

            $this->tool('get_dashboard_report', 'reports', 'Use when the user asks for the business dashboard, business overview, company performance, KPIs, or management summary.', $this->reportArguments(), [], 'reports.dashboard.view', 'read', 'report', 'Dashboard report'),
            $this->tool('get_report_catalog', 'reports', 'Use when the user asks what reports, dashboards, analytics, or Copilot reporting capabilities are available.', [], [], 'reports.catalog.view', 'read', 'report', 'Report catalog'),
            $this->tool('get_inventory_report', 'reports', 'Use when the user asks for an inventory report, current stock report, stock valuation report, low stock report, or inventory table.', $this->reportArguments(), [], 'reports.inventory.view', 'read', 'report', 'Inventory report'),
            $this->tool('get_stock_movement_report', 'reports', 'Use when the user asks for stock movements, stock ledger, stock in, stock out, or movement history.', $this->reportArguments(), [], 'reports.inventory.view', 'read', 'report', 'Stock movement report'),
            $this->tool('get_sales_report', 'reports', 'Use when the user asks for a sales report, daily sales report, monthly sales report, sales trend, or sales table.', $this->reportArguments(), [], 'reports.sales.view', 'read', 'report', 'Sales report'),
            $this->tool('get_sales_product_profit_report', 'reports', 'Use when the user asks about product profit, gross profit by product, margins, profitable products, or sales profitability.', $this->reportArguments(), [], 'reports.sales.view', 'read', 'report', 'Sales product profit report'),
            $this->tool('get_sales_paid_vs_credit_report', 'reports', 'Use when the user asks about paid sales, credit sales, cash versus credit, unpaid sales, or payment status of sales.', $this->reportArguments(), [], 'reports.sales.view', 'read', 'report', 'Paid versus credit sales report'),
            $this->tool('get_customer_debt_report', 'reports', 'Use when the user asks about customer debt, debtors, outstanding balances, credit customers, or who owes money.', $this->reportArguments(), [], 'reports.customers.view', 'read', 'report', 'Customer debt report'),
            $this->tool('get_purchase_report', 'reports', 'Use when the user asks about purchases, stock intake, bought items, purchase cost, supplier stock intake, or buying history.', $this->reportArguments(), [], 'reports.suppliers.view', 'read', 'report', 'Purchase and stock intake report'),
            $this->tool('get_supplier_report', 'reports', 'Use when the user asks about suppliers, supplier exposure, supplier summary, supplier purchases, or supplier raw material value.', $this->reportArguments(), [], 'reports.suppliers.view', 'read', 'report', 'Supplier report'),
            $this->tool('get_raw_material_report', 'reports', 'Use when the user asks about raw material report, raw material stock value, raw material usage, expiry, storage, or raw material suppliers.', $this->reportArguments(), [], 'reports.production.view', 'read', 'report', 'Raw material report'),
            $this->tool('get_production_report', 'reports', 'Use when the user asks for production report, production orders report, order progress, finished goods output, or production value.', $this->reportArguments(), [], 'reports.production.view', 'read', 'report', 'Production report'),
            $this->tool('get_expense_report', 'reports', 'Use when the user asks about expenses, spending, expense categories, expense trend, costs, or who received expense money.', $this->reportArguments(), [], 'reports.expenses.view', 'read', 'report', 'Expense report'),
            $this->tool('get_staff_report', 'reports', 'Use when the user asks about staff, employees, worker payments, payroll estimates, employee status, labour, or wages.', $this->reportArguments(), [], 'reports.staff.view', 'read', 'report', 'Staff report'),
            $this->tool('get_audit_report', 'reports', 'Use when the user asks about audit logs, user activity, Copilot actions, who changed what, risk review, or system activity.', $this->reportArguments(), [], 'reports.audit.view', 'read', 'report', 'Audit report'),
            $this->tool('get_alert_insights', 'reports', 'Use when the user asks what needs attention, alerts, business risks, warnings, recommendations, or proactive insights.', $this->reportArguments(), [], 'reports.alerts.view', 'read', 'report', 'Alert insights'),

            $this->tool('draft_reorder_list', 'draft_actions', 'Use when the user asks Copilot to prepare, draft, create, or make a reorder list, restock list, purchase list, or buying list without posting it.', [
                'limit' => 'integer optional, default 25',
            ], [], 'reports.inventory.view', 'draft', 'copilot_draft', 'Draft reorder list'),
            $this->tool('draft_stock_adjustment', 'draft_actions', 'Use when the user asks Copilot to prepare or draft a stock adjustment, stock count correction, or inventory quantity change without posting it.', [
                'product_name' => 'string required',
                'target_quantity' => 'number required',
                'reason' => 'string optional',
            ], ['product_name', 'target_quantity'], 'reports.inventory.view', 'draft', 'copilot_draft', 'Draft stock adjustment'),
            $this->tool('draft_invoice', 'draft_actions', 'Use when the user asks Copilot to prepare or draft an invoice, bill, or sales document without posting a sale.', [
                'customer_name' => 'string required',
                'product_name' => 'string required',
                'quantity' => 'number required',
            ], ['customer_name', 'product_name', 'quantity'], 'reports.sales.view', 'draft', 'copilot_draft', 'Draft invoice'),
            $this->tool('draft_customer_follow_up', 'draft_actions', 'Use when the user asks Copilot to prepare a customer follow-up, reminder, note, or message without sending it.', [
                'customer_name' => 'string required',
                'message' => 'string optional',
            ], ['customer_name'], 'reports.customers.view', 'draft', 'copilot_draft', 'Draft customer follow-up'),
        ];
    }

    private function dispatch(string $toolName, array $arguments): array
    {
        switch ($toolName) {
            case 'get_inventory_health_summary':
                return $this->inventoryService->getInventoryHealthSummary();

            case 'get_low_stock_products':
                return $this->inventoryService->getLowStockProducts((int) ($arguments['limit'] ?? 25));

            case 'get_out_of_stock_products':
                return $this->inventoryService->getOutOfStockProducts((int) ($arguments['limit'] ?? 25));

            case 'get_reorder_suggestions':
                return $this->inventoryService->getReorderSuggestions((int) ($arguments['limit'] ?? 25));

            case 'get_slow_moving_products':
                return $this->inventoryService->getSlowMovingProducts((int) ($arguments['days'] ?? 90), (int) ($arguments['limit'] ?? 25));

            case 'get_overstocked_products':
                return $this->inventoryService->getOverstockedProducts((int) ($arguments['days'] ?? 90), (int) ($arguments['limit'] ?? 25));

            case 'search_product_stock':
                return $this->inventoryService->searchProductStock((string) ($arguments['product_name'] ?? ''));

            case 'get_inventory_value':
                return $this->inventoryService->getInventoryValue();

            case 'search_customers':
                return $this->customerService->searchCustomers((string) ($arguments['customer_name'] ?? ''));

            case 'get_top_customers_by_sales':
                return $this->customerService->getTopCustomersBySales((int) ($arguments['limit'] ?? 10));

            case 'get_sales_summary':
                return $this->salesService->getSalesSummary(strtolower((string) ($arguments['period'] ?? 'today')));

            case 'search_sales_by_product':
                return $this->salesService->searchSalesByProduct((string) ($arguments['product_name'] ?? ''));

            case 'get_production_overview':
                return $this->productionService->getProductionOverview();

            case 'search_production_orders':
                return $this->productionService->searchProductionOrders((string) ($arguments['keyword'] ?? ''));

            case 'get_low_stock_raw_materials':
                return $this->productionService->getLowStockRawMaterials((int) ($arguments['threshold'] ?? 10));

            case 'get_dashboard_report':
                return (new DashboardReportService())->build($this->filtersFromArguments($arguments));

            case 'get_report_catalog':
                return (new ReportCatalogService())->all();

            case 'get_inventory_report':
                $inventoryReport = new InventoryReportService();
                $filters = $this->filtersFromArguments($arguments);
                $report = $inventoryReport->build($filters);
                $report['table'] = $inventoryReport->table($filters);
                return $report;

            case 'get_stock_movement_report':
                return (new InventoryReportService())->movementReport($this->filtersFromArguments($arguments));

            case 'get_sales_report':
                $salesReport = new SalesReportService();
                $filters = $this->filtersFromArguments($arguments);
                $report = $salesReport->build($filters);
                $report['table'] = $salesReport->table($filters);
                return $report;

            case 'get_sales_product_profit_report':
                return (new SalesReportService())->productProfit($this->filtersFromArguments($arguments));

            case 'get_sales_paid_vs_credit_report':
                return (new SalesReportService())->paidVsCredit($this->filtersFromArguments($arguments));

            case 'get_customer_debt_report':
                $customerReport = new CustomerReportService();
                $filters = $this->filtersFromArguments($arguments);
                $report = $customerReport->build($filters);
                $report['table'] = $customerReport->table($filters);
                return $report;

            case 'get_purchase_report':
                return (new PurchaseReportService())->build($this->filtersFromArguments($arguments));

            case 'get_supplier_report':
                return (new SupplierReportService())->build($this->filtersFromArguments($arguments));

            case 'get_raw_material_report':
                return (new RawMaterialReportService())->build($this->filtersFromArguments($arguments));

            case 'get_production_report':
                return (new ProductionReportService())->build($this->filtersFromArguments($arguments));

            case 'get_expense_report':
                $expenseReport = new ExpenseReportService();
                $filters = $this->filtersFromArguments($arguments);
                $report = $expenseReport->build($filters);
                $report['table'] = $expenseReport->table($filters);
                return $report;

            case 'get_staff_report':
                return (new StaffReportService())->build($this->filtersFromArguments($arguments));

            case 'get_audit_report':
                return (new AuditReportService())->build($this->filtersFromArguments($arguments));

            case 'get_alert_insights':
                return (new AlertInsightService())->build($this->filtersFromArguments($arguments));

            case 'draft_reorder_list':
                return $this->draftActionService->draftReorderList((int) ($arguments['limit'] ?? 25));

            case 'draft_stock_adjustment':
                return $this->draftActionService->draftStockAdjustment(
                    (string) ($arguments['product_name'] ?? ''),
                    (float) ($arguments['target_quantity'] ?? 0),
                    (string) ($arguments['reason'] ?? '')
                );

            case 'draft_invoice':
                return $this->draftActionService->draftInvoice(
                    (string) ($arguments['customer_name'] ?? ''),
                    (string) ($arguments['product_name'] ?? ''),
                    (float) ($arguments['quantity'] ?? 0)
                );

            case 'draft_customer_follow_up':
                return $this->draftActionService->draftCustomerFollowUp(
                    (string) ($arguments['customer_name'] ?? ''),
                    (string) ($arguments['message'] ?? '')
                );
        }

        return [];
    }

    private function validateArguments(array $tool, array $arguments): array
    {
        $clean = [];
        $errors = [];

        foreach ($tool['required_arguments'] as $argument) {
            if (!array_key_exists($argument, $arguments) || trim((string) $arguments[$argument]) === '') {
                $errors[] = $argument . ' is required.';
            }
        }

        foreach ($arguments as $key => $value) {
            if (!array_key_exists($key, $tool['argument_rules'])) {
                continue;
            }

            $rule = $tool['argument_rules'][$key];

            if (in_array($rule['type'] ?? 'string', ['integer', 'number'], true)) {
                if ($value === '' || $value === null) {
                    continue;
                }

                if (!is_numeric($value)) {
                    $errors[] = $key . ' must be a number.';
                    continue;
                }

                $number = ($rule['type'] ?? 'string') === 'integer' ? (int) $value : (float) $value;
                $min = $rule['min'] ?? null;
                $max = $rule['max'] ?? null;

                if ($min !== null && $number < $min) {
                    $errors[] = $key . ' is too small.';
                    continue;
                }

                if ($max !== null && $number > $max) {
                    $errors[] = $key . ' is too large.';
                    continue;
                }

                $clean[$key] = $number;
                continue;
            }

            $text = trim((string) $value);
            $maxLength = $rule['max_length'] ?? 100;

            if (strlen($text) > $maxLength) {
                $errors[] = $key . ' is too long.';
                continue;
            }

            if (!empty($rule['allowed']) && $text !== '' && !in_array($text, $rule['allowed'], true)) {
                $errors[] = $key . ' has an invalid value.';
                continue;
            }

            $clean[$key] = $text;
        }

        return [
            'valid' => empty($errors),
            'arguments' => $clean,
            'errors' => $errors,
        ];
    }

    private function filtersFromArguments(array $arguments): array
    {
        $period = $arguments['period'] ?? 'this_month';
        if ($period === 'all') {
            $period = 'this_year';
        }

        return $this->reportFilters->resolve([
            'period' => $period,
            'date_from' => $arguments['date_from'] ?? null,
            'date_to' => $arguments['date_to'] ?? null,
            'search' => $arguments['search'] ?? '',
            'page' => $arguments['page'] ?? 1,
            'per_page' => $arguments['per_page'] ?? ($arguments['perPage'] ?? 25),
            'lowStockThreshold' => $arguments['lowStockThreshold'] ?? 5,
        ]);
    }

    private function tool(
        string $name,
        string $category,
        string $description,
        array $arguments,
        array $requiredArguments,
        ?string $permission,
        string $risk,
        string $sourceType,
        string $sourceLabel
    ): array {
        return [
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'arguments' => $arguments,
            'required_arguments' => $requiredArguments,
            'argument_rules' => $this->argumentRules($arguments),
            'permission' => $permission,
            'risk' => $risk,
            'source_type' => $sourceType,
            'source_label' => $sourceLabel,
        ];
    }

    private function argumentRules(array $arguments): array
    {
        $rules = [];

        foreach ($arguments as $name => $description) {
            $description = strtolower((string) $description);

            if (str_contains($description, 'integer')) {
                $rules[$name] = ['type' => 'integer', 'min' => 1, 'max' => $name === 'days' ? 365 : 100];
                continue;
            }

            if (str_contains($description, 'number')) {
                $rules[$name] = ['type' => 'number', 'min' => $name === 'target_quantity' ? 0 : 0.001, 'max' => 1000000];
                continue;
            }

            $rules[$name] = ['type' => 'string', 'max_length' => 120];
        }

        if (isset($rules['period'])) {
            $rules['period']['allowed'] = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_quarter', 'this_year', 'custom', 'all'];
        }

        return $rules;
    }

    private function reportArguments(): array
    {
        return [
            'period' => 'string optional: today, yesterday, this_week, last_week, this_month, last_month, this_quarter, this_year, custom',
            'date_from' => 'string optional YYYY-MM-DD, required only when period is custom',
            'date_to' => 'string optional YYYY-MM-DD, required only when period is custom',
            'search' => 'string optional',
            'page' => 'integer optional, default 1',
            'per_page' => 'integer optional, default 25',
            'lowStockThreshold' => 'integer optional, default 5',
        ];
    }

    private function errorResult(string $toolName, string $error, string $message, ?array $tool = null): array
    {
        return [
            'status' => false,
            'tool' => $toolName,
            'category' => $tool['category'] ?? null,
            'risk' => $tool['risk'] ?? null,
            'permission' => $tool['permission'] ?? null,
            'source_type' => $tool['source_type'] ?? null,
            'source_label' => $tool['source_label'] ?? null,
            'error' => $error,
            'message' => $message,
            'arguments' => [],
            'records' => [],
            'record_count' => 0,
        ];
    }

    private function countRecords(array $records): int
    {
        if (array_key_exists('rows', $records)) {
            return is_array($records['rows']) ? count($records['rows']) : 0;
        }

        if (array_key_exists('table', $records) && is_array($records['table'] ?? null)) {
            return is_array($records['table']['rows'] ?? null) ? count($records['table']['rows']) : 0;
        }

        if (array_is_list($records)) {
            return count($records);
        }

        return empty($records) ? 0 : 1;
    }
}
