<?php

namespace App\Services\Reports;

class ReportCatalogService
{
    public function all(): array
    {
        return [
            $this->item('dashboard.business_summary', 'Dashboard', 'Business Summary Dashboard', 'reports.dashboard.view', 'immediate_with_notes', 'Critical'),
            $this->item('sales.daily', 'Sales', 'Daily Sales Report', 'reports.sales.view', 'immediate', 'Critical'),
            $this->item('sales.product_profit', 'Sales', 'Product Profit Report', 'reports.sales.view', 'active_with_cost_coverage_notes', 'Critical'),
            $this->item('sales.paid_vs_credit', 'Sales', 'Paid vs Credit Sales Report', 'reports.sales.view', 'active', 'Critical'),
            $this->item('inventory.current_stock', 'Inventory', 'Current Stock Report', 'reports.inventory.view', 'immediate', 'Critical'),
            $this->item('inventory.low_stock', 'Inventory', 'Low Stock Report', 'reports.inventory.view', 'immediate', 'Critical'),
            $this->item('inventory.stock_movements', 'Inventory', 'Stock Movement Report', 'reports.inventory.view', 'active_after_migration', 'Critical'),
            $this->item('inventory.valuation', 'Inventory', 'Stock Valuation Report', 'reports.inventory.view', 'immediate', 'Critical'),
            $this->item('purchases.stock_intake', 'Purchases', 'Stock Intake and Purchase-Like Report', 'reports.suppliers.view', 'immediate_without_purchase_ledger', 'Critical'),
            $this->item('production.raw_material_usage', 'Production', 'Raw Material Usage Report', 'reports.production.view', 'immediate_without_batch_costing', 'High'),
            $this->item('production.orders', 'Production', 'Production Orders Report', 'reports.production.view', 'immediate_without_batch_costing', 'Critical'),
            $this->item('production.batch_costing', 'Production', 'Production Batch Costing Report', 'reports.production.view', 'needs_production_batch_tables', 'Critical'),
            $this->item('expenses.summary', 'Expenses', 'Expense Summary Report', 'reports.expenses.view', 'immediate_without_branch_scope', 'Critical'),
            $this->item('customers.debt', 'Customers', 'Customer Debt Report', 'reports.customers.view', 'immediate_without_customer_ledger', 'Critical'),
            $this->item('staff.worker_payments', 'Staff', 'Worker Payments Report', 'reports.staff.view', 'immediate_without_payroll_periods', 'High'),
            $this->item('suppliers.summary', 'Suppliers', 'Supplier Summary Report', 'reports.suppliers.view', 'immediate_without_supplier_ledger', 'High'),
            $this->item('suppliers.balance', 'Suppliers', 'Supplier Balance Report', 'reports.suppliers.view', 'needs_supplier_ledger', 'Critical'),
            $this->item('finance.cash_received', 'Finance', 'Cash Received Report', 'reports.finance.view', 'immediate_with_receipt_caveats', 'Critical'),
            $this->item('audit.user_activity', 'Audit/Risk', 'User Activity Audit', 'reports.audit.view', 'needs_audit_logs_migration', 'High'),
            $this->item('alerts.insights', 'Alerts', 'Alerts and Insights', 'reports.alerts.view', 'immediate_for_basic_rules', 'Critical'),
            $this->item('custom.builder', 'Custom Reports', 'Custom Report Builder', 'reports.custom.run', 'planned', 'Critical'),
        ];
    }

    private function item(
        string $key,
        string $category,
        string $name,
        string $permission,
        string $dataStatus,
        string $priority
    ): array {
        return [
            'key' => $key,
            'category' => $category,
            'name' => $name,
            'description' => $name,
            'requiredPermission' => $permission,
            'dataAvailabilityStatus' => $dataStatus,
            'supportedFilters' => ['period', 'from', 'to', 'branch', 'search'],
            'supportedCharts' => ['line', 'bar', 'donut', 'table'],
            'supportedExports' => ['csv', 'xlsx', 'pdf', 'print'],
            'type' => 'system',
            'implementationStatus' => str_starts_with($dataStatus, 'needs_') || $dataStatus === 'planned' ? 'planned' : 'active',
            'priority' => $priority,
        ];
    }
}
