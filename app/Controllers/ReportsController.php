<?php

namespace App\Controllers;

use App\Services\Reports\AlertInsightService;
use App\Services\Reports\AuditReportService;
use App\Services\Reports\CustomerReportService;
use App\Services\Reports\DashboardReportService;
use App\Services\Reports\ExpenseReportService;
use App\Services\Reports\InventoryReportService;
use App\Services\Reports\ProductionReportService;
use App\Services\Reports\PurchaseReportService;
use App\Services\Reports\ReportCatalogService;
use App\Services\Reports\ReportFilterService;
use App\Services\Reports\ReportPermissionService;
use App\Services\Reports\RawMaterialReportService;
use App\Services\Reports\SalesReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\SupplierReportService;
use CodeIgniter\RESTful\ResourceController;
use InvalidArgumentException;

class ReportsController extends ResourceController
{
    private ReportFilterService $filters;
    private ReportPermissionService $permissions;
    private ReportCatalogService $catalog;

    public function __construct()
    {
        $this->filters = new ReportFilterService();
        $this->permissions = new ReportPermissionService();
        $this->catalog = new ReportCatalogService();
    }

    public function catalog()
    {
        if ($denied = $this->permissions->assertCan('reports.catalog.view')) {
            return $this->respond($denied, 403);
        }

        return $this->respond($this->success('reports.catalog', $this->catalog->all()));
    }

    public function dashboard()
    {
        if ($denied = $this->permissions->assertCan('reports.dashboard.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new DashboardReportService())->build($filters);

            return $this->respond($this->success('dashboard.business_summary', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function sales()
    {
        if ($denied = $this->permissions->assertCan('reports.sales.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $service = new SalesReportService();
            $data = $service->build($filters);
            $data['table'] = $service->table($filters);

            return $this->respond($this->success('sales.daily', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function salesProductProfit()
    {
        if ($denied = $this->permissions->assertCan('reports.sales.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new SalesReportService())->productProfit($filters);

            return $this->respond($this->success('sales.product_profit', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function salesPaidVsCredit()
    {
        if ($denied = $this->permissions->assertCan('reports.sales.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new SalesReportService())->paidVsCredit($filters);

            return $this->respond($this->success('sales.paid_vs_credit', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function inventory()
    {
        if ($denied = $this->permissions->assertCan('reports.inventory.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $service = new InventoryReportService();
            $data = $service->build($filters);
            $data['table'] = $service->table($filters);

            return $this->respond($this->success('inventory.current_stock', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function stockMovements()
    {
        if ($denied = $this->permissions->assertCan('reports.inventory.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new InventoryReportService())->movementReport($filters);

            return $this->respond($this->success('inventory.stock_movements', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function purchases()
    {
        if ($denied = $this->permissions->assertCan('reports.suppliers.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new PurchaseReportService())->build($filters);

            return $this->respond($this->success('purchases.stock_intake', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function suppliers()
    {
        if ($denied = $this->permissions->assertCan('reports.suppliers.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new SupplierReportService())->build($filters);

            return $this->respond($this->success('suppliers.summary', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function rawMaterials()
    {
        if ($denied = $this->permissions->assertCan('reports.production.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new RawMaterialReportService())->build($filters);

            return $this->respond($this->success('production.raw_material_usage', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function production()
    {
        if ($denied = $this->permissions->assertCan('reports.production.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new ProductionReportService())->build($filters);

            return $this->respond($this->success('production.orders', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function staff()
    {
        if ($denied = $this->permissions->assertCan('reports.staff.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new StaffReportService())->build($filters);

            return $this->respond($this->success('staff.worker_payments', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function expenses()
    {
        if ($denied = $this->permissions->assertCan('reports.expenses.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $service = new ExpenseReportService();
            $data = $service->build($filters);
            $data['table'] = $service->table($filters);

            return $this->respond($this->success('expenses.summary', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function customers()
    {
        if ($denied = $this->permissions->assertCan('reports.customers.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $service = new CustomerReportService();
            $data = $service->build($filters);
            $data['table'] = $service->table($filters);

            return $this->respond($this->success('customers.debt', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function audit()
    {
        if ($denied = $this->permissions->assertCan('reports.audit.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new AuditReportService())->build($filters);

            return $this->respond($this->success('audit.user_activity', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    public function alerts()
    {
        if ($denied = $this->permissions->assertCan('reports.alerts.view')) {
            return $this->respond($denied, 403);
        }

        return $this->withFilters(function (array $filters) {
            $data = (new AlertInsightService())->build($filters);

            return $this->respond($this->success('alerts.insights', $data, $filters, $data['accuracyNotes'] ?? []));
        });
    }

    private function resolveFilters(): array
    {
        return $this->filters->resolve($this->request->getGet() ?? []);
    }

    private function success(string $reportKey, array $data, ?array $filters = null, array $accuracyNotes = []): array
    {
        $catalogItem = $this->catalogItem($reportKey);
        $table = $this->normalizeTable($data['table'] ?? [], $filters);
        $chart = $this->normalizeChart($data);
        $insights = $data['insights'] ?? ($data['items'] ?? []);

        return [
            'status' => true,
            'message' => 'Report generated successfully.',
            'report' => [
                'key' => $reportKey,
                'title' => $catalogItem['name'] ?? ucwords(str_replace(['.', '_'], ' ', $reportKey)),
                'category' => $catalogItem['category'] ?? null,
                'date_from' => $filters['date_from'] ?? ($filters['fromDate'] ?? null),
                'date_to' => $filters['date_to'] ?? ($filters['toDate'] ?? null),
                'filters' => $filters ?? [],
            ],
            'summary' => $data['summary'] ?? [],
            'chart' => $chart,
            'table' => $table,
            'insights' => $insights,
            'meta' => [
                'reportKey' => $reportKey,
                'dateRange' => $filters ? [
                    'from' => $filters['fromDate'],
                    'to' => $filters['toDate'],
                ] : null,
                'filters' => $filters,
                'accuracyNotes' => $accuracyNotes,
            ],
            'data' => $data,
        ];
    }

    private function withFilters(callable $callback)
    {
        try {
            return $callback($this->resolveFilters());
        } catch (InvalidArgumentException $exception) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidReportFilters',
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function catalogItem(string $reportKey): ?array
    {
        foreach ($this->catalog->all() as $item) {
            if (($item['key'] ?? null) === $reportKey) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeChart(array $data): array
    {
        if (isset($data['chart']) && is_array($data['chart'])) {
            return $data['chart'];
        }

        if (!empty($data['charts']) && is_array($data['charts'])) {
            return $data['charts'][0];
        }

        if (!empty($data['trend']) && is_array($data['trend'])) {
            return [
                'type' => 'line',
                'labels' => array_column($data['trend'], 'label'),
                'datasets' => [
                    [
                        'label' => 'Value',
                        'data' => array_map(static fn ($row) => (float) ($row['value'] ?? 0), $data['trend']),
                    ],
                ],
            ];
        }

        return [
            'type' => null,
            'labels' => [],
            'datasets' => [],
        ];
    }

    private function normalizeTable(array $table, ?array $filters): array
    {
        $rows = $table['rows'] ?? [];
        $perPage = $filters['perPage'] ?? $filters['per_page'] ?? count($rows);

        return [
            'columns' => $table['columns'] ?? array_keys($rows[0] ?? []),
            'rows' => $rows,
            'pagination' => [
                'page' => $filters['page'] ?? 1,
                'per_page' => $perPage,
                'total' => $table['pagination']['total'] ?? count($rows),
            ],
        ];
    }
}
