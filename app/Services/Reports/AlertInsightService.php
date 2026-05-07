<?php

namespace App\Services\Reports;

use App\Services\BranchContextService;
use CodeIgniter\Database\BaseConnection;

class AlertInsightService
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
        return [
            'items' => array_merge(
                $this->lowStockInsights($filters['lowStockThreshold']),
                $this->customerDebtInsights(),
                $this->rawMaterialExpiryInsights()
            ),
            'accuracyNotes' => [
                'Insights are rule-based and use current database records only.',
                'More risk insights become available after reversal, approval, ledger, and batch-costing tables are added.',
            ],
        ];
    }

    private function lowStockInsights(int $threshold): array
    {
        $builder = $this->db->table('inventory i')
            ->select('i.itemId, i.itemName, i.itemQuantity')
            ->where('i.itemQuantity <=', $threshold)
            ->orderBy('i.itemQuantity', 'ASC')
            ->limit(10);

        $this->scope($builder, 'i.branchId');

        return array_map(static function (array $row): array {
            $quantity = (float) ($row['itemQuantity'] ?? 0);

            return [
                'id' => 'low-stock-' . $row['itemId'],
                'title' => $quantity <= 0 ? 'Product is out of stock' : 'Product is almost out of stock',
                'explanation' => ($row['itemName'] ?? 'Product') . ' has ' . $quantity . ' units remaining.',
                'severity' => $quantity <= 0 ? 'critical' : 'warning',
                'category' => 'inventory',
                'relatedRecordType' => 'product',
                'relatedRecordId' => (int) $row['itemId'],
                'suggestedAction' => 'Review recent demand and restock if the product is still active.',
                'link' => '/home/reports?report=inventory.low_stock',
                'created_at' => date('c'),
            ];
        }, $builder->get()->getResultArray());
    }

    private function customerDebtInsights(): array
    {
        $builder = $this->db->table('indebt d')
            ->select('d.custId, c.custName, SUM(d.totalAmount - d.initialDeposit) AS balance', false)
            ->join('customers c', 'c.custId = d.custId', 'left')
            ->groupBy('d.custId, c.custName')
            ->having('balance >', 0)
            ->orderBy('balance', 'DESC')
            ->limit(5);

        $this->scope($builder, 'd.branchId');

        return array_map(static function (array $row): array {
            return [
                'id' => 'customer-debt-' . $row['custId'],
                'title' => 'Customer has outstanding balance',
                'explanation' => ($row['custName'] ?? 'Customer') . ' owes ' . number_format((float) $row['balance']) . '.',
                'severity' => 'warning',
                'category' => 'customers',
                'relatedRecordType' => 'customer',
                'relatedRecordId' => (int) $row['custId'],
                'suggestedAction' => 'Follow up payment before extending more credit.',
                'link' => '/home/reports?report=customers.debt',
                'created_at' => date('c'),
            ];
        }, $builder->get()->getResultArray());
    }

    private function rawMaterialExpiryInsights(): array
    {
        $builder = $this->db->table('raw_materials rm')
            ->select('rm.materialId, rm.name, rm.expiry')
            ->where('rm.expiry IS NOT NULL', null, false)
            ->where('rm.expiry <>', '')
            ->where('rm.expiry <=', date('Y-m-d', strtotime('+30 days')))
            ->orderBy('rm.expiry', 'ASC')
            ->limit(5);

        $this->scope($builder, 'rm.branchId');

        return array_map(static function (array $row): array {
            return [
                'id' => 'raw-expiry-' . $row['materialId'],
                'title' => 'Raw material expiry needs attention',
                'explanation' => ($row['name'] ?? 'Raw material') . ' expires on ' . ($row['expiry'] ?? 'an upcoming date') . '.',
                'severity' => 'warning',
                'category' => 'production',
                'relatedRecordType' => 'raw_material',
                'relatedRecordId' => (int) $row['materialId'],
                'suggestedAction' => 'Use or review the material before it expires.',
                'link' => '/home/reports?report=production.raw_material_usage',
                'created_at' => date('c'),
            ];
        }, $builder->get()->getResultArray());
    }

    private function scope($builder, string $column): void
    {
        $branchId = $this->branchContext->getEffectiveBranchId();

        if ($branchId !== null) {
            $builder->where($column, $branchId);
        }
    }
}
