<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Controllers\Traits\SecuresInput;
use App\Models\Inventory;
use App\Models\Statistics;
use App\Models\Business;
use App\Models\History;
use App\Models\Sales;
use App\Models\Indebt;
use App\Models\DebtTrack;
use App\Models\Stock;
use App\Models\Receipt;
use App\Models\CustomerModel;
use App\Services\BranchContextService;
use App\Services\StockLedgerService;
use App\Services\AuditLogService;
use App\Services\SaleCalculationService;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class Entries extends ResourceController
{
    use SecuresInput;

    /** This controller will hold the following functions
     * Check presence of administrators' data
     * Validation check
     * CRUD stock
     * Sales recording
     * Indebt recording
     * Overall statistics recording
     * In the addition to the update of stock funcns, itemBuyPrice and itemSalePrice have been merged to make itemLeastPrice(amount an owner can sell an item at the minimum)
     **/

    private $inventoryModel;
    private $statisticsModel;
    private $businessModel;
    private $historyModel;
    private $salesModel;
    private $indebtModel;
    private $debtTrackModel;
    private $stockModel;
    private $receiptModel;
    private CustomerModel $customerModel;
    private BranchContextService $branchContext;
    private StockLedgerService $stockLedger;
    private AuditLogService $auditLog;
    private SaleCalculationService $saleCalculator;


    public function __construct()
    {
        $this->inventoryModel = new Inventory();
        $this->statisticsModel = new Statistics();
        $this->businessModel = new Business();
        $this->historyModel = new History();
        $this->salesModel = new Sales();
        $this->indebtModel = new Indebt();
        $this->debtTrackModel = new DebtTrack();
        $this->stockModel = new Stock();
        $this->receiptModel = new Receipt();
        $this->customerModel = new CustomerModel();
        $this->branchContext = service('branchContext');
        $this->stockLedger = new StockLedgerService();
        $this->auditLog = new AuditLogService();
        $this->saleCalculator = new SaleCalculationService();
    }

    private function nostockdata()
    {
        //check presence of admin data
        $response = [
            'status' => false,
            'error' => 'no data',
            'message' => 'We have no stock data here. Make sure everything is right and try again in 10 minutes.'
        ];
        return $this->respond($response);
    }

    // return resource object for validation failure
    public function validationFail()
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];

        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => empty($errors) ? 'Invalid request.' : implode(' ', array_values($errors)),
            'errors' => $errors,
        ];
        return $this->respond($response, 422);
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        //home page
        $data = [
            'title' => 'HSMS',
            'From' => 'Lwetutte Steven',
            'To' => 'Musoke Hamzah',
            'Message' => 'Hello Mr. Musoke, this is how our API based CodeIgniter returns data, hope it is the one you expected.'
        ];
        if (empty($data)) {
            return $this->nostockdata();
        } else {
            return $this->respond($data);
        }
    }

    //Enter new stock quantites
    public function addStock()
    {
        $userId = (int) auth()->id();
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not post set it to post and try again.'
            ], 405);
        }

        $stockItems = $this->request->getVar('stockItems');
        if (empty($stockItems) || !is_array($stockItems)) {
            return $this->respond([
                'status' => false,
                'error' => 'StockItemsListEmpty',
                'message' => 'Stock Items list is empty add an item or items and try again!'
            ], 400);
        }

        $db = db_connect();
        $createdStockIds = [];

        try {
            $db->transBegin();

            foreach ($stockItems as $item) {
                $itemData = is_array($item) ? $item : (array) $item;
                $productId = (int) ($itemData['stockItem'] ?? 0);
                $quantity = (float) ($itemData['stockItemQuantity'] ?? 0);
                $stockPrice = isset($itemData['itemStockPrice']) && is_numeric($itemData['itemStockPrice'])
                    ? (float) $itemData['itemStockPrice']
                    : 0.0;
                $sellingPrice = isset($itemData['itemLeastPrice']) && is_numeric($itemData['itemLeastPrice'])
                    ? (float) $itemData['itemLeastPrice']
                    : 0.0;

                if ($productId <= 0 || $quantity <= 0 || $stockPrice < 0 || $sellingPrice < 0) {
                    throw new InvalidArgumentException('Each stock item must have a valid product, positive quantity, and valid prices.');
                }

                $inventoryItem = $this->inventoryModel->find($productId);
                if (!$inventoryItem || (int) ($inventoryItem['branchId'] ?? 0) !== $branchId) {
                    throw new InvalidArgumentException('One or more selected products do not belong to the active branch.');
                }

                $oldStock = (float) ($inventoryItem['itemQuantity'] ?? 0);
                $stockId = $this->stockModel->insert([
                    'branchId' => $branchId,
                    'stockOwner' => $userId,
                    'stockItem' => $productId,
                    'oldStock' => $oldStock,
                    'stockItemQuantity' => $quantity,
                    'stockItemPrice' => $stockPrice,
                    'itemSellingPrice' => $sellingPrice,
                    'itemSupplier' => $itemData['itemSupplier'] ?? 'none',
                ], true);

                if (!$stockId) {
                    throw new RuntimeException('Stock intake record could not be created.');
                }

                $updated = $db->table('inventory')
                    ->where('branchId', $branchId)
                    ->where('itemId', $productId)
                    ->set('itemQuantity', 'itemQuantity + ' . $quantity, false)
                    ->update();

                if (!$updated || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Inventory quantity could not be updated.');
                }

                $updatedItem = $this->inventoryModel->find($productId);
                $this->stockLedger->recordProductMovement(
                    $branchId,
                    $productId,
                    'purchase',
                    $quantity,
                    0,
                    (float) $updatedItem['itemQuantity'],
                    $stockPrice,
                    'stock',
                    $stockId,
                    'STK-' . $stockId,
                    $userId
                );

                $this->auditLog->record(
                    'stock.intake_created',
                    'stock',
                    $stockId,
                    $inventoryItem,
                    $updatedItem,
                    $userId,
                    $branchId,
                    ['quantity_in' => $quantity]
                );

                $createdStockIds[] = $stockId;
            }

            if ($db->transStatus() === false) {
                throw new RuntimeException('Stock intake transaction failed.');
            }

            $db->transCommit();

            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'stock-added', [
                'stockId' => null,
                'message' => 'Stock added'
            ]);

            return $this->respond([
                'status' => true,
                'error' => null,
                'stockIds' => $createdStockIds,
                'message' => 'Item(s) successfully added in the stock.'
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Stock intake failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'StockIntakeFailed',
                'message' => $e instanceof InvalidArgumentException ? $e->getMessage() : 'Stock intake could not be completed.'
            ], $e instanceof InvalidArgumentException ? 400 : 500);
        }
    }

    /**
     * Create a new resource object, from "posted" parameters
     *
     * @return mixed
     */
    //&& $this->validateStockEntries('stockentry')
    public function createStock()
    {
        $userId = auth()->id();
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }
        //handle & submit stock form entries
        if (strtolower($this->request->getMethod()) === 'post') {
            try {
                $uploadedImage = $this->storeUploadedImage('item_image', 'products');
            } catch (InvalidArgumentException $e) {
                return $this->respond(['status' => false, 'message' => $e->getMessage()], 422);
            } catch (RuntimeException $e) {
                return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
            }

            $stockData = [
                'branchId' => $branchId,
                'itemName' => $this->secureText($this->request->getVar('item_name'), 255),
                'itemCategoryId' => $this->secureInt($this->request->getVar('item_category'), 0),
                'itemModel' => $this->secureText($this->request->getVar('item_model'), 50),
                'itemSku' => $this->secureText($this->request->getVar('item_sku'), 80, true),
                'itemBarcode' => $this->secureText($this->request->getVar('item_barcode'), 120, true),
                'itemImage' => $uploadedImage,
                'itemBrand' => $this->secureText($this->request->getVar('item_brand'), 120, true),
                'itemProductType' => $this->secureAllowed($this->request->getVar('item_product_type'), ['purchased', 'produced', 'service'], 'purchased'),
                'itemUnit' => $this->secureText($this->request->getVar('item_unit'), 30) ?: 'pcs',
                'itemSupplier' => $this->secureText($this->request->getVar('item_supplier'), 150, true),
                'itemReorderLevel' => $this->optionalNumber($this->request->getVar('item_reorder_level'), 0),
                'itemQuality' => $this->secureText($this->request->getVar('item_quality'), 50),
                'itemQuantity' => $this->secureNonNegativeDecimal($this->request->getVar('item_quantity'), 0),
                'itemCondition' => $this->secureText($this->request->getVar('item_condition'), 50),
                'itemSize' => $this->secureText($this->request->getVar('item_size'), 50),
                'itemStockPrice' => $this->optionalNumber($this->request->getVar('item_stock_price'), 0),
                'itemLeastPrice' => $this->secureNonNegativeDecimal($this->request->getVar('item_min_price'), 0),
                'itemWholesalePrice' => $this->optionalNumber($this->request->getVar('item_wholesale_price'), null),
                'itemNotes' => $this->secureText($this->request->getVar('item_notes'), 1500, true) ?? 'none',
                'itemOwner' =>  $userId
            ];

            $saveStock = $this->inventoryModel->save($stockData);

            // is stock data saved?? 
            if ($saveStock) {
                $data = $this->inventoryModel->where('itemOwner', $userId)->orderBy('itemId', 'DESC')->find();
                $item_id = $data[0]['itemId'];
                $historyData = [
                    'branchId' => $branchId,
                    'historyItemId' => $item_id,
                    'busId' => $userId,
                    'historyAction' => 'New item added',
                    'historyDetails' => $this->request->getVar('item_quantity') . " items",
                ];

                $saveHistoryData = $this->historyModel->save($historyData);
     $payload = [
                'stockId' => null,
                'message' => 'Stock created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'stock-created', $payload);
                $response = [
                    'status' => true,
                    'error' => null,
                    'Message' => 'Success!, a new item has been added to existing stock'
                ];
                $this->recordStat(NULL, 'createStock', NULL);
                // stock entry history handling
                if (!$saveHistoryData) {
                    $response = [
                        'status' => false,
                        'error' => 'historyUploadFailed',
                        'Messages' => 'Fail!! New item creation was successful, but your action history was not recorded. But this is OK though you should report it.'
                    ];

                    return $this->respond($response);
                }
                // in case upload & it's history were recorded ok 
                else {
                    $response = [
                        'status' => true,
                        'error' => null,
                        'Messages' => 'Action history has been recorded successfully.'
                    ];
                    return $this->respond($response);
                }

                // return $this->respond($response);
            } else {
                $response = [
                    'status' => false,
                    'error' => 'itemUploadFailed',
                    'Messages' => 'Fail!! New item creation could not be fully processed, check your form entries and try again, please'
                ];
                return $this->respond($response);
            }
        } else {
            return $this->validationFail();
        }
    }

    /**
     * Return the editable properties of a resource object
     *
     * @return mixed
     */
    public function edit($id = null)
    {
        //fetch item to edit
        $data = $this->inventoryModel->find($id);

        if ($data && $this->branchContext->recordMatchesCurrentBranch($data)) {
            return $this->respond($data);
        } else {
            return $this->nostockdata();
        }
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function update($id = null)
    {
        $userId = auth()->id();
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        //update selected item
        $id = $this->request->getVar('itemId');
        $data = $this->inventoryModel->find($id);

        if (empty($data)) {
            // return $this->nostockdata();
            return $this->respond($id);
        }
        if (!$this->branchContext->recordMatchesCurrentBranch($data)) {
            return $this->respond(['status' => false, 'message' => 'This product is outside your current branch scope.'], 403);
        }
        // in case data to update is available //&& $this->validateStockEntries('updateitem')
        else {
            if (strtolower($this->request->getMethod()) === 'post' && $this->validateStockEntries('updateitem')) {
                $updateStock = [];
                $stockDataUpdate = [
                    'branchId' => $branchId ?? ($data['branchId'] ?? null),
                    'itemName' => $this->secureText($this->request->getVar('item_name'), 255),
                    'itemCategoryId' => $this->secureInt($this->request->getVar('item_category'), 0),
                    'itemModel' => $this->secureText($this->request->getVar('item_model'), 50),
                    'itemSku' => $this->secureText($this->request->getVar('item_sku'), 80, true),
                    'itemBarcode' => $this->secureText($this->request->getVar('item_barcode'), 120, true),
                    'itemBrand' => $this->secureText($this->request->getVar('item_brand'), 120, true),
                    'itemProductType' => $this->secureAllowed($this->request->getVar('item_product_type'), ['purchased', 'produced', 'service'], 'purchased'),
                    'itemUnit' => $this->secureText($this->request->getVar('item_unit'), 30) ?: 'pcs',
                    'itemSupplier' => $this->secureText($this->request->getVar('item_supplier'), 150, true),
                    'itemReorderLevel' => $this->optionalNumber($this->request->getVar('item_reorder_level'), 0),
                    'itemQuality' => $this->secureText($this->request->getVar('item_quality'), 50),
                    'itemQuantity' => $this->secureNonNegativeDecimal($this->request->getVar('item_quantity'), 0),
                    'itemCondition' => $this->secureText($this->request->getVar('item_condition'), 50),
                    'itemSize' => $this->secureText($this->request->getVar('item_size'), 50),
                    'itemStockPrice' => $this->optionalNumber($this->request->getVar('item_stock_price'), 0),
                    'itemLeastPrice' => $this->secureNonNegativeDecimal($this->request->getVar('item_min_price'), 0),
                    'itemWholesalePrice' => $this->optionalNumber($this->request->getVar('item_wholesale_price'), null),
                    'itemNotes' => $this->secureText($this->request->getVar('item_notes'), 1500, true) ?? 'none',
                    'itemOwner' => $userId,
                ];
                try {
                    $uploadedImage = $this->storeUploadedImage('item_image', 'products');
                } catch (InvalidArgumentException $e) {
                    return $this->respond(['status' => false, 'message' => $e->getMessage()], 422);
                } catch (RuntimeException $e) {
                    return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
                }
                if ($uploadedImage !== null) {
                    $stockDataUpdate['itemImage'] = $uploadedImage;
                }

                $historyData = [
                    'branchId' => $stockDataUpdate['branchId'],
                    'historyItemId' => $id,
                    'historyAction' => 'Updated an item',
                    'historyDetails' => ''
                ];

                $updateStock = $this->inventoryModel->update($id, $stockDataUpdate);

                if ($updateStock) {
                    $saveHistoryData = $this->historyModel->save($historyData);

                    // stock entry history handling
                    if (!$saveHistoryData) {
                        $response = [
                            'status' => false,
                            'error' => 'historyUpdateFailed',
                            'Message' => 'Fail!! Item update was successful, but your action history was not recorded. But this is OK though you should report it and get it fixed.'
                        ];
                        return $this->respond($response);
                    }
                    // in case history was recorded ok 
                    else {
                             $payload = [
                'itemId' => $id,
                'message' => 'Item updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'item-updated', $payload);
                        $response = [
                            'status' => true,
                            'error' => null,
                            'Message' => 'Item has been updated.'
                        ];
                        return $this->respond($response);
                        // exit();
                    }
                    $this->recordStat($id, 'update', NULL);
                    exit();
                }
                // in case item update fails
                else {
                    $response = [
                        'status' => false,
                        'error' => 'updateFail',
                        'message' => 'We could not update this item, make sure everything is right and try again',
                        'Data'    =>  $stockDataUpdate
                    ];
                    return $this->respond($response);
                }
            }
            // in case form validation fails 
            else {
                return $this->validationFail();
            }
        }
    }

    /**
     * Insert a sale into the sales table
     *
     * @return mixed
     */
    public function saleStock($items = null)
    {
        $userId = (int) auth()->id();
        $cashierName = 'User #' . $userId;
        $currentUser = auth()->user();
        if ($currentUser) {
            $userData = method_exists($currentUser, 'toArray') ? $currentUser->toArray() : [];
            $cashierName = $userData['username']
                ?? $currentUser->username
                ?? $currentUser->email
                ?? $cashierName;
        }

        $saleDetails = $this->request->getVar('saleDetails') ?? [];
        $saleDetailsArray = is_array($saleDetails) ? $saleDetails : (array) $saleDetails;
        $requestedBranchId = $this->request->getVar('branchId') ?? ($saleDetailsArray['branchId'] ?? null);
        $branchId = $this->branchContext->resolveWritableBranchId($requestedBranchId);
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'Only POST requests can create sales.'
            ], 405);
        }

        $items = $this->request->getVar('saleItems');

        if (empty($items) || !is_array($items)) {
            return $this->respond([
                'status' => false,
                'error' => 'ItemsListEmpty',
                'message' => 'Items list is empty add an item or items to make a complete transaction'
            ], 400);
        }

        try {
            $calculation = $this->saleCalculator->calculate($items, $saleDetails, $branchId);

            foreach ($calculation['lines'] as $line) {
                if (!empty($line['custId'])) {
                    $customer = $this->customerModel->find($line['custId']);
                    if (!$customer || (int) ($customer['branchId'] ?? 0) !== $branchId) {
                        throw new InvalidArgumentException('The selected customer does not belong to the active branch.');
                    }
                }
            }

            if ($calculation['dueAmount'] > 0 && empty($calculation['custId'])) {
                throw new InvalidArgumentException('A customer is required when a sale has an outstanding balance.');
            }

            if (
                empty($calculation['custId'])
                && strtolower((string) ($calculation['paymentMethod'] ?? '')) === 'credit'
            ) {
                throw new InvalidArgumentException('Walk-in customers cannot use credit sales. Collect full payment or select a registered customer.');
            }

            if ($calculation['dueAmount'] > 0 && !$this->debtSalesAllowedForBranch($branchId)) {
                return $this->respond([
                    'status' => false,
                    'error' => 'DebtSalesDisabled',
                    'message' => 'Debt sales are disabled for this branch. Collect full payment or ask an admin to enable debt sales.',
                    'calculatedTotals' => [
                        'subtotal' => $calculation['subtotal'],
                        'discount' => $calculation['discount'],
                        'total' => $calculation['total'],
                        'amountPaid' => $calculation['amountPaid'],
                        'dueAmount' => $calculation['dueAmount'],
                    ],
                ], 403);
            }

            if ($calculation['dueAmount'] > 0 && !$this->saleDebtWasConfirmed($saleDetailsArray)) {
                return $this->respond([
                    'status' => false,
                    'error' => 'DebtConfirmationRequired',
                    'message' => 'Confirm that the remaining balance should be recorded as customer debt.',
                    'calculatedTotals' => [
                        'subtotal' => $calculation['subtotal'],
                        'discount' => $calculation['discount'],
                        'total' => $calculation['total'],
                        'amountPaid' => $calculation['amountPaid'],
                        'dueAmount' => $calculation['dueAmount'],
                    ],
                ], 409);
            }
        } catch (InvalidArgumentException $e) {
            return $this->respond([
                'status' => false,
                'error' => 'SaleValidationFailed',
                'message' => $e->getMessage()
            ], 400);
        }

        $db = db_connect();
        $receiptNo = null;
        $saleIds = [];
        $cashDrawer = null;

        try {
            $db->transBegin();

            $timeStamp = uniqid('RS-', true);
            $receiptNo = $this->receiptModel->insert([
                'branchId' => $branchId,
                'createdBy' => $userId,
                'timeStamp' => $timeStamp,
                'discount' => $calculation['discount'],
                'dueAmount' => $calculation['dueAmount'],
                'moreInfo' => $calculation['moreInfo'] ?? '',
                'paymentMethod' => $calculation['paymentMethod'] ?? 'cash',
                'amountPaid' => $calculation['amountPaid'],
                'receiptStatus' => 'completed',
            ], true);

            if (!$receiptNo) {
                throw new RuntimeException('Receipt could not be created.');
            }

            $historyRows = [];
            foreach ($calculation['lines'] as $line) {
                $saleId = $this->salesModel->insert([
                    'branchId' => $branchId,
                    'saleItemId' => $line['productId'],
                    'saleOwner' => $userId,
                    'SR_ID' => $receiptNo,
                    'salePrice' => $line['unitPrice'],
                    'unitCostAtSale' => $line['unitCost'],
                    'lineCostAtSale' => $line['unitCost'] === null ? null : round($line['unitCost'] * $line['quantity'], 2),
                    'saleQuantity' => $line['quantity'],
                    'custId' => $line['custId'],
                    'saleStatus' => 'completed',
                ], true);

                if (!$saleId) {
                    throw new RuntimeException('Sale line could not be created.');
                }

                $updated = $db->table('inventory')
                    ->where('branchId', $branchId)
                    ->where('itemId', $line['productId'])
                    ->where('itemQuantity >=', $line['quantity'])
                    ->set('itemQuantity', 'itemQuantity - ' . $line['quantity'], false)
                    ->update();

                if (!$updated || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Insufficient stock for product ID ' . $line['productId'] . '.');
                }

                $updatedProduct = $this->inventoryModel->find($line['productId']);
                $this->stockLedger->recordProductMovement(
                    $branchId,
                    $line['productId'],
                    'sale',
                    0,
                    $line['quantity'],
                    (float) $updatedProduct['itemQuantity'],
                    $line['unitCost'],
                    'receipt',
                    $receiptNo,
                    (string) $timeStamp,
                    $userId
                );

                $historyRows[] = [
                    'branchId' => $branchId,
                    'historyItemId' => $line['productId'],
                    'busId' => $userId,
                    'historyAction' => $line['quantity'] . ' Item(s) sold',
                    'historyDetails' => (string) ($line['custId'] ?? ''),
                ];

                $saleIds[] = $saleId;
            }

            if (!empty($historyRows) && !$this->historyModel->insertBatch($historyRows)) {
                throw new RuntimeException('Sales history could not be saved.');
            }

            if ($calculation['dueAmount'] > 0) {
                $firstLine = $calculation['lines'][0];
                $debtSaved = $this->indebtModel->save([
                    'branchId' => $branchId,
                    'indebtItemId' => $firstLine['productId'],
                    'indebtOwner' => $userId,
                    'quantityDebted' => array_sum(array_column($calculation['lines'], 'quantity')),
                    'atPrice' => $calculation['total'],
                    'totalAmount' => $calculation['total'],
                    'initialDeposit' => $calculation['amountPaid'],
                    'endDate' => $calculation['endDate'],
                    'custId' => $calculation['custId'],
                    'SR_ID' => $receiptNo,
                ]);

                if (!$debtSaved) {
                    throw new RuntimeException('Customer debt could not be recorded.');
                }
            }

            $paymentMethod = strtolower((string) ($calculation['paymentMethod'] ?? ''));
            if (str_contains($paymentMethod, 'cash') && (float) $calculation['amountPaid'] > 0) {
                $cashDrawerController = new CashDrawersController();
                $cashDrawer = $cashDrawerController->recordSale(
                    $branchId,
                    (int) $receiptNo,
                    $userId,
                    (float) $calculation['amountPaid']
                );
            }

            $this->recordStat(null, 'saleStock', count($calculation['lines']));
            $this->auditLog->record(
                'sale.created',
                'receipt',
                $receiptNo,
                null,
                [
                    'receiptNo' => $receiptNo,
                    'saleIds' => $saleIds,
                    'totals' => $calculation,
                ],
                $userId,
                $branchId
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Sale transaction failed.');
            }

            $db->transCommit();

            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'sale-created', [
                'saleId' => null,
                'message' => 'Sale created'
            ]);

            return $this->respond([
                'status' => true,
                'error' => null,
                'receiptNumber' => $receiptNo,
                'createdBy' => $userId,
                'cashierName' => $cashierName,
                'cashDrawer' => $cashDrawer,
                'calculatedTotals' => [
                    'subtotal' => $calculation['subtotal'],
                    'discount' => $calculation['discount'],
                    'total' => $calculation['total'],
                    'amountPaid' => $calculation['amountPaid'],
                    'dueAmount' => $calculation['dueAmount'],
                ],
                'message' => 'transaction completed successfully'
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Sale transaction failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'SalesTransactionFailed',
                'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Sales transaction could not be completed.'
            ], 409);
        }
    }

    private function saleDebtWasConfirmed(array $saleDetails): bool
    {
        $value = $saleDetails['confirmDebt'] ?? ($saleDetails['debtConfirmed'] ?? false);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'confirmed'], true);
    }

    private function debtSalesAllowedForBranch(int $branchId): bool
    {
        $db = db_connect();

        if ($db->fieldExists('allowDebtSales', 'branches')) {
            $branch = $db->table('branches')
                ->select('allowDebtSales')
                ->where('branchId', $branchId)
                ->get()
                ->getRowArray();

            if ($branch && $branch['allowDebtSales'] !== null && $branch['allowDebtSales'] !== '') {
                return (int) $branch['allowDebtSales'] === 1;
            }
        }

        $settings = $this->currentAppSettings();

        return filter_var($settings['allowDebtSales'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function currentAppSettings(): array
    {
        $profile = $this->businessModel->orderBy('profileId', 'ASC')->findAll(1);
        $settings = $profile[0]['appSettings'] ?? null;

        if (!is_string($settings) || trim($settings) === '') {
            return ['allowDebtSales' => true];
        }

        $decoded = json_decode($settings, true);

        return is_array($decoded) ? $decoded : ['allowDebtSales' => true];
    }

    public function updateSales()
    {
        return $this->cancelSale();
    }

    public function cancelSale()
    {
        $userId = (int) auth()->id();
        $branchId = $this->branchContext->getEffectiveBranchId();
        $receiptNumber = $this->request->getVar('SR_ID');

        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        if (empty($receiptNumber)) {
            return $this->respond([
                'status' => false,
                'error' => 'MissingReceipt',
                'message' => 'Receipt number is required.'
            ], 400);
        }

        $this->salesModel->where('SR_ID', $receiptNumber);
        $this->salesModel->where('saleOwner', $userId);
        $this->salesModel
            ->groupStart()
                ->where('saleStatus <>', 'cancelled')
                ->orWhere('saleStatus IS NULL', null, false)
            ->groupEnd();
        if ($branchId !== null) {
            $this->salesModel->where('branchId', $branchId);
        }
        $sales = $this->salesModel->findAll();

        if (empty($sales)) {
            return $this->respond([
                'status' => false,
                'error' => 'SaleNotFound',
                'message' => 'No sale lines were found for this receipt.'
            ], 404);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            $debts = $this->indebtModel->where('SR_ID', $receiptNumber)->findAll();
            foreach ($debts as $debt) {
                $payments = $this->debtTrackModel->where('debtId', $debt['indebtId'])->countAllResults();
                if ($payments > 0) {
                    throw new RuntimeException('This sale has debt payments and cannot be cancelled without a payment reversal.');
                }
                $this->indebtModel->delete($debt['indebtId']);
            }

            foreach ($sales as $saleLine) {
                $updated = $db->table('inventory')
                    ->where('branchId', $branchId)
                    ->where('itemId', $saleLine['saleItemId'])
                    ->set('itemQuantity', 'itemQuantity + ' . (float) $saleLine['saleQuantity'], false)
                    ->update();

                if (!$updated || $db->affectedRows() !== 1) {
                    throw new RuntimeException('Could not restore stock for product ID ' . $saleLine['saleItemId'] . '.');
                }

                $updatedProduct = $this->inventoryModel->find($saleLine['saleItemId']);
                $this->stockLedger->recordProductMovement(
                    (int) $branchId,
                    (int) $saleLine['saleItemId'],
                    'sale_reversal',
                    (float) $saleLine['saleQuantity'],
                    0,
                    (float) $updatedProduct['itemQuantity'],
                    isset($updatedProduct['itemStockPrice']) ? (float) $updatedProduct['itemStockPrice'] : null,
                    'receipt',
                    $receiptNumber,
                    'RS-' . $receiptNumber,
                    $userId
                );
            }

            $cancelledAt = date('Y-m-d H:i:s');
            $cancelSalesBuilder = $db->table('sales')
                ->where('saleOwner', $userId)
                ->where('SR_ID', $receiptNumber)
                ->groupStart()
                    ->where('saleStatus <>', 'cancelled')
                    ->orWhere('saleStatus IS NULL', null, false)
                ->groupEnd();
            if ($branchId !== null) {
                $cancelSalesBuilder->where('branchId', $branchId);
            }

            if (!$cancelSalesBuilder->update([
                'saleStatus' => 'cancelled',
                'cancelledAt' => $cancelledAt,
                'cancelledBy' => $userId,
            ])) {
                throw new RuntimeException('Sale rows could not be marked as cancelled.');
            }

            $this->receiptModel->update($receiptNumber, [
                'receiptStatus' => 'cancelled',
                'cancelledAt' => $cancelledAt,
                'cancelledBy' => $userId,
            ]);

            if ($db->transStatus() === false) {
                throw new RuntimeException('Sale cancellation status update failed.');
            }

            $this->auditLog->record(
                'sale.cancelled',
                'receipt',
                $receiptNumber,
                ['sales' => $sales, 'debts' => $debts],
                null,
                $userId,
                $branchId ? (int) $branchId : null
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Sale cancellation transaction failed.');
            }

            $db->transCommit();

            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'sale-deleted', [
                'saleId' => $receiptNumber,
                'message' => 'Sale deleted'
            ]);

            return $this->respond([
                'status' => true,
                'error' => null,
                'message' => 'Sales cancelled successfully'
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Sale cancellation failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'SaleCancellationFailed',
                'message' => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Update a record in the sales table
     *
     * @return mixed
     */
    public function createDebt($debtData=null)
    {
        $userId = (int) auth()->id();
        $branchId = $this->branchContext->getEffectiveBranchId();
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond(['status' => false, 'message' => 'Only POST requests can create debts.'], 405);
        }

        $productId = (int) $this->request->getVar('indebtItemId');
        $quantity = (float) $this->request->getVar('quantityDebted');
        $initialDeposit = (float) $this->request->getVar('initialDeposit');
        $customerId = (int) $this->request->getVar('custId');
        $inventoryItem = $this->inventoryModel->find($productId);
        $customer = $customerId ? $this->customerModel->find($customerId) : null;

        if ($productId <= 0 || $quantity <= 0 || $initialDeposit < 0 || $customerId <= 0) {
            return $this->respond(['status' => false, 'message' => 'Product, customer, quantity, and deposit must be valid.'], 400);
        }
        if (!$inventoryItem || (int) ($inventoryItem['branchId'] ?? 0) !== $branchId) {
            return $this->respond(['status' => false, 'message' => 'Selected product does not belong to the active branch.'], 403);
        }
        if (!$customer || (int) ($customer['branchId'] ?? 0) !== $branchId) {
            return $this->respond(['status' => false, 'message' => 'Selected customer does not belong to the active branch.'], 403);
        }

        $minimumPrice = (float) ($inventoryItem['itemLeastPrice'] ?? 0);
        $submittedPrice = is_numeric($this->request->getVar('atPrice')) ? (float) $this->request->getVar('atPrice') : $minimumPrice;
        if ($submittedPrice < $minimumPrice) {
            return $this->respond(['status' => false, 'message' => 'Debt sale price cannot be below the configured minimum price.'], 400);
        }

        $totalAmount = round($submittedPrice * $quantity, 2);
        if ($initialDeposit > $totalAmount) {
            return $this->respond(['status' => false, 'message' => 'Initial deposit cannot exceed the calculated debt total.'], 400);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            $timeStamp = uniqid('DBT-', true);
            $receiptNo = $this->receiptModel->insert([
                'branchId' => $branchId,
                'createdBy' => $userId,
                'timeStamp' => $timeStamp,
                'discount' => 0,
                'dueAmount' => $totalAmount - $initialDeposit,
                'moreInfo' => 'Debt sale',
                'paymentMethod' => 'credit',
                'amountPaid' => $initialDeposit,
                'receiptStatus' => 'completed',
            ], true);

            if (!$receiptNo) {
                throw new RuntimeException('Debt receipt could not be created.');
            }

            $debtId = $this->indebtModel->insert([
                'branchId' => $branchId,
                'indebtItemId' => $productId,
                'indebtOwner' => $userId,
                'quantityDebted' => $quantity,
                'atPrice' => $submittedPrice,
                'initialDeposit' => $initialDeposit,
                'totalAmount' => $totalAmount,
                'endDate' => $this->request->getVar('endDate'),
                'custId' => $customerId,
                'SR_ID' => $receiptNo,
            ], true);

            if (!$debtId) {
                throw new RuntimeException('Debt record could not be created.');
            }

            $updated = $db->table('inventory')
                ->where('branchId', $branchId)
                ->where('itemId', $productId)
                ->where('itemQuantity >=', $quantity)
                ->set('itemQuantity', 'itemQuantity - ' . $quantity, false)
                ->update();

            if (!$updated || $db->affectedRows() !== 1) {
                throw new RuntimeException('Insufficient stock for this debt sale.');
            }

            $updatedProduct = $this->inventoryModel->find($productId);
            $this->stockLedger->recordProductMovement(
                $branchId,
                $productId,
                'debt_sale',
                0,
                $quantity,
                (float) $updatedProduct['itemQuantity'],
                isset($inventoryItem['itemStockPrice']) ? (float) $inventoryItem['itemStockPrice'] : null,
                'debt',
                $debtId,
                (string) $timeStamp,
                $userId
            );

            $this->recordStat($productId, 'addDebt', null);
            $this->auditLog->record(
                'debt.created',
                'indebt',
                $debtId,
                null,
                [
                    'debtId' => $debtId,
                    'receiptNo' => $receiptNo,
                    'productId' => $productId,
                    'quantity' => $quantity,
                    'totalAmount' => $totalAmount,
                    'initialDeposit' => $initialDeposit,
                ],
                $userId,
                $branchId
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Debt transaction failed.');
            }

            $db->transCommit();

            return $this->respond([
                'status' => true,
                'error' => null,
                'receiptNumber' => $receiptNo,
                'debtId' => $debtId,
                'calculatedTotal' => $totalAmount,
                'message' => 'Your item(s) have been added to your debts.'
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Debt creation failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'DebtTransactionFailed',
                'message' => $e->getMessage()
            ], 409);
        }
    }

    // Handle debt payments

    public function payDebt()
    {
        $userId = (int) auth()->id();
        $branchId = $this->branchContext->getEffectiveBranchId();
        $debtId = $this->request->getVar('transactionId');
        $newPay = (float) $this->request->getVar('amountPaid');

        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        $debt = $this->indebtModel->find($debtId);
        if (!$this->branchContext->recordMatchesCurrentBranch($debt)) {
            return $this->respond(['status' => false, 'message' => 'This debt record is outside your current branch scope.'], 403);
        }
        if ($newPay <= 0) {
            return $this->respond(['status' => false, 'message' => 'Payment amount must be positive.'], 400);
        }

        $oldPay = (float) $debt['initialDeposit'];
        $totalPay = (float) $debt['totalAmount'];
        $saleItemId =  $debt['indebtItemId'];
        $saleQty = (float) $debt['quantityDebted'];
        $custId = $debt['custId'];
        $updatedPay = $oldPay + $newPay;

        if ($updatedPay > $totalPay) {
            return $this->respond([
                'status' => false,
                'error' => 'OverPayment',
                'message' => 'Cash paid exceeds the outstanding amount.'
            ], 400);
        }

        $db = db_connect();

        try {
            $db->transBegin();

            $paymentUpdate = $this->indebtModel
                ->where('indebtId', $debtId)
                ->where('initialDeposit', $oldPay)
                ->set('initialDeposit', $updatedPay)
                ->update();

            if (!$paymentUpdate || $db->affectedRows() !== 1) {
                throw new RuntimeException('Debt payment could not be applied. The debt may have changed; refresh and try again.');
            }

            $paymentId = $this->debtTrackModel->insert([
                'debtId' => $debtId,
                'indebtOwner' => $userId,
                'amountPaid' => $newPay,
            ], true);

            if (!$paymentId) {
                throw new RuntimeException('Debt payment history could not be recorded.');
            }

            $isPaidFully = false;
            if (round($totalPay, 2) === round($updatedPay, 2)) {
                if ($saleQty <= 0) {
                    throw new RuntimeException('Debt quantity is invalid.');
                }

                $unitPrice = round($totalPay / $saleQty, 2);
                $saleProduct = $this->inventoryModel->find($saleItemId);
                $unitCostAtSale = isset($saleProduct['itemStockPrice']) ? (float) $saleProduct['itemStockPrice'] : null;
                $saleSaved = $this->salesModel->insert([
                    'branchId' => $branchId,
                    'saleItemId' => $saleItemId,
                    'saleOwner' => $userId,
                    'SR_ID' => $debt['SR_ID'] ?? null,
                    'salePrice' => $unitPrice,
                    'unitCostAtSale' => $unitCostAtSale,
                    'lineCostAtSale' => $unitCostAtSale === null ? null : round($unitCostAtSale * $saleQty, 2),
                    'saleQuantity' => $saleQty,
                    'custId' => $custId,
                    'saleStatus' => 'completed',
                ], true);

                if (!$saleSaved) {
                    throw new RuntimeException('Fully paid debt could not be converted to a sale.');
                }

                $this->recordStat(null, 'saleStock', 1);
                $isPaidFully = true;
            }

            $this->auditLog->record(
                'debt.payment_recorded',
                'indebt',
                $debtId,
                $debt,
                [
                    'debtId' => $debtId,
                    'paymentId' => $paymentId,
                    'amountPaid' => $newPay,
                    'totalPaid' => $updatedPay,
                    'isPaidFully' => $isPaidFully,
                ],
                $userId,
                $branchId ? (int) $branchId : null
            );

            if ($db->transStatus() === false) {
                throw new RuntimeException('Debt payment transaction failed.');
            }

            $db->transCommit();

            return $this->respond([
                'status' => true,
                'error' => null,
                'message' => 'Payment fulfilled.',
                'isPaidFully' => $isPaidFully,
                'remainingBalance' => round($totalPay - $updatedPay, 2),
            ]);
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Debt payment failed: ' . $e->getMessage());

            return $this->respond([
                'status' => false,
                'error' => 'PaymentFailed',
                'message' => $e->getMessage()
            ], 409);
        }
    }

    /**
     * Delete the designated resource object from the model
     *
     * @return mixed
     */
    public function delete($id = null)
    {
        //delete stock item
        $data = $this->inventoryModel->find($id);

        if ($data && $this->branchContext->recordMatchesCurrentBranch($data)) {
            $del_stock_item = $this->inventoryModel->delete($id);

            $historyData = [
                'historyItemId' => $id,
                'historyAction' => 'Deleted an item',
                'historyDetails' => ''
            ];

            if ($del_stock_item) {
                // $saveHistoryData = $this->historyModel->save($historyData);

                // if(!$saveHistoryData){
                //     $response = [
                //         'status' => false,
                //         'error' => 'historyDeleteFailed',
                //         'Messages' => 'Fail!! Your action history was not recorded. But this is OK though you should report it.'
                //     ];
                //     return $this->respond($response);
                //     exit();
                // }
                // in case upload & it's history were recorded ok 
                // else{

                $payload = [
                'saleId' => $id,
                'message' => 'Item deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'item-deleted', $payload);
                
                $response = [
                    'status' => true,
                    'error' => null,
                    // 'Messages' => 'Action history has been recorded successfully.'
                    'Messages' => 'Your Item has been deleted successfully!.'
                ];
                return $this->respond($response);
                // }    
            } else {
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Item selected could not be deleted, try again in 10 minutes'
                ];
                return $this->respond($response);
            }
        }
        // in case there is no matching item
        else {
            return $this->nostockdata();
        }
    }

    /**
     * Add the designated resource object as a stat to the model
     *
     * @return mixed
     */

    private function recordStat($item_id = NULL, $action = NULL, $limit = NULL)
    {
        $userId = auth()->id();
        $branchId = $this->branchContext->getEffectiveBranchId();
        $itemStockWorth = 0;
        $itemQty = 0;
        $statId = null;
        if ($item_id === NULL && ($action === 'update' || $action === 'createStock')) {
            if ($item_id === Null) {
                $this->inventoryModel->orderBy('itemId', 'DESC');
                if ($branchId !== null) {
                    $this->inventoryModel->where('branchId', $branchId);
                }
                $fetchedData = $this->inventoryModel->findAll(1);
                $item_id = $fetchedData[0]['itemId'];
                $itemStockWorth = $fetchedData[0]['itemQuantity'] * $fetchedData[0]['itemStockPrice'];
                $itemQty = $fetchedData[0]['itemQuantity'];
            } else {
                $fetchedData = $this->inventoryModel->find($item_id);
                $itemStockWorth = $fetchedData['itemQuantity'] * $fetchedData['itemStockPrice'];
                $itemQty = $fetchedData['itemQuantity'];
                $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll();
                $statId =  $stat[0]['statId'];
            }

            $statData = [
                'branchId' => $branchId,
                'statItemId' => $item_id,
                'busId' => $userId,
                'statItemStock' => $itemQty,
                'statItemStockWorth' => $itemStockWorth,
                'statItemSales' => 0,
                'statItemSalesWorth' => 0,
                'statItemIndebt' => 0,
                'statItemIndebtWorth' => 0
            ];

            $recordStatData = $this->statisticsModel->save($statData);

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }
        //update stock stats 
        if ($action === 'addStock') {
            $this->stockModel->orderBy('stockId', 'DESC');
            if ($branchId !== null) {
                $this->stockModel->where('branchId', $branchId);
            }
            $fetchedStock = $this->stockModel->findAll(1);
            $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll(1);
            // $itemStockWorth = $stat[0]['statItemStockWorth'] + ($fetchedStock[0]['stockItemQuantity'] * $fetchedStock[0]['stockItemPrice']);
            $itemStockWorth = 0;
            $itemQty =  $stat[0]['statItemStock'] + $fetchedStock[0]['stockItemQuantity'];
            $statId =  $stat[0]['statId'];

            $statData = [
                'statItemStock' => $itemQty,
                'statItemStockWorth' => $itemStockWorth,
                // 'statItemSales' => 0,
                // 'statItemSalesWorth' => 0,
                // 'statItemIndebt' => 0,
                // 'statItemIndebtWorth' => 0
            ];
            $this->statisticsModel->set($statData);
            $this->statisticsModel->where('statId', $statId);
            $recordStatData = $this->statisticsModel->update();

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }

        //update debt stats 
        if ($action === 'addDebt') {
            $this->indebtModel->orderBy('indebtId', 'DESC');
            if ($branchId !== null) {
                $this->indebtModel->where('branchId', $branchId);
            }
            $fetchedDebt = $this->indebtModel->findAll(1);
            $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll(1);
            $itemStockWorth = $stat[0]['statItemIndebtWorth'] + ($fetchedDebt[0]['quantityDebted'] * $fetchedDebt[0]['atPrice']);
            $statItemIndebt =  $stat[0]['statItemIndebt'] + $fetchedDebt[0]['quantityDebted'];
            $statId =  $stat[0]['statId'];

            $statData = [
                // 'statItemSales' => 0,
                // 'statItemSalesWorth' => 0,
                'statItemIndebt' => $statItemIndebt,
                'statItemIndebtWorth' => $itemStockWorth
            ];
            $this->statisticsModel->set($statData);
            $this->statisticsModel->where('statId', $statId);
            $recordStatData = $this->statisticsModel->update();

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }

        //update item sales stats 
        if ($action === 'saleStock') {
            $userId = auth()->id();
            $this->salesModel->orderBy('saleId', 'DESC');
            $this->salesModel->where('saleOwner', $userId);
            $this->salesModel
                ->groupStart()
                    ->where('saleStatus <>', 'cancelled')
                    ->orWhere('saleStatus IS NULL', null, false)
                ->groupEnd();
            if ($branchId !== null) {
                $this->salesModel->where('branchId', $branchId);
            }
            $fetchedSales = $this->salesModel->findAll($limit);
            foreach ($fetchedSales as $sale => $item) {
                $stat = $this->statisticsModel->where('statItemId',  $item['saleItemId'])->findAll(1);
                $itemSaleWorth = $stat[0]['statItemSalesWorth'] + ($item['saleQuantity'] * $item['salePrice']);
                $statItemSale =  $stat[0]['statItemSales'] + $item['saleQuantity'];
                $statId =  $stat[0]['statId'];

                $statData = [
                    'statItemSales' =>  $statItemSale,
                    'statItemSalesWorth' => $itemSaleWorth,
                ];
                $this->statisticsModel->set($statData);
                $this->statisticsModel->where('statId', $statId);
                $recordStatData = $this->statisticsModel->update();

                if (!$recordStatData) {
                    $response = [
                        'status' => false,
                        'error' => 'recordFail',
                        'message' => 'Your records have not been fully kept.'
                    ];

                    return $this->respond($response);
                }
                // in case stat data was recorded successfully
                else {
                    $response = [
                        'status' => true,
                        'error' => null,
                        'message' => 'Your statistics records have successfully been kept.'
                    ];

                    return $this->respond($response);
                }
            }
        }
    }

    // Example controller method for image upload
    public function uploadProfileImage()
    {
        if (strtolower($this->request->getMethod()) === 'post') {
            $file = $this->request->getFile('file'); // Get the uploaded file

            if ($file->isValid() && $file->getClientMimeType() === 'image/jpeg') {
                // Save the file to a designated folder (e.g., 'uploads')
                $newName = $file->getRandomName();
                $file->move(ROOTPATH . 'public/uploads', $newName);

                // Update the user's profile image path in the database
                // (You'll need to implement this part based on your database schema)
                // Example: $this->profile_model->updateProfileImage($user_id, $newName);

                return redirect()->to('/profile')->with('success', 'Profile image uploaded successfully.');
            } else {
                return redirect()->back()->with('error', 'Invalid file format. Please upload a JPEG image.');
            }
        }

        // Load your view here
        // Example: return view('profile/upload_form');
    }


    private function validateStockEntries($page)
    {
        $lowercase = strtolower($page);
        if ($lowercase == 'entry') {
            return $this->validate([
                'item_name' => [
                    'rules' => 'required|max_length[255]|trim|min_length[3]|is_unique[stock.itemName]',
                    'label' => 'Item Name'
                ],
                'item_category' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|trim|required|greater_than[0]',
                    'label' => 'Item Category'
                ],
                'item_model' => [
                    'rules' => 'permit_empty|max_length[50]|trim|min_length[2]',
                    'label' => 'Item Model'
                ],
                'item_sku' => [
                    'rules' => 'permit_empty|max_length[80]|trim',
                    'label' => 'Item SKU'
                ],
                'item_barcode' => [
                    'rules' => 'permit_empty|max_length[120]|trim',
                    'label' => 'Item Barcode'
                ],
                'item_brand' => [
                    'rules' => 'permit_empty|max_length[120]|trim',
                    'label' => 'Item Brand'
                ],
                'item_product_type' => [
                    'rules' => 'permit_empty|in_list[purchased,produced,service]',
                    'label' => 'Product Type'
                ],
                'item_unit' => [
                    'rules' => 'permit_empty|max_length[30]|trim',
                    'label' => 'Unit'
                ],
                'item_supplier' => [
                    'rules' => 'permit_empty|max_length[150]|trim',
                    'label' => 'Supplier'
                ],
                'item_reorder_level' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Reorder Level'
                ],
                'item_quality' => [
                    'rules' => 'required|max_length[50]|trim',
                    'label' => 'Item Quality'
                ],
                'item_quantity' => [
                    'rules' => 'required|numeric|max_length[11]|trim|min_length[1]|greater_than[0]',
                    'label' => 'Item Quantity'
                ],
                'item_condition' => [
                    'rules' => 'required|max_length[50]|min_length[3]|trim|alpha_space',
                    'label' => 'Item Condition'
                ],
                'item_size' => [
                    'rules' => 'permit_empty|max_length[50]|trim|min_length[1]',
                    'label' => 'Item Size'
                ],
                // 'item_buy_price' => [
                //     'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[49]',
                //     'label' => 'Item Buy Price'
                // ],
                // 'item_sale_price' => [
                //     'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[500]',
                //     'label' => 'Item Sale Price'
                // ],
                'item_stock_price' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Cost Price'
                ],
                'item_wholesale_price' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Wholesale Price'
                ],
                'item_notes' => [
                    'rules' => 'permit_empty|max_length[1500]|min_length[3]|trim',
                    'label' => 'Item Notes'
                ],
                'item_owner' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|greater_than[0]|trim',
                    'label' => 'Item Owner'
                ]

            ]);
        } elseif ($lowercase === 'updateitem') {
            return $this->validate([
                'item_name' => [
                    'rules' => 'required|max_length[255]|trim|min_length[3]',
                    'label' => 'Item Name'
                ],
                // 'item_name' => [
                //     'rules' => 'required|max_length[255]|trim|min_length[3]|is_unique[stock.itemName]',
                //     'label' => 'Item Name'
                // ],
                'item_category' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|trim|required|greater_than[0]',
                    'label' => 'Item Category'
                ],
                'item_model' => [
                    'rules' => 'permit_empty|max_length[50]|trim|min_length[2]',
                    'label' => 'Item Model'
                ],
                'item_sku' => [
                    'rules' => 'permit_empty|max_length[80]|trim',
                    'label' => 'Item SKU'
                ],
                'item_barcode' => [
                    'rules' => 'permit_empty|max_length[120]|trim',
                    'label' => 'Item Barcode'
                ],
                'item_brand' => [
                    'rules' => 'permit_empty|max_length[120]|trim',
                    'label' => 'Item Brand'
                ],
                'item_product_type' => [
                    'rules' => 'permit_empty|in_list[purchased,produced,service]',
                    'label' => 'Product Type'
                ],
                'item_unit' => [
                    'rules' => 'permit_empty|max_length[30]|trim',
                    'label' => 'Unit'
                ],
                'item_supplier' => [
                    'rules' => 'permit_empty|max_length[150]|trim',
                    'label' => 'Supplier'
                ],
                'item_reorder_level' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Reorder Level'
                ],
                'item_quality' => [
                    'rules' => 'permit_empty|max_length[50]|trim',
                    'label' => 'Item Quality'
                ],
                'item_quantity' => [
                    'rules' => 'permit_empty|numeric|max_length[11]|trim|min_length[1]',
                    'label' => 'Item Quantity'
                ],
                'item_condition' => [
                    'rules' => 'permit_empty|max_length[50]|trim|alpha_space',
                    'label' => 'Item Condition'
                ],
                'item_size' => [
                    'rules' => 'permit_empty|max_length[50]|trim|min_length[1]',
                    'label' => 'Item Size'
                ],
                'item_min_price' => [
                    'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[49]',
                    'label' => 'Retail Price'
                ],
                'item_stock_price' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Cost Price'
                ],
                'item_wholesale_price' => [
                    'rules' => 'permit_empty|numeric|greater_than_equal_to[0]',
                    'label' => 'Wholesale Price'
                ],
                'item_notes' => [
                    'rules' => 'permit_empty|max_length[1500]|min_length[3]|trim',
                    'label' => 'Item Notes'
                ]
            ]);
        } elseif ($lowercase === 'sellitem') {
            return $this->validate([
                'sale_items' => [
                    'rules' => 'required|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item being sold'
                ],
            ]);
        } elseif ($lowercase === 'debtitem') {
            return $this->validate([
                'debt_item' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item being sold'
                ],
                'debt_owner' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Stock Owner'
                ],
                'debt_quantity' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item quantity being sold',
                ],
                'debt_price' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item Price'
                ]
            ]);
        } else {
            echo "Nothing";
        }
    }

    private function normalizeBranchId($branchId)
    {
        $branchId = trim((string) $branchId);
        return $branchId === '' ? null : (int) $branchId;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function optionalNumber($value, $fallback)
    {
        return $this->secureDecimal($value, $fallback);
    }

    private function storeUploadedImage(string $field, string $folder): ?string
    {
        $file = $this->request->getFile($field);

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$file->isValid()) {
            throw new InvalidArgumentException('The uploaded image is not valid.');
        }

        if ($file->getSize() > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('Product images must be 3MB or smaller.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw new InvalidArgumentException('Only JPEG, PNG, and WEBP images are allowed.');
        }

        $targetDirectory = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Could not prepare the image upload folder.');
        }

        $newName = $file->getRandomName();
        $file->move($targetDirectory, $newName);

        return 'uploads/' . $folder . '/' . $newName;
    }
}
