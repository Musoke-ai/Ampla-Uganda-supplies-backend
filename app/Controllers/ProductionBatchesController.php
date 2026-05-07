<?php

namespace App\Controllers;

use App\Models\Inventory;
use App\Models\Orders;
use App\Models\ProductRegister;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchExpense;
use App\Models\ProductionBatchLabor;
use App\Models\ProductionBatchMaterial;
use App\Models\ProductionBatchOutput;
use App\Models\RawMaterials;
use App\Models\RawMaterialsRegister;
use App\Services\AuditLogService;
use App\Services\BranchContextService;
use App\Services\StockLedgerService;
use CodeIgniter\RESTful\ResourceController;
use RuntimeException;
use Throwable;

class ProductionBatchesController extends ResourceController
{
    private ProductionBatch $batchModel;
    private ProductionBatchMaterial $materialModel;
    private ProductionBatchOutput $outputModel;
    private ProductionBatchLabor $laborModel;
    private ProductionBatchExpense $expenseModel;
    private RawMaterials $rawMaterialModel;
    private RawMaterialsRegister $rawMaterialRegister;
    private Inventory $inventoryModel;
    private ProductRegister $productRegister;
    private Orders $ordersModel;
    private BranchContextService $branchContext;
    private StockLedgerService $stockLedger;
    private AuditLogService $auditLog;

    public function __construct()
    {
        $this->batchModel = new ProductionBatch();
        $this->materialModel = new ProductionBatchMaterial();
        $this->outputModel = new ProductionBatchOutput();
        $this->laborModel = new ProductionBatchLabor();
        $this->expenseModel = new ProductionBatchExpense();
        $this->rawMaterialModel = new RawMaterials();
        $this->rawMaterialRegister = new RawMaterialsRegister();
        $this->inventoryModel = new Inventory();
        $this->productRegister = new ProductRegister();
        $this->ordersModel = new Orders();
        $this->branchContext = service('branchContext');
        $this->stockLedger = new StockLedgerService();
        $this->auditLog = new AuditLogService();
    }

    public function index()
    {
        $builder = db_connect()->table('production_batches pb')
            ->select('pb.*, i.itemName AS productName, o.orderId, e.empName AS supervisorName')
            ->join('inventory i', 'i.itemId = pb.productId', 'left')
            ->join('orders o', 'o.orderId = pb.orderId', 'left')
            ->join('employees e', 'e.empID = pb.supervisorId', 'left')
            ->orderBy('pb.createdAt', 'DESC');

        $this->branchContext->scopeBuilder($builder, 'pb.branchId');
        $batches = $builder->get()->getResultArray();

        foreach ($batches as &$batch) {
            $batch['costing'] = $this->costing((int) $batch['batchId']);
        }
        unset($batch);

        return $this->respond($batches);
    }

    public function show($id = null)
    {
        $batch = $this->loadBatch((int) $id);
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        return $this->respond($this->batchDetails((int) $batch['batchId']));
    }

