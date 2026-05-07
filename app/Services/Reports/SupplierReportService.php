<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class SupplierReportService
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
        $rows = $this->supplierRows($filters);
        $purchaseCost = array_sum(array_column($rows, 'purchase_cost'));
        $rawMaterialValue = array_sum(array_column($rows, 'raw_material_value'));

        return [
            'summary' => [
                'supplierCount' => count($rows),
                'purchaseCost' => round($purchaseCost, 2),
                'rawMaterialValue' => round($rawMaterialValue, 2),
                'estimatedSupplierExposure' => round($purchaseCost + $rawMaterialValue, 2),
            ],
            'chart' => [
                'type' => 'bar',
                'labels' => array_column(array_slice($rows, 0, 10), 'supplier'),
                'datasets' => [
                    [
                        'label' => 'Purchase Cost',
                        'data' => array_map(static fn ($row) => (float) $row['purchase_cost'], array_slice($rows, 0, 10)),
                    ],
                    [
                        'label' => 'Raw Material Value',
                        'data' => array_map(static fn ($row) => (float) $row['raw_material_value'], array_slice($rows, 0, 10)),
                    ],
                ],
            ],
            'table' => $this->paginate($rows, $filters),
            'insights' => $this->insights(),
            'accuracyNotes' => [
                'Supplier reporting is based on supplier names stored on stock intake and raw material records.',
                'True supplier balances need supplier master, purchase invoice, supplier payment, and supplier ledger tables.',
                'Supplier names are normalized by trimming empty values but are not yet linked to supplier IDs.',
            ],
        ];
    }

    public function table(array $filters): array
    {
        return $this->paginate($this->supplierRows($filters), $filters);
    }

    private function supplierRows(array $filters): array
    {
        $suppliers = [];

        foreach ($this->stockSupplierRows($filters) as $row) {
            $supplier = $this->supplierName($row['supplier'] ?? null);
            $suppliers[$supplier] ??= $this->emptySupplier($supplier);
            $suppliers[$supplier]['stock_intakes'] += (int) ($row['intake_count'] ?? 0);
            $suppliers[$supplier]['purchase_quantity'] += (float) ($row['quantity'] ?? 0);
            $suppliers[$supplier]['purchase_cost'] += (float) ($row['purchase_cost'] ?? 0);
        }

        foreach ($this->rawMaterialSupplierRows($filters) as $row) {
            $supplier = $this->supplierName($row['supplier'] ?? null);
            $suppliers[$supplier] ??= $this->emptySupplier($supplier);
            $suppliers[$supplier]['raw_material_count'] += (int) ($row['material_count'] ?? 0);
            $suppliers[$supplier]['raw_material_quantity'] += (float) ($row['quantity'] ?? 0);
            $suppliers[$supplier]['raw_material_value'] += (float) ($row['material_value'] ?? 0);
        }

        foreach ($suppliers as &$row) {
            $row['purchase_quantity'] = round($row['purchase_quantity'], 3);
            $row['purchase_cost'] = round($row['purchase_cost'], 2);
            $row['raw_material_quantity'] = round($row['raw_material_quantity'], 3);
            $row['raw_material_value'] = round($row['raw_material_value'], 2);
            $row['estimated_total_value'] = round($row['purchase_cost'] + $row['raw_material_value'], 2);
        }
        unset($row);

        $rows = array_values($suppliers);
        usort($rows, static fn ($a, $b): int => (float) $b['estimated_total_value'] <=> (float) $a['estimated_total_value']);

        if ($filters['search'] !== '') {
            $rows = array_values(array_filter($rows, static fn ($row): bool => stripos($row['supplier'], $filters['search']) !== false));
        }

        return $rows;
    }

    private function stockSupplierRows(array $filters): array
    {
        if (!$this->db->tableExists('stock')) {
            return [];
        }

        $dateColumn = $this->db->fieldExists('stockCreated', 'stock') ? 'st.stockCreated' : 'st.stockId';
        $builder = $this->db->table('stock st')
            ->select('st.itemSupplier AS supplier, COUNT(*) AS intake_count, SUM(st.stockItemQuantity) AS quantity, SUM(st.stockItemQuantity * st.stockItemPrice) AS purchase_cost', false)
            ->where("{$dateColumn} >=", $filters['from'])
            ->where("{$dateColumn} <=", $filters['to'])
            ->groupBy('st.itemSupplier');

        $this->scope($builder, 'st.branchId', 'stock');

        return $builder->get()->getResultArray();
    }

    private function rawMaterialSupplierRows(array $filters): array
    {
        if (!$this->db->tableExists('raw_materials')) {
            return [];
        }

        $builder = $this->db->table('raw_materials rm')
            ->select('rm.supplier, COUNT(*) AS material_count, SUM(rm.Quantity) AS quantity, SUM(rm.Quantity * rm.unitPrice) AS material_value', false)
            ->groupBy('rm.supplier');

        $this->scope($builder, 'rm.branchId', 'raw_materials');

        return $builder->get()->getResultArray();
    }

    private function paginate(array $rows, array $filters): array
    {
        $offset = ($filters['page'] - 1) * $filters['perPage'];

        return [
            'columns' => ['supplier', 'stock_intakes', 'purchase_quantity', 'purchase_cost', 'raw_material_count', 'raw_material_quantity', 'raw_material_value', 'estimated_total_value'],
            'rows' => array_slice($rows, $offset, $filters['perPage']),
            'pagination' => [
                'page' => $filters['page'],
                'per_page' => $filters['perPage'],
                'total' => count($rows),
            ],
        ];
    }

    private function insights(): array
    {
        return [
            [
                'severity' => 'info',
                'message' => 'Supplier balances are not yet financial balances.',
                'suggested_action' => 'Add supplier IDs, purchase invoices, supplier payments, and supplier ledger entries before treating this as accounts payable.',
            ],
        ];
    }

    private function emptySupplier(string $supplier): array
    {
        return [
            'supplier' => $supplier,
            'stock_intakes' => 0,
            'purchase_quantity' => 0,
            'purchase_cost' => 0,
            'raw_material_count' => 0,
            'raw_material_quantity' => 0,
            'raw_material_value' => 0,
            'estimated_total_value' => 0,
        ];
    }

    private function supplierName($value): string
    {
        $supplier = trim((string) $value);

        return $supplier === '' ? 'Unspecified' : $supplier;
    }

    private function scope($builder, string $column, string $table): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null && $this->db->fieldExists('branchId', $table)) {
            $builder->where($column, $branchId);
        }
    }
}
