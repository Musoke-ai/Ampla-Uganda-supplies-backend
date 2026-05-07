<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class RawMaterialReportService
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
        $stock = $this->stockRows($filters);
        $usage = $this->usageRows($filters);
        $stockValue = array_sum(array_column($stock, 'stock_value'));
        $usageCost = array_sum(array_column($usage, 'total_cost'));

        return [
            'summary' => [
                'materialCount' => count($stock),
                'totalQuantityOnHand' => round(array_sum(array_column($stock, 'quantity')), 3),
                'rawMaterialStockValue' => round($stockValue, 2),
                'usageEntries' => count($usage),
                'usedQuantity' => round(array_sum(array_column($usage, 'quantity_used')), 3),
                'usageCost' => round($usageCost, 2),
                'expiringSoon' => count($this->expiringSoon($stock)),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column(array_slice($usage, 0, 10), 'material'),
                'datasets' => [
                    [
                        'label' => 'Usage Cost',
                        'data' => array_map(static fn ($row) => (float) $row['total_cost'], array_slice($usage, 0, 10)),
                    ],
                ],
            ],
            'table' => $this->paginate($stock, $filters),
            'usage' => $usage,
            'insights' => $this->insights($stock),
            'accuracyNotes' => [
                'Raw material stock values use current Quantity and unitPrice.',
                'Usage comes from daily_rawmaterials_register and is not yet linked to production batches.',
                'Batch costing needs production_batches and production_batch_materials tables.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return $this->paginate($this->stockRows($filters), $filters);
    }

    private function stockRows(array $filters): array
    {
        $builder = $this->db->table('raw_materials rm')
            ->select('rm.materialId AS material_id, rm.branchId AS branch_id, rm.materialCode AS material_code, rm.name AS material, rm.category, rm.size, rm.unitOfMeasure AS unit_of_measure, rm.Quantity AS quantity, rm.unitPrice AS unit_price, rm.reorderLevel AS reorder_level, (rm.Quantity * rm.unitPrice) AS stock_value, rm.supplier, rm.supplierContact AS supplier_contact, rm.storageLocation AS storage_location, rm.status, rm.expiry, rm.note', false)
            ->orderBy('rm.name', 'ASC');

        $this->scope($builder, 'rm.branchId', 'raw_materials');

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('rm.name', $filters['search'])
                ->orLike('rm.materialCode', $filters['search'])
                ->orLike('rm.category', $filters['search'])
                ->orLike('rm.supplier', $filters['search'])
                ->orLike('rm.storageLocation', $filters['search'])
                ->orLike('rm.size', $filters['search'])
                ->groupEnd();
        }

        return array_map(static function (array $row): array {
            return [
                'material_id' => (int) ($row['material_id'] ?? 0),
                'material_code' => $row['material_code'] ?? '',
                'material' => $row['material'] ?: 'Unknown material',
                'category' => $row['category'] ?? '',
                'size' => $row['size'] ?? '',
                'unit_of_measure' => $row['unit_of_measure'] ?? 'pcs',
                'quantity' => (float) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'reorder_level' => (float) ($row['reorder_level'] ?? 0),
                'stock_value' => round((float) ($row['stock_value'] ?? 0), 2),
                'supplier' => trim((string) ($row['supplier'] ?? '')) ?: 'Unspecified',
                'supplier_contact' => $row['supplier_contact'] ?? '',
                'storage_location' => $row['storage_location'] ?? '',
                'status' => $row['status'] ?? 'active',
                'expiry' => $row['expiry'] ?? null,
                'note' => $row['note'] ?? '',
            ];
        }, $builder->get()->getResultArray());
    }

    private function usageRows(array $filters): array
    {
        if (!$this->db->tableExists('daily_rawmaterials_register')) {
            return [];
        }

        $builder = $this->db->table('daily_rawmaterials_register r')
            ->select('r.materialId AS material_id, rm.name AS material, SUM(r.quantity) AS quantity_used, SUM(r.totalCost) AS total_cost, COUNT(*) AS entries', false)
            ->join('raw_materials rm', 'rm.materialId = r.materialId', 'left')
            ->where('r.dailyRawmaterialsDateCreated >=', $filters['from'])
            ->where('r.dailyRawmaterialsDateCreated <=', $filters['to'])
            ->groupBy('r.materialId, rm.name')
            ->orderBy('total_cost', 'DESC');

        $this->scope($builder, 'r.branchId', 'daily_rawmaterials_register');

        if ($filters['search'] !== '') {
            $builder->like('rm.name', $filters['search']);
        }

        return array_map(static function (array $row): array {
            return [
                'material_id' => (int) ($row['material_id'] ?? 0),
                'material' => $row['material'] ?: 'Unknown material',
                'quantity_used' => (float) ($row['quantity_used'] ?? 0),
                'total_cost' => round((float) ($row['total_cost'] ?? 0), 2),
                'entries' => (int) ($row['entries'] ?? 0),
            ];
        }, $builder->get()->getResultArray());
    }

    private function paginate(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['material_id', 'material_code', 'material', 'category', 'size', 'unit_of_measure', 'quantity', 'unit_price', 'reorder_level', 'stock_value', 'supplier', 'supplier_contact', 'storage_location', 'status', 'expiry', 'note'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function expiringSoon(array $rows): array
    {
        $today = strtotime(date('Y-m-d'));
        $limit = strtotime('+30 days', $today);

        return array_values(array_filter($rows, static function (array $row) use ($today, $limit): bool {
            if (empty($row['expiry'])) {
                return false;
            }

            $expiry = strtotime((string) $row['expiry']);

            return $expiry !== false && $expiry >= $today && $expiry <= $limit;
        }));
    }

    private function insights(array $stock): array
    {
        $insights = [];

        foreach ($this->expiringSoon($stock) as $row) {
            $insights[] = [
                'severity' => 'warning',
                'message' => $row['material'] . ' expires soon on ' . $row['expiry'] . '.',
                'suggested_action' => 'Use or review this material before it expires.',
            ];

            if (count($insights) >= 8) {
                break;
            }
        }

        foreach ($stock as $row) {
            if ((float) $row['quantity'] <= 0) {
                $insights[] = [
                    'severity' => 'critical',
                    'message' => $row['material'] . ' is out of stock.',
                    'suggested_action' => 'Restock or adjust production planning.',
                ];
            }

            $reorderLevel = (float) ($row['reorder_level'] ?? 0);
            if ($reorderLevel > 0 && (float) $row['quantity'] <= $reorderLevel) {
                $insights[] = [
                    'severity' => 'warning',
                    'message' => $row['material'] . ' is at or below its reorder level.',
                    'suggested_action' => 'Plan a replenishment with the listed supplier.',
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
