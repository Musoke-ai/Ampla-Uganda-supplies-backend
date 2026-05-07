<?php

namespace App\Services;

use App\Models\StockMovement;

class StockLedgerService
{
    private StockMovement $movementModel;

    public function __construct()
    {
        $this->movementModel = new StockMovement();
    }

    public function recordProductMovement(
        int $branchId,
        int $productId,
        string $movementType,
        float $quantityIn,
        float $quantityOut,
        float $balanceAfter,
        ?float $unitCost,
        string $referenceType,
        $referenceId,
        string $referenceNo,
        int $userId
    ): bool {
        return (bool) $this->movementModel->insert([
            'branchId' => $branchId,
            'productId' => $productId,
            'rawMaterialId' => null,
            'movementType' => $movementType,
            'quantityIn' => $quantityIn,
            'quantityOut' => $quantityOut,
            'balanceAfter' => $balanceAfter,
            'unitCost' => $unitCost,
            'referenceType' => $referenceType,
            'referenceId' => (string) $referenceId,
            'referenceNo' => $referenceNo,
            'userId' => $userId,
        ]);
    }

    public function recordRawMaterialMovement(
        int $branchId,
        int $rawMaterialId,
        string $movementType,
        float $quantityIn,
        float $quantityOut,
        float $balanceAfter,
        ?float $unitCost,
        string $referenceType,
        $referenceId,
        string $referenceNo,
        int $userId
    ): bool {
        return (bool) $this->movementModel->insert([
            'branchId' => $branchId,
            'productId' => null,
            'rawMaterialId' => $rawMaterialId,
            'movementType' => $movementType,
            'quantityIn' => $quantityIn,
            'quantityOut' => $quantityOut,
            'balanceAfter' => $balanceAfter,
            'unitCost' => $unitCost,
            'referenceType' => $referenceType,
            'referenceId' => (string) $referenceId,
            'referenceNo' => $referenceNo,
            'userId' => $userId,
        ]);
    }
}
