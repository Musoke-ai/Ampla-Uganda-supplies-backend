<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Categories;
use App\Models\CustomerModel;
use App\Models\History;
use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Models\ImportRow;
use App\Models\Indebt;
use App\Models\Inventory;
use App\Models\Receipt;
use App\Models\Sales;
use App\Models\Statistics;
use App\Models\Stock;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImportService
{
    private const SUPPORTED_TYPES = ['products', 'customers', 'stock', 'sales'];
    private const MAX_ROWS = 5000;

    private BaseConnection $db;
    private ImportBatch $batchModel;
    private ImportRow $rowModel;
    private ImportMapping $mappingModel;
    private Inventory $inventoryModel;
    private CustomerModel $customerModel;
    private Categories $categoryModel;
    private Stock $stockModel;
    private Sales $salesModel;
    private Receipt $receiptModel;
    private Indebt $indebtModel;
    private History $historyModel;
    private Statistics $statisticsModel;
    private StockLedgerService $stockLedger;
    private AuditLogService $auditLog;

    public function __construct()
    {
        $this->db = db_connect();
        $this->batchModel = new ImportBatch();
        $this->rowModel = new ImportRow();
        $this->mappingModel = new ImportMapping();
        $this->inventoryModel = new Inventory();
        $this->customerModel = new CustomerModel();
        $this->categoryModel = new Categories();
        $this->stockModel = new Stock();
        $this->salesModel = new Sales();
        $this->receiptModel = new Receipt();
        $this->indebtModel = new Indebt();
        $this->historyModel = new History();
        $this->statisticsModel = new Statistics();
        $this->stockLedger = new StockLedgerService();
        $this->auditLog = new AuditLogService();
    }

    public function createBatch(
        string $type,
        ?string $fileName,
        array $headers,
        array $rows,
        ?int $branchId,
        int $userId
    ): array {
        $type = $this->normalizeType($type);

        if (empty($rows)) {
            throw new InvalidArgumentException('The selected file does not contain importable rows.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException('This importer accepts up to ' . self::MAX_ROWS . ' rows per batch.');
        }

        $this->db->transBegin();

        try {
            $batchId = $this->batchModel->insert([
                'branchId' => $branchId,
                'userId' => $userId,
                'importType' => $type,
                'fileName' => $fileName ?: 'uploaded-file',
                'status' => 'uploaded',
                'totalRows' => count($rows),
                'headers' => json_encode(array_values($headers)),
                'summary' => json_encode([
                    'message' => 'Rows uploaded and waiting for column mapping.',
                ]),
            ], true);

            if (!$batchId) {
                throw new RuntimeException('The import batch could not be created.');
            }

            $payload = [];
            foreach (array_values($rows) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $payload[] = [
                    'importBatchId' => $batchId,
                    'rowNumber' => $index + 2,
                    'rawData' => json_encode($this->sanitizeRow($row)),
                    'status' => 'pending',
                ];
            }

            foreach (array_chunk($payload, 250) as $chunk) {
                $this->rowModel->insertBatch($chunk);
            }

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('The import batch could not be stored.');
            }

            $this->db->transCommit();

            return $this->getBatchWithRows((int) $batchId, $userId, false);
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function validateBatch(int $batchId, array $mapping, array $options, int $userId): array
    {
        $batch = $this->requireOwnedBatch($batchId, $userId);
        $type = $this->normalizeType($batch['importType']);
        $rows = $this->rowModel
            ->where('importBatchId', $batchId)
            ->orderBy('rowNumber', 'ASC')
            ->findAll();

        $options = $this->mergeOptions($options);
        $summary = [
            'ready' => 0,
            'warnings' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];
        $seen = [];

        foreach ($rows as $row) {
            $raw = $this->decodeJson($row['rawData']);
            $result = $this->validateRow($type, $raw, $mapping, $options, $batch, $seen);
            $status = empty($result['errors'])
                ? (empty($result['warnings']) ? 'ready' : 'warning')
                : 'error';

            if (($result['normalized']['action'] ?? null) === 'skip') {
                $summary['skipped']++;
            }

            if ($status === 'ready') {
                $summary['ready']++;
            } elseif ($status === 'warning') {
                $summary['warnings']++;
            } else {
                $summary['errors']++;
            }

            $this->rowModel->update($row['id'], [
                'normalizedData' => json_encode($result['normalized']),
                'status' => $status,
                'errors' => json_encode($result['errors']),
                'warnings' => json_encode($result['warnings']),
            ]);
        }

        $this->batchModel->update($batchId, [
            'status' => $summary['errors'] > 0 ? 'validated_with_errors' : 'validated',
            'validRows' => $summary['ready'],
            'warningRows' => $summary['warnings'],
            'errorRows' => $summary['errors'],
            'skippedRows' => $summary['skipped'],
            'mapping' => json_encode($mapping),
            'options' => json_encode($options),
            'summary' => json_encode($summary),
        ]);

        $this->saveMapping($userId, $type, $batch['fileName'] ?? 'Import Mapping', $batch['headers'] ?? null, $mapping, $options);

        return $this->getBatchWithRows($batchId, $userId);
    }

    public function confirmBatch(int $batchId, int $userId): array
    {
        $batch = $this->requireOwnedBatch($batchId, $userId);
        $type = $this->normalizeType($batch['importType']);

        if (!in_array($batch['status'], ['validated', 'validated_with_errors'], true)) {
            throw new InvalidArgumentException('Validate this import before confirming it.');
        }

        $rows = $this->rowModel
            ->where('importBatchId', $batchId)
            ->whereIn('status', ['ready', 'warning'])
            ->orderBy('rowNumber', 'ASC')
            ->findAll();

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $this->db->transBegin();

        try {
            $context = $type === 'sales' ? $this->prepareSalesImportContext($rows) : [];

            foreach ($rows as $row) {
                $normalized = $this->decodeJson($row['normalizedData']);

                if (($normalized['action'] ?? 'create') === 'skip') {
                    $skipped++;
                    $this->rowModel->update($row['id'], ['status' => 'skipped']);
                    continue;
                }

                try {
                    $entity = $this->importRow($type, $normalized, $batch, $userId, (int) $row['rowNumber'], $context);
                    $imported++;
                    $this->rowModel->update($row['id'], [
                        'status' => 'imported',
                        'createdEntityType' => $entity['type'],
                        'createdEntityId' => (string) $entity['id'],
                    ]);
                } catch (Throwable $rowError) {
                    $failed++;
                    $this->rowModel->update($row['id'], [
                        'status' => 'failed',
                        'errors' => json_encode([$rowError->getMessage()]),
                    ]);
                }
            }

            if ($failed > 0) {
                throw new RuntimeException('One or more rows failed during import. No live records were changed.');
            }

            $this->batchModel->update($batchId, [
                'status' => 'completed',
                'importedRows' => $imported,
                'skippedRows' => ((int) ($batch['skippedRows'] ?? 0)) + $skipped,
                'confirmedAt' => date('Y-m-d H:i:s'),
                'summary' => json_encode([
                    'imported' => $imported,
                    'skipped' => ((int) ($batch['skippedRows'] ?? 0)) + $skipped,
                    'failed' => 0,
                ]),
            ]);

            if ($this->db->transStatus() === false) {
                throw new RuntimeException('The import transaction failed.');
            }

            $this->db->transCommit();

            return $this->getBatchWithRows($batchId, $userId);
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->batchModel->update($batchId, [
                'status' => 'failed',
                'summary' => json_encode([
                    'imported' => 0,
                    'skipped' => $skipped,
                    'failed' => $failed,
                    'message' => $e->getMessage(),
                ]),
            ]);
            throw $e;
        }
    }

    public function getHistory(int $userId, int $limit = 25): array
    {
        return $this->batchModel
            ->where('userId', $userId)
            ->orderBy('id', 'DESC')
            ->findAll($limit);
    }

    public function getBatchWithRows(int $batchId, int $userId, bool $includeRows = true): array
    {
        $batch = $this->requireOwnedBatch($batchId, $userId);
        $batch = $this->hydrateBatch($batch);

        if ($includeRows) {
            $batch['rows'] = array_map(
                fn ($row) => $this->hydrateRow($row),
                $this->rowModel
                    ->where('importBatchId', $batchId)
                    ->orderBy('rowNumber', 'ASC')
                    ->findAll(250)
            );
        }

        return $batch;
    }

    public function getSavedMappings(int $userId, ?string $type = null): array
    {
        $builder = $this->mappingModel->where('userId', $userId);
        if ($type !== null) {
            $builder->where('importType', $this->normalizeType($type));
        }

        return array_map(function ($mapping) {
            $mapping['headers'] = $this->decodeJson($mapping['headers']);
            $mapping['mapping'] = $this->decodeJson($mapping['mapping']);
            $mapping['options'] = $this->decodeJson($mapping['options']);
            return $mapping;
        }, $builder->orderBy('id', 'DESC')->findAll(10));
    }

    public function updateRowRawData(int $batchId, int $rowId, array $rawData, int $userId): array
    {
        $batch = $this->requireOwnedBatch($batchId, $userId);
        if (in_array($batch['status'], ['completed', 'failed'], true)) {
            throw new InvalidArgumentException('Completed or failed imports cannot be edited.');
        }

        $row = $this->rowModel
            ->where('importBatchId', $batchId)
            ->where('id', $rowId)
            ->first();

        if (!$row) {
            throw new InvalidArgumentException('Import row was not found.');
        }

        $this->rowModel->update($rowId, [
            'rawData' => json_encode($this->sanitizeRow($rawData)),
            'normalizedData' => null,
            'status' => 'pending',
            'errors' => json_encode([]),
            'warnings' => json_encode([]),
            'createdEntityType' => null,
            'createdEntityId' => null,
        ]);

        $this->batchModel->update($batchId, [
            'status' => 'uploaded',
            'validRows' => 0,
            'warningRows' => 0,
            'errorRows' => 0,
            'skippedRows' => 0,
            'summary' => json_encode([
                'message' => 'One row was edited. Validate again before confirming.',
            ]),
        ]);

        return $this->getBatchWithRows($batchId, $userId);
    }

    private function validateRow(
        string $type,
        array $raw,
        array $mapping,
        array $options,
        array $batch,
        array &$seen
    ): array {
        if ($type === 'products') {
            return $this->validateProduct($raw, $mapping, $options, $batch, $seen);
        }

        if ($type === 'customers') {
            return $this->validateCustomer($raw, $mapping, $options, $batch, $seen);
        }

        if ($type === 'sales') {
            return $this->validateSale($raw, $mapping, $options, $batch, $seen);
        }

        return $this->validateStock($raw, $mapping, $options, $batch, $seen);
    }

    private function validateProduct(array $raw, array $mapping, array $options, array $batch, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $branchId = $this->branchId($batch);
        $name = $this->mappedString($raw, $mapping, 'name');
        $sku = $this->mappedString($raw, $mapping, 'sku');
        $barcode = $this->mappedString($raw, $mapping, 'barcode');
        $categoryName = $this->mappedString($raw, $mapping, 'category') ?: 'Uncategorized';
        $quantity = $this->mappedNumber($raw, $mapping, 'quantity', 0);
        $costPrice = $this->mappedNumber($raw, $mapping, 'costPrice', 0);
        $sellingPrice = $this->mappedNumber($raw, $mapping, 'sellingPrice', 0);

        if ($name === '') {
            $errors[] = 'Product name is required.';
        }
        if ($quantity < 0) {
            $errors[] = 'Opening quantity cannot be negative.';
        }
        if ($costPrice < 0 || $sellingPrice < 0) {
            $errors[] = 'Prices cannot be negative.';
        }

        $fingerprint = strtolower($name . '|' . $sku . '|' . $barcode);
        if ($fingerprint !== '||' && isset($seen['products'][$fingerprint])) {
            $errors[] = 'This product appears more than once in the file.';
        }
        $seen['products'][$fingerprint] = true;

        $existing = $this->findExistingProduct($branchId, $name, $sku, $barcode);
        $action = 'create';
        if ($existing) {
            $strategy = $options['duplicateStrategy'] ?? 'skip';
            if ($strategy === 'update') {
                $action = 'update';
                $warnings[] = 'Existing product will be updated.';
            } else {
                $action = 'skip';
                $warnings[] = 'Existing product will be skipped.';
            }
        }

        $category = $this->categoryModel
            ->where('LOWER(categoryName)', strtolower($categoryName))
            ->first();
        if (!$category && empty($options['createMissingCategories'])) {
            $errors[] = 'Category "' . $categoryName . '" does not exist.';
        } elseif (!$category) {
            $warnings[] = 'Category "' . $categoryName . '" will be created.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized' => [
                'action' => $action,
                'existingId' => $existing['itemId'] ?? null,
                'branchId' => $branchId,
                'name' => $name,
                'categoryName' => $categoryName,
                'categoryId' => $category['categoryId'] ?? null,
                'model' => $this->mappedString($raw, $mapping, 'model') ?: 'Generic',
                'sku' => $sku ?: null,
                'barcode' => $barcode ?: null,
                'brand' => $this->mappedString($raw, $mapping, 'brand') ?: null,
                'productType' => $this->mappedString($raw, $mapping, 'productType') ?: 'purchased',
                'unit' => $this->mappedString($raw, $mapping, 'unit') ?: 'pcs',
                'supplier' => $this->mappedString($raw, $mapping, 'supplier') ?: null,
                'reorderLevel' => $this->mappedNumber($raw, $mapping, 'reorderLevel', 0),
                'quality' => $this->mappedString($raw, $mapping, 'quality') ?: 'Original',
                'quantity' => $quantity,
                'condition' => $this->mappedString($raw, $mapping, 'condition') ?: 'New',
                'size' => $this->mappedString($raw, $mapping, 'size') ?: 'Variable',
                'costPrice' => $costPrice,
                'sellingPrice' => $sellingPrice,
                'wholesalePrice' => $this->mappedNumber($raw, $mapping, 'wholesalePrice', null),
                'notes' => $this->mappedString($raw, $mapping, 'notes') ?: 'Imported product',
            ],
        ];
    }

    private function validateCustomer(array $raw, array $mapping, array $options, array $batch, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $branchId = $this->branchId($batch);
        $name = $this->mappedString($raw, $mapping, 'name');
        $phone = $this->mappedString($raw, $mapping, 'phone');
        $email = $this->mappedString($raw, $mapping, 'email');

        if ($name === '') {
            $errors[] = 'Customer name is required.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = 'Customer email does not look valid.';
        }

        $fingerprint = strtolower($name . '|' . $phone);
        if ($fingerprint !== '|' && isset($seen['customers'][$fingerprint])) {
            $errors[] = 'This customer appears more than once in the file.';
        }
        $seen['customers'][$fingerprint] = true;

        $existing = $this->findExistingCustomer($branchId, $name, $phone);
        $action = 'create';
        if ($existing) {
            $strategy = $options['duplicateStrategy'] ?? 'skip';
            if ($strategy === 'update') {
                $action = 'update';
                $warnings[] = 'Existing customer will be updated.';
            } else {
                $action = 'skip';
                $warnings[] = 'Existing customer will be skipped.';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized' => [
                'action' => $action,
                'existingId' => $existing['custId'] ?? null,
                'branchId' => $branchId,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'location' => $this->mappedString($raw, $mapping, 'location'),
            ],
        ];
    }

    private function validateStock(array $raw, array $mapping, array $options, array $batch, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $branchId = $this->branchId($batch);
        $name = $this->mappedString($raw, $mapping, 'productName');
        $sku = $this->mappedString($raw, $mapping, 'sku');
        $barcode = $this->mappedString($raw, $mapping, 'barcode');
        $quantity = $this->mappedNumber($raw, $mapping, 'quantity', null);

        if ($name === '' && $sku === '' && $barcode === '') {
            $errors[] = 'Product name, SKU, or barcode is required.';
        }
        if ($quantity === null || $quantity <= 0) {
            $errors[] = 'Stock quantity must be greater than zero.';
        }

        $product = $this->findExistingProduct($branchId, $name, $sku, $barcode);
        if (!$product) {
            $errors[] = 'Matching product was not found in the selected branch.';
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized' => [
                'action' => 'create',
                'branchId' => $branchId,
                'productId' => $product['itemId'] ?? null,
                'productName' => $product['itemName'] ?? $name,
                'oldQuantity' => isset($product['itemQuantity']) ? (float) $product['itemQuantity'] : 0,
                'quantity' => $quantity,
                'costPrice' => $this->mappedNumber($raw, $mapping, 'costPrice', null),
                'sellingPrice' => $this->mappedNumber($raw, $mapping, 'sellingPrice', null),
                'supplier' => $this->mappedString($raw, $mapping, 'supplier') ?: 'import',
            ],
        ];
    }

    private function validateSale(array $raw, array $mapping, array $options, array $batch, array &$seen): array
    {
        $errors = [];
        $warnings = [];
        $branchId = $this->branchId($batch);
        $name = $this->mappedString($raw, $mapping, 'productName');
        $sku = $this->mappedString($raw, $mapping, 'sku');
        $barcode = $this->mappedString($raw, $mapping, 'barcode');
        $quantity = $this->mappedNumber($raw, $mapping, 'quantity', null);
        $unitPrice = $this->mappedNumber($raw, $mapping, 'unitPrice', null);
        $discountRaw = $this->mappedString($raw, $mapping, 'discount');
        $amountPaidRaw = $this->mappedString($raw, $mapping, 'amountPaid');
        $discount = $this->mappedNumber($raw, $mapping, 'discount', 0);
        $amountPaid = $this->mappedNumber($raw, $mapping, 'amountPaid', null);
        $customerName = $this->mappedString($raw, $mapping, 'customerName');
        $customerPhone = $this->mappedString($raw, $mapping, 'customerPhone');
        $receiptNo = $this->mappedString($raw, $mapping, 'receiptNo');
        $saleDate = $this->mappedDate($raw, $mapping, 'saleDate');
        $adjustInventory = array_key_exists('adjustInventory', $options) ? (bool) $options['adjustInventory'] : true;

        if ($name === '' && $sku === '' && $barcode === '') {
            $errors[] = 'Product name, SKU, or barcode is required.';
        }
        if ($quantity === null || $quantity <= 0) {
            $errors[] = 'Sale quantity must be greater than zero.';
        }

        $product = $this->findExistingProduct($branchId, $name, $sku, $barcode);
        if (!$product) {
            $errors[] = 'Matching product was not found in the selected branch.';
        } else {
            if ($unitPrice === null) {
                $unitPrice = (float) ($product['itemLeastPrice'] ?? 0);
                $warnings[] = 'Unit price is missing, so the product selling price will be used.';
            }

            if ($unitPrice < 0) {
                $errors[] = 'Sale price cannot be negative.';
            }

            $minimumPrice = (float) ($product['itemLeastPrice'] ?? 0);
            if ($unitPrice < $minimumPrice) {
                $warnings[] = 'Sale price is below the current product selling price.';
            }

            if ($adjustInventory && $quantity !== null && (float) ($product['itemQuantity'] ?? 0) < $quantity) {
                $errors[] = 'Available stock is lower than the imported sale quantity.';
            }
        }

        if ($discount < 0) {
            $errors[] = 'Discount cannot be negative.';
        }

        $grossTotal = max(0, (float) $quantity) * max(0, (float) $unitPrice);
        if ($receiptNo === '' && $discount > $grossTotal) {
            $errors[] = 'Discount cannot exceed the sale subtotal.';
        }
        if ($receiptNo !== '' && $discount > $grossTotal) {
            $warnings[] = 'Receipt-level discount will be checked against all rows with this receipt reference.';
        }

        $netTotal = round(max(0, $grossTotal - max(0, (float) $discount)), 2);
        $amountPaid = $amountPaid === null ? $netTotal : (float) $amountPaid;
        if ($receiptNo === '' && ($amountPaid < 0 || $amountPaid > $netTotal)) {
            $errors[] = 'Amount paid must be between zero and the net sale total.';
        }
        if ($receiptNo !== '' && $amountPaid > $netTotal) {
            $warnings[] = 'Receipt-level amount paid will be checked against all rows with this receipt reference.';
        }

        $customer = null;
        if ($customerName !== '' || $customerPhone !== '') {
            $customer = $this->findExistingCustomer($branchId, $customerName, $customerPhone);
            if (!$customer) {
                $errors[] = 'Matching customer was not found in the selected branch.';
            }
        }

        $dueAmount = round($netTotal - $amountPaid, 2);
        if ($dueAmount > 0 && !$customer) {
            $errors[] = 'Credit sales require a matching customer.';
        }

        $action = 'create';
        if ($receiptNo !== '') {
            $fingerprint = strtolower($receiptNo . '|' . ($product['itemId'] ?? $name));
            if (isset($seen['sales'][$fingerprint])) {
                $errors[] = 'This receipt and product line appears more than once in the file.';
            }
            $seen['sales'][$fingerprint] = true;

            if ($this->findReceiptByCode($branchId, $receiptNo)) {
                $action = 'skip';
                $warnings[] = 'A receipt with this reference already exists and will be skipped.';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized' => [
                'action' => $action,
                'branchId' => $branchId,
                'productId' => $product['itemId'] ?? null,
                'productName' => $product['itemName'] ?? $name,
                'oldQuantity' => isset($product['itemQuantity']) ? (float) $product['itemQuantity'] : 0,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'unitCost' => isset($product['itemStockPrice']) ? (float) $product['itemStockPrice'] : null,
                'lineCost' => isset($product['itemStockPrice']) && $quantity !== null
                    ? round((float) $product['itemStockPrice'] * (float) $quantity, 2)
                    : null,
                'customerId' => $customer['custId'] ?? null,
                'customerName' => $customer['custName'] ?? $customerName,
                'receiptNo' => $receiptNo,
                'receiptKey' => $receiptNo !== '' ? $this->saleReceiptKey($receiptNo) : null,
                'saleDate' => $saleDate,
                'discount' => (float) $discount,
                'discountProvided' => $discountRaw !== '',
                'amountPaid' => $amountPaid,
                'amountPaidProvided' => $amountPaidRaw !== '',
                'dueAmount' => $dueAmount,
                'paymentMethod' => $this->mappedString($raw, $mapping, 'paymentMethod') ?: ($dueAmount > 0 ? 'credit' : 'cash'),
                'notes' => $this->mappedString($raw, $mapping, 'notes') ?: 'Imported sale',
                'adjustInventory' => $adjustInventory,
            ],
        ];
    }

    private function importRow(string $type, array $normalized, array $batch, int $userId, int $rowNumber, array &$context = []): array
    {
        if ($type === 'products') {
            return $this->importProduct($normalized, $batch, $userId, $rowNumber);
        }

        if ($type === 'customers') {
            return $this->importCustomer($normalized, $batch, $userId, $rowNumber);
        }

        if ($type === 'sales') {
            return $this->importSale($normalized, $batch, $userId, $rowNumber, $context);
        }

        return $this->importStock($normalized, $batch, $userId, $rowNumber);
    }

    private function importProduct(array $data, array $batch, int $userId, int $rowNumber): array
    {
        $categoryId = $data['categoryId'] ?: $this->ensureCategory($data['categoryName']);
        $payload = [
            'branchId' => $data['branchId'],
            'itemName' => $data['name'],
            'itemCategoryId' => $categoryId,
            'itemModel' => $data['model'],
            'itemSku' => $data['sku'],
            'itemBarcode' => $data['barcode'],
            'itemBrand' => $data['brand'],
            'itemProductType' => $data['productType'],
            'itemUnit' => $data['unit'],
            'itemSupplier' => $data['supplier'],
            'itemReorderLevel' => $data['reorderLevel'],
            'itemQuality' => $data['quality'],
            'itemQuantity' => $data['quantity'],
            'itemCondition' => $data['condition'],
            'itemSize' => $data['size'],
            'itemStockPrice' => $data['costPrice'],
            'itemLeastPrice' => $data['sellingPrice'],
            'itemWholesalePrice' => $data['wholesalePrice'],
            'itemNotes' => $data['notes'],
            'itemOwner' => $userId,
        ];

        if (($data['action'] ?? 'create') === 'update' && !empty($data['existingId'])) {
            $before = $this->inventoryModel->find($data['existingId']);
            $this->inventoryModel->update($data['existingId'], $payload);
            $productId = (int) $data['existingId'];
            $after = $this->inventoryModel->find($productId);
            $this->auditLog->record('import.product_updated', 'inventory', $productId, $before, $after, $userId, $data['branchId'], [
                'batch_id' => $batch['id'],
                'row_number' => $rowNumber,
            ]);
        } else {
            $productId = (int) $this->inventoryModel->insert($payload, true);
            if (!$productId) {
                throw new RuntimeException('Product could not be imported.');
            }
            $after = $this->inventoryModel->find($productId);
            $this->auditLog->record('import.product_created', 'inventory', $productId, null, $after, $userId, $data['branchId'], [
                'batch_id' => $batch['id'],
                'row_number' => $rowNumber,
            ]);
        }

        $this->historyModel->insert([
            'branchId' => $data['branchId'],
            'historyItemId' => $productId,
            'busId' => $userId,
            'historyAction' => 'Imported product',
            'historyDetails' => (string) $data['quantity'] . ' opening quantity',
        ]);

        $statPayload = [
            'branchId' => $data['branchId'],
            'statItemId' => $productId,
            'busId' => $userId,
            'statItemStock' => $data['quantity'],
            'statItemStockWorth' => $data['quantity'] * $data['costPrice'],
            'statItemSales' => 0,
            'statItemSalesWorth' => 0,
            'statItemIndebt' => 0,
            'statItemIndebtWorth' => 0,
        ];
        $existingStat = $this->statisticsModel->where('statItemId', $productId)->first();
        if ($existingStat) {
            $this->statisticsModel->update($existingStat['statId'], $statPayload);
        } else {
            $this->statisticsModel->insert($statPayload);
        }

        if ((float) $data['quantity'] > 0) {
            $this->stockLedger->recordProductMovement(
                (int) $data['branchId'],
                $productId,
                'import_opening_stock',
                (float) $data['quantity'],
                0,
                (float) $data['quantity'],
                (float) $data['costPrice'],
                'import',
                $batch['id'],
                'IMP-' . $batch['id'],
                $userId
            );
        }

        return ['type' => 'inventory', 'id' => $productId];
    }

    private function importCustomer(array $data, array $batch, int $userId, int $rowNumber): array
    {
        $payload = [
            'custOwner' => $userId,
            'branchId' => $data['branchId'],
            'custName' => $data['name'],
            'custContact' => $data['phone'],
            'custEmail' => $data['email'],
            'custLocation' => $data['location'],
        ];

        if (($data['action'] ?? 'create') === 'update' && !empty($data['existingId'])) {
            $before = $this->customerModel->find($data['existingId']);
            $this->customerModel->update($data['existingId'], $payload);
            $customerId = (int) $data['existingId'];
            $after = $this->customerModel->find($customerId);
            $this->auditLog->record('import.customer_updated', 'customers', $customerId, $before, $after, $userId, $data['branchId'], [
                'batch_id' => $batch['id'],
                'row_number' => $rowNumber,
            ]);
        } else {
            $customerId = (int) $this->customerModel->insert($payload, true);
            if (!$customerId) {
                throw new RuntimeException('Customer could not be imported.');
            }
            $after = $this->customerModel->find($customerId);
            $this->auditLog->record('import.customer_created', 'customers', $customerId, null, $after, $userId, $data['branchId'], [
                'batch_id' => $batch['id'],
                'row_number' => $rowNumber,
            ]);
        }

        return ['type' => 'customer', 'id' => $customerId];
    }

    private function importStock(array $data, array $batch, int $userId, int $rowNumber): array
    {
        $product = $this->inventoryModel->find($data['productId']);
        if (!$product) {
            throw new RuntimeException('Product could not be found for stock import.');
        }

        $oldQuantity = (float) ($product['itemQuantity'] ?? 0);
        $quantity = (float) $data['quantity'];
        $costPrice = $data['costPrice'] ?? (float) ($product['itemStockPrice'] ?? 0);
        $sellingPrice = $data['sellingPrice'] ?? (float) ($product['itemLeastPrice'] ?? 0);

        $stockId = (int) $this->stockModel->insert([
            'branchId' => $data['branchId'],
            'stockOwner' => $userId,
            'stockItem' => $data['productId'],
            'oldStock' => $oldQuantity,
            'stockItemQuantity' => $quantity,
            'stockItemPrice' => $costPrice,
            'itemSellingPrice' => $sellingPrice,
            'itemSupplier' => $data['supplier'],
        ], true);

        if (!$stockId) {
            throw new RuntimeException('Stock intake record could not be imported.');
        }

        $newQuantity = $oldQuantity + $quantity;
        $this->inventoryModel->update($data['productId'], [
            'itemQuantity' => $newQuantity,
            'itemStockPrice' => $costPrice,
            'itemLeastPrice' => $sellingPrice,
        ]);

        $existingStat = $this->statisticsModel->where('statItemId', $data['productId'])->first();
        if ($existingStat) {
            $this->statisticsModel->update($existingStat['statId'], [
                'branchId' => $data['branchId'],
                'statItemStock' => $newQuantity,
                'statItemStockWorth' => $newQuantity * (float) $costPrice,
            ]);
        }

        $updated = $this->inventoryModel->find($data['productId']);
        $this->stockLedger->recordProductMovement(
            (int) $data['branchId'],
            (int) $data['productId'],
            'import_stock_intake',
            $quantity,
            0,
            $newQuantity,
            $costPrice,
            'import',
            $batch['id'],
            'IMP-' . $batch['id'],
            $userId
        );

        $this->historyModel->insert([
            'branchId' => $data['branchId'],
            'historyItemId' => $data['productId'],
            'busId' => $userId,
            'historyAction' => 'Imported stock intake',
            'historyDetails' => (string) $quantity . ' items',
        ]);

        $this->auditLog->record('import.stock_intake_created', 'stock', $stockId, $product, $updated, $userId, $data['branchId'], [
            'batch_id' => $batch['id'],
            'row_number' => $rowNumber,
            'quantity_in' => $quantity,
        ]);

        return ['type' => 'stock', 'id' => $stockId];
    }

    private function importSale(array $data, array $batch, int $userId, int $rowNumber, array &$context = []): array
    {
        $product = $this->inventoryModel->find($data['productId']);
        if (!$product) {
            throw new RuntimeException('Product could not be found for sales import.');
        }

        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unitPrice'];
        $grossTotal = round($quantity * $unitPrice, 2);
        $receiptKey = ($data['receiptKey'] ?? null) ?: $this->saleReceiptKey('IMP-' . $batch['id'] . '-' . $rowNumber);
        $receipt = $context['salesReceipts'][$receiptKey] ?? [
            'receiptCode' => $data['receiptNo'] ?: 'IMP-' . $batch['id'] . '-' . $rowNumber,
            'saleDate' => $data['saleDate'] ?: date('Y-m-d H:i:s'),
            'discount' => (float) ($data['discount'] ?? 0),
            'amountPaid' => (float) ($data['amountPaid'] ?? $grossTotal),
            'dueAmount' => round(max(0, $grossTotal - (float) ($data['discount'] ?? 0) - (float) ($data['amountPaid'] ?? $grossTotal)), 2),
            'paymentMethod' => $data['paymentMethod'],
            'notes' => $data['notes'],
            'customerId' => $data['customerId'] ?: null,
            'quantity' => $quantity,
            'netTotal' => round(max(0, $grossTotal - (float) ($data['discount'] ?? 0)), 2),
            'receiptId' => null,
            'debtRecorded' => false,
        ];

        if (empty($receipt['receiptId'])) {
            $receiptId = (int) $this->receiptModel->insert([
                'branchId' => $data['branchId'],
                'createdBy' => $userId,
                'timeStamp' => $receipt['receiptCode'],
                'discount' => $receipt['discount'],
                'dueAmount' => $receipt['dueAmount'],
                'moreInfo' => $receipt['notes'],
                'paymentMethod' => $receipt['paymentMethod'],
                'amountPaid' => $receipt['amountPaid'],
                'receiptStatus' => 'completed',
            ], true);

            if (!$receiptId) {
                throw new RuntimeException('Receipt could not be imported.');
            }

            $receipt['receiptId'] = $receiptId;
            $this->setDateColumn('receipt', 'SR_ID', $receiptId, 'srDateCreated', $receipt['saleDate']);

            if ((float) $receipt['dueAmount'] > 0 && !empty($receipt['customerId'])) {
                $this->indebtModel->insert([
                    'branchId' => $data['branchId'],
                    'indebtItemId' => $data['productId'],
                    'indebtOwner' => $userId,
                    'quantityDebted' => $receipt['quantity'],
                    'atPrice' => $receipt['netTotal'],
                    'initialDeposit' => $receipt['amountPaid'],
                    'totalAmount' => $receipt['netTotal'],
                    'endDate' => null,
                    'custId' => $receipt['customerId'],
                    'SR_ID' => $receiptId,
                ]);
                $receipt['debtRecorded'] = true;
            }

            $context['salesReceipts'][$receiptKey] = $receipt;
        }

        $receiptId = (int) $receipt['receiptId'];
        $receiptCode = (string) $receipt['receiptCode'];

        $unitCost = $data['unitCost'] ?? (isset($product['itemStockPrice']) ? (float) $product['itemStockPrice'] : null);
        $saleId = (int) $this->salesModel->insert([
            'branchId' => $data['branchId'],
            'saleItemId' => $data['productId'],
            'saleOwner' => $userId,
            'SR_ID' => $receiptId,
            'salePrice' => $unitPrice,
            'unitCostAtSale' => $unitCost,
            'lineCostAtSale' => $unitCost === null ? null : round($unitCost * $quantity, 2),
            'saleQuantity' => $quantity,
            'custId' => $data['customerId'] ?: 0,
            'saleStatus' => 'completed',
        ], true);

        if (!$saleId) {
            throw new RuntimeException('Sale line could not be imported.');
        }

        $this->setDateColumn('sales', 'saleId', $saleId, 'saleDateCreated', $receipt['saleDate']);

        $oldQuantity = (float) ($product['itemQuantity'] ?? 0);
        $newQuantity = $oldQuantity;
        if (!empty($data['adjustInventory'])) {
            $updated = $this->db->table('inventory')
                ->where('branchId', $data['branchId'])
                ->where('itemId', $data['productId'])
                ->where('itemQuantity >=', $quantity)
                ->set('itemQuantity', 'itemQuantity - ' . $quantity, false)
                ->update();

            if (!$updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('Insufficient stock for imported sale.');
            }

            $updatedProduct = $this->inventoryModel->find($data['productId']);
            $newQuantity = (float) ($updatedProduct['itemQuantity'] ?? 0);
            $this->stockLedger->recordProductMovement(
                (int) $data['branchId'],
                (int) $data['productId'],
                'import_sale',
                0,
                $quantity,
                $newQuantity,
                $unitCost,
                'receipt',
                $receiptId,
                (string) $receiptCode,
                $userId
            );
        }

        $this->historyModel->insert([
            'branchId' => $data['branchId'],
            'historyItemId' => $data['productId'],
            'busId' => $userId,
            'historyAction' => 'Imported sale',
            'historyDetails' => (string) $quantity . ' items sold',
        ]);

        $this->updateSalesStatistics((int) $data['branchId'], (int) $data['productId'], $userId, $quantity, $grossTotal, $newQuantity, $unitCost);

        $after = [
            'receiptId' => $receiptId,
            'saleId' => $saleId,
            'productId' => $data['productId'],
            'quantity' => $quantity,
            'grossTotal' => $grossTotal,
            'netTotal' => $receipt['netTotal'],
            'amountPaid' => $receipt['amountPaid'],
            'dueAmount' => $receipt['dueAmount'],
        ];
        $this->auditLog->record('import.sale_created', 'sales', $saleId, $product, $after, $userId, $data['branchId'], [
            'batch_id' => $batch['id'],
            'row_number' => $rowNumber,
            'receipt_id' => $receiptId,
        ]);

        return ['type' => 'sales', 'id' => $saleId];
    }

    private function prepareSalesImportContext(array $rows): array
    {
        $receipts = [];

        foreach ($rows as $row) {
            $data = $this->decodeJson($row['normalizedData'] ?? null);
            if (($data['action'] ?? 'create') === 'skip') {
                continue;
            }

            $rowNumber = (int) ($row['rowNumber'] ?? 0);
            $receiptCode = $data['receiptNo'] ?: 'IMP-' . ($row['importBatchId'] ?? 'ROW') . '-' . $rowNumber;
            $key = ($data['receiptKey'] ?? null) ?: $this->saleReceiptKey($receiptCode);
            $lineGross = round((float) ($data['quantity'] ?? 0) * (float) ($data['unitPrice'] ?? 0), 2);

            if (!isset($receipts[$key])) {
                $receipts[$key] = [
                    'receiptCode' => $receiptCode,
                    'saleDate' => $data['saleDate'] ?: date('Y-m-d H:i:s'),
                    'discount' => !empty($data['discountProvided']) ? (float) ($data['discount'] ?? 0) : 0.0,
                    'amountPaid' => !empty($data['amountPaidProvided']) ? (float) ($data['amountPaid'] ?? 0) : null,
                    'paymentMethod' => $data['paymentMethod'] ?: 'cash',
                    'notes' => $data['notes'] ?: 'Imported sale',
                    'customerId' => $data['customerId'] ?: null,
                    'grossTotal' => 0.0,
                    'quantity' => 0.0,
                    'netTotal' => 0.0,
                    'dueAmount' => 0.0,
                    'receiptId' => null,
                    'debtRecorded' => false,
                ];
            } else {
                if ($receipts[$key]['amountPaid'] === null && !empty($data['amountPaidProvided'])) {
                    $receipts[$key]['amountPaid'] = (float) ($data['amountPaid'] ?? 0);
                }
                if ((float) $receipts[$key]['discount'] === 0.0 && !empty($data['discountProvided'])) {
                    $receipts[$key]['discount'] = (float) ($data['discount'] ?? 0);
                }
                if (empty($receipts[$key]['customerId']) && !empty($data['customerId'])) {
                    $receipts[$key]['customerId'] = $data['customerId'];
                }
            }

            $receipts[$key]['grossTotal'] += $lineGross;
            $receipts[$key]['quantity'] += (float) ($data['quantity'] ?? 0);
        }

        foreach ($receipts as $key => $receipt) {
            if ((float) $receipt['discount'] > (float) $receipt['grossTotal']) {
                throw new RuntimeException('Receipt ' . $receipt['receiptCode'] . ' discount cannot exceed its total sale value.');
            }

            $netTotal = round(max(0, (float) $receipt['grossTotal'] - (float) $receipt['discount']), 2);
            $amountPaid = $receipt['amountPaid'] === null ? $netTotal : (float) $receipt['amountPaid'];

            if ($amountPaid < 0 || $amountPaid > $netTotal) {
                throw new RuntimeException('Receipt ' . $receipt['receiptCode'] . ' amount paid must be between zero and the net sale total.');
            }

            $dueAmount = round(max(0, $netTotal - $amountPaid), 2);
            if ($dueAmount > 0 && empty($receipt['customerId'])) {
                throw new RuntimeException('Receipt ' . $receipt['receiptCode'] . ' has credit due and needs a matching customer.');
            }

            $receipt['netTotal'] = $netTotal;
            $receipt['amountPaid'] = $amountPaid;
            $receipt['dueAmount'] = $dueAmount;
            $receipt['paymentMethod'] = $receipt['paymentMethod'] ?: ($dueAmount > 0 ? 'credit' : 'cash');
            $receipts[$key] = $receipt;
        }

        return ['salesReceipts' => $receipts];
    }

    private function ensureCategory(string $categoryName): int
    {
        $categoryName = trim($categoryName) ?: 'Uncategorized';
        $existing = $this->categoryModel
            ->where('LOWER(categoryName)', strtolower($categoryName))
            ->first();

        if ($existing) {
            return (int) $existing['categoryId'];
        }

        return (int) $this->categoryModel->insert(['categoryName' => $categoryName], true);
    }

    private function findExistingProduct(?int $branchId, string $name, string $sku = '', string $barcode = ''): ?array
    {
        if ($name === '' && $sku === '' && $barcode === '') {
            return null;
        }

        $builder = $this->inventoryModel;
        if ($branchId !== null) {
            $builder = $builder->where('branchId', $branchId);
        }

        $builder = $builder->groupStart();
        $hasCondition = false;

        if ($sku !== '') {
            $builder->where('itemSku', $sku);
            $hasCondition = true;
        }
        if ($barcode !== '') {
            $hasCondition ? $builder->orWhere('itemBarcode', $barcode) : $builder->where('itemBarcode', $barcode);
            $hasCondition = true;
        }
        if ($name !== '') {
            $hasCondition ? $builder->orWhere('LOWER(itemName)', strtolower($name)) : $builder->where('LOWER(itemName)', strtolower($name));
            $hasCondition = true;
        }

        $builder = $builder->groupEnd();

        return $hasCondition ? $builder->first() : null;
    }

    private function findExistingCustomer(?int $branchId, string $name, string $phone = ''): ?array
    {
        if ($name === '' && $phone === '') {
            return null;
        }

        $builder = $this->customerModel;
        if ($branchId !== null) {
            $builder = $builder->where('branchId', $branchId);
        }

        $builder = $builder->groupStart()->where('LOWER(custName)', strtolower($name));
        if ($phone !== '') {
            $builder->orWhere('custContact', $phone);
        }
        $builder = $builder->groupEnd();

        return $builder->first();
    }

    private function findReceiptByCode(?int $branchId, string $receiptNo): ?array
    {
        if ($receiptNo === '') {
            return null;
        }

        $builder = $this->receiptModel->where('timeStamp', $receiptNo);
        if ($branchId !== null) {
            $builder = $builder->where('branchId', $branchId);
        }

        return $builder->first();
    }

    private function saleReceiptKey(string $receiptNo): string
    {
        return strtolower(trim($receiptNo));
    }

    private function mappedString(array $raw, array $mapping, string $field): string
    {
        $header = $mapping[$field] ?? null;
        if (!$header || !array_key_exists($header, $raw)) {
            return '';
        }

        return trim((string) $raw[$header]);
    }

    private function mappedNumber(array $raw, array $mapping, string $field, $fallback)
    {
        $value = $this->mappedString($raw, $mapping, $field);
        if ($value === '') {
            return $fallback;
        }

        $normalized = str_replace([',', 'UGX', 'ugx', ' '], '', $value);

        return is_numeric($normalized) ? (float) $normalized : $fallback;
    }

    private function mappedDate(array $raw, array $mapping, string $field): ?string
    {
        $value = $this->mappedString($raw, $mapping, $field);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function setDateColumn(string $table, string $primaryKey, int $id, string $dateColumn, string $date): void
    {
        if (!$this->db->fieldExists($dateColumn, $table)) {
            return;
        }

        $this->db->table($table)
            ->where($primaryKey, $id)
            ->update([$dateColumn => $date]);
    }

    private function updateSalesStatistics(
        int $branchId,
        int $productId,
        int $userId,
        float $quantity,
        float $grossTotal,
        float $stockBalance,
        ?float $unitCost
    ): void {
        $existing = $this->statisticsModel->where('statItemId', $productId)->first();
        $payload = [
            'branchId' => $branchId,
            'statItemId' => $productId,
            'busId' => $userId,
            'statItemStock' => $stockBalance,
            'statItemStockWorth' => $unitCost === null ? 0 : round($stockBalance * $unitCost, 2),
            'statItemSales' => $quantity,
            'statItemSalesWorth' => $grossTotal,
            'statItemIndebt' => 0,
            'statItemIndebtWorth' => 0,
        ];

        if ($existing) {
            $this->statisticsModel->update($existing['statId'], [
                'branchId' => $branchId,
                'statItemStock' => $stockBalance,
                'statItemStockWorth' => $unitCost === null ? ($existing['statItemStockWorth'] ?? 0) : round($stockBalance * $unitCost, 2),
                'statItemSales' => (float) ($existing['statItemSales'] ?? 0) + $quantity,
                'statItemSalesWorth' => (float) ($existing['statItemSalesWorth'] ?? 0) + $grossTotal,
            ]);
            return;
        }

        $this->statisticsModel->insert($payload);
    }

    private function requireOwnedBatch(int $batchId, int $userId): array
    {
        $batch = $this->batchModel->find($batchId);
        if (!$batch || (int) ($batch['userId'] ?? 0) !== $userId) {
            throw new InvalidArgumentException('Import batch was not found.');
        }

        return $batch;
    }

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported import type.');
        }

        return $type;
    }

    private function branchId(array $batch): ?int
    {
        return isset($batch['branchId']) && $batch['branchId'] !== null ? (int) $batch['branchId'] : null;
    }

    private function mergeOptions(array $options): array
    {
        return [
            'duplicateStrategy' => in_array($options['duplicateStrategy'] ?? 'skip', ['skip', 'update'], true)
                ? $options['duplicateStrategy']
                : 'skip',
            'createMissingCategories' => array_key_exists('createMissingCategories', $options)
                ? (bool) $options['createMissingCategories']
                : true,
            'adjustInventory' => array_key_exists('adjustInventory', $options)
                ? (bool) $options['adjustInventory']
                : true,
        ];
    }

    private function sanitizeRow(array $row): array
    {
        $clean = [];
        foreach ($row as $key => $value) {
            $clean[trim((string) $key)] = is_scalar($value) || $value === null ? trim((string) $value) : json_encode($value);
        }

        return $clean;
    }

    private function decodeJson($value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hydrateBatch(array $batch): array
    {
        foreach (['headers', 'mapping', 'options', 'summary'] as $field) {
            $batch[$field] = $this->decodeJson($batch[$field] ?? null);
        }

        return $batch;
    }

    private function hydrateRow(array $row): array
    {
        foreach (['rawData', 'normalizedData', 'errors', 'warnings'] as $field) {
            $row[$field] = $this->decodeJson($row[$field] ?? null);
        }

        return $row;
    }

    private function saveMapping(int $userId, string $type, string $name, ?string $headers, array $mapping, array $options): void
    {
        $existing = $this->mappingModel
            ->where('userId', $userId)
            ->where('importType', $type)
            ->where('name', $name)
            ->first();
        $payload = [
            'userId' => $userId,
            'importType' => $type,
            'name' => $name,
            'headers' => $headers,
            'mapping' => json_encode($mapping),
            'options' => json_encode($options),
        ];

        if ($existing) {
            $this->mappingModel->update($existing['id'], $payload);
        } else {
            $this->mappingModel->insert($payload);
        }
    }
}