    public function create()
    {
        $userId = (int) auth()->id();
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));

        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        $productId = $this->optionalInt($this->request->getVar('productId'));
        $orderId = $this->optionalInt($this->request->getVar('orderId'));
        $quantityPlanned = $this->number($this->request->getVar('quantityPlanned'));

        if ($quantityPlanned <= 0) {
            return $this->respond(['status' => false, 'message' => 'Planned quantity must be greater than zero.'], 400);
        }

        if ($orderId !== null) {
            $order = $this->ordersModel->find($orderId);
            if (!$order || (int) ($order['branchId'] ?? 0) !== $branchId) {
                return $this->respond(['status' => false, 'message' => 'Selected order is outside the active branch.'], 403);
            }
            $orderProductId = (int) ($order['prodId'] ?? 0) ?: null;
            if ($orderProductId === null) {
                return $this->respond(['status' => false, 'message' => 'Selected order does not have a product to produce.'], 422);
            }

            if ($productId !== null && $productId !== $orderProductId) {
                return $this->respond(['status' => false, 'message' => 'A linked batch must use the product on the selected order.'], 422);
            }

            $productId = $orderProductId;
        }

        if ($productId !== null) {
            $product = $this->inventoryModel->find($productId);
            $productBranchId = $this->optionalInt($product['branchId'] ?? null);
            if (!$product || ($productBranchId !== null && $productBranchId !== $branchId)) {
                return $this->respond(['status' => false, 'message' => 'Selected product is outside the active branch.'], 403);
            }
        }

        $batchNo = trim((string) ($this->request->getVar('batchNo') ?: $this->makeBatchNo()));
        $saved = $this->batchModel->insert([
            'branchId' => $branchId,
            'batchNo' => $batchNo,
            'orderId' => $orderId,
            'productId' => $productId,
            'supervisorId' => $this->optionalInt($this->request->getVar('supervisorId')),
            'quantityPlanned' => $quantityPlanned,
            'status' => $this->request->getVar('status') ?: 'planned',
            'startDate' => $this->nullableString($this->request->getVar('startDate')),
            'endDate' => $this->nullableString($this->request->getVar('endDate')),
            'notes' => $this->nullableString($this->request->getVar('notes')),
            'createdBy' => $userId,
        ], true);

        if (!$saved) {
            return $this->respond(['status' => false, 'message' => 'Production batch could not be created.'], 500);
        }

        return $this->respond(['status' => true, 'batchId' => $saved, 'message' => 'Production batch created.']);
    }

    public function update($id = null)
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId')) ?? (int) $batch['branchId'];
        $productId = $this->optionalInt($this->request->getVar('productId'));
        $orderId = $this->optionalInt($this->request->getVar('orderId'));
        $quantityPlanned = $this->number($this->request->getVar('quantityPlanned'));
        $hasActivity = $this->batchHasActivity((int) $batch['batchId']);

        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        if ($quantityPlanned <= 0) {
            return $this->respond(['status' => false, 'message' => 'Planned quantity must be greater than zero.'], 400);
        }

        if ($quantityPlanned < (float) ($batch['quantityProduced'] ?? 0)) {
            return $this->respond(['status' => false, 'message' => 'Planned quantity cannot be below quantity already produced.'], 422);
        }

        if ($hasActivity) {
            $lockedChanged =
                $branchId !== (int) $batch['branchId'] ||
                $orderId !== $this->optionalInt($batch['orderId'] ?? null) ||
                $productId !== $this->optionalInt($batch['productId'] ?? null);

            if ($lockedChanged) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Branch, order, and product cannot be changed after materials, labor, expenses, or output have been recorded.',
                ], 422);
            }
        }

        if ($orderId !== null) {
            $order = $this->ordersModel->find($orderId);
            if (!$order || (int) ($order['branchId'] ?? 0) !== $branchId) {
                return $this->respond(['status' => false, 'message' => 'Selected order is outside the active branch.'], 403);
            }

            $orderProductId = (int) ($order['prodId'] ?? 0) ?: null;
            if ($orderProductId === null) {
                return $this->respond(['status' => false, 'message' => 'Selected order does not have a product to produce.'], 422);
            }

            if ($productId !== null && $productId !== $orderProductId) {
                return $this->respond(['status' => false, 'message' => 'A linked batch must use the product on the selected order.'], 422);
            }

            $productId = $orderProductId;
        }

        if ($productId !== null) {
            $product = $this->inventoryModel->find($productId);
            $productBranchId = $this->optionalInt($product['branchId'] ?? null);
            if (!$product || ($productBranchId !== null && $productBranchId !== $branchId)) {
                return $this->respond(['status' => false, 'message' => 'Selected product is outside the active branch.'], 403);
            }
        }

        $status = $this->request->getVar('status') ?: ($batch['status'] ?? 'planned');
        if (!in_array($status, ['planned', 'in_progress', 'quality_check', 'completed', 'cancelled'], true)) {
            return $this->respond(['status' => false, 'message' => 'Invalid batch status.'], 400);
        }

        $updated = $this->batchModel->update($batch['batchId'], [
            'branchId' => $branchId,
            'orderId' => $orderId,
            'productId' => $productId,
            'supervisorId' => $this->optionalInt($this->request->getVar('supervisorId')),
            'quantityPlanned' => $quantityPlanned,
            'status' => $status,
            'startDate' => $this->nullableString($this->request->getVar('startDate')),
            'endDate' => $this->nullableString($this->request->getVar('endDate')),
            'notes' => $this->nullableString($this->request->getVar('notes')),
        ]);

        if (!$updated) {
            return $this->respond(['status' => false, 'message' => 'Production batch could not be updated.'], 500);
        }

        return $this->respond(['status' => true, 'message' => 'Production batch updated.']);
    }

    public function delete($id = null)
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        if ($this->batchHasActivity((int) $batch['batchId'])) {
            return $this->respond([
                'status' => false,
                'message' => 'This batch has recorded activity. Cancel or complete it instead of deleting it.',
            ], 409);
        }

        if (!$this->batchModel->delete((int) $batch['batchId'])) {
            return $this->respond(['status' => false, 'message' => 'Production batch could not be deleted.'], 500);
        }

        return $this->respond(['status' => true, 'message' => 'Production batch deleted.']);
    }

    public function addMaterial()
    {
        $userId = (int) auth()->id();
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $branchId = (int) $batch['branchId'];
        $materialId = (int) $this->request->getVar('materialId');
        $quantity = $this->number($this->request->getVar('quantity'));
        $material = $this->rawMaterialModel->find($materialId);

        if ($quantity <= 0) {
            return $this->respond(['status' => false, 'message' => 'Material quantity must be greater than zero.'], 400);
        }
        if (!$material || (int) ($material['branchId'] ?? 0) !== $branchId) {
            return $this->respond(['status' => false, 'message' => 'Selected raw material is outside this batch branch.'], 403);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            $totalCost = round((float) $material['unitPrice'] * $quantity, 2);
            $registerId = $this->rawMaterialRegister->insert([
                'branchId' => $branchId,
                'batchId' => (int) $batch['batchId'],
                'orderId' => $batch['orderId'] ?? null,
                'materialId' => $materialId,
                'quantity' => $quantity,
                'totalCost' => $totalCost,
                'initials' => $this->request->getVar('initials') ?: ('BATCH-' . $batch['batchNo']),
            ], true);

            if (!$registerId) {
                throw new RuntimeException('Could not record raw material usage.');
            }

            $updated = $db->table('raw_materials')
                ->where('branchId', $branchId)
                ->where('materialId', $materialId)
                ->where('Quantity >=', $quantity)
                ->set('Quantity', 'Quantity - ' . $quantity, false)
                ->update();

            if (!$updated || $db->affectedRows() !== 1) {
                throw new RuntimeException("Insufficient stock for {$material['name']}.");
            }

            $rowId = $this->materialModel->insert([
                'batchId' => (int) $batch['batchId'],
                'branchId' => $branchId,
                'materialId' => $materialId,
                'quantity' => $quantity,
                'unitCost' => (float) $material['unitPrice'],
                'totalCost' => $totalCost,
                'dailyRawMaterialRegisterId' => $registerId,
                'notes' => $this->nullableString($this->request->getVar('notes')),
            ], true);

            if (!$rowId) {
                throw new RuntimeException('Could not link material usage to the batch.');
            }

            $updatedMaterial = $this->rawMaterialModel->find($materialId);
            $this->stockLedger->recordRawMaterialMovement(
                $branchId,
                $materialId,
                'production_batch_out',
                0,
                $quantity,
                (float) $updatedMaterial['Quantity'],
                (float) $material['unitPrice'],
                'production_batch',
                $batch['batchId'],
                $batch['batchNo'],
                $userId
            );

            $this->auditLog->record('production_batch.material_added', 'production_batch', $batch['batchId'], $material, $updatedMaterial, $userId, $branchId, ['quantity' => $quantity, 'totalCost' => $totalCost]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Material usage transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Production batch material add failed: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 409);
        }

        return $this->respond(['status' => true, 'message' => 'Material usage recorded.']);
    }

    public function addLabor()
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $cost = $this->number($this->request->getVar('laborCost'));
        if ($cost < 0) {
            return $this->respond(['status' => false, 'message' => 'Labor cost cannot be negative.'], 400);
        }

        $saved = $this->laborModel->insert([
            'batchId' => (int) $batch['batchId'],
            'branchId' => (int) $batch['branchId'],
            'employeeId' => $this->optionalInt($this->request->getVar('employeeId')),
            'role' => $this->nullableString($this->request->getVar('role')),
            'hoursWorked' => $this->number($this->request->getVar('hoursWorked')),
            'laborCost' => $cost,
            'notes' => $this->nullableString($this->request->getVar('notes')),
        ]);

        return $this->respond(['status' => (bool) $saved, 'message' => $saved ? 'Labor cost added.' : 'Labor cost could not be added.']);
    }

    public function addExpense()
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $amount = $this->number($this->request->getVar('amount'));
        if ($amount < 0) {
            return $this->respond(['status' => false, 'message' => 'Expense amount cannot be negative.'], 400);
        }

        $saved = $this->expenseModel->insert([
            'batchId' => (int) $batch['batchId'],
            'branchId' => (int) $batch['branchId'],
            'category' => $this->request->getVar('category') ?: 'Production',
            'description' => $this->nullableString($this->request->getVar('description')),
            'amount' => $amount,
        ]);

        return $this->respond(['status' => (bool) $saved, 'message' => $saved ? 'Production expense added.' : 'Production expense could not be added.']);
    }

    public function postOutput()
    {
        $userId = (int) auth()->id();
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $branchId = (int) $batch['branchId'];
        $productId = (int) ($this->request->getVar('productId') ?: ($batch['productId'] ?? 0));
        $quantity = $this->number($this->request->getVar('quantity'));
        $wastage = $this->number($this->request->getVar('wastageQuantity'));
        $product = $this->inventoryModel->find($productId);

        if ($quantity <= 0) {
            return $this->respond(['status' => false, 'message' => 'Produced quantity must be greater than zero.'], 400);
        }
        if (!$product || (int) ($product['branchId'] ?? 0) !== $branchId) {
            return $this->respond(['status' => false, 'message' => 'Selected product is outside this batch branch.'], 403);
        }

        $costing = $this->costing((int) $batch['batchId']);
        $unitCost = $quantity > 0 ? round(((float) $costing['totalCost']) / $quantity, 2) : null;
        $db = db_connect();

        try {
            $db->transBegin();

            $productRegisterData = [
                'branchId' => $branchId,
                'batchId' => (int) $batch['batchId'],
                'orderId' => $batch['orderId'] ?? null,
                'prodId' => $productId,
                'initials' => $this->request->getVar('initials') ?: ('BATCH-' . $batch['batchNo']),
            ];
            $quantityColumn = $db->fieldExists('Quantity', 'daily_products_register') ? 'Quantity' : 'quantity';
            $productRegisterData[$quantityColumn] = $quantity;

            $dailyProductId = $db->table('daily_products_register')->insert($productRegisterData)
                ? $db->insertID()
                : null;

            if (!$dailyProductId) {
                throw new RuntimeException('Could not record finished goods output.');
            }

            $oldQuantity = (float) ($product['itemQuantity'] ?? 0);
            $oldCost = (float) ($product['itemStockPrice'] ?? 0);
            $newQuantity = $oldQuantity + $quantity;
            $weightedCost = $unitCost !== null && $newQuantity > 0
                ? round((($oldQuantity * $oldCost) + ($quantity * $unitCost)) / $newQuantity, 2)
                : $oldCost;

            $updated = $db->table('inventory')
                ->where('branchId', $branchId)
                ->where('itemId', $productId)
                ->set('itemQuantity', 'itemQuantity + ' . $quantity, false)
                ->set('itemStockPrice', $weightedCost)
                ->update();

            if (!$updated || $db->affectedRows() !== 1) {
                throw new RuntimeException('Could not add finished goods to inventory.');
            }

            $outputId = $this->outputModel->insert([
                'batchId' => (int) $batch['batchId'],
                'branchId' => $branchId,
                'productId' => $productId,
                'quantity' => $quantity,
                'wastageQuantity' => $wastage,
                'unitCost' => $unitCost,
                'dailyProductRegisterId' => $dailyProductId,
                'notes' => $this->nullableString($this->request->getVar('notes')),
            ], true);

            if (!$outputId) {
                throw new RuntimeException('Could not link finished goods to the batch.');
            }

            $newProduced = (float) ($batch['quantityProduced'] ?? 0) + $quantity;
            $newWastage = (float) ($batch['wastageQuantity'] ?? 0) + $wastage;
            $status = $newProduced >= (float) ($batch['quantityPlanned'] ?? 0) ? 'completed' : 'in_progress';

            $this->batchModel->update($batch['batchId'], [
                'quantityProduced' => $newProduced,
                'wastageQuantity' => $newWastage,
                'status' => $status,
                'endDate' => $status === 'completed' ? date('Y-m-d') : ($batch['endDate'] ?? null),
            ]);

            if (!empty($batch['orderId'])) {
                $db->table('orders')
                    ->where('branchId', $branchId)
                    ->where('orderId', $batch['orderId'])
                    ->set('quantityProduced', 'quantityProduced + ' . $quantity, false)
                    ->update();
            }

            $updatedProduct = $this->inventoryModel->find($productId);
            $this->stockLedger->recordProductMovement(
                $branchId,
                $productId,
                'production_batch_in',
                $quantity,
                0,
                (float) $updatedProduct['itemQuantity'],
                $unitCost,
                'production_batch',
                $batch['batchId'],
                $batch['batchNo'],
                $userId
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Finished goods transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Production batch output failed: ' . $e->getMessage());
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 409);
        }

        return $this->respond(['status' => true, 'message' => 'Finished goods posted to inventory.']);
    }

    public function updateStatus()
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $status = $this->request->getVar('status');
        if (!in_array($status, ['planned', 'in_progress', 'quality_check', 'completed', 'cancelled'], true)) {
            return $this->respond(['status' => false, 'message' => 'Invalid batch status.'], 400);
        }

        $this->batchModel->update($batch['batchId'], ['status' => $status]);

        return $this->respond(['status' => true, 'message' => 'Batch status updated.']);
    }

    public function updateQuality()
    {
        $batch = $this->loadBatch((int) $this->request->getVar('batchId'));
        if (!$batch) {
            return $this->failNotFound('Production batch not found.');
        }

        $qualityStatus = $this->request->getVar('qualityStatus') ?: 'pending';
        $this->batchModel->update($batch['batchId'], [
            'qualityStatus' => $qualityStatus,
            'qualityCheckedBy' => $this->optionalInt($this->request->getVar('qualityCheckedBy')) ?: auth()->id(),
            'qualityCheckedAt' => date('Y-m-d H:i:s'),
            'qualityNotes' => $this->nullableString($this->request->getVar('qualityNotes')),
            'status' => $qualityStatus === 'approved' ? ($batch['status'] === 'quality_check' ? 'completed' : $batch['status']) : 'quality_check',
        ]);

        return $this->respond(['status' => true, 'message' => 'Quality check updated.']);
    }

    private function loadBatch(int $batchId): ?array
    {
        $batch = $this->batchModel->find($batchId);

        if (!$batch || !$this->branchContext->recordMatchesCurrentBranch($batch)) {
            return null;
        }

        return $batch;
    }

    private function batchDetails(int $batchId): array
    {
        $batch = $this->batchModel->find($batchId);
        $batch['materials'] = $this->materialModel->where('batchId', $batchId)->findAll();
        $batch['outputs'] = $this->outputModel->where('batchId', $batchId)->findAll();
        $batch['labor'] = $this->laborModel->where('batchId', $batchId)->findAll();
        $batch['expenses'] = $this->expenseModel->where('batchId', $batchId)->findAll();
        $batch['costing'] = $this->costing($batchId);

        return $batch;
    }

    private function costing(int $batchId): array
    {
        $materialCost = (float) (db_connect()->table('production_batch_materials')->selectSum('totalCost')->where('batchId', $batchId)->get()->getRowArray()['totalCost'] ?? 0);
        $laborCost = (float) (db_connect()->table('production_batch_labor')->selectSum('laborCost')->where('batchId', $batchId)->get()->getRowArray()['laborCost'] ?? 0);
        $expenseCost = (float) (db_connect()->table('production_batch_expenses')->selectSum('amount')->where('batchId', $batchId)->get()->getRowArray()['amount'] ?? 0);
        $outputQuantity = (float) (db_connect()->table('production_batch_outputs')->selectSum('quantity')->where('batchId', $batchId)->get()->getRowArray()['quantity'] ?? 0);
        $wastageQuantity = (float) (db_connect()->table('production_batch_outputs')->selectSum('wastageQuantity')->where('batchId', $batchId)->get()->getRowArray()['wastageQuantity'] ?? 0);
        $totalCost = round($materialCost + $laborCost + $expenseCost, 2);

        return [
            'materialCost' => round($materialCost, 2),
            'laborCost' => round($laborCost, 2),
            'expenseCost' => round($expenseCost, 2),
            'totalCost' => $totalCost,
            'outputQuantity' => round($outputQuantity, 3),
            'wastageQuantity' => round($wastageQuantity, 3),
            'costPerUnit' => $outputQuantity > 0 ? round($totalCost / $outputQuantity, 2) : 0,
        ];
    }

    private function batchHasActivity(int $batchId): bool
    {
        $db = db_connect();
        $tables = [
            'production_batch_materials',
            'production_batch_labor',
            'production_batch_expenses',
            'production_batch_outputs',
        ];

        foreach ($tables as $table) {
            $count = (int) $db->table($table)
                ->where('batchId', $batchId)
                ->countAllResults();

            if ($count > 0) {
                return true;
            }
        }

        $batch = $this->batchModel->find($batchId);
        return (float) ($batch['quantityProduced'] ?? 0) > 0
            || (float) ($batch['wastageQuantity'] ?? 0) > 0;
    }

    private function makeBatchNo(): string
    {
        return 'PB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function optionalInt($value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
