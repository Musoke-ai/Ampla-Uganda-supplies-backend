<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\RawMaterialsRegister;
use App\Models\RawMaterials;
use App\Models\ProductRegister;
use App\Models\EmployeeRegister;
use App\Services\BranchContextService;
use App\Services\StockLedgerService;
use App\Services\AuditLogService;
use RuntimeException;
use Throwable;

class RawMaterialsRegisterController extends ResourceController
 {
    /** This controller holds the following functions
    = Check presence of data
    = Validation check
    = CRUD a stock
    = Fetch stock
    **/

    private $rawMaterialsModel;
    private $rawMaterialsRegister;
    private BranchContextService $branchContext;
    private StockLedgerService $stockLedger;
    private AuditLogService $auditLog;

    public function __construct() {
        $this->rawMaterialsModel = new RawMaterials();
        $this->rawMaterialsRegister = new RawMaterialsRegister();
        $this->branchContext = service('branchContext');
        $this->stockLedger = new StockLedgerService();
        $this->auditLog = new AuditLogService();
    }

    // fetch categories data

    public function noData() {
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the raw materials daily list table.Create a new list.'
        ];
        return $this->respond( $response );
        exit();
    }

    // return resource object for validation failure

    public function validationFail() {
        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => $this->validator->getErrors()
        ];
        return $this->respond( $response );
        exit();
    }

    /**
    * Return an array of resource objects, themselves in array format
    *
    * @return mixed
    */

    public function index()
 {
        $dailyList =  $this->branchContext
            ->scopeBuilder($this->rawMaterialsRegister)
            ->findAll();
        if ( empty( $dailyList ) ) {
            return $this->noData();
        } else {
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! daily raw materials list has been fetched to your front end.'
            ];
            return $this->respond( $dailyList );
        }
    }

    /**
    * Return the properties of a resource object
    *
    * @return mixed
    */
    public function createList()
    {
        $userId = (int) auth()->id();
        $json = $this->request->getBody();
        $inputData = json_decode($json, true);
    
        if (empty($inputData)) {
            return $this->respond([
                'status' => false,
                'error' => 'InvalidInput',
                'message' => 'Invalid or empty material data. Expecting a JSON object or array.'
            ], 400);
        }

        $materials = isset($inputData[0]) && is_array($inputData[0]) ? $inputData : [$inputData];
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));

        if ($branchId === null) {
            return $this->respond([
                'status' => false,
                'error' => 'MissingBranch',
                'message' => 'Select a current branch first.'
            ], 422);
        }

        $db = db_connect();
        $createdIds = [];

        try {
            $db->transBegin();

            foreach ($materials as $material) {
                $materialId = (int) ($material['materialId'] ?? 0);
                $quantity = (float) ($material['quantity'] ?? 0);
                $initials = $material['initials'] ?? null;

                if ($materialId <= 0 || $quantity <= 0) {
                    throw new RuntimeException('Each material must have a valid materialId and a numeric quantity greater than 0.');
                }

                $rawMaterial = $this->rawMaterialsModel->find($materialId);
                if (!$rawMaterial) {
                    throw new RuntimeException("Material with ID {$materialId} not found in stock.");
                }
                if ((int) ($rawMaterial['branchId'] ?? 0) !== $branchId) {
                    throw new RuntimeException("Material with ID {$materialId} does not belong to your current branch.");
                }

                $totalCost = round((float) $rawMaterial['unitPrice'] * $quantity, 2);
                $registerId = $this->rawMaterialsRegister->insert([
                    'branchId' => $branchId,
                    'materialId' => $materialId,
                    'quantity' => $quantity,
                    'totalCost' => $totalCost,
                    'initials' => $initials,
                ], true);

                if (!$registerId) {
                    throw new RuntimeException('Failed to add materials to the daily register.');
                }

                $updated = $db->table('raw_materials')
                    ->where('branchId', $branchId)
                    ->where('materialId', $materialId)
                    ->where('Quantity >=', $quantity)
                    ->set('Quantity', 'Quantity - ' . $quantity, false)
                    ->update();

                if (!$updated || $db->affectedRows() !== 1) {
                    throw new RuntimeException("Insufficient stock for: {$rawMaterial['name']}. Requested: {$quantity}, Available: {$rawMaterial['Quantity']}");
                }

                $updatedMaterial = $this->rawMaterialsModel->find($materialId);
                $this->stockLedger->recordRawMaterialMovement(
                    $branchId,
                    $materialId,
                    'production_out',
                    0,
                    $quantity,
                    (float) $updatedMaterial['Quantity'],
                    (float) $rawMaterial['unitPrice'],
                    'daily_rawmaterials_register',
                    $registerId,
                    'RMR-' . $registerId,
                    $userId
                );

                $this->auditLog->record(
                    'raw_material.consumed',
                    'daily_rawmaterials_register',
                    $registerId,
                    $rawMaterial,
                    $updatedMaterial,
                    $userId,
                    $branchId,
                    ['quantity_out' => $quantity, 'total_cost' => $totalCost]
                );

                $createdIds[] = $registerId;
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Raw material register transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Raw material register creation failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'RawMaterialRegisterFailed',
                'message' => $e->getMessage()
            ], 409);
        }

        $payload = [
            'rawMatrialId' => $createdIds,
            'message' => 'Raw material List created'
        ];

        $pusher = get_pusher();
        $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-created', $payload);
    
        return $this->respond([
            'status' => true,
            'error' => null,
            'ids' => $createdIds,
            'message' => 'Raw materials successfully added to the daily list.'
        ]);
    }
    

    /**
    * Return the editable properties of a resource object
    *
    * @return mixed
    */

    public function edit( $id = null )
 {
    }

    /**
    * Add or update a model resource, from 'posted' properties
    *
    * @return mixed
    */

    public function update($id = null)
    {
        $userId = (int) auth()->id();
        $id = trim($this->request->getVar('id'));
        $dailyRawMaterial = $this->rawMaterialsRegister->find($id);

        if (!$id || !$dailyRawMaterial) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing raw material list ID.'
            ]);
        }
        if (!$this->branchContext->recordMatchesCurrentBranch($dailyRawMaterial)) {
            return $this->respond(['status' => false, 'message' => 'This list item is outside your current branch scope.'], 403);
        }

        $branchId = (int) ($dailyRawMaterial['branchId'] ?? 0);
        $oldMaterialId = (int) $dailyRawMaterial['materialId'];
        $newMaterialId = (int) ($this->request->getVar('materialId') ?: $oldMaterialId);
        $requestedQty = (float) $this->request->getVar('quantity');
        $oldQty = (float) $dailyRawMaterial['quantity'];
        $oldRawMaterial = $this->rawMaterialsModel->find($oldMaterialId);
        $newRawMaterial = $this->rawMaterialsModel->find($newMaterialId);

        if ($requestedQty <= 0) {
            return $this->respond([
                'status' => false,
                'error' => 'InvalidQuantity',
                'message' => 'Quantity must be greater than zero.'
            ], 400);
        }
        if (!$oldRawMaterial || !$this->branchContext->recordMatchesCurrentBranch($oldRawMaterial)) {
            return $this->respond(['status' => false, 'message' => 'The original raw material is outside your current branch scope.'], 403);
        }
        if (!$newRawMaterial || !$this->branchContext->recordMatchesCurrentBranch($newRawMaterial)) {
            return $this->respond(['status' => false, 'message' => 'This raw material is outside your current branch scope.'], 403);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            if (!$this->rawMaterialsRegister->update($id, [
                'branchId' => $branchId,
                'materialId' => $newMaterialId,
                'quantity' => $requestedQty,
                'totalCost' => round((float) $newRawMaterial['unitPrice'] * $requestedQty, 2),
                'initials' => $this->request->getVar('initials'),
            ])) {
                throw new RuntimeException('Daily list update failed.');
            }

            if ($oldMaterialId !== $newMaterialId) {
                $restored = $db->table('raw_materials')
                    ->where('branchId', $branchId)
                    ->where('materialId', $oldMaterialId)
                    ->set('Quantity', 'Quantity + ' . $oldQty, false)
                    ->update();

                if (!$restored || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Could not restore the original raw material stock.');
                }

                $deducted = $db->table('raw_materials')
                    ->where('branchId', $branchId)
                    ->where('materialId', $newMaterialId)
                    ->where('Quantity >=', $requestedQty)
                    ->set('Quantity', 'Quantity - ' . $requestedQty, false)
                    ->update();

                if (!$deducted || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Not enough stock in the store.');
                }

                $restoredMaterial = $this->rawMaterialsModel->find($oldMaterialId);
                $updatedMaterial = $this->rawMaterialsModel->find($newMaterialId);
                $this->stockLedger->recordRawMaterialMovement($branchId, $oldMaterialId, 'production_out_reversal', $oldQty, 0, (float) $restoredMaterial['Quantity'], (float) $oldRawMaterial['unitPrice'], 'daily_rawmaterials_register', $id, 'RMR-' . $id, $userId);
                $this->stockLedger->recordRawMaterialMovement($branchId, $newMaterialId, 'production_out', 0, $requestedQty, (float) $updatedMaterial['Quantity'], (float) $newRawMaterial['unitPrice'], 'daily_rawmaterials_register', $id, 'RMR-' . $id, $userId);
            } else {
                $diff = $requestedQty - $oldQty;
                if ($diff > 0) {
                    $updated = $db->table('raw_materials')
                        ->where('branchId', $branchId)
                        ->where('materialId', $newMaterialId)
                        ->where('Quantity >=', $diff)
                        ->set('Quantity', 'Quantity - ' . $diff, false)
                        ->update();

                    if (!$updated || $db->affectedRows() !== 1) {
                        throw new RuntimeException('Not enough stock in the store.');
                    }

                    $updatedMaterial = $this->rawMaterialsModel->find($newMaterialId);
                    $this->stockLedger->recordRawMaterialMovement($branchId, $newMaterialId, 'production_out_adjustment', 0, $diff, (float) $updatedMaterial['Quantity'], (float) $newRawMaterial['unitPrice'], 'daily_rawmaterials_register', $id, 'RMR-' . $id, $userId);
                } elseif ($diff < 0) {
                    $returnedQty = abs($diff);
                    $updated = $db->table('raw_materials')
                        ->where('branchId', $branchId)
                        ->where('materialId', $newMaterialId)
                        ->set('Quantity', 'Quantity + ' . $returnedQty, false)
                        ->update();

                    if (!$updated || $db->affectedRows() !== 1) {
                        throw new RuntimeException('Could not restore raw material stock.');
                    }

                    $updatedMaterial = $this->rawMaterialsModel->find($newMaterialId);
                    $this->stockLedger->recordRawMaterialMovement($branchId, $newMaterialId, 'production_out_reversal', $returnedQty, 0, (float) $updatedMaterial['Quantity'], (float) $newRawMaterial['unitPrice'], 'daily_rawmaterials_register', $id, 'RMR-' . $id, $userId);
                }
            }

            $updatedRegister = $this->rawMaterialsRegister->find($id);
            $this->auditLog->record('raw_material_register.updated', 'daily_rawmaterials_register', $id, $dailyRawMaterial, $updatedRegister, $userId, $branchId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Daily list update transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Raw material register update failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'DailyListUpdateFailed',
                'message' => $e->getMessage()
            ], 409);
        }

        $pusher = get_pusher();
        $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-updated', [
            'rawMatrialId' => $newMaterialId,
            'message' => 'Raw material List updated'
        ]);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Success!! Daily list has been updated'
        ]);
    }

    /**
    * Delete the designated resource object from the model
    *
    * @return mixed
    */

    public function delete($id = null)
    {
        $userId = (int) auth()->id();
        $id = trim($this->request->getVar('id'));
        $dailyRawMaterial = $this->rawMaterialsRegister->find($id);

        if (!$id || !$dailyRawMaterial) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing daily list ID.'
            ]);
        }

        $rawMaterial = $this->rawMaterialsModel->find($dailyRawMaterial['materialId']);
        if (!$this->branchContext->recordMatchesCurrentBranch($dailyRawMaterial) || !$this->branchContext->recordMatchesCurrentBranch($rawMaterial)) {
            return $this->respond(['status' => false, 'message' => 'This list item is outside your current branch scope.'], 403);
        }

        $branchId = (int) $dailyRawMaterial['branchId'];
        $dailyRawMaterialQty = (float) ($dailyRawMaterial['quantity'] ?? 0);
        $db = db_connect();

        try {
            $db->transBegin();

            if (!$this->rawMaterialsRegister->delete($id)) {
                throw new RuntimeException('Daily list has not been deleted.');
            }

            $updated = $db->table('raw_materials')
                ->where('branchId', $branchId)
                ->where('materialId', $rawMaterial['materialId'])
                ->set('Quantity', 'Quantity + ' . $dailyRawMaterialQty, false)
                ->update();

            if (!$updated || $db->affectedRows() !== 1) {
                throw new RuntimeException('Could not restore raw material stock.');
            }

            $updatedMaterial = $this->rawMaterialsModel->find($rawMaterial['materialId']);
            $this->stockLedger->recordRawMaterialMovement(
                $branchId,
                (int) $rawMaterial['materialId'],
                'production_out_reversal',
                $dailyRawMaterialQty,
                0,
                (float) $updatedMaterial['Quantity'],
                (float) $rawMaterial['unitPrice'],
                'daily_rawmaterials_register',
                $id,
                'RMR-' . $id,
                $userId
            );

            $this->auditLog->record('raw_material_register.deleted', 'daily_rawmaterials_register', $id, $dailyRawMaterial, null, $userId, $branchId);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Daily list delete transaction failed.');
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Raw material register delete failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'DailyListDeleteFailed',
                'message' => $e->getMessage()
            ], 409);
        }

        $pusher = get_pusher();
        $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-deleted', [
            'rawMatrialId' => $rawMaterial,
            'message' => 'Raw material List deleted'
        ]);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Success!! Selected raw material has been removed from the list.'
        ]);
    }

    // validate daily list form entries

    private function validateCategoryEntries() {
        return $this->validate( [
            'materialId' => [
                'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim'
            ]
        ] );
    }
}
