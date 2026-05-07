<?php

namespace App\Services;

use App\Models\Inventory;
use InvalidArgumentException;

class SaleCalculationService
{
    private Inventory $inventoryModel;

    public function __construct()
    {
        $this->inventoryModel = new Inventory();
    }

    public function calculate(array $items, $saleDetails, int $branchId): array
    {
        if (empty($items)) {
            throw new InvalidArgumentException('Items list is empty.');
        }

        $details = is_array($saleDetails) ? $saleDetails : (array) $saleDetails;
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $itemData = is_array($item) ? $item : (array) $item;
            $productId = (int) ($itemData['saleItemId'] ?? 0);
            $quantity = (float) ($itemData['saleQuantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Each sale item must have a valid product and positive quantity.');
            }

            $product = $this->inventoryModel->find($productId);
            if (!$product || (int) ($product['branchId'] ?? 0) !== $branchId) {
                throw new InvalidArgumentException('One or more sale items do not belong to the active branch.');
            }

            $minimumPrice = (float) ($product['itemLeastPrice'] ?? 0);
            $submittedPrice = isset($itemData['salePrice']) && is_numeric($itemData['salePrice'])
                ? (float) $itemData['salePrice']
                : $minimumPrice;

            if ($submittedPrice < $minimumPrice) {
                throw new InvalidArgumentException('Sale price cannot be below the configured minimum price.');
            }

            $lineTotal = $submittedPrice * $quantity;
            $subtotal += $lineTotal;
            $lines[] = [
                'product' => $product,
                'productId' => $productId,
                'quantity' => $quantity,
                'unitPrice' => $submittedPrice,
                'unitCost' => isset($product['itemStockPrice']) ? (float) $product['itemStockPrice'] : null,
                'custId' => $itemData['custId'] ?? ($details['custId'] ?? null),
                'lineTotal' => $lineTotal,
            ];
        }

        $discount = isset($details['discount']) && is_numeric($details['discount']) ? (float) $details['discount'] : 0.0;
        $amountPaid = isset($details['tenderedAmount']) && is_numeric($details['tenderedAmount'])
            ? (float) $details['tenderedAmount']
            : 0.0;

        if ($discount < 0 || $discount > $subtotal) {
            throw new InvalidArgumentException('Discount must be between zero and the sale subtotal.');
        }

        $total = round($subtotal - $discount, 2);

        if ($amountPaid < 0 || $amountPaid > $total) {
            throw new InvalidArgumentException('Amount paid must be between zero and the calculated sale total.');
        }

        return [
            'lines' => $lines,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => $total,
            'amountPaid' => round($amountPaid, 2),
            'dueAmount' => round($total - $amountPaid, 2),
            'paymentMethod' => $details['paymentMethod'] ?? null,
            'moreInfo' => $details['moreInfo'] ?? null,
            'custId' => $details['custId'] ?? null,
            'endDate' => $details['endDate'] ?? null,
        ];
    }
}
